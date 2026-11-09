<?php
/**
 * E2E only (mapped into the wp-env store by .wp-env.json, never shipped).
 *
 * While the option `cko_e2e_capture_http` is set, records the JSON body of every outgoing
 * request to Checkout.com's /payment-sessions endpoints into the option
 * `cko_e2e_captured_requests`, so tests can check what the server actually sent.
 */

add_filter(
	'http_request_args',
	function ( $args, $url ) {
		if ( ! get_option( 'cko_e2e_capture_http' ) || false === strpos( (string) $url, '/payment-sessions' ) ) {
			return $args;
		}

		$captured   = (array) get_option( 'cko_e2e_captured_requests', array() );
		$captured[] = array(
			'url'  => (string) $url,
			'body' => isset( $args['body'] ) && is_string( $args['body'] ) ? json_decode( $args['body'], true ) : null,
		);
		update_option( 'cko_e2e_captured_requests', array_slice( $captured, -20 ), false );

		return $args;
	},
	10,
	2
);
