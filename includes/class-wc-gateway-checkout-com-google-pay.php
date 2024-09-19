<?php
/**
 * Google Pay method class.
 *
 * @package wc_checkout_com
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Gateway_Checkout_Com_Google_Pay for Google Pay payment method.
 */
class WC_Gateway_Checkout_Com_Google_Pay extends WC_Payment_Gateway {

	/**
	 * WC_Gateway_Checkout_Com_Google_Pay constructor.
	 */
	public function __construct() {
		$this->id                 = 'wc_checkout_com_google_pay';
		$this->method_title       = __( 'Checkout.com', 'checkout-com-unified-payments-api' );
		$this->method_description = __( 'The Checkout.com extension allows shop owners to process online payments through the <a href="https://www.checkout.com">Checkout.com Payment Gateway.</a>', 'checkout-com-unified-payments-api' );
		$this->title              = __( 'Google Pay', 'checkout-com-unified-payments-api' );
		$this->has_fields         = true;
		$this->supports           = [
			'products',
			'refunds',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_date_changes',
		];

		$this->init_form_fields();
		$this->init_settings();

		// Turn these settings into variables we can use.
		foreach ( $this->settings as $setting_key => $value ) {
			$this->$setting_key = $value;
		}

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );

		// Payment scripts.
		add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );
	}

	/**
	 * Outputs scripts used for checkout payment.
	 */
	public function payment_scripts() {
		// Load on Cart, Checkout, pay for order or add payment method pages.
		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) && ! is_add_payment_method_page() ) {
			return;
		}

		// Load cko google pay setting.
		$google_settings    = get_option( 'woocommerce_wc_checkout_com_google_pay_settings' );
		$google_pay_enabled = ! empty( $google_settings['enabled'] ) && 'yes' === $google_settings['enabled'];

		wp_register_script( 'cko-google-script', 'https://pay.google.com/gp/p/js/pay.js', [ 'jquery' ] );
		wp_register_script( 'cko-google-pay-integration-script', WC_CHECKOUTCOM_PLUGIN_URL . '/assets/js/cko-google-pay-integration.js', [ 'jquery', 'cko-google-script' ], WC_CHECKOUTCOM_PLUGIN_VERSION );

		// Enqueue google pay script.
		if ( $google_pay_enabled ) {

			$core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings' );
			$environment   = 'sandbox' === $core_settings['ckocom_environment'];
			$currency_code = get_woocommerce_currency();
			$total_price   = WC()->cart->total;

			$vars = [
				'environment'   => $environment ? 'TEST' : 'PRODUCTION',
				'public_key'    => $core_settings['ckocom_pk'],
				'merchant_id'   => $this->get_option( 'ckocom_google_merchant_id' ),
				'currency_code' => $currency_code,
				'total_price'   => $total_price,
				'button_type'   => $this->get_option( 'ckocom_google_style', 'google-pay-black' ),
			];

			wp_localize_script( 'cko-google-pay-integration-script', 'cko_google_pay_vars', $vars );

			wp_enqueue_script( 'cko-google-pay-integration-script' );
		}
	}

	/**
	 * Show module configuration in backend.
	 *
	 * @return string|void
	 */
	public function init_form_fields() {
		$this->form_fields = WC_Checkoutcom_Cards_Settings::google_settings();
		$this->form_fields = array_merge(
			$this->form_fields,
			[
				'screen_button' => [
					'id'    => 'screen_button',
					'type'  => 'screen_button',
					'title' => __( 'Other Settings', 'checkout-com-unified-payments-api' ),
				],
			]
		);
	}

	/**
	 * Generate links for the admin page.
	 *
	 * @param string $key The key.
	 * @param array  $value The value.
	 */
	public function generate_screen_button_html( $key, $value ) {
		WC_Checkoutcom_Admin::generate_links( $key, $value );
	}

	/**
	 * Show frames js on checkout page.
	 */
	public function payment_fields() {

		if ( ! empty( $this->get_option( 'description' ) ) ) {
			echo  $this->get_option( 'description' );
		}

		?>
		<input type="hidden" id="cko-google-signature" name="cko-google-signature" value="" />
		<input type="hidden" id="cko-google-protocolVersion" name="cko-google-protocolVersion" value="" />
		<input type="hidden" id="cko-google-signedMessage" name="cko-google-signedMessage" value="" />
		<?php
	}

	/**
	 * Process payment with Google Pay.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {
		if ( ! session_id() ) {
			session_start();
		}

		$order = new WC_Order( $order_id );

		// create google token from Google payment data.
		$google_token = WC_Checkoutcom_Api_Request::generate_google_token();

		// Check if google token is not empty.
		if ( empty( $google_token['token'] ) ) {
			WC_Checkoutcom_Utility::wc_add_notice_self( __( 'There was an issue completing the payment.', 'checkout-com-unified-payments-api' ), 'error' );
			return;
		}

		// Create payment with Google token.
		$result = (array) ( new WC_Checkoutcom_Api_Request )->create_payment( $order, $google_token );

		// Redirect to apm if redirection url is available.
		if ( isset( $result['3d'] ) && ! empty( $result['3d'] ) ) {

			$order->add_order_note(
				sprintf(
					esc_html__( 'Checkout.com 3d Redirect waiting. URL : %s', 'checkout-com-unified-payments-api' ),
					$result['3d']
				)
			);

			return [
				'result'   => 'success',
				'redirect' => $result['3d'],
			];
		}

		// check if result has error and return error message.
		if ( isset( $result['error'] ) && ! empty( $result['error'] ) ) {
			WC_Checkoutcom_Utility::wc_add_notice_self( $result['error'] );
			return;
		}

		if ( class_exists( 'WC_Subscriptions_Order' ) ) {
			// Save source id for subscription.
			WC_Checkoutcom_Subscription::save_source_id( $order_id, $order, $result['source']['id'] );
		}

		// Set action id as woo transaction id.
		$order->set_transaction_id( $result['action_id'] );
		$order->update_meta_data( '_cko_payment_id', $result['id'] );

		// Get cko auth status configured in admin.
		$status = WC_Admin_Settings::get_option( 'ckocom_order_authorised', 'on-hold' );

		/* translators: %s: Action ID. */
		$message = sprintf( esc_html__( 'Checkout.com Payment Authorised - Action ID : %s', 'checkout-com-unified-payments-api' ), $result['action_id'] );

		// check if payment was flagged.
		if ( $result['risk']['flagged'] ) {
			// Get cko auth status configured in admin.
			$status = WC_Admin_Settings::get_option( 'ckocom_order_flagged', 'flagged' );

			/* translators: %s: Action ID. */
			$message = sprintf( esc_html__( 'Checkout.com Payment Flagged - Action ID : %s', 'checkout-com-unified-payments-api' ), $result['action_id'] );
		}

		// add notes for the order and update status.
		$order->add_order_note( $message );
		$order->update_status( $status );

		// Reduce stock levels.
		wc_reduce_stock_levels( $order_id );

		// Remove cart.
		WC()->cart->empty_cart();

		// Return thank you page.
		return [
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		];
	}

	/**
	 * Handle Google Pay refund.
	 *
	 * @param int    $order_id Order ID.
	 * @param null   $amount  Amount to refund.
	 * @param string $reason Reason for refund.
	 *
	 * @return bool|WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order  = wc_get_order( $order_id );
		$result = (array) WC_Checkoutcom_Api_Request::refund_payment( $order_id, $order );

		// check if result has error and return error message.
		if ( ! empty( $result['error'] ) ) {
			WC_Checkoutcom_Utility::wc_add_notice_self( $result['error'] );
			return false;
		}

		// Set action id as woo transaction id.
		$order->set_transaction_id( $result['action_id'] );
		$order->save();

		// Get cko auth status configured in admin.
		$status = WC_Admin_Settings::get_option( 'ckocom_order_refunded', 'refunded' );

		/* translators: %s: Action ID. */
		$message = sprintf( esc_html__( 'Checkout.com Payment refunded - Action ID : %s', 'checkout-com-unified-payments-api' ), $result['action_id'] );

		if ( isset( $_SESSION['cko-refund-is-less'] ) ) {
			if ( $_SESSION['cko-refund-is-less'] ) {
				/* translators: %s: Action ID. */
				$order->add_order_note( sprintf( esc_html__( 'Checkout.com Payment Partially refunded - Action ID : %s', 'checkout-com-unified-payments-api' ), $result['action_id'] ) );

				unset( $_SESSION['cko-refund-is-less'] );

				return true;
			}
		}

		// Add notes for the order and update status.
		$order->add_order_note( $message );
		$order->update_status( $status );

		return true;
	}

}
