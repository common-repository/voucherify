<?php

namespace Voucherify\Wordpress\Handlers\Promotions;

use Voucherify\Wordpress\Common\Helpers\VoucherBuilder;
use Voucherify\Wordpress\Common\Models\Notification;
use Voucherify\Wordpress\Common\Services\OrderService;
use Voucherify\Wordpress\Common\Services\RedemptionService;
use Voucherify\Wordpress\Common\Services\ValidationService;
use WC_Order;

class PromotionOrderService extends OrderService
{
    public function __construct(
        ValidationService $validationService,
        RedemptionService $redemptionService
    ) {
        parent::__construct(new PromotionOrderMetaService(), $validationService, new PromotionSessionService(),
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
            //TODO: meta
        } elseif ($this->session->isVoucherApplied($code)) {
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
        $redeemed_vouchers = [];
        foreach ($this->getAppliedVouchers($order) as $code => $applied_voucher) {
            $redemption_result = $this->redemption_service->redeem(['id' => $code],
                $this->createSessionData($applied_voucher->getSessionKey()), $order);
            if ($redemption_result->result === 'SUCCESS') {
                $redeemed_vouchers[$code] = PromotionVoucher::createFromRedemptionResult($redemption_result);
            } else {
                //TODO: not all coupons applied
            }
        }
        $this->orderMetaService->setAppliedVouchers($order, $redeemed_vouchers);
    }
}