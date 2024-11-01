<?php
/**
 * Created by PhpStorm.
 * User: robert
 * Date: 06.02.2018
 * Time: 06:34
 */
?>
<noscript>
	<style>
		.vcrf_promotion_dropdown .vcrf_form_wrapper,
		.vcrf_promotion_dropdown .vcrf_form_wrapper .vcrf_available_promotions .vcrf_banners_list,
		.vcrf_promotion_dropdown .vcrf_form_wrapper .vcrf_available_promotions {
			display: block;
		}

		.vcrf_promotion_dropdown .vcrf_form_wrapper [name=vcrf_apply_promotion],
		.vcrf_promotion_dropdown .vcrf_form_wrapper .vcrf_promotion_code {
			display: inline-block;
		}

		.vcrf_promotion_dropdown .vcrf_form_wrapper .vcrf_available_promotions .hide_banners,
		.vcrf_promotion_dropdown .vcrf_form_wrapper .vcrf_available_promotions .show_banners {
			display: none;
		}

		.woocommerce-checkout .vcrf_promotion_dropdown .vcrf_form_wrapper {
			display: none;
		}
	</style>
</noscript>
<?php do_action( 'voucherify_before_promotion_form' ); ?>
<div class="vcrf_form_wrapper checkout_coupon cart-collaterals">
	<div class="vcrf_promotion_dropdown_wrapper">
		<select id="vcrf_promotion_code" name="vcrf_promotion_code" class="vcrf_promotion_code">
            <option value=""><?php _e( '-- Select promotion --', 'voucherify' ); ?></option>
            <?php
			if ( ! empty( $available_promotions ) ) :
				foreach ( $available_promotions as $promotion ) :
					?>
                    <option value="<?php echo $promotion->id; ?>"><?php echo $promotion->banner; ?></option><?php
				endforeach;
			endif; ?>
		</select>
		<input id="vcrf_apply_promotion" type="submit" name="vcrf_apply_promotion" class="button vcrf_apply_promotion" value="<?php _e( 'Apply promotion', 'voucherify' ); ?>" />
	</div><div style="clear: both;"></div><?php
		if (!empty($promotions)) :
	?><div class="vcrf_available_promotions">
		<h2><?php _e( 'All available promotions', 'voucherify' ); ?></h2>
		<a href="#" class="show_banners"><?php _e( '(show)', 'voucherify' ); ?></a>
		<a href="#" class="hide_banners"><?php _e( '(hide)', 'voucherify' ); ?></a>
		<ul class="vcrf_banners_list"><?php foreach ( $promotions as $promotion ) :
				?><li><?php echo $promotion; ?></li><?php
			endforeach;
		?></ul>
	</div><?php endif; ?>
</div>
<?php do_action( 'voucherify_after_promotion_form' ); ?>
