#!/usr/bin/env bash
# Local copy of the WooCommerce QIT "Plugin Check" managed test: the WordPress.org Plugin Check,
# "plugin_repo" category, run on the release zip in the e2e wp-env store.
#
# Usage: bin/qit/plugin-check.sh [path/to/plugin.zip]   (default: build one with bin/build.sh)
# Needs: the e2e store running (cd e2e-tests && npm run env:start).
# Exit:  0 = no errors (warnings are listed), 1 = errors.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

SLUG="checkout-com-unified-payments-api"
MOUNT="$(basename "$ROOT")"      # the repo is mounted in the store under its folder name
WP_ENV="$ROOT/e2e-tests/node_modules/.bin/wp-env"
ZIP="${1:-}"

if [[ ! -x "$WP_ENV" ]]; then
	echo "wp-env not found. Run: cd e2e-tests && npm ci && npm run env:start" >&2
	exit 2
fi

if [[ -z "$ZIP" ]]; then
	bash bin/build.sh > /dev/null
	ZIP="$SLUG.zip"
fi
case "$ZIP" in
	/*) ZIP_REL="${ZIP#"$ROOT"/}" ;;
	*) ZIP_REL="$ZIP" ;;
esac
if [[ ! -f "$ROOT/$ZIP_REL" ]]; then
	echo "Zip not found inside the repo: $ZIP (the store only sees files in the repo folder)" >&2
	exit 2
fi

# The mounted copy of the plugin is skipped on every command: two copies of the plugin cannot load.
wp() { "$WP_ENV" run cli wp "$@" --skip-plugins="$MOUNT" 2>/dev/null; }

cleanup() {
	wp plugin deactivate "$SLUG" > /dev/null 2>&1 || true
	wp plugin delete "$SLUG" > /dev/null 2>&1 || true
}
trap cleanup EXIT

wp plugin is-installed plugin-check || wp plugin install plugin-check > /dev/null
wp plugin activate plugin-check > /dev/null || true
wp plugin install "wp-content/plugins/$MOUNT/$ZIP_REL" --force > /dev/null

REPORT="$(wp plugin check "$SLUG" --categories=plugin_repo --format=csv --fields=file,line,type,code,message || true)"

ERRORS="$(printf '%s\n' "$REPORT" | grep -c ',ERROR,' || true)"
WARNINGS="$(printf '%s\n' "$REPORT" | grep -c ',WARNING,' || true)"

SUMMARY="Plugin Check ($SLUG from $ZIP_REL): $ERRORS error(s), $WARNINGS warning(s)."
if [[ "$ERRORS" -gt 0 ]]; then
	printf '%s\n' "$REPORT" | grep ',ERROR,' >&2 || true
	echo "$SUMMARY" >&2
	exit 1
fi
echo "$SUMMARY"
