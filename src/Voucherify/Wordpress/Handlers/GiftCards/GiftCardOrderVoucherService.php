<?php

namespace Voucherify\Wordpress\Handlers\GiftCards;


use Voucherify\ClientException;
use Voucherify\Wordpress\Common\Models\Notification;
use Voucherify\Wordpress\Common\Models\Voucher;
use Voucherify\Wordpress\Common\Services\OrderVoucherService;
use Voucherify\Wordpress\Common\Services\RedemptionService;
use Voucherify\Wordpress\Common\Services\ValidationService;
use Voucherify\Wordpress\Handlers\DiscountVouchers\DiscountVoucher;
use WC_Order;

class GiftCardOrderVoucherService extends OrderVoucherService
{
    public function __construct(
        ValidationService $validationService,
        RedemptionService $redemptionService
    ) {
        parent::__construct(new GiftCardOrderVoucherMetaService(), $validationService, new GiftCardsSessionService(),
            $redemptionService);
    }


    public function add(string $code, float $amount, WC_Order $order = null)
    {
        if (empty($order)) {
            $order = wc_get_order($_POST['order_id']);
        }
        $context           = $this->validationService->createValidationContextForOrder($order) + $this->createGiftCreditsData($amount);
        $validationResult = $this->validationService->validate($code, $context);
        $code              = $validationResult->code;
        if ( ! $validationResult->valid) {
            $errorMessage = $validationResult->error->message ?? 'Coupon code is not valid.';

            return new Notification(false, __($errorMessage, 'voucherify'));
        } elseif ($this->isVoucherApplied($order, $code)) {
            return new Notification(false, 'Coupon code already applied!');
        }

        $applied_vouchers = $this->getAppliedVouchers($order);
        if ( ! empty($applied_vouchers)) {
            return new Notification(false, 'Coupon code already applied!');
        }
        $applied_vouchers[$code] = DiscountVoucher::createFromValidationResult($validationResult);
        $this->orderMetaService->setAppliedVouchers($order, $applied_vouchers);
        $order->save_meta_data();

        return new Notification(true, 'Coupon code applied successfully.');
    }

    private function createGiftCreditsData(int $amount): array
    {
        return [
            'gift' => [
                'credits' => $amount * 100
            ]
        ];
    }

    /**
     * @param  WC_Order  $order
     *
     * @return void
     */
    public function redeem(WC_Order $order)
    {
        $giftCardHasBeenRemoved = false;
        $redeemed_vouchers      = [];
        foreach ($this->getAppliedVouchers($order) as $applied_voucher) {
            $redeemed_voucher = $this->tryRedeem($applied_voucher, $order);
            if ( ! empty($redeemed_voucher)) {
                $redeemed_vouchers[$applied_voucher->getCode()] = $redeemed_voucher;
            } else {
                $giftCardHasBeenRemoved = true;
            }
        }

        if ($giftCardHasBeenRemoved) {
            $order->calculate_totals();
            $order->save();
        }

        $this->orderMetaService->setAppliedVouchers($order, $redeemed_vouchers);
    }

    private function tryRedeem(Voucher $appliedVoucher, WC_Order $order)
    {
        try {
            $context           = $this->createSessionData($appliedVoucher->getSessionKey()) + $this->createGiftCreditsData($appliedVoucher->getAmount());
            $redemption_result = $this->redemption_service->redeem($appliedVoucher->getCode(), $context, $order);
            if ($redemption_result->result === 'SUCCESS') {
                return GiftVoucher::createFromRedemptionResult($redemption_result);
            }
        } catch (ClientException $exception) {
            wc_get_logger()->error("{$exception->getMessage()}:", ["source" => "voucherify"]);
            wc_get_logger()->error($exception->getTraceAsString(), ["source" => "voucherify"]);
        }

        $this->remove($order, $appliedVoucher->getCode());
        $order->add_order_note(
            sprintf(
                __(
                    'WARNING: Gift card %s was validated successfully, payment might have been charged with the discount, but the gift card was not redeemed.',
                    'voucherify'
                ),
                $appliedVoucher->getCode()
            )
        );

        return null;
    }

    public function revalidate(WC_Order $order)
    {
        $giftCardHasBeenRemoved = false;
        /** @var GiftVoucher $voucher */
        foreach ($this->getAppliedVouchers($order) as $voucher) {
            $validationResult = $this->validate($order, $voucher->getCode(), $voucher->getRequestedAmount());
            if ( ! $validationResult->valid) {
                $this->remove($order, $voucher->getCode());
                $order->add_order_note(
                    sprintf(
                        __('Gift card %s was not valid anymore and it has been removed.', 'voucherify'),
                        $voucher->getCode()
                    )
                );
                $giftCardHasBeenRemoved = true;
            }
        }

        if ($giftCardHasBeenRemoved) {
            $order->calculate_totals();
            $order->save();
        }
    }

    private function validate(WC_Order $order, string $code, float $amount)
    {
        $context = $this->validationService->createValidationContextForOrder($order) + $this->createGiftCreditsData($amount);

        return $this->validationService->validate($code, $context);
    }
}