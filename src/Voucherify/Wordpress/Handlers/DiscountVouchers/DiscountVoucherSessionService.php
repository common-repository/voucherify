<?php

namespace Voucherify\Wordpress\Handlers\DiscountVouchers;

use Voucherify\Wordpress\Common\Services\SessionService;

class DiscountVoucherSessionService extends SessionService
{
    /**
     * @var array
     */
    private static $appliedVouchers;

    protected function save(array $vouchers)
    {
        static::$appliedVouchers = $vouchers;
        WC()->session->set('_vcrf_applied_discount_vouchers', static::$appliedVouchers);
    }

    protected function load()
    {
        if (empty(static::$appliedVouchers)) {
            static::$appliedVouchers = WC()->session->get('_vcrf_applied_discount_vouchers', []);
        }

        return static::$appliedVouchers;
    }
}
