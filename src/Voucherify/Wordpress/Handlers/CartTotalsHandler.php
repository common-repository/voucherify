<?php

namespace Voucherify\Wordpress\Handlers;


use Voucherify\Wordpress\Common\Helpers\WcSubscriptionsIntegrationHelper;
use Voucherify\Wordpress\Common\Services\SessionService;
use Voucherify\Wordpress\Common\Services\CartShippingService;
use Voucherify\Wordpress\Discounts\DiscountsCart;
use Voucherify\Wordpress\Handlers\GiftCards\GiftCardsTotalsCalculator;
use Voucherify\Wordpress\Handlers\GiftCards\GiftVoucher;
use WC_Cart;
use WC_Product_Variation;

class CartTotalsHandler
{
    /**
     * @var VoucherHandlersFacade
     */
    private $voucherifyHandler;

    /** @var CartShippingService */
    private $cartShippingService;

    public function __construct(VoucherHandlersFacade $voucherifyHandler, CartShippingService $shippingService)
    {
        $this->voucherifyHandler   = $voucherifyHandler;
        $this->cartShippingService = $shippingService;
    }

    public function setupHooks()
    {
        add_action('vcrf_voucher_added_to_cart', [$this, 'calculateDiscountsOnAddedToCartHook'], 10, 2);
        $this->addCalculateTotalsHook();

        add_filter('wcs_cart_totals_order_total_html', [
            $this,
            'addDiscountInfoToRecurringTotals'
        ], 10, 2);

        add_action( 'woocommerce_cart_totals_after_order_total', [ $this, 'displayDiscountSectionForRecurringCarts' ] );
        add_action( 'woocommerce_review_order_after_order_total', [ $this, 'displayDiscountSectionForRecurringCarts' ] );
    }

    private function addCalculateTotalsHook()
    {
        add_action('woocommerce_after_calculate_totals', [
            $this,
            'calculateTotals'
        ], 999);
    }

    private function removeCalculateTotalsHook()
    {
        remove_action('woocommerce_after_calculate_totals', [
            $this,
            'calculateTotals'
        ], 999);
    }

    public function calculateDiscountsOnAddedToCartHook($code, WC_Cart $cart) {
        $this->calculateDiscounts($cart);
    }

    public function calculateDiscounts(WC_Cart $cart)
    {
        $this->calculateUnitTypeDiscountCartItems($cart);
        $calculate_taxes = wc_tax_enabled() && ! $cart->get_customer()->get_is_vat_exempt();
        $discounts       = new DiscountsCart($this->cartShippingService, $cart, $calculate_taxes);

        foreach ($this->voucherifyHandler->getDiscountVouchersFromCart($cart) as $voucher) {
            $discounts->apply_coupon($voucher->getAsWcCoupon());
        }
        SessionService::setDiscounts($cart, $discounts->get_discounts());
    }

    public function applyMissingCouponsToRecurringCarts(WC_Cart $cart)
    {
        if (defined('VCRF_ADDING_VOUCHER') && VCRF_ADDING_VOUCHER) {
            return;
        }

        if (empty($cart->recurring_cart_key)) {
            return;
        }

        $applyOnRenewals = get_option('voucherify_wc_subs_apply_on_renewals', 'yes');
        if ($applyOnRenewals !== 'yes') {
            return;
        }

        $discountVouchersCartService = $this->voucherifyHandler
            ->getDiscountVouchersHandler()
            ->getCartService();

        $mainCart = WC()->cart;

        $discountVouchers = $discountVouchersCartService->getAppliedVouchers($mainCart);
        foreach ($discountVouchers as $voucher) {
            $discountVouchersCartService->addForCart($cart, $voucher->getCode());
        }

        $promotionsCartService = $this->voucherifyHandler
            ->getPromotionsHandler()
            ->getCartService();

        $promotions = $promotionsCartService->getAppliedVouchers($mainCart);
        foreach ($promotions as $voucher) {
            $promotionsCartService->addForCart($cart, $voucher->getCode());
        }

        $giftCardsCartService = $this->voucherifyHandler
            ->getGiftCardsHandler()
            ->getCartService();

        $giftCards = $giftCardsCartService->getAppliedVouchers($mainCart);
        /** @var GiftVoucher $voucher */
        foreach ($giftCards as $voucher) {
            $requestedAmount = $voucher->getRequestedAmount();
            if ($requestedAmount < 0) {
                $requestedAmount = $voucher->getAmount();
            }
            $giftCardsCartService->addForCart($cart, $voucher->getCode(), $requestedAmount);
        }
    }

