<?php
/**
 * Form Events Tracking
 * Tracks form_start, form_submit, generate_lead across all popular form plugins
 * and native HTML forms. Extracts user data for enhanced conversions.
 *
 * Supported: Contact Form 7, Gravity Forms, WPForms, Ninja Forms,
 *            Elementor Forms, Fluent Forms, Formidable, HubSpot, any HTML form
 */

if (!defined('ABSPATH')) exit;

class DDL_Pro_Form_Events {

    public function __construct() {
        // Plugin-specific server hooks
        add_action('wpcf7_mail_sent', [$this, 'on_cf7_submit'], 10, 1);
        add_action('gform_after_submission', [$this, 'on_gravity_submit'], 10, 2);
        add_action('wpforms_process_complete', [$this, 'on_wpforms_submit'], 10, 4);
        add_action('ninja_forms_after_submission', [$this, 'on_ninja_submit'], 10, 1);
        add_action('elementor_pro/forms/new_record', [$this, 'on_elementor_submit'], 10, 2);
        add_action('fluentform/submission_inserted', [$this, 'on_fluent_submit'], 10, 3);
        add_action('frm_after_create_entry', [$this, 'on_formidable_submit'], 10, 2);

        // JS-based tracking (form_start, submit, CF7 events)
        add_action('wp_footer', [$this, 'inject_form_tracking_js'], 20);
    }

    /* ────────────────────────────────────────────────────────────────────────
       PLUGIN-SPECIFIC SERVER HOOKS
    ──────────────────────────────────────────────────────────────────────── */

    /** Contact Form 7 */
    public function on_cf7_submit($cf) {
        $sub = WPCF7_Submission::get_instance();
        $posted = $sub ? $sub->get_posted_data() : [];

        $this->fire_lead('contact_form_7', (string)$cf->id(), $cf->title(), $posted);
    }

    /** Gravity Forms */
    public function on_gravity_submit($entry, $form) {
        $fields = [];
        foreach ($form['fields'] as $f) {
            $val = $entry[$f->id] ?? '';
            if (!$val) continue;
            if ($f->type === 'email') $fields['email'] = $val;
            if ($f->type === 'phone') $fields['phone'] = $val;
            if ($f->type === 'name') {
                $fields['first_name'] = $entry[$f->id . '.3'] ?? '';
                $fields['last_name']  = $entry[$f->id . '.6'] ?? '';
            }
        }
        $this->fire_lead('gravity_forms', (string)$form['id'], $form['title'] ?? '', $fields);
    }

    /** WPForms */
    public function on_wpforms_submit($fields, $entry, $form_data, $entry_id) {
        $title = $form_data['settings']['form_title'] ?? '';
        $mapped = [];
        foreach ($fields as $f) {
            $t = $f['type'] ?? '';
            $v = $f['value'] ?? '';
            if (!$v) continue;
            if ($t === 'email') $mapped['email'] = $v;
            if ($t === 'phone') $mapped['phone'] = $v;
            if ($t === 'name') {
                $parts = explode(' ', $v, 2);
                $mapped['first_name'] = $parts[0] ?? '';
                $mapped['last_name']  = $parts[1] ?? '';
            }
        }
        $this->fire_lead('wpforms', (string)($form_data['id'] ?? ''), $title, $mapped);
    }

    /** Ninja Forms */
    public function on_ninja_submit($form_data) {
        $form_id = $form_data['form_id'] ?? '';
        $title = '';
        if (function_exists('Ninja_Forms')) {
            $form = Ninja_Forms()->form($form_id)->get();
            $title = $form->get_setting('title') ?? '';
        }
        $mapped = [];
        foreach ($form_data['fields'] ?? [] as $f) {
            $k = strtolower($f['key'] ?? '');
            $v = $f['value'] ?? '';
            if (!$v) continue;
            if (strpos($k, 'email') !== false) $mapped['email'] = $v;
            if (strpos($k, 'phone') !== false) $mapped['phone'] = $v;
            if (strpos($k, 'first') !== false)  $mapped['first_name'] = $v;
            if (strpos($k, 'last') !== false)   $mapped['last_name'] = $v;
        }
        $this->fire_lead('ninja_forms', (string)$form_id, $title, $mapped);
    }

    /** Elementor Forms */
    public function on_elementor_submit($record, $handler) {
        $name = $record->get_form_settings('form_name');
        $raw = $record->get('fields');
        $mapped = [];
        foreach ($raw as $id => $f) {
            $v = $f['value'] ?? '';
            $t = $f['type'] ?? '';
            if (!$v) continue;
            if ($t === 'email') $mapped['email'] = $v;
            if ($t === 'tel')   $mapped['phone'] = $v;
            if ($id === 'name' || $id === 'first_name') $mapped['first_name'] = $v;
            if ($id === 'last_name') $mapped['last_name'] = $v;
        }
        $this->fire_lead('elementor_forms', $record->get_form_settings('id') ?? '', $name, $mapped);
    }

