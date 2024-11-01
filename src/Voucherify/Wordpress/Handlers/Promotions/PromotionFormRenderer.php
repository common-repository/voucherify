<?php

namespace Voucherify\Wordpress\Handlers\Promotions;

use Voucherify\ClientException;
use Voucherify\Wordpress\Common\Services\SessionService;

/**
 * Created by PhpStorm.
 * User: robert
 * Date: 06.02.2018
 * Time: 06:30
 */

if ( ! defined('ABSPATH')) {
    exit;
}

class PromotionFormRenderer
{
    private $all_promotions_fetcher;
    private $valid_promotions_fetcher;
    private $session;

    /**
     * Voucherify_Promotion_Form_Renderer constructor.
     *
     * @param $valid_promotions_fetcher ValidPromotionsFetcher
     * @param $all_promotions_fetcher AllPromotionsFetcher
     */
    public function __construct(
        ValidPromotionsFetcher $valid_promotions_fetcher,
        AllPromotionsFetcher $all_promotions_fetcher
    ) {
        $this->valid_promotions_fetcher = $valid_promotions_fetcher;
        $this->all_promotions_fetcher   = $all_promotions_fetcher;
        $this->session                  = new PromotionSessionService();
    }

    /**
     * Renders promotion select dropdown. By default it's displayed below the coupon code inputbox on both cart and
     * checkout pages.
     */
    public function render_promotion_from()
    {
        try {
            /**
             * @var array $promotions Collection of all promotions available to the store.
             *                        Used in promotion-form.php
             */
            $promotions = $this->all_promotions_fetcher->get_all_promotions();

            /**
             * @var array $available_promotions Collection of all VALID promotions (i.e. promotions that are valid for
             *                                  given cart and customer data). Used in promotion-form.php.
             */
            $available_promotions = $this->valid_promotions_fetcher->get_valid_promotions();

            echo "<div class='vcrf_promotion_dropdown'>";

            vcrf_include_partial('promotion-form.php', [
                'promotions'           => $promotions,
                'available_promotions' => $available_promotions
            ]);

            $this->render_promotion_form_ajax_handler();

            echo "</div>";
        } catch (ClientException $exception) {
            wc_get_logger()->error("{$exception->getMessage()}:", ["source" => "voucherify"]);
            wc_get_logger()->error($exception->getTraceAsString(), ["source" => "voucherify"]);
        }
    }

    /**
     * Renders javascript part of the form. Mostly handles the ajax calls.
     */
    public function render_promotion_form_ajax_handler()
    {
        vcrf_include_partial("promotion-form-ajax-handler.php");
    }

    function renderCartPromotion()
    {
        $cart = WC()->cart;
        $vouchers = $this->session->getAppliedVouchers($cart);
        foreach ($vouchers as $voucher) {
            $code            = $voucher->getCode();
            $discount_totals = SessionService::getDiscounts($cart);
            $discount_amount = $discount_totals['coupon_discounts'][$code];
            if ( ! $cart->display_prices_including_tax()) {
                $discount_amount -= $discount_totals['coupon_discounts_tax'][$code] ?? 0;
            }
            vcrf_include_partial('cart-promotion.php', [
                'display_name' => $voucher->getDisplayName(),
                'code'         => $voucher->getCode(),
                'discount'     => wc_price($discount_amount)
            ]);
        }
    }
}

