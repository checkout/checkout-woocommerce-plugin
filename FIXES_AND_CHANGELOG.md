# Fixes and Changelog

## Table of Contents
1. [Card Saving Fix](#card-saving-fix)
2. [Saved Cards Improvements](#saved-cards-improvements)
3. [Webhook Order Lookup Fix](#webhook-order-lookup-fix)
4. [Migration Information](#migration-information)
5. [Version History](#version-history)

---

## Card Saving Fix

### Date: October 13, 2025

### Problem
Cards were not being saved when customers used the Flow payment method.

### Root Cause
The code was checking `$_POST` directly instead of WooCommerce session, and looking for wrong value (`'true'` instead of `'yes'`).

### Fix
- Changed to use WooCommerce session: `WC()->session->get( 'wc-wc_checkout_com_flow-new-payment-method' )`
- Changed value check from `'true'` to `'yes'` (WooCommerce standard)
- Added admin setting check: `WC_Admin_Settings::get_option( 'ckocom_card_saved' )`
- Added session variable clearing after processing

### Files Modified
- `flow-integration/class-wc-gateway-checkout-com-flow.php`

---

## Saved Cards Improvements

### Version 2 Changes

#### Issues Fixed

1. **Redundant "Saved payment methods" Label**
   - Removed extra label above saved cards accordion
   - Accordion already has proper styling

2. **Flow Container Opacity Issue**
   - Removed opacity effects
   - Flow container now stays fully visible at all times

3. **Saved Card Deselection After Flow Load**
   - Added logic to re-select default saved card after Flow initializes
   - Prevents deselection when cardholder name is prepopulated

4. **Accordion State Management**
   - Improved accordion open/close state handling
   - Better synchronization with Flow component state

### Files Modified
- `flow-integration/class-wc-gateway-checkout-com-flow.php`
- `flow-integration/assets/js/payment-session.js`
- `assets/css/flow.css`

---

## Webhook Order Lookup Fix

### Problem
Webhooks were failing with "Invalid/Empty order_id" even when orders existed.

### Root Cause
When order was found by `_cko_payment_session_id`, the `order_id` was not being added to webhook metadata.

### Fix
- Explicitly set `data->data->metadata->order_id` when order is found by payment session ID
- Enhanced order lookup with multiple fallbacks:
  1. By order ID from metadata
  2. By payment session ID
  3. By payment ID
  4. Create new order from cart (last resort)

### Files Modified
- `flow-integration/class-wc-gateway-checkout-com-flow.php`
- `includes/class-wc-checkout-com-webhook.php`

---

## Migration Information

### No Migration Needed for Saved Cards

When merchants upgrade from Classic Cards gateway (`wc_checkout_com_cards`) to Flow gateway (`wc_checkout_com_flow`), **NO MIGRATION IS REQUIRED** for existing saved cards.

#### Why No Migration is Needed

1. **Token Compatibility**
   - Both gateways save the same type of tokens from Checkout.com (source IDs)
   - Tokens are fully compatible and work identically

2. **Backend Already Handles Both**
   - Payment processing checks for tokens from BOTH gateways
   - Automatically displays saved cards from either gateway

3. **Seamless User Experience**
   - Customers see all their previously saved cards
   - No need to re-enter payment information
   - Cards work immediately after upgrade

#### Upgrade Process

1. **Enable Flow Gateway**
   - Go to: WooCommerce → Settings → Payments → Checkout.com
   - Set "Checkout Mode" to "Flow"
   - Save settings

2. **Verify Saved Cards**
   - Test checkout with logged-in user
   - Verify saved cards appear
   - Test payment with saved card

3. **No Additional Steps Required**
   - Cards from Classic gateway automatically work
   - No database migration needed
   - No token conversion required

---

## Version History

### Version 5.0.0_beta

#### Major Changes
- **Simplified Flow Initialization**
  - Single `initializeFlowIfNeeded()` function
  - Removed complex state management
  - Better protection from `updated_checkout` events

- **Gateway Availability Fixes**
  - Dual filter approach (priority 1 and 999)
  - Override `is_available()` and `valid_for_use()` methods
  - Ensures Flow is always available when enabled

- **3DS Redirect Improvements**
  - Direct redirect to PHP endpoint
  - Smooth transition from 3DS to success page
  - Better order lookup with fallbacks

- **Error Handling**
  - Better API error handling (422, 400 errors)
  - Enhanced logging for debugging
  - User-friendly error messages

#### Bug Fixes
- Fixed syntax error in `flow-container.js`
- Fixed Flow disappearing after `updated_checkout`
- Fixed card saving for Flow payments
- Fixed webhook order lookup
- Fixed `unit_price` calculation (now includes tax)

#### Files Modified
- `flow-integration/assets/js/payment-session.js`
- `flow-integration/assets/js/flow-container.js`
- `flow-integration/class-wc-gateway-checkout-com-flow.php`
- `woocommerce-gateway-checkout-com.php`

---

## Upgrade Notes

### From Version 4.x to 5.0.0_beta

1. **Backup Your Site**
   - Full database backup
   - Plugin files backup

2. **Deactivate Old Plugin**
   - Deactivate Checkout.com plugin
   - Don't delete yet (keep as backup)

3. **Install New Version**
   - Upload new plugin zip
   - Activate plugin

4. **Configure Settings**
   - Go to: WooCommerce → Settings → Payments → Checkout.com
   - Set "Checkout Mode" to "Flow"
   - Configure API keys
   - Save settings

5. **Test Thoroughly**
   - Test regular checkout
   - Test saved cards
   - Test 3DS authentication
   - Test order-pay pages

6. **No Data Loss**
   - Saved cards continue to work
   - Order history preserved
   - Settings migrated automatically

