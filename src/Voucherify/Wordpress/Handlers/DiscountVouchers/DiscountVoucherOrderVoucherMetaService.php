<?php

namespace Voucherify\Wordpress\Handlers\DiscountVouchers;

use Voucherify\Wordpress\Common\Services\OrderVoucherMetaService;
use WC_Order;

class DiscountVoucherOrderVoucherMetaService extends OrderVoucherMetaService
{
    public function __construct()
    {
        add_filter('vcrf_order_applied_vouchers', [$this, 'getAppliedVouchersFilter'], 10, 2);
    }

    protected function save(WC_Order $order, array $vouchers)
    {
        $order->add_meta_data('_vcrf_applied_discount_vouchers', base64_encode(serialize($vouchers ?? [])), true);
        $order->save_meta_data();
    }

    protected function load(WC_Order $order)
    {
        $unserialized = unserialize(base64_decode($order->get_meta('_vcrf_applied_discount_vouchers', true)));
        if (empty($unserialized)) {
            return [];
        }

        return $unserialized;
    }
}
