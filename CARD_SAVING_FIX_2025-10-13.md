# Card Saving Defect Fix - October 13, 2025

## 🐛 Problem Description

Cards were getting saved incorrectly or not being saved at all when customers used the Flow payment method. The user suspected this defect was introduced during the order-pay page fixes.

## 🔍 Root Cause Analysis

The bug was in `flow-integration/class-wc-gateway-checkout-com-flow.php` at lines 1275-1278.

### The Buggy Code:
```php
$save_card_checkbox = isset( $_POST['wc-wc_checkout_com_flow-new-payment-method'] ) && $_POST['wc-wc_checkout_com_flow-new-payment-method'] === 'true';

if ( 'card' === $flow_payment_type && $save_card_checkbox ) {
    $this->flow_save_cards( $order, $flow_pay_id );
}
```

### Issues Identified:

1. **Wrong Data Source**: Checking `$_POST` directly instead of WooCommerce session
2. **Wrong Value**: Looking for `'true'` (string) instead of `'yes'` (WooCommerce standard)
3. **Missing Admin Check**: Not checking if card saving is enabled in admin settings
4. **Session Not Cleared**: Not clearing the session variable after processing
5. **Inconsistent with Classic Cards**: The classic cards gateway uses a different, correct approach

### How It Failed:

- **Regular Checkout**: The checkbox value wasn't being detected properly because:
  - WooCommerce stores checkbox values in session as `'yes'` when checked
  - The code was looking for `'true'` in `$_POST`
  
- **Order-Pay Pages**: Even worse because:
  - `$_POST` data might be completely different on order-pay pages
  - Session data might persist from previous checkouts
  - No proper clearing of session variables

## ✅ The Fix

### Fixed Code:
```php
// Check if customer wants to save card (matching Classic Cards gateway logic)
// WooCommerce stores checkbox value as 'yes' in session when checked
$save_card_enabled = WC_Admin_Settings::get_option( 'ckocom_card_saved' );
$save_card_checkbox = 'yes' === WC()->session->get( 'wc-wc_checkout_com_flow-new-payment-method' );

if ( 'card' === $flow_payment_type && $save_card_enabled && $save_card_checkbox ) {
    $this->flow_save_cards( $order, $flow_pay_id );
    // Clear the session variable after processing
    WC()->session->__unset( 'wc-wc_checkout_com_flow-new-payment-method' );
}
```

### What Changed:

1. ✅ **Proper Data Source**: Now uses `WC()->session->get()` (standard WooCommerce way)
2. ✅ **Correct Value Check**: Checks for `'yes'` (WooCommerce standard)
3. ✅ **Admin Setting Check**: Verifies card saving is enabled before attempting
4. ✅ **Session Cleanup**: Clears the session variable after processing
5. ✅ **Consistency**: Matches the logic in Classic Cards gateway (line 693)

## 📋 Reference: Classic Cards Gateway (Correct Implementation)

From `includes/class-wc-gateway-checkout-com-cards.php` line 693:
```php
if ( $save_card && 'yes' === WC()->session->get( 'wc-wc_checkout_com_cards-new-payment-method' ) ) {
    $this->save_token( $order->get_user_id(), $result );
    WC()->session->__unset( 'wc-wc_checkout_com_cards-new-payment-method' );
}
```

## 🧪 Testing Checklist

To verify the fix works correctly:

### Regular Checkout:
- [ ] Checkbox unchecked → Card should NOT be saved
- [ ] Checkbox checked → Card SHOULD be saved
- [ ] After saving → Session variable should be cleared

### Order-Pay Page:
- [ ] Guest user paying for admin-created order → No checkbox, no saving
- [ ] Logged-in user → Checkbox behavior same as regular checkout
- [ ] Multiple attempts → No duplicate cards saved

### Admin Settings:
- [ ] Card saving disabled in admin → No cards saved regardless of checkbox
- [ ] Card saving enabled in admin → Checkbox controls saving

## 🔗 Related Files

- **Fixed**: `flow-integration/class-wc-gateway-checkout-com-flow.php` (lines 1275-1284)
- **Reference**: `includes/class-wc-gateway-checkout-com-cards.php` (line 693)
- **JS Frontend**: `flow-integration/assets/js/payment-session.js` (checkbox visibility logic)

## 📅 Timeline

- **Issue Introduced**: During order-pay page fixes (trying to check `$_POST` for immediate data)
- **Issue Discovered**: October 13, 2025
- **Fix Applied**: October 13, 2025
- **Status**: ✅ Fixed

## 💡 Lesson Learned

When implementing payment gateway features:
1. Always follow WooCommerce's standard patterns for handling form data
2. Use session storage for checkout data, not direct `$_POST` access
3. Check how core WooCommerce and other gateways handle similar features
4. Always clear session variables after processing to prevent data leakage
5. Be consistent between different payment methods (Classic Cards vs Flow)

## 🎯 Expected Behavior After Fix

1. **Checkbox Checked**: Card saved to customer's account ✅
2. **Checkbox Unchecked**: Card NOT saved ✅  
3. **Admin Disabled**: No saving regardless of checkbox ✅
4. **Session Cleanup**: No leftover data between checkouts ✅
5. **Order-Pay**: Proper behavior maintained ✅

