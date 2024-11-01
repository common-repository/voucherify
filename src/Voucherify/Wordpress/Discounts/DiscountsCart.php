<?php

namespace Voucherify\Wordpress\Discounts;


use Voucherify\Wordpress\Common\Services\CartShippingService;
use WC_Cart;
use WC_Tax;

class DiscountsCart extends Discounts
{

    /** @var WC_Cart */
    private $cart;

    /** @var CartShippingService */
    private $cartShippingService;

    /**
     * @param  WC_Cart  $cart
     */
    public function __construct(CartShippingService $shippingService, &$cart, $calculate_tax = true)
    {
        parent::__construct($calculate_tax);

        $this->cartShippingService = $shippingService;
        $this->cart = $cart;
        $this->setup_items();
        $this->setup_shipping();
        $this->setup_fees();

        $this->discounts->set_items($this->get_discountable_items());
    }

    private function get_item_tax_rates($item)
    {
        if ( ! wc_tax_enabled()) {
            return array();
        }
        $tax_class      = $item->product->get_tax_class();
        $item_tax_rates = $this->item_tax_rates[$tax_class] ?? $this->item_tax_rates[$tax_class]
                = WC_Tax::get_rates($item->product->get_tax_class(), $this->cart->get_customer());

        // Allow plugins to filter item tax rates.
        return apply_filters('woocommerce_cart_totals_get_item_tax_rates', $item_tax_rates, $item, $this->cart);
    }

    private function setup_shipping()
    {
        $this->shipping = $this->cartShippingService->getShippingBeforeDiscounts($this->cart, true);
    }

    private function setup_fees()
    {
        $cart_fees = $this->cart->get_fees();

        $this->fees = [];

        foreach ($cart_fees as $cart_fee) {
            if ($cart_fee->total <= 0) {
                continue;
            }

            /**
             * result = {array} [1]
             * surcharge = {stdClass} [8]
             * id = "surcharge"
             * name = "Surcharge"
             * tax_class = ""
             * taxable = true
             * amount = "1.0068"
             * total = {float} 1.0068
             * tax_data = {array} [1]
             * tax = {float} 0.23
             */

            $fee                     = $this->get_default_fees_props();
            $fee->key                = $cart_fee->id;
            $fee->type               = 'fee';
            $fee->object             = ['data' => $cart_fee, 'sku' => $cart_fee->id];
            $fee->tax_class          = $cart_fee->tax_class;
            $fee->taxable            = $cart_fee->taxable;
            $fee->price_includes_tax = true;
            $fee->quantity           = 1;
            $fee->tax_rates          = WC_Tax::get_rates($cart_fee->tax_class);

            $price = wc_add_number_precision_deep($cart_fee->total);
            if ($fee->taxable) { // fee price is always provided excl. tax
                $tax   = wc_round_tax_total(array_sum(WC_Tax::calc_tax($price, $fee->tax_rates)), 0);
                $price += $tax;
            }

            $fee->price = $price;

            $this->fees[$fee->key] = $fee;
        }
    }

    private function setup_items()
    {
        $this->items = [];

        foreach ($this->cart->get_cart() as $cartItemKey => $cartItem) {
            $item                     = $this->get_default_item_props();
            $item->key                = $cartItemKey;
            $item->object             = $cartItem;
            $item->tax_class          = $cartItem['data']->get_tax_class();
            $item->taxable            = 'taxable' === $cartItem['data']->get_tax_status();
            $item->price_includes_tax = true;
            $item->quantity           = $cartItem['quantity'];
            $item->product            = $cartItem['data'];
            $item->tax_rates          = $this->get_item_tax_rates($item);
            $item->price              = wc_add_number_precision_deep($cartItem["line_subtotal"] + $cartItem["line_subtotal_tax"]);

            $this->items[$cartItemKey] = $item;
        }
    }
}