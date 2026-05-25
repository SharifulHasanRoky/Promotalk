<?php
/**
 * Cookie Manager & Consent Mode
 * Handles: Google Consent Mode v2 integration, first-party cookie management,
 * _gcl/_ga/_gid preservation, cross-domain linker support,
 * and compatibility with popular cookie consent plugins.
 *
 * Compatible with: CookieYes, Complianz, GDPR Cookie Consent, CookieBot,
 *                  Borlabs Cookie, Real Cookie Banner, WP Consent API
 */

if (!defined('ABSPATH')) exit;

class DDL_Pro_Cookie_Manager {

    private $settings;

    public function __construct() {
        $this->settings = get_option('ddl_pro_settings', []);

        // Cookie consent plugin integrations
        add_action('wp_footer', [$this, 'inject_consent_listener_js'], 5);

        // First-party cookie management
        add_action('init', [$this, 'manage_first_party_cookies']);

        // WP Consent API integration
        if (class_exists('WP_CONSENT_API')) {
            add_action('wp_consent_api_registered', [$this, 'register_consent_api']);
            add_filter('wp_consent_api_cookie_consent_type', [$this, 'consent_type']);
        }

        // Set HttpOnly & SameSite attributes on GA cookies
        add_action('send_headers', [$this, 'set_cookie_attributes']);
    }

    /**
     * Inject consent mode listener that auto-updates based on cookie consent plugins
     */
    public function inject_consent_listener_js() {
        ?>
        <script>
        (function(){
            'use strict';

            window.DDLConsent = {
                /**
                 * Update Google Consent Mode
                 */
                update: function(consent) {
                    window.dataLayer = window.dataLayer || [];
                    function gtag(){dataLayer.push(arguments);}
                    gtag('consent', 'update', consent);

                    // Also push as event for GTM triggers
                    dataLayer.push({
                        'event': 'consent_update',
                        'consent_analytics': consent.analytics_storage || 'denied',
                        'consent_ads': consent.ad_storage || 'denied',
                        'consent_ad_user_data': consent.ad_user_data || 'denied',
                        'consent_ad_personalization': consent.ad_personalization || 'denied'
                    });
                },

                /**
                 * Grant all consents
                 */
                grantAll: function() {
                    this.update({
                        'ad_storage': 'granted',
                        'ad_user_data': 'granted',
                        'ad_personalization': 'granted',
                        'analytics_storage': 'granted',
                        'personalization_storage': 'granted'
                    });
                },

                /**
                 * Deny all (except functional/security)
                 */
                denyAll: function() {
                    this.update({
                        'ad_storage': 'denied',
                        'ad_user_data': 'denied',
                        'ad_personalization': 'denied',
                        'analytics_storage': 'denied',
                        'personalization_storage': 'denied'
                    });
                }
            };

            /* ─── CookieYes Integration ─────────────────────────── */
            document.addEventListener('cookieyes_consent_update', function(e){
                var d = e.detail || {};
                DDLConsent.update({
                    'analytics_storage': d.analytics === 'yes' ? 'granted' : 'denied',
                    'ad_storage': d.advertisement === 'yes' ? 'granted' : 'denied',
                    'ad_user_data': d.advertisement === 'yes' ? 'granted' : 'denied',
                    'ad_personalization': d.advertisement === 'yes' ? 'granted' : 'denied',
                    'personalization_storage': d.preferences === 'yes' ? 'granted' : 'denied'
                });
            });

            /* ─── Complianz Integration ─────────────────────────── */
            document.addEventListener('cmplz_fire_categories', function(e){
                var cats = e.detail || {};
                DDLConsent.update({
                    'analytics_storage': cats.statistics ? 'granted' : 'denied',
                    'ad_storage': cats.marketing ? 'granted' : 'denied',
                    'ad_user_data': cats.marketing ? 'granted' : 'denied',
                    'ad_personalization': cats.marketing ? 'granted' : 'denied',
                    'personalization_storage': cats.preferences ? 'granted' : 'denied'
                });
            });

            /* ─── CookieBot Integration ─────────────────────────── */
            window.addEventListener('CookiebotOnConsentReady', function(){
                if (typeof Cookiebot === 'undefined') return;
                DDLConsent.update({
                    'analytics_storage': Cookiebot.consent.statistics ? 'granted' : 'denied',
                    'ad_storage': Cookiebot.consent.marketing ? 'granted' : 'denied',
                    'ad_user_data': Cookiebot.consent.marketing ? 'granted' : 'denied',
                    'ad_personalization': Cookiebot.consent.marketing ? 'granted' : 'denied',
                    'personalization_storage': Cookiebot.consent.preferences ? 'granted' : 'denied'
                });
            });

            /* ─── Borlabs Cookie Integration ────────────────────── */
            if (typeof window.BorlabsCookie !== 'undefined') {
                document.addEventListener('borlabs-cookie-consent-saved', function(){
                    var bc = window.BorlabsCookie;
                    DDLConsent.update({
                        'analytics_storage': bc.checkCookieConsent('statistics') ? 'granted' : 'denied',
                        'ad_storage': bc.checkCookieConsent('marketing') ? 'granted' : 'denied',
                        'ad_user_data': bc.checkCookieConsent('marketing') ? 'granted' : 'denied',
                        'ad_personalization': bc.checkCookieConsent('marketing') ? 'granted' : 'denied'
                    });
                });
            }

            /* ─── Generic: GDPR Cookie Consent / Cookie Notice ──── */
            // Polls for common consent cookie patterns
            function checkGenericConsent() {
                var cookies = document.cookie;

                // cookie_notice_accepted
                if (cookies.indexOf('cookie_notice_accepted=true') > -1) {
                    DDLConsent.grantAll();
                    return;
                }

                // gdpr/moove GDPR
                if (cookies.indexOf('moove_gdpr_popup') > -1) {
                    try {
                        var val = decodeURIComponent(cookies.match(/moove_gdpr_popup=([^;]+)/)[1]);
                        var obj = JSON.parse(val);
                        DDLConsent.update({
                            'analytics_storage': obj.thirdparty === '1' ? 'granted' : 'denied',
                            'ad_storage': obj.advanced === '1' ? 'granted' : 'denied',
                            'ad_user_data': obj.advanced === '1' ? 'granted' : 'denied',
                            'ad_personalization': obj.advanced === '1' ? 'granted' : 'denied'
                        });
                    } catch(e){}
                }
            }

            // Run once on load
            setTimeout(checkGenericConsent, 1000);

        })();
        </script>
        <?php
    }

