# Express Checkout Technical Overview

## Architecture Summary

The Checkout.com WooCommerce plugin implements Express Checkout (Google Pay & Apple Pay) buttons that can appear on:
- **Product pages** (single product)
- **Cart pages** (classic & WooCommerce Blocks)
- **Shop/Archive pages**

The implementation follows a **session-based flow** where the user can complete checkout without filling in billing/shipping forms manually.

---

## High-Level Payment Flow

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│  1. User clicks │ →  │ 2. Add to Cart  │ →  │ 3. Create       │ →  │ 4. Google/Apple │
│  Express Button │    │    (AJAX)       │    │ Payment Context │    │    Pay Sheet    │
└─────────────────┘    └─────────────────┘    └─────────────────┘    └─────────────────┘
                                                                              │
                                                                              ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│  8. Redirect to │ ←  │ 7. Update Order │ ←  │ 6. Token →      │ ←  │ 5. User         │
│  Thank You Page │    │    Status       │    │    Payment API  │    │    Authorizes   │
└─────────────────┘    └─────────────────┘    └─────────────────┘    └─────────────────┘
```

---

## Core Components

### 1. PHP Backend Classes

| Class | File | Purpose |
|-------|------|---------|
| `CKO_Google_Pay_Express` | `includes/express/google-pay/class-google-pay-express.php` | Renders Google Pay buttons, manages WC session, enqueues scripts |
| `CKO_Apple_Pay_Express` | `includes/express/apple-pay/class-apple-pay-express.php` | Renders Apple Pay buttons, manages WC session, enqueues scripts |
| `WC_Gateway_Checkout_Com_Google_Pay` | `includes/class-wc-gateway-checkout-com-google-pay.php` | Gateway class, handles API endpoints, processes payments |
| `WC_Gateway_Checkout_Com_Apple_Pay` | `includes/class-wc-gateway-checkout-com-apple-pay.php` | Gateway class, handles API endpoints, processes payments |

### 2. JavaScript Files

| File | Purpose |
|------|---------|
| `assets/js/cko-google-pay-express-integration.js` | Handles Google Pay button interactions, AJAX calls, payment sheet |
| `assets/js/cko-apple-pay-express-integration.js` | Handles Apple Pay button interactions, AJAX calls, payment sheet |

---

## API Endpoints

Express Checkout uses WooCommerce's `wc-api` hook system. Endpoints are registered via:

```php
add_action( 'woocommerce_api_cko_google_pay_woocommerce', [ $this, 'handle_wc_api' ] );
add_action( 'woocommerce_api_cko_apple_pay_woocommerce', [ $this, 'handle_wc_api' ] );
```

### Google Pay Endpoints

Base URL: `/?wc-api=CKO_Google_Pay_Woocommerce`

| Action Parameter | Method | Purpose |
|------------------|--------|---------|
| `cko_google_pay_action=express_add_to_cart` | POST | Adds product to cart (clears existing cart first) |
| `cko_google_pay_action=express_create_payment_context` | POST | Creates Checkout.com Payment Context for Google Pay |
| `cko_google_pay_action=express_get_cart_total` | GET | Returns current cart total |
| `cko_google_pay_action=express_google_pay_order_session` | POST | Processes payment after user authorizes |

### Apple Pay Endpoints

Base URL: `/?wc-api=CKO_Apple_Pay_Woocommerce`

| Action Parameter | Method | Purpose |
|------------------|--------|---------|
| `cko_apple_pay_action=express_add_to_cart` | POST | Adds product to cart |
| `cko_apple_pay_action=express_create_payment_context` | POST | Creates Checkout.com Payment Context for Apple Pay |
| `cko_apple_pay_action=express_apple_pay_order_session` | POST | Processes payment after user authorizes |

Additional Apple Pay-specific endpoints:
- `/?wc-api=wc_checkoutcom_session` - Creates Apple Pay merchant session
- `/?wc-api=wc_checkoutcom_generate_token` - Generates Apple Pay token

---

## Detailed Flow: Google Pay Express

### Step 1: Button Click (JavaScript)

```javascript
// cko-google-pay-express-integration.js
const cko_express_add_to_cart = async function () {
    var data = {
        product_id: product_id,
        qty: $( '.quantity .qty' ).val(),
        attributes: $( '.variations_form' ).length ? getAttributes().data : [],
        nonce: cko_google_pay_vars.google_pay_express_add_to_cart_nonce
    };

    return await $.ajax( {
        url: cko_google_pay_vars.add_to_cart_url,  // /?wc-api=CKO_Google_Pay_Woocommerce&cko_google_pay_action=express_add_to_cart
        type: 'POST',
        data: data
    });
}
```

### Step 2: Add to Cart (PHP)

```php
// class-wc-gateway-checkout-com-google-pay.php
public function cko_express_add_to_cart() {
    // Verify nonce
    if ( ! wp_verify_nonce( $_POST['nonce'], 'checkoutcom_google_pay_express_add_to_cart' ) ) {
        wp_send_json( [ 'result' => 'failed' ] );
    }

    // Clear cart and add product
    WC()->cart->empty_cart();
    WC()->cart->add_to_cart( $product_id, $qty, $variation_id, $attributes );
    WC()->cart->calculate_totals();

    wp_send_json( [
        'result' => 'success',
        'total'  => WC()->cart->total
    ]);
}
```

### Step 3: Create Payment Context

```javascript
// JavaScript - creates payment context
fetch( cko_google_pay_vars.create_payment_context_url, {
    method: 'POST',
    body: jQuery.param({ express_checkout: true })
}).then(res => res.json())
  .then(data => {
    // data.payment_context_id contains the Checkout.com context ID
});
```

```php
// PHP - creates payment context via Checkout.com API
public function cko_express_create_payment_context() {
    // Calls Checkout.com API to create payment context
    $this->cko_create_payment_context_request( true );
}
```

### Step 4: Display Google Pay Sheet

The JavaScript uses Google Pay JS SDK to display the payment sheet:

```javascript
const paymentDataRequest = {
    apiVersion: 2,
    apiVersionMinor: 0,
    merchantInfo: {
        merchantId: cko_google_pay_vars.merchant_id,
        merchantName: cko_google_pay_vars.merchant_name
    },
    transactionInfo: {
        totalPriceStatus: 'FINAL',
        totalPrice: cartTotal,
        currencyCode: cko_google_pay_vars.currency
    },
    // ... payment method configuration
};

