<?php
/**
 * Plugin Name: Dealer System
 * Description: Force login and dealer management for stock system
 * Version: 2.0.6
 * Author: Vygox
 */

if (!defined('ABSPATH')) {
    exit;
}

define('DEALER_SYSTEM_PATH', plugin_dir_path(__FILE__));
define('DEALER_SYSTEM_URL', plugin_dir_url(__FILE__));

/**
 * Disable WooCommerce Coming Soon mode completely
 */
add_filter('woocommerce_coming_soon_exclude', '__return_true');
add_filter('woocommerce_is_coming_soon_page', '__return_false');
add_action('init', function() {
    remove_action('template_redirect', array('Automattic\WooCommerce\Admin\Features\LaunchYourStore', 'maybe_show_coming_soon_page'), 10);
}, 1);

/**
 * Custom logout handler - instant logout without confirmation
 */
add_action('init', function () {
    if (isset($_GET['dealer_logout']) && $_GET['dealer_logout'] === '1') {
        if (isset($_GET['_nonce']) && wp_verify_nonce($_GET['_nonce'], 'dealer_logout')) {
            wp_logout();
            wp_redirect(home_url('/login/'));
            exit;
        }
    }
});

/**
 * Helper function to get dealer logout URL
 */
function dealer_logout_url() {
    return add_query_arg([
        'dealer_logout' => '1',
        '_nonce' => wp_create_nonce('dealer_logout')
    ], home_url('/'));
}

/**
 * Force login - redirect to login page if not logged in
 */
add_action('template_redirect', function () {
    if (is_admin() || is_page('login') || wp_doing_ajax()) {
        return;
    }

    if (defined('DOING_AJAX') || defined('REST_REQUEST')) {
        return;
    }

    if (is_page('my-account') || strpos($_SERVER['REQUEST_URI'], 'my-account') !== false) {
        return;
    }

    if (!is_user_logged_in()) {
        wp_redirect(home_url('/login/'));
        exit;
    }
});

/**
 * Redirect after login based on user role
 */
add_filter('woocommerce_login_redirect', function ($redirect, $user) {
    if (in_array('dealer', (array) $user->roles)) {
        return home_url('/');
    }
    if (in_array('administrator', (array) $user->roles)) {
        return admin_url();
    }
    return $redirect;
}, 99, 2);

// Also hook into WordPress login redirect
add_filter('login_redirect', function ($redirect, $request, $user) {
    if (!is_wp_error($user) && in_array('dealer', (array) $user->roles)) {
        return home_url('/');
    }
    return $redirect;
}, 99, 3);

/**
 * Redirect dealers away from my-account page to homepage
 */
add_action('template_redirect', function () {
    if (is_account_page() && is_user_logged_in()) {
        $user = wp_get_current_user();
        if (in_array('dealer', (array) $user->roles)) {
            // Allow orders endpoint
            if (is_wc_endpoint_url('orders') || is_wc_endpoint_url('view-order') || is_wc_endpoint_url('order-pay')) {
                return;
            }
            // Redirect to homepage for other my-account pages
            wp_redirect(home_url('/'));
            exit;
        }
    }
}, 1);

/**
 * Hide admin bar for dealers
 */
add_action('after_setup_theme', function () {
    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        if (in_array('dealer', (array) $user->roles)) {
            show_admin_bar(false);
        }
    }
});

/**
 * Prevent dealers from accessing wp-admin
 */
add_action('admin_init', function () {
    if (wp_doing_ajax()) {
        return;
    }

    $user = wp_get_current_user();
    if (in_array('dealer', (array) $user->roles)) {
        wp_redirect(home_url('/'));
        exit;
    }
});

/**
 * Enqueue React scripts and styles
 */
add_action('wp_enqueue_scripts', function () {
    if (is_admin()) {
        return;
    }

    $dist_path = DEALER_SYSTEM_PATH . 'dist/';
    $dist_url = DEALER_SYSTEM_URL . 'dist/';

    // Check if React build exists
    if (!file_exists($dist_path . 'css/style.css')) {
        return;
    }

    // Common styles
    wp_enqueue_style('dealer-react-styles', $dist_url . 'css/style.css', [], time());

    // Page-specific scripts (ES modules)
    if (is_page('login') && !is_user_logged_in()) {
        wp_enqueue_script('dealer-login', $dist_url . 'js/login.js', [], time(), true);
        wp_localize_script('dealer-login', 'dealerLogin', [
            'loginUrl' => wc_get_page_permalink('myaccount'),
            'nonce' => wp_create_nonce('woocommerce-login'),
            'redirect' => home_url('/')
        ]);
    }

    if (is_page('inventory') && is_user_logged_in()) {
        wp_enqueue_script('dealer-inventory', $dist_url . 'js/inventory.js', [], time(), true);
        wp_localize_script('dealer-inventory', 'dealerInventory', dealer_get_inventory_data());
    }

    // Cart page
    if (is_cart() && is_user_logged_in()) {
        wp_enqueue_script('dealer-cart', $dist_url . 'js/cart.js', [], time(), true);
        wp_localize_script('dealer-cart', 'dealerCart', dealer_get_cart_data());
    }

    // Checkout page
    if (is_checkout() && !is_wc_endpoint_url('order-received') && !is_wc_endpoint_url('order-pay') && is_user_logged_in()) {
        wp_enqueue_script('dealer-checkout', $dist_url . 'js/checkout.js', [], time(), true);
        wp_localize_script('dealer-checkout', 'dealerCheckout', dealer_get_checkout_data());
    }

    // Orders page
    if (is_wc_endpoint_url('orders') && is_user_logged_in()) {
        wp_enqueue_script('dealer-orders', $dist_url . 'js/orders.js', [], time(), true);
        wp_localize_script('dealer-orders', 'dealerOrders', dealer_get_orders_data());
    }
});

/**
 * Add type="module" to React scripts
 */
add_filter('script_loader_tag', function ($tag, $handle, $src) {
    $module_handles = ['dealer-login', 'dealer-inventory', 'dealer-cart', 'dealer-orders', 'dealer-checkout'];

    if (in_array($handle, $module_handles)) {
        $tag = str_replace('<script ', '<script type="module" ', $tag);
    }

    return $tag;
}, 10, 3);

/**
 * Get inventory data for React
 */
function dealer_get_inventory_data() {
    return [
        'products' => [],
        'cartUrl' => wc_get_cart_url(),
        'nonce' => wp_create_nonce('wc_store_api'),
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'cartActionNonce' => wp_create_nonce('dealer_cart_action'),
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'addToCartNonce' => wp_create_nonce('dealer_add_to_cart'),
        'searchNonce' => wp_create_nonce('dealer_search_products')
    ];
}

