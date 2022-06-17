<?php

include_once WC_CHECKOUTCOM_PLUGIN_PATH . '/includes/api/class-wc-checkoutcom-api-request.php';

/**
 *  This class handles the payment for subscription renewal
 */
class WC_Checkoutcom_Subscription {

	/**
	 * Create a renewal payment.
	 *
	 * @param int    $renewal_total Renewal total amount.
	 * @param object $renewal_order Order object to be renewed.
	 */
	public static function renewal_payment( $renewal_total, $renewal_order ) {

		// Get renewal order ID.
		$order_id = $renewal_order->get_id();

		$args              = [];
		$subscriptions_arr = [];

		// Get subscription object from the order.
		if ( wcs_order_contains_subscription( $renewal_order, 'renewal' ) ) {
			$subscriptions_arr = wcs_get_subscriptions_for_order( $renewal_order, [ 'order_type' => 'renewal' ] );
		}

		foreach ( $subscriptions_arr as $subscriptions_obj ) {
			$args['source_id']       = get_post_meta( $subscriptions_obj->get_id(), '_cko_source_id', true );
			$args['parent_order_id'] = $subscriptions_obj->get_parent_id();
		}

		$payment_result = (array) WC_Checkoutcom_Api_Request::create_payment( $renewal_order, $args, 'renewal' );

		// Update renewal order status based on payment result.
		if ( ! isset( $payment_result['error'] ) ) {
			self::update_order_status( $payment_result, $renewal_order, $order_id );
		}
	}

	/**
	 * Update status of renewal order and add notes
	 *
	 * @param array  $payment_result Payment result.
	 * @param object $renewal_order Renewal order object.
	 * @param int    $order_id Order ID.
	 */
	public static function update_order_status( $payment_result, $renewal_order, $order_id ) {

		// Set action id as woo transaction id.
		update_post_meta( $order_id, '_transaction_id', $payment_result['action_id'] );
		update_post_meta( $order_id, '_cko_payment_id', $payment_result['id'] );

		if ( 'wc_checkout_com_alternative_payments_sepa' === get_post_meta( $order_id, '_payment_method', true ) ) {
			update_post_meta( $order_id, 'cko_payment_authorized', true );
		}

		// Get cko auth status configured in admin.
		$status  = WC_Admin_Settings::get_option( 'ckocom_order_authorised', 'on-hold' );
		$message = sprintf(
			/* translators: %s: Payment result ID. */
			__( 'Checkout.com Payment Authorised - Action ID : %s ', 'wc_checkout_com' ),
			$payment_result['action_id']
		);

		// check if payment was flagged.
		if ( $payment_result['risk']['flagged'] ) {
			// Get cko auth status configured in admin.
			$status  = WC_Admin_Settings::get_option( 'ckocom_order_flagged', 'flagged' );
			$message = sprintf(
				/* translators: %s: Payment result ID. */
				__( 'Checkout.com Payment Flagged - Action ID : %s ', 'wc_checkout_com' ),
				$payment_result['action_id']
			);
		}

		// add notes for the order.
		$renewal_order->add_order_note( $message );

		$order_status = $renewal_order->get_status();

		if ( 'pending' === $order_status ) {
			update_post_meta( $order_id, 'cko_payment_authorized', true );
			$renewal_order->update_status( $status );
		}

		// Reduce stock levels.
		wc_reduce_stock_levels( $order_id );

	}

	/**
	 *  Save source id for each order containing subscription
	 *
	 *  @param int    $order_id Order ID.
	 *  @param object $order Order object.
	 *  @param string $source_id Payment source ID.
	 */
	public static function save_source_id( $order_id, $order, $source_id ) {
		// update source id for subscription payment method change.
		if ( $order instanceof WC_Subscription ) {
			update_post_meta( $order->get_id(), '_cko_source_id', $source_id );
		}

		// Check for subscription and save source id.
		if ( function_exists( 'wcs_order_contains_subscription' ) ) {
			if ( wcs_order_contains_subscription( $order_id ) ) {
				$subscriptions = wcs_get_subscriptions_for_order( $order );

				foreach ( $subscriptions as $subscription_obj ) {
					update_post_meta( $subscription_obj->get_id(), '_cko_source_id', $source_id );
				}
			}
		}

		return false;
	}

	/**
	 * Save source id for each order containing subscription
	 *
	 * @param object $subscription WC_Subscription.
	 *
	 * @return void
	 */
	public static function subscription_cancelled( $subscription ) {

		if ( 'wc_checkout_com_alternative_payments_sepa' === $subscription->get_payment_method() ) {

			$mandate_cancel = get_post_meta( $subscription->get_id(), '_cko_mandate_cancel', true );

			if ( $mandate_cancel ) {
				$is_mandate_cancel = WC_Checkoutcom_Api_Request::mandate_cancel_request( $mandate_cancel, $subscription->get_id() );

				if ( $is_mandate_cancel ) {
					$subscription->add_order_note( 'Checkout.com mandate cancelled.', false );
				} else {
					$subscription->add_order_note( 'Checkout.com mandate already cancel or failed.', false );
				}
			}
		}

	}

	/**
	 * Save mandate cancel URL to order meta.
	 *
	 * @param int    $order_id Order ID.
	 * @param object $order Order object.
	 * @param string $url Mandate cancel URL.
	 *
	 * @return void
	 */
	public static function save_mandate_cancel( $order_id, $order, $url ) {

		if ( $order instanceof WC_Subscription ) {
			update_post_meta( $order->get_id(), '_cko_mandate_cancel', $url );
		}

		// check for subscription and save source id.
		if ( function_exists( 'wcs_order_contains_subscription' ) ) {
			if ( wcs_order_contains_subscription( $order_id ) ) {
				$subscriptions = wcs_get_subscriptions_for_order( $order );

				foreach ( $subscriptions as $subscription_obj ) {
					update_post_meta( $subscription_obj->get_id(), '_cko_mandate_cancel', $url );
				}
			}
		}
	}
}
