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
    $selectedAttribute = isset($_POST['parent_attribute']) ? $_POST['parent_attribute'] : [];
    
    if (empty($selectedAttribute)) {
        wp_send_json_error(['error' => 'No attributes selected.']);
        return;
    }
    
    $product = wc_get_product($product_id);
    $response = ['success' => false, 'sub_attributes' => []];
    
    if ($product && $product->is_type('variable')) {
        $variation_attributes = $product->get_variation_attributes();
        $attribute_list = []; 

        foreach ($variation_attributes as $attribute_name => $options) { 
            $attribute_slug = esc_html('attribute_' . sanitize_title($attribute_name));
            $attribute_label = esc_html(wc_attribute_label($attribute_name, $product));
            $attribute_list[$attribute_slug] = $attribute_label; 
        }

        // Luôn giữ đầy đủ giá trị của thuộc tính đầu tiên
        $first_attribute_key = array_key_first($variation_attributes);
        $first_attribute_slug = 'attribute_' . sanitize_title($first_attribute_key);
        foreach ($variation_attributes[$first_attribute_key] as $option) {
            $response['sub_attributes'][$first_attribute_slug][] = [
                'checked' => isset($selectedAttribute[$first_attribute_slug]) && $selectedAttribute[$first_attribute_slug] == $option ? 'checked' : '',
                'value'   => $option,
                'key'     => $first_attribute_slug,
                'label'   => $attribute_list[$first_attribute_slug]
            ];
        }

        // Lọc các thuộc tính còn lại dựa trên `selectedAttribute`
        $variations = $product->get_available_variations();  
        foreach ($variations as $variation) {
            $attributes = $variation['attributes'];
            $isMatch = true;

            foreach ($selectedAttribute as $att_key => $att_value) {       
                if (isset($attributes[$att_key]) && $attributes[$att_key] !== $att_value) {
                    $isMatch = false;
                    break;
                }
            } 

            if ($isMatch) {
                foreach ($attributes as $key => $value) {
                    if ($key !== $first_attribute_slug) { // Loại bỏ thuộc tính đầu tiên
                        $response['sub_attributes'][$key][] = [
                            'checked' => '',
                            'value'   => $value,
                            'key'     => $key,
                            'label'   => $attribute_list[$key] ?? ''
                        ];
                    }
                }
            }
        }

        // Loại bỏ giá trị trùng lặp
        $result = [];
        foreach ($response['sub_attributes'] as $attributeKey => $attributeGroup) {
            $tempValues = [];
            $result[$attributeKey] = [];
        
            foreach ($attributeGroup as $attribute) {
                if (!in_array($attribute['value'], $tempValues)) {
                    $result[$attributeKey][] = $attribute;
                    $tempValues[] = $attribute['value'];
                }
            }
        }
        $response['sub_attributes'] = $result;

        // Đánh dấu thuộc tính đã chọn
        foreach ($response['sub_attributes'] as $key => &$value) {
            foreach ($value as $sub_key => &$sub_value) { 
                if (isset($selectedAttribute[$key]) && $sub_value['value'] == $selectedAttribute[$key]) {
                    $sub_value['checked'] = 'checked';
                }
            }
            unset($sub_value);
        }
        unset($value);

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
