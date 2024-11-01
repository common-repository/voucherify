<?php

namespace Voucherify\Wordpress\Common\Services;


use Exception;
use Voucherify\ClientException;
use Voucherify\Wordpress\Common\Models\Voucher;
use WC_Cart;
use WC_Order;

abstract class OrderVoucherService
{
    /** @var ValidationService */
    protected $validationService;
    /** @var RedemptionService */
    protected $redemption_service;
    /** @var SessionService */
    protected $session;
    /** @var OrderVoucherMetaService */
    protected $orderMetaService;
    /** @var ResponseCacheService */
    protected $responseCacheService;

    /**
     * @param OrderVoucherMetaService $orderMetaService
     * @param ValidationService $validationService
     * @param SessionService $session
     * @param RedemptionService $redemptionService
     */
    public function __construct(
        OrderVoucherMetaService $orderMetaService,
        ValidationService $validationService,
        SessionService $session,
        RedemptionService $redemptionService
    ) {
        $this->orderMetaService     = $orderMetaService;
        $this->validationService    = $validationService;
        $this->session              = $session;
        $this->redemption_service   = $redemptionService;
        $this->responseCacheService = new ResponseCacheService();
    }

    public function cancel(WC_Order $order)
    {
        if ( ! is_voucherify_rollback_enabled()) {
            return;
        }
        $redeemed = [];
        foreach ($this->getAppliedVouchers($order) as $applied_voucher) {
            if ($applied_voucher->isRedeemed()) {
                try {
                    $this->redemption_service->rollback_redemption($applied_voucher->getRedemptionId());
                } catch (ClientException $e) {
                    if ($e->getCode() !== 400) {
                        throw new Exception(__('Voucherify: ' . $e->getMessage()));
                    }

                    return;
                }
                $this->remove($order, $applied_voucher->getCode());
                $redeemed[] = $applied_voucher->getCode();
            }
        }

        if (!empty($redeemed)) {
            $order->add_order_note(sprintf(count($redeemed) > 1
                ? __('Voucherify: %s were rolled back', 'voucherify')
                : __('Voucherify: %s was rolled back', 'voucherify'),
                implode(", ", $redeemed)));
        }
    }

    /**
     * @param $code
     *
     * @return Voucher|null
     */
    public function remove(WC_Order $order, $code)
    {
        return $this->orderMetaService->removeVoucher($order, $code);
    }

    public function moveVouchersFromSessionToOrder(WC_Order $order, WC_Cart $cart)
    {
        $appliedVouchers = $this->session->getAppliedVouchers($cart);
        $this->orderMetaService->setAppliedVouchers($order, $appliedVouchers);

        $order->save_meta_data();
    }

    /**
     * @param $order WC_Order
     *
     * @return Voucher[]
     */
    public function getAppliedVouchers(WC_Order $order)
    {
        return $this->orderMetaService->getAppliedVouchers($order);
    }

    public function isVoucherApplied(WC_Order $order, $code)
    {
        $appliedVouchers = $this->getAppliedVouchers($order);

        return isset($appliedVouchers[$code]);
    }

    /**
     * @param WC_Order $order
     * @param Voucher[] $vouchers
     */
    public function setAppliedVouchers(WC_Order $order, array $vouchers)
    {
        $this->orderMetaService->setAppliedVouchers($order, $vouchers);
    }

    /**
     * @param $sessionKey
     *
     * @return array
     */
    protected function createSessionData($sessionKey)
    {
        $sessionData = [
            'session' => [
                'type' => 'LOCK',
            ]
        ];

        if ( ! empty($sessionKey)) {
            $sessionData['session']['key'] = $sessionKey;
        }

        return $sessionData;
    }

    public function clearVouchersSession()
    {
        if (empty(WC()->cart)) {
            return;
        }

        $mainCart  = WC()->cart;
        $carts = [$mainCart];
        if (!empty($mainCart->recurring_carts)) {
            $carts = array_merge($carts, $mainCart->recurring_carts);
        }

        $appliedVouchers = $this->session->getAppliedVouchers($mainCart);
        array_walk($appliedVouchers, function (Voucher $voucher) {
            $this->responseCacheService->removeResponseFromCache($voucher->getCode());
        });

        array_walk($carts, [$this->session, 'clearAppliedVouchers']);
    }
}

