# Flow Field Data Collection Fix

## Problem

When Flow reloads after mandatory field changes (name, address, etc.), the **updated values were not being captured** in the payment session. The old/cached values were being sent to the API instead.

### User Report
> "when flow reloads if any mandatory details are changed, the details are taken from the text box..i am seeing some issues where the change details are not getting reflected."

## Root Cause

The `FlowInitialization.collectCheckoutData()` function was reading data from **two sources in the wrong order**:

```javascript
// BEFORE (WRONG ORDER):
let email = billingAddress["email"] ||              // 1st: cartInfo (CACHED/STALE)
    (document.getElementById("billing_email").value); // 2nd: DOM (FRESH)
```

**Problem**: `cartInfo["billing_address"]` is WooCommerce's cached data attribute that only updates after `updated_checkout` completes. When Flow reloads immediately after a field change (1-second debounce), `cartInfo` still contains the **old values**.

### Data Flow:
```
1. User changes name field (e.g., "John" → "Jane")
2. debouncedCheckFlowReload() fires after 1 second
3. collectCheckoutData() reads billingAddress["given_name"] = "John" (CACHED!)
4. Payment session created with OLD name "John"
5. Flow remounts with wrong data
6. (Later) updated_checkout fires and updates cartInfo
```

## The Fix

**Reversed the priority**: Read from **DOM form fields FIRST**, then fall back to `cartInfo` if DOM field doesn't exist.

### Changes Made

**File**: `flow-integration/assets/js/modules/flow-initialization.js`

#### 1. Name Fields (lines 79-86)
```javascript
// AFTER (CORRECT ORDER):
let email = (document.getElementById("billing_email").value : '') ||  // 1st: DOM (FRESH!)
    billingAddress["email"];                                           // 2nd: cartInfo (fallback)
    
let family_name = (document.getElementById("billing_last_name").value : '') || 
    billingAddress["family_name"];
    
let given_name = (document.getElementById("billing_first_name").value : '') || 
    billingAddress["given_name"];
```

#### 2. Contact & Address Fields (lines 94-108)
```javascript
let phone = (document.getElementById("billing_phone").value : '') || 
    billingAddress["phone"];
    
let address1 = (document.getElementById("billing_address_1").value : '') || 
    billingAddress["street_address"] || '';
    
let address2 = (document.getElementById("billing_address_2").value : '') || 
    billingAddress["street_address2"] || '';
    
let city = (document.getElementById("billing_city").value : '') || 
    billingAddress["city"] || '';
    
let zip = (document.getElementById("billing_postcode").value : '') || 
    billingAddress["postal_code"] || '';
    
let country = (document.getElementById("billing_country").value : '') || 
    billingAddress["country"] || '';
```

#### 3. Shipping Address Fields (lines 117-131)
```javascript
if (shippingElement && shippingElement.checked) {
    shippingAddress1 = (document.getElementById("shipping_address_1").value : '') || 
        shippingAddress["street_address"] || address1;
    // ... (similar for all shipping fields)
}
```

#### 4. Added Debug Logging (lines 111-128)
```javascript
if (typeof window.ckoLogger !== 'undefined' && window.ckoLogger.debugEnabled) {
    window.ckoLogger.debug('[collectCheckoutData] Reading from DOM (fresh) vs cartInfo (cached):', {
        'email_dom': document.getElementById("billing_email").value,
        'email_cartInfo': billingAddress["email"],
        'email_final': email,
        // ... (similar for other fields)
    });
}
```

## Expected Behavior After Fix

### Scenario: User changes name after entering card details

1. User enters card details (Flow loaded)
2. User changes name from "John" to "Jane"
3. After 1 second, `debouncedCheckFlowReload()` fires
4. `collectCheckoutData()` reads **"Jane"** from DOM ✅
5. New payment session created with updated name
6. Flow remounts with correct data
7. User re-enters card details with correct cardholder name

### Debug Logs (Expected)
```
[collectCheckoutData] Reading from DOM (fresh) vs cartInfo (cached):
  given_name_dom: "Jane"           ← Fresh value from form
  given_name_cartInfo: "John"      ← Stale value from cartInfo
  given_name_final: "Jane"         ← Correct value used! ✅
  family_name_dom: "Doe"
  family_name_cartInfo: "Smith"
  family_name_final: "Doe"         ← Correct value used! ✅
```

## Why This Fix Works

### Before (Wrong):
- **Priority**: `cartInfo` → DOM
- **Result**: Flow reloads always used cached (stale) data
- **Issue**: Payment session created with old values

### After (Correct):
- **Priority**: DOM → `cartInfo`
- **Result**: Flow reloads always use fresh form values
- **Benefit**: Payment session created with latest user input

### Why cartInfo fallback is still needed:
- On initial page load, DOM fields might not be populated yet
- WooCommerce might prefill data from session/account
- `cartInfo` provides default values for order-pay page

## Testing Instructions

1. **Initial checkout**: Enter all details → Select Checkout.com → Enter card details
2. **Change name**: Edit "First Name" or "Last Name" field
3. **Wait 1 second**: Flow should reload automatically
4. **Check console logs**: Look for `[collectCheckoutData]` log
5. **Verify**: `given_name_final` and `family_name_final` should match `*_dom` values (not `*_cartInfo`)
6. **Place order**: Should use updated name in payment session

### Expected Logs:
```
🔄 Critical field "billing_first_name" changed - reloading Flow
[collectCheckoutData] Reading from DOM (fresh) vs cartInfo (cached):
  given_name_dom: "UpdatedName"     ✅
  given_name_cartInfo: "OldName"    ❌
  given_name_final: "UpdatedName"   ✅ Correct!
[PAYMENT SESSION] Payment Session Response: {id: 'ps_xxx', ...}
```

## Files Modified

1. `flow-integration/assets/js/modules/flow-initialization.js`
   - Lines 56-131: Updated `collectCheckoutData()` function
   - Reversed data reading priority: DOM first, cartInfo fallback
   - Added debug logging for verification

## Impact

- **Before**: Changed field values not reflected in payment session
- **After**: All field changes immediately captured on Flow reload
- **Performance**: No impact (just changed read order)
- **Compatibility**: Fully backward compatible (fallback to cartInfo still works)

## Related Systems

This fix applies to all fields read in `collectCheckoutData()`:
- ✅ Billing name (first/last)
- ✅ Billing email
- ✅ Billing phone
- ✅ Billing address (line1, line2, city, postcode, country)
- ✅ Shipping address (all fields when "ship to different address" is checked)

Amount, currency, order lines, etc. still come from `cartInfo` (these don't change based on form fields).
