<?php

use Voucherify\Test\Helpers\CurlMock;

use Voucherify\VoucherifyClient;
use Voucherify\ClientException;

class ClientTest extends PHPUnit_Framework_TestCase
{
    protected static $headers;
    protected static $apiId;
    protected static $apiKey;

    public static function setUpBeforeClass()
    {
        self::$apiId = "c70a6f00-cf91-4756-9df5-47628850002b";
        self::$apiKey = "3266b9f8-e246-4f79-bdf0-833929b1380c";
        self::$headers = [
            "content-type: application/json",
            "x-app-id: " . self::$apiId,
            "x-app-token: " . self::$apiKey,
            "x-voucherify-channel: PHP-SDK"
        ];

        CurlMock::enable();
    }

    public static function tearDownAfterClass()
    {
        CurlMock::disable();
    }

    public function testInitialization()
    {
        try {
            $client = new VoucherifyClient(null, self::$apiKey);
        }
        catch (Exception $e) {
            $this->assertEquals($e->getMessage(), "ApiId is required");
        }

        try {
            $client = new VoucherifyClient(self::$apiId, null);
        }
        catch (Exception $e) {
            $this->assertEquals($e->getMessage(), "ApiKey is required");
        }
    }

    public function testCustomApiUrl()
    {
        $headers = [
            "content-type: application/json",
            "x-app-id: " . self::$apiId,
            "x-app-token: " . self::$apiKey,
            "x-voucherify-channel: PHP-SDK"
        ];

        CurlMock::register("https://api.voucherify.io/v1", $headers)
            ->get("/vouchers/test-voucher-1")
            ->reply(200, [ "status" => "ok" ]);

        $client = new VoucherifyClient(self::$apiId, self::$apiKey);

        $result = $client->vouchers->get("test-voucher-1");
        $this->assertEquals($result, (object)[ "status" => "ok" ]);

        CurlMock::register("https://custom-api.voucherify.io/v1", $headers)
            ->get("/vouchers/test-voucher-2")
            ->reply(200, [ "status" => "ok" ]);

        $customUrl = "https://custom-api.voucherify.io";
        $client = new VoucherifyClient(self::$apiId, self::$apiKey, null, $customUrl);

        $result = $client->vouchers->get("test-voucher-2");
        $this->assertEquals($result, (object)[ "status" => "ok" ]);

        CurlMock::done();
    }

    public function testVersioning()
    {
        $headers = [
            "content-type: application/json",
            "x-app-id: " . self::$apiId,
            "x-app-token: " . self::$apiKey,
            "x-voucherify-channel: PHP-SDK"
        ];

        CurlMock::register("https://api.voucherify.io/v1", $headers)
            ->get("/vouchers/test-voucher-1")
            ->reply(200, [ "status" => "ok" ]);

        $client = new VoucherifyClient(self::$apiId, self::$apiKey);

        $result = $client->vouchers->get("test-voucher-1");
        $this->assertEquals($result, (object)[ "status" => "ok" ]);

        $headers = [
            "content-type: application/json",
            "x-app-id: " . self::$apiId,
            "x-app-token: " . self::$apiKey,
            "x-voucherify-channel: PHP-SDK",
            "x-voucherify-api-version: v2017-04-05"
        ];

        CurlMock::register("https://api.voucherify.io/v1", $headers)
            ->get("/vouchers/test-voucher-2")
            ->reply(200, [ "status" => "ok" ]);

        $client = new VoucherifyClient(self::$apiId, self::$apiKey, "v2017-04-05");

        $result = $client->vouchers->get("test-voucher-2");
        $this->assertEquals($result, (object)[ "status" => "ok" ]);

        CurlMock::done();
    }

    public function testCustomHeaders()
    {
        $headers = [
            "content-type: application/json",
            "x-app-id: " . self::$apiId,
            "x-app-token: " . self::$apiKey,
            "x-voucherify-channel: PHP-SDK",
            "x-custom-1: Value-1",
            "x-custom-2: Value-2"
        ];

        CurlMock::register("https://api.voucherify.io/v1", $headers)
            ->get("/vouchers/test-voucher-1")
            ->reply(200, [ "status" => "ok" ]);

        $customHeaders = [
            "x-custom-1" => "Value-1",
            "X-CustoM-2" => "Value-2"
        ];

        $client = new VoucherifyClient(self::$apiId, self::$apiKey, null, null, $customHeaders);

        $result = $client->vouchers->get("test-voucher-1");
        $this->assertEquals($result, (object)[ "status" => "ok" ]);

        $headers = [
            "content-type: application/json",
            "x-app-id: " . self::$apiId,
            "x-app-token: " . self::$apiKey,
            "x-voucherify-channel: PHP-SDK-Value-2",
            "x-custom-1: Value-1"
        ];

        CurlMock::register("https://api.voucherify.io/v1", $headers)
            ->get("/vouchers/test-voucher-2")
            ->reply(200, [ "status" => "ok" ]);

        $customHeaders = [
            "x-custom-1" => "Value-1",
            "X-Voucherify-ChanneL" => "Value-2"
        ];

        $client = new VoucherifyClient(self::$apiId, self::$apiKey, null, null, $customHeaders);

        $result = $client->vouchers->get("test-voucher-2");
        $this->assertEquals($result, (object)[ "status" => "ok" ]);


        $headers = [
            "content-type: application/json",
            "x-app-id: " . self::$apiId,
            "x-app-token: " . self::$apiKey,
            "x-voucherify-channel: PHP-SDK-value-2",
            "x-custom-1: value-1"
        ];

        CurlMock::register("https://api.voucherify.io/v1", $headers)
            ->get("/vouchers/test-voucher-3")
            ->reply(200, [ "status" => "ok" ]);

        $customHeaders = [
            "x-custom-1" => "value-1",
            "X-VoucherifY-ChanneL" => "value-2"
        ];

        $client = new VoucherifyClient(self::$apiId, self::$apiKey, null, null, $customHeaders);

        $result = $client->vouchers->get("test-voucher-3");
        $this->assertEquals($result, (object)[ "status" => "ok" ]);

        CurlMock::done();
    }

    public function testErrorHandling()
    {
        CurlMock::register("https://api.voucherify.io/v1", self::$headers)
            ->post("/customers/", [ "name" => "customer name" ])
            ->reply(400, [
                "message" => "Duplicate resource key",
                "details" => "Campaign with name: test campaign already exists.",
                "key" => "duplicate_resource_key"
            ]);

        try {
            $client = new VoucherifyClient(self::$apiId, self::$apiKey);
            $client->customers->create([ "name" => "customer name"]);
        }
        catch (ClientException $e) {
            $this->assertEquals($e, new ClientException([
                "code" => 400,
                "message" => "Duplicate resource key",
                "details" => "Campaign with name: test campaign already exists.",
                "key" => "duplicate_resource_key"
            ]));
        }

        CurlMock::done();
    }
}