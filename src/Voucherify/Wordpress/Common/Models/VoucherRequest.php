<?php

namespace Voucherify\Wordpress\Common\Models;

class VoucherRequest
{
    /** @var string */
    private $code;
    /** @var array */
    private $context;

    /**
     * @param  string  $code
     * @param  array  $context
     */
    public function __construct(string $code, array $context)
    {
        $this->code    = $code;
        $this->context = $context;
    }

    /**
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @return array
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * @param  string  $code
     */
    public function setCode(string $code)
    {
        $this->code = $code;
    }

    /**
     * @param  array  $context
     */
    public function setContext(array $context)
    {
        $this->context = $context;
    }


}