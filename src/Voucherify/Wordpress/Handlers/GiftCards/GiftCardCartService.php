<?php

namespace Voucherify\Wordpress\Handlers\GiftCards;

use Exception;
use Voucherify\Wordpress\Common\Models\Notification;
use Voucherify\Wordpress\Common\Models\Voucher;
use Voucherify\Wordpress\Common\Services\CartService;
use WC_Cart;

class GiftCardCartService extends CartService
{

    /**
     * @var GiftVouchersValidationService
     */
    private $validationService;

    public function __construct(GiftVouchersValidationService $validationService)
    {
        parent::__construct(new GiftCardsSessionService(), $validationService);
        $this->validationService = $validationService;
    }

    function add(string $code, int $giftCardAppliedAmount): Notification
    {
        $cart = WC()->cart;
        $cart->calculate_totals();
        $notification = $this->addForCart($cart, $code, $giftCardAppliedAmount);
        $applyOnRenewals = get_option('voucherify_wc_subs_apply_on_renewals', 'yes');
        if ('yes' === $applyOnRenewals && $notification->isSuccess() && !empty($cart->recurring_carts)) {
            /** @var WC_Cart $recurringCart */
            foreach ($cart->recurring_carts as $recurringCart) {
                $this->addForCart($recurringCart, $code, $giftCardAppliedAmount);
            }
        }

        return $notification;
    }

    public function addForCart(WC_Cart $cart, string $code, int $giftCardAppliedAmount)
    {
        $context = $this->validationService->createValidationContextForCart($code, $cart);

        try {
            $validationResult = $this->validationService->validate($code,
                $context + $this->createGiftCreditsData($cart, $giftCardAppliedAmount));
        } catch (Exception $exception) {
            $errorMessage = $exception->getMessage();
            if ($exception->getKey() === 'invalid_gift_credits'){
                $errorMessage .= ": {$exception->getDetails()}";
            }

            return new Notification(false, __($errorMessage, 'voucherify'));
        }

        $code = $validationResult->code;
        if ( ! $validationResult->valid) {
            $errorMessage = $validationResult->error->message ?? 'Gift card is not valid.';

            return new Notification(false, __($errorMessage, 'voucherify'));
        } elseif ($this->session->isVoucherApplied($cart, $code)) {
            return new Notification(false, __('Gift card already applied!', 'voucherify'));
        }
        $appliedVouchers = $this->session->getAppliedVouchers($cart);
        if (empty($appliedVouchers)) {
            $voucher = $this->createFromValidationResult($validationResult);
            $voucher->setRequestedAmount(wc_remove_number_precision($giftCardAppliedAmount));
            $this->session->applyVoucher($cart, $voucher);

            $amountAppliedString = wc_format_decimal($voucher->getAmount(), 2);

            return new Notification(
                true,
                sprintf(__('Successfully applied %s from the gift card.', 'voucherify'), $amountAppliedString),
                $voucher
            );
        }

        return new Notification(false, __('Gift card already applied!', 'voucherify'));
    }

    public function revalidate(WC_Cart $cart)
    {
        $revalidated_vouchers = [];
        foreach ($this->session->getAppliedVouchers($cart) as $code => $applied_voucher) {
            $context           = $this->createValidationContext($applied_voucher, $cart);
            $validationResult = $this->validationService->validate($code, $context);
            if ($validationResult->valid) {
                $revalidated_vouchers[$code] = $this->createFromValidationResult($validationResult);
                $revalidated_vouchers[$code]->setRequestedAmount($applied_voucher->getRequestedAmount());
            }
        }
        $this->session->setAppliedVouchers($cart, $revalidated_vouchers);
    }

    protected function createFromValidationResult($validationResult)
    {
        return GiftVoucher::createFromValidationResult($validationResult);
    }

    /**
     * @param GiftVoucher $voucher
     * @param WC_Cart $cart
     * @return array[]|mixed|null
     */
    protected function createValidationContext(Voucher $voucher, WC_Cart $cart)
    {
        $context = $this->validationService->createValidationContextForCart($voucher->getCode(), $cart);

        return $context + $this->createGiftCreditsData($cart, $voucher->getRequestedAmount() * 100);
    }

    private function createGiftCreditsData(WC_Cart $cart, int $amount): array
    {
        $cartTotalAdjusted = $amount;
        if (!empty($cart->recurring_cart_key)) {
            $cartTotalAdjusted = intval(round($cart->get_total(true) * 100));
            if ($cartTotalAdjusted > $amount) {
                $cartTotalAdjusted = $amount;
            }
        }

        return [
            'gift' => [
                'credits' => $cartTotalAdjusted
            ]
        ];
    }
}