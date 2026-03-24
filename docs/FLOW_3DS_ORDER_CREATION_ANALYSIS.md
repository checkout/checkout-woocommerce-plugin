# Flow 3DS Order Creation – Analysis & Issues

## Executive Summary

The merchant reports **multiple issues with order creation** specifically for **Flow 3DS payments**. This document analyzes the Flow 3DS order creation flow, identifies failure points, and recommends fixes.

---

## Flow 3DS Order Creation Sequence

```
1. User loads checkout page
   → loadFlow() creates payment session (NO order exists yet)
   → success_url = /?wc-api=wc_checkoutcom_flow_process (no order_id)

2. User fills form, clicks "Place Order"
   → createOrderBeforePayment() runs (AJAX to WooCommerce checkout)
   → Order created via WC_Checkout::create_order()
   → order_id, order_key returned
   → Form gets <input name="order_id" value="...">
   → FlowSessionStorage.setOrderId(), setOrderKey()

3. flowComponent.submit()
   → User redirected to 3DS
   → Checkout.com uses success_url from ORIGINAL session (created at step 1)
   → CRITICAL: Session was created BEFORE order existed → success_url has NO order_id

4. User completes 3DS, returns
   → Checkout.com redirects to success_url + adds cko-payment-id, cko-payment-session-id
   → Final URL may NOT have order_id (if session was created before order)

5. handle_3ds_return() runs
   → Must find order by: order_id (URL), payment_session_id, payment_id, or email+amount
   → Fallbacks: create_minimal_order_from_payment_details, wc_create_order from cart
```

---

## Identified Issues

### Issue 1: success_url Created Without order_id (Timing Gap)

**Root cause:** The payment session is created when `loadFlow()` runs (page load). At that moment, no order exists. The `success_url` is built without `order_id` and `key`.

**Code location:** `flow-integration/assets/js/payment-session.js` lines 564–616

When building the session request for regular checkout:
- `formOrderId = jQuery('input[name="order_id"]').val()` → empty (order not created yet)
- `sessionOrderId = FlowSessionStorage.getOrderId()` → empty
- Result: `successUrl` has no `order_id` or `key`

**Impact:** When the user returns from 3DS, the redirect URL does not include `order_id` or `key`. The PHP handler must rely on `payment_session_id` or `payment_id` to find the order.

---

### Issue 2: Payment Session Not Updated Before submit()

**Root cause:** After `createOrderBeforePayment()` completes, the form has `order_id` and sessionStorage has `order_key`. But the Flow component uses the **existing** payment session (created at load). There is no call to update the session or create a new one with `order_id` in `success_url` before `flowComponent.submit()`.

**Code location:** `flow-integration/assets/js/payment-session.js` lines 4296–4323

```javascript
const orderId = await createOrderBeforePayment();
// ... order_id now in form and sessionStorage
ckoFlow.flowComponent.submit();  // Uses OLD session - no order_id in success_url!
```

**Impact:** Same as Issue 1 – 3DS return URL lacks `order_id`.

---

### Issue 3: Metadata Sent to Checkout.com Has No order_id

**Root cause:** Payment session metadata is `{ udf5: ... }` only. No `order_id` is sent.

**Code location:** `flow-integration/assets/js/modules/flow-checkout-data.js` line 120

```javascript
let metadata = { udf5: cko_flow_vars.udf5 };
```

**Impact:** Webhooks receive `webhook_data->metadata->order_id` = null. Webhooks cannot match the payment to an order by metadata. They rely on `order_id` in metadata, which is never set for Flow.

---

### Issue 4: _cko_payment_session_id May Not Be Saved Before 3DS Redirect

**Root cause:** The payment session ID is saved to the order in several places, but timing can be wrong:

1. `store_payment_session_id_in_order` – fires on `woocommerce_checkout_create_order` (when order is created)
2. `ajax_save_payment_session_id` – separate AJAX call
3. `ajax_create_order` – stores it if `payment_session_id` is in POST

If the order is created via `createOrderBeforePayment()` (WooCommerce checkout AJAX) with `cko_flow_precreate_order`, the `store_payment_session_id_in_order` hook may not run in the same flow. The `ajax_create_order` handler stores it only if `cko-flow-payment-session-id` is in the form POST.

**Code location:** `flow-integration/class-wc-gateway-checkout-com-flow.php` lines 6437–6440

**Impact:** If `_cko_payment_session_id` is not on the order, `handle_3ds_return` cannot find the order by payment session ID.

---

### Issue 5: createOrderBeforePayment() Can Fail Silently or Return null

