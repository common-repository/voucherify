<?php

namespace Voucherify\Wordpress\Handlers;

class CancelOrderHandler
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
        add_action('woocommerce_order_status_cancelled', [
            $this,
            'onStatusCancel'
        ], 20, 1);
    }

    public function onStatusCancel($orderId) {
        $order = wc_get_order($orderId);

        if ($order instanceof \WC_Order) {
            $this->voucherifyHandler->cancel($orderId);
            $this->voucherifyHandler->releaseVouchersSessionsFromOrder($order);
        }
    }
}