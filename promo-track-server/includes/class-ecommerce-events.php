<?php
/**
 * Shared Ecommerce & Local Service Event Data
 * Provides product/cart/order data used by both Google Ads & Facebook modules.
 * Also fires shared hooks that both platforms listen to.
 */

if (!defined('ABSPATH')) exit;

class PTS_Ecommerce_Events {

    private $s;

    public function __construct() {
        $this->s = get_option('pts_settings', []);

        if (!class_exists('WooCommerce')) return;

        // Server-side: purchase event for both platforms
        add_action('woocommerce_checkout_order_processed', [$this, 'on_purchase'], 10, 3);

        // Enqueue frontend tracking JS
        add_action('wp_enqueue_scripts', [$this, 'enqueue_js']);
        add_action('wp_footer', [$this, 'inject_page_events'], 15);
    }

    public function enqueue_js() {
        wp_enqueue_script('pts-frontend', PTS_URL . 'assets/js/frontend.js', ['jquery'], PTS_VERSION, true);
        wp_localize_script('pts-frontend', 'ptsConfig', [
            'ajaxUrl'  => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('pts_nonce'),
            'currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD',
            'events'   => [
                'page_view'    => !empty($this->s['track_page_view']),
                'view_content' => !empty($this->s['track_view_content']),
                'add_to_cart'  => !empty($this->s['track_add_to_cart']),
                'checkout'     => !empty($this->s['track_initiate_checkout']),
                'purchase'     => !empty($this->s['track_purchase']),
                'lead'         => !empty($this->s['track_lead']),
                'search'       => !empty($this->s['track_search']),
                'contact'      => !empty($this->s['track_contact']),
            ],
        ]);
    }

    /**
     * Inject server-rendered page events (view_content, checkout, purchase, search)
     */
    public function inject_page_events() {
        // ViewContent on product page
        if (!empty($this->s['track_view_content']) && is_product()) {
            global $product;
            if ($product) {
                $data = $this->get_product_data($product);
                do_action('pts_event', 'ViewContent', $data);
            }
        }

        // InitiateCheckout
        if (!empty($this->s['track_initiate_checkout']) && is_checkout() && !is_order_received_page()) {
            $cart = WC()->cart;
            if ($cart && !$cart->is_empty()) {
                do_action('pts_event', 'InitiateCheckout', [
                    'value' => (float) $cart->get_cart_contents_total(),
                    'currency' => get_woocommerce_currency(),
                    'num_items' => $cart->get_cart_contents_count(),
                ]);
            }
        }

        // Search
        if (!empty($this->s['track_search']) && is_search()) {
            do_action('pts_event', 'Search', [
                'search_string' => get_search_query(),
            ]);
        }

        // Purchase (thank you page)
        if (!empty($this->s['track_purchase']) && is_order_received_page()) {
            global $wp;
            $order_id = absint($wp->query_vars['order-received'] ?? 0);
            if ($order_id) {
                $order = wc_get_order($order_id);
                if ($order && !$order->get_meta('_pts_tracked')) {
                    $data = $this->get_order_data($order);
                    do_action('pts_event', 'Purchase', $data);
                    do_action('pts_purchase_server', $order, $data);
                    $order->update_meta_data('_pts_tracked', 1);
                    $order->save();
                }
            }
        }
    }

    /**
     * Server-side purchase (fires on order creation for CAPI)
     */
    public function on_purchase($order_id, $posted_data, $order) {
        if (empty($this->s['track_purchase'])) return;

        $data = $this->get_order_data($order);
        do_action('pts_purchase_server', $order, $data);
    }

    /**
     * Get product data (shared format)
     */
    public function get_product_data($product) {
        $cats = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names']);
        return [
            'content_ids'  => [(string) $product->get_id()],
            'content_name' => $product->get_name(),
            'content_type' => 'product',
            'content_category' => $cats[0] ?? '',
            'value'        => (float) $product->get_price(),
            'currency'     => get_woocommerce_currency(),
        ];
    }

    /**
     * Get order data (shared format)
     */
    public function get_order_data($order) {
        $items = [];
        $content_ids = [];
        foreach ($order->get_items() as $item) {
            $p = $item->get_product();
            if (!$p) continue;
            $content_ids[] = (string) $p->get_id();
            $items[] = [
                'id'       => (string) $p->get_id(),
                'name'     => $p->get_name(),
                'price'    => (float) $p->get_price(),
                'quantity' => $item->get_quantity(),
            ];
        }

        return [
            'content_ids'    => $content_ids,
            'content_type'   => 'product',
            'value'          => (float) $order->get_total(),
            'currency'       => $order->get_currency(),
            'transaction_id' => $order->get_order_number(),
            'num_items'      => count($items),
            'items'          => $items,
            'order_id'       => $order->get_id(),
        ];
    }

    /**
     * Static: Get cart data for JS
     */
    public static function get_cart_data() {
        if (!function_exists('WC') || !WC()->cart) return [];
        $cart = WC()->cart;
        $ids = [];
        foreach ($cart->get_cart() as $item) {
            $p = $item['data'];
            if ($p) $ids[] = (string) $p->get_id();
        }
        return [
            'content_ids' => $ids,
            'content_type' => 'product',
            'value' => (float) $cart->get_cart_contents_total(),
            'currency' => get_woocommerce_currency(),
            'num_items' => $cart->get_cart_contents_count(),
        ];
    }
}
