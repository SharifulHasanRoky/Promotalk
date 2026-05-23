# Shopify DataLayer Pro

A complete GA4-schema DataLayer app for Shopify stores. One-click activation via Theme App Extension — tracks all ecommerce, local service, and interaction events with full multi-platform support.

## Features

### Ecommerce Events (GA4 Schema)
- `page_view` — every page with page_type, user_data
- `view_item` — product pages with full item data
- `view_item_list` — collection/category pages
- `search` / `view_search_results` — search pages
- `view_cart` — cart page
- `add_to_cart` — add to cart button clicks
- `begin_checkout` — checkout initiation
- `purchase` — order complete (thank you page)

### Local Service Events
- `click_to_call` — phone link clicks
- `email_click` — mailto link clicks
- `chat_click` — WhatsApp/Messenger clicks
- `get_directions` — Google Maps/Waze clicks
- `generate_lead` — form submissions

### Engagement Events
- `scroll_depth` — 25%, 50%, 75%, 90%
- `customer_status` — new vs returning

### All Platform Parameters (in each event)
| Platform | Parameters |
|----------|-----------|
| GA4 | ecommerce.items, value, currency, transaction_id |
| Facebook | content_ids, content_name, content_type, contents, value |
| TikTok | contents (array), value, currency |
| Pinterest | product_id, product_name, value |
| LinkedIn | content_name, value, currency |
| Twitter/X | content_ids, value, num_items |

### Google Ads Dynamic Remarketing (Updated Events)
- `view_item_remarketing`
- `view_item_list_remarketing`
- `view_search_results_remarketing`
- `add_to_cart_remarketing`
- `purchase_remarketing`

Each with: `google_business_vertical`, `dynx_itemid`, `dynx_pagetype`, `dynx_totalvalue`, `ecomm_prodid`, `ecomm_pagetype`, `ecomm_totalvalue`

### Event Match Quality 10/10
- Email, Phone, Name (from customer object)
- City, Region, Postal Code, Country
- external_id (persistent first-party cookie)
- _fbp, _fbc (captured/extended)
- gclid, fbclid, ttclid, li_fat_id (from URL)
- client_user_agent, page_location, page_referrer
- Customer ID, orders_count, total_spent

### Cookie Management
- `_pts_eid` — persistent external_id (390 days)
- `_fbc` — captures fbclid from URL
- `_gcl_aw` — captures gclid from URL
- `_pts_purchased` — marks returning customers

## Installation

### Method 1: Theme App Extension (Recommended)
1. Install the app from Shopify App Store
2. Go to **Online Store → Themes → Customize**
3. Add **DataLayer (Head)** block to theme header
4. Add **DataLayer (Body)** block to theme footer
5. Toggle ON → Done!

### Method 2: Manual (Checkout Script)
1. Copy the contents of `snippets/purchase-datalayer.liquid`
2. Go to **Settings → Checkout → Additional Scripts**
3. Paste the code
4. Save

## GTM Triggers

In Google Tag Manager, create triggers for these custom events:

| Trigger Name | Event Name |
|-------------|-----------|
| View Item | `view_item` |
| View Item List | `view_item_list` |
| Search | `search` |
| Add to Cart | `add_to_cart` |
| Begin Checkout | `begin_checkout` |
| Purchase | `purchase` |
| Lead | `generate_lead` |
| Phone Call | `click_to_call` |
| Remarketing - View Item | `view_item_remarketing` |
| Remarketing - Purchase | `purchase_remarketing` |
| Remarketing - Add to Cart | `add_to_cart_remarketing` |

## Data Available in Variables

In GTM, use **Data Layer Variables** to access:

- `ecommerce.items` — product array
- `ecommerce.value` — total value
- `ecommerce.transaction_id` — order number
- `user_data.email` — customer email
- `user_data.external_id` — persistent visitor ID
- `content_ids` — for Facebook
- `contents` — for TikTok
- `dynx_itemid` — for Google Ads remarketing

## Compatibility
- Shopify 2.0 themes (all)
- Shopify Plus (checkout extensibility)
- Works with GTM, sGTM, Stape, Addingwell
- All browsers (no ES6+ required)

## License
MIT
