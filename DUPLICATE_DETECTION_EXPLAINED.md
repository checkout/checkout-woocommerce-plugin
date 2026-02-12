# How Duplicate Detection Works - Complete Explanation

## Overview

Duplicate detection prevents registering multiple webhooks/workflows for the same site URL. A "duplicate" is defined as **multiple webhooks/workflows registered with URLs that match your site's webhook URL** (after normalization).

## Step-by-Step Process

### Step 1: Generate Current Site URL

When you click "Register Webhook" or "Run Webhook check":

```php
$webhook_url = $this->generate_current_webhook_url();
// Example: https://www.wc-cko.net/?wc-api=wc_checkoutcom_webhook
```

**Function:** `generate_current_webhook_url()`
- Uses WordPress `home_url()` to get your site URL
- Appends `?wc-api=wc_checkoutcom_webhook` query parameter
- Returns: `https://yoursite.com/?wc-api=wc_checkoutcom_webhook`

### Step 2: Retrieve All Registered Webhooks/Workflows

The plugin fetches ALL webhooks/workflows from Checkout.com API:

**For ABC Accounts:**
```php
$webhooks = $this->get_list();
// Calls: GET /webhooks
// Returns: Array of webhook objects with 'url' field
```

**For NAS Accounts:**
```php
$workflows = WC_Checkoutcom_Workflows::get_instance()->get_list();
// Calls: GET /workflows
// Returns: Array of workflow objects (may not include 'actions' array)
```

**Important:** The `/workflows` list endpoint doesn't include the `actions` array, so the plugin fetches individual workflow details when needed.

### Step 3: Normalize URLs for Comparison

URLs are normalized to handle variations that should be considered the same:

```php
private function normalize_webhook_url( $url ): string {
    // 1. Trim whitespace
    $url = trim( (string) $url );
    
    // 2. Parse URL into components
    $parsed = wp_parse_url( $url );
    
    // 3. Normalize host (lowercase, remove www.)
    $host = strtolower( $parsed['host'] );
    $host = str_replace( 'www.', '', $host );  // "www.example.com" → "example.com"
    
    // 4. Normalize path (remove trailing slash, ensure leading slash)
    $path = '/' . ltrim( $parsed['path'], '/' );
    if ( '/' !== $path ) {
        $path = untrailingslashit( $path );  // "/webhook/" → "/webhook"
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
- `https://www.example.com/webhook/` → `example.com/webhook`
- `http://EXAMPLE.COM/webhook` → `example.com/webhook`
- `https://example.com/webhook?wc-api=wc_checkoutcom_webhook` → `example.com/webhook?wc-api=wc_checkoutcom_webhook`

### Step 4: Extract Action URLs (NAS Accounts Only)

For workflows, extract URLs from the `actions` array:

```php
public function extract_action_urls( array $item ): array {
    $urls = [];
    $actions = isset( $item['actions'] ) && is_array( $item['actions'] ) ? $item['actions'] : [];
    
    foreach ( $actions as $action ) {
        // Priority 1: Direct URL in action
        if ( isset( $action['url'] ) ) {
            $urls[] = $action['url'];
            continue;
        }
        // Priority 2: URL in configuration
        if ( isset( $action['configuration']['url'] ) ) {
            $urls[] = $action['configuration']['url'];
            continue;
        }
        // Priority 3: URL in configuration.destination
        if ( isset( $action['configuration']['destination']['url'] ) ) {
            $urls[] = $action['configuration']['destination']['url'];
        }
    }
    
    return $urls;
}
```

**If actions are missing:** The plugin fetches individual workflow details using `GET /workflows/{id}` to get the `actions` array.

### Step 5: Compare URLs Using Multiple Strategies

The plugin tries multiple matching strategies in order:

#### Strategy 1: Exact Match (Normalized)

```php
$normalized_target = normalize_webhook_url( $target_url );
$normalized_action = normalize_webhook_url( $action_url );

if ( $normalized_target === $normalized_action ) {
    $matches[] = $item;  // ✅ Match found!
}
```

**Example:**
- Target: `https://www.wc-cko.net/?wc-api=wc_checkoutcom_webhook`
- Normalized: `wc-cko.net/?wc-api=wc_checkoutcom_webhook`
- Action: `https://wc-cko.net/?wc-api=wc_checkoutcom_webhook`
- Normalized: `wc-cko.net/?wc-api=wc_checkoutcom_webhook`
- **Match: YES** ✅

#### Strategy 2: Match Without Query Parameters

```php
$target_without_query = strtok( $normalized_target, '?' );
$action_without_query = strtok( $normalized_action, '?' );

if ( $target_without_query === $action_without_query ) {
    $matches[] = $item;  // ✅ Match found!
}
```

**Example:**
- Target: `wc-cko.net/?wc-api=wc_checkoutcom_webhook`
- Without query: `wc-cko.net/`
- Action: `wc-cko.net/?wc-api=wc_checkoutcom_webhook&other=param`
- Without query: `wc-cko.net/`
- **Match: YES** ✅

#### Strategy 3: Path + Query Only (Restricted)

