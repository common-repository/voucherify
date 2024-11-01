<?php

namespace Voucherify\Wordpress\Handlers\Promotions;

use Voucherify\PromotionTiers;

/**
 * Created by PhpStorm.
 * User: robert
 * Date: 08.02.2018
 * Time: 05:50
 */
if ( ! defined('ABSPATH')) {
    exit;
}

class AllPromotionsFetcher
{
    /** @var PromotionTiers $promotionTiers */
    private $promotionTiers;

    /**
     * Voucherify_Valid_Promotions_Fetcher constructor.
     *
     * @param  PromotionTiers  $promotionTiers
     */
    public function __construct(PromotionTiers $promotionTiers)
    {
        $this->promotionTiers = $promotionTiers;
    }

    /**
     * Gets all possible promotions available for this store.
     *
     * @return mixed|void all possible promotions for this store.
     */
    public function get_all_promotions()
    {
        $response = $this->promotionTiers->getAvailable();

        $promotions = [];
        if ( ! empty($response) && property_exists($response, 'tiers') && is_array($response->tiers)) {
            $promotions = array_map(function ($promotion) {
                return $promotion->banner;
            }, $response->tiers);
        }

        return apply_filters('voucherify_all_promotions', $promotions, $this->promotionTiers);
    }
}

