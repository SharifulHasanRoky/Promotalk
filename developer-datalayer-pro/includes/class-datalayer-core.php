<?php
/**
 * Core DataLayer Class
 * Handles: dataLayer initialization, GTM injection, user data collection,
 * page context detection, and script enqueue for frontend tracking.
 */

if (!defined('ABSPATH')) exit;

class DDL_Pro_DataLayer_Core {

    private $settings;
    private $datalayer_events = [];
    private static $instance = null;

    public function __construct() {
        self::$instance = $this;
        $this->settings = get_option('ddl_pro_settings', []);

        add_action('wp_head', [$this, 'output_consent_defaults'], 1);
        add_action('wp_head', [$this, 'init_datalayer'], 2);
        add_action('wp_head', [$this, 'inject_gtm_head'], 3);
        add_action('wp_body_open', [$this, 'inject_gtm_body'], 1);
        add_action('wp_footer', [$this, 'push_queued_events'], 99);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    /**
     * Output Google Consent Mode v2 defaults before anything else
     */
    public function output_consent_defaults() {
        $default = ($this->settings['consent_mode'] ?? 'granted') === 'denied' ? 'denied' : 'granted';
        ?>
        <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('consent', 'default', {
            'ad_storage': '<?php echo esc_js($default); ?>',
            'ad_user_data': '<?php echo esc_js($default); ?>',
            'ad_personalization': '<?php echo esc_js($default); ?>',
            'analytics_storage': '<?php echo esc_js($default); ?>',
            'functionality_storage': 'granted',
            'personalization_storage': '<?php echo esc_js($default); ?>',
            'security_storage': 'granted',
            'wait_for_update': 500
        });
        </script>
        <?php
    }

    /**
     * Initialize dataLayer with page & user context
     */
    public function init_datalayer() {
        $page = $this->get_page_context();
        $user = $this->get_user_data();
        ?>
        <script>
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            'event': 'ddl_page_meta',
            'page_type': '<?php echo esc_js($page['type']); ?>',
            'page_title': '<?php echo esc_js($page['title']); ?>',
            'page_path': '<?php echo esc_js($page['path']); ?>',
            'page_category': '<?php echo esc_js($page['category']); ?>',
            'site_language': '<?php echo esc_js(get_locale()); ?>',
            'logged_in': <?php echo is_user_logged_in() ? 'true' : 'false'; ?>,
            <?php if (!empty($user)) : ?>
            'user_data': <?php echo wp_json_encode($user); ?>,
            <?php endif; ?>
            'timestamp': <?php echo time(); ?>
        });
        </script>
        <?php
    }

    /**
     * Inject GTM head snippet (uses sGTM URL if available)
     */
    public function inject_gtm_head() {
        $gtm_id = $this->settings['gtm_id'] ?? '';
        if (empty($gtm_id)) return;

        $server_url = $this->settings['server_container_url'] ?? '';
        $domain = !empty($server_url) ? rtrim($server_url, '/') : 'https://www.googletagmanager.com';
        ?>
        <!-- Google Tag Manager (Developer DataLayer Pro) -->
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        '<?php echo esc_url($domain); ?>/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','<?php echo esc_js($gtm_id); ?>');</script>
        <!-- End Google Tag Manager -->
        <?php
    }

    /**
     * Inject GTM noscript body snippet
     */
    public function inject_gtm_body() {
        $gtm_id = $this->settings['gtm_id'] ?? '';
        if (empty($gtm_id)) return;
        ?>
        <!-- Google Tag Manager (noscript) -->
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_attr($gtm_id); ?>"
        height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        <!-- End Google Tag Manager (noscript) -->
        <?php
    }

    /**
     * Enqueue frontend helper script with config
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            'ddl-pro-frontend',
            DDL_PRO_URL . 'assets/js/frontend.js',
            ['jquery'],
            DDL_PRO_VERSION,
            true
        );

        wp_localize_script('ddl-pro-frontend', 'ddlProConfig', [
            'ajaxUrl'       => admin_url('admin-ajax.php'),
            'nonce'         => wp_create_nonce('ddl_pro_nonce'),
            'ga4Id'         => $this->settings['ga4_measurement_id'] ?? '',
            'googleAdsId'   => $this->settings['google_ads_id'] ?? '',
            'serverUrl'     => $this->settings['server_container_url'] ?? '',
            'consentMode'   => $this->settings['consent_mode'] ?? 'granted',
            'isWoo'         => class_exists('WooCommerce') ? '1' : '0',
            'currency'      => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD',
            'vertical'      => $this->settings['business_vertical'] ?? 'retail',
        ]);
    }

    /**
     * Push all queued events in footer
     */
    public function push_queued_events() {
        $events = apply_filters('ddl_pro_datalayer_events', $this->datalayer_events);
        if (empty($events)) return;
        ?>
        <script>
        window.dataLayer = window.dataLayer || [];
        <?php foreach ($events as $event) : ?>
        window.dataLayer.push(<?php echo wp_json_encode($event); ?>);
        <?php endforeach; ?>
        </script>
        <?php
    }

    /**
     * Public: Queue an event for footer output
     */
    public function queue_event($event_name, $data = []) {
        $data['event'] = $event_name;
        $this->datalayer_events[] = $data;
    }

    /**
     * Static access to queue events from other classes
     */
    public static function push($event_name, $data = []) {
        if (self::$instance) {
            self::$instance->queue_event($event_name, $data);
        }
    }

    /**
     * Get user data (hashed for privacy) for enhanced conversions
     */
    private function get_user_data() {
        if (!is_user_logged_in()) return [];

        $user = wp_get_current_user();
        $data = [
            'user_id' => (string) $user->ID,
        ];

        // Email (hashed)
        if ($user->user_email) {
            $data['email_sha256'] = hash('sha256', strtolower(trim($user->user_email)));
        }

        // Name (hashed)
        if ($user->first_name) {
            $data['first_name_sha256'] = hash('sha256', strtolower(trim($user->first_name)));
        }
        if ($user->last_name) {
            $data['last_name_sha256'] = hash('sha256', strtolower(trim($user->last_name)));
        }

        // Phone from WooCommerce billing
        $phone = get_user_meta($user->ID, 'billing_phone', true);
        if ($phone) {
            $data['phone_sha256'] = hash('sha256', preg_replace('/[^0-9+]/', '', $phone));
        }

        // Address from WooCommerce billing
        $city = get_user_meta($user->ID, 'billing_city', true);
        $state = get_user_meta($user->ID, 'billing_state', true);
        $country = get_user_meta($user->ID, 'billing_country', true);
        $postcode = get_user_meta($user->ID, 'billing_postcode', true);

        if ($city) $data['city'] = strtolower(trim($city));
        if ($state) $data['region'] = strtolower(trim($state));
        if ($country) $data['country'] = strtolower($country);
        if ($postcode) $data['postal_code'] = $postcode;

        return $data;
    }

    /**
     * Detect page context dynamically
     */
    private function get_page_context() {
        $type = 'other';
        $category = '';

        if (function_exists('is_shop') && is_shop()) {
            $type = 'shop';
            $category = 'ecommerce';
        } elseif (function_exists('is_product') && is_product()) {
            $type = 'product';
            $category = 'ecommerce';
        } elseif (function_exists('is_product_category') && is_product_category()) {
            $type = 'product_category';
            $category = 'ecommerce';
            $term = get_queried_object();
            if ($term) $category = $term->name;
        } elseif (function_exists('is_cart') && is_cart()) {
            $type = 'cart';
            $category = 'ecommerce';
        } elseif (function_exists('is_checkout') && is_checkout()) {
            $type = function_exists('is_order_received_page') && is_order_received_page() ? 'purchase' : 'checkout';
            $category = 'ecommerce';
        } elseif (function_exists('is_account_page') && is_account_page()) {
            $type = 'account';
            $category = 'ecommerce';
        } elseif (is_front_page()) {
            $type = 'home';
        } elseif (is_search()) {
            $type = 'search';
        } elseif (is_category() || is_tag()) {
            $type = 'category';
            $term = get_queried_object();
            if ($term) $category = $term->name;
        } elseif (is_single()) {
            $type = 'article';
            $cats = get_the_category();
            if (!empty($cats)) $category = $cats[0]->name;
        } elseif (is_page()) {
            $type = 'page';
        } elseif (is_archive()) {
            $type = 'archive';
        } elseif (is_404()) {
            $type = '404';
        }

        return [
            'type'     => $type,
            'title'    => wp_title('', false) ?: get_the_title(),
            'path'     => $_SERVER['REQUEST_URI'] ?? '/',
            'category' => $category,
        ];
    }
}
