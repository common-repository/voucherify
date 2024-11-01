<?php
/**
 * Voucherify
 *
 * @author Robert Klodzinski <Robert.Klodzinski@gmail.com>
 *
 * Plugin Name: Voucherify
 * Plugin URI: https://wordpress.org/plugins/voucherify/
 * Description: Integrates Voucherify API with woocommerce replacing core coupons functionality
 * Version: 4.0.0
 * Author: rspective
 * Author URI: https://www.rspective.com/
 * Text Domain: voucherify
 * Domain Path: /i18n/languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define('VOUCHERIFY_BASEDIR', basename(__DIR__));
define('VOUCHERIFY_INDEX', __FILE__);

require_once "vendor/autoload.php";
require_once "src/functions.php";


use Voucherify\Wordpress\Voucherify;

if ( ! function_exists( 'voucherify' ) ) {
	/**
	 * @return Voucherify
	 */
	function voucherify() {
		static $voucherify;
		if ( null == $voucherify ) {
			$voucherify = new Voucherify();
		}

		return $voucherify;
	}
}

add_action( 'plugins_loaded', 'voucherify_maybe_initialize' );
function voucherify_maybe_initialize() {
    define( 'VOUCHERIFY_PLUGIN_VERSION', get_file_data(__FILE__, ['Version' => 'Version'])['Version'] );
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'voucherify_no_woocommerce_admin_notice' );
	} else {
		voucherify()->initialize();
	}

	if ( ! is_voucherify_enabled() ) {
		add_action( 'admin_notices', 'voucherify_disabled_admin_notice' );
	}
}
