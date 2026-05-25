<?php
/**
 * Plugin Name: PromoTrack Server
 * Plugin URI: https://github.com/SharifulHasanRoky/Promotalk
 * Description: Free server-side tracking for Google Ads & Facebook — enhanced conversions, Conversion API, event match quality 10/10, cookie management, external_id. One-click setup.
 * Version: 1.0.0
 * Author: Promotalk
 * Author URI: https://github.com/SharifulHasanRoky
 * License: GPL v2 or later
 * Text Domain: promo-track-server
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 */

if (!defined('ABSPATH')) exit;

define('PTS_VERSION', '1.0.0');
define('PTS_PATH', plugin_dir_path(__FILE__));
define('PTS_URL', plugin_dir_url(__FILE__));
define('PTS_BASENAME', plugin_basename(__FILE__));

class PromoTrack_Server {

    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_includes();
        $this->init_hooks();
    }

    private function load_includes() {
        $files = [
            'class-admin-settings',
            'class-cookie-manager',
            'class-event-match-quality',
            'class-ecommerce-events',
            'class-google-ads-tracking',
            'class-facebook-tracking',
        ];
        foreach ($files as $file) {
            $path = PTS_PATH . 'includes/' . $file . '.php';
            if (file_exists($path)) require_once $path;
        }
    }

    private function init_hooks() {
        add_action('plugins_loaded', [$this, 'init']);
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }

    public function init() {
        // Admin always
        if (is_admin()) {
            new PTS_Admin_Settings();
        }

        $s = get_option('pts_settings', []);
        if (empty($s['enabled'])) return;

        // Core modules (always active when enabled)
        new PTS_Cookie_Manager();
        new PTS_Event_Match_Quality();
        new PTS_Ecommerce_Events();

        // Platform modules
        if (!empty($s['google_ads_enabled'])) {
            new PTS_Google_Ads_Tracking();
        }
        if (!empty($s['facebook_enabled'])) {
            new PTS_Facebook_Tracking();
        }
    }

    public function activate() {
        $defaults = [
            'enabled'              => 1,
            // Google Ads
            'google_ads_enabled'   => 1,
            'google_ads_id'        => '',
            'google_ads_label'     => '',
            'ga4_measurement_id'   => '',
            'ga4_api_secret'       => '',
            'gtm_server_url'       => '',
            // Facebook
            'facebook_enabled'     => 1,
            'fb_pixel_id'          => '',
            'fb_access_token'      => '',
            'fb_test_event_code'   => '',
            // Cookies
            'cookie_fbp'           => 1,
            'cookie_fbc'           => 1,
            'cookie_gcl_aw'        => 1,
            'cookie_gcl_gs'        => 1,
            'cookie_ga'            => 1,
            'cookie_external_id'   => 1,
            'cookie_duration'      => 390,
            // Event Match Quality
            'emq_email'            => 1,
            'emq_phone'            => 1,
            'emq_name'             => 1,
            'emq_address'          => 1,
            'emq_external_id'      => 1,
            'emq_fbp'              => 1,
            'emq_fbc'              => 1,
            'emq_user_agent'       => 1,
            'emq_ip'               => 1,
            'emq_click_id'         => 1,
            // Events
            'track_page_view'      => 1,
            'track_view_content'   => 1,
            'track_add_to_cart'    => 1,
            'track_initiate_checkout' => 1,
            'track_purchase'       => 1,
            'track_lead'           => 1,
            'track_search'         => 1,
            'track_contact'        => 1,
        ];
        if (!get_option('pts_settings')) {
            update_option('pts_settings', $defaults);
        }
        flush_rewrite_rules();
    }

    public function deactivate() {
        flush_rewrite_rules();
    }
}

PromoTrack_Server::instance();
