#!/bin/sh

PLUGIN_SLUG="checkout-com-unified-payments-api"

echo "Installing PHP and JS dependencies..."
composer install --no-dev || exit "$?"

echo "Create build directory..."
mkdir "$PLUGIN_SLUG"

echo "Copy plugin data to build folder..."
cp -R "assets" "$PLUGIN_SLUG/assets/"
cp -R "includes" "$PLUGIN_SLUG/includes/"
cp -R "flow-integration" "$PLUGIN_SLUG/flow-integration/"
cp -R "languages" "$PLUGIN_SLUG/languages/"
cp -R "lib" "$PLUGIN_SLUG/lib/"
cp -R "vendor" "$PLUGIN_SLUG/vendor/"
cp -R "templates" "$PLUGIN_SLUG/templates/"
cp "readme.txt" "$PLUGIN_SLUG/readme.txt"
cp "check-database-indexes.php" "$PLUGIN_SLUG/check-database-indexes.php"
cp "view-webhook-queue.php" "$PLUGIN_SLUG/view-webhook-queue.php"
cp "woocommerce-gateway-checkout-com.php" "$PLUGIN_SLUG/woocommerce-gateway-checkout-com.php"

echo "Generating zip file..."
zip -q -r "${PLUGIN_SLUG}.zip" "$PLUGIN_SLUG/"

echo "Removing folder $PLUGIN_SLUG"
rm -r "$PLUGIN_SLUG"

echo "${PLUGIN_SLUG}.zip file generated!"
echo "Build done!"