**Failure modes:**
- Nonce expired/missing → validation error
- Form validation fails (WooCommerce) → order not created
- Network timeout
- Race: duplicate calls, `orderCreationInProgress` lock
- `response.data.order_key` empty → guest orders may not get key

**Code location:** `flow-integration/assets/js/payment-session.js` lines 3667–4080

**Impact:** If `createOrderBeforePayment()` returns null, the code does `return` and never calls `flowComponent.submit()`. But if there is a path where submit is called without a valid order (e.g. saved card or APM flow), payment could succeed with no order.

---

### Issue 6: Order Lookup Fallbacks Can Fail

**When `order_id` is missing from URL, lookup order by:**
1. `_cko_payment_session_id` (from URL or payment metadata)
2. `_cko_payment_id`
3. Email + amount (pending/failed orders)
4. Create from cart or `create_minimal_order_from_payment_details`

**Failure cases:**
- No `payment_session_id` in URL and not in payment metadata
- Order has no `_cko_payment_session_id` (Issue 4)
- `create_minimal_order_from_payment_details` fails (e.g. no customer email)
- Cart empty and payment details incomplete

---

### Issue 7: create_minimal_order_from_payment_details() Requirements

**Requires:**
- `payment_details['customer']['email']` – if missing, order creation can fail
- Valid `payment_details` from Checkout.com API
- No exception during `wc_create_order()`

**Code location:** `flow-integration/class-wc-gateway-checkout-com-flow.php` ~line 4920

---

## Recommended Fixes

### Fix 1: Update Payment Session Before submit() (High Priority)

Before `flowComponent.submit()`, create a **new** payment session with `order_id` and `key` in `success_url`, or call a session-update endpoint if Checkout.com supports it.

**Option A:** Reload Flow after order creation (creates new session with order_id in success_url):
```javascript
// After createOrderBeforePayment() succeeds:
// 1. Ensure order_id is in form
// 2. Call reloadFlowComponent() to create new session with order_id in success_url
// 3. Wait for Flow to initialize
// 4. Then flowComponent.submit()
```

**Option B:** Add a backend endpoint to update the session’s success_url with order_id, and call it before submit.

---

### Fix 2: Add order_id to Payment Metadata (High Priority)

When creating/updating the payment session **after** the order exists, include `order_id` in metadata:

```javascript
// In flow-checkout-data.js, when orderId is available:
let metadata = { udf5: cko_flow_vars.udf5 };
if (orderId) {
  metadata.order_id = String(orderId);
}
```

This requires the session to be created or updated **after** order creation. Combined with Fix 1.

---

### Fix 3: Ensure payment_session_id Is Always Saved to Order

Before `flowComponent.submit()`:
1. Confirm `cko-flow-payment-session-id` is in the form
2. If `createOrderBeforePayment` uses WooCommerce checkout AJAX, ensure the handler receives and stores `_cko_payment_session_id` on the order

Verify `ajax_create_order` always receives and saves the payment session ID when it is present in the form.

---

### Fix 4: Add order_id to success_url When Session Is Updated

The payment session can be updated (e.g. on field change). Ensure that when the session is updated and `order_id` exists in the form, it is included in `success_url` and `failure_url`. The logic at lines 680–722 already does this; the main gap is that the **initial** session is created before the order exists. Fix 1 addresses that.

---

### Fix 5: Improve create_minimal_order_from_payment_details Robustness

- Handle missing customer email (e.g. use a placeholder or fail with a clear error)
- Add logging when minimal order creation fails
- Consider setting initial status to `pending` instead of `failed` if the payment has succeeded

---

## Diagnostic Queries for Merchant

To confirm whether specific payments have orders:

```sql
-- By payment ID
SELECT p.ID, p.post_status, pm.meta_value AS payment_id
FROM wp_posts p
INNER JOIN wp_postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_cko_payment_id'
WHERE pm.meta_value = 'pay_xxx'
  AND p.post_type = 'shop_order';

-- By payment session ID
SELECT p.ID, p.post_status, pm.meta_value AS payment_session_id
FROM wp_posts p
INNER JOIN wp_postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_cko_payment_session_id'
WHERE pm.meta_value = 'ps_xxx'
  AND p.post_type = 'shop_order';
```

---

## Summary Table

| Issue | Severity | Fix | Status |
|-------|----------|-----|--------|
| success_url created without order_id | High | Update/create session with order_id before submit | Open |
| Metadata has no order_id | High | Add order_id to metadata when order exists | Open |
| payment_session_id not saved | Medium | Ensure it is always in form and saved by ajax_create_order | Open |
| createOrderBeforePayment failures | Medium | Improve error handling and user feedback | Open |
| Order lookup fallbacks fail | Medium | Strengthen create_minimal_order and logging | Open |
| **Order reused after security check failure** | **Critical** | **Server-side order reuse validation** | **FIXED** |

