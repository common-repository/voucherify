<?php

namespace Voucherify\Wordpress\Handlers\Promotions;

use Voucherify\Wordpress\Common\Services\SessionService;

class PromotionSessionService extends SessionService
{
    /**
     * @var array
     */
    private static $appliedVouchers;

    protected function save(array $vouchers)
    {
        static::$appliedVouchers = $vouchers;
        WC()->session->set('_vcrf_applied_promotions', static::$appliedVouchers);
    }

    protected function load()
    {
        if (empty(static::$appliedVouchers)) {
            static::$appliedVouchers = WC()->session->get('_vcrf_applied_promotions', []);
        }

        return static::$appliedVouchers;
    }
}
