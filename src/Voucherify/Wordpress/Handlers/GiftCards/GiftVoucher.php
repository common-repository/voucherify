<?php

namespace Voucherify\Wordpress\Handlers\GiftCards;


use Voucherify\Wordpress\Common\Models\Voucher;

class GiftVoucher extends Voucher
{
    private $requestedAmount;

    public static function createFromValidationResult($validationResult)
    {
        return new GiftVoucher(
            $validationResult->code,
            $validationResult->code,
            $validationResult->order->total_discount_amount / 100,
            static::createApplicableItemsList($validationResult->applicable_to->data ?? []),
            $validationResult->session->key ?? '',
            '',
            $validationResult->session->ttl ?? '');
    }

    public static function createFromRedemptionResult($redemptionResult)
    {
        return new GiftVoucher(
            $redemptionResult->voucher->code,
            $redemptionResult->voucher->code,
            $redemptionResult->order->total_discount_amount / 100,
            static::createApplicableItemsList($redemptionResult->voucher->applicable_to->data ?? []),
            $redemptionResult->session->key ?? '',
            $redemptionResult->id);
    }

    public function __construct(
        string $code,
        string $display_name,
        float $amount,
        array $applicable_items,
        string $session_key,
        string $redemptionId,
        int $ttl = 0,
        float $requestedAmount = .0
    ) {
        parent::__construct($code, $display_name, $amount, $applicable_items, $session_key, $redemptionId, [], $ttl);

        $this->requestedAmount = $requestedAmount;
    }

    public function setRequestedAmount($requestedAmount)
    {
        $this->requestedAmount = $requestedAmount;
    }

    public function getRequestedAmount()
    {
        return $this->requestedAmount;
    }
}