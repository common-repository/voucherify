<?php

namespace Voucherify\Wordpress\Synchronization\Handlers;

use Voucherify\Wordpress\Synchronization\Services\CustomerService;
use Voucherify\Wordpress\Synchronization\TooManyRequestsException;

class CustomerListener
{
    /**
     * @var CustomerService
     */
    private $customerService;

    public function __construct(CustomerService $customerService)
    {
        $this->customerService = $customerService;
    }

    public function onCustomerUpdate($customer_id, $customer)
    {
        try {
            $this->customerService->save($customer);
        } catch (TooManyRequestsException $exception) {
            wc_get_logger()->error('Voucherify API requests limit has been reached',
                ['source' => 'voucherify']);
        }
    }

    public function onCustomerDelete($customer_id)
    {
        try {
            $this->customerService->delete($customer_id);
        } catch (TooManyRequestsException $exception) {
            wc_get_logger()->error('Voucherify API requests limit has been reached',
                ['source' => 'voucherify']);
        }
    }

    public function onUserUpdate($userId, $oldUserData, $currentUserData)
    {
        if ($currentUserData['role'] != 'customer') {
             return;
        }

        $customer = new \WC_Customer();

        $customer->set_id($userId);
        $customer->set_billing_first_name($currentUserData['first_name']);
        $customer->set_billing_last_name($currentUserData['last_name']);
        $customer->set_billing_email($currentUserData['user_email']);
        $this->onCustomerUpdate($userId, $customer);
    }

    public function onUserCreate($userId, $userdata)
    {
        $this->onUserUpdate($userId, null, $userdata);
    }
}