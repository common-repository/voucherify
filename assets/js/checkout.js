jQuery(document).ready(function () {
    const blockOptions = {
        message: null,
        overlayCSS: {
            background: '#fff',
            opacity: 0.6
        }
    };

    function updateCart() {
        jQuery(document.body).trigger('update_checkout');
        jQuery(document).trigger('wc_update_cart');
    }

    function handleCouponApplyResponse(response) {
        if (response.success && response.data.type === 'gift') {
            jQuery('.woocommerce-cart-form').removeClass( 'processing' ).unblock();
            jQuery("#vouchers-form").hide();
            jQuery("#gift-cards-form").show();
            jQuery("#vcrf_gift_coupon_code").text(response.data.code);
            jQuery("#gift-card-balance").text(response.data.balance);
        } else {
            updateCart();
        }
    }

    jQuery(document.body).on('click', '#vcrf_remove_voucher', function (event) {
        let data = {
            'action': 'vcrf_remove_voucher',
            'code': jQuery(event.currentTarget).attr('vcrf_coupon')
        };
        jQuery('.woocommerce-cart-form').block(blockOptions);
        jQuery('.woocommerce-checkout-review-order-table').block(blockOptions);

        jQuery.post(vcrf_checkout.admin_url, data, function (response) {
            updateCart();
        });
    });

    jQuery(document.body).on('click', '#vcrf_apply_coupon', function (event) {
        let data = {
            'action': 'vcrf_add_voucher',
            'code': jQuery('#vcrf_coupon_code').val()
        };
        jQuery('.woocommerce-cart-form').block(blockOptions);

        jQuery.post(vcrf_checkout.admin_url, data, function (response) {
            handleCouponApplyResponse(response);
        });

    });

    jQuery(document.body).on('click', '#vcrf_apply_gift_coupon', function (event) {
        let data = {
            action: 'vcrf_add_gift_voucher',
            code: jQuery('#vcrf_gift_coupon_code').text(),
            amount: jQuery('#vcrf_gift_coupon_amount').val(),
        };
        jQuery('.woocommerce-cart-form').block(blockOptions);

        jQuery.post(vcrf_checkout.admin_url, data, function (response) {
            updateCart();
        });

    });
    jQuery(document.body).on('click', '#vcrf_cancel_gift_coupon', function (event) {
        updateCart();
    });

    jQuery(document.body).on('click', '#vcrf_remove_gift_card', function (event) {
        event.preventDefault();
        let data = {
            'action': 'vcrf_remove_gift_coupon',
            'code': jQuery(event.currentTarget).attr('vcrf_gift_card')
        };
        jQuery('.woocommerce-cart-form').block(blockOptions);
        jQuery('.woocommerce-checkout-review-order-table').block(blockOptions);

        jQuery.post(vcrf_checkout.admin_url, data, function (response) {
            updateCart();
        });
    });

    jQuery(document.body).on('click', '#vcrf_remove_promotion', function (event) {
        let data = {
            'action': 'vcrf_remove_promotion',
            'code': jQuery(event.currentTarget).attr('vcrf_promotion')
        };
        jQuery('.woocommerce-cart-form').block(blockOptions);
        jQuery('.woocommerce-checkout-review-order-table').block(blockOptions);

        jQuery.post(vcrf_checkout.admin_url, data, function (response) {
            updateCart();
        });
    });

});