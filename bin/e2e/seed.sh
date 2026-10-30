#!/usr/bin/env bash
# Seed the wp-env test store for the Playwright E2E suite (idempotent - safe to re-run).
#
# Usage (from the repo root, after `wp-env start`):
#   CKO_PUBLIC_KEY=pk_sbox_... CKO_SECRET_KEY=sk_sbox_... bin/e2e/seed.sh
#
# Optional: CKO_CLIENT_ID (cli_...), CKO_PROCESSING_CHANNEL_ID (pc_...),
#           E2E_CUSTOMER_PASSWORD (default: password).
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR/../.."

WP_ENV="${WP_ENV:-e2e-tests/node_modules/.bin/wp-env}"
wp() { "$WP_ENV" run cli wp "$@"; }

: "${CKO_PUBLIC_KEY:?Set CKO_PUBLIC_KEY (Checkout.com sandbox public key)}"
: "${CKO_SECRET_KEY:?Set CKO_SECRET_KEY (Checkout.com sandbox secret key)}"
CUSTOMER_PASSWORD="${E2E_CUSTOMER_PASSWORD:-password}"

echo "==> Store basics"
wp rewrite structure '/%postname%/' --hard
wp option update woocommerce_coming_soon no
wp option update woocommerce_store_pages_only no
wp option update woocommerce_currency USD
wp option update woocommerce_default_country 'US:CA'
wp option update woocommerce_store_city 'San Francisco'
wp option update woocommerce_store_postcode '94103'
wp option update woocommerce_enable_guest_checkout yes
wp option update woocommerce_default_customer_address base
wp option update woocommerce_enable_checkout_login_reminder no
wp option update woocommerce_allow_tracking no
wp option update woocommerce_show_marketplace_suggestions no

echo "==> Remember the default (block) checkout content so profiles can switch layouts"
CHECKOUT_ID="$(wp option get woocommerce_checkout_page_id)"
if ! wp option get cko_e2e_blocks_checkout_content >/dev/null 2>&1; then
	wp eval "update_option( 'cko_e2e_blocks_checkout_content', get_post_field( 'post_content', ${CHECKOUT_ID} ), false );"
fi

echo "==> Shipping: free shipping everywhere (keeps totals deterministic)"
if [ -z "$(wp wc shipping_zone_method list 0 --field=method_id --user=admin 2>/dev/null | grep free_shipping || true)" ]; then
	wp wc shipping_zone_method create 0 --method_id=free_shipping --enabled=true --user=admin
fi

echo "==> Products"
ensure_product() { # sku name price virtual
	local id
	id="$(wp wc product list --sku="$1" --field=id --user=admin 2>/dev/null || true)"
	if [ -z "$id" ]; then
		wp wc product create --name="$2" --sku="$1" --regular_price="$3" --virtual="$4" --status=publish --user=admin --porcelain
	else
		wp wc product update "$id" --regular_price="$3" --virtual="$4" --status=publish --user=admin --porcelain
	fi
}
ensure_product e2e-virtual 'E2E Virtual Product' 12.34 true
ensure_product e2e-physical 'E2E Physical Product' 20.00 false

echo "==> Customer account"
if ! wp user get e2e_customer >/dev/null 2>&1; then
	wp user create e2e_customer e2e_customer@example.com --role=customer --user_pass="$CUSTOMER_PASSWORD" --first_name=E2E --last_name=Customer
else
	wp user update e2e_customer --user_pass="$CUSTOMER_PASSWORD"
fi

echo "==> Checkout.com gateway (Flow, sandbox)"
CARDS_JSON="$(node -e '
const s = {
	enabled: "no",
	ckocom_checkout_mode: "flow",
	ckocom_region: "global",
	ckocom_environment: "sandbox",
	title: "Pay by Card with Checkout.com",
	ckocom_account_type: "NAS",
	ckocom_pk: process.env.CKO_PUBLIC_KEY,
	ckocom_sk: process.env.CKO_SECRET_KEY,
	ckocom_client_id: process.env.CKO_CLIENT_ID || "",
	ckocom_processing_channel_id: process.env.CKO_PROCESSING_CHANNEL_ID || "",
	enable_fallback_ac: "no",
	flow_show_card_holder_name: "1",
	flow_component_cardholder_name_position: "top",
	flow_saved_payment: "saved_cards_first",
};
process.stdout.write(JSON.stringify(s));
')"
wp option update woocommerce_wc_checkout_com_cards_settings "$CARDS_JSON" --format=json
wp option update woocommerce_wc_checkout_com_flow_settings '{"enabled":"yes","flow_enabled_payment_methods":["card"]}' --format=json
wp option update ckocom_order_authorised on-hold
# Saved cards on (CVV not re-asked) so save-card / pay-with-saved-card flows are testable.
wp option update ckocom_card_saved 1
wp option update ckocom_card_require_cvv 0

echo "==> Default to the block checkout layout"
"$SCRIPT_DIR/checkout-layout.sh" blocks

echo "Seed complete."
