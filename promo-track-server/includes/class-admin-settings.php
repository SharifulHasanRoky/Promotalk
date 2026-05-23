<?php
/**
 * Admin Settings Page - PromoTrack Server
 * Sections: Google Ads, Facebook, Cookies, Event Match Quality, Events
 * Simple toggle ON/OFF with checkboxes for cookie selection
 */

if (!defined('ABSPATH')) exit;

class PTS_Admin_Settings {

    private $s;

    public function __construct() {
        $this->s = get_option('pts_settings', []);
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
    }

    public function add_menu() {
        add_menu_page('PromoTrack Server', 'PromoTrack', 'manage_options', 'pts-settings', [$this, 'page'], 'dashicons-cloud', 78);
    }

    public function register() {
        register_setting('pts_group', 'pts_settings', ['sanitize_callback' => [$this, 'sanitize']]);
    }

    public function sanitize($i) {
        $toggles = [
            'enabled','google_ads_enabled','facebook_enabled',
            'cookie_fbp','cookie_fbc','cookie_gcl_aw','cookie_gcl_gs','cookie_ga','cookie_external_id',
            'emq_email','emq_phone','emq_name','emq_address','emq_external_id','emq_fbp','emq_fbc','emq_user_agent','emq_ip','emq_click_id',
            'track_page_view','track_view_content','track_add_to_cart','track_initiate_checkout','track_purchase','track_lead','track_search','track_contact',
        ];
        foreach ($toggles as $k) $i[$k] = !empty($i[$k]) ? 1 : 0;

        $texts = ['google_ads_id','google_ads_label','ga4_measurement_id','ga4_api_secret','gtm_server_url','fb_pixel_id','fb_access_token','fb_test_event_code'];
        foreach ($texts as $k) $i[$k] = sanitize_text_field($i[$k] ?? '');

        $i['cookie_duration'] = absint($i['cookie_duration'] ?? 390);
        return $i;
    }

    public function assets($hook) {
        if ($hook !== 'toplevel_page_pts-settings') return;
        wp_enqueue_style('pts-admin', PTS_URL . 'assets/css/admin.css', [], PTS_VERSION);
    }

