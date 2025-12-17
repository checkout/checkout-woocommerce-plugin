# Webhook Matching - Log Analysis Guide

This guide shows you exactly what to search for in logs to trace how a payment webhook was matched to an order.

---

## 🔍 **Quick Search Guide**

### **Step 1: Find the Webhook Entry**

Search for the **Payment ID** from the order notes:
```
pay_u43vgdybaz5ybexffmkkytorxu
```

Or search for:
```
WEBHOOK MATCHING: ========== STARTING ORDER LOOKUP ==========
```

---

## 📋 **Complete Log Sequence**

### **1. Webhook Received (Start)**

**Search:** `WEBHOOK MATCHING: ========== STARTING ORDER LOOKUP ==========`

**What you'll see:**
```
WEBHOOK MATCHING: ========== STARTING ORDER LOOKUP ==========
WEBHOOK MATCHING: Event Type: payment_captured
WEBHOOK MATCHING: Payment ID: pay_u43vgdybaz5ybexffmkkytorxu
WEBHOOK MATCHING: Session ID in metadata: ps_xxxxx (or NOT SET)
WEBHOOK MATCHING: Order ID in metadata: 12345 (or NOT SET)
```

**What this tells you:**
- ✅ Webhook event type
- ✅ Payment ID from webhook
- ✅ Session ID (if present)
- ✅ Order ID (if present in metadata)

---

### **2. Method 1: Order ID from Metadata**

**Search:** `WEBHOOK MATCHING: Trying METHOD 1`

**What you'll see if Order ID exists:**
```
WEBHOOK MATCHING: Trying METHOD 1 (Order ID from metadata): 12345
WEBHOOK MATCHING: ✅ MATCHED BY METHOD 1 (Order ID from metadata) - Order ID: 12345
```

**OR if Order ID not found:**
```
WEBHOOK MATCHING: Trying METHOD 1 (Order ID from metadata): 12345
WEBHOOK MATCHING: ❌ METHOD 1 FAILED - Order ID 12345 not found
```

**What this tells you:**
- ✅ If order_id was in webhook metadata
- ✅ If order was found by order_id
- ❌ If order_id was missing or order not found

---

### **3. Method 2: Combined (Session ID + Payment ID)**

**Search:** `WEBHOOK MATCHING: METHOD 2` or `COMBINED`

**What you'll see if matched:**
```
WEBHOOK MATCHING: ✅ MATCHED BY METHOD 2 (COMBINED: Session ID + Payment ID) - Order ID: 12345
```

**OR if failed:**
```
WEBHOOK MATCHING: ❌ METHOD 2 FAILED - No order found by COMBINED match (Session ID: ps_xxxxx, Payment ID: pay_xxxxx)
```

**What this tells you:**
- ✅ Most reliable matching method (requires BOTH session ID and payment ID)
- ✅ Order found by combined identifiers
- ❌ If either session ID or payment ID missing/doesn't match

---

### **4. Method 3: Payment ID Alone**

**Search:** `WEBHOOK MATCHING: METHOD 3` or `PAYMENT ID ALONE`

**What you'll see if matched:**
```
WEBHOOK MATCHING: ✅ MATCHED BY METHOD 3 (PAYMENT ID ALONE) - Order ID: 12345
WEBHOOK MATCHING: ⚠️ WARNING: Matched by payment ID alone (less reliable than combined match)
```

**OR if failed:**
```
WEBHOOK MATCHING: ❌ METHOD 3 FAILED - No order found by payment ID: pay_xxxxx
```

**What this tells you:**
- ✅ Fallback method (less reliable)
- ✅ Order found by payment ID only
- ⚠️ Warning that this is less reliable
- ❌ If payment ID doesn't match any order

---

### **5. Order Details (If Found)**

**Search:** `WEBHOOK MATCHING: ✅ ORDER FOUND`

**What you'll see:**
```
WEBHOOK MATCHING: ✅ ORDER FOUND - Order ID: 12345
WEBHOOK MATCHING: Order Status: pending
WEBHOOK MATCHING: Order Payment Session ID: ps_xxxxx
WEBHOOK MATCHING: Order Payment ID (_cko_flow_payment_id): pay_xxxxx
WEBHOOK MATCHING: Order Payment ID (_cko_payment_id): pay_xxxxx
```

