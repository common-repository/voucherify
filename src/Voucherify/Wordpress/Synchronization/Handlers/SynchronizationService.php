<?php

namespace Voucherify\Wordpress\Synchronization\Handlers;

use Voucherify\Wordpress\Common\Helpers\Commons;
use Voucherify\Wordpress\Synchronization\CustomersBulkSynchronizationService;
use Voucherify\Wordpress\Synchronization\OrdersBulkSynchronizationService;
use Voucherify\Wordpress\Synchronization\ProductsBulkSynchronizationService;
use Voucherify\Wordpress\Synchronization\Services\CustomerService;
use Voucherify\Wordpress\Synchronization\Services\OrderService;
use Voucherify\Wordpress\Synchronization\Services\ProductService;
use Voucherify\Wordpress\Synchronization\Services\SkuService;
use Voucherify\Wordpress\Synchronization\VariationsBulkSynchronizationService;

class SynchronizationService
{
    /** @var CustomersBulkSynchronizationService */
    private $customersBulkSynchronizationService;
    /** @var ProductsBulkSynchronizationService */
    private $productsBulkSynchronizationService;
    /** @var VariationsBulkSynchronizationService */
    private $variationsBulkSynchronizationService;
    /** @var OrdersBulkSynchronizationService */
    private OrdersBulkSynchronizationService $ordersBulkSynchronizationService;

    public function __construct(
        CustomersBulkSynchronizationService $customersBulkSynchronizationService,
        ProductsBulkSynchronizationService $productsBulkSynchronizationService,
        VariationsBulkSynchronizationService $variationsBulkSynchronizationService,
        OrdersBulkSynchronizationService $ordersBulkSynchronizationService
    )
    {
        $this->customersBulkSynchronizationService = $customersBulkSynchronizationService;
        $this->productsBulkSynchronizationService = $productsBulkSynchronizationService;
        $this->variationsBulkSynchronizationService = $variationsBulkSynchronizationService;
        $this->ordersBulkSynchronizationService = $ordersBulkSynchronizationService;
    }

    public function synchronize()
    {
        $this->customersBulkSynchronizationService->synchronize();
        $this->productsBulkSynchronizationService->synchronize();
        $this->variationsBulkSynchronizationService->synchronize();
        $this->ordersBulkSynchronizationService->synchronize();
    }

    public function countDesynchronizedUsers()
    {
        global $wpdb;

        return $wpdb->get_var("SELECT count(*) FROM {$wpdb->usermeta} customers
                WHERE customers.meta_key = 'wp_capabilities'
                  AND customers.meta_value LIKE '%s:8:\"customer\";b:1;%'
                  AND NOT EXISTS (
                      SELECT 1 FROM {$wpdb->usermeta} vcrf_ids
                       WHERE vcrf_ids.user_id = customers.user_id
                         AND vcrf_ids.meta_key = '" . CustomerService::VCRF_ID_META_KEY_NAME . "'
                       LIMIT 1)
                  AND EXISTS (
                      SELECT 1 FROM {$wpdb->usermeta} bill_data
                       WHERE bill_data.user_id = customers.user_id
                         AND bill_data.meta_key like 'billing_%'
                       LIMIT 1)");
    }

    public function countDesynchronizedProducts()
    {
        global $wpdb;

        return $wpdb->get_var("SELECT count(*) FROM {$wpdb->posts} p
                WHERE p.post_type in ('product')
                  AND post_status = 'publish'
                AND NOT EXISTS (SELECT 1
                                FROM {$wpdb->postmeta} pm
                                WHERE pm.post_id = p.ID
                                  AND pm.meta_key = '" . ProductService::VCRF_ID_META_KEY_NAME . "'
                                LIMIT 1)");
    }

    public function countDesynchronizedProductVariations()
    {
        global $wpdb;

        return $wpdb->get_var("SELECT count(*) FROM {$wpdb->posts} p
                WHERE p.post_type in ('product_variation')
                  AND post_status = 'publish'
                AND NOT EXISTS (SELECT 1
                                FROM {$wpdb->postmeta} pm
                                WHERE pm.post_id = p.ID
                                  AND pm.meta_key = '" . SkuService::VCRF_ID_META_KEY_NAME . "'
                                LIMIT 1)");
    }

    public function countDesynchronizedOrders()
    {
        global $wpdb;

        return $wpdb->get_var("SELECT count(*) FROM {$wpdb->posts} p
                WHERE p.post_type = 'shop_order'
                  AND post_status NOT IN ('trash', 'auto-draft', 'draft', 'wc-checkout-draft')
                AND NOT EXISTS (SELECT 1
                                FROM {$wpdb->postmeta} pm
                                WHERE pm.post_id = p.ID
                                  AND pm.meta_key = '" . OrderService::VCRF_ID_META_KEY_NAME . "'
                                LIMIT 1)");
    }

    public function countDesynchronized()
    {
        return $this->countDesynchronizedUsers()
            + $this->countDesynchronizedOrders()
            + $this->countDesynchronizedProducts()
            + $this->countDesynchronizedProductVariations();
    }

    public function desynchronize()
    {
        global $wpdb;

        $wpdb->delete($wpdb->postmeta, ['meta_key' => Commons::VOUCHERIFY_LAST_SYNC_GMT]);
        $wpdb->delete($wpdb->postmeta, ['meta_key' => CustomerService::VCRF_ID_META_KEY_NAME]);
        $wpdb->delete($wpdb->postmeta, ['meta_key' => OrderService::VCRF_ID_META_KEY_NAME]);
        $wpdb->delete($wpdb->postmeta, ['meta_key' => ProductService::VCRF_ID_META_KEY_NAME]);
        $wpdb->delete($wpdb->postmeta, ['meta_key' => SkuService::VCRF_ID_META_KEY_NAME]);

        $wpdb->delete($wpdb->usermeta, ['meta_key' => CustomerService::VCRF_ID_META_KEY_NAME]);
        $wpdb->delete($wpdb->usermeta, ['meta_key' => Commons::VOUCHERIFY_LAST_SYNC_TIMESTAMP]);
    }
}
