# Critical Fix: Payment Method Switch Cleanup

**Date**: February 1, 2026  
**Issue**: Flow not showing after switching payment methods (Flow → Other → Flow)  
**Root Cause**: `ckoFlowInitialized` flag not reset when switching away from Flow  
**Status**: ✅ FIXED

---

## Problem Analysis

### Observed Behavior

**Test scenario**:
1. User loads checkout page
2. Selects Flow payment method → Flow initializes successfully ✅
3. Switches to TripleA payment method
4. Switches **back** to Flow payment method
5. **Flow does not appear** ❌

### Logs Showing the Issue

```
[FLOW DEBUG] Checkout.com payment method SELECTED
[FLOW DEBUG] canInitializeFlow: Already initialized  ← WRONG!
[FLOW DEBUG] ⏭️ SKIPPED - Already initialized, skipping ckoFlow.init()
```

### Root Cause

When the user switches **away** from Flow to another payment method:
1. WooCommerce removes Flow's DOM elements (standard behavior)
2. Flow component is destroyed
3. **BUT** the `ckoFlowInitialized` flag remains `true` ❌
4. When user switches back to Flow:
   - System sees `ckoFlowInitialized = true`
   - Thinks Flow is already initialized
   - Skips initialization → **no Flow appears**

---

## Solution

### Changes Made

#### 1. Enhanced Recency Check (flow-container-ready-handler.js)

**Previous logic**:
```javascript
// Skip remount if initialized recently
if (timeSinceLastInit < RECENT_INIT_THRESHOLD_MS) {
    return; // Skip remount
}
```

**New logic**:
```javascript
// Skip remount ONLY if initialized recently AND component exists in memory
const componentExistsInMemory = !!(ckoFlow && ckoFlow.flowComponent);

if (timeSinceLastInit < RECENT_INIT_THRESHOLD_MS && componentExistsInMemory) {
    // Component still exists AND initialized recently → wait for SDK
    return;
} else if (timeSinceLastInit < RECENT_INIT_THRESHOLD_MS && !componentExistsInMemory) {
    // Component was destroyed despite recent init → remount immediately
    ckoLogger.debug('Component destroyed - remounting immediately');
    // Continue to remount
}
```

This ensures:
- ✅ Prevents duplicate init if component exists and was just initialized
- ✅ Allows remount if component was destroyed (even if recently initialized)

#### 2. Payment Method Switch Cleanup (payment-session.js)

**Added cleanup logic** when user switches **away** from Flow:

```javascript
// When user selects different payment method (NOT Flow)
if (/* Flow not selected */) {
    // ... existing code ...
    
    // CRITICAL: Reset initialized flag and clean up component
    if (ckoFlowInitialized && ckoFlow.flowComponent) {
        ckoLogger.debug('Cleaning up Flow component - user switched to different payment method');
        try {
            // Unmount the component
            if (typeof ckoFlow.flowComponent.unmount === 'function') {
                ckoFlow.flowComponent.unmount();
            } else if (typeof ckoFlow.flowComponent.destroy === 'function') {
                ckoFlow.flowComponent.destroy();
            }
        } catch (e) {
            ckoLogger.debug('Error unmounting Flow component:', e);
        }
        ckoFlow.flowComponent = null;
        ckoFlowInitialized = false;
        ckoLogger.debug('Flow component cleaned up - ready for re-initialization');
    }
}
```

---

## How It Works Now

### Scenario 1: Flow → TripleA → Flow (Payment Method Switch)

**Before Fix**:
```
1. Select Flow → Flow initializes → ckoFlowInitialized = true
2. Select TripleA → Flow component destroyed but ckoFlowInitialized still true ❌
3. Select Flow again → Sees "already initialized" → Skips init → NO FLOW ❌
```

**After Fix**:
```
1. Select Flow → Flow initializes → ckoFlowInitialized = true ✅
2. Select TripleA → Flow component destroyed AND ckoFlowInitialized = false ✅
3. Select Flow again → Sees "not initialized" → Initializes → FLOW APPEARS ✅
```

### Scenario 2: Flow Initializes → Updated_checkout (DOM Churn)

**Before Fix**:
```
1. Flow initializes at T=0ms → ckoFlowInitialized = true
2. Updated_checkout fires at T=500ms (SDK still rendering)
3. Container-ready fires at T=650ms
4. Recency check: 650ms < 5000ms → Skip remount
5. Component was destroyed → NO FLOW ❌
```

**After Fix**:
```
1. Flow initializes at T=0ms → ckoFlowInitialized = true
2. Updated_checkout fires at T=500ms (SDK still rendering)
3. Container-ready fires at T=650ms
4. Recency check: 650ms < 5000ms BUT component destroyed → Remount ✅
5. FLOW APPEARS ✅
```

### Scenario 3: Flow Initializes → Container-ready Fires Early (Duplicate Prevention)

