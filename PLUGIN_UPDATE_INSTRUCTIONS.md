# How to Update the Plugin Without Creating Duplicates

## Problem
WordPress is creating a new plugin installation instead of updating the existing one.

## Solution

WordPress identifies plugins by:
1. **Plugin folder name** (must match exactly)
2. **Main plugin file name** (must match exactly)
3. **Plugin Name header** (should match)

## Current Plugin Structure
- **Folder Name**: `checkout-com-unified-payments-api`
- **Main File**: `woocommerce-gateway-checkout-com.php`
- **Plugin Name**: `Checkout.com Payment Gateway`

## Steps to Update Properly

### Method 1: Replace Existing Plugin (Recommended)

1. **Before Uploading**:
   - Go to **Plugins → Installed Plugins**
   - Find "Checkout.com Payment Gateway"
   - **Deactivate** the plugin
   - **Delete** the old plugin (this won't delete settings - they're in the database)

2. **Upload New Version**:
   - Go to **Plugins → Add New → Upload Plugin**
   - Choose the new zip file
   - Click **Install Now**
   - Click **Activate Plugin**

### Method 2: Use FTP/File Manager (Faster)

1. **Access WordPress Plugin Directory**:
   - Navigate to: `/wp-content/plugins/checkout-com-unified-payments-api/`

2. **Backup Current Version** (optional):
   - Rename current folder to `checkout-com-unified-payments-api-backup`

3. **Upload New Version**:
   - Extract the zip file
   - Upload the `checkout-com-unified-payments-api` folder to `/wp-content/plugins/`
   - Replace all files

4. **Activate**:
   - Go to **Plugins → Installed Plugins**
   - The plugin should show as updated
   - Reactivate if needed

### Method 3: Use WP-CLI (If Available)

```bash
# Deactivate plugin
wp plugin deactivate checkout-com-unified-payments-api

# Delete old version
wp plugin delete checkout-com-unified-payments-api

# Install new version
wp plugin install /path/to/checkout-com-woocommerce-plugin-5.0.0-beta-*.zip --activate
```

## Why This Happens

WordPress creates a new plugin if:
- The folder name doesn't match exactly
- The main plugin file name is different
- The Plugin Name header changed significantly
- WordPress cache needs to be cleared

## Verification

After updating, verify:
1. Go to **Plugins → Installed Plugins**
2. You should see only **ONE** "Checkout.com Payment Gateway" plugin
3. Check the version number matches: `5.0.0_beta`
4. Settings should be preserved (they're stored in the database)

## If You Still See Duplicates

1. **Delete all instances** of the plugin
2. **Clear WordPress cache** (if using a caching plugin)
3. **Re-upload** the plugin fresh
4. **Reconfigure settings** (they might be lost if you deleted the plugin)








