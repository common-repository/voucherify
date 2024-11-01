<?php

namespace Voucherify\Wordpress\Synchronization;

use Voucherify\Wordpress\ClientExtension;
use Voucherify\Wordpress\Models\AttributeModel;
use Voucherify\Wordpress\Models\ProductModel;
use Voucherify\Wordpress\Synchronization\Services\ProductService;

class ProductsBulkSynchronizationService extends BulkCsvSynchronizationService
{
    /** @var ProductModel */
    private $productModel;
    /** @var AttributeModel */
    private $attributeModel;

    private $attributesDefinitionCache = [];

    /**
     * @param ProductModel $productModel
     * @param AttributeModel $attributeModel
     */
    public function __construct(
        ProductModel $productModel,
        AttributeModel $attributeModel,
        ClientExtension $voucherifyClient
    )
    {
        parent::__construct(
            $voucherifyClient->getApiId(),
            $voucherifyClient->getApiKey(),
            $voucherifyClient->getApiUrl()
        );
        $this->productModel = $productModel;
        $this->attributeModel = $attributeModel;
    }


    protected function getColumnMapping()
    {
        return [
            'source_id' => 'source_id',
            'name' => 'name',
            'attributes' => 'attributes',
            'price' => 'price',
            'image_url' => 'image_url',
        ];
    }

    protected function getEndpoint()
    {
        return "/v1/products/importCSV";
    }

    protected function getDatabaseRowsData($offset, $limit)
    {
        $products = $this->productModel->getProductsListForExport($offset, $limit);

        return array_map(function($product) {
            $attributesListAsString = null;
            $attributes = unserialize($product['attributes']);
            if (!empty($attributes)) {
                $attributesNames = array_map(function($name) {
                    return str_replace('pa_', '', $name);
                }, array_keys($attributes));
                $attributesNamesToRetrieve = array_diff($attributesNames, array_keys($this->attributesDefinitionCache));

                if (!empty($attributesNamesToRetrieve)) {
                    $attributesDefinitions = $this->attributeModel->getAttributes($attributesNamesToRetrieve);

                    foreach ($attributesDefinitions as $attributeDefinition) {
                        $this->attributesDefinitionCache[$attributeDefinition->name] = $attributeDefinition->label;
                    }
                }

                $attributesList = [];
                foreach($attributesNames as $attributeName) {
                    if (!empty($this->attributesDefinitionCache[$attributeName])) {
                        $attributesList[] = $this->attributesDefinitionCache[$attributeName];
                    }
                }
                $attributesListAsString = join(",", $attributesList);
            }

            $imageUrl = $product['thumbnail_url'] ?? $product['image_url'] ?? null;
            if (!empty($imageUrl)) {
                $imageUrl = wp_upload_dir()['baseurl'] . '/' . $imageUrl;
            }

            return [
                'source_id' => createVcrfProductSourceId($product['id']),
                'name' => $product['name'],
                'attributes' => $attributesListAsString,
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
                return $wpdb->prepare("(%d, %s, %s)", [$product['source_id'], ProductService::VCRF_ID_META_KEY_NAME, 'BULK']);
            }, $chunk);

            $wpdb->query(
                "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) values "
                . join(", ", $chunkedRowsValues)
            );
        }
    }
}
