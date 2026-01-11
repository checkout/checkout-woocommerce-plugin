# Webhook Order Lookup Fix

**Version:** 2025-10-13-PRODUCTION-WEBHOOK-FIX  
**Date:** October 13, 2025

---

## 🐛 Problem

Webhooks were failing to find orders with error:

```
WEBHOOK DEBUG: Looking for order by reference: WOOf2282cbee1ac079504421403f
WEBHOOK DEBUG: Order found by reference: NO
Flow webhook: CRITICAL - No order found for webhook processing. Payment ID: pay_xxx
```

### Root Cause

1. **Payment session reference** is set to `$order->get_order_number()` (line 334 in `class-wc-checkoutcom-api-request.php`)
2. If a **Sequential Order Numbers** plugin is active, `get_order_number()` returns a custom format like `WOOf2282cbee1ac079504421403f`
3. The webhook handler tried: `wc_get_order("WOOf2282cbee1ac079504421403f")`
4. **`wc_get_order()` only works with numeric IDs**, not custom order numbers!

---

## ✅ Solution

Enhanced the webhook order lookup to handle **both numeric IDs and custom order numbers**:

### **New Lookup Strategy (in priority order)**:

1. **Try metadata `order_id`** (if present in webhook)
2. **Try direct `wc_get_order()`** (works for numeric references)
3. **Search by `_order_number` meta key** (for Sequential Order Numbers plugin)
4. **Search by `post_name`** (alternative storage location)
5. **Search by `_cko_flow_payment_id`** (fallback using payment ID)

### **Code Location**

File: `checkout-com-unified-payments-api/flow-integration/class-wc-gateway-checkout-com-flow.php`  
Function: `webhook_handler()`  
Lines: 1897-1950

### **Key Changes**

```php
// Before (WRONG):
$order = wc_get_order( $data->data->reference ); // Only works for numeric IDs

// After (FIXED):
// 1. Try direct lookup
$order = wc_get_order( $data->data->reference );

// 2. If failed, search by _order_number meta
if ( ! $order ) {
    $orders = wc_get_orders( array(
        'limit'      => 1,
        'meta_key'   => '_order_number',
        'meta_value' => $data->data->reference,
        'return'     => 'objects',
    ) );
    
    if ( ! empty( $orders ) ) {
        $order = $orders[0];
    }
}

// 3. If still failed, search by post_name
if ( ! $order ) {
    global $wpdb;
    $post_id = $wpdb->get_var( $wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('shop_order', 'shop_order_placehold') AND post_name = %s LIMIT 1",
        sanitize_title( $data->data->reference )
    ) );
    
    if ( $post_id ) {
        $order = wc_get_order( $post_id );
    }
}
```

---

## 🔍 Debugging Enhanced

Added comprehensive logging:

```
WEBHOOK DEBUG: Looking for order by reference: WOOf2282cbee1ac079504421403f
WEBHOOK DEBUG: Direct lookup result: NO
WEBHOOK DEBUG: Searching by order number meta key: WOOf2282cbee1ac079504421403f
WEBHOOK DEBUG: Order found by _order_number meta: YES (ID: 3409)
WEBHOOK DEBUG: Set metadata order_id to: 3409
```

---

## ✨ Benefits

1. ✅ **Works with Sequential Order Numbers plugins**
2. ✅ **Works with default WooCommerce order IDs**
3. ✅ **Works with any custom order number format**
4. ✅ **Comprehensive logging for debugging**
5. ✅ **Multiple fallback strategies**

---

## 🧪 Testing

To test:

1. Install a Sequential Order Numbers plugin (or use default WooCommerce)
2. Create a new order using Flow payment
3. Check webhook logs - should now find the order successfully
4. Verify order status updates correctly

---

## 📝 Related Files

- `checkout-com-unified-payments-api/flow-integration/class-wc-gateway-checkout-com-flow.php` (webhook handler)
- `checkout-com-unified-payments-api/includes/api/class-wc-checkoutcom-api-request.php` (payment session creation)
- `LOGGING_STRATEGY.md` (webhook logging strategy)

