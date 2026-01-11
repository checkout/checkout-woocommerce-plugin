# Webhook Queue System - Complete End-to-End Explanation

## The Problem We're Solving

### Current Situation (Without Queue)

```
Timeline:
T0: Customer clicks "Place Order"
T1: Order creation starts (takes 2-5 seconds)
T2: Webhook arrives from Checkout.com (very fast, < 1 second)
T3: Webhook tries to find order → FAILS (order not created yet)
T4: Webhook returns false/404 → Checkout.com schedules retry
T5: Order creation completes (order saved to database)
T6: Order status = "pending" (incorrect status)
T7: After 2-5 minutes, Checkout.com retries webhook
T8: Webhook finds order → Updates status to "on-hold" or "processing"
```

**Problem**: Order stays in wrong status for 2-5 minutes until webhook retry succeeds.

---

## The Solution: Webhook Queue System

### New Flow (With Queue)

```
Timeline:
T0: Customer clicks "Place Order"
T1: Order creation starts (takes 2-5 seconds)
T2: Webhook arrives from Checkout.com (very fast, < 1 second)
T3: Webhook tries to find order → FAILS (order not created yet)
T4: Webhook processing returns FALSE
T5: System extracts identifiers and saves webhook to queue table
T6: System returns TRUE (prevents Checkout.com retry)
T7: Order creation completes (order saved to database)
T8: System checks queue table for matching webhooks
T9: System finds queued webhook → Processes it immediately
T10: Order status updated to "on-hold" or "processing" (correct status)
```

**Solution**: Order status is updated immediately when order is created, no waiting for retries.

---

## Complete Flow: Step by Step

### PART 1: Webhook Arrives (Order Not Created Yet)

#### Step 1.1: Webhook Received
```
Checkout.com sends webhook to: /wp-json/ckoplugin/v1/webhook
Event Type: "payment_approved" or "payment_captured"
```

#### Step 1.2: Webhook Handler Processes Request
```php
// Location: flow-integration/class-wc-gateway-checkout-com-flow.php
// Method: webhook_handler()

// Extract webhook data
$event_type = $data->type; // "payment_approved" or "payment_captured"
$webhook_data = $data->data;
```

#### Step 1.3: Try to Find Order (Existing Logic)
```php
// Method 1: Try order_id from metadata
$order = wc_get_order($webhook_data->metadata->order_id);

// Method 2: Try payment_session_id
if (!$order) {
    $orders = wc_get_orders([
        'meta_key' => '_cko_payment_session_id',
        'meta_value' => $webhook_data->metadata->cko_payment_session_id,
    ]);
}

// Method 3: Try reference (order number)
// Method 4: Try payment_id
// ... (all existing matching methods)
```

#### Step 1.4: Order Not Found
```
Result: $order = false (order not created yet)
```

#### Step 1.5: Call Processing Function
```php
// For "payment_approved" event
$response = WC_Checkout_Com_Webhook::authorize_payment($data);

// authorize_payment() tries to find order again
// Still fails → returns FALSE
```

#### Step 1.6: Check Return Value - NEW LOGIC
```php
// NEW CODE ADDED HERE
if (false === $response) {
    // Extract all identifiers from webhook
    $payment_id = $webhook_data->id ?? null;
    $order_id = isset($webhook_data->metadata->order_id) 
        ? $webhook_data->metadata->order_id 
        : null;
    $payment_session_id = isset($webhook_data->metadata->cko_payment_session_id)
        ? $webhook_data->metadata->cko_payment_session_id
        : null;
    
    // Only queue if we have at least payment_id
    if ($payment_id) {
        // Save webhook to queue table
        WC_Checkout_Com_Webhook_Queue::save_pending_webhook(
            $payment_id,
            $order_id,
            $payment_session_id,
            $event_type, // "payment_approved" or "payment_captured"
            $data // Full webhook payload
        );
        
        // Log for debugging
        WC_Checkoutcom_Utility::logger(
            "Webhook queued - Payment ID: {$payment_id}, " .
            "Order ID: " . ($order_id ?? 'NULL') . ", " .
            "Session ID: " . ($payment_session_id ?? 'NULL')
        );
        
        // Return true to prevent Checkout.com retry
        $response = true;
    }
}
```

