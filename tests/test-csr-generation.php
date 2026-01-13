<?php
/**
 * Test Script for Apple Pay CSR Generation
 * 
 * INSTRUCTIONS:
 * 1. Run this script via WP-CLI: wp eval-file test-csr-generation.php
 *    OR access via browser: https://checkout.test:8443/wp-content/plugins/checkout-com-unified-payments-api/test-csr-generation.php
 * 2. Make sure you're logged in as admin (for browser access)
 * 
 * This script tests the CSR generation API call directly without going through WordPress AJAX.
 */

// Load WordPress
if ( ! defined( 'ABSPATH' ) ) {
	// Try multiple possible paths
	$wp_load_paths = [
		__DIR__ . '/../../../../wp-load.php',
		dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) . '/wp-load.php',
		'/var/www/html/wp-load.php',
	];
	
	$wp_loaded = false;
	foreach ( $wp_load_paths as $path ) {
		if ( file_exists( $path ) ) {
			require_once $path;
			$wp_loaded = true;
			break;
		}
	}
	
	if ( ! $wp_loaded ) {
		die( 'Error: Could not find wp-load.php. Please ensure WordPress is installed.' );
	}
}

// Security check for browser access (skip for CLI)
if ( php_sapi_name() !== 'cli' && ! defined( 'WP_CLI' ) && ( ! is_user_logged_in() || ! current_user_can( 'manage_woocommerce' ) ) ) {
	die( 'Access denied. You must be logged in as an administrator.' );
}

// Set headers for browser output
if ( ! defined( 'WP_CLI' ) ) {
	header( 'Content-Type: text/html; charset=utf-8' );
	echo '<!DOCTYPE html><html><head><title>CSR Generation Test</title><style>body{font-family:monospace;padding:20px;background:#f5f5f5;} .section{background:white;padding:15px;margin:10px 0;border-left:4px solid #0073aa;} .success{border-left-color:#46b450;} .error{border-left-color:#dc3232;} .warning{border-left-color:#ffb900;} pre{background:#f0f0f0;padding:10px;overflow-x:auto;}</style></head><body>';
}

function test_output( $message, $type = 'info' ) {
	if ( defined( 'WP_CLI' ) ) {
		WP_CLI::line( $message );
	} else {
		$class = $type === 'error' ? 'error' : ( $type === 'success' ? 'success' : 'section' );
		echo '<div class="' . esc_attr( $class ) . '"><pre>' . esc_html( $message ) . '</pre></div>';
	}
}

test_output( '=== Apple Pay CSR Generation Test ===', 'info' );
test_output( '' );

// Step 1: Get settings
test_output( 'Step 1: Loading Checkout.com settings...', 'info' );
$core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings', array() );

if ( empty( $core_settings['ckocom_sk'] ) ) {
	test_output( 'ERROR: Secret key is not configured. Please configure Checkout.com settings first.', 'error' );
	exit;
}

$secret_key_raw = $core_settings['ckocom_sk'];
$environment = isset( $core_settings['ckocom_environment'] ) ? $core_settings['ckocom_environment'] : 'sandbox';
$account_type = isset( $core_settings['ckocom_account_type'] ) ? $core_settings['ckocom_account_type'] : 'ABC';
$region = isset( $core_settings['ckocom_region'] ) ? $core_settings['ckocom_region'] : 'global';
$protocol_version = 'ec_v1';

test_output( 'Settings loaded:', 'info' );
test_output( '  Environment: ' . $environment );
test_output( '  Account Type: ' . $account_type );
test_output( '  Region: ' . $region );
test_output( '  Protocol Version: ' . $protocol_version );
test_output( '  Secret Key: ' . substr( $secret_key_raw, 0, 10 ) . '...' . substr( $secret_key_raw, -10 ) );
test_output( '' );

// Check if NAS account
if ( function_exists( 'cko_is_nas_account' ) && cko_is_nas_account() ) {
	$account_type = 'NAS';
	test_output( 'Detected NAS account via helper function', 'info' );
}

// Step 2: Build API URL
test_output( 'Step 2: Building API URL...', 'info' );
$base_url = 'https://api.checkout.com';
if ( 'sandbox' === $environment ) {
	$base_url = 'https://api.sandbox.checkout.com';
}

// Add region subdomain if not global
if ( 'global' !== $region && ! empty( $region ) ) {
	$base_url = str_replace( 'api.', $region . '.api.', $base_url );
}

$api_url = $base_url . '/applepay/signing-requests';
test_output( 'API URL: ' . $api_url );
test_output( '' );

// Step 3: Prepare authorization header
test_output( 'Step 3: Preparing authorization header...', 'info' );
$secret_key_clean = str_replace( 'Bearer ', '', trim( $secret_key_raw ) );

if ( 'NAS' === $account_type ) {
	$auth_header = 'Bearer ' . $secret_key_clean;
	test_output( 'Using Bearer authentication for NAS account' );
} else {
	// ABC accounts - try direct key first
	$auth_header = $secret_key_clean;
	test_output( 'Using direct key authentication for ABC account (will retry with Bearer if needed)' );
}
test_output( '' );

