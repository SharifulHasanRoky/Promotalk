<?php
/**
 * B2B Events Tracking
 * Auto-detects B2B page types and interactions for SaaS/agency/service companies.
 *
 * Events: pricing_page_view, demo_page_view, case_study_view, feature_page_view,
 *         file_download, demo_request, trial_signup, webinar_register_click,
 *         comparison_interaction, calculator_result, video_start/complete,
 *         scroll_depth, generate_lead
 *
 * Each event includes funnel_stage: awareness → consideration → decision → conversion
 */

if (!defined('ABSPATH')) exit;

class DDL_Pro_B2B_Events {

    public function __construct() {
        add_action('wp_footer', [$this, 'track_b2b_page_views'], 10);
        add_action('wp_footer', [$this, 'inject_b2b_tracking_js'], 20);
        add_action('user_register', [$this, 'on_user_register'], 10, 1);
    }

    /* ────────────────────────────────────────────────────────────────────────
       B2B PAGE VIEW DETECTION (server-rendered)
    ──────────────────────────────────────────────────────────────────────── */

    public function track_b2b_page_views() {
        if (!is_page() && !is_single()) return;

        global $post;
        if (!$post) return;

        $slug  = $post->post_name ?? '';
        $title = strtolower(get_the_title());
        $event = $this->detect_page($slug, $title);

        if (!$event) return;
        ?>
        <script>
        window.dataLayer=window.dataLayer||[];
        window.dataLayer.push({
            'event':'<?php echo esc_js($event['event']); ?>',
            'event_category':'b2b',
            'content_type':'<?php echo esc_js($event['type']); ?>',
            'funnel_stage':'<?php echo esc_js($event['stage']); ?>',
            'page_title':'<?php echo esc_js(get_the_title()); ?>',
            'page_path':'<?php echo esc_js($_SERVER['REQUEST_URI'] ?? '/'); ?>'
        });
        </script>
        <?php
    }

    /* ────────────────────────────────────────────────────────────────────────
       JS-BASED INTERACTION TRACKING
    ──────────────────────────────────────────────────────────────────────── */

