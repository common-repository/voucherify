<?php

namespace Voucherify\Wordpress\Synchronization;

use Exception;
use Voucherify\ClientException;

class TooManyRequestsException extends Exception
{
    /**
     * @param ClientException $exception
     */
    public function __construct(ClientException $exception)
    {
        parent::__construct($exception->getMessage(), 0, $exception);
    }
}