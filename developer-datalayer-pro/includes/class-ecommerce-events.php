<?php
/**
 * Ecommerce Events - Full GA4 Ecommerce DataLayer
 * All events fire dynamically based on actual product/cart/order data.
 *
 * Events: view_item, view_item_list, select_item, add_to_cart, remove_from_cart,
 *         view_cart, begin_checkout, add_shipping_info, add_payment_info, purchase, refund
 */

if (!defined('ABSPATH')) exit;

class DDL_Pro_Ecommerce_Events {

    public function __construct() {
        if (!class_exists('WooCommerce')) return;

        // Server-rendered page events
        add_action('wp_footer', [$this, 'track_view_item'], 10);
        add_action('wp_footer', [$this, 'track_view_item_list'], 10);
        add_action('wp_footer', [$this, 'track_view_cart'], 10);
        add_action('wp_footer', [$this, 'track_begin_checkout'], 10);
        add_action('wp_footer', [$this, 'track_purchase'], 10);

        // JS-based interaction events
        add_action('wp_footer', [$this, 'inject_interaction_js'], 20);

        // Checkout step tracking
        add_action('woocommerce_after_checkout_form', [$this, 'inject_checkout_steps_js']);

        // Server-side hooks for Conversion API
        add_action('woocommerce_checkout_order_processed', [$this, 'fire_purchase_server'], 10, 3);
        add_action('woocommerce_order_refunded', [$this, 'fire_refund_server'], 10, 2);
    }

    /* ────────────────────────────────────────────────────────────────────────
       PAGE-BASED EVENTS
    ──────────────────────────────────────────────────────────────────────── */

    public function track_view_item() {
        if (!is_product()) return;
        global $product;
        if (!$product) return;

        $item = $this->build_item($product);
        ?>
        <script>
        window.dataLayer=window.dataLayer||[];
        window.dataLayer.push({ecommerce:null});
        window.dataLayer.push({
            'event':'view_item',
            'ecommerce':{
                'currency':'<?php echo esc_js(get_woocommerce_currency()); ?>',
                'value':<?php echo (float)$product->get_price(); ?>,
                'items':[<?php echo wp_json_encode($item); ?>]
            }
        });
        </script>
        <?php
    }

    public function track_view_item_list() {
        if (!is_shop() && !is_product_category() && !is_product_tag()) return;

        global $wp_query;
        $items = [];
        $idx = 0;
        foreach ($wp_query->posts as $post) {
            $p = wc_get_product($post->ID);
            if (!$p) continue;
            $item = $this->build_item($p, $idx);
            $item['item_list_name'] = $this->list_name();
            $item['item_list_id'] = $this->list_id();
            $items[] = $item;
            $idx++;
        }
        if (empty($items)) return;
        ?>
        <script>
        window.dataLayer=window.dataLayer||[];
        window.dataLayer.push({ecommerce:null});
        window.dataLayer.push({
            'event':'view_item_list',
            'ecommerce':{
                'item_list_name':'<?php echo esc_js($this->list_name()); ?>',
                'item_list_id':'<?php echo esc_js($this->list_id()); ?>',
                'items':<?php echo wp_json_encode($items); ?>
            }
        });
        </script>
        <?php
    }

    public function track_view_cart() {
        if (!is_cart()) return;
        $cart = WC()->cart;
        if (!$cart || $cart->is_empty()) return;

        $items = $this->cart_items();
        ?>
        <script>
        window.dataLayer=window.dataLayer||[];
        window.dataLayer.push({ecommerce:null});
        window.dataLayer.push({
            'event':'view_cart',
            'ecommerce':{
                'currency':'<?php echo esc_js(get_woocommerce_currency()); ?>',
                'value':<?php echo (float)$cart->get_cart_contents_total(); ?>,
                'items':<?php echo wp_json_encode($items); ?>
            }
        });
        </script>
        <?php
    }

