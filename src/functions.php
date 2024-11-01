<?php

/**
 * Created by PhpStorm.
 * User: robert
 * Date: 08.02.2018
 * Time: 05:17
 */

use Voucherify\Wordpress\Common\Helpers\CartDecorator;
use Voucherify\Wordpress\Common\Helpers\CustomerDecorator;
use Voucherify\Wordpress\Handlers\GiftCards\GiftCardsTotalsCalculator;

/**
 * Admin notice that is displayed when WooCommerce plugin is either not installed or not activated.
 */
function voucherify_no_woocommerce_admin_notice() {
	?>
    <div class="notice notice-warning">
        <p><?php
			_e( '<strong>Voucherify</strong> requires <strong>WooCommerce</strong> plugin enabled.', 'voucherify' );
			?></p>
    </div>
	<?php
}

/**
 * Admin notice that is displayed when voucherify is disabled (from voucherify's options, not deactivated).
 */
function voucherify_disabled_admin_notice() {
	?>
    <div class="notice notice-error">
        <p><?php _e( '<strong>Voucherify</strong> is <strong>disabled</strong>. You are currently using <strong>core WooCommerce coupons</strong>.', 'voucherify' ); ?></p>
    </div>
	<?php
}

/**
 * Filter check whether or not validation should be disabled.
 *
 * Currently it disables validation of vouchers in admin panel.
 *
 * @return boolean whether or not validation should be disabled
 */
function is_validation_blocked() {
	$block_vouchers = ( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX
	                                      && ! ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] === "woocommerce_refund_line_items" ) ) )
	                  || did_action( 'woocommerce_api_request' );

	return apply_filters( 'voucherify_validation_service_block_validation', $block_vouchers );
}

function is_voucherify_rollback_enabled() {
	$voucherify_rollback_enabled = get_option( 'voucherify_rollback_enabled', 'yes' ) === 'yes';

	return apply_filters( 'voucherify_rollback_enabled', $voucherify_rollback_enabled );
}

function is_voucherify_enabled()
{
    $voucherify_enabled = get_option('voucherify_enabled', 'yes') === 'yes';

    return apply_filters('voucherify_enabled', $voucherify_enabled);
}

function createVcrfProductSourceId($wcProductId)
{
    return $wcProductId;
}

function createVcrfVariantSourceId($wcVariantId)
{
    return $wcVariantId;
}

function createVcrfCustomerSourceId($wcCustomerId)
{
    return $wcCustomerId;
}

function createVcrfOrderSourceId($wcOrderId)
{
    return $wcOrderId;
}

function createVcrfGuestCustomerSourceId($wcOrderId)
{
    return "guest-{$wcOrderId}";
}

if ( ! function_exists( 'vcrf_get_admin_order' ) ) {
	function vcrf_get_admin_order() {
		global $post_id;

        $order = null;

		if ( ! is_null( $post_id ) ) {
			$order = wc_get_order( $post_id );
		} elseif ( ! empty( $_POST['order_id'] ) ) {
			$order = wc_get_order( $_POST['order_id'] );
		} elseif ( vcrf_is_rest_request() ) {
			global $vcrf_current_order;
            $order = $vcrf_current_order;
		}

		return $order;
	}
}

if ( ! function_exists( 'vcrf_is_rest_request' ) ) {
	/**
	 * Checks if the request is made by the REST API
	 *
	 * @return bool
	 */
	function vcrf_is_rest_request() {
		return defined( 'REST_REQUEST' );
	}
}

if ( ! function_exists( 'vcrf_is_wc_object_available' ) ) {
	/**
	 * Checks if the default WC() object is available
	 *
	 * @return bool
	 */
	function vcrf_is_wc_object_available() {
		if ( (defined( 'VCRF_FETCH_PROMOTIONS' ) && VCRF_FETCH_PROMOTIONS)
             || (defined('VCRF_APPLY_VOUCHER') && VCRF_APPLY_VOUCHER) ) {
			return true;
		}

		if ( vcrf_is_rest_request() || is_admin() || isset( $GLOBALS['wp']->query_vars['wc-api'] ) ) {
			return false;
		}

		return true;
	}
}

if ( ! function_exists( 'vcrf_include_partial' ) ) {
	function vcrf_include_partial( $filename, $variables = [] ) {
		extract( $variables );
		include( dirname( __DIR__, 1 ) . DIRECTORY_SEPARATOR . "partials" . DIRECTORY_SEPARATOR . $filename );
	}
}

add_action("add_option_voucherify_lock_ttl2", 'test_upsert_option');
add_filter( 'wp_redirect', 'vcrf_maybe_force_resynchronization' );
function vcrf_maybe_force_resynchronization($location) {
    $resynchronizationRequested = $_REQUEST['voucherify_resynchronize'] ?? 'no';
    if ($resynchronizationRequested === 'yes') {
        update_option('vcrf_resynchronize', 'yes');
    } elseif (isset($_COOKIE['resynchronize'])) {
        delete_option('vcrf_resynchronize');
    }

    return $location;
}
