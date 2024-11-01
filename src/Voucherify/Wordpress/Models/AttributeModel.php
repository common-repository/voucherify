<?php

namespace Voucherify\Wordpress\Models;

class AttributeModel
{

    public function getAttributes(array $attributesKeys)
    {
        global $wpdb;

        $whereInPattern = array_fill(0, count($attributesKeys), "%s");
        $whereInPattern = join(",", $whereInPattern);

        $stmt = $wpdb->prepare(
            "SELECT attribute_name as name, "
            . "attribute_label as label "
            . "FROM {$wpdb->prefix}woocommerce_attribute_taxonomies "
            . "WHERE attribute_name in ({$whereInPattern})",
            $attributesKeys
        );

        return $wpdb->get_results($stmt);
    }
}