    public function track_begin_checkout() {
        if (!is_checkout() || is_order_received_page()) return;
        $cart = WC()->cart;
        if (!$cart || $cart->is_empty()) return;

        $items = $this->cart_items();
        $coupon = implode(',', $cart->get_applied_coupons());
        ?>
        <script>
        window.dataLayer=window.dataLayer||[];
        window.dataLayer.push({ecommerce:null});
        window.dataLayer.push({
            'event':'begin_checkout',
            'ecommerce':{
                'currency':'<?php echo esc_js(get_woocommerce_currency()); ?>',
                'value':<?php echo (float)$cart->get_cart_contents_total(); ?>,
                'coupon':'<?php echo esc_js($coupon); ?>',
                'items':<?php echo wp_json_encode($items); ?>
            }
        });
        </script>
        <?php
    }

    public function track_purchase() {
        if (!is_order_received_page()) return;

        global $wp;
        $order_id = absint($wp->query_vars['order-received'] ?? 0);
        if (!$order_id) return;

        $order = wc_get_order($order_id);
        if (!$order) return;

        // Prevent duplicate fires
        if ($order->get_meta('_ddl_ecom_tracked')) return;

        $items = $this->order_items($order);
        $coupons = implode(',', $order->get_coupon_codes());
        ?>
        <script>
        window.dataLayer=window.dataLayer||[];
        window.dataLayer.push({ecommerce:null});
        window.dataLayer.push({
            'event':'purchase',
            'ecommerce':{
                'transaction_id':'<?php echo esc_js($order->get_order_number()); ?>',
                'value':<?php echo (float)$order->get_total(); ?>,
                'tax':<?php echo (float)$order->get_total_tax(); ?>,
                'shipping':<?php echo (float)$order->get_shipping_total(); ?>,
                'currency':'<?php echo esc_js($order->get_currency()); ?>',
                'coupon':'<?php echo esc_js($coupons); ?>',
                'payment_method':'<?php echo esc_js($order->get_payment_method_title()); ?>',
                'shipping_method':'<?php echo esc_js($order->get_shipping_method()); ?>',
                'new_customer':<?php echo $this->is_new_customer($order) ? 'true' : 'false'; ?>,
                'items':<?php echo wp_json_encode($items); ?>
            }
        });
        </script>
        <?php
        $order->update_meta_data('_ddl_ecom_tracked', 1);
        $order->save();
    }

    /* ────────────────────────────────────────────────────────────────────────
       JS-BASED INTERACTION EVENTS
    ──────────────────────────────────────────────────────────────────────── */

    public function inject_interaction_js() {
        ?>
        <script>
        (function($){
            var currency = ddlProConfig.currency || 'USD';

            /* select_item - click product in list */
            $(document).on('click', '.products .product a.woocommerce-LoopProduct-link, .wc-block-grid__product a', function(){
                var $el = $(this).closest('.product, .wc-block-grid__product');
                var id = $el.find('.add_to_cart_button').data('product_id') || $el.data('product-id') || '';
                var name = $(this).find('.woocommerce-loop-product__title, h2, h3').first().text().trim();
                var price = $el.find('.price ins .amount, .price > .amount').first().text().replace(/[^0-9.]/g,'');
                window.dataLayer=window.dataLayer||[];
                window.dataLayer.push({ecommerce:null});
                window.dataLayer.push({
                    'event':'select_item',
                    'ecommerce':{
                        'items':[{'item_id':String(id),'item_name':name,'price':parseFloat(price)||0}]
                    }
                });
            });

            /* add_to_cart - AJAX (archive pages) */
            $(document.body).on('added_to_cart', function(e, frag, hash, $btn){
                var id = $btn.data('product_id') || '';
                var qty = parseInt($btn.data('quantity')) || 1;
                var price = parseFloat($btn.data('product_price') || $btn.closest('.product').find('.price ins .amount, .price > .amount').first().text().replace(/[^0-9.]/g,'')) || 0;
                var name = $btn.closest('.product').find('.woocommerce-loop-product__title, h2').first().text().trim();
                window.dataLayer=window.dataLayer||[];
                window.dataLayer.push({ecommerce:null});
                window.dataLayer.push({
                    'event':'add_to_cart',
                    'ecommerce':{
                        'currency':currency,
                        'value':price*qty,
                        'items':[{'item_id':String(id),'item_name':name,'price':price,'quantity':qty}]
                    }
                });
            });

            /* add_to_cart - single product form submit */
            $('form.cart').on('submit', function(){
                var $form = $(this);
                var qty = parseInt($form.find('[name="quantity"]').val()) || 1;
                var productId = $form.find('[name="add-to-cart"], button[name="add-to-cart"]').val() || $form.find('[name="product_id"]').val() || '';
                var $product = $form.closest('.product');
                var name = $product.find('.product_title, h1').first().text().trim();
                var price = parseFloat($product.find('.price ins .amount, .price > .amount').first().text().replace(/[^0-9.]/g,'')) || 0;
                window.dataLayer=window.dataLayer||[];
                window.dataLayer.push({ecommerce:null});
                window.dataLayer.push({
                    'event':'add_to_cart',
                    'ecommerce':{
                        'currency':currency,
                        'value':price*qty,
                        'items':[{'item_id':String(productId),'item_name':name,'price':price,'quantity':qty}]
                    }
                });
            });

            /* remove_from_cart */
            $(document.body).on('removed_from_cart', function(e, frag, hash, $btn){
                var $row = $btn.closest('tr, .cart_item, .woocommerce-cart-form__cart-item');
                var name = $row.find('.product-name a').text().trim();
                var price = parseFloat($row.find('.product-price .amount').text().replace(/[^0-9.]/g,'')) || 0;
                window.dataLayer=window.dataLayer||[];
                window.dataLayer.push({ecommerce:null});
                window.dataLayer.push({
                    'event':'remove_from_cart',
                    'ecommerce':{
                        'currency':currency,
                        'items':[{'item_name':name,'price':price}]
                    }
                });
            });
        })(jQuery);
        </script>
        <?php
    }

