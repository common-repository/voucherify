<?php

namespace Voucherify\Wordpress\Handlers\Promotions;

use Exception;
use Voucherify\Wordpress\Common\Models\Notification;
use Voucherify\Wordpress\Common\Models\Voucher;
use Voucherify\Wordpress\Common\Services\CartService;

class PromotionRestRouteHandler
{
    /** @var CartService */
    private $cartService;

    /** @var AllPromotionsFetcher */
    private $allPromotionsFetcher;

    /** @var ValidPromotionsFetcher */
    private $validPromotionsFetcher;

    public function __construct($validPromotionsFetcher, $allPromotionsFetcher, CartService $cartService)
    {
        $this->validPromotionsFetcher = $validPromotionsFetcher;
        $this->allPromotionsFetcher = $allPromotionsFetcher;
        $this->cartService = $cartService;
    }

    public function listAvailablePromotions() {
        $promotions = $this->allPromotionsFetcher->get_all_promotions();
        $available_promotions = $this->validPromotionsFetcher->get_valid_promotions();

        return rest_ensure_response([
            'all' => $promotions,
            'valid' => $available_promotions,
        ]);
    }

    public function apply(\WP_REST_Request $request)
    {
        try {
            /** @var Notification $result */
            $code = $request->get_param('code') ?? '';
            $code = urldecode($code);
            $result = $this->cartService->add($code);
            $result->print_notification();
            if ($result->isSuccess()) {
                return rest_ensure_response(
                    [
                        'message' => sprintf(
                            __('%s applied successfully.', 'voucherify'),
                            __('Promotion', 'voucherify')
                        ),
                        'code' => $result->getVoucher()->getCode(),
                        'amount' => $result->getVoucher()->getAmount(),
                        'name' => $result->getVoucher()->getDisplayName(),
                        'type' => 'promotion',
                        'applied_promotions' => $this->getAppliedPromotions()
                    ]
                );
            } else {
                return new \WP_REST_Response(
                    ['message' => $result->getMessage()],
                    403
                );
            }
        } catch (Exception $e) {
            return new \WP_REST_Response(
                ['message' => $e->getMessage()],
                403
            );
        }
    }

    public function listAppliedPromotions()
    {
        return rest_ensure_response(['applied_promotions' => $this->getAppliedPromotions()]);
    }

    public function removePromotion(\WP_REST_Request $request)
    {
        $code = $request->get_param('code') ?? '';
        $code = urldecode($code);

        $vouchers = $this->getAppliedPromotions();

        if (!isset($vouchers[$code])) {
            return rest_ensure_response(
                [
                    'message' => sprintf(__('Promotion %s was not applied.', 'voucherify'), $code),
                    'code' => $code,
                    'applied_promotions' => $this->getAppliedPromotions()
                ]
            );
        }

        if (!empty($code)) {
            $this->cartService->remove($code);
        }

        return rest_ensure_response(
            [
                'message' => sprintf(__('Promotion %s removed.', 'voucherify'), $code),
                'code' => $code,
                'applied_promotions' => $this->getAppliedPromotions()
            ]
        );
    }
    private function getAppliedPromotions() {
        /** @var Voucher[] $appliedVouchers */
        $appliedVouchers = $this->cartService->getAppliedVouchers(WC()->cart);
        return array_map(function (Voucher $voucher) {
            return [
                'name' => $voucher->getDisplayName(),
                'amount' => $voucher->getAmount()
            ];
        }, $appliedVouchers);
    }
}
