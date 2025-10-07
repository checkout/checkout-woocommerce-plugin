# Checkout.com Flow Integration - Technical Developer Guide

## Table of Contents
1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Payment Flows](#payment-flows)
4. [Saved Cards System](#saved-cards-system)
5. [Webhook Processing](#webhook-processing)
6. [MOTO Orders](#moto-orders)
7. [Developer Guidelines](#developer-guidelines)
8. [Troubleshooting](#troubleshooting)

---

## Overview

The Checkout.com Flow Integration provides a unified payment experience using Checkout.com's Payment Session API and Web Components. This guide explains the technical implementation of different payment methods and flows.

### Key Components
- **Flow Gateway**: `class-wc-gateway-checkout-com-flow.php`
- **Payment Session JS**: `payment-session.js`
- **Flow Container**: `flow-container.js`
- **Flow Customization**: `flow-customization.js`

---

## Architecture

### High-Level Architecture
```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   WooCommerce   │    │  Checkout.com    │    │   Flow Gateway  │
│   Checkout      │◄──►│  Payment Session │◄──►│   (PHP Backend) │
│   (Frontend)    │    │      API         │    │                 │
└─────────────────┘    └──────────────────┘    └─────────────────┘
         │                       │                       │
         │                       │                       │
         ▼                       ▼                       ▼
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│  Flow Component │    │   Webhooks       │    │  Order Processing│
│  (Web Components)│    │   (Status Updates)│    │  & Redirects    │
└─────────────────┘    └──────────────────┘    └─────────────────┘
```

### File Structure
```
checkout-com-unified-payments-api/
├── flow-integration/
│   ├── class-wc-gateway-checkout-com-flow.php    # Main Flow Gateway
│   └── assets/
│       ├── js/
│       │   ├── payment-session.js                # Core Flow Logic
│       │   ├── flow-container.js                 # Container Management
│       │   └── flow-customization.js             # UI Customization
│       └── css/
│           └── flow.css                          # Flow Styling
├── includes/
│   ├── api/
│   │   └── class-wc-checkoutcom-api-request.php  # API Requests
│   └── class-wc-gateway-checkout-com-cards.php   # Classic Gateway
└── assets/
    └── js/
        ├── cko-paypal-integration.js             # PayPal Integration
        ├── cko-google-pay-integration.js         # Google Pay
        └── cko-paypal-express-integration.js     # PayPal Express
```

---

## Payment Flows

### 1. Normal Card Payments (Checkout Page)

#### Flow Sequence
```
1. User fills checkout form
2. Selects Flow payment method
3. Flow component initializes
4. User enters card details
5. Payment Session API call
6. Payment processing
7. Redirect to thank you page
```

#### Technical Implementation

**Frontend (payment-session.js)**:
```javascript
// 1. Initialize Flow component
const flowComponent = await window.Flow.init({
    publicKey: cko_flow_vars.public_key,
    environment: cko_flow_vars.environment,
    locale: cko_flow_vars.locale,
    // ... other config
});

// 2. Create Payment Session
const paymentSessionRequest = {
    source: {
        type: 'id',
        id: paymentSessionId
    },
    amount: amount,
    currency: currency,
    reference: orderId,
    // ... other fields
};

// 3. Handle payment completion
flowComponent.on('paymentCompleted', (paymentResponse) => {
    // Set payment ID and submit form
    jQuery("#cko-flow-payment-id").val(paymentResponse.id);
    jQuery("form.checkout").submit();
});
```

**Backend (class-wc-gateway-checkout-com-flow.php)**:
```php
public function process_payment($order_id) {
    // 1. Get Flow payment ID from form
    $flow_pay_id = $_POST['cko-flow-payment-id'];
    
    // 2. Create payment request
    $payment_request = WC_Checkoutcom_Api_Request::create_payment($order, $flow_pay_id);
    
    // 3. Process payment
    $response = WC_Checkoutcom_Api_Request::request($payment_request);
    
    // 4. Handle response and redirect
    if ($response['approved']) {
        $order->payment_complete($flow_pay_id);
        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url($order)
        );
    }
}
```

### 2. Google Pay Integration

#### Flow Sequence
```
1. Google Pay button appears
2. User clicks Google Pay
3. Google Pay sheet opens
4. User selects payment method
5. Google Pay token generated
6. Token sent to Checkout.com
7. Payment processed
8. Redirect to thank you page
```

#### Technical Implementation

**Frontend (cko-google-pay-integration.js)**:
```javascript
// 1. Initialize Google Pay
const paymentsClient = new google.payments.api.PaymentsClient({
    environment: cko_google_pay_vars.environment
});

// 2. Create payment request
const paymentRequest = {
    apiVersion: 2,
    apiVersionMinor: 0,
    allowedPaymentMethods: [{
        type: 'CARD',
        parameters: {
            allowedAuthMethods: ['PAN_ONLY', 'CRYPTOGRAM_3DS'],
            allowedCardNetworks: ['MASTERCARD', 'VISA']
        },
        tokenizationSpecification: {
            type: 'PAYMENT_GATEWAY',
            parameters: {
                gateway: 'checkoutltd',
                gatewayMerchantId: cko_google_pay_vars.merchant_id
            }
        }
    }],
    transactionInfo: {
        totalPriceStatus: 'FINAL',
        totalPrice: totalAmount,
        currencyCode: currency
    }
};

// 3. Handle payment response
paymentsClient.loadPaymentData(paymentRequest)
    .then(paymentData => {
        // Process Google Pay token
        processGooglePayPayment(paymentData);
    });
```

### 3. Apple Pay Integration

#### Flow Sequence
```
1. Apple Pay button appears (Safari/iOS)
2. User clicks Apple Pay
3. Apple Pay sheet opens
4. User authenticates (Touch ID/Face ID)
5. Apple Pay token generated
6. Token sent to Checkout.com
7. Payment processed
8. Redirect to thank you page
```

#### Technical Implementation

**Frontend (Apple Pay)**:
```javascript
// 1. Check Apple Pay availability
if (window.ApplePaySession && ApplePaySession.canMakePayments()) {
    // Show Apple Pay button
    showApplePayButton();
}

// 2. Create payment request
const paymentRequest = {
    countryCode: 'US',
    currencyCode: 'USD',
    supportedNetworks: ['visa', 'masterCard', 'amex'],
    merchantCapabilities: ['supports3DS'],
    total: {
        label: 'Total',
        amount: totalAmount
    }
};

// 3. Handle payment response
const session = new ApplePaySession(3, paymentRequest);
session.onpaymentauthorized = (event) => {
    // Process Apple Pay token
    processApplePayPayment(event.payment);
};
```

### 4. PayPal Integration

#### Flow Sequence
```
1. PayPal button appears
2. User clicks PayPal
3. PayPal popup/redirect opens
4. User logs in and confirms
5. PayPal returns with token
6. Token sent to Checkout.com
7. Payment processed
8. Redirect to thank you page
```

#### Technical Implementation

**Frontend (cko-paypal-integration.js)**:
```javascript
// 1. Initialize PayPal
paypal.Buttons({
    createOrder: function(data, actions) {
        return actions.order.create({
            purchase_units: [{
                amount: {
                    value: totalAmount,
                    currency_code: currency
                }
            }]
        });
    },
    onApprove: function(data, actions) {
        return actions.order.capture().then(function(details) {
            // Process PayPal payment
            processPayPalPayment(details);
        });
    }
}).render('#paypal-button-container');
```

### 5. Alternative Payment Methods (APMs)

#### Supported APMs
- Klarna
- Sofort
- iDEAL
- Bancontact
- EPS
- Giropay
- And many more...

#### Flow Sequence
```
1. APM button appears in Flow component
2. User selects APM
3. Redirect to APM provider
4. User completes payment on provider site
5. Provider redirects back
6. Payment status updated
7. Redirect to thank you page
```

---

## Saved Cards System

### Architecture
```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   User Profile  │    │  WooCommerce     │    │  Checkout.com   │
│   (Frontend)    │◄──►│  Token Storage   │◄──►│  Token API      │
└─────────────────┘    └──────────────────┘    └─────────────────┘
```

### Saved Card Flow

#### 1. Saving a Card
```javascript
// Frontend: User checks "Save to account"
const saveCardCheckbox = jQuery('#wc-wc_checkout_com_flow-new-payment-method');
if (saveCardCheckbox.is(':checked')) {
    // Card will be saved after successful payment
}
```

```php
// Backend: Save card after successful payment
public function flow_save_cards($order, $flow_pay_id) {
    if ($this->get_option('flow_saved_payment') === 'yes') {
        // Create token from payment
        $token = $this->create_token_from_payment($flow_pay_id);
        
        // Save token to user account
        $this->save_token_to_user($token, $order->get_user_id());
    }
}
```

#### 2. Using Saved Cards
```javascript
// Frontend: Display saved cards
const savedCards = cko_flow_vars.saved_cards;
savedCards.forEach(card => {
    // Create radio button for saved card
    const radioButton = createSavedCardRadio(card);
    savedCardsContainer.append(radioButton);
});
```

```php
// Backend: Process saved card payment
public function process_payment($order_id) {
    $saved_card_id = $_POST['wc-wc_checkout_com_flow-payment-token'];
    
    if ($saved_card_id && $saved_card_id !== 'new') {
        // Use saved card token
        $payment_request = $this->create_payment_with_token($saved_card_id);
    } else {
        // Use new card
        $payment_request = $this->create_payment_with_flow();
    }
}
```

### Migration System
```php
// Migrate old saved cards to Flow gateway
public function migrate_old_saved_cards() {
    $user_id = get_current_user_id();
    $migration_flag = get_user_meta($user_id, 'cko_flow_cards_migrated', true);
    
    if (!$migration_flag) {
        // Get tokens from classic gateway
        $old_tokens = $this->get_old_saved_tokens($user_id);
        
        // Copy tokens to Flow gateway
        foreach ($old_tokens as $token) {
            $this->copy_token_to_flow_gateway($token);
        }
        
        // Mark migration as complete
        update_user_meta($user_id, 'cko_flow_cards_migrated', true);
    }
}
```

---

## Webhook Processing

### Webhook Flow
```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│  Checkout.com   │    │   WooCommerce    │    │   Order Update  │
│   Webhook       │───►│  Webhook Handler │───►│   & Status      │
└─────────────────┘    └──────────────────┘    └─────────────────┘
```

### Webhook Handler Implementation
```php
public function webhook_handler() {
    // 1. Get webhook data
    $data = json_decode(file_get_contents('php://input'));
    
    // 2. Verify signature
    $signature = $this->verify_webhook_signature($data);
    
    // 3. Find order by payment ID
    $order = $this->find_order_by_payment_id($data->data->id);
    
    // 4. Process webhook event
    switch ($data->type) {
        case 'payment_approved':
            $order->payment_complete($data->data->id);
            break;
        case 'payment_declined':
            $order->update_status('failed');
            break;
        case 'payment_captured':
            $order->update_status('completed');
            break;
    }
}
```

### Webhook Events
- `payment_approved`: Payment successful
- `payment_declined`: Payment failed
- `payment_captured`: Payment captured
- `payment_voided`: Payment voided
- `payment_refunded`: Payment refunded

---

## MOTO Orders

### MOTO Order Flow
```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│  Admin Panel    │    │   Order Pay      │    │  Payment Flow   │
│  (Create Order) │───►│   Page           │───►│  (Card Only)    │
└─────────────────┘    └──────────────────┘    └─────────────────┘
```

### MOTO Implementation
```php
// Detect MOTO order
if ($order->is_created_via('admin')) {
    // This is a MOTO order
    $is_moto_order = true;
}
```

```javascript
// Frontend: MOTO order handling
const isMotoOrder = window.location.pathname.includes('/order-pay/');

if (isMotoOrder) {
    // Hide saved cards and save checkbox
    jQuery('.saved-cards-accordion-container').hide();
    jQuery('.woocommerce-SavedPaymentMethods-saveNew').hide();
    
    // Set payment type to MOTO
    paymentSessionRequest.payment_type = 'MOTO';
    paymentSessionRequest.enabled_payment_methods = ['card'];
}
```

---

## Developer Guidelines

### 1. Adding New Payment Methods

#### Frontend Integration
```javascript
// Add to Flow component configuration
const flowConfig = {
    // ... existing config
    paymentMethods: {
        // Add new payment method
        'new_payment_method': {
            enabled: true,
            configuration: {
                // Method-specific config
            }
        }
    }
};
```

#### Backend Integration
```php
// Add to payment processing
public function process_payment($order_id) {
    $payment_method = $_POST['payment_method'];
    
    switch ($payment_method) {
        case 'new_payment_method':
            return $this->process_new_payment_method($order_id);
        // ... other methods
    }
}
```

### 2. Customizing Flow Component

#### Appearance Customization
```javascript
// In flow-customization.js
window.appearance = {
    colorPrimary: '#1a1a1a',
    colorBackground: '#ffffff',
    colorBorder: '#e0e0e0',
    borderRadius: ['8px', '8px', '8px', '8px'],
    fontFamily: 'Arial, sans-serif'
};
```

#### Translation Customization
```javascript
// Add translations
window.translations = {
    'payment_method.card': 'Credit Card',
    'payment_method.paypal': 'PayPal',
    // ... more translations
};
```

### 3. Error Handling

#### Frontend Error Handling
```javascript
// Handle Flow component errors
flowComponent.on('error', (error) => {
    console.error('Flow component error:', error);
    // Show user-friendly error message
    showErrorMessage('Payment failed. Please try again.');
});

// Handle API errors
fetch(paymentSessionUrl, options)
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .catch(error => {
        console.error('Payment session error:', error);
        handlePaymentError(error);
    });
```

#### Backend Error Handling
```php
// Handle payment processing errors
try {
    $response = WC_Checkoutcom_Api_Request::request($payment_request);
    
    if (!$response['approved']) {
        throw new Exception('Payment not approved: ' . $response['response_summary']);
    }
    
} catch (Exception $e) {
    WC_Checkoutcom_Utility::logger('Payment error: ' . $e->getMessage());
    
    return array(
        'result' => 'failure',
        'messages' => 'Payment failed. Please try again.'
    );
}
```

### 4. Testing Guidelines

#### Unit Testing
```php
// Test payment processing
public function test_process_payment() {
    $order = wc_create_order();
    $gateway = new WC_Gateway_Checkout_Com_Flow();
    
    // Mock successful payment
    $result = $gateway->process_payment($order->get_id());
    
    $this->assertEquals('success', $result['result']);
    $this->assertArrayHasKey('redirect', $result);
}
```

#### Integration Testing
```javascript
// Test Flow component initialization
describe('Flow Component', () => {
    it('should initialize with correct configuration', () => {
        const flowComponent = await window.Flow.init(mockConfig);
        expect(flowComponent).toBeDefined();
    });
    
    it('should handle payment completion', (done) => {
        flowComponent.on('paymentCompleted', (response) => {
            expect(response.id).toBeDefined();
            done();
        });
    });
});
```

---

## Troubleshooting

### Common Issues

#### 1. Redirect Issues
**Problem**: Payment successful but redirects to cart page
**Solution**: Check JavaScript variable scope in `onPaymentCompleted` callback

```javascript
// Fix: Use direct page detection instead of variable scope
const isOrderPayPage = window.location.pathname.includes('/order-pay/');
const orderPayForm = jQuery('form#order_review');
const checkoutForm = jQuery("form.checkout");

if (isOrderPayPage && orderPayForm.length > 0) {
    orderPayForm.submit();
} else {
    checkoutForm.submit();
}
```

#### 2. Webhook Issues
**Problem**: Webhook returns 422 error
**Solution**: Improve payment ID matching logic

```php
// Fix: More flexible payment ID matching
if (is_null($payment_id)) {
    // Set payment ID from webhook
    $order->set_transaction_id($data->data->id);
    $order->save();
    $payment_id = $data->data->id;
}
```

#### 3. Saved Cards Not Showing
**Problem**: Saved cards not displayed
**Solution**: Check migration and display logic

```php
// Fix: Ensure migration runs
if (is_user_logged_in() && !$order_pay_order) {
    $this->migrate_old_saved_cards();
}
```

#### 4. MOTO Orders Not Working
**Problem**: MOTO orders fail or show wrong UI
**Solution**: Check MOTO detection and payment method restrictions

```javascript
// Fix: Proper MOTO detection and handling
const isMotoOrder = window.location.pathname.includes('/order-pay/');
if (isMotoOrder) {
    paymentSessionRequest.payment_type = 'MOTO';
    paymentSessionRequest.enabled_payment_methods = ['card'];
}
```

### Debugging Tools

#### 1. Console Logging
```javascript
// Enable detailed logging
console.log('[FLOW DEBUG] Payment session request:', paymentSessionRequest);
console.log('[FLOW DEBUG] Flow component state:', flowComponent);
```

#### 2. Server Logging
```php
// Enable server-side logging
WC_Checkoutcom_Utility::logger('Payment processing started for order: ' . $order_id);
WC_Checkoutcom_Utility::logger('Payment response: ' . print_r($response, true));
```

#### 3. Network Monitoring
- Check browser Network tab for API calls
- Verify Payment Session API responses
- Monitor webhook deliveries

### Performance Optimization

#### 1. Lazy Loading
```javascript
// Load Flow component only when needed
if (jQuery('#payment_method_wc_checkout_com_flow').is(':checked')) {
    loadFlowComponent();
}
```

#### 2. Caching
```php
// Cache payment session data
$cache_key = 'cko_payment_session_' . $order_id;
$cached_data = wp_cache_get($cache_key);
if ($cached_data === false) {
    $cached_data = $this->create_payment_session($order_id);
    wp_cache_set($cache_key, $cached_data, '', 300); // 5 minutes
}
```

---

## Conclusion

This technical guide provides a comprehensive overview of the Checkout.com Flow Integration system. The architecture supports multiple payment methods, saved cards, webhooks, and MOTO orders while maintaining a clean, extensible codebase.

For additional support or questions, refer to:
- Checkout.com API Documentation
- WooCommerce Payment Gateway Documentation
- Internal development team

---

**Last Updated**: January 7, 2025  
**Version**: 5.0.0_beta  
**Author**: Development Team
