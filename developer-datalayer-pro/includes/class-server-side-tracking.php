<?php
/**
 * Server-Side Tracking (sGTM)
 * Forwards events to your server-side Google Tag Manager container.
 * Handles event deduplication, enriches payloads with server context,
 * and provides a REST endpoint for client-to-server event relay.
 *
 * Requires: server_container_url in settings
 */

if (!defined('ABSPATH')) exit;

class DDL_Pro_Server_Side_Tracking {

    private $settings;
    private $server_url;
    private $sent_events = [];

    public function __construct() {
        $this->settings   = get_option('ddl_pro_settings', []);
        $this->server_url = rtrim($this->settings['server_container_url'] ?? '', '/');

        if (empty($this->server_url)) return;

        // Listen for server events (runs AFTER Conversion API to avoid duplication)
        add_action('ddl_pro_server_event', [$this, 'forward_to_sgtm'], 20, 2);

        // REST API endpoint for client-side relay
        add_action('rest_api_init', [$this, 'register_rest_endpoint']);

        // Inject sGTM transport URL for client-side hits
        add_action('wp_head', [$this, 'inject_transport_url'], 4);
    }

    /**
     * Forward server event to sGTM container
     */
    public function forward_to_sgtm($event_name, $data = []) {
        // Deduplication: skip if same event+transaction already sent this request
        $dedup_key = $event_name . '_' . ($data['transaction_id'] ?? $data['form_id'] ?? $data['booking_id'] ?? uniqid());
        if (isset($this->sent_events[$dedup_key])) return;
        $this->sent_events[$dedup_key] = true;

        $payload = $this->build_sgtm_payload($event_name, $data);
        $this->send($payload);
    }

    /**
     * Register REST endpoint: /wp-json/ddl-pro/v1/collect
     * Client JS can POST events here to be forwarded server-side
     */
    public function register_rest_endpoint() {
        register_rest_route('ddl-pro/v1', '/collect', [
            'methods'             => 'POST',
            'callback'            => [$this, 'rest_collect'],
            'permission_callback' => '__return_true', // Public endpoint
        ]);
    }

    /**
     * REST handler — relay client events through server
     */
    public function rest_collect($request) {
        $body = $request->get_json_params();
        if (empty($body) || empty($body['events'])) {
            return new WP_REST_Response(['error' => 'No events'], 400);
        }

        // Enrich with server-side data
        $body['ip_override']        = $this->get_client_ip();
        $body['user_agent']         = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $body['timestamp_micros']   = (string)(microtime(true) * 1000000);

        // Add user data if logged in
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $body['user_id'] = (string) $user->ID;
            if (empty($body['user_data'])) {
                $body['user_data'] = $this->get_hashed_user_data($user);
            }
        }

        $this->send($body);

