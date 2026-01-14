<?php
/**
 * Plugin Name: Dealer System
 * Description: Force login and dealer management for stock system
 * Version: 1.0.0
 * Author: Vygox
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Disable WooCommerce Coming Soon mode completely
 */
add_filter('woocommerce_coming_soon_exclude', '__return_true');
add_filter('woocommerce_is_coming_soon_page', '__return_false');
add_action('init', function() {
    // Remove coming soon template
    remove_action('template_redirect', array('Automattic\WooCommerce\Admin\Features\LaunchYourStore', 'maybe_show_coming_soon_page'), 10);
}, 1);

/**
 * Custom logout handler - instant logout without confirmation
 */
add_action('init', function () {
    // Check for custom logout action
    if (isset($_GET['dealer_logout']) && $_GET['dealer_logout'] === '1') {
        // Verify nonce for security
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
    // Skip for admin, login page, and AJAX requests
    if (is_admin() || is_page('login') || wp_doing_ajax()) {
        return;
    }

    // Skip for WooCommerce AJAX and REST API
    if (defined('DOING_AJAX') || defined('REST_REQUEST')) {
        return;
    }

    // Skip for WooCommerce my-account endpoints (needed for login form processing)
    if (is_page('my-account') || strpos($_SERVER['REQUEST_URI'], 'my-account') !== false) {
        return;
    }

    // Redirect if not logged in
    if (!is_user_logged_in()) {
        wp_redirect(home_url('/login/'));
        exit;
    }
});

/**
 * Redirect after login based on user role
 */
add_filter('woocommerce_login_redirect', function ($redirect, $user) {
    // Dealers go to dashboard (home page)
    if (in_array('dealer', (array) $user->roles)) {
        return home_url('/');
    }
    // Admins go to wp-admin
    if (in_array('administrator', (array) $user->roles)) {
        return admin_url();
    }
    return $redirect;
}, 10, 2);

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
 * Inventory Table Shortcode
 * Usage: [dealer_inventory]
 */
add_shortcode('dealer_inventory', function ($atts) {
    if (!is_user_logged_in()) {
        return '<p>Please login to view inventory.</p>';
    }

    $atts = shortcode_atts([
        'limit' => 100,
    ], $atts);

    $args = [
        'post_type' => 'product',
        'posts_per_page' => $atts['limit'],
        'post_status' => 'publish',
    ];

    $products = new WP_Query($args);

    if (!$products->have_posts()) {
        return '<p>No products found.</p>';
    }

    ob_start();
    ?>
    <style>
        .dealer-inventory-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .dealer-inventory-table th,
        .dealer-inventory-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .dealer-inventory-table th {
            background: #f5f5f5;
            font-weight: 600;
        }
        .dealer-inventory-table tr:hover {
            background: #f9f9f9;
        }
        .stock-status.in-stock {
            color: #28a745;
        }
        .stock-status.out-of-stock {
            color: #dc3545;
        }
        .stock-status.low-stock {
            color: #ffc107;
        }
        .dealer-search-box {
            margin-bottom: 20px;
        }
        .dealer-search-box input {
            padding: 10px 15px;
            width: 100%;
            max-width: 400px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .add-to-cart-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .add-to-cart-form input[type="number"] {
            width: 70px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .add-to-cart-form button {
            padding: 8px 16px;
            background: #0073aa;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .add-to-cart-form button:hover {
            background: #005a87;
        }
    </style>

    <div class="dealer-search-box">
        <input type="text" id="dealer-search" placeholder="Search by SKU or product name..." onkeyup="filterInventory()">
    </div>

    <table class="dealer-inventory-table" id="inventory-table">
        <thead>
            <tr>
                <th>SKU</th>
                <th>Product</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Status</th>
                <th>Order</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($products->have_posts()) : $products->the_post();
                $product = wc_get_product(get_the_ID());
                $stock = $product->get_stock_quantity();
                $sku = $product->get_sku();
                $price = $product->get_price();

                // Determine stock status
                $status_class = 'in-stock';
                $status_text = 'In Stock';
                if ($stock <= 0) {
                    $status_class = 'out-of-stock';
                    $status_text = 'Out of Stock';
                } elseif ($stock <= 10) {
                    $status_class = 'low-stock';
                    $status_text = 'Low Stock';
                }
            ?>
            <tr data-sku="<?php echo esc_attr($sku); ?>" data-name="<?php echo esc_attr(get_the_title()); ?>">
                <td><?php echo esc_html($sku ?: '-'); ?></td>
                <td><?php the_title(); ?></td>
                <td>$<?php echo number_format($price, 2); ?></td>
                <td><?php echo $stock !== null ? $stock : 'N/A'; ?></td>
                <td><span class="stock-status <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                <td>
                    <?php if ($stock > 0) : ?>
                    <form class="add-to-cart-form" action="<?php echo esc_url(wc_get_cart_url()); ?>" method="post">
                        <input type="number" name="quantity" value="1" min="1" max="<?php echo $stock; ?>">
                        <input type="hidden" name="add-to-cart" value="<?php echo get_the_ID(); ?>">
                        <button type="submit">Add</button>
                    </form>
                    <?php else : ?>
                    <span style="color: #999;">N/A</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; wp_reset_postdata(); ?>
        </tbody>
    </table>

    <script>
    function filterInventory() {
        const search = document.getElementById('dealer-search').value.toLowerCase();
        const rows = document.querySelectorAll('#inventory-table tbody tr');

        rows.forEach(row => {
            const sku = row.getAttribute('data-sku').toLowerCase();
            const name = row.getAttribute('data-name').toLowerCase();

            if (sku.includes(search) || name.includes(search)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
    </script>
    <?php
    return ob_get_clean();
});

/**
 * Add logout link to header
 */
add_action('wp_head', function () {
    if (!is_user_logged_in() || is_admin()) {
        return;
    }
    ?>
    <style>
        /* Hide theme header and footer for dealers */
        .site-header,
        #site-header,
        header.site-header,
        .main-navigation,
        #site-navigation,
        .site-footer,
        #site-footer,
        footer.site-footer,
        .footer-widgets,
        .site-info {
            display: none !important;
        }

        /* Dealer header bar */
        .dealer-header-bar {
            background: #1a1a2e;
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 9999;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .dealer-header-bar a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 4px;
            transition: background 0.2s;
        }
        .dealer-header-bar a:hover {
            background: rgba(255,255,255,0.1);
        }
        .dealer-nav {
            display: flex;
            gap: 5px;
        }
        .dealer-welcome {
            font-weight: 500;
        }

        /* Main content area */
        .site-content,
        #content,
        .content-area {
            padding-top: 0 !important;
            margin-top: 0 !important;
        }

        /* Page title */
        .entry-title {
            display: none;
        }

        /* Hide sidebar */
        .sidebar,
        #secondary,
        .widget-area,
        aside.sidebar,
        .is-right-sidebar,
        .is-left-sidebar {
            display: none !important;
        }

        /* Make content full width */
        .site-content .content-area,
        .has-sidebar .site-content .content-area {
            width: 100% !important;
            max-width: 100% !important;
        }
    </style>
    <?php
});

add_action('wp_body_open', function () {
    if (!is_user_logged_in() || is_admin()) {
        return;
    }
    $user = wp_get_current_user();
    ?>
    <div class="dealer-header-bar">
        <div class="dealer-welcome">
            Welcome, <?php echo esc_html($user->display_name); ?>
        </div>
        <nav class="dealer-nav">
            <a href="<?php echo home_url('/'); ?>">Inventory</a>
            <a href="<?php echo wc_get_cart_url(); ?>">Cart (<?php echo WC()->cart->get_cart_contents_count(); ?>)</a>
            <a href="<?php echo wc_get_account_endpoint_url('orders'); ?>">My Orders</a>
            <a href="<?php echo esc_url(dealer_logout_url()); ?>">Logout</a>
        </nav>
    </div>
    <?php
});
