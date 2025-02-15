<div class="woocommerce-form-coupon-toggle">
	<?php wc_print_notice( apply_filters( 'woocommerce_checkout_coupon_message', esc_html__( 'Have a coupon?', 'voucherify' ) . ' <a href="#" class="showcoupon">' . esc_html__( 'Click here to enter your code', 'woocommerce' ) . '</a>' ), 'notice' ); ?>
</div>

<form class="checkout_coupon woocommerce-form-coupon">

    <p><?php esc_html_e( 'If you have a coupon code, please apply it below.', 'voucherify' ); ?></p>

    <p class="form-row form-row-first">
        <input type="text" name="coupon_code" class="input-text"
               placeholder="<?php esc_attr_e( 'Coupon code', 'voucherify' ); ?>" id="vcrf_coupon_code"
               value=""/>
    </p>

    <p class="form-row form-row-last">
        <button type="button" class="button" name="" id="vcrf_apply_coupon"
                value="<?php esc_attr_e( 'Apply coupon', 'woocommerce' ); ?>"><?php esc_html_e( 'Apply coupon', 'woocommerce' ); ?></button>
    </p>

    <div class="clear"></div>
</form>
