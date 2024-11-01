<tr class="cart-discount">
    <th><?php _e('Coupon', 'woocommerce'); ?>: <?php echo $display_name ?>:</th>
    <td>-<span class="woocommerce-Price-amount amount"><?php echo $discount ?></span>
        <a href="#" onclick="return false;" id="vcrf_remove_voucher" class="cart-remove-coupon" vcrf_coupon="<?php echo $code ?>"><?php esc_attr_e( 'Remove', 'woocommerce' ); ?></a></td>
</tr>