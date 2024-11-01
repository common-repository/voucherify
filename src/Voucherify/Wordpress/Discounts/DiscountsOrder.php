<?php

namespace Voucherify\Wordpress\Discounts;


use Voucherify\Wordpress\Common\Services\CartShippingService;
use WC_Abstract_Order;
use WC_Order;
use WC_Order_Item_Shipping;
use WC_Tax;

class DiscountsOrder extends Discounts
{

    /** @var WC_Abstract_Order */
    private $order;

    /**
     * @param  WC_Abstract_Order  $order  Order object.
     */
    public function __construct(&$order, $calculate_tax = true)
    {
        parent::__construct($calculate_tax);

        $this->order = $order;
        $this->setup_items();
        $this->setup_shipping();
//		$this->setup_fees();

        $this->discounts->set_items($this->get_discountable_items());
    }

    private function setup_items()
    {
        $this->items = [];

        foreach ($this->order->get_items() as $cart_item_key => $cartItem) {
            $item                     = $this->get_default_item_props();
            $item->key                = $cart_item_key;
            $item->object             = $cartItem;
            $item->tax_class          = $cartItem->get_product()->get_tax_class();
            $item->taxable            = 'taxable' === $cartItem->get_product()->get_tax_status();
            $item->price_includes_tax = true;
            $item->quantity           = $cartItem->get_quantity();
            $item->product            = $cartItem->get_product();
            $rates                    = WC_Tax::get_rates($cartItem->get_product()->get_tax_class());
            $item->tax_rates          = $rates;
            $item->price              = wc_add_number_precision_deep(
                $cartItem->get_subtotal('edit') + $cartItem->get_subtotal_tax('edit')
            );

            $this->items[$cart_item_key] = $item;
        }
    }

    private function get_tax_location()
    {
        $args         = array();
        $tax_based_on = get_option('woocommerce_tax_based_on');

        if ('shipping' === $tax_based_on && ! $this->order->get_shipping_country()) {
            $tax_based_on = 'billing';
        }

        $args = wp_parse_args(
            $args,
            array(
                'country'  => 'billing' === $tax_based_on ? $this->order->get_billing_country() : $this->order->get_shipping_country(),
                'state'    => 'billing' === $tax_based_on ? $this->order->get_billing_state() : $this->order->get_shipping_state(),
                'postcode' => 'billing' === $tax_based_on ? $this->order->get_billing_postcode() : $this->order->get_shipping_postcode(),
                'city'     => 'billing' === $tax_based_on ? $this->order->get_billing_city() : $this->order->get_shipping_city(),
            )
        );

        // Default to base.
        if ('base' === $tax_based_on || empty($args['country'])) {
            $args['country']  = WC()->countries->get_base_country();
            $args['state']    = WC()->countries->get_base_state();
            $args['postcode'] = WC()->countries->get_base_postcode();
            $args['city']     = WC()->countries->get_base_city();
        }

        return apply_filters('woocommerce_order_get_tax_location', $args, $this);
    }

    private function setup_shipping()
    {
        $shipping_tax_class = get_option('woocommerce_shipping_tax_class');
        if ('inherit' === $shipping_tax_class) {
            $found_classes      = array_intersect(array_merge(array(''), WC_Tax::get_tax_class_slugs()),
                $this->order->get_items_tax_classes());
            $shipping_tax_class = count($found_classes) ? current($found_classes) : false;
        }
        $calculate_tax_for = array_merge($this->get_tax_location(), array('tax_class' => $shipping_tax_class));
        $tax_rates         = WC_Tax::find_rates($calculate_tax_for);

        /**
         * @var WC_Order_Item_Shipping $shipping_rate
         */
        foreach ($this->order->get_shipping_methods() as $shipping_idx => $shipping_rate) {
            $rate_args = $this->get_shipping_rate_args($shipping_rate);

            if ($rate_args->calc_tax == 'per_item') {
                $this->setup_per_item_shipping($shipping_idx, $shipping_rate, $rate_args, $tax_rates);
            } else {
                $this->setup_per_order_shipping($shipping_idx, $shipping_rate, $tax_rates);
            }
        }
    }

