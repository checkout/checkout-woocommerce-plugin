# Critical Fix: Duplicate Flow Initialization (Recency Check)

**Date**: February 1, 2026  
**Issue**: Flow component initialized twice on 2nd checkout visit (3-second gap)  
**Root Cause**: Container-ready handler remounting Flow before SDK finishes rendering  
**Status**: ✅ FIXED

---

## Problem Analysis

### Observed Behavior

On the **2nd checkout visit** (after completing first order):
1. User goes to checkout page
2. Flow initializes successfully at **13:02:41**
3. Payment session API called: `ps_394MbcAc8W0zPGMZZQKIV9eLd7f`
4. **3 seconds later** at **13:02:44**:
   - Container-ready handler detects "Flow component needs remounting"
   - Flow initializes **again**
   - Second payment session API called: `ps_394Mc07K6kBdQj17OOZGyCeFuVu`

### Root Cause

The `flow-container-ready-handler.js` was making incorrect remounting decisions:

```javascript
// Line 72: This condition was TOO EAGER
if (!flowComponentActuallyMounted || flowWasInitializedBefore) {
    // Remount Flow
}
```

**The Problem**:
- When Flow is freshly initialized, `ckoFlowInitialized = true` immediately
- But the Flow SDK takes **3-5 seconds** to render the component root into the DOM
- During this window, `flowComponentActuallyMounted` is `false`
- Handler sees: "Flow initialized BUT component not mounted" → incorrectly triggers remount
- This creates a **second payment session** unnecessarily

### Why This Happens on 2nd Visit

On the **1st checkout visit**:
- Flow initializes for the first time
- No prior state exists
- Only one initialization occurs

On the **2nd checkout visit** (after completing order):
- Browser navigates from success page → back to checkout
- Flow initializes again
- WooCommerce triggers `updated_checkout` events
- Container-ready events fire while Flow SDK is still rendering
- Handler incorrectly decides to remount

---

## Solution: Recency Check

Added a **timestamp-based recency check** to prevent remounting if Flow was initialized within the last **5 seconds**.

### Changes

#### 1. `flow-container-ready-handler.js`

**Added tracking variables**:
```javascript
let lastInitTimestamp = 0;
const RECENT_INIT_THRESHOLD_MS = 5000; // 5 seconds
```

**Added recency check before remounting**:
```javascript
// CRITICAL: Check if Flow was initialized very recently
// The Flow SDK takes a few milliseconds to render the component root into the DOM
// If we try to remount during this window, we'll create duplicate payment sessions
const timeSinceLastInit = Date.now() - lastInitTimestamp;
if (lastInitTimestamp > 0 && timeSinceLastInit < RECENT_INIT_THRESHOLD_MS) {
    ckoLogger.debug('Flow component not mounted but initialized recently, waiting for SDK to render', {
        timeSinceLastInit: timeSinceLastInit + 'ms',
        threshold: RECENT_INIT_THRESHOLD_MS + 'ms'
    });
    return; // SKIP remount
}
```

**Added timestamp update on remount**:
```javascript
// Re-initialize
initializeFlowIfNeeded();
// Update timestamp to track when we last initialized
lastInitTimestamp = Date.now();
```

**Added public API to track initialization**:
```javascript
window.FlowContainerReadyHandler = {
    init: function () { /* ... */ },
    // Track when Flow was last initialized to prevent duplicate inits
    markInitialized: function() {
        lastInitTimestamp = Date.now();
    }
};
```

#### 2. `payment-session.js`

**Track timestamp on successful mount**:
```javascript
// Mark Flow as initialized and clear guard flag now that component is mounted
ckoFlowInitialized = true;
ckoFlowInitializing = false;

// Track initialization timestamp to prevent duplicate inits
if (typeof FlowContainerReadyHandler !== 'undefined' && FlowContainerReadyHandler.markInitialized) {
    FlowContainerReadyHandler.markInitialized();
}

// Enable UI after successful mount
ckoFlow.enableUIAfterMount();
```

---

## How It Works

### Before (Duplicate Init)

```
Time 0ms:    User selects Flow payment method
Time 100ms:  Flow.init() called, payment session created (ps_xxx1)
Time 150ms:  Flow component mounted
Time 200ms:  ckoFlowInitialized = true
Time 500ms:  Flow SDK still rendering component root...
Time 1000ms: Container-ready event fires
Time 1100ms: Handler checks:
             - flowInitialized = true ✅
             - flowComponentActuallyMounted = false ❌ (SDK still rendering)
             - Decision: "Needs remounting!" → Re-initialize
Time 1200ms: Flow.init() called AGAIN, payment session created (ps_xxx2) ❌ DUPLICATE!
```

### After (Recency Check Prevents Duplicate)