**What this tells you:**
- ✅ Order was successfully matched
- ✅ Current order status
- ✅ Payment session ID stored on order
- ✅ Payment IDs stored on order (both fields)

---

### **6. Order Not Found**

**Search:** `WEBHOOK MATCHING: ❌ ORDER NOT FOUND`

**What you'll see:**
```
WEBHOOK MATCHING: ❌ ORDER NOT FOUND - No matching order found
Flow webhook: No order found for webhook processing. Payment ID: pay_xxxxx - Will attempt to queue or process via webhook handlers
```

**What this tells you:**
- ❌ All 3 methods failed
- ✅ Webhook will be queued (if payment_approved or payment_captured)
- ✅ Checkout.com will retry (for other webhook types)

---

### **7. Payment ID Validation**

**Search:** `WEBHOOK MATCHING: Payment ID mismatch` or `Payment ID validation`

**What you'll see if mismatch:**
```
WEBHOOK MATCHING: ❌ CRITICAL ERROR - Payment ID mismatch in Flow webhook handler!
WEBHOOK MATCHING: Order ID: 12345
WEBHOOK MATCHING: Order _cko_flow_payment_id: pay_xxxxx
WEBHOOK MATCHING: Order _cko_payment_id: pay_xxxxx
WEBHOOK MATCHING: Expected payment ID: pay_xxxxx
WEBHOOK MATCHING: Webhook payment ID: pay_different
WEBHOOK MATCHING: ❌ REJECTING WEBHOOK - Payment ID does not match order!
```

**OR if match:**
```
Flow webhook: ✅ Payment ID validation passed - Order payment ID: pay_xxxxx, Webhook payment ID: pay_xxxxx
```

**What this tells you:**
- ✅ Payment IDs match (webhook is for correct order)
- ❌ Payment IDs don't match (webhook rejected - wrong payment)

---

### **8. Duplicate Prevention**

**Search:** `WEBHOOK: Already processed` or `WEBHOOK: ✅ Marked as processed`

**What you'll see:**
```
WEBHOOK: ✅ Already processed - Payment ID: pay_xxxxx, Type: payment_captured, Order: 12345
WEBHOOK: ✅ Skipping duplicate webhook processing to prevent multiple order updates
```

**OR:**
```
WEBHOOK: ✅ Marked as processed - Payment ID: pay_xxxxx, Type: payment_captured, Order: 12345
```

**What this tells you:**
- ✅ Webhook already processed (duplicate prevention working)
- ✅ Webhook marked as processed (will skip duplicates)

---

### **9. Webhook Processing**

**Search:** `WEBHOOK PROCESS:` + event type (e.g., `capture_payment`, `authorize_payment`)

**What you'll see:**
```
=== WEBHOOK PROCESS: capture_payment START ===
WEBHOOK PROCESS: Event type: payment_captured
WEBHOOK PROCESS: Order ID from metadata: 12345
WEBHOOK PROCESS: Payment ID: pay_xxxxx
WEBHOOK PROCESS: Order loaded successfully - Order ID: 12345, Status: pending
WEBHOOK PROCESS: Order status updated to: processing
=== WEBHOOK PROCESS: capture_payment END (SUCCESS) ===
```

**What this tells you:**
- ✅ Webhook handler executed
- ✅ Order was found and loaded
- ✅ Order status was updated
- ✅ Processing completed successfully

---

## 🔎 **Search Patterns for Specific Scenarios**

### **Scenario 1: Find How Order Was Matched**

**Search:** Payment ID + `WEBHOOK MATCHING: ✅ MATCHED BY METHOD`

**Example:**
```
pay_u43vgdybaz5ybexffmkkytorxu WEBHOOK MATCHING: ✅ MATCHED BY METHOD
```

**Result:** Shows which method (1, 2, or 3) matched the order

---

### **Scenario 2: Find Why Matching Failed**

**Search:** Payment ID + `WEBHOOK MATCHING: ❌`

**Example:**
```
pay_u43vgdybaz5ybexffmkkytorxu WEBHOOK MATCHING: ❌
```

**Result:** Shows which methods failed and why

---

### **Scenario 3: Find Payment ID Mismatch**

**Search:** Payment ID + `Payment ID mismatch` or `REJECTING WEBHOOK`

