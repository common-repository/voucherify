<?php

namespace Voucherify\Wordpress\Common\Helpers;

use WC_Customer;

/**
 * Created by PhpStorm.
 * User: bbs
 * Date: 1/23/18
 * Time: 11:10 AM
 */
class CustomerDecorator
{
    /** @var WC_Customer $order */
    private $customer;

    /**
     * Voucherify_Customer_Decorator constructor.
     *
     * @param  WC_Customer  $customer
     */
    public function __construct(WC_Customer $customer)
    {
        $this->customer = $customer;
    }

    /**
     * Converts customer data to `customer` property of the params that are passed to the endpoint call.
     *
     * @return array `customer` property of payload to be used during endpoint call
     */
    public function get_data()
    {
        $customer_data = [];

        $customer = $this->customer;

        if ( ! empty($customer->get_id())) {
            $customer_data['source_id'] = $customer->get_id();
        }

        if ( ! empty($customer->get_email())) {
            $customer_data['email'] = $customer->get_email();
        }

        if ( ! empty($customer->get_display_name())) {
            $customer_data['name'] = $customer->get_display_name();
        }

        if (empty($customer_data)) {
            return [];
        }

        return apply_filters('voucherify_customer_decorator_get_data', ['customer' => $customer_data],
            $this->customer);
    }
}