/**
 * Helper function to format product data
 */
function dealer_format_product($product_id) {
    $product = wc_get_product($product_id);
    if (!$product) return null;

    // Get order type prices
    $stock_price = (float) get_post_meta($product_id, '_stock_order_price', true);
    $daily_price = (float) get_post_meta($product_id, '_daily_order_price', true);
    $vor_price = (float) get_post_meta($product_id, '_vor_order_price', true);

    // Fallback to regular price if not set
    $default_price = (float) $product->get_price();
    if ($stock_price <= 0) $stock_price = $default_price;
    if ($daily_price <= 0) $daily_price = $default_price;
    if ($vor_price <= 0) $vor_price = $default_price;

    return [
        'id' => $product_id,
        'sku' => $product->get_sku() ?: '',
        'name' => get_the_title($product_id),
        'stock' => (int) $product->get_stock_quantity(),
        'prices' => [
            'stock_order' => $stock_price,
            'daily_order' => $daily_price,
            'vor_order' => $vor_price,
        ],
    ];
}

/**
 * AJAX handler for searching products with pagination
 */
add_action('wp_ajax_dealer_search_products', function() {
    check_ajax_referer('dealer_search_products', 'nonce');

    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
    $per_page = 50;

    $args = [
        'post_type' => 'product',
        'posts_per_page' => $per_page,
        'paged' => $page,
        'post_status' => 'publish',
        'orderby' => 'title',
        'order' => 'ASC',
    ];

    // If search term provided, search by SKU or title
    if (!empty($search)) {
        $args['meta_query'] = [
            'relation' => 'OR',
            [
                'key' => '_sku',
                'value' => $search,
                'compare' => 'LIKE'
            ]
        ];
        $args['s'] = $search;
        // Remove pagination for search to show all results
        $args['posts_per_page'] = 100;
        unset($args['paged']);
    }

    $query = new WP_Query($args);
    $products = [];

    while ($query->have_posts()) {
        $query->the_post();
        $formatted = dealer_format_product(get_the_ID());
        if ($formatted) {
            $products[] = $formatted;
        }
    }

    wp_reset_postdata();

    // Get total count for pagination (only when not searching)
    $total = empty($search) ? $query->found_posts : count($products);
    $total_pages = empty($search) ? ceil($total / $per_page) : 1;

    wp_send_json_success([
        'products' => $products,
        'total' => $total,
        'page' => $page,
        'total_pages' => $total_pages,
        'has_more' => empty($search) && $page < $total_pages
    ]);
});

/**
 * AJAX handler for adding product to cart with order type
 */
add_action('wp_ajax_dealer_add_to_cart', function() {
    check_ajax_referer('dealer_add_to_cart', 'nonce');

    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);
    $order_type = sanitize_text_field($_POST['order_type']);

    if ($quantity <= 0) $quantity = 1;

    // Validate order type
    $valid_types = ['stock_order', 'daily_order', 'vor_order'];
    if (!in_array($order_type, $valid_types)) {
        $order_type = 'stock_order';
    }

    // Get the price for this order type
    $price_key = '_' . $order_type . '_price';
    $price = (float) get_post_meta($product_id, $price_key, true);

    if ($price <= 0) {
        $product = wc_get_product($product_id);
        $price = $product ? (float) $product->get_price() : 0;
    }

    // Add to cart with custom data
    $cart_item_data = [
        'dealer_order_type' => $order_type,
        'dealer_custom_price' => $price,
    ];

    $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity, 0, [], $cart_item_data);

    if ($cart_item_key) {
        wp_send_json_success([
            'message' => 'Product added to cart',
            'cart_count' => WC()->cart->get_cart_contents_count(),
            'cart_item_key' => $cart_item_key
        ]);
    } else {
        wp_send_json_error(['message' => 'Could not add product to cart']);
    }
});

/**
 * Apply custom price from order type
 */
add_action('woocommerce_before_calculate_totals', function($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;

    foreach ($cart->get_cart() as $cart_item) {
        if (isset($cart_item['dealer_custom_price']) && $cart_item['dealer_custom_price'] > 0) {
            $cart_item['data']->set_price($cart_item['dealer_custom_price']);
        }
    }
}, 20);

/**
 * Display order type in cart
 */
add_filter('woocommerce_get_item_data', function($item_data, $cart_item) {
    if (isset($cart_item['dealer_order_type'])) {
        $type_labels = [
            'stock_order' => 'Stock Order',
            'daily_order' => 'Daily Order',
            'vor_order' => 'VOR Order',
        ];
        $item_data[] = [
            'key' => 'Order Type',
            'value' => $type_labels[$cart_item['dealer_order_type']] ?? $cart_item['dealer_order_type'],
        ];
    }
    return $item_data;
}, 10, 2);

/**
 * Save order type to order item meta
 */
add_action('woocommerce_checkout_create_order_line_item', function($item, $cart_item_key, $values) {
    if (isset($values['dealer_order_type'])) {
        $item->add_meta_data('_dealer_order_type', $values['dealer_order_type'], true);
    }
}, 10, 3);

/**
 * Get cart data for React
 */
function dealer_get_cart_data() {
    $items = [];
    $cart = null;

    if (function_exists('WC') && WC()->cart) {
        $cart = WC()->cart;
    }

    $type_labels = [
        'stock_order' => 'Stock Order',
        'daily_order' => 'Daily Order',
        'vor_order' => 'VOR Order',
    ];

    if ($cart) {
        foreach ($cart->get_cart() as $cart_key => $cart_item) {
            $product = $cart_item['data'];
            $order_type = $cart_item['dealer_order_type'] ?? 'stock_order';
            $custom_price = $cart_item['dealer_custom_price'] ?? (float) $product->get_price();

            $items[] = [
                'key' => $cart_key,
                'id' => $cart_item['product_id'],
                'name' => $product->get_name(),
                'sku' => $product->get_sku() ?: '',
                'price' => (float) $custom_price,
                'quantity' => $cart_item['quantity'],
                'subtotal' => (float) $custom_price * $cart_item['quantity'],
                'orderType' => $order_type,
                'orderTypeLabel' => $type_labels[$order_type] ?? 'Stock Order',
            ];
        }
    }

    return [
        'items' => $items,
        'total' => $cart ? (float) $cart->get_total('edit') : 0,
        'checkoutUrl' => wc_get_checkout_url(),
        'updateCartUrl' => wc_get_cart_url(),
        'nonce' => wp_create_nonce('wc_store_api'),
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'cartActionNonce' => wp_create_nonce('dealer_cart_action')
    ];
}

