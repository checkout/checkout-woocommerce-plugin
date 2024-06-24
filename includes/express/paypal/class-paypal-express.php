<?php
/**
 * PayPal Express handler.
 *
 * @package wc_checkout_com
 */

defined( 'ABSPATH' ) || exit;

class CKO_Paypal_Express {

	private static $instance = null;

	public static function get_instance(): CKO_Paypal_Express {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		$paypal_settings = get_option( 'woocommerce_wc_checkout_com_paypal_settings' );

		$is_express_enable = ! empty( $paypal_settings['paypal_express'] ) && 'yes' === $paypal_settings['paypal_express'];
		$paypal_enabled    = ! empty( $paypal_settings['enabled'] ) && 'yes' === $paypal_settings['enabled'];

		if ( ! $paypal_enabled || ! $is_express_enable ) {
			return;
		}

		add_action( 'woocommerce_after_add_to_cart_form', [ $this, 'display_payment_request_button_html' ], 1 );

		add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );

		add_filter( 'woocommerce_available_payment_gateways', [ $this, 'disable_other_gateways' ] );

		add_action( 'woocommerce_review_order_after_submit', [ $this, 'cancel_paypal_session_markup' ] );

		add_action( 'woocommerce_init', [ $this, 'express_cancel_session' ] );

		// Clear PayPal session if any of the below action run.
		add_action( 'woocommerce_cart_emptied', [ $this, 'empty_paypal_session' ], 1 );
		add_action( 'woocommerce_cart_item_removed', [ $this, 'empty_paypal_session' ], 1 );
		add_action( 'woocommerce_update_cart_action_cart_updated', [ $this, 'empty_paypal_session' ], 1 );
		add_action( 'woocommerce_cart_item_set_quantity', [ $this, 'empty_paypal_session' ], 1 );
		add_action( 'woocommerce_add_to_cart', [ $this, 'empty_paypal_session' ], 1 );

