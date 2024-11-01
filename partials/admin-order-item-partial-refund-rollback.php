<script>
    jQuery(document).ready(function () {
        var label = '<?php echo _e( 'Rollback voucher redemption', 'voucherify' ); ?>';
        jQuery(".wc-order-refund-items table").prepend(
            '<tr><td class="label"><div class="partial_refund_rollback"><label for="partial_refund_rollback">' + label
            + '</label></div></td><td class="total"><div class="partial_refund_rollback"><input type="checkbox" id="partial_refund_rollback" name="partial_refund_rollback"/></div></td></tr>'
        );
        jQuery.ajaxPrefilter(function (options, originalOptions, jqXHR) {
            var originalParams = originalOptions.data || {};
            if (originalOptions.type === "POST" && originalParams.action === "woocommerce_refund_line_items") {
                var partial_refund_rollback = {partial_refund_rollback: jQuery(".wc-order-refund-items #partial_refund_rollback").is(":checked")};
                options.data = jQuery.param(jQuery.extend(originalParams, partial_refund_rollback));
            }
        });
        jQuery("#woocommerce-order-items").on('change keyup', '.wc-order-refund-items #refund_amount', function (event) {
            var value = (event.target.value || '0').replace('<?php echo wc_get_price_decimal_separator(); ?>', '.');
            if (!value.match('^[0-9%]*\.?[0-9%]*$')) {
                return;
            }
            if (value === '.' || value === '-') {
                value = 0;
            }
            var floatValue = parseFloat(value);
            var remainingRefundAmount = parseFloat('<?php echo $remaining_refund_amount; ?>');
            if (remainingRefundAmount - floatValue > 0) {
                jQuery(".wc-order-refund-items .partial_refund_rollback").slideDown('fast');
            } else {
                jQuery(".wc-order-refund-items .partial_refund_rollback").slideUp('fast');
            }
        });
    });
</script>