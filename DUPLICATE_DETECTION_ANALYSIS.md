# Duplicate Detection Analysis

## Workflow Structure Analysis

Based on the workflow you provided:

```json
{
    "id": "wf_26y2za6cbk2evjkmnkzh4ovmnm",
    "name": "https://www.wc-cko.net/?wc-api=wc_checkoutcom_webhook",
    "actions": [
        {
            "id": "wfa_igpej6jl4fjuvnngu6utkm3oca",
            "type": "webhook",
            "url": "https://www.wc-cko.net/?wc-api=wc_checkoutcom_webhook"
        }
    ]
}
```

## How Duplicate Detection Works

### Step 1: Extract Action URLs

The `extract_action_urls()` method looks for URLs in this order:

```php
foreach ( $actions as $action ) {
    // Priority 1: Direct URL in action
    if ( isset( $action['url'] ) ) {
        $urls[] = $action['url'];  // ✅ FOUND: "https://www.wc-cko.net/?wc-api=wc_checkoutcom_webhook"
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
```

**For your workflow:** Extracts `"https://www.wc-cko.net/?wc-api=wc_checkoutcom_webhook"` ✅

### Step 2: Normalize URLs

Both the target URL and action URLs are normalized:

```php
private function normalize_webhook_url( $url ): string {
    // 1. Parse URL
    $parsed = wp_parse_url( $url );
    
    // 2. Normalize host (lowercase, remove www.)
    $host = strtolower( $parsed['host'] );
    $host = str_replace( 'www.', '', $host );  // "www.wc-cko.net" → "wc-cko.net"
    
    // 3. Normalize path (remove trailing slash)
    $path = '/' . ltrim( $parsed['path'], '/' );
    if ( '/' !== $path ) {
        $path = untrailingslashit( $path );
    }
    
    // 4. Combine: host + path + query
    $normalized = $host . $path;
    if ( '' !== $parsed['query'] ) {
        $normalized .= '?' . $parsed['query'];
    }
    
    return $normalized;
}
```

**Example Normalization:**
- Original: `"https://www.wc-cko.net/?wc-api=wc_checkoutcom_webhook"`
- Normalized: `"wc-cko.net/?wc-api=wc_checkoutcom_webhook"`

### Step 3: Compare URLs

The plugin tries multiple matching strategies:

#### Strategy 1: Exact Match (Normalized)
```php
if ( $normalized_action_url === $normalized_target_url ) {
    $matches[] = $item;  // ✅ Match found!
}
```

#### Strategy 2: Match Without Query Parameters
```php
$target_without_query = strtok( $target_url, '?' );
$action_without_query = strtok( $action_url, '?' );
if ( $action_without_query === $target_without_query ) {
    $matches[] = $item;  // ✅ Match found!
}
```

#### Strategy 3: Match Path + Query Only
```php
// Ignores host differences (useful for localhost/dev)
$target_path_query = $target_parsed['path'] . '?' . $target_parsed['query'];
$action_path_query = $action_parsed['path'] . '?' . $action_parsed['query'];
if ( $target_path_query === $action_path_query ) {
    $matches[] = $item;  // ✅ Match found!
}
```

### Step 4: Count Matches

After comparing all workflows:

```php
$match_count = count( $matches );
```

### Step 5: Determine Duplicate Status

```php
if ( $match_count > 1 ) {
    // ❌ DUPLICATES DETECTED
    // Multiple workflows have the same URL
    return "Multiple webhooks registered. Please delete duplicates."
    
} elseif ( $match_count === 1 ) {
    // ✅ ALREADY REGISTERED
    // Exactly one workflow matches
    return "Webhook already registered. No action needed."
    
} else {
    // ✅ NO MATCH
    // No workflows match - safe to register
    create_new_webhook();
}
```

## Example Scenarios

### Scenario 1: No Duplicates (Good)
```
Workflows:
1. wf_xxx → URL: "https://site1.com/webhook"
2. wf_yyy → URL: "https://site2.com/webhook"

Your Site URL: "https://yoursite.com/webhook"

Matches: 0 → Safe to register ✅
```

### Scenario 2: Already Registered (Good)
```
Workflows:
1. wf_xxx → URL: "https://yoursite.com/webhook"

Your Site URL: "https://yoursite.com/webhook"

Matches: 1 → Already registered ✅
```

### Scenario 3: Duplicates Detected (Bad)
```
Workflows:
1. wf_xxx → URL: "https://yoursite.com/webhook"
2. wf_yyy → URL: "https://yoursite.com/webhook"
3. wf_zzz → URL: "https://yoursite.com/webhook"

Your Site URL: "https://yoursite.com/webhook"

Matches: 3 → Duplicates detected ❌
```

