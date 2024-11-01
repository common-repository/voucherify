<div class="wc-used-coupons wc-used-vcrf-coupons">
    <ul class="wc_coupon_list">
        <li><strong><?php esc_attr_e( 'Coupon(s)', 'woocommerce' ); ?></strong></li>
		<?php foreach ( $vouchers as /** @var Voucher $voucher */ $voucher ): ?>
            <li class="code editable">
                <div class="tips">
                    <span><?php echo $voucher->getCode() ?></span>
                </div>
                <div class="remove-coupon" id="vcrf_admin_remove_voucher" vcrf_code="<?php echo $voucher->getCode() ?>"></div>
            </li>
		<?php endforeach; ?>
    </ul>
</div>
<script>
    jQuery(document.body).on('click', '#vcrf_admin_remove_voucher', function (event) {
        let data = {
            'action': 'vcrf_admin_remove_voucher',
            'code': jQuery(event.currentTarget).attr('vcrf_code'),
            'order_id': woocommerce_admin_meta_boxes.post_id
        };

        jQuery.post('<?php echo $vcrf_admin_url ?>', data, function (response) {
            jQuery('#woocommerce-order-items').trigger('wc_order_items_reload');
        });
    });
</script>