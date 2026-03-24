# Flow 3DS: Payment Succeeds at Checkout.com but NO WooCommerce Order Exists

## Executive Summary

This document identifies **ALL scenarios** where a payment can succeed at Checkout.com but no corresponding WooCommerce order exists. Each scenario includes the exact code path, root cause, and file/line references.

---

## 1. JavaScript Payment Flow Analysis

### 1.1 How `createOrderBeforePayment()` is Called

**File:** `flow-integration/assets/js/payment-session.js`

**Call path:** Place Order button click → `onSubmit` handler → (for new card, not saved card) async IIFE → `createOrderBeforePayment()` (line 4311)

```javascript
// Lines 4309-4316
const orderId = await createOrderBeforePayment();
if (!orderId) {
    ckoLogger.error('[CREATE ORDER] Failed to create order - cannot proceed with payment');
    return;  // Submit is NOT called
}
ckoFlow.flowComponent.submit();  // Line 4336
```

**Key finding:** If `createOrderBeforePayment()` returns `null`, the code **returns early** and **never** calls `flowComponent.submit()`. Payment cannot proceed for this path.

---

### 1.2 When `createOrderBeforePayment()` Can Fail or Return null

**File:** `flow-integration/assets/js/payment-session.js` (lines 3667-4020)

| Failure Case | Line(s) | Reason |
|--------------|---------|--------|
| Order creation already in progress (race) | 3669-3683 | `FlowState.get('orderCreationInProgress')` → returns null after 500ms wait if still locked |
| No form found (checkout/order-pay) | 3743-3775 | `jQuery("form.checkout")` and `form#order_review` both empty → returns null |
| Nonce missing | 3824-3837 | `woocommerce-process-checkout-nonce` not in form → returns null |
| AJAX request fails | 3889-3896 | `jQuery.ajax().fail()` → `responseText` may be error HTML |
| Response not parseable as JSON | 3899-3900 | `parseJsonLenient()` returns null |
| Response indicates failure | 3902-3920 | `response.result === 'failure'` or no `response.data.order_id` |
| Order ID extraction fails | 3934-3940 | No `order_id` in redirect URL, `response.order_id`, or `response.data.order_id` |

**Note:** For **order-pay pages**, `flowComponent.submit()` can be called **without** `createOrderBeforePayment()` — the order already exists from the URL (lines 4186-4242). If the order-pay form is malformed or order was deleted, payment could succeed with no valid order.

---

### 1.3 Is `flowComponent.submit()` Ever Called Without a Valid Order?

**Yes, in these cases:**

| Scenario | Code Path | Why Order May Not Exist |
|----------|-----------|-------------------------|
| **Order-pay page** | Lines 4201-4213 | Order ID from URL; order could have been deleted, or URL tampered |
| **Session created before order** | Lines 564-616, 4311-4336 | Session created at page load; after `createOrderBeforePayment()` succeeds, `submit()` uses **existing** session. Session's `success_url` has no `order_id` if session wasn't updated. |
| **Session update with stale order_id** | Lines 680-722 | Session update uses `formOrderId` or `sessionOrderId`. If user had old tab with stale order_id, wrong order could be used. |

**Critical:** The Flow component uses the **payment session created at load**. After `createOrderBeforePayment()` completes, there is **no** call to update the session or create a new one. The `success_url` in the session is whatever was set when the session was created — which is **before** the order existed for regular checkout.

---

### 1.4 Payment Completion Handler (`onPaymentCompleted`)

**File:** `flow-integration/assets/js/payment-session.js` (lines 1137-1334)

**3DS flow:** When `paymentResponse.threeDs && paymentResponse.threeDs.challenged` is true, the handler **returns immediately** (lines 1140-1143). The user is redirected to 3DS by Checkout.com. The redirect URL is determined by the **payment session's success_url**, not by `onPaymentCompleted`.

**Non-3DS (e.g. APM):** If `!hasOrderId`, the form is submitted (line 1221) — WooCommerce creates the order server-side. If `hasOrderId`, it redirects to the process endpoint with order_id (line 1332).

**Gap:** For 3DS card payments, the redirect happens **before** `onPaymentCompleted` can run (or it returns early). The URL comes from the session. If the session had no `order_id` in `success_url`, the 3DS return URL will not have `order_id`.