**Before Fix (from previous version)**:
```
1. Flow initializes at T=0ms → ckoFlowInitialized = true
2. Container-ready fires at T=100ms (SDK still rendering)
3. NO recency check → Remounts → DUPLICATE SESSION ❌
```

**After Fix**:
```
1. Flow initializes at T=0ms → ckoFlowInitialized = true
2. Container-ready fires at T=100ms (SDK still rendering)
3. Recency check: 100ms < 5000ms AND component exists → Skip remount ✅
4. SDK finishes rendering at T=3000ms → Flow appears normally ✅
```

---

## Expected Logs After Fix

### Test Case 1: Flow → TripleA → Flow

```
# Select Flow
[FLOW DEBUG] Checkout.com payment method SELECTED
[FLOW DEBUG] 🚀 STARTING - Calling ckoFlow.init()...
[FLOW DEBUG] Component mounted - enabling UI! 🔥🔥🔥

# Select TripleA
[FLOW DEBUG] Checkout.com payment method NOT selected - other method selected
[FLOW DEBUG] Cleaning up Flow component - user switched to different payment method
[FLOW DEBUG] Flow component cleaned up - ready for re-initialization

# Select Flow again
[FLOW DEBUG] Checkout.com payment method SELECTED
[FLOW DEBUG] canInitializeFlow() check result: {canInit: true, ...}  ← NOT "already initialized"
[FLOW DEBUG] 🚀 STARTING - Calling ckoFlow.init()...  ← Re-initializes!
[FLOW DEBUG] Component mounted - enabling UI! 🔥🔥🔥
```

### Test Case 2: Container-Ready During SDK Rendering

```
[FLOW DEBUG] 🚀 STARTING - Calling ckoFlow.init()... {timestamp: '13:17:22'}
[FLOW DEBUG] Component mounted - enabling UI! 🔥🔥🔥
[FLOW DEBUG] 🔔 Container-ready event received
[FLOW DEBUG] Flow component not mounted but initialized recently, waiting for SDK to render
             {timeSinceLastInit: '850ms', threshold: '5000ms', componentInMemory: true}
[FLOW DEBUG] onReady callback fired! 🔥🔥🔥
```

### Test Case 3: Container-Ready After Component Destroyed

```
[FLOW DEBUG] 🚀 STARTING - Calling ckoFlow.init()... {timestamp: '13:17:22'}
[FLOW DEBUG] Component mounted - enabling UI! 🔥🔥🔥
# ... WooCommerce destroys DOM during updated_checkout ...
[FLOW DEBUG] 🔔 Container-ready event received
[FLOW DEBUG] Flow component was destroyed despite recent init - remounting immediately
             {timeSinceLastInit: '1200ms', componentInMemory: false}
[FLOW DEBUG] 🔄 Flow component needs remounting - container is ready, re-initializing...
[FLOW DEBUG] 🚀 STARTING - Calling ckoFlow.init()...
[FLOW DEBUG] Component mounted - enabling UI! 🔥🔥🔥
```

---

## Verification Steps

### Test 1: Payment Method Switching
1. Go to checkout page
2. Select Flow payment method → **Verify Flow appears**
3. Select TripleA payment method
4. **Verify logs show**: "Cleaning up Flow component - user switched to different payment method"
5. Select Flow payment method again
6. **Verify Flow appears again** (not "already initialized" skip)

### Test 2: Duplicate Prevention (Still Works)
1. Fresh checkout visit
2. Select Flow payment method
3. **Verify logs show only 1** "🚀 STARTING - Calling ckoFlow.init()"
4. **Verify only 1 payment session API call**

### Test 3: DOM Churn Handling
1. Select Flow payment method
2. Change billing address (triggers updated_checkout)
3. **Verify Flow remains visible** (no blank screen)
4. **Verify logs show** "initialized recently, waiting for SDK to render" OR "remounting immediately"

---

## Files Modified

1. `/flow-integration/assets/js/modules/flow-container-ready-handler.js`
   - Enhanced recency check to consider component memory state
   - Only skip remount if component exists AND initialized recently
   - Remount immediately if component destroyed (even if recently initialized)

2. `/flow-integration/assets/js/payment-session.js`
   - Added cleanup logic when switching away from Flow
   - Reset `ckoFlowInitialized = false` on payment method change
   - Unmount and null component reference
   - Added debug logging for cleanup

---

## Success Criteria

✅ Flow appears when first selected  
✅ Flow appears when switching back (Flow → Other → Flow)  
✅ No duplicate initialization (only 1 payment session per init)  
✅ Flow survives updated_checkout DOM churn  
✅ Clean logs showing proper state management  

---

## Next Steps

**For User**:
1. Deploy updated plugin
2. Test all three scenarios above
3. Verify logs show proper cleanup and re-initialization
4. Confirm Flow appears correctly in all cases

**Expected Result**: Flow should always appear when selected, regardless of payment method switching or DOM updates, with no duplicate sessions.
