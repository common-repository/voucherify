<?php

namespace Voucherify\Wordpress\Common\Helpers;

use Automattic\WooCommerce\Admin\Overrides\Order;
use Voucherify\Wordpress\Handlers\GiftCards\GiftCardsTotalsCalculator;
use WC_Customer;
use WC_Data;
use WC_Order;
use WC_Order_Item_Product;
use WC_Order_Item_Shipping;
use WC_Product;
use WC_Product_Subscription_Variation;
use WC_Subscriptions_Product;

class Commons
{
    public const VOUCHERIFY_LAST_SYNC_TIMESTAMP = '_voucherify_last_sync_timestamp';
    public const VOUCHERIFY_LAST_SYNC_GMT = '_voucherify_last_sync_gmt';

    function getOrderData(WC_Order $order = null)
    {
        $is_order_ready = ! empty($order) && $order->has_status(['pending', 'processing', 'completed']);
        if ( ! $is_order_ready && vcrf_is_wc_object_available()) {
            $cart_decorator = new CartDecorator(WC()->cart);

            return $cart_decorator->get_data();
        }

        // we're in admin panel or customer payment page
        if (empty($order)) {
            $order = vcrf_get_admin_order();
        }

        if (empty($order)) {
            return [];
        }

        return [
            'order' => [
                'source_id' => createVcrfOrderSourceId($order->get_id()),
                'amount'    => intval(round(100 * $this->getOrderAmount($order))),
                'items'     => $this->getItems($order),
                'customer'  => $this->convertWcOrderToVcrfCustomer($order),
                'metadata'  => [
                    'status' => $order->get_status(),
                    'type' => $this->getOrderType($order),
                    'data_source' => 'order'
                ]
            ]
        ];
    }

    private function getOrderType(WC_Order $order)
    {
        if (function_exists('wcs_order_contains_subscription')) {
            if (wcs_order_contains_subscription( $order->get_id(), ['parent', 'resubscribe'] )) {
                return "parent";
            } elseif (wcs_order_contains_subscription( $order->get_id(), ['renewal'] )) {
                return "renewal";
            }
        }

        return "normal";
    }

    public function getOrderTotalDiscount(WC_Order $order)
    {
        $giftCardsTotalCalc = new GiftCardsTotalsCalculator();

        return $giftCardsTotalCalc->calculateAppliedGiftCardsTotal($order)
               + doubleval($order->get_discount_total('edit'));
    }

    public function getOrderAmount(WC_Order $order, bool $skipItemsAddedByCoupons = true)
    {
        $orderAmount = $this->getOrderTotalDiscount($order)
                       + doubleval($order->get_total('edit'))
                       + doubleval($order->get_discount_tax('edit'));

        if ($skipItemsAddedByCoupons) {
            $orderAmount -= $this->getOrderTotalOfItemsAddedByCoupon($order);
        }

        return $orderAmount;
    }

    /**
     * Collects information about customer and prepares portion of context data
     * to be consumed by API.
     *
     * @param WC_Order|null $order
     *
     * @return array customer data in form of array accepted by API's context.
     */
    function getCustomerData(WC_Order $order = null)
    {
        if ( ! empty($order)) {
            return ['customer' => $this->convertWcOrderToVcrfCustomer($order)];
        }

        if (vcrf_is_wc_object_available()) {
            $customer_decorator = new CustomerDecorator(WC()->customer);

            return $customer_decorator->get_data();
        }

        if (empty($order)) {
            $order = vcrf_get_admin_order();
        }

        if (empty($order)) {
            return [];
        }

        return ['customer' => $this->convertWcOrderToVcrfCustomer($order)];
    }

    function addVcrfSyncMetadata(WC_Data $wc_data, $vcrf_id, $idKeyName = "")
    {
        if (is_a($wc_data, 'WC_Customer')) {
            $timestamp = get_user_meta($wc_data->get_id(), 'last_update', true);
            $wc_data->add_meta_data(self::VOUCHERIFY_LAST_SYNC_TIMESTAMP, $timestamp, true);
        } elseif (is_a($wc_data, 'WC_Product') || is_a($wc_data, 'WC_Order')) {
            $timestamp = get_post_modified_time('U', true, $wc_data->get_id());
            $wc_data->add_meta_data(self::VOUCHERIFY_LAST_SYNC_GMT, date('Y-m-d H:i:s', $timestamp), true);
        } else {
            return;
        }

        $vcrfIdKey = "_vcrf_id";
        if ( ! empty($idKeyName)) {
            $vcrfIdKey = $idKeyName;
        }

        $wc_data->add_meta_data($vcrfIdKey, $vcrf_id, true);
        $wc_data->save_meta_data();
    }

