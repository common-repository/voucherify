<?php

namespace Voucherify\Wordpress\Handlers;

use Voucherify\Wordpress\ClientExtension;
use Voucherify\Wordpress\Common\Models\Notification;
use Voucherify\Wordpress\Common\Models\Voucher;
use Voucherify\Wordpress\Handlers\DiscountVouchers\VoucherifyDiscountVouchersHandler;
use Voucherify\Wordpress\Handlers\GiftCards\VoucherifyGiftCardsHandler;
use Voucherify\Wordpress\Handlers\Promotions\VoucherifyPromotionsHandler;
use WC_Cart;
use WC_Order;

class VoucherHandlersFacade
{
    /**
     * @var VoucherifyDiscountVouchersHandler
     */
    private $discountVouchersHandler;
    /**
     * @var VoucherifyPromotionsHandler
     */
    private $promotionsHandler;
    /**
     * @var VoucherifyGiftCardsHandler
     */
    private $giftCardsHandler;
    /**
     * @var ClientExtension
     */
    private $voucherifyClient;

    public function __construct(
        ClientExtension $voucherifyClient
    ) {
        $this->voucherifyClient        = $voucherifyClient;
        $this->discountVouchersHandler = new VoucherifyDiscountVouchersHandler($voucherifyClient);
        $this->promotionsHandler       = new VoucherifyPromotionsHandler($voucherifyClient);
        $this->giftCardsHandler        = new VoucherifyGiftCardsHandler($voucherifyClient);
    }

    public function setupHooks()
    {
        $this->discountVouchersHandler->setupHooks();
        $this->promotionsHandler->setupHooks();
        $this->giftCardsHandler->setupHooks();
    }

    public function revalidate(WC_Cart $cart)
    {
        $this->discountVouchersHandler->getCartService()->revalidate($cart);
        $this->promotionsHandler->getCartService()->revalidate($cart);
        $this->giftCardsHandler->getCartService()->revalidate($cart);
    }

    public function revalidateOrder(WC_Order $order) {
        $this->discountVouchersHandler->getOrderService()->revalidate($order);
        $this->promotionsHandler->getOrderService()->revalidate($order);
        $this->giftCardsHandler->getOrderService()->revalidate($order);
    }

    /**
     * @return Voucher[]
     */
    public function getDiscountVouchersFromCart(WC_Cart $cart)
    {
        return $this->discountVouchersHandler->getCartService()->getAppliedVouchers($cart)
               + $this->promotionsHandler->getCartService()->getAppliedVouchers($cart);
    }

    /**
     * @return Voucher[]
     */
    public function getGiftCardsFromCart(WC_Cart $cart)
    {
        return $this->giftCardsHandler->getCartService()->getAppliedVouchers($cart);
    }

    public function redeem(WC_Order $order)
    {
        $this->discountVouchersHandler->getOrderService()->redeem($order);
        $this->promotionsHandler->getOrderService()->redeem($order);
        $this->giftCardsHandler->getOrderService()->redeem($order);
    }

    /**
     * @param $code
     * @param $order
     *
     * @return Notification
     */
    public function addToOrder($code, WC_Order $order, $giftCardAmount = null)
    {
        if (strncmp('promo', $code, 5) === 0) {
            return $this->promotionsHandler->getOrderService()->add($code, $order);
        } elseif (!empty($giftCardAmount)) {
            return $this->giftCardsHandler->getOrderService()->add($code, $giftCardAmount, $order);
        } else {
            return $this->discountVouchersHandler->getOrderService()->add($code, $order);
        }
    }

    /**
     * @param $code
     *
     * @return bool
     */
    public function removeFromOrder(WC_Order $order, $code)
    {
        $isSuccessfullyRemoved =
            ! empty($this->discountVouchersHandler->getOrderService()->remove($order, $code)) ||
            ! empty($this->promotionsHandler->getOrderService()->remove($order, $code)) ||
            ! empty($this->giftCardsHandler->getOrderService()->remove($order, $code));

        $order->calculate_totals();

        return $isSuccessfullyRemoved;
    }

