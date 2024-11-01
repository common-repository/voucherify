<?php

namespace Voucherify\Wordpress\Common\Services;


use Exception;
use Voucherify\ClientException;
use Voucherify\Wordpress\Common\Models\Voucher;
use WC_Order;

abstract class OrderService
{
    /** @var ValidationService */
    protected $validationService;
    /** @var RedemptionService */
    protected $redemption_service;
    /** @var SessionService */
    protected $session;
    /** @var OrderMetaService */
    protected $orderMetaService;
    /** @var ResponseCacheService */
    protected $responseCacheService;

    /**
     * @param OrderMetaService $orderMetaService
     * @param ValidationService $validationService
     * @param SessionService $session
     * @param RedemptionService $redemptionService
     */
    public function __construct(
        OrderMetaService $orderMetaService,
        ValidationService $validationService,
        SessionService $session,
        RedemptionService $redemptionService
    ) {
        $this->orderMetaService = $orderMetaService;
        $this->validationService = $validationService;
        $this->session = $session;
        $this->redemption_service = $redemptionService;
        $this->responseCacheService = new ResponseCacheService();
    }

    public function refund(WC_Order $order)
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
                $this->remove($applied_voucher->getCode());
                $redeemed[] = $applied_voucher->getCode();
            }
        }
        $order->add_order_note(sprintf(count($redeemed) > 1
            ? __('Voucherify: %s were rolled back', 'voucherify')
            : __('Voucherify: %s was rolled back', 'voucherify'),
            implode(", ", $redeemed)));
    }

    /**
     * @param $code
     *
     * @return Voucher|null
     */
    public function remove($code)
    {
        $order = wc_get_order($_POST['order_id']);

        return $this->orderMetaService->removeVoucher($order, $code);
    }

    public function moveVouchersFromSessionToOrder(WC_Order $order)
    {
        if (empty($order->get_id()) && empty($this->orderMetaService->getAppliedVouchers($order))) {
            $appliedVouchers = $this->session->getAppliedVouchers();
            $this->orderMetaService->setAppliedVouchers($order, $appliedVouchers);
            $this->session->setAppliedVouchers([]);

            array_walk($appliedVouchers, function(Voucher $voucher) {
                $this->responseCacheService->removeResponseFromCache($voucher->getCode());
            });

            $order->save_meta_data();
        }
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

    /**
     * @param  WC_Order  $order
     * @param  Voucher[]  $vouchers
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
                'type'     => 'LOCK',
                'ttl'      => get_option('voucherify_lock_ttl', 7),
                'ttl_unit' => 'DAYS'
            ]
        ];
        if ( ! empty($sessionKey)) {
            $sessionData['session']['key'] = $sessionKey;
        }

        return $sessionData;
    }
}

