<?php
/**
 * Webhook handler class.
 *
 * @package wc_checkout_com
 */

use Checkout\CheckoutApiException;

/**
 * Class WC_Checkout_Com_Webhook handles webhook events from Checkout.com.
 */
class WC_Checkout_Com_Webhook {

	/**
	 * Process webhook for authorize payment.
	 *
	 * @param array $data Webhook data.
	 *
	 * @return boolean
	 */
	public static function authorize_payment( $data ) {
		$webhook_data = $data->data;
		$order_id     = $webhook_data->metadata->order_id;

		// Return false if no order id.
		if ( empty( $order_id ) ) {
			return false;
		}

		// Load order form order id.
		$order = self::get_wc_order( $order_id );

		$already_captured = $order->get_meta( 'cko_payment_captured' );

		if ( $already_captured ) {
			return true;
		}

		$already_authorized = $order->get_meta( 'cko_payment_authorized' );
		$auth_status        = WC_Admin_Settings::get_option( 'ckocom_order_authorised', 'on-hold' );
		$message            = 'Webhook received from checkout.com. Payment Authorized';

		// Add note to order if Authorized already.
		if ( $already_authorized && $order->get_status() === $auth_status ) {
			$order->add_order_note( $message );
			return true;
		}

		// Get action id from webhook data.
		$action_id = $webhook_data->action_id;

		// Set action id as woo transaction id.
		$order->set_transaction_id( $action_id );
		$order->update_meta_data( '_cko_payment_id', $webhook_data->id );
		$order->update_meta_data( 'cko_payment_authorized', true );

		$order->add_order_note( $message );
		$order->update_status( $auth_status );

		return true;
	}

	/**
	 * Process webhook for card verification.
	 *
	 * @param array $data Webhook data.
	 *
	 * @return bool
	 */
	public static function card_verified( $data ) {
		$webhook_data = $data->data;
		$order_id     = $webhook_data->metadata->order_id;
		$action_id    = $webhook_data->action_id;

		// Return false if no order id.
		if ( empty( $order_id ) ) {
			return false;
		}

		// Load order form order id.
		$order = self::get_wc_order( $order_id );

		$order->add_order_note( __( 'Checkout.com Card verified webhook received', 'checkout-com-unified-payments-api' ) );
		// Set action id as woo transaction id.
		$order->set_transaction_id( $action_id );

		// Get cko capture status configured in admin.
		$status = WC_Admin_Settings::get_option( 'ckocom_order_captured', 'processing' );

		// update status of the order.
		$order->update_status( $status );

		return true;
	}

	/**
	 * Process webhook for captured payment.
	 *
	 * @param array $data Webhook data.
	 *
	 * @return bool
	 */
	public static function capture_payment( $data ) {
		$webhook_data = $data->data;
		$order_id     = $webhook_data->metadata->order_id;

		// Return false if no order id.
		if ( empty( $order_id ) ) {
			return false;
		}

		// Load order form order id.
		$order    = self::get_wc_order( $order_id );
		$order_id = $order->get_id();

		// Check if payment is already captured.
		$already_captured = $order->get_meta( 'cko_payment_captured' );
		$message          = 'Webhook received from checkout.com Payment captured';

		$already_authorized = $order->get_meta( 'cko_payment_authorized' );

		/**
		* We return false here as payment approved webhook is not yet delivered
		* Gateway will retry sending the captured webhook.
		*/
		if ( ! $already_authorized ) {
			WC_Checkoutcom_Utility::logger( 'Payment approved webhook not received yet : ' . $order_id, null );
			return false;
		}

		// Add note to order if captured already.
		if ( $already_captured ) {
			$order->add_order_note( $message );
			return true;
		}

		$order->add_order_note( __( 'Checkout.com Payment Capture webhook received', 'checkout-com-unified-payments-api' ) );

		// Get action id from webhook data.
		$action_id          = $webhook_data->action_id;
		$amount             = $webhook_data->amount;
		$order_amount       = $order->get_total();
		$order_amount_cents = WC_Checkoutcom_Utility::value_to_decimal( $order_amount, $order->get_currency() );

		// Set action id as woo transaction id.
		$order->set_transaction_id( $action_id );
		$order->update_meta_data( 'cko_payment_captured', true );

		// Get cko capture status configured in admin.
		$status = WC_Admin_Settings::get_option( 'ckocom_order_captured', 'processing' );

		/* translators: %s: Action ID. */
		$order_message = sprintf( esc_html__( 'Checkout.com Payment Captured - Action ID : %s', 'checkout-com-unified-payments-api' ), $action_id );

		// Check if webhook amount is less than order amount.
		if ( $amount < $order_amount_cents ) {
			/* translators: %s: Action ID. */
			$order_message = sprintf( esc_html__( 'Checkout.com Payment partially captured - Action ID : %s', 'checkout-com-unified-payments-api' ), $action_id );
		}

		// add notes for the order and update status.
		$order->add_order_note( $order_message );
		$order->update_status( $status );

		return true;
	}

