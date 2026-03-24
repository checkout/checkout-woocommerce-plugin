# How Duplicate Webhook Detection Works

## Overview

The plugin checks for duplicate webhooks/workflows by comparing URLs. A "duplicate" is defined as **multiple webhooks/workflows registered with the same URL** (or URLs that normalize to the same value).

## Step-by-Step Process

### 1. **Generate Current Site URL**

When you click "Register Webhook" or "Run Webhook check", the plugin first generates your site's webhook URL:

```php
$webhook_url = $this->generate_current_webhook_url();
// Example: https://yoursite.com/?wc-api=wc_checkoutcom_webhook
```

### 2. **Retrieve All Registered Webhooks**

The plugin fetches ALL webhooks/workflows from Checkout.com API:

```php
// For ABC accounts
$webhooks = $this->get_list();  // Gets all webhooks from API

// For NAS accounts  
$workflows = WC_Checkoutcom_Workflows::get_instance()->get_list();  // Gets all workflows
```

### 3. **Normalize URLs for Comparison**

URLs are normalized to handle variations that should be considered the same:

```php
private function normalize_webhook_url( $url ): string {
    // 1. Trim whitespace
    $url = trim( (string) $url );
    
    // 2. Parse URL into components
    $parsed = wp_parse_url( $url );
    
    // 3. Normalize host (lowercase, remove www.)
    $host = strtolower( $parsed['host'] );
    $host = str_replace( 'www.', '', $host );
    
    // 4. Normalize path (remove trailing slash, ensure leading slash)
    $path = '/' . ltrim( $parsed['path'], '/' );
    if ( '/' !== $path ) {
        $path = untrailingslashit( $path );
    }
    
    // 5. Combine: host + path + query
    $normalized = $host . $path;
    if ( '' !== $parsed['query'] ) {
        $normalized .= '?' . $parsed['query'];
    }
    
    return $normalized;
}
```

**Example Normalizations:**
- `https://example.com/webhook` → `example.com/webhook`
- `http://www.example.com/webhook/` → `example.com/webhook`
- `https://EXAMPLE.COM/webhook?wc-api=wc_checkoutcom_webhook` → `example.com/webhook?wc-api=wc_checkoutcom_webhook`

### 4. **Compare URLs**

The plugin compares your site URL (normalized) against each registered webhook URL (normalized):

```php
$target_url = $this->normalize_webhook_url( $webhook_url );  // Your site URL

foreach ( $webhooks as $item ) {
    $item_url = $this->normalize_webhook_url( $item['url'] );  // Registered webhook URL
    
    // Exact match after normalization
    if ( $item_url === $target_url ) {
        $matches[] = $item;  // Found a match!
    }
}
```

### 5. **Flexible Matching (Enhanced)**

The plugin also tries flexible matching to handle edge cases:

```php
// Method 1: Exact match (normalized)
if ( $normalized_item_url === $normalized_target_url ) {
    $matches[] = $item;
}

// Method 2: Match without query parameters
$target_without_query = strtok( $target_url, '?' );
$item_without_query = strtok( $item_url, '?' );
if ( $item_without_query === $target_without_query ) {
    $matches[] = $item;
}

// Method 3: Match path + query only (ignores host)
// Useful for localhost/dev environments
$target_path_query = $target_parsed['path'] . '?' . $target_parsed['query'];
$item_path_query = $item_parsed['path'] . '?' . $item_parsed['query'];
if ( $target_path_query === $item_path_query ) {
    $matches[] = $item;
}
```

### 6. **Count Matches**

After comparing all webhooks, the plugin counts how many match:

```php
$match_count = count( $matches );
```

### 7. **Determine Action Based on Count**

The plugin takes different actions based on the match count:

```php
if ( $match_count > 1 ) {
    // ❌ DUPLICATES DETECTED
    // Multiple webhooks found with the same URL
    wp_send_json_error([
        'message' => 'Multiple webhooks registered. Please delete duplicates and keep only one.'
    ]);
    
} elseif ( $match_count === 1 ) {
    // ✅ ALREADY REGISTERED
    // Exactly one webhook found - perfect!
    wp_send_json_error([
        'message' => 'Webhook already registered for this URL. No action needed.'
    ]);
    
} else {
    // ✅ NO MATCH FOUND
    // No webhook found - safe to register new one
    $this->create( $webhook_url );
}
```

