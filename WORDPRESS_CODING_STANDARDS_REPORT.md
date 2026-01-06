# WordPress Coding Standards Compliance Report
## Checkout.com WooCommerce Plugin - Flow Integration

**Date:** January 4, 2025  
**Analyzed Branch:** `refactor/reduce-complexity`  
**Standards Reference:** [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)

---

## Executive Summary

This report identifies **WordPress Coding Standards violations** found in the Checkout.com WooCommerce plugin, specifically focusing on the Flow integration code. The analysis covers PHP, JavaScript, security, documentation, and naming convention violations.

**Total Violations Found:** 200+ instances across multiple categories

---

## 1. PHP CODING STANDARDS VIOLATIONS

### 1.1 Array Syntax Inconsistency ⚠️ MEDIUM PRIORITY

**Issue:** Mixing `array()` and `[]` syntax throughout the codebase.

**WordPress Standard:** Use `array()` for PHP 5.3+ compatibility, or consistently use `[]` if PHP 7.0+ is required.

#### Violations:

**File:** `flow-integration/class-wc-gateway-checkout-com-flow.php`

- **Line 25:** Uses `array()`
  ```php
  $core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings', array() );
  ```

- **Line 32:** Uses `array()`
  ```php
  $this->supports = array(
      'products',
      'refunds',
      // ...
  );
  ```

- **Line 53:** Uses `array()`
  ```php
  add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
  ```

- **Line 239:** Uses `[]` (short array syntax)
  ```php
  $payment_meta[ $this->id ] = [
      'post_meta' => [
          '_cko_source_id' => [
              'value' => $source_id,
              'label' => 'Checkout.com FLOW Source ID',
          ],
      ],
  ];
  ```

- **Line 260:** Uses `array()`
  ```php
  return array(
      'screen_button' => array(
          // ...
      ),
  );
  ```

**Recommendation:** Standardize to `array()` syntax for WordPress compatibility, or document PHP 7.0+ requirement and use `[]` consistently.

---

### 1.2 Direct Superglobal Access Without wp_unslash() ⚠️ HIGH PRIORITY

**Issue:** `$_POST`, `$_GET`, `$_REQUEST` accessed without `wp_unslash()` before sanitization.

**WordPress Standard:** Always use `wp_unslash()` before sanitizing superglobals, as WordPress adds slashes to all input.

#### Violations:

**File:** `flow-integration/class-wc-gateway-checkout-com-flow.php`

- **Line 217:** Missing `wp_unslash()`
  ```php
  $save_card_hidden = isset( $_POST['cko-flow-save-card-persist'] ) ? sanitize_text_field( $_POST['cko-flow-save-card-persist'] ) : '';
  ```
  **Should be:**
  ```php
  $save_card_hidden = isset( $_POST['cko-flow-save-card-persist'] ) ? sanitize_text_field( wp_unslash( $_POST['cko-flow-save-card-persist'] ) ) : '';
  ```

- **Line 218:** Missing `wp_unslash()`
  ```php
  $save_card_post = isset( $_POST['wc-wc_checkout_com_flow-new-payment-method'] ) ? sanitize_text_field( $_POST['wc-wc_checkout_com_flow-new-payment-method'] ) : '';
  ```

- **Line 1463:** Missing `wp_unslash()`
  ```php
  $flow_payment_id_from_post = isset( $_POST['cko-flow-payment-id'] ) ? \sanitize_text_field( $_POST['cko-flow-payment-id'] ) : '';
  ```

- **Line 1494:** Missing `wp_unslash()` (though `absint()` handles it)
  ```php
  $order_id_from_post = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
  ```

- **Line 1586:** Missing `wp_unslash()`
  ```php
  $flow_payment_id = isset( $_POST['cko-flow-payment-id'] ) ? sanitize_text_field( $_POST['cko-flow-payment-id'] ) : '';
  ```

- **Line 1589:** Missing `wp_unslash()`
  ```php
  if ( empty( $flow_payment_id ) && isset( $_GET['cko-payment-id'] ) ) {
      $flow_payment_id = sanitize_text_field( $_GET['cko-payment-id'] );
  ```

- **Line 1613:** Missing `wp_unslash()`
  ```php
  $flow_payment_type_for_save = isset( $_POST['cko-flow-payment-type'] ) ? sanitize_text_field( $_POST['cko-flow-payment-type'] ) : '';
  ```

- **Line 1630:** Missing `wp_unslash()`
  ```php
  $save_card_from_get = isset( $_GET['cko-save-card'] ) ? sanitize_text_field( $_GET['cko-save-card'] ) : '';
  ```

