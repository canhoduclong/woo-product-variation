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
add_action('woocommerce_single_product_summary', 'wcvm_display_dynamic_attributes');
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
        echo '<label><h4>Số lượng: </h4>';
        echo '<input type="text" id="quantity" name="quantity" value="1"> ';
        echo '</label><br>';
        echo '<input type="hidden" id="product_id" value="'. esc_attr($product->get_id()) .'"><br />';
        echo '<input type="hidden" id="variation_id" name="variation_id" value="">';
        echo '<a id="wcvm-reload-all" class="button" href="javascript:void(0)">Chọn Lại</a> <br />'; 
        echo '<button type="button" class="button add-to-cart" disabled>THÊM VÀO GIỎ</button>';
    }elseif($product){
        echo '  <div class="details-filter-row details-row-size">
                    <label for="qty">Qty:</label>
                    <div class="product-details-quantity">
                        <div class="quantity"> 
                            <input type="number" id="quantity" class="input-text qty text" name="quantity" value="1" aria-label="Product quantity" size="4" min="1" max="" step="1" placeholder="" inputmode="numeric" autocomplete="off" style="display: none;">
                        </div>
                    </div>
                </div>
                <div class="product-details-action">
                    <button  name="add-to-cart" value="14" class="btn-product btn-cart add_to_cart button alt">Add to cart</button>
                </div>';
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
    $response = ['success' => false, 'variation_id'=>null, 'price' => null];

    if ($product && $product->is_type('variable')) {
        $variations = $product->get_available_variations();

        foreach ($variations as $variation) {
            $attributes = $variation['attributes']; 
            if (array_diff_assoc( $attributes, $selected_attributes) === []) {
                $response['price'] = $variation['display_price'];
                $response['variation_id'] = $variation['variation_id'];
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
//--- reload all attribute
add_action('wp_ajax_wcvm_reload_all_attributes', 'wcvm_reload_all_attributes');
add_action('wp_ajax_nopriv_wcvm_reload_all_attributes', 'wcvm_reload_all_attributes');

function wcvm_reload_all_attributes() {
    check_ajax_referer('wcvm_nonce', 'nonce');

    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;

    if (!$product_id) {
        wp_send_json_error(['error' => 'Invalid product ID']);
        return;
    }

    $product = wc_get_product($product_id);

    if ($product && $product->is_type('variable')) {
        $variation_attributes = $product->get_variation_attributes();
        $available_variations = $product->get_available_variations();
        $response = ['success' => true, 'attributes' => []];

        foreach ($variation_attributes as $attribute_name => $options) {
            $attribute_slug = 'attribute_' . sanitize_title($attribute_name);
            $attribute_label = wc_attribute_label($attribute_name, $product);

            $response['attributes'][$attribute_slug] = [];
            foreach ($options as $option) {
                $response['attributes'][$attribute_slug][] = [
                    'checked' => '',
                    'value' => $option,
                    'key' => $attribute_slug,
                    'label' => $attribute_label,
                ];
            }
        }

        wp_send_json($response);
    }

    wp_send_json_error(['error' => 'Product is not variable']);
}

//-- thêm vao gio  hàng

add_action('wp_ajax_wcvm_add_to_cart', 'wcvm_add_to_cart');
add_action('wp_ajax_nopriv_wcvm_add_to_cart', 'wcvm_add_to_cart');

function wcvm_add_to_cart() {
    // Kiểm tra nonce để đảm bảo tính bảo mật
    check_ajax_referer('wcvm_nonce', 'nonce');

    // Lấy các tham số từ AJAX
    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    $quantity = isset($_POST['quantity']) ? absint($_POST['quantity']) : 1;
    $variation_id = isset($_POST['variation_id']) ? absint($_POST['variation_id']) : 0;
    $variation = isset($_POST['variation']) ? $_POST['variation'] : [];

    if ($product_id > 0) {
        // Thêm sản phẩm vào giỏ hàng
        $added = WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation);

        if ($added) {
            // Cập nhật lại giỏ hàng
            WC_AJAX::get_refreshed_fragments();
        } else {
            wp_send_json_error(['message' => 'Không thể thêm sản phẩm vào giỏ hàng.']);
        }
    } else {
        wp_send_json_error(['message' => 'Dữ liệu không hợp lệ.']);
    }

    wp_die();
}

