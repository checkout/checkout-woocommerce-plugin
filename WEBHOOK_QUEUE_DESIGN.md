# Webhook Queue System Design

## Problem Statement

When orders are created, there's a race condition where webhooks (auth/capture) can arrive before the order is fully created or before identifiers match. This causes:
- Webhooks to fail and retry (Checkout.com retries after a few minutes)
- Orders to stay in incorrect status for several minutes
- Poor user experience and potential order status inconsistencies

## Solution Overview

Implement a **webhook queuing system** that:
1. Stores webhooks temporarily when orders aren't found
2. Processes queued webhooks when orders are created
3. Matches webhooks to orders using payment_id as the primary identifier

## Architecture

### Components

1. **Database Table**: `wp_cko_pending_webhooks`
   - Stores webhooks that arrive before orders are ready
   - Indexed by payment_id for fast lookups

2. **Webhook Queue Manager Class**: `WC_Checkout_Com_Webhook_Queue`
   - Handles storing and retrieving pending webhooks
   - Processes queued webhooks when orders are created

3. **Modified Webhook Handlers**:
   - `authorize_payment()` - Queue if order not found
   - `capture_payment()` - Queue if order not found

4. **Order Creation Hook**:
   - After order is created and payment_id is saved
   - Check for pending webhooks and process them

## Database Schema

```sql
CREATE TABLE wp_cko_pending_webhooks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_id VARCHAR(255) NOT NULL,
    order_id VARCHAR(50) NULL,
    payment_session_id VARCHAR(255) NULL,
    webhook_type VARCHAR(50) NOT NULL, -- 'payment_approved' or 'payment_captured'
    webhook_data LONGTEXT NOT NULL, -- JSON encoded webhook payload
    created_at DATETIME NOT NULL,
    processed_at DATETIME NULL,
    INDEX idx_payment_id (payment_id),
    INDEX idx_order_id (order_id),
    INDEX idx_payment_session_id (payment_session_id),
    INDEX idx_processed (processed_at),
    INDEX idx_created (created_at)
);
```

**Fields:**
- `payment_id`: Checkout.com payment ID (primary matching key)
- `order_id`: WooCommerce order ID (if available in webhook metadata)
- `payment_session_id`: Payment session ID (if available, used for Flow payments)
- `webhook_type`: Type of webhook event ('payment_approved' or 'payment_captured')
- `webhook_data`: Full webhook payload (JSON encoded)
- `created_at`: When webhook was queued
- `processed_at`: When webhook was processed (NULL = pending)

## Flow Diagram

### Webhook Arrives - Processing Flow

```
Webhook Arrives
    ↓
Extract payment_id and order_id
    ↓
Try normal webhook processing:
  - authorize_payment() or capture_payment()
  - Uses existing matching logic (order_id, payment_id, etc.)
    ↓
Processing Returns?
    ↓
SUCCESS (true) ──→ Return true (webhook processed successfully)
    │
FAILURE (false) ──→ Extract identifiers from webhook:
                      - payment_id ($data->data->id)
                      - order_id ($data->data->metadata->order_id)
                      - payment_session_id ($data->data->metadata->cko_payment_session_id)
                      ↓
                  Save to pending_webhooks table
                      ↓
                  Set $response = true (CRITICAL: Prevents Checkout.com retry)
                      ↓
                  Return HTTP 200 OK to Checkout.com
```

**Key Point**: Webhooks are ONLY queued when normal processing **returns false** (fails).

### Order Created

```
Order Created
    ↓
Save payment_id to order meta (_cko_payment_id)
    ↓
Save payment_session_id to order meta (_cko_payment_session_id) if available
    ↓
Save order (commit to database)
    ↓
Check pending_webhooks table for matching:
  1. payment_id (primary)
  2. payment_session_id (secondary, for Flow)
  3. order_id (tertiary)
    ↓
Found Pending Webhooks?
    ↓ YES
Sort by type (auth first, then capture)
    ↓
Process each webhook:
  - authorize_payment() or capture_payment()
  - Update order status
  - Mark webhook as processed
    ↓
Delete processed webhooks from table
```

## Matching Logic

### Primary Matching: Payment ID
- Search orders by `_cko_payment_id` or `_cko_flow_payment_id` meta field
- Most reliable identifier (unique per payment)

### Secondary Matching: Payment Session ID (Flow payments)
- Search orders by `_cko_payment_session_id` meta field
- Important for Flow payments where order_id may not be in webhook
- Used when payment_id matching fails

### Tertiary Matching: Order ID
- Use `order_id` from webhook metadata if available
- Fallback if payment_id and payment_session_id matching fail