/**
 * Get orders data for React
 */
function dealer_get_orders_data() {
    $orders = [];
    $user_id = get_current_user_id();

    if ($user_id) {
        $customer_orders = wc_get_orders([
            'status' => ['pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed'],
            'customer_id' => $user_id,
            'limit' => 20,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        foreach ($customer_orders as $order) {
            $items = [];
            foreach ($order->get_items() as $item) {
                $items[] = [
                    'name' => $item->get_name(),
                    'quantity' => $item->get_quantity(),
                    'total' => (float) $item->get_total(),
                ];
            }

            $orders[] = [
                'id' => $order->get_id(),
                'number' => $order->get_order_number(),
                'date' => $order->get_date_created()->date_i18n('M j, Y'),
                'status' => ucfirst($order->get_status()),
                'total' => (float) $order->get_total(),
                'items' => $items,
            ];
        }
    }

    return [
        'orders' => $orders
    ];
}

/**
 * Get checkout data for React
 */
function dealer_get_checkout_data() {
    $items = [];
    $cart = null;

    if (function_exists('WC') && WC()->cart) {
        $cart = WC()->cart;
    }

    $type_labels = [
        'stock_order' => 'Stock Order',
        'daily_order' => 'Daily Order',
        'vor_order' => 'VOR Order',
    ];

    if ($cart) {
        foreach ($cart->get_cart() as $cart_key => $cart_item) {
            $product = $cart_item['data'];
            $order_type = $cart_item['dealer_order_type'] ?? 'stock_order';
            $custom_price = $cart_item['dealer_custom_price'] ?? (float) $product->get_price();

            $items[] = [
                'key' => $cart_key,
                'id' => $cart_item['product_id'],
                'name' => $product->get_name(),
                'sku' => $product->get_sku() ?: '',
                'price' => (float) $custom_price,
                'quantity' => $cart_item['quantity'],
                'subtotal' => (float) $custom_price * $cart_item['quantity'],
                'orderType' => $order_type,
                'orderTypeLabel' => $type_labels[$order_type] ?? 'Stock Order',
            ];
        }
    }

    return [
        'items' => $items,
        'total' => $cart ? (float) $cart->get_total('edit') : 0,
        'cartUrl' => wc_get_cart_url(),
        'nonce' => wp_create_nonce('wc_store_api'),
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'cartActionNonce' => wp_create_nonce('dealer_cart_action'),
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'placeOrderNonce' => wp_create_nonce('dealer_place_order')
    ];
}

/**
 * AJAX handler for placing order
 */
add_action('wp_ajax_dealer_place_order', function() {
    check_ajax_referer('dealer_place_order', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Not logged in']);
        return;
    }

    $order_notes = sanitize_textarea_field($_POST['order_notes'] ?? '');

    try {
        // Create order from cart
        $checkout = WC()->checkout();

        // Get customer data
        $user = wp_get_current_user();

        $order_data = [
            'status' => 'pending',
            'customer_id' => get_current_user_id(),
        ];

        $order = wc_create_order($order_data);

        if (is_wp_error($order)) {
            wp_send_json_error(['message' => $order->get_error_message()]);
            return;
        }

        // Add items from cart
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            $quantity = $cart_item['quantity'];
            $custom_price = $cart_item['dealer_custom_price'] ?? $product->get_price();

            $item_id = $order->add_product($product, $quantity, [
                'subtotal' => $custom_price * $quantity,
                'total' => $custom_price * $quantity,
            ]);

            // Add order type meta
            if (isset($cart_item['dealer_order_type'])) {
                wc_add_order_item_meta($item_id, '_dealer_order_type', $cart_item['dealer_order_type']);
            }
        }

        // Set customer billing info
        $order->set_billing_first_name($user->first_name ?: $user->display_name);
        $order->set_billing_last_name($user->last_name ?: '');
        $order->set_billing_email($user->user_email);

        // Add order notes
        if (!empty($order_notes)) {
            $order->add_order_note($order_notes, true);
        }

        // Calculate totals
        $order->calculate_totals();

        // Set payment method
        $order->set_payment_method('');
        $order->set_payment_method_title('Dealer Account');

        // Save order
        $order->save();

        // Empty cart
        WC()->cart->empty_cart();

        wp_send_json_success([
            'message' => 'Order placed successfully',
            'order_id' => $order->get_id(),
            'redirect' => wc_get_account_endpoint_url('orders')
        ]);

    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
});

/**
 * Login page shortcode - renders React login
 */
add_shortcode('dealer_login', function () {
    if (is_user_logged_in()) {
        wp_redirect(home_url('/'));
        exit;
    }

    return '<div id="dealer-login-root"></div>';
});

/**
 * Inventory shortcode - renders React inventory
 */
add_shortcode('dealer_inventory', function () {
    if (!is_user_logged_in()) {
        return '<p>Please login to view inventory.</p>';
    }

    return '<div id="dealer-inventory-root"></div>';
});

/**
 * Cart shortcode - renders React cart
 */
add_shortcode('dealer_cart', function () {
    if (!is_user_logged_in()) {
        return '<p>Please login to view cart.</p>';
    }

    return '<div id="dealer-cart-root"></div>';
});

/**
 * Orders shortcode - renders React orders
 */
add_shortcode('dealer_orders', function () {
    if (!is_user_logged_in()) {
        return '<p>Please login to view orders.</p>';
    }

    return '<div id="dealer-orders-root"></div>';
});

/**
 * Checkout shortcode - renders React checkout
 */
add_shortcode('dealer_checkout', function () {
    if (!is_user_logged_in()) {
        return '<p>Please login to checkout.</p>';
    }

    return '<div id="dealer-checkout-root"></div>';
});

/**
 * Hide theme elements and set white background
 */
// Disable caching for dealer pages
add_action("send_headers", function() {
    if (is_page("login") || is_page("inventory") || is_page("cart") || is_checkout() || is_wc_endpoint_url("orders")) {
        header("Cache-Control: no-cache, no-store, must-revalidate");
        header("Pragma: no-cache");
        header("Expires: 0");
    }
});

add_action('wp_head', function () {
    if (is_admin()) {
        return;
    }
    ?>
    <link rel="icon" type="image/x-icon" href="<?php echo DEALER_SYSTEM_URL; ?>dist/ZEEKR_black.ico">
    <link rel="shortcut icon" href="<?php echo DEALER_SYSTEM_URL; ?>dist/ZEEKR_black.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Figtree font */
        html, body, * {
            font-family: 'Figtree', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif !important;
        }

        /* Light theme base */
        html, body {
            background-color: #fff !important;
            color: #111827 !important;
            overflow-x: hidden !important;
            max-width: 100vw !important;
        }

        /* Hide ALL theme elements */
        .site-header,
        #site-header,
        header.site-header,
        .main-navigation,
        #site-navigation,
        .site-footer,
        #site-footer,
        footer.site-footer,
        .footer-widgets,
        .site-info,
        .top-bar,
        .sidebar,
        #secondary,
        .widget-area,
        aside.sidebar,
        .is-right-sidebar,
        .is-left-sidebar,
        .entry-title,
        .entry-meta,
        .entry-header,
        .site-branding,
        .navigation-branding,
        .menu-toggle,
        #mobile-header,
        .woocommerce-breadcrumb,
        .page-header {
            display: none !important;
        }

        /* Full width content - reset all containers */
        .site-content,
        .site-content .content-area,
        .has-sidebar .site-content .content-area,
        #primary,
        .site-main,
        .container,
        .grid-container,
        .inside-article,
        #content,
        .content-area,
        article,
        .entry-content,
        .woocommerce,
        .woocommerce-page {
            width: 100% !important;
            max-width: 100vw !important;
            float: none !important;
            padding: 0 !important;
            margin: 0 !important;
            background: transparent !important;
            box-sizing: border-box !important;
            overflow-x: hidden !important;
        }

        /* Fix WooCommerce account page layout */
        .woocommerce-account .woocommerce-MyAccount-content {
            width: 100% !important;
            max-width: 100vw !important;
            float: none !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        /* Hide WooCommerce elements on login */
        .woocommerce-MyAccount-navigation,
        .woocommerce-form-login .lost_password,
        .u-column2,
        .woocommerce-form-register {
            display: none !important;
        }

        /* Hide product images in order details */
        .woocommerce-table--order-details .product-thumbnail,
        .woocommerce-table--order-details td.product-thumbnail,
        .woocommerce-table--order-details th.product-thumbnail,
        .woocommerce-order-details .product-thumbnail,
        .order_details .product-thumbnail,
        .shop_table .product-thumbnail,
        .woocommerce-cart-form .product-thumbnail,
        .woocommerce img.attachment-woocommerce_thumbnail,
        .woocommerce-order img.wp-post-image {
            display: none !important;
        }

        /* React root containers - centered flexbox */
        #dealer-login-root {
            min-height: 100vh;
            width: 100% !important;
            max-width: 100vw !important;
            display: flex;
            flex-wrap: nowrap;
            flex-direction: column;
            align-items: center;
            box-sizing: border-box;
            overflow-x: hidden !important;
        }

        #dealer-inventory-root {
            min-height: 100vh;
            width: 80vw !important;
            max-width: 80vw !important;
            margin: 0 auto !important;
            padding-top: 80px !important;
            display: flex;
            flex-wrap: nowrap;
            flex-direction: column;
            align-items: center;
            box-sizing: border-box;
            overflow-x: hidden !important;
        }

        #dealer-cart-root {
            min-height: 100vh;
            width: 80vw !important;
            max-width: 80vw !important;
            margin: 0 auto !important;
            padding-top: 120px !important;
            display: flex;
            flex-wrap: nowrap;
            flex-direction: column;
            align-items: center;
            box-sizing: border-box;
            overflow-x: hidden !important;
        }

        /* Hide WooCommerce checkout elements when React checkout is active (not on order-pay page) */
        body:not(.woocommerce-order-pay) .woocommerce-checkout .woocommerce-form-coupon-toggle,
        body:not(.woocommerce-order-pay) .woocommerce-checkout .woocommerce-form-coupon,
        body:not(.woocommerce-order-pay) .woocommerce-checkout #customer_details,
        body:not(.woocommerce-order-pay) .woocommerce-checkout #order_review,
        body:not(.woocommerce-order-pay) .woocommerce-checkout #order_review_heading,
        body:not(.woocommerce-order-pay) .woocommerce-checkout .woocommerce-checkout-review-order,
        body:not(.woocommerce-order-pay) .woocommerce-checkout .woocommerce-NoticeGroup,
        body:not(.woocommerce-order-pay) .woocommerce-checkout .checkout.woocommerce-checkout {
            display: none !important;
        }

        /* Order Pay page styles */
        body.woocommerce-order-pay .woocommerce {
            width: 100% !important;
            max-width: 80vw !important;
            margin: 0 auto !important;
            padding: 100px 16px 80px 16px !important;
            box-sizing: border-box !important;
        }

        body.woocommerce-order-pay .woocommerce h2 {
            text-align: center !important;
            font-size: 2rem !important;
            font-weight: 700 !important;
            margin-bottom: 32px !important;
            background: linear-gradient(135deg, #111827, #6b7280, #9ca3af, #374151, #6b7280, #111827) !important;
            background-size: 200% 200% !important;
            -webkit-background-clip: text !important;
            -webkit-text-fill-color: transparent !important;
            background-clip: text !important;
            animation: gradientShift 4s ease-in-out infinite !important;
        }

        body.woocommerce-order-pay .woocommerce table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }

        body.woocommerce-order-pay .woocommerce table th,
        body.woocommerce-order-pay .woocommerce table td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        body.woocommerce-order-pay .woocommerce table thead {
            background-color: #f9fafb;
        }

        body.woocommerce-order-pay .woocommerce #payment {
            background: #f9fafb;
            border-radius: 12px;
            padding: 24px;
            margin-top: 24px;
        }

        body.woocommerce-order-pay .woocommerce #payment .payment_methods {
            list-style: none;
            padding: 0;
            margin: 0 0 16px 0;
        }

        body.woocommerce-order-pay .woocommerce #payment .payment_methods li {
            padding: 12px 0;
        }

        body.woocommerce-order-pay .woocommerce #payment .payment_methods label {
            font-weight: 500;
            cursor: pointer;
        }

        body.woocommerce-order-pay .woocommerce #payment #place_order {
            width: 100%;
            background: #111827;
            color: white;
            border: none;
            padding: 16px 32px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 12px;
            cursor: pointer;
            transition: background 0.2s;
        }

        body.woocommerce-order-pay .woocommerce #payment #place_order:hover {
            background: #374151;
        }

        /* Dealer page title */
        .dealer-view-order-header {
            text-align: center !important;
            margin-bottom: 32px !important;
            padding-top: 0 !important;
        }

        .dealer-page-title {
            font-size: 2rem !important;
            font-weight: 700 !important;
            color: #111827 !important;
            margin: 0 !important;
            background: linear-gradient(135deg, #111827, #6b7280, #9ca3af, #374151, #6b7280, #111827) !important;
            background-size: 200% 200% !important;
            -webkit-background-clip: text !important;
            -webkit-text-fill-color: transparent !important;
            background-clip: text !important;
            animation: gradientShift 4s ease-in-out infinite !important;
        }

        /* View Order page styles */
        body.woocommerce-view-order .woocommerce {
            width: 100% !important;
            max-width: 80vw !important;
            margin: 0 auto !important;
            padding: 100px 16px 80px 16px !important;
            box-sizing: border-box !important;
        }

        body.woocommerce-view-order .woocommerce > p:first-child {
            text-align: center !important;
            font-size: 1.1rem !important;
            color: #6b7280 !important;
            margin-bottom: 32px !important;
        }

        body.woocommerce-view-order .woocommerce h2 {
            font-size: 1.5rem !important;
            font-weight: 600 !important;
            margin: 32px 0 16px 0 !important;
        }

        body.woocommerce-view-order .woocommerce table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }

        body.woocommerce-view-order .woocommerce table th,
        body.woocommerce-view-order .woocommerce table td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        body.woocommerce-view-order .woocommerce table thead {
            background-color: #f9fafb;
        }

        body.woocommerce-view-order .woocommerce .woocommerce-order-details {
            margin-bottom: 32px;
        }

        body.woocommerce-view-order .woocommerce .woocommerce-customer-details {
            background: #f9fafb;
            border-radius: 12px;
            padding: 24px;
        }

        body.woocommerce-view-order .woocommerce .woocommerce-customer-details address {
            font-style: normal;
            line-height: 1.8;
        }

        body.woocommerce-view-order .woocommerce .woocommerce-button {
            display: inline-block;
            padding: 12px 24px;
            font-size: 14px;
            font-weight: 500;
            border-radius: 8px;
            text-decoration: none;
            margin-right: 8px;
            margin-top: 16px;
            transition: all 0.2s;
        }

        body.woocommerce-view-order .woocommerce .woocommerce-button.pay {
            background-color: #111827;
            color: white !important;
        }

        body.woocommerce-view-order .woocommerce .woocommerce-button.cancel {
            background-color: #fef2f2;
            color: #dc2626 !important;
        }

        /* Order Received (Thank You) page styles */
        body.woocommerce-order-received .woocommerce {
            width: 100% !important;
            max-width: 80vw !important;
            margin: 0 auto !important;
            padding: 100px 16px 80px 16px !important;
            box-sizing: border-box !important;
        }

        body.woocommerce-order-received .woocommerce .woocommerce-order {
            text-align: center !important;
        }

        body.woocommerce-order-received .woocommerce .woocommerce-thankyou-order-received {
            font-size: 2rem !important;
            font-weight: 700 !important;
            margin-bottom: 32px !important;
            background: linear-gradient(135deg, #111827, #6b7280, #9ca3af, #374151, #6b7280, #111827) !important;
            background-size: 200% 200% !important;
            -webkit-background-clip: text !important;
            -webkit-text-fill-color: transparent !important;
            background-clip: text !important;
            animation: gradientShift 4s ease-in-out infinite !important;
        }

        body.woocommerce-order-received .woocommerce .woocommerce-order-overview {
            list-style: none;
            padding: 24px;
            margin: 0 0 32px 0;
            background: #f9fafb;
            border-radius: 12px;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 32px;
        }

        body.woocommerce-order-received .woocommerce .woocommerce-order-overview li {
            text-align: center;
        }

        body.woocommerce-order-received .woocommerce .woocommerce-order-overview li strong {
            display: block;
            font-size: 1.25rem;
            color: #111827;
        }

        body.woocommerce-order-received .woocommerce h2 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 32px 0 16px 0;
            text-align: left;
        }

        body.woocommerce-order-received .woocommerce table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }

        body.woocommerce-order-received .woocommerce table th,
        body.woocommerce-order-received .woocommerce table td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        body.woocommerce-order-received .woocommerce table thead {
            background-color: #f9fafb;
        }

        #dealer-checkout-root {
            min-height: 100vh;
            width: 80vw !important;
            max-width: 80vw !important;
            margin: 0 auto !important;
            padding-top: 120px !important;
            display: flex;
            flex-wrap: nowrap;
            flex-direction: column;
            align-items: center;
            box-sizing: border-box;
            overflow-x: hidden !important;
        }

        /* Ensure children of root containers are constrained */
        #dealer-login-root > div {
            width: 100% !important;
            max-width: 100vw !important;
            box-sizing: border-box !important;
        }

        #dealer-inventory-root > div,
        #dealer-cart-root > div,

        #dealer-checkout-root > div {
            width: 100% !important;
            box-sizing: border-box !important;
        }
        /* Cancel button confirmation */
        .woocommerce-button.cancel {
            cursor: pointer;
        }
    </style>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        var cancelLinks = document.querySelectorAll("a.cancel, a.woocommerce-button.cancel");
        cancelLinks.forEach(function(link) {
            link.addEventListener("click", function(e) {
                if (!confirm("Are you sure you want to cancel this order?")) {
                    e.preventDefault();
                    return false;
                }
            });
        });
    });
    </script>
    <?php
});

