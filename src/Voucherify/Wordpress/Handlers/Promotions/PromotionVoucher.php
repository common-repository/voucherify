<?php

namespace Voucherify\Wordpress\Handlers\Promotions;

use Voucherify\Wordpress\Common\Models\Voucher;

class PromotionVoucher extends Voucher
{
    public static function createFromValidationResult($validationResult)
    {
        $discountedOrderItems = [];

        if (isset($validationResult->discount) && $validationResult->discount->type === 'UNIT') {
            $totalDiscountAmount  = 0;
            $discountedOrderItems = static::createDiscountedOrderItems($validationResult->discount);
            foreach ($discountedOrderItems as $orderItem) {
                $totalDiscountAmount += wc_get_price_including_tax($orderItem->getWCProduct(),
                    ['qty' => $orderItem->getQuantity()]);
            }
            $applicableItems = static::createDiscountedApplicableOrderItems($validationResult->discount);
        } else {
            $applicableItems = static::createApplicableItemsList($validationResult->applicable_to->data ?? [],
                $validationResult->discount->unit_type ?? null);
        }

        return new PromotionVoucher(
            $validationResult->id,
            $validationResult->banner ?? $validationResult->name ?? $validationResult->id,
            ($validationResult->order->total_discount_amount ?? 0) / 100,
            $applicableItems,
            $validationResult->session->key ?? '',
            '',
            $discountedOrderItems,
            $validationResult->session->ttl ?? 0);
    }

    public static function createFromRedemptionResult($redemptionResult)
    {
        $promotionTier = $redemptionResult->promotion_tier;

        $discountedOrderItems = [];

        if (isset($promotionTier->action->discount) && $promotionTier->action->discount->type === 'UNIT') {
            $totalDiscountAmount  = 0;
            $discountedOrderItems = static::createDiscountedOrderItemsForRedemption($promotionTier->action->discount,$redemptionResult->order->items);
            foreach ($discountedOrderItems as $orderItem) {
                $totalDiscountAmount += wc_get_price_including_tax($orderItem->getWCProduct(),
                    ['qty' => $orderItem->getQuantity()]);
            }
            $applicableItems = static::createDiscountedApplicableOrderItemsForRedeption($promotionTier->action->discount,$redemptionResult->order->items);
        } else {
            $applicableItems = static::createApplicableItemsList($redemptionResult->voucher->applicable_to->data ?? [],
                $promotionTier->action->discount->unit_type ?? null);
        }

        return new PromotionVoucher(
            $promotionTier->id,
            $promotionTier->banner ?? $promotionTier->name ?? $promotionTier->id,
            $redemptionResult->order->total_discount_amount / 100,
            $applicableItems,
            '',
            $redemptionResult->id,
            $discountedOrderItems);
    }
}
