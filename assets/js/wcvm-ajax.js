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
        $('#wcvm-price').text('');
        $('.wcvm-add-to-cart').prop('disabled', true);
    }
    function reset_all_attribute(){
        const wrappers = document.querySelectorAll('.wcvm-attribute-wrapper');
        for (let i = 0; i < wrappers.length; i++) {
            wrappers[i].remove();
        }
    }
    // Handle parent attribute selection
    $(document).on('change', '.wcvm-attribute-radio', function () {

        const wrappers = document.querySelectorAll('.wcvm-attribute-wrapper');

        let selectedAttributes = getSelectedAttributes(); 

        let wrapper = $(this).closest('.wcvm-attribute-wrapper');
        let parentAttribute = wrapper.data('attribute'); 
        let value = $(this).val(); 

        // Update selected attributes
        selectedAttributes[parentAttribute] = value;
        let productId = $('#wcvm-product-id').val(); 

       
        if (Object.keys(selectedAttributes).length !== wrappers.length) {
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
               // parent_attribute: parentAttribute, 
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
        fetchPrice(productId);
    });

    function fetchPrice(productId) {
        let selectedAttributes = getSelectedAttributes();
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
                    $('#wcvm-price').text('Price: ' + response.price + ' USD');
                    $('.wcvm-add-to-cart').prop('disabled', false);
                } else {
                    $('#wcvm-price').text('');
                    $('.wcvm-add-to-cart').prop('disabled', true);
                }
            },
        });
    }
    
});
