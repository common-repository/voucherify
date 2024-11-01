<?php

namespace Voucherify\Wordpress\Handlers;

class UnlockOrderHandler
{

    /**
     * @var VoucherHandlersFacade
     */
    private $voucherifyHandler;

    public function __construct(
        VoucherHandlersFacade $voucherifyHandler
    ) {
        $this->voucherifyHandler = $voucherifyHandler;
    }

    private $time_left_in_seconds = 0;

    function add_unlock_order_meta_box()
    {
        $order = vcrf_get_admin_order();

        if (empty($order)) {
            return;
        }

        $this->time_left_in_seconds = 0;
        foreach ($this->voucherifyHandler->getAllFromOrder($order) as $voucher) {
            $ttl          = $voucher->getTtl();
            $created_time = $voucher->getCreatedTime();

            if ( ! empty($ttl) && ! empty($created_time)) {
                $time_left_in_seconds = $ttl * 24 * 60 * 60 - time() + $created_time;
                if ($time_left_in_seconds > $this->time_left_in_seconds) {
                    $this->time_left_in_seconds = $time_left_in_seconds;
                }
            }
        }
        if ($this->time_left_in_seconds == 0) {
            return;
        }

        add_meta_box('vcrf_unlock_order_fields', __('Voucherify session', 'voucherify'), [
            $this,
            'unlock_order_fields'
        ], 'shop_order', 'side', 'core');
    }


    function unlock_order_fields()
    {
        if ( ! empty($this->time_left_in_seconds)) {
            $days    = $this->time_left_in_seconds / 86400;
            $hours   = $this->time_left_in_seconds / 3600 % 24;
            $minutes = $this->time_left_in_seconds / 60 % 60;
            $seconds = $this->time_left_in_seconds % 60;
            _e('Time to release: ', 'voucherify');
            printf('%d %s %d %s %d %s %d %s',
                $days, _n('day', 'days', $days,'woocommerce'),
                $hours, _n('hour', 'hours', $hours, 'voucherify'),
                $minutes, _n('minute', 'minutes', $minutes, 'voucherify'),
                $seconds, _n('second', 'seconds', $seconds, 'voucherify'));
        }
        vcrf_include_partial('admin-order-ajax-handler.php');
    }

    public function unlock_order_ajax_handler()
    {
        $order = vcrf_get_admin_order();
        if ( ! empty($order)) {
            $this->voucherifyHandler->releaseVouchersSessionsFromOrder($order);
        }
    }

    public function setupHooks()
    {
        add_action('wp_ajax_vcrf_unlock_order', [
            $this,
            'unlock_order_ajax_handler'
        ], 10);

        add_action('add_meta_boxes', [
            $this,
            'add_unlock_order_meta_box'
        ], 10);
    }
}

