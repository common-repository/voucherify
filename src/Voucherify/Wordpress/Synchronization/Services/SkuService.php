<?php

namespace Voucherify\Wordpress\Synchronization\Services;

use Voucherify\ClientException;
use Voucherify\Products;
use Voucherify\Wordpress\Common\Helpers\Commons;
use Voucherify\Wordpress\Synchronization\TooManyRequestsException;
use WC_Product;
use WC_Product_Variation;

class SkuService
{
    public const VCRF_ID_META_KEY_NAME = "_vcrf_sku_id";

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
            $this->logger->error(__("Couldn't synchronize sku: {$e->getMessage()}", 'voucherify'),
                ['source' => 'voucherify']);
            $this->logger->error($e->getTraceAsString(), ['source' => 'voucherify']);

            if (stripos($e->getMessage(), 'Too many requests') !== false) {
                throw new TooManyRequestsException($e);
            }
        }
    }

    public function saveFromParent(\WC_Product_Variable $product)
    {
        $variations = array_map('wc_get_product', $product->get_children());
        array_walk($variations, [$this, 'save']);
    }

    /**
     * @throws TooManyRequestsException
     */
    public function remove(WC_Product_Variation $variation)
    {
        try {
            $this->products->deleteSku(
                createVcrfProductSourceId($variation->get_parent_id()),
                createVcrfVariantSourceId($variation->get_id())
            );
        } catch (ClientException $exception) {
            $this->logger->error(__("Couldn't remove sku: {$exception->getMessage()}", 'voucherify'),
                ['source' => 'voucherify']);
            $this->logger->error($exception->getTraceAsString(), ['source' => 'voucherify']);

            if (stripos($exception->getDetails(), 'Too many requests') !== false) {
                throw new TooManyRequestsException($exception);
            }
        }
    }

    /**
     * @throws ClientException
     */
    private function upsert(WC_Product $skuProduct)
    {
        $vcrfSku = $this->commons->convertToVcrfSku($skuProduct);

        if ($skuProduct->is_type(['variation', 'subscription_variation'])) {
            $parentProductVcrfId = createVcrfProductSourceId($skuProduct->get_parent_id());
        } else {
            $parentProductVcrfId = createVcrfProductSourceId($skuProduct->get_id());
        }

        return $this->products->createSku($parentProductVcrfId, $vcrfSku);
    }
}
