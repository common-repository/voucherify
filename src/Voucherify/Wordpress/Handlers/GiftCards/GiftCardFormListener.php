<?php

namespace Voucherify\Wordpress\Handlers\GiftCards;

use Voucherify\Wordpress\Common\Models\Notification;
use Voucherify\Wordpress\Common\Services\InfoService;

class GiftCardFormListener
{


    /**
     * @var InfoService
     */
    private $infoService;
    /**
     * @var GiftCardCartService
     */
    private $cartService;
    /**
     * @var GiftVouchersValidationService
     */
    private $validationService;


    public function __construct(
        InfoService $infoService,
        GiftCardCartService $cartService,
        GiftVouchersValidationService $validationService
    ) {
        $this->infoService       = $infoService;
        $this->cartService       = $cartService;
        $this->validationService = $validationService;
    }

    public function getGiftCardDetails($filtered, $code)
    {
        $giftVoucherInfo = $this->infoService->getGiftVoucherInfo($code);
        if ( ! empty($giftVoucherInfo)) {
            return $giftVoucherInfo;
        }

        return $filtered;
    }

    public function ajaxAddGiftCard()
    {
        define('VCRF_APPLY_VOUCHER', true);
        $code   = $_POST['code'] ?? '';

        $cart_total = doubleval(WC()->cart->get_total('edit'));
        $amount = intval($_POST['amount'] ?? '') * 100;
        $amount = min($amount, intval(round($cart_total * 100)));

        $result = $this->cartService->add($code, $amount);
        $result->print_notification();
        if ($result->isSuccess()) {
            wp_send_json_success([
                'message' => __('Gift card added', 'voucherify'),
                'code'    => $code
            ]);
        } else {
            wp_send_json_error();
        }
    }

    public function ajaxRemoveGiftCard()
    {
        $code = $_POST['code'] ?? '';
        if ( ! empty($code)) {
            $this->cartService->remove($code);
        }
        (new Notification(true, __('Gift card removed', 'voucherify')))->print_notification();
        wp_send_json_success();
    }
}