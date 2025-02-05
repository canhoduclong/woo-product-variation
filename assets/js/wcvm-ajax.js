jQuery(document).ready(function ($) {
    let selectedAttributes = {};

    function getSelectedAttributes() {
        let selectedAttributes = {};
    
        // Duyệt qua từng `.wcvm-attribute-wrapper`
        $('.wcvm-attribute-wrapper').each(function () {
            let attribute = $(this).data('attribute'); // Lấy tên thuộc tính từ `data-attribute`
            let selectedOption = $(this).find('input[type="radio"]:checked').val(); // Lấy giá trị của radio đã chọn
    
            if (selectedOption) {
                selectedAttributes[attribute] = selectedOption;
            }
        });
    
        return selectedAttributes;
    }

    function reset_price(){
        $('.price').text('');
        $('.add-to-cart').prop('disabled', true);
    }

    function reset_all_attribute(){
        const wrappers = document.querySelectorAll('.wcvm-attribute-wrapper');
        for (let i = 0; i < wrappers.length; i++) {
            wrappers[i].remove();
        }
    }
    
    function showMinicart() {
        // $('#minicart').fadeIn();
        $('#minicart').addClass('open');
        $('#close-minicart').addClass('open');

    }

    // Function to hide minicart
    function hideMinicart() {
        //$('#minicart').fadeOut();
        $('#minicart').removeClass('open');
        $('#close-minicart').removeClass('open');
    }
    // Update minicart content
    function updateMinicart(cartData) {
        var itemsHtml = '';
        //console.log(cartData);
        cartData.items.forEach(function(item) {
            itemsHtml += '<div class="product">';
            itemsHtml += '<div class="product-cart-details">';
            itemsHtml += '<h4 class="product-title"><a href="' + item.permalink + '">' + item.name + '</a></h4>';
            itemsHtml += '<span class="cart-product-info">'; 
            itemsHtml += '<span class="cart-product-qty">' + item.quantity + '</span> x ' + item.price_html;
            itemsHtml += '</span>';
            itemsHtml += '</div>';
            itemsHtml += '<figure class="product-image-container">';
            itemsHtml += '<a href="' + item.permalink + '" class="product-image">';
            itemsHtml +=  item.image;
            itemsHtml += '</a>';
            itemsHtml += '</figure>';
            itemsHtml += '<a href="javascript:;" class="btn-remove remove-from-cart" cart_item_key="' + item.cart_item_key + '" title="Remove Product"><i class="icon-close"></i></a>';
            itemsHtml += '</div>';

        });
        
        $('.cart-count').html(cartData.items.length); 
        $('.cart-txt').html(cartData.subtotal); 

        $('#minicart-items').html(itemsHtml); 
        $('#minicart-total-count').html(cartData.total_count);
        $('#minicart-subtotal').html(cartData.subtotal);
    }
    // Function to update minicart on page load
    function updateMinicartOnLoad() {
        $.ajax({
            type: 'POST',
            url: ajax_object.ajax_url,
            data: {
                action: 'get_cart_data'
            },
            success: function(response) {
                if (response.success) {
                    updateMinicart(response.data); 
                    showMinicart();
                }
            }
        });
    }



    
    function reset_all_following_wrappers(startIndex) {
        const wrappers = $('.wcvm-attribute-wrapper');
        wrappers.slice(startIndex + 1).remove(); // Xóa tất cả các wrapper sau `startIndex`
    } 

    function fetchPrice(productId, selectedAttributes) {
        //let selectedAttributes = getSelectedAttributes(); 
        $.ajax({
            url: wcvm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wcvm_get_price',
                product_id: productId,
                selected_attributes: selectedAttributes,
                nonce: wcvm_ajax.nonce,
            },
            success: function (response) {
                if (response.success) { 
                    $('.price').text('Giá: ' + response.price + ' đ');
                    $('#variation_id').val(response.variation_id); 
                    //$('.add-to-cart').prop('disabled', false);
                    document.querySelector('.add-to-cart').removeAttribute('disabled');
                    
                } else {
                    $('.price').text('');
                    $('#variation_id').val('');
                    $('.add-to-cart').prop('disabled', true);
                }
            },
        });
    }
    //--- reload all



    // Hàm lấy lại tất cả các thuộc tính
    function reloadAllAttributes() {
        let productId = $('#product_id').val();
        let pid = document.getElementById('product_id').value;

        // Gửi yêu cầu AJAX để lấy lại toàn bộ thuộc tính
        $.ajax({
            url: wcvm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wcvm_reload_all_attributes',
                product_id: productId,
                nonce: wcvm_ajax.nonce,
            },
            success: function (response) {
                if (response.success) {
                    $('#wcvm-attributes').empty();  // Xóa các thuộc tính hiện tại

                    let attributes = response.attributes;

                    // Hiển thị lại các thuộc tính
                    $.each(attributes, function (attributeKey, attributeValues) {
                        let wrapper = $('<div class="wcvm-attribute-wrapper" data-attribute="' + attributeKey + '"></div>');
                        wrapper.append('<h4>' + attributeValues[0].label + '</h4>');
                        $.each(attributeValues, function (index, attribute) {
                            wrapper.append(
                                `<label><input type="radio" class="wcvm-attribute-radio" name="${attribute.key}" value="${attribute.value}" ${attribute.checked}> ${attribute.value}</label><br>`
                            );
                        });
                        $('#wcvm-attributes').append(wrapper);
                    });
                    
                } else {

                    alert('Không thể tải lại thuộc tính. Vui lòng thử lại!');

                }
            },
        });
    }
    
    // Handle parent attribute selection
    $(document).on('change', '.wcvm-attribute-radio', function () {

        let wrapper = $(this).closest('.wcvm-attribute-wrapper');
        let wrapperIndex = $('.wcvm-attribute-wrapper').index(wrapper);

        // Kiểm tra nếu wrapper hiện tại là wrapper đầu tiên
        if (wrapperIndex === 0) {
            reset_price();
            reset_all_following_wrappers(wrapperIndex); // Reset tất cả các wrapper phía sau
        }

        let selectedAttributes = getSelectedAttributes();  
 
        let parentAttribute = wrapper.data('attribute'); 
        let value = $(this).val(); 

        // Update selected attributes
        selectedAttributes[parentAttribute] = value;
        let productId = $('#product_id').val(); 

       
        if (Object.keys(selectedAttributes).length !== wrapper.length) {
            reset_price(); 
        }

        reset_all_attribute();
        
        // Remove all sub-attributes below the current parent
        // wrapper.nextAll('.wcvm-attribute-wrapper').remove();

        // Fetch child attributes via AJAX
        $.ajax({
            url: wcvm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wcvm_get_child_attributes',
                product_id: productId,
                parent_attribute: selectedAttributes, 
                nonce: wcvm_ajax.nonce,
            },
            success: function (response) {
                if (response.success) {
                    let subAttributes = response.sub_attributes;
            
                    // Duyệt qua các thuộc tính cha
                    $.each(subAttributes, function (attributeKey, attributeValues) {
            
                        // Kiểm tra xem phần tử có tồn tại trong DOM hay chưa
                        let selector = `.wcvm-attribute-wrapper[data-attribute="${attributeKey}"]`;
                        if (document.querySelector(selector)) {
                            // Nếu phần tử đã tồn tại, xóa hết phần tử con trước khi thêm lại
                            let existingWrapper = $(selector);
                            existingWrapper.empty(); // Xóa hết nội dung cũ của phần tử này
            
                            // Duyệt qua các thuộc tính con và thêm các radio button vào
                            $.each(attributeValues, function (index, attribute) {
                                existingWrapper.append(
                                    `<label><input type="radio" class="wcvm-attribute-radio" name="${attribute.key}" value="${attribute.value}" ${attribute.checked}> ${attribute.value}</label><br>`
                                );
                            });
                        } else {
                            // Nếu phần tử chưa tồn tại, tạo mới phần tử .wcvm-attribute-wrapper
                            let subWrapper = $('<div class="wcvm-attribute-wrapper" data-attribute="' + attributeKey + '"></div>');
                            subWrapper.append('<h4>' + attributeValues[0].label + '</h4>'); // Dùng nhãn của thuộc tính đầu tiên làm tiêu đề
                            
                            // Duyệt qua các thuộc tính con và thêm các radio button vào
                            $.each(attributeValues, function (index, attribute) {
                                subWrapper.append(
                                    `<label><input type="radio" class="wcvm-attribute-radio" name="${attribute.key}" value="${attribute.value}" ${attribute.checked}> ${attribute.value}</label><br>`
                                );
                            });
            
                            // Thêm vào container chính (ví dụ: #wcvm-attributes)
                            $('#wcvm-attributes').append(subWrapper);
                        }
                    });
                }
            }
            
        });

        // Optionally, update price or other details
        fetchPrice(productId,selectedAttributes);
    });

    $(document).on('click', '.add-to-cart', function (e) {
        e.preventDefault();
        
        let button = $(this);
        
        /*
        let productId = button.data('product_id');
        let variationId = button.data('variation_id');
        let quantity = button.data('quantity') || 1;
        */

        let productId = document.querySelector('#product_id').value;
        let variationId =  document.querySelector('#variation_id').value;
        let quantity = document.querySelector('#quantity').value || 1;


        let variation = {}; // Nếu có biến thể, truyền các giá trị ở đây

        // Thu thập các giá trị biến thể từ các radio hoặc select
        $('.wcvm-attribute-wrapper').each(function () {
            let attribute = $(this).data('attribute');
            let value = $(this).find('input:checked, select').val();
            if (attribute && value) {
                variation[attribute] = value;
            }
        });

        // Gửi AJAX
        $.ajax({
            url: wcvm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wcvm_add_to_cart',
                product_id: productId,
                variation_id: variationId,
                quantity: quantity,
                variation: variation,
                nonce: wcvm_ajax.nonce,
            },
            success: function (response) { 
                if (response.fragments) {
                    console.log(response);
                    // Cập nhật giỏ hàng
                    $.each(response.fragments, function (key, value) {
                        $(key).replaceWith(value);
                    });
                    
                    updateMinicartOnLoad();

                    setTimeout(hideMinicart, 5000); 

                } else if (response.error) {
                    alert(response.error.message);
                }
            },
            error: function () {
                alert('Đã xảy ra lỗi khi thêm sản phẩm vào giỏ hàng.');
            },
        });
    });
    
    // Click event to close the minicart
    $('#close-minicart').on('click', function() {
        hideMinicart(); 
    });


    // Sự kiện nhấn nút "Reload All"
    $(document).on('click', '#wcvm-reload-all', function () {
        reloadAllAttributes();
        reset_price(); 
    });


});