#### Step 1.7: Webhook Response - CRITICAL
```php
// After queuing, set response to true
$response = true;  // This ensures HTTP 200 is sent

// In webhook_handler():
$http_code = $response ? 200 : 400;  // Will be 200 because $response = true
$this->send_response($http_code, $response ? 'OK' : 'Failed');
// Sends: HTTP 200 OK with message "OK"

// Checkout.com receives HTTP 200 OK
// Checkout.com thinks webhook was processed successfully
// Checkout.com will NOT retry (no retry scheduled)
```

#### Step 1.8: Database State
```
Table: wp_cko_pending_webhooks
┌────┬─────────────┬──────────┬─────────────────────┬──────────────────┬─────────────┬──────────────┬─────────────┐
│ id │ payment_id  │ order_id │ payment_session_id  │ webhook_type     │ webhook_data│ created_at   │ processed_at│
├────┼─────────────┼──────────┼─────────────────────┼──────────────────┼─────────────┼──────────────┼─────────────┤
│ 1  │ pay_abc123  │ NULL     │ sess_xyz789         │ payment_approved │ {...JSON...}│ 2025-01-15...│ NULL        │
└────┴─────────────┴──────────┴─────────────────────┴──────────────────┴─────────────┴──────────────┴─────────────┘
```

---

### PART 2: Order Creation Completes

#### Step 2.1: Order Created
```php
// Location: flow-integration/class-wc-gateway-checkout-com-flow.php
// Method: process_payment()

// Order is created
$order = wc_create_order([...]);

// Payment ID saved to order meta
$order->update_meta_data('_cko_payment_id', $flow_payment_id);
$order->update_meta_data('_cko_flow_payment_id', $flow_payment_id);

// Payment Session ID saved (if available)
if (!empty($payment_session_id)) {
    $order->update_meta_data('_cko_payment_session_id', $payment_session_id);
}

// Order saved to database
$order->save();
```

#### Step 2.2: Check for Pending Webhooks - NEW LOGIC
```php
// NEW CODE ADDED HERE (right after $order->save())

// Get identifiers from order
$payment_id = $order->get_meta('_cko_payment_id');
$payment_session_id = $order->get_meta('_cko_payment_session_id');
$order_id = $order->get_id();

// Check queue for matching webhooks
WC_Checkout_Com_Webhook_Queue::process_pending_webhooks_for_order($order);
```

#### Step 2.3: Queue Manager Finds Matching Webhooks
```php
// Location: includes/class-wc-checkout-com-webhook-queue.php
// Method: process_pending_webhooks_for_order($order)

public static function process_pending_webhooks_for_order($order) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cko_pending_webhooks';
    
    $payment_id = $order->get_meta('_cko_payment_id');
    $payment_session_id = $order->get_meta('_cko_payment_session_id');
    $order_id = $order->get_id();
    
    // Find pending webhooks matching this order
    // Priority: payment_id > payment_session_id > order_id
    $pending_webhooks = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$table_name}
        WHERE processed_at IS NULL
        AND (
            payment_id = %s
            OR payment_session_id = %s
            OR order_id = %s
        )
        ORDER BY 
            CASE webhook_type
                WHEN 'payment_approved' THEN 1
                WHEN 'payment_captured' THEN 2
                ELSE 3
            END,
            created_at ASC
    ", $payment_id, $payment_session_id, $order_id));
    
    if (empty($pending_webhooks)) {
        return; // No pending webhooks
    }
    
    // Process each webhook in order (auth first, then capture)
    foreach ($pending_webhooks as $queued_webhook) {
        // Decode webhook data
        $webhook_data = json_decode($queued_webhook->webhook_data);
        
        // Set order_id in metadata (for processing functions)
        if (isset($webhook_data->data->metadata)) {
            $webhook_data->data->metadata->order_id = $order_id;
        } else {
            $webhook_data->data->metadata = (object)['order_id' => $order_id];
        }
        
        // Process webhook based on type
        $success = false;
        if ($queued_webhook->webhook_type === 'payment_approved') {
            $success = WC_Checkout_Com_Webhook::authorize_payment($webhook_data);
        } elseif ($queued_webhook->webhook_type === 'payment_captured') {
            $success = WC_Checkout_Com_Webhook::capture_payment($webhook_data);
        }
        
        if ($success) {
            // Mark as processed
            $wpdb->update(
                $table_name,
                ['processed_at' => current_time('mysql')],
                ['id' => $queued_webhook->id],
                ['%s'],
                ['%d']
            );
            
            WC_Checkoutcom_Utility::logger(
                "Queued webhook processed successfully - " .
                "Webhook ID: {$queued_webhook->id}, " .
                "Order ID: {$order_id}, " .
                "Type: {$queued_webhook->webhook_type}"
            );
        } else {
            // Processing failed - leave in queue for retry
            WC_Checkoutcom_Utility::logger(
                "Queued webhook processing failed - " .
                "Webhook ID: {$queued_webhook->id}, " .
                "Order ID: {$order_id}"
            );
        }
    }
}
```

