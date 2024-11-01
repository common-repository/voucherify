<?php

namespace Voucherify\Wordpress\Handlers\GiftCards;

use Voucherify\Wordpress\Common\Services\SessionService;

class GiftCardsSessionService extends SessionService
{
    /**
     * @var array
     */
    private static $appliedGiftCards;

    protected function save(array $vouchers)
    {
        static::$appliedGiftCards = $vouchers;
        WC()->session->set('_vcrf_applied_gift_cards', static::$appliedGiftCards);
    }

    protected function load()
    {
        if (empty(static::$appliedGiftCards)) {
            static::$appliedGiftCards = WC()->session->get('_vcrf_applied_gift_cards', []);
        }

        return static::$appliedGiftCards;
    }
}
