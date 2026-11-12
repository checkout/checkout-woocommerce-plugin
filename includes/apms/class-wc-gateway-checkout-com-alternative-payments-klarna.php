<?php
/**
 * Klarna APM class.
 *
 * @package wc_checkout_com
 */

use Checkout\CheckoutApiException;
use Checkout\CheckoutUtils;

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
		$this->method_title       = __( 'Checkout.com', 'checkout-com-unified-payments-api' );
		$this->method_description = __( 'The Checkout.com extension allows shop owners to process online payments through the <a href="https://www.checkout.com">Checkout.com Payment Gateway.</a>', 'checkout-com-unified-payments-api' );
		$this->title              = __( 'Klarna', 'checkout-com-unified-payments-api' );
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
			$klarna_session = WC_Checkoutcom_Api_Request::klarna_session();

			$client_token = '';
			if ( ! empty( $klarna_session['partner_metadata']['client_token'] ) ) {
				$client_token = $klarna_session['partner_metadata']['client_token'];
			}

			if ( ! empty( $client_token ) ) {
				printf( '<input type="hidden" id="klarna-client-token" value="%s">', esc_attr( $client_token ) );
			} else {
				?>
				<script>
					jQuery('#payment_method_wc_checkout_com_alternative_payments_klarna').prop( 'checked', false );
					jQuery('.payment_method_wc_checkout_com_alternative_payments_klarna').hide();

					setTimeout( () => {
							jQuery('#payment_method_wc_checkout_com_alternative_payments_klarna').prop( 'checked', false );
						},
						2000
					);

				</script>
				<?php
			}
			?>

			<div id="cart-info" data-cart="<?php echo esc_attr( wp_json_encode( WC_Checkoutcom_Api_Request::get_cart_info() ) ); ?>"></div>

			<div class="klarna-details"></div>
			<div id="klarna_container"></div>

			<!-- cko klarna js file -->
			<?php wp_enqueue_script( 'cko-klarna', WC_CHECKOUTCOM_PLUGIN_URL . '/assets/js/klarna.js', array( 'jquery' ), WC_CHECKOUTCOM_PLUGIN_VERSION, true ); ?>

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
		$result = (array) $this->create_payment( $order, self::PAYMENT_METHOD );

		// check if result has error and return error message.
		if ( ! empty( $result['error'] ) ) {
			WC_Checkoutcom_Utility::wc_add_notice_self( $result['error'], 'error' );
			return;
		}

		// redirect to apm if redirection url is available.
		if ( ! empty( $result['redirect'] ) ) {
			return [
				'result'   => 'success',
				'redirect' => $result['redirect'],
			];
		}
	}

	/**
	 * Create payment.
	 *
	 * @param WC_Order $order The order object.
	 * @param string   $payment_method The payment method.
	 *
	 * @return array
	 */
	private function create_payment( WC_Order $order, $payment_method ) {
		$payment_context_id = WC_Checkoutcom_Utility::cko_get_session( 'cko_klarna_pc_id' );

		try {
			$checkout = new Checkout_SDK();

			// Build metadata for Klarna payment
			global $wp_version;
			$woocommerce = WC();
			
			$udf5 = sprintf(
				'Platform Data - WordPress %s / Woocommerce %s, Integration Data - Checkout.com %s, SDK Data - PHP SDK %s, Order ID - %s, Server - %s',
				$wp_version,
				$woocommerce->version,
				WC_CHECKOUTCOM_PLUGIN_VERSION,
				CheckoutUtils::PROJECT_VERSION,
				$order->get_order_number(),
				get_site_url()
			);

			$metadata = [
				'udf5'     => $udf5,
				'order_id' => $order->get_id(),
			];

			$payment_request_param                     = $checkout->get_payment_request();
			$payment_request_param->payment_context_id = $payment_context_id;
			cko_apply_processing_channel_id( $payment_request_param );
			$payment_request_param->reference          = $order->get_order_number();
			$payment_request_param->metadata           = $metadata;

			$response      = $checkout->get_builder()->getPaymentsClient()->requestPayment( $payment_request_param );
			$response_code = $response['http_metadata']->getStatusCode();

			if ( ! WC_Checkoutcom_Utility::is_successful( $response ) || 'Declined' === $response['status'] ) {
				$order->update_meta_data( '_cko_payment_id', $response['id'] );
				$order->save();

				$error_message = esc_html__( 'An error has occurred while Klarna payment request. ', 'checkout-com-unified-payments-api' );

				wp_send_json_error( [ 'messages' => $error_message ] );

				return [
					'result'   => 'fail',
					'redirect' => '',
				];
			}

			/**
			 * Full Success = 201
			 * Partial Success = 202
			 *
			 * Ref : https://api-reference.checkout.com/#operation/requestAPaymentOrPayout!path=1/payment_context_id&t=request
			 */
			if ( in_array( $response_code, [ 201, 202 ], true ) ) {

				// The payment context was created when the checkout was shown; only an approved
				// payment for this order's amount and currency moves it to the authorised status.
				$verified = new WP_Error( 'cko_payment_pending', 'Payment is pending (HTTP 202).' );
				if ( 201 === $response_code ) {
					// Use the response when it carries amount and currency, otherwise fetch the details.
					$details  = ( isset( $response['amount'], $response['currency'] ) ) ? (array) $response : null;
					$verified = WC_Checkoutcom_Utility::verify_payment_for_order( $order, (string) $response['id'], $details );
				}

				// A payment that could not be confirmed for this order does not complete the
				// checkout: stock and cart are left alone and the shopper sees an error.
				$is_pending = is_wp_error( $verified )
					&& ( 'cko_payment_pending' === $verified->get_error_code()
						|| ( 'cko_payment_not_approved' === $verified->get_error_code() && 'Pending' === ( is_array( $details ?? null ) ? ( $details['status'] ?? '' ) : '' ) ) );

				if ( is_wp_error( $verified ) && ! $is_pending ) {
					return $this->reject_unconfirmed_payment( $order, $response, $verified );
				}

				$order->set_transaction_id( $response['id'] );
				$order->update_meta_data( '_cko_payment_id', $response['id'] );

				if ( class_exists( 'WC_Subscriptions_Order' ) && isset( $response['source'] ) ) {
					// Save source id for subscription.
					WC_Checkoutcom_Subscription::save_source_id( $order->get_id(), $order, $response['source']['id'] );
				}

				if ( is_wp_error( $verified ) ) {
					WC_Checkoutcom_Utility::logger( 'Klarna payment for order ' . $order->get_id() . ' not confirmed: ' . $verified->get_error_message() );

					/* translators: %s: Payment ID. */
					$note = sprintf( esc_html__( 'Checkout.com Klarna payment pending confirmation - Payment ID : %s', 'checkout-com-unified-payments-api' ), $response['id'] );

					$order->update_status( 'on-hold', $note );
				} else {
					// Get cko auth status configured in admin.
					$status = WC_Admin_Settings::get_option( 'ckocom_order_authorised', 'on-hold' );

					$order->update_meta_data( 'cko_payment_authorized', true );
					$order->update_status( $status );
				}

				// Reduce stock levels.
				wc_reduce_stock_levels( $order->get_id() );

				// Remove cart.
				WC()->cart->empty_cart();

				// Return thank you page.
				return [
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order ),
				];

			}
		} catch ( CheckoutApiException $ex ) {
			$gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) === 'yes';

			$error_message = esc_html__( 'An error has occurred while Klarna payment request. ', 'checkout-com-unified-payments-api' );

			// Check if gateway response is enabled from module settings.
			if ( $gateway_debug ) {
				$error_message .= $ex->getMessage();
			}

			WC_Checkoutcom_Utility::logger( $error_message, $ex );

			wp_send_json_error( [ 'messages' => $error_message ] );
		}
	}

	/**
	 * Handle a Klarna payment that did not pass verify_payment_for_order(): the payment is
	 * voided when possible, the order is marked failed (not bound to the payment) and an error
	 * is returned to the checkout.
	 *
	 * @param WC_Order $order    Order.
	 * @param array    $response Payment response.
	 * @param WP_Error $error    Verification result.
	 *
	 * @return array
	 */
	private function reject_unconfirmed_payment( WC_Order $order, $response, $error ) {
		$payment_id = isset( $response['id'] ) ? (string) $response['id'] : '';

		WC_Checkoutcom_Utility::logger( 'Klarna payment for order ' . $order->get_id() . ' not confirmed: ' . $error->get_error_message() );

		$voided = false;
		if ( WC_Checkoutcom_Utility::is_valid_payment_id( $payment_id ) ) {
			try {
				$checkout                = new Checkout_SDK();
				$void_request            = new Checkout\Payments\VoidRequest();
				$void_request->reference = (string) $order->get_order_number();

				$void_response = $checkout->get_builder()->getPaymentsClient()->voidPayment( $payment_id, $void_request );
				$voided        = WC_Checkoutcom_Utility::is_successful( $void_response );
			} catch ( Exception $ex ) {
				WC_Checkoutcom_Utility::logger( 'Klarna payment ' . $payment_id . ' could not be voided.', $ex );
			}
		}

		if ( in_array( $error->get_error_code(), [ 'cko_amount_mismatch', 'cko_currency_mismatch' ], true ) ) {
			/* translators: %s: Payment ID. */
			$note = sprintf( esc_html__( 'Payment amount or currency does not match the order - Checkout.com Payment ID : %s', 'checkout-com-unified-payments-api' ), $payment_id );
		} else {
			/* translators: %s: Payment ID. */
			$note = sprintf( esc_html__( 'Checkout.com Klarna payment could not be confirmed for this order - Payment ID : %s', 'checkout-com-unified-payments-api' ), $payment_id );
		}

		$note .= $voided
			? ' ' . esc_html__( '(payment voided)', 'checkout-com-unified-payments-api' )
			: ' ' . esc_html__( '(payment could not be voided automatically, please review it in the Checkout.com dashboard)', 'checkout-com-unified-payments-api' );

		$order->update_status( 'failed', $note );

		return [
			'error' => esc_html__( 'An error has occurred while Klarna payment request. ', 'checkout-com-unified-payments-api' ),
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