---

## 2. PHP 3DS Return Handler Analysis

### 2.1 `handle_3ds_return()` Order Lookup Methods

**File:** `flow-integration/class-wc-gateway-checkout-com-flow.php` (lines 3519-4108)

**Lookup sequence:**

| Priority | Method | Lines | When Used |
|----------|--------|-------|-----------|
| 1 | `order_id` + `key` from GET | 3552-3567 | Fast path — direct load and key validation |
| 2 | `_cko_payment_session_id` meta | 3625-3675 | From URL or `payment_details['metadata']['cko_payment_session_id']` |
| 3 | `_cko_payment_id` meta | 3678-3705 | Fallback if no payment_session_id match |
| 4 | Email + amount (pending/failed) | 3712-3762 | Customer email from payment_details, 7-day window |
| 5 | `create_minimal_order_from_payment_details()` | 3828-3841 | Cart empty, payment_details available |
| 6 | `wc_create_order()` from cart | 3860-3863 | Cart has items |
| 7 | `create_minimal_order_from_payment_details()` (fallback) | 3870-3883 | `wc_create_order()` failed |

---

### 2.2 When Order Lookup Fails

**Scenario A: No `order_id` in URL**

- Session was created before order existed → `success_url` has no `order_id`.
- Checkout.com redirects to `/?wc-api=wc_checkoutcom_flow_process&cko-payment-id=xxx` (no order_id).

**Scenario B: Payment session ID missing**

- Not in URL: `cko-payment-session-id` not appended by Checkout.com.
- Not in payment metadata: `payment_details['metadata']['cko_payment_session_id']` empty.
- **Cause:** Payment session metadata only has `{ udf5: ... }` — `cko_payment_session_id` is set by Checkout.com when session is created, but `order_id` is never sent in metadata (see Section 4).

**Scenario C: Order has no `_cko_payment_session_id`**

- `store_payment_session_id_in_order` runs on `woocommerce_checkout_create_order`.
- Order created via `createOrderBeforePayment()` (AJAX) — the hook runs during checkout processing.
- If `cko-flow-payment-session-id` is not in the form POST, it won't be saved (line 650).
- **File:** `flow-integration/class-wc-gateway-checkout-com-flow.php` lines 644-655.

**Scenario D: All fallbacks fail**

- Email+amount: No matching pending/failed order.
- `create_minimal_order_from_payment_details()` fails (see 2.4).
- `wc_create_order()` fails (e.g. cart empty).
- **Result:** `wp_die()` (lines 3840, 3844, 3882, 3884, 4105-4107) — user sees error, but **payment has already succeeded** at Checkout.com.

---

### 2.3 `create_minimal_order_from_payment_details()` — When It Can Fail

**File:** `flow-integration/class-wc-gateway-checkout-com-flow.php` (lines 4884-5119)

| Failure Point | Line(s) | Condition |
|---------------|---------|-----------|
| `wc_create_order()` fails | 4918-4923 | Returns `null` or `WP_Error` |
| Exception during order setup | 4887 (try block) | Any exception in try block |
| Missing `payment_details` | Caller | Caller checks `!empty($payment_details)` before calling |

**Note:** The function does **not** require customer email. It sets `set_billing_email` only if `$customer_email` is not empty (line 4927). Order creation can succeed with empty billing email.

**Real failure:** `wc_create_order()` can fail due to:
- Database errors
- Plugin conflicts
- Invalid `customer_id` (e.g. user deleted)
- WooCommerce/filter preventing order creation

---

## 3. Webhook Handler Analysis

### 3.1 How Webhook Finds the Order

**File:** `includes/class-wc-checkout-com-webhook.php`

**All webhook handlers** (authorize_payment, capture_payment, card_verified, etc.) use:

```php
$order_id = isset($webhook_data->metadata->order_id) ? $webhook_data->metadata->order_id : null;
$order = self::get_wc_order($order_id);
```

**`get_wc_order()`** (lines 848-884):
1. `wc_get_order($order_id)` — direct load
2. `wc_get_orders(['order_number' => $order_id])` — fallback by order number

