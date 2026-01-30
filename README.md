# Checkout.com WooCommerce Plugin - Flow Integration

Checkout.com Payment Gateway plugin for WooCommerce with Flow integration support.

## Version

**Current Version:** 5.0.2

## Features

### Flow Integration
- **Checkout.com Flow** - Modern, secure payment processing using Checkout.com's Flow Web Components
- **Saved Cards** - Customers can save payment methods for future use
- **3D Secure (3DS)** - Full support for 3D Secure authentication
- **3DS Return Handling** - Server-side processing on return to reduce client-side failures
- **Card Validation** - Real-time card validation before order creation
- **Webhook Processing** - Reliable webhook handling with queue system and duplicate detection
- **Order Management** - Automatic order status updates based on payment status

### Payment Methods Supported
- Credit/Debit Cards (via Flow)
- Saved Payment Methods
- Apple Pay
- Google Pay
- PayPal
- Alternative Payment Methods (APMs)

## Installation

1. Download the plugin zip file: `checkout-com-unified-payments-api.zip`
2. Go to WordPress Admin → Plugins → Add New → Upload Plugin
3. Upload the zip file
4. Activate the plugin
5. Configure your Checkout.com credentials in WooCommerce → Settings → Payments → Checkout.com Payment

## Configuration

### Required Settings

1. **Secret Key** - Your Checkout.com secret key
2. **Public Key** - Your Checkout.com public key
3. **Webhook URL** - Configure in Plugin Settings
   ```
   https://your-site.com/?wc-api=wc_checkoutcom_webhook
   ```

### Flow Integration Settings

- Enable Flow payment method
- Configure Flow appearance and behavior
- Set up saved cards functionality
- Configure 3DS settings

### Quick Setup (Flow)

1. In WordPress, go to **Plugins → Checkout.com Payment Gateway → Settings**.
2. If the Checkout.com gateways are disabled, enable them in **WooCommerce → Settings → Payments**.
3. In **Quick Setup**, set:
   - **Checkout Mode**: Flow
   - **Environment**: Sandbox (or Live)
   - **Account Type**: NAS (if applicable)
   - **Secret Key** and **Public Key**
   - **Payment Method Title**
4. Under **Webhook Status**, click **Refresh Status**.
5. If not configured, click **Register Webhook**.
6. (Optional) Set **Enabled Payment Methods** for Flow.
7. Click **Save changes**.

## Upgrade Your WooCommerce Integration to Flow



Follow this guide to upgrade your WooCommerce plugin to Flow.

### Before You Begin

- Ensure your WordPress and WooCommerce instances are up to date.
- You need the following Checkout.com API credentials:
  - Public key
  - Secret key
  - Signature key

### Key Considerations

- Your existing Checkout.com keys will continue to work after upgrading.
- Make sure all required APMs and wallet methods are selected under **Enabled Payment Methods** (Flow).
- Confirm with Checkout.com Support that those APMs and wallets are onboarded for **Flow** on your account.

### Upgrade Steps

1. **Install the updated plugin**
   - Use the WordPress plugin directory:
     - Go to **Plugins → Add New**.
     - Search for **Checkout.com Payment Gateway**.
     - Select **Install Now**, then **Activate Plugin**.
   - Or install manually:
     - Download the latest release from the WooCommerce plugin repository.
     - Go to **Plugins → Add New → Upload Plugin → Choose file**.
     - Upload the ZIP file and select **Install Now**, then **Activate Plugin**.
2. **Configure the plugin**
   - Go to **Checkout.com → Quick Settings**.
   - Set **Checkout Mode** to **Flow**.
   - Set **Environment** to **Sandbox** or **Live** depending on the environment you are upgrading.
   - Your existing **Public Key** and **Secret Key** will be pre-populated during upgrade.
   - Set **Payment Method Title**.
   - Use **Refresh Status** (Webhook) to check the webhook status.
   - If not registered, select **Register Webhook**.
   - If already registered, you will see a green confirmation line with the webhook URL.
   - Recheck **Card Settings** and **Order Settings**; existing values remain, no changes needed.
   - For **Express Payments**, follow the instructions under **Express Payments**.
   - For Flow look-and-feel changes, update **Flow Settings**.
