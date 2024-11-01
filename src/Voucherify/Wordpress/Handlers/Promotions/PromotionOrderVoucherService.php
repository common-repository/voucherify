<?php

namespace Voucherify\Wordpress\Handlers\Promotions;

use Voucherify\ClientException;
use Voucherify\Wordpress\Common\Models\Notification;
use Voucherify\Wordpress\Common\Models\Voucher;
use Voucherify\Wordpress\Common\Services\OrderVoucherService;
use Voucherify\Wordpress\Common\Services\RedemptionService;
use Voucherify\Wordpress\Common\Services\ValidationService;
use WC_Order;

class PromotionOrderVoucherService extends OrderVoucherService
{
    public function __construct(
        ValidationService $validationService,
        RedemptionService $redemptionService
    ) {
        parent::__construct(new PromotionOrderVoucherMetaService(), $validationService, new PromotionSessionService(),
            $redemptionService);
    }


    public function add(string $code, WC_Order $order = null)
    {
        if (empty($order)) {
            $order = wc_get_order($_POST['order_id']);
        }
        $context           = $this->validationService->createValidationContextForOrder($order);
        $validationResult = $this->validationService->validate($code, $context);
        $code              = $validationResult->code;
        if ( ! $validationResult->valid) {
            $errorMessage = $validationResult->error->message ?? 'Promotion is not valid.';

            return new Notification(false, __($errorMessage, 'voucherify'));
        } elseif ($this->isVoucherApplied($order, $code)) {
            return new Notification(false, 'Promotion already applied!');
        }

        $applied_vouchers = $this->getAppliedVouchers($order);
        if ( ! empty($applied_vouchers)) {
            return new Notification(false, 'Promotion already applied!');
        }
        $applied_vouchers[$code] = PromotionVoucher::createFromValidationResult($validationResult);
        $this->orderMetaService->setAppliedVouchers($order, $applied_vouchers);
        $order->save_meta_data();

        return new Notification(true, 'Promotion applied successfully.');
    }

    public function redeem(WC_Order $order)
    {
        $promotionHasBeenRemoved = false;
        $redeemedPromotions = [];
        foreach ($this->getAppliedVouchers($order) as $appliedVoucher) {
            $redeemedPromotion = $this->tryRedeem($appliedVoucher, $order);
            if ( ! empty($redeemedPromotion)) {
                $redeemedPromotions[$appliedVoucher->getCode()] = $redeemedPromotion;
            } else {
                $promotionHasBeenRemoved = true;
            }
        }

        if ($promotionHasBeenRemoved) {
            $order->calculate_totals();
            $order->save();
        }

        $this->orderMetaService->setAppliedVouchers($order, $redeemedPromotions);
    }

    private function tryRedeem(Voucher $appliedVoucher, WC_Order $order) {
        try {
            $redemptionResult = $this->redemption_service->redeem(['id' => $appliedVoucher->getCode()],
                $this->createSessionData($appliedVoucher->getSessionKey()), $order);

            if ($redemptionResult->result === 'SUCCESS') {
                return PromotionVoucher::createFromRedemptionResult($redemptionResult);
            }
        } catch (ClientException $exception) {
            wc_get_logger()->error("{$exception->getMessage()}:", ["source" => "voucherify"]);
            wc_get_logger()->error($exception->getTraceAsString(), ["source" => "voucherify"]);
        }

        $this->remove($order, $appliedVoucher->getCode());
        $order->add_order_note(
            sprintf(
                __(
                    'WARNING: Promotion %s was validated successfully, payment might have been charged with the discount, but the promotion was not redeemed.',
                    'voucherify'
                ),
                $appliedVoucher->getCode()
            )
        );

        return null;
    }

    public function revalidate(WC_Order $order)
    {
        $promotionHasBeenRemoved = false;
        foreach ($this->getAppliedVouchers($order) as $voucher) {
            $validationResult = $this->validate($order, $voucher->getCode());
            if ( ! $validationResult->valid) {
                $this->remove($order, $voucher->getCode());
                $order->add_order_note(
                    sprintf(
                        __('Promotion %s was not valid anymore and it has been removed.', 'voucherify'),
                        $voucher->getCode()
                    )
                );
                $promotionHasBeenRemoved = true;
            }
        }

        if ($promotionHasBeenRemoved) {
            $order->calculate_totals();
            $order->save();
        }
    }

    private function validate($order, string $code)
    {
        $context = $this->validationService->createValidationContextForOrder($order);

        return $this->validationService->validate($code, $context);
    }
}
