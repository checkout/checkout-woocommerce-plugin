# Files Copied from `refactor/reduce-complexity` to `refactor/reduce-complexity-v2`

## Summary
- **Total files copied:** 13 files
- **PHP files:** 10
- **JavaScript files:** 2  
- **CSS files:** 1
- **Shell scripts:** 1 (build.sh)

---

## Complete List of Copied Files

### 1. Build Script
- `bin/build.sh` - Updated to use root directory (`PLUGIN_SOURCE_DIR="."`)

### 2. Flow Integration Files (3 files)
- `flow-integration/class-wc-gateway-checkout-com-flow.php`
- `flow-integration/assets/css/flow.css`
- `flow-integration/assets/js/payment-session.js`

### 3. API & Utility Files (2 files)
- `includes/api/class-wc-checkoutcom-api-request.php`
- `includes/api/class-wc-checkoutcom-utility.php`

### 4. Gateway Classes (4 files)
- `includes/class-wc-gateway-checkout-com-apple-pay.php`
- `includes/class-wc-gateway-checkout-com-cards.php`
- `includes/class-wc-gateway-checkout-com-google-pay.php`
- `includes/class-wc-gateway-checkout-com-paypal.php`

### 5. Settings & Admin Files (3 files)
- `includes/settings/admin/class-wc-checkoutcom-admin.php`
- `includes/settings/class-wc-checkoutcom-webhook.php`
- `includes/settings/class-wc-checkoutcom-workflows.php`

---

## Files NOT Copied (Structure Differences)

These files exist in the old branch but were NOT copied because:
- They are in `checkout-com-unified-payments-api/` directory (old structure)
- They don't exist in master (new structure has files at root)
- They are identical in both branches

### Examples:
- `checkout-com-unified-payments-api/assets/js/admin.js` → Already at `assets/js/admin.js` in master
- `checkout-com-unified-payments-api/lib/class-checkout-sdk.php` → Already at `lib/class-checkout-sdk.php` in master
- `checkout-com-unified-payments-api/includes/express/paypal/class-simulate-cart.php` → Doesn't exist in old branch

---

## Verification

All copied files have actual code differences (not just path differences) between the two branches. These represent your refactoring work, coding standards fixes, and feature improvements.