    public function inject_b2b_tracking_js() {
        ?>
        <script>
        (function($){
            'use strict';
            var DL = window.dataLayer = window.dataLayer || [];

            /* ─── FILE DOWNLOADS (whitepaper, ebook, PDF, etc.) ────── */
            var dlExts = ['pdf','doc','docx','xls','xlsx','ppt','pptx','zip','csv'];
            var b2bKW = ['whitepaper','white-paper','case-study','casestudy','ebook','e-book','guide','datasheet','brochure','report','playbook','template'];

            $(document).on('click', 'a[href]', function(){
                var href = (this.href || '').toLowerCase();
                var ext = href.split('.').pop().split('?')[0];
                if (dlExts.indexOf(ext) === -1) return;

                var fileName = href.split('/').pop().split('?')[0];
                var text = this.textContent.toLowerCase().trim();
                var isB2B = b2bKW.some(function(kw){ return href.indexOf(kw)>-1 || text.indexOf(kw)>-1; });

                var contentType = 'document';
                if (href.indexOf('whitepaper')>-1||href.indexOf('white-paper')>-1) contentType='whitepaper';
                else if (href.indexOf('case-study')>-1||href.indexOf('casestudy')>-1) contentType='case_study';
                else if (href.indexOf('ebook')>-1||href.indexOf('e-book')>-1) contentType='ebook';
                else if (href.indexOf('guide')>-1) contentType='guide';
                else if (href.indexOf('datasheet')>-1) contentType='datasheet';
                else if (href.indexOf('report')>-1) contentType='report';
                else if (href.indexOf('template')>-1) contentType='template';

                DL.push({
                    'event':'file_download',
                    'event_category':'b2b',
                    'file_name': fileName,
                    'file_extension': ext,
                    'content_type': contentType,
                    'is_b2b_content': isB2B,
                    'link_text': this.textContent.trim(),
                    'funnel_stage': isB2B ? 'consideration' : 'awareness'
                });

                if (isB2B) {
                    DL.push({
                        'event':'generate_lead',
                        'event_category':'b2b',
                        'lead_type': contentType+'_download'
                    });
                }
            });

            /* ─── DEMO / TRIAL CTA CLICKS ──────────────────────────── */
            $(document).on('click', [
                'a[href*="demo"],a[href*="trial"],a[href*="free-trial"]',
                '.btn-demo,.btn-trial,.cta-demo,.cta-trial',
                '[data-action="demo"],[data-action="trial"]'
            ].join(','), function(){
                var text = this.textContent.toLowerCase().trim();
                var evName = 'demo_request';
                if (text.indexOf('trial')>-1 || text.indexOf('free')>-1) evName = 'trial_signup';
                if (text.indexOf('consult')>-1) evName = 'consultation_request';

                DL.push({
                    'event': evName,
                    'event_category':'b2b',
                    'cta_text': this.textContent.trim(),
                    'cta_url': this.href || '',
                    'funnel_stage':'decision'
                });
            });

            /* ─── PRICING INTERACTIONS ─────────────────────────────── */
            $(document).on('click', '.pricing-plan .btn,.plan-card .btn,[data-plan] .btn,.pricing-cta', function(){
                var $plan = $(this).closest('[data-plan],.pricing-plan,.plan-card');
                var planName = $plan.length ? ($plan.data('plan') || $plan.find('h2,h3,.plan-name').first().text().trim()) : '';
                DL.push({
                    'event':'pricing_plan_click',
                    'event_category':'b2b',
                    'plan_name': planName,
                    'cta_text': this.textContent.trim(),
                    'funnel_stage':'decision'
                });
            });

            $(document).on('click', '.pricing-toggle,[data-billing-toggle],.billing-switch', function(){
                DL.push({
                    'event':'pricing_toggle',
                    'event_category':'b2b',
                    'billing_cycle': $(this).data('billing') || this.textContent.trim()
                });
            });

            /* ─── WEBINAR / EVENT REGISTER ─────────────────────────── */
            $(document).on('click', 'a[href*="webinar"],a[href*="event"],.webinar-register,.event-register,[data-webinar]', function(){
                DL.push({
                    'event':'webinar_register_click',
                    'event_category':'b2b',
                    'webinar_title': this.textContent.trim() || this.title || '',
                    'funnel_stage':'consideration'
                });
            });

            /* ─── COMPARISON / VS PAGES ────────────────────────────── */
            $(document).on('click', 'a[href*="compare"],a[href*="/vs"],a[href*="alternative"],.comparison-table a', function(){
                DL.push({
                    'event':'comparison_interaction',
                    'event_category':'b2b',
                    'comparison_target': this.textContent.trim(),
                    'funnel_stage':'consideration'
                });
            });

            /* ─── ROI / CALCULATOR TOOLS ───────────────────────────── */
            var calcStarted = {};
            $(document).on('focusin', '.roi-calculator,.calculator-tool,[data-calculator],.savings-calculator', function(){
                var id = this.id || 'calc';
                if (calcStarted[id]) return;
                calcStarted[id] = true;
                DL.push({'event':'calculator_start','event_category':'b2b','funnel_stage':'consideration'});
            });
            $(document).on('submit', '.roi-calculator,.calculator-tool,[data-calculator],.savings-calculator', function(){
                DL.push({'event':'calculator_result','event_category':'b2b','funnel_stage':'decision'});
            });

            /* ─── VIDEO TRACKING ───────────────────────────────────── */
            // HTML5 videos
            $('video').each(function(){
                var v = this;
                $(v).on('play', function(){
                    DL.push({'event':'video_start','event_category':'b2b','video_title': v.title||'','video_provider':'html5','funnel_stage':'awareness'});
                });
                $(v).on('ended', function(){
                    DL.push({'event':'video_complete','event_category':'b2b','video_title': v.title||'','video_provider':'html5'});
                });
            });
            // YouTube postMessage
            window.addEventListener('message', function(ev){
                try {
                    var d = JSON.parse(ev.data);
                    if (d.event === 'onStateChange') {
                        if (d.info === 1) DL.push({'event':'video_start','event_category':'b2b','video_provider':'youtube'});
                        if (d.info === 0) DL.push({'event':'video_complete','event_category':'b2b','video_provider':'youtube'});
                    }
                } catch(e){}
            });

            /* ─── SCROLL DEPTH ─────────────────────────────────────── */
            var thresholds = [25,50,75,90], fired = {};
            $(window).on('scroll', function(){
                var pct = Math.round((window.scrollY / (document.body.scrollHeight - window.innerHeight)) * 100);
                thresholds.forEach(function(t){
                    if (pct >= t && !fired[t]) {
                        fired[t] = true;
                        DL.push({
                            'event':'scroll_depth',
                            'event_category':'b2b',
                            'scroll_percentage': t,
                            'engagement_level': t>=75?'high':(t>=50?'medium':'low')
                        });
                    }
                });
            });

        })(jQuery);
        </script>
        <?php
    }

