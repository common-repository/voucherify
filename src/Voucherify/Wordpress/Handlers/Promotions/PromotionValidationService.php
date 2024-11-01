<?php

namespace Voucherify\Wordpress\Handlers\Promotions;

use Voucherify\Promotions;
use Voucherify\Wordpress\Common\Services\ValidationService;

/**
 * Created by PhpStorm.
 * User: robert
 * Date: 20.01.18
 * Time: 06:55
 */


/**
 * Class PromotionValidationService
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
class PromotionValidationService extends ValidationService
{
    /** @var Promotions */
    private $promotions;

    public function __construct(
        Promotions $promotions
    ) {
        parent::__construct(new PromotionSessionService());
        $this->promotions = $promotions;
    }

    public function validate(string $code, array $context)
    {
        $response = $this->responseCacheService->getCachedResponse($code, $context);
        if (empty($response)) {
            $response = $this->promotions->validate($context);
            $this->responseCacheService->addResponseToCache($code, $context, $response);
        }
        if ( ! $response->valid) {
            return $response;
        }
        foreach ($response->promotions ?? [] as $promotion) {
            if ($promotion->id == $code) {
                return $promotion;
            }
        }

        return (object)['valid' => false];
    }
}