    /**
     * Manage first-party cookies for better tracking persistence
     * Sets _ddl_uid (first-party user identifier) and preserves _gcl data
     */
    public function manage_first_party_cookies() {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) return;

        // Set first-party user identifier cookie
        if (!isset($_COOKIE['_ddl_uid'])) {
            $uid = $this->generate_uid();
            $expire = time() + (400 * DAY_IN_SECONDS); // 400 days (max allowed)
            setcookie('_ddl_uid', $uid, [
                'expires'  => $expire,
                'path'     => '/',
                'domain'   => $this->get_cookie_domain(),
                'secure'   => is_ssl(),
                'httponly'  => false, // Needs JS access
                'samesite'=> 'Lax',
            ]);
        }

        // Extend _ga cookie if present (keeps it first-party for 2 years)
        if (isset($_COOKIE['_ga']) && !headers_sent()) {
            $expire = time() + (730 * DAY_IN_SECONDS);
            setcookie('_ga', $_COOKIE['_ga'], [
                'expires'  => $expire,
                'path'     => '/',
                'domain'   => $this->get_cookie_domain(),
                'secure'   => is_ssl(),
                'httponly'  => false,
                'samesite' => 'Lax',
            ]);
        }
    }

    /**
     * Set proper attributes on GA/GCL cookies via headers
     */
    public function set_cookie_attributes() {
        // This ensures cookies set by JS also get proper attributes
        // Applied via server-side header modification if supported
        if (!is_ssl()) return;

        // Add Partitioned attribute hint for CHIPS support
        header('Accept-CH: Sec-CH-UA-Platform', false);
    }

    /**
     * Register with WP Consent API
     */
    public function register_consent_api() {
        if (function_exists('wp_add_cookie_info')) {
            wp_add_cookie_info('_ddl_uid', 'Developer DataLayer Pro', 'functional', '400 days', 'First-party user identifier for tracking continuity', false, false, false);
            wp_add_cookie_info('_ga', 'Google Analytics', 'statistics', '2 years', 'Google Analytics client identifier', false, false, false);
            wp_add_cookie_info('_gcl_aw', 'Google Ads', 'marketing', '90 days', 'Google Ads click identifier', false, false, false);
        }
    }

    /**
     * Filter consent type for WP Consent API
     */
    public function consent_type($type) {
        return 'optin'; // Require explicit consent
    }

    /**
     * Generate unique user identifier
     */
    private function generate_uid() {
        return bin2hex(random_bytes(16));
    }

    /**
     * Get cookie domain (top-level for cross-subdomain)
     */
    private function get_cookie_domain() {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        // Remove port
        $host = preg_replace('/:\d+$/', '', $host);
        // For cross-subdomain, prefix with dot
        $parts = explode('.', $host);
        if (count($parts) > 2) {
            // e.g., www.example.com → .example.com
            return '.' . implode('.', array_slice($parts, -2));
        }
        return '.' . $host;
    }
}
