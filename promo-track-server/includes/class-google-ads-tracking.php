<?php
/**
 * Google Ads Tracking - PromoTrack Server
 * - Conversion Tracking (gtag.js)
 * - Enhanced Conversions (user_data with hashed PII)
 * - Server-Side Tracking (GA4 Measurement Protocol)
 * - GCLID capture & forwarding
 * - Consent Mode v2
 */

if (!defined('ABSPATH')) exit;

class PTS_Google_Ads_Tracking {

    private $s;
    private $emq;

    public function __construct() {
        $this->s = get_option('pts_settings', []);
        $this->emq = new PTS_Event_Match_Quality();

        // Inject gtag.js
        add_action('wp_head', [$this, 'inject_gtag'], 5);

        // Listen to purchase for server-side
        add_action('pts_purchase_server', [$this, 'send_purchase_server'], 10, 2);

        // Listen to shared events for client-side gtag calls
        add_action('pts_event', [$this, 'render_event'], 10, 2);

        // Form submissions as conversions
        add_action('wpcf7_mail_sent', [$this, 'on_lead']);
        add_action('wpforms_process_complete', [$this, 'on_lead_wpforms'], 10, 4);
    }

    /**
     * Inject Google Ads gtag.js with Consent Mode v2 + Enhanced Conversions
     */
    public function inject_gtag() {
        $ads_id = $this->s['google_ads_id'] ?? '';
        $ga4_id = $this->s['ga4_measurement_id'] ?? '';
        $server_url = $this->s['gtm_server_url'] ?? '';

        if (empty($ads_id) && empty($ga4_id)) return;

        $primary_id = $ads_id ?: $ga4_id;
        $gtag_domain = !empty($server_url) ? rtrim($server_url, '/') : 'https://www.googletagmanager.com';
        ?>
        <!-- PromoTrack Server: Google Ads + GA4 -->
        <script>
        window.dataLayer=window.dataLayer||[];
        function gtag(){dataLayer.push(arguments);}
        gtag('consent','default',{
            'ad_storage':'granted','ad_user_data':'granted','ad_personalization':'granted',
            'analytics_storage':'granted','functionality_storage':'granted',
            'personalization_storage':'granted','security_storage':'granted'
        });
        gtag('set','ads_data_redaction',false);
        gtag('set','url_passthrough',true);
        </script>
        <script async src="<?php echo esc_url($gtag_domain); ?>/gtag/js?id=<?php echo esc_attr($primary_id); ?>"></script>
        <script>
        gtag('js', new Date());
        <?php if ($ads_id) : ?>
        gtag('config','<?php echo esc_js($ads_id); ?>',{
            'allow_enhanced_conversions': true
            <?php if ($server_url) : ?>,'transport_url':'<?php echo esc_js($server_url); ?>','first_party_collection':true<?php endif; ?>
        });
        <?php endif; ?>
        <?php if ($ga4_id) : ?>
        gtag('config','<?php echo esc_js($ga4_id); ?>',{
            'send_page_view': true
            <?php if ($server_url) : ?>,'transport_url':'<?php echo esc_js($server_url); ?>','first_party_collection':true<?php endif; ?>
        });
        <?php endif; ?>
        <?php
        // Enhanced conversion data for logged-in users
        $ud = $this->emq->get_google_user_data();
        if (!empty($ud)) :
        ?>
        gtag('set','user_data',<?php echo wp_json_encode($ud); ?>);
        <?php endif; ?>
        </script>
        <?php
    }

    /**
     * Render client-side gtag event
     */
    public function render_event($event_name, $data = []) {
        $ads_id = $this->s['google_ads_id'] ?? '';
        $label = $this->s['google_ads_label'] ?? '';

        $gtag_event = $this->map_event($event_name);
        if (!$gtag_event) return;

        $params = [];
        if (isset($data['value'])) $params['value'] = $data['value'];
        if (isset($data['currency'])) $params['currency'] = $data['currency'];
        if (isset($data['transaction_id'])) $params['transaction_id'] = $data['transaction_id'];
        if (isset($data['items'])) $params['items'] = $data['items'];
        if (isset($data['search_string'])) $params['search_term'] = $data['search_string'];

        // Enhanced conversion user data
        $ud = $this->emq->get_google_user_data();
        if (!empty($ud)) $params['user_data'] = $ud;
        ?>
        <script>
        gtag('event','<?php echo esc_js($gtag_event); ?>',<?php echo wp_json_encode($params); ?>);
        <?php if ($event_name === 'Purchase' && $ads_id && $label) : ?>
        gtag('event','conversion',{
            'send_to':'<?php echo esc_js($ads_id . '/' . $label); ?>',
            'value':<?php echo (float)($data['value'] ?? 0); ?>,
            'currency':'<?php echo esc_js($data['currency'] ?? 'USD'); ?>',
            'transaction_id':'<?php echo esc_js($data['transaction_id'] ?? ''); ?>'
        });
        <?php endif; ?>
        </script>
        <?php
    }

