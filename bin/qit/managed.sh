#!/usr/bin/env bash
# Run the real WooCommerce QIT managed tests on a release zip BEFORE publishing it.
#
# These are the tests WooCommerce runs on every new version. Activation, Woo E2E, Woo API and
# PHPStan can only run on QIT itself; the others have local copies in bin/qit/local.sh.
#
# One-time setup (needs a WooCommerce.com vendor account with access to the product):
#   composer global require "woocommerce/qit-cli:*"
#   qit connect            # opens the browser to log in
#   qit extensions         # check the product slug; set QIT_SLUG if it differs from the default
#
# Usage: bin/qit/managed.sh [path/to/plugin.zip] [test ...]
#   bin/qit/managed.sh checkout-com-unified-payments-api.zip
#   bin/qit/managed.sh checkout-com-unified-payments-api.zip security plugin-check
# Exit: 0 when every test passed (warnings allowed), 1 otherwise.
set -uo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SLUG="${QIT_SLUG:-checkout-com-payments-gateway}"
ZIP="${1:-$ROOT/checkout-com-unified-payments-api.zip}"
shift || true
TESTS=("$@")
if [ ${#TESTS[@]} -eq 0 ]; then
	TESTS=(activation woo-e2e woo-api security phpcompatibility phpstan malware validation plugin-check)
fi

if ! command -v qit > /dev/null 2>&1; then
	echo 'QIT CLI not found. Install it: composer global require "woocommerce/qit-cli:*" and run: qit connect' >&2
	exit 2
fi
if [ ! -f "$ZIP" ]; then
	echo "Zip not found: $ZIP (build one with: bash bin/build.sh)" >&2
	exit 2
fi

FAILED=()
for test in "${TESTS[@]}"; do
	echo "== qit run:$test $SLUG --zip=$ZIP"
	if qit "run:$test" "$SLUG" --zip="$ZIP"; then
		echo "-> $test: passed"
	else
		echo "-> $test: FAILED (open the report link above for details)"
		FAILED+=("$test")
	fi
	echo
done

if [ ${#FAILED[@]} -gt 0 ]; then
	echo "QIT managed tests FAILED: ${FAILED[*]}"
	exit 1
fi
echo "QIT managed tests passed."
