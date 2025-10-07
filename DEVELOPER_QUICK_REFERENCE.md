# Checkout.com Flow Integration - Developer Quick Reference

## 🚀 Quick Start

### Key Files
- **Main Gateway**: `flow-integration/class-wc-gateway-checkout-com-flow.php`
- **Frontend Logic**: `flow-integration/assets/js/payment-session.js`
- **Container Management**: `flow-integration/assets/js/flow-container.js`
- **API Requests**: `includes/api/class-wc-checkoutcom-api-request.php`

### Environment Variables
```php
// Required in wp-config.php or environment
define('CKO_PUBLIC_KEY', 'your_public_key');
define('CKO_SECRET_KEY', 'your_secret_key');
define('CKO_ENVIRONMENT', 'sandbox'); // or 'live'
```

---

## 🔧 Common Tasks

### 1. Enable/Disable Payment Methods
```php
// In Flow settings
$enabled_methods = ['card', 'paypal', 'klarna'];
update_option('woocommerce_wc_checkout_com_flow_settings', [
    'flow_enabled_payment_methods' => $enabled_methods
]);
```

### 2. Customize Flow Appearance
```javascript
// In flow-customization.js
window.appearance = {
    colorPrimary: '#1a1a1a',
    colorBackground: '#ffffff',
    colorBorder: '#e0e0e0',
    borderRadius: ['8px', '8px', '8px', '8px']
};
```

### 3. Add Custom Translations
```javascript
// In flow-customization.js
window.translations = {
    'payment_method.card': 'Credit Card',
    'payment_method.paypal': 'PayPal',
    'error.payment_failed': 'Payment failed. Please try again.'
};
```

### 4. Handle MOTO Orders
```php
// Detect MOTO order
if ($order->is_created_via('admin')) {
    // MOTO-specific logic
    $payment_type = 'MOTO';
}
```

---

## 🐛 Common Issues & Fixes

### Issue: Redirect to Cart Page
**Cause**: JavaScript variable scope issue in `onPaymentCompleted`
**Fix**: Use direct page detection
```javascript
const isOrderPayPage = window.location.pathname.includes('/order-pay/');
if (isOrderPayPage) {
    jQuery('form#order_review').submit();
} else {
    jQuery("form.checkout").submit();
}
```

### Issue: Webhook 422 Error
**Cause**: Payment ID mismatch
**Fix**: Flexible payment ID matching
```php
if (is_null($payment_id)) {
    $order->set_transaction_id($data->data->id);
    $order->save();
    $payment_id = $data->data->id;
}
```

### Issue: Saved Cards Not Showing
**Cause**: Migration not running
**Fix**: Ensure migration runs
```php
if (is_user_logged_in() && !$order_pay_order) {
    $this->migrate_old_saved_cards();
}
```

### Issue: MOTO Orders Show Saved Cards
**Cause**: MOTO detection not working
**Fix**: Hide saved cards for MOTO
```javascript
if (isMotoOrder) {
    jQuery('.saved-cards-accordion-container').hide();
    jQuery('.woocommerce-SavedPaymentMethods-saveNew').hide();
}
```

---

## 📊 Debugging

### Enable Debug Logging
```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// In Flow gateway
WC_Checkoutcom_Utility::logger('Debug message: ' . $data);
```

### Frontend Debugging
```javascript
// Enable console logging
console.log('[FLOW DEBUG]', data);

// Check Flow component state
console.log('[FLOW] Component state:', flowComponent);
```

### Network Monitoring
1. Open Browser DevTools → Network tab
2. Look for Payment Session API calls
3. Check webhook deliveries
4. Monitor redirect responses

---

## 🔄 Payment Flow States

### Normal Checkout Flow
```
1. User fills form → 2. Selects Flow → 3. Enters card → 4. Payment Session API → 5. Process payment → 6. Redirect
```

### MOTO Order Flow
```
1. Admin creates order → 2. Order pay page → 3. Card only → 4. MOTO payment → 5. Redirect
```

### Saved Card Flow
```
1. User selects saved card → 2. Token payment → 3. Process → 4. Redirect
```

### 3DS Flow
```
1. Payment initiated → 2. 3DS challenge → 3. User authenticates → 4. Webhook → 5. Complete
```

---

## 🛠️ Development Commands

### Create Deployment Package
```bash
cd /path/to/checkout-com-unified-payments-api
./create-deployment.sh
# Select: 1 for staging, 2 for production
```

### Git Workflow
```bash
# Add changes
git add .

# Commit with message
git commit -m "Fix: Description of changes"

# Push to branch
git push origin feature/flow-integration-v5.0.0-beta
```

### Testing
```bash
# Test on staging
# 1. Upload staging package
# 2. Test all payment methods
# 3. Test MOTO orders
# 4. Test saved cards
# 5. Check webhooks
```

---

## 📋 Checklist for New Features

### Before Development
- [ ] Understand existing architecture
- [ ] Check API documentation
- [ ] Review similar implementations
- [ ] Plan testing strategy

### During Development
- [ ] Follow coding standards
- [ ] Add proper error handling
- [ ] Include debug logging
- [ ] Test edge cases

### After Development
- [ ] Test all payment methods
- [ ] Test MOTO orders
- [ ] Test saved cards
- [ ] Test webhooks
- [ ] Test error scenarios
- [ ] Update documentation
- [ ] Create deployment package

---

## 🔗 Useful Links

- **Checkout.com API Docs**: https://api-reference.checkout.com/
- **WooCommerce Gateway Docs**: https://woocommerce.com/document/payment-gateway-api/
- **Flow Web Components**: https://docs.checkout.com/payment-flows
- **GitHub Repository**: https://github.com/checkout/checkout-woocommerce-plugin

---

## 📞 Support

### Internal Team
- **Technical Lead**: [Contact Info]
- **QA Team**: [Contact Info]
- **DevOps**: [Contact Info]

### External Support
- **Checkout.com Support**: support@checkout.com
- **WooCommerce Support**: https://woocommerce.com/support/

---

**Last Updated**: January 7, 2025  
**Version**: 5.0.0_beta
