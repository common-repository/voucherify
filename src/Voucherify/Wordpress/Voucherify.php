<?php

namespace Voucherify\Wordpress;

use Voucherify\Wordpress\Blocks\BlockifiedCart;
use Voucherify\Wordpress\Common\Services\ResponseCacheService;
use Voucherify\Wordpress\Common\Services\CartShippingService;
use Voucherify\Wordpress\Handlers\Admin\AdminSettings;
use Voucherify\Wordpress\Handlers\Admin\VoucherifyAdminOrderHandler;
use Voucherify\Wordpress\Handlers\CancelOrderHandler;
use Voucherify\Wordpress\Handlers\CartTotalsHandler;
use Voucherify\Wordpress\Handlers\OrderTotalsHandler;
use Voucherify\Wordpress\Handlers\RefundOrderHandler;
use Voucherify\Wordpress\Synchronization\Handlers\SynchronizationHandler;
use Voucherify\Wordpress\Handlers\UnlockOrderHandler;
use Voucherify\Wordpress\Handlers\VoucherHandlersFacade;

/**
 * Created by PhpStorm.
 * User: robert
 * Date: 20.01.18
 * Time: 06:50
 */

if ( ! defined('ABSPATH')) {
    exit;
}


class Voucherify
{
    const VCRF_DEFAULT_API_ENDPOINT = 'https://api.voucherify.io';
    const VCRF_DEFAULT_API_ENDPOINT_VERSION = 'v1';
    const VCRF_ENDPOINTS_INFO = 'https://docs.voucherify.io/docs/api-endpoints';

    /** @var ClientExtension */
    private $voucherifyClient;
    /** @var ResponseCacheService */
    private $responseCacheService;
    /** @var VoucherHandlersFacade */
    private $vouchersHandler;
    /** @var UnlockOrderHandler */
    private $unlockOrderHandler;
    /** @var OrderTotalsHandler */
    private $orderTotalsHandler;
    /** @var CartTotalsHandler */
    private $cartTotalsHandler;
    /** @var RefundOrderHandler */
    private $refundOrderHandler;
    /** @var VoucherifyAdminOrderHandler */
    private $adminOrderHandler;
    /** @var SynchronizationHandler */
    private $synchronizationHandler;

    /**
     * Initializes all the dependencies required by this class.
     */
    public function __construct()
    {
        $this->voucherifyClient = new ClientExtension(get_option('voucherify_app_id'),
            get_option('voucherify_app_secret_key'), $this->get_api_url());

        new AdminSettings();
        $this->responseCacheService = new ResponseCacheService();
        $this->vouchersHandler = new VoucherHandlersFacade($this->voucherifyClient);
        $this->cartTotalsHandler = new CartTotalsHandler($this->vouchersHandler, new CartShippingService());
        $this->orderTotalsHandler = new OrderTotalsHandler($this->vouchersHandler);
        $this->refundOrderHandler = new RefundOrderHandler($this->vouchersHandler);
        $this->cancelOrderHandler = new CancelOrderHandler($this->vouchersHandler);
        $this->unlockOrderHandler = new UnlockOrderHandler($this->vouchersHandler);
        $this->adminOrderHandler = new VoucherifyAdminOrderHandler($this->vouchersHandler);
        $this->synchronizationHandler = new SynchronizationHandler($this->voucherifyClient);
    }

    /**
     * Performs plugin initialization
     * like registering hooks (actions, filters), etc.
     *
     * If it's required to shut down the plugin and revert all changes made
     * by this method one should use {@link Voucherify::shut_down()}
     */
    public function initialize()
    {
        load_plugin_textdomain('voucherify', false, basename(dirname(__FILE__, 2)) . '/i18n/languages');

        if (is_voucherify_enabled()) {

            do_action('voucherify_initialize');

            /**
             * By default WC coupon's code is case insensitive.
             * Since the voucher's code is case sesitive, it was required
             * to disable a filter that makes it all lowercase.
             */
            remove_filter('woocommerce_coupon_code', 'wc_strtolower');

            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_styles']);
            add_action('wp_enqueue_scripts', [$this, 'enqueue_cart_and_checkout_styles']);
            add_filter('woocommerce_coupons_enabled', [$this, 'coupons_enabled'], 20);

            add_action('vcrf_voucher_removed', [
                $this->voucherifyClient,
                'release_session_lock'
            ], 10, 2);

            add_action('vcrf_voucher_removed', [
                $this->responseCacheService,
                'removeResponseFromCache'
            ], 10, 1);

            $this->vouchersHandler->setupHooks();
            $this->adminOrderHandler->setupHooks();
            $this->orderTotalsHandler->setupHooks();
            $this->cartTotalsHandler->setupHooks();
            $this->refundOrderHandler->setupHooks();
            $this->cancelOrderHandler->setupHooks();
            $this->unlockOrderHandler->setupHooks();
            $this->synchronizationHandler->setupHooks();

            BlockifiedCart::setupHooks();
        }
    }

    /**
     * In case it's required to shut down the plugin functionality programmatically
     * it can be done with this method.
     *
     * This method will deregister all the hooks (actions, filters) and revert other changes,
     * configurations, etc performed by {@link Voucherify::initalize()}
     */
    public function shut_down()
    {
        if (is_voucherify_enabled()) {
            do_action('voucherify_shut_down');
        }
    }

    function coupons_enabled()
    {
        return false;
    }

    public function enqueue_admin_styles()
    {
        wp_enqueue_style('voucherify-admin', plugins_url("/assets/css/admin.css", dirname(__FILE__, 3)));
    }

    function enqueue_cart_and_checkout_styles()
    {
        $style_filename = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? 'voucherify.css' : 'voucherify.min.css';
        wp_enqueue_style('voucherify-cart-styles', plugins_url("/assets/css/$style_filename", dirname(__FILE__, 3)));
        wp_enqueue_script('vcrf-checkout-script', plugins_url("/assets/js/checkout.js", dirname(__FILE__, 3)));
        wp_localize_script('vcrf-checkout-script', 'vcrf_checkout', ['admin_url' => admin_url('admin-ajax.php')]);
    }

    public function get_api_endpoint()
    {
        return get_option('voucherify_api_endpoint', static::VCRF_DEFAULT_API_ENDPOINT);
    }

    public function get_api_version()
    {
        return static::VCRF_DEFAULT_API_ENDPOINT_VERSION;
    }

    public function get_api_url()
    {
        return rtrim($this->get_api_endpoint(), '/');
    }

    public function getVoucherifyApiClient()
    {
        return $this->voucherifyClient;
    }
}