	/**
	 * Process webhook for capture declined payment.
	 *
	 * @param array $data Webhook data.
	 *
	 * @return bool
	 */
	public static function capture_declined( $data ) {
		$webhook_data = $data->data;
		$order_id     = $webhook_data->metadata->order_id;

		// Return false if no order id.
		if ( empty( $order_id ) ) {
			return false;
		}

		// Load order form order id.
		$order = self::get_wc_order( $order_id );

		$message = 'Webhook received from checkout.com. Payment capture declined. Reason : ' . $webhook_data->response_summary;

		// Add note to order if capture declined.
		$order->add_order_note( $message );

		return true;
	}

	/**
	 * Process webhook for void payment.
	 *
	 * @param array $data Webhook data.
	 *
	 * @return bool
	 */
	public static function void_payment( $data ) {
		$webhook_data = $data->data;
		$order_id     = $webhook_data->metadata->order_id;

		// Return false if no order id.
		if ( empty( $order_id ) ) {
			return false;
		}

		// Load order form order id.
		$order    = self::get_wc_order( $order_id );
		$order_id = $order->get_id();

		// check if payment is already captured.
		$already_voided = $order->get_meta( 'cko_payment_voided' );
		$message        = 'Webhook received from checkout.com. Payment voided';

		// Add note to order if captured already.
		if ( $already_voided ) {
			$order->add_order_note( $message );
			return true;
		}

		$order->add_order_note( esc_html__( 'Checkout.com Payment Void webhook received', 'checkout-com-unified-payments-api' ) );

		// Get action id from webhook data.
		$action_id = $webhook_data->action_id;

		// Set action id as woo transaction id.
		$order->set_transaction_id( $action_id );
		$order->update_meta_data( 'cko_payment_voided', true );

		// Get cko capture status configured in admin.
		$status = WC_Admin_Settings::get_option( 'ckocom_order_void', 'cancelled' );

		/* translators: %s: Action ID. */
		$order_message = sprintf( esc_html__( 'Checkout.com Payment Voided - Action ID : %s', 'checkout-com-unified-payments-api' ), $action_id );

		// add notes for the order and update status.
		$order->add_order_note( $order_message );
		$order->update_status( $status );

		return true;
	}

	/**
	 * Process webhook for refund payment.
	 * Order status will not be changed if it's not fully refunded,
	 * if it's fully refunded, order status will be changed to refunded
	 * status by WC.
	 *
	 * @param array $data Webhook data.
	 *
	 * @return bool
	 */
	public static function refund_payment( $data ) {
		$webhook_data = $data->data;
		$order_id     = $webhook_data->metadata->order_id;

		// Return false if no order id.
		if ( empty( $order_id ) ) {
			return false;
		}

		// Load order form order id.
		$order    = self::get_wc_order( $order_id );
		$order_id = $order->get_id();

		// check if payment is already refunded.
		$already_refunded = $order->get_meta( 'cko_payment_refunded' );
		$message          = 'Webhook received from checkout.com. Payment refunded';

		// Get action id from webhook data.
		$action_id          = $webhook_data->action_id;
		$amount             = $webhook_data->amount;
		$order_amount       = $order->get_total();
		$order_amount_cents = WC_Checkoutcom_Utility::value_to_decimal( $order_amount, $order->get_currency() );
		$get_transaction_id = $order->get_transaction_id();

		if ( $get_transaction_id === $action_id ) {
			return true;
		}

		// Add note to order if refunded already.
		if ( $order->get_total_refunded() == $order_amount ) { // PHPCS:ignore WordPress.PHP.StrictComparisons.LooseComparison
			$order->add_order_note( $message );
			return true;
		}

		$order->add_order_note( esc_html__( 'Checkout.com Payment Refund webhook received', 'checkout-com-unified-payments-api' ) );

		// Set action id as woo transaction id.
		$order->set_transaction_id( $action_id );
		$order->update_meta_data( 'cko_payment_refunded', true );

		$refund_amount = WC_Checkoutcom_Utility::decimal_to_value( $amount, $order->get_currency() );

		/* translators: %s: Action ID. */
		$order_message = sprintf( esc_html__( 'Checkout.com Payment Refunded - Action ID : %s', 'checkout-com-unified-payments-api' ), $action_id );

		// Check if webhook amount is less than order amount - partial refund.
		if ( $amount < $order_amount_cents ) {
			/* translators: %s: Action ID. */
			$order_message = sprintf( esc_html__( 'Checkout.com Payment partially refunded - Action ID : %s', 'checkout-com-unified-payments-api' ), $action_id );

			$refund = wc_create_refund(
				[
					'amount'     => $refund_amount,
					'reason'     => '',
					'order_id'   => $order_id,
					'line_items' => [],
				]
			);

		} elseif ( $amount == $order_amount_cents ) { // PHPCS:ignore WordPress.PHP.StrictComparisons.LooseComparison
			// Full refund.
			/* translators: %s: Action ID. */
			$order_message = sprintf( esc_html__( 'Checkout.com Payment fully refunded - Action ID : %s', 'checkout-com-unified-payments-api' ), $action_id );

			$refund = wc_create_refund(
				[
					'amount'     => $refund_amount,
					'reason'     => '',
					'order_id'   => $order_id,
					'line_items' => [],
				]
			);
		}

		// add notes for the order and update status.
		$order->add_order_note( $order_message );

		return true;
	}

