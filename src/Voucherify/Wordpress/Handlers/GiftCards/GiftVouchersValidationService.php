<?php

namespace Voucherify\Wordpress\Handlers\GiftCards;

use Voucherify\Validations;
use Voucherify\Wordpress\Common\Services\ValidationService;

class GiftVouchersValidationService extends ValidationService
{
    /** @var Validations validations */
    private $validationsApi;

    /**
     * @param  Validations  $validations
     */
    public function __construct(
        Validations $validations
    ) {
        parent::__construct(new GiftCardsSessionService());
        $this->validationsApi = $validations;
    }

    public function validate(string $code, array $context)
    {
        $response = $this->responseCacheService->getCachedResponse($code, $context);
        if (empty($response)) {
            $response = $this->validationsApi->validate($code, $context);
            $this->responseCacheService->addResponseToCache($code, $context, $response);
        }

        return $response;
    }
}