<?php
/**
 * Cookie Manager - PromoTrack Server
 * Manages: _fbp, _fbc, _gcl_aw, _gcl_gs, _ga, external_id
 * Captures fbclid/gclid/wbraid from URL and stores as first-party cookies
 * Generates persistent external_id for cross-device matching
 */

if (!defined('ABSPATH')) exit;

class PTS_Cookie_Manager {

    private $s;
    private $duration;

    public function __construct() {
        $this->s = get_option('pts_settings', []);
        $this->duration = ($this->s['cookie_duration'] ?? 390) * DAY_IN_SECONDS;

        add_action('init', [$this, 'manage_cookies'], 1);
        add_action('wp_head', [$this, 'inject_cookie_js'], 1);
    }

    /**
     * Server-side cookie management
     */
    public function manage_cookies() {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) return;

        $domain = $this->cookie_domain();
        $secure = is_ssl();
        $expire = time() + $this->duration;

        // external_id — persistent unique visitor identifier
        if (!empty($this->s['cookie_external_id']) && !isset($_COOKIE['_pts_eid'])) {
            $eid = $this->generate_external_id();
            setcookie('_pts_eid', $eid, ['expires' => $expire, 'path' => '/', 'domain' => $domain, 'secure' => $secure, 'httponly' => false, 'samesite' => 'Lax']);
            $_COOKIE['_pts_eid'] = $eid;
        }

        // Capture gclid from URL → _gcl_aw
        if (!empty($this->s['cookie_gcl_aw']) && isset($_GET['gclid'])) {
            $gclid = sanitize_text_field($_GET['gclid']);
            $val = 'GCL.' . time() . '.' . $gclid;
            setcookie('_gcl_aw', $val, ['expires' => $expire, 'path' => '/', 'domain' => $domain, 'secure' => $secure, 'httponly' => false, 'samesite' => 'Lax']);
            $_COOKIE['_gcl_aw'] = $val;
        }

        // Capture gclid → _gcl_gs (secondary)
        if (!empty($this->s['cookie_gcl_gs']) && isset($_GET['gclsrc'])) {
            $gs = sanitize_text_field($_GET['gclsrc']);
            setcookie('_gcl_gs', $gs, ['expires' => $expire, 'path' => '/', 'domain' => $domain, 'secure' => $secure, 'httponly' => false, 'samesite' => 'Lax']);
        }

        // Capture wbraid
        if (isset($_GET['wbraid'])) {
            $wbraid = sanitize_text_field($_GET['wbraid']);
            setcookie('_gcl_wb', $wbraid, ['expires' => $expire, 'path' => '/', 'domain' => $domain, 'secure' => $secure, 'httponly' => false, 'samesite' => 'Lax']);
            $_COOKIE['_gcl_wb'] = $wbraid;
        }

        // Capture fbclid from URL → _fbc
        if (!empty($this->s['cookie_fbc']) && isset($_GET['fbclid'])) {
            $fbclid = sanitize_text_field($_GET['fbclid']);
            $fbc = 'fb.1.' . (time() * 1000) . '.' . $fbclid;
            setcookie('_fbc', $fbc, ['expires' => $expire, 'path' => '/', 'domain' => $domain, 'secure' => $secure, 'httponly' => false, 'samesite' => 'Lax']);
            $_COOKIE['_fbc'] = $fbc;
        }

        // _fbp — generate if not exists (mimics FB pixel behavior)
        if (!empty($this->s['cookie_fbp']) && !isset($_COOKIE['_fbp'])) {
            $fbp = 'fb.1.' . (time() * 1000) . '.' . rand(1000000000, 9999999999);
            setcookie('_fbp', $fbp, ['expires' => $expire, 'path' => '/', 'domain' => $domain, 'secure' => $secure, 'httponly' => false, 'samesite' => 'Lax']);
            $_COOKIE['_fbp'] = $fbp;
        }

