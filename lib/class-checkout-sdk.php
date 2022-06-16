<?php
include_once dirname( __DIR__ ) . '/includes/api/class-wc-checkoutcom-utility.php';

use Checkout\CheckoutDefaultSdk;
use Checkout\CheckoutFourSdk;
use Checkout\Environment;
use Checkout\CheckoutArgumentException;

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
	 */
	public function __construct() {

		$core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings' );
		$environment   = 'sandbox' === $core_settings['ckocom_environment'] ? Environment::sandbox() : Environment::production();

		$this->nas_account_type = cko_is_nas_account();

		if ( $this->nas_account_type ) {
			$builder = CheckoutFourSdk::staticKeys();
		} else {
			$builder = CheckoutDefaultSdk::staticKeys();
		}

		$builder->setPublicKey( $core_settings['ckocom_pk'] );
		$builder->setSecretKey( $core_settings['ckocom_sk'] );
		$builder->setEnvironment( $environment );

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
			return new Checkout\Payments\Four\Request\PaymentRequest();
		} else {
			return new Checkout\Payments\PaymentRequest();
		}
	}

	/**
	 * Returns the CaptureRequest object.
	 *
	 * @return object
	 */
	public function get_capture_request() {
		if ( $this->nas_account_type ) {
			return new Checkout\Payments\Four\CaptureRequest();
		} else {
			return new Checkout\Payments\CaptureRequest();
		}
	}

}
