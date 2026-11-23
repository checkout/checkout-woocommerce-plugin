<?php
/**
 * E2E only (mapped into the wp-env store by .wp-env.json, never shipped).
 *
 * While the option `cko_e2e_hook_resilience` is set, mimics the "hook resilience" part of the
 * WooCommerce QIT activation test: outgoing HTTP requests fail, REST authentication fails, the
 * REST server returns nothing and e-mails are not sent. The plugin must keep every page working
 * (no fatal error, no HTTP 500) when this happens.
 */

if ( ! get_option( 'cko_e2e_hook_resilience' ) ) {
	return;
}

add_filter(
	'pre_http_request',
	static function () {
		return new WP_Error( 'cko_e2e_http_blocked', 'Outgoing HTTP blocked by the hook resilience test.' );
	},
	PHP_INT_MAX
);

add_filter(
	'rest_authentication_errors',
	static function () {
		return new WP_Error( 'cko_e2e_rest_blocked', 'REST authentication blocked by the hook resilience test.', array( 'status' => 401 ) );
	},
	PHP_INT_MAX
);

add_filter( 'rest_pre_serve_request', '__return_null', PHP_INT_MAX );

add_filter( 'pre_wp_mail', '__return_false', PHP_INT_MAX );
