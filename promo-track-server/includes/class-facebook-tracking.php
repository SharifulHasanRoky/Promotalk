<?php
/**
 * Facebook Tracking - PromoTrack Server
 * - Pixel (browser-side via fbq)
 * - Conversion API (server-side)
 * - Event Deduplication (event_id shared between browser & server)
 * - Event Match Quality 10/10 (all user_data params)
 * - All ecommerce events + local service events
 */

if (!defined('ABSPATH')) exit;

class PTS_Facebook_Tracking {

    private $s;
    private $emq;

    public function __construct() {
        $this->s = get_option('pts_settings', []);
        $this->emq = new PTS_Event_Match_Quality();

        // Inject FB Pixel base code
        add_action('wp_head', [$this, 'inject_pixel'], 6);

        // Listen to shared events for browser pixel
        add_action('pts_event', [$this, 'render_pixel_event'], 10, 2);

        // Server-side: purchase
        add_action('pts_purchase_server', [$this, 'send_purchase_capi'], 10, 2);

        // Server-side: lead (forms)
        add_action('wpcf7_mail_sent', [$this, 'send_lead_capi']);
        add_action('wpforms_process_complete', [$this, 'send_lead_capi_wpforms'], 10, 4);
        add_action('gform_after_submission', [$this, 'send_lead_capi_gravity'], 10, 2);

        // Local service events
        add_action('wp_footer', [$this, 'inject_local_service_js'], 20);
    }

    /**
     * Inject Facebook Pixel base code with advanced matching
     */
    public function inject_pixel() {
        $pixel_id = $this->s['fb_pixel_id'] ?? '';
        if (empty($pixel_id)) return;

        // Get user data for Advanced Matching
        $raw = $this->emq->collect_raw();
        $am = [];
        if (!empty($raw['email'])) $am['em'] = strtolower(trim($raw['email']));
        if (!empty($raw['phone'])) $am['ph'] = preg_replace('/[^0-9]/', '', $raw['phone']);
        if (!empty($raw['first_name'])) $am['fn'] = strtolower(trim($raw['first_name']));
        if (!empty($raw['last_name'])) $am['ln'] = strtolower(trim($raw['last_name']));
        if (!empty($raw['city'])) $am['ct'] = strtolower(trim($raw['city']));
        if (!empty($raw['state'])) $am['st'] = strtolower(trim($raw['state']));
        if (!empty($raw['postcode'])) $am['zp'] = trim($raw['postcode']);
        if (!empty($raw['country'])) $am['country'] = strtolower($raw['country']);

        $eid = $_COOKIE['_pts_eid'] ?? '';
        if ($eid) $am['external_id'] = $eid;
        ?>
        <!-- PromoTrack Server: Facebook Pixel -->
        <script>
        !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
        n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
        document,'script','https://connect.facebook.net/en_US/fbevents.js');
        fbq('init','<?php echo esc_js($pixel_id); ?>'<?php echo !empty($am) ? ',' . wp_json_encode($am) : ''; ?>);
        <?php if (!empty($this->s['track_page_view'])) : ?>
        fbq('track','PageView');
        <?php endif; ?>
        </script>
        <noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=<?php echo esc_attr($pixel_id); ?>&ev=PageView&noscript=1"/></noscript>
        <?php