**⚠️ IMPORTANT:** This strategy is now restricted to prevent false matches!

```php
// Only use path+query matching when:
// 1. BOTH URLs are localhost/dev/test, OR
// 2. Hostnames match exactly

$target_host = parse_url( $target_url )['host'];
$action_host = parse_url( $action_url )['host'];

$is_localhost_target = is_localhost_or_dev( $target_host );
$is_localhost_action = is_localhost_or_dev( $action_host );

if ( ( $is_localhost_target && $is_localhost_action ) || $target_host === $action_host ) {
    // Compare path+query only
    $target_path_query = $target_parsed['path'] . '?' . $target_parsed['query'];
    $action_path_query = $action_parsed['path'] . '?' . $action_parsed['query'];
    
    if ( $target_path_query === $action_path_query ) {
        $matches[] = $item;  // ✅ Match found!
    }
}
```

**What is localhost/dev/test?**
- `localhost`, `127.0.0.1`, `::1`
- IP addresses (e.g., `192.168.1.1`)
- Domains with `.dev`, `.test`, `.local`
- Domains with `dev.`, `test.`, `staging.` prefix
- Domains containing `ngrok`, `tunnel`

**Example - Will Match:**
- Target: `http://localhost/?wc-api=wc_checkoutcom_webhook`
- Action: `http://127.0.0.1/?wc-api=wc_checkoutcom_webhook`
- Both are localhost → **Match: YES** ✅

**Example - Will NOT Match (Fixed):**
- Target: `https://www.wc-cko.net/?wc-api=wc_checkoutcom_webhook`
- Action: `https://checkout-dev.rt.gw/?wc-api=wc_checkoutcom_webhook`
- Different production domains → **Match: NO** ❌ (Previously matched incorrectly!)

#### Strategy 4: Name Field Fallback (Workflows Only)

For older workflows that store URL in the name field:

```php
// Check if name contains full URL
$name_url = normalize_webhook_url( $item['name'] );
if ( $name_url === $target_url ) {
    $matches[] = $item;  // ✅ Match found!
}

// Or check if name contains matching hostname
$target_host = extract_hostname( $target_url );
$name_host = extract_hostname( $item['name'] );
if ( $target_host === $name_host ) {
    $matches[] = $item;  // ✅ Match found!
}
```

### Step 6: Count Matches

After comparing all webhooks/workflows:

```php
$match_count = count( $matches );
```

### Step 7: Determine Duplicate Status

```php
if ( $match_count > 1 ) {
    // ❌ DUPLICATES DETECTED
    // Multiple webhooks/workflows have the same URL
    return "Multiple webhooks registered. Please delete duplicates."
    
} elseif ( $match_count === 1 ) {
    // ✅ ALREADY REGISTERED
    // Exactly one webhook/workflow matches
    return "Webhook already registered. No action needed."
    
} else {
    // ✅ NO MATCH
    // No webhooks/workflows match - safe to register
    create_new_webhook();
}
```

## Visual Flow Diagram

```
┌─────────────────────────────────────┐
│  User clicks "Register Webhook"     │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│  Generate current site URL          │
│  https://site.com/?wc-api=...        │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│  Fetch ALL webhooks/workflows       │
│  from Checkout.com API               │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│  For NAS: Fetch individual          │
│  workflow details if actions missing │
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
│  For each webhook/workflow:         │
│  1. Extract URL(s)                  │
│  2. Normalize each URL              │
│  3. Try Strategy 1: Exact match     │
│  4. Try Strategy 2: Without query  │
│  5. Try Strategy 3: Path+query      │
│     (only if localhost or same host)│
│  6. Try Strategy 4: Name field      │
│  7. If match → add to matches[]     │
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
Registered Workflows:
1. https://site1.com/webhook → URL: "https://site1.com/webhook"
2. https://site2.com/webhook → URL: "https://site2.com/webhook"
3. https://site3.com/webhook → URL: "https://site3.com/webhook"

Your Site URL: https://yoursite.com/?wc-api=wc_checkoutcom_webhook

Normalized Comparisons:
- site1.com/webhook ≠ yoursite.com/?wc-api=wc_checkoutcom_webhook → NO MATCH
- site2.com/webhook ≠ yoursite.com/?wc-api=wc_checkoutcom_webhook → NO MATCH
- site3.com/webhook ≠ yoursite.com/?wc-api=wc_checkoutcom_webhook → NO MATCH

Result: 0 matches → Safe to register ✅
```

### Scenario 2: Already Registered (Good)
```
Registered Workflows:
1. https://yoursite.com/?wc-api=wc_checkoutcom_webhook

Your Site URL: https://yoursite.com/?wc-api=wc_checkoutcom_webhook

Normalized:
- Target: yoursite.com/?wc-api=wc_checkoutcom_webhook
- Action: yoursite.com/?wc-api=wc_checkoutcom_webhook

Result: 1 match → Already registered ✅
```

