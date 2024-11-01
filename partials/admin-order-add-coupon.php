<button type="button" class="button"
        id="vcrf_admin_apply_coupon"><?php esc_attr_e( 'Apply coupon', 'woocommerce' ); ?></button>
<script>
    jQuery(document).ready(function () {

        var tryAddingCoupon = function(code, amount) {
            let data = {
                'action': 'vcrf_admin_add_coupon',
                'code': code,
                'order_id': woocommerce_admin_meta_boxes.post_id,
                'amount': amount
            };
            jQuery.post('<?php echo admin_url( 'admin-ajax.php' ); ?>', data, function (response) {
                if (!response.success) {
                    let alertMessage = "Applying coupon failed.";
                    if (response.data && response.data.message) {
                        alertMessage = response.data.message;
                    }

                    window.alert(alertMessage);
                } else if ('type' in response.data && response.data.type === 'gift' && isNaN(parseInt(amount))) {
                    let amountPrompt = null;
                    do {
                        amountPrompt = window.prompt("Provide currency amount (integer greater than 0) to redeem from the gift card.");
                    } while(amountPrompt != null && isNaN(parseInt(amountPrompt)));

                    if (amountPrompt != null) {
                        tryAddingCoupon(code, parseInt(amountPrompt));
                    }
                } else if (response.success) {
                    jQuery('#woocommerce-order-items').trigger('wc_order_items_reload');
                }
            });
        }

        jQuery('#woocommerce-order-items').off('click', '#vcrf_admin_apply_coupon');
        jQuery('#woocommerce-order-items').on('click', '#vcrf_admin_apply_coupon', function (event) {
            const code = window.prompt('Enter a coupon code to apply. Discounts are applied to line totals, before taxes.');
            if (null != code) {
                tryAddingCoupon(code);
            }
        });
    });
</script>
