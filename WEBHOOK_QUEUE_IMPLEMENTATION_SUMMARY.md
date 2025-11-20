# Webhook Queue Implementation Summary

## Critical Requirement Confirmed

**When webhook is queued → Return HTTP 200 OK to Checkout.com → Prevents retry**

## Implementation Location

### Flow Gateway Webhook Handler
**File**: `flow-integration/class-wc-gateway-checkout-com-flow.php`  
**Method**: `webhook_handler()`  
**Lines**: ~2982-3053 (switch statement for event types)

### Current Code Structure:
```php
switch ( $event_type ) {
    case 'payment_approved':
        $response = WC_Checkout_Com_Webhook::authorize_payment( $data );
        // NEW CODE GOES HERE: Check if false, queue it
        break;
    
    case 'payment_captured':
        $response = WC_Checkout_Com_Webhook::capture_payment( $data );
        // NEW CODE GOES HERE: Check if false, queue it
        break;
    
    // ... other cases
}

// Final response (line 3055)
$http_code = $response ? 200 : 400;  // If $response = true → 200, false → 400
$this->send_response($http_code, $response ? 'OK' : 'Failed');
```

## Implementation Logic

### For `payment_approved` and `payment_captured` events:

```php
case 'payment_approved':
    $response = WC_Checkout_Com_Webhook::authorize_payment( $data );
    
    // NEW: If processing failed, queue the webhook
    if ( false === $response ) {
        $webhook_data = $data->data;
        $payment_id = $webhook_data->id ?? null;
        $order_id = isset($webhook_data->metadata->order_id) 
            ? $webhook_data->metadata->order_id 
            : null;
        $payment_session_id = isset($webhook_data->metadata->cko_payment_session_id)
            ? $webhook_data->metadata->cko_payment_session_id
            : null;
        
        // Only queue if we have payment_id (required for matching)
        if ( $payment_id ) {
            // Save to queue
            $queued = WC_Checkout_Com_Webhook_Queue::save_pending_webhook(
                $payment_id,
                $order_id,
                $payment_session_id,
                'payment_approved',
                $data
            );
            
            if ( $queued ) {
                WC_Checkoutcom_Utility::logger(
                    'WEBHOOK QUEUE: payment_approved webhook queued - ' .
                    'Payment ID: ' . $payment_id . ', ' .
                    'Order ID: ' . ($order_id ?? 'NULL') . ', ' .
                    'Session ID: ' . ($payment_session_id ?? 'NULL')
                );
                
                // CRITICAL: Set response to true so HTTP 200 is sent
                // This tells Checkout.com webhook was processed successfully
                // Checkout.com will NOT retry
                $response = true;
            }
        } else {
            WC_Checkoutcom_Utility::logger(
                'WEBHOOK QUEUE: Cannot queue payment_approved webhook - ' .
                'Payment ID missing'
            );
            // Leave $response = false, will return HTTP 400 (let Checkout.com retry)
        }
    }
    break;

case 'payment_captured':
    $response = WC_Checkout_Com_Webhook::capture_payment( $data );
    
    // NEW: Same logic as payment_approved
    if ( false === $response ) {
        $webhook_data = $data->data;
        $payment_id = $webhook_data->id ?? null;
        $order_id = isset($webhook_data->metadata->order_id) 
            ? $webhook_data->metadata->order_id 
            : null;
        $payment_session_id = isset($webhook_data->metadata->cko_payment_session_id)
            ? $webhook_data->metadata->cko_payment_session_id
            : null;
        
        if ( $payment_id ) {
            $queued = WC_Checkout_Com_Webhook_Queue::save_pending_webhook(
                $payment_id,
                $order_id,
                $payment_session_id,
                'payment_captured',
                $data
            );
            
            if ( $queued ) {
                WC_Checkoutcom_Utility::logger(
                    'WEBHOOK QUEUE: payment_captured webhook queued - ' .
                    'Payment ID: ' . $payment_id . ', ' .
                    'Order ID: ' . ($order_id ?? 'NULL') . ', ' .
                    'Session ID: ' . ($payment_session_id ?? 'NULL')
                );
                
                // CRITICAL: Set response to true
                $response = true;
            }
        }
    }
    break;
```

## Response Flow

### When Webhook is Queued:
```
$response = false (from authorize_payment/capture_payment)
    ↓
Queue webhook → Success
    ↓
$response = true (set explicitly)
    ↓
$http_code = $response ? 200 : 400  → 200
    ↓
send_response(200, 'OK')
    ↓
Checkout.com receives: HTTP 200 OK
    ↓
Checkout.com: "Webhook processed successfully, no retry needed"
```

### When Webhook Cannot Be Queued (no payment_id):
```
$response = false (from authorize_payment/capture_payment)
    ↓
Try to queue → Fails (no payment_id)
    ↓
$response = false (unchanged)
    ↓
$http_code = $response ? 200 : 400  → 400
    ↓
send_response(400, 'Failed')
    ↓
Checkout.com receives: HTTP 400 Bad Request
    ↓
Checkout.com: "Webhook failed, will retry"
```

## Key Points

1. **Only queue `payment_approved` and `payment_captured`** (as per requirements)
2. **Only queue when processing returns `false`**
3. **Set `$response = true` after successful queuing** → HTTP 200 sent
4. **Checkout.com receives success** → No retry scheduled
5. **Webhook processed later** when order is created

## Testing Checklist

- [ ] Webhook arrives before order created → Queued → HTTP 200 sent
- [ ] Checkout.com does NOT retry (verify logs)
- [ ] Order created → Queued webhook processed → Order status updated
- [ ] Webhook arrives after order created → Processed normally → HTTP 200 sent
- [ ] Multiple webhooks for same payment → Both queued → Processed in order
- [ ] Payment ID missing → Not queued → HTTP 400 sent → Checkout.com retries




