#!/usr/bin/env php
<?php
/**
 * Generate Apple Pay CSR (Certificate Signing Request) using Checkout.com API
 * 
 * This script generates a CSR file for Apple Pay setup following the instructions at:
 * https://www.checkout.com/docs/payments/add-payment-methods/apple-pay/api-only#Create_a_certificate_signing_request
 * 
 * Usage:
 *   php generate-apple-pay-csr.php
 *   php generate-apple-pay-csr.php --secret-key=sk_test_xxx --environment=sandbox
 *   php generate-apple-pay-csr.php --secret-key=sk_test_xxx --environment=sandbox --protocol-version=ec_v1
 *   php generate-apple-pay-csr.php --secret-key=sk_test_xxx --environment=sandbox --account-type=NAS
 * 
 * @package wc_checkout_com
 */

// Load WordPress if running from plugin directory
$wp_load_paths = [
	__DIR__ . '/../../../../wp-load.php',
	__DIR__ . '/../../../wp-load.php',
	__DIR__ . '/../../wp-load.php',
];

$wp_loaded = false;
foreach ( $wp_load_paths as $wp_path ) {
	if ( file_exists( $wp_path ) ) {
		require_once $wp_path;
		$wp_loaded = true;
		break;
	}
}

// Parse command line arguments
$options = getopt( '', [
	'secret-key:',
	'environment:',
	'protocol-version:',
	'account-type:',
	'region:',
	'output:',
	'help'
] );

// Display help
if ( isset( $options['help'] ) ) {
	echo "Generate Apple Pay CSR (Certificate Signing Request)\n\n";
	echo "Usage:\n";
	echo "  php generate-apple-pay-csr.php [options]\n\n";
	echo "Options:\n";
	echo "  --secret-key=KEY        Checkout.com secret key (required if not using WordPress settings)\n";
	echo "  --environment=ENV       Environment: 'sandbox' or 'live' (default: from WordPress settings or 'sandbox')\n";
	echo "  --protocol-version=VER   Protocol version: 'ec_v1' or 'rsa_v1' (default: 'ec_v1')\n";
	echo "  --account-type=TYPE     Account type: 'NAS' or 'ABC' (default: from WordPress settings or 'ABC')\n";
	echo "  --region=REGION         Region: 'global', 'ksa', etc. (default: from WordPress settings or 'global')\n";
	echo "  --output=FILE           Output file path (default: 'cko.csr')\n";
	echo "  --help                  Show this help message\n\n";
	echo "If WordPress is loaded, settings will be read from WordPress options.\n";
	echo "Otherwise, you must provide --secret-key and --environment.\n\n";
	exit( 0 );
}

// Get settings from WordPress or command line
$secret_key = null;
$environment = 'sandbox';
$protocol_version = 'ec_v1';
$account_type = 'ABC';
$region = 'global';
$output_file = 'cko.csr';

if ( $wp_loaded && function_exists( 'get_option' ) ) {
	// Try to load WordPress settings
	$core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings', array() );
	
	if ( ! empty( $core_settings ) ) {
		$secret_key = $core_settings['ckocom_sk'] ?? null;
		$environment = $core_settings['ckocom_environment'] ?? 'sandbox';
		$account_type = $core_settings['ckocom_account_type'] ?? 'ABC';
		$region = $core_settings['ckocom_region'] ?? 'global';
		
		// Check if NAS account (function might exist)
		if ( function_exists( 'cko_is_nas_account' ) && cko_is_nas_account() ) {
			$account_type = 'NAS';
		}
		
		echo "✓ Loaded settings from WordPress\n";
		echo "  Environment: {$environment}\n";
		echo "  Account Type: {$account_type}\n";
		echo "  Region: {$region}\n\n";
	}
}

// Override with command line arguments
if ( ! empty( $options['secret-key'] ) ) {
	$secret_key = $options['secret-key'];
}

if ( ! empty( $options['environment'] ) ) {
	$environment = $options['environment'];
}

if ( ! empty( $options['protocol-version'] ) ) {
	$protocol_version = $options['protocol-version'];
}

if ( ! empty( $options['account-type'] ) ) {
	$account_type = $options['account-type'];
}

if ( ! empty( $options['region'] ) ) {
	$region = $options['region'];
}

if ( ! empty( $options['output'] ) ) {
	$output_file = $options['output'];
}

// Validate required parameters
if ( empty( $secret_key ) ) {
	echo "✗ Error: Secret key is required.\n";
	echo "  Please provide --secret-key or ensure WordPress settings are configured.\n";
	exit( 1 );
}

