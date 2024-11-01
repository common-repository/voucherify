<?php

namespace Voucherify\Wordpress\Handlers\GiftCards;


use Voucherify\Wordpress\Common\Models\Notification;
use Voucherify\Wordpress\Common\Services\OrderService;
use Voucherify\Wordpress\Common\Services\RedemptionService;
use Voucherify\Wordpress\Common\Services\ValidationService;
use Voucherify\Wordpress\Handlers\DiscountVouchers\DiscountVoucher;
use WC_Order;

class GiftCardOrderService extends OrderService
{
    public function __construct(
        ValidationService $validationService,
        RedemptionService $redemptionService
    ) {
        parent::__construct(new GiftCardOrderMetaService(), $validationService, new GiftCardsSessionService(),
            $redemptionService);
    }


    public function add(string $code, float $amount)
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
            //TODO: meta
        } elseif ($this->session->isVoucherApplied($code)) {
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
        $redeemed_vouchers = [];
        foreach ($this->getAppliedVouchers($order) as $code => $applied_voucher) {
            $context           = $this->createSessionData($applied_voucher->getSessionKey()) + $this->createGiftCreditsData($applied_voucher->getAmount());
            $redemption_result = $this->redemption_service->redeem($applied_voucher->getCode(), $context, $order);
            if ($redemption_result->result === 'SUCCESS') {
                $redeemed_vouchers[$code] = GiftVoucher::createFromRedemptionResult($redemption_result);
            } else {
                //TODO: not all coupons applied
            }
        }
        //TODO: recalculate discounts
        $this->orderMetaService->setAppliedVouchers($order, $redeemed_vouchers);
    }
}