        // Also send PageView via CAPI
        if (!empty($this->s['track_page_view'])) {
            $this->send_capi_event('PageView', []);
        }
    }

    /**
     * Render browser pixel event (from shared event system)
     */
    public function render_pixel_event($event_name, $data = []) {
        $fb_event = $this->map_event($event_name);
        if (!$fb_event) return;

        $event_id = $this->generate_event_id();
        $params = $this->build_pixel_params($event_name, $data);
        $params['eventID'] = $event_id;
        ?>
        <script>
        fbq('track','<?php echo esc_js($fb_event); ?>',<?php echo wp_json_encode($params); ?>,{eventID:'<?php echo esc_js($event_id); ?>'});
        </script>
        <?php

        // Also fire CAPI for deduplication
        $this->send_capi_event($fb_event, $data, $event_id);
    }

    /**
     * Server-side purchase via Conversion API
     */
    public function send_purchase_capi($order, $data) {
        $event_id = 'purchase_' . ($data['transaction_id'] ?? $order->get_id());
        $this->send_capi_event('Purchase', $data, $event_id, $order);
    }

    /**
     * Server-side lead (CF7)
     */
    public function send_lead_capi($cf = null) {
        $this->send_capi_event('Lead', ['value' => 0, 'currency' => 'USD']);
    }

    public function send_lead_capi_wpforms($fields, $entry, $form_data, $entry_id) {
        $this->send_lead_capi();
    }

    public function send_lead_capi_gravity($entry, $form) {
        $this->send_lead_capi();
    }

    /**
     * Inject local service event tracking (phone, email, chat, directions)
     */
    public function inject_local_service_js() {
        if (empty($this->s['track_contact'])) return;
        ?>
        <script>
        (function($){
            // Click to call
            $(document).on('click','a[href^="tel:"]',function(){
                var eid = 'contact_' + Date.now();
                fbq('track','Contact',{content_name:'phone_call',content_category:'local_service'},{eventID:eid});
                // CAPI via AJAX
                $.post(ptsConfig.ajaxUrl,{action:'pts_capi_event',nonce:ptsConfig.nonce,event:'Contact',event_id:eid,data:JSON.stringify({content_name:'phone_call'})});
            });
            // Email click
            $(document).on('click','a[href^="mailto:"]',function(){
                var eid = 'contact_' + Date.now();
                fbq('track','Contact',{content_name:'email',content_category:'local_service'},{eventID:eid});
                $.post(ptsConfig.ajaxUrl,{action:'pts_capi_event',nonce:ptsConfig.nonce,event:'Contact',event_id:eid,data:JSON.stringify({content_name:'email'})});
            });
            // WhatsApp/Messenger
            $(document).on('click','a[href*="wa.me"],a[href*="whatsapp"],a[href*="m.me"]',function(){
                var eid = 'contact_' + Date.now();
                fbq('track','Contact',{content_name:'chat',content_category:'local_service'},{eventID:eid});
                $.post(ptsConfig.ajaxUrl,{action:'pts_capi_event',nonce:ptsConfig.nonce,event:'Contact',event_id:eid,data:JSON.stringify({content_name:'chat'})});
            });
            // Directions
            $(document).on('click','a[href*="maps.google"],a[href*="google.com/maps"],a[href*="waze.com"]',function(){
                var eid = 'findloc_' + Date.now();
                fbq('track','FindLocation',{content_name:'get_directions'},{eventID:eid});
                $.post(ptsConfig.ajaxUrl,{action:'pts_capi_event',nonce:ptsConfig.nonce,event:'FindLocation',event_id:eid,data:JSON.stringify({content_name:'directions'})});
            });
        })(jQuery);
        </script>
        <?php
    }

    /**
     * AJAX handler for CAPI events from frontend
     */
    public function __construct_ajax() {
        add_action('wp_ajax_pts_capi_event', [$this, 'ajax_capi']);
        add_action('wp_ajax_nopriv_pts_capi_event', [$this, 'ajax_capi']);
    }

    /**
     * Send event to Facebook Conversion API
     */
    private function send_capi_event($event_name, $data = [], $event_id = '', $order = null) {
        $pixel_id = $this->s['fb_pixel_id'] ?? '';
        $token = $this->s['fb_access_token'] ?? '';
        if (empty($pixel_id) || empty($token)) return;

        if (empty($event_id)) $event_id = $this->generate_event_id();

        // Build user_data with ALL parameters for 10/10 score
        $user_data = $this->emq->get_fb_user_data();

        // If we have order, enrich user_data from billing
        if ($order && method_exists($order, 'get_billing_email')) {
            $email = $order->get_billing_email();
            if ($email) $user_data['em'] = [hash('sha256', strtolower(trim($email)))];
            $phone = $order->get_billing_phone();
            if ($phone) $user_data['ph'] = [hash('sha256', preg_replace('/[^0-9]/', '', $phone))];
            $fn = $order->get_billing_first_name();
            if ($fn) $user_data['fn'] = [hash('sha256', strtolower(trim($fn)))];
            $ln = $order->get_billing_last_name();
            if ($ln) $user_data['ln'] = [hash('sha256', strtolower(trim($ln)))];
            $user_data['ct'] = [hash('sha256', strtolower(trim($order->get_billing_city())))];
            $user_data['st'] = [hash('sha256', strtolower(trim($order->get_billing_state())))];
            $user_data['zp'] = [hash('sha256', trim($order->get_billing_postcode()))];
            $user_data['country'] = [strtolower($order->get_billing_country())];
        }

        // Build event payload
        $event = [
            'event_name'  => $event_name,
            'event_time'  => time(),
            'event_id'    => $event_id,
            'action_source' => 'website',
            'event_source_url' => home_url($_SERVER['REQUEST_URI'] ?? '/'),
            'user_data'   => $user_data,
        ];

        // Custom data
        $custom = [];
        if (isset($data['value'])) $custom['value'] = (float) $data['value'];
        if (isset($data['currency'])) $custom['currency'] = $data['currency'];
        if (isset($data['content_ids'])) $custom['content_ids'] = $data['content_ids'];
        if (isset($data['content_type'])) $custom['content_type'] = $data['content_type'];
        if (isset($data['content_name'])) $custom['content_name'] = $data['content_name'];
        if (isset($data['num_items'])) $custom['num_items'] = $data['num_items'];
        if (isset($data['transaction_id'])) $custom['order_id'] = $data['transaction_id'];
        if (isset($data['search_string'])) $custom['search_string'] = $data['search_string'];

        if (!empty($custom)) $event['custom_data'] = $custom;

        // Build full payload
        $payload = ['data' => [$event]];

        // Test event code
        $test_code = $this->s['fb_test_event_code'] ?? '';
        if (!empty($test_code)) $payload['test_event_code'] = $test_code;

        // Send to Facebook
        $url = 'https://graph.facebook.com/v18.0/' . $pixel_id . '/events?access_token=' . $token;

        wp_remote_post($url, [
            'body'     => wp_json_encode($payload),
            'headers'  => ['Content-Type' => 'application/json'],
            'timeout'  => 10,
            'blocking' => false,
        ]);
    }

    /**
     * Build pixel params for browser event
     */
    private function build_pixel_params($event_name, $data) {
        $params = [];
        if (isset($data['value'])) $params['value'] = (float) $data['value'];
        if (isset($data['currency'])) $params['currency'] = $data['currency'];
        if (isset($data['content_ids'])) $params['content_ids'] = $data['content_ids'];
        if (isset($data['content_type'])) $params['content_type'] = $data['content_type'];
        if (isset($data['content_name'])) $params['content_name'] = $data['content_name'];
        if (isset($data['content_category'])) $params['content_category'] = $data['content_category'];
        if (isset($data['num_items'])) $params['num_items'] = $data['num_items'];
        if (isset($data['search_string'])) $params['search_string'] = $data['search_string'];
        return $params;
    }

    /**
     * Map internal event names to Facebook standard events
     */
    private function map_event($name) {
        $map = [
            'ViewContent'      => 'ViewContent',
            'AddToCart'        => 'AddToCart',
            'InitiateCheckout' => 'InitiateCheckout',
            'Purchase'         => 'Purchase',
            'Lead'             => 'Lead',
            'Search'           => 'Search',
            'Contact'          => 'Contact',
        ];
        return $map[$name] ?? null;
    }

    /**
     * Generate unique event_id for deduplication
     */
    private function generate_event_id() {
        return uniqid('pts_', true);
    }
}

