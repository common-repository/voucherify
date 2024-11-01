<?php

namespace Voucherify\Wordpress\Common\Models;

use WC_Coupon;
use WC_Order_Item_Product;
use WC_Product;

class Vcrf_Coupon extends WC_Coupon
{
    private $applicable_items = [];

    public function __construct($data = '')
    {
        parent::__construct($data);

        $this->data['discount_type'] = 'fixed_cart';
    }


    public function set_applicable_items(array $items)
    {
        $this->applicable_items = $items;
    }

    public function is_valid_for_product($product, $values = array())
    {
        if (empty($this->applicable_items)) {
            return true;
        }

        $sku = $this->extractSku($values);
        $productId = $this->extractProductId($values);
        $vcrfProductId = $this->extractVcrfProductId($values);

        $possibleSourceIds = [$sku, $productId, $vcrfProductId];

        $applicableSourceIdsForProduct = array_intersect($possibleSourceIds, $this->applicable_items);

        return count($applicableSourceIdsForProduct) > 0;
    }

    private function extractSku($values) {
        $sku = null;

        if ($values instanceof WC_Order_Item_Product) {
            $sku = $values->get_product()->get_sku();
        }

        if (empty($sku) && isset($values['data']) && $values['data'] instanceof WC_Product) {
            $sku = $values['data']->get_sku();
        }

        if (empty($sku) && isset($values['sku'])) {
            $sku = $values['sku'];
        }

        return $sku;
    }

    private function extractProductId($values) {
        $productId = null;

        if ($values instanceof WC_Order_Item_Product) {
            $productId = $values->get_product()->get_id();
        }

        if (empty($productId) && isset($values['data']) && $values['data'] instanceof WC_Product) {
            $productId = $values['data']->get_id();
        }

        if (empty($productId) && isset($values['product_id'])) {
            $productId = $values['product_id'];
        }

        return $productId;
    }

    private function extractVcrfProductId($values) {
        $vcrfProductId = null;

        if ($values instanceof WC_Order_Item_Product) {
            $vcrfProductId = $values->get_product()->get_meta('_vcrf_prod_id', true);
        }

        if (empty($vcrfProductId) && isset($values['data']) && $values['data'] instanceof WC_Product) {
            $vcrfProductId = $values['data']->get_meta('_vcrf_prod_id', true);
        }

        return $vcrfProductId;
    }

    public function is_valid_for_cart()
    {
        if (empty($this->applicable_items)) {
            return parent::is_valid_for_cart();
        }

        return false;
    }


}