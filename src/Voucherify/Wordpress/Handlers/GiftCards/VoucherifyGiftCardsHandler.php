<?php

namespace Voucherify\Wordpress\Handlers\GiftCards;

use Voucherify\Wordpress\ClientExtension;
use Voucherify\Wordpress\Common\Services\InfoService;
use Voucherify\Wordpress\Common\Services\RedemptionService;

class VoucherifyGiftCardsHandler
{
    /**
     * @var GiftCardFormListener
     */
    private $formListener;
    /**
     * @var GiftCardFormRenderer
     */
    private $formRenderer;
    /**
     * @var GiftCardOrderVoucherService
     */
    private $orderService;
    /**
     * @var GiftCardCartService
     */
    private $cartService;
    /**
     * @var GiftVouchersValidationService
     */
    private $validationService;

    /**
     * @var InfoService
     */
    private $infoService;

    public function __construct(ClientExtension $voucherifyClient)
    {
        $this->infoService       = new InfoService($voucherifyClient);
        $this->validationService = new GiftVouchersValidationService($voucherifyClient->validations);
        $this->cartService       = new GiftCardCartService($this->validationService);
        $this->formRenderer      = new GiftCardFormRenderer();
        $this->formListener      = new GiftCardFormListener($this->infoService, $this->cartService, $this->validationService);
        $redemptionService       = new RedemptionService($voucherifyClient->redemptions);
        $this->orderService      = new GiftCardOrderVoucherService($this->validationService, $redemptionService);
    }

    public function setupHooks()
    {
        add_filter( 'wcs_cart_totals_order_total_html', [$this->formRenderer, 'displayRecurringTotalWithoutDiscount'], 10, 2);
        add_filter( 'woocommerce_cart_totals_order_total_html', [$this->formRenderer, 'displayCartTotalWithoutDiscount']);
        add_action('woocommerce_cart_totals_after_order_total', [$this->formRenderer, 'displayTotalToPay'], -90);

        add_action(
            'woocommerce_cart_totals_after_order_total',
            [$this->formRenderer, 'displayRecurringGiftCartPayment'],
            12
        );
        add_action(
            'woocommerce_review_order_after_order_total',
            [$this->formRenderer, 'displayRecurringGiftCartPayment'],
            12
        );

        add_action(
            'woocommerce_cart_totals_after_order_total',
            [$this->formRenderer, 'displayRecurringTotalToPay'],
            13
        );
        add_action(
            'woocommerce_review_order_after_order_total',
            [$this->formRenderer, 'displayRecurringTotalToPay'],
            13
        );

        add_action('woocommerce_cart_totals_after_order_total', [$this, 'cartTotalsAfterOrderTotal'], 11);
        add_action('woocommerce_review_order_after_order_total', [$this, 'cartTotalsAfterOrderTotal'], 11);


        add_filter('vcrf_gift_card_details', [$this->formListener, 'getGiftCardDetails'], 10, 2);

        add_action(
            'woocommerce_cart_totals_after_order_total',
            [$this->formRenderer, 'displayGiftVoucherLineItems'],
            -100
        );
        add_action(
            'woocommerce_review_order_after_order_total',
            [$this->formRenderer, 'displayGiftVoucherLineItems'],
            -100
        );

        add_action('woocommerce_review_order_after_order_total', [$this->formRenderer, 'displayTotalToPay'], -90);

        add_action('woocommerce_admin_order_totals_after_tax', [$this->formRenderer, 'displayAdminTotals']);

        add_action('woocommerce_get_order_item_totals', [$this->formRenderer, 'getOrderItemTotals'], 10, 3);

        add_action('wp_ajax_nopriv_vcrf_add_gift_voucher', [$this->formListener, 'ajaxAddGiftCard']);
        add_action('wp_ajax_vcrf_add_gift_voucher', [$this->formListener, 'ajaxAddGiftCard']);

        add_action('wp_ajax_vcrf_remove_gift_coupon', [$this->formListener, 'ajaxRemoveGiftCard']);
        add_action('wp_ajax_nopriv_vcrf_remove_gift_coupon', [$this->formListener, 'ajaxRemoveGiftCard']);

        add_action('rest_api_init', [$this, 'register_rest_apis']);
    }

    public function register_rest_apis() {
        WC()->frontend_includes();
        WC()->initialize_cart();
        WC()->cart->calculate_totals();

        $giftCardRestRouteHandler = new GiftCardsRestRouteHandler($this->infoService, $this->cartService);

        register_rest_route( 'voucherify/v1', '/gift-cards/apply', array(
            'methods'  => 'POST', // HTTP method (GET, POST, etc.)
            'callback' => [$giftCardRestRouteHandler, 'applyGiftCard'],
			'permission_callback' => '__return_true'
        ));

        register_rest_route( 'voucherify/v1', '/gift-cards', array(
            'methods'  => 'GET', // HTTP method (GET, POST, etc.)
            'callback' => [$giftCardRestRouteHandler, 'listGiftCards'],
			'permission_callback' => '__return_true'
        ));

        register_rest_route( 'voucherify/v1', '/gift-cards/remove/(?P<code>[^/]+)', array(
            'methods'  => ['GET', 'POST', 'DELETE'], // HTTP method (GET, POST, etc.)
            'callback' => [$giftCardRestRouteHandler, 'removeGiftCard'],
			'permission_callback' => '__return_true'
        ));
    }

    public function cartTotalsBeforeOrderTotal()
    {
        if (empty($this->cartService->getAppliedVouchers(WC()->cart))) {
            return;
        }
        add_filter('woocommerce_cart_get_total', [$this->formRenderer, 'getCartTotalWithGiftCard'], 1000);
    }

    public function cartTotalsAfterOrderTotal()
    {
        remove_filter('woocommerce_cart_get_total', [$this->formRenderer, 'getCartTotalWithGiftCard'], 1000);
    }

    /**
     * @return GiftCardOrderVoucherService
     */
    public function getOrderService()
    {
        return $this->orderService;
    }

    /**
     * @return GiftCardCartService
     */
    public function getCartService(): GiftCardCartService
    {
        return $this->cartService;
    }
}
