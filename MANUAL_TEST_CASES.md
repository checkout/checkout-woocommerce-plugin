# Manual Test Cases - All Fixes Verification

## Overview
This document contains manual test cases to verify all the fixes implemented in the latest version.

---

## Test Case 1: Webhook Queue System - Webhook Arrives Before Order Creation

### Objective
Verify that webhooks arriving before orders are created are properly queued and processed when the order is created.

### Prerequisites
- Webhook debug logging enabled in plugin settings
- Test environment with Checkout.com sandbox credentials

### Test Steps
1. **Prepare for fast webhook arrival:**
   - Clear all logs
   - Ensure slow order creation (add delays if needed for testing)

2. **Initiate a payment:**
   - Go to checkout page
   - Fill in test card details
   - Click "Place Order"
   - **Immediately** check webhook logs (webhook may arrive before order is created)

3. **Verify webhook queuing:**
   - Check logs for: `WEBHOOK PROCESS: WEBHOOK QUEUE: Webhook queued successfully`
   - Verify webhook returns HTTP 200 (not 404)
   - Check database table `wp_cko_pending_webhooks` for queued webhook

4. **Verify order creation:**
   - Wait for order to be created
   - Check logs for: `WEBHOOK PROCESS: WEBHOOK QUEUE: Processing X pending webhook(s)`
   - Verify order status is updated correctly (on-hold or processing)

### Expected Results
- ✅ Webhook is queued when order not found
- ✅ HTTP 200 returned to Checkout.com (prevents retry)
- ✅ Queued webhook is processed when order is created
- ✅ Order status is updated correctly
- ✅ No "Order not found" errors in logs

### Logs to Check
```
WEBHOOK PROCESS: WEBHOOK QUEUE: Webhook queued successfully
WEBHOOK PROCESS: WEBHOOK QUEUE: Processing X pending webhook(s)
WEBHOOK PROCESS: WEBHOOK QUEUE: Successfully processed queued webhook
```

---

## Test Case 2: Order Status Regression - Authorization Webhook After Capture

### Objective
Verify that authorization webhook does NOT downgrade order status from Processing to On-hold when it arrives after capture webhook.

### Prerequisites
- Order with payment that gets captured quickly
- Both authorization and capture webhooks configured

### Test Steps
1. **Create a successful payment:**
   - Complete checkout with test card
   - Payment should be authorized and captured quickly

2. **Monitor order status changes:**
   - Check order notes timeline
   - Note the sequence of status changes

3. **Verify status sequence:**
   - Order should go: `Pending` → `Processing` (from capture)
   - Authorization webhook should NOT change status back to `On-hold`
   - Order should remain in `Processing` status

### Expected Results
- ✅ Capture webhook sets status to `Processing`
- ✅ Authorization webhook arrives after capture
- ✅ Authorization webhook adds note but does NOT change status
- ✅ Order remains in `Processing` status
- ✅ Logs show: `Order already in advanced state (processing), skipping status update`

### Order Notes Sequence (Expected)
```
1. Order status changed from Pending payment to Processing
2. Checkout.com Payment Captured - Payment ID: xxx, Action ID: xxx
3. Webhook received from checkout.com. Payment Authorized - Payment ID: xxx, Action ID: xxx
   (NO status change back to On-hold)
```

### Logs to Check
```
WEBHOOK PROCESS: authorize_payment - Current order status: processing
WEBHOOK PROCESS: authorize_payment - Order already in advanced state (processing), skipping status update to prevent downgrade
```

---

## Test Case 3: Failed Order Reuse Prevention

### Objective
Verify that failed orders are NOT reused when customer retries payment.

### Prerequisites
- Test card that will be declined
   - Use card: `4000 0000 0000 0002` (declined card)

