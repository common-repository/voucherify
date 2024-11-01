<?php

namespace Voucherify\Wordpress\Handlers\DiscountVouchers;


use Voucherify\Wordpress\Common\Services\SessionService;

/**
 * Created by PhpStorm.
 * User: robert
 * Date: 06.02.2018
 * Time: 06:30
 */
class DiscountVoucherFormsRenderer
{

    /** @var DiscountVoucherSessionService $session */
    private $session;

    public function __construct()
    {
        $this->session = new DiscountVoucherSessionService();
    }

    function render_cart_coupon_form()
    {
        vcrf_include_partial("cart-form-coupon.php");
    }

    function render_cart_gift_form()
    {
        vcrf_include_partial("cart-form-gift-coupon.php");
    }

    function render_checkout_coupon_form_with_wrapper()
    {
        echo '<div id="vcrf_checkout_coupon_form_container">';
        $this->render_checkout_coupon_form();
        echo '</div>';
    }

    function render_checkout_coupon_form()
    {
        if ( ! $this->session->getAppliedVouchers(WC()->cart)) {
            vcrf_include_partial("checkout-form-coupon.php");
        }
    }

    function renderCartDiscountVoucher()
    {
        $cart = WC()->cart;
        $vouchers = $this->session->getAppliedVouchers($cart);
        foreach ($vouchers as $voucher) {
            $code            = $voucher->getCode();
            $discount_totals = SessionService::getDiscounts($cart);
            $discount_amount = $discount_totals['coupon_discounts'][$code];
            if ( ! $cart->display_prices_including_tax()) {
                $discount_amount -= $discount_totals['coupon_discounts_tax'][$code] ?? 0;
            }
            vcrf_include_partial('cart-discount-voucher.php', [
                'display_name' => $voucher->getDisplayName(),
                'code'         => $voucher->getCode(),
                'discount'     => wc_price($discount_amount)
            ]);
        }
    }

    public function ajax_update_checkout_form_voucher()
    {
        ob_start();
        $this->render_checkout_coupon_form();
        $buffer = ob_get_clean();
        wp_send_json_success($buffer);
    }
}

