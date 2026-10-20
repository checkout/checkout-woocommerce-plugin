<?php
/**
 * Checkout SDK wrapper class.
 *
 * @package wc_checkout_com
 */

defined( 'ABSPATH' ) || exit;

use Checkout\CheckoutSdk;
use Checkout\Environment;
use Checkout\CheckoutArgumentException;

require_once dirname( __DIR__ ) . '/includes/api/class-wc-checkoutcom-utility.php';

/**
 * Wrapper class around the Checkout.com SDK.
 *
 * Ref: https://github.com/checkout/checkout-sdk-php/
 */
class Checkout_SDK {

	/**
	 * CheckoutApi object.
	 *
	 * @var object
	 */
	private $builder;

	/**
	 * Constructor.
	 *
	 * @param bool $use_fallback Use Fallback account flag.
	 */
	public function __construct( $use_fallback = false ) {
		// Check if SDK classes are available
		if ( ! class_exists( 'Checkout\CheckoutSdk' ) ) {
			WC_Checkoutcom_Utility::logger( 'Checkout.com SDK classes not found. Please ensure vendor/autoload.php is loaded.' );
			return;
		}
		
		$core_settings = function_exists( 'cko_get_raw_option' )
			? cko_get_raw_option( 'woocommerce_wc_checkout_com_cards_settings' )
			: get_option( 'woocommerce_wc_checkout_com_cards_settings', array() );
		
		// Validate that API keys are present before initializing SDK
		// This prevents errors when keys are being registered/updated
		$public_key = $use_fallback ? ( $core_settings['fallback_ckocom_pk'] ?? '' ) : ( $core_settings['ckocom_pk'] ?? '' );
		$secret_key = $use_fallback ? ( $core_settings['fallback_ckocom_sk'] ?? '' ) : ( $core_settings['ckocom_sk'] ?? '' );
		
		if ( empty( $public_key ) || empty( $secret_key ) ) {
			// Keys not set yet - this is normal during initial setup or key registration
			// Don't initialize SDK, but don't log as error (only log if WP_DEBUG is enabled)
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				WC_Checkoutcom_Utility::logger( 'Checkout.com SDK not initialized: API keys not configured yet.' );
			}
			return;
		}
		
		$environment   = 'sandbox' === ( $core_settings['ckocom_environment'] ?? 'sandbox' ) ? Environment::sandbox() : Environment::production();
		// Derive the merchant-specific subdomain from the configured Client ID.
		// Checkout.com's MSSD scheme uses the first 8 chars of the client_id (the part
		// after the "cli_" prefix) as the API subdomain. We also accept a bare subdomain
		// if that's what was entered. Falls back to the global domain when blank/invalid.
		$client_id = isset( $core_settings['ckocom_client_id'] ) ? strtolower( trim( (string) $core_settings['ckocom_client_id'] ) ) : '';
		$subdomain = '';
		if ( '' !== $client_id ) {
			$raw = ( 0 === strpos( $client_id, 'cli_' ) ) ? substr( $client_id, 4 ) : $client_id;
			$candidate = substr( $raw, 0, 8 );
			// SDK requires: optional "pl-" prefix then lowercase alphanumerics.
			if ( preg_match( '/^(?:pl-)?[a-z0-9]+$/', $candidate ) ) {
				$subdomain = $candidate;
			}
		}

		// All Checkout.com accounts are NAS; the SDK's NAS static-keys builder is
		// always used (the legacy ABC "previous" builder has been removed).
		$builder = CheckoutSdk::builder()->staticKeys();

		$builder->publicKey( $public_key );
		$builder->secretKey( $secret_key );
		$builder->environment( $environment );

		if ( '' !== $subdomain ) {
			// Merchant-specific subdomain configured: route to the merchant subdomain.
			$builder->environmentSubdomain( $subdomain );
		} else {
			// SDK 6.x requires either a merchant-specific subdomain or an explicit
			// opt-out; with no subdomain configured, use the global api.checkout.com domain.
			$builder->useLegacyDomain();
		}

		if ( $use_fallback ) {
			$builder->publicKey( $core_settings['fallback_ckocom_pk'] ?? '' );
			$builder->secretKey( $core_settings['fallback_ckocom_sk'] ?? '' );
		}

		try {

			$this->builder = $builder->build();

		} catch ( CheckoutArgumentException $e ) {
			WC_Checkoutcom_Utility::logger( $e->getMessage(), $e );
		}
	}

	/**
	 * Returns the CheckoutApi object.
	 *
	 * @return object
	 */
	public function get_builder() {
		return $this->builder;
	}

	/**
	 * Returns the PaymentRequest object.
	 *
	 * @return object
	 */
	public function get_payment_request() {
		if ( ! class_exists( 'Checkout\CheckoutSdk' ) ) {
			return null;
		}

		return new Checkout\Payments\Request\PaymentRequest();
	}

	/**
	 * Returns the CaptureRequest object.
	 *
	 * @return object
	 */
	public function get_capture_request() {
		if ( ! class_exists( 'Checkout\CheckoutSdk' ) ) {
			return null;
		}

		return new Checkout\Payments\CaptureRequest();
	}
}
