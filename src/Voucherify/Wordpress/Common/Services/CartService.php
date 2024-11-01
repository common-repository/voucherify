<?php

namespace Voucherify\Wordpress\Common\Services;

use Voucherify\Wordpress\Common\Models\Voucher;
use WC_Cart;

abstract class CartService
{
    /**
     * @var ValidationService
     */
    private $validationService;

    /** @var SessionService $session */
    protected $session;

    /**
     * @param  SessionService  $session
     */
    public function __construct(
        SessionService $session,
        ValidationService $validation_service
    ) {
        $this->session           = $session;
        $this->validationService = $validation_service;
    }

    public function remove($code)
    {
        $this->session->removeVoucher($code);
    }

    /**
     * @return Voucher[]
     */
    public function getAppliedVouchers(WC_Cart $cart)
    {
        return $this->session->getAppliedVouchers($cart);
    }

    public function revalidate(WC_Cart $cart)
    {
        $revalidated_vouchers = [];
        foreach ($this->session->getAppliedVouchers($cart) as $code => $applied_voucher) {
            $context           = $this->createValidationContext($applied_voucher, $cart);
            $validationResult = $this->validationService->validate($code, $context);
            if ($validationResult->valid) {
                $revalidated_vouchers[$code] = $this->createFromValidationResult($validationResult);
            }
        }
        $this->session->setAppliedVouchers($cart, $revalidated_vouchers);
    }

    abstract protected function createValidationContext(Voucher $voucher, WC_Cart $cart);

    /**
     * @param  array  $validationResult
     *
     * @return Voucher
     */
    abstract protected function createFromValidationResult($validationResult);
}

