#!/bin/bash
# Build WordPress plugin zip with CORRECT structure for updates
# This ensures merchants won't see duplicate plugins

set -e

# Get script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Plugin identifiers (MUST match existing installation)
PLUGIN_FOLDER="checkout-com-unified-payments-api"
MAIN_FILE="woocommerce-gateway-checkout-com.php"
PLUGIN_NAME="Checkout.com Payment Gateway"

echo "🔍 Verifying plugin identifiers..."
echo "   Folder: $PLUGIN_FOLDER"
echo "   Main file: $MAIN_FILE"
echo "   Plugin Name: $PLUGIN_NAME"
echo ""

# Verify main file exists
if [ ! -f "$MAIN_FILE" ]; then
    echo "❌ ERROR: Main plugin file not found: $MAIN_FILE"
    exit 1
fi

# Verify Plugin Name in header
if ! grep -q "Plugin Name: $PLUGIN_NAME" "$MAIN_FILE"; then
    echo "⚠️  WARNING: Plugin Name header might not match!"
else
    echo "✅ Plugin Name header verified"
fi

# Create generic zip name for client distribution
ZIP_NAME="${PLUGIN_FOLDER}.zip"

echo ""
echo "📦 Building zip file: $ZIP_NAME"
echo ""

# Create temp directory with plugin folder structure
TEMP_DIR=$(mktemp -d)
PLUGIN_DIR="${TEMP_DIR}/${PLUGIN_FOLDER}"
mkdir -p "${PLUGIN_DIR}"

echo "📁 Creating plugin folder structure..."

# Copy files (excluding unwanted ones)
rsync -av \
  --exclude='.git' \
  --exclude='.gitignore' \
  --exclude='*.zip' \
  --exclude='*.md' \
  --exclude='tests' \
  --exclude='*.log' \
  --exclude='node_modules' \
  --exclude='.DS_Store' \
  --exclude='__MACOSX' \
  --exclude='backups' \
  --exclude='*-backup-*' \
  --exclude='check-domain-association-file.php' \
  --exclude='diagnose-*.php' \
  --exclude='generate-*.php' \
  --exclude='test-*.php' \
  --exclude='terms-and-conditions-checkbox.php' \
  --exclude='create-zip.py' \
  --exclude='build-zip.sh' \
  --exclude='build-webhook-queue-zip.sh' \
  --exclude='build-plugin-zip.py' \
  --exclude='build-correct-zip.sh' \
  --exclude='check-zip-structure.py' \
  --exclude='verify-and-fix-zip.py' \
  --exclude='diagnose-header-error.py' \
  --exclude='php-uploads.ini' \
  . "${PLUGIN_DIR}/" > /dev/null 2>&1

# Verify structure
if [ ! -f "${PLUGIN_DIR}/${MAIN_FILE}" ]; then
    echo "❌ ERROR: Main plugin file not found in plugin directory!"
    rm -rf "${TEMP_DIR}"
    exit 1
fi

# Check if vendor directory exists (required for SDK)
if [ ! -f "${PLUGIN_DIR}/vendor/autoload.php" ]; then
    echo "⚠️  WARNING: vendor/autoload.php not found!"
    echo "   The plugin requires vendor dependencies. Please ensure vendor/ folder exists."
    echo "   You may need to run 'composer install' or copy vendor from Release folder."
else
    echo "✅ Vendor dependencies found"
fi

echo "✅ Files copied to plugin folder"

# Create zip from temp directory (so folder structure is preserved)
cd "${TEMP_DIR}"
echo "📦 Creating zip archive..."
zip -r "${ZIP_NAME}" "${PLUGIN_FOLDER}" > /dev/null

# Move to original directory
mv "${ZIP_NAME}" "${SCRIPT_DIR}/"

# Cleanup
rm -rf "${TEMP_DIR}"

# Verify zip structure
cd "${SCRIPT_DIR}"
echo ""
echo "🔍 Verifying zip structure..."
EXPECTED_PATH="${PLUGIN_FOLDER}/${MAIN_FILE}"
if unzip -l "${ZIP_NAME}" | grep -q "${EXPECTED_PATH}"; then
    echo "   ✅ Correct structure verified: ${EXPECTED_PATH}"
else
    echo "   ❌ ERROR: Zip structure is incorrect!"
    echo "   Expected: ${EXPECTED_PATH}"
    unzip -l "${ZIP_NAME}" | head -5
    exit 1
fi

FILE_COUNT=$(unzip -l "${ZIP_NAME}" | tail -1 | awk '{print $2}')
ZIP_SIZE=$(ls -lh "${ZIP_NAME}" | awk '{print $5}')

echo ""
echo "============================================================"
echo "✅ SUCCESS: Plugin zip created with correct structure!"
echo "============================================================"
echo "📁 File: ${ZIP_NAME}"
echo "💾 Size: ${ZIP_SIZE}"
echo "📊 Files: ${FILE_COUNT}"
echo ""
echo "🔑 WordPress Update Identifiers:"
echo "   1. ✅ Folder name: ${PLUGIN_FOLDER}"
echo "   2. ✅ Main file: ${MAIN_FILE}"
echo "   3. ✅ Plugin Name: ${PLUGIN_NAME}"
echo ""
echo "💡 This zip will UPDATE existing plugin installations"
echo "   Merchants will NOT see duplicate plugins!"
echo "============================================================"

