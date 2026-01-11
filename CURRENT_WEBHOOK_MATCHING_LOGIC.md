# Current Webhook Matching Logic

## Overview

There are **TWO different webhook handlers** in the codebase:

1. **Standard Webhook Handler** (`includes/class-wc-checkout-com-webhook.php`)
   - Used for Cards, PayPal, Google Pay, Apple Pay, etc.
   - Simpler matching logic

2. **Flow Gateway Webhook Handler** (`flow-integration/class-wc-gateway-checkout-com-flow.php`)
   - Used specifically for Flow payments
   - More comprehensive matching logic with multiple fallback methods

---

## 1. Standard Webhook Handler Matching Logic

**Location**: `includes/class-wc-checkout-com-webhook.php` → `get_wc_order()` method

### Matching Methods (in order):

#### Method 1: Direct Order ID Lookup
```php
$order = wc_get_order( $order_id );
```
- **Source**: `$webhook_data->metadata->order_id`
- **How**: Direct WooCommerce order lookup by ID
- **Success Rate**: HIGH (if order_id is present and correct)

#### Method 2: Order Number Lookup
```php
$orders = wc_get_orders([
    'order_number' => $order_id,
]);
```
- **Source**: Same `order_id` from metadata (but treated as order number)
- **How**: Searches by order number (works with Sequential Order Numbers plugins)
- **Success Rate**: MEDIUM (depends on order number format)

### Current Flow:
```
Webhook Arrives
    ↓
Extract order_id from $webhook_data->metadata->order_id
    ↓
Try wc_get_order($order_id)
    ↓
Found? ──NO──→ Try wc_get_orders(['order_number' => $order_id])
    │                      ↓
    │                  Found? ──NO──→ Return false (webhook fails)
    │                      │
    └──YES──→ Process webhook
```

### Issues:
- **No payment_id matching**: Doesn't try to match by payment ID if order_id fails
- **No fallback**: If order not found, webhook fails and Checkout.com retries
- **Single identifier**: Only uses order_id from metadata

---

## 2. Flow Gateway Webhook Handler Matching Logic

**Location**: `flow-integration/class-wc-gateway-checkout-com-flow.php` → `webhook_handler()` method

### Matching Methods (in order):

#### Method 1: Order ID from Metadata
```php
if (!empty($data->data->metadata->order_id)) {
    $order = wc_get_order($data->data->metadata->order_id);
}
```
- **Source**: `$data->data->metadata->order_id`
- **Priority**: Highest
- **Success Rate**: HIGH

#### Method 2: Payment Session ID
```php
$orders = wc_get_orders([
    'meta_key' => '_cko_payment_session_id',
    'meta_value' => $data->data->metadata->cko_payment_session_id,
]);
```
- **Source**: `$data->data->metadata->cko_payment_session_id`
- **Meta Field**: `_cko_payment_session_id`
- **Success Rate**: HIGH (unique identifier)
- **Note**: Sets `order_id` in metadata after finding order

#### Method 3: Order Reference (Order Number)
Multiple sub-methods:

**3a. Direct Lookup:**
```php
$order = wc_get_order($data->data->reference);
```

**3b. Custom Reference Meta:**
```php
$orders = wc_get_orders([
    'meta_key' => '_cko_order_reference',
    'meta_value' => $data->data->reference,
]);
```

**3c. Sequential Order Numbers Plugin:**
```php
$orders = wc_get_orders([
    'meta_key' => '_order_number',
    'meta_value' => $data->data->reference,
]);
```

**3d. Post Name Lookup:**
```php
// Direct SQL query for post_name
$post_id = $wpdb->get_var(...);
```

- **Source**: `$data->data->reference`
- **Success Rate**: MEDIUM (depends on order number format)
- **Note**: Sets `order_id` in metadata after finding order

#### Method 4: Payment ID
```php
$orders = wc_get_orders([
    'meta_key' => '_cko_flow_payment_id',
    'meta_value' => $data->data->id,
]);
```
- **Source**: `$data->data->id` (payment ID from webhook)
- **Meta Field**: `_cko_flow_payment_id` or `_cko_payment_id`
- **Success Rate**: HIGH (unique per payment)
- **Note**: Sets `order_id` in metadata after finding order
- **Issue**: ⚠️ **No status filter** - could match completed orders

