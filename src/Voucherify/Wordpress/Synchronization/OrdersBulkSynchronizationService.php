<?php

namespace Voucherify\Wordpress\Synchronization;

use Voucherify\ApiClient;
use Voucherify\Wordpress\Common\Helpers\Commons;
use Voucherify\Wordpress\Synchronization\Services\OrderService;
use WC_Order;

class OrdersBulkSynchronizationService
{
    /** @var ApiClient */
    private $voucherifyApiClient;
    /** @var Commons */
    private $commons;

    /**
     * @param ApiClient $voucherifyApiClient
     * @param Commons $commons
     */
    public function __construct(ApiClient $voucherifyApiClient, Commons $commons)
    {
        $this->voucherifyApiClient = $voucherifyApiClient;
        $this->commons = $commons;
    }

    public function synchronize()
    {
        $limit = 2000;
        $page = 1;
        do {
            $orders = $this->getOrders($page, $limit);
            $this->queueOrdersImport($orders);
            $this->markOrders($orders);
        } while (count($orders) >= $limit);
    }

    private function markOrders(array $orders) {
        global $wpdb;
        $ordersChunked = array_chunk($orders, 500);

        foreach ($ordersChunked as $chunk) {
            $chunkedOrdersValues = array_map(function(WC_Order $order) use ($wpdb) {
                return $wpdb->prepare("(%d, %s, %s)", [$order->get_id(), OrderService::VCRF_ID_META_KEY_NAME, 'BULK']);
            }, $chunk);

            $wpdb->query(
                "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) values "
                . join(", ", $chunkedOrdersValues)
            );
        }
    }

    /**
     * @param $orders \WC_Order[]
     */
    private function queueOrdersImport(array $orders)
    {
        $voucherifyOrders = $this->convertToVoucherifyOrders($orders);
        $this->voucherifyApiClient->post("/orders/import", $voucherifyOrders);
    }

    /**
     * @param $orders \WC_Order[]
     */
    private function convertToVoucherifyOrders(array $orders)
    {
        $voucherifyOrders = [];
        foreach($orders as $order) {
            $voucherifyOrders[] = (object)[
                'source_id'             => createVcrfOrderSourceId($order->get_id()),
                'customer'              => $this->commons->convertWcOrderToVcrfCustomer($order),
                'amount'                =>
                    intval(round(100 * $this->commons->getOrderAmount($order, false))),
                'items'                 => $this->commons->getItems($order, false),
                'status'                => $this->commons->convertWcStatusToVcrfOrderStatus(
                    $order->get_status('edit')
                ),
                'metadata'              => ['wc_status' => $order->get_status()]
            ];
        }

        return $voucherifyOrders;
    }

    /**
     * @param $page
     * @param $limit
     * @return WC_Order[]
     */
    private function getOrders($page, $limit) {
        return wc_get_orders([
            'limit' => $limit,
            'paged' => $page++,
            'type' => 'shop_order',
            'status' => [
                'wc-pending',
                'wc-processing',
                'wc-on-hold',
                'wc-completed',
                'wc-cancelled',
                'wc-refunded',
                'wc-failed',
            ],
            'meta_query' => [
                [
                    'key' => OrderService::VCRF_ID_META_KEY_NAME,
                    'compare' => 'NOT EXISTS'
                ]
            ]
        ]);
    }
}
