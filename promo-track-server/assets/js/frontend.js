/**
 * PromoTrack Server - Frontend JS
 * Handles client-side events: add_to_cart, lead (form submit), contact
 */
(function($){
    'use strict';
    if (typeof ptsConfig === 'undefined') return;
    var ev = ptsConfig.events || {};
    var currency = ptsConfig.currency || 'USD';

    /* ─── ADD TO CART ──────────────────────────────────── */
    if (ev.add_to_cart) {
        // WooCommerce AJAX add_to_cart
        $(document.body).on('added_to_cart', function(e, frag, hash, $btn){
            var id = String($btn.data('product_id') || '');
            var price = parseFloat($btn.data('product_price') || 0);
            var qty = parseInt($btn.data('quantity') || 1);
            var eid = 'atc_' + Date.now();

            // Facebook Pixel
            if (typeof fbq === 'function') {
                fbq('track','AddToCart',{
                    content_ids:[id], content_type:'product',
                    value:price*qty, currency:currency
                },{eventID:eid});
            }
            // Google Ads
            if (typeof gtag === 'function') {
                gtag('event','add_to_cart',{
                    value:price*qty, currency:currency,
                    items:[{id:id,price:price,quantity:qty}]
                });
            }
            // Server CAPI
            $.post(ptsConfig.ajaxUrl,{
                action:'pts_capi_event', nonce:ptsConfig.nonce,
                event:'AddToCart', event_id:eid,
                data:JSON.stringify({content_ids:[id],value:price*qty,currency:currency})
            });
        });

        // Single product form
        $('form.cart').on('submit', function(){
            var $f = $(this), $p = $f.closest('.product');
            var id = $f.find('[name="add-to-cart"],button[name="add-to-cart"]').val() || '';
            var price = parseFloat($p.find('.price ins .amount,.price > .amount').first().text().replace(/[^0-9.]/g,'')) || 0;
            var qty = parseInt($f.find('[name="quantity"]').val()) || 1;
            var eid = 'atc_' + Date.now();

            if (typeof fbq === 'function') {
                fbq('track','AddToCart',{content_ids:[String(id)],content_type:'product',value:price*qty,currency:currency},{eventID:eid});
            }
            if (typeof gtag === 'function') {
                gtag('event','add_to_cart',{value:price*qty,currency:currency,items:[{id:String(id),price:price,quantity:qty}]});
            }
        });
    }

    /* ─── LEAD (form submit) ───────────────────────────── */
    if (ev.lead) {
        // CF7
        document.addEventListener('wpcf7mailsent', function(e){
            var eid = 'lead_' + Date.now();
            if (typeof fbq === 'function') fbq('track','Lead',{},{eventID:eid});
            if (typeof gtag === 'function') gtag('event','generate_lead',{value:0,currency:currency});
            $.post(ptsConfig.ajaxUrl,{action:'pts_capi_event',nonce:ptsConfig.nonce,event:'Lead',event_id:eid,data:'{}'});
        });

        // Generic forms (not WooCommerce)
        $(document).on('submit','form:not(.cart):not(.checkout):not(.woocommerce-cart-form):not([role="search"])',function(){
            var $f = $(this);
            if ($f.hasClass('wpcf7-form') || $f.closest('.gform_wrapper').length || $f.hasClass('wpforms-form')) return;
            var eid = 'lead_' + Date.now();
            if (typeof fbq === 'function') fbq('track','Lead',{},{eventID:eid});
            if (typeof gtag === 'function') gtag('event','generate_lead',{value:0,currency:currency});
        });
    }

    /* ─── SEARCH ───────────────────────────────────────── */
    if (ev.search) {
        $(document).on('submit','form[role="search"],.search-form,[action*="?s="]',function(){
            var q = $(this).find('input[type="search"],input[name="s"]').val() || '';
            if (!q) return;
            if (typeof fbq === 'function') fbq('track','Search',{search_string:q});
            if (typeof gtag === 'function') gtag('event','search',{search_term:q});
        });
    }

})(jQuery);