3. **Test your updated integration**
   - Follow WooCommerce test instructions and confirm order updates in WooCommerce.

### Go Live

After validating the upgrade:
- Set **Environment** to **Live**.
- Set **Secret Key** and **Public Key** to your production keys.

## Apple Pay Setup Guide

**Path A: New merchant (Flow checkout)**
- Complete Checkout.com Apple Pay for web (Flow) setup:
  https://www.checkout.com/docs/payments/add-payment-methods/apple-pay/web
- Enable **Apple Pay** in **Quick Setup**.
- For **Fast Checkout** (Express Checkout), configure Apple Pay under **Express Checkout**:
  https://www.checkout.com/docs/payments/add-payment-methods/apple-pay/api-only

**Path B: Upgrading from classic checkout**
- Ask Checkout.com support to enable Apple Pay for **Flow** and share your **site URL/domain**:
  https://www.checkout.com/docs/payments/add-payment-methods/apple-pay/web
- Enable **Apple Pay** in **Quick Setup** and save.

**Path C: Express Checkout already configured**
- Go to **Express Checkout** settings and verify Apple Pay.
- Enable **Fast Checkout**.

### Express Payments (Apple Pay) Guide

Use this if you want Apple Pay buttons outside the Flow card form.

#### If you already have Apple Pay configured
- Go to **Checkout.com → Express Payments → Apple Pay**.
- You should see **✅ Setup Detected** with a prompt to run **Step 4: Test Certificate and Key**.
- Click **Test Certificate and Key** to verify compatibility with the new plugin version.
- No other changes are needed unless the test fails.

#### Full setup (new or reconfiguration)
Apple Pay setup requires moving between **Checkout.com Settings** and your **Apple Developer** account. You will generate files in the plugin, upload them to Apple, download certificates from Apple, and upload them back in the plugin.

1. **Step 1a: Generate Certificate Signing Request (CSR)**
   - Create a Merchant ID in Apple Developer if needed.
   - Generate the CSR in the plugin and upload it to Apple Developer within 24 hours.
2. **Step 1b: Upload Domain Association File**
   - Download the domain association file from Apple Developer and upload it here.
   - Verify the file is publicly accessible at:
     `https://YOUR-DOMAIN/.well-known/apple-developer-merchantid-domain-association.txt`
   - For Bitnami, you may need to place it manually:
     `sudo cp /path/to/apple-developer-merchantid-domain-association.txt /opt/bitnami/apps/letsencrypt/.well-known/apple-developer-merchantid-domain-association.txt`
3. **Step 3a: Generate Merchant Identity CSR and Key**
   - Generate `uploadMe.csr` and `certificate_sandbox.key`.
   - Upload the CSR to Apple Developer and download the signed certificate.
4. **Step 3b: Upload and Convert Merchant Identity Certificate**
   - Upload the signed certificate and convert it to PEM.
5. **Step 4: Test Certificate and Key (Final Verification)**
   - Ensure Merchant Identifier, Domain Name, Display Name, certificate, and key are configured.
   - Click **Test Certificate and Key**.

#### Apple Pay settings to verify
- **Enable Apple Pay**
- **Payment Method Title** and **Description**
- **Merchant Identifier**
- **Merchant Identity Certificate Path** and **Key Path**
- **Domain Name** and **Display Name**

#### Express Checkout settings
- Enable **Apple Pay / Google Pay**
- Choose where to show buttons (Product, Shop/Category, Cart, Checkout)
- Configure appearance (Theme, Button Language)

Docs: https://www.checkout.com/docs/payments/add-payment-methods/apple-pay/api-only

## Google Pay Setup Guide

**Path A: New merchant (Flow checkout)**
- Enable **Google Pay** in **Quick Setup**.
- For **Fast Checkout**, configure Google Pay under **Express Checkout**.

**Path B: Upgrading from classic checkout**
- Enable **Google Pay** in **Quick Setup** and save.

**Path C: Express Checkout already configured**
- Go to **Express Checkout** settings and verify Google Pay.
- Enable **Fast Checkout**.

## PayPal Setup Guide

