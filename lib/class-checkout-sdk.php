<?php
/**
 * Checkout SDK wrapper class.
 *
 * @package wc_checkout_com
 */

use Checkout\CheckoutSdk;
use Checkout\Environment;
use Checkout\CheckoutArgumentException;

include_once dirname( __DIR__ ) . '/includes/api/class-wc-checkoutcom-utility.php';

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
		$core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings' );
		$environment   = 'sandbox' === $core_settings['ckocom_environment'] ? Environment::sandbox() : Environment::production();

		$this->nas_account_type = cko_is_nas_account();

		if ( $this->nas_account_type && false === $use_fallback ) {
			$builder = CheckoutSdk::builder()->staticKeys();
		} else {
			$builder = CheckoutSdk::builder()->previous()->staticKeys();
		}

		$builder->publicKey( $core_settings['ckocom_pk'] );
		$builder->secretKey( $core_settings['ckocom_sk'] );
		$builder->environment( $environment );

		if ( $use_fallback ) {
			$builder->publicKey( $core_settings['fallback_ckocom_pk'] );
			$builder->secretKey( $core_settings['fallback_ckocom_sk'] );
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
		if ( $this->nas_account_type ) {
			return new Checkout\Payments\CaptureRequest();
		} else {
			return new Checkout\Payments\Previous\CaptureRequest();
		}
	}
}