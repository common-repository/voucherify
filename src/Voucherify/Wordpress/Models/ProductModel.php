<?php

namespace Voucherify\Wordpress\Models;

use Voucherify\Wordpress\Synchronization\Services\ProductService;
use Voucherify\Wordpress\Synchronization\Services\SkuService;

class ProductModel
{
    public function getProductsListForExport($offset, $limit = 1000) {
        global $wpdb;

        $query = "SELECT p.ID as id,"
            . "sum(if(variation.ID is not null, 1, 0)) > 0 as has_variations,"
            . "sum(if(img.ID is not null, 1, 0)) > 0 as has_image,"
            . "p.post_title as name,"
            . "thumbnailsrc.meta_value as thumbnail_url,"
            . "min(price.meta_value) as price,"
            . "attrs.meta_value as attributes," // list names of attributes, not values (values mapping for sku only)
            . "min(imgsrc.meta_value) as image_url "
            . "FROM {$wpdb->posts} p "
            . "left join {$wpdb->posts} variation on variation.post_parent = p.ID and variation.post_type = 'product_variation' "
            . "left join {$wpdb->posts} img on img.post_parent = p.ID and img.post_type = 'attachment' "
            . "left join {$wpdb->postmeta} thumbnail on thumbnail.post_id = p.id and thumbnail.meta_key = '_thumbnail_id' "
            . "left join {$wpdb->posts} thumbnailimg on thumbnail.meta_value = thumbnailimg.ID and thumbnailimg.post_type = 'attachment' "
            . "left join {$wpdb->postmeta} thumbnailsrc on thumbnailsrc.post_id = thumbnailimg.ID and thumbnailsrc.meta_key = '_wp_attached_file' "
            . "left join {$wpdb->postmeta} price on price.post_id = p.id and price.meta_key = '_price' "
            . "left join {$wpdb->postmeta} attrs on attrs.post_id = p.id and attrs.meta_key = '_product_attributes' "
            . "left join {$wpdb->postmeta} imgsrc on imgsrc.post_id = img.id and imgsrc.meta_key = '_wp_attached_file' "
            . "WHERE p.post_type = 'product' and p.post_status = 'publish' "
            . "AND NOT EXISTS ("
            . "     SELECT 1 FROM {$wpdb->postmeta} pm"
            . "     WHERE pm.post_id = p.ID"
            . "     AND pm.meta_key = '" . ProductService::VCRF_ID_META_KEY_NAME . "' LIMIT 1"
            . ") "
            . "group by p.ID, p.post_title, thumbnailsrc.meta_value, attrs.meta_value "
            . "LIMIT $limit OFFSET $offset";
        return $wpdb->get_results(
            $query,
            ARRAY_A
        );
    }

    public function getVariationsListForExport($offset, $limit = 1000) {
        global $wpdb;

        $query = "SELECT p.ID as id,"
            . "p.post_parent as product_id,"
            . "sum(if(img.ID is not null, 1, 0)) > 0 as has_image,"
            . "thumbnailsrc.meta_value as thumbnail_url,"
            . "p.post_title as name,"
            . "min(price.meta_value) as price,"
            . "attrs.meta_value as attributes,"
            . "min(imgsrc.meta_value) as image_url "
            . "FROM {$wpdb->posts} p "
            . "left join {$wpdb->posts} img on img.post_parent = p.ID and img.post_type = 'attachment' "
            . "left join {$wpdb->postmeta} thumbnail on thumbnail.post_id = p.id and thumbnail.meta_key = '_thumbnail_id' "
            . "left join {$wpdb->posts} thumbnailimg on thumbnail.meta_value = thumbnailimg.ID "
            . "and thumbnailimg.post_type = 'attachment' "
            . "left join {$wpdb->postmeta} thumbnailsrc on thumbnailsrc.post_id = thumbnailimg.ID "
            . "and thumbnailsrc.meta_key = '_wp_attached_file' "
            . "left join {$wpdb->postmeta} price on price.post_id = p.id and price.meta_key = '_price' "
            . "left join {$wpdb->postmeta} attrs on attrs.post_id = p.id and attrs.meta_key = '_product_attributes' "
            . "left join {$wpdb->postmeta} imgsrc on imgsrc.post_id = img.id and imgsrc.meta_key = '_wp_attached_file' "
            . "WHERE p.post_type = 'product_variation' and p.post_status = 'publish' "
            . "AND NOT EXISTS ("
            . "     SELECT 1 FROM {$wpdb->postmeta} pm"
            . "     WHERE pm.post_id = p.ID"
            . "     AND pm.meta_key = '" . SkuService::VCRF_ID_META_KEY_NAME . "' LIMIT 1"
            . ") "
            . "group by p.ID, p.post_title, thumbnailsrc.meta_value, attrs.meta_value "
            . "LIMIT $limit OFFSET $offset";
        return $wpdb->get_results(
            $query,
            ARRAY_A
        );
    }

    public function getVariantsAttributes($ids)
    {
        global $wpdb;

        $pattern = array_fill(0, count($ids), '%d');
        $pattern = join(",", $pattern);
        $query = "select post_id as id, attribute_label as name, meta_value as value "
            . "from {$wpdb->postmeta} meta "
            . "join {$wpdb->prefix}woocommerce_attribute_taxonomies tax "
            . "on concat('attribute_pa_', tax.attribute_name) = meta.meta_key "
            . "and meta.meta_key like 'attribute_pa_%' where post_id in ($pattern)";

        $stmt = $wpdb->prepare($query, $ids);

        return $wpdb->get_results($stmt);
    }
}