**Flow gateway pre-processing** (`class-wc-gateway-checkout-com-flow.php` lines 5589-5625):
- Method 1: `metadata->order_id` (usually null for Flow — see Section 4)
- Method 2: `_cko_payment_session_id` + `_cko_payment_id` meta query

If no order is found, `$order` remains null.

---

### 3.2 When Webhook Arrives and Order Doesn't Exist

**File:** `includes/class-wc-checkout-com-webhook.php`

| Handler | Lines | Behavior |
|---------|-------|----------|
| authorize_payment | 59-68, 76-84 | Returns `false` if order_id empty/invalid or order not found |
| capture_payment | 355-364, 367-377 | Same |
| card_verified | 275-284, 291-301 | Same |
| All others | Similar | Same |

**Result:** Webhook returns `false` → Checkout.com may retry. The plugin also **queues** the webhook for `payment_approved` and `payment_captured` (lines 5960-6057).

---

### 3.3 Webhook Queue Mechanism

**File:** `includes/class-wc-checkout-com-webhook-queue.php`

**When webhook fails:** `save_pending_webhook($payment_id, $order_id, $payment_session_id, $webhook_type, $data)` is called.

**When queue is processed:** `process_pending_webhooks_for_order($order)` is called with an **existing order**.

**Lookup:** `get_pending_webhooks_for_order($order)` queries by:
- `payment_id` (from order meta `_cko_payment_id`)
- `payment_session_id` (from order meta `_cko_payment_session_id`)
- `order_id` (from order)

**Critical gap:** The queue is only processed when `process_pending_webhooks_for_order()` is invoked — which happens from:
- `handle_3ds_return()` after order is found/created (line 4076)
- `process_payment()` after order is processed (line 3266)

**If no order is ever created:** Queued webhooks are **never** processed. The payment succeeded at Checkout.com, but no order exists and the queue is never drained.

---

## 4. Payment Session Creation — order_id in Metadata and success_url

### 4.1 Is order_id in Payment Metadata?

**File:** `flow-integration/assets/js/modules/flow-checkout-data.js` (lines 118-147)

```javascript
let metadata = { udf5: cko_flow_vars.udf5 };
// ...
return {
    // ...
    orderId: orderId,  // Returned separately, NOT in metadata
    metadata: metadata,  // Only udf5
};
```

**File:** `flow-integration/assets/js/payment-session.js` (line 471)

```javascript
metadata: metadata,  // No order_id added
```

**Result:** `order_id` is **never** sent in payment metadata to Checkout.com. Webhooks receive `metadata->order_id` = null for Flow payments.

---

### 4.2 Is order_id in success_url?

**Initial session (page load):** Lines 564-616

- Regular checkout: `formOrderId` and `sessionOrderId` are empty → `success_url` has no `order_id`.
- Order-pay: `orderId` and `orderKey` from URL → included if both present.

**Session update (field change):** Lines 680-722

- Uses `formOrderId` or `sessionOrderId` if available.
- If order was created after session creation, update may include it — but the **initial** session used for 3DS redirect is created at load, before the order exists.

**Before submit:** There is **no** session update or new session creation after `createOrderBeforePayment()`. The Flow component submits with the **original** session.

---

## 5. Complete Scenario List: Payment Succeeds, No Order

### Scenario 1: success_url Has No order_id (Timing Gap)

**Path:** Regular checkout → Place Order → createOrderBeforePayment() → flowComponent.submit() → 3DS → redirect

**Root cause:** Session created at page load; order created just before submit; session not updated. success_url has no order_id.

**Result:** Redirect to `/?wc-api=wc_checkoutcom_flow_process&cko-payment-id=xxx` (no order_id).

**Order lookup:** Depends on payment_session_id in URL and/or order meta. If payment_session_id not in URL or order has no `_cko_payment_session_id`, lookup can fail.

**Files:** payment-session.js 564-616, 4311-4336; class-wc-gateway-checkout-com-flow.php 3569-3841.

---

### Scenario 2: Payment Session ID Not Saved to Order

**Path:** Order created via createOrderBeforePayment(); `cko-flow-payment-session-id` not in form POST when `store_payment_session_id_in_order` runs.

