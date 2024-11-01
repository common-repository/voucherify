<?php

namespace Voucherify\Wordpress\Handlers\Promotions;


use Voucherify\Wordpress\Common\Models\Notification;
use Voucherify\Wordpress\Common\Models\Voucher;
use Voucherify\Wordpress\Common\Services\CartService;
use Voucherify\Wordpress\Common\Services\ValidationService;
use WC_Cart;

/**
 * Created by PhpStorm.
 * User: robert
 * Date: 06.02.2018
 * Time: 06:30
 */

if ( ! defined('ABSPATH')) {
    exit;
}

class PromotionCartService extends CartService
{
    /**
     * @var PromotionValidationService
     */
    private $validationService;

    public function __construct(ValidationService $validationService)
    {
        parent::__construct(new PromotionSessionService(), $validationService);
        $this->validationService = $validationService;
    }

    public function add(string $code)
    {
        $cart = WC()->cart;
        $cart->calculate_totals();
        $notification = $this->addForCart($cart, $code);
        $applyOnRenewals = get_option('voucherify_wc_subs_apply_on_renewals', 'yes');
        if ('yes' === $applyOnRenewals && $notification->isSuccess() && !empty($cart->recurring_carts)) {
            foreach($cart->recurring_carts as $recurringCart) {
                $this->addForCart($recurringCart, $code);
            }
        }

        return $notification;
    }

    public function addForCart(WC_Cart $cart, string $code) {
        $context          = $this->validationService->createValidationContextForCart($code, $cart);
        $validationResult = $this->validationService->validate($code, $context);

        $code = $validationResult->code;
        if ( ! $validationResult->valid) {
            $errorMessage = $validationResult->error->message ?? 'Promotion code is not valid.';

            return new Notification(false, __($errorMessage, 'voucherify'));
        } elseif ($this->session->isVoucherApplied($cart, $code)) {
            return new Notification(false, __('Promotion code already applied!', 'voucherify'));
        }
        $appliedVouchers = $this->session->getAppliedVouchers($cart);
        if (empty($appliedVouchers)) {
            $voucher = $this->createFromValidationResult($validationResult);
            $this->session->applyVoucher($cart, $voucher);

            return new Notification(true, __('Promotion code applied successfully.', 'voucherify'), $voucher);
        }
        if (current($appliedVouchers) instanceof PromotionVoucher) {
            return new Notification(false, __('Promotion code already applied!', 'voucherify'));
        }

        return new Notification(false, __('Coupon code already applied!', 'voucherify'));
    }

    protected function createFromValidationResult($validationResult)
    {
        return PromotionVoucher::createFromValidationResult($validationResult);
    }

    protected function createValidationContext(Voucher $voucher, WC_Cart $cart)
    {
        return $this->validationService->createValidationContextForCart($voucher->getCode(), $cart);
    }
}

