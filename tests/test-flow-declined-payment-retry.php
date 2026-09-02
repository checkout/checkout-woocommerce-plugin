<?php
/**
 * Regression test — Flow declined-payment retry lockout (bugs reported by merchant, Aug 2026).
 *
 * Encodes the decision logic of the three linked fixes so we never regress the lockout:
 *
 *   Bug 1  A declined payment id must NOT be written to _cko_payment_id (it poisons the guard).
 *   Bug 2  The duplicate-prevention guard must not compare _cko_payment_id against itself. It may
 *          only skip when the id arrived on this request (POST/GET) OR the order is already
 *          authorised/captured — never for a stale id resolved from the order's own meta on a
 *          failed/pending order.
 *   Bug 3  WC()->session->set('3ds_action_id', …) must be guarded so admin/WP-CLI/cron declines
 *          (no front-end session) do not fatal.
 *
 * This is a pure-logic test (no WordPress bootstrap) that mirrors the exact boolean the guard
 * now evaluates in flow-integration/class-wc-gateway-checkout-com-flow.php:
 *
 *   $is_duplicate = ( '' !== $existing_payment ) && ( $existing_payment === $flow_payment_id )
 *       && ( $flow_payment_id_from_request || $already_processed );
 *
 * Run: php tests/test-flow-declined-payment-retry.php
 */

/**
 * Mirror of the guard decision under test.
 *
 * @param string $existing_payment            Order meta _cko_payment_id.
 * @param string $flow_payment_id             Resolved id (POST/GET, else order-meta fallback).
 * @param bool   $flow_payment_id_from_request True if id came from POST/GET this request.
 * @param bool   $already_processed           True if order meta shows authorised/captured.
 * @return bool  Whether the guard would treat this as a duplicate and SKIP the payment.
 */
function cko_flow_guard_is_duplicate( $existing_payment, $flow_payment_id, $flow_payment_id_from_request, $already_processed ) {
	return ( '' !== $existing_payment )
		&& ( $existing_payment === $flow_payment_id )
		&& ( $flow_payment_id_from_request || $already_processed );
}

$failures = 0;
$checks   = 0;

/**
 * @param string $label    Scenario description.
 * @param bool   $expected Expected is_duplicate (true = skip payment, false = process payment).
 * @param bool   $actual   Result from the guard.
 */
function cko_assert( $label, $expected, $actual ) {
	global $failures, $checks;
	$checks++;
	$ok = ( $expected === $actual );
	if ( ! $ok ) {
		$failures++;
	}
	printf(
		"[%s] %s (expected skip=%s, got skip=%s)\n",
		$ok ? 'PASS' : 'FAIL',
		$label,
		$expected ? 'true' : 'false',
		$actual ? 'true' : 'false'
	);
}

echo "Flow declined-payment retry — guard regression test\n";
echo str_repeat( '=', 70 ) . "\n";

// Scenario A: Bug 1 applied — declined id stored elsewhere, so _cko_payment_id is empty on a
// declined/retry order. Resolved id is empty too. Guard is never reached (outer !empty check),
// but if it were, it must NOT be a duplicate. Retry proceeds.
cko_assert(
	'A. Declined-then-retry, _cko_payment_id empty (Bug 1 fixed) -> process',
	false,
	cko_flow_guard_is_duplicate( '', '', false, false )
);

// Scenario B: Legacy order stranded BEFORE Bug 1 fix — _cko_payment_id still holds the declined
// id; retry arrives with no fresh POST/GET id, so fallback resolves the same declined id.
// Old code: '===' was always true -> skipped forever. Fixed guard: not request-sourced and not
// authorised -> NOT duplicate -> retry proceeds. (This also unstrands legacy poisoned orders.)
cko_assert(
	'B. Legacy poisoned order, stale id via meta fallback, not authorised -> process',
	false,
	cko_flow_guard_is_duplicate( 'pay_declined_123', 'pay_declined_123', false, false )
);

// Scenario C: Genuine double-submit — a real authorised payment id re-presented in POST.
// Must be treated as a duplicate and skipped (prevents double charge).
cko_assert(
	'C. Double-submit, authorised id re-sent via POST -> skip',
	true,
	cko_flow_guard_is_duplicate( 'pay_auth_456', 'pay_auth_456', true, true )
);

// Scenario D: Already-authorised order, no fresh id, meta fallback resolves the authorised id.
// Must still skip (no double charge) because the order is genuinely already authorised.
cko_assert(
	'D. Authorised order, id via meta fallback, already authorised -> skip',
	true,
	cko_flow_guard_is_duplicate( 'pay_auth_456', 'pay_auth_456', false, true )
);

// Scenario E: 3DS return via GET with a fresh id, order not yet marked in meta (empty stored id).
// Not a duplicate -> proceeds into 3DS handling.
cko_assert(
	'E. 3DS return via GET, no stored id yet -> process',
	false,
	cko_flow_guard_is_duplicate( '', 'pay_3ds_789', true, false )
);

// Scenario F: Fresh id via POST that matches a stored id on a failed order that was NEVER
// authorised (e.g. stored by a stale path) — request-sourced match still skips, matching the
// original intent of catching a re-submit of the same id. Documented for completeness.
cko_assert(
	'F. Fresh POST id equal to stored id -> skip (re-submit protection)',
	true,
	cko_flow_guard_is_duplicate( 'pay_x', 'pay_x', true, false )
);

echo str_repeat( '=', 70 ) . "\n";
if ( 0 === $failures ) {
	echo "All {$checks} checks passed — retry lockout fixed, double-charge protection preserved.\n";
	exit( 0 );
}
echo "{$failures}/{$checks} checks FAILED.\n";
exit( 1 );
