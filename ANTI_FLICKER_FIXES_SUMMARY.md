# Anti-Flicker Fixes Implementation Summary

## Date: 2026-02-01

## Problem
Visual disruptions (flicker) caused by:
1. Multiple blocked initialization attempts calling `showFlowWaitingMessage()` repeatedly
2. Multiple container-ready events during `updated_checkout` causing rapid-fire checks
3. Container recreation during DOM churn causing layout shifts
4. Long CSS transition durations (300ms) amplifying flicker

---

## Fixes Implemented

### ✅ Fix #1: Debounce Container-Ready Event Handler

**File**: `flow-integration/assets/js/modules/flow-container-ready-handler.js`

**Change**: Added 150ms debounce to container-ready event handler

**Before**:
```javascript
document.addEventListener('cko:flow-container-ready', function (event) {
    // Handle event immediately
});
```

**After**:
```javascript
let containerReadyDebounceTimer;
document.addEventListener('cko:flow-container-ready', function (event) {
    clearTimeout(containerReadyDebounceTimer);
    containerReadyDebounceTimer = setTimeout(function() {
        handleContainerReady(event);
    }, 150); // Wait for DOM to settle
});
```

**Benefit**: 
- Prevents rapid-fire checks during `updated_checkout` events
- Reduces unnecessary remounting attempts
- Eliminates flicker from multiple events firing in quick succession

---

### ✅ Fix #2: Improve Container Detection

**File**: `flow-integration/assets/js/flow-container.js`

**Change**: 
- Increased `updated_checkout` delay from 100ms → 200ms
- Added double-check to prevent false "missing" container detections
- Check if payment_box exists before recreating container

**Before**:
```javascript
setTimeout(function() {
    const flowContainer = document.getElementById('flow-container');
    if (paymentMethod && !flowContainer) {
        addPaymentMethod(); // Recreate immediately
    }
}, 100);
```

**After**:
```javascript
setTimeout(function() {
    const flowContainer = document.getElementById('flow-container');
    if (paymentMethod && !flowContainer) {
        // Double-check: container might exist but without ID
        const paymentBox = paymentMethod.querySelector("div.payment_box");
        if (paymentBox && paymentBox.id !== 'flow-container') {
            // Just add ID, don't recreate
            paymentBox.id = "flow-container";
            // ... emit event
        } else if (!paymentBox) {
            // Truly missing - recreate
            addPaymentMethod();
        }
    }
}, 200); // Increased from 100ms
```

**Benefit**:
- Prevents false "missing" detections during transient DOM updates
- Reduces unnecessary container recreations
- Eliminates layout shifts from attribute reapplication

---

### ✅ Fix #3: Prevent Duplicate Waiting Message Insertions

**File**: `flow-integration/assets/js/payment-session.js`

**Change**: Check if waiting message exists BEFORE making any visual changes

**Before**:
```javascript
function showFlowWaitingMessage() {
    flowContainer.style.display = "block"; // Visual change immediately
    
    let waitingMessage = flowContainer.querySelector('.cko-flow__waiting-message');
    if (!waitingMessage) {
        // Create message
    }
}
```

**After**:
```javascript
function showFlowWaitingMessage() {
    // Check if message already exists FIRST
    let waitingMessage = flowContainer.querySelector('.cko-flow__waiting-message');
    if (waitingMessage) {
        ckoLogger.debug('Waiting message already shown - skipping duplicate insertion');
        return; // Early exit - no visual changes
    }
    
    flowContainer.style.display = "block"; // Only change if needed
    // Create message
}
```

**Benefit**:
- Prevents duplicate message insertions during multiple blocked attempts
- Eliminates layout shifts from repeated DOM insertions
- Reduces unnecessary container visibility toggles

---

### ✅ Fix #4: Reduce CSS Transition Duration

**File**: `flow-integration/assets/css/flow.css`

**Changes**:
- Container transitions: 300ms → 150ms
- Skeleton transitions: 300ms → 150ms
- Button transitions: 200ms → 150ms
- Checkbox transitions: 200ms → 150ms
- Component fade-in: 300ms → 200ms
- Accordion animations: 300ms → 200ms

**Benefit**:
- Faster transitions make flicker less noticeable
- More responsive UI feel
- Reduces cumulative transition time during rapid changes

---

## Expected Impact

### Before Fixes
- **Container-ready events**: 3-4 events → 3-4 remount checks → Potential flicker
- **Blocked initializations**: 4 attempts → 4 waiting messages inserted → Visible flicker
- **Container recreations**: 2 times → 2 attribute reapplications → Layout shifts
- **CSS transitions**: 300ms → Amplified flicker

### After Fixes
- **Container-ready events**: 3-4 events → 1 debounced check → No flicker
- **Blocked initializations**: 4 attempts → 1 waiting message (early exit on duplicates) → No flicker
- **Container recreations**: 0-1 times (double-check prevents false detections) → No/minimal layout shifts
- **CSS transitions**: 150-200ms → Faster transitions, less noticeable

---

## Testing Recommendations

### 1. **Initial Load Test**
- Navigate to checkout page
- Observe initial load without entering fields
- **Expected**: No flicker, smooth container appearance

### 2. **Field Entry Test**
- Enter required fields one by one
- Observe container/waiting message behavior
- **Expected**: Waiting message appears once, no flicker

### 3. **Payment Method Selection Test**
- Select Flow payment method
- Observe Flow initialization
- **Expected**: Smooth initialization, no container flicker

### 4. **Name Change Test** (Critical)
- Enter card details
- Change name field
- Observe Flow reload
- **Expected**: Smooth reload, no duplicate containers

### 5. **Rapid Field Changes Test**
- Rapidly change multiple fields
- Observe `updated_checkout` behavior
- **Expected**: Debounced checks, no flicker

---

## Files Modified

1. ✅ `flow-integration/assets/js/modules/flow-container-ready-handler.js`
   - Added debounce timer
   - Extracted handler to separate function

2. ✅ `flow-integration/assets/js/flow-container.js`
   - Increased delay to 200ms
   - Added double-check for container existence
   - Improved recreation logic

3. ✅ `flow-integration/assets/js/payment-session.js`
   - Added early exit in `showFlowWaitingMessage()`
   - Moved duplicate check before visual changes

4. ✅ `flow-integration/assets/css/flow.css`
   - Reduced all transition durations
   - Updated 6 transition/animation rules

---

## Next Steps

1. **Build & Deploy**: Run `build.sh` to compile changes
2. **Test**: Follow testing recommendations above
3. **Monitor**: Check browser console for flicker-related logs
4. **Verify**: Confirm no visual disruptions during critical flows

---

## Rollback Plan

If issues occur, revert changes:
```bash
git diff HEAD -- flow-integration/assets/js/modules/flow-container-ready-handler.js
git diff HEAD -- flow-integration/assets/js/flow-container.js
git diff HEAD -- flow-integration/assets/js/payment-session.js
git diff HEAD -- flow-integration/assets/css/flow.css
```

All changes are isolated and can be reverted individually.

---

**Implementation Complete** ✅