    /**
     * Checkout step tracking: add_shipping_info & add_payment_info
     */
    public function inject_checkout_steps_js() {
        ?>
        <script>
        (function($){
            var currency = ddlProConfig.currency || 'USD';
            var shippingFired = false, paymentFired = false;

            $(document.body).on('updated_checkout', function(){
                if (!shippingFired) {
                    var method = $('input[name^="shipping_method"]:checked, input[name^="shipping_method"][type="hidden"]').val();
                    if (method) {
                        shippingFired = true;
                        window.dataLayer=window.dataLayer||[];
                        window.dataLayer.push({ecommerce:null});
                        window.dataLayer.push({'event':'add_shipping_info','ecommerce':{'currency':currency,'shipping_tier':method}});
                    }
                }
            });

            $('form.checkout').on('checkout_place_order', function(){
                if (!paymentFired) {
                    var method = $('input[name="payment_method"]:checked').val();
                    if (method) {
                        paymentFired = true;
                        window.dataLayer=window.dataLayer||[];
                        window.dataLayer.push({ecommerce:null});
                        window.dataLayer.push({'event':'add_payment_info','ecommerce':{'currency':currency,'payment_type':method}});
                    }
                }
                return true;
            });
        })(jQuery);
        </script>
        <?php
    }

    /* ────────────────────────────────────────────────────────────────────────
       SERVER-SIDE HOOKS (for Conversion API)
    ──────────────────────────────────────────────────────────────────────── */

    public function fire_purchase_server($order_id, $posted_data, $order) {
        $items = [];
        foreach ($order->get_items() as $oi) {
            $p = $oi->get_product();
            if (!$p) continue;
            $items[] = [
                'item_id'   => (string) $p->get_id(),
                'item_name' => $p->get_name(),
                'price'     => (float) $p->get_price(),
                'quantity'  => $oi->get_quantity(),
            ];
        }

        $data = [
            'transaction_id'  => $order->get_order_number(),
            'value'           => (float) $order->get_total(),
            'currency'        => $order->get_currency(),
            'tax'             => (float) $order->get_total_tax(),
            'shipping'        => (float) $order->get_shipping_total(),
            'items'           => $items,
            'user_email'      => $order->get_billing_email(),
            'user_phone'      => $order->get_billing_phone(),
            'user_first_name' => $order->get_billing_first_name(),
            'user_last_name'  => $order->get_billing_last_name(),
            'user_city'       => $order->get_billing_city(),
            'user_state'      => $order->get_billing_state(),
            'user_country'    => $order->get_billing_country(),
            'user_postcode'   => $order->get_billing_postcode(),
        ];

        do_action('ddl_pro_server_event', 'purchase', $data);
    }

