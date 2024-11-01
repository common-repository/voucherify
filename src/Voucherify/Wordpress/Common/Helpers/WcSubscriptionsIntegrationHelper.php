<?php

namespace Voucherify\Wordpress\Common\Helpers;

use WC_Subscriptions_Cart;

class WcSubscriptionsIntegrationHelper
{
    public static function doesCartContainsSubscriptions()
    {
        return class_exists(WC_Subscriptions_Cart::class) && WC_Subscriptions_Cart::cart_contains_subscription();
    }
}