/**
 * Dealer header bar for logged-in users
 */
add_action('wp_body_open', function () {
    if (!is_user_logged_in() || is_admin() || is_page('login')) {
        return;
    }

    $user = wp_get_current_user();
    $cart_count = 0;
    if (function_exists('WC') && WC()->cart) {
        $cart_count = WC()->cart->get_cart_contents_count();
    }
    ?>
    <style>
        .dealer-header-bar {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.8);
            color: #111827;
            padding: 10px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            width: max-content;
            gap: 24px;
            
            white-space: nowrap;
            border-radius: 9999px;
            z-index: 9999;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12), 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        .dealer-header-bar a {
            color: #374151;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.2s;
            font-size: 14px;
        }
        .dealer-header-bar a:hover {
            background: rgba(0, 0, 0, 0.05);
            color: #111827;
        }
        .dealer-header-bar a.active {
            background: rgba(0, 0, 0, 0.08);
            color: #111827;
            font-weight: 500;
        }
        .dealer-nav {
            display: flex;
            flex-wrap: nowrap;
            gap: 4px;
        }
        .dealer-logo {
            flex-shrink: 0;
        }
        .dealer-logo a {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            padding: 0;
        }
        .dealer-logo a:hover {
            background: transparent;
        }
        .dealer-logo img {
            height: 28px;
            width: max-content;
            gap: 24px;
        }
        .dealer-credit {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            padding: 8px 16px;
            font-size: 14px;
            font-weight: 600;
            color: #059669;
            background: rgba(5, 150, 105, 0.1);
            border-radius: 8px;
        }
        .dealer-logout {
            color: #dc2626 !important;
        }
        .dealer-logout:hover {
            background: rgba(220, 38, 38, 0.1) !important;
            color: #b91c1c !important;
        }
        /* Hamburger menu button */
        .dealer-menu-toggle {
            display: none;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            width: 40px;
            height: 40px;
            cursor: pointer;
            background: transparent;
            border: none;
            padding: 8px;
            gap: 5px;
        }
        .dealer-menu-toggle span {
            display: block;
            width: 20px;
            height: 2px;
            background: #374151;
            border-radius: 2px;
            transition: all 0.3s ease;
        }
        .dealer-menu-toggle.active span:nth-child(1) {
            transform: rotate(45deg) translate(5px, 5px);
        }
        .dealer-menu-toggle.active span:nth-child(2) {
            opacity: 0;
        }
        .dealer-menu-toggle.active span:nth-child(3) {
            transform: rotate(-45deg) translate(5px, -5px);
        }
        /* Mobile overlay */
        .dealer-nav-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9998;
        }
        .dealer-nav-overlay.active {
            display: block;
        }
        /* Mobile styles */
        @media (max-width: 768px) {
            .dealer-header-bar {
                width: calc(100% - 32px);
                min-width: unset;
                max-width: unset;
                padding: 10px 16px;
                top: 16px;
            }
            .dealer-menu-toggle {
                display: flex;
            flex-wrap: nowrap;
            }
            .dealer-nav {
                position: fixed;
                top: 0;
                right: -300px;
                width: 280px;
                height: 100vh;
                background: white;
                flex-direction: column;
                padding: 80px 24px 24px;
                gap: 8px;
                box-shadow: -4px 0 20px rgba(0, 0, 0, 0.1);
                transition: right 0.3s ease;
                z-index: 10000;
                visibility: hidden;
            }
            .dealer-nav.active {
                right: 0;
                visibility: visible;
            }
            .dealer-nav a {
                padding: 14px 16px;
                font-size: 16px;
                border-radius: 12px;
            .dealer-credit {
                padding: 14px 16px;
                font-size: 16px;
                border-radius: 12px;
                justify-content: center;
            }
            }
            .dealer-nav-close {
                position: absolute;
                top: 20px;
                right: 20px;
                width: 40px;
                height: 40px;
                display: flex;
            flex-wrap: nowrap;
                align-items: center;
                justify-content: center;
                background: #f3f4f6;
                border: none;
                border-radius: 50%;
                cursor: pointer;
                font-size: 20px;
                color: #374151;
            }
            /* Mobile container widths */
            #dealer-inventory-root,
            #dealer-cart-root,
            #dealer-orders-root,
            #dealer-checkout-root {
                width: calc(100% - 24px) !important;
                max-width: 100% !important;
                padding-top: 80px !important;
                padding-left: 12px !important;
                padding-right: 12px !important;
            }
        }
        @media (min-width: 769px) {
            .dealer-nav-close {
                display: none;
            }
        }
        html, body {
            padding-top: 0 !important;
            overflow-x: hidden;
        }
        /* WordPress admin bar adjustment */
        .admin-bar .dealer-header-bar {
            top: 52px; /* 20px + 32px admin bar */
        }
        @media (max-width: 782px) {
            .admin-bar .dealer-header-bar {
                top: 62px; /* 16px + 46px mobile admin bar */
            }
        }
    </style>
    <div class="dealer-nav-overlay" onclick="closeDealerMenu()"></div>
    <div class="dealer-header-bar">
        <div class="dealer-logo">
            <a href="<?php echo home_url('/'); ?>">
                <img src="<?php echo DEALER_SYSTEM_URL; ?>dist/ZEEKR_black.png" alt="ZEEKR" height="28">
            </a>
        </div>
        <button class="dealer-menu-toggle" onclick="toggleDealerMenu()">
            <span></span>
            <span></span>
            <span></span>
        </button>
        <nav class="dealer-nav">
            <button class="dealer-nav-close" onclick="closeDealerMenu()">&times;</button>
            <a href="<?php echo home_url('/inventory/'); ?>" <?php echo is_page('inventory') ? 'class="active"' : ''; ?>>Inventory</a>
            <a href="<?php echo wc_get_cart_url(); ?>">Cart</a>
            <a href="<?php echo wc_get_account_endpoint_url('orders'); ?>">My Orders</a>
            <span class="dealer-credit">Balance: $<?php echo number_format(dealer_get_funds_balance(), 2); ?></span>
            <a href="<?php echo esc_url(dealer_logout_url()); ?>" class="dealer-logout">Logout</a>
        </nav>
    </div>
    <script>
    function toggleDealerMenu() {
        document.querySelector('.dealer-nav').classList.toggle('active');
        document.querySelector('.dealer-menu-toggle').classList.toggle('active');
        document.querySelector('.dealer-nav-overlay').classList.toggle('active');
        document.body.style.overflow = document.querySelector('.dealer-nav').classList.contains('active') ? 'hidden' : '';
    }
    function closeDealerMenu() {
        document.querySelector('.dealer-nav').classList.remove('active');
        document.querySelector('.dealer-menu-toggle').classList.remove('active');
        document.querySelector('.dealer-nav-overlay').classList.remove('active');
        document.body.style.overflow = '';
    }
    </script>
    <?php
});

