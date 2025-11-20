# Verify Zip File Structure - checkout-com-unified-payments-api (37).zip

## Quick Check

To verify if `checkout-com-unified-payments-api (37).zip` has the correct structure:

### Method 1: Extract and Check

1. Extract the zip file
2. Check if you see:
   ```
   checkout-com-unified-payments-api/
     ├── woocommerce-gateway-checkout-com.php  ← Should be HERE
     ├── includes/
     └── ...
   ```

### Method 2: Use Python Script

Run:
```bash
python3 check-zip-structure.py
```

### Method 3: Command Line Check

```bash
unzip -l "checkout-com-unified-payments-api (37).zip" | head -10
```

**Look for:**
- ✅ **CORRECT**: `checkout-com-unified-payments-api/woocommerce-gateway-checkout-com.php`
- ❌ **WRONG**: `woocommerce-gateway-checkout-com.php` (at root level)

## Expected Structure

The zip file MUST contain:
```
checkout-com-unified-payments-api/
  ├── woocommerce-gateway-checkout-com.php
  ├── includes/
  │   ├── class-wc-checkout-com-webhook.php
  │   ├── class-wc-checkout-com-webhook-queue.php
  │   └── ...
  ├── flow-integration/
  └── ...
```

## If Structure is Wrong

If the zip has files at root level (wrong structure), rebuild it using:

```bash
./build-correct-zip.sh
```

This will create a properly structured zip file that will UPDATE existing installations.




