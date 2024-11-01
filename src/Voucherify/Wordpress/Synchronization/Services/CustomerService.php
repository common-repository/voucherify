<?php

namespace Voucherify\Wordpress\Synchronization\Services;

use Voucherify\ClientException;
use Voucherify\Customers;
use Voucherify\Wordpress\Common\Helpers\Commons;
use Voucherify\Wordpress\Synchronization\TooManyRequestsException;
use WC_Customer;

class CustomerService
{
    public const VCRF_ID_META_KEY_NAME = "_vcrf_cust_id";

    /**
     * @var Customers
     */
    public $customers;
    /**
     * @var Commons
     */
    private $commons;

    public function __construct(Customers $customers)
    {
        $this->customers = $customers;
        $this->commons = new Commons();
    }

    /**
     * @throws TooManyRequestsException
     */
    public function save(WC_Customer $customer)
    {
        try {

            $result = $this->upsert($customer);

            $this->commons->addVcrfSyncMetadata($customer, $result->id, static::VCRF_ID_META_KEY_NAME);
        } catch (ClientException $e) {
            $logger = wc_get_logger();
            $logger->error(__('Couldn\'t save customer', 'voucherify'), ['original_message' => $e->getMessage()]);

            if (stripos($e->getMessage(), 'Too many requests') !== false) {
                throw new TooManyRequestsException($e);
            }
        }
    }

    /**
     * @throws TooManyRequestsException
     */
    public function delete($customerId)
    {
        try {
            $this->customers->delete(createVcrfCustomerSourceId($customerId));
        } catch (ClientException $e) {
            $logger = wc_get_logger();
            $logger->error(__('Couldn\'t delete customer', 'voucherify'), ['original_message' => $e->getMessage()]);

            if (stripos($e->getMessage(), 'Too many requests') !== false) {
                throw new TooManyRequestsException($e);
            }
        }
    }

    /**
     * @throws ClientException
     */
    private function upsert(WC_Customer $customer)
    {
        $vcrfCustomer = $this->commons->convertWcCustomerToVcrfCustomer($customer);
        return $this->customers->create($vcrfCustomer);
    }
}