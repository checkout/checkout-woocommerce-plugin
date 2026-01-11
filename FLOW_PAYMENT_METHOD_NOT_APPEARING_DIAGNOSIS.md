# Flow Payment Method Not Appearing - Diagnosis & Solution

## Problem Summary
The Flow payment method is not appearing on the checkout page, even though PHP logs indicate it's enabled and being added to available gateways.

## Key Findings from Logs

### ✅ PHP Side (Working)
- Flow gateway is enabled: `[FLOW ENABLED] Flow gateway enabled: yes`
- Checkout mode is correct: `[FLOW ENABLED] Checkout mode: flow`
- Gateway is being added: `[FLOW DEBUG] FORCING Flow gateway into available gateways list!`
- Gateway availability check passes: `[FLOW AVAILABILITY] Gateway IS available - all checks passed`

### ❌ Critical Issues Found

#### 1. **API Credentials Missing**
```
[FLOW AVAILABILITY] Secret key: NOT SET
[FLOW AVAILABILITY] Public key: NOT SET
[FLOW AVAILABILITY] WARNING: API credentials not set, but gateway is still available
```

**Impact**: While the gateway is marked as available, the frontend JavaScript requires these credentials to initialize the Flow component. Without them, the payment method may not render or function correctly.

#### 2. **Payment Method Not in DOM**
Browser console shows:
```javascript
flowPaymentExists: false  // #payment_method_wc_checkout_com_flow not found
```

**Impact**: Even though PHP says the gateway is available, WooCommerce is not rendering the payment method radio button on the checkout page.

## Root Cause Analysis

The payment method radio button (`#payment_method_wc_checkout_com_flow`) is not appearing in the DOM, which means:

1. **WooCommerce template rendering is excluding it** - Even though it's in `available_gateways`, something is preventing it from being rendered
2. **Possible causes**:
   - Another plugin/theme filter removing it after our filter runs
   - WooCommerce's internal validation excluding it
   - Template override filtering payment methods
   - Missing API credentials causing WooCommerce to hide it

## Solutions

### Immediate Fix: Set API Credentials

**CRITICAL**: The API credentials must be set for Flow to work properly.

1. Go to **WooCommerce → Settings → Payments → Checkout.com**
2. Ensure the following are set:
   - **Secret Key** (`ckocom_sk` or `ckocom_secret_key`)
   - **Public Key** (`ckocom_pk` or `ckocom_public_key`)
   - **Environment** (Sandbox or Live)
3. Save settings
4. Clear any caching (browser cache, WordPress cache, CDN cache)
5. Test the checkout page again

### Diagnostic Steps

1. **Check if payment method appears after setting credentials**:
   - Open browser console
   - Look for `🔍 DIAGNOSTIC: Flow state` messages
   - Check if `flowPaymentExists: true` appears

2. **Verify gateway is in available gateways**:
   - Add this to browser console:
   ```javascript
   jQuery(document).ready(function() {
       setTimeout(function() {
           const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
           const ids = Array.from(paymentMethods).map(el => el.id);
           console.log('Available payment methods:', ids);
           console.log('Flow payment method exists:', ids.includes('payment_method_wc_checkout_com_flow'));
       }, 1000);
   });
   ```

3. **Check for conflicting filters**:
   - Temporarily deactivate other payment gateway plugins
   - Check theme's `functions.php` for payment gateway filters
   - Look for `woocommerce_available_payment_gateways` filters in other plugins

4. **Check WooCommerce logs**:
   - Go to **WooCommerce → Status → Logs**
   - Look for `[FLOW AVAILABILITY CHECK]` messages
   - Verify the gateway is being added to available gateways

### Additional Checks

1. **Verify checkout mode setting**:
   - Settings should be: `WooCommerce → Settings → Payments → Checkout.com → Checkout Mode: Flow`

2. **Check for JavaScript errors**:
   - Open browser console
   - Look for any red error messages
   - Check if `cko_flow_vars` is defined:
   ```javascript
   console.log('cko_flow_vars:', typeof cko_flow_vars !== 'undefined' ? cko_flow_vars : 'NOT DEFINED');
   ```

3. **Verify scripts are loading**:
   - Check Network tab in browser DevTools
   - Look for `payment-session.js` file loading
   - Verify it's not blocked or returning 404

## Expected Behavior After Fix

Once API credentials are set and the issue is resolved, you should see:

1. **In Browser Console**:
   ```
   🔍 DIAGNOSTIC: Flow state BEFORE updated_checkout: {
     flowPaymentExists: true,
     flowPaymentChecked: true/false,
     flowContainerExists: true,
     ...
   }
   ```

2. **In DOM**:
   - Radio button with ID `payment_method_wc_checkout_com_flow`
   - Label for the Flow payment method
   - `#flow-container` div for the Flow web component

3. **In WooCommerce Logs**:
   ```
   [FLOW AVAILABILITY] Secret key: SET (pk_test_...)
   [FLOW AVAILABILITY] Public key: SET (pk_test_...)
   ```

## Webhook Issues (Separate Problem)

The logs also show webhook errors:
- `WEBHOOK DEBUG: Preparing to send response - Status: 401, Message: Unauthorized: Invalid signature`
- `Flow webhook: CRITICAL - No order found for webhook processing`

These are separate issues that should be addressed after fixing the payment method display:
1. Verify webhook secret is correctly configured
2. Check webhook URL is accessible
3. Ensure webhook signature verification is working

## Next Steps

1. **Set API credentials** (most critical)
2. **Clear all caches**
3. **Test checkout page**
4. **Check browser console for diagnostic messages**
5. **If still not appearing**, check for conflicting plugins/themes
6. **If still not appearing**, upload the new plugin version with enhanced diagnostics to get more detailed logs






