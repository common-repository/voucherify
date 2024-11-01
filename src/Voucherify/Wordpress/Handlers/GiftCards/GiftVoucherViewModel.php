<?php

namespace Voucherify\Wordpress\Handlers\GiftCards;

class GiftVoucherViewModel
{
    /**
     * @var string
     */
    private $name;
    /**
     * @var int
     */
    private $availableBalance;


    /**
     * @param  string  $name
     * @param  int  $availableBalance
     */
    public function __construct(string $name, int $availableBalance)
    {
        $this->name             = $name;
        $this->availableBalance = $availableBalance;
    }

    /**
     * @return string
     */
    public function get_name()
    {
        return $this->name;
    }

    /**
     * @return int
     */
    public function get_available_balance()
    {
        return $this->availableBalance;
    }


}