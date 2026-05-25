<?php
/**
 * Google Ads Dynamic Remarketing Events
 * SEPARATE from GA4 ecommerce — these push dynx_* and ecomm_* params
 * specifically for Google Ads audience building & dynamic remarketing campaigns.
 *
 * Events: search, view_item_list, view_item, add_to_cart, begin_checkout, purchase
 * Each includes: google_business_vertical, dynx_itemid, dynx_pagetype, dynx_totalvalue
 */

if (!defined('ABSPATH')) exit;

class DDL_Pro_Remarketing_Events {

    private $settings;
    private $vertical;

    public function __construct() {
        if (!class_exists('WooCommerce')) return;

        $this->settings = get_option('ddl_pro_settings', []);
        $this->vertical = $this->settings['business_vertical'] ?? 'retail';

        add_action('wp_footer', [$this, 'track_home'], 12);
        add_action('wp_footer', [$this, 'track_search'], 12);
        add_action('wp_footer', [$this, 'track_view_item_list'], 12);
        add_action('wp_footer', [$this, 'track_view_item'], 12);
        add_action('wp_footer', [$this, 'track_cart'], 12);
        add_action('wp_footer', [$this, 'track_checkout'], 12);
        add_action('wp_footer', [$this, 'track_purchase'], 12);
        add_action('wp_footer', [$this, 'inject_add_to_cart_js'], 20);
    }

    /* ─── HOME ─────────────────────────────────────────────────────────── */

    public function track_home() {
        if (!is_front_page()) return;
        $this->push_remarketing('page_view', [], 'home', 0);
    }

    /* ─── SEARCH ───────────────────────────────────────────────────────── */

    public function track_search() {
        if (!is_search()) return;

        global $wp_query;
        $ids = [];
        $total = 0;

        foreach ($wp_query->posts as $post) {
            if ($post->post_type !== 'product') continue;
            $p = wc_get_product($post->ID);
            if (!$p) continue;
            $ids[] = $this->product_id($p);
            $total += (float) $p->get_price();
            if (count($ids) >= 10) break;
        }
        ?>
        <script>
        window.dataLayer=window.dataLayer||[];
        window.dataLayer.push({
            'event':'view_search_results',
            'search_term':'<?php echo esc_js(get_search_query()); ?>',
            'google_business_vertical':'<?php echo esc_js($this->vertical); ?>',
            'dynx_itemid':<?php echo wp_json_encode($ids); ?>,
            'dynx_pagetype':'searchresults',
            'dynx_totalvalue':<?php echo $total; ?>,
            'ecomm_prodid':<?php echo wp_json_encode($ids); ?>,
            'ecomm_pagetype':'searchresults',
            'ecomm_totalvalue':<?php echo $total; ?>
        });
        </script>
        <?php
    }

    /* ─── VIEW ITEM LIST (shop/category) ───────────────────────────────── */

    public function track_view_item_list() {
        if (!is_shop() && !is_product_category() && !is_product_tag()) return;

        global $wp_query;
        $ids = [];
        $total = 0;
        $items = [];

        foreach ($wp_query->posts as $post) {
            $p = wc_get_product($post->ID);
            if (!$p) continue;
            $pid = $this->product_id($p);
            $ids[] = $pid;
            $price = (float) $p->get_price();
            $total += $price;
            $items[] = [
                'id' => $pid,
                'google_business_vertical' => $this->vertical,
                'name' => $p->get_name(),
                'price' => $price,
            ];
        }

        if (empty($ids)) return;
        ?>
        <script>
        window.dataLayer=window.dataLayer||[];
        window.dataLayer.push({
            'event':'view_item_list',
            'google_business_vertical':'<?php echo esc_js($this->vertical); ?>',
            'dynx_itemid':<?php echo wp_json_encode($ids); ?>,
            'dynx_pagetype':'category',
            'dynx_totalvalue':<?php echo $total; ?>,
            'ecomm_prodid':<?php echo wp_json_encode($ids); ?>,
            'ecomm_pagetype':'category',
            'ecomm_totalvalue':<?php echo $total; ?>,
            'items':<?php echo wp_json_encode($items); ?>
        });
        </script>
        <?php
    }

    /* ─── VIEW ITEM (product page) ─────────────────────────────────────── */

