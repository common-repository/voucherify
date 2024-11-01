<?php

namespace Voucherify\Wordpress\Common\Models;

use WC_Product;

class DiscountedOrderItem
{
    /**
     * @var WC_Product
     */
    protected $wcProduct;

    /**
     * @var  int
     */
    private $quantity;

    /**
     * @var  string
     */
    private $effect;

    public function __construct($wcProduct, $quantity, $effect)
    {
        $this->wcProduct = $wcProduct;
        $this->quantity  = $quantity;
        $this->effect    = $effect;
    }

    /**
     * @return int
     */
    public function getQuantity()
    {
        return $this->quantity;
    }

    /**
     * @return WC_Product
     */
    public function getWCProduct()
    {
        return $this->wcProduct;
    }

    /**
     * @return string
     */
    public function getEffect(): string
    {
        return $this->effect;
    }
}