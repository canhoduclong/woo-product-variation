jQuery(document).ready(function ($) {
    $('.single_add_to_cart_button').on('click', function (e) {
        e.preventDefault();

        var $button = $(this);
        var product_id = $button.data('product_id');
        var variations = {};

        $('.wcvm-variation').each(function () {
            var attribute_name = $(this).find('input').attr('name');
            variations[attribute_name] = [];
            $(this).find('input:checked').each(function () {
                variations[attribute_name].push($(this).val());
            });
        });

        $.ajax({
            url: wcvm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wcvm_add_to_cart',
                product_id: product_id,
                variations: variations
            },
            success: function (response) {
                if (response.fragments) {
                    $.each(response.fragments, function (key, value) {
                        $(key).replaceWith(value);
                    });
                }
            },
            error: function (response) {
                alert(response.responseJSON.message || 'Error adding to cart.');
            }
        });
    });
});

//=============


jQuery(document).ready(function ($) {
    $('.single_add_to_cart_button').on('click', function (e) {
        e.preventDefault();

        var form = $(this).closest('form');
        var data = form.serialize();

        $.ajax({
            url: wc_add_to_cart_params.ajax_url,
            type: 'POST',
            data: {
                action: 'woocommerce_ajax_add_to_cart',
                product_id: form.find('[name="product_id"]').val(),
                variation_data: data,
            },
            success: function (response) {
                if (response.fragments) {
                    $.each(response.fragments, function (key, value) {
                        $(key).replaceWith(value);
                    });
                }
            },
            error: function () {
                alert('There was an error adding the product to the cart.');
            }
        });
    });
});


