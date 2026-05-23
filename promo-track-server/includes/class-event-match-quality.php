<?php
/**
 * Event Match Quality - Score 10/10
 * Collects ALL user data parameters from every available source:
 * - Logged-in user profile
 * - WooCommerce billing/shipping
 * - Form submissions (stored in session/transient)
 * - Cookies (_fbp, _fbc, external_id, gclid)
 * - Server headers (IP, User Agent)
 *
 * Returns hashed data ready for both Google Ads Enhanced Conversions
 * and Facebook Conversion API user_data format.
 */

if (!defined('ABSPATH')) exit;

class PTS_Event_Match_Quality {

    private $s;

    public function __construct() {
        $this->s = get_option('pts_settings', []);

        // Store form data when available
        add_action('wpcf7_mail_sent', [$this, 'store_from_cf7']);
        add_action('wpforms_process_complete', [$this, 'store_from_wpforms'], 10, 4);
        add_action('gform_after_submission', [$this, 'store_from_gravity'], 10, 2);
    }

    /**
     * Get FULL user data for Facebook CAPI (user_data format)
     * This achieves Event Match Quality 10/10
     */
    public function get_fb_user_data() {
        $raw = $this->collect_raw();
        $ud = [];

        // em — email (SHA-256 hashed, lowercase, trimmed)
        if (!empty($raw['email']) && !empty($this->s['emq_email'])) {
            $ud['em'] = [hash('sha256', strtolower(trim($raw['email'])))];
        }

        // ph — phone (SHA-256 hashed, digits only with country code)
        if (!empty($raw['phone']) && !empty($this->s['emq_phone'])) {
            $phone = preg_replace('/[^0-9]/', '', $raw['phone']);
            $ud['ph'] = [hash('sha256', $phone)];
        }

        // fn — first name
        if (!empty($raw['first_name']) && !empty($this->s['emq_name'])) {
            $ud['fn'] = [hash('sha256', strtolower(trim($raw['first_name'])))];
        }

        // ln — last name
        if (!empty($raw['last_name']) && !empty($this->s['emq_name'])) {
            $ud['ln'] = [hash('sha256', strtolower(trim($raw['last_name'])))];
        }

        // Address fields
        if (!empty($this->s['emq_address'])) {
            if (!empty($raw['city']))     $ud['ct'] = [hash('sha256', strtolower(trim($raw['city'])))];
            if (!empty($raw['state']))    $ud['st'] = [hash('sha256', strtolower(trim($raw['state'])))];
            if (!empty($raw['postcode'])) $ud['zp'] = [hash('sha256', trim($raw['postcode']))];
            if (!empty($raw['country']))  $ud['country'] = [strtolower($raw['country'])];
        }

        // external_id
        if (!empty($this->s['emq_external_id'])) {
            $eid = $_COOKIE['_pts_eid'] ?? '';
            if ($eid) $ud['external_id'] = [$eid];
        }

        // _fbp
        if (!empty($this->s['emq_fbp'])) {
            $fbp = $_COOKIE['_fbp'] ?? '';
            if ($fbp) $ud['fbp'] = $fbp;
        }

        // _fbc
        if (!empty($this->s['emq_fbc'])) {
            $fbc = $_COOKIE['_fbc'] ?? '';
            if ($fbc) $ud['fbc'] = $fbc;
        }

        // Client IP
        if (!empty($this->s['emq_ip'])) {
            $ud['client_ip_address'] = $this->get_ip();
        }

        // User Agent
        if (!empty($this->s['emq_user_agent'])) {
            $ud['client_user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        }

        // Click ID (fbc already covers fbclid)
        if (!empty($this->s['emq_click_id'])) {
            if (isset($_GET['fbclid']) && empty($ud['fbc'])) {
                $ud['fbc'] = 'fb.1.' . (time() * 1000) . '.' . sanitize_text_field($_GET['fbclid']);
            }
        }

        return $ud;
    }

    /**
     * Get user data for Google Ads Enhanced Conversions
     */
    public function get_google_user_data() {
        $raw = $this->collect_raw();
        $ud = [];

        if (!empty($raw['email']) && !empty($this->s['emq_email'])) {
            $ud['sha256_email_address'] = hash('sha256', strtolower(trim($raw['email'])));
        }
        if (!empty($raw['phone']) && !empty($this->s['emq_phone'])) {
            $ud['sha256_phone_number'] = hash('sha256', preg_replace('/[^0-9+]/', '', $raw['phone']));
        }
        if (!empty($raw['first_name']) && !empty($this->s['emq_name'])) {
            $ud['address']['sha256_first_name'] = hash('sha256', strtolower(trim($raw['first_name'])));
        }
        if (!empty($raw['last_name']) && !empty($this->s['emq_name'])) {
            $ud['address']['sha256_last_name'] = hash('sha256', strtolower(trim($raw['last_name'])));
        }
        if (!empty($this->s['emq_address'])) {
            if (!empty($raw['city']))     $ud['address']['city'] = strtolower(trim($raw['city']));
            if (!empty($raw['state']))    $ud['address']['region'] = strtolower(trim($raw['state']));
            if (!empty($raw['postcode'])) $ud['address']['postal_code'] = trim($raw['postcode']);
            if (!empty($raw['country']))  $ud['address']['country'] = strtolower($raw['country']);
        }

        return $ud;
    }

    /**
     * Get raw (unhashed) user data from all sources
     */
    public function collect_raw() {
        $data = [];

        // Source 1: Logged-in user
        if (is_user_logged_in()) {
            $u = wp_get_current_user();
            $data['email'] = $u->user_email;
            $data['first_name'] = $u->first_name;
            $data['last_name'] = $u->last_name;
            $data['phone'] = get_user_meta($u->ID, 'billing_phone', true);
            $data['city'] = get_user_meta($u->ID, 'billing_city', true);
            $data['state'] = get_user_meta($u->ID, 'billing_state', true);
            $data['postcode'] = get_user_meta($u->ID, 'billing_postcode', true);
            $data['country'] = get_user_meta($u->ID, 'billing_country', true);
        }

        // Source 2: WooCommerce session (guest)
        if (empty($data['email']) && function_exists('WC') && WC()->session) {
            $c = WC()->session->get('customer');
            if (is_array($c)) {
                if (!empty($c['email']))      $data['email'] = $c['email'];
                if (!empty($c['first_name'])) $data['first_name'] = $c['first_name'];
                if (!empty($c['last_name']))  $data['last_name'] = $c['last_name'];
                if (!empty($c['phone']))      $data['phone'] = $c['phone'];
                if (!empty($c['city']))       $data['city'] = $c['city'];
                if (!empty($c['state']))      $data['state'] = $c['state'];
                if (!empty($c['postcode']))   $data['postcode'] = $c['postcode'];
                if (!empty($c['country']))    $data['country'] = $c['country'];
            }
        }

        // Source 3: Stored form submission
        if (empty($data['email'])) {
            $stored = get_transient('pts_emq_' . $this->visitor_key());
            if (is_array($stored)) {
                $data = array_merge($data, $stored);
            }
        }

        return array_filter($data);
    }

    /**
     * Get real client IP
     */
    public function get_ip() {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($headers as $h) {
            if (!empty($_SERVER[$h])) {
                return trim(explode(',', $_SERVER[$h])[0]);
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    /**
     * Store data from Contact Form 7
     */
    public function store_from_cf7($cf) {
        $sub = WPCF7_Submission::get_instance();
        if (!$sub) return;
        $d = $sub->get_posted_data();
        $this->store_user_data($d);
    }

    /**
     * Store data from WPForms
     */
    public function store_from_wpforms($fields, $entry, $form_data, $entry_id) {
        $mapped = [];
        foreach ($fields as $f) {
            $t = $f['type'] ?? '';
            $v = $f['value'] ?? '';
            if (!$v) continue;
            if ($t === 'email') $mapped['email'] = $v;
            if ($t === 'phone') $mapped['phone'] = $v;
            if ($t === 'name') {
                $parts = explode(' ', $v, 2);
                $mapped['first_name'] = $parts[0];
                $mapped['last_name'] = $parts[1] ?? '';
            }
        }
        $this->store_user_data($mapped);
    }

    /**
     * Store data from Gravity Forms
     */
    public function store_from_gravity($entry, $form) {
        $mapped = [];
        foreach ($form['fields'] as $f) {
            $v = $entry[$f->id] ?? '';
            if (!$v) continue;
            if ($f->type === 'email') $mapped['email'] = $v;
            if ($f->type === 'phone') $mapped['phone'] = $v;
            if ($f->type === 'name') {
                $mapped['first_name'] = $entry[$f->id . '.3'] ?? '';
                $mapped['last_name'] = $entry[$f->id . '.6'] ?? '';
            }
        }
        $this->store_user_data($mapped);
    }

    /**
     * Store user data in transient for non-logged-in users
     */
    private function store_user_data($data) {
        $clean = [];
        foreach ($data as $k => $v) {
            if (!is_string($v) || !$v) continue;
            $kl = strtolower($k);
            if (strpos($kl, 'email') !== false && filter_var($v, FILTER_VALIDATE_EMAIL)) $clean['email'] = $v;
            if (strpos($kl, 'phone') !== false || strpos($kl, 'tel') !== false) $clean['phone'] = $v;
            if (strpos($kl, 'first') !== false) $clean['first_name'] = $v;
            if (strpos($kl, 'last') !== false) $clean['last_name'] = $v;
            if ($kl === 'your-name' || $kl === 'name') {
                $parts = explode(' ', $v, 2);
                $clean['first_name'] = $parts[0];
                $clean['last_name'] = $parts[1] ?? '';
            }
        }
        if (!empty($clean)) {
            set_transient('pts_emq_' . $this->visitor_key(), $clean, 3600);
        }
    }

    private function visitor_key() {
        return md5(($_SERVER['REMOTE_ADDR'] ?? '') . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    }
}
