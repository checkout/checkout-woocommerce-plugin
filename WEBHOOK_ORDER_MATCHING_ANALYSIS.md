# Webhook Order Matching Analysis for FLOW Payments

## Current Webhook Order Matching Flow

**Location**: `flow-integration/class-wc-gateway-checkout-com-flow.php` → `webhook_handler()` method (line 3141)

### Order Lookup Methods (Priority Order)

1. **Method 1: Order ID from Metadata** (Line 3286)
   - Checks: `$data->data->metadata->order_id`
   - **Risk**: LOW - Direct order ID lookup, most reliable
   - **Issue**: None - this is the safest method

2. **Method 2: Payment Session ID** (Line 3296)
   - Checks: `_cko_payment_session_id` meta field
   - **Risk**: LOW - Unique identifier set during payment session creation
   - **Issue**: None - reliable identifier

3. **Method 3: Order Reference** (Line 3357)
   - Checks: `$data->data->reference` → matches `_cko_order_reference` or `_order_number`
   - **Risk**: MEDIUM - Order numbers might not be unique (Sequential Order Numbers plugin)
   - **Issue**: Could match wrong order if reference is not unique

4. **Method 4: Session + Cart Hash COMBINED** (Line 3455)
   - Checks: `_cko_session_cart_id` = `customer_{user_id}_{cart_hash}` OR `session_{session_key}_{cart_hash}`
   - **Risk**: LOW - Unique identifier combining user session + cart contents
   - **Issue**: ⚠️ **ONLY CHECKS LOGGED-IN USERS** - Missing guest user lookup!
     - Line 3470: Only checks `customer_{user_id}_{cart_hash}`
     - Does NOT check `session_{session_key}_{cart_hash}` for guest users
     - **Impact**: Guest orders might not be found by this method

5. **Method 5: Payment ID** (Line 3510)
   - Checks: `_cko_flow_payment_id` or `_cko_payment_id` meta fields
   - **Risk**: MEDIUM - Payment IDs should be unique, but...
   - **Issue**: ⚠️ **NO STATUS FILTER** - Could match completed/processing orders
     - Line 3517-3534: No `'status' => array('pending', 'failed')` filter
     - **Impact**: Could update wrong order if payment ID somehow matches completed order

6. **Method 6: Email + Amount Match** (Line 3596)
   - Checks: Billing email + payment amount (in cents)
   - **Risk**: HIGH - Multiple orders can have same email + amount
   - **Issue**: ⚠️ **COULD MATCH WRONG ORDER**
     - Line 3605-3611: Filters by `pending`, `on-hold`, `processing` (good)
     - Line 3618-3636: Takes FIRST match where amount matches
     - **Impact**: If user has multiple pending orders with same amount, could match wrong one

7. **Method 7: Create New Order** (Line 3639)
   - Creates order from webhook payment details if none found
   - **Risk**: LOW - Only happens if no order found
   - **Issue**: None - this is a fallback

---

## ⚠️ CRITICAL ISSUES IDENTIFIED

### Issue 1: No Status Check Before Updating Orders

**Problem**: Webhook handler does NOT check order status before updating orders.

**Current Behavior**:
- Webhook can update orders with ANY status (`completed`, `processing`, `cancelled`, etc.)
- No protection against updating completed orders (same issue we fixed in `handle_3ds_return`)

**Location**: After order is found (line 3558+), no status check before processing

**Impact**: 
- Could update completed orders
- Could change order status incorrectly
- Could cause data integrity issues

**Example Scenario**:
1. Order #100 is completed with payment `pay_abc123`
2. New payment `pay_xyz789` arrives via webhook
3. Webhook matches order #100 by payment session ID (if somehow reused)
4. Order #100 gets updated with new payment ID and status changed

---

### Issue 2: Payment ID Mismatch Handling

**Problem**: If order has different payment ID than webhook, processing continues anyway.

**Current Behavior** (Line 3590-3594):
```php
} elseif ( $order && $payment_id !== $data->data->id ) {
    // Payment ID exists but doesn't match - log but don't fail for Flow payments
    WC_Checkoutcom_Utility::logger( 'Flow webhook: Payment ID mismatch - Order: ' . $payment_id . ', Webhook: ' . $data->data->id . ' - Continuing processing' );
}
```

**Impact**: 
- Wrong order could be updated if matched by other methods (email+amount, reference, etc.)
- No validation that payment ID matches before processing

**Example Scenario**:
1. Order #100 has payment `pay_abc123`
2. Webhook arrives for payment `pay_xyz789`
3. Order #100 matched by email+amount (Method 6)
4. Payment IDs don't match, but processing continues anyway
5. Order #100 gets updated with wrong payment ID

---

### Issue 3: Guest User Session+Cart Lookup Missing

**Problem**: Method 4 only checks logged-in users, not guest users.

**Current Behavior** (Line 3465-3507):
- Only checks: `customer_{user_id}_{cart_hash}` (logged-in users)
- Does NOT check: `session_{session_key}_{cart_hash}` (guest users)

**Impact**: 
- Guest orders might not be found by Method 4
- Falls back to less reliable methods (email+amount, payment ID)

---

### Issue 4: Email + Amount Matching Risk

**Problem**: Multiple orders can have same email + amount.

**Current Behavior** (Line 3605-3636):
- Filters by status: `pending`, `on-hold`, `processing` ✅ (good)
- Takes FIRST match where amount matches
- No additional validation

**Impact**: 
- If user has multiple pending orders with same amount, could match wrong one
- Example: User places 2 orders for $50 each, webhook could match wrong order

