<?php

namespace Voucherify\Wordpress\Synchronization\Handlers;

use Voucherify\Wordpress\Synchronization\Services\SkuService;
use Voucherify\Wordpress\Synchronization\TooManyRequestsException;

class SkuListener
{
    /**
     * @var SkuService
     */
    private $skuService;

    public function __construct(SkuService $skuService)
    {
        $this->skuService = $skuService;
    }

    public function onUpsert($variationId) {
        /** @var \WC_Product_Variation $variation */
        $variation = wc_get_product($variationId);

        if (get_post_status($variation->get_parent_id()) === 'publish') {
            try {
                $this->skuService->save($variation);
            } catch (TooManyRequestsException $exception) {
                wc_get_logger()->error('Voucherify API requests limit has been reached',
                    ['source' => 'voucherify']);
            }
        }
    }

    public function onDelete($variationId) {
        try {
            $this->skuService->remove(wc_get_product($variationId));
        } catch (TooManyRequestsException $exception) {
            wc_get_logger()->error('Voucherify API requests limit has been reached',
                ['source' => 'voucherify']);
        }
    }
}