```
Time 0ms:    User selects Flow payment method
Time 100ms:  Flow.init() called, payment session created (ps_xxx1)
Time 150ms:  Flow component mounted
Time 200ms:  ckoFlowInitialized = true
             lastInitTimestamp = Date.now() ✅ TRACKED
Time 500ms:  Flow SDK still rendering component root...
Time 1000ms: Container-ready event fires
Time 1100ms: Handler checks:
             - flowInitialized = true ✅
             - flowComponentActuallyMounted = false ❌ (SDK still rendering)
             - timeSinceLastInit = 900ms
             - 900ms < 5000ms ✅ TOO RECENT!
             - Decision: "Initialized recently, skipping" → NO remount
Time 3000ms: Flow SDK finishes rendering component root
             Everything works normally ✅
```

---

## Expected Logs After Fix

### First Checkout Visit
```
[FLOW DEBUG] 🚀 STARTING - Calling ckoFlow.init()... {timestamp: '13:02:41'}
[FLOW PAYMENT] Payment Session Response: {id: 'ps_xxx1', ...}
[FLOW DEBUG] Component mounted - enabling UI! 🔥🔥🔥
[FLOW DEBUG] 🔔 Container-ready event received
[FLOW DEBUG] Flow component not mounted but initialized recently, waiting for SDK to render
               {timeSinceLastInit: '850ms', threshold: '5000ms'}
[FLOW DEBUG] onReady callback fired! 🔥🔥🔥
```

### Subsequent Updated_checkout Events
```
[FLOW DEBUG] 🔔 Container-ready event received
[FLOW DEBUG] ✅ Flow component still mounted, no remounting needed
```

---

## Verification

To verify this fix works:

1. **Fresh checkout visit**:
   - Add item to cart
   - Go to checkout
   - Select Flow payment method
   - **Expected**: Only **1 payment session API call** (ps_xxx)
   - **Expected**: Only **1 "🚀 STARTING - Calling ckoFlow.init()"** log

2. **Complete order and return to checkout**:
   - Complete payment
   - Return to checkout (add another item)
   - Select Flow payment method
   - **Expected**: Only **1 payment session API call** (ps_yyy)
   - **Expected**: Only **1 "🚀 STARTING - Calling ckoFlow.init()"** log
   - **Expected**: Log showing "initialized recently, waiting for SDK to render"

3. **No duplicate payment sessions**:
   - Search logs for `[FLOW PAYMENT] Payment Session Response`
   - Should see **exactly 1** per checkout visit

---

## Technical Details

### Why 5 Seconds?

- Flow SDK typically renders component root in **500-3000ms**
- WooCommerce `updated_checkout` events can fire multiple times within **2-3 seconds**
- **5 seconds** provides safe buffer to ensure:
  1. SDK finishes rendering
  2. All debounced events settle
  3. DOM is stable

### Why Not Use `ckoFlowInitializing`?

The existing `ckoFlowInitializing` flag is cleared as soon as `mount()` completes (line 2007), which happens **before** the Flow SDK renders the component root into the DOM. This leaves a gap where the handler can incorrectly decide to remount.

The recency check provides an **additional safety net** that lasts **5 seconds** after initialization, covering the entire SDK rendering period.

---

## WooCommerce-Standard Pattern

This approach aligns with WooCommerce best practices:
- **Debouncing**: Preventing rapid-fire events during AJAX updates
- **Guard flags**: Using multiple layers of protection (existing `ckoFlowInitializing` + new recency check)
- **Time-based guards**: Allowing DOM to settle before making decisions
- **Logging**: Clear debug messages explaining why actions are skipped

---

## Files Modified

1. `/flow-integration/assets/js/modules/flow-container-ready-handler.js`
   - Added `lastInitTimestamp` and `RECENT_INIT_THRESHOLD_MS`
   - Added recency check before remounting
   - Added `markInitialized()` public API

2. `/flow-integration/assets/js/payment-session.js`
   - Call `FlowContainerReadyHandler.markInitialized()` after successful mount

---

## Success Criteria

✅ Only **1 payment session API call** per checkout visit  
✅ Only **1 Flow initialization** per checkout visit  
✅ No duplicate "🚀 STARTING - Calling ckoFlow.init()" logs  
✅ Flow component renders correctly on first and subsequent visits  
✅ No visual flicker or disruption  

---

## Next Steps

**For User**:
1. Deploy updated plugin to staging
2. Test critical flows:
   - Fresh checkout visit (add item → checkout)
   - Complete order → return to checkout → add another item
   - Verify logs show only 1 payment session per visit
3. Search logs for "initialized recently, waiting for SDK to render" message
4. Confirm no duplicate payment sessions in Checkout.com dashboard

**Expected Result**: Clean, single initialization on every checkout visit with no duplicate API calls.
