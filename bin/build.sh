#!/bin/sh
# Build WordPress plugin zip with CORRECT structure for updates
# This ensures merchants won't see duplicate plugins

set -e

# Get script directory (works with both sh and bash)
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR/.."  # Go to repo root

# Plugin identifiers (MUST match existing installation)
PLUGIN_FOLDER="checkout-com-unified-payments-api"
MAIN_FILE="woocommerce-gateway-checkout-com.php"
PLUGIN_NAME="Checkout.com Payment Gateway"
PLUGIN_SOURCE_DIR="."

echo "🔍 Verifying plugin identifiers..."
echo "   Folder: $PLUGIN_FOLDER"
echo "   Main file: $MAIN_FILE"
echo "   Plugin Name: $PLUGIN_NAME"
echo "   Source directory: $PLUGIN_SOURCE_DIR"
echo ""

# Verify source directory exists
if [ ! -d "$PLUGIN_SOURCE_DIR" ]; then
    echo "❌ ERROR: Plugin source directory not found: $PLUGIN_SOURCE_DIR"
    exit 1
fi

# Verify main file exists in source directory
if [ ! -f "${PLUGIN_SOURCE_DIR}/${MAIN_FILE}" ]; then
    echo "❌ ERROR: Main plugin file not found: ${PLUGIN_SOURCE_DIR}/${MAIN_FILE}"
    exit 1
fi

# Verify Plugin Name in header
if ! grep -q "Plugin Name: $PLUGIN_NAME" "${PLUGIN_SOURCE_DIR}/${MAIN_FILE}"; then
    echo "⚠️  WARNING: Plugin Name header might not match!"
else
    echo "✅ Plugin Name header verified"
fi

# Create generic zip name for client distribution
ZIP_NAME="${PLUGIN_FOLDER}.zip"

echo ""
echo "📦 Building zip file: $ZIP_NAME"
echo ""

# --- Dependency namespace scoping check (GH #27 fix) -----------------------
# Monolog (bundled via checkout-sdk-php) must be namespace-scoped before we
# package a release, otherwise a merchant loading their own Monolog v3 fatals
# against ours. `composer install`/`composer update` already runs Strauss
# automatically via the post-install-cmd/post-update-cmd hooks in
# composer.json, scoping the Monolog classes IN PLACE inside vendor/ (there is
# no separate vendor-prefixed/ folder — extra.strauss.target_directory is set
# to "vendor", so the renamed classes live in the normal vendor/ tree and ship
# exactly like any other dependency). This block is just a safety net so we
# never ship an unscoped (or half-scoped) build by accident.

# The SDK's one internal reference to Monolog (lib/Checkout/AbstractCheckoutSdkBuilder.php)
# must have been rewritten by Strauss to point at the scoped namespace, or the SDK would
# fatal looking for a `Monolog\Logger` class that no longer exists once Monolog is renamed.
SDK_BUILDER_FILE="vendor/checkout/checkout-sdk-php/lib/Checkout/AbstractCheckoutSdkBuilder.php"
if [ -f "$SDK_BUILDER_FILE" ] && grep -qE "use Monolog\\\\(Logger|Handler)" "$SDK_BUILDER_FILE"; then
    echo "❌ ERROR: ${SDK_BUILDER_FILE} still references the unscoped Monolog\\ namespace."
    echo "   Strauss did not rewrite this call site. Either:"
    echo "     a) confirm Strauss's update_call_sites handled cross-package references and"
    echo "        re-run 'composer install', or"
    echo "     b) manually update the 'use Monolog\\...' lines in that file to"
    echo "        'use CheckoutComWC\\Vendor\\Monolog\\...' before building a release."
    exit 1
fi

# Run the actual regression test (autoload resolution AND the real merchant-collision
# simulation — a stub Monolog\Logger loaded before our autoloader, in an isolated
# process). The lighter checks above catch the common failure fast; this is the one
# that actually proves the reported bug is fixed. Do not ship a zip without this passing.
if command -v php >/dev/null 2>&1; then
    php tests/test-monolog-namespace-scoping.php || {
        echo ""
        echo "❌ ERROR: Monolog namespace scoping regression test FAILED (see output above)."
        echo "   Do not ship this build to the client until this passes."
        exit 1
    }
else
    echo "❌ ERROR: php not found on PATH — cannot verify the Monolog scoping fix."
    echo "   Do not ship a build without running: php tests/test-monolog-namespace-scoping.php"
    exit 1
fi
echo ""

# Create temp directory with plugin folder structure
TEMP_DIR=$(mktemp -d)
PLUGIN_DIR="${TEMP_DIR}/${PLUGIN_FOLDER}"
mkdir -p "${PLUGIN_DIR}"

echo "📁 Creating plugin folder structure..."

# Copy files from plugin source directory (excluding unwanted ones)
# Use --inplace to avoid temporary file creation issues in sandbox environments
rsync -av --inplace \
  --exclude='.git' \
  --exclude='.gitignore' \
  --exclude='.tmp' \
  --exclude='*.zip' \
  --exclude='*.md' \
  --exclude='tests' \
  --exclude='*.log' \
  --exclude='node_modules' \
  --exclude='.DS_Store' \
  --exclude='__MACOSX' \
  --exclude='backups' \
  --exclude='*-backup-*' \
  --exclude='e2e-tests' \
  --exclude='.env' \
  --exclude='.env.*' \
  --exclude='.cursor' \
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
  --exclude='composer.phar' \
  --exclude='bin/strauss.phar' \
  --exclude='vendor/wp-cli' \
  "${PLUGIN_SOURCE_DIR}/" "${PLUGIN_DIR}/" > /dev/null 2>&1

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
mv "${ZIP_NAME}" "${SCRIPT_DIR}/../"

# Cleanup
rm -rf "${TEMP_DIR}"

# Verify zip structure
cd "${SCRIPT_DIR}/.."
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