**Root cause:** Hidden field not populated before order creation, or form serialization excludes it.

**Result:** Order has no `_cko_payment_session_id`. handle_3ds_return cannot find order by session ID. Falls back to payment_id, email+amount, or create_minimal_order.

**Files:** class-wc-gateway-checkout-com-flow.php 644-655, 637-673.

---

### Scenario 3: Payment Session ID Missing from 3DS Redirect URL

**Path:** Checkout.com appends `cko-payment-id` to success_url but may not append `cko-payment-session-id` in all configurations.

**Root cause:** Checkout.com behavior or plugin configuration.

**Result:** handle_3ds_return has no payment_session_id from URL. Must get from payment_details metadata — which requires successful API fetch. If fetch fails or metadata missing, session-based lookup fails.

**Files:** class-wc-gateway-checkout-com-flow.php 3607-3615.

---

### Scenario 4: Payment Details API Fetch Fails

**Path:** handle_3ds_return needs payment_details for session ID, email+amount fallback, create_minimal_order.

**Root cause:** Checkout.com API error, network failure, invalid credentials.

**Result:** `wp_die()` at line 3600. Payment succeeded at Checkout.com; user sees error; no order.

**Files:** class-wc-gateway-checkout-com-flow.php 3594-3600.

---

### Scenario 5: create_minimal_order_from_payment_details() Fails

**Path:** All other lookups fail; cart empty; create_minimal_order_from_payment_details() called.

**Root cause:** `wc_create_order()` returns null or WP_Error (DB error, plugin conflict, etc.).

**Result:** `wp_die()` at lines 3840 or 3882. Payment succeeded; no order.

**Files:** class-wc-gateway-checkout-com-flow.php 3828-3841, 3870-3884, 4884-4923.

---

### Scenario 6: wc_create_order() from Cart Fails

**Path:** Cart has items; create_minimal_order not used; wc_create_order() called directly.

**Root cause:** Same as Scenario 5.

**Result:** `wp_die()` at 3882 or 3884. Payment succeeded; no order.

**Files:** class-wc-gateway-checkout-com-flow.php 3860-3884.

---

### Scenario 7: Exception During Order Lookup

**Path:** Exception in handle_3ds_return order lookup block (e.g. in meta_query, date logic).

**Root cause:** Bug, bad data, or plugin conflict.

**Result:** Catch block (lines 4091-4107) calls create_minimal_order_from_payment_details(). If that fails, `wp_die()`. Payment succeeded; no order.

**Files:** class-wc-gateway-checkout-com-flow.php 4091-4107.

---

### Scenario 8: Webhook Arrives Before Order Exists, order_id Always Null

**Path:** Webhook (payment_approved/payment_captured) arrives; metadata has no order_id (Flow never sends it).

**Root cause:** order_id not in payment metadata (flow-checkout-data.js line 120).

**Result:** authorize_payment/capture_payment return false. Webhook queued. Queue is only processed when process_pending_webhooks_for_order() is called with an order. If handle_3ds_return never creates/finds an order, queue is never processed. Payment succeeded; no order; webhook never applied.

**Files:** flow-checkout-data.js 120; class-wc-checkout-com-webhook.php 46, 59-68; class-wc-checkout-com-webhook-queue.php 196-247.

---

### Scenario 9: Order-Pay Page — Order Deleted or Invalid

**Path:** User on order-pay page; order_id from URL; flowComponent.submit() called without createOrderBeforePayment() (order "exists" from URL).

**Root cause:** Order was deleted, or URL tampered with invalid order_id.

**Result:** handle_3ds_return gets order_id from URL; wc_get_order() returns false; `wp_die()` at 3559. Payment may have succeeded at Checkout.com if 3DS completed before return.

**Files:** payment-session.js 4209-4213; class-wc-gateway-checkout-com-flow.php 3552-3560.

---

### Scenario 10: Order Lookup by Email+Amount Fails (Strict Matching)

**Path:** No order_id, no payment_session_id match, no payment_id match. Fallback: pending/failed orders by email and amount.

**Root cause:** No matching order (different amount, different email, outside 7-day window, or order has different payment_session_id).

**Result:** Falls through to create_minimal_order or wc_create_order. If those fail, no order.

