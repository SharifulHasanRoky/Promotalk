// ═══════════════════════════════════════════════════════════════════════
// SHOPIFY DATALAYER PRO - CUSTOMER EVENTS PIXEL
// ═══════════════════════════════════════════════════════════════════════
// Location: Shopify Admin → Settings → Customer events → Add custom pixel
// 
// Setup:
// 1. Click "Add custom pixel"
// 2. Name: DataLayer Pro
// 3. Permission: Permission not required
// 4. Paste this entire code in the "Code" section
// 5. Save → Connect
// ═══════════════════════════════════════════════════════════════════════

// page_viewed
analytics.subscribe("page_viewed", (event) => {
  window.top.postMessage({type: 'shopify_event', event: 'page_viewed', data: event}, '*');
});

// product_viewed
analytics.subscribe("product_viewed", (event) => {
  var p = event.data.productVariant;
  window.top.postMessage({type: 'shopify_event', event: 'view_item', data: {
    item_id: p.sku || p.id,
    item_name: p.product.title,
    price: parseFloat(p.price.amount),
    currency: p.price.currencyCode,
    item_brand: p.product.vendor,
    item_category: p.product.type
  }}, '*');
});

// product_added_to_cart
analytics.subscribe("product_added_to_cart", (event) => {
  var line = event.data.cartLine;
  var p = line.merchandise;
  window.top.postMessage({type: 'shopify_event', event: 'add_to_cart', data: {
    item_id: p.sku || p.id,
    item_name: p.product.title,
    price: parseFloat(p.price.amount),
    currency: p.price.currencyCode,
    quantity: line.quantity
  }}, '*');
});

// checkout_started
analytics.subscribe("checkout_started", (event) => {
  var c = event.data.checkout;
  window.top.postMessage({type: 'shopify_event', event: 'begin_checkout', data: {
    value: parseFloat(c.totalPrice.amount),
    currency: c.totalPrice.currencyCode,
    items: c.lineItems.map(function(li){
      return {
        item_id: li.variant.sku || li.variant.id,
        item_name: li.title,
        price: parseFloat(li.variant.price.amount),
        quantity: li.quantity
      };
    })
  }}, '*');
});

// checkout_completed (Purchase)
analytics.subscribe("checkout_completed", (event) => {
  var c = event.data.checkout;
  window.top.postMessage({type: 'shopify_event', event: 'purchase', data: {
    transaction_id: c.order.id,
    value: parseFloat(c.totalPrice.amount),
    currency: c.totalPrice.currencyCode,
    tax: parseFloat(c.totalTax.amount),
    shipping: parseFloat(c.shippingLine.price.amount),
    items: c.lineItems.map(function(li){
      return {
        item_id: li.variant.sku || li.variant.id,
        item_name: li.title,
        price: parseFloat(li.variant.price.amount),
        quantity: li.quantity
      };
    }),
    customer_email: c.email,
    customer_phone: c.phone
  }}, '*');
});

// search_submitted
analytics.subscribe("search_submitted", (event) => {
  window.top.postMessage({type: 'shopify_event', event: 'search', data: {
    search_term: event.data.searchResult.query
  }}, '*');
});
