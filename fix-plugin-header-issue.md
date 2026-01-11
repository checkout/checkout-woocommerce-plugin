# Fix: "Plugin does not have a valid header" Error

## Common Causes

1. **Zip file structure is wrong** - Main plugin file not in correct location
2. **File encoding issues** - BOM or wrong encoding
3. **Content before `<?php`** - Whitespace or characters before opening tag
4. **Plugin file path mismatch** - WordPress can't find the main file

## Most Likely Issue: Zip Structure

If you're getting this error, the zip file likely has **wrong structure**:

### ❌ WRONG (Causes Header Error):
```
checkout-com-unified-payments-api (37).zip
├── woocommerce-gateway-checkout-com.php  ← WordPress looks here but structure is wrong
├── includes/
└── ...
```

WordPress expects: `checkout-com-unified-payments-api/woocommerce-gateway-checkout-com.php`

### ✅ CORRECT:
```
checkout-com-unified-payments-api (37).zip
└── checkout-com-unified-payments-api/
    ├── woocommerce-gateway-checkout-com.php  ← WordPress finds it here
    ├── includes/
    └── ...
```

## Solution

### Step 1: Verify Current Zip Structure

Extract the zip and check:
```bash
unzip -l "checkout-com-unified-payments-api (37).zip" | head -10
```

### Step 2: Rebuild with Correct Structure

Use the build script:
```bash
cd /Users/lalit.swain/Documents/Projects/Woocomerce-Flow
chmod +x build-correct-zip.sh
./build-correct-zip.sh
```

This creates: `checkout-com-unified-payments-api-webhook-queue-YYYYMMDD-HHMMSS.zip`

### Step 3: Verify Plugin Header

The plugin header should be:
```php
<?php
/**
 * Plugin Name: Checkout.com Payment Gateway
 * ...
 */
```

**Must be:**
- First line: `<?php` (no BOM, no whitespace before)
- Second line: `/**`
- Third line: ` * Plugin Name: Checkout.com Payment Gateway`

## Quick Fix Script

Run this to check and fix:
```bash
python3 verify-and-fix-zip.py
```

This will:
1. Check the zip structure
2. Create corrected zip if needed
3. Verify plugin header is accessible




