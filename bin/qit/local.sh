#!/usr/bin/env bash
# Local copies of the WooCommerce QIT managed tests that can run without a QIT account.
# Each one is graded like QIT: errors fail, warnings are only listed.
#
#   bin/qit/local.sh                 all static checks (security, compat, validation, malware, audit)
#   bin/qit/local.sh security        PHPCS WordPress.Security + WordPress.DB      (QIT Security)
#   bin/qit/local.sh compat          PHPCompatibility, "Requires PHP" to 8.5      (QIT Code Compatibility)
#   bin/qit/local.sh validation      headers, readme, HPOS/blocks declarations    (QIT Validation + Plugin Check headers)
#   bin/qit/local.sh malware         obfuscation, shell calls, hidden files       (QIT Malware)
#   bin/qit/local.sh audit           composer audit of the bundled packages       (QIT Security, dependency part)
#   bin/qit/local.sh plugin-check    WordPress.org Plugin Check on the zip        (QIT Plugin Check; needs the e2e store)
#
# The QIT Activation test is covered by e2e-tests/tests/server/qit-activation.spec.ts.
# Woo E2E, Woo API and PHPStan run only on QIT: see bin/qit/managed.sh.
set -uo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
TOOLS="$ROOT/bin/qit/tools"
cd "$ROOT"

tools() {
	local set="$1"
	if [[ ! -x "$TOOLS/$set/vendor/bin/phpcs" ]]; then
		composer install --working-dir="$TOOLS/$set" --no-interaction --no-progress --quiet
	fi
	echo "$TOOLS/$set/vendor/bin/phpcs"
}

run_security() {
	echo "== Security (PHPCS WordPress.Security + WordPress.DB)"
	"$(tools security)" -q --standard="$ROOT/bin/qit/phpcs-security.xml" --basepath="$ROOT" \
		--warning-severity=0 --report=full --report-width=140 "$ROOT"
}

run_compat() {
	local min
	min="$(grep -m1 -E '^ \* Requires PHP:' woocommerce-gateway-checkout-com.php | sed -E 's/.*: *//')"
	echo "== Code compatibility (PHP ${min}-8.5)"
	# Own code must have no errors. Bundled packages are listed only: their PHP 8 functions
	# come with the bundled symfony/polyfill-php80, which QIT does not count.
	"$(tools compat)" -q --standard=PHPCompatibility --runtime-set testVersion "${min}-8.5" --extensions=php \
		--basepath="$ROOT" --warning-severity=0 --report=full \
		--ignore='*/vendor/*,*/node_modules/*,*/e2e-tests/*,*/tests/*,*/bin/*,*/build/*' "$ROOT"
}

run_validation() {
	echo "== Validation (headers, readme, feature declarations)"
	php "$ROOT/bin/qit/check-validation.php" "$ROOT"
}

run_malware() {
	echo "== Malware scan"
	php "$ROOT/bin/qit/check-malware.php" "$ROOT"
}

run_audit() {
	echo "== Dependency audit (composer, bundled packages)"
	composer audit --no-dev --locked --no-interaction
}

run_plugin_check() {
	echo "== Plugin Check (WordPress.org, plugin_repo)"
	bash "$ROOT/bin/qit/plugin-check.sh" "$@"
}

FAILED=()
run() {
	local name="$1"
	shift
	if "run_${name//-/_}" "$@"; then
		echo "-> $name: passed"
	else
		echo "-> $name: FAILED"
		FAILED+=("$name")
	fi
	echo
}

if [[ $# -eq 0 ]]; then
	for check in security compat validation malware audit; do
		run "$check"
	done
else
	check="$1"
	shift
	case "$check" in
		security | compat | validation | malware | audit | plugin-check) run "$check" "$@" ;;
		*) echo "Unknown check: $check" >&2; exit 2 ;;
	esac
fi

if [[ ${#FAILED[@]} -gt 0 ]]; then
	echo "QIT local checks FAILED: ${FAILED[*]}"
	exit 1
fi
echo "QIT local checks passed."