/**
 * Remove unnecessary WooCommerce scripts and styles on dealer pages
 */
add_action('wp_enqueue_scripts', function () {
    if (is_page('login') || is_front_page()) {
        // Keep only essential WooCommerce functionality
        wp_dequeue_style('woocommerce-general');
        wp_dequeue_style('woocommerce-layout');
        wp_dequeue_style('woocommerce-smallscreen');
    }
}, 100);

/**
 * Replace WooCommerce cart with React cart for dealers
 */
add_filter('woocommerce_locate_template', function ($template, $template_name) {
    if (!is_user_logged_in()) {
        return $template;
    }

    $user = wp_get_current_user();
    if (!in_array('dealer', (array) $user->roles)) {
        return $template;
    }

    // Replace cart template
    if ($template_name === 'cart/cart.php' || $template_name === 'cart/cart-empty.php') {
        return DEALER_SYSTEM_PATH . 'templates/cart.php';
    }

    // Replace checkout template
    if ($template_name === 'checkout/form-checkout.php') {
        return DEALER_SYSTEM_PATH . 'templates/checkout.php';
    }

    return $template;
}, 10, 2);

/**
 * Replace WooCommerce orders with React orders for dealers
 */
// Add title to view-order page
add_action('woocommerce_account_view-order_endpoint', function ($order_id) {
    $user = wp_get_current_user();
    if (in_array('dealer', (array) $user->roles)) {
        echo '<div class="dealer-view-order-header">';
        echo '<h1 class="dealer-page-title">Order #' . esc_html($order_id) . '</h1>';
        echo '</div>';
    }
}, 1);

