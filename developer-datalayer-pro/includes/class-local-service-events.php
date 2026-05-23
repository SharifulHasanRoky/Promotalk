<?php
/**
 * Local Service Events Tracking
 * Auto-detects and tracks interactions typical for local/service businesses.
 *
 * Events: click_to_call, get_directions, email_click, chat_click,
 *         store_locator_search, booking_click, quote_request_click,
 *         view_store_page, view_business_hours, generate_lead
 *
 * Also integrates with booking plugins (WC Bookings, Amelia, Booked).
 */

if (!defined('ABSPATH')) exit;

class DDL_Pro_Local_Service_Events {

    public function __construct() {
        add_action('wp_footer', [$this, 'inject_tracking_js'], 20);
        add_action('wp_footer', [$this, 'track_store_page'], 10);

        // Booking plugin hooks
        add_action('woocommerce_booking_confirmed', [$this, 'server_booking_confirmed'], 10, 1);
        add_action('amelia_after_booking_added', [$this, 'server_amelia_booking'], 10, 1);
        add_action('booked_new_appointment_created', [$this, 'server_booked_appointment'], 10, 1);
    }

    /**
     * Inject all local-service tracking JS (auto-detection)
     */
    public function inject_tracking_js() {
        ?>
        <script>
        (function($){
            'use strict';
            var DL = window.dataLayer = window.dataLayer || [];

            /* ─── CLICK TO CALL ────────────────────────────────────── */
            $(document).on('click', 'a[href^="tel:"]', function(){
                var phone = this.href.replace('tel:','').trim();
                DL.push({
                    'event':'click_to_call',
                    'event_category':'local_service',
                    'phone_number': phone,
                    'link_text': this.textContent.trim(),
                    'page_location': location.href
                });
                DL.push({
                    'event':'generate_lead',
                    'event_category':'local_service',
                    'lead_type':'phone_call',
                    'contact_method':'phone'
                });
            });

            /* ─── GET DIRECTIONS ───────────────────────────────────── */
            $(document).on('click', [
                'a[href*="maps.google"]','a[href*="google.com/maps"]',
                'a[href*="maps.apple.com"]','a[href*="waze.com"]',
                '.get-directions','[data-directions]'
            ].join(','), function(){
                DL.push({
                    'event':'get_directions',
                    'event_category':'local_service',
                    'destination_url': this.href || '',
                    'link_text': this.textContent.trim(),
                    'page_location': location.href
                });
            });

            /* ─── EMAIL CLICK ──────────────────────────────────────── */
            $(document).on('click', 'a[href^="mailto:"]', function(){
                var email = this.href.replace('mailto:','').split('?')[0].trim();
                DL.push({
                    'event':'email_click',
                    'event_category':'local_service',
                    'email_address': email,
                    'link_text': this.textContent.trim(),
                    'page_location': location.href
                });
                DL.push({
                    'event':'generate_lead',
                    'event_category':'local_service',
                    'lead_type':'email',
                    'contact_method':'email'
                });
            });

            /* ─── CHAT / WHATSAPP / MESSENGER ──────────────────────── */
            $(document).on('click', [
                'a[href*="wa.me"]','a[href*="whatsapp"]',
                'a[href*="m.me"]','a[href*="messenger"]',
                '.chat-button','.live-chat','[data-chat]',
                '.whatsapp-btn','.messenger-btn'
            ].join(','), function(){
                var type = 'chat';
                var href = (this.href || '').toLowerCase();
                if (href.indexOf('wa.me') > -1 || href.indexOf('whatsapp') > -1) type = 'whatsapp';
                else if (href.indexOf('m.me') > -1 || href.indexOf('messenger') > -1) type = 'messenger';

                DL.push({
                    'event':'chat_click',
                    'event_category':'local_service',
                    'chat_type': type,
                    'link_text': this.textContent.trim(),
                    'page_location': location.href
                });
                DL.push({
                    'event':'generate_lead',
                    'event_category':'local_service',
                    'lead_type': type,
                    'contact_method': type
                });
            });

            /* ─── STORE LOCATOR ────────────────────────────────────── */
            $(document).on('click', '.store-locator,.location-finder,[data-store-locator],.find-store,.find-location', function(){
                DL.push({
                    'event':'store_locator_click',
                    'event_category':'local_service',
                    'page_location': location.href
                });
            });
            $(document).on('submit', '.store-locator-search,[data-store-search]', function(){
                var q = $(this).find('input[type="text"],input[type="search"]').val() || '';
                DL.push({
                    'event':'store_locator_search',
                    'event_category':'local_service',
                    'search_term': q
                });
            });

            /* ─── BOOKING / APPOINTMENT CLICKS ─────────────────────── */
            $(document).on('click', [
                '.book-appointment,.book-now,.schedule-appointment',
                '[data-booking],[data-appointment]',
                'a[href*="book"],a[href*="appointment"],a[href*="schedule"]',
                '.btn-book,.btn-appointment,.booking-btn'
            ].join(','), function(){
                DL.push({
                    'event':'booking_click',
                    'event_category':'local_service',
                    'link_text': this.textContent.trim(),
                    'link_url': this.href || '',
                    'page_location': location.href
                });
            });

            /* ─── QUOTE / ESTIMATE REQUEST ─────────────────────────── */
            $(document).on('click', [
                '.get-quote,.request-quote,.free-quote',
                '[data-quote],[data-estimate]',
                'a[href*="quote"],a[href*="estimate"]',
                '.btn-quote,.btn-estimate'
            ].join(','), function(){
                DL.push({
                    'event':'quote_request_click',
                    'event_category':'local_service',
                    'link_text': this.textContent.trim(),
                    'page_location': location.href
                });
            });

            /* ─── BUSINESS HOURS VISIBILITY ────────────────────────── */
            var hoursEls = document.querySelectorAll('.business-hours,.opening-hours,.store-hours,[data-hours]');
            if (hoursEls.length && 'IntersectionObserver' in window) {
                var obs = new IntersectionObserver(function(entries){
                    entries.forEach(function(entry){
                        if (entry.isIntersecting) {
                            DL.push({
                                'event':'view_business_hours',
                                'event_category':'local_service',
                                'page_location': location.href
                            });
                            obs.disconnect();
                        }
                    });
                }, {threshold:0.5});
                hoursEls.forEach(function(el){ obs.observe(el); });
            }

        })(jQuery);
        </script>
        <?php
    }

