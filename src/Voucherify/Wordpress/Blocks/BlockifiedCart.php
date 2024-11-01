<?php

namespace Voucherify\Wordpress\Blocks;

class BlockifiedCart
{
    /** @var self */
    private static $instance = null;

    public static function getInstance()
    {
        if (static::$instance === null) {
            static::$instance = new self();
        }

        return static::$instance;
    }

    public static function setupHooks()
    {
        $blockifiedCart = self::getInstance();

        add_action('woocommerce_blocks_loaded', [$blockifiedCart, 'onBlocksLoadedHook']);
        add_action('block_categories_all', [$blockifiedCart, 'registerBlock'], 10, 2);
        add_filter(
            '__experimental_woocommerce_blocks_add_data_attributes_to_namespace',
            [$blockifiedCart, 'addNamespaceToAllowed'],
            10,
            1
        );
    }

    public function addNamespaceToAllowed($allowed_namespaces)
    {
        $allowed_namespaces[] = 'voucherify';
        return $allowed_namespaces;
    }

    public function onBlocksLoadedHook()
    {
        $blockifiedCart = self::getInstance();
        add_action('woocommerce_blocks_cart_block_registration', [$blockifiedCart, 'addIntegrationToRegistry']);
        add_action('woocommerce_blocks_checkout_block_registration', [$blockifiedCart, 'addIntegrationToRegistry']);
    }

    public function addIntegrationToRegistry($integrationRegistry)
    {
        $integrationRegistry->register(new BlockifiedCartIntegration());
    }

    public function registerBlock($categories)
    {
        return array_merge(
            $categories,
            [
                [
                    'slug' => 'voucherify-blockified-cart',
                    'title' => __('Voucherify Coupons Forms', 'voucherify-blockified-cart'),
                ],
            ]
        );
    }
}