    /** Fluent Forms */
    public function on_fluent_submit($entry_id, $form_data, $form) {
        $title = $form->title ?? '';
        $mapped = [];
        if (is_array($form_data)) {
            foreach ($form_data as $k => $v) {
                if (!is_string($v) || !$v) continue;
                if (strpos($k, 'email') !== false) $mapped['email'] = $v;
                if (strpos($k, 'phone') !== false) $mapped['phone'] = $v;
            }
            if (isset($form_data['names']) && is_array($form_data['names'])) {
                $mapped['first_name'] = $form_data['names']['first_name'] ?? '';
                $mapped['last_name']  = $form_data['names']['last_name'] ?? '';
            }
        }
        $this->fire_lead('fluent_forms', (string)($form->id ?? ''), $title, $mapped);
    }

    /** Formidable Forms */
    public function on_formidable_submit($entry_id, $form_id) {
        $title = '';
        if (class_exists('FrmForm')) {
            $form = FrmForm::getOne($form_id);
            $title = $form->name ?? '';
        }
        $this->fire_lead('formidable_forms', (string)$form_id, $title, []);
    }

    /* ────────────────────────────────────────────────────────────────────────
       JS-BASED TRACKING (form_start, form_submit, CF7 client events)
    ──────────────────────────────────────────────────────────────────────── */

    public function inject_form_tracking_js() {
        ?>
        <script>
        (function($){
            'use strict';
            var DL = window.dataLayer = window.dataLayer || [];
            var tracked = new WeakSet();

            function formType(name) {
                if (!name) return 'general';
                var n = name.toLowerCase();
                if (n.indexOf('contact') > -1) return 'contact';
                if (n.indexOf('quote') > -1 || n.indexOf('estimate') > -1) return 'quote_request';
                if (n.indexOf('newsletter') > -1 || n.indexOf('subscribe') > -1) return 'newsletter';
                if (n.indexOf('demo') > -1) return 'demo_request';
                if (n.indexOf('support') > -1) return 'support';
                if (n.indexOf('register') > -1 || n.indexOf('signup') > -1) return 'registration';
                if (n.indexOf('feedback') > -1) return 'feedback';
                return 'general';
            }

            function plugin(form) {
                var $f = $(form);
                if ($f.hasClass('wpcf7-form')) return 'contact_form_7';
                if ($f.closest('.gform_wrapper').length) return 'gravity_forms';
                if ($f.hasClass('wpforms-form')) return 'wpforms';
                if ($f.closest('.nf-form-content').length) return 'ninja_forms';
                if ($f.hasClass('elementor-form')) return 'elementor_forms';
                if ($f.closest('.frm_forms').length) return 'formidable';
                if ($f.hasClass('fluentform') || $f.closest('.fluentform').length) return 'fluent_forms';
                if ($f.hasClass('mc4wp-form')) return 'mailchimp';
                if ($f.hasClass('hs-form')) return 'hubspot';
                return 'html';
            }

            function formName(form) {
                var $f = $(form);
                var name = $f.attr('data-form-name') || $f.attr('aria-label') || '';
                if (name) return name;
                var $h = $f.prev('h1,h2,h3,h4,h5');
                if ($h.length) return $h.text().trim();
                var $sec = $f.closest('section,.widget,.elementor-widget');
                if ($sec.length) { var $hh = $sec.find('h1,h2,h3,h4').first(); if ($hh.length) return $hh.text().trim(); }
                var $leg = $f.find('legend,.form-title');
                if ($leg.length) return $leg.first().text().trim();
                return '';
            }

            function scanForms() {
                $('form').each(function(){
                    var form = this;
                    if (tracked.has(form)) return;
                    tracked.add(form);

                    var $f = $(form);
                    // Skip non-lead forms
                    if ($f.attr('role') === 'search') return;
                    if ($f.hasClass('cart') || $f.hasClass('checkout') || $f.hasClass('woocommerce-cart-form')) return;
                    if ($f.closest('#loginform,.login').length) return;

                    var fPlugin = plugin(form);
                    var fName = formName(form);
                    var fId = form.id || $f.attr('data-form-id') || '';

                    // form_start
                    var started = false;
                    $f.on('focusin', function(){
                        if (started) return;
                        started = true;
                        DL.push({
                            'event':'form_start',
                            'event_category':'form',
                            'form_id': fId,
                            'form_name': fName,
                            'form_plugin': fPlugin,
                            'page_location': location.href
                        });
                    });

                    // form_submit (only for plugins NOT already tracked server-side with their own JS)
                    if (['contact_form_7','gravity_forms','wpforms','ninja_forms'].indexOf(fPlugin) === -1) {
                        $f.on('submit', function(){
                            DL.push({
                                'event':'form_submit',
                                'event_category':'form',
                                'form_id': fId,
                                'form_name': fName,
                                'form_plugin': fPlugin,
                                'form_type': formType(fName),
                                'page_location': location.href
                            });
                            DL.push({
                                'event':'generate_lead',
                                'event_category':'form',
                                'lead_source': fPlugin,
                                'form_id': fId,
                                'form_name': fName
                            });
                        });
                    }
                });
            }

            /* CF7 client-side events */
            document.addEventListener('wpcf7mailsent', function(e){
                var d = e.detail;
                DL.push({
                    'event':'form_submit',
                    'event_category':'form',
                    'form_id': String(d.contactFormId),
                    'form_name': (d.apiResponse && d.apiResponse.contactFormTitle) || '',
                    'form_plugin':'contact_form_7',
                    'form_type': formType((d.apiResponse && d.apiResponse.contactFormTitle) || ''),
                    'form_status':'success'
                });
                DL.push({
                    'event':'generate_lead',
                    'event_category':'form',
                    'lead_source':'contact_form_7',
                    'form_id': String(d.contactFormId)
                });
            });
            document.addEventListener('wpcf7invalid', function(e){
                DL.push({
                    'event':'form_error',
                    'event_category':'form',
                    'form_id': String(e.detail.contactFormId),
                    'form_plugin':'contact_form_7',
                    'form_status':'validation_error'
                });
            });

            /* Init + MutationObserver for dynamic forms */
            $(function(){ scanForms(); });
            if ('MutationObserver' in window) {
                new MutationObserver(function(muts){
                    var hasForm = muts.some(function(m){
                        return Array.from(m.addedNodes).some(function(n){
                            return n.tagName === 'FORM' || (n.querySelectorAll && n.querySelectorAll('form').length);
                        });
                    });
                    if (hasForm) scanForms();
                }).observe(document.body, {childList:true, subtree:true});
            }

        })(jQuery);
        </script>
        <?php
    }

