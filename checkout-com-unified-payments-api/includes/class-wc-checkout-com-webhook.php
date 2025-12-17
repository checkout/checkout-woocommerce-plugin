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
	 * Check if webhook debug logging is enabled.
	 *
	 * @return bool
	 */
	private static function is_webhook_debug_enabled() {
		$core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings' );
		return ( isset( $core_settings['cko_gateway_responses'] ) && $core_settings['cko_gateway_responses'] === 'yes' );
	}

	/**
	 * Process webhook for authorize payment.
	 *
	 * @param array $data Webhook data.
	 *
	 * @return boolean
	 */
	public static function authorize_payment( $data ) {
		$webhook_debug_enabled = self::is_webhook_debug_enabled();
		
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( '=== WEBHOOK PROCESS: authorize_payment START ===' );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Event type: payment_approved' );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Full webhook data structure: ' . print_r($data, true) );
		}
		
		$webhook_data = $data->data;
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Webhook data extracted: ' . print_r($webhook_data, true) );
		}
		
		$order_id = isset($webhook_data->metadata->order_id) ? $webhook_data->metadata->order_id : null;
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order ID from metadata: ' . ($order_id ?? 'NULL') );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Payment ID: ' . ($webhook_data->id ?? 'NULL') );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Reference: ' . ($webhook_data->reference ?? 'NULL') );
			
			// Log all available metadata
			if (isset($webhook_data->metadata)) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: All metadata: ' . print_r($webhook_data->metadata, true) );
			}
		}

		// Return false if no order id.
		if ( empty( $order_id ) || ! is_numeric( $order_id ) ) {
			// Always log errors, even if debug is disabled
			WC_Checkoutcom_Utility::logger( "WEBHOOK PROCESS: ERROR - Invalid/Empty order_id: " . ($order_id ?? 'NULL') );
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( "WEBHOOK PROCESS: Order ID type: " . gettype($order_id) );
				WC_Checkoutcom_Utility::logger( "WEBHOOK PROCESS: Order ID is_numeric: " . (is_numeric($order_id) ? 'YES' : 'NO') );
				WC_Checkoutcom_Utility::logger( '=== WEBHOOK PROCESS: authorize_payment END (FAILED - Invalid Order ID) ===' );
			}
			return false;
		}

		// Load order form order id.
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Attempting to load order ID: ' . $order_id );
		}
		$order = self::get_wc_order( $order_id );
		
		if ( ! $order ) {
			// Always log errors, even if debug is disabled
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: ERROR - Order not found for ID: ' . $order_id );
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( '=== WEBHOOK PROCESS: authorize_payment END (FAILED - Order Not Found) ===' );
			}
			return false;
		}
		
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order loaded successfully - Order ID: ' . $order->get_id() . ', Status: ' . $order->get_status() );
		}

		$already_captured = $order->get_meta( 'cko_payment_captured' );
		$current_status = $order->get_status();

		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: authorize_payment - Current order status: ' . $current_status );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: authorize_payment - Already captured: ' . ( $already_captured ? 'YES' : 'NO' ) );
		}

		$payment_id = $webhook_data->id;
		$action_id = $webhook_data->action_id;
		$amount = isset( $webhook_data->amount ) ? $webhook_data->amount : 0;
		$formatted_amount = $amount > 0 ? wc_price( WC_Checkoutcom_Utility::decimal_to_value( $amount, $order->get_currency() ), array( 'currency' => $order->get_currency() ) ) : '';
		$message = sprintf( 'Webhook received from checkout.com. Payment Authorized - Payment ID: %s, Action ID: %s%s', $payment_id, $action_id, $formatted_amount ? ', Amount: ' . $formatted_amount : '' );

		// CRITICAL: Check if already captured FIRST (most important check)
		// Don't update status if already captured (even if not authorized yet)
		// This prevents downgrading from processing back to on-hold
		if ( $already_captured ) {
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: authorize_payment - Payment already captured, skipping status update to prevent downgrade' );
			}
			// Payment already captured - just add note and update meta, don't change status
			$order->set_transaction_id( $action_id );
			$order->update_meta_data( '_cko_payment_id', $payment_id );
			$order->update_meta_data( 'cko_payment_authorized', true );
			$order->add_order_note( $message );
			return true;
		}

		$already_authorized = $order->get_meta( 'cko_payment_authorized' );
		$auth_status        = WC_Admin_Settings::get_option( 'ckocom_order_authorised', 'on-hold' );

		// Don't update status if already authorized AND status matches
		if ( $already_authorized && $order->get_status() === $auth_status ) {
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: authorize_payment - Already authorized with matching status, adding note only' );
			}
			$order->add_order_note( $message );
			return true;
		}

		// Don't update status if order is already in a more advanced state (processing, completed)
		// This prevents race conditions where capture webhook updated status but meta not saved yet
		if ( in_array( $current_status, array( 'processing', 'completed' ), true ) ) {
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: authorize_payment - Order already in advanced state (' . $current_status . '), skipping status update to prevent downgrade' );
			}
			// Order is already processing/completed - don't downgrade to on-hold
			// Just add note and update meta, but don't change status
			$order->set_transaction_id( $action_id );
			$order->update_meta_data( '_cko_payment_id', $payment_id );
			$order->update_meta_data( 'cko_payment_authorized', true );
			$order->add_order_note( $message );
			return true;
		}

		// Set action id as woo transaction id.
		$order->set_transaction_id( $action_id );
		$order->update_meta_data( '_cko_payment_id', $payment_id );
		$order->update_meta_data( 'cko_payment_authorized', true );

		$order->add_order_note( $message );
		$order->update_status( $auth_status );

		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order status updated to: ' . $auth_status );
			WC_Checkoutcom_Utility::logger( '=== WEBHOOK PROCESS: authorize_payment END (SUCCESS) ===' );
		}
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
		$webhook_debug_enabled = self::is_webhook_debug_enabled();
		
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( '=== WEBHOOK PROCESS: card_verified START ===' );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Event type: card_verified' );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Full webhook data structure: ' . print_r($data, true) );
		}
		
		$webhook_data = $data->data;
		$order_id     = isset($webhook_data->metadata->order_id) ? $webhook_data->metadata->order_id : null;
		$action_id    = isset($webhook_data->action_id) ? $webhook_data->action_id : null;
		
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order ID from metadata: ' . ($order_id ?? 'NULL') );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Action ID: ' . ($action_id ?? 'NULL') );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Payment ID: ' . ($webhook_data->id ?? 'NULL') );
		}

		// Return false if no order id.
		if ( empty( $order_id ) || ! is_numeric( $order_id ) ) {
			// Always log errors
			WC_Checkoutcom_Utility::logger( "WEBHOOK PROCESS: ERROR - Invalid/Empty order_id: " . ($order_id ?? 'NULL') );
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( '=== WEBHOOK PROCESS: card_verified END (FAILED - Invalid Order ID) ===' );
			}
			return false;
		}

		// Load order form order id.
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Attempting to load order ID: ' . $order_id );
		}
		$order = self::get_wc_order( $order_id );
		
		if ( ! $order ) {
			// Always log errors
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: ERROR - Order not found for ID: ' . $order_id );
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( '=== WEBHOOK PROCESS: card_verified END (FAILED - Order Not Found) ===' );
			}
			return false;
		}
		
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order loaded successfully - Order ID: ' . $order->get_id() . ', Status: ' . $order->get_status() );
		}

		$payment_id = $webhook_data->id;
		$order->add_order_note( sprintf( __( 'Checkout.com Card verified webhook received - Payment ID: %s, Action ID: %s', 'checkout-com-unified-payments-api' ), $payment_id, $action_id ) );
		// Set action id as woo transaction id.
		$order->set_transaction_id( $action_id );

		// Get cko capture status configured in admin.
		$status = WC_Admin_Settings::get_option( 'ckocom_order_captured', 'processing' );

		// update status of the order.
		$order->update_status( $status );

		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order status updated to: ' . $status );
			WC_Checkoutcom_Utility::logger( '=== WEBHOOK PROCESS: card_verified END (SUCCESS) ===' );
		}
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
		$webhook_debug_enabled = self::is_webhook_debug_enabled();
		
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( '=== WEBHOOK PROCESS: capture_payment START ===' );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Event type: payment_captured' );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Full webhook data structure: ' . print_r($data, true) );
		}
		
		$webhook_data = $data->data;
		$order_id     = isset($webhook_data->metadata->order_id) ? $webhook_data->metadata->order_id : null;
		
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order ID from metadata: ' . ($order_id ?? 'NULL') );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Payment ID: ' . ($webhook_data->id ?? 'NULL') );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Amount: ' . (isset($webhook_data->amount) ? $webhook_data->amount : 'NULL') );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Action ID: ' . (isset($webhook_data->action_id) ? $webhook_data->action_id : 'NULL') );
		}

		// Return false if no order id.
		if ( empty( $order_id ) || ! is_numeric( $order_id ) ) {
			// Always log errors
			WC_Checkoutcom_Utility::logger( "WEBHOOK PROCESS: ERROR - Invalid/Empty order_id: " . ($order_id ?? 'NULL') );
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( '=== WEBHOOK PROCESS: capture_payment END (FAILED - Invalid Order ID) ===' );
			}
			return false;
		}

		// Load order form order id.
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Attempting to load order ID: ' . $order_id );
		}
		$order    = self::get_wc_order( $order_id );
		
		if ( ! $order ) {
			// Always log errors
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: ERROR - Order not found for ID: ' . $order_id );
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( '=== WEBHOOK PROCESS: capture_payment END (FAILED - Order Not Found) ===' );
			}
			return false;
		}
		
		$order_id = $order->get_id();
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order loaded successfully - Order ID: ' . $order_id . ', Status: ' . $order->get_status() );
		}

		// Check if payment is already captured.
		$already_captured = $order->get_meta( 'cko_payment_captured' );
		$payment_id       = $webhook_data->id;

		// Get action id from webhook data.
		$action_id          = $webhook_data->action_id;
		$amount             = $webhook_data->amount;
		$order_amount       = $order->get_total();
		$order_amount_cents = WC_Checkoutcom_Utility::value_to_decimal( $order_amount, $order->get_currency() );
		$formatted_amount_for_message = wc_price( WC_Checkoutcom_Utility::decimal_to_value( $amount, $order->get_currency() ), array( 'currency' => $order->get_currency() ) );

		$message          = sprintf( 'Webhook received from checkout.com Payment captured - Payment ID: %s, Action ID: %s, Amount: %s', $payment_id, $action_id, $formatted_amount_for_message );

		$already_authorized = $order->get_meta( 'cko_payment_authorized' );

		// If not already authorized, set it now (capture implies authorization)
		// This handles cases where capture webhook arrives before auth webhook
		if ( ! $already_authorized ) {
			$order->update_meta_data( 'cko_payment_authorized', true );
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Capture webhook arrived before auth - setting authorization status automatically for order: ' . $order_id );
			}
		}

		// Add note to order if captured already.
		if ( $already_captured ) {
			$order->add_order_note( $message );
			return true;
		}

		$formatted_amount = wc_price( WC_Checkoutcom_Utility::decimal_to_value( $amount, $order->get_currency() ), array( 'currency' => $order->get_currency() ) );
		$order->add_order_note( sprintf( __( 'Checkout.com Payment Capture webhook received - Payment ID: %s, Amount: %s', 'checkout-com-unified-payments-api' ), $payment_id, $formatted_amount ) );

		// Set action id as woo transaction id.
		$order->set_transaction_id( $action_id );
		$order->update_meta_data( 'cko_payment_captured', true );

		// Get cko capture status configured in admin.
		$status = WC_Admin_Settings::get_option( 'ckocom_order_captured', 'processing' );

		$formatted_amount = wc_price( WC_Checkoutcom_Utility::decimal_to_value( $amount, $order->get_currency() ), array( 'currency' => $order->get_currency() ) );
		/* translators: %1$s: Payment ID, %2$s: Action ID, %3$s: Amount. */
		$order_message = sprintf( esc_html__( 'Checkout.com Payment Captured - Payment ID: %1$s, Action ID: %2$s, Amount: %3$s', 'checkout-com-unified-payments-api' ), $payment_id, $action_id, $formatted_amount );

		// Check if webhook amount is less than order amount.
		if ( $amount < $order_amount_cents ) {
			/* translators: %1$s: Payment ID, %2$s: Action ID, %3$s: Amount. */
			$order_message = sprintf( esc_html__( 'Checkout.com Payment partially captured - Payment ID: %1$s, Action ID: %2$s, Amount: %3$s', 'checkout-com-unified-payments-api' ), $payment_id, $action_id, $formatted_amount );
		}

		// add notes for the order and update status.
		$order->add_order_note( $order_message );
		$order->update_status( $status );

		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order status updated to: ' . $status );
			WC_Checkoutcom_Utility::logger( '=== WEBHOOK PROCESS: capture_payment END (SUCCESS) ===' );
		}
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
		$webhook_debug_enabled = self::is_webhook_debug_enabled();
		
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( '=== WEBHOOK PROCESS: capture_declined START ===' );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Event type: payment_capture_declined' );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Full webhook data structure: ' . print_r($data, true) );
		}
		
		$webhook_data = $data->data;
		$order_id     = isset($webhook_data->metadata->order_id) ? $webhook_data->metadata->order_id : null;
		$payment_id   = isset($webhook_data->id) ? $webhook_data->id : null;
		$action_id    = isset($webhook_data->action_id) ? $webhook_data->action_id : null;
		$response_summary = isset($webhook_data->response_summary) ? $webhook_data->response_summary : 'N/A';
		
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order ID from metadata: ' . ($order_id ?? 'NULL') );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Payment ID: ' . ($payment_id ?? 'NULL') );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Action ID: ' . ($action_id ?? 'NULL') );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Response summary: ' . $response_summary );
		}

		// Return false if no order id.
		if ( empty( $order_id ) || ! is_numeric( $order_id ) ) {
			// Always log errors
			WC_Checkoutcom_Utility::logger( "WEBHOOK PROCESS: ERROR - Invalid/Empty order_id: " . ($order_id ?? 'NULL') );
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( '=== WEBHOOK PROCESS: capture_declined END (FAILED - Invalid Order ID) ===' );
			}
			return false;
		}

		// Load order form order id.
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Attempting to load order ID: ' . $order_id );
		}
		$order = self::get_wc_order( $order_id );
		
		if ( ! $order ) {
			// Always log errors
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: ERROR - Order not found for ID: ' . $order_id );
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( '=== WEBHOOK PROCESS: capture_declined END (FAILED - Order Not Found) ===' );
			}
			return false;
		}
		
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order loaded successfully - Order ID: ' . $order->get_id() . ', Status: ' . $order->get_status() );
		}

		// Get amount from webhook or use order amount as fallback
		$amount = isset( $webhook_data->amount ) ? $webhook_data->amount : 0;
		$formatted_amount = '';
		if ( $amount > 0 ) {
			$formatted_amount = wc_price( WC_Checkoutcom_Utility::decimal_to_value( $amount, $order->get_currency() ), array( 'currency' => $order->get_currency() ) );
		} else {
			// Fallback to order amount if webhook doesn't have amount
			$formatted_amount = wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) );
		}
		
		// Include Payment ID and Action ID (if available) in the order note for consistency with other webhook handlers
		if ( ! empty( $action_id ) ) {
			$message = sprintf( 'Webhook received from checkout.com. Payment capture declined - Payment ID: %s, Action ID: %s, Reason: %s, Amount: %s', $payment_id, $action_id, $response_summary, $formatted_amount );
		} else {
			$message = sprintf( 'Webhook received from checkout.com. Payment capture declined - Payment ID: %s, Reason: %s, Amount: %s', $payment_id, $response_summary, $formatted_amount );
		}

		// Add note to order if capture declined.
		$order->add_order_note( $message );

		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Capture declined note added to order' );
			WC_Checkoutcom_Utility::logger( '=== WEBHOOK PROCESS: capture_declined END (SUCCESS) ===' );
		}
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
		$webhook_debug_enabled = self::is_webhook_debug_enabled();
		
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( '=== WEBHOOK PROCESS: void_payment START ===' );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Event type: payment_voided' );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Full webhook data structure: ' . print_r($data, true) );
		}
		
		$webhook_data = $data->data;
		$order_id     = isset($webhook_data->metadata->order_id) ? $webhook_data->metadata->order_id : null;
		
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order ID from metadata: ' . ($order_id ?? 'NULL') );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Payment ID: ' . ($webhook_data->id ?? 'NULL') );
		}

		// Return false if no order id.
		if ( empty( $order_id ) || ! is_numeric( $order_id ) ) {
			// Always log errors
			WC_Checkoutcom_Utility::logger( "WEBHOOK PROCESS: ERROR - Invalid/Empty order_id: " . ($order_id ?? 'NULL') );
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( '=== WEBHOOK PROCESS: void_payment END (FAILED - Invalid Order ID) ===' );
			}
			return false;
		}

		// Load order form order id.
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Attempting to load order ID: ' . $order_id );
		}
		$order    = self::get_wc_order( $order_id );
		
		if ( ! $order ) {
			// Always log errors
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: ERROR - Order not found for ID: ' . $order_id );
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( '=== WEBHOOK PROCESS: void_payment END (FAILED - Order Not Found) ===' );
			}
			return false;
		}
		
		$order_id = $order->get_id();
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order loaded successfully - Order ID: ' . $order_id . ', Status: ' . $order->get_status() );
		}

		// CRITICAL: Validate payment ID matches order (prevent wrong webhooks from matching orders)
		$payment_id = $webhook_data->id;
		$order_payment_id = $order->get_meta( '_cko_flow_payment_id' );
		$order_payment_id_alt = $order->get_meta( '_cko_payment_id' );
		
		// Use Flow payment ID if available, otherwise fall back to regular payment ID
		$expected_payment_id = ! empty( $order_payment_id ) ? $order_payment_id : $order_payment_id_alt;
		
		if ( ! empty( $expected_payment_id ) && $expected_payment_id !== $payment_id ) {
			// Payment ID mismatch - reject webhook
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: ❌ CRITICAL ERROR - Void webhook payment ID mismatch!' );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order ID: ' . $order_id );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order _cko_flow_payment_id: ' . ( $order_payment_id ?: 'NOT SET' ) );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order _cko_payment_id: ' . ( $order_payment_id_alt ?: 'NOT SET' ) );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Expected payment ID: ' . $expected_payment_id );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Webhook payment ID: ' . $payment_id );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: ❌ REJECTING VOID WEBHOOK - Payment ID does not match order!' );
			
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( '=== WEBHOOK PROCESS: void_payment END (FAILED - Payment ID Mismatch) ===' );
			}
			return false; // Reject webhook - wrong payment
		}
		
		if ( $webhook_debug_enabled && ! empty( $expected_payment_id ) ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: ✅ Payment ID validation passed - Order payment ID: ' . $expected_payment_id . ', Webhook payment ID: ' . $payment_id );
		}

		// check if payment is already captured.
		$already_voided = $order->get_meta( 'cko_payment_voided' );

		// Get action id from webhook data.
		$action_id = $webhook_data->action_id;
		$amount = isset( $webhook_data->amount ) ? $webhook_data->amount : 0;
		$formatted_amount = '';
		if ( $amount > 0 ) {
			$formatted_amount = wc_price( WC_Checkoutcom_Utility::decimal_to_value( $amount, $order->get_currency() ), array( 'currency' => $order->get_currency() ) );
		} else {
			// Fallback to order amount if webhook doesn't have amount
			$formatted_amount = wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) );
		}

		$message        = sprintf( 'Webhook received from checkout.com. Payment voided - Payment ID: %s, Action ID: %s%s', $payment_id, $action_id, $formatted_amount ? ', Amount: ' . $formatted_amount : '' );

		// Add note to order if captured already.
		if ( $already_voided ) {
			$order->add_order_note( $message );
			return true;
		}

		$order->add_order_note( sprintf( esc_html__( 'Checkout.com Payment Void webhook received - Payment ID: %s%s', 'checkout-com-unified-payments-api' ), $payment_id, $formatted_amount ? ', Amount: ' . $formatted_amount : '' ) );

		// Set action id as woo transaction id.
		$order->set_transaction_id( $action_id );
		$order->update_meta_data( 'cko_payment_voided', true );

		// Get cko capture status configured in admin.
		$status = WC_Admin_Settings::get_option( 'ckocom_order_void', 'cancelled' );

		/* translators: %1$s: Payment ID, %2$s: Action ID, %3$s: Amount. */
		$order_message = sprintf( esc_html__( 'Checkout.com Payment Voided - Payment ID: %1$s, Action ID: %2$s, Amount: %3$s', 'checkout-com-unified-payments-api' ), $payment_id, $action_id, $formatted_amount );

		// add notes for the order and update status.
		$order->add_order_note( $order_message );
		$order->update_status( $status );

		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order status updated to: ' . $status );
			WC_Checkoutcom_Utility::logger( '=== WEBHOOK PROCESS: void_payment END (SUCCESS) ===' );
		}
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
		$webhook_debug_enabled = self::is_webhook_debug_enabled();
		
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( '=== WEBHOOK PROCESS: refund_payment START ===' );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Event type: payment_refunded' );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Full webhook data structure: ' . print_r($data, true) );
		}
		
		$webhook_data = $data->data;
		$order_id     = isset($webhook_data->metadata->order_id) ? $webhook_data->metadata->order_id : null;
		
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order ID from metadata: ' . ($order_id ?? 'NULL') );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Payment ID: ' . ($webhook_data->id ?? 'NULL') );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Refund amount: ' . (isset($webhook_data->amount) ? $webhook_data->amount : 'NULL') );
		}

		// Return false if no order id.
		if ( empty( $order_id ) || ! is_numeric( $order_id ) ) {
			// Always log errors
			WC_Checkoutcom_Utility::logger( "WEBHOOK PROCESS: ERROR - Invalid/Empty order_id: " . ($order_id ?? 'NULL') );
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( '=== WEBHOOK PROCESS: refund_payment END (FAILED - Invalid Order ID) ===' );
			}
			return false;
		}

		// Load order form order id.
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Attempting to load order ID: ' . $order_id );
		}
		$order    = self::get_wc_order( $order_id );
		
		if ( ! $order ) {
			// Always log errors
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: ERROR - Order not found for ID: ' . $order_id );
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( '=== WEBHOOK PROCESS: refund_payment END (FAILED - Order Not Found) ===' );
			}
			return false;
		}
		
		$order_id = $order->get_id();
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order loaded successfully - Order ID: ' . $order_id . ', Status: ' . $order->get_status() );
		}

		// CRITICAL: Validate payment ID matches order (prevent wrong webhooks from matching orders)
		$payment_id = $webhook_data->id;
		$order_payment_id = $order->get_meta( '_cko_flow_payment_id' );
		$order_payment_id_alt = $order->get_meta( '_cko_payment_id' );
		
		// Use Flow payment ID if available, otherwise fall back to regular payment ID
		$expected_payment_id = ! empty( $order_payment_id ) ? $order_payment_id : $order_payment_id_alt;
		
		if ( ! empty( $expected_payment_id ) && $expected_payment_id !== $payment_id ) {
			// Payment ID mismatch - reject webhook
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: ❌ CRITICAL ERROR - Refund webhook payment ID mismatch!' );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order ID: ' . $order_id );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order _cko_flow_payment_id: ' . ( $order_payment_id ?: 'NOT SET' ) );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order _cko_payment_id: ' . ( $order_payment_id_alt ?: 'NOT SET' ) );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Expected payment ID: ' . $expected_payment_id );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Webhook payment ID: ' . $payment_id );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: ❌ REJECTING REFUND WEBHOOK - Payment ID does not match order!' );
			
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( '=== WEBHOOK PROCESS: refund_payment END (FAILED - Payment ID Mismatch) ===' );
			}
			return false; // Reject webhook - wrong payment
		}
		
		if ( $webhook_debug_enabled && ! empty( $expected_payment_id ) ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: ✅ Payment ID validation passed - Order payment ID: ' . $expected_payment_id . ', Webhook payment ID: ' . $payment_id );
		}

		// check if payment is already refunded.
		$already_refunded = $order->get_meta( 'cko_payment_refunded' );

		// Get action id from webhook data.
		$action_id          = $webhook_data->action_id;
		$amount             = $webhook_data->amount;
		$order_amount       = $order->get_total();
		$order_amount_cents = WC_Checkoutcom_Utility::value_to_decimal( $order_amount, $order->get_currency() );
		$get_transaction_id = $order->get_transaction_id();

		$refund_amount_formatted = wc_price( WC_Checkoutcom_Utility::decimal_to_value( $amount, $order->get_currency() ), array( 'currency' => $order->get_currency() ) );
		$message          = sprintf( 'Webhook received from checkout.com. Payment refunded - Payment ID: %s, Action ID: %s, Amount: %s', $payment_id, $action_id, $refund_amount_formatted );

		if ( $get_transaction_id === $action_id ) {
			return true;
		}

		// Add note to order if refunded already.
		if ( $order->get_total_refunded() == $order_amount ) { // PHPCS:ignore WordPress.PHP.StrictComparisons.LooseComparison
			$order->add_order_note( $message );
			return true;
		}

		// Set action id as woo transaction id.
		$order->set_transaction_id( $action_id );
		$order->update_meta_data( 'cko_payment_refunded', true );

		$refund_amount = WC_Checkoutcom_Utility::decimal_to_value( $amount, $order->get_currency() );

		/* translators: %1$s: Payment ID, %2$s: Action ID, %3$s: Amount. */
		$order_message = sprintf( esc_html__( 'Checkout.com Payment Refunded - Payment ID: %1$s, Action ID: %2$s, Amount: %3$s', 'checkout-com-unified-payments-api' ), $payment_id, $action_id, $refund_amount_formatted );

		// Check if webhook amount is less than order amount - partial refund.
		if ( $amount < $order_amount_cents ) {
			/* translators: %1$s: Payment ID, %2$s: Action ID, %3$s: Amount. */
			$order_message = sprintf( esc_html__( 'Checkout.com Payment partially refunded - Payment ID: %1$s, Action ID: %2$s, Amount: %3$s', 'checkout-com-unified-payments-api' ), $payment_id, $action_id, $refund_amount_formatted );

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
			/* translators: %1$s: Payment ID, %2$s: Action ID, %3$s: Amount. */
			$order_message = sprintf( esc_html__( 'Checkout.com Payment fully refunded - Payment ID: %1$s, Action ID: %2$s, Amount: %3$s', 'checkout-com-unified-payments-api' ), $payment_id, $action_id, $refund_amount_formatted );

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

		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Refund processed successfully' );
			WC_Checkoutcom_Utility::logger( '=== WEBHOOK PROCESS: refund_payment END (SUCCESS) ===' );
		}
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
		$webhook_debug_enabled = self::is_webhook_debug_enabled();
		
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( '=== WEBHOOK PROCESS: cancel_payment START ===' );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Event type: payment_canceled' );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Full webhook data structure: ' . print_r($data, true) );
		}
		
		$webhook_data  = $data->data;
		$payment_id    = isset($webhook_data->id) ? $webhook_data->id : null;
		
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Payment ID: ' . ($payment_id ?? 'NULL') );
		}

		// Initialize the Checkout Api.
		$checkout = new Checkout_SDK();

		try {
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Fetching payment details from Checkout.com for payment ID: ' . $payment_id );
			}
			// Check if payment is already voided or captured on checkout.com hub.
			$details = $checkout->get_builder()->getPaymentsClient()->getPaymentDetails( $payment_id );
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Payment details retrieved: ' . print_r($details, true) );
			}

			$order_id = ! empty( $details['metadata']['order_id'] ) ? $details['metadata']['order_id'] : null;
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order ID from payment details metadata: ' . ($order_id ?? 'NULL') );
			}

			// Return false if no order id.
			if ( empty( $order_id ) || ! is_numeric( $order_id ) ) {
				// Always log errors
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: ERROR - No valid order_id found in payment details' );
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Payment details metadata: ' . print_r(isset($details['metadata']) ? $details['metadata'] : 'N/A', true) );
					WC_Checkoutcom_Utility::logger( '=== WEBHOOK PROCESS: cancel_payment END (FAILED - Invalid Order ID) ===' );
				}
				return false;
			}

			// Load order form order id.
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Attempting to load order ID: ' . $order_id );
			}
			$order = self::get_wc_order( $order_id );
			
			if ( ! $order ) {
				// Always log errors
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: ERROR - Order not found for ID: ' . $order_id );
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( '=== WEBHOOK PROCESS: cancel_payment END (FAILED - Order Not Found) ===' );
				}
				return false;
			}
			
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order loaded successfully - Order ID: ' . $order->get_id() . ', Status: ' . $order->get_status() );
			}

			// CRITICAL: Validate payment ID matches order (prevent wrong webhooks from matching orders)
			$order_payment_id = $order->get_meta( '_cko_flow_payment_id' );
			$order_payment_id_alt = $order->get_meta( '_cko_payment_id' );
			
			// Use Flow payment ID if available, otherwise fall back to regular payment ID
			$expected_payment_id = ! empty( $order_payment_id ) ? $order_payment_id : $order_payment_id_alt;
			
			if ( ! empty( $expected_payment_id ) && $expected_payment_id !== $payment_id ) {
				// Payment ID mismatch - reject webhook
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: ❌ CRITICAL ERROR - Cancel webhook payment ID mismatch!' );
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order ID: ' . $order->get_id() );
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order _cko_flow_payment_id: ' . ( $order_payment_id ?: 'NOT SET' ) );
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order _cko_payment_id: ' . ( $order_payment_id_alt ?: 'NOT SET' ) );
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Expected payment ID: ' . $expected_payment_id );
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Webhook payment ID: ' . $payment_id );
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: ❌ REJECTING CANCEL WEBHOOK - Payment ID does not match order!' );
				
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( '=== WEBHOOK PROCESS: cancel_payment END (FAILED - Payment ID Mismatch) ===' );
				}
				return false; // Reject webhook - wrong payment
			}
			
			if ( $webhook_debug_enabled && ! empty( $expected_payment_id ) ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: ✅ Payment ID validation passed - Order payment ID: ' . $expected_payment_id . ', Webhook payment ID: ' . $payment_id );
			}

			$status  = 'wc-cancelled';
			$amount = isset( $webhook_data->amount ) ? $webhook_data->amount : 0;
			$formatted_amount = '';
			if ( $amount > 0 ) {
				$formatted_amount = wc_price( WC_Checkoutcom_Utility::decimal_to_value( $amount, $order->get_currency() ), array( 'currency' => $order->get_currency() ) );
			} else {
				// Fallback to order amount if webhook doesn't have amount
				$formatted_amount = wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) );
			}
			$message = sprintf( 'Webhook received from checkout.com. Payment cancelled%s', $formatted_amount ? ' - Amount: ' . $formatted_amount : '' );

			// Add notes for the order and update status.
			$order->add_order_note( $message );
			$order->update_status( $status );

			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order status updated to: ' . $status );
				WC_Checkoutcom_Utility::logger( '=== WEBHOOK PROCESS: cancel_payment END (SUCCESS) ===' );
			}
			return true;

		} catch ( CheckoutApiException $ex ) {
			// Always log errors
			$error_message = 'WEBHOOK PROCESS: ERROR - An error has occurred while processing cancel request.';
			WC_Checkoutcom_Utility::logger( $error_message );
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Exception message: ' . $ex->getMessage() );
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Exception code: ' . $ex->getCode() );
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Exception trace: ' . $ex->getTraceAsString() );
			}

			// Check if gateway response is enabled from module settings.
			if ( $webhook_debug_enabled ) {
				$error_message .= $ex->getMessage();
			}

			WC_Checkoutcom_Utility::logger( $error_message, $ex );
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( '=== WEBHOOK PROCESS: cancel_payment END (FAILED - Exception) ===' );
			}
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
		$webhook_debug_enabled = self::is_webhook_debug_enabled();
		
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( '=== WEBHOOK PROCESS: decline_payment START ===' );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Event type: payment_declined/payment_authentication_failed' );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Full webhook data structure: ' . print_r($data, true) );
		}
		
		$webhook_data     = $data->data;
		$order_id         = isset($webhook_data->metadata->order_id) ? $webhook_data->metadata->order_id : null;
		$payment_id       = isset($webhook_data->id) ? $webhook_data->id : null;
		$action_id        = isset($webhook_data->action_id) ? $webhook_data->action_id : null;
		$response_summary = isset($webhook_data->response_summary) ? $webhook_data->response_summary : null;
		
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order ID from metadata: ' . ($order_id ?? 'NULL') );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Payment ID: ' . ($payment_id ?? 'NULL') );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Action ID: ' . ($action_id ?? 'NULL') );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Response summary: ' . ($response_summary ?? 'NULL') );
		}

		if ( empty( $order_id ) || ! is_numeric( $order_id ) ) {
			// Always log errors
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: ERROR - No valid order_id for payment: ' . ($payment_id ?? 'NULL') );
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( '=== WEBHOOK PROCESS: decline_payment END (FAILED - Invalid Order ID) ===' );
			}
			return false;
		}

		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Attempting to load order ID: ' . $order_id );
		}
		$order = self::get_wc_order( $order_id );
		
		if ( ! $order ) {
			// Always log errors
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: ERROR - Order not found for ID: ' . $order_id );
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( '=== WEBHOOK PROCESS: decline_payment END (FAILED - Order Not Found) ===' );
			}
			return false;
		}
		
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order loaded successfully - Order ID: ' . $order->get_id() . ', Status: ' . $order->get_status() );
		}

		// CRITICAL: Validate payment ID matches order (prevent wrong webhooks from matching orders)
		$order_payment_id = $order->get_meta( '_cko_flow_payment_id' );
		$order_payment_id_alt = $order->get_meta( '_cko_payment_id' );
		
		// Use Flow payment ID if available, otherwise fall back to regular payment ID
		$expected_payment_id = ! empty( $order_payment_id ) ? $order_payment_id : $order_payment_id_alt;
		
		// CRITICAL: If order has a payment ID, it MUST match the webhook payment ID
		// If order doesn't have payment ID yet, this webhook might be for a different payment attempt
		// For decline webhooks, we should be more strict - only process if payment IDs match OR order has no payment ID set
		if ( ! empty( $expected_payment_id ) && $expected_payment_id !== $payment_id ) {
			// Payment ID mismatch - reject webhook
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: ❌ CRITICAL ERROR - Decline webhook payment ID mismatch!' );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order ID: ' . $order->get_id() );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order _cko_flow_payment_id: ' . ( $order_payment_id ?: 'NOT SET' ) );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order _cko_payment_id: ' . ( $order_payment_id_alt ?: 'NOT SET' ) );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Expected payment ID: ' . $expected_payment_id );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Webhook payment ID: ' . $payment_id );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: ❌ REJECTING DECLINE WEBHOOK - Payment ID does not match order!' );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: This webhook is for a different payment attempt - ignoring to prevent incorrect order updates' );
			
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( '=== WEBHOOK PROCESS: decline_payment END (FAILED - Payment ID Mismatch) ===' );
			}
			return false; // Reject webhook - wrong payment
		}
		
		// If order doesn't have payment ID yet, this might be the first payment attempt for this order
		// But we should still validate - if order_id in metadata matches, it's likely correct
		// However, if multiple payments have same order_id, we can't distinguish them
		// For now, we'll allow it but log a warning
		if ( empty( $expected_payment_id ) ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: ⚠️ Order has no payment ID set yet - Processing decline webhook' );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: ⚠️ Order ID: ' . $order->get_id() . ', Webhook Payment ID: ' . $payment_id );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: ⚠️ This could be a different payment attempt - verify order_id in metadata is correct' );
		}
		
		if ( $webhook_debug_enabled && ! empty( $expected_payment_id ) ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: ✅ Payment ID validation passed - Order payment ID: ' . $expected_payment_id . ', Webhook payment ID: ' . $payment_id );
		}

		$status  = 'wc-failed';
		// Get amount from webhook or use order amount as fallback
		$amount = isset( $webhook_data->amount ) ? $webhook_data->amount : 0;
		$formatted_amount = '';
		if ( $amount > 0 ) {
			$formatted_amount = wc_price( WC_Checkoutcom_Utility::decimal_to_value( $amount, $order->get_currency() ), array( 'currency' => $order->get_currency() ) );
		} else {
			// Fallback to order amount if webhook doesn't have amount
			$formatted_amount = wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) );
		}
		
		// Include Payment ID and Action ID (if available) in the order note for consistency with other webhook handlers
		if ( ! empty( $action_id ) ) {
			$message = sprintf( 'Webhook received from checkout.com. Payment declined - Payment ID: %s, Action ID: %s, Reason: %s, Amount: %s', $payment_id, $action_id, $response_summary, $formatted_amount );
		} else {
			$message = sprintf( 'Webhook received from checkout.com. Payment declined - Payment ID: %s, Reason: %s, Amount: %s', $payment_id, $response_summary, $formatted_amount );
		}

		// Add notes for the order and update status.
		$order->add_order_note( $message );
		$order->update_status( $status );

		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order status updated to: ' . $status );
			WC_Checkoutcom_Utility::logger( '=== WEBHOOK PROCESS: decline_payment END (SUCCESS) ===' );
		}
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
		$webhook_debug_enabled = self::is_webhook_debug_enabled();
		
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: get_wc_order - Looking up order ID: ' . $order_id );
		}
		
		$order = wc_get_order( $order_id );
		
		if ( $order ) {
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: get_wc_order - Order found via wc_get_order - ID: ' . $order->get_id() . ', Status: ' . $order->get_status() );
			}
			return $order;
		}

		// Query order by order number to check if order exist.
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: get_wc_order - Order not found via wc_get_order, trying order_number lookup' );
		}
		$orders = wc_get_orders(
			[
				'order_number' => $order_id,
			]
		);

		if ( ! empty( $orders ) && isset( $orders[0] ) ) {
			$order = $orders[0];
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: get_wc_order - Order found via order_number lookup - ID: ' . $order->get_id() . ', Status: ' . $order->get_status() );
			}
			return $order;
		}
		
		// Always log errors
		WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: get_wc_order - ERROR - Order not found by ID or order_number: ' . $order_id );
		return false;
	}
}
