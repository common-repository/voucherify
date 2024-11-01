<?php

namespace Voucherify\Wordpress\Synchronization\Handlers;

use Voucherify\Wordpress\Synchronization\Services\ProductService;
use Voucherify\Wordpress\Synchronization\Services\SkuService;
use Voucherify\Wordpress\Synchronization\TooManyRequestsException;
use WC_Product;

class ProductListener
{

    /**
     * @var ProductService
     */
    private $productService;
    /**
     * @var SkuService
     */
    private $skuService;

    public function __construct(ProductService $productService, SkuService $skuService)
    {
        $this->productService = $productService;
        $this->skuService = $skuService;
    }

    //prevents the second update
    private $isUpdated = false;

    /**
     * Callback function for hook after the product is saved.
     *
     * @param $product_id
     * @param WC_Product $product
     *
     */
    public function onProductUpdate($product_id, $product)
    {
        try {
            $status = get_post_status($product_id);
            if ($product->is_type(['simple', 'variable', 'variable-subscription', 'subscription'])
                && $status == 'publish' && ! $this->isUpdated) {
                $hasBeenSyncPreviously = ! empty($product->get_meta(ProductService::VCRF_ID_META_KEY_NAME));

                $this->productService->save($product);

                if ($product->is_type(['variable', 'variable-subscription']) && ! $hasBeenSyncPreviously) {
                    /** @var \WC_Product_Variable $product */
                    $this->skuService->saveFromParent($product);
                }

                $this->isUpdated = true;
            }
        } catch (TooManyRequestsException $exception) {
            wc_get_logger()->error('Voucherify API requests limit has been reached',
                ['source' => 'voucherify']);
        }
    }

    /**
     * Callback function for hook after the product is deleted.
     *
     * @param $product_id
     *
     */
    public function onProductDelete($productId)
    {
        $product = wc_get_product($productId);
        if ('product' === get_post_type($product)) {
            try {
                $this->productService->delete($product);
            } catch (TooManyRequestsException $exception) {
                wc_get_logger()->error('Voucherify API requests limit has been reached',
                    ['source' => 'voucherify']);
            }
        }
    }
}