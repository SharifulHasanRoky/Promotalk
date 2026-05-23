<script>
(function(){
  var DL = window.dataLayer = window.dataLayer || [];
  var C = {
    currency: (document.querySelector('meta[property="og:price:currency"]')||{}).content || 'USD',
    vertical: 'retail',
    domain: '.' + location.hostname.split('.').slice(-2).join('.')
  };

  // ─── HELPERS ────────────────────────────────────────────────────────
  function ck(n){var m=document.cookie.match(new RegExp('(?:^|; )'+n+'=([^;]*)'));return m?decodeURIComponent(m[1]):'';}
  function el(s){var e=document.querySelector(s);return e&&e.value||'';}
  var params = new URLSearchParams(location.search);

  // ─── USER DATA (EMQ 10/10) ──────────────────────────────────────────
  var eid = ck('_pts_eid');
  if(!eid){eid='eid_'+Date.now()+'_'+Math.random().toString(36).substr(2,9);document.cookie='_pts_eid='+eid+';path=/;max-age=33696000;domain='+C.domain+';SameSite=Lax';}

  var user_data = {
    email: el('#billing_email,input[type="email"],input[name*="email"]'),
    phone_number: el('#billing_phone,input[type="tel"],input[name*="phone"]'),
    first_name: el('#billing_first_name,input[name*="first_name"]'),
    last_name: el('#billing_last_name,input[name*="last_name"]'),
    city: el('#billing_city'),
    region: el('#billing_state,select[name="billing_state"]'),
    postal_code: el('#billing_postcode'),
    country: el('#billing_country,select[name="billing_country"]'),
    external_id: eid,
    fbp: ck('_fbp'),
    fbc: ck('_fbc'),
    client_id: (function(){var g=ck('_ga');if(!g)return '';var p=g.split('.');return p.length>=4?p[2]+'.'+p[3]:'';}()),
    gclid: params.get('gclid')||ck('_gcl_aw')||'',
    fbclid: params.get('fbclid')||'',
    ttclid: params.get('ttclid')||'',
    li_fat_id: params.get('li_fat_id')||'',
    user_agent: navigator.userAgent,
    page_location: location.href,
    page_referrer: document.referrer
  };

  // ─── PAGE TYPE ──────────────────────────────────────────────────────
  var path = location.pathname.toLowerCase(), body = document.body.className||'';
  var PAGE = 'other';
  if(body.match(/single-product/)||document.querySelector('.product_title,[data-product-id]')) PAGE='product';
  else if(body.match(/woocommerce-cart/)||path.match(/\/cart\/?$/)) PAGE='cart';
  else if(path.match(/order-received|thank.?you/)||body.match(/order-received/)) PAGE='purchase';
  else if(body.match(/woocommerce-checkout/)||path.match(/\/checkout\/?$/)) PAGE='checkout';
  else if(body.match(/woocommerce-shop|post-type-archive-product/)||path.match(/\/shop\/?$/)) PAGE='product_list';
  else if(body.match(/tax-product_cat/)) PAGE='product_category';
  else if(body.match(/search|is-search/)||path.indexOf('?s=')>-1) PAGE='search';
  else if(path==='/'||body.match(/home|front-page/)) PAGE='home';

  // ─── PRODUCT DATA (from JSON-LD or DOM) ─────────────────────────────
  var items = [];
  try{var lds=document.querySelectorAll('script[type="application/ld+json"]');for(var i=0;i<lds.length;i++){var j=JSON.parse(lds[i].textContent);var pr=j['@type']==='Product'?j:null;if(!pr&&j['@graph']){for(var g=0;g<j['@graph'].length;g++){if(j['@graph'][g]['@type']==='Product'){pr=j['@graph'][g];break;}}}if(pr){var of=Array.isArray(pr.offers)?pr.offers[0]:pr.offers||{};items.push({item_id:pr.sku||pr.productID||'',item_name:pr.name||'',price:parseFloat(of.price||of.lowPrice||0),item_brand:pr.brand?(pr.brand.name||pr.brand):'',item_category:pr.category||'',currency:of.priceCurrency||C.currency,quantity:1});}}}catch(ex){}
  if(!items.length&&PAGE==='product'){var nm=document.querySelector('.product_title,h1.entry-title');var pc=document.querySelector('.price ins .amount,.price > .amount');if(nm)items.push({item_id:(body.match(/postid-(\d+)/)||['',''])[1],item_name:nm.textContent.trim(),price:pc?parseFloat(pc.textContent.replace(/[^0-9.]/g,'')):0,currency:C.currency,quantity:1});}

  // ─── ORDER DATA (purchase page) ────────────────────────────────────
  var order = null;
  if(PAGE==='purchase'){order={};for(var k=0;k<DL.length;k++){if(DL[k]&&DL[k].ecommerce&&DL[k].ecommerce.transaction_id){order=DL[k].ecommerce;break;}}if(!order.transaction_id){var oe=document.querySelector('.woocommerce-order-overview__order strong,.order-number');var te=document.querySelector('.order-total .amount');if(oe)order.transaction_id=oe.textContent.trim();if(te)order.value=parseFloat(te.textContent.replace(/[^0-9.]/g,''))||0;var um=path.match(/order-received\/(\d+)/);if(um&&!order.transaction_id)order.transaction_id=um[1];}if(!order.transaction_id)order=null;}

  // ═══════════════════════════════════════════════════════════════════════
  // EVENTS — 1 event per action, all platform params inside
  // ═══════════════════════════════════════════════════════════════════════

  // ─── PAGE VIEW (every page) ─────────────────────────────────────────
  DL.push({
    'event': 'page_view',
    'page_type': PAGE,
    'page_location': location.href,
    'page_path': path,
    'page_referrer': document.referrer,
    'user_data': user_data
  });

  // ─── VIEW ITEM (product page) ──────────────────────────────────────
  if(PAGE==='product' && items.length){
    var p=items[0], eid1='vi_'+Date.now();
    DL.push({ecommerce:null});
    DL.push({
      'event': 'view_item',
      'event_id': eid1,
      'ecommerce': {currency:p.currency, value:p.price, items:items},
      'user_data': user_data,
      // FB params
      'content_ids': [p.item_id],
      'content_name': p.item_name,
      'content_type': 'product',
      'content_category': p.item_category,
      // TikTok params
      'contents': [{content_id:p.item_id,content_name:p.item_name,price:p.price,quantity:1}],
      // Pinterest params
      'product_id': p.item_id,
      'product_name': p.item_name,
      // Universal
      'value': p.price,
      'currency': p.currency
    });
    // Google Ads Dynamic Remarketing (separate — required by Google)
    DL.push({
      'event': 'remarketing',
      'google_business_vertical': C.vertical,
      'dynx_itemid': p.item_id,
      'dynx_pagetype': 'offerdetail',
      'dynx_totalvalue': p.price,
      'ecomm_prodid': p.item_id,
      'ecomm_pagetype': 'product',
      'ecomm_totalvalue': p.price
    });
  }

  // ─── VIEW ITEM LIST (shop/category) ────────────────────────────────
  if((PAGE==='product_list'||PAGE==='product_category') && items.length){
    var ids=items.map(function(x){return x.item_id;}), tv=items.reduce(function(s,x){return s+x.price;},0);
    DL.push({ecommerce:null});
    DL.push({
      'event': 'view_item_list',
      'ecommerce': {item_list_name:PAGE==='product_category'?document.title:'Shop', items:items},
      'user_data': user_data,
      'content_ids': ids,
      'content_type': 'product',
      'value': tv,
      'currency': C.currency
    });
    DL.push({'event':'remarketing','google_business_vertical':C.vertical,'dynx_itemid':ids,'dynx_pagetype':'category','dynx_totalvalue':tv,'ecomm_prodid':ids,'ecomm_pagetype':'category','ecomm_totalvalue':tv});
  }

  // ─── SEARCH ────────────────────────────────────────────────────────
  if(PAGE==='search'){
    var sq=params.get('s')||(document.querySelector('input[name="s"]')||{}).value||'';
    DL.push({'event':'search','search_term':sq,'user_data':user_data,'search_string':sq,'query':sq});
    DL.push({'event':'remarketing','google_business_vertical':C.vertical,'dynx_pagetype':'searchresults','dynx_itemid':[],'dynx_totalvalue':0});
  }

  // ─── BEGIN CHECKOUT ────────────────────────────────────────────────
  if(PAGE==='checkout'){
    DL.push({ecommerce:null});
    DL.push({'event':'begin_checkout','event_id':'chk_'+Date.now(),'ecommerce':{currency:C.currency},'user_data':user_data});
    DL.push({'event':'remarketing','google_business_vertical':C.vertical,'dynx_pagetype':'conversionintent','ecomm_pagetype':'cart'});
  }

  // ─── PURCHASE ──────────────────────────────────────────────────────
  if(PAGE==='purchase' && order && order.transaction_id){
    var cids=(order.items||[]).map(function(x){return x.item_id||x.id||'';});
    var eid3='pur_'+order.transaction_id;
    DL.push({ecommerce:null});
    DL.push({
      'event': 'purchase',
      'event_id': eid3,
      'ecommerce': {transaction_id:order.transaction_id, value:order.value||0, currency:order.currency||C.currency, tax:order.tax||0, shipping:order.shipping||0, items:order.items||[]},
      'user_data': user_data,
      'content_ids': cids,
      'content_type': 'product',
      'num_items': cids.length,
      'order_id': order.transaction_id,
      'contents': cids.map(function(id){return{content_id:id};}),
      'value': order.value||0,
      'currency': order.currency||C.currency,
      'new_customer': !document.cookie.match(/_pts_purchased/)
    });
    document.cookie='_pts_purchased=1;path=/;max-age=31536000;domain='+C.domain;
    DL.push({'event':'remarketing','google_business_vertical':C.vertical,'dynx_itemid':cids,'dynx_pagetype':'conversion','dynx_totalvalue':order.value||0,'ecomm_prodid':cids,'ecomm_pagetype':'purchase','ecomm_totalvalue':order.value||0,'transaction_id':order.transaction_id});
  }

  // ─── HOME / OTHER REMARKETING ──────────────────────────────────────
  if(PAGE==='home') DL.push({'event':'remarketing','google_business_vertical':C.vertical,'dynx_pagetype':'home','ecomm_pagetype':'home'});
  if(['other','cart'].indexOf(PAGE)>-1) DL.push({'event':'remarketing','google_business_vertical':C.vertical,'dynx_pagetype':'other','ecomm_pagetype':'other'});

  // ═══════════════════════════════════════════════════════════════════════
  // INTERACTION EVENTS (user clicks)
  // ═══════════════════════════════════════════════════════════════════════

  // ─── ADD TO CART ───────────────────────────────────────────────────
  document.addEventListener('click',function(ev){
    var btn=ev.target.closest('.add_to_cart_button,.single_add_to_cart_button,[data-add-to-cart],form.cart button[type="submit"]');
    if(!btn)return;
    var p=items[0]||{}, eid4='atc_'+Date.now();
    DL.push({ecommerce:null});
    DL.push({
      'event': 'add_to_cart',
      'event_id': eid4,
      'ecommerce': {currency:p.currency||C.currency, value:p.price||0, items:[p]},
      'user_data': user_data,
      'content_ids': [p.item_id||''],
      'content_name': p.item_name||'',
      'content_type': 'product',
      'contents': [{content_id:p.item_id,content_name:p.item_name,price:p.price,quantity:1}],
      'product_id': p.item_id||'',
      'value': p.price||0,
      'currency': p.currency||C.currency
    });
    DL.push({'event':'remarketing','google_business_vertical':C.vertical,'dynx_itemid':p.item_id||'','dynx_pagetype':'conversionintent','dynx_totalvalue':p.price||0});
  });

  // ─── FORM SUBMIT (Lead) ───────────────────────────────────────────
  document.addEventListener('submit',function(ev){
    var f=ev.target;if(!f||f.tagName!=='FORM')return;
    if(f.classList.contains('cart')||f.classList.contains('checkout')||f.getAttribute('role')==='search')return;
    DL.push({'event':'generate_lead','event_id':'lead_'+Date.now(),'user_data':user_data,'form_id':f.id||''});
  });
  document.addEventListener('wpcf7mailsent',function(){
    DL.push({'event':'generate_lead','event_id':'lead_cf7_'+Date.now(),'user_data':user_data,'form_plugin':'contact_form_7'});
  });

  // ─── CLICK TO CALL ────────────────────────────────────────────────
  document.addEventListener('click',function(ev){
    var a=ev.target.closest('a[href^="tel:"]');if(!a)return;
    DL.push({'event':'click_to_call','phone_number':a.href.replace('tel:',''),'user_data':user_data});
  });

  // ─── EMAIL CLICK ──────────────────────────────────────────────────
  document.addEventListener('click',function(ev){
    var a=ev.target.closest('a[href^="mailto:"]');if(!a)return;
    DL.push({'event':'email_click','email_target':a.href.replace('mailto:','').split('?')[0],'user_data':user_data});
  });

  // ─── CHAT (WhatsApp/Messenger) ────────────────────────────────────
  document.addEventListener('click',function(ev){
    var a=ev.target.closest('a[href*="wa.me"],a[href*="whatsapp"],a[href*="m.me"]');if(!a)return;
    DL.push({'event':'chat_click','chat_type':a.href.indexOf('wa.me')>-1?'whatsapp':'messenger','user_data':user_data});
  });

  // ─── GET DIRECTIONS ───────────────────────────────────────────────
  document.addEventListener('click',function(ev){
    var a=ev.target.closest('a[href*="maps.google"],a[href*="google.com/maps"],a[href*="waze.com"]');if(!a)return;
    DL.push({'event':'get_directions','user_data':user_data});
  });

  // ─── SCROLL DEPTH ─────────────────────────────────────────────────
  var sf={};window.addEventListener('scroll',function(){var pct=Math.round(window.scrollY/(document.body.scrollHeight-window.innerHeight)*100);[25,50,75,90].forEach(function(t){if(pct>=t&&!sf[t]){sf[t]=1;DL.push({'event':'scroll_depth','scroll_percentage':t,'page_type':PAGE});}});});

  // ─── CUSTOMER STATUS ──────────────────────────────────────────────
  DL.push({'event':'customer_status','customer_type':document.cookie.match(/_pts_purchased/)?'returning':'new'});

  console.log('[DataLayer] ✓ Page:', PAGE, '| Events ready');
})();
</script>
