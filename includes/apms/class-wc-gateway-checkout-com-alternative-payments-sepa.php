<?php
/**
 * Sepa APM class.
 *
 * @package wc_checkout_com
 */

/**
 * Class WC_Gateway_Checkout_Com_Alternative_Payments_Sepa
 *
 * @class   WC_Gateway_Checkout_Com_Alternative_Payments_Sepa
 * @extends WC_Gateway_Checkout_Com_Alternative_Payments
 */
class WC_Gateway_Checkout_Com_Alternative_Payments_Sepa extends WC_Gateway_Checkout_Com_Alternative_Payments {

	const PAYMENT_METHOD = 'sepa';

	/**
	 * Construct method.
	 */
	public function __construct() {
		$this->id                 = 'wc_checkout_com_alternative_payments_sepa';
		$this->method_title       = __( 'Checkout.com', 'checkout-com-unified-payments-api' );
		$this->method_description = __( 'The Checkout.com extension allows shop owners to process online payments through the <a href="https://www.checkout.com">Checkout.com Payment Gateway.</a>', 'checkout-com-unified-payments-api' );
		$this->title              = __( 'SEPA Direct Debit', 'checkout-com-unified-payments-api' );
		$this->has_fields         = true;
		$this->supports           = [
			'products',
			'refunds',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_date_changes',
			'subscription_payment_method_change_admin',
		];

		$this->init_form_fields();

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );

