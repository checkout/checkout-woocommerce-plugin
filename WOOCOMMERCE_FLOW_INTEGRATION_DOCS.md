# WooCommerce Flow Integration Documentation

Last updated: January 2025

From downloading the plugin to requesting your first test payment, learn how to get started with the Checkout.com Flow integration for WooCommerce.

## Information

This guide assumes you have already set up WooCommerce on your WordPress instance.

---

## Before you start

You must create a public and private key to configure the integration.

Additionally, you need a signature key to configure webhooks.

### Create a public API key

1. Sign in to the sandbox environment in the Dashboard.
2. Select the _Developers_ icon in the top navigation bar, and then select the _Keys_ tab.
3. Select _Create a new key_.
4. When you're prompted for which type of key to create, select _Public API key_.
5. Give the API key a description to make it easier to identify in the future.
6. Disable the _Allow any processing channel_ setting.
7. Select the processing channel you want to use for WooCommerce from the list.
8. Select _Submit_ to create the key.

Make a note of your public API key as you'll need it for a later step. You can view your public API key at any time after creation.

### Create a private API key

1. Sign in to the sandbox environment in the Dashboard.
2. Select the _Developers_ icon in the top navigation bar, and then select the _Keys_ tab.
3. Select _Create a new key_.
4. When you're prompted for which type of key to create, select _Secret API key_.
5. Give the API key a description to make it easier to identify in the future.
6. Under _Scopes_, select _Default_.
7. Disable the _Allow any processing channel_ setting.
8. Select the processing channel you want to use for WooCommerce from the list.
9. Select _Create key_.
10. Copy your private API key securely. You'll need it to configure the plugin.

### Note

For security, you cannot view the secret API key again after you've left the _Create a new key_ page. Ensure you copy its value securely before you exit or close the window.

### Create a webhook

Webhooks are notifications that we send when an event occurs on your account. For example, when a payment is captured. The WooCommerce plugin uses them to update order statuses automatically.

You can configure a webhook in your WooCommerce settings.

**Webhook URL Format:**
```
https://your-site.com/?wc-api=wc_checkoutcom_webhook
```

### Check you have no previous version of the plugin

1. Sign in to WordPress as an administrator.
2. In the left menu, select _Plugins_.
3. Look for Checkout.com plugins. If you find one, select _Delete_, or select _Deactivate_ and then _Delete_.

---

## Install the plugin

You can install the plugin in the following ways:

### Use the WordPress plugin directory

1. Sign in to WordPress as an administrator.
2. In your WordPress dashboard, go to _Plugins_ > _Add New_.
3. Search for _Checkout.com Payment Gateway_.
4. Select _Install Now_.
5. After the installation completes, select _Activate Plugin_.

### Download the plugin and install it manually

1. Go to the WooCommerce plugin repository or download from GitHub releases.
2. Download the latest release of the plugin (`checkout-com-unified-payments-api.zip`).
3. Sign in to WordPress as an administrator.
4. In your WordPress dashboard, go to _Plugins_ > _Add New_.
5. Select _Upload Plugin_ > _Choose file_.
6. Upload your downloaded ZIP file.
7. Select _Install Now_.
8. After the installation completes, select _Activate Plugin_.

---

## Configure the plugin

1. In your WordPress dashboard, go to _WooCommerce_ > _Settings_ > _Payments_.
2. Find _Checkout.com Payment_ and select _Manage_.
3. Select _Enable Checkout.com card payments_.
4. Set the environment to _Sandbox_ (for testing) or _Live_ (for production).
5. Enter a payment option title. This is displayed to customers on your checkout page (e.g., "Credit/Debit Card").
6. Under _Checkout mode_, select _Flow_.
7. Set _Account type_ to _NAS_ (or your account type).
8. Enter your **Secret key** and **Public key**.
9. Select _Save changes_.
10. Select _Card Settings_, configure your preferences, then select _Save changes_.
11. Select _Order Settings_, review the order status mappings, then select _Save changes_.

### Register a webhook with the default configuration

To check the current webhook status and register a webhook with the default configuration:

1. In your WordPress dashboard, go to _WooCommerce_ > _Settings_ > _Payments_.
2. Find _Checkout.com Payment_ and select _Manage_.
3. Select the _Webhooks_ tab.
4. Select _Run Webhook check_ to check if a webhook is configured for the current site.

If no webhook is configured, select _Register Webhook_. This creates a new webhook for all events listed in your Dashboard account.