// Register AJAX hooks outside class (WordPress requirement)
add_action('wp_ajax_pts_capi_event', 'pts_ajax_capi_handler');
add_action('wp_ajax_nopriv_pts_capi_event', 'pts_ajax_capi_handler');

function pts_ajax_capi_handler() {
    check_ajax_referer('pts_nonce', 'nonce');
    $event = sanitize_text_field($_POST['event'] ?? '');
    $event_id = sanitize_text_field($_POST['event_id'] ?? '');
    $data = json_decode(stripslashes($_POST['data'] ?? '{}'), true);

    if (empty($event)) wp_send_json_error();

    $s = get_option('pts_settings', []);
    $pixel_id = $s['fb_pixel_id'] ?? '';
    $token = $s['fb_access_token'] ?? '';
    if (empty($pixel_id) || empty($token)) wp_send_json_error();

    $emq = new PTS_Event_Match_Quality();
    $user_data = $emq->get_fb_user_data();

    $payload = [
        'data' => [[
            'event_name'       => $event,
            'event_time'       => time(),
            'event_id'         => $event_id,
            'action_source'    => 'website',
            'event_source_url' => wp_get_referer() ?: home_url('/'),
            'user_data'        => $user_data,
            'custom_data'      => $data,
        ]],
    ];

    $test_code = $s['fb_test_event_code'] ?? '';
    if ($test_code) $payload['test_event_code'] = $test_code;

    wp_remote_post('https://graph.facebook.com/v18.0/' . $pixel_id . '/events?access_token=' . $token, [
        'body'    => wp_json_encode($payload),
        'headers' => ['Content-Type' => 'application/json'],
        'timeout' => 10,
        'blocking'=> false,
    ]);

    wp_send_json_success();
}
