<?php

namespace Voucherify\Wordpress\Common\Services;


use Voucherify\Wordpress\Common\Models\CachedResponse;

class ResponseCacheService
{
    private const VOUCHER_RESPONSES_SESSION_KEY = '_vcrf_voucher_responses';
    private static $responseCaches;

    public function __construct()
    {
        if ( ! empty(static::$responseCaches)) {
            return;
        }

        WC()->initialize_session();

        static::$responseCaches = WC()->session->get(self::VOUCHER_RESPONSES_SESSION_KEY);
        if (empty(static::$responseCaches)) {
            static::$responseCaches = [];
        }
    }

    public function getCachedResponse($code, array $context)
    {
        if ( ! array_key_exists($code, static::$responseCaches)) {
            return null;
        }

        /** @var CachedResponse $cachedResponse */
        $cachedResponse = static::$responseCaches[$code];

        if ($cachedResponse->hasSameContext($context)) {
            return $cachedResponse->getResponse();
        } else {
            $this->removeResponseFromCache($code);

            return null;
        }
    }

    public function removeResponseFromCache($code)
    {
        if (array_key_exists($code, static::$responseCaches)) {
            unset(static::$responseCaches[$code]);
            WC()->session->set(self::VOUCHER_RESPONSES_SESSION_KEY, static::$responseCaches);
        }
    }

    public function addResponseToCache($code, array $context, $response)
    {
        static::$responseCaches[$code] = new CachedResponse($code, $context, $response);
        WC()->session->set(self::VOUCHER_RESPONSES_SESSION_KEY, static::$responseCaches);
    }
}
