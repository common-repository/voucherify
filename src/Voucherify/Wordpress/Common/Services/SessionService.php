<?php

namespace Voucherify\Wordpress\Common\Services;

use Voucherify\Wordpress\Common\Models\Voucher;
use WC_Cart;

abstract class SessionService
{
    private const DISCOUNTS_SESSION_KEY = '_vcrf_discounts';
    const MAIN_CART_KEY = 'main';

    private function clearDiscounts()
    {
        WC()->session->set(self::DISCOUNTS_SESSION_KEY, []);
    }

    public static function setDiscounts(WC_Cart $cart, array $discounts)
    {
        $cartDiscounts = WC()->session->get(self::DISCOUNTS_SESSION_KEY, []);
        $cartDiscounts[static::getCartKey($cart)] = $discounts;
        WC()->session->set(self::DISCOUNTS_SESSION_KEY, $cartDiscounts);
    }

    public static function getDiscounts(WC_Cart $cart)
    {
        $discounts = WC()->session->get(self::DISCOUNTS_SESSION_KEY, []);

        if (isset($discounts[static::getCartKey($cart)])) {
            return $discounts[static::getCartKey($cart)];
        }

        return [];
    }

    /**
     * @return Voucher[]
     */
    public function getAppliedVouchers(WC_Cart $cart)
    {
        return $this->getAppliedVouchersForCartKey(static::getCartKey($cart));
    }

    private function getAppliedVouchersForCartKey($cartKey) {
        $appliedVouchers = $this->load();

        if (isset($appliedVouchers[$cartKey])) {
            return $appliedVouchers[$cartKey];
        }

        return [];
    }

    public function clearAppliedVouchers(WC_Cart $cart) {
        $appliedVouchers = $this->load();
        if (empty($appliedVouchers)) {
            $appliedVouchers = [];
        }

        unset($appliedVouchers[static::getCartKey($cart)]);

        $this->save($appliedVouchers);
    }

    /**
     * @param Voucher[] $appliedVouchers
     */
    public function setAppliedVouchers(WC_Cart $cart, array $appliedVouchers)
    {
        $allVouchers = $this->load();
        $allVouchers[static::getCartKey($cart)] = $appliedVouchers;
        $this->save($allVouchers);
    }

    public function applyVoucher(WC_Cart $cart, Voucher $voucher)
    {
        $appliedVouchers                      = $this->load();
        $appliedVouchers[static::getCartKey($cart)][$voucher->getCode()] = $voucher;
        $this->save($appliedVouchers);
        do_action('vcrf_voucher_added_to_cart', $voucher->getCode(), $cart);
    }

    public function removeVoucher(string $code)
    {
        $appliedVouchers = $this->load();

        if ( ! isset($appliedVouchers[static::MAIN_CART_KEY][$code])) {
            return;
        }

        $voucher = $appliedVouchers[static::MAIN_CART_KEY][$code];

        do_action('vcrf_voucher_removed', $code, $voucher->getSessionKey());

        foreach($appliedVouchers as &$cartVouchers) {
            unset($cartVouchers[$code]);
        }

        $this->save($appliedVouchers);
        $this->clearDiscounts();
    }

    public function isVoucherApplied(WC_Cart $cart, $code)
    {
        $appliedVouchers = $this->load();
        return isset($appliedVouchers[static::getCartKey($cart)][$code]);
    }

    public function getSessionKey($code)
    {
        $appliedVouchers = $this->load();
        if (isset($appliedVouchers[static::MAIN_CART_KEY][$code])) {
            return $appliedVouchers[static::MAIN_CART_KEY][$code]->getSessionKey();
        }

        return '';
    }

    protected abstract function save(array $vouchers);

    protected abstract function load();

    public static function getCartKey(WC_Cart $cart)
    {
        if ( ! empty($cart->recurring_cart_key)) {
            return $cart->recurring_cart_key;
        }

        return static::MAIN_CART_KEY;
    }
}