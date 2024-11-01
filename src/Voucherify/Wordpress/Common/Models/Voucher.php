<?php

namespace Voucherify\Wordpress\Common\Models;

use WC_Coupon;

abstract class Voucher
{
    /**
     * @var string
     */
    private $code;
    /**
     * @var string
     */
    private $displayName;
    /**
     * @var float
     */
    private $amount;
    /**
     * @var array
     */
    private $applicableItems;
    /**
     * @var string
     */
    private $sessionKey;
    /**
     * @var string
     */
    private $redemptionId;
    /**
     * @var int
     */
    private $ttl;
    /**
     * @var int
     */
    private $createdTime;

    /**
     * @var DiscountedOrderItem[]
     */
    private $discountedOrderItems;


    public function __construct(
        string $code,
        string $display_name,
        float $amount,
        array $applicable_items,
        string $session_key,
        string $redemptionId,
        array $discountedOrderItems,
        int $ttl = 0
    ) {
        $this->code                 = $code;
        $this->displayName          = $display_name;
        $this->amount               = $amount;
        $this->applicableItems      = $applicable_items;
        $this->sessionKey           = $session_key;
        $this->redemptionId         = $redemptionId;
        $this->discountedOrderItems = $discountedOrderItems;
        $this->ttl                  = $ttl;
        $this->createdTime          = time();
    }


    /**
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @return string
     */
    public function getDisplayName()
    {
        return $this->displayName;
    }

    /**
     * @return float
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @return array
     */
    public function getApplicableItems()
    {
        return $this->applicableItems;
    }

    /**
     * @return string
     */
    public function getSessionKey()
    {
        return $this->sessionKey;
    }

    /**
     * @return WC_Coupon
     */
    public function getAsWcCoupon()
    {
        $coupon = new Vcrf_Coupon();
        $coupon->set_virtual(true);
        $coupon->set_code($this->code);
        $coupon->set_amount($this->amount); // if prices do not include tax, this must be net price
        $coupon->set_applicable_items($this->applicableItems);
        $coupon->apply_changes();

        return $coupon;
    }

    /**
     * @return string
     */
    public function getRedemptionId()
    {
        return $this->redemptionId;
    }

    /**
     * @return bool
     */
    public function isRedeemed()
    {
        return ! empty($this->redemptionId);
    }

    /**
     * @return int
     */
    public function getTtl(): int
    {
        return $this->ttl;
    }

    /**
     * @return int
     */
    public function getCreatedTime(): int
    {
        return $this->createdTime;
    }

    /**
     * @param  string  $sessionKey
     */
    public function setSessionKey(string $sessionKey)
    {
        $this->sessionKey = $sessionKey;
    }

    /**
     * @param  int  $ttl
     */
    public function setTtl(int $ttl)
    {
        $this->ttl = $ttl;
    }

    /**
     * @return DiscountedOrderItem[]
     */
    public function getDiscountedOrderItems()
    {
        return $this->discountedOrderItems;
    }

    protected static function createApplicableItemsList($applicableToData, $unitType = null)
    {
        $isFreeShipping = isset($unitType) && $unitType == "prod_5h1pp1ng";
        if ($isFreeShipping) {
            $applicableItems = ["prod_5h1pp1ng"];
        } else {
            $applicableItems = array_map(function ($sku) {
                return $sku->source_id ?? '--';
            }, $applicableToData ?? []);
            $applicableItems = array_filter($applicableItems);
        }

        return $applicableItems;
    }

    /**
     * @param $discount
     *
     * @return DiscountedOrderItem[]
     */
    protected static function createDiscountedOrderItems($discount)
    {
        $wcProducts = [];
        foreach (static::mapDiscountToDiscountedProducts($discount) as $product) {
            if (isset($product->sku)) {
                $wcProduct = wc_get_product(wc_get_product_id_by_sku($product->sku->sku));
            } elseif (isset($product->product)) {
                $wcProduct = wc_get_product($product->product->source_id);
            } else {
                continue;
            }

            if ( ! empty($wcProduct)) {
                $wcProducts[] = new DiscountedOrderItem($wcProduct, $product->unit_off, $product->effect);
            }
        }

        return $wcProducts;
    }

    protected static function createDiscountedApplicableOrderItems($discount)
    {
        $skus = [];
        foreach (static::mapDiscountToDiscountedProducts($discount) as $product) {
            if (isset($product->sku)) {
                $skus[] = $product->sku->sku;
            } elseif (isset($product->product)) {
                $skus[] = $product->product->id ?? null;
                $skus[] = $product->product->source_id ?? null;
            }
        }

        return array_unique(array_filter($skus));
    }

    //workaround for the redemption result. Discounted units does not contain sku
    protected static function createDiscountedApplicableOrderItemsForRedeption($discount, $vcrfOrderItems)
    {
        $skuIdToSourceIdMap = static::createSkuIdToSourceIdMap($vcrfOrderItems);
        $skus = [];
        foreach (static::mapDiscountToDiscountedProducts($discount) as $product) {
            if ( ! isset($skuIdToSourceIdMap[$product->unit_type])) {
                continue;
            }
            $skus[] = $skuIdToSourceIdMap[$product->unit_type];
        }

        return $skus;
    }

    //workaround for the redemption result. Discounted units does not contain sku
    protected static function createDiscountedOrderItemsForRedemption($discount, $vcrfOrderItems)
    {
        $skuIdToSourceIdMap = static::createSkuIdToSourceIdMap($vcrfOrderItems);
        $wcProducts = [];
        foreach (static::mapDiscountToDiscountedProducts($discount) as $product) {
            if ( ! isset($skuIdToSourceIdMap[$product->unit_type])) {
                continue;
            }
            $wcProduct = wc_get_product($skuIdToSourceIdMap[$product->unit_type]);
            if ( ! empty($wcProduct)) {
                $wcProducts[] = new DiscountedOrderItem($wcProduct, $product->unit_off, $product->effect);
            }
        }

        return $wcProducts;
    }

    protected static function createSkuIdToSourceIdMap($vcrfOrderItems)
    {
        $skuIdToSourceIdMap = [];
        foreach ($vcrfOrderItems as $item) {
            if (isset($item->sku)) {
                $skuIdToSourceIdMap[$item->sku->id] = $item->sku->source_id;
            } elseif (isset($item->product)) {
                $skuIdToSourceIdMap[$item->product->id] = $item->product->source_id;
            }
        }

        return $skuIdToSourceIdMap;
    }


    private static function mapDiscountToDiscountedProducts($discount)
    {
        $products = [];
        if ($discount->effect === "ADD_MANY_ITEMS") {
            foreach ($discount->units ?? [] as $unit) {
                $products[] = $unit;
            }
        } elseif ($discount->effect === "ADD_MISSING_ITEMS" || $discount->effect === "ADD_NEW_ITEMS") {
            $products[] = $discount;
        }

        return $products;
    }
}