    public function track_view_item() {
        if (!is_product()) return;

        global $product;
        if (!$product) return;

        $pid = $this->product_id($product);
        $price = (float) $product->get_price();
        ?>
        <script>
        window.dataLayer=window.dataLayer||[];
        window.dataLayer.push({
            'event':'view_item',
            'google_business_vertical':'<?php echo esc_js($this->vertical); ?>',
            'dynx_itemid':'<?php echo esc_js($pid); ?>',
            'dynx_pagetype':'offerdetail',
            'dynx_totalvalue':<?php echo $price; ?>,
            'ecomm_prodid':'<?php echo esc_js($pid); ?>',
            'ecomm_pagetype':'product',
            'ecomm_totalvalue':<?php echo $price; ?>,
            'items':[{
                'id':'<?php echo esc_js($pid); ?>',
                'google_business_vertical':'<?php echo esc_js($this->vertical); ?>',
                'name':'<?php echo esc_js($product->get_name()); ?>',
                'price':<?php echo $price; ?>
            }]
        });
        </script>
        <?php
    }

    /* ─── CART / CHECKOUT ──────────────────────────────────────────────── */

    public function track_cart() {
        if (!is_cart()) return;
        $this->push_cart_remarketing('cart', 'conversionintent');
    }

    public function track_checkout() {
        if (!is_checkout() || is_order_received_page()) return;
        $this->push_cart_remarketing('checkout', 'conversionintent');
    }

    /* ─── PURCHASE ─────────────────────────────────────────────────────── */

    public function track_purchase() {
        if (!is_order_received_page()) return;

        global $wp;
        $order_id = absint($wp->query_vars['order-received'] ?? 0);
        if (!$order_id) return;

        $order = wc_get_order($order_id);
        if (!$order) return;
        if ($order->get_meta('_ddl_remarketing_tracked')) return;

        $ids = [];
        $items = [];
        foreach ($order->get_items() as $oi) {
            $p = $oi->get_product();
            if (!$p) continue;
            $pid = $this->product_id($p);
            $ids[] = $pid;
            $items[] = [
                'id' => $pid,
                'google_business_vertical' => $this->vertical,
                'name' => $p->get_name(),
                'price' => (float) $p->get_price(),
                'quantity' => $oi->get_quantity(),
            ];
        }
        ?>
        <script>
        window.dataLayer=window.dataLayer||[];
        window.dataLayer.push({
            'event':'purchase',
            'google_business_vertical':'<?php echo esc_js($this->vertical); ?>',
            'dynx_itemid':<?php echo wp_json_encode($ids); ?>,
            'dynx_pagetype':'conversion',
            'dynx_totalvalue':<?php echo (float)$order->get_total(); ?>,
            'ecomm_prodid':<?php echo wp_json_encode($ids); ?>,
            'ecomm_pagetype':'purchase',
            'ecomm_totalvalue':<?php echo (float)$order->get_total(); ?>,
            'transaction_id':'<?php echo esc_js($order->get_order_number()); ?>',
            'new_customer':<?php echo $this->is_new_customer($order) ? 'true' : 'false'; ?>,
            'items':<?php echo wp_json_encode($items); ?>
        });
        </script>
        <?php
        $order->update_meta_data('_ddl_remarketing_tracked', 1);
        $order->save();
    }

    /* ─── ADD TO CART (JS) ─────────────────────────────────────────────── */

