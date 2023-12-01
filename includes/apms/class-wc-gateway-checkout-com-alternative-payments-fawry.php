<?php
/**
 * Fawry APM class.
 *
 * @package wc_checkout_com
 */

/**
 * Class WC_Gateway_Checkout_Com_Alternative_Payments_Fawry
 *
 * @class   WC_Gateway_Checkout_Com_Alternative_Payments_Fawry
 * @extends WC_Gateway_Checkout_Com_Alternative_Payments
 */
class WC_Gateway_Checkout_Com_Alternative_Payments_Fawry extends WC_Gateway_Checkout_Com_Alternative_Payments {

	const PAYMENT_METHOD = 'fawry';

	/**
	 * Construct method.
	 */
	public function __construct() {
		$this->id                 = 'wc_checkout_com_alternative_payments_fawry';
		$this->method_title       = __( 'Checkout.com', 'checkout-com-unified-payments-api' );
		$this->method_description = __( 'The Checkout.com extension allows shop owners to process online payments through the <a href="https://www.checkout.com">Checkout.com Payment Gateway.</a>', 'checkout-com-unified-payments-api' );
		$this->title              = __( 'Fawry', 'checkout-com-unified-payments-api' );
		$this->supports           = [ 'products', 'refunds' ];
		$this->has_fields         = true;

		$this->init_form_fields();

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
	}

	/**
	 * Payment fields to be displayed.
	 */
	public function payment_fields() {
		// get available apms depending on currency.
		$apm_available = WC_Checkoutcom_Utility::get_alternative_payment_methods();
		$message       = __( 'Pay with Fawry', 'checkout-com-unified-payments-api' );

		?>
			<p style="margin-bottom: 0;"> <?php echo $message; ?> </p>
		<?php

		if ( ! in_array( self::PAYMENT_METHOD, $apm_available, true ) ) {
			?>
				<script>
					jQuery('.payment_method_wc_checkout_com_alternative_payments_fawry').hide();
				</script>
			<?php
		}
	}

	/**
	 * Process Fawry APM payment.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		if ( ! session_id() ) {
			session_start();
		}

		$order = wc_get_order( $order_id );

		// create alternative payment.
		$result = (array) WC_Checkoutcom_Api_Request::create_apm_payment( $order, self::PAYMENT_METHOD );

		// check if result has error and return error message.
		if ( ! empty( $result['error'] ) ) {
			WC_Checkoutcom_Utility::wc_add_notice_self( $result['error'], 'error' );
			return;
		}

		$status  = WC_Admin_Settings::get_option( 'ckocom_order_authorised', 'on-hold' );
		$message = '';

		if ( self::PAYMENT_METHOD === $result['source']['type'] ) {
			$order->update_meta_data( 'cko_fawry_reference_number', $result['source']['reference_number'] );
			$order->update_meta_data( 'cko_payment_authorized', true );

			// Get cko auth status configured in admin.
			$message = sprintf(
				/* translators: 1: Result ID, 2: Payment reference number. */
				esc_html__( 'Checkout.com - Fawry payment - Action ID : %1$s - Fawry reference number : %2$s', 'checkout-com-unified-payments-api' ),
				$result['id'],
				$result['source']['reference_number']
			);

			if ( 'Captured' === $result['status'] ) {
				$status  = WC_Admin_Settings::get_option( 'ckocom_order_captured', 'processing' );
				$message = sprintf(
					/* translators: %s: Result ID. */
					esc_html__( 'Checkout.com Payment Captured - Action ID - %s', 'checkout-com-unified-payments-api' ),
					$result['id']
				);
			}
		}

		$order->set_transaction_id( $result['id'] );
		$order->update_meta_data( '_cko_payment_id', $result['id'] );

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
