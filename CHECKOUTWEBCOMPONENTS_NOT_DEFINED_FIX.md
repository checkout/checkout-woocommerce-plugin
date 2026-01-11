# Fix: "CheckoutWebComponents is not defined" Error

## Problem
Sometimes users see the error: `CheckoutWebComponents is not defined` on the WooCommerce checkout page.

## Root Cause
The Checkout.com Web Components SDK script (`https://checkout-web-components.checkout.com/index.js`) is loaded **asynchronously** for better page performance. However, our code was trying to use `CheckoutWebComponents` immediately without checking if the script had finished loading.

### Why This Happens
1. **Async Script Loading**: The SDK script has the `async` attribute, which means it loads in parallel with other scripts and doesn't block page rendering
2. **Race Condition**: Sometimes our `payment-session.js` script executes before the SDK script finishes loading
3. **Network Delays**: Slow network connections can delay the SDK script loading

## Solution Implemented

### 1. Added Wait Mechanism
Before using `CheckoutWebComponents`, the code now:
- Checks if `CheckoutWebComponents` is available
- If not available, waits up to 10 seconds (100ms intervals) for it to load
- Shows a user-friendly error if it fails to load after waiting

**Location**: `flow-integration/assets/js/payment-session.js` (lines 977-1004)

```javascript
// CRITICAL: Wait for CheckoutWebComponents SDK to be available
if (typeof CheckoutWebComponents === 'undefined') {
    ckoLogger.error('CheckoutWebComponents SDK not loaded yet. Waiting for script to load...');
    
    // Wait for CheckoutWebComponents to be available (max 10 seconds)
    let waitAttempts = 0;
    const maxWaitAttempts = 100; // 100 attempts * 100ms = 10 seconds max wait
    
    while (typeof CheckoutWebComponents === 'undefined' && waitAttempts < maxWaitAttempts) {
        await new Promise(resolve => setTimeout(resolve, 100)); // Wait 100ms
        waitAttempts++;
    }
    
    if (typeof CheckoutWebComponents === 'undefined') {
        ckoLogger.error('CRITICAL: CheckoutWebComponents SDK failed to load after waiting.');
        showError('Payment gateway script failed to load. Please refresh the page and try again.');
        // ... cleanup code ...
        return;
    }
}
```

### 2. Added Script Dependency
Added `checkout-com-flow-script` as a dependency for `payment-session.js` so WordPress ensures proper loading order.

**Location**: `woocommerce-gateway-checkout-com.php` (line 1288)

```php
wp_enqueue_script(
    'checkout-com-flow-payment-session-script', 
    WC_CHECKOUTCOM_PLUGIN_URL . '/flow-integration/assets/js/payment-session.js', 
    array( 'jquery', 'flow-customization-script', 'checkout-com-flow-container-script', 'checkout-com-flow-script', 'wp-i18n' ), 
    WC_CHECKOUTCOM_PLUGIN_VERSION
);
```

### 3. Added Early Warning Check
Added an early check at the start of `loadFlow()` to catch the issue early and log a warning.

**Location**: `flow-integration/assets/js/payment-session.js` (lines 79-83)

```javascript
// Early check: Verify CheckoutWebComponents SDK script is loaded
if (typeof CheckoutWebComponents === 'undefined') {
    ckoLogger.warn('CheckoutWebComponents SDK not yet available. Will wait for it during initialization.');
}
```

## How It Works Now

1. **Early Detection**: When `loadFlow()` starts, it checks if the SDK is available and logs a warning if not
2. **Wait Before Use**: Before calling `CheckoutWebComponents()`, the code waits up to 10 seconds for it to become available
3. **Graceful Failure**: If the SDK doesn't load after 10 seconds, it shows a user-friendly error message and cleans up the UI
4. **Proper Dependencies**: WordPress ensures the SDK script loads before our payment-session script (though async loading means we still need the wait mechanism)

## Expected Behavior

### Normal Case (SDK loads quickly)
- No error messages
- Flow initializes normally
- Console shows: `CheckoutWebComponents SDK loaded after Xms` (if it had to wait)

### Slow Network Case (SDK takes time to load)
- Warning: `CheckoutWebComponents SDK not yet available. Will wait for it during initialization.`
- Info: `CheckoutWebComponents SDK not loaded yet. Waiting for script to load...`
- Success: `CheckoutWebComponents SDK loaded after Xms`
- Flow initializes normally

### Failure Case (SDK fails to load)
- Error: `CRITICAL: CheckoutWebComponents SDK failed to load after waiting. Check network connection and script URL.`
- User sees: `Payment gateway script failed to load. Please refresh the page and try again.`
- Loading overlays are hidden
- Place Order button is enabled

## Testing

To test this fix:

1. **Normal Test**: Load checkout page - should work normally
2. **Slow Network Test**: 
   - Open browser DevTools → Network tab
   - Set throttling to "Slow 3G"
   - Load checkout page - should wait and then initialize
3. **Block SDK Test**:
   - Open browser DevTools → Network tab
   - Block `checkout-web-components.checkout.com`
   - Load checkout page - should show error after 10 seconds

## Additional Notes

- The wait mechanism uses `await` with `setTimeout` to avoid blocking the main thread
- Maximum wait time is 10 seconds (configurable via `maxWaitAttempts`)
- The SDK script remains async-loaded for performance benefits
- Error messages are user-friendly and actionable

## Related Files

- `flow-integration/assets/js/payment-session.js` - Main fix location
- `woocommerce-gateway-checkout-com.php` - Script dependency configuration






