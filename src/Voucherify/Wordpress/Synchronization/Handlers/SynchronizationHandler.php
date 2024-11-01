<?php

namespace Voucherify\Wordpress\Synchronization\Handlers;

use Voucherify\ApiClient;
use Voucherify\Wordpress\ClientExtension;
use Voucherify\Wordpress\Common\Helpers\Commons;
use Voucherify\Wordpress\Handlers\DiscountVouchers\DiscountVoucherOrderVoucherMetaService;
use Voucherify\Wordpress\Handlers\GiftCards\GiftCardOrderVoucherMetaService;
use Voucherify\Wordpress\Handlers\Promotions\PromotionOrderVoucherMetaService;
use Voucherify\Wordpress\Models\AttributeModel;
use Voucherify\Wordpress\Models\CustomerModel;
use Voucherify\Wordpress\Models\ProductModel;
use Voucherify\Wordpress\Synchronization\CustomersBulkSynchronizationService;
use Voucherify\Wordpress\Synchronization\OrdersBulkSynchronizationService;
use Voucherify\Wordpress\Synchronization\ProductsBulkSynchronizationService;
use Voucherify\Wordpress\Synchronization\Services\CustomerService;
use Voucherify\Wordpress\Synchronization\Services\OrderService;
use Voucherify\Wordpress\Synchronization\Services\ProductService;
use Voucherify\Wordpress\Synchronization\Services\SkuService;
use Voucherify\Wordpress\Synchronization\VariationsBulkSynchronizationService;

class SynchronizationHandler
{
    /**
     * @var SynchronizationService
     */
    private $synchronizationService;
    /**
     * @var ProductListener
     */
    private $productListener;

    /** @var SkuListener */
    private $skuListener;

    /**
     * @var CustomerListener
     */
    private $customerListener;
    /**
     * @var OrderListener
     */
    private $orderListener;

    public function __construct(ClientExtension $voucherifyClient)
    {
        $customerService = new CustomerService($voucherifyClient->customers);
        $productService = new ProductService($voucherifyClient->products);
        $skuService = new SkuService($voucherifyClient->products);
        $orderService = new OrderService($voucherifyClient->orders);

        $productModel = new ProductModel();
        $apiClient = new ApiClient(
                $voucherifyClient->getApiId(),
                $voucherifyClient->getApiKey(),
                null,
                $voucherifyClient->getApiUrl(),
                $voucherifyClient->getCustomHeaders()
        );
        $this->synchronizationService = new SynchronizationService(
                new CustomersBulkSynchronizationService(new CustomerModel(), $voucherifyClient),
                new ProductsBulkSynchronizationService($productModel, new AttributeModel(), $voucherifyClient),
                new VariationsBulkSynchronizationService($productModel, $voucherifyClient),
                new OrdersBulkSynchronizationService($apiClient, new Commons())
        );
        $this->customerListener = new CustomerListener($customerService);
        $this->productListener = new ProductListener($productService, $skuService);
        $this->skuListener = new SkuListener($skuService);
        $this->orderListener = new OrderListener(
                new DiscountVoucherOrderVoucherMetaService(),
                new GiftCardOrderVoucherMetaService,
                new PromotionOrderVoucherMetaService,
                $orderService
        );
    }

    public function setupHooks()
    {
        if (is_admin() && $this->synchronizationService->countDesynchronized() > 0) {
            add_action('admin_notices', [$this, 'desynchronizedAdminNotice']);
        }

        add_action('woocommerce_new_product', [
            $this->productListener,
            'onProductUpdate'
        ], 10, 2);

        add_action('woocommerce_update_product', [
            $this->productListener,
            'onProductUpdate'
        ], 10, 2);

        add_action('woocommerce_delete_product', [
            $this->productListener,
            'onProductDelete'
        ], 10, 1);

        add_action('woocommerce_new_product_variation', [
            $this->skuListener,
            'onUpsert'
        ], 10, 2);

        add_action('woocommerce_save_product_variation', [
            $this->skuListener,
            'onUpsert'
        ], 10, 2);

        add_action('woocommerce_before_delete_product_variation', [
            $this->skuListener,
            'onDelete'
        ], 10, 1);

        add_action('delete_post', [
            $this->productListener,
            'onProductDelete'
        ], 10, 2);

        add_action('profile_update', [$this->customerListener, 'onUserUpdate'], 10, 3);
        add_action('user_register', [$this->customerListener, 'onUserCreate'], 10, 2);

        add_action('woocommerce_new_customer', [
            $this->customerListener,
            'onCustomerUpdate'
        ], 10, 2);

        add_action('woocommerce_update_customer', [
            $this->customerListener,
            'onCustomerUpdate'
        ], 10, 2);

        add_action('delete_user', [
            $this->customerListener,
            'onCustomerDelete'
        ], 10, 1);

        add_action('woocommerce_after_order_object_save', [
            $this->orderListener,
            'afterOrderSave'
        ], 10, 1);

        add_action('woocommerce_delete_order', [
            $this->orderListener,
            'onDelete'
        ], 10, 1);

        add_action('woocommerce_trash_order', [
            $this->orderListener,
            'onDelete'
        ], 10, 1);

        add_action('admin_init', [$this, 'maybeResynchronize']);
    }

    public function maybeResynchronize()
    {
        if ($_REQUEST['page'] === 'voucherify-settings'
                && in_array('yes', [$_REQUEST['resync'] ?? 'no', $_REQUEST['sync'] ?? 'no'])) {

            if ('yes' === $_REQUEST['resync'] ?? 'no') {
                $this->synchronizationService->desynchronize();
            }

            $this->synchronizationService->synchronize();

            wp_redirect(admin_url('admin.php?page=voucherify-settings'));
        }
    }

    function desynchronizedAdminNotice()
    {
        ?>
        <div id="voucherify_desynchronized_notice" class="notice notice-error">
            <p><?php _e('<strong>Voucherify</strong> is <strong>desynchronized</strong>.', 'voucherify'); ?>
                <?php _e('Synchronize manually in the Voucherify Options.', 'voucherify'); ?></p>
        </div>
        <?php
    }
}
