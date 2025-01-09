<?php

class Dialogpay_API_Products {

    
    public function get_products( WP_REST_Request $request ) 
    {
        if ( ! class_exists( 'WooCommerce' ) ) 
        {
            return rest_ensure_response([
                'success' => false,
                'message' => 'WooCommerce is not installed or activated.',
            ]);
        }
    
        // Get parameters from the request
        $search_query = sanitize_text_field( $request->get_param('search') );
        $product_id = absint( $request->get_param('id') );
        $product_category = sanitize_text_field( $request->get_param('category') );
    
        // Check if the product list is cached
        $cached_products = get_transient( 'dialogpay_products_cache' );
    
        if ( $cached_products ) {
            // Filter cached products by product ID
            if ( ! empty( $product_id ) ) {
                $filtered_products = array_filter($cached_products, function ($product) use ($product_id) {
                    return $product['id'] === $product_id;
                });
    
                if ( empty( $filtered_products ) ) {
                    return rest_ensure_response([
                        'success' => false,
                        'message' => 'No products found with the given ID in cache.',
                    ]);
                }
    
                return rest_ensure_response([
                    'success' => true,
                    'cached' => true,
                    'products' => array_values($filtered_products),
                ]);
            }
    
            // Filter cached products by search query
            if ( ! empty( $search_query ) ) {
                $filtered_products = array_filter($cached_products, function ($product) use ($search_query) {
                    return stripos($product['name'], $search_query) !== false; // Case-insensitive search in product name
                });
    
                if ( empty( $filtered_products ) ) {
                    return rest_ensure_response([
                        'success' => false,
                        'message' => 'No products match the search query in cache.',
                    ]);
                }
    
                return rest_ensure_response([
                    'success' => true,
                    'cached' => true,
                    'products' => array_values($filtered_products),
                ]);
            }
    
            // Filter cached products by product category
            if ( ! empty( $product_category ) ) {
                $filtered_products = array_filter($cached_products, function ($product) use ($product_category) {
                    return in_array($product_category, $product['category']); // Match category in cached products
                });
    
                if ( empty( $filtered_products ) ) {
                    return rest_ensure_response([
                        'success' => false,
                        'message' => 'No products found in the specified category in cache.',
                    ]);
                }
    
                return rest_ensure_response([
                    'success' => true,
                    'cached' => true,
                    'products' => array_values($filtered_products),
                ]);
            }
    
            // Return all cached products if no filters are applied
            return rest_ensure_response([
                'success' => true,
                'cached' => true,
                'products' => $cached_products,
            ]);
        }
    
        // If no cache exists, perform a live query
        $args = [
            'post_type' => 'product',
            'posts_per_page' => -1, // Fetch all products if no cache
            'post_status' => 'publish',
        ];
    
        // Add product ID filter
        if ( ! empty( $product_id ) ) {
            $args['p'] = $product_id; // Query by product ID
        }
    
        // Add search query filter
        if ( ! empty( $search_query ) ) {
            $args['s'] = $search_query; // Search by name
        }
    
        // Add product category filter
        if ( ! empty( $product_category ) ) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'product_cat',
                    'field'    => 'slug', // Match category by slug
                    'terms'    => $product_category,
                ],
            ];
        }
    
        $query = new WP_Query( $args );
    
        if ( ! $query->have_posts() ) {
            return rest_ensure_response([
                'success' => false,
                'message' => 'No products match the criteria.',
            ]);
        }
    
        $products = [];
        while ( $query->have_posts() ) {
            $query->the_post();
            $product = wc_get_product( get_the_ID() );
    
            $products[] = [
                'id'        => $product->get_id(),
                'name'      => $product->get_name(),
                'price'     => $product->get_price(),
                'category'  => wp_get_post_terms( $product->get_id(), 'product_cat', ['fields' => 'slugs'] ), // Get category slugs
                'link'      => $product->get_permalink(),
                'stock'     => $product->is_in_stock() ? 'In Stock' : 'Out of Stock',
                'image'     => wp_get_attachment_url($product->get_image_id()),
            ];
        }
    
        wp_reset_postdata();
    
        // Save the product data in a transient for future use if no search query or product ID or category is provided
        if ( empty( $search_query ) && empty( $product_id ) && empty( $product_category ) ) {
            set_transient( 'dialogpay_products_cache', $products, 12 * HOUR_IN_SECONDS );
        }
    
        return rest_ensure_response([
            'success' => true,
            'cached' => false,
            'products' => $products,
        ]);
    }
    


    
    






    public function get_categories( WP_REST_Request $request ) 
{
    if ( ! class_exists( 'WooCommerce' ) ) 
    {
        return rest_ensure_response([
            'success' => false,
            'message' => 'WooCommerce is not installed or activated.',
        ]);
    }

    // Check if categories data exists in the transient
    $cached_categories = get_transient( 'dialogpay_categories_cache' );
    delete_transient('dialogpay_categories_cache');
    if ( $cached_categories ) {
        return rest_ensure_response([
            'success' => true,
            'cached' => true,
            'categories' => $cached_categories,
        ]);
    }

    // Fetch fresh product categories if no cached data exists
    $args = [
        'taxonomy'   => 'product_cat',
        'hide_empty' => true, // Only show categories with products
    ];

    $categories = get_terms( $args );

    if ( is_wp_error( $categories ) || empty( $categories ) ) {
        return rest_ensure_response([
            'success' => false,
            'message' => 'No categories found.',
        ]);
    }

    $category_data = array_map(function($category) {
        return [
            'id'          => $category->term_id,
            'name'        => $category->name,
            'slug'        => $category->slug,
            'description' => $category->description,
            'count'       => $category->count,
            'link'        => get_term_link( $category ),
        ];
    }, $categories);

    // Save the category data in a transient
    set_transient( 'dialogpay_categories_cache', $category_data, 12 * HOUR_IN_SECONDS ); // Cache for 12 hours

    return rest_ensure_response([
        'success' => true,
        'cached' => false,
        'categories' => $category_data,
    ]);
}


}










 // public function __construct() {
    //     //$this->register_routes();
    // }

    // public function register_routes() {
    //     register_rest_route( 'dialogpay-api/v1', '/products', [
    //         'methods' => 'GET',
    //         'callback' => [ $this, 'get_products' ],
    //         'permission_callback' => [ new dialogpay_API_Auth(), 'verify_token' ],
    //     ]);

    //     // register_rest_route( 'dialogpay-api/v1', '/products', [
    //     //     'methods' => 'GET',
    //     //     'callback' => [ $this, 'get_products' ],
    //     //     'permission_callback' => function( $request ) {
    //     //         return $this->verify_token( $request ) ? true : new WP_Error(
    //     //             'rest_forbidden',
    //     //             __( 'Sorry, you are not allowed to do that ffffffff.' ),
    //     //             [ 'status' => 401 ]
    //     //         );
    //     //     },
    //     // ]);
    // }