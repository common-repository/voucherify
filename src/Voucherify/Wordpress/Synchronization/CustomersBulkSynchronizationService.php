<?php

namespace Voucherify\Wordpress\Synchronization;

use Voucherify\Wordpress\ClientExtension;
use Voucherify\Wordpress\Models\CustomerModel;
use Voucherify\Wordpress\Synchronization\Services\CustomerService;

class CustomersBulkSynchronizationService extends BulkCsvSynchronizationService
{
    private CustomerModel $customerModel;

    /**
     * @param CustomerModel $customerModel
     */
    public function __construct(CustomerModel $customerModel, ClientExtension $voucherifyClient)
    {
        parent::__construct(
            $voucherifyClient->getApiId(),
            $voucherifyClient->getApiKey(),
            $voucherifyClient->getApiUrl()
        );
        $this->customerModel = $customerModel;
    }

    protected function getColumnMapping()
    {
        return [
            'Name' => function($row) {
                return join(' ', array_filter([$row['first_name'], $row['last_name']]));
            },
            'Email' => 'email',
            'Phone' => 'phone',
            'Source_id' => function($row) { return $row['source_id']; },
            'Address_line_1' => 'address1',
            'Address_line_2' => 'address2',
            'Address_Postal_Code' => 'postal_code',
            'Address_City' => 'city',
            'Address_State' => 'state',
            'Address_Country' => 'country',
        ];
    }

    protected function getEndpoint()
    {
        return "/v1/customers/importCSV";
    }

    protected function getDatabaseRowsData($offset, $limit)
    {
        return $this->customerModel->getBillingDetailsList($offset, $limit);
    }

    protected function markSynced($updatingRows)
    {
        global $wpdb;
        $rowsChunked = array_chunk($updatingRows, 500);

        foreach ($rowsChunked as $chunk) {
            $chunkedRowsValues = array_map(function($user) use ($wpdb) {
                return $wpdb->prepare("(%d, %s, %s)", [$user['source_id'], CustomerService::VCRF_ID_META_KEY_NAME, 'BULK']);
            }, $chunk);

            $wpdb->query(
                "INSERT INTO {$wpdb->usermeta} (user_id, meta_key, meta_value) values "
                . join(", ", $chunkedRowsValues)
            );
        }
    }
}
