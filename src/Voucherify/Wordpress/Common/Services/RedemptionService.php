<?php

namespace Voucherify\Wordpress\Common\Services;

use Voucherify\ClientException;
use Voucherify\Redemptions;
use Voucherify\Wordpress\Common\Helpers\Commons;
use WC_Order;

/**
 * Created by PhpStorm.
 * User: bbs
 * Date: 1/23/18
 * Time: 10:15 AM
 */
class RedemptionService
{
    /** @var Redemptions $redemptions */
    private $redemptions;
    /**
     * @var Commons
     */
    private $commons;

    /**
     * Voucherify_Redemption_Service constructor.
     *
     * @param  Redemptions  $redemptions
     */
    public function __construct(Redemptions $redemptions)
    {
        $this->redemptions = $redemptions;
        $this->commons = new Commons();
    }

    /**
     * @throws ClientException
     */
    public function redeem($id, $baseContext, WC_Order $order)
    {
        $context = apply_filters('voucherify_redemption_service_redemption_context',
            $baseContext + $this->commons->getCustomerData($order) + $this->commons->getOrderData($order));

        $redemptionResult = $this->redemptions->redeem($id, $context);

        $order->add_meta_data('_vcrf_redemption_result', json_encode($redemptionResult ?? []), true);
        $order->add_meta_data('_vcrf_redemption_status', $redemptionResult->status ?? 'FAILED', true);

        return apply_filters('voucherify_redemption_service_redeem',
            $redemptionResult, $this->redemptions);
    }

    /**
     * Makes the API call to rollback redemption.
     *
     * @param  string  $redemption_id  id of redemption to rollback
     *
     * @throws ClientException if voucherify api could not rollback redemption.
     */
    public function rollback_redemption($redemption_id)
    {
        apply_filters('voucherify_redemption_service_rollback',
            $this->redemptions->rollback($redemption_id));
    }
}

