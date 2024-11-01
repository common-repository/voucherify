<?php

namespace Voucherify\Wordpress\Synchronization;

abstract class BulkCsvSynchronizationService
{
    /** @var string */
    private $apiUrl;
    /** @var string */
    private $apiId;
    /** @var string */
    private $apiKey;

    /**
     * @param string $apiUrl
     * @param string $apiId
     * @param string $apiKey
     */
    public function __construct(string $apiId, string $apiKey, string $apiUrl)
    {
        $this->apiUrl = $apiUrl;
        $this->apiId = $apiId;
        $this->apiKey = $apiKey;
    }


    /**
     * array of mapping where key is the csv column name and value is the db column name (data source)
     * or a callable accepting db $row as an argument
     *
     * @return array
     */
    abstract protected function getColumnMapping();

    /**
     * Endpoint path in format e.g.: /v1/customers/importCSV
     * No base api url.
     *
     * @return string
     */
    abstract protected function getEndpoint();

    /**
     * This function should use a model and retrieve list of rows to be exported
     *
     * @param $offset
     * @param $limit
     * @return array
     */
    abstract protected function getDatabaseRowsData($offset, $limit);

    abstract protected function markSynced($updatingRows);

    public function synchronize() {
        $creationTime = time();
        $className = get_class($this);
        $className = explode("\\", $className);
        $className = end($className);
        $randomFilePath = join(DIRECTORY_SEPARATOR, [get_temp_dir(),  "$className-$creationTime.csv"]);
        $randomFilePath = str_replace(
            DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR,
            DIRECTORY_SEPARATOR,
            $randomFilePath
        );
        $updatingRows = $this->createCsv($randomFilePath);

        $this->export($randomFilePath);

        $this->markSynced($updatingRows);
    }

    protected function export($csvFilePath)
    {
        // Initialize cURL session
        $ch = curl_init();

        // Set cURL options
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl . $this->getEndpoint());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);

        // Set the CSV file for upload
        $postData = array(
            'file' => curl_file_create($csvFilePath, 'text/csv', basename($csvFilePath))
        );

        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getCustomHeaders());

        // Execute cURL request
        curl_exec($ch);

        // Check for errors
        if (curl_errno($ch)) {
            wc_get_logger()->notice(
                self::class . ': Problems occured during export: ' . curl_error($ch),
                ['source' => 'voucherify']
            );
        }

        // Close cURL session
        curl_close($ch);
    }

    protected function createCsv($randomFilePath, $limit = 1000)
    {
        $resource = fopen($randomFilePath, 'w');

        $mapping = $this->getColumnMapping();
        $mappingKeys = array_keys($mapping);

        // fields mapping - header
        fputcsv($resource, $mappingKeys);

        $iteration = 0;

        $updatingRows = [];

        do {
            $rows = $this->getDatabaseRowsData($limit * $iteration++, $limit);

            foreach ($rows as $row) {
                $rowData = [];
                foreach($mapping as $csvColumnName => $callableOrDbColumnName) {
                    if (is_callable($callableOrDbColumnName)) {
                        $rowData[$csvColumnName] = $callableOrDbColumnName($row);
                    } else {
                        $rowData[$csvColumnName] = $row[$callableOrDbColumnName];
                    }
                }

                fputcsv($resource, $rowData);
                $updatingRows[] = $row;
            }
        } while (count($rows) >= $limit);

        fclose($resource);

        return $updatingRows;
    }

    private function getCustomHeaders()
    {
        $headers = [
            'Accept: application/json',
            'content-type' => 'multipart/form-data',
            "X-App-Id: {$this->apiId}",
            "X-App-Token: {$this->apiKey}",
            'x-vf-wp-session-token' => wp_get_session_token()
        ];

        if (defined('VOUCHERIFY_PLUGIN_VERSION')) {
            $headers['x-voucherify-channel'] = "wc-plugin-" . VOUCHERIFY_PLUGIN_VERSION;
        }

        $wc_version = defined('WC_VERSION') ? WC_VERSION : null;
        if (!empty($wc_version)) {
            $headers['x-vf-wc-version'] = $wc_version;
        }

        return $headers;
    }
}
