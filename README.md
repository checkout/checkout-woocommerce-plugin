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

### How It Works

1. **Order Creation**: Order is created via AJAX before payment processing
2. **Payment Session**: Payment session is created with Checkout.com
3. **Flow Component**: Flow Web Component is mounted and validated
4. **Payment Processing**: Payment is processed through Flow
5. **Webhook Handling**: Payment status updates are received via webhooks
6. **Order Update**: Order status is automatically updated based on payment result

### Key Features

- **Early Order Creation**: Orders are created before payment to ensure webhook matching
- **Client-Side Validation**: Flow component validation before submission
- **Server-Side Validation**: Comprehensive validation on the server
- **Duplicate Prevention**: Prevents duplicate order creation
- **Webhook Queue**: Handles webhooks even if order isn't immediately found
- **3DS Support**: Full 3D Secure authentication flow
- **Saved Cards**: Secure tokenization and card saving

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