---

## Implemented Fix: Order Reuse Validation (v5.0.3)

### Problem
When a customer's first payment attempt failed due to a security check (amount mismatch from coupon timing), the same order was incorrectly reused for a subsequent payment attempt. This caused:
1. Order 376072 created with amount $169 → payment failed security check → order marked `failed` with `_cko_security_check_failed` flag
2. Second payment attempt at $152.10 reused order 376072 instead of creating a new order
3. The successful payment was applied to an order with the wrong original amount

### Root Cause
JavaScript `createOrderBeforePayment()` had an early-return that reused any `order_id` found in the form, even if:
- The order had `_cko_security_check_failed` flag (previous payment failed security check)
- The order's cart hash didn't match the current cart (cart changed)
- The order total didn't match the current cart total (coupon applied/removed)

### Fix Implementation

#### 1. JavaScript Changes (`payment-session.js`)
Modified `createOrderBeforePayment()` to only early-return for **order-pay pages**. For regular checkout, it **always** calls the server to determine if an existing order can be reused.

```javascript
// Before fix: early-return if ANY order_id exists
if (orderIdField.length && orderIdField.val()) {
    return parseInt(existingOrderId);  // WRONG: reuses failed orders
}

// After fix: only early-return for order-pay pages
const isOrderPayPage = window.location.pathname.includes('/order-pay/');
if (isOrderPayPage && orderIdField.length && orderIdField.val()) {
    return parseInt(existingOrderId);  // OK: order-pay page
}
// For regular checkout: always call server to validate
```

#### 2. JavaScript Changes (`flow-updated-checkout-guard.js`)
When cart total changes (e.g., coupon applied), clear stale `order_id` from form and session storage:

```javascript
if (currentOrderAmount !== previousOrderAmount) {
    // Clear stale order_id from form
    jQuery('input[name="order_id"]').val('');
    // Clear from session storage
    FlowSessionStorage.clearOrderData();
    // Reload Flow component
    reloadFlowComponent();
}
```

#### 3. PHP Server-Side Validation (`class-wc-gateway-checkout-com-flow.php`)
Added two new methods for order reuse validation:

**`get_reusable_order_id($payment_session_id)`**
- Checks `order_awaiting_payment` session (WooCommerce standard pattern)
- Falls back to searching by `_cko_payment_session_id` meta

**`validate_order_reuse($order)`** - Validates if an order can be reused:
1. **Security check failed**: If `_cko_security_check_failed` meta exists → cannot reuse
2. **Status check**: Only `pending` status can be reused (failed orders create a new order)
3. **Cart hash check**: Current cart hash must match order's cart hash
4. **Total check**: Current cart total must match order total (±$0.01 tolerance)

### Comprehensive Logging
Added detailed logging for audit trail:

```
[CREATE ORDER] ✅ REUSING existing order - Order ID: 376072, Reason: Order status is "pending" and cart matches
[CREATE ORDER] ⚠️ CANNOT reuse existing order 376072 - Reason: Previous payment failed security check (amount mismatch) - Will create NEW order
[ORDER REUSE CHECK] Order 376072 has security check failed flag - Reason: Amount mismatch
[ORDER REUSE CHECK] Order 376072 cart hash mismatch - Order: abc123, Current: xyz789
```

### Behavior Matrix

| Scenario | Before Fix | After Fix |
|----------|------------|-----------|
| Order-pay page | Reuse ✓ | Reuse ✓ (no change) |
| Checkout, first attempt | New order ✓ | New order ✓ (no change) |
| **Checkout, retry after decline (failed status)** | **Reuse (WRONG)** | **New order ✓** |
| **Checkout, retry after security fail** | **Reuse (WRONG)** | **New order ✓** |
| **Checkout, cart changed** | **Reuse (WRONG)** | **New order ✓** |
| Checkout, page refresh | New order ✓ | New order ✓ (no change) |
| Checkout, same session, pending order exists | Reuse ✓ | Reuse ✓ (if cart matches) |

### Filter for Customization
Developers can customize which order statuses allow reuse:

```php
add_filter( 'cko_flow_order_reusable_statuses', function( $statuses, $order ) {
    // Example: also allow 'on-hold' status
    $statuses[] = 'on-hold';
    return $statuses;
}, 10, 2 );
```
