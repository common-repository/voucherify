<?php

namespace Voucherify\Wordpress;

use Voucherify\ApiClient;
use Voucherify\ClientException;
use Voucherify\VoucherifyClient;


if (!defined('ABSPATH')) {
    exit;
}

class ClientExtension extends VoucherifyClient
{
    private $apiId;
    private $apiKey;
    private $apiUrl;

    /**
     * @var ApiClient
     */
    protected $client;

    public function __construct($apiId, $apiKey, $apiUrl, $apiVersion = null)
    {
        $customHeaders = $this->getCustomHeaders();
        $this->apiId = $apiId;
        $this->apiKey = $apiKey;
        $this->apiUrl = $apiUrl;
        parent::__construct($this->apiId, $this->apiKey, $apiVersion, $this->apiUrl, $customHeaders);
        $this->client = new ApiClient($this->apiId, $this->apiKey, $apiVersion, $this->apiUrl, $customHeaders);
    }

    public function getCustomHeaders()
    {
        $customHeaders = [
            'x-vf-wp-session-token' => wp_get_session_token()
        ];

        if (defined('VOUCHERIFY_PLUGIN_VERSION')) {
            $customHeaders['x-voucherify-channel'] = "wc-plugin-" . VOUCHERIFY_PLUGIN_VERSION;
        }

        $wc_version = defined('WC_VERSION') ? WC_VERSION : null;
        if (!empty($wc_version)) {
            $customHeaders['x-vf-wc-version'] = $wc_version;
        }

        return $customHeaders;
    }

    public function release_session_lock($code, $session_key)
    {
        if (empty($session_key)) {
            return;
        }
        try {
            $this->client->delete('/vouchers/' . $code . '/sessions/' . $session_key);
        } catch (ClientException $e) {
            wc_get_logger()->notice(__('Voucher was already removed', 'voucherify') . PHP_EOL .
                'original_message: ' . $e->getMessage(), ["source" => "voucherify"]);
        }
    }

    /**
     * @return mixed
     */
    public function getApiId()
    {
        return $this->apiId;
    }

    /**
     * @return mixed
     */
    public function getApiKey()
    {
        return $this->apiKey;
    }

    /**
     * @return mixed
     */
    public function getApiUrl()
    {
        return $this->apiUrl;
    }
}
