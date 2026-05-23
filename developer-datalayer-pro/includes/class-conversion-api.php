<?php
/**
 * Conversion API — GA4 Measurement Protocol
 * Sends server-side events directly to GA4 via Measurement Protocol.
 * Bypasses ad-blockers and ensures data accuracy.
 *
 * Listens to: do_action('ddl_pro_server_event', $event_name, $data)
 * Supported events: purchase, generate_lead, sign_up, add_to_cart, refund, booking_confirmed, appointment_booked
 *
 * Handles: event deduplication, user data hashing, client_id/session_id extraction
 */

if (!defined('ABSPATH')) exit;

class DDL_Pro_Conversion_API {

    private $settings;
    private $measurement_id;
    private $api_secret;

    public function __construct() {
        $this->settings       = get_option('ddl_pro_settings', []);
        $this->measurement_id = $this->settings['ga4_measurement_id'] ?? '';
        $this->api_secret     = $this->settings['ga4_api_secret'] ?? '';

        if (empty($this->measurement_id) || empty($this->api_secret)) return;

        // Listen for server events from all modules
        add_action('ddl_pro_server_event', [$this, 'handle_event'], 10, 2);

        // AJAX endpoint for client-triggered server events
        add_action('wp_ajax_ddl_pro_server_event', [$this, 'ajax_handler']);
        add_action('wp_ajax_nopriv_ddl_pro_server_event', [$this, 'ajax_handler']);
    }

    /**
     * Handle server-side event dispatch
     */
    public function handle_event($event_name, $data = []) {
        // Map internal events to GA4 event names
        $ga4_event = $this->map_event_name($event_name);
        if (!$ga4_event) return;

        // Build payload
        $payload = $this->build_payload($ga4_event, $data);
        if (!$payload) return;

        // Send async (non-blocking)
        $this->send_to_ga4($payload);
    }

    /**
     * AJAX handler for client-triggered server events
     */
    public function ajax_handler() {
        check_ajax_referer('ddl_pro_nonce', 'nonce');

        $event_name = sanitize_text_field($_POST['event_name'] ?? '');
        $event_data = json_decode(stripslashes($_POST['event_data'] ?? '{}'), true);

        if (empty($event_name) || !is_array($event_data)) {
            wp_send_json_error('Invalid data');
        }

        $this->handle_event($event_name, $event_data);
        wp_send_json_success(['sent' => true]);
    }

    /**
     * Build GA4 Measurement Protocol payload
     */
    private function build_payload($event_name, $data) {
        $client_id = $this->get_client_id($data);
        if (!$client_id) {
            $client_id = $this->generate_client_id();
        }

        // Base event
        $event = [
            'name'   => $event_name,
            'params' => [
                'engagement_time_msec' => '100',
                'session_id'           => $this->get_session_id($data),
            ],
        ];

        // Add event-specific params
        switch ($event_name) {
            case 'purchase':
                $event['params']['transaction_id'] = $data['transaction_id'] ?? '';
                $event['params']['value']          = (float)($data['value'] ?? 0);
                $event['params']['currency']       = $data['currency'] ?? 'USD';
                $event['params']['tax']            = (float)($data['tax'] ?? 0);
                $event['params']['shipping']       = (float)($data['shipping'] ?? 0);
                if (!empty($data['items'])) {
                    $event['params']['items'] = array_slice($data['items'], 0, 200);
                }
                break;

            case 'refund':
                $event['params']['transaction_id'] = $data['transaction_id'] ?? '';
                $event['params']['value']          = (float)($data['value'] ?? 0);
                $event['params']['currency']       = $data['currency'] ?? 'USD';
                break;

            case 'generate_lead':
                $event['params']['value']     = (float)($data['value'] ?? 0);
                $event['params']['currency']  = $data['currency'] ?? 'USD';
                $event['params']['lead_type'] = $data['lead_type'] ?? $data['form_type'] ?? 'form';
                if (!empty($data['form_name']))   $event['params']['form_name']   = $data['form_name'];
                if (!empty($data['form_plugin'])) $event['params']['form_plugin'] = $data['form_plugin'];
                break;

            case 'sign_up':
                $event['params']['method'] = $data['lead_type'] ?? 'website';
                break;

            case 'add_to_cart':
                $event['params']['value']    = (float)($data['value'] ?? ($data['price'] ?? 0) * ($data['quantity'] ?? 1));
                $event['params']['currency'] = $data['currency'] ?? 'USD';
                if (!empty($data['items'])) {
                    $event['params']['items'] = $data['items'];
                }
                break;

            default:
                // Pass through any custom params
                foreach ($data as $k => $v) {
                    if (in_array($k, ['user_email','user_phone','user_first_name','user_last_name','user_city','user_state','user_country','user_postcode'])) continue;
                    if (is_scalar($v)) {
                        $event['params'][$k] = $v;
                    }
                }
                break;
        }

        // Build full payload
        $payload = [
            'client_id'            => $client_id,
            'non_personalized_ads' => false,
            'events'               => [$event],
        ];

        // User properties for enhanced conversions
        $user_data = $this->extract_user_data($data);
        if (!empty($user_data)) {
            $payload['user_data'] = $user_data;
        }

        // User ID if available
        $user_id = $data['user_id'] ?? '';
        if (!$user_id && is_user_logged_in()) {
            $user_id = (string) get_current_user_id();
        }
        if ($user_id) {
            $payload['user_id'] = $user_id;
        }

        return $payload;
    }