// Add dealer orders page wrapper with header and table container
add_action('woocommerce_account_orders_endpoint', function () {
    $user = wp_get_current_user();
    $roles = (array) $user->roles;
    // Apply for dealer and admin users
    if (in_array('dealer', $roles) || in_array('administrator', $roles)) {
        echo '<div class="dealer-orders-page">';
        echo '<div id="dealer-orders-header"></div>';
        echo '<div class="dealer-orders-table-wrapper">';
    }
}, 1);

// Close the dealer orders containers after WooCommerce content
add_action('woocommerce_account_orders_endpoint', function () {
    $user = wp_get_current_user();
    $roles = (array) $user->roles;
    // Apply for dealer and admin users
    if (in_array('dealer', $roles) || in_array('administrator', $roles)) {
        echo '</div>'; // close table-wrapper
        echo '</div>'; // close dealer-orders-page
    }
}, 99);

/**
 * Homepage landing shortcode
 */
add_shortcode('dealer_home', function () {
    ob_start();
    ?>
    <style>
        .dealer-home-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            overflow: hidden;
            z-index: 1;
        }
        .dealer-home-video {
            position: absolute;
            top: 50%;
            left: 50%;
            min-width: 100%;
            min-height: 100%;
            width: max-content;
            gap: 24px;
            height: auto;
            transform: translate(-50%, -50%);
            object-fit: cover;
        }
        .dealer-home-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            z-index: 2;
        }
        .dealer-home-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            z-index: 3;
            color: white;
        }
        .dealer-home-title {
            font-size: 4.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            letter-spacing: -1px;
        }
        .dealer-home-description {
            font-size: 1.5rem;
            font-weight: 300;
            opacity: 0.9;
        }
        .dealer-home-btn {
            display: inline-block;
            margin-top: 2.5rem;
            padding: 1rem 3rem;
            font-size: 1.25rem;
            font-weight: 500;
            color: white;
            background-color: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 9999px;
            text-decoration: none;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }
        .dealer-home-btn:hover {
            background-color: rgba(255,255,255,0.25);
            transform: scale(1.05);
        }
        @media (max-width: 768px) {
            .dealer-home-content {
                width: 100%;
                padding: 0 16px;
                box-sizing: border-box;
            }
            .dealer-home-title {
                font-size: 1.75rem;
                line-height: 1.3;
            }
            .dealer-home-description {
                font-size: 0.95rem;
            }
            .dealer-home-btn {
                padding: 0.75rem 2rem;
                font-size: 1rem;
            }
        }
    </style>
    <div class="dealer-home-container">
        <video 
            class="dealer-home-video" 
            autoplay 
            muted 
            loop 
            playsinline
            preload="none"
        >
            <source src="https://assets.zeekrlife.com/videos/1751009481.mp4" type="video/mp4">
        </video>
        <div class="dealer-home-overlay"></div>
        <div class="dealer-home-content">
            <h1 class="dealer-home-title">Dealer Ordering & Inventory Portal</h1>
            <p class="dealer-home-description">Manage inventory, place orders, and track fulfillment in one system.</p>
            <a href="/inventory/" class="dealer-home-btn">Order Now</a>
        </div>
    </div>
    <?php
    return ob_get_clean();
});

