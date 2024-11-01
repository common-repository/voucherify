<?php

namespace Voucherify\Wordpress\Synchronization\Services;

use Voucherify\ClientException;
use Voucherify\Orders;
use Voucherify\Wordpress\Common\Helpers\Commons;
use Voucherify\Wordpress\Synchronization\TooManyRequestsException;
use WC_Order;

class OrderService
{
    public const VCRF_ID_META_KEY_NAME = "_vcrf_ord_id";

    /**
     * @var Orders
     */
    private $orders;
    /**
     * @var Commons
     */
    private $commons;

    /** @var \WC_Logger */
    private $logger;

    private $perRequestCache = [];

    public function __construct(Orders $orders)
    {
        $this->orders = $orders;
        $this->commons = new Commons();
        $this->logger = wc_get_logger();
    }

    /**
     * @throws TooManyRequestsException
     */
    public function save(WC_Order $order)
    {
        if ( ! $this->isModified($order)) {
            return;
        }

        try {
            $result = $this->upsert($order);
            $order->add_meta_data(CustomerService::VCRF_ID_META_KEY_NAME, $result->customer->id, true);

            if ($order->is_paid()) {
                $order->add_meta_data('_vcrf_marked_paid', 'YES', true);
            }

            $this->commons->addVcrfSyncMetadata($order, $result->id, static::VCRF_ID_META_KEY_NAME);
        } catch (ClientException $e) {
            $this->logger->error(__("Couldn't upsert order [wc_order_id={$order->get_id()}]: {$e->getMessage()}",
                'voucherify'), ['source' => 'voucherify']);
            $this->logger->error($e->getTraceAsString(), ['source' => 'voucherify']);

            if (stripos($e->getMessage(), 'Too many requests') !== false) {
                throw new TooManyRequestsException($e);
            } else {
                $this->commons->addVcrfSyncMetadata($order, 'fail', static::VCRF_ID_META_KEY_NAME);
            }
        }
    }

    /**
     * @throws ClientException
     */
    private function upsert(WC_Order $order)
    {
        $vcrfOrder = $this->convert($order);

        try {
            $result = $this->orders->create($vcrfOrder);
        } catch (ClientException $e) {
            $this->logger->error(__("Couldn't create order: {$e->getMessage()}", 'voucherify'),
                ['source' => 'voucherify']);
            $this->logger->error(__($e->getTraceAsString(), 'voucherify'), ['source' => 'voucherify']);

            throw $e;
        }

        $this->perRequestCache[$order->get_id()] = $vcrfOrder;

        return $result;
    }

    private function convert(WC_Order $order)
    {
        return [
            'source_id'             => createVcrfOrderSourceId($order->get_id()),
            'customer'              => $this->commons->convertWcOrderToVcrfCustomer($order),
            'amount'                =>
                intval(round(100 * $this->commons->getOrderAmount($order, false))),
            'items'                 => $this->commons->getItems($order, false),
            'status'                => $this->commons->convertWcStatusToVcrfOrderStatus($order->get_status('edit')),
            'metadata'              => ['wc_status' => $order->get_status()]
        ];
    }

    /**
     * @throws TooManyRequestsException
     */
    public function delete($wcOrderId)
    {
        try {
            return $this->orders->update(['id' => createVcrfOrderSourceId($wcOrderId), 'status' => 'CANCELLED']);
        } catch (ClientException $e) {
            $this->logger->error(__("Couldn't delete order [wc_order_id=$wcOrderId]: {$e->getMessage()}", 'voucherify'),
                ['source' => 'voucherify']);
            $this->logger->error($e->getTraceAsString(), ['source' => 'voucherify']);

            if (stripos($e->getMessage(), 'Too many requests') !== false) {
                throw new TooManyRequestsException($e);
            }
        }
    }

    private function isModified(WC_Order $order)
    {
        if ( ! isset($this->perRequestCache[$order->get_id()])) {
            return true;
        }

        $cached = $this->perRequestCache[$order->get_id()];
        unset($cached['id']);
        unset($cached['customer']['id']);

        $converted = $this->convert($order);
        unset($converted['id']);
        unset($converted['customer']['id']);

        return $converted != $cached;
    }
}
