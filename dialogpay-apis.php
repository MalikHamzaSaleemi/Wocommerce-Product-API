<?php
/**
 * Plugin Name: DialogPay APIs
 * Description: A plugin to provide a secure API for authenticating users and fetching WooCommerce product data.
 * Version: 1.0.0
 * Author: DialogPay
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}


$includes_dir = plugin_dir_path(__FILE__) . 'includes';
if (is_dir($includes_dir)) {
    // Create a RecursiveDirectoryIterator to iterate over all files and subdirectories
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($includes_dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    // Loop through each file
    foreach ($iterator as $file) {
        // Include only PHP files
        if ($file->isFile() && $file->getExtension() === 'php') {
            require_once $file->getPathname();
        }
    }
}



function dialogpay_api_endpoints() {
    $namespace = 'dialogpay-api/v1';
    $routes = array(
        array(
            'route' => '/authenticate',
            'methods' => 'POST',
            'callback' => [ new Dialogpay_API_Auth(), 'authenticate_user' ],
            'permission_callback' => '__return_true'
        ),
        array(
            'route' => '/products',
            'methods' => 'GET',
            'callback' => [ new Dialogpay_API_Products(), 'get_products' ],
            'permission_callback' => [ new Dialogpay_API_Auth(), 'verify_token' ]
        ),
        array(
            'route' => '/product-categories',
            'methods' => 'GET',
            'callback' => [ new Dialogpay_API_Products(), 'get_categories' ],
            'permission_callback' => [ new Dialogpay_API_Auth(), 'verify_token' ]
        )
    );

    // Loop through each route and register it
    foreach ($routes as $route) {
        register_rest_route($namespace, $route['route'], array(
            'methods' => isset($route['methods']) ? $route['methods'] : 'GET',
            'callback' => $route['callback'],
            'permission_callback' => isset($route['permission_callback']) ? $route['permission_callback'] : '__return_true', // Public access
        ));
    }

}
add_action('rest_api_init', 'dialogpay_api_endpoints');

// Rewrite rule for custom API endpoint
function dialogpay_api_rewrite_rule() {
    add_rewrite_rule('^dialogpay-api/v1/authenticate/?$', 'index.php?rest_route=/dialogpay-api/v1/authenticate', 'top');
    add_rewrite_rule('^dialogpay-api/v1/products/?$', 'index.php?rest_route=/dialogpay-api/v1/products', 'top');

    add_rewrite_rule('^dialogpay-api/v1/product-categories/?$', 'index.php?rest_route=/dialogpay-api/v1/product-categories', 'top');



}
add_action('init', 'dialogpay_api_rewrite_rule');

// Flush rewrite rules on plugin activation
function custom_api_flush_rewrite_rules() {
    dialogpay_api_rewrite_rule();
    //flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'custom_api_flush_rewrite_rules');