    private function calculateUnitTypeDiscountCartItems(WC_Cart $cart)
    {
        $this->removeCalculateTotalsHook();
        $oldContext    = $cart->get_cart_contents();
        $existingItems = [];
        foreach ($cart->get_cart_contents() as $key => $cartContent) {
            if ($cartContent['added_by_vcrf_coupon'] ?? false) {
                $addedQuantity = $cartContent['vcrf_added_quantity'] ?? 0;
                $quantity      = max($cartContent['quantity'] - $addedQuantity, 0);

                if ($quantity > 0) {
                    $existingItems[$cartContent['product_id']] = $key;
                    if (isset($cartContent['variation_id'])) {
                        $existingItems[$cartContent['variation_id']] = $key;
                    }
                    $this->setCartMeta($cart, $key, [
                        'added_by_vcrf_coupon' => null
                    ]);
                    $cart->set_quantity($key, $quantity);
                } else {
                    $cart->remove_cart_item($key);
                }
            } else {
                $existingItems[$cartContent['product_id']] = $key;
                if (!empty($cartContent['variation_id'])) {
                    $existingItems[$cartContent['variation_id']] = $key;
                }
            }
        }

        foreach ($this->voucherifyHandler->getDiscountVouchersFromCart($cart) as $voucher) {
            foreach ($voucher->getDiscountedOrderItems() as $discountItem) {
                if ( ! $discountItem->getWCProduct()) {
                    continue;
                }
                $product        = $discountItem->getWCProduct();
                $productId      = $product->get_id();
                $inCartQuantity = $cart->get_cart_item_quantities()[$product instanceof WC_Product_Variation ? $product->get_parent_id() : $productId] ?? 0;
                if (key_exists($productId, $existingItems)) {
                    $key           = $existingItems[$productId];
                    $addedQuantity = $discountItem->getQuantity();
                    if ($discountItem->getEffect() === 'ADD_MISSING_ITEMS') {
                        $oldQuantity   = $oldContext[$key]['quantity'];
                        $addedQuantity = max($addedQuantity - $inCartQuantity, 0);
                        if ($oldQuantity > $inCartQuantity + $addedQuantity) {
                            $addedQuantity = 0;
                            $inCartQuantity = $oldQuantity;
                        }
                    }
                    $this->setCartMeta($cart, $key, [
                        'added_by_vcrf_coupon' => true,
                        'vcrf_added_quantity'  => $addedQuantity,
                        'vcrf_discounted_quantity' => $discountItem->getQuantity()
                    ]);
                    $cart->set_quantity($key, $inCartQuantity + $addedQuantity);
                } else {
                    $cart->add_to_cart($productId,
                        $discountItem->getQuantity(),
                        0,
                        [],
                        [
                            'added_by_vcrf_coupon' => true,
                            'vcrf_added_quantity'  => $discountItem->getQuantity(),
                            'vcrf_discounted_quantity' => $discountItem->getQuantity()
                        ]);
                }
            }
        }
        $this->addCalculateTotalsHook();
    }

    private function setCartMeta(WC_Cart $cart, $productKey, $meta)
    {
        $c = $cart->get_cart_contents();
        foreach ($meta as $key => $value) {
            if ($value == null) {
                unset($c[$productKey][$key]);
            } else {
                $c[$productKey][$key] = $value;
            }
        }
        $cart->set_cart_contents($c);
    }

    public function calculateTotals(WC_Cart $cart)
    {
        $this->applyDiscounts($cart);
        $this->applyGiftCards($cart);
        $cart->set_session();
    }

