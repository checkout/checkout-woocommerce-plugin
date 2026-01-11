# Zip File Structure Check - checkout-com-unified-payments-api (37).zip

## How to Check the Zip File

Since I cannot directly access the zip file due to terminal limitations, please run this command:

```bash
cd /Users/lalit.swain/Documents/Projects/Woocomerce-Flow
python3 check-zip-structure.py
```

Or manually check:

```bash
unzip -l "checkout-com-unified-payments-api (37).zip" | head -20
```

## What to Look For

### ✅ CORRECT Structure (Will Update Existing Plugin):
```
Archive: checkout-com-unified-payments-api (37).zip
  Length      Date    Time    Name
---------  ---------- -----   ----
        0  ...         ...     checkout-com-unified-payments-api/
     3525  ...         ...     checkout-com-unified-payments-api/woocommerce-gateway-checkout-com.php
        0  ...         ...     checkout-com-unified-payments-api/includes/
    ...
```

**Key**: Files start with `checkout-com-unified-payments-api/`

### ❌ WRONG Structure (Will Create Duplicate):
```
Archive: checkout-com-unified-payments-api (37).zip
  Length      Date    Time    Name
---------  ---------- -----   ----
     3525  ...         ...     woocommerce-gateway-checkout-com.php
        0  ...         ...     includes/
    ...
```

**Key**: Files are at root level (no `checkout-com-unified-payments-api/` folder)

## Solution: Build Correct Zip File

If the structure is wrong, use the build script:

```bash
chmod +x build-correct-zip.sh
./build-correct-zip.sh
```

This creates: `checkout-com-unified-payments-api-webhook-queue-YYYYMMDD-HHMMSS.zip`

## Guaranteed Correct Structure

The build script ensures:
1. ✅ Folder: `checkout-com-unified-payments-api/`
2. ✅ Main file: `checkout-com-unified-payments-api/woocommerce-gateway-checkout-com.php`
3. ✅ Plugin Name: `Checkout.com Payment Gateway`
4. ✅ All files inside plugin folder

This will UPDATE existing installations, not create duplicates.




