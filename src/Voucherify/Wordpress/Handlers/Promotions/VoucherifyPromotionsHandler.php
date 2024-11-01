<?php

namespace Voucherify\Wordpress\Handlers\Promotions;

use Voucherify\Wordpress\ClientExtension;
use Voucherify\Wordpress\Common\Services\OrderVoucherService;
use Voucherify\Wordpress\Common\Services\RedemptionService;
use Voucherify\Wordpress\Handlers\DiscountVouchers\DiscountVoucherRestRouteHandler;

class VoucherifyPromotionsHandler
{
    /**
     * @var PromotionValidationService
     */
    private $validationService;
    /**
     * @var ValidPromotionsFetcher
     */
    private $validPromotionsFetcher;
    /**
     * @var PromotionFormRenderer
     */
    private $formRenderer;
    /**
     * @var PromotionFormListener
     */
    private $formHandler;
    /**
     * @var OrderVoucherService
     */
    private $orderService;
    /**
     * @var PromotionCartService
     */
    private $cartService;

    /**
     * @var AllPromotionsFetcher
     */
    private $allPromotionsFetcher;

    public function __construct(ClientExtension $voucherifyClient)
    {
        $redemptionService            = new RedemptionService($voucherifyClient->redemptions);
        $this->allPromotionsFetcher = new AllPromotionsFetcher($voucherifyClient->promotions->tiers);
        $this->validationService      = new PromotionValidationService($voucherifyClient->promotions);
        $this->cartService            = new PromotionCartService($this->validationService);
        $this->validPromotionsFetcher = new ValidPromotionsFetcher($voucherifyClient->promotions);
        $this->formRenderer           = new PromotionFormRenderer(
            $this->validPromotionsFetcher,
            $this->allPromotionsFetcher
        );
        $this->formHandler            = new PromotionFormListener($this->cartService);
        $this->orderService           = new PromotionOrderVoucherService($this->validationService,
            $redemptionService);
    }

    public function setupHooks()
    {
        add_action('wp_loaded', [$this->formHandler, 'handle'], 25);
        add_action('wp_ajax_vcrf_fetch_promotions', [
            $this->validPromotionsFetcher,
            'handle_ajax_vcrf_fetch_promotions'
        ], 10);
        add_action('wp_ajax_nopriv_vcrf_fetch_promotions', [
            $this->validPromotionsFetcher,
            'handle_ajax_vcrf_fetch_promotions'
        ], 10);


        add_action('woocommerce_cart_actions', [
            $this->formRenderer,
            'render_promotion_from'
        ], 9);

        add_action('woocommerce_cart_totals_before_order_total', [
            $this->formRenderer,
            'renderCartPromotion'
        ], 10);
        add_action('woocommerce_review_order_before_order_total', [
            $this->formRenderer,
            'renderCartPromotion'
        ], 10);
        add_action('wp_ajax_nopriv_vcrf_remove_promotion', [
            $this->formHandler,
            'ajaxRemovePromotion'
        ]);
        add_action('wp_ajax_vcrf_remove_promotion', [$this->formHandler, 'ajaxRemovePromotion']);

        add_action('rest_api_init', [$this, 'register_rest_apis']);
    }

    public function register_rest_apis() {
        WC()->frontend_includes();
        WC()->initialize_cart();
        WC()->cart->calculate_totals();

        $promotionsRestRouteHanlder = new PromotionRestRouteHandler(
            $this->validPromotionsFetcher,
            $this->allPromotionsFetcher,
            $this->cartService
        );

        register_rest_route( 'voucherify/v1', '/promotions/available', array(
            'methods'  => 'GET', // HTTP method (GET, POST, etc.)
            'callback' => [$promotionsRestRouteHanlder, 'listAvailablePromotions'],
			'permission_callback' => '__return_true'
        ));

        register_rest_route( 'voucherify/v1', '/promotions/apply', array(
            'methods'  => 'POST', // HTTP method (GET, POST, etc.)
            'callback' => [$promotionsRestRouteHanlder, 'apply'],
			'permission_callback' => '__return_true'
        ));

        register_rest_route( 'voucherify/v1', '/promotions', array(
            'methods'  => 'GET', // HTTP method (GET, POST, etc.)
            'callback' => [$promotionsRestRouteHanlder, 'listAppliedPromotions'],
			'permission_callback' => '__return_true'
        ));

        register_rest_route( 'voucherify/v1', '/promotions/remove/(?P<code>[^/]+)', array(
            'methods'  => 'DELETE', // HTTP method (GET, POST, etc.)
            'callback' => [$promotionsRestRouteHanlder, 'removePromotion'],
			'permission_callback' => '__return_true'
        ));
    }

    /**
     * @return PromotionCartService
     */
    public function getCartService(): PromotionCartService
    {
        return $this->cartService;
    }

    /**
     * @return PromotionOrderVoucherService
     */
    public function getOrderService()
    {
        return $this->orderService;
    }


}
