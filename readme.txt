=== Checkout.com Payment Gateway ===
Contributors: checkoutintegration
Tags: checkout, payments, credit card, payment gateway, apple pay, google pay, payment request
Requires at least: 5.0
Stable tag: trunk
Requires PHP: 7.3
Tested up to: 6.4.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Checkout.com helps your business offer more payment methods and currencies to more customers. We provide best-in-class payment processing for credit card & alternative payment methods.

== Description ==

Checkout.com helps your business offer more payment methods and currencies to more customers. We provide best-in-class payment processing for credit card & alternative payment methods.

We combine gateway, international acquiring, and payment processing services, all through one integration with WooCommerce. So that you can accept more payments and support more customers around the world.

The Checkout.com plugin for WooCommerce allows shop owners to process online payments through the Checkout.com Payment Gateway.

This plugin is an integration of Checkout.com and offers 6 payment modes. This plugin is maintained by Checkout.com.

= Card Payments with Frames.js =
The payment form is embedded and shoppers complete payments without leaving your website. The Frames.js payment form is cross-browser and cross-device compatible, and can accept online payments from all major credit cards.

= Alternative Payments =
Users can place orders with the following alternative and local payment options used around the world: Alipay, Bancontact, Boleto, EPS, Fawry, Giropay, Ideal, Klarna, KNet, Poli, Sepa (WooCommerce subscription compatible), Sofort, Multibanco.

= Google Pay Payments =
Users can place orders with a Google Pay wallet.

= Apple Pay Payments =
Users can place orders with an Apple Pay wallet.

= PayPal Payments =
Users can place orders with an PayPal wallet.

= Saved Cards Payments =
Users can place orders with a payment card saved in their account.

= Woocommerce invoicing payment =
Merchant can send invoice email to customers for payment.

= Features =

* Credit Cards
* Google Pay
* Apple Pay
* PayPal
* ACH Payments
* DS 2.0
* Local Payment Methods

Contact us at: partnerships@checkout.com

= Contributors & Developers =

“Checkout.com Payment Gateway” is open source software. The following people have contributed to this plugin.

== Screenshots ==


1. Installation
2. Configuration
3. Checkout
4. Webhook


== Frequently Asked Questions ==


= What fees are associated with the gateway? =
Checkout.com uses Interchange++ which offers more transparency than other pricing models. With Checkout.com there are no minimums, no contracts required and pricing is based on your transaction volume and history.

Other payment processors charge a blended rate. We use the Interchange++ model, a payment processing fee structure that standardizes the commissions collected by card issuers such as Visa and Mastercard. The amount varies based on card type, the customer's issuing bank, merchant location, and other parameters.

Using Interchange++, we pass the fees charged by card brands directly through to you. Checkout.com charges a single, consistent mark-up that never changes, so you’ll have full transparency of your costs and know exactly what you’re paying. There are also no hidden fees, no setup fees and no account maintenance fees. Additionally, you’ll also get:

* Dedicated account management and service
* Risk and fraud management tools
* Integration with all major shopping carts

To learn more about our pricing, visit https://www.checkout.com/pricing

= What currencies and countries does the payment gateway support? =
**Countries** - the following are supported in the integration:
* Argentina
* Australia
* Austria
* Bahrain
* Belgium
* Brazil
* Bulgaria
* Canada
* Chile
* Colombia
* Croatia
* Cyprus
* Czech Republic
* Denmark
* Ecuador
* Egypt
* Estonia
* Finland
* France
* Germany
* Gibraltar
* Greece
* Guernsey
* Hong Kong
* Hungary
* Iceland
* Ireland
* Isle of Man
* Italy
* Japan
* Jersey
* Jordan
* Kuwait
* Latvia
* Liechtenstein
* Lithuania
* Luxembourg
* Malta
* Mexico
* Netherlands
* New Zealand
* Norway
* Oman
* Pakistan
* Peru
* Philippines
* Poland
* Portugal
* Qatar
* Romania
* Saudi Arabia
* Singapore
* Slovakia
* Slovenia
* Spain
* Sweden
* Switzerland
* Thailand
* UAE
* United Kingdom
* United States
* Uruguay

