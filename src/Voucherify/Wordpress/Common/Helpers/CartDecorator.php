<?php

namespace Voucherify\Wordpress\Common\Helpers;


use Voucherify\Wordpress\Common\Services\CartShippingService;
use WC_Cart;

/**
 * Created by PhpStorm.
 * User: bbs
 * Date: 1/23/18
 * Time: 11:10 AM
 */
class CartDecorator
{
    /** @var WC_Cart $order */
    private $cart;

    /**
     * Voucherify_Cart_Decorator constructor.
     *
     * @param  WC_Cart  $cart
     */
    public function __construct(WC_Cart $cart)
    {
        $this->cart = $cart;
    }

    /**
     * Converts cart data to `order` property of the params that are passed to the endpoint call.
     *
     * @return array `order` property of payload to be used during endpoint call
     */
    public function get_data()
    {
        $items = apply_filters('voucherify_cart_decorator_get_cart_items', $this->cart->get_cart(), $this->cart);

        $amount = $this->cart->get_total('edit') + $this->cart->get_discount_total() + $this->cart->get_discount_tax();

        $amount = apply_filters('voucherify_cart_decorator_calc_amount', $amount, $this->cart);

        $order_data = [
            'order' => [
                'amount' => (int)($amount * 100),
                'metadata' => [
                    'type' => $this->getOrderType(),
                    'data_source' => 'cart',
                    'recurring_cart_key' => $this->cart->recurring_cart_key ?? '',
                ]
            ]
        ];

        $items_data = [];
        foreach ($items as $item) {
            if ( isset($item['added_by_vcrf_coupon']) && $item['added_by_vcrf_coupon']) {
                if ($item['quantity'] > ($item['vcrf_added_quantity'] ?? 0)) {
                    $item_subtotal = $item['line_subtotal'] / $item['quantity'];
                    $item_subtotal_tax = ($item['line_subtotal_tax'] ?? 0) / $item['quantity'];
                    $item['quantity']   -= $item['vcrf_added_quantity'] ?? 0;
                    $item['line_subtotal'] = $item_subtotal * $item['quantity'];
                    $item['line_subtotal_tax'] = $item_subtotal_tax * $item['quantity'];
                    $order_data['order']['amount'] -= (int)(100*($item['line_subtotal'] + ($item['line_subtotal_tax'] ?? 0)));
                } else {
                    $order_data['order']['amount'] -= (int)(100*($item['line_subtotal'] + $item['line_subtotal_tax'] ?? 0));
                    continue;
                }
            }
            $item_data = [];

            if (!empty($item['variation_id'])) {
                $item_data['source_id'] = createVcrfVariantSourceId($item['variation_id']);
                $item_data['related_object'] = 'sku';
            } else {
                $item_data['source_id'] = createVcrfVariantSourceId($item['product_id']);
                $item_data['related_object'] = 'product';
            }

            if ( ! empty($item['quantity']) && ! empty($item['line_subtotal'])) {
                $quantity              = $item['quantity'];
                $item_data['quantity'] = $quantity;
                $price                 = $item['line_subtotal'];
                if (wc_tax_enabled() && ! empty($item['line_subtotal_tax'])) {
                    $price += $item['line_subtotal_tax'];
                }
                $item_data['price'] = intval(round(100 * $price / $quantity));
            }

            if ( ! empty($item_data)) {
                $items_data[] = $item_data;
            }
        }

        $cartShippingService = new CartShippingService();

        $shippingAmount = array_reduce($cartShippingService->getShippingBeforeDiscounts($this->cart),
            function ($carry, $shippingItem) {
                $carry += doubleval($shippingItem->price);

                return $carry;
            },
            0);

        if ( $shippingAmount > 0 ) {
            $items_data[] = [
                'product_id' => 'prod_5h1pp1ng',
                'quantity'   => 1,
                'price'      => $shippingAmount
            ];
        }

        if ( ! empty($items_data)) {
            $order_data['order']['items'] = $items_data;
        }

        return apply_filters('voucherify_cart_decorator_get_data', $order_data, $this->cart);
    }

    private function getOrderType()
    {
        if (!empty($this->cart->recurring_cart_key)) {
            return "renewal";
        } elseif (!empty($this->cart->recurring_carts)) {
            return "parent";
        }

        return "normal";
    }
}

