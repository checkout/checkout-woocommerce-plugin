# Webhook Processing Steps - Complete Guide

This document details the exact steps and checks performed for each webhook type in the Checkout.com WooCommerce Flow integration.

---

## 🔄 **FLOW WEBHOOK HANDLER (Entry Point)**

**Location:** `class-wc-gateway-checkout-com-flow.php` → `webhook_handler()`

### **Step 1: Order Matching (BEFORE Event Processing)**

The Flow webhook handler matches orders using 3 methods (in order):

1. **Method 1: Order ID from Metadata**
   - Extract `order_id` from `$data->data->metadata->order_id`
   - Load order using `wc_get_order($order_id)`
   - ✅ If found: Use this order
   - ❌ If not found: Try Method 2

2. **Method 2: Combined (Payment Session ID + Payment ID)**
   - Extract `cko_payment_session_id` from `$data->data->metadata->cko_payment_session_id`
   - Extract `payment_id` from `$data->data->id`
   - Query orders with BOTH:
     - `_cko_payment_session_id` = session ID
     - `_cko_flow_payment_id` = payment ID
   - First try orders with status: `pending`, `failed`, `on-hold`, `processing`
   - If not found, try all orders (fallback)
   - ✅ If found: Use this order
   - ❌ If not found: Try Method 3

3. **Method 3: Payment ID Alone**
   - Extract `payment_id` from `$data->data->id`
   - Query orders with `_cko_flow_payment_id` = payment ID
   - First try orders with status: `pending`, `failed`, `on-hold`, `processing`
   - If not found, try all orders (fallback)
   - ✅ If found: Use this order
   - ❌ If not found: Order not found

### **Step 2: Payment ID Validation (BEFORE Event Processing)**

**CRITICAL CHECK:** This happens BEFORE any webhook event is processed.

- Get order's payment IDs:
  - `_cko_flow_payment_id` (preferred)
  - `_cko_payment_id` (fallback)
