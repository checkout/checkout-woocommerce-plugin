# No Migration Needed for Saved Cards

## Summary

When merchants upgrade from the Classic Cards gateway (`wc_checkout_com_cards`) to the Flow gateway (`wc_checkout_com_flow`), **NO MIGRATION IS REQUIRED** for existing saved cards. The system now automatically displays and handles tokens from both gateways seamlessly.

## Why No Migration is Needed

### 1. Token Compatibility
Both the Classic Cards gateway and Flow gateway save the same type of tokens from Checkout.com (source IDs). These tokens are fully compatible and work identically in payment processing.

### 2. Backend Already Handles Both
The payment processing backend (lines 1210-1217 in `class-wc-gateway-checkout-com-flow.php`) already checks for tokens from BOTH gateways:

```php
elseif ( WC_Checkoutcom_Api_Request::is_using_saved_payment_method() ) {
    $token = 'wc-wc_checkout_com_flow-payment-token';

    if ( ! isset( $_POST[ $token ] ) ) {
        $token = 'wc-wc_checkout_com_cards-payment-token';  // Checks Classic Cards tokens
    }
    
    $arg = sanitize_text_field( $_POST[ $token ] );
    $result = (array) WC_Checkoutcom_Api_Request::create_payment( $order, $arg );
}
```

### 3. Flow UI Only Needed for New Cards
When a customer selects a saved card (from either gateway), the system uses the token directly via API. The Flow Web Components UI is ONLY needed when entering NEW card details - not when using saved cards.

## The Solution (Implemented in v5.0.0)

### Modified `saved_payment_methods()` Function
The `saved_payment_methods()` function now:

1. **Retrieves tokens from BOTH gateways**:
   ```php
   $flow_tokens = WC_Payment_Tokens::get_customer_tokens( $user_id, $this->id );
   $classic_tokens = WC_Payment_Tokens::get_customer_tokens( $user_id, $classic_gateway->id );
   $all_tokens = array_merge( $flow_tokens, $classic_tokens );
   ```

2. **Removes duplicates** based on token value (source ID)

3. **Displays all unique cards** to the customer with a `data-gateway-source` attribute to track which gateway the token came from

4. **Logs detailed information** for debugging

### Migration Function Deprecated
The `migrate_old_saved_cards()` function has been deprecated and replaced with a no-op that logs a deprecation notice.

## Benefits

### For Merchants:
- ✅ No manual migration steps required
- ✅ All existing saved cards appear immediately after upgrade
- ✅ Customers see all their saved cards regardless of when they were saved

### For Developers:
- ✅ Simpler codebase - no migration logic to maintain
- ✅ No risk of migration failures or data loss
- ✅ Automatic backwards compatibility

### For Customers:
- ✅ Seamless experience - all saved cards work
- ✅ No need to re-enter card details
- ✅ Can use cards saved before the upgrade

## Testing

To verify this works:

1. Create a test customer on Classic Cards gateway
2. Save a card using Classic Cards
3. Switch to Flow gateway in admin settings
4. Log in as the test customer
5. Go to checkout
6. Verify the saved card appears in the list
7. Complete a payment using the saved card
8. Verify payment processes successfully

## Logging

The system now logs token retrieval:
```
=== MULTI-GATEWAY TOKEN RETRIEVAL ===
User ID: 123
Flow tokens found: 2
Classic Cards tokens found: 3
Total tokens (before dedup): 5
Unique tokens (after dedup): 5
=== END MULTI-GATEWAY TOKEN RETRIEVAL ===
```

## Technical Details

### Token Structure
Both gateway types store tokens using WooCommerce's `WC_Payment_Token_CC` class with:
- `token`: The Checkout.com source ID (e.g., `src_abc123`)
- `gateway_id`: Either `wc_checkout_com_flow` or `wc_checkout_com_cards`
- `card_type`, `last4`, `expiry_month`, `expiry_year`: Card details

### Payment Processing
When processing a payment with a saved token:
1. Customer selects saved card
2. Token ID is submitted via POST
3. Backend retrieves token object
4. Source ID is extracted from token
5. Payment API call is made with source ID
6. **Flow UI is never needed** - just a simple API request

## Migration History

- **Prior to v5.0.0**: System attempted to migrate tokens from Classic Cards to Flow
- **v5.0.0+**: No migration needed - both token types displayed and processed automatically

## Conclusion

The key insight is that **saved card tokens don't need the Flow UI component**. Flow is only for capturing NEW card details. Since both gateways use the same Checkout.com tokens, we can simply show tokens from both gateways and let the existing backend logic handle them.

This is a much simpler, more reliable solution than attempting to migrate tokens between gateways.