    private function setup_per_item_shipping(
        $shipping_idx,
        WC_Order_Item_Shipping $shipping_rate,
        $rate_args,
        array $tax_rates
    ) {
        $cart = $this->order->get_items();

        $shipping_id           = $shipping_rate->get_id();
        $shipping_id           = str_replace(':', '_', $shipping_id);
        $shipping_rate_options = get_option("woocommerce_{$shipping_id}_settings", true);

        $costs = $rate_args->cost;

        foreach ($costs as $cost_item_key => $cost) {
            if ( ! isset($cart[$cost_item_key])) {
                continue;
            }

            $shipping_item_key = CartShippingService::SHIPPING_ITEM_KEY . ":$shipping_idx:$cost_item_key";

            $shipping                     = CartShippingService::getDefaultShippingProps();
            $shipping->key                = $shipping_item_key;
            $shipping->type               = 'shipping';
            $shipping->calc_tax           = 'per_item';
            $shipping->calc_tax_item      = 'item';
            $shipping->object             = ['sku' => CartShippingService::SHIPPING_ITEM_KEY];
            $shipping->tax_class          = $cart[$cost_item_key]['data']->get_tax_class();
            $shipping->taxable            = $shipping_rate_options['tax_status'] == 'taxable';
            $shipping->price_includes_tax = true;
            $shipping->quantity           = 1;
            $shipping->tax_rates          = $tax_rates;

            $price = wc_add_number_precision_deep($cost);
            if ($shipping->taxable) { // shipping price is always provided excl. tax
                $item_taxes = WC_Tax::calc_shipping_tax($price, $shipping->tax_rates);
                $tax        = wc_round_tax_total(array_sum($item_taxes), 0);
                $price      += $tax;
            }

            $shipping->price = $price;

            $this->shipping[$shipping_item_key] = $shipping;
        }

        if (isset($costs->order)) {
            $shipping_item_key = CartShippingService::SHIPPING_ITEM_KEY . ":$shipping_idx:order";

            $shipping                     = CartShippingService::getDefaultShippingProps();
            $shipping->key                = $shipping_item_key;
            $shipping->calc_tax           = 'per_item';
            $shipping->calc_tax_item      = 'order';
            $shipping->type               = 'shipping';
            $shipping->object             = ['sku' => CartShippingService::SHIPPING_ITEM_KEY];
            $shipping->tax_class          = get_option('woocommerce_shipping_tax_class');
            $shipping->taxable            = $shipping_rate_options['tax_status'] == 'taxable';
            $shipping->price_includes_tax = true;
            $shipping->quantity           = 1;
            $shipping->tax_rates          = $tax_rates;

            $price = wc_add_number_precision_deep($costs['order']);
            if ($shipping->taxable) { // shipping price is always provided excl. tax
                $item_taxes = WC_Tax::calc_shipping_tax($price, $shipping->tax_rates);
                $tax        = wc_round_tax_total(array_sum($item_taxes), 0);
                $price      += $tax;
            }

            $shipping->price = $price;

            $this->shipping[$shipping_item_key] = $shipping;
        }
    }

    private function setup_per_order_shipping($shipping_idx, WC_Order_Item_Shipping $item_shipping, array $tax_rates)
    {
        $shipping_item_key = CartShippingService::SHIPPING_ITEM_KEY . ":$shipping_idx";

        $shipping                     = CartShippingService::getDefaultShippingProps();
        $shipping->key                = $shipping_item_key;
        $shipping->type               = 'shipping';
        $shipping->calc_tax           = 'per_order';
        $shipping->object             = ['sku' => CartShippingService::SHIPPING_ITEM_KEY];
        $shipping->tax_class          = get_option('woocommerce_shipping_tax_class');
        $shipping->taxable            = $item_shipping->get_tax_status() == 'taxable';
        $shipping->price_includes_tax = true;
        $shipping->quantity           = 1;
        $shipping->tax_rates          = $tax_rates;
        $price                        = wc_add_number_precision($item_shipping->get_total());
        if ($shipping->taxable) { // shipping price is always provided excl. tax
            $tax   = wc_round_tax_total(array_sum(WC_Tax::calc_tax($price, $shipping->tax_rates)), 0);
            $price += $tax;
        }

        $shipping->price = $price;

        $this->shipping[$shipping_item_key] = $shipping;
    }

    private function get_shipping_rate_args(WC_Order_Item_Shipping $shipping_rate)
    {
        $shipping_rate_meta = $shipping_rate->get_meta_data();
        $rate_args          = [
            'calc_tax' => 'per_order',
            'cost'     => wc_add_number_precision_deep($shipping_rate->get_total()),
        ];

        if ( ! empty($shipping_rate_meta['_rate_args'])) {
            $rate_args = wp_parse_args(
                json_decode($shipping_rate_meta['_rate_args']),
                $rate_args
            );
        }

        return (object)$rate_args;
    }
}