    public function getProductAvailableAttributes(WC_Product $product)
    {
        $available_attributes = [];

        /** @var \WC_Product_Attribute $attribute */
        foreach ($product->get_attributes() as $attribute) {
            if ($attribute->get_variation()) {
                $available_attributes[] = $attribute->get_name();
            }
        }

        return $available_attributes;
    }

    public function convertWcProductToVcrfProduct(WC_Product $product)
    {
        $availableAttributes = $this->getProductAvailableAttributes($product);

        $productVcrfStructure = [
            'name'       => $product->get_name(),
            'source_id'  => createVcrfProductSourceId($product->get_id()),
            'attributes' => $availableAttributes,
            'metadata'   => $this->getProductMetaData($product),
        ];

        if ($product->is_type('simple')) {
            $productVcrfStructure['price'] = intval(round($product->get_regular_price() * 100));
        } elseif (class_exists(WC_Subscriptions_Product::class) && $product->is_type('subscription')) {
            $productRegularPrice = doubleval(WC_Subscriptions_Product::get_regular_price($product, 'edit'));
            $productVcrfStructure['price'] = intval(round($productRegularPrice * 100));
        }

        $productImage = wp_get_attachment_image_url($product->get_image_id());
        if ( ! empty($productImage)) {
            $productVcrfStructure['image_url'] = $productImage;
        }

        return $productVcrfStructure;
    }

    public function convertToVcrfSku(WC_Product $product)
    {
        $variation_attrs = null;
        if ($product->is_type(['variation', 'subscription_variation'])) {
            $available_attributes = $this->getProductAvailableAttributes(wc_get_product($product->get_parent_id()));
            $variation_attrs = [];
            foreach ($available_attributes as $attribute_name) {
                $variation_attrs[$attribute_name] = $product->get_attribute($attribute_name);
            }
        }

        $sku = [
            'sku'        => $product->get_name(),
            'source_id'  => createVcrfVariantSourceId($product->get_id()),
            'attributes' => empty($variation_attrs) ? null : $variation_attrs,
            'price'      => doubleval($product->get_regular_price('edit')) * 100,
            'metadata'   => $this->getProductMetaData($product),
        ];

        $productImage = wp_get_attachment_image_url($product->get_image_id());
        if ( ! empty($productImage)) {
            $sku['image_url'] = $productImage;
        }

        return $sku;
    }

    public function convertWcOrderToVcrfCustomer(WC_Order $order)
    {
        $customer = [
            'name'    => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'email'   => $order->get_billing_email(),
            'address' => [
                'city'        => $order->get_billing_city(),
                'state'       => $order->get_billing_state(),
                'line_1'      => $order->get_billing_address_1(),
                'line_2'      => $order->get_billing_address_2(),
                'country'     => $order->get_billing_country(),
                'postal_code' => $order->get_billing_postcode()
            ]
        ];

        $wcCustomerId = $order->get_customer_id();
        if ( ! empty($wcCustomerId)) {
            $customer['source_id'] = createVcrfCustomerSourceId($wcCustomerId);
        } else {
            $customer['source_id'] = createVcrfGuestCustomerSourceId($order->get_id());
        }

        return $customer;
    }

    public function convertWcCustomerToVcrfCustomer(WC_Customer $customer)
    {
        return [
            'source_id' => createVcrfCustomerSourceId($customer->get_id()),
            'name'      => $customer->get_billing_first_name() . ' ' . $customer->get_billing_last_name(),
            'email'     => $customer->get_billing_email(),
            'address'   => [
                'city'        => $customer->get_billing_city(),
                'state'       => $customer->get_billing_state(),
                'line_1'      => $customer->get_billing_address_1(),
                'line_2'      => $customer->get_billing_address_2(),
                'country'     => $customer->get_billing_country(),
                'postal_code' => $customer->get_billing_postcode()
            ]
        ];
    }

