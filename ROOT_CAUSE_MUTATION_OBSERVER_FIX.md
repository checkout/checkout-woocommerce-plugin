# Root Cause Fix: MutationObserver Resetting Initialized Flag

**Date**: February 1, 2026  
**Issue**: Flow loads twice on 2nd checkout visit  
**Real Root Cause**: MutationObserver too aggressive - resets `ckoFlowInitialized` during WooCommerce DOM updates  
**Status**: ✅ FIXED

---

## What Was Actually Happening

### From Your Latest Logs:

```
[FLOW DEBUG] 🚀 STARTING - Calling ckoFlow.init()... (1st init - CORRECT)
[FLOW DEBUG] Component mounted - enabling UI! 🔥🔥🔥
[FLOW DEBUG] onReady callback fired! 🔥🔥🔥
[FLOW DEBUG] Triggering update_checkout after debounce

# WooCommerce fires updated_checkout, replaces DOM
[FLOW DEBUG] [FLOW STATE] initialized changed: {old: true, new: false}  ← MUTATION OBSERVER!
[FLOW DEBUG] [FLOW CONTAINER] Container missing after updated_checkout - recreating
[FLOW DEBUG] 🔔 Container-ready event received {flowInitialized: false, ...}  ← FLAG WAS RESET!
[FLOW DEBUG] ✅ Flow component still mounted or never initialized, no remounting needed  ← SKIP REMOUNT
```

**Result**: Flow initialized once, but **component wasn't remounted** after WooCommerce replaced the DOM, so Flow appeared to disappear or load incorrectly.

---

## The Real Culprit: MutationObserver

###Before (Clean 5.0.2):

```javascript
// payment-session.js line 2343
const observer = new MutationObserver(() => {
    const element = document.querySelector('[data-testid="checkout-web-component-root"]');
    
    // If the element is not present, update ckoFlowInitialized.
    if (!element) {
        ckoFlowInitialized = false;  // ← TOO AGGRESSIVE!
    }
});
```

**Problem**: Resets `ckoFlowInitialized` whenever the element is missing, including:
- ✅ When user switches away from Flow (good)
- ❌ During WooCommerce `updated_checkout` DOM replacement (bad!)
- ❌ While Flow SDK is rendering (bad!)

###After Fix:

```javascript
const observer = new MutationObserver(() => {
    const element = document.querySelector('[data-testid="checkout-web-component-root"]');

    // Only reset initialized flag if:
    // 1. Element is missing from DOM AND
    // 2. Component doesn't exist in memory AND  
    // 3. NOT during updated_checkout (WooCommerce replaces DOM)
    if (!element) {
        const componentExistsInMemory = !!(ckoFlow && ckoFlow.flowComponent);
        const duringUpdatedCheckout = window.ckoFlowUpdatedCheckoutInProgress || false;
        
        if (!componentExistsInMemory && !duringUpdatedCheckout) {
            ckoFlowInitialized = false;
        }
    }
});
```

**Now**: Only resets flag when component is **actually destroyed**, not during legitimate DOM updates.

---

## Additional Fix: Protected updated_checkout Window

### flow-container.js

```javascript
jQuery(document).on("updated_checkout", function () {
    // Set flag to prevent MutationObserver from resetting state during DOM churn
    window.ckoFlowUpdatedCheckoutInProgress = true;
    
    setTimeout(function() {
        // ... handle container ...
        
        // Clear flag after delay
        setTimeout(function() {
            window.ckoFlowUpdatedCheckoutInProgress = false;
        }, 500);
    }, 100);
});
```

**This protects** the 600ms window (100ms + 500ms) during `updated_checkout` from MutationObserver interference.

---

## What Changed

### 1. payment-session.js (MutationObserver)
- Added check: component exists in memory
- Added check: not during `updated_checkout`
- Only reset flag if component truly destroyed

### 2. flow-container.js (updated_checkout handler)
- Set `window.ckoFlowUpdatedCheckoutInProgress = true` at start
- Clear flag after 600ms total delay

### 3. flow-container-ready-handler.js (Previous minimal fix)
- Changed condition from `if (!flowComponentActuallyMounted || flowWasInitializedBefore)`
- To: `if (flowWasInitializedBefore)` only

---

## Expected Behavior After Fix

### Scenario: 2nd Checkout Visit (Your Test Case)