    public function fire_refund_server($order_id, $refund_id) {
        $order = wc_get_order($order_id);
        $refund = wc_get_order($refund_id);
        if (!$order || !$refund) return;

        do_action('ddl_pro_server_event', 'refund', [
            'transaction_id' => $order->get_order_number(),
            'value'          => abs((float) $refund->get_total()),
            'currency'       => $order->get_currency(),
        ]);
    }

    /* ────────────────────────────────────────────────────────────────────────
       HELPERS — Dynamic product data from WooCommerce
    ──────────────────────────────────────────────────────────────────────── */

    private function build_item($product, $index = 0) {
        $cats = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names']);
        $brand = $this->get_brand($product);

        $item = [
            'item_id'    => (string) $product->get_id(),
            'item_name'  => $product->get_name(),
            'price'      => (float) $product->get_price(),
            'item_brand' => $brand,
            'index'      => $index,
            'quantity'   => 1,
        ];

        // Up to 5 categories
        if (!empty($cats[0])) $item['item_category']  = $cats[0];
        if (!empty($cats[1])) $item['item_category2'] = $cats[1];
        if (!empty($cats[2])) $item['item_category3'] = $cats[2];
        if (!empty($cats[3])) $item['item_category4'] = $cats[3];
        if (!empty($cats[4])) $item['item_category5'] = $cats[4];

        // Variant
        if ($product->is_type('variation')) {
            $item['item_variant'] = implode(', ', $product->get_variation_attributes());
        }

        // Discount
        if ($product->is_on_sale() && $product->get_regular_price()) {
            $item['discount'] = round((float)$product->get_regular_price() - (float)$product->get_sale_price(), 2);
        }

        // SKU
        $sku = $product->get_sku();
        if ($sku) $item['item_sku'] = $sku;

        return $item;
    }

    private function cart_items() {
        $items = [];
        $idx = 0;
        foreach (WC()->cart->get_cart() as $ci) {
            $p = $ci['data'];
            if (!$p) continue;
            $item = $this->build_item($p, $idx);
            $item['quantity'] = $ci['quantity'];
            $items[] = $item;
            $idx++;
        }
        return $items;
    }

    private function order_items($order) {
        $items = [];
        $idx = 0;
        foreach ($order->get_items() as $oi) {
            $p = $oi->get_product();
            if (!$p) continue;
            $item = $this->build_item($p, $idx);
            $item['quantity'] = $oi->get_quantity();
            $item['discount'] = round((float)($oi->get_subtotal() - $oi->get_total()), 2);
            $items[] = $item;
            $idx++;
        }
        return $items;
    }

    private function get_brand($product) {
        $taxonomies = ['product_brand', 'pa_brand', 'pwb-brand', 'yith_product_brand'];
        foreach ($taxonomies as $tax) {
            if (!taxonomy_exists($tax)) continue;
            $terms = wp_get_post_terms($product->get_id(), $tax, ['fields' => 'names']);
            if (!empty($terms) && !is_wp_error($terms)) return $terms[0];
        }
        $meta = get_post_meta($product->get_id(), '_brand', true);
        return $meta ?: '';
    }

    private function list_name() {
        if (is_shop()) return 'Shop';
        if (is_product_category()) {
            $t = get_queried_object();
            return $t ? $t->name : 'Category';
        }
        if (is_product_tag()) {
            $t = get_queried_object();
            return $t ? 'Tag: ' . $t->name : 'Tag';
        }
        if (is_search()) return 'Search Results';
        return 'Product List';
    }

    private function list_id() {
        if (is_shop()) return 'shop';
        if (is_product_category()) {
            $t = get_queried_object();
            return $t ? 'cat_' . $t->term_id : 'category';
        }
        if (is_product_tag()) {
            $t = get_queried_object();
            return $t ? 'tag_' . $t->term_id : 'tag';
        }
        if (is_search()) return 'search';
        return 'list';
    }

    private function is_new_customer($order) {
        $email = $order->get_billing_email();
        if (!$email) return true;
        $prev = wc_get_orders([
            'billing_email' => $email,
            'status'        => ['wc-completed', 'wc-processing'],
            'limit'         => 2,
            'exclude'       => [$order->get_id()],
        ]);
        return empty($prev);
    }
}
