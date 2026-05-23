<?php
/**
 * Plugin Name: Developer DataLayer Pro
 * Plugin URI: https://github.com/SharifulHasanRoky/Promotalk
 * Description: One-click GA4 DataLayer — all ecommerce, local service, form, B2B events, Google Ads remarketing, Conversion API, server-side tracking, event match quality & cookie management. Just toggle ON.
 * Version: 1.0.0
 * Author: Promotalk
 * Author URI: https://github.com/SharifulHasanRoky
 * License: GPL v2 or later
 * Text Domain: developer-datalayer-pro
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 */

if (!defined('ABSPATH')) exit;

define('DDL_PRO_VERSION', '1.0.0');
define('DDL_PRO_PATH', plugin_dir_path(__FILE__));
define('DDL_PRO_URL', plugin_dir_url(__FILE__));
define('DDL_PRO_BASENAME', plugin_basename(__FILE__));

class Developer_DataLayer_Pro {

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
        $includes = [
            'class-admin-settings',
            'class-datalayer-core',
            'class-ecommerce-events',
            'class-remarketing-events',
            'class-local-service-events',
            'class-form-events',
            'class-b2b-events',
            'class-conversion-api',
            'class-server-side-tracking',
            'class-event-match-quality',
            'class-cookie-manager',
        ];
        foreach ($includes as $file) {
            $path = DDL_PRO_PATH . 'includes/' . $file . '.php';
            if (file_exists($path)) require_once $path;
        }
    }

    private function init_hooks() {
        add_action('plugins_loaded', [$this, 'init_plugin']);
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }

    public function init_plugin() {
        // Admin settings always
        if (is_admin()) {
            new DDL_Pro_Admin_Settings();
        }

        $s = get_option('ddl_pro_settings', []);
        if (empty($s['enabled'])) return;

        // Core always loads when enabled
        new DDL_Pro_DataLayer_Core();
        new DDL_Pro_Cookie_Manager();
        new DDL_Pro_Event_Match_Quality();

        // Toggleable modules
        if (!empty($s['ecommerce_events']))      new DDL_Pro_Ecommerce_Events();
        if (!empty($s['remarketing_events']))     new DDL_Pro_Remarketing_Events();
        if (!empty($s['local_service_events']))   new DDL_Pro_Local_Service_Events();
        if (!empty($s['form_events']))            new DDL_Pro_Form_Events();
        if (!empty($s['b2b_events']))             new DDL_Pro_B2B_Events();
        if (!empty($s['conversion_api']))         new DDL_Pro_Conversion_API();
        if (!empty($s['server_side_tracking']))   new DDL_Pro_Server_Side_Tracking();
    }

    public function activate() {
        $defaults = [
            'enabled'               => 1,
            'gtm_id'                => '',
            'ga4_measurement_id'    => '',
            'ga4_api_secret'        => '',
            'google_ads_id'         => '',
            'server_container_url'  => '',
            'business_vertical'     => 'retail',
            'remarketing_id_type'   => 'sku_or_id',
            'consent_mode'          => 'granted',
            // Module toggles — all ON by default (1-click)
            'ecommerce_events'      => 1,
            'remarketing_events'    => 1,
            'local_service_events'  => 1,
            'form_events'           => 1,
            'b2b_events'            => 1,
            'conversion_api'        => 1,
            'server_side_tracking'  => 1,
            'event_match_quality'   => 1,
            'cookie_management'     => 1,
        ];
        if (!get_option('ddl_pro_settings')) {
            update_option('ddl_pro_settings', $defaults);
        }
        flush_rewrite_rules();
    }

    public function deactivate() {
        flush_rewrite_rules();
    }
}

Developer_DataLayer_Pro::instance();
