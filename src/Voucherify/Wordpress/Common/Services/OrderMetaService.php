<?php

namespace Voucherify\Wordpress\Common\Services;

use Voucherify\Wordpress\Common\Models\Voucher;
use WC_Order;

abstract class OrderMetaService
{

    public static function getAllAppliedVouchers(WC_Order $order)
    {
        return apply_filters('vcrf_order_applied_vouchers', [], $order);
    }

    public function getAppliedVouchersFilter(array $appliedVouchers, WC_Order $order)
    {
        return array_merge($appliedVouchers ?? [], $this->load($order) ?? []);
    }

    /**
     * @param  WC_Order  $order
     * @param  Voucher[]  $vouchers
     */
    public function setAppliedVouchers(WC_Order $order, array $vouchers)
    {
        $this->save($order, $vouchers);
    }

    public function getAppliedVouchers(WC_Order $order)
    {
        return $this->load($order);
    }

    /**
     * @param  WC_Order  $order
     * @param  string  $code
     *
     * @return Voucher|null
     */
    public function removeVoucher(WC_Order $order, string $code)
    {
        $appliedVouchers = $this->load($order);
        if ( ! isset($appliedVouchers[$code])) {
            return null;
        }
        $voucher = $appliedVouchers[$code];
        do_action('vcrf_voucher_removed', $code, $voucher->getSessionKey());
        unset($appliedVouchers[$code]);
        $this->save($order, $appliedVouchers);

        return $voucher;
    }

    protected abstract function save(WC_Order $order, array $vouchers);

    protected abstract function load(WC_Order $order);
}