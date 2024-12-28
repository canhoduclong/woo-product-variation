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
    function reset_all_sub_attribute(){
        const wrappers = document.querySelectorAll('.wcvm-attribute-wrapper');
        for (let i = 1; i < wrappers.length; i++) {
            wrappers[i].remove();
        }
    }
    // Handle parent attribute selection
    $(document).on('change', '.wcvm-attribute-radio', function () {
        
        let wrapper = $(this).closest('.wcvm-attribute-wrapper');
        if (wrapper.is(':first-child')) { 
            reset_price();
            reset_all_sub_attribute();
        }
        let parentAttribute = wrapper.data('attribute');
        let selectedAttributes = getSelectedAttributes();

        let value = $(this).val();
        let productId = $('#wcvm-product-id').val();

        // Update selected attributes
        selectedAttributes[parentAttribute] = value;

        // Remove all sub-attributes below the current parent
        wrapper.nextAll('.wcvm-attribute-wrapper').remove();

        // Fetch child attributes via AJAX
        $.ajax({
            url: wcvm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wcvm_get_child_attributes',
                product_id: productId,
                parent_attribute: selectedAttributes,
               // parent_attribute: parentAttribute,
                value: value,
                nonce: wcvm_ajax.nonce,
            },
            success: function (response) {
                if (response.success) {
                    let subAttributes = response.sub_attributes;

                    $.each(subAttributes, function (attribute, values) {
                        let selector = `.wcvm-attribute-wrapper[data-attribute="attribute_${attribute}"]`;

                        if (document.querySelector(selector)) {  
                            let oldElement = $(`.wcvm-attribute-wrapper[data-attribute="attribute_${oldAttribute}"]`);
                            
                        }else{
                            let subWrapper = $('<div class="wcvm-attribute-wrapper" data-attribute="attribute_' + attribute + '"></div>');
                            subWrapper.append('<h4>' + attribute + '</h4>'); 
                            $.each(values, function (index, value) {
                                subWrapper.append(
                                    '<label><input type="radio" class="wcvm-attribute-radio" name="' + attribute + '" value="' + value + '"> ' + value + '</label><br>'
                                );
                            }); 
                            $('#wcvm-attributes').append(subWrapper);
                        }
                    });
                }
            },
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