        // Extend _ga cookie lifetime
        if (!empty($this->s['cookie_ga']) && isset($_COOKIE['_ga'])) {
            setcookie('_ga', $_COOKIE['_ga'], ['expires' => $expire, 'path' => '/', 'domain' => $domain, 'secure' => $secure, 'httponly' => false, 'samesite' => 'Lax']);
        }
    }

    /**
     * Client-side JS to capture/extend cookies the server can't reach
     */
    public function inject_cookie_js() {
        ?>
        <script>
        (function(){
            var d = <?php echo (int)($this->s['cookie_duration'] ?? 390); ?>;
            var exp = new Date(Date.now() + d*86400000).toUTCString();
            var domain = '<?php echo esc_js($this->cookie_domain()); ?>';

            function setCk(name, val) {
                document.cookie = name + '=' + val + ';expires=' + exp + ';path=/;domain=' + domain + ';SameSite=Lax<?php echo is_ssl() ? ';Secure' : ''; ?>';
            }

            // Capture fbclid if not already in _fbc
            var params = new URLSearchParams(location.search);
            <?php if (!empty($this->s['cookie_fbc'])) : ?>
            if (params.has('fbclid') && !document.cookie.match(/_fbc=/)) {
                setCk('_fbc', 'fb.1.' + Date.now() + '.' + params.get('fbclid'));
            }
            <?php endif; ?>

            <?php if (!empty($this->s['cookie_gcl_aw'])) : ?>
            if (params.has('gclid') && !document.cookie.match(/_gcl_aw=/)) {
                setCk('_gcl_aw', 'GCL.' + Math.floor(Date.now()/1000) + '.' + params.get('gclid'));
            }
            <?php endif; ?>

            // Extend _fbp if it exists (refresh expiry)
            <?php if (!empty($this->s['cookie_fbp'])) : ?>
            var fbpMatch = document.cookie.match(/_fbp=([^;]+)/);
            if (fbpMatch) setCk('_fbp', fbpMatch[1]);
            <?php endif; ?>
        })();
        </script>
        <?php
    }

    /**
     * PUBLIC: Get all cookie values for event payloads
     */
    public static function get_cookies() {
        return [
            'fbp'         => $_COOKIE['_fbp'] ?? '',
            'fbc'         => $_COOKIE['_fbc'] ?? '',
            'external_id' => $_COOKIE['_pts_eid'] ?? '',
            'gcl_aw'      => $_COOKIE['_gcl_aw'] ?? '',
            'gcl_wb'      => $_COOKIE['_gcl_wb'] ?? '',
            'ga'          => $_COOKIE['_ga'] ?? '',
            'client_id'   => self::extract_ga_client_id(),
            'gclid'       => self::extract_gclid(),
            'fbclid'      => self::extract_fbclid(),
        ];
    }

    /**
     * Extract GA client_id from _ga cookie
     */
    public static function extract_ga_client_id() {
        if (!isset($_COOKIE['_ga'])) return '';
        $parts = explode('.', $_COOKIE['_ga']);
        return count($parts) >= 4 ? $parts[2] . '.' . $parts[3] : '';
    }

    /**
     * Extract gclid from _gcl_aw cookie
     */
    public static function extract_gclid() {
        if (!isset($_COOKIE['_gcl_aw'])) return '';
        $parts = explode('.', $_COOKIE['_gcl_aw']);
        return count($parts) >= 3 ? $parts[2] : '';
    }

    /**
     * Extract fbclid from _fbc cookie
     */
    public static function extract_fbclid() {
        if (!isset($_COOKIE['_fbc'])) return '';
        $parts = explode('.', $_COOKIE['_fbc']);
        return count($parts) >= 4 ? $parts[3] : '';
    }

    private function generate_external_id() {
        return bin2hex(random_bytes(16));
    }

    private function cookie_domain() {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $host = preg_replace('/:\d+$/', '', $host);
        $parts = explode('.', $host);
        return count($parts) > 2 ? '.' . implode('.', array_slice($parts, -2)) : '.' . $host;
    }
}