**Currencies**  - [150+ transaction currencies](https://docs.checkout.com/resources/codes/currency-codes) are supported in the integration

= Does SEPA support recurring payments like for subscription? =
Yes

= How do I contact the Payment Provider’s Support? =
support@checkout.com

== Installation ==

**From merchant’s WordPress admin**
1. Go to plugin section-> Add new
2. Search for "Checkout.com Payment Gateway"
3. Click on Install Now
4. Click on Activate
5. Click on Settings to configure the module
6. Configure the webhook url in your checkout hub account.

**Webhook URL:**
http://example.com/?wc-api=wc_checkoutcom_webhook

After the plugin has been configured, customers will be able to choose Checkout.com as a valid payment method.

== Changelog ==
v4.7.0 12th July 2024
- **[tweak]** Upgrade Klarna integration

v4.6.0 27th June 2024
- **[feat]** Upgrade PayPal with Express Checkout
- **[feat]** Update iDEAL APM with latest standard

v4.5.0 15th Mar 2024
- **[feat]** Upgrade checkout-sdk-php library
- **[feat]** Updated PayPal integration
- **[fix]** PHP notices and warnings

v4.4.20 31st Jan 2024
- **[tweak]** Add order note when 3ds redirection happens
- **[tweak]** Disable new webhook event from webhook registration

v4.4.19 17th Jan 2024
- **[tweak]** Add new Webhook event types
- **[fix]** PHP notices and warnings

v4.4.18 11th Jan 2024
- **[tweak]** Apple Pay button condition to show on checkout page
- **[fix]** Idempotency key not working after failed payment request

v4.4.17 4th Dec 2023
- **[fix]** Fix incorrect use of method

v4.4.16 24th Nov 2023
- **[tweak]** WC HPOS related fixes
- **[tweak]** Add new configuration section to add ABC fallback account for refund process
- **[fix]** PHP Non-static method call statically error

v4.4.15 26th Sep 2023
- **[tweak]** Update IBAN input field validation and styling

v4.4.14 20th March 2023
- **[tweak]** Update Giropay source property and description property

v4.4.13 15th March 2023
- **[tweak]** Add payment retry if 3ds redirection link is expired with idempotency key

v4.4.12 3rd March 2023
- **[tweak]** Update style of co-badged cards choice option
- **[tweak]** Implement idempotency key for payment request to avoid duplicate payment
- **[fix]** Credit card input filed not showing on small screen device
- **[fix]** CVV error when new card is used and CVV required for saved card is enabled

v4.4.11 8th February 2023
- **[tweak]** Update Google Pay logo and button styles
- **[fix]** WooCommerce order property access warning
- **[fix]** Fix JS error of undefined

v4.4.10 17th January 2023
- **[feat]** New settings to set custom placeholder for card input fields
- **[tweak]** Always show Refund button for any order status
- **[tweak]** Make CVV input position dynamic for saved cards
- **[fix]** Fix string missing translation

v4.4.9 16th December 2022
- **[feat]** Accept SEPA payment with free subscription.
- **[tweak]** Added new filter `checkout_apm_sepa_address` on checkout with SEPA for address.
- **[fix]** Ideal set hidden value.
- **[fix]** Fix missing string translation.

v4.4.8 4th November 2022
- **[fix]** Webhook notices logging.
- **[fix]** Card holder name send undefined on add payment screen.
- **[tweak]** Upgrade minimum required PHP version to 7.3
- **[tweak]** Apple Pay button JS event.
- **[tweak]** Gateway icon `woocommerce_gateway_icon` filter usage.
- **[tweak]** Add Google Pay & Apple Pay icon for gateway list on checkout.

v4.4.7 18th August 2022
- **[fix]** Apple Pay MADA support.
- **[fix]** Fix MOTO payment type.

v4.4.6 8th August 2022
- **[feat]** Add PayPal payment method.
- **[fix]** Fix MADA card not working Apple Pay.

v4.4.5 22nd July 2022
- **[tweak]** Add version number to all script enqueue.
- **[tweak]** Use WC session for save card on checkout.
- **[fix]** Payment method add not adding new if there is one card saved.

v4.4.4 7th July 2022
- **[fix]** Frames not showing if saved card is deactivate.

v4.4.3 7th July 2022
- **[feat]** Support for Carte Bancaire card.
- **[feat]** Google Pay and Apple Pay subscription support added.
- **[update]** Upgraded Checkout API SDK to latest version 2.5.1
- **[tweak]** Changed translation domain to match with plugin slug.
- **[tweak]** Improved frame and related script enqueue.
- **[tweak]** Refactoring code and fix PHPCS error in plugin.

v4.4.2 16th June 2022
- **[tweak]** Show refund button of completed orders.
- **[fix]** Default saved card deselects.
- **[fix]** Customer name update on card tokenization.

v4.4.1 1st June 2022
- **[tweak]** Add condition to not run code if order payment method is not cko.
- **[update]** Update function usage from PHP 8 to 7

v4.4.0 24th May 2022
- **[feat]** Add refund support for APMs and Apple Pay.
- **[update]** Upgraded Checkout API SDK to latest version 2.4.0
- **[fix]** Order status not changing on webhook received.
- **[fix]** Strings added for translation.

v4.3.9 21st Apr 2022
- **[feat]** Add NAS account type support.
- **[feat]** Add webhook registration and detection settings.
- **[tweak]** Remove order status change in refund webhook.
- **[fix]** Fix failed order status update on payment authorization.
- **[fix]** Fix PHP warnings.

v4.3.8 4th Apr 2022
- **[feat]** Subscription support for SEPA DD payment method.
- **[feat]** Text domain support with pot file.
- **[tweak]** 3Ds support to Google Pay method.
- **[update]** New 3Ds parameter for card type method.

v4.3.7 7th Mar 2022
- **[fix]** Fix PHP warning of missing file.

v4.3.6 1st Mar 2022
- **[feat]** Multibanco payment method added
- **[tweak]** Ability to pass customer IP address in payment requests
- **[fix]** WooCommerce Recurring Failed to Authorize and Terminate subscription
- **[fix]** DOC link '404 error' in core settings has been fixed
- **[update]** Trimmed token before sending to checkout.com

v4.3.5 15 Dec 2021
. Update cko php sdk to cater for Klarna
. Remove warning concerning php class_exists function used for subscription
. Update Ideal logo

v4.3.4 11 Aug 2021
. Fix conflict with the woo subscription plugin

v4.3.3 29 Jul 2021
. Fix conflict with subscription class

v4.3.2 28 Jul 2021
. Subscriptions-add payment source id on order
. Handle refunds with amount having comma seperator
. Prevent Auth and Capture having same order status on plugin configuration
. Add validation to handle MADA card for apple pay for Saudi Arabia
. Hide place order button when apple pay is selected
. Fix google pay environment
. handle php notices for php 7.4
. Include cardholder name in Frames
. Fix apple pay supported network and country code

v4.3.1 09 Jun 2021
. Add support for mada on apple pay

v4.3.0 14 Apr 2021
. Added support to handle recurring payments via WooCommerce Subscriptions
. Fixed payment declined webhook

v4.2.2 29 Mar 2021
. Fixed 3ds redirection when protocol is https
. Fixed fawry captured webhook
. Hide default place order button when google pay is selected
. Refactor Framejs integration
. Refactor APMs integration


v4.2.1 25 Feb 2021
. Set woo order id in metadata
. Restrict declined reason in error message in case of risk declined response
. Improve order note in backend orders
. Update auto capture flow
. Add support for approved webhook
. Update post meta with payment_captured when payment response status is captured
. Add validation to verify payment id when webhook is sent
. Set order number as reference when capture and refund action happens
. Fix UI for guest users
. Change label in Alternative payments setting
. Fix fawry product mismatch
. Fix message translation in case of declined errors


v4.2.0 25 Nov 2020
. The merchant can perform a partial refund in the Hub and it is reflected in Woo backend.
. The partial refund amount is deducted from the transaction order amount in Woo.
. The notification in Woo specifies that the transaction has been partially refunded.
. The merchant is able to perform a partial refund from Woo backend.
. The merchant is no longer required to use the private shared key for webhook authentication. The new release supports HMAC CKO Signature authentication.
. Core settings persist during update of plugin
. When performing a full refund in the Hub, the notification in Woo specifies that the transaction has been fully refunded.

v4.1.16 26 Oct 2020
. Bug Fix - Removed saved card checkbox for guest users

v4.1.15 29 Sep 2020
. Update Mada bins
. Fixed mada bin file path
. Fixed frames styling
. Added condition to enqueue google pay script only if its selected
. Remove timeout setting to allow iframe to load faster

v4.1.14 09 June 2020
. Display Fawry number in order confirmation page
. Fix fawry product qty in payment request

v4.1.13 09 June 2020
Fix Fawry payment for virtual product

v4.1.12 25 May 2020
Added support for payment declined webhook

v4.1.11 15 May 2020
. Fixed Sepa payment
. Added country validation based on apms

v4.1.10 05 May 2020
. Send integration data in udf5
. Added multi iframe option
. Minor css fix
. Fixed frames fallback localisation for EN

v4.1.9 16 March 2020
. Add display card icon option in module setting
. Add alert when place order is clicked and card not valid
. Add background color in frames field
. Add language fallback for frames localization
. Make translation text_domain uniform
. Fix JCB icon height and width

v4.1.8 17 Feb 2020
. Update frames js integration to frames v2

v4.1.7 12 Feb 2020
. Add function to get authorization value from header in case not apache web server
. Remove sanitize text field from apple token to fix undefined error

v4.1.6 06 Jan 2020
. Fixed cvv check validation
. Auto select new card when there is no saved card
. Fixed apple pay curl issue
. Add validation for shipping and billing address

v4.1.5 24 Dec 2019
. Fixed display issue for cvv field

v4.1.4 20 Dec 2019
. Fixed module settings when saving configuration

v4.1.3 20 Dec 2019
. Fixed settings not getting updated

v4.1.2 12 Dec 2019
. Save default module configuration on initial setup
. Minor bug fix

v4.1.1 09 Dec 2019
. Update plugin name
. Sanitize post fields
. Fixed bug related to mada cards

v4.1.0 18 Sep 2019
· Added support for apple pay
. Removed phone number field from card payment requests
. Fixed klarna bugs

v4.0.2 18 Sep 2019
· Improvements - Update metadata with additional information

v4.0.1 17 Sep 2019
· Bug Fix

v4.0.0 3 Sep 2019
· Checkout.com Woocommerce module for the Unified Payments API

== Upgrade Notice ==
= 4.1.16 =
4.1.16 - Bug Fix - Removed saved card checkbox for guest users
