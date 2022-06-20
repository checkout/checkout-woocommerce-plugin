<?php
/**
 * Giropay APM class.
 *
 * @package wc_checkout_com
 */

/**
 * Class WC_Gateway_Checkout_Com_Alternative_Payments_Giropay
 *
 * @class   WC_Gateway_Checkout_Com_Alternative_Payments_Giropay
 * @extends WC_Gateway_Checkout_Com_Alternative_Payments
 */
class WC_Gateway_Checkout_Com_Alternative_Payments_Giropay extends WC_Gateway_Checkout_Com_Alternative_Payments {

	const PAYMENT_METHOD = 'giropay';

	/**
	 * Construct method.
	 */
	public function __construct() {
		$this->id         = 'wc_checkout_com_alternative_payments_giropay';
		$this->title      = __( 'Giropay', 'checkout-com-unified-payments-api' );
		$this->has_fields = true;
		$this->supports   = [ 'products', 'refunds' ];

		$this->init_form_fields();

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );

	}

	/**
	 * Payment fields to be displayed.
	 */
	public function payment_fields() {
		// get available apms depending on currency.
		$apm_available = WC_Checkoutcom_Utility::get_alternative_payment_methods();
		$message       = __( 'Pay with Giropay. You will be redirected upon place order', 'checkout-com-unified-payments-api' );

		?>
			<p style="margin-bottom: 0;"> <?php echo $message; ?> </p>
		<?php

		if ( ! in_array( self::PAYMENT_METHOD, $apm_available, true ) ) {
			?>
				<script>
					jQuery('.payment_method_wc_checkout_com_alternative_payments_giropay').hide();
				</script>
			<?php
		}

	}

	/**
	 * Process Giropay APM payment.
	 *
	 * @global $woocommerce
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		if ( ! session_id() ) {
			session_start();
		}

		global $woocommerce;

		$order = wc_get_order( $order_id );

		// create alternative payment.
		$result = (array) WC_Checkoutcom_Api_Request::create_apm_payment( $order, self::PAYMENT_METHOD );

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
