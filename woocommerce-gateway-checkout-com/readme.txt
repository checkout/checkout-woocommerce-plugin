=== Checkout.com Payment Gateway ===
Contributors: checkoutintegration
Tags: checkout, payments, credit card, Payment gateway, Apple pay, Payment request
Requires at least: 4.0
Stable tag: 4.1.16
Version: 4.1.16
Tested up to: 5.5
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
Users can place orders with the following alternative and local payment options used around the world: Alipay, Bancontact, Boleto, EPS, Fawry, Giropay, Ideal, Klarna, KNet, Poli, Sepa, Sofort.

= Google Pay Payments =
Users can place orders with a Google Pay wallet.

= Apple Pay Payments =
Users can place orders with an Apple Pay wallet.

= Saved Cards Payments =
Users can place orders with a payment card saved in their account.

= Woocommerce invoicing payment =
Merchant can send invoice email to customers for payment.

= Features =

* Credit Cards
* Google Pay
* Apple Pay
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