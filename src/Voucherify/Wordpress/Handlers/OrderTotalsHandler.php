<?php

namespace Voucherify\Wordpress\Handlers;


use Voucherify\Wordpress\Discounts\DiscountsOrder;
use Voucherify\Wordpress\Handlers\GiftCards\GiftCardsTotalsCalculator;
use WC_Abstract_Order;
use WC_Cart;
use WC_Order;
use WC_Order_Item_Product;
use WC_Order_Item_Shipping;
use WC_Order_Item_Tax;
use WC_Subscription;

/**
 * Created by PhpStorm.
 * User: bbs
 * Date: 1/23/18
 * Time: 11:44 AM
 */
class OrderTotalsHandler
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

    public function setupHooks()
    {
        add_filter('wcs_renewal_order_created', [
            $this,
            'onRenewalOrderCreated'
        ]);

        add_filter('wcs_subscription_meta', [
            $this,
            'removeCouponsCopiedFromInitialOrder'
        ], 10, 3);

        add_action('woocommerce_checkout_create_subscription', [
            $this,
            'onBeforeCreateSubscription'
        ], 1000, 4);

        add_action('woocommerce_before_order_object_save', [
            $this,
            'onBeforeOrderSave'
        ], 10);

		add_action( 'woocommerce_checkout_update_order_meta', [
			$this,
			'onBeforeOrderMetaSave'
		] );

		add_action( 'woocommerce_store_api_checkout_update_order_meta', [
			$this,
			'onBeforeStoreApiOrderMetaSave'
		] );

        add_action('woocommerce_before_save_order_items', [
            $this,
            'beforeSaveOrderItems'
        ], 10, 2);

        add_action('woocommerce_order_before_calculate_totals', [
            $this,
            'beforeCalculateTotals'
        ], 20, 2);

        add_action('woocommerce_order_after_calculate_totals', [
            $this,
            'calculateTotals'
        ], 20, 2);

        add_action('woocommerce_admin_order_items_after_shipping', [
            $this,
            'addJsForUpdatingShippingLineItemValues'
        ]);

        add_action('woocommerce_admin_order_totals_after_shipping', [
            $this,
            'addJsForSwitchingSippingDiscountPlaces'
        ]);

        add_action('woocommerce_admin_order_totals_after_shipping', [
            $this,
            'addJsForShowingShippingAmountBeforeDiscount'
        ]);

        add_action('woocommerce_cart_emptied', [
            $this,
            'onCartEmptied'
        ]);

        add_filter('woocommerce_hidden_order_itemmeta', [$this, 'hiddenOrderItemMeta'], 50);

        add_filter( 'woocommerce_checkout_create_order_line_item_object', [$this, 'checkoutCreateOrderLineItemObject'], 10, 4);

        add_filter('woocommerce_get_order_item_totals', [$this, 'getOrderItemTotals'], 10, 3);
    }

    public function getOrderItemTotals($total_rows, WC_Abstract_Order $order, $tax_display)
    {
        if (!isset($total_rows['shipping']) || !$order->meta_exists('_voucherify_original_shipping_price') || !$order->meta_exists('_voucherify_original_shipping_tax')) {
            return $total_rows;
        }

        $discountedShippingTotal = $order->get_shipping_total('edit');
        $discountedShippingTax = $order->get_shipping_tax('edit');

        $order->set_shipping_total($order->get_meta('_voucherify_original_shipping_price'));
        $order->set_shipping_tax($order->get_meta('_voucherify_original_shipping_tax'));
        remove_filter('woocommerce_get_order_item_totals', [$this, 'getOrderItemTotals'], 10);
        $total_rows = $order->get_order_item_totals($tax_display);
        add_filter('woocommerce_get_order_item_totals', [$this, 'getOrderItemTotals'], 10, 3);

        $order->set_shipping_total($discountedShippingTotal);
        $order->set_shipping_tax($discountedShippingTax);

        return $total_rows;
    }

    public function hiddenOrderItemMeta($args) {
        $args[] = '_added_by_vcrf_coupon';
        $args[] = '_vcrf_added_quantity';
        $args[] = '_vcrf_discounted_quantity';
        return $args;
    }

    public function checkoutCreateOrderLineItemObject(WC_Order_Item_Product $item, $cart_item_key, $values, $order) {
        if (isset($values['added_by_vcrf_coupon']) && $values['added_by_vcrf_coupon']) {
            $item->add_meta_data('_added_by_vcrf_coupon', $values['added_by_vcrf_coupon'], true);
            $item->add_meta_data('_vcrf_added_quantity', $values['vcrf_added_quantity'], true);
            $item->add_meta_data('_vcrf_discounted_quantity', $values['vcrf_discounted_quantity'], true);
        }

        return $item;
    }

    public function onCartEmptied() {
        $this->voucherifyHandler->getDiscountVouchersHandler()->getOrderService()->clearVouchersSession();
        $this->voucherifyHandler->getPromotionsHandler()->getOrderService()->clearVouchersSession();
        $this->voucherifyHandler->getGiftCardsHandler()->getOrderService()->clearVouchersSession();
    }

    public function removeCouponsCopiedFromInitialOrder($meta, $subscription, $order)
    {
        return array_filter($meta, function ($metaItem) {
            return ! in_array($metaItem['meta_key'], [
                '_vcrf_applied_promotions',
                '_vcrf_applied_gift_cards',
                '_vcrf_applied_discount_vouchers',
            ]);
        });
    }

    public function onBeforeCreateSubscription(
        WC_Subscription $subscription,
        array $posted_data,
        WC_Order $order,
        WC_Cart $cart
    ) {
        $this->voucherifyHandler->moveVouchersFromSessionToOrder($cart, $subscription);
        $subscription->calculate_totals();
    }

    public function onRenewalOrderCreated(WC_Order $order) {

        $this->voucherifyHandler->revalidateOrder($order);

        return $order;
    }

    public function onBeforeOrderMetaSave($orderId)
    {
        $order = wc_get_order($orderId);
        $this->voucherifyHandler->moveVouchersFromSessionToOrder(WC()->cart, $order);
        $order->calculate_totals();
    }

	/**
	 * @param WC_Abstract_Order $order
	 * @return void
	 */
	public function onBeforeStoreApiOrderMetaSave($order)
	{
		$this->onBeforeOrderMetaSave($order->get_id());
	}

    public function onBeforeOrderSave(WC_Order $order)
    {
        $changes = $order->get_changes();
        if (empty($changes['status'])) {
            return;
        }

        $old_data   = $order->get_data();
        $new_status = $changes['status'];
        $old_status = $old_data['status'] ?? null;

        if (in_array($new_status, ['cancelled'])) {
            return;
        }

        if ( ! in_array($new_status, ['processing', 'completed'])
             || in_array($old_status, ['processing', 'completed'])) {
            return;
        }

        //status was changed to processing or completed
        $this->voucherifyHandler->redeem($order);
    }

    public function beforeSaveOrderItems($order_id, $items)
    {
        $this->calculateUnitTypeDiscountOrderItems(wc_get_order($order_id));
    }

    public function beforeCalculateTotals($and_taxes, WC_Abstract_Order $order)
    {
        if ( ! $order instanceof WC_Order) {
            return;
        }

        // Reset line item totals.
        foreach ($order->get_items() as $item) {
            $item->set_total($item->get_subtotal());
            $item->set_total_tax($item->get_subtotal_tax());
        }
    }

    public function calculateTotals($and_taxes, WC_Abstract_Order $order)
    {
        if ($order instanceof \WC_Order_Refund) {
            return;
        }

        $discounts = new DiscountsOrder($order, true);
        foreach ($this->voucherifyHandler->getDiscountVouchersFromOrder($order) as $voucher) {
            $discounts->apply_coupon($voucher->getAsWcCoupon());
        }
        $this->applyDiscounts($order, $discounts->get_discounts());
        $this->applyGiftCards($order);
    }

    private function applyDiscounts(WC_Order $order, array $discounts)
    {
        $items_total     = 0;
        $items_total_tax = 0;
        /**
         * @var WC_Order_Item_Product $item
         */
        foreach ($order->get_items() as $item_key => $item) {
            $item->set_total($item->get_total() - $discounts['amounts'][$item_key] ?? 0);
            $item->set_total_tax($item->get_total_tax() - $discounts['total_tax'][$item_key] ?? 0);
            $itemTaxes = $item->get_taxes('edit');
            foreach($itemTaxes['subtotal'] as $taxId => $taxValue) {
                $itemTaxes['total'][$taxId] = $taxValue - $discounts['taxes'][$item_key][$taxId] ?? 0;
            }
            $item->set_taxes($itemTaxes);
            $item->save();
            $items_total     += $item->get_total();
            $items_total_tax += $item->get_total_tax();
        }

        $order->update_taxes();

        $net_discount_amounts = array_sum($discounts['amounts']) + $discounts['shipping_amount'] + array_sum($discounts['fees_amounts']);
        $tax_discount_amounts = array_sum($discounts['total_tax']) + $discounts['shipping_total_tax'] + array_sum($discounts['fees_total_tax']);

        $order->set_discount_total($net_discount_amounts);
        $order->set_discount_tax($tax_discount_amounts);

        /**
         * @var WC_Order_Item_Tax $tax
         */
        foreach ($order->get_items('tax') as $tax) {
            foreach ($discounts['shipping_taxes'] as $rate_id => $tax_discount) {
                if ($tax->get_rate_id() === $rate_id) {
                    $tax->set_shipping_tax_total($tax->get_shipping_tax_total() - $tax_discount);
                    $tax->save();
                }
            }
        }

        /**
         * @var WC_Order_Item_Shipping $shipping
         */
        foreach ($order->get_shipping_methods() as $shipping) {
            $shippingId = $shipping->get_id();
            if (empty($discounts['shipping_amounts'][$shippingId])) {
                continue;
            }

            $originalPrice   = wc_price($shipping->get_total());
            $originalTax     = wc_price($shipping->get_total_tax());
            $discountedPrice = empty($discounts['shipping_amounts'][$shippingId]) ?
                null : wc_price($shipping->get_total() - $discounts['shipping_amounts'][$shippingId]);
            $discountedTax   = empty($discounts['shipping_total_taxes'][$shippingId]) ?
                null : wc_price($shipping->get_total_tax() - $discounts['shipping_total_taxes'][$shippingId]);

            $shipping->update_meta_data('_voucherify_original_price', $originalPrice);
            $shipping->update_meta_data('_voucherify_original_tax', $originalTax);
            $shipping->update_meta_data('_voucherify_discounted_price',
                '<span class="voucherify_discounted_price">' . $discountedPrice . '</span>');
            $shipping->update_meta_data('_voucherify_discounted_tax',
                '<span class="voucherify_discounted_tax">' . $discountedTax . '</span>');
        }

        $shippingTotal = $order->get_shipping_total();
        $shippingTax = $order->get_shipping_tax();
        $discountedShippingTotal = $shippingTotal - $discounts['shipping_amount'];
        $discountedShippingTax = $shippingTax - $discounts['shipping_total_tax'];

        $order->update_meta_data('_voucherify_original_shipping_price', $shippingTotal);
        $order->update_meta_data('_voucherify_original_shipping_tax', $shippingTax);
        $order->update_meta_data('_voucherify_discounted_shipping_price', $discountedShippingTotal);
        $order->update_meta_data('_voucherify_discounted_shipping_tax', $discountedShippingTax);

        $order->set_shipping_tax($discountedShippingTax);
        $order->set_shipping_total($discountedShippingTotal);
        $order->set_cart_tax($items_total_tax);
        $total = $items_total + $items_total_tax + $discountedShippingTotal + $discountedShippingTax;
        $order->set_total(max(0, $total));
    }

    private function applyGiftCards(WC_Order $order)
    {
        $giftCards = $this->voucherifyHandler->getGiftCardsFromOrder($order);
        if (empty($giftCards)) {
            return;
        }
        $discount = (new GiftCardsTotalsCalculator)->calculate($giftCards);
        $order->set_total($order->get_total('edit') - $discount);
    }

    public function addJsForUpdatingShippingLineItemValues() {
        ?>
        <script>
            (function() {
                let discountedPrices = jQuery('.voucherify_discounted_price');
                discountedPrices.each(function () {
                    let discountedPrice = jQuery(this);
                    discountedPrice.closest('.shipping').find('.line_cost .view').html(discountedPrice.html());
                });

                let discountedTaxes = jQuery('.voucherify_discounted_tax');
                discountedTaxes.each(function () {
                    let discountedTax = jQuery(this);
                    discountedTax.closest('.shipping').find('.line_tax .view').html(discountedTax.html());
                });
            })();
        </script>
        <?php
    }

    public function addJsForSwitchingSippingDiscountPlaces($orderId)
    {
        $order = wc_get_order($orderId);

        if ($order->get_total_discount() <= 0 && !$order->get_shipping_methods()) {
            return;
        }

        $shippingLabel = __( 'Shipping:', 'woocommerce' );
        $couponsLabel = __( 'Coupon(s):', 'woocommerce' );

        ?>
        <script>
            (function() {
                let shippingEl = jQuery(".wc-order-totals tr td.label:contains('<?php echo esc_js($shippingLabel); ?>')")
                    .closest("tr");
                let couponsEl = jQuery(".wc-order-totals tr td.label:contains('<?php echo esc_js($couponsLabel); ?>')")
                    .closest("tr");

                shippingEl.insertBefore(couponsEl);
            })();
        </script>
        <?php
    }

    public function addJsForShowingShippingAmountBeforeDiscount($orderId)
    {
        $order = wc_get_order($orderId);

        if (!$order->get_shipping_methods()) {
            return;
        }

        $shippingLabel = __( 'Shipping:', 'woocommerce' );

        $shippingTotalWithoutDiscount = array_reduce($order->get_shipping_methods(), function (float $carry, WC_Order_Item_Shipping $shippingItem) {
            return $carry + (double)$shippingItem->get_total('edit');
        }, .0);
        $shippingTotalWithoutDiscount = wc_price( $shippingTotalWithoutDiscount, array( 'currency' => $order->get_currency() ) )

        ?>
        <script>
            (function() {
                let shippingEl2 = jQuery(".wc-order-totals tr td.label:contains('<?php echo esc_js($shippingLabel); ?>')")
                    .closest("tr");

                shippingEl2.find(".total").html('<?php echo $shippingTotalWithoutDiscount; ?>');
            })();
        </script>
        <?php
    }

    private function calculateUnitTypeDiscountOrderItems(WC_Order $order)
    {
        $unitTypeDiscountedItems = [];
        foreach ($this->voucherifyHandler->getDiscountVouchersFromOrder($order) as $voucher) {
            foreach ($voucher->getDiscountedOrderItems() as $discountItem) {
                $product = $discountItem->getWCProduct();
                if ( ! $product) {
                    continue;
                }

                /** @var WC_Order_Item_Product $orderItem */
                $orderItem = $this->getUnitTypeDiscountedOrderItem($order, $product);

                if ( ! empty($orderItem)) {
                    $initialQty = max(
                        $orderItem->get_quantity('edit') - $orderItem->get_meta('_vcrf_added_quantity'),
                        0
                    );

                    if ($discountItem->getEffect() === 'ADD_MISSING_ITEMS') {
                        $addedQuantity = max($discountItem->getQuantity() - $initialQty, 0);
                    } else {
                        $addedQuantity = $discountItem->getQuantity();
                    }

                    $orderItem->update_meta_data('_vcrf_added_quantity', $addedQuantity);
                    $orderItem->update_meta_data('_vcrf_discounted_quantity', $discountItem->getQuantity());

                    $orderItem->set_quantity($initialQty + $addedQuantity);
                } else {
                    $itemId    = $order->add_product($product, $discountItem->getQuantity(), ['order' => $order]);
                    $orderItem = $order->get_item($itemId);
                    $orderItem->add_meta_data('_added_by_vcrf_coupon', true);
                    $orderItem->add_meta_data('_vcrf_added_quantity', $discountItem->getQuantity());
                    $orderItem->add_meta_data('_vcrf_discounted_quantity', $discountItem->getQuantity());
                    $orderItem->save();
                }

                $unitTypeDiscountedItems[] = $orderItem->get_id();
            }
        }

        foreach ($order->get_items() as $orderItem) {
            if (($orderItem->get_meta('_added_by_vcrf_coupon') ?? false)
                && ! in_array($orderItem->get_id(), $unitTypeDiscountedItems)) {
                $addedQuantity = $orderItem->get_meta('_vcrf_added_quantity') ?? 0;
                $quantity      = max($orderItem->get_quantity() - $addedQuantity, 0);

                if ($quantity > 0) {
                    $orderItem->delete_meta_data('_added_by_vcrf_coupon');
                    $orderItem->delete_meta_data('_vcrf_added_quantity');
                    $orderItem->delete_meta_data('_vcrf_discounted_quantity');
                    $orderItem->set_quantity($quantity);
                } else {
                    $order->remove_item($orderItem->get_id());
                }
            }
        }

        $order->save();
    }

    private function getUnitTypeDiscountedOrderItem(WC_Order $order, \WC_Product $product)
    {
        foreach ($order->get_items() as $orderItem) {
            if ($orderItem->get_data()['product_id'] === $product->get_id()) {
                return $orderItem;
            }
        }

        return null;
    }
}
