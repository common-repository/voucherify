<?php

namespace Voucherify\Wordpress\Handlers\Promotions;

use Voucherify\ClientException;
use Voucherify\Promotions;
use Voucherify\Wordpress\Common\Helpers\Commons;

/**
 * Created by PhpStorm.
 * User: robert
 * Date: 08.02.2018
 * Time: 05:50
 */

/**
 * Class Voucherify_Valid_Promotions_Fetcher
 *
 * This class is responsible for fetching valid promotions from the API.
 *
 * It takes current cart and customer data and requests for all valid promotions for this data.
 */
class ValidPromotionsFetcher
{
    /** @var Promotions $promotions */
    private $promotions;
    /**
     * @var Commons
     */
    private $commons;

    /**
     * Voucherify_Valid_Promotions_Fetcher constructor.
     *
     * @param  Promotions  $promotions
     */
    public function __construct(Promotions $promotions)
    {
        $this->promotions = $promotions;
        $this->commons = new Commons();
    }

    /**
     * Takes cart and customer information and based on it requests API for list of valid promotions.
     *
     * @return array list of valid promotions or empty if no promotion found
     * @throws ClientException
     */
    public function get_valid_promotions()
    {
        $context = apply_filters('voucherify_validation_service_validation_context',
            $this->commons->getCustomerData() + $this->commons->getOrderData());

        $context = apply_filters('voucherify_validation_service_validation_promotion_context', $context);

        $response = $this->promotions->validate($context);

        if ($response->valid && ! empty($response->promotions)) {
            return $response->promotions;
        }

        return [];
    }

    /**
     * Hooks into `wp_ajax_vcrf_fetch_promotions` and `wp_ajax_nopriv_vcrf_fetch_promotions` to handle
     * ajax request responsible for fetching valid promotions and returning them as JSON to the frontend.
     * @throws ClientException
     */
    public function handle_ajax_vcrf_fetch_promotions()
    {
        define('VCRF_FETCH_PROMOTIONS', true);
        echo json_encode($this->get_valid_promotions());
        die();
    }
}