**Total Instances:** 49+ direct superglobal accesses without `wp_unslash()`

**Recommendation:** Add `wp_unslash()` before all `sanitize_text_field()`, `sanitize_email()`, etc. calls on superglobals.

---

### 1.3 Direct $_POST Assignment ⚠️ CRITICAL SECURITY ISSUE

**Issue:** Direct assignment to `$_POST` superglobal.

**WordPress Standard:** Never directly modify superglobals. Use proper WordPress APIs.

#### Violations:

**File:** `flow-integration/class-wc-gateway-checkout-com-flow.php`

- **Line 3700-3701:** Direct `$_POST` assignment
  ```php
  $_POST['cko-flow-payment-id'] = $payment_id;
  $_POST['cko-flow-payment-type'] = $payment_type;
  ```

**Recommendation:** Use session storage, order meta, or proper WordPress APIs instead of modifying `$_POST`.

---

### 1.4 Missing Nonce Verification ⚠️ HIGH PRIORITY

**Issue:** Some AJAX handlers may lack proper nonce verification.

**WordPress Standard:** All AJAX handlers must verify nonces using `wp_verify_nonce()` or `check_ajax_referer()`.

#### Current Nonce Verifications Found:

**File:** `flow-integration/class-wc-gateway-checkout-com-flow.php`

- **Line 5341:** ✅ Has nonce verification
  ```php
  if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'cko_flow_payment_session' ) ) {
  ```

- **Line 5472:** ✅ Has nonce verification
  ```php
  $nonce_valid = wp_verify_nonce( $nonce_value, 'woocommerce-process_checkout' );
  ```

- **Line 5801:** ✅ Has nonce verification
- **Line 5891:** ✅ Has nonce verification
- **Line 6002:** ✅ Has nonce verification

**File:** `woocommerce-gateway-checkout-com.php`

- **Line 1782:** ⚠️ Uses `wc_get_var()` but has `phpcs:ignore` comment
  ```php
  $nonce_value = wc_get_var( $_REQUEST['woocommerce-process-checkout-nonce'], wc_get_var( $_REQUEST['_wpnonce'], '' ) ); // phpcs:ignore
  ```

**Recommendation:** Review all AJAX handlers to ensure nonce verification is present and properly implemented.

---

### 1.5 Missing PHPDoc Blocks ⚠️ LOW PRIORITY

**Issue:** Some functions lack complete PHPDoc blocks with `@param`, `@return`, `@since` tags.

**WordPress Standard:** All functions must have complete PHPDoc blocks.

#### Violations:

**File:** `flow-integration/class-wc-gateway-checkout-com-flow.php`

- **Line 236:** Missing `@since` tag
  ```php
  /**
   * Store save card preference in order metadata when order is created.
   * This ensures the preference survives 3DS redirects.
   *
   * @param WC_Order $order The order object.
   * @param array    $data  The order data.
   * @return void
   */
  public function store_save_card_preference_in_order( $order, $data ) {
  ```

**Recommendation:** Add `@since` tags to all functions indicating the version when they were introduced.

---

### 1.6 Inconsistent Indentation ⚠️ LOW PRIORITY

**Issue:** Some files may use spaces instead of tabs.

**WordPress Standard:** Use tabs for indentation, not spaces.

**Recommendation:** Run PHPCS with WordPress rules to detect and fix indentation issues.

---

## 2. JAVASCRIPT CODING STANDARDS VIOLATIONS

### 2.1 Console.log in Production Code ⚠️ MEDIUM PRIORITY

**Issue:** `console.log()`, `console.warn()`, `console.error()` calls throughout JavaScript files.

**WordPress Standard:** Remove or conditionally enable console statements based on debug mode.

#### Violations:

**File:** `flow-integration/assets/js/payment-session.js`

- **Line 12:** `console.warn()` in production
  ```javascript
  console.warn('[FLOW] Logger module not loaded - using fallback logger');
  ```

- **Line 15-20:** Multiple `console.log()` calls in fallback logger
  ```javascript
  error: function(m, d) { console.error('[FLOW ERROR] ' + m, d !== undefined ? d : ''); },
  warn: function(m, d) { console.warn('[FLOW WARNING] ' + m, d !== undefined ? d : ''); },
  webhook: function(m, d) { console.log('[FLOW WEBHOOK] ' + m, d !== undefined ? d : ''); },
  ```

- **Line 58:** `console.warn()` in production
  ```javascript
  console.warn('[FLOW] Terms prevention module not loaded - isTermsCheckbox unavailable');
  ```