---

### Issue 5: Payment ID Lookup Without Status Filter

**Problem**: Method 5 doesn't filter by order status.

**Current Behavior** (Line 3517-3534):
- Searches ALL orders with matching payment ID
- No status filter
- Could match completed/processing orders

**Impact**: 
- Could update completed orders if payment ID somehow matches
- Should only match `pending` or `failed` orders

---

## 🔒 RECOMMENDED FIXES

### Fix 1: Add Status Check Before Processing

Add status validation after order is found:

```php
// After order is found (around line 3558)
if ( $order ) {
    $order_status = $order->get_status();
    
    // CRITICAL: Only process pending or failed orders
    if ( ! in_array( $order_status, array( 'pending', 'failed' ), true ) ) {
        WC_Checkoutcom_Utility::logger( 'WEBHOOK: Order status is ' . $order_status . ' (not pending/failed) - Cannot update order' );
        WC_Checkoutcom_Utility::logger( 'WEBHOOK: Completed/processing orders must NEVER be updated via webhook' );
        
        // Return success but don't process
        $this->send_response( 200, 'Order already processed' );
        return;
    }
    
    // Continue with payment ID validation...
}
```

---

### Fix 2: Validate Payment ID Match

Add payment ID validation before processing:

```php
// After order is found and status checked
if ( $order && ! empty( $data->data->id ) ) {
    $existing_payment_id = $order->get_meta( '_cko_payment_id' );
    
    // If order has payment ID, it MUST match webhook payment ID
    if ( ! empty( $existing_payment_id ) && $existing_payment_id !== $data->data->id ) {
        WC_Checkoutcom_Utility::logger( 'WEBHOOK: Payment ID mismatch - Order: ' . $existing_payment_id . ', Webhook: ' . $data->data->id );
        WC_Checkoutcom_Utility::logger( 'WEBHOOK: Order has different payment ID - rejecting webhook to prevent wrong order update' );
        
        $this->send_response( 400, 'Payment ID mismatch' );
        return;
    }
}
```

---

### Fix 3: Add Guest User Session+Cart Lookup

Add guest user lookup to Method 4:

```php
// After logged-in user check (around line 3507)
} else {
    // Guest user - try to find by session key + cart hash
    // Note: For webhooks, we don't have session key, so this might not work
    // But we should at least try if cart hash is in metadata
    if ( ! empty( $cart_hash_from_metadata ) ) {
        // For guests, we'd need session key in metadata, which might not be available
        // This is a limitation of webhook matching for guest users
        WC_Checkoutcom_Utility::logger( 'WEBHOOK: Guest user session+cart lookup not possible without session key in metadata' );
    }
}
```

---

### Fix 4: Add Status Filter to Payment ID Lookup

Add status filter to Method 5:

```php
// Method 5: Try payment ID (around line 3517)
$orders = wc_get_orders( array(
    'limit'        => 1,
    'meta_key'     => '_cko_flow_payment_id',
    'meta_value'   => $data->data->id,
    'status'       => array( 'pending', 'failed' ), // ADD THIS
    'return'       => 'objects',
) );
```

---

### Fix 5: Improve Email + Amount Matching

Add additional validation:

```php
// After finding order by email+amount (around line 3621)
if ( $order_amount === $payment_amount ) {
    // ADDITIONAL VALIDATION: Check if order already has payment ID
    $existing_payment_id = $potential_order->get_meta( '_cko_payment_id' );
    
    // If order already has payment ID, it must match webhook payment ID
    if ( ! empty( $existing_payment_id ) && $existing_payment_id !== $data->data->id ) {
        WC_Checkoutcom_Utility::logger( 'WEBHOOK: Email+amount match found but payment ID mismatch - skipping' );
        continue; // Try next order
    }
    
    $order = $potential_order;
    // ... rest of code
}
```

---

## 📊 Risk Assessment Summary

| Method | Risk Level | Can Match Wrong Order? | Status Check? | Payment ID Validation? |
|--------|------------|------------------------|---------------|------------------------|
| Method 1: Order ID | LOW | No | N/A | N/A |
| Method 2: Payment Session ID | LOW | No | ❌ No | ❌ No |
| Method 3: Order Reference | MEDIUM | Possible | ❌ No | ❌ No |
| Method 4: Session+Cart | LOW | No (but incomplete) | ❌ No | ❌ No |
| Method 5: Payment ID | MEDIUM | Possible | ❌ No | ❌ No |
| Method 6: Email+Amount | HIGH | Yes | ✅ Yes | ❌ No |
| Method 7: Create New | LOW | N/A | N/A | N/A |

---

## ✅ Recommended Implementation Priority

1. **HIGH PRIORITY**: Add status check before processing (Fix 1)
2. **HIGH PRIORITY**: Add payment ID validation (Fix 2)
3. **MEDIUM PRIORITY**: Add status filter to payment ID lookup (Fix 4)
4. **MEDIUM PRIORITY**: Improve email+amount matching (Fix 5)
5. **LOW PRIORITY**: Add guest user session lookup (Fix 3) - might not be feasible without session key in metadata

---

## 🎯 Conclusion

**YES, there IS a possibility webhook can update incorrect orders** due to:

1. **No status check** - Can update completed/processing orders
2. **Payment ID mismatch ignored** - Continues processing even if payment IDs don't match
3. **Email+amount matching** - Can match wrong order if multiple orders exist
4. **No status filter on payment ID lookup** - Could match completed orders

**Recommended Action**: Implement Fixes 1, 2, and 4 immediately to prevent wrong order updates.








