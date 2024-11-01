<?php

namespace Voucherify\Wordpress\Handlers\GiftCards;

use Voucherify\Wordpress\Common\Models\Voucher;
use WC_Order;

class GiftCardsTotalsCalculator
{
    /**
     * @param $giftVouchers Voucher[]
     *
     * @return float|int
     */
    public function calculate(array $giftVouchers)
    {
        $giftVouchersGrossSum = 0;
        /**
         * @var  GiftVoucher[] $giftVouchers
         */
        foreach ($giftVouchers as $giftVoucher) {
            $voucherGross         = $giftVoucher->getAmount();
            $giftVouchersGrossSum += $voucherGross;
        }

        return $giftVouchersGrossSum;
    }

    public function calculateAppliedGiftCardsTotal(WC_Order $order) {
        $orderMetaService = new GiftCardOrderVoucherMetaService();
        $giftVouchers = $orderMetaService->getAppliedVouchers($order);
        return $this->calculate($giftVouchers);
    }
}