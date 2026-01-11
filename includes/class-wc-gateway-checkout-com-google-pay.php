<?php
/**
 * Google Pay method class.
 *
 * @package wc_checkout_com
 */

defined( 'ABSPATH' ) || exit;

require_once 'express/google-pay/class-google-pay-express.php';

use Checkout\CheckoutApiException;
use Checkout\Payments\PaymentType;

/**
 * Class WC_Gateway_Checkout_Com_Google_Pay for Google Pay payment method.
 */
#[AllowDynamicProperties]
class WC_Gateway_Checkout_Com_Google_Pay extends WC_Payment_Gateway {

	/**
	 * WC_Gateway_Checkout_Com_Google_Pay constructor.
	 */
	public function __construct() {
		$this->id                 = 'wc_checkout_com_google_pay';
		$this->method_title       = __( 'Checkout.com', 'checkout-com-unified-payments-api' );
		$this->method_description = __( 'The Checkout.com extension allows shop owners to process online payments through the <a href="https://www.checkout.com">Checkout.com Payment Gateway.</a>', 'checkout-com-unified-payments-api' );
		$this->title              = __( 'Google Pay', 'checkout-com-unified-payments-api' );
		$this->has_fields         = true;
		$this->supports           = [
			'products',
			'refunds',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_date_changes',
		];

		$this->init_form_fields();
		$this->init_settings();

		// Turn these settings into variables we can use.
		foreach ( $this->settings as $setting_key => $value ) {
			$this->$setting_key = $value;
		}

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );

