<?php

namespace Voucherify\Wordpress\Handlers\DiscountVouchers;

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;
use Voucherify\Wordpress\Common\Models\Notification;
use Voucherify\Wordpress\Common\Models\Voucher;
use Voucherify\Wordpress\Common\Services\CartService;
use Voucherify\Wordpress\Common\Services\InfoService;

/**
 * Created by PhpStorm.
 * User: robert
 * Date: 06.02.2018
 * Time: 06:30
 */
class DiscountVoucherRestRouteHandler
{
    /** @var InfoService */
    private $info_service;
    /** @var CartService */
    private $cart_service;

    public function __construct(InfoService $info_service, CartService $cart_service)
    {
        $this->info_service = $info_service;
        $this->cart_service = $cart_service;
    }

    /**
     * @throws RouteException
     */
    public function maybeAddVoucher(\WP_REST_Request $request)
    {
        define('VCRF_APPLY_VOUCHER', true);
        $code = $request->get_param('code') ?? '';
        $code = urldecode($code);

        if (empty($code)) {
            return new \WP_REST_Response(
                ['message' => __('Please provide a valid code.', 'voucherify')],
                400
            );
        }

        $giftCardDetails = apply_filters('vcrf_gift_card_details', null, $code);
        if (!empty($giftCardDetails)) {
            $decimalBalance = wc_format_decimal(
                wc_remove_number_precision($giftCardDetails->get_available_balance()),
                wc_get_price_decimals()
            );
            return rest_ensure_response(
                [
                    'code' => $giftCardDetails->get_name(),
                    'type' => 'gift',
                    'balance' => $decimalBalance,
                    'balance_localized' => wc_format_localized_decimal($decimalBalance)
                ]
            );
        }

        /** @var Notification $result */
        $result = $this->cart_service->add($code);
        if ($result->isSuccess()) {
            return rest_ensure_response(
                [
                    'message' => $result->getMessage(),
                    'code' => $result->getVoucher()->getCode(),
                    'amount' => $result->getVoucher()->getAmount(),
                    'type' => 'discount',
                    'applied_vouchers' => $this->getAppliedVouchers()
                ]
            );
        } else {
            return new \WP_REST_Response(
                ['message' => $result->getMessage()],
                404
            );
        }
    }

    public function listVouchers()
    {
        return rest_ensure_response(['applied_vouchers' => $this->getAppliedVouchers()]);
    }

    private function getAppliedVouchers() {
        /** @var Voucher[] $appliedVouchers */
        $appliedVouchers = $this->cart_service->getAppliedVouchers(WC()->cart);
        return array_map(function (Voucher $voucher) {
            return $voucher->getAmount();
        }, $appliedVouchers);
    }

    public function removeVoucher(\WP_REST_Request $request)
    {
        $code = $request->get_param('code') ?? '';
        $code = urldecode($code);

        $vouchers = $this->getAppliedVouchers();

        if (!isset($vouchers[$code])) {
            return rest_ensure_response(
                [
                    'message' => sprintf(__('Coupon code %s was not applied.', 'voucherify'), $code),
                    'code' => $code,
                    'applied_vouchers' => $this->getAppliedVouchers()
                ]
            );
        }

        if (!empty($code)) {
            $this->cart_service->remove($code);
        }

        return rest_ensure_response(
            [
                'message' => sprintf(__('Coupon code %s removed.', 'voucherify'), $code),
                'code' => $code,
                'applied_vouchers' => $this->getAppliedVouchers()
            ]
        );
    }
}
