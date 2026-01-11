# Checkout.com Flow - Logging Strategy

**Version:** 2025-10-13-FINAL-E2E  
**Date:** October 13, 2025

---

## 🎯 Overview

This document explains the centralized logging strategy for Checkout.com Flow integration. The goal is to provide **production-ready logging** that shows critical information (errors, webhooks, 3DS) while hiding debug noise.

---

## 📊 Logging Architecture

### **1. Frontend Logging (JavaScript)**

#### **Centralized Logger (`ckoLogger`)**

Located at the top of `payment-session.js`, this utility controls all console output:

```javascript
var ckoLogger = {
    debugEnabled: (typeof cko_flow_vars !== 'undefined' && cko_flow_vars.debug_logging) || false,
    
    // ALWAYS VISIBLE (Production + Debug)
    error: function(message, data) { ... },      // Critical errors
    warn: function(message, data) { ... },       // Warnings
    webhook: function(message, data) { ... },    // Webhook processing
    threeDS: function(message, data) { ... },    // 3DS authentication
    payment: function(message, data) { ... },    // Payment processing
    version: function(version) { ... },          // Version info
    
    // DEBUG ONLY (Hidden in Production)
    debug: function(message, data) { ... },      // General debugging
    performance: function(message, data) { ... } // Performance metrics
};
```

#### **Log Categories**

| Category | When Visible | Purpose | Example |
|----------|--------------|---------|---------|
| **`ckoLogger.error()`** | ✅ Always | Critical errors | Payment API failures, missing forms |
| **`ckoLogger.warn()`** | ✅ Always | Warnings | Missing data, fallbacks triggered |
| **`ckoLogger.webhook()`** | ✅ Always | Webhook events | Payment captured, order completed |
| **`ckoLogger.threeDS()`** | ✅ Always | 3DS flow | Redirect initiated, authentication completed |
| **`ckoLogger.payment()`** | ✅ Always | Payment events | Session created, payment processed |
| **`ckoLogger.version()`** | ✅ Always | Version info | Track deployed version |
| **`ckoLogger.debug()`** | ❌ Debug only | General info | Form submissions, checkbox states |
| **`ckoLogger.performance()`** | ❌ Debug only | Performance | Load times, API response times |

---

### **2. Backend Logging (PHP)**

Backend logging uses WooCommerce's built-in logger and is **already well-structured**. All backend logs are written to WooCommerce log files (accessible via WooCommerce → Status → Logs).

**Example:**
```php
WC_Checkoutcom_Utility::logger( '[FLOW] Processing payment for order: ' . $order_id );
```

**Note:** Backend logs are always enabled and visible in log files. They don't appear in browser console.

---

## ⚙️ Admin Settings

### **Debug Logging Setting**

**Location:** WooCommerce → Settings → Payments → Flow Integration

**Setting:** `flow_debug_logging`  
**Label:** "Debug Logging"  
**Description:** Enable detailed console logging for debugging

**When Enabled:**
- Shows ALL logs (errors, warnings, debug, performance, webhooks, 3DS)
- Useful for development and troubleshooting
- **Should be disabled in production**

**When Disabled (Production Mode):**
- Shows ONLY critical logs (errors, warnings, webhooks, 3DS, payment events)
- Hides debug noise (form details, checkbox states, performance metrics)
- Clean, production-ready console output

---

## 🔍 What You'll See in Production

### **✅ Always Visible (debug_logging OFF)**

```javascript
// Version
🚀 Checkout.com Flow v2025-10-13-FINAL-E2E

// Errors
[FLOW ERROR] cko_flow_vars is not defined. Flow cannot be initialized.
[FLOW ERROR] No checkout or order-pay form found after 3DS redirect

// Warnings
[FLOW WARNING] Failed to parse translation data: SyntaxError...

// 3DS Flow (CRITICAL!)
[FLOW 3DS] 3DS redirect completed - Processing payment {paymentId: "pay_xxx", status: "succeeded"}
[FLOW 3DS] Submitting order-pay form to complete order

// Payment Events
[FLOW PAYMENT] Payment Session Response: {id: "ps_xxx", ...}

// Webhooks (if implemented)
[FLOW WEBHOOK] Received: payment_captured
[FLOW WEBHOOK] Order completed via webhook
```

### **❌ Hidden in Production (debug_logging OFF)**

```javascript
// Debug details
[FLOW DEBUG] Checkbox checked: true
[FLOW DEBUG] Set payment ID in hidden input: pay_xxx
[FLOW DEBUG] onChange - Deselected saved card

// Performance
[FLOW PERFORMANCE] Flow initialization started
[FLOW PERFORMANCE] Page Load → Flow Ready: 1234.56ms
```

---

## 💡 Best Practices

### **For Development:**
1. ✅ Enable "Debug Logging" in Flow settings
2. ✅ Check browser console for detailed logs
3. ✅ Check WooCommerce logs for backend processing

### **For Production:**
1. ❌ **Disable "Debug Logging"** in Flow settings
2. ✅ Monitor browser console for errors/warnings
3. ✅ Check WooCommerce logs for payment processing
4. ✅ Always visible: Errors, 3DS flow, webhook events

### **For Troubleshooting:**
1. Enable "Debug Logging" temporarily
2. Reproduce the issue
3. Collect browser console logs + WooCommerce logs
4. Disable "Debug Logging" when done

---

## 📁 Files Modified

### **JavaScript:**
- `flow-integration/assets/js/payment-session.js` - Added `ckoLogger` utility, replaced all console.log statements
- `flow-integration/assets/js/flow-customization.js` - Updated to use `ckoLogger`
- `flow-integration/assets/js/flow-container.js` - Updated to use `ckoLogger`

### **PHP:**
- `includes/settings/class-wc-checkoutcom-cards-settings.php` - Renamed `flow_performance_logging` → `flow_debug_logging`
- `woocommerce-gateway-checkout-com.php` - Updated to pass `debug_logging` flag to JavaScript

### **Backend:**
- `flow-integration/class-wc-gateway-checkout-com-flow.php` - Removed debug logging from `process_payment()`

---

## 🎉 Benefits

1. **✅ Production-Safe** - No debug noise in production console
2. **✅ Webhook Visibility** - Always see payment confirmations
3. **✅ 3DS Debugging** - Always see authentication flow
4. **✅ Error Tracking** - Never miss failures
5. **✅ Clean Console** - Professional, minimal output
6. **✅ Easy Debugging** - Toggle one setting to see everything
7. **✅ Performance** - Reduced console output improves performance

---

## 🔧 Future Enhancements

Potential improvements for future versions:

1. **Log Levels** - Add more granular control (ERROR, WARN, INFO, DEBUG)
2. **Remote Logging** - Send critical errors to external monitoring service
3. **Log Filtering** - Filter logs by category in browser console
4. **Session Recording** - Integrate with session replay tools for debugging

---

## 📞 Support

For issues or questions about logging:
1. Check this document first
2. Enable "Debug Logging" to see detailed output
3. Collect browser console logs + WooCommerce logs
4. Contact support with logs attached

---

**Last Updated:** October 13, 2025  
**Version:** 2025-10-13-FINAL-E2E

