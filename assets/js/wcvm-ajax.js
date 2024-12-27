jQuery(document).ready(function ($) {
    let selectedAttributes = {};

    // Xử lý khi chọn radio button
    $('.wcvm-attribute-radio').on('change', function () {
        let attribute = $(this).closest('.wcvm-attribute').data('attribute');
        let value = $(this).val();

        // Cập nhật giá trị thuộc tính đã chọn
        selectedAttributes[attribute] = value;

        // Gọi AJAX để cập nhật các thuộc tính liên quan và giá
        updateAttributesAndPrice();
    });

    function updateAttributesAndPrice() {
        let productId = $('#wcvm-product-id').val();

        $.ajax({
            url: wcvm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wcvm_get_filtered_attributes',
                product_id: productId,
                attributes: selectedAttributes,
                nonce: wcvm_ajax.nonce,
            },
            success: function (response) {
                if (response.success) {
                    let filteredAttributes = response.data.filtered_attributes;
                    let price = response.data.price;

                    // Cập nhật các radio button
                    $('.wcvm-attribute').each(function () {
                        let attribute = $(this).data('attribute');
                        let radios = $(this).find('.wcvm-attribute-radio');

                        radios.each(function () {
                            let value = $(this).val();

                            // Đối với thuộc tính đầu tiên (Màu sắc), không ẩn radio button
                            if (Object.keys(selectedAttributes).indexOf(attribute) === 0) {
                                $(this).parent().show();
                                return;
                            }

                            // Đối với các thuộc tính khác, chỉ hiển thị các giá trị hợp lệ
                            if (filteredAttributes[attribute] && filteredAttributes[attribute].includes(value)) {
                                $(this).parent().show();
                            } else {
                                $(this).parent().hide();
                                $(this).prop('checked', false); // Bỏ chọn radio bị ẩn
                            }
                        });
                    });

                    // Hiển thị giá và kích hoạt nút "Add to Cart"
                    if (price !== null) {
                        $('#wcvm-price').text('Price: ' + price + ' USD');
                        $('.wcvm-add-to-cart').prop('disabled', false);
                    } else {
                        $('#wcvm-price').text('');
                        $('.wcvm-add-to-cart').prop('disabled', true);
                    }
                }
            },
        });
    }
});
