<?php

namespace Voucherify\Wordpress\Handlers\Promotions;

use Voucherify\Wordpress\Common\Services\OrderVoucherMetaService;
use WC_Order;

class PromotionOrderVoucherMetaService extends OrderVoucherMetaService
{

    public function __construct()
    {
        add_filter('vcrf_order_applied_vouchers', [$this, 'getAppliedVouchersFilter'], 20, 2);
    }

    protected function save(WC_Order $order, array $vouchers)
    {
        $order->add_meta_data('_vcrf_applied_promotions', base64_encode(serialize($vouchers ?? [])), true);
        $order->save_meta_data();
    }

    protected function load(WC_Order $order)
    {
        $unserialized = unserialize(base64_decode($order->get_meta('_vcrf_applied_promotions', true)));
        if (empty($unserialized)) {
            return [];
        }

        return $unserialized;
    }
}