### Current Flow:
```
Webhook Arrives
    ↓
Method 1: Try order_id from metadata
    ↓
Found? ──NO──→ Method 2: Try payment_session_id
    │                      ↓
    │                  Found? ──NO──→ Method 3: Try reference (order number)
    │                      │                      ↓
    │                      │                  Found? ──NO──→ Method 4: Try payment_id
    │                      │                      │                      ↓
    │                      │                      │                  Found? ──NO──→ Return 404 (webhook fails)
    │                      │                      │                      │
    └──YES──→ Process webhook          └──YES──→ Process webhook
```

### After Order Found:
- Sets `order_id` in `$data->data->metadata` for processing functions
- Validates payment_id match (but doesn't fail if mismatch)
- If no payment_id in order, sets it from webhook

### Critical Issue:
**Line 2954-2958**: If no order found after all methods, returns **404** and **dies**:
```php
if (! $order) {
    WC_Checkoutcom_Utility::logger('Flow webhook: CRITICAL - No order found...');
    http_response_code(404);
    wp_die('Order not found', 'Webhook Error', array('response' => 404));
}
```

This causes Checkout.com to retry the webhook.

---

## Key Differences

| Aspect | Standard Handler | Flow Handler |
|--------|-----------------|--------------|
| **Matching Methods** | 2 methods | 4+ methods |
| **Payment ID Matching** | ❌ No | ✅ Yes (Method 4) |
| **Payment Session ID** | ❌ No | ✅ Yes (Method 2) |
| **Order Reference** | ✅ Basic | ✅ Comprehensive (4 sub-methods) |
| **Fallback Strategy** | ❌ Fails immediately | ✅ Multiple fallbacks |
| **On Failure** | Returns `false` | Returns 404 + dies |

---

## Problems with Current Logic

### 1. Race Condition Issue
- **Problem**: Webhook arrives before order is fully created/saved
- **Current Behavior**: 
  - Standard handler: Returns `false` → Checkout.com retries
  - Flow handler: Returns 404 → Checkout.com retries
- **Impact**: Order stays in wrong status for minutes until retry succeeds

### 2. No Payment ID Matching in Standard Handler
- **Problem**: Standard handler doesn't try payment_id if order_id fails
- **Impact**: More webhook failures for non-Flow payments

### 3. Payment ID Matching Happens Too Late
- **Problem**: Payment ID matching is Method 4 (last resort)
- **Impact**: If order_id is wrong/missing, goes through 3 methods before trying payment_id

### 4. No Queuing Mechanism
- **Problem**: If order not found, webhook fails immediately
- **Impact**: Checkout.com retries, causing delays

---

## What Happens When Order Not Found

### Standard Handler (`authorize_payment()`, `capture_payment()`):
```php
if (! $order) {
    WC_Checkoutcom_Utility::logger('WEBHOOK PROCESS: ERROR - Order not found...');
    return false;  // Webhook fails
}
```
- **HTTP Response**: 400 (Bad Request)
- **Checkout.com Action**: Retries webhook after a few minutes
- **Order Status**: Stays incorrect until retry succeeds

### Flow Handler:
```php
if (! $order) {
    WC_Checkoutcom_Utility::logger('Flow webhook: CRITICAL - No order found...');
    http_response_code(404);
    wp_die('Order not found', 'Webhook Error', array('response' => 404));
}
```
- **HTTP Response**: 404 (Not Found)
- **Checkout.com Action**: Retries webhook after a few minutes
- **Order Status**: Stays incorrect until retry succeeds

---

## Proposed Solution Impact

The webhook queue system will:

1. **Intercept before failure**: When order not found, save webhook to queue instead of failing
2. **Use payment_id as primary**: Match by payment_id first (most reliable)
3. **Process on order creation**: When order is created, check queue and process pending webhooks
4. **Prevent retries**: Return success when webhook is queued (prevents Checkout.com retry)

This solves the race condition by ensuring webhooks are processed as soon as orders are ready, rather than waiting for retries.