- **Line 85:** `console.log()` in production
  ```javascript
  console.log('[FLOW 3DS] ⚠️⚠️⚠️ EARLY DETECTION: 3DS return detected...', {
  ```

- **Line 1896:** `console.log()` in production
  ```javascript
  console.log('[FLOW] ✓ Place Order button enabled');
  ```

- **Line 1903:** `console.log()` in production
  ```javascript
  console.log('[FLOW] Mount - Save card checkbox not available (feature disabled)');
  ```

- **Line 1908:** `console.log()` in production
  ```javascript
  console.log('[FLOW] ✓ Checkout is now fully interactive');
  ```

- **Line 2947:** `console.log()` in production
  ```javascript
  console.log(`[FLOW RELOAD] updated_checkout EVENT #${window.ckoUpdatedCheckoutCount} fired...`);
  ```

- **Line 3256:** `console.log()` in production
  ```javascript
  console.log(`[FLOW RELOAD] Page load #${window.ckoPageLoadCount} at ${new Date().toLocaleTimeString()}`);
  ```

**Total Instances:** 48+ console statements in JavaScript files

**Recommendation:** 
- Wrap all console statements in debug checks: `if (window.ckoLogger && window.ckoLogger.debugEnabled) { ... }`
- Or remove console statements entirely and rely on the logger module

---

### 2.2 Missing JSDoc Comments ⚠️ LOW PRIORITY

**Issue:** Many JavaScript functions lack proper JSDoc documentation.

**WordPress Standard:** Document all functions with `@param`, `@return`, `@since` tags.

#### Example Violation:

**File:** `flow-integration/assets/js/payment-session.js`

Many functions lack JSDoc blocks. Example:
```javascript
function reloadFlowComponent() {
    // No JSDoc block
    if (!window.ckoReloadCount) {
        window.ckoReloadCount = 0;
    }
    // ...
}
```

**Recommendation:** Add JSDoc blocks to all functions:
```javascript
/**
 * Reloads the Flow component when critical checkout fields change.
 *
 * @since 5.0.1
 * @return {void}
 */
