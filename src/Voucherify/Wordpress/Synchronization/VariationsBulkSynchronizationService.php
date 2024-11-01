<?php

namespace Voucherify\Wordpress\Synchronization;

use Voucherify\Wordpress\ClientExtension;
use Voucherify\Wordpress\Models\ProductModel;
use Voucherify\Wordpress\Synchronization\Services\SkuService;

class VariationsBulkSynchronizationService extends BulkCsvSynchronizationService
{
    /** @var ProductModel */
    private $productModel;

    /**
     * @param ProductModel $productModel
     */
    public function __construct(
        ProductModel $productModel,
        ClientExtension $voucherifyClient
    )
    {
        parent::__construct(
            $voucherifyClient->getApiId(),
            $voucherifyClient->getApiKey(),
            $voucherifyClient->getApiUrl()
        );
        $this->productModel = $productModel;
    }


    protected function getColumnMapping()
    {
        return [
            'product_id' => 'product_id',
            'source_id' => 'source_id',
            'sku' => 'sku',
            'attributes' => 'attributes',
            'price' => 'price',
            'image_url' => 'image_url',
        ];
    }

    protected function getEndpoint()
    {
        return "/v1/skus/importCSV";
    }

    protected function getDatabaseRowsData($offset, $limit)
    {
        $products = $this->productModel->getVariationsListForExport($offset, $limit);

        $ids = array_map(function($product) {
            return $product['id'];
        }, $products);
        $rawVariantAttributes = $this->productModel->getVariantsAttributes($ids);
        $variantAttributes = [];
        foreach($rawVariantAttributes as $attribute) {
            $variantAttributes[$attribute->id][$attribute->name] = $attribute->value;
        }

        return array_map(function($product) use ($variantAttributes) {
            $imageUrl = $product['thumbnail_url'] ?? $product['image_url'] ?? null;
            if (!empty($imageUrl)) {
                $imageUrl = wp_upload_dir()['baseurl'] . '/' . $imageUrl;
            }

            return [
                'product_id' => createVcrfProductSourceId($product['product_id']),
                'source_id' => createVcrfVariantSourceId($product['id']),
                'sku' => $product['name'],
                'attributes' => json_encode($variantAttributes[$product['id']], JSON_UNESCAPED_SLASHES),
                'price' => $product['price'],
                'image_url' => $imageUrl,
            ];
        }, $products);
    }

    protected function markSynced($updatingRows)
    {
        global $wpdb;
        $rowsChunked = array_chunk($updatingRows, 500);

        foreach ($rowsChunked as $chunk) {
            $chunkedRowsValues = array_map(function($product) use ($wpdb) {
                return $wpdb->prepare("(%d, %s, %s)", [$product['source_id'], SkuService::VCRF_ID_META_KEY_NAME, 'BULK']);
            }, $chunk);

            $wpdb->query(
                "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) values "
                . join(", ", $chunkedRowsValues)
            );
        }
    }
}
