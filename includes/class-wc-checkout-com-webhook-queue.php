<?php
/**
 * Webhook Queue Manager Class
 *
 * Handles queuing and processing of webhooks that arrive before orders are created.
 *
 * @package wc_checkout_com
 */

/**
 * Class WC_Checkout_Com_Webhook_Queue handles webhook queuing.
 */
class WC_Checkout_Com_Webhook_Queue {

	/**
	 * Get the table name for pending webhooks.
	 *
	 * @return string
	 */
	private static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'cko_pending_webhooks';
	}

	/**
	 * Create the pending webhooks table.
	 *
	 * @return void
	 */
	public static function create_table() {
		global $wpdb;
		$table_name = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			payment_id VARCHAR(255) NOT NULL,
			order_id VARCHAR(50) NULL,
			payment_session_id VARCHAR(255) NULL,
			webhook_type VARCHAR(50) NOT NULL,
			webhook_data LONGTEXT NOT NULL,
			created_at DATETIME NOT NULL,
			processed_at DATETIME NULL,
			INDEX idx_payment_id (payment_id),
			INDEX idx_order_id (order_id),
			INDEX idx_payment_session_id (payment_session_id),
			INDEX idx_processed (processed_at),
			INDEX idx_created (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

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
	 * Save a pending webhook to the queue.
	 *
	 * @param string $payment_id Payment ID from Checkout.com.
	 * @param string|null $order_id Order ID (may be null for Flow payments).
	 * @param string|null $payment_session_id Payment session ID (may be null).
	 * @param string $webhook_type Webhook type ('payment_approved' or 'payment_captured').
	 * @param object $webhook_data Full webhook payload.
	 * @return bool True if saved successfully, false otherwise.
	 */
	public static function save_pending_webhook( $payment_id, $order_id, $payment_session_id, $webhook_type, $webhook_data ) {
		global $wpdb;
		$table_name = self::get_table_name();
		$webhook_debug_enabled = self::is_webhook_debug_enabled();

		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: === WEBHOOK QUEUE: save_pending_webhook START ===' );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: WEBHOOK QUEUE: Attempting to queue webhook - Type: ' . $webhook_type . ', Payment ID: ' . $payment_id . ', Order ID: ' . ( $order_id ?? 'NULL' ) . ', Session ID: ' . ( $payment_session_id ?? 'NULL' ) );
		}

		// Validate webhook type (only auth and capture)
		if ( ! in_array( $webhook_type, array( 'payment_approved', 'payment_captured' ), true ) ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: WEBHOOK QUEUE: ERROR - Invalid webhook type for queuing: ' . $webhook_type );
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: === WEBHOOK QUEUE: save_pending_webhook END (FAILED - Invalid Type) ===' );
			}
			return false;
		}

		// Validate payment_id (required)
		if ( empty( $payment_id ) ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: WEBHOOK QUEUE: ERROR - Cannot queue webhook - payment_id is required' );
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: === WEBHOOK QUEUE: save_pending_webhook END (FAILED - Missing Payment ID) ===' );
			}
			return false;
		}

		// Encode webhook data as JSON
		$webhook_data_json = wp_json_encode( $webhook_data );
		if ( false === $webhook_data_json ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: WEBHOOK QUEUE: ERROR - Failed to encode webhook data as JSON' );
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: === WEBHOOK QUEUE: save_pending_webhook END (FAILED - JSON Encode Error) ===' );
			}
			return false;
		}

		// Sanitize inputs
		$payment_id = sanitize_text_field( $payment_id );
		$order_id = ! empty( $order_id ) ? sanitize_text_field( $order_id ) : null;
		$payment_session_id = ! empty( $payment_session_id ) ? sanitize_text_field( $payment_session_id ) : null;
		$webhook_type = sanitize_text_field( $webhook_type );

		$result = $wpdb->insert(
			$table_name,
			array(
				'payment_id'         => $payment_id,
				'order_id'          => $order_id,
				'payment_session_id' => $payment_session_id,
				'webhook_type'      => $webhook_type,
				'webhook_data'      => $webhook_data_json,
				'created_at'        => current_time( 'mysql' ),
				'processed_at'      => null,
			),
			array(
				'%s', // payment_id
				'%s', // order_id
				'%s', // payment_session_id
				'%s', // webhook_type
				'%s', // webhook_data
				'%s', // created_at
				null, // processed_at
			)
		);

		if ( false === $result ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: WEBHOOK QUEUE: ERROR - Failed to save webhook to queue - ' . $wpdb->last_error );
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: === WEBHOOK QUEUE: save_pending_webhook END (FAILED - Database Error) ===' );
			}
			return false;
		}

		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: WEBHOOK QUEUE: Successfully queued webhook - Queue ID: ' . $wpdb->insert_id . ', Type: ' . $webhook_type . ', Payment ID: ' . $payment_id );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: === WEBHOOK QUEUE: save_pending_webhook END (SUCCESS) ===' );
		} else {
			// Always log successful queue operations (even if debug disabled)
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: WEBHOOK QUEUE: Webhook queued successfully - Type: ' . $webhook_type . ', Payment ID: ' . $payment_id . ', Order ID: ' . ( $order_id ?? 'NULL' ) );
		}

		return true;
	}

	/**
	 * Get pending webhooks for an order.
	 *
	 * @param WC_Order $order WooCommerce order object.
	 * @return array Array of pending webhook objects.
	 */
	public static function get_pending_webhooks_for_order( $order ) {
		global $wpdb;
		$table_name = self::get_table_name();

		$payment_id = $order->get_meta( '_cko_payment_id' );
		$payment_session_id = $order->get_meta( '_cko_payment_session_id' );
		$order_id = $order->get_id();

		// Build WHERE clause with priority: payment_id > payment_session_id > order_id
		$where_conditions = array();
		$where_values = array();

		if ( ! empty( $payment_id ) ) {
			$where_conditions[] = 'payment_id = %s';
			$where_values[] = $payment_id;
		}

		if ( ! empty( $payment_session_id ) ) {
			$where_conditions[] = 'payment_session_id = %s';
			$where_values[] = $payment_session_id;
		}

		if ( ! empty( $order_id ) ) {
			$where_conditions[] = 'order_id = %s';
			$where_values[] = (string) $order_id;
		}

		// If no identifiers available, return empty array
		if ( empty( $where_conditions ) ) {
			return array();
		}

		$where_clause = '(' . implode( ' OR ', $where_conditions ) . ') AND processed_at IS NULL';

		$query = "SELECT * FROM {$table_name} 
			WHERE {$where_clause}
			ORDER BY 
				CASE webhook_type
					WHEN 'payment_approved' THEN 1
					WHEN 'payment_captured' THEN 2
					ELSE 3
				END,
				created_at ASC";

		if ( ! empty( $where_values ) ) {
			$query = $wpdb->prepare( $query, $where_values );
		}

		$results = $wpdb->get_results( $query );

		return $results ? $results : array();
	}

	/**
	 * Process pending webhooks for an order.
	 *
	 * @param WC_Order $order WooCommerce order object.
	 * @return int Number of webhooks processed.
	 */
	public static function process_pending_webhooks_for_order( $order ) {
		$webhook_debug_enabled = self::is_webhook_debug_enabled();
		$pending_webhooks = self::get_pending_webhooks_for_order( $order );

		if ( empty( $pending_webhooks ) ) {
			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: WEBHOOK QUEUE: No pending webhooks found for Order ID: ' . $order->get_id() );
			}
			return 0;
		}

		$order_id = $order->get_id();
		$processed_count = 0;

		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: === WEBHOOK QUEUE: process_pending_webhooks_for_order START ===' );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: WEBHOOK QUEUE: Found ' . count( $pending_webhooks ) . ' pending webhook(s) for Order ID: ' . $order_id );
		} else {
			// Always log when processing queued webhooks (even if debug disabled)
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: WEBHOOK QUEUE: Processing ' . count( $pending_webhooks ) . ' pending webhook(s) for Order ID: ' . $order_id );
		}

		foreach ( $pending_webhooks as $queued_webhook ) {
			// Decode webhook data
			$webhook_data = json_decode( $queued_webhook->webhook_data );

			if ( ! $webhook_data ) {
				WC_Checkoutcom_Utility::logger(
					'WEBHOOK PROCESS: WEBHOOK QUEUE: ERROR - Failed to decode webhook data for queued webhook ID: ' . $queued_webhook->id
				);
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: WEBHOOK QUEUE: Skipping webhook ID: ' . $queued_webhook->id . ' due to decode error' );
				}
				continue;
			}

			if ( $webhook_debug_enabled ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: WEBHOOK QUEUE: Processing queued webhook - Queue ID: ' . $queued_webhook->id . ', Type: ' . $queued_webhook->webhook_type . ', Payment ID: ' . $queued_webhook->payment_id );
			}

			// Ensure order_id is set in metadata for processing functions
			if ( ! isset( $webhook_data->data->metadata ) ) {
				$webhook_data->data->metadata = new stdClass();
			}
			$webhook_data->data->metadata->order_id = $order_id;

			// Process webhook based on type
			$success = false;
			if ( 'payment_approved' === $queued_webhook->webhook_type ) {
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: WEBHOOK QUEUE: Calling authorize_payment() for queued webhook' );
				}
				$success = WC_Checkout_Com_Webhook::authorize_payment( $webhook_data );
			} elseif ( 'payment_captured' === $queued_webhook->webhook_type ) {
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: WEBHOOK QUEUE: Calling capture_payment() for queued webhook' );
				}
				$success = WC_Checkout_Com_Webhook::capture_payment( $webhook_data );
			}

			if ( $success ) {
				// Mark as processed
				global $wpdb;
				$table_name = self::get_table_name();
				$wpdb->update(
					$table_name,
					array( 'processed_at' => current_time( 'mysql' ) ),
					array( 'id' => $queued_webhook->id ),
					array( '%s' ),
					array( '%d' )
				);

				$processed_count++;
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger(
						'WEBHOOK PROCESS: WEBHOOK QUEUE: Successfully processed queued webhook - ' .
						'Queue ID: ' . $queued_webhook->id . ', ' .
						'Order ID: ' . $order_id . ', ' .
						'Type: ' . $queued_webhook->webhook_type . ', ' .
						'Payment ID: ' . $queued_webhook->payment_id
					);
				} else {
					// Always log successful processing (even if debug disabled)
					WC_Checkoutcom_Utility::logger(
						'WEBHOOK PROCESS: WEBHOOK QUEUE: Successfully processed queued webhook - ' .
						'Order ID: ' . $order_id . ', ' .
						'Type: ' . $queued_webhook->webhook_type . ', ' .
						'Payment ID: ' . $queued_webhook->payment_id
					);
				}
			} else {
				WC_Checkoutcom_Utility::logger(
					'WEBHOOK PROCESS: WEBHOOK QUEUE: ERROR - Failed to process queued webhook - ' .
					'Queue ID: ' . $queued_webhook->id . ', ' .
					'Order ID: ' . $order_id . ', ' .
					'Type: ' . $queued_webhook->webhook_type . ', ' .
					'Payment ID: ' . $queued_webhook->payment_id . ' - ' .
					'Will remain in queue for retry'
				);
				if ( $webhook_debug_enabled ) {
					WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: WEBHOOK QUEUE: Webhook processing returned false - check webhook handler logs for details' );
				}
			}
		}

		if ( $webhook_debug_enabled ) {
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: WEBHOOK QUEUE: Processed ' . $processed_count . ' of ' . count( $pending_webhooks ) . ' queued webhook(s) for Order ID: ' . $order_id );
			WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: === WEBHOOK QUEUE: process_pending_webhooks_for_order END ===' );
		} else {
			// Always log summary (even if debug disabled)
			if ( $processed_count > 0 ) {
				WC_Checkoutcom_Utility::logger( 'WEBHOOK PROCESS: WEBHOOK QUEUE: Completed processing - ' . $processed_count . ' webhook(s) processed successfully for Order ID: ' . $order_id );
			}
		}

		return $processed_count;
	}

	/**
	 * Cleanup old processed webhooks.
	 *
	 * @param int $days Number of days to keep processed webhooks (default 7).
	 * @return int Number of webhooks deleted.
	 */
	public static function cleanup_old_webhooks( $days = 7 ) {
		global $wpdb;
		$table_name = self::get_table_name();

		$cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} WHERE processed_at IS NOT NULL AND processed_at < %s",
				$cutoff_date
			)
		);

		if ( false !== $deleted ) {
			WC_Checkoutcom_Utility::logger( "WEBHOOK QUEUE: Cleaned up {$deleted} old processed webhook(s) older than {$days} days" );
		}

		return $deleted ? $deleted : 0;
	}

	/**
	 * Cleanup old unprocessed webhooks (orphaned webhooks).
	 *
	 * @param int $days Number of days to keep unprocessed webhooks (default 7).
	 * @return int Number of webhooks deleted.
	 */
	public static function cleanup_old_unprocessed_webhooks( $days = 7 ) {
		global $wpdb;
		$table_name = self::get_table_name();

		$cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} WHERE processed_at IS NULL AND created_at < %s",
				$cutoff_date
			)
		);

		if ( false !== $deleted ) {
			WC_Checkoutcom_Utility::logger( "WEBHOOK QUEUE: Cleaned up {$deleted} old unprocessed webhook(s) older than {$days} days" );
		}

		return $deleted ? $deleted : 0;
	}
}




