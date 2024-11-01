<?php
defined( 'ABSPATH' ) || exit;
?>

<div id="vouchers-form" class="coupon">
    <label for="coupon_code"><?php esc_attr_e( 'Coupon:', 'voucherify' ); ?></label>
    <input type="text" name="coupon_code" class="input-text" id="vcrf_coupon_code" value="" placeholder="Coupon code">
    <button type="button" class="button" name="apply_coupon" id="vcrf_apply_coupon" value="Apply coupon">Apply coupon
    </button>
</div>
<div id="gift-cards-form" class="coupon" style="display: none;">
<!--<div id="gift-cards-form" class="coupon">-->
    <span style="text-align: left">
        <div>Gift card: <span id="vcrf_gift_coupon_code"></span></div>
        <div>Available balance: <span id="gift-card-balance"></span></div>
    </span>
    <input type="text" name="gift_card_amount" class="input-text" id="vcrf_gift_coupon_amount" value="" placeholder="0">
    <button type="button" class="button" name="apply_coupon" id="vcrf_apply_gift_coupon">
        Apply gift card
    </button>
    <button type="button" class="button" name="apply_coupon" id="vcrf_cancel_gift_coupon">
        Cancel
    </button>
</div>