    /**
     * Track store/location/contact page views
     */
    public function track_store_page() {
        $slugs = ['store', 'location', 'locations', 'find-us', 'contact', 'our-store', 'our-stores', 'visit-us'];
        if (!is_page($slugs)) return;
        ?>
        <script>
        window.dataLayer=window.dataLayer||[];
        window.dataLayer.push({
            'event':'view_store_page',
            'event_category':'local_service',
            'page_title':'<?php echo esc_js(get_the_title()); ?>',
            'page_location': window.location.href
        });
        </script>
        <?php
    }

    /* ────────────────────────────────────────────────────────────────────────
       SERVER-SIDE BOOKING HOOKS
    ──────────────────────────────────────────────────────────────────────── */

    public function server_booking_confirmed($booking_id) {
        $data = [
            'booking_id' => $booking_id,
            'interaction_type' => 'booking_complete',
        ];
        if (function_exists('get_wc_booking')) {
            $b = get_wc_booking($booking_id);
            if ($b) {
                $data['service_name'] = $b->get_product() ? $b->get_product()->get_name() : '';
                $data['booking_date'] = $b->get_start_date();
                $data['booking_value'] = $b->get_cost();
            }
        }
        do_action('ddl_pro_server_event', 'booking_confirmed', $data);
    }

    public function server_amelia_booking($booking) {
        do_action('ddl_pro_server_event', 'appointment_booked', [
            'booking_id'   => $booking['id'] ?? '',
            'service_name' => $booking['service']['name'] ?? '',
            'booking_date' => $booking['bookingStart'] ?? '',
            'booking_value'=> $booking['price'] ?? 0,
        ]);
    }

    public function server_booked_appointment($appointment_id) {
        do_action('ddl_pro_server_event', 'appointment_booked', [
            'booking_id' => $appointment_id,
        ]);
    }
}
