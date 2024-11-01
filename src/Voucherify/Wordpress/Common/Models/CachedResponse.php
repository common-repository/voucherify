<?php

namespace Voucherify\Wordpress\Common\Models;

class CachedResponse
{
    private $code;
    private $context;
    private $response;

    /**
     * @param $code
     * @param $context
     * @param $response
     */
    public function __construct($code, $context, $response)
    {
        $this->code     = $code;
        $this->context  = $context;
        $this->response = $response;
    }


    /**
     * @return mixed
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @param  mixed  $code
     */
    public function setCode($code)
    {
        $this->code = $code;
    }

    /**
     * @return mixed
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * @param  mixed  $context
     */
    public function setContext($context)
    {
        $this->context = $context;
    }

    /**
     * @return mixed
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @param  mixed  $response
     */
    public function setResponse($response)
    {
        $this->response = $response;
    }

    public function hasSameContext($context)
    {
        return ($this->context['order'] ?? []) == ($context['order'] ?? [])
               && ($this->context['customer'] ?? []) == ($context['customer'] ?? [])
               && ($this->context['gift'] ?? []) == ($context['gift'] ?? []);
    }
}