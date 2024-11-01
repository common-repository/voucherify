<?php

namespace Voucherify\Wordpress\Discounts;


use Voucherify\Wordpress\Common\Models\Vcrf_Coupon;
use Voucherify\Wordpress\Common\Services\CartShippingService;
use WC_Discounts;
use WC_Tax;

abstract class Discounts
{
    /** @var WC_Discounts */
    protected $discounts;

    protected $items = [];

    protected $shipping = [];

    protected $fees = [];

    protected $calculate_tax;

    public function __construct($calculate_tax = true)
    {
        $this->discounts     = new WC_Discounts();
        $this->calculate_tax = $calculate_tax;
    }

    public function apply_coupon(Vcrf_Coupon $coupon)
    {
        $this->discounts->apply_coupon($coupon, false);
    }

    public function get_discounts()
    {
        add_filter('woocommerce_prices_include_tax', '__return_true', PHP_INT_MAX);
        $coupon_discount_amounts     = $this->discounts->get_discounts_by_coupon(true);
        $coupon_discount_tax_amounts = array();

        $amounts      = [];
        $taxes        = [];
        $summed_taxes = [];
        // See how much tax was 'discounted' per item and per coupon.
        foreach ($this->discounts->get_discounts(true) as $coupon_code => $coupon_discounts) {
            $coupon_discount_tax_amounts[$coupon_code] = 0;

            foreach ($coupon_discounts as $item_key => $coupon_discount) {
                $item = $this->get_discountable_item($item_key);

                if (empty($summed_taxes[$item_key])) {
                    $summed_taxes[$item_key] = 0;
                }

                if (empty($amounts[$item_key])) {
                    $amounts[$item_key] = 0;
                }

                if (empty($taxes[$item_key])) {
                    $taxes[$item_key] = [];
                }

                $amounts[$item_key] += $coupon_discount;

                if ($this->calculate_tax && $this->is_taxable($item)) {
                    // Item subtotals were sent, so set 3rd param.
                    $item_taxes = $this->calc_tax($coupon_discount, $item);
                    $item_tax   = wc_round_tax_total(array_sum($item_taxes), 0);

                    foreach ($item_taxes as $tax_id => $tax) {
                        if (empty($taxes[$item_key][$tax_id])) {
                            $taxes[$item_key][$tax_id] = $tax;
                        } else {
                            $taxes[$item_key][$tax_id] += $tax;
                        }
                    }

                    $summed_taxes[$item_key]                   += $item_tax;
                    $coupon_discount_tax_amounts[$coupon_code] += $item_tax;
                    // Remove tax from discount total.
                    if (wc_prices_include_tax()) {
                        $amounts[$item_key] -= $item_tax;
                    }
                }
            }
        }
        remove_filter('woocommerce_prices_include_tax', '__return_true', PHP_INT_MAX);

        $shipping_amounts = [];
        $shipping_total_taxes = [];
        $shipping_amount    = 0;
        $shipping_taxes     = [];
        $shipping_total_tax = 0;

        foreach (array_keys($amounts) as $item_key) {
            if (substr($item_key, 0, strlen(CartShippingService::SHIPPING_ITEM_KEY)) == CartShippingService::SHIPPING_ITEM_KEY) {
                $wcShippingId = str_replace(CartShippingService::SHIPPING_ITEM_KEY . ":", "", $item_key);
                $shipping_amounts[$wcShippingId] = $amounts[$item_key];
                $shipping_amount += $amounts[$item_key];
                unset($amounts[$item_key]);

                foreach ($taxes[$item_key] as $tax_id => $tax) {
                    if (empty($shipping_taxes[$tax_id])) {
                        $shipping_taxes[$tax_id] = 0;
                    }

                    $shipping_taxes[$tax_id] += $tax;
                }
                unset($taxes[$item_key]);

                $shipping_total_tax += $summed_taxes[$item_key];
                $shipping_total_taxes[$wcShippingId] = $summed_taxes[$item_key];
                unset($summed_taxes[$item_key]);
            }
        }

        $fees_amounts   = [];
        $fees_taxes     = [];
        $fees_total_tax = [];

        foreach (array_keys($amounts) as $item_key) {
            if ( ! in_array($item_key, array_keys($this->fees))) {
                continue;
            }

            $fees_amounts[$item_key] = $amounts[$item_key];
            unset($amounts[$item_key]);

            $fees_taxes[$item_key] = $taxes[$item_key];
            unset($taxes[$item_key]);

            $fees_total_tax[$item_key] = $summed_taxes[$item_key];
            unset($summed_taxes[$item_key]);
        }

        return wc_remove_number_precision_deep([
            'amounts'   => $amounts,
            'taxes'     => $taxes,
            'total_tax' => $summed_taxes,

            'shipping_amounts'    => $shipping_amounts,
            'shipping_total_taxes'    => $shipping_total_taxes,
            'shipping_amount'    => $shipping_amount,
            'shipping_taxes'     => $shipping_taxes,
            'shipping_total_tax' => $shipping_total_tax,

            'fees_amounts'   => $fees_amounts,
            'fees_taxes'     => $fees_taxes,
            'fees_total_tax' => $fees_total_tax,

            'coupon_discounts'     => $coupon_discount_amounts,
            'coupon_discounts_tax' => $coupon_discount_tax_amounts
        ]);
    }

    protected function get_default_item_props()
    {
        return (object)array(
            'type'               => 'product',
            'object'             => null,
            'tax_class'          => '',
            'taxable'            => false,
            'quantity'           => 0,
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

    protected function get_default_fees_props()
    {
        return (object)array(
            'type'               => 'fee',
            'object'             => null,
            'tax_class'          => '',
            'taxable'            => false,
            'quantity'           => 0,
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

    protected function get_discountable_items()
    {
        return array_filter($this->items + $this->shipping + $this->fees);
    }

    private function get_discountable_item($item_key)
    {
        $discounted_items = $this->get_discountable_items();

        if (isset($discounted_items[$item_key])) {
            return $discounted_items[$item_key];
        }

        return null;
    }

    private function is_taxable($item)
    {
        $is_taxable_product         = $item->type == 'product' && $item->product->is_taxable();
        $is_taxable_shipping_or_fee = in_array($item->type, ['shipping', 'fee']) && $item->taxable;

        return $is_taxable_product || $is_taxable_shipping_or_fee;
    }

    private function calc_tax($coupon_discount, $item)
    {
        switch ($item->type) {
            case 'shipping':
                return $this->calc_shipping_tax($coupon_discount, $item);
            default:
                return $this->calc_product_tax($coupon_discount, $item);
        }

        // Taxes - if not an array and not set to false, calc tax based on cost and passed calc_tax variable. This saves shipping methods having to do complex tax calculations.
//        if ( ! is_array( $taxes ) && false !== $taxes && $total_cost > 0 && $this->is_taxable() ) {
//            $taxes = 'per_item' === $args['calc_tax'] ? $this->get_taxes_per_item( $args['cost'] ) : WC_Tax::calc_shipping_tax( $total_cost, WC_Tax::get_shipping_tax_rates() );
//        }
    }

    private function calc_shipping_tax($coupon_discount, $item)
    {
        $taxes = WC_Tax::calc_inclusive_tax($coupon_discount, $item->tax_rates);

        return apply_filters('woocommerce_calc_shipping_tax', $taxes, $coupon_discount, $item->tax_rates);
    }

    private function calc_product_tax($coupon_discount, $item)
    {
        return WC_Tax::calc_tax($coupon_discount, $item->tax_rates, wc_prices_include_tax());
    }
}