## Visual Flow Diagram

```
┌─────────────────────────────────────┐
│  User clicks "Register Webhook"    │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│  Generate current site URL          │
│  https://site.com/?wc-api=...       │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│  Fetch ALL webhooks from API        │
│  [webhook1, webhook2, webhook3...] │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│  Normalize site URL                 │
│  site.com/?wc-api=wc_checkoutcom... │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│  For each webhook:                  │
│  1. Normalize webhook URL           │
│  2. Compare with normalized site URL│
│  3. If match → add to matches[]     │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│  Count matches                      │
└──────────────┬──────────────────────┘
               │
       ┌───────┴───────┐
       │               │
       ▼               ▼
┌──────────┐    ┌──────────┐    ┌──────────┐
│ 0 matches│    │1 match   │    │2+ matches│
│          │    │          │    │          │
│ Register │    │ Already  │    │ Duplicate│
│ New      │    │ Exists   │    │ Detected │
└──────────┘    └──────────┘    └──────────┘
```

## Example Scenarios

### Scenario 1: No Duplicates (Good)
```
Registered Webhooks:
1. https://site1.com/webhook
2. https://site2.com/webhook
3. https://site3.com/webhook

Your Site URL: https://yoursite.com/?wc-api=wc_checkoutcom_webhook

Result: 0 matches → Safe to register ✅
```

### Scenario 2: Already Registered (Good)
```
Registered Webhooks:
1. https://yoursite.com/?wc-api=wc_checkoutcom_webhook
2. https://othersite.com/webhook

Your Site URL: https://yoursite.com/?wc-api=wc_checkoutcom_webhook

Result: 1 match → Already registered ✅
```

### Scenario 3: Duplicates Detected (Bad)
```
Registered Webhooks:
1. https://yoursite.com/?wc-api=wc_checkoutcom_webhook
2. https://yoursite.com/?wc-api=wc_checkoutcom_webhook
3. https://yoursite.com/?wc-api=wc_checkoutcom_webhook

Your Site URL: https://yoursite.com/?wc-api=wc_checkoutcom_webhook

Result: 3 matches → Duplicates detected ❌
```

## Why Duplicates Are Bad

1. **Multiple Webhook Calls**: Each duplicate webhook will receive the same event, causing:
   - Duplicate order processing
   - Multiple emails sent
   - Confusion in order status

2. **Performance Issues**: Unnecessary API calls and processing

3. **Data Integrity**: Risk of processing the same payment multiple times

## How to Fix Duplicates

If duplicates are detected:

1. **Go to Checkout.com Dashboard**
   - Log into your Checkout.com account
   - Navigate to Settings → Webhooks (or Workflows for NAS)

2. **Identify Duplicates**
   - Look for multiple webhooks with the same URL
   - Note their IDs

3. **Delete Extras**
   - Keep only ONE webhook for your site URL
   - Delete all others

4. **Re-run Check**
   - Click "Run Webhook check" again
   - Should now show "1 match" or "Already registered"

## Code Locations

- **Duplicate Detection**: `includes/settings/class-wc-checkoutcom-webhook.php`
  - `ajax_register_webhook()` - Lines 214-250
  - `get_matching_webhooks()` - Lines 109-194
  - `normalize_webhook_url()` - Lines 73-100

- **Workflows (NAS Accounts)**: `includes/settings/class-wc-checkoutcom-workflows.php`
  - `get_matching_workflows()` - Lines 180-237
  - Similar logic but for workflows instead of webhooks

## Debugging

Enable "Gateway Responses" logging to see detailed comparison logs:

```
[WEBHOOK MATCH] Original target URL: https://yoursite.com/?wc-api=wc_checkoutcom_webhook
[WEBHOOK MATCH] Normalized target URL: yoursite.com/?wc-api=wc_checkoutcom_webhook
[WEBHOOK MATCH] Total webhooks returned: 6
[WEBHOOK MATCH] Webhook #1 - Original: https://yoursite.com/?wc-api=wc_checkoutcom_webhook, Normalized: yoursite.com/?wc-api=wc_checkoutcom_webhook, Match: YES
[WEBHOOK MATCH] Webhook #2 - Original: https://othersite.com/webhook, Normalized: othersite.com/webhook, Match: NO
...
[WEBHOOK MATCH] Final matches found: 1
```

This helps you understand exactly which webhooks match and why.
