<?php

namespace Voucherify\Wordpress\Handlers\DiscountVouchers;

use Voucherify\ClientException;
use Voucherify\Wordpress\Common\Models\Notification;
use Voucherify\Wordpress\Common\Services\OrderVoucherService;
use Voucherify\Wordpress\Common\Models\Voucher;
use Voucherify\Wordpress\Common\Services\RedemptionService;
use Voucherify\Wordpress\Common\Services\ValidationService;
use WC_Order;

class DiscountVoucherOrderVoucherService extends OrderVoucherService
{
    public function __construct(
        DiscountVoucherOrderVoucherMetaService $discountVoucherOrderVoucherMetaService,
        ValidationService $validationService,
        RedemptionService $redemptionService
    ) {
        parent::__construct($discountVoucherOrderVoucherMetaService, $validationService,
            new DiscountVoucherSessionService(), $redemptionService);
    }


    public function add(string $code, WC_Order $order = null)
    {
        if (empty($order)) {
            $order = wc_get_order($_POST['order_id']);
        }
        $validationResult = $this->validate($order, $code);
        if (!$this->isValidationSuccessful($validationResult)) {
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

    private function isValidationSuccessful($validationResult) {
        if (!$validationResult->valid) {
            return false;
        }

        $discountVoucher = DiscountVoucher::createFromValidationResult($validationResult);
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

    public function redeem(WC_Order $order)
    {
        $couponHasBeenRemoved = false;
        $redeemed_vouchers = [];
        foreach ($this->getAppliedVouchers($order) as $appliedVoucher) {
            $code            = $appliedVoucher->getCode();
            $redeemedVoucher = $this->tryRedeem($appliedVoucher, $order);
            if ( ! empty($redeemedVoucher)) {
                $redeemed_vouchers[$code] = $redeemedVoucher;
            } else {
                $couponHasBeenRemoved = true;
            }
        }

        if ($couponHasBeenRemoved) {
            $order->calculate_totals();
            $order->save();
        }

        $this->orderMetaService->setAppliedVouchers($order, $redeemed_vouchers);
    }

    private function tryRedeem(Voucher $appliedVoucher, WC_Order $order)
    {
        try {
            $redemption_result = $this->redemption_service->redeem($appliedVoucher->getCode(),
                $this->createSessionData($appliedVoucher->getSessionKey()), $order);
            if ($redemption_result->result === 'SUCCESS') {
                return DiscountVoucher::createFromRedemptionResult($redemption_result);
            }
        } catch (ClientException $exception) {
            wc_get_logger()->error("{$exception->getMessage()}:", ["source" => "voucherify"]);
            wc_get_logger()->error($exception->getTraceAsString(), ["source" => "voucherify"]);
        }

        $this->remove($order, $appliedVoucher->getCode());
        $order->add_order_note(
            sprintf(
                __(
                    'WARNING: Code %s was validated successfully, payment might have been charged with the discount, but the coupon was not redeemed.',
                    'voucherify'
                ),
                $appliedVoucher->getCode()
            )
        );

        return null;
    }

    public function revalidate(WC_Order $order)
    {
        $couponHasBeenRemoved = false;
        foreach($this->getAppliedVouchers($order) as $voucher) {
            $validationResult = $this->validate($order, $voucher->getCode());
            if (!$validationResult->valid) {
                $this->remove($order, $voucher->getCode());
                $order->add_order_note(
                    sprintf(
                        __('Code %s was not valid anymore and it has been removed.', 'voucherify'), $voucher->getCode()
                    )
                );
                $couponHasBeenRemoved = true;
            }
        }

        if ($couponHasBeenRemoved) {
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
