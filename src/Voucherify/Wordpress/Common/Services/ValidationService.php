<?php

namespace Voucherify\Wordpress\Common\Services;

use Voucherify\Wordpress\Common\Helpers\CartDecorator;
use Voucherify\Wordpress\Common\Helpers\Commons;
use WC_Cart;
use WC_Order;

abstract class ValidationService
{

    /**
     * @var Commons
     */
    private $commons;

    public function __construct(
        SessionService $session
    ) {
        $this->session              = $session;
        $this->responseCacheService = new ResponseCacheService();
        $this->commons = new Commons();
    }

    /** @var SessionService */
    protected $session;
    /**
     * @var ResponseCacheService
     */
    protected $responseCacheService;

    abstract public function validate(string $code, array $context);

    public function createValidationContextForOrder(WC_Order $order)
    {
        $session_data  = $this->createSessionData();
        $order_data    = $this->commons->getOrderData($order);
        $customer_data = $this->commons->getCustomerData($order);

        return apply_filters('voucherify_validation_service_validation_context',
            $order_data + $customer_data + $session_data);
    }

    public function createValidationContextForCart(string $code, WC_Cart $cart)
    {
        $session_data = $this->createSessionData();
        $session_key  = $this->session->getSessionKey($code);
        if ( ! empty($session_key)) {
            $session_data['session']['key'] = $session_key;
        }

        $cart_decorator = new CartDecorator($cart);
        $order_data     = $cart_decorator->get_data();
        $customer_data  = $this->commons->getCustomerData();

        return apply_filters('voucherify_validation_service_validation_context',
            $order_data + $customer_data + $session_data);
    }

    private function createSessionData()
    {
        return [
            'session' => [
                'type'     => 'LOCK',
                'ttl'      => get_option('voucherify_lock_ttl', 7),
                'ttl_unit' => 'DAYS'
            ]
        ];
    }
}