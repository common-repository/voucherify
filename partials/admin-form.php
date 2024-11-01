<?php
/**
 * Created by PhpStorm.
 * User: robert
 * Date: 20.01.18
 * Time: 13:01
 */

use Voucherify\Wordpress\Handlers\Admin\AdminSettings;
use Voucherify\Wordpress\Voucherify;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<script>
    var vcrfSettings = {
        appId: '<?php echo esc_js( get_option( 'voucherify_app_id' ) ); ?>',
        appSecretKey: '<?php echo esc_js( get_option( 'voucherify_app_secret_key' ) ); ?>',
    };
</script>
<div class="wrap">
	<form method="post" id="voucherify-settings-form" action="<?php echo admin_url( 'options.php' ); ?>">
		<?php settings_fields( AdminSettings::OPTIONS_GROUP_NAME ); ?>
		<?php do_settings_sections( AdminSettings::OPTIONS_GROUP_NAME ); ?>

		<h1 class="wp-heading-inline"><?php _e( 'Voucherify Options', 'voucherify' ); ?></h1>
		<table class="form-table">
			<tbody>
			<tr>
				<th scope="row" colspan="2">
					<input type="checkbox" name="voucherify_enabled" id="voucherify_enabled"
					       value="yes" <?php checked( get_option( 'voucherify_enabled', 'yes' ), 'yes', true ); ?>/>
					<label for="voucherify_enabled">
						<?php _e( 'Voucherify integration enabled', 'voucherify' ); ?>
					</label>
				</th>
			</tr>
			<tr>
				<th scope="row"><label for="voucherify_app_id"><?php _e( 'App ID', 'voucherify' ); ?></label></th>
				<td><input name="voucherify_app_id"
				           type="text"
				           id="voucherify_app_id"
				           value="<?php echo esc_attr( get_option( 'voucherify_app_id' ) ); ?>"
				           class="regular-text code"></td>
			</tr>
			<tr>
				<th scope="row">
					<label for="voucherify_app_secret_key">
						<?php _e( 'App Secret Key', 'voucherify' ); ?>
					</label>
				</th>
				<td><input name="voucherify_app_secret_key"
				           type="text"
				           id="voucherify_app_secret_key"
				           value="<?php echo esc_attr( get_option( 'voucherify_app_secret_key' ) ); ?>"
				           class="regular-text code"></td>
			</tr>

            <tr>
                <th scope="row">
                    <label for="voucherify_api_endpoint">
                        <?php _e( 'Api Endpoint', 'voucherify' ); ?>
                    </label>
                </th>
                <td>
                    <input name="voucherify_api_endpoint"
                           type="text"
                           id="voucherify_api_endpoint"
                           value="<?php echo esc_attr( voucherify()->get_api_endpoint() ); ?>"
                           class="regular-text code">
                    <div><?php _e( "More info about endpoints:", 'voucherify' ); ?>
                            <a href="<?php echo Voucherify::VCRF_ENDPOINTS_INFO; ?>" target="_blank">
                                <?php echo Voucherify::VCRF_ENDPOINTS_INFO; ?></a>
                    </div>
                </td>
            </tr>

            <tr>
                <th scope="row" colspan="2">
                    <input type="checkbox" name="voucherify_rollback_enabled" id="voucherify_rollback_enabled"
                           value="yes" <?php checked( get_option( 'voucherify_rollback_enabled', 'yes' ), 'yes', true ); ?>/>
                    <label for="voucherify_rollback_enabled">
						<?php _e( 'Voucherify rollback enabled', 'voucherify' ); ?>
                    </label>
                </th>
            </tr>
            <tr>
                <th scope="row">
                    <label for="voucherify_lock_ttl">
						<?php
                        _e( 'Length of coupon validity time window after application', 'voucherify' );
                        ?>
                    </label>
                </th>
                <td><input name="voucherify_lock_ttl"
                           type="number"
                           min="1"
                           id="voucherify_lock_ttl"
                           value="<?php echo esc_attr( get_option( 'voucherify_lock_ttl', 7 ) ); ?>"
                           class="regular-text code">
                    <select id="voucherify_lock_ttl_unit" name="voucherify_lock_ttl_unit">
                        <option value="DAYS" <?php echo selected('DAYS', get_option( 'voucherify_lock_ttl_unit', 'DAYS' )); ?>>
                            <?php _e( 'days', 'voucherify' ); ?></option>
                        <option value="HOURS" <?php echo selected('HOURS', get_option( 'voucherify_lock_ttl_unit', 'DAYS' )); ?>>
                            <?php _e( 'hours', 'voucherify' ); ?></option>
                        <option value="MINUTES" <?php echo selected('MINUTES', get_option( 'voucherify_lock_ttl_unit', 'DAYS' )); ?>>
                            <?php _e( 'minutes', 'voucherify' ); ?></option>
                    </select>
                </td>
            </tr>
			</tbody>
		</table>
        <?php if (class_exists(WC_Subscriptions::class)) : ?>
            <h2>Integration with
                <a href="https://woocommerce.com/products/woocommerce-subscriptions/" target="_blank">Woocommerce
                    Subscriptions</a><span class="dashicons dashicons-external"></span></h2>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row" colspan="2">
                            <input type="checkbox"
                                   name="voucherify_wc_subs_apply_on_renewals"
                                   id="voucherify_wc_subs_apply_on_renewals"
                                   value="yes" <?php checked( get_option( 'voucherify_wc_subs_apply_on_renewals', 'yes' ), 'yes', true ); ?>/>
                            <label for="voucherify_wc_subs_apply_on_renewals">
                                <?php _e( 'Apply coupons on renewal orders (up to their limits)', 'voucherify' ); ?>
                            </label>
                        </th>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>

		<?php
		/**
		 * Fires after options form's table on voucherify settings page.
		 */
		do_action( 'voucherify_options_form_after_table' );
		?>

        <div id="btns-when-no-keys-change" style="">

            <?php submit_button(null, 'primary', 'submit-btn'); ?>

            <div>
                <h3><?php _e( 'Synchronize', 'voucherify' ); ?></h3>
                <p>
                    <?php _e('Synchronize all products, SKUs, customers, and orders that have not been synchronized yet.', 'voucherify'); ?>
                </p>
                <a href="<?php echo esc_attr(admin_url() . 'admin.php?page=voucherify-settings&sync=yes'); ?>"
                   class="button" <?php disabled( ! get_option( 'voucherify_enabled', 'yes' ) ); ?>>
                    <?php _e( 'Synchronize', 'voucherify' ); ?>
                </a>
            </div>
            <br><br>
            <div>
                <h3><?php _e( 'Resynchronize all', 'voucherify' ); ?></h3>
                <p>
                    <?php _e('Resynchronize all existing products, SKUs, customers, and orders, including those that have been already synchronized.', 'voucherify'); ?>
                </p>
                <p>
                    <?php _e('This action may take several minutes.', 'voucherify'); ?>
                </p>
                <a href="<?php echo esc_attr(admin_url() . 'admin.php?page=voucherify-settings&resync=yes'); ?>"
                   class="button button-link-delete" <?php disabled( ! get_option( 'voucherify_enabled', 'yes' ) ); ?>>
                    <?php _e( 'Resynchronize all', 'voucherify' ); ?>
                </a>
            </div>
        </div>

        <div id="saving-btns-after-keys-change">
            <input type="hidden" name="resync" id="vcrf-force-resync" value="no" />
            <h1><?php _e('You have changed App ID or App Secret Key', 'voucherify'); ?></h1>
            <p><?php _e('If you have changed your App ID and/or App Secret Key, because you want to change a Voucherify project, resynchronize all your products, SKUs, customers, and orders to migrate this data to Voucherify.', 'voucherify'); ?></p>
            <p><?php _e('If you are not changing a Voucherify project, you do not have to resynchronize your data.', 'voucherify'); ?></p>
            <p>
                <?php _e('<strong>Warning: Resynchronization may take up to couple minutes</strong>', 'voucherify'); ?>
            </p>
            <button id="save-and-resynchronize" type="button" class="button button-primary"><?php _e( 'Save Changes without resynchronization', 'voucherify' ); ?></button>
            <button id="save-without-resynchronization" type="button" class="button button-delete"><?php _e( 'Save changes and resynchronize', 'voucherify' ); ?></button>
        </div>
	</form>
</div>
<script>
    (function($){
        $('#voucherify_app_id, #voucherify_app_secret_key').keyup(function(e) {
            if (vcrfSettings.appId !== $('#voucherify_app_id').val()
                || vcrfSettings.appSecretKey !== $('#voucherify_app_secret_key').val()) {
                $('#btns-when-no-keys-change').hide();
                $('#saving-btns-after-keys-change').show();
            } else {
                $('#btns-when-no-keys-change').show();
                $('#saving-btns-after-keys-change').hide();
            }
        });

        $("#save-and-resynchronize").click(function(e) {
            e.preventDefault();
            $("#vcrf-force-resync").val('yes');
            $("#voucherify-settings-form").submit();
        });

        $("#save-without-resynchronization").click(function(e) {
            e.preventDefault();
            $("#vcrf-force-resync").val('no');
            $("#voucherify-settings-form").submit();
        });
    })(jQuery);
</script>