    /**
     * @param $order
     *
     * @return array
     */
    public function getItems(WC_Order $order, bool $skipItemsAddedByCoupons = true)
    {
        /** @var WC_Order_Item_Product[] $orderItems */
        $orderItems = $order->get_items();

        $items = [];
        foreach ($orderItems as $item) {
            $itemProperties = [];

            $quantity = $item->get_quantity();
            $itemInitialAmount = $item->get_subtotal();
            if (wc_tax_enabled()) {
                $itemInitialAmount += doubleval($item->get_subtotal_tax());
            }
            $price = intval(round(100 * $itemInitialAmount / $quantity));

            if ($item->get_meta('_added_by_vcrf_coupon', true) ?? false) {
                $quantityAddedByCoupons = $item->get_meta('_vcrf_added_quantity') ?? 0;
                $quantityAddedByCustomer = max($quantity - intval($quantityAddedByCoupons), 0);
                $discountedQuantity = $item->get_meta('_vcrf_discounted_quantity') ?? 0;

                if ($skipItemsAddedByCoupons) {
                    $quantity = $quantityAddedByCustomer;
                } else {
                    if (!empty($discountedQuantity)) {
                        $itemProperties['discount_quantity'] = $discountedQuantity;
                    }

                    if (!empty($quantityAddedByCustomer)) {
                        $itemProperties['initial_quantity'] = $quantityAddedByCustomer;
                    }

                    $itemProperties['discount_amount'] = $discountedQuantity * $price;
                }

                if ($quantity <= 0) {
                    continue;
                }
            }

            $product                 = $item->get_product();

            if (in_array($product->get_type(), ['variation', 'subscription_variation'])) {
                $sourceId = createVcrfVariantSourceId($item->get_product_id());
                $relatedObject = 'sku';
            } else {
                $sourceId = createVcrfProductSourceId($item->get_product_id());
                $relatedObject = 'product';
            }

            $itemProperties['source_id']      = $sourceId;
            $itemProperties['related_object'] = $relatedObject;
            $itemProperties['quantity']       = $quantity;
            $itemProperties['price']          = $price;

            $items[] = $itemProperties;
        }

        $shippingAmount = array_reduce(
            $order->get_shipping_methods(),
            function ($carry, WC_Order_Item_Shipping $shippingMethod) {
                $carry += doubleval($shippingMethod->get_total('edit'));
                if (wc_tax_enabled()) {
                    $carry += doubleval($shippingMethod->get_total_tax('edit'));
                }

                return $carry;
            },
            0
        );

        if ($shippingAmount > 0) {
            $items[] = [
                'product_id' => 'prod_5h1pp1ng',
                'quantity'   => 1,
                'price'      => $shippingAmount * 100
            ];
        }

        return $items;
    }

    private function getOrderTotalOfItemsAddedByCoupon(WC_Order $order)
    {
        $orderItems = $order->get_items();
        $addedItemsTotalAmount = 0;
        foreach ($orderItems as $item) {
            if ($item->get_meta('_added_by_vcrf_coupon') ?? false) {
                $addedQuantity = intval($item->get_meta('_vcrf_added_quantity') ?? 0);
                $totalQuantity = $item->get_quantity();
                $realAddedQuantity = max(min($totalQuantity, $addedQuantity), 0);

                if ($realAddedQuantity <= 0) {
                    continue;
                }

                $itemAmount = intval(round(wc_add_number_precision($item->get_subtotal() + $item->get_subtotal_tax())));
                $itemPrice = intval(round($itemAmount / $item->get_quantity()));
                $addedItemsTotalAmount += wc_remove_number_precision($itemPrice * $realAddedQuantity);
            }
        }

        return $addedItemsTotalAmount;
    }

    private function getProductMetaData(WC_Product $product)
    {
        $metadata = [
            'description' => $product->get_description()
        ];

        if (class_exists(WC_Product_Subscription_Variation::class) && $product instanceof WC_Product_Subscription_Variation) {
            $metadata = array_merge($metadata, [
                'signup_fee' => WC_Subscriptions_Product::get_sign_up_fee($product),
                'subscription_period' => WC_Subscriptions_Product::get_period($product),
                'subscription_period_interval' => WC_Subscriptions_Product::get_interval($product),
                'subscription_length' => WC_Subscriptions_Product::get_length($product),
                'free_trial_length' => WC_Subscriptions_Product::get_trial_length($product),
                'free_trial_period' => WC_Subscriptions_Product::get_trial_length($product),
            ]);
        }

        return $metadata;
    }

    public function convertWcStatusToVcrfOrderStatus($wcStatus)
    {
        switch ($wcStatus) {
            case 'processing':
                $vcrfStatus = 'PAID';
                break;
            case 'completed':
                $vcrfStatus = 'FULFILLED';
                break;
            case 'cancelled':
            case 'failed':
            case 'refunded':
                $vcrfStatus = 'CANCELED';
                break;
            default:
                $vcrfStatus = 'CREATED';
        }

        return $vcrfStatus;
    }
}