    public function page() {
        $s = $this->s;
        ?>
        <div class="wrap pts-wrap">
            <h1>PromoTrack Server</h1>
            <p class="pts-sub">Free Server-Side Tracking — Google Ads + Facebook Ads — Event Match Quality 10/10</p>

            <form method="post" action="options.php">
                <?php settings_fields('pts_group'); ?>

                <!-- MASTER -->
                <div class="pts-card pts-master">
                    <div class="pts-card-head"><h2>Master Switch</h2><?php $this->toggle('enabled'); ?></div>
                    <p>Enable/disable all tracking. When ON, configured platforms will fire events.</p>
                </div>

                <!-- GOOGLE ADS -->
                <div class="pts-card">
                    <div class="pts-card-head"><h2>Google Ads — Conversion & Enhanced Conversions</h2><?php $this->toggle('google_ads_enabled'); ?></div>
                    <table class="form-table">
                        <tr><th>Google Ads Conversion ID</th><td><input type="text" name="pts_settings[google_ads_id]" value="<?php echo esc_attr($s['google_ads_id'] ?? ''); ?>" placeholder="AW-123456789" class="regular-text"></td></tr>
                        <tr><th>Conversion Label</th><td><input type="text" name="pts_settings[google_ads_label]" value="<?php echo esc_attr($s['google_ads_label'] ?? ''); ?>" placeholder="AbCdEfGh123" class="regular-text"><p class="description">For purchase conversion. Leave blank to use value-based bidding without label.</p></td></tr>
                        <tr><th>GA4 Measurement ID</th><td><input type="text" name="pts_settings[ga4_measurement_id]" value="<?php echo esc_attr($s['ga4_measurement_id'] ?? ''); ?>" placeholder="G-XXXXXXXXXX" class="regular-text"></td></tr>
                        <tr><th>GA4 API Secret</th><td><input type="text" name="pts_settings[ga4_api_secret]" value="<?php echo esc_attr($s['ga4_api_secret'] ?? ''); ?>" placeholder="For Measurement Protocol (server-side)" class="regular-text"></td></tr>
                        <tr><th>GTM Server Container URL</th><td><input type="url" name="pts_settings[gtm_server_url]" value="<?php echo esc_attr($s['gtm_server_url'] ?? ''); ?>" placeholder="https://sgtm.yourdomain.com" class="regular-text"><p class="description">Optional: for server-side GTM routing. Leave blank to use Google directly.</p></td></tr>
                    </table>
                    <div class="pts-features">
                        <span class="pts-badge">✓ Conversion Tracking</span>
                        <span class="pts-badge">✓ Enhanced Conversions</span>
                        <span class="pts-badge">✓ Server-Side via Measurement Protocol</span>
                        <span class="pts-badge">✓ GCLID Capture</span>
                        <span class="pts-badge">✓ Consent Mode v2</span>
                    </div>
                </div>

                <!-- FACEBOOK -->
                <div class="pts-card">
                    <div class="pts-card-head"><h2>Facebook — Pixel + Conversion API</h2><?php $this->toggle('facebook_enabled'); ?></div>
                    <table class="form-table">
                        <tr><th>Pixel ID</th><td><input type="text" name="pts_settings[fb_pixel_id]" value="<?php echo esc_attr($s['fb_pixel_id'] ?? ''); ?>" placeholder="123456789012345" class="regular-text"></td></tr>
                        <tr><th>Conversion API Access Token</th><td><input type="text" name="pts_settings[fb_access_token]" value="<?php echo esc_attr($s['fb_access_token'] ?? ''); ?>" placeholder="EAAxxxxxxx..." class="regular-text"><p class="description">Get from Events Manager → Settings → Generate Access Token</p></td></tr>
                        <tr><th>Test Event Code</th><td><input type="text" name="pts_settings[fb_test_event_code]" value="<?php echo esc_attr($s['fb_test_event_code'] ?? ''); ?>" placeholder="TEST12345 (optional, for testing)" class="regular-text"></td></tr>
                    </table>
                    <div class="pts-features">
                        <span class="pts-badge">✓ Pixel (Browser)</span>
                        <span class="pts-badge">✓ Conversion API (Server)</span>
                        <span class="pts-badge">✓ Event Deduplication</span>
                        <span class="pts-badge">✓ EMQ Score 10/10</span>
                        <span class="pts-badge">✓ All Ecom Events</span>
                        <span class="pts-badge">✓ Local Service Events</span>
                    </div>
                </div>

                <!-- COOKIES -->
                <div class="pts-card">
                    <div class="pts-card-head"><h2>Cookie Management</h2></div>
                    <p>Select which cookies to manage for better tracking accuracy & attribution:</p>
                    <table class="form-table pts-cookie-table">
                        <tr><th><?php $this->check('cookie_fbp'); ?> <code>_fbp</code></th><td>Facebook Browser ID — identifies browser across sessions</td></tr>
                        <tr><th><?php $this->check('cookie_fbc'); ?> <code>_fbc</code></th><td>Facebook Click ID — captures fbclid from URL for attribution</td></tr>
                        <tr><th><?php $this->check('cookie_gcl_aw'); ?> <code>_gcl_aw</code></th><td>Google Ads Click ID — captures gclid for conversion attribution</td></tr>
                        <tr><th><?php $this->check('cookie_gcl_gs'); ?> <code>_gcl_gs</code></th><td>Google Ads GS cookie — additional Google click data</td></tr>
                        <tr><th><?php $this->check('cookie_ga'); ?> <code>_ga</code></th><td>Google Analytics Client ID — extends cookie lifetime to first-party</td></tr>
                        <tr><th><?php $this->check('cookie_external_id'); ?> <code>external_id</code></th><td>Unique visitor ID — sent to both Google & Facebook for cross-device matching</td></tr>
                    </table>
                    <table class="form-table">
                        <tr><th>Cookie Duration (days)</th><td><input type="number" name="pts_settings[cookie_duration]" value="<?php echo esc_attr($s['cookie_duration'] ?? 390); ?>" min="1" max="400" style="width:80px"> <span class="description">Max 400 days (browser limit)</span></td></tr>
                    </table>
                </div>

                <!-- EVENT MATCH QUALITY -->
                <div class="pts-card">
                    <div class="pts-card-head"><h2>Event Match Quality — Score 10/10</h2></div>
                    <p>All parameters below are sent (hashed) with every server event for maximum matching:</p>
                    <table class="form-table pts-emq-table">
                        <tr><th><?php $this->check('emq_email'); ?> Email (SHA-256)</th><td>From logged-in user, WooCommerce billing, or form submissions</td></tr>
                        <tr><th><?php $this->check('emq_phone'); ?> Phone (SHA-256)</th><td>From billing data or form submissions</td></tr>
                        <tr><th><?php $this->check('emq_name'); ?> First + Last Name (SHA-256)</th><td>From user profile or billing data</td></tr>
                        <tr><th><?php $this->check('emq_address'); ?> Address (City, State, Zip, Country)</th><td>From WooCommerce billing address</td></tr>
                        <tr><th><?php $this->check('emq_external_id'); ?> External ID</th><td>Unique visitor identifier (first-party cookie)</td></tr>
                        <tr><th><?php $this->check('emq_fbp'); ?> _fbp Cookie</th><td>Facebook Browser Parameter</td></tr>
                        <tr><th><?php $this->check('emq_fbc'); ?> _fbc Cookie</th><td>Facebook Click ID (from fbclid)</td></tr>
                        <tr><th><?php $this->check('emq_user_agent'); ?> User Agent</th><td>Browser user agent string</td></tr>
                        <tr><th><?php $this->check('emq_ip'); ?> Client IP Address</th><td>Real visitor IP (behind proxies/CDN)</td></tr>
                        <tr><th><?php $this->check('emq_click_id'); ?> Click IDs (gclid/fbclid/wbraid)</th><td>Ad platform click identifiers from URL</td></tr>
                    </table>
                    <div class="pts-emq-score">
                        <strong>Expected Score:</strong>
                        <span class="pts-score">10/10</span>
                        <span class="pts-score-note">All parameters enabled = maximum match quality</span>
                    </div>
                </div>

                <!-- EVENTS -->
                <div class="pts-card">
                    <div class="pts-card-head"><h2>Events to Track</h2></div>
                    <p>Toggle which events fire on both Google Ads & Facebook:</p>
                    <table class="form-table pts-events-table">
                        <tr><th><?php $this->check('track_page_view'); ?> PageView</th><td>Every page load</td></tr>
                        <tr><th><?php $this->check('track_view_content'); ?> ViewContent / view_item</th><td>Product page views</td></tr>
                        <tr><th><?php $this->check('track_add_to_cart'); ?> AddToCart / add_to_cart</th><td>Add to cart clicks</td></tr>
                        <tr><th><?php $this->check('track_initiate_checkout'); ?> InitiateCheckout / begin_checkout</th><td>Checkout page view</td></tr>
                        <tr><th><?php $this->check('track_purchase'); ?> Purchase / purchase</th><td>Order completed (with value & transaction ID)</td></tr>
                        <tr><th><?php $this->check('track_lead'); ?> Lead / generate_lead</th><td>Form submissions & contact actions</td></tr>
                        <tr><th><?php $this->check('track_search'); ?> Search / search</th><td>Site search queries</td></tr>
                        <tr><th><?php $this->check('track_contact'); ?> Contact / click_to_call</th><td>Phone calls, emails, WhatsApp clicks</td></tr>
                    </table>
                </div>

                <?php submit_button('Save All Settings'); ?>
            </form>
        </div>
        <?php
    }

    private function toggle($key) {
        $checked = !empty($this->s[$key]) ? 'checked' : '';
        echo '<label class="pts-toggle"><input type="hidden" name="pts_settings['.$key.']" value="0"><input type="checkbox" name="pts_settings['.$key.']" value="1" '.$checked.'><span class="pts-slider"></span></label>';
    }

    private function check($key) {
        $checked = !empty($this->s[$key]) ? 'checked' : '';
        echo '<label class="pts-check"><input type="hidden" name="pts_settings['.$key.']" value="0"><input type="checkbox" name="pts_settings['.$key.']" value="1" '.$checked.'><span class="pts-checkmark"></span></label>';
    }
}
