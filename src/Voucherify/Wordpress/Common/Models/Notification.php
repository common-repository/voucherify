<?php

namespace Voucherify\Wordpress\Common\Models;

class Notification
{
    /**
     * @var boolean
     */
    private $success;
    /**
     * @var string
     */
    private $message;

    private ?Voucher $voucher;

    /**
     * @param  bool  $success
     * @param  string  $message
     */
    public function __construct(bool $success, string $message, ?Voucher $voucher = null)
    {
        $this->success = $success;
        $this->message = $message;
        $this->voucher = $voucher;
    }

    /**
     * @return bool
     */
    public function isSuccess()
    {
        return $this->success;
    }

    public function print_notification()
    {
        wc_add_notice(__($this->message, 'voucherify'), $this->success ? 'success' : 'error');
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    public function getVoucher()
    {
        return $this->voucher;
    }
}