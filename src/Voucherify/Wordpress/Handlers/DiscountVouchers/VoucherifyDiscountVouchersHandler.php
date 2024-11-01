<?php

namespace Voucherify\Wordpress\Handlers\DiscountVouchers;

use Voucherify\Wordpress\ClientExtension;
use Voucherify\Wordpress\Common\Services\InfoService;
use Voucherify\Wordpress\Common\Services\RedemptionService;

class VoucherifyDiscountVouchersHandler
{

    /**
     * @var DiscountVoucherValidationService
     */
    private $validationService;
    /**
     * @var DiscountVoucherFormsListener
     */
    private $discountVoucherFormsListener;
    /**
     * @var DiscountVoucherFormsRenderer
     */
    private $discountVoucherFormsRenderer;
    /**
     * @var DiscountVoucherOrderVoucherService
     */
    private $orderService;
    /**
     * @var DiscountVoucherCartService
     */
    private $cartService;

    /** @var InfoService */
    private $infoService;

    public function __construct(ClientExtension $voucherifyClient)
    {
        $this->infoService                  = new InfoService($voucherifyClient->vouchers);
        $this->validationService            = new DiscountVoucherValidationService($voucherifyClient->validations);
        $this->cartService                  = new DiscountVoucherCartService($this->validationService);
        $this->orderService                 = new DiscountVoucherOrderVoucherService(
            new DiscountVoucherOrderVoucherMetaService(),
            $this->validationService,
            new RedemptionService($voucherifyClient->redemptions)
        );
        $this->discountVoucherFormsListener = new DiscountVoucherFormsListener($this->infoService, $this->cartService);
        $this->discountVoucherFormsRenderer = new DiscountVoucherFormsRenderer();
    }

    public function setupHooks()
    {
        add_action('woocommerce_before_checkout_form', [
            $this->discountVoucherFormsRenderer,
            'render_checkout_coupon_form_with_wrapper'
        ], 10);
        add_action('woocommerce_cart_actions', [
            $this->discountVoucherFormsRenderer,
            'render_cart_coupon_form'
        ], 10);
        add_action('woocommerce_cart_totals_before_order_total', [
            $this->discountVoucherFormsRenderer,
            'renderCartDiscountVoucher'
        ], 10);
        add_action('woocommerce_review_order_before_order_total', [
            $this->discountVoucherFormsRenderer,
            'renderCartDiscountVoucher'
        ], 10);
        add_action('wp_ajax_nopriv_vcrf_update_checkout_form_voucher', [
            $this->discountVoucherFormsRenderer,
            'ajax_update_checkout_form_voucher'
        ]);
        add_action('wp_ajax_vcrf_update_checkout_form_voucher', [
            $this->discountVoucherFormsRenderer,
            'ajax_update_checkout_form_voucher'
        ]);
        add_action('wp_ajax_nopriv_vcrf_add_voucher', [$this->discountVoucherFormsListener, 'ajax_add_voucher']);
        add_action('wp_ajax_vcrf_add_voucher', [$this->discountVoucherFormsListener, 'ajax_add_voucher']);
        add_action('wp_ajax_nopriv_vcrf_remove_voucher', [
            $this->discountVoucherFormsListener,
            'ajax_remove_voucher'
        ]);
        add_action('wp_ajax_vcrf_remove_voucher', [$this->discountVoucherFormsListener, 'ajax_remove_voucher']);

        add_action('rest_api_init', [$this, 'register_rest_apis']);
    }

    public function register_rest_apis() {
        WC()->frontend_includes();
        WC()->initialize_cart();
        WC()->cart->calculate_totals();

        $discountVoucherRestRouteHandler = new DiscountVoucherRestRouteHandler($this->infoService, $this->cartService);

        register_rest_route( 'voucherify/v1', '/discount-vouchers/apply', array(
            'methods'  => 'POST', // HTTP method (GET, POST, etc.)
            'callback' => [$discountVoucherRestRouteHandler, 'maybeAddVoucher'],
			'permission_callback' => '__return_true'
        ));

        register_rest_route( 'voucherify/v1', '/discount-vouchers', array(
            'methods'  => 'GET', // HTTP method (GET, POST, etc.)
            'callback' => [$discountVoucherRestRouteHandler, 'listVouchers'],
			'permission_callback' => '__return_true'
        ));

        register_rest_route( 'voucherify/v1', '/discount-vouchers/remove/(?P<code>[^/]+)', array(
            'methods'  => ['GET', 'POST', 'DELETE'], // HTTP method (GET, POST, etc.)
            'callback' => [$discountVoucherRestRouteHandler, 'removeVoucher'],
			'permission_callback' => '__return_true'
        ));
    }

    /**
     * @return DiscountVoucherCartService
     */
    public function getCartService()
    {
        return $this->cartService;
    }

    /**
     * @return DiscountVoucherOrderVoucherService
     */
    public function getOrderService()
    {
        return $this->orderService;
    }
}
