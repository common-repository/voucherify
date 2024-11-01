<?php

namespace Voucherify\Wordpress\Handlers\Promotions;


use Exception;
use Voucherify\Wordpress\Common\Models\Notification;
use Voucherify\Wordpress\Common\Services\CartService;

/**
 * Created by PhpStorm.
 * User: robert
 * Date: 09.02.2018
 * Time: 14:13
 */
class PromotionFormListener
{
    /** @var CartService $cart_service */
    private $cart_service;

    /**
     * Voucherify_Form_Handler constructor.
     *
     * @param  CartService  $cart_service  Promotion service
     */
    public function __construct(CartService $cart_service)
    {
        $this->cart_service = $cart_service;
    }

    /**
     * Handles from submission. Should only work when the promotion code has been selected on the form.
     *
     * If promotion code found - apply the promotion
     */
    public function handle()
    {
        if ( ! isset($_POST['vcrf_promotion_code']) || empty($_POST['vcrf_promotion_code'])) {
            return;
        }

        if (isset($_POST['ajax_request']) && $_POST['ajax_request'] === 'true') {
            $this->handle_ajax();
            die();
        }

        $result = $this->cart_service->add($_POST['vcrf_promotion_code']);
        $result->print_notification();
    }

    /**
     * Handles request coming via ajax (requires echoing responses).
     */
    private function handle_ajax()
    {
        try {
            $result = $this->cart_service->add($_POST['vcrf_promotion_code']);
            $result->print_notification();
            if ($result->isSuccess()) {
                echo json_encode([
                    'error'   => false,
                    'message' => sprintf(__('%s applied successfully.', 'voucherify'),
                        __('Promotion', 'voucherify'))
                ]);
                die();
            }
        } catch (Exception $e) {
            // we will return nice notice to the user.
        }

        echo json_encode([
            'error'   => true,
            'message' => __('Promotion has not been accepted thus a discount will not be applied.', 'voucherify')
        ]);

        die();
    }

    public function ajaxRemovePromotion()
    {
        $code = $_POST['code'] ?? '';
        if ( ! empty($code)) {
            $this->cart_service->remove($code);
        }
        (new Notification(true, __('Promotion removed', 'voucherify')))->print_notification();
        wp_send_json_success();
    }
}

