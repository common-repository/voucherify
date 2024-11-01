<?php

namespace Voucherify\Wordpress\Handlers\DiscountVouchers;


use Voucherify\Wordpress\Common\Models\Notification;
use Voucherify\Wordpress\Common\Models\Voucher;
use Voucherify\Wordpress\Common\Services\CartService;
use WC_Cart;

/**
 * Created by PhpStorm.
 * User: robert
 * Date: 06.02.2018
 * Time: 06:30
 */
class DiscountVoucherCartService extends CartService
{

    /**
     * @var DiscountVoucherValidationService
     */
    private $validationService;

    public function __construct(DiscountVoucherValidationService $validationService)
    {
        parent::__construct(new DiscountVoucherSessionService(), $validationService);
        $this->validationService = $validationService;
    }

    public function add(string $code)
    {
        define('VCRF_ADDING_VOUCHER', true);
        $cart = WC()->cart;
        $cart->calculate_totals();
        $notification = $this->addForCart($cart, $code);
        $applyOnRenewals = get_option('voucherify_wc_subs_apply_on_renewals', 'yes');
        if (!$this->isUnitType($cart, $code) && 'yes' === $applyOnRenewals && $notification->isSuccess() && !empty($cart->recurring_carts)) {
            foreach($cart->recurring_carts as $recurringCart) {
                $this->addForCart($recurringCart, $code);
            }
        }

        return $notification;
    }

    private function isUnitType(WC_Cart $cart, $code) {
        $appliedVouchers = $this->session->getAppliedVouchers($cart);
        if (!empty($appliedVouchers[$code]) && $appliedVouchers[$code] instanceof DiscountVoucher) {
            return $appliedVouchers[$code]->getDiscountType() === 'UNIT';
        }

        return false;
    }

    public function addForCart(WC_Cart $cart, string $code)
    {
        $context           = $this->validationService->createValidationContextForCart($code, $cart);
        $validationResult = $this->validationService->validate($code, $context);

        $code = $validationResult->code;
        if (!$this->isValidationSuccessful($validationResult)) {
            $errorMessage = $validationResult->error->message ?? 'Coupon code %s is not valid.';

            return new Notification(false, sprintf(__($errorMessage, 'voucherify'), $code));
        } elseif ($this->session->isVoucherApplied($cart, $code)) {
            return new Notification(false, sprintf( __('Coupon code %s already applied!', 'voucherify'), $code));
        }
        $appliedVouchers = $this->session->getAppliedVouchers($cart);
        if (empty($appliedVouchers)) {
            $voucher = $this->createFromValidationResult($validationResult);
            $this->session->applyVoucher($cart, $voucher);

            return new Notification(true, sprintf(__('Coupon code %s applied successfully.', 'voucherify'), $code), $voucher);
        }

        if (current($appliedVouchers) instanceof DiscountVoucher) {
            return new Notification(false, sprintf(__('Coupon code %s already applied!', 'voucherify'), $code));
        }

        return new Notification(false, __('Promotion already applied!', 'voucherify'));
    }

    private function isValidationSuccessful($validationResult) {
        if (!$validationResult->valid || (!isset($validationResult->discount) && !isset($validationResult->gift))) {
            return false;
        }

        $discountVoucher = $this->createFromValidationResult($validationResult);
        if ($discountVoucher->getDiscountType() != 'UNIT') {
            return true;
        }

        foreach($discountVoucher->getDiscountedOrderItems() as $item) {
            if ($item->getWCProduct()->is_type(['subscription', 'subscription_variation', 'variable-subscription'])) {
                return false;
            }
        }

        return true;
    }

    protected function createFromValidationResult($validationResult)
    {
        return DiscountVoucher::createFromValidationResult($validationResult);
    }

    protected function createValidationContext(Voucher $voucher, WC_Cart $cart)
    {
        return $this->validationService->createValidationContextForCart($voucher->getCode(), $cart);
    }
}