### Webhook Processing Order
1. **Auth webhooks first** (`payment_approved`)
2. **Capture webhooks second** (`payment_captured`)
3. Ensures proper state transitions

## Implementation Details

### 1. Webhook Queue Manager Class

**Location**: `includes/class-wc-checkout-com-webhook-queue.php`

**Key Methods**:
- `save_pending_webhook($payment_id, $order_id, $payment_session_id, $webhook_type, $webhook_data)` - Store webhook
- `get_pending_webhooks($payment_id, $payment_session_id = null)` - Retrieve pending webhooks (by payment_id or payment_session_id)
- `mark_processed($webhook_id)` - Mark webhook as processed
- `process_pending_webhooks_for_order($order)` - Process all pending webhooks for an order
- `cleanup_old_webhooks($days = 7)` - Remove old processed webhooks

### 2. Modified Webhook Handlers

**authorize_payment()**:
```php
// Current flow:
// 1. Try to find order (existing logic)
// 2. If order not found → return false
// 3. If order found → process and return true/false

// New flow:
// 1. Try to find order (existing logic - NO CHANGES)
// 2. If order found → process normally (NO CHANGES)
// 3. If processing returns false → save to queue, return true
// 4. If order not found → save to queue, return true
```

**capture_payment()**:
```php
// Similar to authorize_payment()
// Only queue when processing returns false
// Also check if auth webhook was processed first (existing logic)
```

**Important**: We wrap the existing webhook handlers, not replace them. Queue only when they return `false`.

### 3. Order Creation Hook

**Hook**: `woocommerce_checkout_order_processed` or after `$order->save()`

**Location**: In Flow gateway class after order is created and payment_id is saved

**Action**:
```php
// After order->save() with payment_id
WC_Checkout_Com_Webhook_Queue::process_pending_webhooks_for_order($order);
```

**Also**: Check queue when order status changes to ensure webhooks are processed even if hook missed.

### 4. Cleanup Mechanism

**Scheduled Task**: Daily cleanup of processed webhooks older than 7 days

**Hook**: `wp_scheduled_delete` or custom cron

## Edge Cases & Considerations

### 1. Multiple Webhooks for Same Payment
- Store all webhooks separately
- Process in order (auth → capture)
- Handle duplicate webhooks (check if already processed)

### 2. Webhook Arrives After Order Created
- Normal processing (no queue needed)
- Should be rare but handled gracefully

### 3. Order Never Created
- Webhooks remain in queue
- Cleanup after 7 days
- Log for monitoring

### 4. Race Conditions
- Use database transactions where possible
- Lock rows during processing
- Handle concurrent webhook processing

### 5. Webhook Retries
- **Only queue when webhook processing returns `false`**
- **CRITICAL**: When webhook is queued, set `$response = true` to return HTTP 200 OK
- This tells Checkout.com the webhook was processed successfully (prevents retry)
- Log that webhook was queued for later processing
- This ensures we don't queue successfully processed webhooks
- Checkout.com will NOT retry because it receives HTTP 200 success response

### 6. Payment ID Not Available
- Fallback to order_id matching
- If both unavailable, log error and return false (let Checkout.com retry)

## Security Considerations

1. **Data Sanitization**: Sanitize all webhook data before storage
2. **SQL Injection**: Use prepared statements
3. **Data Privacy**: Store minimal data necessary
4. **Access Control**: Only process webhooks from authenticated sources

## Performance Considerations

1. **Indexes**: Payment_id and order_id indexed for fast lookups
2. **Batch Processing**: Process multiple webhooks in single query
3. **Cleanup**: Regular cleanup prevents table bloat
4. **Caching**: Consider caching recent webhook lookups

## Testing Strategy

1. **Unit Tests**: Test queue manager methods
2. **Integration Tests**: Test webhook → queue → order creation flow
3. **Race Condition Tests**: Simulate concurrent webhooks and orders
4. **Edge Case Tests**: Missing payment_id, duplicate webhooks, etc.

## Monitoring & Logging

- Log when webhooks are queued
- Log when queued webhooks are processed
- Log errors during processing
- Monitor queue size (alert if growing)
- Track processing time

## Migration Strategy

1. Create database table on plugin activation/update
2. Backward compatible (existing webhooks still work)
3. Gradual rollout (feature flag if needed)
4. Monitor for issues

## Rollback Plan

- Keep existing webhook handlers unchanged (add queue as fallback)
- Can disable queue processing via constant/option
- Database table can be dropped without affecting core functionality

