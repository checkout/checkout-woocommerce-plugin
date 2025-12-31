<?php
/**
 * Checkout SDK wrapper class.
 *
 * @package wc_checkout_com
 */

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
	 * Account type.
	 *
	 * @var bool
	 */
	private $nas_account_type;

	/**
	 * Constructor.
	 *
	 * @param bool $use_fallback Use Fallback account flag.
	 */
	public function __construct( $use_fallback = false ) {
		// Check if SDK classes are available
		if ( ! class_exists( 'Checkout\CheckoutSdk' ) ) {
			// Only log this error once per hour to avoid log spam across multiple requests
			$transient_key = 'cko_sdk_error_logged';
			$error_logged = get_transient( $transient_key );
			
			if ( ! $error_logged ) {
				$autoloader_path = dirname( dirname( __FILE__ ) ) . '/vendor/autoload.php';
				$autoloader_exists = file_exists( $autoloader_path );
				
				// Only log in admin context or if this is a critical operation
				$should_log = is_admin() || ( defined( 'WP_CLI' ) && WP_CLI );
				
				if ( $should_log ) {
					if ( ! $autoloader_exists ) {
						WC_Checkoutcom_Utility::logger( 'Checkout.com SDK: vendor/autoload.php not found at ' . $autoloader_path . '. Please run "composer install" in the plugin directory to install dependencies.' );
					} else {
						WC_Checkoutcom_Utility::logger( 'Checkout.com SDK: vendor/autoload.php exists but SDK classes are not loaded. The autoloader may not be included properly.' );
					}
					// Set transient to prevent logging again for 1 hour
					set_transient( $transient_key, true, HOUR_IN_SECONDS );
				}
			}
			return;
		}
		
		$core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings', array() );
		
		// Check if settings exist
		if ( empty( $core_settings ) ) {
			WC_Checkoutcom_Utility::logger( 'Checkout.com SDK: Core settings not found. Please configure the plugin settings first.' );
			return;
		}
		
		// Check if keys are present
		$public_key = $use_fallback ? ( $core_settings['fallback_ckocom_pk'] ?? '' ) : ( $core_settings['ckocom_pk'] ?? '' );
		$secret_key = $use_fallback ? ( $core_settings['fallback_ckocom_sk'] ?? '' ) : ( $core_settings['ckocom_sk'] ?? '' );
		
		if ( empty( $public_key ) || empty( $secret_key ) ) {
			WC_Checkoutcom_Utility::logger( 'Checkout.com SDK: Public key or Secret key is missing. Please configure your API keys in the plugin settings.' );
			return;
		}
		
		$environment = 'sandbox' === ( $core_settings['ckocom_environment'] ?? 'sandbox' ) ? Environment::sandbox() : Environment::production();
		$subdomain   = ! isset( $core_settings['ckocom_region'] ) ? '--' : $core_settings['ckocom_region'];

		$this->nas_account_type = cko_is_nas_account();

		if ( $this->nas_account_type && false === $use_fallback ) {
			$builder = CheckoutSdk::builder()->staticKeys();
		} else {
			$builder = CheckoutSdk::builder()->previous()->staticKeys();
		}

		$builder->publicKey( $public_key );
		$builder->secretKey( $secret_key );
		$builder->environment( $environment );

		if ( '--' !== $subdomain ) {
			$builder->environmentSubdomain( $subdomain );
		}

		if ( $use_fallback ) {
			$builder->publicKey( $core_settings['fallback_ckocom_pk'] ?? '' );
			$builder->secretKey( $core_settings['fallback_ckocom_sk'] ?? '' );
		}

		try {
			$this->builder = $builder->build();
		} catch ( CheckoutArgumentException $e ) {
			WC_Checkoutcom_Utility::logger( 'Checkout.com SDK initialization failed: ' . $e->getMessage(), $e );
			$this->builder = null;
		} catch ( \Exception $e ) {
			WC_Checkoutcom_Utility::logger( 'Checkout.com SDK initialization error: ' . $e->getMessage(), $e );
			$this->builder = null;
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
		
		if ( $this->nas_account_type ) {
			return new Checkout\Payments\Request\PaymentRequest();
		} else {
			return new Checkout\Payments\Previous\PaymentRequest();
		}
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
		
		if ( $this->nas_account_type ) {
			return new Checkout\Payments\CaptureRequest();
		} else {
			return new Checkout\Payments\Previous\CaptureRequest();
		}
	}
}