#### Step 2.4: Webhook Processed Successfully
```
Result: authorize_payment() or capture_payment() succeeds
Order status updated: "pending" → "on-hold" or "processing"
```

#### Step 2.5: Queue Entry Marked as Processed
```
Table: wp_cko_pending_webhooks
┌────┬─────────────┬──────────┬─────────────────────┬──────────────────┬─────────────┬──────────────┬─────────────┐
│ id │ payment_id  │ order_id │ payment_session_id  │ webhook_type     │ webhook_data│ created_at   │ processed_at│
├────┼─────────────┼──────────┼─────────────────────┼──────────────────┼─────────────┼──────────────┼─────────────┤
│ 1  │ pay_abc123  │ 12345    │ sess_xyz789         │ payment_approved │ {...JSON...}│ 2025-01-15...│ 2025-01-15...│
└────┴─────────────┴──────────┴─────────────────────┴──────────────────┴─────────────┴──────────────┴─────────────┘
```

---

## Database Schema

### Table: `wp_cko_pending_webhooks`

```sql
CREATE TABLE wp_cko_pending_webhooks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_id VARCHAR(255) NOT NULL,
    order_id VARCHAR(50) NULL,
    payment_session_id VARCHAR(255) NULL,
    webhook_type VARCHAR(50) NOT NULL,
    webhook_data LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL,
    processed_at DATETIME NULL,
    INDEX idx_payment_id (payment_id),
    INDEX idx_order_id (order_id),
    INDEX idx_payment_session_id (payment_session_id),
    INDEX idx_processed (processed_at),
    INDEX idx_created (created_at)
);
```

**Field Explanations:**
- `id`: Auto-increment primary key
- `payment_id`: Checkout.com payment ID (required, primary matching key)
- `order_id`: WooCommerce order ID (optional, may be NULL for Flow payments)
- `payment_session_id`: Payment session ID (optional, used for Flow payments)
- `webhook_type`: Event type ("payment_approved" or "payment_captured")
- `webhook_data`: Full webhook payload as JSON (stored for later processing)
- `created_at`: When webhook was queued
- `processed_at`: When webhook was processed (NULL = pending, has value = processed)

---

## Matching Logic Explained

### When Order is Created, How Do We Find Matching Webhooks?

#### Priority Order:

1. **Primary Match: Payment ID**
   ```php
   // Match webhook.payment_id = order._cko_payment_id
   // Most reliable - unique per payment
   WHERE payment_id = 'pay_abc123'
   ```

2. **Secondary Match: Payment Session ID**
   ```php
   // Match webhook.payment_session_id = order._cko_payment_session_id
   // Important for Flow payments where order_id may be missing
   WHERE payment_session_id = 'sess_xyz789'
   ```

3. **Tertiary Match: Order ID**
   ```php
   // Match webhook.order_id = order.ID
   // Fallback if payment_id and session_id don't match
   WHERE order_id = '12345'
   ```

### Why This Order?

- **Payment ID** is most reliable (unique, always present)
- **Payment Session ID** is important for Flow (order_id may be missing)
- **Order ID** is fallback (may not be in webhook metadata)

---