    private function applyDiscounts(WC_Cart $cart)
    {
        $this->voucherifyHandler->revalidate($cart);
        $this->calculateDiscounts($cart);
        $discounts = SessionService::getDiscounts($cart);

        if (empty($discounts)) {
            return;
        }

        foreach ($discounts['amounts'] as $item_key => $amount) {
            $cart->cart_contents[$item_key]['line_total'] = $cart->cart_contents[$item_key]['line_total'] - $amount;
        }

        foreach ($discounts['total_tax'] as $item_key => $total_tax) {
            $cart->cart_contents[$item_key]['line_tax'] = $cart->cart_contents[$item_key]['line_tax'] - $total_tax;
        }

        foreach ($discounts['taxes'] as $item_key => $taxes) {
            foreach ($taxes as $tax_id => $tax) {
                if (empty($cart->cart_contents[$item_key]['line_tax_data']['total'][$tax_id])) {
                    $cart->cart_contents[$item_key]['line_tax_data']['total'][$tax_id] = 0;
                }

                $cart->cart_contents[$item_key]['line_tax_data']['total'][$tax_id] -= $tax;
            }
        }

        $net_discount_amounts = array_sum($discounts['amounts']) + $discounts['shipping_amount'] + array_sum($discounts['fees_amounts']);
        $tax_discount_amounts = array_sum($discounts['total_tax']) + $discounts['shipping_total_tax'] + array_sum($discounts['fees_total_tax']);

        $cart_content_taxes = $cart->get_cart_contents_taxes();
        foreach ($discounts['taxes'] as $taxes) {
            foreach ($taxes as $tax_id => $tax) {
                if (isset($cart_content_taxes[$tax_id])) {
                    $cart_content_taxes[$tax_id] -= $tax;
                }
            }
        }
        $cart->set_cart_contents_taxes($cart_content_taxes);

        $cart_contents_total = $cart->get_cart_contents_total();
        $discount            = $net_discount_amounts;
        if (wc_prices_include_tax()) {
            $discount += $tax_discount_amounts;
        }

        $cart->set_cart_contents_total($cart_contents_total - $discount);
        $cart->set_cart_contents_tax(array_sum($cart_content_taxes));

        // todo this should be left with 0?
        $cart->set_discount_total($net_discount_amounts);
        $cart->set_discount_tax($tax_discount_amounts);

        $cart->set_fee_total($cart->get_fee_total() - array_sum($discounts['fees_amounts']));
        $cart->set_fee_tax($cart->get_fee_tax() - array_sum($discounts['fees_total_tax']));

        if ($cart->get_fee_tax() <= 0) {
            $fees_zero_taxes = array_map(function ($fees_tax) {
                return 0;
            }, $cart->get_fee_taxes());
            $cart->set_fee_taxes($fees_zero_taxes);
        } else {
            $fees_taxes = $cart->get_fee_taxes();
            foreach ($discounts['fees_taxes'] as $fee_taxes) {
                foreach ($fee_taxes as $tax_id => $tax) {
                    if (isset($fees_taxes[$tax_id])) {
                        $fees_taxes[$tax_id] -= $tax;
                    }
                }
            }
            $cart->set_fee_taxes($fees_taxes);
        }

        $shipping_taxes = $cart->get_shipping_taxes();
        foreach ($discounts['shipping_taxes'] as $tax_id => $tax) {
            if (isset($shipping_taxes[$tax_id])) {
                $shipping_taxes[$tax_id] -= $tax;
            }
        }
        $cart->set_shipping_taxes($shipping_taxes);

        $cart->set_total(max(0, $cart->get_total('edit') - $net_discount_amounts - $tax_discount_amounts));
        $cart->set_total_tax($cart->get_total_tax() - $tax_discount_amounts);
    }

    private function applyGiftCards(WC_Cart $cart)
    {
        $giftCards = $this->voucherifyHandler->getGiftCardsFromCart($cart);
        if (empty($giftCards)) {
            return;
        }
        $totalGross      = $cart->get_total('edit');
        $discount = (new GiftCardsTotalsCalculator)->calculate($giftCards);
        $cart->set_total(max(0, $totalGross - $discount));
    }

    public function addDiscountInfoToRecurringTotals($html, WC_Cart $cart)
    {
        foreach ($this->voucherifyHandler->getDiscountVouchersFromCart($cart) as $voucher) {
            $couponCode = $voucher->getCode();
            $discountText = sprintf(
                _x('Discount applied (%s): %s', 'Discount applied (<coupon code>): <amount>', 'voucherify'),
                $couponCode,
                wcs_cart_price_string(-1 * $voucher->getAmount(), $cart)
            );
            $html .= "<div class='hide-for-js'><small>$discountText</small></div>";
        }

        return $html;
    }

    public function displayDiscountSectionForRecurringCarts()
    {
        $discountsHtmlParts = [];
        if (WcSubscriptionsIntegrationHelper::doesCartContainsSubscriptions()) {
            foreach (WC()->cart->recurring_carts as $recurringCart) {
                $vouchers = $this->voucherifyHandler->getDiscountVouchersFromCart($recurringCart);
                if (empty($vouchers)) {
                    continue;
                }

                foreach ($vouchers as $voucher) {
                    $couponDiscount = wcs_cart_price_string( -1 * $voucher->getAmount(), $recurringCart);
                    $discountsHtmlParts[$voucher->getCode()][] = "<td>$couponDiscount</td>";
                }
            }
        }

        if (empty($discountsHtmlParts)) {
            return;
        }

        foreach ($discountsHtmlParts as $voucherCode => $voucherHtmlParts) {
            ?>
            <tr class="cart-discount vcrf-cart-discount hide-for-no-js">
                <th rowspan="<?php echo count($voucherHtmlParts); ?>">
                    <?php _e('Coupon', 'woocommerce'); ?>: <?php echo $voucherCode; ?>
                </th>
                <?php echo join("</tr><tr  class='cart-discount vcrf-cart-discount hide-for-no-js'>", $voucherHtmlParts); ?>
            </tr>
            <?php
        }
        ?>
        <tr class="cart-discount vcrf-cart-discount-notice">
            <td colspan="2">
            <?php _e('The discount will be applied to subsequent subscription renewals. The quantity of discounted renewals is defined by the coupon redemption limit.', 'voucherify'); ?>
            </td>
        </tr>
        <script>
            jQuery(document.body).on("updated_wc_div", function () {
                jQuery(".vcrf-cart-discount, .vcrf-cart-discount-notice")
                    .insertBefore(".order-total.recurring-total:first");
            });
            jQuery(document).ready(function() {
                jQuery(document.body).trigger("updated_wc_div");
            });
        </script>
        <?php
    }
}