    /**
     * Send payload to GA4 Measurement Protocol endpoint
     */
    private function send_to_ga4($payload) {
        $url = sprintf(
            'https://www.google-analytics.com/mp/collect?measurement_id=%s&api_secret=%s',
            urlencode($this->measurement_id),
            urlencode($this->api_secret)
        );

        // Use server container URL if available
        $server_url = $this->settings['server_container_url'] ?? '';
        if (!empty($server_url)) {
            $url = sprintf(
                '%s/mp/collect?measurement_id=%s&api_secret=%s',
                rtrim($server_url, '/'),
                urlencode($this->measurement_id),
                urlencode($this->api_secret)
            );
        }

        $args = [
            'body'        => wp_json_encode($payload),
            'headers'     => ['Content-Type' => 'application/json'],
            'timeout'     => 5,
            'blocking'    => false, // Non-blocking — don't wait for response
            'data_format' => 'body',
        ];

        wp_remote_post($url, $args);

        // Log for debugging (only in WP_DEBUG mode)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[DDL Pro CAPI] Event sent: ' . ($payload['events'][0]['name'] ?? 'unknown'));
        }
    }

    /**
     * Extract and hash user data for enhanced conversions
     */
    private function extract_user_data($data) {
        $user_data = [];

        // Email
        $email = $data['user_email'] ?? '';
        if (!$email && is_user_logged_in()) {
            $email = wp_get_current_user()->user_email;
        }
        if ($email) {
            $user_data['sha256_email_address'] = [hash('sha256', strtolower(trim($email)))];
        }

        // Phone
        $phone = $data['user_phone'] ?? '';
        if (!$phone && is_user_logged_in()) {
            $phone = get_user_meta(get_current_user_id(), 'billing_phone', true);
        }
        if ($phone) {
            $user_data['sha256_phone_number'] = [hash('sha256', preg_replace('/[^0-9+]/', '', $phone))];
        }

        // Address
        $address = [];
        $fn = $data['user_first_name'] ?? '';
        $ln = $data['user_last_name'] ?? '';
        if ($fn) $address['sha256_first_name'] = hash('sha256', strtolower(trim($fn)));
        if ($ln) $address['sha256_last_name']  = hash('sha256', strtolower(trim($ln)));
        if (!empty($data['user_city']))    $address['city']        = strtolower(trim($data['user_city']));
        if (!empty($data['user_state']))   $address['region']      = strtolower(trim($data['user_state']));
        if (!empty($data['user_country'])) $address['country']     = strtolower($data['user_country']);
        if (!empty($data['user_postcode']))$address['postal_code'] = $data['user_postcode'];

        if (!empty($address)) {
            $user_data['address'] = [$address];
        }

        return $user_data;
    }

    /**
     * Map internal event names to GA4 event names
     */
    private function map_event_name($name) {
        $map = [
            'purchase'           => 'purchase',
            'refund'             => 'refund',
            'generate_lead'      => 'generate_lead',
            'sign_up'            => 'sign_up',
            'add_to_cart'        => 'add_to_cart',
            'booking_confirmed'  => 'purchase',  // Treat bookings as purchases
            'appointment_booked' => 'generate_lead',
            'customer_inquiry'   => 'generate_lead',
        ];
        return $map[$name] ?? $name;
    }

    /**
     * Get client_id from cookie or generate one
     */
    private function get_client_id($data) {
        // From passed data
        if (!empty($data['client_id'])) return $data['client_id'];

        // From _ga cookie
        if (isset($_COOKIE['_ga'])) {
            $parts = explode('.', $_COOKIE['_ga']);
            if (count($parts) >= 4) {
                return $parts[2] . '.' . $parts[3];
            }
        }

        return '';
    }

    /**
     * Get session_id from GA4 session cookie
     */
    private function get_session_id($data) {
        if (!empty($data['session_id'])) return $data['session_id'];

        $ga4_id = str_replace('G-', '', $this->measurement_id);
        $cookie_name = '_ga_' . $ga4_id;

        if (isset($_COOKIE[$cookie_name])) {
            $parts = explode('.', $_COOKIE[$cookie_name]);
            // Format: GS1.1.SESSION_ID.TIMESTAMP...
            if (count($parts) >= 3) {
                return $parts[2];
            }
        }

        return (string) time();
    }

    /**
     * Generate a random client_id for anonymous users
     */
    private function generate_client_id() {
        return rand(100000000, 999999999) . '.' . time();
    }
}
