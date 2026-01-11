# Plugin Update Verification - Webhook Queue Feature

## Critical Requirements for WordPress Plugin Updates

WordPress identifies plugins by **THREE identifiers** that MUST match exactly:

### ✅ Current Plugin Identifiers (Verified)

1. **Plugin Folder Name**: `checkout-com-unified-payments-api`
   - ✅ Confirmed in plugin header comments (line 21)
   - ✅ Must be the folder name inside the zip file

2. **Main Plugin File**: `woocommerce-gateway-checkout-com.php`
   - ✅ Confirmed in plugin header comments (line 22)
   - ✅ Must be at: `checkout-com-unified-payments-api/woocommerce-gateway-checkout-com.php`

3. **Plugin Name Header**: `Checkout.com Payment Gateway`
   - ✅ Confirmed in plugin header (line 3)
   - ✅ Must match exactly (case-sensitive)

## Zip File Structure Requirements

### ✅ CORRECT Structure (Will Update Existing Plugin):
```
checkout-com-unified-payments-api-webhook-queue-YYYYMMDD-HHMMSS.zip
└── checkout-com-unified-payments-api/
    ├── woocommerce-gateway-checkout-com.php  ← Main plugin file
    ├── includes/
    │   ├── class-wc-checkout-com-webhook.php
    │   ├── class-wc-checkout-com-webhook-queue.php  ← NEW
    │   └── ...
    ├── flow-integration/
    │   └── class-wc-gateway-checkout-com-flow.php
    └── ...
```

### ❌ WRONG Structure (Will Create Duplicate Plugin):
```
checkout-com-unified-payments-api-webhook-queue-YYYYMMDD-HHMMSS.zip
├── woocommerce-gateway-checkout-com.php  ← WRONG: Files at root level
├── includes/
└── ...
```

## How to Build Correct Zip File

### Method 1: Use the Build Script

```bash
cd /Users/lalit.swain/Documents/Projects/Woocomerce-Flow
python3 build-plugin-zip.py
```

This script:
- ✅ Creates correct folder structure (`checkout-com-unified-payments-api/`)
- ✅ Verifies all three identifiers match
- ✅ Excludes unnecessary files
- ✅ Creates zip ready for WordPress upload

### Method 2: Manual Verification

1. **Extract your zip file**
2. **Check structure**:
   ```bash
   unzip -l your-plugin.zip | head -10
   ```
   Should show: `checkout-com-unified-payments-api/woocommerce-gateway-checkout-com.php`

3. **If structure is wrong**:
   - Create folder: `checkout-com-unified-payments-api`
   - Move all files into that folder
   - Re-zip only that folder

## Verification Checklist

Before distributing to merchants, verify:

- [ ] Zip file contains folder: `checkout-com-unified-payments-api/`
- [ ] Main file path: `checkout-com-unified-payments-api/woocommerce-gateway-checkout-com.php`
- [ ] Plugin Name header: `Checkout.com Payment Gateway` (exact match)
- [ ] Version number updated (if needed)
- [ ] Test upload on fresh WordPress install
- [ ] Test update on existing plugin installation

## Testing Update Behavior

1. **Install existing plugin** (version without webhook queue)
2. **Upload new zip file**
3. **Expected result**: 
   - ✅ Plugin updates (version changes)
   - ✅ Settings preserved
   - ✅ Only ONE plugin visible in plugins list
4. **If duplicate appears**:
   - ❌ Zip structure is wrong
   - ❌ Check folder name matches
   - ❌ Check main file name matches
   - ❌ Check Plugin Name header matches

## Common Issues

### Issue: "Plugin installed as separate plugin"
**Cause**: Zip file doesn't have `checkout-com-unified-payments-api/` folder  
**Fix**: Rebuild zip with correct folder structure

### Issue: "Plugin Name doesn't match"
**Cause**: Plugin Name header changed  
**Fix**: Ensure header is exactly: `Plugin Name: Checkout.com Payment Gateway`

### Issue: "Main file not found"
**Cause**: File name changed or in wrong location  
**Fix**: Ensure file is `woocommerce-gateway-checkout-com.php` inside plugin folder

## Current Plugin Information

- **Plugin Name**: Checkout.com Payment Gateway ✅
- **Version**: 5.0.0_beta
- **Folder Name**: checkout-com-unified-payments-api ✅
- **Main File**: woocommerce-gateway-checkout-com.php ✅
- **Text Domain**: checkout-com-unified-payments-api

All identifiers are correct and will allow proper updates!