    /**
     * Server-side purchase via GA4 Measurement Protocol
     */
    public function send_purchase_server($order, $data) {
        $ga4_id = $this->s['ga4_measurement_id'] ?? '';
        $secret = $this->s['ga4_api_secret'] ?? '';
        if (empty($ga4_id) || empty($secret)) return;

        $server_url = $this->s['gtm_server_url'] ?? '';
        $base = !empty($server_url) ? rtrim($server_url, '/') : 'https://www.google-analytics.com';

        $url = $base . '/mp/collect?measurement_id=' . urlencode($ga4_id) . '&api_secret=' . urlencode($secret);

        $cookies = PTS_Cookie_Manager::get_cookies();
        $client_id = $cookies['client_id'] ?: (rand(100000000, 999999999) . '.' . time());

        $payload = [
            'client_id' => $client_id,
            'user_id'   => is_user_logged_in() ? (string) get_current_user_id() : null,
            'events'    => [[
                'name'   => 'purchase',
                'params' => [
                    'transaction_id'       => $data['transaction_id'] ?? '',
                    'value'                => (float)($data['value'] ?? 0),
                    'currency'             => $data['currency'] ?? 'USD',
                    'items'                => $data['items'] ?? [],
                    'engagement_time_msec' => '100',
                    'session_id'           => (string) time(),
                ],
            ]],
        ];

        // Enhanced conversion user data
        $ud = $this->emq->get_google_user_data();
        if (!empty($ud)) $payload['user_data'] = $ud;

        wp_remote_post($url, [
            'body'     => wp_json_encode(array_filter($payload)),
            'headers'  => ['Content-Type' => 'application/json'],
            'timeout'  => 5,
            'blocking' => false,
        ]);
    }

    /**
     * Track form submission as lead conversion
     */
    public function on_lead($cf = null) {
        $ads_id = $this->s['google_ads_id'] ?? '';
        $ga4_id = $this->s['ga4_measurement_id'] ?? '';
        $secret = $this->s['ga4_api_secret'] ?? '';

        if (empty($ga4_id) || empty($secret)) return;

        $server_url = $this->s['gtm_server_url'] ?? '';
        $base = !empty($server_url) ? rtrim($server_url, '/') : 'https://www.google-analytics.com';
        $url = $base . '/mp/collect?measurement_id=' . urlencode($ga4_id) . '&api_secret=' . urlencode($secret);

        $cookies = PTS_Cookie_Manager::get_cookies();
        $client_id = $cookies['client_id'] ?: (rand(100000000, 999999999) . '.' . time());

        $payload = [
            'client_id' => $client_id,
            'events'    => [[
                'name'   => 'generate_lead',
                'params' => [
                    'value'    => 0,
                    'currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD',
                ],
            ]],
        ];

        $ud = $this->emq->get_google_user_data();
        if (!empty($ud)) $payload['user_data'] = $ud;

        wp_remote_post($url, [
            'body'     => wp_json_encode($payload),
            'headers'  => ['Content-Type' => 'application/json'],
            'timeout'  => 5,
            'blocking' => false,
        ]);
    }

    public function on_lead_wpforms($fields, $entry, $form_data, $entry_id) {
        $this->on_lead();
    }

    /**
     * Map event names to gtag events
     */
    private function map_event($name) {
        $map = [
            'ViewContent'      => 'view_item',
            'AddToCart'        => 'add_to_cart',
            'InitiateCheckout' => 'begin_checkout',
            'Purchase'         => 'purchase',
            'Lead'             => 'generate_lead',
            'Search'           => 'search',
            'Contact'          => 'generate_lead',
        ];
        return $map[$name] ?? null;
    }
}
