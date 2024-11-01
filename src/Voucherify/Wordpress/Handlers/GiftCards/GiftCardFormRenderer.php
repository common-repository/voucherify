<?php

namespace Voucherify\Wordpress\Handlers\GiftCards;


use Voucherify\Wordpress\Common\Helpers\WcSubscriptionsIntegrationHelper;
use Voucherify\Wordpress\Common\Models\Voucher;
use WC_Cart;
use WC_Order;

class GiftCardFormRenderer
{


    /**
     * @var GiftCardsSessionService
     */
    private $session;
    /**
     * @var GiftCardOrderVoucherMetaService
     */
    private $orderMetaService;

    public function __construct()
    {
        $this->session          = new GiftCardsSessionService();
        $this->orderMetaService = new GiftCardOrderVoucherMetaService();
    }

    function displayGiftVoucherLineItems()
    {
        foreach ($this->session->getAppliedVouchers(WC()->cart) as $voucher) : ?>
            <tr class="vcrf-discount">
                <th><?php esc_html_e('Gift Voucher', 'woocommerce'); ?>:<br> <?php echo $voucher->getCode(); ?></th>
                <td data-title="<?php esc_attr_e('Total',
                    'woocommerce'); ?>"><?php echo wc_price(-$voucher->getAmount()); ?>
                    [<a href="#" id="vcrf_remove_gift_card"
                        vcrf_gift_card="<?php echo $voucher->getCode() ?>">remove</a>]
                </td>
            </tr>
        <?php endforeach;
    }

    public function displayCartTotalWithoutDiscount() {
        $cart = WC()->cart;
        $appliedVouchers = $this->session->getAppliedVouchers($cart);
        $appliedVouchersAmounts = array_map(function(GiftVoucher $voucher) {
            return $voucher->getAmount();
        }, $appliedVouchers);

        $cartTotalWithoutDiscount = function ($cart_total) use ($appliedVouchersAmounts)
        {
            return $cart_total + array_sum($appliedVouchersAmounts);
        };

        ob_start();
        add_filter( 'woocommerce_cart_get_total', $cartTotalWithoutDiscount );
        remove_filter( 'woocommerce_cart_totals_order_total_html', [$this, 'displayCartTotalWithoutDiscount']);
        wc_cart_totals_order_total_html();
        add_filter( 'woocommerce_cart_totals_order_total_html', [$this, 'displayCartTotalWithoutDiscount']);
        remove_filter( 'woocommerce_cart_get_total', $cartTotalWithoutDiscount );
        return ob_get_clean();
    }

    public function displayRecurringTotalWithoutDiscount($order_total, WC_Cart $cart) {
        $appliedVouchers = $this->session->getAppliedVouchers($cart);
        $appliedVouchersAmounts = array_map(function(GiftVoucher $voucher) {
            return $voucher->getAmount();
        }, $appliedVouchers);

        $cartTotalWithoutDiscount = function ($cart_total) use ($appliedVouchersAmounts)
        {
            return $cart_total + array_sum($appliedVouchersAmounts);
        };

        ob_start();
        add_filter( 'woocommerce_cart_get_total', $cartTotalWithoutDiscount, 5 );
        remove_filter( 'woocommerce_cart_totals_order_total_html', [$this, 'displayCartTotalWithoutDiscount']);
        remove_filter( 'wcs_cart_totals_order_total_html', [$this, 'displayRecurringTotalWithoutDiscount'], 10, 2);
        wcs_cart_totals_order_total_html($cart);
        add_filter( 'wcs_cart_totals_order_total_html', [$this, 'displayRecurringTotalWithoutDiscount'], 10, 2);
        add_filter( 'woocommerce_cart_totals_order_total_html', [$this, 'displayCartTotalWithoutDiscount']);
        remove_filter( 'woocommerce_cart_get_total', $cartTotalWithoutDiscount, 5 );
        return ob_get_clean();
    }


    public function displayTotalToPay()
    {
        if (empty($this->session->getAppliedVouchers(WC()->cart))) {
            return;
        }
        ?>
        <tr class="order-total">
            <th><?php esc_html_e('Total to pay', 'voucherify'); ?></th>
            <td data-title="<?php esc_attr_e('Total to pay', 'voucherify'); ?>">
                <?php echo wc_price(WC()->cart->get_total('edit')) ?>
            </td>
        </tr>
        <?php
    }

    public function displayRecurringGiftCartPayment()
    {
        $giftCardsAmountsHtml = [];
        if (WcSubscriptionsIntegrationHelper::doesCartContainsSubscriptions()) {
            foreach (WC()->cart->recurring_carts as $recurringCart) {
                $vouchers = $this->session->getAppliedVouchers($recurringCart);
                if (empty($vouchers)) {
                    continue;
                }

                foreach ($vouchers as $voucher) {
                    $giftCartAmount = wcs_cart_price_string( -1 * $voucher->getAmount(), $recurringCart);
                    $giftCardsAmountsHtml[$voucher->getCode()][] = "<td>$giftCartAmount</td>";
                }
            }
        }

        if (empty($giftCardsAmountsHtml)) {
            return;
        }

        foreach ($giftCardsAmountsHtml as $giftCartCode => $giftCartHtmlParts) {
            ?>
            <tr class="cart-discount vcrf-cart-discount">
                <th rowspan="<?php echo count($giftCartHtmlParts); ?>">
                    <?php _e('Coupon', 'woocommerce'); ?>: <?php echo $giftCartCode; ?>
                </th>
                <?php echo join("</tr><tr  class='cart-discount vcrf-cart-discount'>", $giftCartHtmlParts); ?>
            </tr>
            <?php
        }
        ?>
        <tr class="cart-discount vcrf-cart-discount-notice">
            <td colspan="2">
                <?php _e('The discount will be applied to subsequent subscription renewals. The quantity of discounted renewals is defined by the coupon redemption limit.', 'voucherify'); ?>
            </td>
        </tr>
        <?php
    }

    public function displayRecurringTotalToPay()
    {
        if (!WcSubscriptionsIntegrationHelper::doesCartContainsSubscriptions()) {
            return;
        }

        $vouchersSum = array_reduce(WC()->cart->recurring_carts, function ($carry, WC_Cart $recurringCart) {
            return $carry +
                   array_sum(
                       array_map(
                           function (Voucher $voucher) {
                               return $voucher->getAmount();
                           },
                           $this->session->getAppliedVouchers($recurringCart)
                       )
                   );
        }, .0);

        if ($vouchersSum <= .0) {
            return;
        }

        $recurringTotalToPay = [];
        if (WcSubscriptionsIntegrationHelper::doesCartContainsSubscriptions()) {
            /** @var WC_Cart $recurringCart */
            foreach (WC()->cart->recurring_carts as $recurringCart) {
                $singleRecurringTotalToPay = wcs_cart_price_string( $recurringCart->get_total(), $recurringCart);

                $recurringTotalToPay[] =
                    "<td data-title='"
                    . esc_attr_e('Recurring total to pay', 'voucherify')
                    . "'>$singleRecurringTotalToPay</td>";
            }
        }

        if (empty($recurringTotalToPay)) {
            return;
        }
        ?>
        <tr class="cart-discount vcrf-cart-discount">
            <th rowspan="<?php echo count($recurringTotalToPay); ?>">
                <?php _e('Recurring total to pay', 'woocommerce'); ?>
            </th>
            <?php echo join("</tr><tr  class='order-total order-total-to-pay'>", $recurringTotalToPay); ?>
        </tr>
        <?php
    }

    function getCartTotalWithGiftCard($total)
    {
        return $total + (new GiftCardsTotalsCalculator())->calculate($this->session->getAppliedVouchers(WC()->cart));
    }

    function getOrderTotalWithGiftCard($total, $order)
    {
        return $total + (new GiftCardsTotalsCalculator())->calculate($this->orderMetaService->getAppliedVouchers($order));
    }

    function displayAdminTotals($orderId)
    {
        $order        = wc_get_order($orderId);
        $giftVouchers = $this->orderMetaService->getAppliedVouchers($order);
        if (empty($giftVouchers)) {
            return;
        }
        $giftVouchersGrossSum = (new GiftCardsTotalsCalculator())->calculate($giftVouchers);
        ?>
        <tr>
            <td class="label"><?php esc_html_e('Gift cards', 'voucherify'); ?>:</td>
            <td width="1%"></td>
            <td class="total">
                <?php echo wc_price($giftVouchersGrossSum, array('currency' => $order->get_currency())); ?>
            </td>
        </tr>
        <?php
    }

    // todo check this, what's this for?
    function getOrderItemTotals($total_rows, WC_Order $order, $tax_display)
    {
        $giftVouchers = $this->orderMetaService->getAppliedVouchers($order);
        if (empty($giftVouchers)) {
            return $total_rows;
        }
        add_filter('woocommerce_order_get_total', [$this, 'getOrderTotalWithGiftCard'], 1000, 2);
        $total_rows['order_total'] = array(
            'label' => __('Total:', 'woocommerce'),
            'value' => $order->get_formatted_order_total(get_option('woocommerce_tax_display_cart')),
        );
        remove_filter('woocommerce_order_get_total', [$this, 'getOrderTotalWithGiftCard'], 1000);

        $total_rows['order_total_gift_card'] = array(
            'label' => __('Gift Vouchers:', 'woocommerce'),
            'value' => wc_price((new GiftCardsTotalsCalculator())->calculate($giftVouchers)),
        );

        $total_rows['order_total_to_pay'] = array(
            'label' => __('Total to pay:', 'woocommerce'),
            'value' => $order->get_formatted_order_total(''),
        );

        return $total_rows;
    }
}