		// Payment scripts.
		add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );

		add_action( 'woocommerce_api_' . strtolower( 'CKO_Google_Pay_Woocommerce' ), [ $this, 'handle_wc_api' ] );
	}

	/**
	 * Handle Google Pay method API requests.
	 *
	 * @return void
	 */
	public function handle_wc_api() {
		// Log that API endpoint was hit
		WC_Checkoutcom_Utility::logger( 'Google Pay: handle_wc_api called' );
		WC_Checkoutcom_Utility::logger( 'Google Pay: GET params: ' . print_r( $_GET, true ) );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		if ( ! empty( $_GET['cko_google_pay_action'] ) ) {
			WC_Checkoutcom_Utility::logger( 'Google Pay: Action requested: ' . $_GET['cko_google_pay_action'] );
			
			switch ( $_GET['cko_google_pay_action'] ) {

				case 'express_add_to_cart':
					$this->cko_express_add_to_cart();
					break;

				case 'express_create_payment_context':
					$this->cko_express_create_payment_context();
					break;

				case 'express_get_cart_total':
					$this->cko_express_get_cart_total();
					break;

				case 'express_google_pay_order_session':
					WC_Checkoutcom_Utility::logger( 'Google Pay: Calling cko_express_google_pay_order_session' );
					WC_Checkoutcom_Utility::cko_set_session( 'cko_google_pay_order_id', isset( $_GET['google_pay_order_id'] ) ? wc_clean( $_GET['google_pay_order_id'] ) : '' );
					$this->cko_express_google_pay_order_session();
					break;
			}
		} else {
			WC_Checkoutcom_Utility::logger( 'Google Pay: No cko_google_pay_action in GET params' );
		}

		// phpcs:enable
		exit();
	}

	public function cko_express_add_to_cart() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'checkoutcom_google_pay_express_add_to_cart' ) ) {
			wp_send_json( [ 'result' => 'failed' ] );
		}

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		WC()->shipping->reset_shipping();

		$product_id   = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$qty          = ! isset( $_POST['qty'] ) ? 1 : absint( $_POST['qty'] );
		
		$product      = wc_get_product( $product_id );
		
		if ( ! $product ) {
			wp_send_json( [ 'result' => 'error', 'message' => 'Product not found' ] );
		}
		
		$product_type = $product->get_type();

		// First empty the cart to prevent wrong calculation.
		WC()->cart->empty_cart();

		if ( ( 'variable' === $product_type || 'variable-subscription' === $product_type ) && isset( $_POST['attributes'] ) ) {
			$attributes = wc_clean( wp_unslash( $_POST['attributes'] ) );

			$data_store   = WC_Data_Store::load( 'product' );
			$variation_id = $data_store->find_matching_product_variation( $product, $attributes );

			$is_added_to_cart = WC()->cart->add_to_cart( $product->get_id(), $qty, $variation_id, $attributes );
		}

		if ( in_array( $product_type, [ 'simple', 'variation', 'subscription', 'subscription_variation' ], true ) ) {
			$is_added_to_cart = WC()->cart->add_to_cart( $product->get_id(), $qty );
		}

		WC()->cart->calculate_totals();

		$data           = [];
		$data['result'] = 'success';
		$data['total']  = WC()->cart->total;

		wp_send_json( $data );
	}

	public function cko_express_create_payment_context() {

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! empty( $_POST['express_checkout'] ) ) {
			// Check if add_to_cart was successful
			if ( ! empty( $_POST['add_to_cart'] ) && 'success' !== $_POST['add_to_cart'] ) {
				wp_send_json_error( array( 'messages' => 'Failed to add product to cart' ) );
				return;
			}
			
			// Check if using existing cart
			if ( ! empty( $_POST['use_existing_cart'] ) && 'true' === $_POST['use_existing_cart'] ) {
				// Cart should already be populated
			}
		}

		if ( WC()->cart->is_empty() ) {
			wp_send_json_error( array( 'messages' => 'Cart is empty' ) );
			return;
		}

		// Create payment context similar to PayPal
		$this->cko_create_payment_context_request( true );
	}

	public function cko_express_get_cart_total() {
		// Return cart total directly from WooCommerce
		// This works for both classic and Blocks cart pages
		
		// Ensure cart is loaded
		if ( ! WC()->cart ) {
			wp_send_json_error( array( 'messages' => 'Cart not initialized' ) );
			return;
		}
		
		if ( WC()->cart->is_empty() ) {
			wp_send_json_error( array( 'messages' => 'Cart is empty' ) );
			return;
		}

		// Recalculate totals to ensure we have the latest
		WC()->cart->calculate_totals();

		// Get cart total - use 'raw' to get the numeric value directly
		$cart_total_raw = WC()->cart->get_total( 'raw' );
		
		// Also get formatted total for display
		$cart_total_formatted = WC()->cart->get_total( '' );

		// Ensure we have a valid total
		if ( $cart_total_raw <= 0 ) {
			wp_send_json_error( array( 'messages' => 'Invalid cart total' ) );
			return;
		}

		wp_send_json_success( array(
			'total' => floatval( $cart_total_raw ),
			'total_formatted' => $cart_total_formatted,
			'currency' => get_woocommerce_currency(),
		) );
	}

	public function cko_express_google_pay_order_session() {
		// Express now uses the exact same flow as classic Google Pay
		// Classic Google Pay sends: cko-google-signature, cko-google-protocolVersion, cko-google-signedMessage
		// Express now sends the same fields, so we can use the same process_payment method
		$has_classic_token_fields = isset( $_POST['cko-google-signature'] ) && isset( $_POST['cko-google-protocolVersion'] ) && isset( $_POST['cko-google-signedMessage'] );
		
		if ( $has_classic_token_fields ) {
			// Get payment data for email and address extraction
			$payment_data_json = isset( $_POST['payment_data'] ) ? wp_unslash( $_POST['payment_data'] ) : '';
			$payment_data = ! empty( $payment_data_json ) ? json_decode( $payment_data_json, true ) : array();
			
			// Extract email and address from payment data
			$email = '';
			$shipping_address = null;
			if ( ! empty( $payment_data ) ) {
				if ( isset( $payment_data['email'] ) ) {
					$email = sanitize_email( $payment_data['email'] );
				}
				if ( isset( $payment_data['shippingAddress'] ) ) {
					$shipping_address = $payment_data['shippingAddress'];
				}
			}
			
			// Create the order from cart
			$order = $this->create_express_order_from_cart( $email, $shipping_address );
			
			if ( ! $order ) {
				wp_send_json_error( array( 'messages' => 'Failed to create order for Google Pay Express checkout.' ) );
				return;
			}
			
			// process_payment expects the payment method to be set in $_POST
			$_POST['payment_method'] = 'wc_checkout_com_google_pay';
			
			// Use the same process_payment method as classic Google Pay
			$result = $this->process_payment( $order->get_id() );
			
			// process_payment returns an array with 'result' and 'redirect'
			if ( isset( $result['result'] ) && 'success' === $result['result'] ) {
				// Payment successful - return success
				wp_send_json_success(
					array(
						'result'      => 'success',
						'redirect_url' => isset( $result['redirect'] ) ? $result['redirect'] : $this->get_return_url( $order ),
					)
				);
			} else {
				// Payment failed
				$error_message = isset( $result['messages'] ) ? $result['messages'] : 'Payment failed.';
				wp_send_json_error( array( 'messages' => $error_message ) );
			}
			
			return; // Exit early since we've handled the payment
			
			// Convert response to array if it's an object
			if ( is_object( $payment_result ) ) {
				$payment_result = json_decode( json_encode( $payment_result ), true );
			} else {
				$payment_result = (array) $payment_result;
			}

			// Debug: Log the payment response
			WC_Checkoutcom_Utility::logger( 'Google Pay Express: Payment response received. Has error: ' . ( isset( $payment_result['error'] ) ? 'yes' : 'no' ) );
			WC_Checkoutcom_Utility::logger( 'Google Pay Express: Payment response type: ' . gettype( $payment_result ) );
			WC_Checkoutcom_Utility::logger( 'Google Pay Express: Payment response structure: ' . print_r( $payment_result, true ) );

			// Clear Google Pay session
			WC_Checkoutcom_Utility::cko_set_session( 'cko_google_pay_order_id', '' );
			WC_Checkoutcom_Utility::cko_set_session( 'cko_gc_id', '' );

			// Check if payment was successful
			if ( isset( $payment_result['error'] ) && ! empty( $payment_result['error'] ) ) {
				// Payment failed
				$order->update_status( 'failed', 'Google Pay Express payment failed.' );
				WC_Checkoutcom_Utility::logger( 'Google Pay Express: Payment failed with error: ' . $payment_result['error'] );
				wp_send_json_error( array( 'messages' => $payment_result['error'] ) );
				return;
			}

			// Extract action_id from response (might be directly in response or in actions array)
			$action_id = null;
			if ( isset( $payment_result['action_id'] ) ) {
				$action_id = $payment_result['action_id'];
			} elseif ( isset( $payment_result['actions'] ) && is_array( $payment_result['actions'] ) && ! empty( $payment_result['actions'][0]['id'] ) ) {
				$action_id = $payment_result['actions'][0]['id'];
			}
			
			// Extract payment ID
			$payment_id = isset( $payment_result['id'] ) ? $payment_result['id'] : null;

			// Update order with payment details
			$order->set_transaction_id( $payment_id );
			$order->update_meta_data( '_cko_payment_id', $payment_id );
			$order->update_meta_data( '_cko_action_id', $action_id );
			$order->update_meta_data( '_cko_payment_method', 'googlepay' );
			$order->save();

			// Handle subscriptions
			if ( WC_Checkoutcom_Utility::is_subscription( $order_id ) ) {
				WC_Checkoutcom_Utility::update_subscription_payment_method( $order, $payment_id );
			}

			// Reduce stock levels
			$order->reduce_order_stock();

			// Remove cart
			WC()->cart->empty_cart();

			// Return success and redirect to order received page
			wp_send_json_success(
				array(
					'result'      => 'success',
					'redirect_url' => $this->get_return_url( $order ),
				)
			);
			
			return; // Exit early since we've handled the payment
		} else {
			// Fallback to old approach (for backwards compatibility)
			$payment_data_json = isset( $_POST['payment_data'] ) ? wp_unslash( $_POST['payment_data'] ) : '';
			
			if ( empty( $payment_data_json ) ) {
				wp_send_json_error( array( 'messages' => 'Missing payment data for Google Pay Express checkout.' ) );
				return;
			}
			
			$payment_data = json_decode( $payment_data_json, true );
		}
		
		if ( ! $payment_data ) {
			wp_send_json_error( array( 'messages' => 'Invalid payment data for Google Pay Express checkout.' ) );
			return;
		}

		try {
			// Extract email and address from Google Pay payment data
			$email = '';
			$shipping_address = null;
			
			// Extract email from payment data
			if ( isset( $payment_data['email'] ) ) {
				$email = sanitize_email( $payment_data['email'] );
			}
			
			// Extract shipping address from payment data
			if ( isset( $payment_data['shippingAddress'] ) ) {
				$shipping_address = $payment_data['shippingAddress'];
			}
			
			// Store payment data in session for address extraction
			WC_Checkoutcom_Utility::cko_set_session( 'cko_google_pay_payment_data', $payment_data );
			
			// Create the order from cart
			$order = $this->create_express_order_from_cart( $email, $shipping_address );
			
			if ( ! $order ) {
				wp_send_json_error( array( 'messages' => 'Failed to create order for Google Pay Express checkout.' ) );
				return;
			}

			// Extract Google Pay token data - handle both API v1 and v2 structures
			$tokenization_data = null;
			$token_data = null;
			
			// Check if we have extracted token fields (new approach matching classic)
			if ( $has_extracted_token_fields ) {
				// Use extracted token fields directly (same format as classic Google Pay)
				$token_data = array(
					'signature'       => wp_unslash( $_POST['token_signature'] ),
					'protocolVersion' => sanitize_text_field( $_POST['token_protocolVersion'] ),
					'signedMessage'  => wp_unslash( $_POST['token_signedMessage'] ),
				);
			}
			// API v1 structure: paymentMethodToken.token (string that needs JSON.parse)
			// Fallback: parse from payment_data (old approach)
			elseif ( isset( $payment_data['paymentMethodToken'] ) && isset( $payment_data['paymentMethodToken']['token'] ) ) {
				$tokenization_data = $payment_data['paymentMethodToken']['token'];
				
				if ( is_array( $tokenization_data ) ) {
					$token_data = $tokenization_data;
				} else {
					$token_data = json_decode( $tokenization_data, true );
					
					if ( json_last_error() !== JSON_ERROR_NONE ) {
						wp_send_json_error( array( 'messages' => 'Invalid Google Pay token data: ' . json_last_error_msg() ) );
						return;
					}
				}
			}
			// API v2 structure: paymentMethodData.tokenizationData.token (fallback)
			elseif ( isset( $payment_data['paymentMethodData'] ) && isset( $payment_data['paymentMethodData']['tokenizationData'] ) && isset( $payment_data['paymentMethodData']['tokenizationData']['token'] ) ) {
				$tokenization_data = $payment_data['paymentMethodData']['tokenizationData']['token'];
				$token_data = json_decode( $tokenization_data, true );
			}
			
			// Check if we have token data
			if ( ! $token_data || ! isset( $token_data['signature'] ) || ! isset( $token_data['signedMessage'] ) ) {
				wp_send_json_error( array( 'messages' => 'Invalid Google Pay payment data structure.' ) );
				return;
			}

			// Get protocol version
			$protocol_version = isset( $token_data['protocolVersion'] ) ? $token_data['protocolVersion'] : 'ECv2';
			
			// Generate Google Pay token using Checkout.com wallet token API
			$_POST['cko-google-protocolVersion'] = $protocol_version;
			$_POST['cko-google-signature'] = $token_data['signature'];
			
			// signedMessage is a JSON string that contains encryptedMessage for ECv1 or base64 string for ECv2
			$signed_message = $token_data['signedMessage'];
			
			// If signedMessage is an array/object (shouldn't happen but just in case), encode it
			if ( is_array( $signed_message ) || is_object( $signed_message ) ) {
				$signed_message = json_encode( $signed_message );
			}
			
			$_POST['cko-google-signedMessage'] = (string) $signed_message;

			$token_response = WC_Checkoutcom_Api_Request::generate_google_token();

			// If wallet token API fails for ECv1, we'll need to use network_token approach
			if ( isset( $token_response['error'] ) || ! isset( $token_response['token'] ) ) {
				if ( 'ECv1' === $protocol_version ) {
					$error_message = isset( $token_response['error'] ) ? $token_response['error'] : 'Google Pay returned ECv1 protocol which requires network_token approach. Please contact support or try again.';
					wp_send_json_error( array( 
						'messages' => 'Google Pay returned ECv1 protocol. Please try again or use a different payment method.' 
					) );
					return;
				} else {
					$error_message = isset( $token_response['error'] ) ? $token_response['error'] : 'Failed to generate Google Pay token.';
					wp_send_json_error( array( 'messages' => $error_message ) );
					return;
				}
			}

			// Process payment directly with the token (same as normal Google Pay)
			$payment_result = ( new WC_Checkoutcom_Api_Request() )->create_payment( $order, $token_response );
			
			// Convert response to array if it's an object
			if ( is_object( $payment_result ) ) {
				$payment_result = json_decode( json_encode( $payment_result ), true );
			} else {
				$payment_result = (array) $payment_result;
			}

			// Clear Google Pay session
			WC_Checkoutcom_Utility::cko_set_session( 'cko_google_pay_order_id', '' );
			WC_Checkoutcom_Utility::cko_set_session( 'cko_gc_id', '' );

			// Check if payment was successful
			if ( isset( $payment_result['error'] ) && ! empty( $payment_result['error'] ) ) {
				// Payment failed
				$order->update_status( 'failed', 'Google Pay Express payment failed.' );
				WC_Checkoutcom_Utility::logger( 'Google Pay Express: Payment failed with error: ' . $payment_result['error'] );
				wp_send_json_error( array( 'messages' => $payment_result['error'] ) );
				return;
			}

			// Extract action_id from response (might be directly in response or in actions array)
			$action_id = null;
			if ( isset( $payment_result['action_id'] ) ) {
				$action_id = $payment_result['action_id'];
			} elseif ( isset( $payment_result['actions'] ) && is_array( $payment_result['actions'] ) && ! empty( $payment_result['actions'][0]['id'] ) ) {
				$action_id = $payment_result['actions'][0]['id'];
			}

			// Extract payment ID
			$payment_id = isset( $payment_result['id'] ) ? $payment_result['id'] : null;

			// Check if payment result is valid (must have at least id or action_id)
			if ( empty( $payment_id ) && empty( $action_id ) ) {
				$error_message = 'Invalid payment response: missing payment ID';
				WC_Checkoutcom_Utility::logger( 'Google Pay Express: ' . $error_message, new Exception( print_r( $payment_result, true ) ) );
				$order->update_status( 'failed', 'Google Pay Express payment failed: Invalid response.' );
				wp_send_json_error( array( 'messages' => $error_message ) );
				return;
			}

			// Check for 3DS redirect (same as normal Google Pay)
			if ( isset( $payment_result['3d'] ) && ! empty( $payment_result['3d'] ) ) {
				$order->add_order_note(
					sprintf(
						/* translators: %s: URL */
						esc_html__( 'Checkout.com 3d Redirect waiting. URL : %s', 'checkout-com-unified-payments-api' ),
						$payment_result['3d']
					)
				);
				
				wp_send_json_success( array( 'redirect_url' => $payment_result['3d'] ) );
				return;
			}

			// Handle subscriptions (same as normal Google Pay)
			if ( class_exists( 'WC_Subscriptions_Order' ) && isset( $payment_result['source']['id'] ) ) {
				// Save source id for subscription.
				WC_Checkoutcom_Subscription::save_source_id( $order->get_id(), $order, $payment_result['source']['id'] );
			}

			// Set action id as woo transaction id (same as normal Google Pay)
			if ( ! empty( $action_id ) ) {
				$order->set_transaction_id( $action_id );
			}
			if ( ! empty( $payment_id ) ) {
				$order->update_meta_data( '_cko_payment_id', $payment_id );
			}

			// Get cko auth status configured in admin (same as normal Google Pay)
			$status = WC_Admin_Settings::get_option( 'ckocom_order_authorised', 'on-hold' );

			// Get action ID for order notes
			$action_id_for_notes = ! empty( $action_id ) ? $action_id : ( ! empty( $payment_id ) ? $payment_id : 'N/A' );

			/* translators: %s: Action ID. */
			$message = sprintf( esc_html__( 'Checkout.com Payment Authorised - Action ID : %s', 'checkout-com-unified-payments-api' ), $action_id_for_notes );

			// check if payment was flagged (same as normal Google Pay)
			if ( isset( $payment_result['risk']['flagged'] ) && $payment_result['risk']['flagged'] ) {
				// Get cko auth status configured in admin.
				$status = WC_Admin_Settings::get_option( 'ckocom_order_flagged', 'flagged' );

				/* translators: %s: Action ID. */
				$message = sprintf( esc_html__( 'Checkout.com Payment Flagged - Action ID : %s', 'checkout-com-unified-payments-api' ), $action_id_for_notes );
			}

			// add notes for the order and update status (same as normal Google Pay)
			$order->add_order_note( $message );
			$order->update_status( $status );

			// Reduce stock levels (same as normal Google Pay)
			wc_reduce_stock_levels( $order->get_id() );

			// Remove cart (same as normal Google Pay)
			WC()->cart->empty_cart();

			// Payment successful - redirect to thank you page
			$redirect_url = $this->get_return_url( $order );
			
			wp_send_json_success( array( 'redirect_url' => $redirect_url ) );

			} catch ( CheckoutApiException $ex ) {
				$gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) === 'yes';
				$error_message = 'An error occurred while processing Google Pay Express payment. ';
				
				if ( $gateway_debug ) {
					$error_message .= $ex->getMessage();
				}

				WC_Checkoutcom_Utility::logger( $error_message, $ex );
				wp_send_json_error( array( 'messages' => $error_message ) );
			} catch ( Exception $ex ) {
				WC_Checkoutcom_Utility::logger( 'Google Pay Express: Unexpected error', $ex );
				wp_send_json_error( array( 'messages' => 'An unexpected error occurred. Please check logs.' ) );
			}
	}

	/**
	 * Create payment context request for express checkout.
	 *
	 * @param bool $is_express Whether this is express checkout.
	 * @return void
	 */
	private function cko_create_payment_context_request( $is_express = false ) {
		// Set proper headers for JSON response
		header( 'Content-Type: application/json' );
		
		// Ensure no output before JSON response
		if ( ob_get_level() ) {
			ob_clean();
		}

		// Google Pay Express does not use payment contexts - it processes payments directly with tokens
		// This method is kept for compatibility but just returns success without creating a context
		// The actual payment processing happens in cko_express_google_pay_order_session with the token
		wp_send_json( [ 'success' => true, 'message' => 'Google Pay Express does not use payment contexts' ], 200 );
		exit();
	}

	/**
	 * Create express order from cart.
	 *
	 * @param string $email Email address from Google Pay.
	 * @param array  $shipping_address Shipping address from Google Pay.
	 * @return WC_Order|false
	 */
	private function create_express_order_from_cart( $email = '', $shipping_address = null ) {
		try {
			// Determine customer ID and email
			// IMPORTANT: For Google Pay Express:
			// - If user is logged in: Use logged-in user's email, but address from Google Pay
			// - If user is not logged in: Use email and address from Google Pay only
			$customer_id = 0;
			$customer_email = '';
			
			// Check if user is logged in
			if ( is_user_logged_in() ) {
				$current_user = wp_get_current_user();
				$customer_id = $current_user->ID;
				// For logged-in users, use their account email (not Google Pay email)
				$customer_email = $current_user->user_email;
			} else {
				// For guest users, use email from Google Pay data only
				if ( ! empty( $email ) ) {
					$customer_email = $email;
				}
			}

			// Create order with proper customer ID
			$order = wc_create_order( array( 'customer_id' => $customer_id ) );
			
			if ( ! $order ) {
				return false;
			}

			// Set customer email
			if ( ! empty( $customer_email ) ) {
				$order->set_billing_email( $customer_email );
			}

			// Add cart items to order
			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
				$product = $cart_item['data'];
				$order->add_product( $product, $cart_item['quantity'] );
			}

			// Set order addresses from Google Pay payment data if available
			// IMPORTANT: For Google Pay Express:
			// - If logged in: Use logged-in user's email (already set above), but address from Google Pay
			// - If guest: Use Google Pay email and address
			if ( ! empty( $shipping_address ) ) {
				// For logged-in users: Don't pass email to set_order_addresses_from_google_pay_data
				// because we want to keep the logged-in user's email (already set above)
				// For guest users: Pass Google Pay email so it gets set in the address
				$email_for_address = is_user_logged_in() ? '' : $customer_email;
				$this->set_order_addresses_from_google_pay_data( $order, $shipping_address, $email_for_address );
			}

			// Set shipping method if available
			$shipping_packages = WC()->shipping->get_packages();
			if ( ! empty( $shipping_packages ) ) {
				foreach ( $shipping_packages as $package ) {
					$chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
					if ( ! empty( $chosen_methods ) ) {
						$order->set_shipping_method( $chosen_methods[0] );
					}
				}
			}

			// Calculate totals
			$order->calculate_totals();

			// Set payment method to Google Pay Express
			$order->set_payment_method( 'wc_checkout_com_google_pay' );
			$order->set_payment_method_title( 'Google Pay Express' );

			// Set order status
			$order->set_status( 'pending' );
			$order->save();

			// Store order ID in session for potential use
			WC()->session->set( 'order_awaiting_payment', $order->get_id() );

			return $order;

		} catch ( Exception $e ) {
			WC_Checkoutcom_Utility::logger( 'Error creating Google Pay Express order: ' . $e->getMessage(), $e );
			return false;
		}
	}

	/**
	 * Get Google Pay email from payment context.
	 *
	 * @return string
	 */
	private function get_google_pay_email_from_context() {
		$cko_gc_details = WC_Checkoutcom_Utility::cko_get_session( 'cko_gc_details' );
		
		if ( empty( $cko_gc_details ) ) {
			$cko_gc_id = WC_Checkoutcom_Utility::cko_get_session( 'cko_gc_id' );
			if ( ! empty( $cko_gc_id ) ) {
				try {
					$checkout = new Checkout_SDK();
					$cko_gc_details = $checkout->get_builder()->getPaymentContextsClient()->getPaymentContextDetails( $cko_gc_id );
					WC_Checkoutcom_Utility::cko_set_session( 'cko_gc_details', $cko_gc_details );
				} catch ( Exception $e ) {
					return '';
				}
			}
		}

		if ( ! empty( $cko_gc_details ) ) {
			// Check multiple possible locations for email
			if ( isset( $cko_gc_details['payment_request']['source']['account_holder']['email'] ) ) {
				return $cko_gc_details['payment_request']['source']['account_holder']['email'];
			}
			if ( isset( $cko_gc_details['payment_request']['shipping']['email'] ) ) {
				return $cko_gc_details['payment_request']['shipping']['email'];
			}
			if ( isset( $cko_gc_details['payment_request']['billing']['email'] ) ) {
				return $cko_gc_details['payment_request']['billing']['email'];
			}
			if ( isset( $cko_gc_details['payment_request']['payer']['email'] ) ) {
				return $cko_gc_details['payment_request']['payer']['email'];
			}
			if ( isset( $cko_gc_details['payment_request']['customer']['email'] ) ) {
				return $cko_gc_details['payment_request']['customer']['email'];
			}
		}

		return '';
	}

	/**
	 * Set order addresses from Google Pay payment data (for express checkout).
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $shipping_address Shipping address from Google Pay.
	 * @param string   $email Email address from Google Pay.
	 */
	private function set_order_addresses_from_google_pay_data( $order, $shipping_address, $email = '' ) {
		if ( empty( $shipping_address ) ) {
			return;
		}

		// Extract name from Google Pay address
		$name_parts = array();
		if ( isset( $shipping_address['name'] ) ) {
			$name_parts = explode( ' ', $shipping_address['name'], 2 );
		}
		$first_name = $name_parts[0] ?? '';
		$last_name = $name_parts[1] ?? '';

		// Map Google Pay address fields to WooCommerce fields
		$address_line1 = $shipping_address['address1'] ?? '';
		$address_line2 = $shipping_address['address2'] ?? $shipping_address['address3'] ?? '';
		$city = $shipping_address['locality'] ?? $shipping_address['city'] ?? '';
		$state = $shipping_address['administrativeArea'] ?? $shipping_address['state'] ?? '';
		$postcode = $shipping_address['postalCode'] ?? $shipping_address['postal_code'] ?? '';
		$country = $shipping_address['countryCode'] ?? $shipping_address['country'] ?? '';

		// Set billing address (always set all fields to ensure address is saved)
		$order->set_billing_first_name( $first_name );
		$order->set_billing_last_name( $last_name );
		$order->set_billing_address_1( $address_line1 );
		$order->set_billing_address_2( $address_line2 );
		$order->set_billing_city( $city );
		$order->set_billing_state( $state );
		$order->set_billing_postcode( $postcode );
		$order->set_billing_country( $country );
		// Only set billing email if provided and not already set
		// For Google Pay Express, logged-in users already have their email set
		// so we don't want to overwrite it with Google Pay email
		if ( ! empty( $email ) && empty( $order->get_billing_email() ) ) {
			$order->set_billing_email( $email );
		}
		if ( isset( $shipping_address['phoneNumber'] ) && ! empty( $shipping_address['phoneNumber'] ) ) {
			$order->set_billing_phone( $shipping_address['phoneNumber'] );
		}

		// Set shipping address (same as billing for express checkout)
		// Always set shipping address fields, even if some are empty, to ensure WooCommerce shows shipping address
		$order->set_shipping_first_name( $first_name );
		$order->set_shipping_last_name( $last_name );
		$order->set_shipping_address_1( $address_line1 );
		$order->set_shipping_address_2( $address_line2 );
		$order->set_shipping_city( $city );
		$order->set_shipping_state( $state );
		$order->set_shipping_postcode( $postcode );
		$order->set_shipping_country( $country );
		
		// Save the order to ensure shipping address is persisted
		$order->save();
	}

	/**
	 * Set order addresses from Google Pay payment context data (for payment contexts - not used in express).
	 *
	 * @param WC_Order $order
	 */
	private function set_order_addresses_from_google_pay( $order ) {
		$cko_gc_details = WC_Checkoutcom_Utility::cko_get_session( 'cko_gc_details' );
		
		if ( empty( $cko_gc_details ) ) {
			// Try to get payment context details
			$cko_gc_id = WC_Checkoutcom_Utility::cko_get_session( 'cko_gc_id' );
			if ( ! empty( $cko_gc_id ) ) {
				try {
					$checkout = new Checkout_SDK();
					$cko_gc_details = $checkout->get_builder()->getPaymentContextsClient()->getPaymentContextDetails( $cko_gc_id );
					WC_Checkoutcom_Utility::cko_set_session( 'cko_gc_details', $cko_gc_details );
				} catch ( Exception $e ) {
					// If we can't get details, use default addresses
					return;
				}
			}
		}

		if ( ! empty( $cko_gc_details ) && isset( $cko_gc_details['payment_request']['shipping']['address'] ) ) {
			$shipping_address = $cko_gc_details['payment_request']['shipping']['address'];
			$shipping_name = isset( $cko_gc_details['payment_request']['shipping']['first_name'] ) 
				? explode( ' ', $cko_gc_details['payment_request']['shipping']['first_name'] ) 
				: array();
			$first_name = $shipping_name[0] ?? '';
			$last_name = $shipping_name[1] ?? '';

			// Set billing address
			$order->set_billing_first_name( $first_name );
			$order->set_billing_last_name( $last_name );
			$order->set_billing_address_1( $shipping_address['address_line1'] ?? '' );
			$order->set_billing_address_2( $shipping_address['address_line2'] ?? '' );
			$order->set_billing_city( $shipping_address['city'] ?? '' );
			$order->set_billing_postcode( $shipping_address['zip'] ?? '' );
			$order->set_billing_country( $shipping_address['country'] ?? '' );

			// Set shipping address
			$order->set_shipping_first_name( $first_name );
			$order->set_shipping_last_name( $last_name );
			$order->set_shipping_address_1( $shipping_address['address_line1'] ?? '' );
			$order->set_shipping_address_2( $shipping_address['address_line2'] ?? '' );
			$order->set_shipping_city( $shipping_address['city'] ?? '' );
			$order->set_shipping_postcode( $shipping_address['zip'] ?? '' );
			$order->set_shipping_country( $shipping_address['country'] ?? '' );
		}
	}

	/**
	 * Request payment with payment context.
	 *
	 * @param WC_Order $order Order object.
	 * @param string   $payment_context_id Payment context ID.
	 * @param string   $processing_channel_id Processing channel ID.
	 * @return array
	 */
	private function request_payment( $order, $payment_context_id, $processing_channel_id ) {
		try {
			$checkout = new Checkout_SDK();

			$amount       = $order->get_total();
			$amount_cents = WC_Checkoutcom_Utility::value_to_decimal( $amount, $order->get_currency() );

			$payment_request_param                        = $checkout->get_payment_request();
			$payment_request_param->payment_context_id    = $payment_context_id;
			$payment_request_param->processing_channel_id = $processing_channel_id;
			$payment_request_param->reference             = $order->get_order_number();
			$payment_request_param->amount                = $amount_cents;

			$items             = new Checkout\Payments\Contexts\PaymentContextsItems();
			$items->name       = 'All Products';
			$items->unit_price = $amount_cents;
			$items->quantity   = '1';

			$payment_request_param->items = [ $items ];

			$response      = $checkout->get_builder()->getPaymentsClient()->requestPayment( $payment_request_param );
			$response_code = $response['http_metadata']->getStatusCode();

			if ( ! WC_Checkoutcom_Utility::is_successful( $response ) || 'Declined' === $response['status'] ) {
				$order->update_meta_data( '_cko_payment_id', $response['id'] );
				$order->save();

				$error_message = esc_html__( 'An error has occurred while Google Pay payment request. ', 'checkout-com-unified-payments-api' );

				wp_send_json_error( [ 'messages' => $error_message ] );
			}

			/**
			 * Full Success = 201
			 * Partial Success = 202
			 *
			 * Ref : https://api-reference.checkout.com/#operation/requestAPaymentOrPayout!path=1/payment_context_id&t=request
			 */
			if ( in_array( $response_code, [ 201, 202 ], true ) ) {

				$order->set_transaction_id( $response['id'] );
				$order->update_meta_data( '_cko_payment_id', $response['id'] );

				if ( class_exists( 'WC_Subscriptions_Order' ) && isset( $response['source'] ) ) {
					// Save source id for subscription.
					WC_Checkoutcom_Subscription::save_source_id( $order->get_id(), $order, $response['source']['id'] );
				}

				// Get cko auth status configured in admin.
				$status = WC_Admin_Settings::get_option( 'ckocom_order_authorised', 'on-hold' );

				$order->update_meta_data( 'cko_payment_authorized', true );
				$order->update_status( $status );

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

			$error_message = esc_html__( 'An error has occurred while Google Pay payment request. ', 'checkout-com-unified-payments-api' );

			// Check if gateway response is enabled from module settings.
			if ( $gateway_debug ) {
				$error_message .= $ex->getMessage();
			}

			WC_Checkoutcom_Utility::logger( $error_message, $ex );

			wp_send_json_error( [ 'messages' => $error_message ] );
		}
	}

	/**
	 * Outputs scripts used for checkout payment.
	 */
	public function payment_scripts() {
		// Load on Cart, Checkout, pay for order or add payment method pages.
		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) && ! is_add_payment_method_page() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		// Load cko google pay setting.
		$google_settings    = get_option( 'woocommerce_wc_checkout_com_google_pay_settings' );
		$google_pay_enabled = ! empty( $google_settings['enabled'] ) && 'yes' === $google_settings['enabled'];

		wp_register_script( 'cko-google-script', 'https://pay.google.com/gp/p/js/pay.js', [ 'jquery' ] );
		wp_register_script( 'cko-google-pay-integration-script', WC_CHECKOUTCOM_PLUGIN_URL . '/assets/js/cko-google-pay-integration.js', [ 'jquery', 'cko-google-script' ], WC_CHECKOUTCOM_PLUGIN_VERSION );

		// Enqueue google pay script.
		if ( $google_pay_enabled ) {

			$core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings' );
			$environment   = 'sandbox' === $core_settings['ckocom_environment'];
			$currency_code = get_woocommerce_currency();
			$total_price   = WC()->cart->total;

			// Logic for order-pay page.
			// If on order-pay page, try fetching order total from the last order.
			if ( is_wc_endpoint_url( 'order-pay' ) ) {

				global $wp;

				// Get order ID from URL if available.
				$order_id = absint( $wp->query_vars['order-pay'] );

				if ( ! $order_id && isset( $_GET['key'] ) ) {
					$pay_order = wc_get_order( wc_get_order_id_by_order_key( sanitize_text_field( $_GET['key'] ) ) );
				} else {
					$pay_order = wc_get_order( $order_id );
				}

				if ( $pay_order ) {
					$total_price = $pay_order->get_total();
				}
			}

			$vars = [
				'environment'   => $environment ? 'TEST' : 'PRODUCTION',
				'public_key'    => $core_settings['ckocom_pk'],
				'merchant_id'   => $this->get_option( 'ckocom_google_merchant_id' ),
				'currency_code' => $currency_code,
				'total_price'   => $total_price,
				'button_type'   => $this->get_option( 'ckocom_google_style', 'google-pay-black' ),
			];

			wp_localize_script( 'cko-google-pay-integration-script', 'cko_google_pay_vars', $vars );

			wp_enqueue_script( 'cko-google-pay-integration-script' );
		}
	}

	/**
	 * Show module configuration in backend.
	 *
	 * @return string|void
	 */
	public function init_form_fields() {
		$this->form_fields = WC_Checkoutcom_Cards_Settings::google_settings();
		$this->form_fields = array_merge(
			$this->form_fields,
			[
				'screen_button' => [
					'id'    => 'screen_button',
					'type'  => 'screen_button',
					'title' => __( 'Other Settings', 'checkout-com-unified-payments-api' ),
				],
			]
		);
	}

	/**
	 * Generate links for the admin page.
	 *
	 * @param string $key The key.
	 * @param array  $value The value.
	 */
	public function generate_screen_button_html( $key, $value ) {
		WC_Checkoutcom_Admin::generate_links( $key, $value );
	}

	/**
	 * Show frames js on checkout page.
	 */
	public function payment_fields() {

		if ( ! empty( $this->get_option( 'description' ) ) ) {
			echo esc_html( $this->get_option( 'description' ) );
		}

		?>
		<input type="hidden" id="cko-google-signature" name="cko-google-signature" value="" />
		<input type="hidden" id="cko-google-protocolVersion" name="cko-google-protocolVersion" value="" />
		<input type="hidden" id="cko-google-signedMessage" name="cko-google-signedMessage" value="" />
		<?php
	}

	/**
	 * Process payment with Google Pay.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {
		if ( ! session_id() ) {
			session_start();
		}

		$order = new WC_Order( $order_id );

		// Create google token from Google payment data
		$google_token = WC_Checkoutcom_Api_Request::generate_google_token();

		// Check if google token is not empty.
		if ( empty( $google_token['token'] ) ) {
			WC_Checkoutcom_Utility::wc_add_notice_self( __( 'There was an issue completing the payment.', 'checkout-com-unified-payments-api' ), 'error' );
			return;
		}

		// Create payment with Google token.
		$result = (array) ( new WC_Checkoutcom_Api_Request() )->create_payment( $order, $google_token );

		// Redirect to apm if redirection url is available.
		if ( isset( $result['3d'] ) && ! empty( $result['3d'] ) ) {

			$order->add_order_note(
				sprintf(
					/* translators: %s: URL */
					esc_html__( 'Checkout.com 3d Redirect waiting. URL : %s', 'checkout-com-unified-payments-api' ),
					$result['3d']
				)
			);

			return [
				'result'   => 'success',
				'redirect' => $result['3d'],
			];
		}

		// check if result has error and return error message.
		if ( isset( $result['error'] ) && ! empty( $result['error'] ) ) {
			WC_Checkoutcom_Utility::wc_add_notice_self( $result['error'] );
			return;
		}

		if ( class_exists( 'WC_Subscriptions_Order' ) ) {
			// Save source id for subscription.
			WC_Checkoutcom_Subscription::save_source_id( $order_id, $order, $result['source']['id'] );
		}

		// Set action id as woo transaction id.
		$order->set_transaction_id( $result['action_id'] );
		$order->update_meta_data( '_cko_payment_id', $result['id'] );

		// Get cko auth status configured in admin.
		$status = WC_Admin_Settings::get_option( 'ckocom_order_authorised', 'on-hold' );

		/* translators: %s: Action ID. */
		$message = sprintf( esc_html__( 'Checkout.com Payment Authorised - Action ID : %s', 'checkout-com-unified-payments-api' ), $result['action_id'] );

		// check if payment was flagged.
		if ( $result['risk']['flagged'] ) {
			// Get cko auth status configured in admin.
			$status = WC_Admin_Settings::get_option( 'ckocom_order_flagged', 'flagged' );

			/* translators: %s: Action ID. */
			$message = sprintf( esc_html__( 'Checkout.com Payment Flagged - Action ID : %s', 'checkout-com-unified-payments-api' ), $result['action_id'] );
		}

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
	 * Handle Google Pay refund.
	 *
	 * @param int    $order_id Order ID.
	 * @param null   $amount  Amount to refund.
	 * @param string $reason Reason for refund.
	 *
	 * @return bool|WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order  = wc_get_order( $order_id );
		$result = (array) WC_Checkoutcom_Api_Request::refund_payment( $order_id, $order );

		// check if result has error and return error message.
		if ( ! empty( $result['error'] ) ) {
			WC_Checkoutcom_Utility::wc_add_notice_self( $result['error'] );
			return false;
		}

		// Set action id as woo transaction id.
		$order->set_transaction_id( $result['action_id'] );
		$order->save();

		// Get cko auth status configured in admin.
		$status = WC_Admin_Settings::get_option( 'ckocom_order_refunded', 'refunded' );

		/* translators: %s: Action ID. */
		$message = sprintf( esc_html__( 'Checkout.com Payment refunded - Action ID : %s', 'checkout-com-unified-payments-api' ), $result['action_id'] );

		if ( isset( $_SESSION['cko-refund-is-less'] ) ) {
			if ( $_SESSION['cko-refund-is-less'] ) {
				/* translators: %s: Action ID. */
				$order->add_order_note( sprintf( esc_html__( 'Checkout.com Payment Partially refunded - Action ID : %s', 'checkout-com-unified-payments-api' ), $result['action_id'] ) );

				unset( $_SESSION['cko-refund-is-less'] );

				return true;
			}
		}

		// Add notes for the order and update status.
		$order->add_order_note( $message );
		$order->update_status( $status );

		return true;
	}
}
