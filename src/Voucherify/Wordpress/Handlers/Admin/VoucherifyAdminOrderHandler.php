<?php

namespace Voucherify\Wordpress\Handlers\Admin;


use Voucherify\Wordpress\Common\Services\OrderVoucherMetaService;
use Voucherify\Wordpress\Handlers\VoucherHandlersFacade;
use WC_Order;
use WP_REST_Request;

/**
 * Created by PhpStorm.
 * User: robert
 * Date: 06.02.2018
 * Time: 06:30
 */
class VoucherifyAdminOrderHandler
{
    /**
     * @var VoucherHandlersFacade
     */
    private $voucherifyHandler;

    public function __construct(
        VoucherHandlersFacade $voucherifyHandler
    ) {
        $this->voucherifyHandler = $voucherifyHandler;
    }

    public function setupHooks()
    {
        add_action('woocommerce_order_item_add_action_buttons', [
            $this,
            'displayButtonAddCoupon'
        ]);
        add_action('woocommerce_admin_order_totals_after_shipping', [
            $this,
            'displayListCoupons'
        ], 10, 1);

        add_filter('woocommerce_order_item_display_meta_key', [
            $this,
            'displayItemMetaKey'
        ], 10, 3);

        add_filter('woocommerce_rest_pre_insert_shop_subscription', [
            $this,
            'beforeRestOrderInsert'
        ], 20, 2);

        add_filter('woocommerce_rest_pre_insert_shop_order_object', [
            $this,
            'beforeRestOrderInsert'
        ], 10, 2);

        add_action('wp_ajax_vcrf_admin_add_coupon', [$this, 'ajaxAddVoucher']);

        add_action('wp_ajax_vcrf_admin_remove_voucher', [
            $this,
            'ajaxRemoveVoucher'
        ], 20);
    }

    public function beforeRestOrderInsert(WC_Order $order, WP_REST_Request $request)
    {
        $order->calculate_totals();
        $coupon_lines = $request->get_param('coupon_lines') ?? [];
        foreach ($coupon_lines as $coupon_line) {
            $this->voucherifyHandler->addToOrder($coupon_line['code'], $order, $coupon_line['amount'] ?? null);
        }
        $request->set_param('coupon_lines', []);

        return $order;
    }

    public function ajaxAddVoucher()
    {
        $code   = $_POST['code'] ?? '';
        $order   = wc_get_order($_POST['order_id'] ?? '');
        $giftCardAmount = floatval($_POST['amount'] ?? '');

        if (empty($giftCardAmount)) {
            $giftCardDetails = apply_filters('vcrf_gift_card_details', null, $code);
            if (!empty($giftCardDetails)) {
                wp_send_json_success([
                    'code'    => $giftCardDetails->get_name(),
                    'type'    => 'gift',
                    'balance' => wc_format_decimal(
                        wc_remove_number_precision($giftCardDetails->get_available_balance()),
                        wc_get_price_decimals()
                    )
                ]);
            }
        }

        $result = $this->voucherifyHandler->addToOrder($code, $order, $giftCardAmount);

        $responseData = [
            'message' => $result->getMessage(),
            'code'    => $code
        ];

        if ($result->isSuccess()) {
            wp_send_json_success($responseData);
        } else {
            wp_send_json_error($responseData);
        }
    }

    public function ajaxRemoveVoucher()
    {
        $code = $_POST['code'] ?? '';
        $order = wc_get_order($_POST['order_id']);
        if ( ! empty($code)) {
            $success = $this->voucherifyHandler->removeFromOrder($order, $code);
            $success ? wp_send_json_success() : wp_send_json_error();
        }
    }

    public function displayListCoupons($order_id)
    {
        $order            = wc_get_order($order_id);
        $applied_vouchers = OrderVoucherMetaService::getAllAppliedVouchers($order);
        if ( ! empty($applied_vouchers)) {
            vcrf_include_partial('admin-order-list-coupons.php',
                [
                    'vouchers'       => $applied_vouchers,
                    'vcrf_admin_url' => admin_url('admin-ajax.php')
                ]);
        }
    }

    public function displayButtonAddCoupon()
    {
        vcrf_include_partial("admin-order-add-coupon.php");
    }


    public function displayItemMetaKey($key, $meta, $item)
    {
        if ($key === '_voucherify_discounted_price') {
            return 'Dicounted Price';
        } elseif ($key === '_voucherify_discounted_tax') {
            return 'Dicounted Tax';
        } elseif ($key === '_voucherify_original_price') {
            return 'Original Price';
        } elseif ($key === '_voucherify_original_tax') {
            return 'Original Tax';
        }

        return $key;
    }
}