        return new WP_REST_Response(['status' => 'ok'], 200);
    }

    /**
     * Inject transport_url for gtag.js to route hits through sGTM
     */
    public function inject_transport_url() {
        if (empty($this->server_url)) return;
        ?>
        <script>
        window.ddlServerUrl = '<?php echo esc_js($this->server_url); ?>';
        // Configure gtag transport if gtag is present
        window.addEventListener('load', function(){
            if (typeof gtag === 'function') {
                gtag('config', '<?php echo esc_js($this->settings['ga4_measurement_id'] ?? ''); ?>', {
                    'transport_url': '<?php echo esc_js($this->server_url); ?>',
                    'first_party_collection': true
                });
            }
        });
        </script>
        <?php
    }

    /**
     * Build sGTM-compatible payload
     */
    private function build_sgtm_payload($event_name, $data) {
        $client_id = $this->get_client_id();
        $session_id = $this->get_session_id();

        $payload = [
            'client_id'         => $client_id,
            'timestamp_micros'  => (string)(microtime(true) * 1000000),
            'non_personalized_ads' => false,
            'events' => [[
                'name'   => $event_name,
                'params' => $this->build_event_params($event_name, $data, $session_id),
            ]],
        ];

        // User ID
        $user_id = $data['user_id'] ?? '';
        if (!$user_id && is_user_logged_in()) {
            $user_id = (string) get_current_user_id();
        }
        if ($user_id) $payload['user_id'] = $user_id;

        // User data for enhanced conversions
        $user_data = $this->extract_user_data_from_event($data);
        if (!empty($user_data)) $payload['user_data'] = $user_data;

        // Server enrichment
        $payload['ip_override']  = $this->get_client_ip();
        $payload['user_agent']   = $_SERVER['HTTP_USER_AGENT'] ?? '';

        return $payload;
    }

    /**
     * Build event-specific params
     */
    private function build_event_params($event_name, $data, $session_id) {
        $params = [
            'session_id'           => $session_id,
            'engagement_time_msec' => '100',
            'page_location'        => home_url($_SERVER['REQUEST_URI'] ?? '/'),
            'page_title'           => wp_title('', false) ?: get_bloginfo('name'),
            'page_referrer'        => $_SERVER['HTTP_REFERER'] ?? '',
        ];

        // Event-specific data
        $event_keys = [
            'transaction_id', 'value', 'currency', 'tax', 'shipping',
            'items', 'form_id', 'form_name', 'form_plugin', 'form_type',
            'lead_type', 'booking_id', 'service_name', 'booking_value',
        ];

        foreach ($event_keys as $key) {
            if (isset($data[$key]) && $data[$key] !== '') {
                $params[$key] = $data[$key];
            }
        }

        // Ensure value is float
        if (isset($params['value'])) {
            $params['value'] = (float) $params['value'];
        }

        return $params;
    }

    /**
     * Send payload to sGTM endpoint
     */
    private function send($payload) {
        $url = $this->server_url . '/mp/collect';

        // Add measurement_id and api_secret as query params
        $mid = $this->settings['ga4_measurement_id'] ?? '';
        $secret = $this->settings['ga4_api_secret'] ?? '';

        if ($mid && $secret) {
            $url .= '?measurement_id=' . urlencode($mid) . '&api_secret=' . urlencode($secret);
        }

        wp_remote_post($url, [
            'body'        => wp_json_encode($payload),
            'headers'     => [
                'Content-Type' => 'application/json',
                'X-Forwarded-For' => $this->get_client_ip(),
                'User-Agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'DDL-Pro/1.0',
            ],
            'timeout'     => 5,
            'blocking'    => false,
            'data_format' => 'body',
        ]);
    }

    /**
     * Get client_id from _ga cookie
     */
    private function get_client_id() {
        if (isset($_COOKIE['_ga'])) {
            $parts = explode('.', $_COOKIE['_ga']);
            if (count($parts) >= 4) {
                return $parts[2] . '.' . $parts[3];
            }
        }
        // Generate fallback
        return rand(100000000, 999999999) . '.' . time();
    }

    /**
     * Get session_id from GA4 session cookie
     */
    private function get_session_id() {
        $ga4_id = str_replace('G-', '', $this->settings['ga4_measurement_id'] ?? '');
        $cookie_name = '_ga_' . $ga4_id;

        if (isset($_COOKIE[$cookie_name])) {
            $parts = explode('.', $_COOKIE[$cookie_name]);
            if (count($parts) >= 3) return $parts[2];
        }
        return (string) time();
    }

    /**
     * Get real client IP
     */
    private function get_client_ip() {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = explode(',', $_SERVER[$header])[0];
                return trim($ip);
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Extract user data from event payload
     */
    private function extract_user_data_from_event($data) {
        $ud = [];

        $email = $data['user_email'] ?? '';
        if ($email) $ud['sha256_email_address'] = [hash('sha256', strtolower(trim($email)))];

        $phone = $data['user_phone'] ?? '';
        if ($phone) $ud['sha256_phone_number'] = [hash('sha256', preg_replace('/[^0-9+]/', '', $phone))];

        $address = [];
        if (!empty($data['user_first_name'])) $address['sha256_first_name'] = hash('sha256', strtolower(trim($data['user_first_name'])));
        if (!empty($data['user_last_name']))  $address['sha256_last_name']  = hash('sha256', strtolower(trim($data['user_last_name'])));
        if (!empty($data['user_city']))       $address['city']              = strtolower(trim($data['user_city']));
        if (!empty($data['user_state']))      $address['region']            = strtolower(trim($data['user_state']));
        if (!empty($data['user_country']))    $address['country']           = strtolower($data['user_country']);
        if (!empty($data['user_postcode']))   $address['postal_code']       = $data['user_postcode'];

        if (!empty($address)) $ud['address'] = [$address];

        return $ud;
    }

    /**
     * Get hashed user data from WP user object
     */
    private function get_hashed_user_data($user) {
        $ud = [];
        if ($user->user_email) {
            $ud['sha256_email_address'] = [hash('sha256', strtolower(trim($user->user_email)))];
        }
        $phone = get_user_meta($user->ID, 'billing_phone', true);
        if ($phone) {
            $ud['sha256_phone_number'] = [hash('sha256', preg_replace('/[^0-9+]/', '', $phone))];
        }
        $address = [];
        if ($user->first_name) $address['sha256_first_name'] = hash('sha256', strtolower(trim($user->first_name)));
        if ($user->last_name)  $address['sha256_last_name']  = hash('sha256', strtolower(trim($user->last_name)));
        if (!empty($address)) $ud['address'] = [$address];

        return $ud;
    }
}
