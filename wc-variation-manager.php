<?php
/**
 * Plugin Name: WooCommerce Variation Manager
 * Description: Dynamic product variations with dependent attributes.
 * Version: 1.0
 * Author: vBrand
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Enqueue scripts and styles
add_action('wp_enqueue_scripts', 'wcvm_enqueue_scripts');
function wcvm_enqueue_scripts() {
    if (is_product()) {
        wp_enqueue_script('wcvm-ajax', plugin_dir_url(__FILE__) . 'assets/js/wcvm-ajax.js', ['jquery'], '1.0', true);
        wp_localize_script('wcvm-ajax', 'wcvm_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('wcvm_nonce'),
        ]);
    }
}

// Display attributes dynamically
add_action('woocommerce_before_add_to_cart_button', 'wcvm_display_dynamic_attributes');
function wcvm_display_dynamic_attributes() {
    global $product;

    if ($product && $product->is_type('variable')) {
        $attributes = $product->get_variation_attributes();

        echo '<div id="wcvm-attributes">';
        
        //echo "<pre>";  print_r($attributes);  echo "</pre>";

        foreach ($attributes as $attribute_name => $options) {
           
            
            $attribute_slug     = '';
            $attribute_label    = '';
            if (is_object($options) && method_exists($options, 'is_taxonomy') && $options->is_taxonomy()) {
                // Tạo slug cho thuộc tính
                $attribute_slug = esc_html('attribute_' . $attribute_name);
    
                // Lấy tên hiển thị của thuộc tính
                $attribute_label = esc_html(wc_attribute_label($attribute_name, $product)); 
            } elseif (is_array($options)) {
                // Nếu thuộc tính là custom attribute (mảng giá trị)
                $attribute_slug = esc_html('attribute_' . sanitize_title($attribute_name));
                $attribute_label = esc_html($attribute_name);
            } else {
                echo 'Không xác định được kiểu thuộc tính: ' . esc_html($attribute_name) . '<br>';
            }
            
            echo '<div class="wcvm-attribute" data-attribute="' . esc_attr($attribute_slug) . '">'; 
            echo '<strong>' . $attribute_label . '</strong><br>';
            foreach ($options as $option) {
                echo '<label>';
                echo '<input type="radio" class="wcvm-attribute-radio" name="' . $attribute_slug . '" value="' . esc_attr($option) . '"> ';
                echo esc_html($option);
                echo '</label><br>';
            }
            echo '</div>';
        }

        echo '</div>';

        // Hidden input for product ID
        echo '<input type="hidden" id="wcvm-product-id" value="' . esc_attr($product->get_id()) . '">';
        echo '<div id="wcvm-price" style="margin-top: 15px; font-size: 1.2em; font-weight: bold;"></div>';
        echo '<button type="button" class="button wcvm-add-to-cart" disabled>Add to Cart</button>';
    }
}

// AJAX handler to get filtered attributes and price
add_action('wp_ajax_wcvm_get_filtered_attributes', 'wcvm_get_filtered_attributes');
add_action('wp_ajax_nopriv_wcvm_get_filtered_attributes', 'wcvm_get_filtered_attributes');
function wcvm_get_filtered_attributes() {
    check_ajax_referer('wcvm_nonce', 'nonce');

    $product_id = intval($_POST['product_id']);

    /**
     * Láy thuộc tính chọn
     */
    $selected_attributes = isset($_POST['attributes']) ? $_POST['attributes'] : []; 

    /**
     * Láy thông tin sản phẩm
     */
    $product = wc_get_product($product_id); 

    if (!$product || !$product->is_type('variable')) {
        wp_send_json_error(['message' => 'Invalid product.']);
    }

    /**
     * Lấy các biến thể của sản phẩm
     */
    $available_variations = $product->get_available_variations(); 


    $filtered_attributes = [];
    $final_price = null;

    foreach ($available_variations as $variation) {
        $variation_attributes = $variation['attributes'];
        
        $is_match = true;

       /**
        * Kiểm tra xem thuộc tính chọn có trong danh sách biến thể ( variation ) hay không ?
        */
        foreach ($selected_attributes as $key => $value) {
            if (!isset($variation_attributes[$key]) || $variation_attributes[$key] !== $value) {
                $is_match = false;
                break;
            }
        }

        if ($is_match) {
            /**
             * Lọc thuộc tính của biến thể ( variation ) có thuộc tính = Thuộc tính  được chọn
             */
            foreach ($variation_attributes as $key => $value) {
                if (!isset($filtered_attributes[$key])) {
                    $filtered_attributes[$key] = [];
                }
                if (!in_array($value, $filtered_attributes[$key])) {
                    $filtered_attributes[$key][] = $value;
                }
            }
            
            /**
             * Khi client chọn đủ option thì mới ra được giá của  variable này
             */
            if (count($selected_attributes) === count($variation_attributes)) {
                $final_price = $variation['display_price'];
            }

        }
    }

    wp_send_json_success(['filtered_attributes' => $filtered_attributes, 'price' => $final_price]);
}
