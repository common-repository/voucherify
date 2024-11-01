=== Voucherify ===
Contributors: rspective, robertklodzinski
Tags: voucherify, api, integration, woocommerce, rspective
Requires PHP: 7.4
Requires at least: 4.8
Tested up to: 6.4.3
WC tested up to: 8.6.1
WC requires at least: 5.3.3
Stable tag: 4.0.0

Integrates Voucherify API with woocommerce

== Description ==
[Voucherify](https://voucherify.io/?utm_source=woocommerce&utm_medium=docs&utm_campaign=extension) helps clients like Verivox, Book A Tiger and Helix build coupon generation, distribution and tracking unlike legacy coupon software. Voucherify is an infrastructure through API for software developers who are dissatisfied with high-maintenance custom coupon software.

Whether you're just starting out or you're an established enterprise with thousands of promotions, we have you covered.

Our API-first platform helps developers integrate digital promotions across any marketing channel or customer touchpoint - eventually giving full control over campaigns back to the marketing team.

= The Voucherify Framework covers the following features: =

- Multiple promotion campaigns: Coupons, Gifts, Discounts, Referral Programs, Loyalty Points
- E-commerce integration with Woocommerce checkout form
- Manager for distributing coupon codes to customers
- Redemptions tracking & monitoring

[An account in Voucherify](https://app.voucherify.io/#/signup?plan=standard?utm_source=woocommerce&utm_medium=docs&utm_campaign=extension) is required to leverage this plugin for Woocommerce. Every paid plan starts with the 30-day free trial ([pricing](https://www.voucherify.io/pricing?utm_source=woocommerce&utm_medium=docs&utm_campaign=extension)).

== Installation ==

= Requirements =

* PHP version 7.4 or greater
* MySQL version 5.6 or greater
* WordPress 6.4.3 (it's very probable it will work on earlier versions)
* WooCommerce 8.6.1 (it's very probable it will work on earlier versions)

= API Settings =

Provide App ID and App Secret Key from your [voucherify account](https://app.voucherify.io/#/signup?plan=standard?utm_source=woocommerce&utm_medium=docs&utm_campaign=extension), and make sure the option "Enable integration with Voucherify" is checked.

Learn more here: [https://docs.voucherify.io/docs](https://docs.voucherify.io/docs)

== Screenshots ==

1. Voucherify Panel
2. Campaigns
3. Voucherify Settings in Wordpress Admin Panel

== Changelog ==

= 4.0.0 - 2024-06-24 =
* Improvement: support for woocommerce-subscriptions plugin
* Improvement: support for unit type discount vouchers
* Improvement: support for Products, Orders and Customers synchronization
* Improvement: support for blockified cart

= 3.0.0 - 2022-04-19 =
* Improvement: Support for gift vouchers
* Improvement: WC version and voucherify plugin versions parameters sent in the Voucherify API request headers

= 2.5.1 - 2022-04-14 =
* Fix: typo

= 2.5.0 - 2022-04-01 =
* Improvement: added unit types (minutes, hours, days) to the lock session length setting
* Improvement: added wp token id in headers to all requests to the Voucherify API.

= 2.4.0 - 2022-03-03 =
* Improvement: added woocommerce order id as a order.source_id in the API call request

= 2.3.1 - 2021-12-21 =
* Fix: fatal error when trying to cancel order from the orders list in the admin panel

= 2.3.0 - 2021-11-29 =
* Fix: removed unecessary revalidation just before the redeem
* Improvement: a new setting in plugin options to override the default API endpoint

= 2.2.2 - 2021-09-12 =
* Fix: missing shipping info during the redeem

= 2.2.1 - 2021-09-07 =
* Fix: issues with order item prices sending to the API

= 2.2.0 - 2021-06-02 =
* Fix: fatal error while trying to remove a coupon that does not exists in voucherify anymore
* Fix: Supporting PayPal IPN

= 2.1.6 - 2021-05-10 =
* Fix: adding new discount code should properly replace the previous code in the cart
* Fix: prevent adding multiple discount codes to the order created via admin panel
* Fix: discount revalidation after cart update
* Fix: support for woocommerce-subscriptions plugin

= 2.1.5 - 2021-04-22 =
* Fix: missing file

= 2.1.4 - 2021-04-21 =
* Fix: Releasing voucherify session when order is cancelled
* Improvement: added session release button to the order edit screen

= 2.1.3 - 2021-04-19 =
* Fix: Supporting PayPal IPN

= 2.1.2 - 2021-03-27 =
* Fix: Supporting vouchers via Woocommerce REST API

= 2.1.1 - 2021-03-23 =
* Added Spanish Language Pack

= 2.1.0 - 2021-03-10 =
* Improvement: Added support for free shipping coupons
* Improvement: Added support for redeem rollback operation
* Improvement: Added support for separate discounts per products
* Improvement: Changed redeem to lock operation for orders placed by customers
* Fix: Sending product ID to the api
* Fix: Multiple issues with supporting vouchers in the WC admin panel

= 2.0.1 - 2020-08-14 =
* Fix: Added SKUs to the API request
* Fix: Vouchers application in the admin panel
* Fix: Redeem from Admin panel should be done when order is changed to processing or completed
* Fix: wrong total amount when redeem is made from the admin panel
* Fix: other bugs and exceptions when trying to add vouchers from the admin panel

= 2.0.0 - 2018-02-10 =
* Improvement: added integration with voucherify's promotions

= 1.0.1 - 2018-02-08 =
* Fix: Core Coupons was not visible when integration disabled (in voucherify settings, while plugin activated).

= 1.0.0 - 2018-01-27 =
* Initial implementation