### Test Steps
1. **First payment attempt (should fail):**
   - Go to checkout
   - Enter declined card: `4000 0000 0000 0002`
   - Fill other details
   - Click "Place Order"
   - Payment should fail
   - Note the order number (e.g., Order #679)

2. **Verify first order:**
   - Check order status is `Failed`
   - Check order notes show payment declined
   - Verify order has payment ID in meta

3. **Second payment attempt (retry):**
   - Stay on checkout page (or go back)
   - Enter same declined card again
   - Click "Place Order" again

4. **Verify new order created:**
   - Check that a NEW order number is created (e.g., Order #680)
   - Old failed order (#679) should remain unchanged
   - New order should have its own payment attempt

### Expected Results
- ✅ First attempt creates Order #679 with status `Failed`
- ✅ Second attempt creates NEW Order #680 (not reusing #679)
- ✅ Old failed order (#679) remains `Failed` and unchanged
- ✅ Logs show: `Existing order already has payment ID/transaction ID - NOT reusing`
- ✅ Logs show: `Will create NEW order instead`

### Logs to Check
```
[FLOW] Found existing order with same session+cart hash - Order ID: 679, Status: failed
[FLOW] ⚠️ Existing order already has payment ID/transaction ID - NOT reusing
[FLOW] Reason: Order was already processed (even if failed), reusing would cause payment conflicts
```

---

## Test Case 4: Cart Clearing - Only on Successful Payment

### Objective
Verify that cart is NOT cleared when payment fails.

### Prerequisites
- Test declined card: `4000 0000 0000 0002`
- Items in cart

### Test Steps
1. **Add items to cart:**
   - Add 2-3 products to cart
   - Note the cart contents

2. **Attempt payment with declined card:**
   - Go to checkout
   - Enter declined card: `4000 0000 0000 0002`
   - Fill other details
   - Click "Place Order"

3. **Verify cart status:**
   - After payment fails, check cart
   - Cart should still contain the items
   - User should be able to retry payment

4. **Verify order status:**
   - Order should be marked as `Failed`
   - User should see error message on checkout page

### Expected Results
- ✅ Payment fails
- ✅ Cart remains intact (items still in cart)
- ✅ Order status is `Failed`
- ✅ User sees error message on checkout page
- ✅ User can retry payment without losing cart items
- ✅ Logs show: `Payment failed - Order status is failed. NOT clearing cart`

### Logs to Check
```
[PROCESS PAYMENT] Payment failed - Order status is failed. NOT clearing cart or redirecting to success page
```

---

## Test Case 5: Redirect on Payment Failure

### Objective
Verify that user is redirected to checkout page (not order-received page) when payment fails.

### Prerequisites
- Test declined card: `4000 0000 0000 0002`

### Test Steps
1. **Initiate failed payment:**
   - Go to checkout page
   - Enter declined card details
   - Click "Place Order"

2. **Verify redirect:**
   - After payment fails, check URL
   - Should be redirected to checkout page (not order-received)
   - URL should contain `?payment_failed=1` parameter

3. **Verify error message:**
   - Error message should be displayed on checkout page
   - Order should be marked as `Failed` in backend

### Expected Results
- ✅ User is redirected to checkout page (not order-received)
- ✅ URL contains `payment_failed=1` parameter
- ✅ Error message displayed on checkout page
- ✅ Order status is `Failed` in backend
- ✅ User can retry payment
- ✅ Logs show: `Payment failed, redirecting to checkout page`

### Logs to Check
```
[FLOW 3DS API] Payment failed, redirecting to checkout page: /checkout/?payment_failed=1
```

---

## Test Case 6: Payment ID in Decline Webhook Notes

### Objective
Verify that payment declined webhook order notes include Payment ID and Action ID.

### Prerequisites
- Test declined card: `4000 0000 0000 0002`
- Webhook configured for payment_declined event

### Test Steps
1. **Initiate declined payment:**
   - Go to checkout
   - Enter declined card: `4000 0000 0000 0002`
   - Complete checkout

2. **Check order notes:**
   - Go to order details in WooCommerce admin
   - Check order notes section
   - Look for decline webhook note

3. **Verify note format:**
   - Note should include Payment ID
   - Note should include Action ID (if available)
   - Note should include decline reason

### Expected Results
- ✅ Order note shows: `Webhook received from checkout.com. Payment declined - Payment ID: pay_xxx, Action ID: act_xxx, Reason: [reason]`
- ✅ Payment ID is present and valid
- ✅ Action ID is present (if available in webhook)
- ✅ Decline reason is included

### Order Note Format (Expected)
```
Webhook received from checkout.com. Payment declined - Payment ID: pay_qtgqbpuou2pepa5ycxlqcfatiy, Action ID: act_5nufyhuor6runnzwb676xuofxi, Reason: Blocked by cardholder/contact cardholder
```

---

## Test Case 7: Webhook Queue Logging Integration

### Objective
Verify that webhook queue logs are properly integrated with webhook debug logging.

### Prerequisites
- Webhook debug logging enabled in plugin settings
- Webhook queue system active

### Test Steps
1. **Enable webhook debug:**
   - Go to WooCommerce → Settings → Checkout.com
   - Enable "Gateway Responses" (webhook debug logging)

2. **Trigger webhook queue scenario:**
   - Create a payment where webhook arrives before order
   - Or manually trigger webhook queue processing

3. **Check logs:**
   - View WooCommerce logs
   - Look for webhook queue related logs
   - Verify log format consistency

### Expected Results
- ✅ All webhook queue logs use `WEBHOOK PROCESS:` prefix
- ✅ Logs are consistent with other webhook logs
- ✅ Detailed logs when debug enabled
- ✅ Summary logs when debug disabled
- ✅ Logs show queue operations clearly

### Log Format (Expected)
```
WEBHOOK PROCESS: WEBHOOK QUEUE: Webhook queued successfully - Type: payment_approved, Payment ID: pay_xxx
WEBHOOK PROCESS: WEBHOOK QUEUE: Processing 1 pending webhook(s) for Order ID: 123
WEBHOOK PROCESS: WEBHOOK QUEUE: Successfully processed queued webhook - Order ID: 123, Type: payment_approved
```

---

## Test Case 8: Complete Payment Flow - Success Scenario

### Objective
Verify complete successful payment flow works correctly with all fixes.

### Prerequisites
- Test approved card: `4242 4242 4242 4242`
- Webhook debug enabled

### Test Steps
1. **Complete successful payment:**
   - Add products to cart
   - Go to checkout
   - Enter approved card: `4242 4242 4242 4242`
   - Complete checkout

2. **Verify order creation:**
   - Order should be created
   - Status should be `Processing` or `On-hold` (depending on capture)

3. **Verify webhook processing:**
   - Check order notes for webhook events
   - Verify payment ID in all notes
   - Verify status transitions are correct

4. **Verify cart clearing:**
   - Cart should be empty after successful payment
   - User should be redirected to order-received page

### Expected Results
- ✅ Order created successfully
- ✅ Payment authorized and captured
- ✅ Order status updated correctly
- ✅ Cart cleared after success
- ✅ Redirected to order-received page
- ✅ All order notes include Payment ID
- ✅ No status regression issues

---

## Test Case 9: 3DS Authentication Flow

### Objective
Verify 3DS authentication flow works correctly with all fixes.

### Prerequisites
- 3DS enabled in Checkout.com settings
- Test card that triggers 3DS: `4000 0027 6000 3184`

### Test Steps
1. **Initiate 3DS payment:**
   - Go to checkout
   - Enter 3DS test card: `4000 0027 6000 3184`
   - Complete checkout

2. **Complete 3DS authentication:**
   - Redirected to 3DS challenge page
   - Complete authentication
   - Redirected back to site

3. **Verify order processing:**
   - Order should be processed correctly
   - Status should be updated appropriately
   - No failed order reuse issues

### Expected Results
- ✅ 3DS redirect works correctly
- ✅ Authentication completes successfully
- ✅ Order processed after 3DS return
- ✅ Status updated correctly
- ✅ No cart clearing on failure
- ✅ Proper redirect on success/failure

---

## Test Case 10: Multiple Rapid Payment Attempts

### Objective
Verify system handles multiple rapid payment attempts correctly.

### Prerequisites
- Test declined card: `4000 0000 0000 0002`

### Test Steps
1. **First attempt (fail):**
   - Attempt payment with declined card
   - Note order number

2. **Second attempt immediately (fail):**
   - Without refreshing, try again
   - Note order number

3. **Third attempt (success):**
   - Use approved card: `4242 4242 4242 4242`
   - Complete payment

### Expected Results
- ✅ Each attempt creates a new order
- ✅ Failed orders are not reused
- ✅ Successful payment creates new order
- ✅ Cart cleared only on success
- ✅ No order conflicts

---

## Test Case 11: Webhook Order Matching - Payment Session ID

### Objective
Verify webhook queue matches orders using payment_session_id when order_id is missing.

### Prerequisites
- Flow payment with payment session ID
- Webhook arriving before order creation

### Test Steps
1. **Create Flow payment:**
   - Initiate payment via Flow
   - Note payment session ID (if available in logs)

2. **Verify webhook queuing:**
   - Webhook arrives before order creation
   - Webhook should be queued with payment_session_id

3. **Verify order matching:**
   - Order created with payment_session_id in meta
   - Queued webhook should match and process

### Expected Results
- ✅ Webhook queued with payment_session_id
- ✅ Order created with payment_session_id meta
- ✅ Queued webhook matches order via payment_session_id
- ✅ Webhook processed successfully

---

## Test Case 12: Capture Declined Webhook - Payment ID in Notes

### Objective
Verify that capture declined webhook includes Payment ID in order notes.

### Prerequisites
- Payment that gets authorized but capture fails
- Capture declined webhook configured

### Test Steps
1. **Authorize payment:**
   - Complete payment authorization successfully

2. **Trigger capture decline:**
   - Attempt to capture payment
   - Capture should be declined

3. **Check order notes:**
   - Verify capture declined note includes Payment ID
   - Verify Action ID if available

### Expected Results
- ✅ Order note shows: `Payment capture declined - Payment ID: pay_xxx, Action ID: act_xxx, Reason: [reason]`
- ✅ Payment ID present
- ✅ Action ID present (if available)

---

## Summary Checklist

After running all test cases, verify:

- [ ] Webhook queue system works correctly
- [ ] No order status regression (Processing → On-hold)
- [ ] Failed orders are not reused
- [ ] Cart only cleared on successful payment
- [ ] Redirect to checkout on failure (not order-received)
- [ ] Payment ID included in all decline webhook notes
- [ ] Webhook queue logs integrated properly
- [ ] Successful payment flow works end-to-end
- [ ] 3DS flow works correctly
- [ ] Multiple rapid attempts handled correctly
- [ ] Payment session ID matching works
- [ ] Capture declined notes include Payment ID

---

## Test Cards Reference

### Approved Cards
- `4242 4242 4242 4242` - Standard approved card
- `4000 0000 0000 3220` - Approved card (3DS optional)

### Declined Cards
- `4000 0000 0000 0002` - Declined card
- `4000 0000 0000 0069` - Expired card

### 3DS Cards
- `4000 0027 6000 3184` - 3DS challenge required
- `4000 0000 0000 3055` - 3DS authentication required

---

## Log Locations

- **WooCommerce Logs:** WooCommerce → Status → Logs → Select `checkout-com-unified-payments-api` log
- **Webhook Queue Table:** Database table `wp_cko_pending_webhooks`
- **Order Notes:** WooCommerce → Orders → Select Order → Order Notes tab

---

## Troubleshooting

### If webhook queue not working:
1. Check if table exists: `SELECT * FROM wp_cko_pending_webhooks LIMIT 1;`
2. Check webhook debug logs for queue messages
3. Verify webhook returns HTTP 200 (not 404)

### If order status regression:
1. Check order notes timeline
2. Verify webhook processing order
3. Check logs for: `Order already in advanced state`

### If failed order reused:
1. Check order meta for payment ID
2. Verify logs show: `NOT reusing`
3. Check order status is `failed`

---

## Notes

- All test cases should be run in a test/staging environment
- Use Checkout.com sandbox credentials for testing
- Enable webhook debug logging for detailed troubleshooting
- Monitor both WooCommerce logs and Checkout.com webhook logs
- Test with different payment scenarios (success, failure, 3DS)

