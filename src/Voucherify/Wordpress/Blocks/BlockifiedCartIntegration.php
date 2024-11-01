<?php

namespace Voucherify\Wordpress\Blocks;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

/**
 * Class for integrating with WooCommerce Blocks
 */
class BlockifiedCartIntegration implements IntegrationInterface
{
    private const VOUCHERIFY_BLOCKIFIED_CART_VERSION = '0.1.0';

    /**
     * The name of the integration.
     *
     * @return string
     */
    public function get_name()
    {
        return 'voucherify-blockified-cart';
    }

    /**
     * When called invokes any initialization/setup for the integration.
     */
    public function initialize()
    {
        $this->registerFrontendScripts();
        $this->registerBlockEditorScripts();
        $this->registerMainIntegration();
    }

    /**
     * Registers the main JS file required to add filters and Slot/Fills.
     */
    public function registerMainIntegration()
    {
        $scriptPath = '/assets/js/dist/index.js';

        $scriptUrl = plugins_url($scriptPath, VOUCHERIFY_INDEX);

        $script_asset_path = dirname(VOUCHERIFY_INDEX)
            . '/src-generated/Voucherify/Wordpress/Blocks/index.asset.php';
        $script_asset = file_exists($script_asset_path)
            ? require $script_asset_path
            : array(
                'dependencies' => array(),
                'version' => $this->get_file_version($scriptPath),
            );

        wp_register_script(
            'voucherify-blockified-cart-integration',
            $scriptUrl,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );
    }

    /**
     * Returns an array of script handles to enqueue in the frontend context.
     *
     * @return string[]
     */
    public function get_script_handles()
    {
        return array('voucherify-blockified-cart-integration', 'voucherify-blockified-cart-frontend');
    }

    /**
     * Returns an array of script handles to enqueue in the editor context.
     *
     * @return string[]
     */
    public function get_editor_script_handles()
    {
        return array('voucherify-blockified-cart-integration');
    }

    /**
     * An array of key, value pairs of data made available to the block on the client side.
     *
     * @return array
     */
    public function get_script_data()
    {
        $data = array(
            'voucherify-blockified-cart-active' => true,
            'example-data' => __('This is some example data from the server', 'voucherify'),
            'optInDefaultText' => __('I want to receive updates about products and promotions.', 'voucherify'),
        );

        return $data;

    }

    public function registerBlockEditorScripts()
    {
        $script_path = '/assets/js/dist/index.js';
        $script_url = plugins_url($script_path, VOUCHERIFY_INDEX);
        $script_asset_path = dirname(VOUCHERIFY_INDEX)
            . '/src-generated/Voucherify/Wordpress/Blocks/index.asset.php';
        $script_asset = file_exists($script_asset_path)
            ? require $script_asset_path
            : array(
                'dependencies' => array(),
                'version' => $this->get_file_version($script_asset_path),
            );

        wp_register_script(
            'voucherify-blockified-cart-editor',
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );
    }

    public function registerFrontendScripts()
    {
        $style_path  = 'assets/js/dist/view.css';
		$style_url  = plugins_url( $style_path, VOUCHERIFY_INDEX );

        $script_path = 'assets/js/dist/view.js';
        $script_url = plugins_url($script_path, VOUCHERIFY_INDEX);
        $script_asset_path = dirname(VOUCHERIFY_INDEX)
            . 'src-generated/Voucherify/Wordpress/Blocks/view.asset.php';
        $script_asset = file_exists($script_asset_path)
            ? require $script_asset_path
            : array(
                'dependencies' => array(),
                'version' => $this->get_file_version($script_asset_path),
            );

        wp_register_script(
            'voucherify-blockified-cart-frontend',
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );

        wp_set_script_translations('voucherify-blockified-cart-frontend', 'voucherify', dirname(VOUCHERIFY_INDEX) . '/i18n/languages');

        wp_enqueue_style(
            'voucherify-blockified-cart-frontend',
            $style_url,
            [],
            $this->get_file_version($style_path)
        );
    }

    /**
     * Get the file modified time as a cache buster if we're in dev mode.
     *
     * @param string $file Local path to the file.
     * @return string The cache buster value to use for the given file.
     */
    protected function get_file_version($file)
    {
        if (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG && file_exists($file)) {
            return filemtime($file);
        }
        return static::VOUCHERIFY_BLOCKIFIED_CART_VERSION;
    }
}