// Build API URL based on environment and region
$base_url = 'https://api.checkout.com';
if ( 'sandbox' === $environment ) {
	$base_url = 'https://api.sandbox.checkout.com';
}

// Add region subdomain if not global
if ( 'global' !== $region && ! empty( $region ) ) {
	$base_url = str_replace( 'api.', $region . '.api.', $base_url );
}

$api_url = $base_url . '/applepay/signing-requests';

// Prepare authorization header
$auth_header = $secret_key;
if ( 'NAS' === $account_type ) {
	$auth_header = 'Bearer ' . $secret_key;
}

// Prepare request body
$request_body = [
	'protocol_version' => $protocol_version
];

echo "Generating Apple Pay CSR...\n";
echo "  API URL: {$api_url}\n";
echo "  Protocol Version: {$protocol_version}\n";
echo "  Account Type: {$account_type}\n\n";

// Make API request
if ( function_exists( 'wp_remote_post' ) ) {
	// Use WordPress HTTP API if available
	$response = wp_remote_post(
		$api_url,
		[
			'headers' => [
				'Authorization' => $auth_header,
				'Content-Type' => 'application/json',
			],
			'body' => json_encode( $request_body ),
			'timeout' => 30,
		]
	);

	// Check for errors
	if ( is_wp_error( $response ) ) {
		echo "✗ Error making API request:\n";
		echo "  " . $response->get_error_message() . "\n";
		exit( 1 );
	}

	$response_code = wp_remote_retrieve_response_code( $response );
	$response_body = wp_remote_retrieve_body( $response );
} else {
	// Use cURL if WordPress HTTP API is not available
	$ch = curl_init( $api_url );
	
	curl_setopt_array( $ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_POST => true,
		CURLOPT_POSTFIELDS => json_encode( $request_body ),
		CURLOPT_HTTPHEADER => [
			'Authorization: ' . $auth_header,
			'Content-Type: application/json',
		],
		CURLOPT_TIMEOUT => 30,
		CURLOPT_SSL_VERIFYPEER => true,
	] );
	
	$response_body = curl_exec( $ch );
	$response_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	$curl_error = curl_error( $ch );
	curl_close( $ch );
	
	if ( false === $response_body || ! empty( $curl_error ) ) {
		echo "✗ Error making API request:\n";
		echo "  cURL Error: {$curl_error}\n";
		exit( 1 );
	}
}

if ( 200 !== $response_code && 201 !== $response_code ) {
	echo "✗ API request failed:\n";
	echo "  Response Code: {$response_code}\n";
	echo "  Response Body: {$response_body}\n";
	exit( 1 );
}

// Parse response
$response_data = json_decode( $response_body, true );

if ( ! isset( $response_data['content'] ) || empty( $response_data['content'] ) ) {
	echo "✗ Error: Invalid response from API\n";
	echo "  Response: {$response_body}\n";
	exit( 1 );
}

// Get CSR content
$csr_content = $response_data['content'];

// Ensure CSR content is properly formatted
if ( false === strpos( $csr_content, '-----BEGIN CERTIFICATE REQUEST-----' ) ) {
	// If it doesn't start with BEGIN, it might be base64 encoded
	$csr_content = "-----BEGIN CERTIFICATE REQUEST-----\n" . $csr_content . "\n-----END CERTIFICATE REQUEST-----";
}

// Save to file
$output_path = __DIR__ . '/' . $output_file;
$result = file_put_contents( $output_path, $csr_content );

if ( false === $result ) {
	echo "✗ Error: Failed to write CSR file to: {$output_path}\n";
	exit( 1 );
}

echo "✓ CSR file generated successfully!\n";
echo "  File: {$output_path}\n";
echo "  Size: " . filesize( $output_path ) . " bytes\n\n";

echo "Next steps:\n";
echo "1. Go to your Apple Developer account: https://developer.apple.com/account/resources/identifiers/list/merchantId\n";
echo "2. Select your Merchant ID\n";
echo "3. In the 'Apple Pay Payment Processing Certificate' section, click 'Create Certificate'\n";
echo "4. Answer 'No' to processing in China and click 'Continue'\n";
echo "5. Upload the file: {$output_path}\n";
echo "6. Download the signed certificate (apple_pay.cer) from Apple\n";
echo "7. Convert and upload the certificate to Checkout.com following the instructions at:\n";
echo "   https://www.checkout.com/docs/payments/add-payment-methods/apple-pay/api-only#Upload_the_signed_payment_processing_certificate\n\n";

echo "Note: The CSR is valid for 24 hours. Complete the certificate creation within this timeframe.\n";

