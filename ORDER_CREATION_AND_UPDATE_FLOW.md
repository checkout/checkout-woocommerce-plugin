# WooCommerce Order Creation and Update Flow - Complete Documentation

## Table of Contents
1. [Order Creation Flow](#order-creation-flow)
2. [Order Update Flow](#order-update-flow)
3. [Webhook Processing](#webhook-processing)
4. [Status Transitions](#status-transitions)
5. [Duplicate Prevention](#duplicate-prevention)
6. [3DS Flow](#3ds-flow)

---

## Order Creation Flow

### Phase 1: Checkout Page - Order Creation Hook

**Location:** `woocommerce-gateway-checkout-com.php` (lines 379-500)

**Hook:** `woocommerce_checkout_create_order`

**When:** WooCommerce creates order object during checkout form submission

**Process:**

1. **Duplicate Order Check:**
   ```php
   // Generate session+cart identifier
   $session_cart_identifier = 'customer_{customer_id}_{cart_hash}'
   // OR
   $session_cart_identifier = 'session_{session_key}_{cart_hash}'
   ```

2. **Check for Existing Orders:**
   - Search for orders with same `_cko_session_cart_id` meta
   - **Pending orders:** Check last 7 days
   - **Failed orders:** Check last 2 days

3. **If Existing Order Found:**
   - If status is `pending` or `failed`:
     - Refresh order with current cart items
     - Update shipping methods
     - Recalculate totals
     - Return existing order (prevent duplicate)
   - If status is other (e.g., `processing`, `completed`):
     - Create new order

4. **If No Existing Order:**
   - Save `_cko_session_cart_id` to new order meta
   - Continue with normal order creation

**Result:** Order object created with status `pending`

---

### Phase 2: Payment Processing - `process_payment()`

**Location:** `flow-integration/class-wc-gateway-checkout-com-flow.php` (line 1299)

**When:** User clicks "Place Order" button

**Entry Point:** `process_payment( $order_id )`

#### Step 1: Duplicate Prevention Check

```php
// Check 1: Existing transaction ID
if ( ! empty( $order->get_transaction_id() ) ) {
    return ['result' => 'success', 'redirect' => $return_url];
}

// Check 2: Existing payment ID
if ( $existing_payment === $flow_payment_id ) {
    return ['result' => 'success', 'redirect' => $return_url];
}
```

#### Step 2: Determine Payment Flow Type

**Three possible paths:**

##### Path A: 3DS Return Handler
**Condition:** `!empty($flow_payment_id) && isset($_POST['cko-flow-payment-type'])`

**Process:**
1. Fetch payment details from Checkout.com API
2. Extract payment status and action ID
3. Set order meta:
   ```php
   $order->set_transaction_id($result['action_id']);
   $order->update_meta_data('_cko_payment_id', $flow_payment_id);
   $order->update_meta_data('_cko_flow_payment_id', $flow_payment_id);
   $order->update_meta_data('_cko_flow_payment_type', $flow_payment_type);
   $order->update_meta_data('_cko_order_reference', $order->get_order_number());
   ```
4. **CRITICAL:** Save order immediately
   ```php
   $order->save(); // Line 1394
   ```
5. Process pending webhooks:
   ```php
   WC_Checkout_Com_Webhook_Queue::process_pending_webhooks_for_order($order);
   ```
6. Check if payment already captured:
   ```php
   $already_captured = $order->get_meta('cko_payment_captured');
   ```
7. Set order status:
   - If **NOT captured:** Set to `on-hold` (authorized status)
   - If **already captured:** Skip status update (keep `processing`)

##### Path B: Saved Card Payment
**Condition:** `WC_Checkoutcom_Api_Request::is_using_saved_payment_method()`

**Process:**
1. Get saved card token from POST
2. Create payment with token via API:
   ```php
   $result = WC_Checkoutcom_Api_Request::create_payment($order, $token);
   ```
3. Handle 3DS redirect if needed:
   ```php
   if (isset($result['3d'])) {
       return ['result' => 'success', 'redirect' => $result['3d']];
   }
   ```
4. Set transaction ID and payment ID
5. Check if already captured
6. Set status (same logic as Path A)

##### Path C: New Flow Payment
**Condition:** Normal Flow payment (not 3DS return, not saved card)

**Process:**
1. Get payment ID from POST: `$_POST['cko-flow-payment-id']`
2. Get payment type: `$_POST['cko-flow-payment-type']`
3. Set order meta:
   ```php
   $order->update_meta_data('_cko_payment_id', $flow_pay_id);
   $order->update_meta_data('_cko_flow_payment_id', $flow_pay_id);
   $order->update_meta_data('_cko_flow_payment_type', $flow_payment_type);
   $order->update_meta_data('_cko_order_reference', $order->get_order_number());
   ```
4. Get payment session ID (for 3DS return lookup):
   - From POST: `$_POST['cko-flow-payment-session-id']`
   - Or fetch from payment metadata via API
5. Save session+cart identifier
6. **CRITICAL:** Save order immediately (line 1624)
   ```php
   $order->save();
   ```
7. Process pending webhooks (line 1628-1630)
8. Check if already captured
9. Set status (same logic as Path A)

#### Step 3: Card Saving Logic

**Location:** Lines 1667-1699

**Process:**
1. Check if save card enabled in settings
2. Check if customer selected to save card:
   - Priority 1: Hidden field `cko-flow-save-card-persist` (survives 3DS)
   - Priority 2: POST checkbox `wc-wc_checkout_com_flow-new-payment-method`
   - Priority 3: Session variable
3. If card type is `card` and save enabled:
   ```php
   $this->flow_save_cards($order, $flow_pay_id);
   ```

#### Step 4: Final Order Update

**Location:** Lines 1701-1706

**Process:**
1. Add order note with payment message
2. Update order status (if not null):
   ```php
   if (null !== $status) {
       $order->update_status($status);
   }
   ```
3. Reduce stock levels
4. Empty cart
5. Return redirect URL:
   ```php
   return [
       'result' => 'success',
       'redirect' => $this->get_return_url($order)
   ];
   ```

---

## Order Update Flow

### Update Mechanism 1: Webhooks

**Location:** `includes/class-wc-checkout-com-webhook.php`

#### Webhook Entry Point

**Location:** `flow-integration/class-wc-gateway-checkout-com-flow.php` (line 2547)

**Endpoint:** `/wp-json/ckoplugin/v1/webhook`

**Process:**
1. Receive webhook from Checkout.com
2. Verify webhook signature
3. Extract event type: `$data->type`
4. Find order using multiple methods:
   - Method 1: Order ID from metadata
   - Method 2: Payment session ID
   - Method 3: Order reference (order number)
   - Method 4: Payment ID

#### Webhook Event Types

##### 1. `payment_approved` → `authorize_payment()`

**Location:** `includes/class-wc-checkout-com-webhook.php` (line 32)

**Process:**
1. Extract order ID from webhook metadata
2. Load order
3. Check if already captured:
   ```php
   if ($already_captured) {
       return true; // Skip - don't downgrade status
   }
   ```
4. Check if already authorized:
   ```php
   if ($already_authorized && $order->get_status() === $auth_status) {
       $order->add_order_note($message);
       return true;
   }
   ```
5. Set transaction ID and payment ID
6. Set `cko_payment_authorized` meta to `true`
7. Add order note
8. Update status to `on-hold` (or configured authorized status)

**Status Change:** `pending` → `on-hold`

##### 2. `payment_captured` → `capture_payment()`

**Location:** `includes/class-wc-checkout-com-webhook.php` (line 202)

**Process:**
1. Extract order ID from webhook metadata
2. Load order
3. Check if already captured:
   ```php
   if ($already_captured) {
       $order->add_order_note($message);
       return true; // Skip duplicate processing
   }
   ```
4. If not already authorized, set authorization:
   ```php
   if (!$already_authorized) {
       $order->update_meta_data('cko_payment_authorized', true);
   }
   ```
5. Set transaction ID (action ID)
6. Set `cko_payment_captured` meta to `true`
7. Add order note
8. Update status to `processing` (or configured captured status)

**Status Change:** `pending`/`on-hold` → `processing`

**Important:** This webhook can arrive BEFORE `payment_approved`, so it handles authorization automatically.

##### 3. `card_verified` → `card_verified()`

**Location:** `includes/class-wc-checkout-com-webhook.php` (line 130)

**Process:**
1. Extract order ID and action ID
2. Load order
3. Set transaction ID
4. Update status to `processing`

**Status Change:** `pending` → `processing`

#### Webhook Queue System

**Location:** `includes/class-wc-checkout-com-webhook-queue.php`

**Purpose:** Handle webhooks that arrive before order is created

**Process:**

1. **Webhook Arrives Before Order:**
   - Order not found via any matching method
   - Webhook saved to queue table: `wp_cko_pending_webhooks`
   - HTTP 200 returned (prevents Checkout.com retry)

2. **Order Created:**
   - After `$order->save()` (lines 1394, 1624, 2345)
   - Check for pending webhooks:
     ```php
     WC_Checkout_Com_Webhook_Queue::process_pending_webhooks_for_order($order);
     ```

3. **Process Queued Webhooks:**
   - Find webhooks by payment_id or payment_session_id
   - Set order_id in webhook metadata
   - Process webhook normally
   - Mark webhook as processed

---

### Update Mechanism 2: 3DS Return Handler

**Location:** `flow-integration/class-wc-gateway-checkout-com-flow.php` (line 1356)

**When:** User returns from 3DS authentication page

**Process:**

1. **3DS Return Detection:**
   - Check for payment ID in POST: `$_POST['cko-flow-payment-id']`
   - Check for payment type: `$_POST['cko-flow-payment-type']`

2. **Fetch Payment Details:**
   ```php
   $payment_details = $builder->getPaymentsClient()->getPaymentDetails($flow_payment_id);
   ```

3. **Order Lookup:**
   - Try to find existing order by payment_session_id
   - Or create new order if not found (3DS API endpoint)

4. **Order Update:**
   - Set transaction ID
   - Set payment IDs
   - Save order immediately
   - Process pending webhooks

5. **Status Update:**
   - Check if already captured
   - If NOT captured: Set to `on-hold`
   - If captured: Skip status update (preserve `processing`)

---

## Status Transitions

### Normal Flow (No 3DS)

```
1. Order Created
   Status: pending
   Meta: _cko_payment_id, _cko_flow_payment_id

2. Webhook: payment_approved
   Status: pending → on-hold
   Meta: cko_payment_authorized = true

3. Webhook: payment_captured
   Status: on-hold → processing
   Meta: cko_payment_captured = true
```

### Fast Capture Flow (Capture Before Authorization)

```
1. Order Created
   Status: pending

2. Webhook: payment_captured (arrives first)
   Status: pending → processing
   Meta: cko_payment_captured = true
   Meta: cko_payment_authorized = true (auto-set)

3. Webhook: payment_approved (arrives later)
   Status: processing (unchanged - already captured)
   Note: Only adds note, doesn't change status
```

### 3DS Flow

```
1. Order Created
   Status: pending

2. User Redirected to 3DS
   Status: pending (unchanged)

3. User Returns from 3DS
   Status: pending → on-hold (if not captured)
   OR
   Status: processing (if already captured via webhook)

4. Webhook: payment_approved (if not already processed)
   Status: on-hold (unchanged if already set)
   OR
   Status: pending → on-hold (if webhook arrives after return)

5. Webhook: payment_captured
   Status: on-hold → processing
   OR
   Status: processing (unchanged if already set)
```

### Saved Card Flow

```
1. Order Created
   Status: pending

2. Payment Created with Token
   Status: pending

3. If 3DS Required:
   Redirect to 3DS → Return → Status: on-hold
   
4. If No 3DS:
   Status: pending → on-hold

5. Webhook: payment_captured
   Status: on-hold → processing
```

---

## Duplicate Prevention

### Level 1: Session + Cart Hash

**Location:** `woocommerce-gateway-checkout-com.php` (line 379)

**Mechanism:**
- Generate hash from: `customer_id`/`session_key` + `cart_items` + `cart_total`
- Store as `_cko_session_cart_id` meta
- Check for existing orders with same hash
- Refresh existing order instead of creating duplicate

### Level 2: Transaction ID Check

**Location:** `flow-integration/class-wc-gateway-checkout-com-flow.php` (line 1325)

**Mechanism:**
- Check if order already has transaction ID
- If yes, skip processing and return success

### Level 3: Payment ID Check

**Location:** `flow-integration/class-wc-gateway-checkout-com-flow.php` (line 1339)

**Mechanism:**
- Check if order already processed with same payment ID
- If yes, skip processing and return success

---

## 3DS Flow

### Step-by-Step Process

1. **User Submits Payment:**
   - Flow component creates payment session
   - Payment ID returned

2. **3DS Required:**
   - Checkout.com returns `3d` redirect URL
   - User redirected to 3DS challenge page

3. **3DS Challenge:**
   - User completes authentication
   - Checkout.com processes result

4. **3DS Return:**
   - User redirected back to checkout page
   - POST data includes: `cko-flow-payment-id`, `cko-flow-payment-type`

5. **Order Processing:**
   - `process_payment()` detects 3DS return (line 1358)
   - Fetches payment details from API
   - Updates order with payment info
   - Processes pending webhooks
   - Sets status based on capture state

6. **Webhook Processing:**
   - Authorization webhook may arrive before or after return
   - Capture webhook may arrive before or after return
   - Status updated accordingly

---

## Key Meta Fields

### Order Meta Fields Set During Creation/Update

| Meta Key | Purpose | Set When |
|----------|---------|----------|
| `_cko_payment_id` | Checkout.com payment ID | Payment processing |
| `_cko_flow_payment_id` | Flow-specific payment ID | Payment processing |
| `_cko_flow_payment_type` | Payment type (card, applepay, etc.) | Payment processing |
| `_cko_payment_session_id` | Payment session ID (for 3DS lookup) | Payment processing |
| `_cko_order_reference` | Order number (for webhook lookup) | Payment processing |
| `_cko_session_cart_id` | Session+cart hash (duplicate prevention) | Order creation |
| `cko_payment_authorized` | Authorization flag | Authorization webhook |
| `cko_payment_captured` | Capture flag | Capture webhook |

---

## Critical Timing Points

### 1. Order Save Timing

**CRITICAL:** Order must be saved immediately after setting payment IDs:

```php
// Line 1394 (3DS return)
$order->save();

// Line 1624 (Normal Flow)
$order->save();

// Line 2345 (3DS API order creation)
$order->save();
```

**Why:** Webhooks may arrive immediately after payment creation. Order must exist in database for webhook matching.

### 2. Webhook Processing Timing

**After order save:**
```php
WC_Checkout_Com_Webhook_Queue::process_pending_webhooks_for_order($order);
```

**Why:** Process any webhooks that arrived before order was created.

### 3. Status Update Logic

**Check capture state before updating:**
```php
$already_captured = $order->get_meta('cko_payment_captured');
if (!$already_captured) {
    $status = 'on-hold';
} else {
    $status = null; // Skip update - preserve processing
}
```

**Why:** Prevent overwriting `processing` status back to `on-hold` if capture webhook arrived first.

---

## Summary Flow Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    CHECKOUT PAGE                            │
│  User fills form → Selects Flow → Enters card details      │
└───────────────────────┬─────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────┐
│         woocommerce_checkout_create_order HOOK              │
│  • Check for duplicate orders (session+cart hash)         │
│  • Create/refresh order                                    │
│  • Status: pending                                         │
└───────────────────────┬─────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────┐
│              process_payment() ENTRY                        │
│  • Duplicate prevention checks                             │
│  • Determine payment flow type                             │
└───────┬───────────────┬───────────────┬─────────────────────┘
        │               │               │
        ▼               ▼               ▼
┌──────────────┐ ┌──────────────┐ ┌──────────────┐
│ 3DS Return  │ │ Saved Card   │ │ New Flow     │
│ Handler      │ │ Payment      │ │ Payment      │
└──────┬───────┘ └──────┬───────┘ └──────┬───────┘
       │                 │                │
       └─────────────────┴────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────┐
│              SET ORDER META & SAVE                          │
│  • _cko_payment_id                                         │
│  • _cko_flow_payment_id                                     │
│  • _cko_payment_session_id                                 │
│  • $order->save() ← CRITICAL                               │
└───────────────────────┬─────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────┐
│         PROCESS PENDING WEBHOOKS                           │
│  • Check webhook queue                                     │
│  • Process any queued webhooks                             │
└───────────────────────┬─────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────┐
│              CHECK CAPTURE STATE                            │
│  • $already_captured = $order->get_meta('cko_payment_captured')│
│  • If NOT captured: Set status to 'on-hold'                │
│  • If captured: Skip status update (preserve 'processing')  │
└───────────────────────┬─────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────┐
│              WEBHOOKS ARRIVE                                │
│  • payment_approved → Status: on-hold                       │
│  • payment_captured → Status: processing                   │
│  • Order notes added                                       │
└─────────────────────────────────────────────────────────────┘
```

---

## Important Notes

1. **Order must be saved immediately** after setting payment IDs to allow webhook matching
2. **Webhooks can arrive in any order** - capture may come before authorization
3. **Status updates check capture state** to prevent downgrading from `processing` to `on-hold`
4. **Duplicate prevention** works at multiple levels (session hash, transaction ID, payment ID)
5. **3DS return handler** processes pending webhooks and checks capture state before updating status
6. **Webhook queue system** handles race conditions when webhooks arrive before order creation

