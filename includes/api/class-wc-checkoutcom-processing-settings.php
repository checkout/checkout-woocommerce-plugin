<?php
/**
 * Processing settings with the scheme Transaction Link Identifier (TLID).
 *
 * @package wc_checkout_com
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WC_Checkoutcom_Processing_Settings', false ) ) {
	return;
}

/**
 * The bundled SDK (3.2.x) has no processing.scheme_transaction_link_id field. The SDK
 * serializes every public, non-null property, so adding it here sends it in the request
 * and leaves requests without a TLID unchanged.
 *
 * Loaded on demand (WC_Checkoutcom_Api_Request::new_processing_settings()) so the SDK
 * autoloader is in place before the parent class is needed.
 */
class WC_Checkoutcom_Processing_Settings extends \Checkout\Payments\ProcessingSettings {

	/**
	 * Mastercard Transaction Link Identifier from the first payment in the credential chain.
	 *
	 * @var string|null
	 */
	public $scheme_transaction_link_id;
}
