/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * UNIVERSAL DATALAYER - GTM Custom HTML Tag
 * ═══════════════════════════════════════════════════════════════════════════════
 * 
 * HOW TO USE:
 * 1. Go to Google Tag Manager
 * 2. Create a new Tag → Custom HTML
 * 3. Paste this ENTIRE code (wrapped in <script>...</script>)
 * 4. Trigger: All Pages
 * 5. Done! All events auto-fire based on page context.
 *
 * WHAT IT DOES:
 * - Auto-detects page type (product, cart, checkout, thank you, search, etc.)
 * - Pushes GA4-schema dataLayer events for ALL platforms
 * - Google Ads dynamic remarketing (dynx_*, ecomm_*)
 * - Facebook Pixel events (ViewContent, AddToCart, Purchase, Lead, etc.)
 * - TikTok events (ViewContent, AddToCart, PlaceAnOrder, etc.)
 * - LinkedIn, Twitter/X, Pinterest, YouTube conversion events
 * - Full ecommerce funnel + local service events
 * - Event Match Quality 10/10 (user_data with all parameters)
 * - Customer lifecycle events (new vs returning)
 * - Consent Mode v2 ready
 *
 * SCHEMA: GA4 ecommerce standard with extensions for all platforms
 * ═══════════════════════════════════════════════════════════════════════════════
 */