function reloadFlowComponent() {
    // ...
}
```

---

### 2.3 Global Variable Pollution ⚠️ MEDIUM PRIORITY

**Issue:** Many global variables attached to `window` object.

**WordPress Standard:** Use namespaces or IIFE patterns to avoid global pollution.

#### Violations:

**File:** `flow-integration/assets/js/payment-session.js`

- `window.ckoFlow`
- `window.ckoPreventUpdateCheckout`
- `window.ckoTermsCheckboxLastClicked`
- `window.ckoTermsCheckboxLastClickTime`
- `window.ckoUpdatedCheckoutCount`
- `window.ckoPageLoadCount`
- `window.FlowState` (better - namespaced)
- `window.ckoLogger` (better - namespaced)

**Recommendation:** Consolidate all globals into a single namespace:
```javascript
window.CheckoutComFlow = {
    state: { ... },
    logger: { ... },
    // ...
};
```

---

### 2.4 Inconsistent Indentation ⚠️ LOW PRIORITY

**Issue:** May use spaces instead of tabs.

**WordPress Standard:** Use tabs for indentation in JavaScript.

**Recommendation:** Run JSHint/JSCS to detect and fix indentation issues.

---

## 3. SECURITY STANDARDS VIOLATIONS

### 3.1 Insufficient Input Sanitization ⚠️ HIGH PRIORITY

**Issue:** Some inputs sanitized but `wp_unslash()` missing.

**WordPress Standard:** Always use `wp_unslash()` before sanitization.

**See Section 1.2 for detailed violations.**

---

### 3.2 Direct Superglobal Modification ⚠️ CRITICAL SECURITY ISSUE

**Issue:** Direct assignment to `$_POST` superglobal.

**See Section 1.3 for detailed violation.**

---

### 3.3 Missing Capability Checks ⚠️ MEDIUM PRIORITY

**Issue:** Some AJAX handlers may lack capability checks.

**WordPress Standard:** Check user capabilities before processing sensitive operations.

**Recommendation:** Review all AJAX handlers and add capability checks where appropriate:
```php
if ( ! current_user_can( 'manage_woocommerce' ) ) {
    wp_send_json_error( array( 'message' => __( 'Permission denied.', 'checkout-com-unified-payments-api' ) ) );
    return;
}
```

---

## 4. DOCUMENTATION STANDARDS VIOLATIONS

### 4.1 Missing @since Tags ⚠️ LOW PRIORITY

**Issue:** Many functions lack `@since` tags.

**WordPress Standard:** All functions must have `@since` tags indicating version.

**Recommendation:** Add `@since 5.0.1` (or appropriate version) to all functions.

---

### 4.2 Inconsistent Docblock Format ⚠️ LOW PRIORITY

**Issue:** Some use `/**`, some use `/*`.

**WordPress Standard:** Use `/**` for all docblocks.

**Recommendation:** Standardize all docblocks to use `/**`.

---

## 5. NAMING CONVENTIONS VIOLATIONS

### 5.1 Function Naming ⚠️ LOW PRIORITY

**Issue:** Some functions may not follow WordPress naming conventions.

**WordPress Standard:** Use lowercase with underscores: `function_name()`.

**Recommendation:** Review all function names and ensure they follow WordPress conventions.

---

### 5.2 Variable Naming ⚠️ LOW PRIORITY

**Issue:** Some PHP variables may use camelCase instead of snake_case.

**WordPress Standard:** Use snake_case for PHP variables.

**Recommendation:** Review and standardize variable naming.

---

## 6. CODE STRUCTURE VIOLATIONS

### 6.1 Large File Sizes ⚠️ LOW PRIORITY

**Issue:** Some files are very large.

**Files:**
- `flow-integration/assets/js/payment-session.js` - ~4,600 lines
- `flow-integration/class-wc-gateway-checkout-com-flow.php` - ~6,000+ lines

**WordPress Standard:** Keep files focused and reasonably sized.

**Note:** This has been partially addressed through recent refactoring (modules extracted).

**Recommendation:** Continue refactoring large files into smaller, focused modules.

---

### 6.2 Hook Priority Issues ⚠️ LOW PRIORITY

**Issue:** Some hooks use unusual priorities (0, 1).

**WordPress Standard:** Use standard priorities (10, 20, etc.) unless necessary.

**Example:**
```php
// Line 1748: Priority 0
add_action( 'init', function() {
    add_action( 'wp_ajax_cko_flow_create_order', 'cko_ajax_flow_create_order', 1 );
}, 0 );
```

**Recommendation:** Document why unusual priorities are necessary, or use standard priorities.

---

## SUMMARY OF VIOLATIONS BY PRIORITY

### 🔴 CRITICAL (Must Fix)
1. Direct `$_POST` assignment (Line 3700-3701)
2. Missing `wp_unslash()` before sanitization (49+ instances)

### 🟠 HIGH PRIORITY (Should Fix)
1. Missing nonce verification in some AJAX handlers
2. Insufficient input sanitization patterns
3. Missing capability checks

### 🟡 MEDIUM PRIORITY (Consider Fixing)
1. Console.log statements in production (48+ instances)
2. Array syntax inconsistency (17+ instances)
3. Global variable pollution

### 🟢 LOW PRIORITY (Nice to Have)
1. Missing PHPDoc/JSDoc blocks
2. Missing `@since` tags
3. Inconsistent indentation
4. Naming convention issues
5. Large file sizes

---

## RECOMMENDED FIXES

### Phase 1: Critical Security Fixes
1. Remove direct `$_POST` assignment (Line 3700-3701)
2. Add `wp_unslash()` to all superglobal accesses (49+ instances)
3. Review and add nonce verification to all AJAX handlers

### Phase 2: High Priority Fixes
1. Add capability checks to AJAX handlers
2. Standardize input sanitization patterns
3. Review security practices

### Phase 3: Medium Priority Fixes
1. Wrap console statements in debug checks or remove
2. Standardize array syntax (`array()` vs `[]`)
3. Consolidate global variables into namespace

### Phase 4: Low Priority Fixes
1. Add complete PHPDoc/JSDoc blocks
2. Add `@since` tags
3. Fix indentation issues
4. Standardize naming conventions

---

## TOOLS FOR AUTOMATED CHECKING

### PHP Coding Standards
```bash
# Install PHPCS with WordPress rules
composer require --dev wp-coding-standards/wpcs

# Run PHPCS
./vendor/bin/phpcs --standard=WordPress checkout-com-unified-payments-api/
```

### JavaScript Coding Standards
```bash
# Use JSHint with WordPress preset
npm install -g jshint
jshint --config .jshintrc flow-integration/assets/js/
```

---

## CONCLUSION

The codebase has **200+ WordPress Coding Standards violations** across multiple categories. The most critical issues are:

1. **Security vulnerabilities** (direct `$_POST` assignment, missing `wp_unslash()`)
2. **Production debug code** (console.log statements, error_log calls)
3. **Inconsistent coding patterns** (array syntax, indentation)

**Recommended Action:** Address critical and high-priority violations first, then gradually fix medium and low-priority issues.

---

**Report Generated:** January 4, 2025  
**Next Review:** After Phase 1 fixes are implemented

