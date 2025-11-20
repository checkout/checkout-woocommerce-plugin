# Failed Order Refresh Prevention & Payment ID in Order Notes - Analysis

## Part 1: Option 1 Implementation - Exclude Failed Orders

### Current Behavior

**Location:** `woocommerce-gateway-checkout-com.php` (line 490)

**Current Code:**
```php
} elseif ( in_array( $existing_order_status, array( 'pending', 'failed' ), true ) ) {
    // Refreshes both pending AND failed orders
```

**Problem:**
- Failed orders are refreshed/reused when customer retries payment
- Merchant loses historical record of failed orders
- Creates operational issues (can't track failed attempts)

### Proposed Change

**Change Line 490:**
```php
// FROM:
} elseif ( in_array( $existing_order_status, array( 'pending', 'failed' ), true ) ) {

// TO:
} elseif ( 'pending' === $existing_order_status ) {
```

**Impact:**
- ✅ Failed orders will NEVER be refreshed
- ✅ Each retry creates a NEW order
- ✅ Failed orders remain as historical records
- ✅ Pending orders still get refreshed (prevents duplicates during same session)

### Code Context

**File:** `woocommerce-gateway-checkout-com.php`
**Lines:** 490-550 (refresh logic)

**Current Flow:**
1. Line 487-489: Check if order has payment IDs → Don't reuse ✅
2. Line 490: Check if status is pending OR failed → Reuse ❌ (needs change)
3. Line 494-550: Refresh order with new cart items

**After Change:**
1. Line 487-489: Check if order has payment IDs → Don't reuse ✅
2. Line 490: Check if status is pending ONLY → Reuse ✅
3. Failed orders: Skip refresh → Create new order ✅

---

## Part 2: Adding Payment ID to Order Notes

### Current Order Notes Analysis

#### 1. Webhook: Payment Authorized

**Location:** `includes/class-wc-checkout-com-webhook.php` (line 113)

**Current Note:**
```php
$message = 'Webhook received from checkout.com. Payment Authorized';
$order->add_order_note( $message );
```

**Available Data:**
- `$webhook_data->id` - Payment ID ✅ Available
- `$webhook_data->action_id` - Action ID ✅ Available (already used as transaction ID)

**Proposed Change:**
```php
$message = sprintf( 
    'Webhook received from checkout.com. Payment Authorized - Payment ID: %s', 
    $webhook_data->id 
);
```

**Also Check:**
- Line 101: Duplicate authorization note (already authorized case)
  - Should also include payment ID

---

#### 2. Webhook: Payment Captured

**Location:** `includes/class-wc-checkout-com-webhook.php` (line 297)

**Current Note:**
```php
$order_message = sprintf( 
    esc_html__( 'Checkout.com Payment Captured - Action ID : %s', 'checkout-com-unified-payments-api' ), 
    $action_id 
);
```

**Available Data:**
- `$webhook_data->id` - Payment ID ✅ Available
- `$action_id` - Action ID ✅ Already included

**Proposed Change:**
```php
$order_message = sprintf( 
    esc_html__( 'Checkout.com Payment Captured - Payment ID: %s, Action ID: %s', 'checkout-com-unified-payments-api' ), 
    $webhook_data->id,
    $action_id 
);
```

**Also Check:**
- Line 268: Duplicate capture note (already captured case)
  - Should also include payment ID
- Line 272: Initial capture webhook received note
  - Should include payment ID

---

#### 3. Webhook: Card Verified

**Location:** `includes/class-wc-checkout-com-webhook.php` (line 178)

**Current Note:**
```php
$order->add_order_note( __( 'Checkout.com Card verified webhook received', 'checkout-com-unified-payments-api' ) );
```

**Available Data:**
- `$webhook_data->id` - Payment ID ✅ Available
- `$action_id` - Action ID ✅ Available

**Proposed Change:**
```php
$order->add_order_note( 
    sprintf( 
        __( 'Checkout.com Card verified webhook received - Payment ID: %s, Action ID: %s', 'checkout-com-unified-payments-api' ), 
        $webhook_data->id,
        $action_id 
    ) 
);
```

---

#### 4. Flow: 3DS Return Authorization

**Location:** `flow-integration/class-wc-gateway-checkout-com-flow.php` (line 1412)

**Current Note:**
```php
$message = sprintf( 
    esc_html__( 'Checkout.com Payment Authorised - using FLOW (3DS return): %s', 'checkout-com-unified-payments-api' ), 
    $flow_payment_type 
);
```

**Available Data:**
- `$flow_payment_id` - Payment ID ✅ Available
- `$result['action_id']` - Action ID ✅ Available (from payment details)

**Proposed Change:**
```php
$message = sprintf( 
    esc_html__( 'Checkout.com Payment Authorised - using FLOW (3DS return): %s - Payment ID: %s', 'checkout-com-unified-payments-api' ), 
    $flow_payment_type,
    $flow_payment_id 
);
```

**Also Check:**
- Line 1417: Flagged payment note (3DS return)
  - Already includes Payment ID ✅
- Line 1421: Already captured case (3DS return)
  - Should include Payment ID

---

#### 5. Flow: Normal Payment Authorization

**Location:** `flow-integration/class-wc-gateway-checkout-com-flow.php` (line 1640)

**Current Note:**
```php
$message = sprintf( 
    esc_html__( 'Checkout.com Payment Authorised - using FLOW : %s', 'checkout-com-unified-payments-api' ), 
    $flow_payment_type 
);
```

**Available Data:**
- `$flow_pay_id` - Payment ID ✅ Available
- Action ID: Not directly available (would need to fetch from payment details)

**Proposed Change:**
```php
$message = sprintf( 
    esc_html__( 'Checkout.com Payment Authorised - using FLOW : %s - Payment ID: %s', 'checkout-com-unified-payments-api' ), 
    $flow_payment_type,
    $flow_pay_id 
);
```

---

#### 6. Flow: Saved Card Payment Authorization

**Location:** `flow-integration/class-wc-gateway-checkout-com-flow.php` (line 1515)

**Current Note:**
```php
$message = sprintf( 
    esc_html__( 'Checkout.com Payment Authorised - Action ID : %s', 'checkout-com-unified-payments-api' ), 
    $result['action_id'] 
);
```

**Available Data:**
- `$result['id']` - Payment ID ✅ Available
- `$result['action_id']` - Action ID ✅ Already included

**Proposed Change:**
```php
$message = sprintf( 
    esc_html__( 'Checkout.com Payment Authorised - Payment ID: %s, Action ID: %s', 'checkout-com-unified-payments-api' ), 
    $result['id'],
    $result['action_id'] 
);
```

**Also Check:**
- Line 1523: Flagged payment note (saved card)
  - Should include Payment ID
- Line 1528: Already captured case (saved card)
  - Should include Payment ID

---

#### 7. Webhook: Payment Voided

**Location:** `includes/class-wc-checkout-com-webhook.php` (line 450)

**Current Note:**
```php
$order_message = sprintf( 
    esc_html__( 'Checkout.com Payment Voided - Action ID : %s', 'checkout-com-unified-payments-api' ), 
    $action_id 
);
```

**Available Data:**
- `$webhook_data->id` - Payment ID ✅ Available
- `$action_id` - Action ID ✅ Already included

**Proposed Change:**
```php
$order_message = sprintf( 
    esc_html__( 'Checkout.com Payment Voided - Payment ID: %s, Action ID: %s', 'checkout-com-unified-payments-api' ), 
    $webhook_data->id,
    $action_id 
);
```

**Also Check:**
- Line 437: Initial void webhook received note
  - Should include payment ID

---

#### 8. Webhook: Payment Refunded

**Location:** `includes/class-wc-checkout-com-webhook.php` (line 583)

**Current Note:**
```php
$order_message = sprintf( 
    esc_html__( 'Checkout.com Payment Refunded - Action ID : %s', 'checkout-com-unified-payments-api' ), 
    $action_id 
);
```

**Available Data:**
- `$webhook_data->id` - Payment ID ✅ Available
- `$action_id` - Action ID ✅ Already included

**Proposed Change:**
```php
$order_message = sprintf( 
    esc_html__( 'Checkout.com Payment Refunded - Payment ID: %s, Action ID: %s', 'checkout-com-unified-payments-api' ), 
    $webhook_data->id,
    $action_id 
);
```

**Also Check:**
- Line 542: Initial refund webhook received note
  - Should include payment ID

---

#### 9. Flow: 3DS Redirect Waiting

**Location:** `flow-integration/class-wc-gateway-checkout-com-flow.php` (line 1488)

**Current Note:**
```php
$order->add_order_note(
    sprintf(
        esc_html__( 'Checkout.com 3d Redirect waiting. URL : %s', 'checkout-com-unified-payments-api' ),
        $result['3d']
    )
);
```

**Available Data:**
- `$result['id']` - Payment ID ✅ Available
- No action ID yet (payment not completed)

**Proposed Change:**
```php
$order->add_order_note(
    sprintf(
        esc_html__( 'Checkout.com 3d Redirect waiting - Payment ID: %s, URL: %s', 'checkout-com-unified-payments-api' ),
        $result['id'],
        $result['3d']
    )
);
```

---

## Summary of Changes Needed

### Change 1: Prevent Failed Order Refresh
- **File:** `woocommerce-gateway-checkout-com.php`
- **Line:** 490
- **Change:** Remove `'failed'` from status check array

### Change 2: Add Payment ID to Order Notes

**Webhook Notes (includes/class-wc-checkout-com-webhook.php):**
1. Line 97, 101, 113: Payment Authorized
2. Line 253, 268, 272, 288: Payment Captured
3. Line 178: Card Verified
4. Line 437, 450: Payment Voided
5. Line 542, 583: Payment Refunded

**Flow Notes (flow-integration/class-wc-gateway-checkout-com-flow.php):**
6. Line 1412, 1417, 1421: 3DS Return Authorization
7. Line 1515, 1523, 1528: Saved Card Authorization
8. Line 1640: Normal Flow Authorization
9. Line 1488: 3DS Redirect Waiting

**Total:** ~15 order note locations need payment ID added

---

## Benefits

### Failed Order Prevention:
- ✅ Failed orders remain as historical records
- ✅ Each retry creates new order (better tracking)
- ✅ No operational confusion
- ✅ Clear audit trail

### Payment ID in Notes:
- ✅ Easy to find payment ID in order notes
- ✅ Better debugging and support
- ✅ Consistent format across all notes
- ✅ Helps with payment reconciliation

---

## Testing Considerations

1. **Failed Order Test:**
   - Create order → Fail payment → Retry payment
   - Verify: New order created, old failed order unchanged

2. **Pending Order Test:**
   - Create order → Abandon → Return later
   - Verify: Same order refreshed (not new order)

3. **Order Notes Test:**
   - Check all order notes include payment ID
   - Verify format is consistent
   - Check translations work correctly

---

## Implementation Notes

1. **Translation Strings:**
   - All order notes use `esc_html__()` or `sprintf()` with translation strings
   - Need to update translation strings to include payment ID placeholder

2. **Consistency:**
   - Some notes already include payment ID (e.g., flagged payments)
   - Make format consistent: "Payment ID: {id}, Action ID: {action_id}"

3. **Backward Compatibility:**
   - Old orders won't have payment ID in notes (not an issue)
   - New orders will have payment ID (improvement)