- Get webhook payment ID: `$data->data->id`
- **If order has payment ID:**
  - ✅ **Match:** Continue processing
  - ❌ **Mismatch:** Reject webhook (HTTP 200, but don't process)
- **If order has NO payment ID:**
  - Set payment ID from webhook (first payment attempt)
  - Continue processing

### **Step 3: Event Type Routing**

Based on `$data->type`, route to appropriate handler:

- `payment_approved` → `authorize_payment()`
- `payment_captured` → `capture_payment()`
- `payment_declined` / `payment_authentication_failed` → `decline_payment()`
- `payment_capture_declined` → `capture_declined()`
- `payment_refunded` → `refund_payment()`
- `payment_voided` → `void_payment()`
- `payment_canceled` → `cancel_payment()`

### **Step 4: Mark as Processed**

After successful processing:
- Create unique webhook ID: `{payment_id}_{event_type}`
- Add to order meta: `_cko_processed_webhook_ids` (array)
- Prevents duplicate processing

---

## 1️⃣ **PAYMENT APPROVED (Authorization)**

**Event Type:** `payment_approved`  
**Handler:** `WC_Checkout_Com_Webhook::authorize_payment()`

### **Steps:**

1. **Extract Data**
   - `order_id` from `$data->data->metadata->order_id`
   - `payment_id` from `$data->data->id`
   - `action_id` from `$data->data->action_id`

2. **Validate Order ID**
   - ✅ Must be numeric and not empty
   - ❌ If invalid: Return `false` (webhook queued if possible)

3. **Load Order**
   - Use `self::get_wc_order($order_id)`
   - ❌ If not found: Return `false` (webhook queued if possible)

4. **Check Current State**
   - Get `cko_payment_captured` meta
   - Get current order status
   - Get `cko_payment_authorized` meta

5. **Status Update Logic**
   - ✅ **If already captured:** 
     - Add note only
     - Update meta: `cko_payment_authorized = true`
     - Set transaction ID
     - **DO NOT change status** (prevent downgrade)
     - Return `true`
   
   - ✅ **If already authorized AND status matches configured status:**
     - Add note only
     - Return `true`
   
   - ✅ **If order status is `processing` or `completed`:**
     - Add note
     - Update meta: `cko_payment_authorized = true`
     - Set transaction ID
     - **DO NOT change status** (prevent downgrade)
     - Return `true`
   
   - ✅ **Otherwise:**
     - Set transaction ID: `action_id`
     - Update meta: `_cko_payment_id = payment_id`
     - Update meta: `cko_payment_authorized = true`
     - Add order note
     - Update status to configured status (default: `on-hold`)
     - Return `true`

6. **If Processing Failed**
   - Return `false`
   - Flow handler queues webhook for later processing

---

## 2️⃣ **PAYMENT CAPTURED**

**Event Type:** `payment_captured`  
**Handler:** `WC_Checkout_Com_Webhook::capture_payment()`

### **Steps:**

1. **Extract Data**
   - `order_id` from `$data->data->metadata->order_id`
   - `payment_id` from `$data->data->id`
   - `action_id` from `$data->data->action_id`
   - `amount` from `$data->data->amount`

2. **Validate Order ID**
   - ✅ Must be numeric and not empty
   - ❌ If invalid: Return `false` (webhook queued if possible)

3. **Load Order**
   - Use `self::get_wc_order($order_id)`
   - ❌ If not found: Return `false` (webhook queued if possible)

4. **Check Authorization Status**
   - Get `cko_payment_authorized` meta
   - ✅ **If not authorized:** Set `cko_payment_authorized = true` (capture implies authorization)

5. **Check Capture Status**
   - Get `cko_payment_captured` meta
   - ✅ **If already captured:** 
     - Add note only
     - Return `true`

6. **Process Capture**
   - Add generic capture note
   - Set transaction ID: `action_id`
   - Update meta: `cko_payment_captured = true`
   - Compare amounts:
     - If `amount < order_amount`: Partial capture note
     - If `amount == order_amount`: Full capture note
   - Add specific capture note
   - Update status to configured status (default: `processing`)
   - Return `true`

7. **If Processing Failed**
   - Return `false`
   - Flow handler queues webhook for later processing

---

## 3️⃣ **PAYMENT DECLINED (Failed)**

**Event Type:** `payment_declined` or `payment_authentication_failed`  
**Handler:** `WC_Checkout_Com_Webhook::decline_payment()`

### **Steps:**

1. **Extract Data**
   - `order_id` from `$data->data->metadata->order_id`
   - `payment_id` from `$data->data->id`
   - `action_id` from `$data->data->action_id`
   - `response_summary` from `$data->data->response_summary`

2. **Validate Order ID**
   - ✅ Must be numeric and not empty
   - ❌ If invalid: Return `false`

3. **Load Order**
   - Use `self::get_wc_order($order_id)`
   - ❌ If not found: Return `false`

4. **CRITICAL: Payment ID Validation**
   - Get order's `_cko_flow_payment_id` or `_cko_payment_id`
   - Compare with webhook `payment_id`
   - ✅ **If order has payment ID AND matches:** Continue
   - ✅ **If order has NO payment ID:** Continue (first attempt)
   - ❌ **If order has payment ID AND doesn't match:** 
     - Log error
     - Return `false` (reject webhook)

5. **Check Order Status**
   - Get current order status
   - ✅ **If status is `failed`:** 
     - Add note only
     - Return `true` (prevent duplicate status change)

6. **Process Decline**
   - Create decline message with payment ID, action ID, and reason
   - Add order note
   - Update status to `failed`
   - Return `true`

---

## 4️⃣ **PAYMENT CAPTURE DECLINED (Partial Capture Failed)**

**Event Type:** `payment_capture_declined`  
**Handler:** `WC_Checkout_Com_Webhook::capture_declined()`

### **Steps:**

1. **Extract Data**
   - `order_id` from `$data->data->metadata->order_id`
   - `payment_id` from `$data->data->id`
   - `action_id` from `$data->data->action_id`
   - `response_summary` from `$data->data->response_summary`

2. **Validate Order ID**
   - ✅ Must be numeric and not empty
   - ❌ If invalid: Return `false`

3. **Load Order**
   - Use `self::get_wc_order($order_id)`
   - ❌ If not found: Return `false`

4. **Process Capture Decline**
   - Create message with payment ID, action ID, and reason
   - Add order note
   - **Note:** Status is NOT changed (order remains in current state)
   - Return `true`

**Note:** This webhook does NOT validate payment ID (may need to be added for consistency).

---

## 5️⃣ **PAYMENT REFUNDED (Returned)**

**Event Type:** `payment_refunded`  
**Handler:** `WC_Checkout_Com_Webhook::refund_payment()`

### **Steps:**

1. **Extract Data**
   - `order_id` from `$data->data->metadata->order_id`
   - `payment_id` from `$data->data->id`
   - `action_id` from `$data->data->action_id`
   - `amount` from `$data->data->amount`

2. **Validate Order ID**
   - ✅ Must be numeric and not empty
   - ❌ If invalid: Return `false`

3. **Load Order**
   - Use `self::get_wc_order($order_id)`
   - ❌ If not found: Return `false`

4. **CRITICAL: Payment ID Validation**
   - Get order's `_cko_flow_payment_id` or `_cko_payment_id`
   - Compare with webhook `payment_id`
   - ✅ **If order has payment ID AND matches:** Continue
   - ✅ **If order has NO payment ID:** Continue (first attempt)
   - ❌ **If order has payment ID AND doesn't match:** 
     - Log error
     - Return `false` (reject webhook)

5. **Check Transaction ID**
   - Get order's transaction ID
   - ✅ **If transaction ID matches `action_id`:** 
     - Return `true` (already processed)

6. **Check Refund Status**
   - Get `order->get_total_refunded()`
   - ✅ **If fully refunded:** 
     - Add note only
     - Return `true`

7. **Process Refund**
   - Set transaction ID: `action_id`
   - Update meta: `cko_payment_refunded = true`
   - Convert amount to order currency
   - Compare amounts:
     - If `amount < order_amount`: **Partial refund**
       - Create partial refund note
       - Create WooCommerce refund record
     - If `amount == order_amount`: **Full refund**
       - Create full refund note
       - Create WooCommerce refund record
   - Add order note
   - Return `true`

---

## 6️⃣ **PAYMENT VOIDED**

**Event Type:** `payment_voided`  
**Handler:** `WC_Checkout_Com_Webhook::void_payment()`

### **Steps:**

1. **Extract Data**
   - `order_id` from `$data->data->metadata->order_id`
   - `payment_id` from `$data->data->id`
   - `action_id` from `$data->data->action_id`

2. **Validate Order ID**
   - ✅ Must be numeric and not empty
   - ❌ If invalid: Return `false`

3. **Load Order**
   - Use `self::get_wc_order($order_id)`
   - ❌ If not found: Return `false`

4. **CRITICAL: Payment ID Validation**
   - Get order's `_cko_flow_payment_id` or `_cko_payment_id`
   - Compare with webhook `payment_id`
   - ✅ **If order has payment ID AND matches:** Continue
   - ✅ **If order has NO payment ID:** Continue (first attempt)
   - ❌ **If order has payment ID AND doesn't match:** 
     - Log error
     - Return `false` (reject webhook)

5. **Check Void Status**
   - Get `cko_payment_voided` meta
   - ✅ **If already voided:** 
     - Add note only
     - Return `true`

6. **Process Void**
   - Create void message with payment ID and action ID
   - Update meta: `cko_payment_voided = true`
   - Add order note
   - Update status to `cancelled`
   - Return `true`

---

## 7️⃣ **PAYMENT CANCELLED**

**Event Type:** `payment_canceled`  
**Handler:** `WC_Checkout_Com_Webhook::cancel_payment()`

### **Steps:**

1. **Extract Payment ID**
   - `payment_id` from `$data->data->id`

2. **Fetch Payment Details from Checkout.com API**
   - Initialize Checkout SDK
   - Call `getPaymentDetails($payment_id)`
   - ❌ If API call fails: Return `false`

3. **Extract Order ID**
   - `order_id` from `$details['metadata']['order_id']`
   - ✅ Must be numeric and not empty
   - ❌ If invalid: Return `false`

4. **Load Order**
   - Use `self::get_wc_order($order_id)`
   - ❌ If not found: Return `false`

5. **CRITICAL: Payment ID Validation**
   - Get order's `_cko_flow_payment_id` or `_cko_payment_id`
   - Compare with webhook `payment_id`
   - ✅ **If order has payment ID AND matches:** Continue
   - ✅ **If order has NO payment ID:** Continue (first attempt)
   - ❌ **If order has payment ID AND doesn't match:** 
     - Log error
     - Return `false` (reject webhook)

6. **Process Cancellation**
   - Create cancellation message with payment ID
   - Add order note
   - Update status to `cancelled`
   - Return `true`

---

## 🔒 **SECURITY & VALIDATION SUMMARY**

### **Payment ID Validation (Critical)**

**Applied to:**
- ✅ `payment_declined` / `payment_authentication_failed`
- ✅ `payment_refunded`
- ✅ `payment_voided`
- ✅ `payment_canceled`
- ✅ Flow webhook handler (before event routing)

**NOT Applied to:**
- ⚠️ `payment_approved` (relies on order_id matching)
- ⚠️ `payment_captured` (relies on order_id matching)
- ⚠️ `payment_capture_declined` (should be added)

### **Duplicate Prevention**

1. **Webhook Queue:**
   - Checks for duplicate webhooks before queuing
   - Prevents same `payment_id + webhook_type` from being queued twice

2. **Processed Webhooks Tracking:**
   - Stores `_cko_processed_webhook_ids` array on order
   - Format: `{payment_id}_{event_type}`
   - Prevents same webhook from being processed twice

3. **Status Checks:**
   - `authorize_payment()`: Checks if already captured/authorized
   - `capture_payment()`: Checks if already captured
   - `decline_payment()`: Checks if already failed
   - `void_payment()`: Checks if already voided
   - `refund_payment()`: Checks if transaction ID matches or fully refunded

### **Status Protection**

- **Prevents Downgrades:**
  - `authorize_payment()` won't downgrade from `processing`/`completed` to `on-hold`
  - `decline_payment()` won't change status if already `failed`

- **Status Update Rules:**
  - `authorize_payment()`: Updates to configured status (default: `on-hold`)
  - `capture_payment()`: Updates to configured status (default: `processing`)
  - `decline_payment()`: Updates to `failed`
  - `void_payment()`: Updates to `cancelled`
  - `cancel_payment()`: Updates to `cancelled`
  - `capture_declined()`: **NO status change** (note only)
  - `refund_payment()`: **NO status change** (WooCommerce handles refund status)

---

## 📝 **LOGGING**

All webhook processing includes extensive logging:

- **Always Logged (Critical):**
  - Order matching results
  - Payment ID mismatches
  - Order not found errors
  - Invalid order IDs

- **Debug Mode Only:**
  - Full webhook data structure
  - Step-by-step processing details
  - Status change decisions
  - Meta updates

**Log Location:** WordPress debug log (if enabled) or Checkout.com plugin logs

---

## 🔄 **WEBHOOK QUEUE SYSTEM**

**When Used:**
- `payment_approved` webhook fails to process
- `payment_captured` webhook fails to process

**How It Works:**
1. Webhook processing fails (returns `false`)
2. Flow handler checks if webhook can be queued
3. If `payment_id` exists, saves to `wp_cko_webhook_queue` table
4. Returns `true` to Checkout.com (prevents retries)
5. Queue processor runs when order is found/created
6. Processes queued webhooks in order

**Duplicate Prevention:**
- Checks for existing unprocessed webhook with same `payment_id + webhook_type`
- Checks `_cko_processed_webhook_ids` before processing
- Marks as processed after successful processing

---

## ✅ **CHECKLIST FOR EACH WEBHOOK**

Before processing any webhook, verify:

- [ ] Order ID is valid and numeric
- [ ] Order exists in WooCommerce
- [ ] Payment ID matches order (if order has payment ID)
- [ ] Webhook hasn't been processed before (`_cko_processed_webhook_ids`)
- [ ] Order status allows this update (no downgrades)
- [ ] All required data is present (payment_id, action_id, etc.)

---

**Last Updated:** 2025-01-17  
**Version:** 5.0.0


