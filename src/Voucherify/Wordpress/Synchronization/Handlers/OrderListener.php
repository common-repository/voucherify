<?php

namespace Voucherify\Wordpress\Synchronization\Handlers;

use Voucherify\Wordpress\Handlers\DiscountVouchers\DiscountVoucherOrderVoucherMetaService;
use Voucherify\Wordpress\Handlers\GiftCards\GiftCardOrderVoucherMetaService;
use Voucherify\Wordpress\Handlers\Promotions\PromotionOrderVoucherMetaService;
use Voucherify\Wordpress\Synchronization\Services\OrderService;
use Voucherify\Wordpress\Synchronization\TooManyRequestsException;

class OrderListener
{
    /** @var OrderService $orderService */
    private $orderService;
    private DiscountVoucherOrderVoucherMetaService $discountVoucherOrderVoucherMetaService;
    private GiftCardOrderVoucherMetaService $giftCardOrderVoucherMetaService;
    private PromotionOrderVoucherMetaService $promotionOrderVoucherMetaService;

    /**
     * Voucherify_Order_Listener constructor.
     *
     * @param OrderService $orderService
     */
    public function __construct(
        DiscountVoucherOrderVoucherMetaService $discountVoucherOrderVoucherMetaService,
        GiftCardOrderVoucherMetaService $giftCardOrderVoucherMetaService,
        PromotionOrderVoucherMetaService $promotionOrderVoucherMetaService,
        OrderService $orderService
    )
    {
        $this->discountVoucherOrderVoucherMetaService = $discountVoucherOrderVoucherMetaService;
        $this->giftCardOrderVoucherMetaService = $giftCardOrderVoucherMetaService;
        $this->promotionOrderVoucherMetaService = $promotionOrderVoucherMetaService;
        $this->orderService = $orderService;
    }

    /**
     * Callback function for hook after the order is saved.
     *
     * @param $orderId
     *
     */
    public function afterOrderSave($orderId)
    {
        $order = wc_get_order($orderId);

        if (in_array($order->get_status(), ['checkout-draft', 'pending'])) {
            return;
        }

        if (in_array($order->get_meta('_vcrf_redemption_status'), ['complete', 'SUCCEEDED', 'ROLLED_BACK'])) {
            return;
        }

        // - if orders has coupons - block for statues "processing" AND "completed" as redemption will do the order sync
        // - if order has no coupons - do the order sync for paid order only once (it will be marked paid in meta)
        if (
            $order->get_meta('_vcrf_marked_paid') === 'YES'
            || ($order->is_paid() && $this->anyDiscountApplied($order))
        ) {
            return;
        }

        try {
            $this->orderService->save($order);
        } catch (TooManyRequestsException $exception) {
            wc_get_logger()->error(
                'Voucherify API requests limit has been reached',
                ['source' => 'voucherify']
            );
        }
    }

    public function onDelete($wcOrderId)
    {
        try {
            $this->orderService->delete($wcOrderId);
        } catch (TooManyRequestsException $exception) {
            wc_get_logger()->error('Voucherify API requests limit has been reached',
                ['source' => 'voucherify']);
        }
    }

    private function anyDiscountApplied(\WC_Order $order) {
        return !empty($this->discountVoucherOrderVoucherMetaService->getAppliedVouchers($order))
            || !empty($this->giftCardOrderVoucherMetaService->getAppliedVouchers($order))
            || !empty($this->promotionOrderVoucherMetaService->getAppliedVouchers($order));
    }
}
