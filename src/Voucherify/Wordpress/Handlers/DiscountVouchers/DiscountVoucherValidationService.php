<?php

namespace Voucherify\Wordpress\Handlers\DiscountVouchers;

use Voucherify\Validations;
use Voucherify\Wordpress\Common\Services\ValidationService;
use WC_Subscriptions;

/**
 * Created by PhpStorm.
 * User: robert
 * Date: 20.01.18
 * Time: 06:55
 */

/**
 * Class Voucherify_Validation_Service
 *
 * This service's responsibility is to process validation of a voucher.
 * It will get the coupon code from the user and call the API to validate it.
 *
 * If the coupon is valid, it will create a virtual coupon (non-existend in the WC database)
 * of type 'fixed_cart' and with discount amount.
 *
 * This service assumes that all the responsibility for applying proper amount of discount
 * is on the API side, thus it will only send all the info about customer and cart
 * and expect a discounted amount in return, or information that the code is invalid.
 */
class DiscountVoucherValidationService extends ValidationService
{
    /** @var Validations $validations */
    private $validations;

    public function __construct(
        Validations $validations
    ) {
        parent::__construct(new DiscountVoucherSessionService());
        $this->validations = $validations;
    }

    public function validate(string $code, array $context)
    {
        if (class_exists(WC_Subscriptions::class)) {
            $cartKey = $context['order']['metadata']['recurring_cart_key'] ?? '';
            $key = json_encode(["code" => $code, "cart_key" => $cartKey]);
        } else {
            $key = $code;
        }

        $response = $this->responseCacheService->getCachedResponse($key, $context);
        if (empty($response)) {
            $response = $this->validations->validate($code, $context);
            $this->responseCacheService->addResponseToCache($key, $context, $response);
        }

        return $response;
    }
}