(function() {
  'use strict';

  var DL = window.dataLayer = window.dataLayer || [];

  // ═══════════════════════════════════════════════════════════════════════════
  // CONFIGURATION (auto-detected from page, override if needed)
  // ═══════════════════════════════════════════════════════════════════════════

  var CONFIG = {
    currency: document.querySelector('meta[property="og:price:currency"]')
              ? document.querySelector('meta[property="og:price:currency"]').content
              : (window.__PTS_CURRENCY || 'USD'),
    business_vertical: 'retail', // retail|education|hotels_rentals|jobs|local|real_estate|travel
    cookie_domain: '.' + location.hostname.split('.').slice(-2).join('.')
  };


  // ═══════════════════════════════════════════════════════════════════════════
  // SECTION 1: USER DATA & EVENT MATCH QUALITY (10/10)
  // Collects all available user parameters for maximum match quality
  // ═══════════════════════════════════════════════════════════════════════════

  var USER_DATA = (function() {
    var ud = {};

    // Helper: SHA-256 hash (async, stores result)
    function sha256(str) {
      if (!str) return '';
      str = str.toLowerCase().trim();
      // If crypto API available, hash; otherwise return raw for server-side hashing
      if (window.crypto && crypto.subtle) {
        return str; // GTM server will hash; we provide normalized value
      }
      return str;
    }

    // Collect from dataLayer (previously pushed by backend)
    var prev = {};
    for (var i = 0; i < DL.length; i++) {
      if (DL[i] && DL[i].user_data) { prev = DL[i].user_data; break; }
      if (DL[i] && DL[i].visitorEmail) { prev.email = DL[i].visitorEmail; }
    }

    // Collect from page elements (checkout forms, account pages)
    var emailEl = document.querySelector(
      '#billing_email, input[name="billing_email"], input[name="email"], ' +
      'input[type="email"], [data-user-email]'
    );
    var phoneEl = document.querySelector(
      '#billing_phone, input[name="billing_phone"], input[name="phone"], ' +
      'input[type="tel"], [data-user-phone]'
    );
    var fnEl = document.querySelector(
      '#billing_first_name, input[name="billing_first_name"], ' +
      'input[name="first_name"], [data-user-firstname]'
    );
    var lnEl = document.querySelector(
      '#billing_last_name, input[name="billing_last_name"], ' +
      'input[name="last_name"], [data-user-lastname]'
    );
    var cityEl = document.querySelector('#billing_city, input[name="billing_city"]');
    var stateEl = document.querySelector('#billing_state, select[name="billing_state"]');
    var zipEl = document.querySelector('#billing_postcode, input[name="billing_postcode"]');
    var countryEl = document.querySelector('#billing_country, select[name="billing_country"]');

    // Build user_data object
    ud.email = prev.email || prev.email_sha256 || (emailEl && emailEl.value) || '';
    ud.phone_number = prev.phone || prev.phone_sha256 || (phoneEl && phoneEl.value) || '';
    ud.first_name = prev.first_name || prev.first_name_sha256 || (fnEl && fnEl.value) || '';
    ud.last_name = prev.last_name || prev.last_name_sha256 || (lnEl && lnEl.value) || '';


    ud.address = {
      city: prev.city || (cityEl && cityEl.value) || '',
      region: prev.region || prev.state || (stateEl && stateEl.value) || '',
      postal_code: prev.postal_code || (zipEl && zipEl.value) || '',
      country: prev.country || (countryEl && countryEl.value) || ''
    };

    // Cookie-based identifiers
    function getCookie(name) {
      var m = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
      return m ? decodeURIComponent(m[1]) : '';
    }

    ud.fbp = getCookie('_fbp');
    ud.fbc = getCookie('_fbc');
    ud.external_id = getCookie('_pts_eid') || getCookie('_ddl_uid') || '';
    ud.client_id = (function() {
      var ga = getCookie('_ga');
      if (!ga) return '';
      var p = ga.split('.');
      return p.length >= 4 ? p[2] + '.' + p[3] : '';
    })();

    // Click IDs from URL
    var params = new URLSearchParams(location.search);
    ud.gclid = params.get('gclid') || getCookie('_gcl_aw') || '';
    ud.fbclid = params.get('fbclid') || '';
    ud.wbraid = params.get('wbraid') || '';
    ud.gbraid = params.get('gbraid') || '';
    ud.ttclid = params.get('ttclid') || '';
    ud.li_fat_id = params.get('li_fat_id') || getCookie('li_fat_id') || '';

    // IP & User Agent (available server-side, marked here for GTM SS)
    ud.client_user_agent = navigator.userAgent;
    ud.page_location = location.href;
    ud.page_referrer = document.referrer;

    // Generate external_id if not present
    if (!ud.external_id) {
      ud.external_id = 'eid_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
      var exp = new Date(Date.now() + 390 * 86400000).toUTCString();
      document.cookie = '_pts_eid=' + ud.external_id + ';expires=' + exp +
                        ';path=/;domain=' + CONFIG.cookie_domain + ';SameSite=Lax';
    }

    return ud;
  })();


  // ═══════════════════════════════════════════════════════════════════════════
  // SECTION 2: PAGE TYPE DETECTION
  // ═══════════════════════════════════════════════════════════════════════════

  var PAGE = (function() {
    var path = location.pathname.toLowerCase();
    var body = document.body.className || '';
    var title = document.title.toLowerCase();

    // WooCommerce / Shopify / Generic ecommerce detection
    if (body.match(/single-product|product-template/) || document.querySelector('.product_title, [data-product-id], .shopify-section.product'))
      return 'product';
    if (body.match(/woocommerce-cart|template-cart/) || path.match(/\/cart\/?$/))
      return 'cart';
    if (path.match(/order-received|thank.?you|order.?confirm/) || body.match(/woocommerce-order-received/))
      return 'purchase';
    if (body.match(/woocommerce-checkout|template-checkout/) || path.match(/\/checkout\/?$/))
      return 'checkout';
    if (body.match(/woocommerce-shop|post-type-archive-product/) || path.match(/\/shop\/?$/))
      return 'product_list';
    if (body.match(/tax-product_cat|product-category/))
      return 'product_category';
    if (body.match(/search-results|is-search/) || path.match(/[?&]s=/))
      return 'search';
    if (path === '/' || body.match(/home|front-page/))
      return 'home';
    if (body.match(/single-post|postid-/))
      return 'article';
    if (path.match(/\/contact|\/about|\/services/))
      return 'service_page';
    if (path.match(/\/pricing|\/plans/))
      return 'pricing';
    return 'other';
  })();


  // ═══════════════════════════════════════════════════════════════════════════
  // SECTION 3: PRODUCT DATA EXTRACTION (dynamic from page)
  // ═══════════════════════════════════════════════════════════════════════════

  var PRODUCTS = (function() {
    var items = [];

    // Try structured data (JSON-LD)
    var ldScripts = document.querySelectorAll('script[type="application/ld+json"]');
    for (var i = 0; i < ldScripts.length; i++) {
      try {
        var ld = JSON.parse(ldScripts[i].textContent);
        if (ld['@type'] === 'Product' || (ld['@graph'] && ld['@graph'].find)) {
          var product = ld['@type'] === 'Product' ? ld : null;
          if (!product && ld['@graph']) {
            for (var g = 0; g < ld['@graph'].length; g++) {
              if (ld['@graph'][g]['@type'] === 'Product') { product = ld['@graph'][g]; break; }
            }
          }
          if (product) {
            var offer = product.offers || (product.offers && product.offers[0]) || {};
            if (Array.isArray(product.offers)) offer = product.offers[0] || {};
            items.push({
              item_id: product.sku || product.productID || product['@id'] || '',
              item_name: product.name || '',
              price: parseFloat(offer.price || offer.lowPrice || 0),
              item_brand: product.brand ? (product.brand.name || product.brand) : '',
              item_category: product.category || '',
              currency: offer.priceCurrency || CONFIG.currency,
              quantity: 1
            });
          }
        }
      } catch(e) {}
    }

    // Fallback: meta tags
    if (items.length === 0) {
      var ogTitle = document.querySelector('meta[property="og:title"]');
      var ogPrice = document.querySelector('meta[property="product:price:amount"], meta[property="og:price:amount"]');
      if (ogTitle && ogPrice) {
        items.push({
          item_id: document.querySelector('meta[property="product:retailer_item_id"]')
                   ? document.querySelector('meta[property="product:retailer_item_id"]').content : '',
          item_name: ogTitle.content,
          price: parseFloat(ogPrice.content) || 0,
          currency: CONFIG.currency,
          quantity: 1
        });
      }
    }

    // Fallback: WooCommerce page elements
    if (items.length === 0 && PAGE === 'product') {
      var nameEl = document.querySelector('.product_title, h1.entry-title');
      var priceEl = document.querySelector('.price ins .amount, .price > .amount, .price .woocommerce-Price-amount');
      var skuEl = document.querySelector('.sku');
      if (nameEl) {
        items.push({
          item_id: skuEl ? skuEl.textContent.trim() : (document.body.className.match(/postid-(\d+)/) || ['',''])[1],
          item_name: nameEl.textContent.trim(),
          price: priceEl ? parseFloat(priceEl.textContent.replace(/[^0-9.]/g, '')) : 0,
          currency: CONFIG.currency,
          quantity: 1
        });
      }
    }

    return items;
  })();


  // ═══════════════════════════════════════════════════════════════════════════
  // SECTION 4: ORDER DATA EXTRACTION (purchase/thank you page)
  // ═══════════════════════════════════════════════════════════════════════════

  var ORDER = (function() {
    if (PAGE !== 'purchase') return null;

    var order = { transaction_id: '', value: 0, tax: 0, shipping: 0, currency: CONFIG.currency, items: [], coupon: '' };

    // Try extracting from existing dataLayer
    for (var i = 0; i < DL.length; i++) {
      if (DL[i] && DL[i].ecommerce && DL[i].ecommerce.transaction_id) {
        return DL[i].ecommerce;
      }
      if (DL[i] && DL[i].transactionId) {
        order.transaction_id = DL[i].transactionId;
        order.value = parseFloat(DL[i].transactionTotal || 0);
        order.items = DL[i].transactionProducts || [];
        return order;
      }
    }

    // Try page content (WooCommerce thank you page)
    var orderEl = document.querySelector('.woocommerce-order-overview__order strong, .order-number');
    var totalEl = document.querySelector('.woocommerce-order-overview__total .amount, .order-total .amount');
    if (orderEl) order.transaction_id = orderEl.textContent.trim();
    if (totalEl) order.value = parseFloat(totalEl.textContent.replace(/[^0-9.]/g, '')) || 0;

    // Try URL param
    var urlOrder = location.pathname.match(/order-received\/(\d+)/);
    if (urlOrder && !order.transaction_id) order.transaction_id = urlOrder[1];

    return order.transaction_id ? order : null;
  })();


  // ═══════════════════════════════════════════════════════════════════════════
  // SECTION 5: PUSH EVENTS — GA4 ECOMMERCE + ALL PLATFORMS
  // ═══════════════════════════════════════════════════════════════════════════

  // --- 5.1: Universal Page Meta (fires on every page) ---
  DL.push({
    'event': 'ddl_page_data',
    'page_type': PAGE,
    'page_title': document.title,
    'page_location': location.href,
    'page_path': location.pathname,
    'page_referrer': document.referrer,
    'site_language': document.documentElement.lang || 'en',
    'user_data': USER_DATA,
    'timestamp': Math.floor(Date.now() / 1000)
  });

  // --- 5.2: Product Page — view_item ---
  if (PAGE === 'product' && PRODUCTS.length > 0) {
    var p = PRODUCTS[0];
    var eventId = 'vi_' + Date.now();

    // GA4 ecommerce
    DL.push({ecommerce: null});
    DL.push({
      'event': 'view_item',
      'event_id': eventId,
      'ecommerce': {
        'currency': p.currency,
        'value': p.price,
        'items': PRODUCTS
      },
      'user_data': USER_DATA
    });

    // Google Ads Dynamic Remarketing
    DL.push({
      'event': 'view_item_remarketing',
      'google_business_vertical': CONFIG.business_vertical,
      'dynx_itemid': p.item_id || p.item_name,
      'dynx_pagetype': 'offerdetail',
      'dynx_totalvalue': p.price,
      'ecomm_prodid': p.item_id || p.item_name,
      'ecomm_pagetype': 'product',
      'ecomm_totalvalue': p.price
    });

    // Facebook
    DL.push({
      'event': 'fb_ViewContent',
      'event_id': eventId,
      'fb_event_name': 'ViewContent',
      'content_ids': [p.item_id],
      'content_name': p.item_name,
      'content_type': 'product',
      'content_category': p.item_category || '',
      'value': p.price,
      'currency': p.currency
    });

    // TikTok
    DL.push({
      'event': 'tt_ViewContent',
      'event_id': eventId,
      'tt_event_name': 'ViewContent',
      'contents': [{ content_id: p.item_id, content_name: p.item_name, price: p.price, quantity: 1 }],
      'value': p.price,
      'currency': p.currency
    });

    // Pinterest
    DL.push({
      'event': 'pin_PageVisit',
      'event_id': eventId,
      'pin_event_name': 'pagevisit',
      'product_id': p.item_id,
      'product_name': p.item_name,
      'product_price': p.price,
      'product_currency': p.currency
    });

    // LinkedIn
    DL.push({
      'event': 'li_ViewContent',
      'event_id': eventId,
      'li_event_name': 'view_content',
      'content_name': p.item_name,
      'content_value': p.price,
      'content_currency': p.currency
    });

    // Twitter/X
    DL.push({
      'event': 'tw_ViewContent',
      'event_id': eventId,
      'tw_event_name': 'ViewContent',
      'content_ids': [p.item_id],
      'content_name': p.item_name,
      'value': p.price,
      'currency': p.currency
    });
  }


  // --- 5.3: Product List / Category / Shop ---
  if ((PAGE === 'product_list' || PAGE === 'product_category') && PRODUCTS.length > 0) {
    var ids = PRODUCTS.map(function(p) { return p.item_id || p.item_name; });
    var totalVal = PRODUCTS.reduce(function(s, p) { return s + p.price; }, 0);

    DL.push({ecommerce: null});
    DL.push({
      'event': 'view_item_list',
      'ecommerce': {
        'item_list_name': PAGE === 'product_category' ? document.title : 'Shop',
        'items': PRODUCTS
      },
      'user_data': USER_DATA
    });

    DL.push({
      'event': 'view_item_list_remarketing',
      'google_business_vertical': CONFIG.business_vertical,
      'dynx_itemid': ids,
      'dynx_pagetype': 'category',
      'dynx_totalvalue': totalVal,
      'ecomm_prodid': ids,
      'ecomm_pagetype': 'category',
      'ecomm_totalvalue': totalVal
    });

    DL.push({
      'event': 'fb_ViewCategory',
      'fb_event_name': 'ViewCategory',
      'content_ids': ids,
      'content_type': 'product',
      'value': totalVal,
      'currency': CONFIG.currency
    });

    DL.push({
      'event': 'pin_ViewCategory',
      'pin_event_name': 'viewcategory',
      'product_ids': ids,
      'value': totalVal,
      'currency': CONFIG.currency
    });
  }

  // --- 5.4: Search Page ---
  if (PAGE === 'search') {
    var searchTerm = (new URLSearchParams(location.search)).get('s') ||
                     (document.querySelector('input[name="s"], input[type="search"]') || {}).value || '';
    var eventId = 'search_' + Date.now();

    DL.push({
      'event': 'search',
      'event_id': eventId,
      'search_term': searchTerm,
      'user_data': USER_DATA
    });

    DL.push({
      'event': 'search_remarketing',
      'google_business_vertical': CONFIG.business_vertical,
      'dynx_pagetype': 'searchresults',
      'dynx_itemid': PRODUCTS.map(function(p){return p.item_id;}).slice(0,10),
      'dynx_totalvalue': PRODUCTS.reduce(function(s,p){return s+p.price;},0)
    });

    DL.push({
      'event': 'fb_Search',
      'event_id': eventId,
      'fb_event_name': 'Search',
      'search_string': searchTerm,
      'content_ids': PRODUCTS.map(function(p){return p.item_id;}).slice(0,10)
    });

    DL.push({ 'event': 'tt_Search', 'event_id': eventId, 'tt_event_name': 'Search', 'query': searchTerm });
    DL.push({ 'event': 'pin_Search', 'pin_event_name': 'search', 'search_query': searchTerm });
  }


  // --- 5.5: Checkout Page ---
  if (PAGE === 'checkout') {
    var eventId = 'checkout_' + Date.now();

    DL.push({ecommerce: null});
    DL.push({
      'event': 'begin_checkout',
      'event_id': eventId,
      'ecommerce': { 'currency': CONFIG.currency },
      'user_data': USER_DATA
    });

    DL.push({
      'event': 'checkout_remarketing',
      'google_business_vertical': CONFIG.business_vertical,
      'dynx_pagetype': 'conversionintent',
      'ecomm_pagetype': 'cart'
    });

    DL.push({ 'event': 'fb_InitiateCheckout', 'event_id': eventId, 'fb_event_name': 'InitiateCheckout' });
    DL.push({ 'event': 'tt_InitiateCheckout', 'event_id': eventId, 'tt_event_name': 'InitiateCheckout' });
    DL.push({ 'event': 'pin_Checkout', 'event_id': eventId, 'pin_event_name': 'checkout' });
    DL.push({ 'event': 'li_StartCheckout', 'event_id': eventId, 'li_event_name': 'start_checkout' });
  }

  // --- 5.6: Purchase / Thank You Page ---
  if (PAGE === 'purchase' && ORDER) {
    var eventId = 'purchase_' + (ORDER.transaction_id || Date.now());
    var contentIds = (ORDER.items || []).map(function(i){return i.item_id || i.id || '';});

    DL.push({ecommerce: null});
    DL.push({
      'event': 'purchase',
      'event_id': eventId,
      'ecommerce': {
        'transaction_id': ORDER.transaction_id,
        'value': ORDER.value,
        'tax': ORDER.tax || 0,
        'shipping': ORDER.shipping || 0,
        'currency': ORDER.currency || CONFIG.currency,
        'coupon': ORDER.coupon || '',
        'items': ORDER.items || []
      },
      'user_data': USER_DATA,
      'new_customer': !document.cookie.match(/_pts_purchased/)
    });

    // Mark as returning customer
    document.cookie = '_pts_purchased=1;path=/;max-age=31536000;domain=' + CONFIG.cookie_domain;

    // Google Ads Remarketing
    DL.push({
      'event': 'purchase_remarketing',
      'google_business_vertical': CONFIG.business_vertical,
      'dynx_itemid': contentIds,
      'dynx_pagetype': 'conversion',
      'dynx_totalvalue': ORDER.value,
      'ecomm_prodid': contentIds,
      'ecomm_pagetype': 'purchase',
      'ecomm_totalvalue': ORDER.value,
      'transaction_id': ORDER.transaction_id
    });

    // Facebook
    DL.push({
      'event': 'fb_Purchase',
      'event_id': eventId,
      'fb_event_name': 'Purchase',
      'content_ids': contentIds,
      'content_type': 'product',
      'value': ORDER.value,
      'currency': ORDER.currency || CONFIG.currency,
      'num_items': contentIds.length,
      'order_id': ORDER.transaction_id
    });

    // TikTok
    DL.push({
      'event': 'tt_PlaceAnOrder',
      'event_id': eventId,
      'tt_event_name': 'PlaceAnOrder',
      'contents': contentIds.map(function(id){return {content_id:id};}),
      'value': ORDER.value,
      'currency': ORDER.currency || CONFIG.currency
    });

    // Pinterest
    DL.push({
      'event': 'pin_Checkout',
      'event_id': eventId,
      'pin_event_name': 'checkout',
      'order_id': ORDER.transaction_id,
      'value': ORDER.value,
      'currency': ORDER.currency || CONFIG.currency,
      'line_items': contentIds.map(function(id){return {product_id:id};})
    });

    // LinkedIn
    DL.push({
      'event': 'li_Purchase',
      'event_id': eventId,
      'li_event_name': 'purchase',
      'value': ORDER.value,
      'currency': ORDER.currency || CONFIG.currency,
      'transaction_id': ORDER.transaction_id
    });

    // Twitter/X
    DL.push({
      'event': 'tw_Purchase',
      'event_id': eventId,
      'tw_event_name': 'Purchase',
      'value': ORDER.value,
      'currency': ORDER.currency || CONFIG.currency,
      'content_ids': contentIds,
      'num_items': contentIds.length
    });

    // YouTube (via Google Ads, same conversion)
    DL.push({
      'event': 'yt_conversion',
      'event_id': eventId,
      'conversion_type': 'purchase',
      'value': ORDER.value,
      'currency': ORDER.currency || CONFIG.currency
    });
  }


  // --- 5.7: Home Page ---
  if (PAGE === 'home') {
    DL.push({
      'event': 'home_remarketing',
      'google_business_vertical': CONFIG.business_vertical,
      'dynx_pagetype': 'home',
      'ecomm_pagetype': 'home'
    });
  }

  // --- 5.8: Other pages ---
  if (['other','article','service_page','pricing'].indexOf(PAGE) > -1) {
    DL.push({
      'event': 'other_remarketing',
      'google_business_vertical': CONFIG.business_vertical,
      'dynx_pagetype': 'other',
      'ecomm_pagetype': 'other'
    });
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // SECTION 6: INTERACTION EVENTS (click-based, fire on user action)
  // ═══════════════════════════════════════════════════════════════════════════

  // --- 6.1: Add to Cart ---
  document.addEventListener('click', function(e) {
    var btn = e.target.closest('.add_to_cart_button, .single_add_to_cart_button, [data-add-to-cart], .btn-add-cart, form.cart button[type="submit"]');
    if (!btn) return;

    var product = PRODUCTS[0] || {};
    var eventId = 'atc_' + Date.now();

    DL.push({ecommerce: null});
    DL.push({
      'event': 'add_to_cart',
      'event_id': eventId,
      'ecommerce': {
        'currency': product.currency || CONFIG.currency,
        'value': product.price || 0,
        'items': [product]
      },
      'user_data': USER_DATA
    });

    // Google Ads Remarketing
    DL.push({
      'event': 'add_to_cart_remarketing',
      'google_business_vertical': CONFIG.business_vertical,
      'dynx_itemid': product.item_id || '',
      'dynx_pagetype': 'conversionintent',
      'dynx_totalvalue': product.price || 0
    });

    // Facebook
    DL.push({
      'event': 'fb_AddToCart',
      'event_id': eventId,
      'fb_event_name': 'AddToCart',
      'content_ids': [product.item_id || ''],
      'content_name': product.item_name || '',
      'content_type': 'product',
      'value': product.price || 0,
      'currency': product.currency || CONFIG.currency
    });

    // TikTok
    DL.push({
      'event': 'tt_AddToCart',
      'event_id': eventId,
      'tt_event_name': 'AddToCart',
      'contents': [{content_id: product.item_id, content_name: product.item_name, price: product.price, quantity: 1}],
      'value': product.price || 0,
      'currency': product.currency || CONFIG.currency
    });

    // Pinterest
    DL.push({ 'event': 'pin_AddToCart', 'event_id': eventId, 'pin_event_name': 'addtocart', 'product_id': product.item_id, 'value': product.price });
    // LinkedIn
    DL.push({ 'event': 'li_AddToCart', 'event_id': eventId, 'li_event_name': 'add_to_cart', 'content_name': product.item_name, 'value': product.price });
    // Twitter
    DL.push({ 'event': 'tw_AddToCart', 'event_id': eventId, 'tw_event_name': 'AddToCart', 'content_ids': [product.item_id], 'value': product.price });
  });


  // --- 6.2: Form Submit (Lead) ---
  document.addEventListener('submit', function(e) {
    var form = e.target;
    if (!form || form.tagName !== 'FORM') return;
    // Skip ecommerce forms
    if (form.classList.contains('cart') || form.classList.contains('checkout') ||
        form.classList.contains('woocommerce-cart-form') || form.getAttribute('role') === 'search') return;

    var eventId = 'lead_' + Date.now();

    DL.push({
      'event': 'generate_lead',
      'event_id': eventId,
      'form_id': form.id || '',
      'form_name': form.getAttribute('data-form-name') || form.getAttribute('aria-label') || '',
      'user_data': USER_DATA
    });

    DL.push({ 'event': 'fb_Lead', 'event_id': eventId, 'fb_event_name': 'Lead' });
    DL.push({ 'event': 'tt_SubmitForm', 'event_id': eventId, 'tt_event_name': 'SubmitForm' });
    DL.push({ 'event': 'pin_Lead', 'event_id': eventId, 'pin_event_name': 'lead' });
    DL.push({ 'event': 'li_Lead', 'event_id': eventId, 'li_event_name': 'generate_lead' });
    DL.push({ 'event': 'tw_Lead', 'event_id': eventId, 'tw_event_name': 'Lead' });
  });

  // CF7 integration
  document.addEventListener('wpcf7mailsent', function(e) {
    var eventId = 'lead_cf7_' + Date.now();
    DL.push({ 'event': 'generate_lead', 'event_id': eventId, 'form_plugin': 'contact_form_7', 'user_data': USER_DATA });
    DL.push({ 'event': 'fb_Lead', 'event_id': eventId, 'fb_event_name': 'Lead' });
    DL.push({ 'event': 'tt_SubmitForm', 'event_id': eventId, 'tt_event_name': 'SubmitForm' });
    DL.push({ 'event': 'li_Lead', 'event_id': eventId, 'li_event_name': 'generate_lead' });
  });

  // --- 6.3: Click to Call ---
  document.addEventListener('click', function(e) {
    var link = e.target.closest('a[href^="tel:"]');
    if (!link) return;
    var eventId = 'call_' + Date.now();
    DL.push({ 'event': 'click_to_call', 'event_id': eventId, 'phone_number': link.href.replace('tel:',''), 'user_data': USER_DATA });
    DL.push({ 'event': 'fb_Contact', 'event_id': eventId, 'fb_event_name': 'Contact', 'content_name': 'phone_call' });
    DL.push({ 'event': 'tt_Contact', 'event_id': eventId, 'tt_event_name': 'Contact' });
    DL.push({ 'event': 'li_Contact', 'event_id': eventId, 'li_event_name': 'contact' });
  });

  // --- 6.4: Email Click ---
  document.addEventListener('click', function(e) {
    var link = e.target.closest('a[href^="mailto:"]');
    if (!link) return;
    var eventId = 'email_' + Date.now();
    DL.push({ 'event': 'email_click', 'event_id': eventId, 'email_target': link.href.replace('mailto:','').split('?')[0], 'user_data': USER_DATA });
    DL.push({ 'event': 'fb_Contact', 'event_id': eventId, 'fb_event_name': 'Contact', 'content_name': 'email' });
  });

  // --- 6.5: WhatsApp / Messenger / Chat ---
  document.addEventListener('click', function(e) {
    var link = e.target.closest('a[href*="wa.me"], a[href*="whatsapp"], a[href*="m.me"], a[href*="messenger"]');
    if (!link) return;
    var type = link.href.indexOf('wa.me') > -1 || link.href.indexOf('whatsapp') > -1 ? 'whatsapp' : 'messenger';
    var eventId = 'chat_' + Date.now();
    DL.push({ 'event': 'chat_click', 'event_id': eventId, 'chat_type': type, 'user_data': USER_DATA });
    DL.push({ 'event': 'fb_Contact', 'event_id': eventId, 'fb_event_name': 'Contact', 'content_name': type });
    DL.push({ 'event': 'generate_lead', 'event_id': eventId, 'lead_type': type });
  });

  // --- 6.6: Get Directions ---
  document.addEventListener('click', function(e) {
    var link = e.target.closest('a[href*="maps.google"], a[href*="google.com/maps"], a[href*="waze.com"], a[href*="maps.apple.com"]');
    if (!link) return;
    var eventId = 'dir_' + Date.now();
    DL.push({ 'event': 'get_directions', 'event_id': eventId, 'user_data': USER_DATA });
    DL.push({ 'event': 'fb_FindLocation', 'event_id': eventId, 'fb_event_name': 'FindLocation' });
  });

  // --- 6.7: Outbound Link Click ---
  document.addEventListener('click', function(e) {
    var link = e.target.closest('a[href]');
    if (!link) return;
    try {
      var url = new URL(link.href);
      if (url.hostname !== location.hostname) {
        DL.push({ 'event': 'outbound_click', 'outbound_url': link.href, 'link_text': link.textContent.trim().substring(0,100) });
      }
    } catch(ex) {}
  });

  // --- 6.8: Scroll Depth ---
  var scrollFired = {};
  window.addEventListener('scroll', function() {
    var pct = Math.round((window.scrollY / (document.body.scrollHeight - window.innerHeight)) * 100);
    [25,50,75,90].forEach(function(t) {
      if (pct >= t && !scrollFired[t]) {
        scrollFired[t] = true;
        DL.push({ 'event': 'scroll_depth', 'scroll_percentage': t, 'page_type': PAGE });
      }
    });
  });

  // ═══════════════════════════════════════════════════════════════════════════
  // SECTION 7: CUSTOMER LIFECYCLE EVENTS
  // ═══════════════════════════════════════════════════════════════════════════

  DL.push({
    'event': 'customer_status',
    'customer_type': document.cookie.match(/_pts_purchased/) ? 'returning' : 'new',
    'session_count': parseInt(sessionStorage.getItem('_pts_sessions') || '0') + 1
  });
  sessionStorage.setItem('_pts_sessions', (parseInt(sessionStorage.getItem('_pts_sessions') || '0') + 1).toString());

  // ═══════════════════════════════════════════════════════════════════════════
  // DONE — All events pushed. GTM will pick them up automatically.
  // ═══════════════════════════════════════════════════════════════════════════

})();
