<?php

namespace Voucherify\Wordpress\Handlers\Admin;

class AdminSettings
{
    const OPTIONS_GROUP_NAME = 'voucherify-options';

    /**
     * Voucherify_Admin_Settings constructor.
     */
    function __construct()
    {
        add_action('admin_menu', [$this, 'add_menu_item']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    /**
     * Internal helper method to add a setting of given key to the right voucherify options group.
     *
     * @param $key string key
     */
    private function register_setting($key)
    {
        register_setting(self::OPTIONS_GROUP_NAME, $key);
    }

    /**
     * Register voucherify settings used in the plugin.
     */
    function register_settings()
    {
        $this->register_setting('voucherify_enabled');
        $this->register_setting('voucherify_app_id');
        $this->register_setting('voucherify_app_secret_key');
        $this->register_setting('voucherify_api_endpoint');
        $this->register_setting('voucherify_rollback_enabled');
        $this->register_setting('voucherify_lock_ttl');
        $this->register_setting( 'voucherify_lock_ttl_unit' );
        $this->register_setting( 'voucherify_wc_subs_apply_on_renewals' );
    }

    /**
     * Renders voucherify settings form if user's role is administrator.
     */
    function render_form()
    {
        if ( ! current_user_can('administrator')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        load_plugin_textdomain('voucherify', false, VOUCHERIFY_BASEDIR . '/i18n/languages');

        vcrf_include_partial('admin-form.php');
    }

    /**
     * Adds voucherify menu item to the admin main menu.
     */
    function add_menu_item()
    {
        add_menu_page('Voucherify API settings', 'Voucherify', 'administrator', 'voucherify-settings',
            [$this, 'render_form'],
            plugins_url(VOUCHERIFY_BASEDIR . '/assets/img/voucherify-icon.png'));
    }
}