**Example:**
```
pay_u43vgdybaz5ybexffmkkytorxu Payment ID mismatch
```

**Result:** Shows if webhook was rejected due to payment ID mismatch

---

### **Scenario 4: Find Duplicate Webhook**

**Search:** Payment ID + `Already processed`

**Example:**
```
pay_u43vgdybaz5ybexffmkkytorxu Already processed
```

**Result:** Shows if webhook was skipped as duplicate

---

### **Scenario 5: Find Queued Webhook**

**Search:** Payment ID + `WEBHOOK QUEUE`

**Example:**
```
pay_u43vgdybaz5ybexffmkkytorxu WEBHOOK QUEUE
```

**Result:** Shows if webhook was queued (order not found yet)

---

## 📊 **Log Analysis Checklist**

For a specific payment, check these in order:

1. ✅ **Webhook Received**
   - Search: `WEBHOOK MATCHING: ========== STARTING ORDER LOOKUP ==========`
   - Check: Payment ID, Session ID, Order ID in metadata

2. ✅ **Matching Method Used**
   - Search: `WEBHOOK MATCHING: ✅ MATCHED BY METHOD`
   - Check: Which method (1, 2, or 3) matched

3. ✅ **Order Details**
   - Search: `WEBHOOK MATCHING: ✅ ORDER FOUND`
   - Check: Order ID, Status, Payment IDs

4. ✅ **Payment ID Validation**
   - Search: `Payment ID validation` or `Payment ID mismatch`
   - Check: If payment IDs match

5. ✅ **Duplicate Check**
   - Search: `Already processed` or `Marked as processed`
   - Check: If webhook was already processed

6. ✅ **Processing Result**
   - Search: `WEBHOOK PROCESS:` + event type
   - Check: If webhook was processed successfully

---

## 🎯 **Example: Complete Log Trace**

For payment `pay_u43vgdybaz5ybexffmkkytorxu`:

```
1. WEBHOOK MATCHING: ========== STARTING ORDER LOOKUP ==========
2. WEBHOOK MATCHING: Event Type: payment_captured
3. WEBHOOK MATCHING: Payment ID: pay_u43vgdybaz5ybexffmkkytorxu
4. WEBHOOK MATCHING: Session ID in metadata: ps_xxxxx
5. WEBHOOK MATCHING: Order ID in metadata: NOT SET
6. WEBHOOK MATCHING: ❌ METHOD 1 FAILED - Order ID not found
7. WEBHOOK MATCHING: ✅ MATCHED BY METHOD 2 (COMBINED: Session ID + Payment ID) - Order ID: 12345
8. WEBHOOK MATCHING: ✅ ORDER FOUND - Order ID: 12345
9. WEBHOOK MATCHING: Order Status: pending
10. Flow webhook: ✅ Payment ID validation passed
11. WEBHOOK PROCESS: capture_payment START
12. WEBHOOK PROCESS: Order status updated to: processing
13. WEBHOOK PROCESS: capture_payment END (SUCCESS)
14. WEBHOOK: ✅ Marked as processed - Payment ID: pay_u43vgdybaz5ybexffmkkytorxu
```

**Analysis:**
- ✅ Webhook received for `payment_captured` event
- ✅ Order ID NOT in metadata (normal for Flow checkout)
- ✅ Method 1 failed (no order_id in metadata)
- ✅ Method 2 succeeded (matched by Session ID + Payment ID)
- ✅ Payment ID validation passed
- ✅ Webhook processed successfully
- ✅ Order status updated to `processing`

---

## 🔧 **Log File Location**

Logs are written to:
- **WordPress Debug Log:** `wp-content/debug.log` (if `WP_DEBUG_LOG` enabled)
- **Checkout.com Plugin Logs:** Check plugin settings for log location
- **Server Error Logs:** Check your hosting provider's error logs

---

## 💡 **Tips**

1. **Use Payment ID as primary search term** - It's unique and appears in all relevant logs
2. **Search for "WEBHOOK MATCHING:"** - Shows the matching process
3. **Search for "WEBHOOK PROCESS:"** - Shows the processing result
4. **Check timestamps** - Match logs with order note timestamps
5. **Look for ❌ and ✅** - Quick visual indicators of success/failure

---

**Last Updated:** 2025-01-17  
**Version:** 5.0.0