**Path A: New merchant (Flow checkout)**
- Enable **PayPal** in **Quick Setup**.
- For **Fast Checkout**, configure PayPal under **Express Checkout**.

**Path B: Upgrading from classic checkout**
- Enable **PayPal** in **Quick Setup** and save.

**Path C: Express Checkout already configured**
- Go to **Express Checkout** settings and verify PayPal.
- Enable **Fast Checkout**.

## Flow Integration Details

### Overview

The Flow integration provides a modern, secure payment experience using Checkout.com's Flow Web Components. This integration ensures reliable payment processing with comprehensive validation, webhook handling, and order management.

### How Flow Integration Works

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
- **Client-Side Validation**: Flow component validates card details in real-time
- Card number, expiry, CVV are validated before submission
- Invalid cards are rejected before payment attempt

#### Step 5: Payment Processing
- Customer submits payment through Flow component
- Payment is processed securely through Checkout.com
- For 3D Secure: Customer is redirected for authentication
- Payment result is returned
- For 3DS return: server-side handler processes the return and redirects to success page

#### Step 6: Webhook Processing
- Checkout.com sends webhook with payment status
- Webhook is matched to order using:
  1. Order ID from metadata (primary)
  2. Payment Session ID + Payment ID (secondary)
  3. Payment ID alone (fallback)
- If order not found immediately, webhook is queued for later processing

#### Step 7: Order Status Update
- Order status is automatically updated based on payment result:
  - ✅ **Payment Approved** → Order status: `Processing`
  - ✅ **Payment Captured** → Order status: `Processing` (if not already)
  - ❌ **Payment Declined** → Order status: `Failed`
  - ⏸️ **Payment Cancelled** → Order status: `Cancelled`

### Key Features Explained

#### 🔒 Early Order Creation
**What it does:** Creates the WooCommerce order before payment processing begins.

**Why it's important:**
- Ensures webhooks can always find the order
- Prevents webhook matching failures
- Allows order tracking throughout the payment process

**How it works:**
- Order is created via AJAX when customer clicks "Place Order"
- Order is saved with `Pending payment` status
- Payment session ID is stored for webhook matching

#### ✅ Dual Validation System
**Client-Side Validation:**
- Flow component validates card details in real-time
- Prevents invalid cards from being submitted
- Provides instant feedback to customers

**Server-Side Validation:**
- Comprehensive validation of all checkout fields
- Validates billing/shipping addresses
- Ensures data integrity before order creation
- Blocks order creation if validation fails

#### 🚫 Duplicate Prevention
**Problem:** Multiple clicks or slow networks can cause duplicate orders.

**Solution:**
- Client-side lock prevents multiple simultaneous requests
- Server-side check prevents duplicate orders with same payment session ID
- If duplicate detected, existing order is returned instead of creating new one

#### 📬 Webhook Queue System
**Problem:** Webhooks might arrive before order is fully saved to database.

**Solution:**
- Webhook queue temporarily stores webhooks if order not found
- Queue is processed when order becomes available
- Ensures no webhooks are lost
- Automatic retry mechanism

#### 🔐 3D Secure (3DS) Support
**How it works:**
1. Payment requires 3DS authentication
2. Customer is redirected to bank's 3DS page
3. Customer completes authentication
4. Customer is redirected back to store
5. Server-side handler processes the return and redirects to success page
6. Payment status is confirmed via webhook
7. Order status is updated accordingly

**Features:**
- Automatic 3DS detection
- Seamless redirect flow
- Webhook handling after 3DS return
- Prevents duplicate status updates

#### 💳 Saved Cards
**How it works:**
1. Customer opts to save card during checkout
2. Card is tokenized securely by Checkout.com
3. Token is stored in customer's account
4. Saved cards appear on future checkouts
5. Customer can select saved card for quick checkout

**Security:**
- Cards are never stored on your server
- Only secure tokens are stored
- PCI compliance handled by Checkout.com
- Cards can be deleted by customer

### Payment Flow Diagram

