# E2E Test Run Summary

## Test Run: February 26, 2026

### Overall Results

| Project | Passed | Failed | Skipped |
|---------|--------|--------|---------|
| Storefront | 3 | 10 | 11 |

### Working Features

1. **Product Navigation & Cart**
   - Product pages load correctly
   - Add to cart works
   - Cart displays items correctly
   - Cart totals calculate correctly

2. **Checkout Form**
   - Billing address fields are filled correctly
   - Country selection (Select2) works
   - Email with unique run ID works

3. **Payment Method Selection**
   - Checkout.com Flow payment method is selected
   - Card iframes are detected and filled:
     - Cardholder name ✓
     - Card number ✓
     - Expiry date ✓
     - CVV ✓
   - Terms checkbox is accepted

### Known Issues

1. **Coupon Tests Failing**
   - Coupons `PERCENT10`, `FIXED10` don't exist in the store
   - Need to create these coupons in WooCommerce admin or update test data

2. **Payment Completion**
   - Card details are filled but "Payment form is not valid" error appears
   - Likely causes:
     - Checkout.com sandbox configuration
     - SSL certificate issues (console shows warnings)
     - Flow component validation timing

### Recommended Next Steps

1. **Verify Sandbox Configuration**
   - Ensure Checkout.com Flow is properly configured in sandbox mode
   - Check API keys and public key settings in WooCommerce

2. **Create Test Coupons**
   - Create `PERCENT10` (10% discount) coupon in WooCommerce admin
   - Create `FIXED10` (£10 fixed discount) coupon

3. **Debug Payment Flow**
   - Use the video recordings in `test-results/` to see exact behavior
   - Check browser console for JavaScript errors
   - Verify Flow component emits proper validation events

4. **Test Cards for Checkout.com Sandbox**
   - Success: `4242 4242 4242 4242`
   - Declined: `4000 0000 0000 0002`
   - 3DS: `4000 0000 0000 3220`
   - Expiry: Any future date (e.g., `12/30`)
   - CVV: Any 3 digits (e.g., `100`)

### Running Tests

```bash
# Run all storefront tests
npm run test:storefront

# Run specific test file
npx playwright test tests/storefront/guest-checkout.spec.ts --project=storefront --headed

# View test report
npm run report
```

### Artifacts Location

- Screenshots: `test-results/<test-name>/test-failed-1.png`
- Videos: `test-results/<test-name>/video.webm`
- Error Context: `test-results/<test-name>/error-context.md`