## Potential Issues

### Issue 1: URL Variations Not Detected as Duplicates

If workflows have slightly different URLs that normalize differently:

```
Workflow 1: "https://www.wc-cko.net/?wc-api=wc_checkoutcom_webhook"
Workflow 2: "https://wc-cko.net/?wc-api=wc_checkoutcom_webhook"

After normalization:
- Workflow 1: "wc-cko.net/?wc-api=wc_checkoutcom_webhook"
- Workflow 2: "wc-cko.net/?wc-api=wc_checkoutcom_webhook"

✅ These SHOULD match (www. is removed)
```

### Issue 2: Different Protocols

```
Workflow 1: "http://wc-cko.net/?wc-api=wc_checkoutcom_webhook"
Workflow 2: "https://wc-cko.net/?wc-api=wc_checkoutcom_webhook"

After normalization:
- Workflow 1: "wc-cko.net/?wc-api=wc_checkoutcom_webhook"
- Workflow 2: "wc-cko.net/?wc-api=wc_checkoutcom_webhook"

✅ These SHOULD match (protocol is ignored)
```

### Issue 3: Query Parameter Order

```
Workflow 1: "https://wc-cko.net/?wc-api=wc_checkoutcom_webhook&param=value"
Workflow 2: "https://wc-cko.net/?param=value&wc-api=wc_checkoutcom_webhook"

After normalization:
- Workflow 1: "wc-cko.net/?wc-api=wc_checkoutcom_webhook&param=value"
- Workflow 2: "wc-cko.net/?param=value&wc-api=wc_checkoutcom_webhook"

❌ These WON'T match (query order matters)
```

**Note:** This is actually correct behavior - different query parameters mean different endpoints.

## Current Implementation Status

### ✅ What Works Correctly

1. **Extracts URLs from actions array** - Finds `action['url']` correctly
2. **Normalizes URLs** - Handles www., trailing slashes, case differences
3. **Multiple matching strategies** - Tries exact, without query, path+query only
4. **Counts matches** - Correctly identifies when multiple workflows match

### ⚠️ Potential Edge Cases

1. **Query parameter order** - Different order = different URLs (this is correct)
2. **Trailing slashes** - Handled correctly by normalization
3. **Protocol differences** - Handled correctly (protocol not in normalized string)
4. **Subdomain differences** - `www.` is removed, but `staging.` vs `www.` won't match (this is correct)

## Recommendations

### If You're Seeing False Duplicates

1. **Enable Debug Logging:**
   - WooCommerce → Settings → Checkout.com
   - Enable "Gateway Responses"
   - Check `wp-content/debug.log`

2. **Check Log Output:**
   ```
   [WORKFLOW MATCH] Compare - Original: https://www.wc-cko.net/?wc-api=wc_checkoutcom_webhook, Normalized: wc-cko.net/?wc-api=wc_checkoutcom_webhook, Match: YES/NO
   ```

3. **Verify URLs:**
   - Are the URLs exactly the same after normalization?
   - Are there any subtle differences (spaces, encoding, etc.)?

### If You're Not Detecting Real Duplicates

1. **Check if workflows have actions array** - The plugin now fetches individual details if missing
2. **Verify URL extraction** - Check logs to see if URLs are being extracted correctly
3. **Check normalization** - Verify that URLs normalize to the same value

## Code Flow Summary

```
User clicks "Register Webhook"
  ↓
ajax_register_webhook()
  ↓
get_matching_workflows($webhook_url)
  ↓
For each workflow:
  ├─ Extract action URLs (extract_action_urls)
  ├─ Normalize each action URL
  ├─ Compare with normalized target URL
  └─ If match → Add to $matches array
  ↓
Count matches
  ↓
if (count > 1) → Duplicates detected ❌
if (count == 1) → Already registered ✅
if (count == 0) → Register new webhook ✅
```

## Testing Your Workflow

For your specific workflow:

**Workflow URL:** `"https://www.wc-cko.net/?wc-api=wc_checkoutcom_webhook"`

**If your site URL is:** `"https://www.wc-cko.net/?wc-api=wc_checkoutcom_webhook"`

**Normalized comparison:**
- Target: `"wc-cko.net/?wc-api=wc_checkoutcom_webhook"`
- Action: `"wc-cko.net/?wc-api=wc_checkoutcom_webhook"`
- **Match: YES** ✅

**If there are 2+ workflows with this same URL:**
- **Result: Duplicates detected** ❌

The duplicate detection should work correctly for your workflow structure!
