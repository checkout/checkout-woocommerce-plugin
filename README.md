# Checkout.com WooCommerce Plugin - Flow Integration

Checkout.com Payment Gateway plugin for WooCommerce with Flow integration support.

## Version

**Current Version:** 5.0.0

## Features

### Flow Integration
- **Checkout.com Flow** - Modern, secure payment processing using Checkout.com's Flow Web Components
- **Saved Cards** - Customers can save payment methods for future use
- **3D Secure (3DS)** - Full support for 3D Secure authentication
- **Card Validation** - Real-time card validation before order creation
- **Webhook Processing** - Reliable webhook handling with queue system
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
3. **Webhook URL** - Configure in Checkout.com Hub:
   ```
   https://your-site.com/?wc-api=wc_checkoutcom_webhook
   ```

### Flow Integration Settings

- Enable Flow payment method
- Configure Flow appearance and behavior
- Set up saved cards functionality
- Configure 3DS settings

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
5. Payment status is confirmed via webhook
6. Order status is updated accordingly

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
│       │   └── flow-customization.js
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

## Support

For support and integration help:
- **Integration Support**: integration@checkout.com
- **General Support**: support@checkout.com
- **Sales**: sales@checkout.com

## License

MIT License

## Changelog

### Version 5.0.0
- Initial Flow integration release
- Complete Flow Web Components integration
- Saved cards functionality
- 3D Secure support
- Webhook queue system
- Enhanced order management
- Comprehensive validation and error handling

## Documentation

For detailed documentation, visit: [Checkout.com Documentation](https://docs.checkout.com)

---

**Checkout.com** is authorised and regulated as a Payment institution by the UK Financial Conduct Authority.