**Webhook Events Supported:**
- `payment_approved` - Payment authorized successfully
- `payment_captured` - Payment captured successfully
- `payment_declined` - Payment declined
- `payment_cancelled` - Payment cancelled
- `payment_voided` - Payment voided
- `payment_refunded` - Payment refunded

---

## Test your integration

1. Go to your shop's public URL and add a product to your cart.
2. Go to your cart then proceed to checkout.
3. Enter the required billing details. We recommend using a real email address so that you can receive the order confirmation.
4. Select the _Checkout.com Payment_ method.
5. The Flow payment form will appear. Enter the following card details:
   * Number – `4242 4242 4242 4242`
   * Expiry date – Any future date (e.g., `12/25`)
   * CVV – `100`
   * Cardholder name – Any name
6. Select the terms and conditions box.
7. Select _Place order_. 
   - The order will be created first (status: `Pending payment`)
   - Payment will be processed through Flow
   - If 3D Secure is required, you'll be redirected for authentication
   - After successful payment, you'll be redirected to the order confirmation page
8. If you entered a real email address in the billing details, you'll receive an order confirmation email.
9. Sign in to your WordPress account as an administrator.
10. Select _WooCommerce_ > _Orders_ in the left menu. Your test order is displayed and has a status of `Processing`. This indicates that the payment has been successfully captured and that your webhooks are set up correctly.

### Test Cards

