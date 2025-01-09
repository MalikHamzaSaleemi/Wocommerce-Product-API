<?php 
function clear_dialogpay_products_cache_on_product_change() {
        // Clear the transient
        delete_transient('dialogpay_products_cache');

    }

        add_action('save_post_product', 'clear_dialogpay_products_cache_on_product_change');
        add_action('woocommerce_delete_product', 'clear_dialogpay_products_cache_on_product_change');
        add_action('woocommerce_after_product_object_save', 'clear_dialogpay_products_cache_on_product_change');
        add_action('woocommerce_product_quick_edit_save', 'clear_dialogpay_products_cache_on_product_change');
               

      // Hook for when a product category is created
add_action('create_product_cat', 'on_product_category_created', 10, 2);
function on_product_category_created($term_id, $taxonomy) {
    if ($taxonomy === 'product_cat') {
        // Your custom code when a category is created
        error_log("Product category created: ID " . $term_id);
    }
}

// Hook for when a product category is deleted
add_action('delete_product_cat', 'on_product_category_deleted', 10, 3);
function on_product_category_deleted($term_id, $tt_id, $taxonomy) {
    if ($taxonomy === 'product_cat') {
        // Your custom code when a category is deleted
        error_log("Product category deleted: ID " . $term_id);
    }
}