		// Meta field on subscription edit.
		add_filter( 'woocommerce_subscription_payment_meta', [ $this, 'add_payment_meta_field' ], 10, 2 );
	}

	/**
	 * Add subscription order payment meta field.
	 *
	 * @param array           $payment_meta associative array of meta data required for automatic payments.
	 * @param WC_Subscription $subscription An instance of a subscription object.
	 * @return array
	 */
	public function add_payment_meta_field( $payment_meta, $subscription ) {
		$source_id = $subscription->get_meta( '_cko_source_id' );

		$payment_meta[ $this->id ] = [
			'post_meta' => [
				'_cko_source_id' => [
					'value' => $source_id,
					'label' => 'Checkout.com SEPA Direct Debit Source ID',
				],
			],
		];

		return $payment_meta;
	}

	/**
	 * Payment fields to be displayed.
	 */
	public function payment_fields() {
		// get available apms depending on currency.
		$apm_available = WC_Checkoutcom_Utility::get_alternative_payment_methods();

		if ( ! in_array( self::PAYMENT_METHOD, $apm_available, true ) ) {
			?>
				<script>
					jQuery('.payment_method_wc_checkout_com_alternative_payments_sepa').hide();
				</script>
			<?php
		} else {
				WC_Checkoutcom_Apm_Templates::get_sepa_details( wp_get_current_user() );
			?>
				<script>
				// Alter default place order button click.
				jQuery('#place_order').click(function(e) {
					// check if apm is selected as payment method.
					if (jQuery('#payment_method_wc_checkout_com_alternative_payments_sepa').is(':checked')) {

						const iban = jQuery('#sepa-iban').val();

						if (0 === iban.length) {
							alert( '<?php esc_html_e( 'Please enter your bank accounts iban', 'checkout-com-unified-payments-api' ); ?>' );
							return false;
						}

						if (0 === jQuery('input[name="sepa-checkbox-input"]:checked').length) {
							alert( '<?php esc_html_e( 'Please accept the mandate to continue', 'checkout-com-unified-payments-api' ); ?>' );
							return false;
						}
					}
				});
				</script>
			<?php
		}
	}

	/**
	 * Process Sepa APM payment.
	 *
	 * @global $woocommerce
	 * @param int $order_id Order ID.
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {
		if ( ! session_id() ) {
			session_start();
		}

		$order   = wc_get_order( $order_id );
		$status  = WC_Admin_Settings::get_option( 'ckocom_order_authorised', 'on-hold' );
		$mandate = WC()->session->get( 'mandate_reference' );
		$message = '';
		$result  = [];

		if ( 0 >= $order->get_total() ) { // 0 cost order.
			$message = esc_html__( 'Checkout.com - SEPA payment for free order.', 'checkout-com-unified-payments-api' );

			// save src id for future use.
			$post_data = sanitize_post( $_POST );
			$method    = WC_Checkoutcom_Api_Request::get_apm_method( $post_data, $order, 'sepa' );

			if ( ! empty( $method->id ) ) {
				$status = WC_Admin_Settings::get_option( 'ckocom_order_captured', 'processing' );

				if ( class_exists( 'WC_Subscriptions_Order' ) ) {
					WC_Checkoutcom_Subscription::save_source_id( $order_id, $order, $method->id );
				}
			}

			// check if result has error and return error message.
			if ( empty( $method->id ) ) {
				WC_Checkoutcom_Utility::wc_add_notice_self( esc_html__( 'Please try correct IBAN', 'checkout-com-unified-payments-api' ) );

				$message .= esc_html__( ' Please try correct IBAN', 'checkout-com-unified-payments-api' );

				$order->add_order_note( $message );

				// Prevent from going into action loops.
				remove_action( 'woocommerce_order_status_failed', 'WC_Subscriptions_Manager::failed_subscription_sign_ups_for_order' );
				remove_action( 'woocommerce_order_status_changed', 'WC_Subscriptions_Order::maybe_record_subscription_payment', 9, 3 );

				$subscriptions = function_exists( 'wcs_get_subscriptions_for_order' ) ? wcs_get_subscriptions_for_order( $order ) : null;
				if ( ! empty( $subscriptions ) ) {
					$order->update_status( 'failed' );
					WC_Subscriptions_Manager::failed_subscription_sign_ups_for_order( $order );
				} else {
					$order->update_status( 'failed' );
				}

				add_action( 'woocommerce_order_status_failed', 'WC_Subscriptions_Manager::failed_subscription_sign_ups_for_order' );
				add_action( 'woocommerce_order_status_changed', 'WC_Subscriptions_Order::maybe_record_subscription_payment', 9, 3 );
				// Prevent from going into action loops - END.

				return [
					'result'   => 'fail',
					'redirect' => '',
				];
			}
		} else {
			// create alternative payment.
			$result = (array) WC_Checkoutcom_Api_Request::create_apm_payment( $order, self::PAYMENT_METHOD );

			if ( ! empty( $mandate ) && ! empty( $result['source'] ) && self::PAYMENT_METHOD === $result['source']['type'] ) {

				$order->update_meta_data( 'cko_sepa_mandate_reference', $mandate );
				$order->update_meta_data( 'cko_payment_authorized', true );

				WC()->session->__unset( 'mandate_reference' );
			}

			if ( ! empty( $result['source'] ) && self::PAYMENT_METHOD === $result['source']['type'] ) {

				$message = sprintf(
				/* translators: 1: Result ID, 2: Mandate reference. */
					esc_html__( 'Checkout.com - Sepa payment Action ID : %1$s - Sepa mandate reference : %2$s', 'checkout-com-unified-payments-api' ),
					$result['id'],
					$mandate
				);
			}

			// check if result has error and return error message.
			if ( isset( $result['error'] ) && ! empty( $result['error'] ) ) {
				WC_Checkoutcom_Utility::wc_add_notice_self( $result['error'] );

				$order->add_order_note( $result['error'] );

				// Prevent from going into action loops.
				remove_action( 'woocommerce_order_status_failed', 'WC_Subscriptions_Manager::failed_subscription_sign_ups_for_order' );
				remove_action( 'woocommerce_order_status_changed', 'WC_Subscriptions_Order::maybe_record_subscription_payment', 9, 3 );

				$subscriptions = function_exists( 'wcs_get_subscriptions_for_order' ) ? wcs_get_subscriptions_for_order( $order ) : null;
				if ( ! empty( $subscriptions ) ) {
					$order->update_status( 'failed' );
					WC_Subscriptions_Manager::failed_subscription_sign_ups_for_order( $order );
				} else {
					$order->update_status( 'failed' );
				}

				add_action( 'woocommerce_order_status_failed', 'WC_Subscriptions_Manager::failed_subscription_sign_ups_for_order' );
				add_action( 'woocommerce_order_status_changed', 'WC_Subscriptions_Order::maybe_record_subscription_payment', 9, 3 );
				// Prevent from going into action loops - END.

				return [
					'result'   => 'fail',
					'redirect' => '',
				];
			}
		}

		// save source id for subscription.
		if ( class_exists( 'WC_Subscriptions_Order' ) ) {

			if ( ! empty( $result['source'] ) ) {
				WC_Checkoutcom_Subscription::save_source_id( $order_id, $order, $result['source']['id'] );
			}

			$mandate_cancel = WC()->session->get( 'mandate_cancel' );
			if ( ! empty( $mandate_cancel ) ) {
				WC_Checkoutcom_Subscription::save_mandate_cancel( $order_id, $order, $mandate_cancel );
				WC()->session->__unset( 'mandate_cancel' );
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