For test cards and a range of possible scenarios, see [Checkout.com Testing Documentation](https://www.checkout.com/docs/testing).

**Common Test Cards:**
- **Success:** `4242 4242 4242 4242`
- **3D Secure Required:** `4000 0025 0000 3155`
- **Declined:** `4000 0000 0000 0002`
- **Insufficient Funds:** `4000 0000 0000 9995`

You can now either go live as is or extend your configuration.

---

## Go live

When your testing is complete and you're ready to start accepting payments:

1. Contact our Sales team to move to a live account.
2. Update your plugin settings:
   - Change _Environment_ from _Sandbox_ to _Live_
   - Update your _Secret key_ and _Public key_ with live credentials
   - Re-register your webhook URL in the live Dashboard
3. Test a small transaction to verify everything works correctly.

---

## How Flow Integration Works

The Flow integration provides a modern, secure payment experience using Checkout.com's Flow Web Components. This integration ensures reliable payment processing with comprehensive validation, webhook handling, and order management.

### Payment Flow Overview

The payment flow follows these steps:

#### Step 1: Checkout Page Load
- Customer fills out billing and shipping information
- Flow payment method is selected
- Flow Web Component is initialized and mounted

#### Step 2: Order Creation (Before Payment)
- **Why Early?** Orders are created via AJAX before payment processing begins
- This ensures the order exists in the database for webhook matching
- Order status: `Pending payment`
- Payment session ID is stored with the order

#### Step 3: Payment Session Creation
- Payment session is created with Checkout.com API
- Session includes order details, customer information, and amount
- Payment session ID is returned and stored

#### Step 4: Flow Component Validation
- **Client-Side Validation:** Flow component validates card details in real-time
- Card number, expiry, CVV are validated before submission
- Invalid cards are rejected before payment attempt

#### Step 5: Payment Processing
- Customer submits payment through Flow component
- Payment is processed securely through Checkout.com
- For 3D Secure: Customer is redirected for authentication
- Payment result is returned

#### Step 6: Webhook Processing
- Checkout.com sends webhook with payment status
- Webhook is matched to order using:
  1. **Order ID from metadata** (primary method)
  2. **Payment Session ID + Payment ID** (secondary method)
  3. **Payment ID alone** (fallback method)
- If order not found immediately, webhook is queued for later processing

#### Step 7: Order Status Update
- Order status is automatically updated based on payment result:
  * ✅ **Payment Approved** → Order status: `Processing` or `On hold` (if manual capture)
  * ✅ **Payment Captured** → Order status: `Processing`
  * ❌ **Payment Declined** → Order status: `Failed`
  * ⏸️ **Payment Cancelled** → Order status: `Cancelled`

### Key Features

#### 🔒 Early Order Creation
Orders are created before payment processing to ensure webhooks can always find the order. This prevents webhook matching failures and allows order tracking throughout the payment process.

#### ✅ Dual Validation System
- **Client-Side:** Flow component validates card details in real-time
- **Server-Side:** Comprehensive validation of all checkout fields before order creation

#### 🚫 Duplicate Prevention
- Client-side lock prevents multiple simultaneous requests
- Server-side check prevents duplicate orders with same payment session ID
- Webhook queue prevents duplicate webhook processing

#### 📬 Webhook Queue System
- Temporarily stores webhooks if order not found immediately
- Queue is processed when order becomes available
- Ensures no webhooks are lost
- Automatic retry mechanism

#### 🔐 3D Secure (3DS) Support
- Automatic 3DS detection
- Seamless redirect flow
- Webhook handling after 3DS return
- Prevents duplicate status updates

#### 💳 Saved Cards
- Customer can opt to save card during checkout
- Cards are tokenized securely by Checkout.com
- Saved cards appear on future checkouts
- Cards can be deleted by customer

---

## Extend your configuration

There are a number of ways you can extend your WooCommerce integration so that it suits all your business needs.

### Add more payment methods

#### Note
To start accepting an alternative payment method, we first need to enable it on your account. Contact your account manager or our Sales team to get started.

We currently support the following payment methods on WooCommerce:

* Apple Pay
* Google Pay
* PayPal
* Bancontact
* Cartes Bancaires
* EPS
* iDEAL
* Klarna Payments
* Klarna Debit Risk
* KNET
* Multibanco
* Poli
* Sofort

#### Enable alternative payments

1. Sign in to WordPress as an administrator.
2. In the left menu, select _WooCommerce_ > _Settings_ > _Payments_.
3. Find the payment method you want to enable (e.g., _Checkout.com - PayPal_).
4. Select _Manage_.
5. Tick _Enable Checkout.com_.
6. Enter a _Title_. This is what the customer sees on the checkout page.
7. Enter your API credentials if required.
8. Select _Save changes_.

That's it! Your checkout page now includes the option to pay using your additional payment method(s).

#### Apple Pay

##### Information
Apple Pay is only supported on self-hosted instances of WordPress.

##### Before you start
If you're located in the UAE or Saudi Arabia, contact your account manager or our Sales team to activate Apple Pay on your account.

To get started with Apple Pay payments, you must first generate your certificate signing request and upload it to the Apple Development Center.

Once this is done, you'll need to complete the certification process. Read our [Apple Pay guide](https://www.checkout.com/docs/payments/add-payment-methods/apple-pay) to configure your environment.

##### Enable Apple Pay

1. Sign in to WordPress as an administrator.
2. In the left menu, select _WooCommerce_ > _Settings_ > _Payments_.
3. Find _Checkout.com - Apple Pay_ and select _Manage_.
4. Select _Enable Checkout.com_.
5. Enter a title and description. These are displayed to customers on your checkout page.
6. Enter your merchant identifier. You can find it in the Apple Development Center.
7. Enter the absolute path to your merchant certificate and merchant certificate key.
8. Select a button type and button theme.
9. Set the button language using a two-digit ISO 639-1 code (for example, use `en` for English).
10. Select _Save changes_.

To test Apple Pay, use the Apple Pay test cards.

#### Google Pay

##### Before you start
If you're located in the UAE or Saudi Arabia, contact your account manager or our Sales team to activate Google Pay on your account.

To get started with Google Pay payments, you must register with Google Pay and choose Checkout.com as your payment processor.

##### Enable Google Pay

1. Sign in to WordPress as an administrator.
2. In the left menu, select _WooCommerce_ > _Settings_ > _Payments_.
3. Find _Checkout.com - Google Pay_ and select _Manage_.
4. Select _Enable Checkout.com_.
5. Enter a title and description. These are displayed to customers on your checkout page.
6. Leave the merchant identifier set to `01234567890123456789` for testing purposes.
7. To enable 3DS for Google Pay, set _Use 3D Secure_ to _Yes_.
8. Select a button style.
9. Select _Save changes_.

### Enable 3D Secure payments

1. Sign in to WordPress as an administrator.
2. In the left menu, select _WooCommerce_ > _Settings_ > _Payments_.
3. Find _Checkout.com Payment_ and select _Manage_.
4. Select _Card Settings_.
5. Set _Use 3D Secure_ to _Yes_.
6. Select _Save changes_.

3D Secure payments are now enabled on your account. When a payment requires 3DS authentication, customers will be automatically redirected to their bank's authentication page.

### Capture payments manually

#### Enable manual captures

1. Sign in to WordPress as an administrator.
2. In the left menu, select _WooCommerce_ > _Settings_ > _Payments_.
3. Find _Checkout.com Payment_ and select _Manage_.
4. Select _Card Settings_.
5. Set _Payment Action_ to _Authorize Only_.
6. Select _Save changes_.

Any payments received are authorized only. You must manually capture them within seven days, or they are automatically voided.

#### Capture a payment

1. In the Dashboard sandbox, select _Payments_ > _Processing_ > _All Payments_.
2. Select the test payment. The _Payment details_ page opens.
3. Select _Capture payment_ in the top right.
4. Select _Capture payment_. The _Status_ column on the _Payments_ page is updated to say `CAPTURED`.
5. Sign in to WordPress as an administrator.
6. Select _WooCommerce_ > _Orders_ in the left menu.
7. Select your test order to display the order details.

The order note confirms that your payment has been successfully captured.

### Accept recurring payments via the WooCommerce Subscriptions extension

With recurring payments, you can process shopper interactions for scheduled payments, such as subscription payments.

#### Note
To use this feature, you must be using WooCommerce Subscriptions to manage subscriptions within WooCommerce. See [WooCommerce Subscriptions Store Manager Guide](https://woocommerce.com/document/subscriptions/store-manager-guide/).

The Checkout.com WooCommerce plugin registers with payment events triggered by WooCommerce Subscriptions to support the following actions:

* Cancellation of a subscription
* Suspension of a subscription
* Re-activation of a subscription
* Change of amount for a subscription
* Change of date for a subscription
* Management of multiple subscriptions

### Configure order statuses

These settings allow you to edit the order statuses in line with the status of the payment. They are automatically set to WooCommerce's default values, so be aware that editing them may cause problems with the order flow.

To find these settings:

1. Sign in to WordPress as an administrator.
2. Go to _WooCommerce_ > _Settings_ > _Payments_.
3. Find the Checkout.com plugin.
4. Select _Manage_ and then _Order Settings_.

**Default Order Status Mappings:**
- **Payment Approved:** `Processing` (or `On hold` if manual capture enabled)
- **Payment Captured:** `Processing`
- **Payment Declined:** `Failed`
- **Payment Cancelled:** `Cancelled`
- **Payment Refunded:** `Refunded`

### Saved Cards Configuration

The Flow integration supports saving customer payment methods for future use.

#### Enable Saved Cards

1. Sign in to WordPress as an administrator.
2. Go to _WooCommerce_ > _Settings_ > _Payments_.
3. Find _Checkout.com Payment_ and select _Manage_.
4. Select _Card Settings_.
5. Enable _Save card for future use_ or _Enable tokenization_.
6. Select _Save changes_.

#### Saved Cards Display Options

You can configure how saved cards are displayed:

- **Saved Cards First:** Saved cards appear before the new card form
- **New Payment First:** New card form appears first, saved cards below

This can be configured in the Flow integration settings.

---

## Troubleshooting

### Flow Component Not Loading

If the Flow payment form is not appearing:

1. Check that Flow mode is enabled in settings
2. Verify your Public API key is correct
3. Check browser console for JavaScript errors
4. Ensure all required checkout fields are filled
5. Verify SSL certificate is valid (required for Flow)

### Webhooks Not Processing

If order statuses are not updating:

1. Verify webhook URL is registered in Dashboard
2. Check webhook signature key matches
3. Review webhook logs in WordPress (if logging enabled)
4. Check that webhook events are enabled in Dashboard
5. Verify order exists before webhook arrives (early order creation should handle this)

### Payment Session Errors

If you see payment session errors:

1. Verify all required billing fields are filled
2. Check email format is valid
3. Ensure amount and currency are set correctly
4. Verify API keys are correct for your environment
5. Check network connectivity to Checkout.com API

### 3D Secure Issues

If 3DS authentication is not working:

1. Verify 3DS is enabled in Card Settings
2. Check that test card requires 3DS (use `4000 0025 0000 3155`)
3. Ensure redirect URLs are configured correctly
4. Check that webhook is processing after 3DS return

---

## Support

For support and integration help:

* **Integration Support:** integration@checkout.com
* **General Support:** support@checkout.com
* **Sales:** sales@checkout.com
* **Documentation:** [Checkout.com Documentation](https://www.checkout.com/docs)

---

## Requirements

* WordPress 5.0+
* WooCommerce 3.0+
* PHP 7.3+
* SSL Certificate (required for production)
* Modern browser with JavaScript enabled

---

## License

MIT License

---

## Changelog

### Version 5.0.0

* Initial Flow integration release
* Complete Flow Web Components integration
* Saved cards functionality
* 3D Secure support
* Webhook queue system
* Enhanced order management
* Comprehensive validation and error handling
* Duplicate prevention (orders and webhooks)
* Early order creation for reliable webhook matching

---

**Checkout.com** is authorised and regulated as a Payment institution by the UK Financial Conduct Authority.


