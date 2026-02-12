# Workflow Actions Fetch Fix

## Problem

The Checkout.com `/workflows` list endpoint (`GET /workflows`) does **not** include the `actions` array in the response. This means:

1. The plugin couldn't extract webhook URLs from workflows
2. Matching workflows by URL failed
3. Users saw "No workflows found" even when workflows existed

### API Response Structure

**List Endpoint (`GET /workflows`):**
```json
{
  "data": [
    {
      "id": "wf_xxx",
      "name": "WC-CKO staging2.fundedtradingplus.com",
      "active": true,
      "created_at": "...",
      "updated_at": "...",
      "_links": {...}
      // ❌ NO "actions" ARRAY
    }
  ]
}
```

**Individual Workflow Endpoint (`GET /workflows/{id}`):**
```json
{
  "id": "wf_xxx",
  "name": "WC-CKO staging2.fundedtradingplus.com",
  "active": true,
  "actions": [
    {
      "type": "webhook",
      "url": "https://staging2.fundedtradingplus.com/?wc-api=wc_checkoutcom_webhook"
    }
  ]
  // ✅ INCLUDES "actions" ARRAY
}
```

## Solution

The plugin now:

1. **Fetches Individual Workflow Details** when `actions` array is missing
2. **Caches Results** to avoid repeated API calls
3. **Falls Back to Name Matching** for older workflows

### Implementation Details

#### 1. New Method: `fetch_workflow_details()`

Fetches individual workflow details including the `actions` array:

```php
private function fetch_workflow_details( $workflow_id ) {
    // Check cache first
    if ( isset( $this->workflow_details_cache[ $workflow_id ] ) ) {
        return $this->workflow_details_cache[ $workflow_id ];
    }
    
    // Fetch from API: GET /workflows/{id}
    $workflow_url = rtrim( $this->url, '/' ) . '/' . $workflow_id;
    $response = wp_remote_get( $workflow_url, ... );
    
    // Cache and return
    $this->workflow_details_cache[ $workflow_id ] = $workflow_data;
    return $workflow_data;
}
```

#### 2. Updated Matching Logic

```php
foreach ( $workflows as $item ) {
    // Check if actions are missing
    $has_actions = isset( $item['actions'] ) && !empty( $item['actions'] );
    
    // Fetch individual details if actions missing
    if ( !$has_actions && !empty( $item['id'] ) ) {
        $workflow_details = $this->fetch_workflow_details( $item['id'] );
        if ( $workflow_details && isset( $workflow_details['actions'] ) ) {
            $item['actions'] = $workflow_details['actions'];
        }
    }
    
    // Now extract URLs from actions
    $action_urls = $this->extract_action_urls( $item );
    // ... match URLs ...
}
```

#### 3. Improved Name-Based Fallback

Enhanced fallback logic to handle:
- Full URLs in name field: `"https://site.com/?wc-api=..."`
- Hostname-only names: `"WC-CKO staging2.fundedtradingplus.com"`
- Extracts hostname and compares with target URL hostname

## Performance Considerations

### Caching

- **Workflow details are cached** per request to avoid repeated API calls
- Cache is stored in `$this->workflow_details_cache` array
- Each workflow is fetched only once per request

### API Calls

**Before Fix:**
- 1 API call: `GET /workflows` (list)
- Total: **1 call**

**After Fix:**
- 1 API call: `GET /workflows` (list)
- N API calls: `GET /workflows/{id}` for each workflow missing actions
- Total: **1 + N calls** (but cached, so only once per workflow)

**Optimization:**
- Only fetches details for workflows that don't have actions
- Caches results to avoid repeated fetches
- Falls back to name matching when API fetch fails

## Example Flow

### Scenario: 9 Workflows, None Have Actions in List Response

1. **Fetch List** → `GET /workflows`
   - Returns 9 workflows without `actions`

2. **For Each Workflow:**
   - Check if `actions` exists → NO
   - Fetch individual details → `GET /workflows/{id}`
   - Extract action URLs from fetched details
   - Compare URLs with target URL
   - Cache the fetched details

3. **Result:**
   - Finds matching workflows based on actual webhook URLs
   - Shows correct status to user

## Benefits

1. ✅ **Accurate Matching**: Uses actual webhook URLs from workflow actions
2. ✅ **Performance**: Caches results to minimize API calls
3. ✅ **Reliability**: Falls back to name matching if API fetch fails
4. ✅ **Backward Compatible**: Still works with workflows that have actions in list response

## Testing

To verify the fix works:

1. **Enable Debug Logging:**
   - WooCommerce → Settings → Checkout.com
   - Enable "Gateway Responses"

2. **Run Webhook Check:**
   - Click "Run Webhook check"
   - Check `wp-content/debug.log`

3. **Expected Log Output:**
```
[WORKFLOW MATCH] Total workflows returned: 9
[WORKFLOW MATCH] Actions missing for workflow wf_xxx, fetching details...
[WORKFLOW FETCH] Fetching details for workflow ID: wf_xxx
[WORKFLOW FETCH] Successfully fetched workflow wf_xxx - Has actions: YES (1)
[WORKFLOW MATCH] Successfully fetched 1 actions for workflow wf_xxx
[WORKFLOW MATCH] Compare - Original: https://staging2.fundedtradingplus.com/?wc-api=wc_checkoutcom_webhook, Normalized: staging2.fundedtradingplus.com/?wc-api=wc_checkoutcom_webhook, Match: YES
[WORKFLOW MATCH] Final matches found: 1
```

## Files Modified

- `includes/settings/class-wc-checkoutcom-workflows.php`
  - Added `fetch_workflow_details()` method
  - Added `$workflow_details_cache` property
  - Updated `get_matching_workflows()` to fetch details when needed
  - Enhanced name-based fallback matching

## Notes

- The fix is backward compatible
- Only fetches details when necessary (when actions are missing)
- Caches results to optimize performance
- Gracefully handles API failures with fallback matching