    /* ────────────────────────────────────────────────────────────────────────
       HELPER: Fire server-side lead event
    ──────────────────────────────────────────────────────────────────────── */

    private function fire_lead($plugin, $form_id, $form_title, $fields) {
        $data = [
            'form_id'     => $form_id,
            'form_name'   => $form_title,
            'form_plugin' => $plugin,
            'form_type'   => $this->detect_type($form_title),
        ];

        // Extract user data for enhanced conversions
        $data = array_merge($data, $this->extract_user($fields));

        do_action('ddl_pro_server_event', 'generate_lead', $data);
    }

    private function extract_user($fields) {
        $out = [];
        if (!is_array($fields)) return $out;

        foreach ($fields as $k => $v) {
            if (empty($v) || is_array($v)) continue;
            $k = strtolower($k);

            if (strpos($k, 'email') !== false && filter_var($v, FILTER_VALIDATE_EMAIL)) {
                $out['user_email'] = sanitize_email($v);
            }
            if (strpos($k, 'phone') !== false || strpos($k, 'tel') !== false) {
                $out['user_phone'] = sanitize_text_field($v);
            }
            if (strpos($k, 'first') !== false && strpos($k, 'name') !== false) {
                $out['user_first_name'] = sanitize_text_field($v);
            }
            if (strpos($k, 'last') !== false && strpos($k, 'name') !== false) {
                $out['user_last_name'] = sanitize_text_field($v);
            }
            // CF7 style "your-name"
            if ($k === 'your-name' || $k === 'name' || $k === 'full-name') {
                $parts = explode(' ', sanitize_text_field($v), 2);
                $out['user_first_name'] = $parts[0] ?? '';
                $out['user_last_name']  = $parts[1] ?? '';
            }
        }
        return $out;
    }

    private function detect_type($title) {
        if (empty($title)) return 'general';
        $t = strtolower($title);
        if (strpos($t, 'contact') !== false) return 'contact';
        if (strpos($t, 'quote') !== false || strpos($t, 'estimate') !== false) return 'quote_request';
        if (strpos($t, 'newsletter') !== false || strpos($t, 'subscribe') !== false) return 'newsletter';
        if (strpos($t, 'demo') !== false) return 'demo_request';
        if (strpos($t, 'support') !== false) return 'support';
        if (strpos($t, 'register') !== false || strpos($t, 'signup') !== false) return 'registration';
        if (strpos($t, 'feedback') !== false) return 'feedback';
        return 'general';
    }
}