/**
 * Replace WooCommerce checkout with React checkout for dealers
 */
add_action('woocommerce_before_checkout_form', function() {
    // Don't interfere with order-pay page
    if (is_wc_endpoint_url('order-pay')) return;
    $user = wp_get_current_user();
    if (in_array('dealer', (array) $user->roles)) {
        echo '<div id="dealer-checkout-root"></div>';
        // Remove default checkout form
        remove_action('woocommerce_checkout_order_review', 'woocommerce_order_review', 10);
        remove_action('woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20);
    }
}, 1);

add_filter('woocommerce_checkout_show_terms', function($show) {
    $user = wp_get_current_user();
    if (in_array('dealer', (array) $user->roles)) {
        return false;
    }
    return $show;
});

/**
 * Get dealer's account funds balance
 */
function dealer_get_funds_balance() {
    if (!is_user_logged_in()) return 0;
    if (!class_exists("YITH_YWF_Customer")) return 0;
    
    $user_id = get_current_user_id();
    $customer = new YITH_YWF_Customer($user_id);
    return $customer->get_funds();
}

/**
 * Only allow Account Funds payment for dealers
 */
add_filter("woocommerce_available_payment_gateways", function($gateways) {
    if (!is_user_logged_in()) return $gateways;
    
    $user = wp_get_current_user();
    if (in_array("dealer", (array) $user->roles)) {
        foreach ($gateways as $key => $gateway) {
            if ($key !== "yith_funds") {
                unset($gateways[$key]);
            }
        }
    }
    return $gateways;
});

/**
 * Debug: Log ALL order status changes to find auto-cancel source
 */
add_action('woocommerce_order_status_changed', function($order_id, $old_status, $new_status) {
    if ($new_status === 'cancelled') {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);
        $trace_summary = [];
        foreach ($backtrace as $i => $frame) {
            $file = isset($frame['file']) ? basename($frame['file']) : 'unknown';
            $line = isset($frame['line']) ? $frame['line'] : '?';
            $func = isset($frame['function']) ? $frame['function'] : 'unknown';
            $trace_summary[] = "#{$i} {$file}:{$line} {$func}()";
        }

        error_log("=== ORDER CANCELLED DEBUG ===");
        error_log("Order ID: {$order_id}");
        error_log("Old Status: {$old_status}");
        error_log("New Status: {$new_status}");
        error_log("Time: " . date('Y-m-d H:i:s'));
        error_log("REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
        error_log("HTTP_REFERER: " . ($_SERVER['HTTP_REFERER'] ?? 'N/A'));
        error_log("User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'N/A'));
        error_log("Is CRON: " . (defined('DOING_CRON') && DOING_CRON ? 'YES' : 'NO'));
        error_log("Is AJAX: " . (defined('DOING_AJAX') && DOING_AJAX ? 'YES' : 'NO'));
        error_log("Is REST: " . (defined('REST_REQUEST') && REST_REQUEST ? 'YES' : 'NO'));
        error_log("Backtrace:\n" . implode("\n", $trace_summary));
        error_log("=== END DEBUG ===");
    }
}, 10, 3);

/**
 * Debug: Log WooCommerce scheduled cancel action
 */
add_action('woocommerce_cancel_unpaid_order', function($order) {
    $order_id = is_object($order) ? $order->get_id() : $order;
    error_log("=== WOOCOMMERCE AUTO-CANCEL TRIGGERED ===");
    error_log("Order ID: {$order_id}");
    error_log("Time: " . date('Y-m-d H:i:s'));
    error_log("This is WooCommerce's scheduled unpaid order cancellation!");
    error_log("=== END ===");
}, 1);

/**
 * Prevent WooCommerce from auto-cancelling dealer orders
 * WooCommerce has a setting to cancel unpaid orders after X minutes
 */
add_filter('woocommerce_cancel_unpaid_order', function($cancel, $order) {
    if (!$order) return $cancel;

    $customer_id = $order->get_customer_id();
    if ($customer_id) {
        $user = get_user_by('id', $customer_id);
        if ($user && in_array('dealer', (array) $user->roles)) {
            error_log("BLOCKED auto-cancel for dealer order #{$order->get_id()}");
            return false; // Don't cancel dealer orders automatically
        }
    }
    return $cancel;
}, 10, 2);

/**
 * Require confirmation before cancelling order for dealers
 * This prevents accidental cancellation from browser prefetch or misclicks
 */
add_action('wp_loaded', function() {
    if (isset($_GET['cancel_order']) && $_GET['cancel_order'] === 'true') {
        $user = wp_get_current_user();
        if (in_array('dealer', (array) $user->roles)) {
            if (!isset($_GET['confirmed'])) {
                $order_id = intval($_GET['order_id'] ?? 0);
                $confirm_url = esc_url(add_query_arg('confirmed', '1'));
                $back_url = esc_url(wc_get_account_endpoint_url('orders'));
                ?>
                <!DOCTYPE html>
                <html>
                <head><title>Confirm Cancel Order</title></head>
                <body style="text-align:center;padding:100px;font-family:system-ui,sans-serif;">
                    <h2 style="margin-bottom:20px;">Cancel Order #<?php echo $order_id; ?>?</h2>
                    <p style="color:#666;margin-bottom:30px;">Are you sure you want to cancel this order?</p>
                    <a href="<?php echo $confirm_url; ?>" style="background:#dc2626;color:white;padding:12px 24px;border-radius:8px;text-decoration:none;margin:10px;display:inline-block;">Yes, Cancel Order</a>
                    <a href="<?php echo $back_url; ?>" style="background:#e5e7eb;color:#374151;padding:12px 24px;border-radius:8px;text-decoration:none;margin:10px;display:inline-block;">No, Go Back</a>
                </body>
                </html>
                <?php
                exit;
            }
        }
    }
}, 5);

/**
 * Redirect to orders page after cancelling order (instead of my-account)
 */
add_filter('woocommerce_get_cancel_order_url_raw', function($url, $order) {
    // Change redirect to orders page for dealers
    $user = wp_get_current_user();
    if (in_array('dealer', (array) $user->roles)) {
        $url = add_query_arg(array(
            'cancel_order' => 'true',
            'order' => $order->get_order_key(),
            'order_id' => $order->get_id(),
            'redirect' => wc_get_account_endpoint_url('orders'),
            '_wpnonce' => wp_create_nonce('woocommerce-cancel_order')
        ), $order->get_cancel_endpoint());
    }
    return $url;
}, 10, 2);

/**
 * After order cancelled, redirect dealers to view-order page
 * DISABLED for debugging - orders were being auto-cancelled
 */
/*
add_action('woocommerce_cancelled_order', function($order_id) {
    $user = wp_get_current_user();
    if (in_array('dealer', (array) $user->roles)) {
        wp_safe_redirect(wc_get_account_endpoint_url('view-order') . $order_id . '/');
        exit;
    }
});
*/


/**
 * AJAX handler for removing item from cart
 */
add_action('wp_ajax_dealer_remove_from_cart', function() {
    check_ajax_referer('dealer_cart_action', 'nonce');
    
    $cart_item_key = sanitize_text_field($_POST['cart_item_key']);
    
    if (WC()->cart->remove_cart_item($cart_item_key)) {
        wp_send_json_success([
            'message' => 'Item removed',
            'cart_count' => WC()->cart->get_cart_contents_count()
        ]);
    } else {
        wp_send_json_error(['message' => 'Could not remove item']);
    }
});

/**
 * AJAX handler for updating cart item quantity
 */
add_action('wp_ajax_dealer_update_cart_item', function() {
    check_ajax_referer('dealer_cart_action', 'nonce');
    
    $cart_item_key = sanitize_text_field($_POST['cart_item_key']);
    $quantity = intval($_POST['quantity']);
    
    if ($quantity < 1) $quantity = 1;
    
    if (WC()->cart->set_quantity($cart_item_key, $quantity)) {
        WC()->cart->calculate_totals();
        wp_send_json_success([
            'message' => 'Cart updated',
            'cart_count' => WC()->cart->get_cart_contents_count()
        ]);
    } else {
        wp_send_json_error(['message' => 'Could not update cart']);
    }
});

/**
 * Style order status as colored badges in orders table
 */
add_filter('woocommerce_my_account_my_orders_columns', function($columns) {
    return $columns;
});

add_action('woocommerce_my_account_my_orders_column_order-status', function($order) {
    $status = $order->get_status();
    $status_name = wc_get_order_status_name($status);
    
    $colors = [
        'pending' => 'background:#fef3c7;color:#d97706;',
        'processing' => 'background:#dbeafe;color:#2563eb;',
        'on-hold' => 'background:#ffedd5;color:#ea580c;',
        'completed' => 'background:#dcfce7;color:#16a34a;',
        'cancelled' => 'background:#fee2e2;color:#dc2626;',
        'refunded' => 'background:#f3f4f6;color:#6b7280;',
        'failed' => 'background:#fee2e2;color:#dc2626;',
    ];
    
    $style = $colors[$status] ?? 'background:#f3f4f6;color:#6b7280;';
    
    echo '<span style="display:inline-block;padding:6px 12px;border-radius:9999px;font-size:12px;font-weight:600;' . $style . '">' . esc_html($status_name) . '</span>';
}, 10);
