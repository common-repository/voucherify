<?php

namespace Voucherify\Wordpress\Common\Services;

use Voucherify\ClientException;
use Voucherify\Vouchers;
use Voucherify\Wordpress\Handlers\GiftCards\GiftVoucherViewModel;

class InfoService
{

    /**
     * @var Vouchers
     */
    public $vouchers;

    public function __construct($vouchers)
    {
        $this->vouchers = $vouchers;
    }

    public function getGiftVoucherInfo($code)
    {
        try {
            $voucher_info = $this->vouchers->get($code);
            if (($voucher_info->type ?? '') === 'GIFT_VOUCHER') {
                return new GiftVoucherViewModel($voucher_info->code, $voucher_info->gift->balance);
            }
        } catch (ClientException $e) {
            //Do nothing
        }

        return null;
    }
}