const paymentData = await paymentsClient.loadPaymentData(paymentDataRequest);
```

### Step 5: Process Payment (after user authorizes)

```javascript
// Send payment token to server
$.ajax({
    url: '/?wc-api=CKO_Google_Pay_Woocommerce&cko_google_pay_action=express_google_pay_order_session',
    method: 'POST',
    data: {
        'cko-google-signature': paymentData.paymentMethodData.tokenizationData.token.signature,
        'cko-google-protocolVersion': paymentData.paymentMethodData.tokenizationData.token.protocolVersion,
        'cko-google-signedMessage': paymentData.paymentMethodData.tokenizationData.token.signedMessage,
        'payment_data': JSON.stringify(paymentData)
    }
});
```

```php
// PHP - processes the payment
public function cko_express_google_pay_order_session() {
    // 1. Create WooCommerce order from cart
    $order = $this->create_express_order_from_cart( $email, $shipping_address );
    
    // 2. Generate Google Pay token via Checkout.com
    $google_token = WC_Checkoutcom_Api_Request::generate_google_token();
    
    // 3. Create payment with Checkout.com
    $result = ( new WC_Checkoutcom_Api_Request() )->create_payment( $order, $google_token );
    
    // 4. Handle 3DS if required, or complete payment
    if ( isset( $result['3d'] ) ) {
        // Redirect to 3DS
    } else {
        // Payment complete
        wp_send_json_success([ 'redirect_url' => $order->get_checkout_order_received_url() ]);
    }
}
```

---

## Key WooCommerce Session Variables

The plugin uses WooCommerce sessions to track Express Checkout state:

| Session Key | Purpose |
|-------------|---------|
| `cko_google_pay_order_id` | Temporary order ID during Express flow |
| `cko_gc_id` | Google Pay context ID |
| `cko_apple_pay_order_id` | Temporary order ID during Apple Pay flow |
| `cko_ap_id` | Apple Pay context ID |

---

## Settings & Configuration

### Google Pay Settings

WordPress Option: `woocommerce_wc_checkout_com_google_pay_settings`

```php
[
    'enabled' => 'yes',
    'google_pay_express' => 'yes',  // Master toggle for Express buttons
    'google_merchant_id' => 'BCR2DN...',
    // ... other settings
]
```

### Apple Pay Settings

WordPress Option: `woocommerce_wc_checkout_com_apple_pay_settings`

```php
[
    'enabled' => 'yes',
    'apple_pay_express' => 'yes',  // Master toggle for Express buttons
    'apple_merchant_id' => 'merchant.com.yoursite',
    // ... certificate paths, etc.
]
```

---

## Checkout Mode: Classic vs Flow

The plugin supports two checkout modes:

1. **Classic Mode**: Traditional WooCommerce checkout with separate Google Pay/Apple Pay gateways
2. **Flow Mode**: Uses Checkout.com Flow (embedded components)

For Express Checkout in **Flow Mode**, the gateway is dynamically enabled:

```php
// class-google-pay-express.php
private function maybe_enable_google_pay_in_flow_mode() {
    if ( 'flow' !== $checkout_mode ) {
        return;
    }

    // Dynamically enable Google Pay gateway when Express session exists
    add_filter( 'woocommerce_payment_gateways', [ $this, 'add_google_pay_gateway' ] );
    add_filter( 'option_woocommerce_wc_checkout_com_google_pay_settings', [ $this, 'force_enable_google_pay_settings' ] );
    add_filter( 'woocommerce_available_payment_gateways', [ $this, 'restrict_gateways_to_google_pay' ] );
}
```

---

## Domain Verification

### Apple Pay

Apple Pay requires domain verification. The plugin handles this via:

1. CSR generation: `wp_ajax_cko_generate_apple_pay_csr`
2. Certificate upload: `wp_ajax_cko_upload_apple_pay_certificate`
3. Domain association file: Must be served at `/.well-known/apple-developer-merchantid-domain-association`

### Google Pay

Google Pay requires merchant verification through Google Pay Business Console.

---

## Headless/API Integration Notes

For custom frontend (e.g., Next.js) integration, developers would need to:

1. **Replicate the AJAX endpoints** as REST API endpoints
2. **Handle cart management** via WooCommerce REST API or custom endpoints
3. **Implement the payment flow**:
   - Add products to cart
   - Create Payment Context via Checkout.com API
   - Display native Google Pay / Apple Pay sheet
   - Submit payment token to backend
   - Create WooCommerce order and process payment

The existing AJAX endpoints are tightly coupled to WooCommerce sessions and expect traditional WordPress/WooCommerce context.

---

## File Structure

```
includes/
├── express/
│   ├── google-pay/
│   │   └── class-google-pay-express.php    # Express button rendering & session management
│   └── apple-pay/
│       └── class-apple-pay-express.php     # Express button rendering & session management
├── class-wc-gateway-checkout-com-google-pay.php  # Gateway + API handlers
├── class-wc-gateway-checkout-com-apple-pay.php   # Gateway + API handlers
└── class-wc-checkoutcom-api-request.php          # Checkout.com API wrapper

assets/js/
├── cko-google-pay-express-integration.js   # Google Pay client-side logic
└── cko-apple-pay-express-integration.js    # Apple Pay client-side logic
```

---

## Summary for External Developers

To integrate Express Checkout on a custom frontend:

1. **You need**: Checkout.com API credentials (Public Key, Secret Key)
2. **Client-side**: Use Google Pay JS SDK / Apple Pay JS API directly
3. **Server-side**: Create endpoints that:
   - Manage WooCommerce cart
   - Create orders via WooCommerce
   - Call Checkout.com APIs for tokenization and payment
4. **Domain verification**: Required for Apple Pay
5. **Webhooks**: Configure Checkout.com webhooks to update order status

The plugin's Express Checkout is designed for traditional WordPress/WooCommerce architecture and would require significant adaptation for headless implementations.
