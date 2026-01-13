# Changes Compared to Master Branch

## Summary
This branch (`refactor/reduce-complexity-v2`) contains fixes and improvements from the `refactor/reduce-complexity` branch that were merged into a clean master branch with the correct WordPress plugin structure.

---

## Key Fixes and Changes

### 1. **Payment Method Title Detection Fix** 🎯
**Problem:** APM payments (like Alma, Klarna, etc.) were incorrectly displaying as "Pay by Card" instead of their actual payment method names.

**Files Changed:**
- `flow-integration/class-wc-gateway-checkout-com-flow.php`
- `includes/class-wc-checkout-com-webhook.php`

**Fix:** 
- Modified `get_payment_method_title_by_type()` to prioritize `source.type` for APMs
- Dynamically retrieve APM titles from WooCommerce gateway registry
- Fallback to settings mapping, then capitalize payment type
- Applied in both `handle_3ds_return()` and `process_payment()` methods

---

### 2. **Reference Field Addition** 📝
**Problem:** Missing `reference` field in payment session request.

**Files Changed:**
- `flow-integration/assets/js/payment-session.js`

**Fix:**
- Added `reference` field to `paymentSessionRequest` object
- Ensures proper order tracking in Checkout.com system

---

### 3. **Admin Menu Structure Update** 🎨
**Problem:** Old admin menu structure without proper grouping.

**Files Changed:**
- `includes/settings/admin/class-wc-checkoutcom-admin.php`

**Fix:**
- New grouped menu structure with emojis:
  - ⚡ Quick Setup
  - 💳 Card Settings
  - 📱 Express Payments
  - 📦 Order Settings
  - 🔧 Advanced
  - 🎨 Flow Settings (if Flow mode)

---

### 4. **Enhanced Logging System** 📊
**Problem:** Logging didn't respect log levels properly.

**Files Changed:**
- `includes/api/class-wc-checkoutcom-utility.php`

**Fix:**
- Implemented log level detection from message prefixes:
  - `[FLOW DEBUG]` → debug level
  - `[FLOW INFO]` → info level
  - `[FLOW WARNING]` → warning level
  - `[FLOW ERROR]` → error level
- Server-side informational logs use info level
- Proper exception logging with context

---

### 5. **Webhook Handler Improvements** 🔔
**Problem:** Webhook handling needed improvements for Flow mode.

**Files Changed:**
- `includes/class-wc-gateway-checkout-com-cards.php`
- `includes/settings/class-wc-checkoutcom-webhook.php`
- `includes/settings/class-wc-checkoutcom-workflows.php`

**Fix:**
- Conditional webhook registration based on checkout mode
- Improved webhook queue handling
- Better error handling and logging

---

### 6. **Flow Integration Enhancements** 🚀
**New Files Added:**
- `flow-integration/assets/js/modules/flow-initialization.js`
- `flow-integration/assets/js/modules/flow-logger.js`
- `flow-integration/assets/js/modules/flow-state.js`
- `flow-integration/assets/js/modules/flow-terms-prevention.js`
- `flow-integration/assets/js/modules/flow-validation.js`
- `flow-integration/assets/js/flow-container.js`
- `flow-integration/assets/js/flow-customization.js`

**Files Modified:**
- `flow-integration/class-wc-gateway-checkout-com-flow.php` - Payment processing improvements
- `flow-integration/assets/css/flow.css` - UI enhancements
- `flow-integration/assets/js/payment-session.js` - Reference field addition

---

### 7. **Admin Assets** 🎛️
**New Files Added:**
- `assets/css/admin-settings.css` - Admin styling
- `assets/js/admin-checkout-mode-toggle.js` - Mode toggle functionality
- `assets/js/admin.js` - Enhanced admin functionality

---

### 8. **Express Integration Updates** 💳
**Files Modified:**
- `assets/js/cko-apple-pay-express-integration.js`
- `assets/js/cko-google-pay-express-integration.js`
- `includes/class-wc-gateway-checkout-com-apple-pay.php`
- `includes/class-wc-gateway-checkout-com-google-pay.php`
- `includes/class-wc-gateway-checkout-com-paypal.php`