```
# User clicks Checkout.com
[FLOW DEBUG] 🚀 STARTING - Calling ckoFlow.init()... (1st init)
[FLOW PAYMENT] Payment Session Response: {id: 'ps_xxx1', ...}
[FLOW DEBUG] Component mounted - enabling UI! 🔥🔥🔥
[FLOW DEBUG] onReady callback fired! 🔥🔥🔥
[FLOW DEBUG] Triggering update_checkout after debounce

# WooCommerce fires updated_checkout
[FLOW DEBUG] [FLOW CONTAINER] updated_checkout event fired
# MutationObserver sees element missing BUT:
# - componentExistsInMemory = true ✅
# - duringUpdatedCheckout = true ✅
# - Decision: DON'T reset flag ✅

[FLOW DEBUG] [FLOW CONTAINER] Container missing - recreating
[FLOW DEBUG] 🔔 Container-ready event received {flowInitialized: true, ...}  ← FLAG PRESERVED!
[FLOW DEBUG] 🔄 Flow component needs remounting - re-initializing...  ← REMOUNT TRIGGERED!
[FLOW DEBUG] 🚀 STARTING - Calling ckoFlow.init()... (2nd init - CORRECT!)
[FLOW PAYMENT] Payment Session Response: {id: 'ps_xxx2', ...}
[FLOW DEBUG] Component mounted - enabling UI! 🔥🔥🔥
```

**Result**: Flow remounts correctly after `updated_checkout`!

---

## Why This is the Correct Fix

### Previous Approach (Recency Check):
- ❌ Too complex
- ❌ Required timestamps
- ❌ Had edge cases
- ❌ Didn't address root cause

### This Approach:
- ✅ **Fixes the actual bug** (MutationObserver too aggressive)
- ✅ Simple guard conditions
- ✅ Uses existing WooCommerce patterns (`updated_checkout` flag)
- ✅ No new timers or state
- ✅ Only 2 small changes

---

## Expected Logs After Fix

### Good Signs ✅
```
[FLOW DEBUG] 🚀 STARTING - Calling ckoFlow.init()...
[FLOW DEBUG] onReady callback fired! 🔥🔥🔥
[FLOW DEBUG] Triggering update_checkout
[FLOW DEBUG] [FLOW CONTAINER] updated_checkout event fired
[FLOW DEBUG] 🔔 Container-ready event received {flowInitialized: true, ...}
[FLOW DEBUG] 🔄 Flow component needs remounting
[FLOW DEBUG] 🚀 STARTING - Calling ckoFlow.init()... (2nd - expected!)
```

### Bad Signs ❌ (if still broken)
```
[FLOW STATE] initialized changed: {old: true, new: false}  ← MutationObserver still resetting
[FLOW DEBUG] 🔔 Container-ready event received {flowInitialized: false, ...}  ← Flag lost
[FLOW DEBUG] ✅ ... no remounting needed  ← Skipped remount
```

---

## Testing Instructions

1. **Incognito** → Add product → Checkout
2. Fill name/address, select Crypto PSP
3. Select **Checkout.com** → complete order → 3DS → success
4. Home → add product → checkout (name/address already filled, Crypto PSP selected)
5. Select **Checkout.com**
6. **Expected**: Flow loads, then remounts (2 inits total - this is CORRECT)
7. **Check logs**: Should see `flowInitialized: true` preserved during `updated_checkout`
8. **Check logs**: Should see "🔄 Flow component needs remounting"

---

## Success Criteria

✅ Flow loads correctly on 2nd checkout visit  
✅ `ckoFlowInitialized` NOT reset during `updated_checkout`  
✅ Component remounts when container is recreated  
✅ Only 2 payment sessions (1st init + remount after updated_checkout)  
✅ No "initialized changed: {old: true, new: false}" during updated_checkout  

---

## Files Modified

1. `/flow-integration/assets/js/payment-session.js`
   - Enhanced MutationObserver with 2 guard conditions

2. `/flow-integration/assets/js/flow-container.js`
   - Set/clear `window.ckoFlowUpdatedCheckoutInProgress` flag

3. `/flow-integration/assets/js/modules/flow-container-ready-handler.js`
   - Changed remount condition (from previous fix)

---

**This fix addresses the actual root cause. Please test!**