## Complete Code Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                    WEBHOOK ARRIVES                               │
└─────────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────────┐
│  webhook_handler()                                              │
│  - Extract event type                                           │
│  - Try to find order (existing logic)                           │
└─────────────────────────────────────────────────────────────────┘
                            ↓
                    Order Found?
                            ↓
                    ┌───────┴───────┐
                    │               │
                   YES              NO
                    │               │
                    ↓               ↓
        ┌───────────────────┐  ┌──────────────────────┐
        │ Process Normally  │  │ Call authorize_      │
        │ - authorize_      │  │ payment() or         │
        │   payment()        │  │ capture_payment()    │
        │ - Returns true     │  │ - Returns FALSE     │
        └───────────────────┘  └──────────────────────┘
                                        ↓
                            ┌───────────────────────────┐
                            │ Extract Identifiers:      │
                            │ - payment_id             │
                            │ - order_id (if present)  │
                            │ - payment_session_id      │
                            │   (if present)           │
                            └───────────────────────────┘
                                        ↓
                            ┌───────────────────────────┐
                            │ Save to Queue Table       │
                            │ - payment_id             │
                            │ - order_id               │
                            │ - payment_session_id      │
                            │ - webhook_type           │
                            │ - webhook_data (JSON)    │
                            └───────────────────────────┘
                                        ↓
                            ┌───────────────────────────┐
                            │ Return HTTP 200 OK        │
                            │ (Prevents Checkout.com    │
                            │  retry)                   │
                            └───────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                    ORDER CREATED                                 │
└─────────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────────┐
│  process_payment()                                              │
│  - Create order                                                 │
│  - Save payment_id to meta                                      │
│  - Save payment_session_id to meta                              │
│  - $order->save()                                               │
└─────────────────────────────────────────────────────────────────┘
                            ↓
        ┌───────────────────────────────────────┐
        │ process_pending_webhooks_for_order()  │
        │ - Get payment_id from order           │
        │ - Get payment_session_id from order  │
        │ - Get order_id from order            │
        └───────────────────────────────────────┘
                            ↓
        ┌───────────────────────────────────────┐
        │ Query Queue Table                      │
        │ WHERE (payment_id = X                  │
        │    OR payment_session_id = Y           │
        │    OR order_id = Z)                    │
        │ AND processed_at IS NULL               │
        │ ORDER BY webhook_type, created_at      │
        └───────────────────────────────────────┘
                            ↓
                    Found Webhooks?
                            ↓
                    ┌───────┴───────┐
                    │               │
                   YES              NO
                    │               │
                    ↓               ↓
        ┌───────────────────┐  ┌──────────────┐
        │ For Each Webhook: │  │ Done - No    │
        │ 1. Set order_id   │  │ webhooks to │
        │    in metadata    │  │ process     │
        │ 2. Call           │  └──────────────┘
        │    authorize_     │
        │    payment() or   │
        │    capture_       │
        │    payment()      │
        │ 3. If success:    │
        │    Mark processed │
        └───────────────────┘
                            ↓
                    ┌───────────────┐
                    │ Order Status  │
                    │ Updated!      │
                    └───────────────┘
```

---

## Edge Cases Handled

### 1. Multiple Webhooks for Same Payment
- **Scenario**: Auth webhook and capture webhook both arrive before order created
- **Solution**: Both saved to queue, processed in order (auth → capture)

### 2. Webhook Arrives After Order Created
- **Scenario**: Normal processing succeeds
- **Solution**: No queue needed, webhook processed immediately

### 3. Order Never Created
- **Scenario**: Webhook queued but order creation fails
- **Solution**: Webhook stays in queue, cleanup after 7 days

### 4. Payment ID Missing
- **Scenario**: Webhook doesn't have payment_id
- **Solution**: Can't queue (no way to match), return false (let Checkout.com retry)

### 5. Duplicate Webhooks
- **Scenario**: Same webhook arrives multiple times
- **Solution**: Each saved separately, processed once (duplicate processing is idempotent)

---

## Benefits

1. **Immediate Status Update**: Order status updated as soon as order is created
2. **No Retries Needed**: Checkout.com doesn't retry (we return success when queued)
3. **Handles Race Conditions**: Webhooks arriving before orders are handled gracefully
4. **Works for Flow Payments**: Supports payment_session_id matching when order_id missing
5. **Backward Compatible**: Existing webhook processing unchanged

---

## Summary

**Before**: Webhook fails → Wait 2-5 minutes → Retry → Order status updated

**After**: Webhook queued → Order created → Webhook processed immediately → Order status updated

The queue acts as a buffer between fast-arriving webhooks and slower order creation, ensuring orders are updated correctly without delays.