**Improvements:**
- Better express checkout handling
- Improved error handling
- Enhanced integration with Flow mode

---

### 9. **API & Utility Improvements** 🔧
**Files Modified:**
- `includes/api/class-wc-checkoutcom-api-request.php`
- `includes/api/class-wc-checkoutcom-utility.php`

**Improvements:**
- Better error handling
- Enhanced logging
- Improved utility functions

---

### 10. **Build Script Update** 📦
**Files Modified:**
- `bin/build.sh`

**Fix:**
- Updated `PLUGIN_SOURCE_DIR` from `"${PLUGIN_FOLDER}"` to `"."`
- Ensures correct WordPress plugin structure (root-level files)
- Fixes multiple installation issues

---

## File Statistics

- **Total files changed:** 21 files (modified)
- **New files added:** 7 files (Flow modules + admin assets - not yet committed)
- **Lines added:** 2,409
- **Lines removed:** 1,347
- **Net change:** +1,062 lines

**Modified Files (21):**
1. `bin/build.sh` - Build script update
2. `flow-integration/class-wc-gateway-checkout-com-flow.php` - Major payment processing improvements (+1,070 lines)
3. `flow-integration/assets/js/payment-session.js` - Reference field addition
4. `flow-integration/assets/css/flow.css` - UI enhancements
5. `flow-integration/assets/js/flow-container.js` - Container improvements
6. `flow-integration/assets/js/flow-customization.js` - Customization updates
7. `includes/api/class-wc-checkoutcom-api-request.php` - API improvements
8. `includes/api/class-wc-checkoutcom-utility.php` - Enhanced logging
9. `includes/class-wc-gateway-checkout-com-cards.php` - Webhook improvements
10. `includes/class-wc-gateway-checkout-com-apple-pay.php` - Express integration
11. `includes/class-wc-gateway-checkout-com-google-pay.php` - Express integration
12. `includes/class-wc-gateway-checkout-com-paypal.php` - Express integration
13. `includes/class-wc-checkout-com-webhook.php` - Webhook handler
14. `includes/class-wc-checkout-com-webhook-queue.php` - Queue improvements
15. `includes/settings/admin/class-wc-checkoutcom-admin.php` - Admin menu update
16. `includes/settings/class-wc-checkoutcom-webhook.php` - Webhook settings
17. `includes/settings/class-wc-checkoutcom-workflows.php` - Workflow settings
18. `assets/js/admin.js` - Admin enhancements (+466 lines)
19. `assets/js/cko-apple-pay-express-integration.js` - Apple Pay updates
20. `assets/js/cko-google-pay-express-integration.js` - Google Pay updates
21. `woocommerce-gateway-checkout-com.php` - Main plugin file updates (+506 lines)

**New Files (7 - uncommitted):**
1. `flow-integration/assets/js/modules/flow-initialization.js`
2. `flow-integration/assets/js/modules/flow-logger.js`
3. `flow-integration/assets/js/modules/flow-state.js`
4. `flow-integration/assets/js/modules/flow-terms-prevention.js`
5. `flow-integration/assets/js/modules/flow-validation.js`
6. `assets/css/admin-settings.css`
7. `assets/js/admin-checkout-mode-toggle.js`

---

## WordPress Coding Standards Applied ✅

- Proper sanitization and escaping
- Replaced `print_r()` with `wp_json_encode()`
- Changed `exit();` to `exit;`
- Proper logging with appropriate levels
- Code comments and documentation

---

## Testing Recommendations

1. ✅ Test APM payment method title display (Alma, Klarna, etc.)
2. ✅ Test Flow payment processing
3. ✅ Test admin menu navigation
4. ✅ Test webhook handling
5. ✅ Test express checkout (Apple Pay, Google Pay, PayPal)
6. ✅ Verify logging levels in debug mode
7. ✅ Test plugin installation/update

---

## Notes

- All changes maintain backward compatibility
- Structure follows WordPress plugin guidelines
- Build script creates correct zip structure
- No breaking changes introduced
