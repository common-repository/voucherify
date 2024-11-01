<?php

namespace Voucherify\Wordpress\Synchronization\Services;

use Voucherify\ClientException;
use Voucherify\Products;
use Voucherify\Wordpress\Common\Helpers\Commons;
use Voucherify\Wordpress\Synchronization\TooManyRequestsException;
use WC_Product;

class ProductService
{
    public const VCRF_ID_META_KEY_NAME = "_vcrf_prod_id";

    /** @var Products */
    private $products;
    /** @var \WC_Logger */
    private $logger;
    /** @var Commons */
    private $commons;

    public function __construct(Products $products)
    {
        $this->products = $products;
        $this->commons = new Commons();
        $this->logger = wc_get_logger();
    }

    /**
     * @throws TooManyRequestsException
     */
    public function save(WC_Product $product)
    {
        try {
            $vcrfProductResponse = $this->upsert($product);

            $this->commons->addVcrfSyncMetadata($product, $vcrfProductResponse->id, static::VCRF_ID_META_KEY_NAME);
        } catch (ClientException $e) {
            $this->logger->error(__("Couldn't synchronize product: {$e->getMessage()}", 'voucherify'),
                ['source' => 'voucherify']);
            $this->logger->error($e->getTraceAsString(), ['source' => 'voucherify']);

            if (stripos($e->getMessage(), 'Too many requests') !== false) {
                throw new TooManyRequestsException($e);
            }
        }
    }

    /**
     * Deletes the Voucherify product.
     *
     * @param WC_Product $product
     *
     * @throws TooManyRequestsException
     */
    public function delete(WC_Product $product)
    {
        try {
            $this->products->delete(createVcrfProductSourceId($product->get_id()));
        } catch (ClientException $e) {
            $this->logger->error(__("Couldn't delete product: {$e->getMessage()}", 'voucherify'),
                ['source' => 'voucherify']);
            $this->logger->error($e->getTraceAsString(), ['source' => 'voucherify']);

            if (stripos($e->getMessage(), 'Too many requests') !== false) {
                throw new TooManyRequestsException($e);
            }
        }
    }

    private function upsert(WC_Product $product)
    {
        $productToConvert = $product;
        if ($product->is_type(['variation', 'subscription_variation'])) {
            $productToConvert = wc_get_product($product->get_parent_id());
        }

        $vcrfProduct = $this->commons->convertWcProductToVcrfProduct($productToConvert);

        return $this->products->create($vcrfProduct);
    }
}