	/**
	 * Process webhook for cancelled payment.
	 *
	 * @param array $data Webhook data.
	 *
	 * @return bool
	 */
	public static function cancel_payment( $data ) {
		$webhook_data  = $data->data;
		$payment_id    = $webhook_data->id;
		$gateway_debug = 'yes' === WC_Admin_Settings::get_option( 'cko_gateway_responses', 'no' );

		// Initialize the Checkout Api.
		$checkout = new Checkout_SDK();

		try {
			// Check if payment is already voided or captured on checkout.com hub.
			$details = $checkout->get_builder()->getPaymentsClient()->getPaymentDetails( $payment_id );

			$order_id = ! empty( $details['metadata']['order_id'] ) ? $details['metadata']['order_id'] : null;

			// Return false if no order id.
			if ( empty( $order_id ) ) {
				WC_Checkoutcom_Utility::logger( 'No order id', null );

				return false;
			}

			// Load order form order id.
			$order = self::get_wc_order( $order_id );

			$status  = 'wc-cancelled';
			$message = 'Webhook received from checkout.com. Payment cancelled';

			// Add notes for the order and update status.
			$order->add_order_note( $message );
			$order->update_status( $status );

			return true;

		} catch ( CheckoutApiException $ex ) {
			$error_message = 'An error has occurred while processing your cancel request.';

			// Check if gateway response is enabled from module settings.
			if ( $gateway_debug ) {
				$error_message .= $ex->getMessage();
			}

			WC_Checkoutcom_Utility::logger( $error_message, $ex );

			return false;
		}
	}

	/**
	 * Desc : This function is used to change the status of an order which are created following
	 * Status changed from "pending payment to Failed".
	 *
	 * @param array $data Webhook data.
	 *
	 * @return bool
	 */
	public static function decline_payment( $data ) {
		$webhook_data     = $data->data;
		$order_id         = $webhook_data->metadata->order_id;
		$payment_id       = $webhook_data->id;
		$response_summary = $webhook_data->response_summary;

		if ( empty( $order_id ) ) {
			WC_Checkoutcom_Utility::logger( 'No order id for payment ' . $payment_id, null );

			return false;
		}

		$order = self::get_wc_order( $order_id );

		$status  = 'wc-failed';
		$message = 'Webhook received from checkout.com. Payment declined Reason : ' . $response_summary;

		// Add notes for the order and update status.
		$order->add_order_note( $message );
		$order->update_status( $status );

		return true;
	}

	/**
	 * Load order from order id or Query order by order number.
	 *
	 * @param int $order_id Order id.
	 *
	 * @return bool|mixed|WC_Order|WC_Order_Refund
	 */
	private static function get_wc_order( $order_id ) {
		$order = wc_get_order( $order_id );

		// Query order by order number to check if order exist.
		if ( ! $order ) {
			$orders = wc_get_orders(
				[
					'order_number' => $order_id,
				]
			);

			$order = $orders[0];
		}

		return $order;
	}

}
