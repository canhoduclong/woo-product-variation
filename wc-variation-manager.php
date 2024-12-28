<?php
/**
 * Plugin Name: WC Variation Manager
 * Description: Plugin quản lý các biến thể sản phẩm trong WooCommerce với các thuộc tính cha - con và sử dụng AJAX.
 * Version: 1.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Enqueue scripts and styles
add_action('wp_enqueue_scripts', 'wcvm_enqueue_scripts');
function wcvm_enqueue_scripts() {
    wp_enqueue_script('wcvm-ajax', plugin_dir_url(__FILE__) . 'assets/js/wcvm-ajax.js', array('jquery'), null, true);
    wp_localize_script('wcvm-ajax', 'wcvm_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wcvm_nonce')
    ));
}

// Display attributes dynamically
add_action('woocommerce_before_add_to_cart_button', 'wcvm_display_dynamic_attributes');
function wcvm_display_dynamic_attributes() {
    global $product;

    if ($product && $product->is_type('variable')) {
        $attributes = $product->get_variation_attributes();

        echo '<div id="wcvm-attributes">';
        
        foreach ($attributes as $attribute_name => $options) {
            $attribute_slug = esc_html('attribute_' . sanitize_title($attribute_name));
            $attribute_label = esc_html(wc_attribute_label($attribute_name, $product));
            
            echo '<div class="wcvm-attribute-wrapper" data-attribute="' . esc_attr($attribute_slug) . '">';
            echo '<h4>' . $attribute_label . '</h4>';
            
            foreach ($options as $option) {
                echo '<label>';
                echo '<input type="radio" class="wcvm-attribute-radio" name="' . $attribute_slug . '" value="' . esc_attr($option) . '"> ';
                echo esc_html($option);
                echo '</label><br>';
            }
            
            echo '<div class="wcvm-sub-attributes"></div>'; // Placeholder for child attributes
            echo '</div>';
        }

        echo '</div>';

        // Hidden input for product ID
        echo '<input type="hidden" id="wcvm-product-id" value="' . esc_attr($product->get_id()) . '">';
        echo '<div id="wcvm-price" style="margin-top: 15px; font-size: 1.2em; font-weight: bold;"></div>';
        echo '<button type="button" class="button wcvm-add-to-cart" disabled>Add to Cart</button>';
    }
}


// AJAX handler to get price and enable "Add to Cart"
add_action('wp_ajax_wcvm_get_price', 'wcvm_get_price');
add_action('wp_ajax_nopriv_wcvm_get_price', 'wcvm_get_price');
function wcvm_get_price() {
    check_ajax_referer('wcvm_nonce', 'nonce');

    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    $selected_attributes = isset($_POST['selected_attributes']) ? $_POST['selected_attributes'] : [];

    $product = wc_get_product($product_id);
    $response = ['success' => false, 'price' => null];

    if ($product && $product->is_type('variable')) {
        $variations = $product->get_available_variations();

        foreach ($variations as $variation) {
            $attributes = $variation['attributes'];
            
            if (array_diff_assoc( $attributes, $selected_attributes) === []) {
                $response['price'] = $variation['display_price'];
                $response['success'] = true;
                break;
            }
        }
    }

    wp_send_json($response);
}


add_action('wp_ajax_wcvm_get_child_attributes', 'wcvm_get_child_attributes');
add_action('wp_ajax_nopriv_wcvm_get_child_attributes', 'wcvm_get_child_attributes');
function wcvm_get_child_attributes() {
    check_ajax_referer('wcvm_nonce', 'nonce');

    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    //$parent_attribute = isset($_POST['parent_attribute']) ? sanitize_text_field($_POST['parent_attribute']) : '';
    
    // Lấy Attributes
    $template  = isset($_POST['parent_attribute']) ? $_POST['parent_attribute'] : [];
    if (empty($template )) {
        wp_send_json_error(['error' => 'No attributes selected.']);
        return;
    }
    
    $value = isset($_POST['value']) ? sanitize_text_field($_POST['value']) : '';
    $product = wc_get_product($product_id);
    $response = ['success' => false, 'sub_attributes' => []];
    
    if ($product && $product->is_type('variable')) {

        $attributes = $product->get_variation_attributes();  
        //print_r($attributes);
        /*
        foreach ($attributes as $attribute_name => $options) {
            $attribute_slug = esc_html('attribute_' . sanitize_title($attribute_name));
            $attribute_label = esc_html(wc_attribute_label($attribute_name, $product));
        }
        */
        $variations = $product->get_available_variations();    
        foreach ($variations as $variation) {
            $attributes = $variation['attributes'];
            
            $isMatch = true; 
            foreach ($template as $key => $value) {
                if (!isset($attributes[$key]) || $attributes[$key] !== $value) {
                    $isMatch = false;
                    break;
                }
            }

            if ($isMatch) {
                foreach ($attributes as $key => $val) {
                    if (array_key_exists($key, $template)) {
                        continue;
                    }
                    
                    $key = str_replace('attribute_', '', $key); 
                    if (!isset($response['sub_attributes'][$key])) {
                        $response['sub_attributes'][$key] = [];
                    }
                    $response['sub_attributes'][$key][] = $val;
                    
                }
            } 
        }

        foreach ($response['sub_attributes'] as $key => $values) {
            $response['sub_attributes'][$key] = array_unique($values);
        } 
        $response['success'] = true;
    }
    wp_send_json($response);
}

// Hàm so khớp dữ liệu
function matchData($template, $data) {
    return array_filter($data, function ($item) use ($template) {
        foreach ($template as $key => $value) {
            if (!isset($item[$key]) || $item[$key] !== $value) {
                return false;
            }
        }
        return true;
    });
}