		/**
		 * Filters.
		 */
		add_filter( 'woocommerce_checkout_get_value', [ $this, 'fill_paypal_selected_address_field' ], 10, 2 );
	}

	public function payment_scripts() {

		// Load on Cart, Checkout, pay for order or add payment method pages.
		if ( ! is_product() || ! WC_Checkoutcom_Utility::is_paypal_express_available() ) {
			return;
		}

		$core_settings   = get_option( 'woocommerce_wc_checkout_com_cards_settings' );
		$paypal_settings = get_option( 'woocommerce_wc_checkout_com_paypal_settings' );
		$environment     = 'sandbox' === $core_settings['ckocom_environment'];

		if ( $environment ) {
			// sandbox.
			$paypal_js_arg['client-id'] = 'ASLqLf4pnWuBshW8Qh8z_DRUbIv2Cgs3Ft8aauLm9Z-MO9FZx1INSo38nW109o_Xvu88P3tly88XbJMR';
		} else {
			// live.
			$paypal_js_arg['client-id'] = 'ATbi1ysGm-jp4RmmAFz1EWH4dFpPd-VdXIWWzR4QZK5LAvDu_5atDY9dsUEJcLS5mTpR8Wb1l_m6Ameq';
		}

		$paypal_js_arg['merchant-id'] = $paypal_settings[ 'ckocom_paypal_merchant_id' ] ?? '';

		$paypal_js_arg['disable-funding'] = 'credit,card,sepa';
		$paypal_js_arg['commit']          = 'false';
		$paypal_js_arg['currency']        = get_woocommerce_currency();
		$paypal_js_arg['intent']          = 'capture'; // 'authorize' // ???

		if ( WC_Checkoutcom_Utility::is_cart_contains_subscription() ) {
			$paypal_js_arg['intent'] = 'tokenize';
			$paypal_js_arg['vault']  = 'true';
		}

		$paypal_js_url = add_query_arg( $paypal_js_arg, 'https://www.paypal.com/sdk/js' );

		wp_register_script( 'cko-paypal-script', $paypal_js_url, [ 'jquery' ], null );

		$vars = [
			'add_to_cart_url'                  => add_query_arg( [ 'cko_paypal_action' => 'express_add_to_cart'], WC()->api_request_url( 'CKO_Paypal_Woocommerce' ) ),
			'create_order_url'                 => add_query_arg( [ 'cko_paypal_action' => 'express_create_order'], WC()->api_request_url( 'CKO_Paypal_Woocommerce' ) ),
			'paypal_order_session_url'         => add_query_arg( [ 'cko_paypal_action' => 'express_paypal_order_session'], WC()->api_request_url( 'CKO_Paypal_Woocommerce' ) ),
			'cc_capture'                       => add_query_arg( [ 'cko_paypal_action' => 'cc_capture' ], WC()->api_request_url( 'CKO_Paypal_Woocommerce' ) ),
			'woocommerce_process_checkout'     => wp_create_nonce('woocommerce-process_checkout'),
			'is_cart_contains_subscription'    => WC_Checkoutcom_Utility::is_cart_contains_subscription(),
			'paypal_button_selector'           => '#cko-paypal-button-wrapper',
			'redirect'                         => wc_get_checkout_url(),
			'paypal_express_add_to_cart_nonce' => wp_create_nonce( 'checkoutcom_paypal_express_add_to_cart' ),
            'debug'                            => 'yes' === WC_Admin_Settings::get_option( 'cko_console_logging', 'no' ),
		];

		wp_localize_script( 'cko-paypal-script', 'cko_paypal_vars', $vars );

		wp_enqueue_script( 'cko-paypal-script' );

		wp_register_script(
			'cko-paypal-express-integration-script',
			WC_CHECKOUTCOM_PLUGIN_URL . '/assets/js/cko-paypal-express-integration.js',
			[ 'jquery', 'cko-paypal-script' ],
			WC_CHECKOUTCOM_PLUGIN_VERSION
		);

		wp_enqueue_script( 'cko-paypal-express-integration-script' );
	}

	public function display_payment_request_button_html() {

		if ( ! is_product() || ! WC_Checkoutcom_Utility::is_paypal_express_available() ) {
			return;
		}

		?>
		<style>
			.cko-disabled {
				cursor: not-allowed;
				-webkit-filter: grayscale(100%);
				filter: grayscale(100%);
			}
		</style>
		<div id="cko-paypal-button-wrapper" style="margin-top: 1em;clear:both;display:none;"></div>
		<?php
	}

	public function disable_other_gateways( array $methods ) {

		if ( ! isset( $methods[ 'wc_checkout_com_paypal' ] ) ) {
			return $methods;
		}

		$cko_paypal_order_id = WC_Checkoutcom_Utility::cko_get_session( 'cko_paypal_order_id' );
		$cko_pc_id           = WC_Checkoutcom_Utility::cko_get_session( 'cko_pc_id' );

		// Check if PayPal session variable exist for current customer.
		$disable_all_gateway = ! empty( $cko_pc_id ) && ! empty( $cko_paypal_order_id );

		if ( $disable_all_gateway ) {
			return [ 'wc_checkout_com_paypal' => $methods[ 'wc_checkout_com_paypal' ] ];
		}

		return $methods;
	}

	public function cancel_paypal_session_markup() {
		$cko_paypal_order_id = WC_Checkoutcom_Utility::cko_get_session( 'cko_paypal_order_id' );
		$cko_pc_id           = WC_Checkoutcom_Utility::cko_get_session( 'cko_pc_id' );

		// Check if PayPal session variable exist for current customer.
		$paypal_session_exist = ! empty( $cko_pc_id ) && ! empty( $cko_paypal_order_id );

		$cancel_url = add_query_arg( [ 'cko-paypal-session-cancel' => '1', 'cko-paypal-session-cancel-nonce' => wp_create_nonce( 'checkoutcom_paypal_cancel' ), ], wc_get_checkout_url() );

		if ( ! $paypal_session_exist ) {
			return;
		}

		ob_start();
		?>
		<p id="cko-paypal-cancel" class="has-text-align-center">
		<?php

		printf(
		// translators: %3$ is funding source like "PayPal" or "Venmo", other placeholders are html tags for a link.
			esc_html__(
				'You are currently paying with PayPal. %1$s%2$sChoose another payment method%3$s.',
				'woocommerce-paypal-payments'
			),
			'<br/>',
			'<a href="' . esc_url( $cancel_url ) . '">',
			'</a>',
		);

		?></p><?php
		echo ob_get_clean();
	}

	/**
	 * Handle cancel PayPal express session request.
	 */
	public function express_cancel_session() {

		if (
				! isset( $_GET[ 'cko-paypal-session-cancel-nonce' ] )
				|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET[ 'cko-paypal-session-cancel-nonce' ] ) ), 'checkoutcom_paypal_cancel' )
		) {
			return;
		}

		self::empty_paypal_session();
	}

	/**
	 * Empty PayPal session variable.
	 */
	public function empty_paypal_session() {
		WC_Checkoutcom_Utility::cko_set_session( 'cko_paypal_order_id', '' );
		WC_Checkoutcom_Utility::cko_set_session( 'cko_pc_id', '' );
		WC_Checkoutcom_Utility::cko_set_session( 'cko_pc_details', '' );
	}

	/**
	 * Set default checkout field value from PayPal Express.
	 *
	 * @param  string $value Default checkout field value.
	 * @param  string $key   The checkout form field name/key
	 *
	 * @return string $value Checkout field value.
	 */
	public function fill_paypal_selected_address_field( $value, $key ) {

		if ( ! session_id() ) {
			session_start();
		}

		$cko_paypal_order_id = WC_Checkoutcom_Utility::cko_get_session( 'cko_paypal_order_id' );
		$cko_pc_id           = WC_Checkoutcom_Utility::cko_get_session( 'cko_pc_id' );
		$cko_pc_details      = WC_Checkoutcom_Utility::cko_get_session( 'cko_pc_details' );

		if ( empty( $cko_pc_id ) ) {
			return $value;
		}

		if ( empty( $cko_pc_details ) ) {
			try {
				$checkout       = new Checkout_SDK();
				$response       = $checkout->get_builder()->getPaymentContextsClient()->getPaymentContextDetails( $cko_pc_id );
				$cko_pc_details = $response;

				WC_Checkoutcom_Utility::cko_set_session( 'cko_pc_details', $response );

			} catch ( CheckoutApiException $ex ) {}
		}

		if ( isset( $cko_pc_details['payment_request']['shipping']['address'] ) ) {
			$paypal_shipping_address    = $cko_pc_details['payment_request']['shipping']['address'];
			$shipping_name              = $cko_pc_details['payment_request']['shipping']['first_name'] ? explode( ' ', $cko_pc_details['payment_request']['shipping']['first_name'] ) : [];
			$paypal_shipping_first_name = $shipping_name[0] ?? '';
			$paypal_shipping_last_name  = $shipping_name[1] ?? '';

			switch ( $key ) {
				case 'billing_first_name':
				case 'shipping_first_name':
					return $paypal_shipping_first_name;

				case 'billing_last_name':
				case 'shipping_last_name':
					return $paypal_shipping_last_name;

				case 'billing_address_1':
				case 'shipping_address_1':
					return $paypal_shipping_address[ 'address_line1' ];

				case 'billing_address_2':
				case 'shipping_address_2':
					return $paypal_shipping_address[ 'address_line2' ]  ?? '';

				case 'billing_city':
				case 'shipping_city':
					return $paypal_shipping_address[ 'city' ];

				case 'billing_postcode':
				case 'shipping_postcode':
					return $paypal_shipping_address[ 'zip' ];

				case 'billing_country':
				case 'shipping_country':
					return $paypal_shipping_address[ 'country' ];
			}
		}

		return $value;
	}
}

CKO_Paypal_Express::get_instance();