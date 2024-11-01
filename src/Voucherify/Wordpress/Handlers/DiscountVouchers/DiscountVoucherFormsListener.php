<?php

namespace Voucherify\Wordpress\Handlers\DiscountVouchers;

use Voucherify\Wordpress\Common\Models\Notification;
use Voucherify\Wordpress\Common\Services\CartService;
use Voucherify\Wordpress\Common\Services\InfoService;

/**
 * Created by PhpStorm.
 * User: robert
 * Date: 06.02.2018
 * Time: 06:30
 */
class DiscountVoucherFormsListener
{
    /** @var InfoService */
    private $info_service;
    /** @var CartService */
    private $cart_service;

    public function __construct(InfoService $info_service, CartService $cart_service)
    {
        $this->info_service = $info_service;
        $this->cart_service = $cart_service;
    }

    public function ajax_add_voucher()
    {
        define('VCRF_APPLY_VOUCHER', true);
        $code = $_POST['code'] ?? '';

        if (empty($code)) {
            wc_add_notice(__("Please provide a valid code.", "voucherify"), "notice");
            wp_send_json_error();
        }

        $giftCardDetails = apply_filters('vcrf_gift_card_details', null, $code);
        if (!empty($giftCardDetails)) {
            wp_send_json_success([
                'code'    => $giftCardDetails->get_name(),
                'type'    => 'gift',
                'balance' => wc_format_decimal(
                    wc_remove_number_precision($giftCardDetails->get_available_balance()),
                    wc_get_price_decimals()
                )
            ]);
        }

        // @deprecated below filter will be removed in future versions
        if (apply_filters('vcrf_handle_add_voucher_ajax', false, $code)) {
            return;
        }

        $result = $this->cart_service->add($code);
        $result->print_notification();
        if ($result->isSuccess()) {
            wp_send_json_success([
                'message' => __('Coupon added', 'voucherify'),
                'code'    => $code,
                'type'    => 'discount'
            ]);
        } else {
            wp_send_json_error();
        }
    }

    public function ajax_remove_voucher()
    {
        $code = $_POST['code'] ?? '';
        if ( ! empty($code)) {
            $this->cart_service->remove($code);
        }
        (new Notification(true, __('Coupon removed', 'voucherify')))->print_notification();
        wp_send_json_success();
    }
}