// Step 4: Prepare request body
test_output( 'Step 4: Preparing request body...', 'info' );
$request_body = [
	'protocol_version' => $protocol_version,
];
test_output( 'Request Body: ' . json_encode( $request_body, JSON_PRETTY_PRINT ) );
test_output( '' );

// Step 5: Make API request
test_output( 'Step 5: Making API request...', 'info' );
test_output( 'Request URL: ' . $api_url );
test_output( 'Request Method: POST' );
test_output( 'Content-Type: application/json' );
test_output( 'Authorization: ' . ( strpos( $auth_header, 'Bearer' ) !== false ? 'Bearer [HIDDEN]' : 'Direct [HIDDEN]' ) );
test_output( '' );

$response = wp_remote_post(
	$api_url,
	[
		'headers' => [
			'Authorization' => $auth_header,
			'Content-Type'  => 'application/json',
		],
		'body'    => json_encode( $request_body ),
		'timeout' => 30,
	]
);

// Step 6: Process response
test_output( 'Step 6: Processing response...', 'info' );

if ( is_wp_error( $response ) ) {
	test_output( 'ERROR: ' . $response->get_error_message(), 'error' );
	test_output( 'Error Code: ' . $response->get_error_code(), 'error' );
	exit;
}

$response_code = wp_remote_retrieve_response_code( $response );
$response_body = wp_remote_retrieve_body( $response );

test_output( 'Response Code: ' . $response_code );

if ( 200 === $response_code || 201 === $response_code ) {
	test_output( 'SUCCESS! CSR generated successfully.', 'success' );
	$response_data = json_decode( $response_body, true );
	
	if ( isset( $response_data['csr'] ) ) {
		test_output( 'CSR Content:', 'success' );
		test_output( $response_data['csr'] );
		
		// Save to file
		$filename = 'apple-pay-csr-' . date( 'Y-m-d-His' ) . '.csr';
		$filepath = __DIR__ . '/' . $filename;
		file_put_contents( $filepath, $response_data['csr'] );
		test_output( 'CSR saved to: ' . $filepath, 'success' );
	} else {
		test_output( 'Full Response:', 'success' );
		test_output( $response_body );
	}
} else {
	test_output( 'ERROR: API returned error code ' . $response_code, 'error' );
	test_output( 'Response Body:', 'error' );
	test_output( $response_body );
	
	// Try to parse error
	$error_data = json_decode( $response_body, true );
	if ( $error_data ) {
		test_output( '', 'error' );
		test_output( 'Parsed Error Details:', 'error' );
		if ( isset( $error_data['error_type'] ) ) {
			test_output( '  Error Type: ' . $error_data['error_type'] );
		}
		if ( isset( $error_data['error_codes'] ) && is_array( $error_data['error_codes'] ) ) {
			test_output( '  Error Codes: ' . implode( ', ', $error_data['error_codes'] ) );
		}
		if ( isset( $error_data['message'] ) ) {
			test_output( '  Message: ' . $error_data['message'] );
		}
	}
	
	// If 401 (Unauthorized) or 400 (Bad Request), try with Bearer for ABC accounts
	if ( ( 401 === $response_code || 400 === $response_code ) && 'ABC' === $account_type ) {
		test_output( '', 'warning' );
		test_output( 'Retrying with Bearer authentication...', 'warning' );
		
		$auth_header_bearer = 'Bearer ' . $secret_key_clean;
		
		$response_retry = wp_remote_post(
			$api_url,
			[
				'headers' => [
					'Authorization' => $auth_header_bearer,
					'Content-Type'  => 'application/json',
				],
				'body'    => json_encode( $request_body ),
				'timeout' => 30,
			]
		);
		
		if ( ! is_wp_error( $response_retry ) ) {
			$retry_code = wp_remote_retrieve_response_code( $response_retry );
			$retry_body = wp_remote_retrieve_body( $response_retry );
			
			test_output( 'Retry Response Code: ' . $retry_code );
			
			if ( 200 === $retry_code || 201 === $retry_code ) {
				test_output( 'SUCCESS with Bearer authentication!', 'success' );
				$response_data = json_decode( $retry_body, true );
				
				if ( isset( $response_data['csr'] ) ) {
					test_output( 'CSR Content:', 'success' );
					test_output( $response_data['csr'] );
					
					// Save to file
					$filename = 'apple-pay-csr-' . date( 'Y-m-d-His' ) . '.csr';
					$filepath = __DIR__ . '/' . $filename;
					file_put_contents( $filepath, $response_data['csr'] );
					test_output( 'CSR saved to: ' . $filepath, 'success' );
				} else {
					test_output( 'Full Response:', 'success' );
					test_output( $retry_body );
				}
			} else {
				test_output( 'ERROR: Retry also failed with code ' . $retry_code, 'error' );
				test_output( 'Retry Response Body:', 'error' );
				test_output( $retry_body );
			}
		} else {
			test_output( 'ERROR: Retry request failed: ' . $response_retry->get_error_message(), 'error' );
		}
	}
}

test_output( '' );
test_output( '=== Test Complete ===', 'info' );

if ( ! defined( 'WP_CLI' ) ) {
	echo '</body></html>';
}