**Files:** class-wc-gateway-checkout-com-flow.php 3712-3762.

---

## 6. Summary Table

| # | Scenario | Payment Succeeds | Order Exists | Primary Cause |
|---|----------|------------------|--------------|---------------|
| 1 | success_url no order_id | Yes | No | Session created before order; not updated |
| 2 | payment_session_id not on order | Yes | No | Form POST missing session ID |
| 3 | payment_session_id not in URL | Yes | No | Checkout.com redirect behavior |
| 4 | Payment details API fetch fails | Yes | No | API/network error |
| 5 | create_minimal_order fails | Yes | No | wc_create_order fails |
| 6 | wc_create_order from cart fails | Yes | No | wc_create_order fails |
| 7 | Exception in order lookup | Yes | No | Exception + minimal order fallback fails |
| 8 | Webhook metadata has no order_id | Yes | No | order_id never sent; queue never processed |
| 9 | Order-pay order deleted/invalid | Yes | No | Order missing when return happens |
| 10 | Email+amount fallback finds nothing | Yes | No | No matching order; creation fallbacks fail |

---

## 7. Recommended Fixes (Priority Order)

1. ~~**Update payment session before submit**~~ — **NOT POSSIBLE** - Checkout.com payment session `success_url` is immutable after creation.
2. **Add order_id to payment metadata** — In flow-checkout-data.js, add `metadata.order_id = orderId` when orderId exists. (Requires creating session AFTER order, which changes the flow significantly.)
3. **Ensure payment_session_id is always in form** — Before submit, guarantee hidden field is set and included in POST. **IMPLEMENTED in v5.0.3**
4. **Harden create_minimal_order_from_payment_details** — Better error handling, logging, and fallbacks. **IMPLEMENTED in v5.0.3**
5. **Process queued webhooks by payment_id** — Add a cron or trigger to process queued webhooks when an order is later found by payment_id (e.g. from handle_3ds_return or manual reconciliation).

---

## 8. Fixes Implemented in v5.0.3

### Fix 1: Ensure payment_session_id is Always Available (JavaScript)

**File:** `flow-integration/assets/js/payment-session.js`

In `createOrderBeforePayment()`, added fallback to `window.currentPaymentSessionId` if hidden field is missing:

```javascript
// Get payment session ID - CRITICAL for order lookup after 3DS return.
// Try multiple sources: 1) hidden field, 2) window global, 3) force add field
let paymentSessionId = '';
const paymentSessionIdField = jQuery('input[name="cko-flow-payment-session-id"]');

if (paymentSessionIdField.length > 0 && paymentSessionIdField.val()) {
    paymentSessionId = paymentSessionIdField.val();
} else if (window.currentPaymentSessionId) {
    // Fallback: use window global if hidden field not found
    paymentSessionId = window.currentPaymentSessionId;
    // Also try to add the hidden field for subsequent use
    if (window.ckoAddPaymentSessionIdField) {
        window.ckoAddPaymentSessionIdField();
    }
}
```

### Fix 2: Warning Logging When payment_session_id Missing (PHP)

**File:** `flow-integration/class-wc-gateway-checkout-com-flow.php`

In `ajax_create_order()`, added warning when payment_session_id is empty:

```php
if ( empty( $payment_session_id ) ) {
    WC_Checkoutcom_Utility::logger( '[CREATE ORDER] ⚠️ WARNING: payment_session_id is EMPTY - order lookup after 3DS return will rely on fallback methods (email+amount)' );
}
```

### Fix 3: Save payment_session_id in Minimal Order (PHP)

**File:** `flow-integration/class-wc-gateway-checkout-com-flow.php`

In `create_minimal_order_from_payment_details()`, added saving of `_cko_payment_session_id` from payment metadata:

```php
// Save payment session ID if available in payment details metadata.
if ( isset( $payment_details['metadata']['cko_payment_session_id'] ) ) {
    $payment_session_id = $payment_details['metadata']['cko_payment_session_id'];
    $order->update_meta_data( '_cko_payment_session_id', $payment_session_id );
}
```

This ensures that even minimal orders (created as fallback) can be found by webhooks using `_cko_payment_session_id`.