    public function inject_add_to_cart_js() {
        ?>
        <script>
        (function($){
            var vertical = '<?php echo esc_js($this->vertical); ?>';

            /* AJAX add_to_cart on archive */
            $(document.body).on('added_to_cart', function(e, frag, hash, $btn){
                var id = String($btn.data('product_id') || '');
                var price = parseFloat($btn.data('product_price') || 0);
                window.dataLayer=window.dataLayer||[];
                window.dataLayer.push({
                    'event':'add_to_cart',
                    'google_business_vertical': vertical,
                    'dynx_itemid': id,
                    'dynx_pagetype':'conversionintent',
                    'dynx_totalvalue': price,
                    'ecomm_prodid': id,
                    'ecomm_pagetype':'cart',
                    'ecomm_totalvalue': price,
                    'items':[{'id':id,'google_business_vertical':vertical,'price':price,'quantity':1}]
                });
            });

            /* Single product add_to_cart */
            $('form.cart').on('submit', function(){
                var $p = $(this).closest('.product');
                var id = $(this).find('[name="add-to-cart"], button[name="add-to-cart"]').val() || '';
                var price = parseFloat($p.find('.price ins .amount, .price > .amount').first().text().replace(/[^0-9.]/g,'')) || 0;
                var qty = parseInt($(this).find('[name="quantity"]').val()) || 1;
                window.dataLayer=window.dataLayer||[];
                window.dataLayer.push({
                    'event':'add_to_cart',
                    'google_business_vertical': vertical,
                    'dynx_itemid': String(id),
                    'dynx_pagetype':'conversionintent',
                    'dynx_totalvalue': price * qty,
                    'ecomm_prodid': String(id),
                    'ecomm_pagetype':'cart',
                    'ecomm_totalvalue': price * qty,
                    'items':[{'id':String(id),'google_business_vertical':vertical,'price':price,'quantity':qty}]
                });
            });
        })(jQuery);
        </script>
        <?php
    }

    /* ────────────────────────────────────────────────────────────────────────
       HELPERS
    ──────────────────────────────────────────────────────────────────────── */

    private function push_cart_remarketing($page_type, $dynx_page) {
        $cart = WC()->cart;
        if (!$cart || $cart->is_empty()) return;

        $ids = [];
        $items = [];
        foreach ($cart->get_cart() as $ci) {
            $p = $ci['data'];
            if (!$p) continue;
            $pid = $this->product_id($p);
            $ids[] = $pid;
            $items[] = [
                'id' => $pid,
                'google_business_vertical' => $this->vertical,
                'name' => $p->get_name(),
                'price' => (float) $p->get_price(),
                'quantity' => $ci['quantity'],
            ];
        }
        ?>
        <script>
        window.dataLayer=window.dataLayer||[];
        window.dataLayer.push({
            'event':'begin_checkout',
            'google_business_vertical':'<?php echo esc_js($this->vertical); ?>',
            'dynx_itemid':<?php echo wp_json_encode($ids); ?>,
            'dynx_pagetype':'<?php echo esc_js($dynx_page); ?>',
            'dynx_totalvalue':<?php echo (float)$cart->get_cart_contents_total(); ?>,
            'ecomm_prodid':<?php echo wp_json_encode($ids); ?>,
            'ecomm_pagetype':'<?php echo esc_js($page_type); ?>',
            'ecomm_totalvalue':<?php echo (float)$cart->get_cart_contents_total(); ?>,
            'items':<?php echo wp_json_encode($items); ?>
        });
        </script>
        <?php
    }

    private function push_remarketing($event, $ids, $pagetype, $value) {
        ?>
        <script>
        window.dataLayer=window.dataLayer||[];
        window.dataLayer.push({
            'event':'<?php echo esc_js($event); ?>',
            'google_business_vertical':'<?php echo esc_js($this->vertical); ?>',
            'dynx_itemid':<?php echo wp_json_encode($ids); ?>,
            'dynx_pagetype':'<?php echo esc_js($pagetype); ?>',
            'dynx_totalvalue':<?php echo (float)$value; ?>,
            'ecomm_prodid':<?php echo wp_json_encode($ids); ?>,
            'ecomm_pagetype':'<?php echo esc_js($pagetype); ?>',
            'ecomm_totalvalue':<?php echo (float)$value; ?>
        });
        </script>
        <?php
    }

    /**
     * Get product identifier (SKU or ID based on settings)
     */
    private function product_id($product) {
        $type = $this->settings['remarketing_id_type'] ?? 'sku_or_id';
        switch ($type) {
            case 'sku':
                return $product->get_sku() ?: (string) $product->get_id();
            case 'id':
                return (string) $product->get_id();
            default: // sku_or_id
                $sku = $product->get_sku();
                return $sku ?: (string) $product->get_id();
        }
    }

    private function is_new_customer($order) {
        $email = $order->get_billing_email();
        if (!$email) return true;
        $prev = wc_get_orders([
            'billing_email' => $email,
            'status' => ['wc-completed', 'wc-processing'],
            'limit' => 2,
            'exclude' => [$order->get_id()],
        ]);
        return empty($prev);
    }
}
