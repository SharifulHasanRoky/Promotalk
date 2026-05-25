<?php
/**
 * Admin Settings Page
 * Simple UI with niche headings (Ecommerce, Local Service, B2B, etc.)
 * and ON/OFF toggle switches for each module. 1-click activation.
 */

if (!defined('ABSPATH')) exit;

class DDL_Pro_Admin_Settings {

    private $settings;

    public function __construct() {
        $this->settings = get_option('ddl_pro_settings', []);
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function add_menu() {
        add_menu_page(
            'DataLayer Pro',
            'DataLayer Pro',
            'manage_options',
            'ddl-pro-settings',
            [$this, 'render_page'],
            'dashicons-chart-area',
            80
        );
    }

    public function register_settings() {
        register_setting('ddl_pro_settings_group', 'ddl_pro_settings', [
            'sanitize_callback' => [$this, 'sanitize_settings'],
        ]);
    }

    public function sanitize_settings($input) {
        $toggles = [
            'enabled', 'ecommerce_events', 'remarketing_events',
            'local_service_events', 'form_events', 'b2b_events',
            'conversion_api', 'server_side_tracking', 'event_match_quality', 'cookie_management',
        ];
        foreach ($toggles as $key) {
            $input[$key] = !empty($input[$key]) ? 1 : 0;
        }
        $input['gtm_id'] = sanitize_text_field($input['gtm_id'] ?? '');
        $input['ga4_measurement_id'] = sanitize_text_field($input['ga4_measurement_id'] ?? '');
        $input['ga4_api_secret'] = sanitize_text_field($input['ga4_api_secret'] ?? '');
        $input['google_ads_id'] = sanitize_text_field($input['google_ads_id'] ?? '');
        $input['server_container_url'] = esc_url_raw($input['server_container_url'] ?? '');
        $input['business_vertical'] = sanitize_text_field($input['business_vertical'] ?? 'retail');
        $input['remarketing_id_type'] = sanitize_text_field($input['remarketing_id_type'] ?? 'sku_or_id');
        $input['consent_mode'] = sanitize_text_field($input['consent_mode'] ?? 'granted');
        return $input;
    }

    public function enqueue_admin_assets($hook) {
        if ($hook !== 'toplevel_page_ddl-pro-settings') return;
        wp_enqueue_style('ddl-pro-admin', DDL_PRO_URL . 'assets/css/admin.css', [], DDL_PRO_VERSION);
    }

    public function render_page() {
        $s = $this->settings;
        ?>
        <div class="wrap ddl-pro-wrap">
            <h1>Developer DataLayer Pro</h1>
            <p class="ddl-subtitle">One-click GA4 DataLayer for all your tracking needs. Toggle modules ON/OFF below.</p>

            <form method="post" action="options.php">
                <?php settings_fields('ddl_pro_settings_group'); ?>

                <!-- MASTER SWITCH -->
                <div class="ddl-card ddl-card-master">
                    <div class="ddl-card-header">
                        <h2>Master Switch</h2>
                        <?php $this->render_toggle('enabled', $s); ?>
                    </div>
                    <p>Enable or disable the entire DataLayer output. When ON, all active modules will fire.</p>
                </div>

                <!-- CONFIGURATION -->
                <div class="ddl-card">
                    <div class="ddl-card-header"><h2>Configuration</h2></div>
                    <table class="form-table">
                        <tr>
                            <th>GTM Container ID</th>
                            <td><input type="text" name="ddl_pro_settings[gtm_id]" value="<?php echo esc_attr($s['gtm_id'] ?? ''); ?>" placeholder="GTM-XXXXXXX" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th>GA4 Measurement ID</th>
                            <td><input type="text" name="ddl_pro_settings[ga4_measurement_id]" value="<?php echo esc_attr($s['ga4_measurement_id'] ?? ''); ?>" placeholder="G-XXXXXXXXXX" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th>GA4 API Secret</th>
                            <td><input type="text" name="ddl_pro_settings[ga4_api_secret]" value="<?php echo esc_attr($s['ga4_api_secret'] ?? ''); ?>" placeholder="For Measurement Protocol" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th>Google Ads ID</th>
                            <td><input type="text" name="ddl_pro_settings[google_ads_id]" value="<?php echo esc_attr($s['google_ads_id'] ?? ''); ?>" placeholder="AW-XXXXXXXXX" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th>Server Container URL</th>
                            <td><input type="url" name="ddl_pro_settings[server_container_url]" value="<?php echo esc_attr($s['server_container_url'] ?? ''); ?>" placeholder="https://sgtm.yourdomain.com" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th>Business Vertical</th>
                            <td>
                                <select name="ddl_pro_settings[business_vertical]">
                                    <option value="retail" <?php selected($s['business_vertical'] ?? '', 'retail'); ?>>Retail / Ecommerce</option>
                                    <option value="education" <?php selected($s['business_vertical'] ?? '', 'education'); ?>>Education</option>
                                    <option value="hotels_rentals" <?php selected($s['business_vertical'] ?? '', 'hotels_rentals'); ?>>Hotels & Rentals</option>
                                    <option value="jobs" <?php selected($s['business_vertical'] ?? '', 'jobs'); ?>>Jobs</option>
                                    <option value="local" <?php selected($s['business_vertical'] ?? '', 'local'); ?>>Local Deals</option>
                                    <option value="real_estate" <?php selected($s['business_vertical'] ?? '', 'real_estate'); ?>>Real Estate</option>
                                    <option value="travel" <?php selected($s['business_vertical'] ?? '', 'travel'); ?>>Travel</option>
                                    <option value="custom" <?php selected($s['business_vertical'] ?? '', 'custom'); ?>>Custom</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Product ID Type (Remarketing)</th>
                            <td>
                                <select name="ddl_pro_settings[remarketing_id_type]">
                                    <option value="sku_or_id" <?php selected($s['remarketing_id_type'] ?? '', 'sku_or_id'); ?>>SKU (fallback to ID)</option>
                                    <option value="sku" <?php selected($s['remarketing_id_type'] ?? '', 'sku'); ?>>SKU Only</option>
                                    <option value="id" <?php selected($s['remarketing_id_type'] ?? '', 'id'); ?>>Product ID Only</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Consent Mode Default</th>
                            <td>
                                <select name="ddl_pro_settings[consent_mode]">
                                    <option value="granted" <?php selected($s['consent_mode'] ?? '', 'granted'); ?>>Granted (all consents)</option>
                                    <option value="denied" <?php selected($s['consent_mode'] ?? '', 'denied'); ?>>Denied (wait for consent)</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- ECOMMERCE -->
                <div class="ddl-card">
                    <div class="ddl-card-header">
                        <h2>Ecommerce Events</h2>
                        <?php $this->render_toggle('ecommerce_events', $s); ?>
                    </div>
                    <p class="ddl-desc">GA4 ecommerce: <code>view_item</code>, <code>view_item_list</code>, <code>select_item</code>, <code>add_to_cart</code>, <code>remove_from_cart</code>, <code>view_cart</code>, <code>begin_checkout</code>, <code>add_shipping_info</code>, <code>add_payment_info</code>, <code>purchase</code>, <code>refund</code></p>
                    <p class="ddl-note">All product data (name, price, category, brand, variant) is dynamically pulled from WooCommerce.</p>
                </div>

                <!-- GOOGLE ADS REMARKETING -->
                <div class="ddl-card">
                    <div class="ddl-card-header">
                        <h2>Google Ads Dynamic Remarketing</h2>
                        <?php $this->render_toggle('remarketing_events', $s); ?>
                    </div>
                    <p class="ddl-desc">Separate remarketing events: <code>search</code>, <code>view_item_list</code>, <code>view_item</code>, <code>add_to_cart</code>, <code>begin_checkout</code>, <code>purchase</code></p>
                    <p class="ddl-note">Includes <code>dynx_itemid</code>, <code>dynx_pagetype</code>, <code>dynx_totalvalue</code>, <code>ecomm_prodid</code>, <code>google_business_vertical</code> for audience building.</p>
                </div>

                <!-- LOCAL SERVICE -->
                <div class="ddl-card">
                    <div class="ddl-card-header">
                        <h2>Local Service Events</h2>
                        <?php $this->render_toggle('local_service_events', $s); ?>
                    </div>
                    <p class="ddl-desc"><code>click_to_call</code>, <code>get_directions</code>, <code>email_click</code>, <code>store_locator</code>, <code>booking_click</code>, <code>quote_request</code>, <code>chat_click</code> (WhatsApp/Messenger), <code>view_business_hours</code></p>
                    <p class="ddl-note">Auto-detects phone links, map links, booking buttons, and chat widgets.</p>
                </div>

                <!-- FORM EVENTS -->
                <div class="ddl-card">
                    <div class="ddl-card-header">
                        <h2>Form Events</h2>
                        <?php $this->render_toggle('form_events', $s); ?>
                    </div>
                    <p class="ddl-desc"><code>form_start</code>, <code>form_submit</code>, <code>generate_lead</code>, <code>form_error</code></p>
                    <p class="ddl-note">Supports Contact Form 7, Gravity Forms, WPForms, Ninja Forms, Elementor Forms, Fluent Forms, Formidable, and any native HTML form.</p>
                </div>

                <!-- B2B EVENTS -->
                <div class="ddl-card">
                    <div class="ddl-card-header">
                        <h2>B2B Events</h2>
                        <?php $this->render_toggle('b2b_events', $s); ?>
                    </div>
                    <p class="ddl-desc"><code>demo_request</code>, <code>file_download</code>, <code>pricing_page_view</code>, <code>webinar_register</code>, <code>case_study_view</code>, <code>comparison_interaction</code>, <code>calculator_result</code>, <code>video_start/complete</code></p>
                    <p class="ddl-note">Auto-detects pricing pages, demo CTAs, whitepapers, and B2B content downloads with funnel stage tracking.</p>
                </div>

                <!-- CONVERSION API -->
                <div class="ddl-card">
                    <div class="ddl-card-header">
                        <h2>Conversion API (Server-Side Events)</h2>
                        <?php $this->render_toggle('conversion_api', $s); ?>
                    </div>
                    <p class="ddl-desc">Sends events via GA4 Measurement Protocol from server. Ensures tracking works even with ad-blockers.</p>
                    <p class="ddl-note">Requires GA4 Measurement ID + API Secret above. Events: <code>purchase</code>, <code>generate_lead</code>, <code>sign_up</code>, <code>add_to_cart</code></p>
                </div>

                <!-- SERVER-SIDE TRACKING -->
                <div class="ddl-card">
                    <div class="ddl-card-header">
                        <h2>Server-Side Tracking (sGTM)</h2>
                        <?php $this->render_toggle('server_side_tracking', $s); ?>
                    </div>
                    <p class="ddl-desc">Forwards events to your server-side GTM container for enhanced data collection and first-party tracking.</p>
                    <p class="ddl-note">Requires Server Container URL above. Handles event deduplication automatically.</p>
                </div>

                <!-- EVENT MATCH QUALITY -->
                <div class="ddl-card">
                    <div class="ddl-card-header">
                        <h2>Event Match Quality</h2>
                        <?php $this->render_toggle('event_match_quality', $s); ?>
                    </div>
                    <p class="ddl-desc">Enhances every event with hashed user data (email, phone, name, address) for better attribution matching in Google Ads & GA4.</p>
                    <p class="ddl-note">SHA-256 hashed. Data sourced from logged-in users, WooCommerce billing, and form submissions.</p>
                </div>

                <!-- COOKIE MANAGEMENT -->
                <div class="ddl-card">
                    <div class="ddl-card-header">
                        <h2>Cookie Management & Consent</h2>
                        <?php $this->render_toggle('cookie_management', $s); ?>
                    </div>
                    <p class="ddl-desc">Google Consent Mode v2, first-party cookie management, <code>_gcl</code>/<code>_ga</code>/<code>_gid</code> preservation, and cross-domain linker support.</p>
                    <p class="ddl-note">Works with popular cookie consent plugins (CookieYes, Complianz, GDPR Cookie Consent, etc.)</p>
                </div>

                <?php submit_button('Save Settings'); ?>
            </form>
        </div>
        <?php
    }

    private function render_toggle($key, $settings) {
        $checked = !empty($settings[$key]) ? 'checked' : '';
        ?>
        <label class="ddl-toggle">
            <input type="hidden" name="ddl_pro_settings[<?php echo esc_attr($key); ?>]" value="0">
            <input type="checkbox" name="ddl_pro_settings[<?php echo esc_attr($key); ?>]" value="1" <?php echo $checked; ?>>
            <span class="ddl-toggle-slider"></span>
            <span class="ddl-toggle-label"><?php echo $checked ? 'ON' : 'OFF'; ?></span>
        </label>
        <?php
    }
}
