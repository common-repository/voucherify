<?php

namespace Voucherify\Wordpress\Handlers\DiscountVouchers;


use Voucherify\Wordpress\Common\Models\Voucher;
use WC_Product;
use WC_Subscriptions_Product;

class DiscountVoucher extends Voucher
{
    private $discountType;

    public function __construct(
        string $code,
        string $display_name,
        float $amount,
        array $applicable_items,
        string $session_key,
        string $redemptionId,
        array $discountedOrderItems,
        int $ttl = 0,
        string $discountType = null
    )
    {
        parent::__construct(
            $code,
            $display_name,
            $amount,
            $applicable_items,
            $session_key,
            $redemptionId,
            $discountedOrderItems,
            $ttl
        );

        $this->discountType = $discountType;
    }

    public static function createFromValidationResult($validationResult)
    {
        $totalDiscountAmount = 0;
        if (isset($validationResult->order->total_discount_amount)) {
            $totalDiscountAmount = $validationResult->order->total_discount_amount / 100;
        }
        $discountedOrderItems = [];

        $discountType = self::getDiscountTypeFromValidation($validationResult);
        if ($discountType === 'UNIT') {
            $totalDiscountAmount  = 0;
            $discountedOrderItems = static::createDiscountedOrderItems($validationResult->discount);
            foreach ($discountedOrderItems as $orderItem) {
                $totalDiscountAmount += static::getProductTotal($orderItem->getWCProduct(), $orderItem->getQuantity(), $validationResult->order->metadata->type ?? 'parent');
            }
            $applicableItems = static::createDiscountedApplicableOrderItems($validationResult->discount);
        } else {
            $applicableItems = static::createApplicableItemsList(
                $validationResult->applicable_to->data ?? [],
                $validationResult->discount->unit_type ?? null
            );
        }

        return new DiscountVoucher(
            $validationResult->code,
            $validationResult->code,
            $totalDiscountAmount,
            $applicableItems,
            $validationResult->session->key ?? '',
            '',
            $discountedOrderItems,
            $validationResult->session->ttl ?? 0,
            $discountType
        );
    }

    public static function createFromRedemptionResult($redemptionResult)
    {
        $totalDiscountAmount = 0;
        if (isset($redemptionResult->order->total_discount_amount)) {
            $totalDiscountAmount = $redemptionResult->order->total_discount_amount / 100;
        }

        $discountedOrderItems = [];

        $discountType = self::getDiscountTypeFromRedemption($redemptionResult);

        if ($discountType === 'UNIT') {
            $totalDiscountAmount  = 0;
            $discountedOrderItems = static::createDiscountedOrderItemsForRedemption(
                $redemptionResult->voucher->discount,
                $redemptionResult->order->items
            );
            foreach ($discountedOrderItems as $orderItem) {
                $totalDiscountAmount += static::getProductTotal(
                    $orderItem->getWCProduct(),
                    $orderItem->getQuantity(),
                    $redemptionResult->order->metadata->type ?? 'parent'
                );
            }
            $applicableItems = static::createDiscountedApplicableOrderItemsForRedeption(
                $redemptionResult->voucher->discount,
                $redemptionResult->order->items
            );
        } else {
            $applicableItems = static::createApplicableItemsList(
                $redemptionResult->voucher->applicable_to->data ?? [],
                $redemptionResult->voucher->discount->unit_type ?? null
            );
        }

        return new DiscountVoucher(
            $redemptionResult->voucher->code,
            $redemptionResult->voucher->code,
            $totalDiscountAmount,
            $applicableItems,
            $redemptionResult->session->key ?? '',
            $redemptionResult->id,
            $discountedOrderItems,
            0,
            $discountType
        );
    }

    private static function getProductTotal(WC_Product $product, $quantity, $orderType)
    {
        if (class_exists(WC_Subscriptions_Product::class)
            && $product->is_type(['subscription', 'subscription_variation', 'variable-subscription'])) {
            $price = doubleval(WC_Subscriptions_Product::get_price($product));

            if ($orderType === 'parent') {
                $price += doubleval(WC_Subscriptions_Product::get_sign_up_fee($product));
            }
        } else {
            $price = $product->get_price('edit');
        }

        return wc_get_price_including_tax($product, [
            'price' => $price,
            'qty' => $quantity
        ]);
    }

    private static function getDiscountTypeFromValidation($validationResult) {
        if (!empty(isset($validationResult->discount->type))) {
            return $validationResult->discount->type;
        }

        return null;
    }

    private static function getDiscountTypeFromRedemption($redemptionResult) {
        if (!empty(isset($redemptionResult->voucher->discount->type))) {
            return $redemptionResult->voucher->discount->type;
        }

        return null;
    }

    /**
     * @return string|null
     */
    public function getDiscountType()
    {
        return $this->discountType;
    }
}