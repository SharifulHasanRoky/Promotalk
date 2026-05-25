<?php
/**
 * Event Match Quality Enhancement
 * Enriches every dataLayer push with hashed user parameters for Google Ads
 * enhanced conversions and GA4 User-ID matching. Improves attribution accuracy.
 *
 * Provides: user_data object in dataLayer with sha256-hashed PII
 * Sources: logged-in user, WooCommerce billing, form submissions, cookies
 */

if (!defined('ABSPATH')) exit;

class DDL_Pro_Event_Match_Quality {

    private $settings;

    public function __construct() {
        $this->settings = get_option('ddl_pro_settings', []);

        // Inject enhanced user data into dataLayer on every page
        add_action('wp_footer', [$this, 'inject_enhanced_user_data'], 5);

        // On checkout, inject billing data for matching
        add_action('woocommerce_after_checkout_form', [$this, 'inject_checkout_user_data']);

        // Store form submission data for matching
        add_filter('ddl_pro_datalayer_events', [$this, 'enrich_events_with_user_data']);
    }

    /**
     * Inject enhanced user data for logged-in users
     * This data helps Google Ads match conversions to ad clicks
     */
    public function inject_enhanced_user_data() {
        $user_data = $this->collect_user_data();
        if (empty($user_data)) return;
        ?>
        <script>
        window.dataLayer=window.dataLayer||[];
        window.dataLayer.push({
            'event':'enhanced_user_data',
            'user_data': <?php echo wp_json_encode($user_data); ?>
        });
        </script>
        <?php
    }

    /**
     * On checkout page, capture billing fields for enhanced conversions
     */
    public function inject_checkout_user_data() {
        ?>
        <script>
        (function($){
            // Capture enhanced conversion data on checkout submit
            $('form.checkout').on('checkout_place_order', function(){
                var email = $('#billing_email').val() || '';
                var phone = $('#billing_phone').val() || '';
                var fn = $('#billing_first_name').val() || '';
                var ln = $('#billing_last_name').val() || '';
                var city = $('#billing_city').val() || '';
                var state = $('#billing_state').val() || '';
                var country = $('#billing_country').val() || '';
                var postcode = $('#billing_postcode').val() || '';

                if (!email && !phone) return true;

                window.dataLayer = window.dataLayer || [];
                window.dataLayer.push({
                    'event': 'enhanced_conversion_data',
                    'enhanced_conversion_data': {
                        'email': email.toLowerCase().trim(),
                        'phone_number': phone.replace(/[^0-9+]/g, ''),
                        'first_name': fn.toLowerCase().trim(),
                        'last_name': ln.toLowerCase().trim(),
                        'home_address': {
                            'city': city.toLowerCase().trim(),
                            'region': state.toLowerCase().trim(),
                            'country': country.toLowerCase(),
                            'postal_code': postcode.trim()
                        }
                    }
                });
                return true;
            });
        })(jQuery);
        </script>
        <?php
    }

    /**
     * Enrich queued events with user_data if available
     */
    public function enrich_events_with_user_data($events) {
        $user_data = $this->collect_user_data();
        if (empty($user_data)) return $events;

        foreach ($events as &$event) {
            $name = $event['event'] ?? '';
            // Only enrich conversion events
            if (in_array($name, ['purchase', 'generate_lead', 'sign_up', 'begin_checkout', 'add_to_cart'])) {
                if (!isset($event['user_data'])) {
                    $event['user_data'] = $user_data;
                }
            }
        }

        return $events;
    }

    /**
     * Collect user data from all available sources
     * Returns hashed data ready for enhanced conversions
     */
    private function collect_user_data() {
        $data = [];

        // Source 1: Logged-in user
        if (is_user_logged_in()) {
            $user = wp_get_current_user();

            if ($user->user_email) {
                $data['sha256_email_address'] = hash('sha256', strtolower(trim($user->user_email)));
            }

            if ($user->first_name) {
                $data['sha256_first_name'] = hash('sha256', strtolower(trim($user->first_name)));
            }
            if ($user->last_name) {
                $data['sha256_last_name'] = hash('sha256', strtolower(trim($user->last_name)));
            }

            // WooCommerce billing data
            $phone = get_user_meta($user->ID, 'billing_phone', true);
            if ($phone) {
                $data['sha256_phone_number'] = hash('sha256', preg_replace('/[^0-9+]/', '', $phone));
            }

            $city = get_user_meta($user->ID, 'billing_city', true);
            $state = get_user_meta($user->ID, 'billing_state', true);
            $country = get_user_meta($user->ID, 'billing_country', true);
            $postcode = get_user_meta($user->ID, 'billing_postcode', true);

            if ($city)     $data['city']        = strtolower(trim($city));
            if ($state)    $data['region']      = strtolower(trim($state));
            if ($country)  $data['country']     = strtolower($country);
            if ($postcode) $data['postal_code'] = $postcode;
        }

        // Source 2: WooCommerce session (guest checkout)
        if (empty($data) && function_exists('WC') && WC()->session) {
            $customer = WC()->session->get('customer');
            if (!empty($customer)) {
                if (!empty($customer['email'])) {
                    $data['sha256_email_address'] = hash('sha256', strtolower(trim($customer['email'])));
                }
                if (!empty($customer['first_name'])) {
                    $data['sha256_first_name'] = hash('sha256', strtolower(trim($customer['first_name'])));
                }
                if (!empty($customer['last_name'])) {
                    $data['sha256_last_name'] = hash('sha256', strtolower(trim($customer['last_name'])));
                }
                if (!empty($customer['phone'])) {
                    $data['sha256_phone_number'] = hash('sha256', preg_replace('/[^0-9+]/', '', $customer['phone']));
                }
            }
        }

        // Source 3: Stored form submission (from transient)
        if (empty($data)) {
            $stored = get_transient('ddl_emq_' . $this->get_visitor_hash());
            if ($stored && is_array($stored)) {
                $data = $stored;
            }
        }

        return $data;
    }

    /**
     * Get a unique hash for the current visitor (for transient storage)
     */
    private function get_visitor_hash() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return md5($ip . $ua);
    }

    /**
     * Store user data from form submission (called by other modules)
     * Usage: do_action('ddl_pro_store_user_match', $email, $phone, $name)
     */
    public static function store_match_data($email = '', $phone = '', $first_name = '', $last_name = '') {
        $data = [];
        if ($email) $data['sha256_email_address'] = hash('sha256', strtolower(trim($email)));
        if ($phone) $data['sha256_phone_number']  = hash('sha256', preg_replace('/[^0-9+]/', '', $phone));
        if ($first_name) $data['sha256_first_name'] = hash('sha256', strtolower(trim($first_name)));
        if ($last_name)  $data['sha256_last_name']  = hash('sha256', strtolower(trim($last_name)));

        if (!empty($data)) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $hash = md5($ip . $ua);
            set_transient('ddl_emq_' . $hash, $data, HOUR_IN_SECONDS);
        }
    }
}
