# Order Status Regression Analysis - Payment Captured but Status Goes Back to On Hold

## Problem Statement

**Order Notes Timeline:**
1. ✅ "Order status changed from Pending payment to On hold" - 11:03 am
2. ✅ "Checkout.com Payment Authorised – using FLOW (3DS return): card – Payment ID: pay_lx2tk64kvj7enll2losvpxv6ou" - 11:03 am
3. ✅ "Order status changed from Pending payment to Processing" - 11:03 am
4. ✅ "Checkout.com Payment Captured – Payment ID: pay_lx2tk64kvj7enll2losvpxv6ou, Action ID: act_6sw6hkj7rkbevl42kp2eufuxi4" - 11:03 am
5. ✅ "Checkout.com Payment Capture webhook received – Payment ID: pay_lx2tk64kvj7enll2losvpxv6ou" - 11:03 am

**Issue:** Payment is captured (status should be Processing), but status goes back to On hold.

---

## Root Cause Analysis

### Current Flow (3DS Return Handler)

**Location:** `flow-integration/class-wc-gateway-checkout-com-flow.php` (line 1397-1425)

**Sequence:**
1. **Line 1399:** Process pending webhooks
   ```php
   WC_Checkout_Com_Webhook_Queue::process_pending_webhooks_for_order($order);
   ```

2. **Line 1406:** Check if already captured
   ```php
   $already_captured = $order->get_meta('cko_payment_captured');
   ```

3. **Line 1411:** If NOT captured, set status to 'on-hold'
   ```php
   if (!$already_captured) {
       $status = 'on-hold';
   }
   ```

4. **Line 1712:** Update status (if not null)
   ```php
   if (null !== $status) {
       $order->update_status($status);
   }
   ```

### The Problem

**Webhook Processing Order Issue:**

When `process_pending_webhooks_for_order()` is called (line 1399), it processes webhooks in the order they were queued (by `created_at`). 

**Possible Scenarios:**

#### Scenario A: Authorization Webhook Processed After Capture

1. **Capture webhook arrives first** → Queued
2. **Authorization webhook arrives second** → Queued
3. **3DS return handler runs:**
   - Processes capture webhook → Sets `cko_payment_captured` = true → Sets status to 'processing' ✅
   - Processes authorization webhook → Checks `$already_captured` → Should return early ✅
   - Checks `$already_captured` again (line 1406) → Should be true ✅
   - Should skip status update ✅

**BUT:** If authorization webhook processes AFTER the check at line 1406, but BEFORE the status update at line 1712, then:
- Capture webhook sets status to 'processing'
- Authorization webhook processes → Sets status to 'on-hold' ❌
- Then 3DS return handler checks `$already_captured` → Might be false if webhook hasn't saved meta yet ❌

#### Scenario B: Race Condition in Webhook Processing

**The Critical Issue:**

Looking at `authorize_payment()` (line 116):
```php
$order->update_status($auth_status); // Always updates to 'on-hold' if not already captured
```

**But the check at line 89-93:**
```php
if ($already_captured) {
    return true; // Should prevent this
}
```

**However:** If authorization webhook is processed from queue AFTER capture webhook, but the order meta hasn't been refreshed yet, the check might fail.

#### Scenario C: Webhook Processing Order

**Location:** `includes/class-wc-checkout-com-webhook-queue.php` (line 166)

The webhooks are retrieved with:
```php
ORDER BY created_at ASC
```

This means webhooks are processed in the order they were created, NOT in logical order (auth first, then capture).

**Problem:**
- If capture webhook was created first → Processed first → Sets status to 'processing' ✅
- If authorization webhook was created second → Processed second → Should check if captured ✅
- BUT: If authorization webhook processes and the order object hasn't been refreshed, `$already_captured` check might fail ❌

---

## The Real Issue

**Looking at the authorization webhook handler (line 105-108):**

```php
// Add note to order if Authorized already.
if ($already_authorized && $order->get_status() === $auth_status) {
    $order->add_order_note($message);
    return true;
}
```

**This check only prevents duplicate processing if:**
- Order is already authorized AND
- Order status already matches auth_status ('on-hold')

**But if:**
- Order is authorized (meta set)
- Order status is 'processing' (from capture webhook)
- This check FAILS → Continues to line 116 → Updates status to 'on-hold' ❌

**The bug:** The authorization webhook doesn't check if the order is already captured before updating status!

---

## Code Flow Analysis

### Authorization Webhook Handler

**Location:** `includes/class-wc-checkout-com-webhook.php` (line 32)

**Current Logic:**
1. Line 89: Check if already captured → Return early ✅
2. Line 95: Check if already authorized → If yes and status matches, return ✅
3. Line 116: **ALWAYS updates status to 'on-hold'** ❌

**The Problem at Line 105-108:**
```php
if ($already_authorized && $order->get_status() === $auth_status) {
    $order->add_order_note($message);
    return true;
}
```

**This only prevents update if status is already 'on-hold'.**

**If status is 'processing' (from capture), this check fails, and it continues to line 116 which updates status to 'on-hold'!**

---

## The Fix Needed

**In `authorize_payment()` function:**

**Current check (line 105-108):**
```php
if ($already_authorized && $order->get_status() === $auth_status) {
    $order->add_order_note($message);
    return true;
}
```

**Should be:**
```php
// Don't update status if already captured (even if not authorized yet)
if ($already_captured) {
    // Just add note, don't change status
    $order->add_order_note($message);
    return true;
}

// Don't update status if already authorized AND status matches
if ($already_authorized && $order->get_status() === $auth_status) {
    $order->add_order_note($message);
    return true;
}

// Don't update status if order is already in a more advanced state (processing, completed)
$current_status = $order->get_status();
if (in_array($current_status, array('processing', 'completed', 'on-hold'), true)) {
    // If already processing/completed, don't downgrade
    if ($current_status === 'processing' || $current_status === 'completed') {
        $order->add_order_note($message);
        return true; // Don't downgrade from processing/completed
    }
}
```

**OR simpler fix:**

After line 108, add another check:
```php
// Don't update status if order is already processing or completed (more advanced states)
$current_status = $order->get_status();
if (in_array($current_status, array('processing', 'completed'), true)) {
    $order->add_order_note($message);
    return true; // Don't downgrade from processing/completed to on-hold
}
```

---

## Summary

**Root Cause:**
The authorization webhook handler (`authorize_payment()`) updates status to 'on-hold' even when the order is already in 'processing' status (from capture webhook). The check at line 105-108 only prevents update if status is already 'on-hold', but doesn't prevent downgrading from 'processing' to 'on-hold'.

**Fix:**
Add a check to prevent status update if order is already in 'processing' or 'completed' status. Authorization should never downgrade an order from a more advanced state.

**Impact:**
- Prevents status regression from 'processing' back to 'on-hold'
- Ensures order status always reflects the most advanced payment state
- Maintains proper status hierarchy: pending → on-hold → processing → completed

