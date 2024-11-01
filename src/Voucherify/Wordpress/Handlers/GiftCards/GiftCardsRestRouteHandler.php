<?php

namespace Voucherify\Wordpress\Handlers\GiftCards;

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
class GiftCardsRestRouteHandler
{
    /** @var InfoService */
    private $infoService;
    /** @var CartService */
    private $cartService;

    public function __construct(InfoService $infoService, CartService $cartService)
    {
        $this->infoService = $infoService;
        $this->cartService = $cartService;
    }

    public function applyGiftCard(\WP_REST_Request $request)
    {
        define('VCRF_APPLY_VOUCHER', true);
        $code = $request->get_param('code') ?? '';
        $code = urldecode($code);
        $amount = $request->get_param('amount') ?? '';

        $cart_total = doubleval(WC()->cart->get_total('edit'));
        $amount = intval($amount) * 100;
        $amount = min($amount, intval(round($cart_total * 100)));

        $result = $this->cartService->add($code, $amount);
        /** @var Notification $result */
        if ($result->isSuccess()) {
            return rest_ensure_response(
                [
                    'message' => $result->getMessage(),
                    'code' => $result->getVoucher()->getCode(),
                    'amount' => $result->getVoucher()->getAmount(),
                    'type' => 'discount',
                    'applied_vouchers' => $this->getAppliedGiftCards()
                ]
            );
        } else {
            return new \WP_REST_Response(
                ['message' => $result->getMessage()],
                404
            );
        }
    }

    public function listGiftCards()
    {
        return rest_ensure_response(['applied_gift_cards' => $this->getAppliedGiftCards()]);
    }

    private function getAppliedGiftCards() {
        /** @var Voucher[] $appliedVouchers */
        $appliedVouchers = $this->cartService->getAppliedVouchers(WC()->cart);
        return array_map(function (Voucher $voucher) {
            return $voucher->getAmount();
        }, $appliedVouchers);
    }

    public function removeGiftCard(\WP_REST_Request $request)
    {
        $code = $request->get_param('code') ?? '';
        $code = urldecode($code);

        $vouchers = $this->getAppliedGiftCards();

        if (empty($vouchers[$code])) {
            return rest_ensure_response(
                [
                    'message' => sprintf(__('Coupon code %s was not applied.', 'voucherify'), $code),
                    'code' => $code,
                    'applied_vouchers' => $this->getAppliedGiftCards()
                ]
            );
        }

        if (!empty($code)) {
            $this->cartService->remove($code);
        }

        return rest_ensure_response(
            [
                'message' => sprintf(__('Coupon code %s removed.', 'voucherify'), $code),
                'code' => $code,
                'applied_vouchers' => $this->getAppliedGiftCards()
            ]
        );
    }
}
