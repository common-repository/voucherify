<?php

namespace Voucherify\Wordpress\Common\Services;

use WC_Cart;
use WC_Shipping_Rate;
use WC_Tax;

class CartShippingService
{
    public const SHIPPING_ITEM_KEY = 'prod_5h1pp1ng';

    private const SHIPPING_BEFORE_DISCOUNT_SESSION_KEY = '_vcrf_shipping_before_discount';

    public function getShippingBeforeDiscounts(WC_Cart $cart, $forceCartShippingCalculation = false) {
        $savedShippingBeforeDiscount = WC()->session->get(static::SHIPPING_BEFORE_DISCOUNT_SESSION_KEY);
        if (!empty($savedShippingBeforeDiscount[SessionService::getCartKey($cart)]) && $forceCartShippingCalculation !== true) {
            return $savedShippingBeforeDiscount[SessionService::getCartKey($cart)];
        }

        $shipping = [];

        /**
         * @var WC_Shipping_Rate $shipping_rate
         */
        foreach ($cart->calculate_shipping() as $shipping_idx => $shipping_rate) {
            $rate_args = $this->getShippingRateArgs($shipping_rate);

            if ($rate_args->calc_tax == 'per_item') {
                $shipping_rate_items = $this->setupPerItemShipping($cart, $shipping_idx, $shipping_rate, $rate_args);
                $shipping = array_merge($shipping, $shipping_rate_items);
            } else {
                $shipping_rate_items = $this->setupPerOrderShipping($shipping_idx, $shipping_rate);
                $shipping = array_merge($shipping, $shipping_rate_items);
            }
        }

        if (empty($savedShippingBeforeDiscount)) {
            $savedShippingBeforeDiscount = [];
        }

        $savedShippingBeforeDiscount[SessionService::getCartKey($cart)] = $shipping;

        WC()->session->set(static::SHIPPING_BEFORE_DISCOUNT_SESSION_KEY, $savedShippingBeforeDiscount);

        return $shipping;
    }

    private function setupPerItemShipping(WC_Cart $cart, $shipping_idx, WC_Shipping_Rate $shipping_rate, $rate_args)
    {
        $cart = $cart->get_cart();

        $shipping_id           = $shipping_rate->get_id();
        $shipping_id           = str_replace(':', '_', $shipping_id);
        $shipping_rate_options = get_option("woocommerce_{$shipping_id}_settings", true);

        $costs = $rate_args->cost;

        $shipping_rate_items = [];

        foreach ($costs as $cost_item_key => $cost) {
            if ( ! isset($cart[$cost_item_key])) {
                continue;
            }

            $shipping_item_key = static::SHIPPING_ITEM_KEY . ":$shipping_idx:$cost_item_key";

            $shipping                     = static::getDefaultShippingProps();
            $shipping->key                = $shipping_item_key;
            $shipping->type               = 'shipping';
            $shipping->calc_tax           = 'per_item';
            $shipping->calc_tax_item      = 'item';
            $shipping->object             = ['sku' => static::SHIPPING_ITEM_KEY];
            $shipping->tax_class          = $cart[$cost_item_key]['data']->get_tax_class();
            $shipping->taxable            = $shipping_rate_options['tax_status'] == 'taxable';
            $shipping->price_includes_tax = true;
            $shipping->quantity           = 1;
            $shipping->tax_rates          = WC_Tax::get_shipping_tax_rates($cart[$cost_item_key]['data']->get_tax_class());

            $price = wc_add_number_precision_deep($cost);
            if ($shipping->taxable) { // shipping price is always provided excl. tax
                $item_taxes = WC_Tax::calc_shipping_tax($price, $shipping->tax_rates);
                $tax        = wc_round_tax_total(array_sum($item_taxes), 0);
                $price      += $tax;
            }

            $shipping->price = $price;

            $shipping_rate_items[$shipping_item_key] = $shipping;
        }

        if (isset($costs->order)) {
            $shipping_item_key = static::SHIPPING_ITEM_KEY . ":$shipping_idx:order";

            $shipping                     = static::getDefaultShippingProps();
            $shipping->key                = $shipping_item_key;
            $shipping->calc_tax           = 'per_item';
            $shipping->calc_tax_item      = 'order';
            $shipping->type               = 'shipping';
            $shipping->object             = ['sku' => static::SHIPPING_ITEM_KEY];
            $shipping->tax_class          = get_option('woocommerce_shipping_tax_class');
            $shipping->taxable            = $shipping_rate_options['tax_status'] == 'taxable';
            $shipping->price_includes_tax = true;
            $shipping->quantity           = 1;
            $shipping->tax_rates          = WC_Tax::get_shipping_tax_rates();

            $price = wc_add_number_precision_deep($costs['order']);
            if ($shipping->taxable) { // shipping price is always provided excl. tax
                $item_taxes = WC_Tax::calc_shipping_tax($price, $shipping->tax_rates);
                $tax        = wc_round_tax_total(array_sum($item_taxes), 0);
                $price      += $tax;
            }

            $shipping->price = $price;

            $shipping_rate_items[$shipping_item_key] = $shipping;
        }

        return $shipping_rate_items;
    }

    private function setupPerOrderShipping($shipping_idx, WC_Shipping_Rate $shipping_rate)
    {
        $shipping_id           = $shipping_rate->get_id();
        $shipping_id           = str_replace(':', '_', $shipping_id);
        $shipping_rate_options = get_option("woocommerce_{$shipping_id}_settings", true);

        $shipping_item_key = static::SHIPPING_ITEM_KEY . ":$shipping_idx";

        $shipping                     = static::getDefaultShippingProps();
        $shipping->key                = $shipping_item_key;
        $shipping->type               = 'shipping';
        $shipping->calc_tax           = 'per_order';
        $shipping->object             = ['sku' => static::SHIPPING_ITEM_KEY];
        $shipping->tax_class          = get_option('woocommerce_shipping_tax_class');
        $shipping->taxable            = 'taxable' == $shipping_rate_options['tax_status'] ?? '' ;
        $shipping->price_includes_tax = true;
        $shipping->quantity           = 1;
        $shipping->tax_rates          = WC_Tax::get_shipping_tax_rates();

        $price = wc_add_number_precision_deep($shipping_rate->get_cost());
        if ($shipping->taxable) { // shipping price is always provided excl. tax
            $tax   = wc_round_tax_total(array_sum(WC_Tax::calc_tax($price, $shipping->tax_rates)), 0);
            $price += $tax;
        }

        $shipping->price = $price;

        return [$shipping_item_key => $shipping];
    }

    private function getShippingRateArgs(WC_Shipping_Rate $shipping_rate)
    {
        $shipping_rate_meta = $shipping_rate->get_meta_data();
        $rate_args          = [
            'calc_tax' => 'per_order',
            'cost'     => wc_add_number_precision_deep($shipping_rate->get_cost()),
        ];

        if ( ! empty($shipping_rate_meta['_rate_args'])) {
            $rate_args = wp_parse_args(
                json_decode($shipping_rate_meta['_rate_args']),
                $rate_args
            );
        }

        return (object)$rate_args;
    }

    public static function getDefaultShippingProps()
    {
        return (object)array(
            'type'               => 'shipping',
            'tax_class'          => '',
            'taxable'            => false,
            'quantity'           => 0,
            'object'             => false,
            'product'            => false,
            'price_includes_tax' => true,
            'subtotal'           => 0,
            'subtotal_tax'       => 0,
            'subtotal_taxes'     => array(),
            'total'              => 0,
            'total_tax'          => 0,
            'taxes'              => array(),
        );
    }
}
