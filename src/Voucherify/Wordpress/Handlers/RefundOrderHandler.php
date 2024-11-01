<?php

namespace Voucherify\Wordpress\Handlers;


use Exception;
use WC_Order;

class RefundOrderHandler
{
    /**
     * @var VoucherHandlersFacade
     */
    private $voucherifyHandler;

    public function __construct(VoucherHandlersFacade $voucherifyHandler) {
        $this->voucherifyHandler = $voucherifyHandler;
    }

    public function setupHooks()
    {
        add_action('woocommerce_order_fully_refunded', [
            $this,
            'on_after_order_refund'
        ], 10, 1);

        add_action('woocommerce_order_partially_refunded', [
            $this,
            'on_after_order_refund'
        ], 10, 1);

        add_action('woocommerce_admin_order_item_headers', [
            $this,
            'add_partial_refund_rollback_checkbox'
        ], 10, 1);
    }

    /**
     * Callback function for hook after the order is fully refunded.
     * If voucherify api response code is 400 (voucher is rolled back) refund is applied without an error
     *
     * @param  string  $order_id
     *
     * @throws Exception
     */
    public function on_after_order_refund($order_id)
    {
        $this->voucherifyHandler->cancel($order_id);
    }

    /**
     * Adds partial refund rollback checkbox
     *
     * @param  WC_Order  $order
     *
     */
    public function add_partial_refund_rollback_checkbox(WC_Order $order)
    {
        if (is_voucherify_rollback_enabled() && $this->isVoucherRedeemed($order)) {
            vcrf_include_partial("admin-order-item-partial-refund-rollback.php",
                ['remaining_refund_amount' => $order->get_remaining_refund_amount()]);
        }
    }

    private function isVoucherRedeemed($order)
    {
        foreach ($this->voucherifyHandler->getAllFromOrder($order) as $applied_voucher) {
            if ($applied_voucher->isRedeemed()) {
                return true;
            }
        }

        return false;
    }
}