```
Customer Checkout
    ↓
Fill Billing/Shipping Info
    ↓
Select Flow Payment Method
    ↓
Click "Place Order"
    ↓
[VALIDATION] Client-Side + Server-Side
    ↓
[ORDER CREATED] Status: Pending payment
    ↓
Create Payment Session with Checkout.com
    ↓
[FLOW COMPONENT] Card Details Entered
    ↓
[VALIDATION] Flow Component Validates Card
    ↓
Submit Payment
    ↓
[3DS?] If Required → Redirect → Authenticate → Return
    ↓
Payment Processed
    ↓
[WEBHOOK] Payment Status Received
    ↓
Match Webhook to Order
    ↓
Update Order Status
    ↓
[COMPLETE] Order Status: Processing/Failed
```

### Technical Architecture

#### Frontend (JavaScript)
- **payment-session.js**: Handles order creation, payment session, Flow component integration
- **flow-container.js**: Manages Flow component container and initialization
- **flow-customization.js**: Customizes Flow component appearance and behavior
- **modules/**: Flow modules (logger, state, 3DS detection, validation, guards)

#### Backend (PHP)
- **class-wc-gateway-checkout-com-flow.php**: Main gateway class, handles payment processing
- **Webhook Handler**: Processes incoming webhooks and updates orders
- **Webhook Queue**: Manages webhook queuing system

#### Database
- Order meta stores: Payment Session ID, Payment ID, Webhook IDs
- Webhook queue table: Temporary storage for unmatched webhooks

## Webhook Configuration

Configure the following webhook URL in your Checkout.com Hub:

```
https://your-site.com/?wc-api=wc_checkoutcom_webhook
```

### Webhook Events Supported

- `payment_approved`
- `payment_captured`
- `payment_declined`
- `payment_cancelled`
- `payment_voided`
- `payment_refunded`

## Development

### Building the Plugin

Use the build script to create the plugin zip:

```bash
./bin/build.sh
```

This will create `checkout-com-unified-payments-api.zip` with the correct WordPress plugin structure.

### File Structure

```
checkout-com-unified-payments-api/
├── woocommerce-gateway-checkout-com.php  # Main plugin file
├── flow-integration/                      # Flow integration
│   ├── class-wc-gateway-checkout-com-flow.php
│   └── assets/
│       ├── js/
│       │   ├── payment-session.js
│       │   ├── flow-container.js
│       │   ├── flow-customization.js
│       │   └── modules/
│       │       ├── flow-3ds-detection.js
│       │       ├── flow-container-ready-handler.js
│       │       ├── flow-field-change-handler.js
│       │       ├── flow-initialization.js
│       │       ├── flow-logger.js
│       │       ├── flow-saved-card-handler.js
│       │       ├── flow-state.js
│       │       ├── flow-terms-prevention.js
│       │       └── flow-updated-checkout-guard.js
│       └── css/
│           └── flow.css
├── includes/                              # Core functionality
│   ├── api/
│   ├── settings/
│   ├── admin/
│   └── ...
├── assets/                                # Frontend assets
├── lib/                                   # Libraries
└── vendor/                                # Composer dependencies
```

## Requirements

- WordPress 5.0+
- WooCommerce 3.0+
- PHP 7.3+
- SSL Certificate (required for production)
- Tested up to WordPress 6.7.0 and WooCommerce 8.3.1

## Support

For support and integration help:
- **Integration Support**: integration@checkout.com
- **General Support**: support@checkout.com
- **Sales**: sales@checkout.com

## License

GPL v2 or later

## Changelog

### Version 5.0.2
- Implement idempotent Flow initialization to prevent duplicate payment session requests
- Add generation tracking and single-flight lock mechanism for initialization
- Add destruction confirmation to handle transient DOM churn
- Optimize state logging to only log actual value changes
- Fix name field changes triggering Flow reload after initialization
- Production-ready improvements with comprehensive error handling

### Version 5.0.1
- Flow module refactor for stability and 3DS return handling
- Webhook duplicate detection and improved matching
- Checkout styling updates for payment method title spacing

## Documentation

For detailed documentation, visit: [Checkout.com Documentation](https://docs.checkout.com)

---

**Checkout.com** is authorised and regulated as a Payment institution by the UK Financial Conduct Authority.