    /* ────────────────────────────────────────────────────────────────────────
       SERVER-SIDE: User registration as lead
    ──────────────────────────────────────────────────────────────────────── */

    public function on_user_register($user_id) {
        $user = get_userdata($user_id);
        if (!$user) return;

        do_action('ddl_pro_server_event', 'sign_up', [
            'user_id'         => (string) $user_id,
            'user_email'      => $user->user_email,
            'user_first_name' => $user->first_name,
            'user_last_name'  => $user->last_name,
            'lead_type'       => 'registration',
            'funnel_stage'    => 'conversion',
        ]);
    }

    /* ────────────────────────────────────────────────────────────────────────
       HELPER: Detect B2B page type from slug/title
    ──────────────────────────────────────────────────────────────────────── */

    private function detect_page($slug, $title) {
        $map = [
            ['keys' => ['pricing', 'plans', 'packages'],      'event' => 'pricing_page_view',   'type' => 'pricing',      'stage' => 'decision'],
            ['keys' => ['demo', 'request-demo', 'book-demo'], 'event' => 'demo_page_view',      'type' => 'demo',         'stage' => 'decision'],
            ['keys' => ['case-stud', 'casestud', 'success-stor'], 'event' => 'case_study_view', 'type' => 'case_study',   'stage' => 'consideration'],
            ['keys' => ['features', 'feature'],               'event' => 'feature_page_view',   'type' => 'features',     'stage' => 'awareness'],
            ['keys' => ['integration', 'connect'],            'event' => 'integrations_view',   'type' => 'integrations', 'stage' => 'consideration'],
            ['keys' => ['testimonial', 'review', 'customer-stor'], 'event' => 'testimonials_view', 'type' => 'social_proof', 'stage' => 'consideration'],
            ['keys' => ['partner', 'reseller'],               'event' => 'partners_view',       'type' => 'partners',     'stage' => 'consideration'],
            ['keys' => ['about', 'about-us', 'our-team'],    'event' => 'about_page_view',     'type' => 'company',      'stage' => 'awareness'],
            ['keys' => ['resource', 'knowledge', 'library'],  'event' => 'resources_view',      'type' => 'resources',    'stage' => 'awareness'],
            ['keys' => ['compare', 'vs', 'alternative'],      'event' => 'comparison_view',     'type' => 'comparison',   'stage' => 'consideration'],
            ['keys' => ['webinar', 'event', 'workshop'],      'event' => 'webinar_page_view',   'type' => 'webinar',      'stage' => 'consideration'],
        ];

        foreach ($map as $m) {
            foreach ($m['keys'] as $kw) {
                if (strpos($slug, $kw) !== false || strpos($title, $kw) !== false) {
                    return ['event' => $m['event'], 'type' => $m['type'], 'stage' => $m['stage']];
                }
            }
        }

        return null;
    }
}
