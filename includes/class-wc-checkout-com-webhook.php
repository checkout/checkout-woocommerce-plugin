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
		return WC_Admin_Settings::get_option( 'cko_gateway_responses' ) === 'yes';
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
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Event ID: ' . ( isset( $data->id ) ? $data->id : 'N/A' ) . ', type: ' . ( isset( $data->type ) ? $data->type : 'N/A' ) );
		}

		$webhook_data = $data->data;
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Webhook data extracted. Payment ID: ' . ( isset( $webhook_data->id ) ? $webhook_data->id : 'N/A' ) . ', reference: ' . ( isset( $webhook_data->reference ) ? $webhook_data->reference : 'N/A' ) );
		}
		
		$order_id = isset($webhook_data->metadata->order_id) ? $webhook_data->metadata->order_id : null;
		
		// FALLBACK: Use reference as order ID if metadata->order_id is not set
		// We send order_id as reference when submitting payment session
		if ( empty( $order_id ) && isset( $webhook_data->reference ) && is_numeric( $webhook_data->reference ) ) {
			$order_id = $webhook_data->reference;
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: ✅ Using reference as order ID: ' . $order_id );
			}
		}
		
		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order ID from metadata: ' . ($order_id ?? 'NULL') );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Payment ID: ' . ($webhook_data->id ?? 'NULL') );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Reference: ' . ($webhook_data->reference ?? 'NULL') );
			
			// Log available metadata keys only (not values — may contain PII)
			if ( isset( $webhook_data->metadata ) ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Metadata keys present: ' . implode( ', ', array_keys( (array) $webhook_data->metadata ) ) );
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

		// CRITICAL: Never process webhooks if payment security check failed (amount/currency mismatch).
		// Order was correctly set to Failed - webhooks must NOT override this.
		$security_check_failed = $order->get_meta( '_cko_security_check_failed' );
		if ( ! empty( $security_check_failed ) ) {
			$payment_id = $webhook_data->id ?? 'N/A';
			$order->add_order_note( sprintf(
				/* translators: %s: reason for security check failure (e.g. amount_mismatch, currency_mismatch) */
				__( 'Checkout.com webhook ignored: Payment security check previously failed (%s). Order status remains Failed.', 'checkout-com-unified-payments-api' ),
				esc_html( $security_check_failed )
			) );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: authorize_payment - Order has security check failure (' . $security_check_failed . '), rejecting webhook to preserve Failed status. Payment ID: ' . $payment_id );
			return true; // Acknowledge webhook but do not change status.
		}

		$already_captured = $order->get_meta( 'cko_payment_captured' );
		$already_authorized = $order->get_meta( 'cko_payment_authorized' );
		$current_status   = $order->get_status();
		
		$payment_id = $webhook_data->id;
		$action_id  = $webhook_data->action_id;
		
		// MULTI-TAB DETECTION: Check if this payment ID is different from the order's primary payment
		$order_primary_payment_id = $order->get_meta( '_cko_payment_id' );
		$order_flow_payment_id = $order->get_meta( '_cko_flow_payment_id' );
		$expected_payment_id = ! empty( $order_flow_payment_id ) ? $order_flow_payment_id : $order_primary_payment_id;
		
		$is_different_payment = false;
		if ( ! empty( $expected_payment_id ) && $expected_payment_id !== $payment_id ) {
			$is_different_payment = true;
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 
					sprintf(
						'WEBHOOK PROCESS: ⚠️ MULTI-TAB DETECTED - Authorize webhook for DIFFERENT payment. Order primary: %s, Webhook: %s',
						$expected_payment_id,
						$payment_id
					)
				);
			}
		}

		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: authorize_payment - Current order status: ' . $current_status );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: authorize_payment - Already captured: ' . ( $already_captured ? 'YES' : 'NO' ) );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: authorize_payment - Different payment (multi-tab): ' . ( $is_different_payment ? 'YES' : 'NO' ) );
		}
		
		// If order already authorized/captured AND this is a different payment, note it as duplicate
		if ( ( $already_authorized || $already_captured ) && $is_different_payment ) {
			$amount     = isset( $webhook_data->amount ) ? $webhook_data->amount : 0;
			$formatted_amount = $amount > 0 ? wc_price( WC_Checkoutcom_Utility::decimal_to_value( $amount, $order->get_currency() ), array( 'currency' => $order->get_currency() ) ) : '';
			
			// Find the session ID for this payment from the payment attempts array
			$session_id_for_note = '';
			$payment_attempts = $order->get_meta( '_cko_payment_attempts' );
			if ( ! empty( $payment_attempts ) && is_array( $payment_attempts ) ) {
				foreach ( $payment_attempts as $attempt ) {
					if ( isset( $attempt['payment_id'] ) && $attempt['payment_id'] === $payment_id && ! empty( $attempt['payment_session_id'] ) ) {
						$session_id_for_note = $attempt['payment_session_id'];
						break;
					}
				}
			}
			
			$order->add_order_note(
				sprintf(
					/* translators: %1$s: Payment ID from webhook, %2$s: Session ID, %3$s: Amount */
					__( 'Duplicate payment authorization webhook received from different tab/session. Payment ID: %1$s, Session: %2$s%3$s. Order was already authorized/captured with a different payment.', 'checkout-com-unified-payments-api' ),
					$payment_id,
					$session_id_for_note ? $session_id_for_note : 'unknown',
					$formatted_amount ? ', Amount: ' . $formatted_amount : ''
				)
			);
			
			// Update the payment attempts array
			if ( ! empty( $payment_attempts ) && is_array( $payment_attempts ) ) {
				foreach ( $payment_attempts as $index => $attempt ) {
					if ( isset( $attempt['payment_id'] ) && $attempt['payment_id'] === $payment_id ) {
						$payment_attempts[ $index ]['status'] = 'duplicate_authorized_after_primary';
						$payment_attempts[ $index ]['webhook_received_at'] = current_time( 'mysql' );
						break;
					}
				}
				$order->update_meta_data( '_cko_payment_attempts', $payment_attempts );
				$order->save();
			}
			
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Duplicate authorize from different tab acknowledged. Order ID: ' . $order_id . ', Duplicate Payment ID: ' . $payment_id );
			return true; // Acknowledge webhook but don't change order
		}
		
		// MULTI-TAB SCENARIO: Secondary payment authorized while order is failed/pending
		// GOLDEN RULE: Authorization does NOT determine primary payment - only CAPTURE does
		// We note the authorization but do NOT change primary payment ID
		if ( $is_different_payment && in_array( $current_status, array( 'failed', 'pending' ), true ) ) {
			WC_Checkoutcom_Utility::logger( 
				sprintf(
					'WEBHOOK PROCESS: ⚠️ MULTI-TAB - Order %d is %s, secondary payment %s authorized. Noting but NOT changing primary (waiting for capture).',
					$order_id,
					$current_status,
					$payment_id
				)
			);
			
			// Check if this payment ID is tracked in payment_attempts
			$payment_attempts = $order->get_meta( '_cko_payment_attempts' );
			$payment_tracked = false;
			if ( ! empty( $payment_attempts ) && is_array( $payment_attempts ) ) {
				foreach ( $payment_attempts as $attempt ) {
					if ( isset( $attempt['payment_id'] ) && $attempt['payment_id'] === $payment_id ) {
						$payment_tracked = true;
						break;
					}
				}
			}
			
			if ( $payment_tracked ) {
				// Update the payment attempts array - note as authorized but NOT as primary yet
				// Primary is only set by the first CAPTURE
				if ( ! empty( $payment_attempts ) && is_array( $payment_attempts ) ) {
					foreach ( $payment_attempts as $index => $attempt ) {
						if ( isset( $attempt['payment_id'] ) && $attempt['payment_id'] === $payment_id ) {
							$payment_attempts[ $index ]['status'] = 'authorized_pending_capture';
							$payment_attempts[ $index ]['webhook_received_at'] = current_time( 'mysql' );
							break;
						}
					}
					$order->update_meta_data( '_cko_payment_attempts', $payment_attempts );
				}
				
				$order->add_order_note(
					sprintf(
						__( 'Secondary payment authorized (multi-tab). Payment ID: %s. Waiting for capture to determine primary payment.', 'checkout-com-unified-payments-api' ),
						$payment_id
					)
				);
				
				$order->save();
				// Continue processing authorization (update status to on-hold if currently failed/pending)
			} else {
				// Payment not tracked - note it but don't set as primary
				WC_Checkoutcom_Utility::logger( 
					sprintf(
						'WEBHOOK PROCESS: ⚠️ Authorize webhook for untracked payment %s on order %d - noting but NOT setting as primary',
						$payment_id,
						$order_id
					)
				);
				// Do NOT update _cko_flow_payment_id - let capture determine primary
				// Continue processing
			}
		}

		// CRITICAL: Never downgrade completed or processing orders - check FIRST before any other logic
		if ( in_array( $current_status, array( 'processing', 'completed' ), true ) ) {
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: authorize_payment - Order already completed/processing, skipping status update (payment authorised must never downgrade)' );
			}
			$amount     = isset( $webhook_data->amount ) ? $webhook_data->amount : 0;
			$formatted_amount = $amount > 0 ? wc_price( WC_Checkoutcom_Utility::decimal_to_value( $amount, $order->get_currency() ), array( 'currency' => $order->get_currency() ) ) : '';
			$message    = sprintf( 'Webhook received from checkout.com. Payment Authorized - Payment ID: %s, Action ID: %s%s', $payment_id, $action_id, $formatted_amount ? ', Amount: ' . $formatted_amount : '' );
			$order->set_transaction_id( $action_id );
			$order->update_meta_data( '_cko_payment_id', $payment_id );
			$order->update_meta_data( 'cko_payment_authorized', true );
			$order->add_order_note( $message );
			return true;
		}

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

		$auth_status        = WC_Admin_Settings::get_option( 'ckocom_order_authorised', 'on-hold' );

		// Don't update status if already authorized AND status matches
		if ( $already_authorized && $order->get_status() === $auth_status ) {
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: authorize_payment - Already authorized with matching status, adding note only' );
			}
			$order->add_order_note( $message );
			return true;
		}

		// Set action id as woo transaction id.
		$order->set_transaction_id( $action_id );
		$order->update_meta_data( '_cko_payment_id', $payment_id );
		$order->update_meta_data( 'cko_payment_authorized', true );

		// Ensure payment method title is correct before status update (for Flow gateway)
		if ( $order->get_payment_method() === 'wc_checkout_com_flow' ) {
			$payment_type = $order->get_meta( '_cko_flow_payment_type' );
			
			if ( ! empty( $payment_type ) ) {
				$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
				$gateway = isset( $available_gateways[ $order->get_payment_method() ] ) ? $available_gateways[ $order->get_payment_method() ] : null;
				
				if ( $gateway && is_callable( array( $gateway, 'get_payment_method_title_by_type' ) ) ) {
					$correct_title = $gateway->get_payment_method_title_by_type( $order, null );
					$order->set_payment_method_title( $correct_title );
					$order->save();
					
					clean_post_cache( $order_id );
					$order = wc_get_order( $order_id );
				}
			}
		}

		// CRITICAL: Final guard before update_status - order may have been updated by capture webhook
		// during Flow payment title processing (save/reload above). Never downgrade completed/processing.
		$order = wc_get_order( $order_id );
		if ( $order && in_array( $order->get_status(), array( 'processing', 'completed' ), true ) ) {
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: authorize_payment - Final guard: Order now in advanced state (' . $order->get_status() . '), skipping status update to prevent downgrade' );
			}
			$order->set_transaction_id( $action_id );
			$order->update_meta_data( '_cko_payment_id', $payment_id );
			$order->update_meta_data( 'cko_payment_authorized', true );
			$order->add_order_note( $message );
			return true;
		}

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
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Event ID: ' . ( isset( $data->id ) ? $data->id : 'N/A' ) . ', type: ' . ( isset( $data->type ) ? $data->type : 'N/A' ) );
		}
		
		$webhook_data = $data->data;
		$order_id     = isset($webhook_data->metadata->order_id) ? $webhook_data->metadata->order_id : null;
		$action_id    = isset($webhook_data->action_id) ? $webhook_data->action_id : null;
		
		// FALLBACK: Use reference as order ID if metadata->order_id is not set
		if ( empty( $order_id ) && isset( $webhook_data->reference ) && is_numeric( $webhook_data->reference ) ) {
			$order_id = $webhook_data->reference;
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: ✅ Using reference as order ID: ' . $order_id );
			}
		}
		
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

		// CRITICAL: Never process webhooks if payment security check failed (amount/currency mismatch).
		// Order was correctly set to Failed - webhooks must NOT override this.
		$security_check_failed = $order->get_meta( '_cko_security_check_failed' );
		if ( ! empty( $security_check_failed ) ) {
			$payment_id = $webhook_data->id ?? 'N/A';
			$order->add_order_note( sprintf(
				/* translators: %s: reason for security check failure (e.g. amount_mismatch, currency_mismatch) */
				__( 'Checkout.com webhook ignored: Payment security check previously failed (%s). Order status remains Failed.', 'checkout-com-unified-payments-api' ),
				esc_html( $security_check_failed )
			) );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: card_verified - Order has security check failure (' . $security_check_failed . '), rejecting webhook to preserve Failed status. Payment ID: ' . $payment_id );
			return true; // Acknowledge webhook but do not change status.
		}

		$payment_id = $webhook_data->id;
		$order->add_order_note( sprintf( __( 'Checkout.com Card verified webhook received - Payment ID: %s, Action ID: %s', 'checkout-com-unified-payments-api' ), $payment_id, $action_id ) );
		// Set action id as woo transaction id.
		$order->set_transaction_id( $action_id );

		// Get cko capture status configured in admin.
		$status = WC_Admin_Settings::get_option( 'ckocom_order_captured', 'processing' );

		// CRITICAL: Refresh order and check status - never downgrade from completed
		$order = wc_get_order( $order_id );
		$current_status = $order->get_status();
		
		// update status of the order - but never downgrade from completed
		if ( 'completed' === $current_status ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: card_verified - Order already completed, skipping status update to prevent downgrade. Order ID: ' . $order_id );
		} else {
			$order->update_status( $status );
		}

		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order status updated to: ' . $status . ' (or skipped if already completed)' );
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
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Event ID: ' . ( isset( $data->id ) ? $data->id : 'N/A' ) . ', type: ' . ( isset( $data->type ) ? $data->type : 'N/A' ) );
		}
		
		$webhook_data = $data->data;
		$order_id     = isset($webhook_data->metadata->order_id) ? $webhook_data->metadata->order_id : null;
		
		// FALLBACK: Use reference as order ID if metadata->order_id is not set
		if ( empty( $order_id ) && isset( $webhook_data->reference ) && is_numeric( $webhook_data->reference ) ) {
			$order_id = $webhook_data->reference;
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: ✅ Using reference as order ID: ' . $order_id );
			}
		}
		
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

		// CRITICAL: Never process webhooks if payment security check failed (amount/currency mismatch).
		// Order was correctly set to Failed - webhooks must NOT override this.
		$security_check_failed = $order->get_meta( '_cko_security_check_failed' );
		if ( ! empty( $security_check_failed ) ) {
			$payment_id = $webhook_data->id ?? 'N/A';
			$order->add_order_note( sprintf(
				/* translators: %s: reason for security check failure (e.g. amount_mismatch, currency_mismatch) */
				__( 'Checkout.com webhook ignored: Payment security check previously failed (%s). Order status remains Failed.', 'checkout-com-unified-payments-api' ),
				esc_html( $security_check_failed )
			) );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: capture_payment - Order has security check failure (' . $security_check_failed . '), rejecting webhook to preserve Failed status. Payment ID: ' . $payment_id );
			return true; // Acknowledge webhook but do not change status.
		}

		// Check if payment is already captured.
		$already_captured = $order->get_meta( 'cko_payment_captured' );
		$payment_id       = $webhook_data->id;
		$current_order_status = $order->get_status();
		
		// MULTI-TAB DETECTION: Check if this payment ID is different from the order's primary payment
		$order_primary_payment_id = $order->get_meta( '_cko_payment_id' );
		$order_flow_payment_id = $order->get_meta( '_cko_flow_payment_id' );
		$expected_payment_id = ! empty( $order_flow_payment_id ) ? $order_flow_payment_id : $order_primary_payment_id;
		
		$is_different_payment = false;
		if ( ! empty( $expected_payment_id ) && $expected_payment_id !== $payment_id ) {
			$is_different_payment = true;
			WC_Checkoutcom_Utility::logger( 
				sprintf(
					'WEBHOOK PROCESS: ⚠️ MULTI-TAB DETECTED - Capture webhook for DIFFERENT payment. Order primary: %s, Webhook: %s, Order status: %s',
					$expected_payment_id,
					$payment_id,
					$current_order_status
				)
			);
		}
		
		// If order already captured AND this is a different payment, note it as duplicate
		if ( $already_captured && $is_different_payment ) {
			// Find the session ID for this payment from the payment attempts array
			$session_id_for_note = '';
			$payment_attempts = $order->get_meta( '_cko_payment_attempts' );
			if ( ! empty( $payment_attempts ) && is_array( $payment_attempts ) ) {
				foreach ( $payment_attempts as $attempt ) {
					if ( isset( $attempt['payment_id'] ) && $attempt['payment_id'] === $payment_id && ! empty( $attempt['payment_session_id'] ) ) {
						$session_id_for_note = $attempt['payment_session_id'];
						break;
					}
				}
			}
			
			$order->add_order_note(
				sprintf(
					/* translators: %1$s: Payment ID from webhook, %2$s: Session ID */
					__( 'Duplicate payment capture webhook received from different tab/session. Payment ID: %1$s, Session: %2$s. Order was already captured with a different payment. This payment attempt was processed after another completed first.', 'checkout-com-unified-payments-api' ),
					$payment_id,
					$session_id_for_note ? $session_id_for_note : 'unknown'
				)
			);
			
			// Update the payment attempts array
			if ( ! empty( $payment_attempts ) && is_array( $payment_attempts ) ) {
				foreach ( $payment_attempts as $index => $attempt ) {
					if ( isset( $attempt['payment_id'] ) && $attempt['payment_id'] === $payment_id ) {
						$payment_attempts[ $index ]['status'] = 'duplicate_captured_after_primary';
						$payment_attempts[ $index ]['webhook_received_at'] = current_time( 'mysql' );
						break;
					}
				}
				$order->update_meta_data( '_cko_payment_attempts', $payment_attempts );
				$order->save();
			}
			
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Duplicate capture from different tab acknowledged. Order ID: ' . $order_id . ', Duplicate Payment ID: ' . $payment_id );
			return true; // Acknowledge webhook but don't change order
		}
		
		// GOLDEN RULE: First payment to CAPTURE becomes PRIMARY forever
		// MULTI-TAB SCENARIO: This is the first capture for an order that's failed/pending/on-hold
		// If no capture has been processed yet, THIS capture sets the primary payment
		if ( $is_different_payment && in_array( $current_order_status, array( 'failed', 'pending', 'on-hold' ), true ) ) {
			WC_Checkoutcom_Utility::logger( 
				sprintf(
					'WEBHOOK PROCESS: ✅ FIRST CAPTURE WINS - Order %d is %s, payment %s is first to capture! Setting as PRIMARY.',
					$order_id,
					$current_order_status,
					$payment_id
				)
			);
			
			// Check if this payment ID is tracked in payment_attempts
			$payment_attempts = $order->get_meta( '_cko_payment_attempts' );
			$payment_tracked = false;
			if ( ! empty( $payment_attempts ) && is_array( $payment_attempts ) ) {
				foreach ( $payment_attempts as $attempt ) {
					if ( isset( $attempt['payment_id'] ) && $attempt['payment_id'] === $payment_id ) {
						$payment_tracked = true;
						break;
					}
				}
			}
			
			if ( $payment_tracked ) {
				// GOLDEN RULE: This is the FIRST capture - it becomes PRIMARY forever
				$order->update_meta_data( '_cko_payment_id', $payment_id );
				$order->update_meta_data( '_cko_flow_payment_id', $payment_id );
				
				// Update the payment attempts array - mark this as primary, demote others
				if ( ! empty( $payment_attempts ) && is_array( $payment_attempts ) ) {
					foreach ( $payment_attempts as $index => $attempt ) {
						if ( isset( $attempt['payment_id'] ) && $attempt['payment_id'] === $payment_id ) {
							$payment_attempts[ $index ]['status'] = 'captured_primary';
							$payment_attempts[ $index ]['is_primary'] = true;
							$payment_attempts[ $index ]['webhook_received_at'] = current_time( 'mysql' );
							$payment_attempts[ $index ]['captured_at'] = current_time( 'mysql' );
						} else {
							// All other payments are NOT primary
							$payment_attempts[ $index ]['is_primary'] = false;
							if ( ! isset( $payment_attempts[ $index ]['status'] ) || $payment_attempts[ $index ]['status'] === 'initiated' || $payment_attempts[ $index ]['status'] === 'authorized_pending_capture' ) {
								$payment_attempts[ $index ]['status'] = 'superseded_by_first_capture';
							}
						}
					}
					$order->update_meta_data( '_cko_payment_attempts', $payment_attempts );
				}
				
				$order->add_order_note(
					sprintf(
						__( 'First payment captured - this becomes the PRIMARY payment for this order. Payment ID: %s. All future refunds will use this payment.', 'checkout-com-unified-payments-api' ),
						$payment_id
					)
				);
				
				$order->save();
				// Continue processing - let the normal capture flow proceed
			} else {
				// Payment not tracked - this might be a rogue webhook, reject it
				WC_Checkoutcom_Utility::logger( 
					sprintf(
						'WEBHOOK PROCESS: ❌ REJECTING - Capture webhook for untracked payment %s on order %d',
						$payment_id,
						$order_id
					)
				);
				return false;
			}
		}

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
		
		// GOLDEN RULE: First capture sets the primary payment ID forever
		// Only set _cko_flow_payment_id if not already set (this is the payment used for refunds)
		$existing_flow_payment_id = $order->get_meta( '_cko_flow_payment_id' );
		if ( empty( $existing_flow_payment_id ) ) {
			$order->update_meta_data( '_cko_flow_payment_id', $payment_id );
			$order->update_meta_data( '_cko_payment_id', $payment_id );
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: capture_payment - First capture, setting PRIMARY payment ID: ' . $payment_id );
			}
		}

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

		// Ensure payment method title is correct before status update (for Flow gateway)
		if ( $order->get_payment_method() === 'wc_checkout_com_flow' ) {
			$payment_type = $order->get_meta( '_cko_flow_payment_type' );
			
			if ( ! empty( $payment_type ) ) {
				$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
				$gateway = isset( $available_gateways[ $order->get_payment_method() ] ) ? $available_gateways[ $order->get_payment_method() ] : null;
				
				if ( $gateway && is_callable( array( $gateway, 'get_payment_method_title_by_type' ) ) ) {
					$correct_title = $gateway->get_payment_method_title_by_type( $order, null );
					$order->set_payment_method_title( $correct_title );
					$order->save();
					
					clean_post_cache( $order_id );
					$order = wc_get_order( $order_id );
				}
			}
		}

		// CRITICAL: Refresh order from database and check status before updating
		// Never downgrade from 'completed' to 'processing'
		$order = wc_get_order( $order_id );
		$current_status = $order->get_status();
		
		// add notes for the order and update status.
		$order->add_order_note( $order_message );
		
		// Only update status if not already in a higher/equal state
		if ( 'completed' === $current_status ) {
			// Never downgrade from completed
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: capture_payment - Order already completed, skipping status update to prevent downgrade. Order ID: ' . $order_id );
		} elseif ( 'processing' === $current_status && 'processing' === $status ) {
			// Already at target status
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: capture_payment - Order already processing, no status change needed. Order ID: ' . $order_id );
		} else {
			$order->update_status( $status );
		}

		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order status updated to: ' . $status . ' (or skipped if already in higher state)' );
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
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Event ID: ' . ( isset( $data->id ) ? $data->id : 'N/A' ) . ', type: ' . ( isset( $data->type ) ? $data->type : 'N/A' ) );
		}
		
		$webhook_data = $data->data;
		$order_id     = isset($webhook_data->metadata->order_id) ? $webhook_data->metadata->order_id : null;
		$payment_id   = isset($webhook_data->id) ? $webhook_data->id : null;
		$action_id    = isset($webhook_data->action_id) ? $webhook_data->action_id : null;
		$response_summary = isset($webhook_data->response_summary) ? $webhook_data->response_summary : 'N/A';
		
		// FALLBACK: Use reference as order ID if metadata->order_id is not set
		if ( empty( $order_id ) && isset( $webhook_data->reference ) && is_numeric( $webhook_data->reference ) ) {
			$order_id = $webhook_data->reference;
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: ✅ Using reference as order ID: ' . $order_id );
			}
		}
		
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
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Event ID: ' . ( isset( $data->id ) ? $data->id : 'N/A' ) . ', type: ' . ( isset( $data->type ) ? $data->type : 'N/A' ) );
		}
		
		$webhook_data = $data->data;
		$order_id     = isset($webhook_data->metadata->order_id) ? $webhook_data->metadata->order_id : null;
		
		// FALLBACK: Use reference as order ID if metadata->order_id is not set
		if ( empty( $order_id ) && isset( $webhook_data->reference ) && is_numeric( $webhook_data->reference ) ) {
			$order_id = $webhook_data->reference;
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: ✅ Using reference as order ID: ' . $order_id );
			}
		}
		
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
		
		// CRITICAL: Refresh order and check status - only void if order hasn't been fulfilled
		$order = wc_get_order( $order_id );
		$current_status = $order->get_status();
		
		// If order is completed (fulfilled), don't change to cancelled - needs manual review
		if ( 'completed' === $current_status ) {
			$order->add_order_note( __( 'Void webhook received but order is already completed (fulfilled). Manual review required.', 'checkout-com-unified-payments-api' ) );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: void_payment - Order already completed, skipping status change to cancelled. Order ID: ' . $order_id );
		} else {
			$order->update_status( $status );
		}

		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order status updated to: ' . $status . ' (or skipped if completed)' );
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
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Event ID: ' . ( isset( $data->id ) ? $data->id : 'N/A' ) . ', type: ' . ( isset( $data->type ) ? $data->type : 'N/A' ) );
		}
		
		$webhook_data = $data->data;
		$order_id     = isset($webhook_data->metadata->order_id) ? $webhook_data->metadata->order_id : null;
		
		// FALLBACK: Use reference as order ID if metadata->order_id is not set
		if ( empty( $order_id ) && isset( $webhook_data->reference ) && is_numeric( $webhook_data->reference ) ) {
			$order_id = $webhook_data->reference;
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: ✅ Using reference as order ID: ' . $order_id );
			}
		}
		
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
			$order->save();
			return true;
		}

		// Set action id as woo transaction id.
		$order->set_transaction_id( $action_id );
		$order->update_meta_data( 'cko_payment_refunded', true );
		$order->save();

		$refund_amount = WC_Checkoutcom_Utility::decimal_to_value( $amount, $order->get_currency() );

		/* translators: %1$s: Payment ID, %2$s: Action ID, %3$s: Amount. */
		$order_message = sprintf( esc_html__( 'Checkout.com Payment Refund webhook received - Payment ID: %1$s, Action ID: %2$s, Amount: %3$s', 'checkout-com-unified-payments-api' ), $payment_id, $action_id, $refund_amount_formatted );

		// Check if webhook amount is less than order amount - partial refund.
		if ( $amount < $order_amount_cents ) {
			/* translators: %1$s: Payment ID, %2$s: Action ID, %3$s: Amount. */
			$order_message = sprintf( esc_html__( 'Checkout.com Payment Partial Refund webhook received - Payment ID: %1$s, Action ID: %2$s, Amount: %3$s', 'checkout-com-unified-payments-api' ), $payment_id, $action_id, $refund_amount_formatted );

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
			$order_message = sprintf( esc_html__( 'Checkout.com Payment Full Refund webhook received - Payment ID: %1$s, Action ID: %2$s, Amount: %3$s', 'checkout-com-unified-payments-api' ), $payment_id, $action_id, $refund_amount_formatted );

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
		$order->save();

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
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Event ID: ' . ( isset( $data->id ) ? $data->id : 'N/A' ) . ', type: ' . ( isset( $data->type ) ? $data->type : 'N/A' ) );
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
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Payment details retrieved. ID: ' . ( isset( $details['id'] ) ? $details['id'] : 'N/A' ) . ', status: ' . ( isset( $details['status'] ) ? $details['status'] : 'N/A' ) );
			}

			$order_id = ! empty( $details['metadata']['order_id'] ) ? $details['metadata']['order_id'] : null;
			
			// FALLBACK: Use reference as order ID if metadata->order_id is not set
			if ( empty( $order_id ) && ! empty( $details['reference'] ) && is_numeric( $details['reference'] ) ) {
				$order_id = $details['reference'];
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: ✅ Using reference as order ID: ' . $order_id );
				}
			}
			
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order ID from payment details metadata: ' . ($order_id ?? 'NULL') );
			}

			// Return false if no order id.
			if ( empty( $order_id ) || ! is_numeric( $order_id ) ) {
				// Always log errors
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: ERROR - No valid order_id found in payment details' );
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Payment details metadata keys: ' . ( isset( $details['metadata'] ) ? implode( ', ', array_keys( (array) $details['metadata'] ) ) : 'N/A' ) );
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
			
			// CRITICAL: Refresh order and check status - don't cancel completed/processing orders
			$order = wc_get_order( $order_id );
			$current_status = $order->get_status();
			
			// If order is completed or processing, don't cancel - needs manual review
			if ( in_array( $current_status, array( 'completed', 'processing' ), true ) ) {
				$order->add_order_note( __( 'Cancel webhook received but order is already ' . $current_status . '. Manual review required.', 'checkout-com-unified-payments-api' ) );
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: cancel_payment - Order already ' . $current_status . ', skipping status change to cancelled. Order ID: ' . $order_id );
			} else {
				$order->update_status( $status );
			}

			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order status updated to: ' . $status . ' (or skipped if completed/processing)' );
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
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Event ID: ' . ( isset( $data->id ) ? $data->id : 'N/A' ) . ', type: ' . ( isset( $data->type ) ? $data->type : 'N/A' ) );
		}
		
		$webhook_data     = $data->data;
		$order_id         = isset($webhook_data->metadata->order_id) ? $webhook_data->metadata->order_id : null;
		$payment_id       = isset($webhook_data->id) ? $webhook_data->id : null;
		$action_id        = isset($webhook_data->action_id) ? $webhook_data->action_id : null;
		$response_summary = isset($webhook_data->response_summary) ? $webhook_data->response_summary : null;
		
		// FALLBACK: Use reference as order ID if metadata->order_id is not set
		if ( empty( $order_id ) && isset( $webhook_data->reference ) && is_numeric( $webhook_data->reference ) ) {
			$order_id = $webhook_data->reference;
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: ✅ Using reference as order ID: ' . $order_id );
			}
		}
		
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

		// MULTI-TAB DETECTION: Check if this payment ID is different from the order's primary payment
		$order_payment_id = $order->get_meta( '_cko_flow_payment_id' );
		$order_payment_id_alt = $order->get_meta( '_cko_payment_id' );
		$expected_payment_id = ! empty( $order_payment_id ) ? $order_payment_id : $order_payment_id_alt;
		
		$is_different_payment = false;
		if ( ! empty( $expected_payment_id ) && $expected_payment_id !== $payment_id ) {
			$is_different_payment = true;
			WC_Checkoutcom_Utility::logger( 
				sprintf(
					'WEBHOOK PROCESS: ⚠️ MULTI-TAB DETECTED - Decline webhook for DIFFERENT payment. Order primary: %s, Webhook: %s',
					$expected_payment_id,
					$payment_id
				)
			);
		}
		
		// Check if this payment is tracked in our payment_attempts array
		$payment_attempts = $order->get_meta( '_cko_payment_attempts' );
		$payment_tracked = false;
		$is_tracked_as_secondary = false;
		if ( ! empty( $payment_attempts ) && is_array( $payment_attempts ) ) {
			foreach ( $payment_attempts as $attempt ) {
				if ( isset( $attempt['payment_id'] ) && $attempt['payment_id'] === $payment_id ) {
					$payment_tracked = true;
					$is_tracked_as_secondary = isset( $attempt['is_primary'] ) && ! $attempt['is_primary'];
					break;
				}
			}
		}
		
		// MULTI-TAB: If this is a secondary payment that failed, just note it - don't fail the order
		// because another tab might have a successful payment pending
		if ( $is_different_payment && $payment_tracked && $is_tracked_as_secondary ) {
			// Find the session ID for this payment
			$session_id_for_note = '';
			foreach ( $payment_attempts as $attempt ) {
				if ( isset( $attempt['payment_id'] ) && $attempt['payment_id'] === $payment_id && ! empty( $attempt['payment_session_id'] ) ) {
					$session_id_for_note = $attempt['payment_session_id'];
					break;
				}
			}
			
			WC_Checkoutcom_Utility::logger( 
				sprintf(
					'WEBHOOK PROCESS: ⚠️ MULTI-TAB - Secondary payment %s (Session: %s) declined on order %d. NOT failing order - primary payment may still succeed.',
					$payment_id,
					$session_id_for_note ? $session_id_for_note : 'unknown',
					$order->get_id()
				)
			);
			
			// Update the payment attempts array
			foreach ( $payment_attempts as $index => $attempt ) {
				if ( isset( $attempt['payment_id'] ) && $attempt['payment_id'] === $payment_id ) {
					$payment_attempts[ $index ]['status'] = 'declined';
					$payment_attempts[ $index ]['webhook_received_at'] = current_time( 'mysql' );
					$payment_attempts[ $index ]['decline_reason'] = $response_summary;
					break;
				}
			}
			$order->update_meta_data( '_cko_payment_attempts', $payment_attempts );
			
			$order->add_order_note(
				sprintf(
					__( 'Secondary payment attempt declined (multi-tab). Payment ID: %s, Session: %s, Reason: %s. Order status unchanged - primary payment may still complete.', 'checkout-com-unified-payments-api' ),
					$payment_id,
					$session_id_for_note ? $session_id_for_note : 'unknown',
					$response_summary
				)
			);
			$order->save();
			
			return true; // Acknowledge webhook but don't change order status
		}
		
		// If this decline is for a DIFFERENT payment that's NOT tracked, it might be a rogue webhook
		// or a payment from a much older session - reject it to avoid incorrectly failing the order
		if ( $is_different_payment && ! $payment_tracked ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: ❌ REJECTING - Decline webhook for untracked different payment!' );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order ID: ' . $order->get_id() );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order primary payment ID: ' . $expected_payment_id );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Webhook payment ID: ' . $payment_id );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: This payment is not tracked - ignoring to prevent incorrect order updates' );
			
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( '=== WEBHOOK PROCESS: decline_payment END (FAILED - Untracked Payment) ===' );
			}
			return false;
		}
		
		// If order doesn't have payment ID yet, this is the first payment attempt
		if ( empty( $expected_payment_id ) ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order has no payment ID set yet - Processing decline webhook for first payment attempt' );
		}
		
		if ( $webhook_debug_enabled && ! empty( $expected_payment_id ) && ! $is_different_payment ) {
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
		
		// CRITICAL: Refresh order and check status - don't fail orders that are already successful
		$order = wc_get_order( $order_id );
		$current_status = $order->get_status();
		
		// If order is already in a successful state, don't downgrade to failed
		// This can happen if a successful payment was processed and a stale decline webhook arrives
		if ( in_array( $current_status, array( 'completed', 'processing', 'on-hold' ), true ) ) {
			$order->add_order_note( __( 'Decline webhook received but order is already ' . $current_status . '. This may be a stale webhook for a previous failed attempt. Status unchanged.', 'checkout-com-unified-payments-api' ) );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: decline_payment - Order already ' . $current_status . ', skipping status change to failed. Order ID: ' . $order_id );
		} else {
			$order->update_status( $status );
		}

		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: Order status updated to: ' . $status . ' (or skipped if already successful)' );
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
