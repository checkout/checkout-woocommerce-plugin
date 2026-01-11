# Logging Guide

## Overview

The Checkout.com WooCommerce plugin includes comprehensive logging capabilities to help debug issues and monitor payment processing.

## Logging Strategy

### Log Levels

1. **Error Logs**: Always active, critical errors
2. **Debug Logs**: Only when debug mode enabled
3. **Info Logs**: General information
4. **Warning Logs**: Non-critical issues

### Logging Configuration

#### Enable File Logging

1. Go to: **WooCommerce → Settings → Payments → Checkout.com**
2. Enable **"Debug Logging"** option
3. Save settings

#### Log File Location

- **WordPress Debug Log**: `wp-content/debug.log` (if `WP_DEBUG` is enabled)
- **Plugin Logs**: Check plugin settings for custom log location

## Logging Improvements

### Enhanced Logging Features

1. **Structured Logging**
   - Consistent log format
   - Easy to parse and search
   - Includes timestamps and context

2. **Conditional Logging**
   - Debug logs only when enabled
   - Error logs always active
   - Reduces log file size

3. **Webhook Logging**
   - Detailed webhook processing logs
   - Request/response logging
   - Error tracking

4. **Performance Logging**
   - API request timing
   - Database query timing
   - Component initialization timing

## Log Categories

### Flow Integration Logs

**Prefix**: `[FLOW DEBUG]`, `[FLOW ERROR]`, `[FLOW INFO]`

**Examples:**
```
[FLOW DEBUG] Available payment gateways count: 1
[FLOW DEBUG] Flow gateway is available: TRUE
[FLOW ERROR] Payment session creation failed: 422
```

### Gateway Availability Logs

**Prefix**: `[FLOW AVAILABILITY]`

**Examples:**
```
[FLOW AVAILABILITY] Gateway enabled: YES
[FLOW AVAILABILITY] Checkout mode: flow
[FLOW AVAILABILITY] is_available() result: TRUE
```

### Webhook Logs

**Prefix**: `[WEBHOOK]`

**Examples:**
```
[WEBHOOK] Processing payment_captured event
[WEBHOOK] Order found by payment_session_id: abc123
[WEBHOOK] Order processing completed: SUCCESS
```

### API Request Logs

**Prefix**: `[API REQUEST]`

**Examples:**
```
[API REQUEST] Creating payment session
[API REQUEST] Response status: 201
[API REQUEST] Request duration: 234ms
```

## Diagnostic Logging

### Client-Side Logging

The plugin includes diagnostic console logs for troubleshooting:

**Prefix**: `🔍 DIAGNOSTIC:`, `[FLOW CONTAINER]`, `[FLOW DEBUG]`

**Examples:**
```javascript
🔍 DIAGNOSTIC: initializeFlowIfNeeded() called
[FLOW CONTAINER] Script loaded
[FLOW CONTAINER] ✅ Created flow-container id on payment_box div
```

### When to Use Diagnostic Logs

- Troubleshooting Flow initialization issues
- Debugging container creation problems
- Tracking `updated_checkout` events
- Verifying deployment

## Best Practices

### 1. Enable Logging During Development

Always enable debug logging when developing or troubleshooting.

### 2. Monitor Log File Size

- Regularly rotate log files
- Archive old logs
- Don't leave debug logging enabled in production

### 3. Use Structured Logging

Include context in log messages:
```php
WC_Checkoutcom_Utility::logger('[FLOW DEBUG] Payment session created', [
    'session_id' => $session_id,
    'order_id' => $order_id,
    'amount' => $amount
]);
```

### 4. Log Errors Immediately

Always log errors with full context:
```php
WC_Checkoutcom_Utility::logger('[FLOW ERROR] Payment failed', [
    'error' => $error_message,
    'order_id' => $order_id,
    'payment_id' => $payment_id
]);
```

## Conditional Logging

### Webhook Logging

Webhook logging is controlled by the `cko_gateway_responses` setting:

- **Enabled**: Full debug logs for webhooks
- **Disabled**: Only error logs

**To enable:**
1. Go to: WooCommerce → Settings → Payments → Checkout.com
2. Enable **"Log Gateway Responses"**
3. Save settings

### Debug Logging

Debug logs are controlled by the `cko_file_logging` setting:

- **Enabled**: All debug logs written to file
- **Disabled**: Only error logs written to file

**To enable:**
1. Go to: WooCommerce → Settings → Payments → Checkout.com
2. Enable **"Debug Logging"**
3. Save settings

## Log Analysis

### Common Log Patterns

#### Successful Payment
```
[FLOW DEBUG] Payment session created
[FLOW DEBUG] Flow component initialized
[WEBHOOK] Processing payment_captured event
[WEBHOOK] Order processing completed: SUCCESS
```

#### Failed Payment
```
[FLOW ERROR] Payment session creation failed: 422
[FLOW ERROR] API Error: amount_should_be_equal_to_sum_of_all_items_unit_price_times_quantity
```

#### Gateway Availability Issue
```
[FLOW DEBUG] Available payment gateways count: 0
[FLOW DEBUG] CRITICAL: Flow gateway NOT available during checkout processing!
```

## Troubleshooting with Logs

### Issue: Flow Not Loading

**Check logs for:**
- `[FLOW CONTAINER] Script loaded`
- `🔍 DIAGNOSTIC: initializeFlowIfNeeded() called`
- `[FLOW ERROR]` entries

### Issue: Payment Failing

**Check logs for:**
- `[API REQUEST]` entries
- `[FLOW ERROR]` entries
- `[WEBHOOK]` entries

### Issue: Gateway Not Available

**Check logs for:**
- `[FLOW AVAILABILITY]` entries
- `[FLOW DEBUG] Available payment gateways count`
- `[FLOW DEBUG] FORCING Flow gateway into available gateways list`

## Log Retention

### Recommended Settings

- **Development**: Keep all logs, rotate weekly
- **Staging**: Keep logs for 30 days
- **Production**: Keep error logs only, rotate daily

### Log Rotation

Use server-level log rotation or WordPress plugins to manage log file size.

---

## Summary

- Enable debug logging during development
- Use structured logging with context
- Monitor log file size
- Use conditional logging to reduce noise
- Analyze logs for patterns when troubleshooting

