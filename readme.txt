=== AuroPay Gateway for wooCommerce===
Contributors: woocommerce
Requires at least: 5.6
Tested up to: 5.6
Stable tag: 1.2.0
Requires PHP: 5.6
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Tags: wordpress,woocommerce,auropay,payment,gateway,cashfree

Allows you to use Auropay payment gateway with the WooCommerce plugin.
Accept Credit/Debit card  payments using AuroPay directly on your WooCommerce shop, and issue refunds.

== Description ==

Accept Credit/Debit card payments using AuroPay directly on your WooCommerce site.

Embed payment form (Iframe) directly in the checkout page.

You can choose the type of credit card you accept and their name & logo will automatically appear in the payment selection page (in new page mode).

This is compatible with WooCommerce>=5.4

Developed by AuroPay.net

== Dependencies ==

1. Wordpress 5.6 and later
2. Woocommerce 5.4.1 and later
3. PHP 5.6.0 and later

== Configuration ==

1. Visit the WooCommerce settings page, and click on the Checkout/Payment Gateways tab.
2. Click on Auropay to edit the settings. If you do not see Auropay in the list at the top of the screen make sure you have activated the plugin in the WordPress Plugin Manager.
3. Enable the Payment Method, name it Credit Card / Debit Card / Internet Banking (this will show up on the payment page your customer sees), add in your Key id and Key Secret.


== Frequently Asked Questions ==

= Does this support both production mode and sandbox mode for testing? =
Yes, it does - production and Test (sandbox) mode is driven by the API keys you use with a checkbox in the admin settings to toggle between both.


== Screenshots ==
1. The Auropay payment gateway settings screen used to configure the main Auropay gateway.
2. Offer a range of payment methods such as local and alternative payment methods ex. wallet, credit card, debit card, upi and netbanking etc.

== Changelog ==

= 1.1.1 =
* Added validation for checkout page

= 1.1.0 =
* Added validation for setting page

== Upgrade Notice ==
= 1.2.0 =
* switch from 1.1 to 1.2 Added the animation loader after place the order

== License ==

The Auropay WooCommerce plugin is released under the GPLv3 license, same as that
of WordPress. See the LICENSE file for the complete LICENSE text.
