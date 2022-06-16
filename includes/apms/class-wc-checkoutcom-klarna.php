<?php
/**
 * Klarna APM class.
 *
 * @package wc_checkout_com
 */

/**
 * Class WC_Gateway_Checkout_Com_Alternative_Payments_Klarna
 *
 * @class   WC_Gateway_Checkout_Com_Alternative_Payments_Klarna
 * @extends WC_Gateway_Checkout_Com_Alternative_Payments
 */
class WC_Gateway_Checkout_Com_Alternative_Payments_Klarna extends WC_Gateway_Checkout_Com_Alternative_Payments {

	const PAYMENT_METHOD = 'klarna';

	/**
	 * Construct method.
	 */
	public function __construct() {
		$this->id                 = 'wc_checkout_com_alternative_payments_klarna';
		$this->method_title       = __( 'Checkout.com', 'wc_checkout_com' );
		$this->method_description = __( 'The Checkout.com extension allows shop owners to process online payments through the <a href="https://www.checkout.com">Checkout.com Payment Gateway.</a>', 'wc_checkout_com' );
		$this->title              = __( 'Klarna', 'wc_checkout_com' );
		$this->has_fields         = true;
		$this->supports           = [ 'products', 'refunds' ];

		$this->init_form_fields();

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
	}

	/**
	 * Payment fields to be displayed.
	 */
	public function payment_fields() {
		// get available apms depending on currency.
		$apm_available = WC_Checkoutcom_Utility::get_alternative_payment_methods();

		?>
			<input type="hidden" id="cko-klarna-token" name="cko-klarna-token" value="" />
		<?php

		if ( ! in_array( self::PAYMENT_METHOD, $apm_available, true ) ) {
			?>
				<script>
					jQuery('.payment_method_wc_checkout_com_alternative_payments_klarna').hide();
				</script>
			<?php
		} else {
			$klarna_session            = WC_Checkoutcom_Api_request::klarna_session();
			$client_token              = $klarna_session->client_token;
			$payment_method_categories = $klarna_session->payment_method_categories;
			WC_Checkoutcom_Apm_Templates::get_klarna( $client_token, $payment_method_categories );
			?>

			<input type="hidden" id="klarna-client-token" value="<?php echo $client_token; ?>">
			<div id="cart-info" data-cart='<?php echo json_encode( WC_Checkoutcom_Api_request::get_cart_info() ); ?>'></div>

			<div class="klarna-details"></div>
			<div id="klarna_container"></div>

			<!-- klarna js file -->
			<script src='<?php echo WC_CHECKOUTCOM_PLUGIN_URL . '/assets/js/klarna.js'; ?>'></script>

			<?php
		}

	}

	/**
	 * Process Klarna APM payment.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {
		if ( ! session_id() ) {
			session_start();
		}

		$order = wc_get_order( $order_id );

		// create alternative payment.
		$result = (array) WC_Checkoutcom_Api_request::create_apm_payment( $order, self::PAYMENT_METHOD );

		// check if result has error and return error message.
		if ( isset( $result['error'] ) && ! empty( $result['error'] ) ) {
			WC_Checkoutcom_Utility::wc_add_notice_self( $result['error'], 'error' );
			return;
		}

		// redirect to apm if redirection url is available.
		if ( isset( $result['apm_redirection'] ) && ! empty( $result['apm_redirection'] ) ) {

			return [
				'result'   => 'success',
				'redirect' => $result['apm_redirection'],
			];
		}
	}

	/**
	 * Process refund for the order.
	 *
	 * @param int    $order_id Order ID.
	 * @param int    $amount   Amount to refund.
	 * @param string $reason   Refund reason.
	 *
	 * @return bool
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {

		return parent::process_refund( $order_id, $amount, $reason );

	}
}