    /**
     * @param  WC_Order  $order
     *
     * @return Voucher[]
     */
    public function getDiscountVouchersFromOrder(WC_Order $order)
    {
        return $this->discountVouchersHandler->getOrderService()->getAppliedVouchers($order)
               + $this->promotionsHandler->getOrderService()->getAppliedVouchers($order);
    }

    /**
     * @param  WC_Order  $order
     *
     * @return Voucher[]
     */
    public function getGiftCardsFromOrder(WC_Order $order)
    {
        return $this->giftCardsHandler->getOrderService()->getAppliedVouchers($order);
    }

    /**
     * @param  WC_Order  $order
     *
     * @return Voucher[]
     */
    public function getAllFromOrder(WC_Order $order)
    {
        return $this->getGiftCardsFromOrder($order) + $this->getDiscountVouchersFromOrder($order);
    }

    public function cancel($orderId)
    {
        $order = wc_get_order($orderId);
        if ( ! empty($order)) {
            $this->discountVouchersHandler->getOrderService()->cancel($order);
            $this->promotionsHandler->getOrderService()->cancel($order);
            $this->giftCardsHandler->getOrderService()->cancel($order);
        }
    }

    public function moveVouchersFromSessionToOrder(WC_Cart $cart, WC_Order $order)
    {
        $this->discountVouchersHandler->getOrderService()->moveVouchersFromSessionToOrder($order, $cart);
        $this->promotionsHandler->getOrderService()->moveVouchersFromSessionToOrder($order, $cart);
        $this->giftCardsHandler->getOrderService()->moveVouchersFromSessionToOrder($order, $cart);
    }

    public function releaseVouchersSessionsFromOrder(WC_Order $order)
    {
        $this->releaseDiscountVouchersSessionsFromOrder($order);
        $this->releasePromotionsSessionsFromOrder($order);
        $this->releaseGiftCardsSessionsFromOrder($order);
    }

    private function releaseDiscountVouchersSessionsFromOrder(WC_Order $order) {
        $appliedVouchers = $this->discountVouchersHandler->getOrderService()->getAppliedVouchers($order);
        $this->releaseVoucherSession($appliedVouchers);
        $this->discountVouchersHandler->getOrderService()->setAppliedVouchers($order, $appliedVouchers);
    }

    private function releasePromotionsSessionsFromOrder(WC_Order $order) {
        $appliedVouchers = $this->promotionsHandler->getOrderService()->getAppliedVouchers($order);
        $this->releaseVoucherSession($appliedVouchers);
        $this->promotionsHandler->getOrderService()->setAppliedVouchers($order, $appliedVouchers);
    }

    private function releaseGiftCardsSessionsFromOrder(WC_Order $order) {
        $appliedVouchers = $this->giftCardsHandler->getOrderService()->getAppliedVouchers($order);
        $this->releaseVoucherSession($appliedVouchers);
        $this->giftCardsHandler->getOrderService()->setAppliedVouchers($order, $appliedVouchers);
    }

    /**
     * @param Voucher[] $appliedVouchers
     *
     * @return void
     */
    private function releaseVoucherSession(array $appliedVouchers) {
        foreach ($appliedVouchers as $voucher) {
            $sessionKey = $voucher->getSessionKey();
            $this->voucherifyClient->release_session_lock($voucher->getCode(), $sessionKey);
            $voucher->setSessionKey('');
            $voucher->setTtl(0);
        }
    }

    public function getDiscountVouchersHandler()
    {
        return $this->discountVouchersHandler;
    }

    public function getPromotionsHandler()
    {
        return $this->promotionsHandler;
    }

    public function getGiftCardsHandler()
    {
        return $this->giftCardsHandler;

    }
}