### Scenario 3: Real Duplicates (Bad)
```
Registered Workflows:
1. https://yoursite.com/?wc-api=wc_checkoutcom_webhook
2. https://yoursite.com/?wc-api=wc_checkoutcom_webhook
3. https://yoursite.com/?wc-api=wc_checkoutcom_webhook

Your Site URL: https://yoursite.com/?wc-api=wc_checkoutcom_webhook

Normalized:
- All normalize to: yoursite.com/?wc-api=wc_checkoutcom_webhook

Result: 3 matches → Duplicates detected ❌
```

### Scenario 4: False Duplicates (Fixed!)

**Before Fix:**
```
Registered Workflows:
1. https://checkout-dev.rt.gw/?wc-api=wc_checkoutcom_webhook
2. https://dev.goldencbd.fr/?wc-api=wc_checkoutcom_webhook
3. https://checkoutdev.rtmake.com/?wc-api=wc_checkoutcom_webhook

Your Site URL: https://www.wc-cko.net/?wc-api=wc_checkoutcom_webhook

Old Logic (path+query only):
- Target path+query: /?wc-api=wc_checkoutcom_webhook
- Action 1 path+query: /?wc-api=wc_checkoutcom_webhook → MATCH ❌ (WRONG!)
- Action 2 path+query: /?wc-api=wc_checkoutcom_webhook → MATCH ❌ (WRONG!)
- Action 3 path+query: /?wc-api=wc_checkoutcom_webhook → MATCH ❌ (WRONG!)

Result: 3 matches → False duplicates detected ❌
```

**After Fix:**
```
Registered Workflows:
1. https://checkout-dev.rt.gw/?wc-api=wc_checkoutcom_webhook
2. https://dev.goldencbd.fr/?wc-api=wc_checkoutcom_webhook
3. https://checkoutdev.rtmake.com/?wc-api=wc_checkoutcom_webhook

Your Site URL: https://www.wc-cko.net/?wc-api=wc_checkoutcom_webhook

New Logic (restricted path+query):
- Target host: wc-cko.net (localhost/dev: NO)
- Action 1 host: checkout-dev.rt.gw (localhost/dev: NO)
- Different production domains → Skip path+query match ✅
- Action 2 host: dev.goldencbd.fr (localhost/dev: NO)
- Different production domains → Skip path+query match ✅
- Action 3 host: checkoutdev.rtmake.com (localhost/dev: NO)
- Different production domains → Skip path+query match ✅

Result: 0 matches → Safe to register ✅
```

## Matching Strategies Summary

| Strategy | When Used | Example Match |
|----------|-----------|---------------|
| **Exact Match** | Always tried first | `example.com/webhook` = `example.com/webhook` |
| **Without Query** | If exact fails | `example.com/webhook` = `example.com/webhook?param=value` |
| **Path+Query Only** | Only if both localhost OR same host | `localhost/webhook` = `127.0.0.1/webhook` |
| **Name Field** | Workflows only, if actions empty | Name contains URL or hostname |

## Why Duplicates Are Bad

1. **Multiple Webhook Calls**: Each duplicate receives the same event
2. **Duplicate Processing**: Same order processed multiple times
3. **Data Integrity**: Risk of processing same payment multiple times
4. **Performance**: Unnecessary API calls and processing

## The Recent Fix

### Problem
The "path+query only" strategy was matching workflows with different production domains, causing false duplicate detection.

### Solution
Restricted path+query matching to only work when:
- Both URLs are localhost/dev/test environments, OR
- Hostnames match exactly

### Impact
- ✅ Eliminates false duplicate detection
- ✅ Still works for localhost/dev environments
- ✅ More accurate matching for production domains

## Code Locations

- **Duplicate Detection**: `includes/settings/class-wc-checkoutcom-webhook.php`
  - `ajax_register_webhook()` - Lines 214-250
  - `get_matching_webhooks()` - Lines 109-194
  - `normalize_webhook_url()` - Lines 73-100
  - `is_localhost_or_dev()` - Lines 66-100 (NEW)

- **Workflows (NAS Accounts)**: `includes/settings/class-wc-checkoutcom-workflows.php`
  - `get_matching_workflows()` - Lines 262-317
  - `extract_action_urls()` - Lines 234-252
  - `fetch_workflow_details()` - Lines 159-220 (NEW)
  - `is_localhost_or_dev()` - Lines 137-175 (NEW)

## Debugging

Enable "Gateway Responses" logging to see detailed comparison logs:

```
[WORKFLOW MATCH] Original target URL: https://www.wc-cko.net/?wc-api=wc_checkoutcom_webhook
[WORKFLOW MATCH] Normalized target URL: wc-cko.net/?wc-api=wc_checkoutcom_webhook
[WORKFLOW MATCH] Compare - Original: https://checkout-dev.rt.gw/?wc-api=wc_checkoutcom_webhook
[WORKFLOW MATCH] Skipping path+query match - different production domains
[WORKFLOW MATCH]   Target host: wc-cko.net (localhost/dev: NO)
[WORKFLOW MATCH]   Action host: checkout-dev.rt.gw (localhost/dev: NO)
[WORKFLOW MATCH] FINAL RESULT: 0 match(es) found
```

This helps you understand exactly which workflows match and why.
