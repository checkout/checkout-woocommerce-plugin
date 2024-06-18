<?php
/**
 * PayPal's method class.
 *
 * @package wc_checkout_com
 */

defined( 'ABSPATH' ) || exit;

require_once 'express/paypal/class-paypal-express.php';

use Checkout\CheckoutApiException;
use Checkout\Payments\PaymentType;

/**
 * Class WC_Gateway_Checkout_Com_PayPal for PayPal payment method.
 */
class WC_Gateway_Checkout_Com_PayPal extends WC_Payment_Gateway {

	/**
	 * WC_Gateway_Checkout_Com_PayPal constructor.
	 */
	public function __construct() {
		$this->id                 = 'wc_checkout_com_paypal';
		$this->method_title       = __( 'Checkout.com', 'checkout-com-unified-payments-api' );
		$this->method_description = __( 'The Checkout.com extension allows shop owners to process online payments through the <a href="https://www.checkout.com">Checkout.com Payment Gateway.</a>', 'checkout-com-unified-payments-api' );
		$this->title              = __( 'PayPal', 'checkout-com-unified-payments-api' );
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

		// PayPal Payment scripts.
		add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );
		add_filter( 'script_loader_tag', [ $this, 'add_attributes_to_script' ], 10, 3 );

		add_action( 'woocommerce_api_' . strtolower( 'CKO_Paypal_Woocommerce' ), [ $this, 'handle_wc_api' ] );
	}

	/**
     * Handle paypal method API requests.
     *
	 * @return void
	 */
    public function handle_wc_api() {

	    if ( ! empty( $_GET['cko_paypal_action'] ) ) {
		    switch ( $_GET['cko_paypal_action'] ) {

                case "create_order":
				    if ( ! empty( $_POST ) ) {

                        WC()->checkout->process_checkout();

                        if ( wc_notice_count( 'error' ) > 0 ) {
						    WC()->session->set( 'reload_checkout', true );
						    $error_messages_data = wc_get_notices( 'error' );
						    $error_messages      = array();
						    foreach ( $error_messages_data as $key => $value ) {
							    $error_messages[] = $value['notice'];
						    }
						    wc_clear_notices();
						    ob_start();
						    wp_send_json_error( array( 'messages' => $error_messages ) );
						    exit;
					    }
					    exit();
				    }
                    break;

			    case "cc_capture":
				    WC_Checkoutcom_Utility::cko_set_session( 'cko_paypal_order_id', wc_clean( $_GET['paypal_order_id'] ) );
				    $this->cko_cc_capture();
				    break;

			    case "express_add_to_cart":
					$this->cko_express_add_to_cart();
					break;

			    case "express_create_order":
				    $this->cko_express_create_order();
					break;

			    case "express_paypal_order_session":
				    WC_Checkoutcom_Utility::cko_set_session( 'cko_paypal_order_id', wc_clean( $_GET['paypal_order_id'] ) );
					$this->cko_express_paypal_order_session();
					break;
            }
        }

	    exit();
    }

	public function cko_express_add_to_cart() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ 'nonce' ] ) ), 'checkoutcom_paypal_express_add_to_cart' ) ) {
			wp_send_json( [ 'result' => 'failed' ] );
		}

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		WC()->shipping->reset_shipping();

		$product_id   = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$qty          = ! isset( $_POST['qty'] ) ? 1 : absint( $_POST['qty'] );
		$product      = wc_get_product( $product_id );
		$product_type = $product->get_type();

		// First empty the cart to prevent wrong calculation.
		WC()->cart->empty_cart();

		// TODO: Add check for variable type product.
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
//		$data          += $this->build_display_items();
		$data['result'] = 'success';
		$data['total']  = WC()->cart->total;

		wp_send_json( $data );
	}

	public function cko_express_create_order() {

		if ( ! empty( $_POST['express_checkout'] ) && ! empty( $_POST[ 'add_to_cart' ] ) && 'success' !== $_POST[ 'add_to_cart' ] ) {
			return null;
		}

		if ( WC()->cart->is_empty() ) {
			return null;
		}

		$this->cko_create_order_request( true );
	}

	public function cko_express_paypal_order_session() {
		$cko_paypal_order_id = WC_Checkoutcom_Utility::cko_get_session( 'cko_paypal_order_id' );
		$cko_pc_id           = WC_Checkoutcom_Utility::cko_get_session( 'cko_pc_id' );

		wp_send_json_success();
	}

	/**
	 * Process payment with PayPal.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {
		if ( ! session_id() ) {
			session_start();
		}

		$cko_paypal_order_id = WC_Checkoutcom_Utility::cko_get_session( 'cko_paypal_order_id' );
		$cko_pc_id           = WC_Checkoutcom_Utility::cko_get_session( 'cko_pc_id' );

		if ( ! empty( $cko_pc_id ) ) {

			try {
				$checkout = new Checkout_SDK();
				$response = $checkout->get_builder()->getPaymentContextsClient()->getPaymentContextDetails( $cko_pc_id );


				$order_id = absint( WC()->session->get( 'order_awaiting_payment' ) );
				$order    = wc_get_order( $order_id );

				if ( isset( $response['payment_request']['shipping']['address'] ) ) {
					$paypal_shipping_address    = $response['payment_request']['shipping']['address'];
					$paypal_shipping_first_name = $response['payment_request']['shipping']['first_name'];
					$paypal_shipping_last_name  = $response['payment_request']['shipping']['last_name'] ?? '';

//					$order->set_billing_address_1( $paypal_shipping_address[ 'address_line1' ] );
//					$order->set_billing_address_2( $paypal_shipping_address[ 'address_line2' ] ?? '' );
//					$order->set_billing_city( $paypal_shipping_address[ 'city' ] );
//					$order->set_billing_postcode( $paypal_shipping_address[ 'zip' ] );
//					$order->set_billing_country( $paypal_shipping_address[ 'country' ] );
//
//					$order->set_shipping_first_name( $paypal_shipping_first_name );
//					$order->set_shipping_last_name( $paypal_shipping_last_name );
//					$order->set_shipping_address_1( $paypal_shipping_address[ 'address_line1' ] );
//					$order->set_shipping_address_2( $paypal_shipping_address[ 'address_line2' ] ?? '' );
//					$order->set_shipping_city( $paypal_shipping_address[ 'city' ] );
//					$order->set_shipping_postcode( $paypal_shipping_address[ 'zip' ] );
//					$order->set_shipping_country( $paypal_shipping_address[ 'country' ] );
				}

				$payment_context_id    = $cko_pc_id;
				$processing_channel_id = $response['payment_request']['processing_channel_id'];

				// Payment request to capture amount.
				$return_response = $this->request_payment( $order, $payment_context_id, $processing_channel_id );

				WC_Checkoutcom_Utility::cko_set_session( 'cko_paypal_order_id', '' );
				WC_Checkoutcom_Utility::cko_set_session( 'cko_pc_id', '' );

				wp_send_json( $return_response );

			} catch ( CheckoutApiException $ex ) {
				$gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) === 'yes';

				$error_message = 'An error has occurred while processing PayPal getPaymentContextDetails request. ';

				if ( $gateway_debug ) {
					$error_message .= $ex->getMessage();
				}

				WC_Checkoutcom_Utility::logger( $error_message, $ex );

				wp_send_json_error( [ 'messages' => $error_message ] );
			}


		} else {
			// Not express checkout
			$this->cko_create_order_request();
		}

		exit();
	}

	/**
     * Handle PayPal PaymentContexts request with cart data.
     *
     * 1st step to generate PayPal Order ID.
     *
	 * @return void
	 */
    public function cko_create_order_request( $is_express = false ) {

	    $paymentContextsRequest           = new Checkout\Payments\Contexts\PaymentContextsRequest();
	    $paymentContextsRequest->source   = new Checkout\Payments\Request\Source\Apm\RequestPayPalSource();
	    $paymentContextsRequest->currency = get_woocommerce_currency();

	    $paymentContextsRequest->processing                      = new Checkout\Payments\Contexts\PaymentContextsProcessing();
	    $paymentContextsRequest->processing->shipping_preference = Checkout\Payments\ShippingPreference::$GET_FROM_FILE;

		if ( $is_express ) {
			$paymentContextsRequest->processing->user_action = Checkout\Payments\UserAction::$CONTINUE;
		}

	    $total_amount = WC()->cart->total;
	    $amount_cents = WC_Checkoutcom_Utility::value_to_decimal( $total_amount, get_woocommerce_currency() );

	    $paymentContextsRequest->amount  = $amount_cents;
	    $paymentContextsRequest->capture = true;

	    $paymentContextsRequest->payment_type = PaymentType::$regular;

        if ( WC_Checkoutcom_Utility::is_cart_contains_subscription() ) {
		    $paymentContextsRequest->payment_type = PaymentType::$recurring;

	        $plan       = new Checkout\Payments\BillingPlan();
	        $plan->type = Checkout\Payments\BillingPlanType::$merchant_initiated_billing_single_agreement;

	        $paymentContextsRequest->processing->plan = $plan;
        }

	    $items             = new Checkout\Payments\Contexts\PaymentContextsItems();
	    $items->name       = 'All Products';
	    $items->unit_price = $amount_cents;
	    $items->quantity   = '1';

	    $paymentContextsRequest->items = [ $items ];

	    $checkout = new Checkout_SDK();

        try {
	        $response = $checkout->get_builder()->getPaymentContextsClient()->createPaymentContexts( $paymentContextsRequest );

	        WC_Checkoutcom_Utility::cko_set_session( 'cko_pc_id', $response['id'] );

            if ( isset( $response['partner_metadata']['order_id'] ) ) {

                wp_send_json( [ 'order_id' => $response['partner_metadata']['order_id'] ], 200 );
            }

        } catch ( CheckoutApiException $ex ) {
	        $gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) === 'yes';

	        $error_message = 'An error has occurred while processing PayPal createPaymentContexts request. ';

	        if ( $gateway_debug ) {
		        $error_message .= $ex->getMessage();
	        }

	        WC_Checkoutcom_Utility::logger( $error_message, $ex );

	        wp_send_json_error( [ 'messages' => $error_message ] );
        }

        exit();
    }

	/**
     * Handle last payment request to capture payment.
     *
	 * @return void
	 */
    public function cko_cc_capture() {
	    $cko_paypal_order_id = WC_Checkoutcom_Utility::cko_get_session( 'cko_paypal_order_id' );
	    $cko_pc_id           = WC_Checkoutcom_Utility::cko_get_session( 'cko_pc_id' );

	    if ( empty( $cko_pc_id ) ) {
		    return;
	    }

	    try {
	        $checkout = new Checkout_SDK();
            $response = $checkout->get_builder()->getPaymentContextsClient()->getPaymentContextDetails( $cko_pc_id );

            $order_id = absint( WC()->session->get( 'order_awaiting_payment' ) );
            $order    = wc_get_order( $order_id );

            $payment_context_id    = $cko_pc_id;
            $processing_channel_id = $response['payment_request']['processing_channel_id'];

            // Payment request to capture amount.
            $return_response = $this->request_payment( $order, $payment_context_id, $processing_channel_id );

		    WC_Checkoutcom_Utility::cko_set_session( 'cko_paypal_order_id', '' );
		    WC_Checkoutcom_Utility::cko_set_session( 'cko_pc_id', '' );

            wp_send_json_success( $return_response );

        } catch ( CheckoutApiException $ex ) {
            $gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) === 'yes';

            $error_message = 'An error has occurred while processing PayPal getPaymentContextDetails request. ';

            if ( $gateway_debug ) {
                $error_message .= $ex->getMessage();
            }

            WC_Checkoutcom_Utility::logger( $error_message, $ex );

            wp_send_json_error( [ 'messages' => $error_message ] );
        }
    }

	/**
     * Handle request to capture amount.
     *
	 * @param WC_Order $order Order object.
	 * @param string   $payment_context_id Payment Context ID.
	 * @param string   $processing_channel_id Processing channel ID.
	 * @return array|void
	 */
    public function request_payment( $order, $payment_context_id, $processing_channel_id ) {

	    try {
	        $checkout = new Checkout_SDK();

		    $amount        = $order->get_total();
		    $amount_cents  = WC_Checkoutcom_Utility::value_to_decimal( $amount, $order->get_currency() );

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

	            $error_message = esc_html__( 'An error has occurred while PayPal payment request. ', 'checkout-com-unified-payments-api' );

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

		    $error_message = esc_html__( 'An error has occurred while PayPal payment request. ', 'checkout-com-unified-payments-api' );

		    // Check if gateway response is enabled from module settings.
		    if ( $gateway_debug ) {
			    $error_message .= $ex->getMessage();
		    }

		    WC_Checkoutcom_Utility::logger( $error_message, $ex );

		    wp_send_json_error( [ 'messages' => $error_message ] );
        }
    }

	/**
	 * Show module configuration in backend.
	 *
	 * @return string|void
	 */
	public function init_form_fields() {
		$this->form_fields = WC_Checkoutcom_Cards_Settings::paypal_settings();
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
	 * Add attributes to script tag.
	 *
	 * @param string $tag HTML for the script tag.
	 * @param string $handle Handle of script.
	 * @param string $src Src of script.
	 * @return string
	 */
    public function add_attributes_to_script( $tag, $handle, $src ) {
	    if ( 'cko-paypal-script' !== $handle ) {
		    return $tag;
	    }

	    return str_replace( '<script src', '<script data-partner-attribution-id="CheckoutLtd_PSP" src', $tag );
    }

	/**
	 * Outputs scripts used for checkout payment.
	 */
	public function payment_scripts() {
		$paypal_enabled = ! empty( $this->get_option( 'enabled' ) ) && 'yes' === $this->get_option( 'enabled' );

		if ( ! $paypal_enabled ) {
			return;
		}

		if ( ! empty( WC_Checkoutcom_Utility::cko_get_session( 'cko_pc_id' ) ) ) {
			return;
		}

		// Load on Cart, Checkout, pay for order or add payment method pages.
		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) && ! is_add_payment_method_page() ) {
			return;
		}

		if ( is_wc_endpoint_url( 'order-received' ) ) {
			return;
		}

		$core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings' );
		$environment   = 'sandbox' === $core_settings['ckocom_environment'];

        if ( $environment ) {
            // sandbox.
		    $paypal_js_arg['client-id'] = 'ASLqLf4pnWuBshW8Qh8z_DRUbIv2Cgs3Ft8aauLm9Z-MO9FZx1INSo38nW109o_Xvu88P3tly88XbJMR';
        } else {
            // live.
		    $paypal_js_arg['client-id'] = 'ATbi1ysGm-jp4RmmAFz1EWH4dFpPd-VdXIWWzR4QZK5LAvDu_5atDY9dsUEJcLS5mTpR8Wb1l_m6Ameq';
        }

		$paypal_js_arg['merchant-id'] = $this->get_option( 'ckocom_paypal_merchant_id' );

		$paypal_js_arg['disable-funding'] = 'credit,card,sepa';
		$paypal_js_arg['commit']          = 'false';
		$paypal_js_arg['currency']        = get_woocommerce_currency();
		$paypal_js_arg['intent']          = 'capture'; // 'authorize' // ???

        if ( WC_Checkoutcom_Utility::is_cart_contains_subscription() ) {
	        $paypal_js_arg['intent'] = 'tokenize';
	        $paypal_js_arg['vault']  = 'true';
        }

		$paypal_js_url = add_query_arg( $paypal_js_arg, 'https://www.paypal.com/sdk/js' );

		wp_register_script( 'cko-paypal-script', $paypal_js_url, [ 'jquery' ], null );

		$vars = [
			'create_order_url'              => add_query_arg( [ 'cko_paypal_action' => 'create_order'], WC()->api_request_url( 'CKO_Paypal_Woocommerce' ) ),
			'cc_capture'                    => add_query_arg( [ 'cko_paypal_action' => 'cc_capture' ], WC()->api_request_url( 'CKO_Paypal_Woocommerce' ) ),
			'woocommerce_process_checkout'  => wp_create_nonce('woocommerce-process_checkout'),
			'is_cart_contains_subscription' => WC_Checkoutcom_Utility::is_cart_contains_subscription(),
            'paypal_button_selector'        => '#paypal-button-container',
		];

		wp_localize_script( 'cko-paypal-script', 'cko_paypal_vars', $vars );

		wp_enqueue_script( 'cko-paypal-script' );

		wp_register_script(
                'cko-paypal-integration-script',
			WC_CHECKOUTCOM_PLUGIN_URL . '/assets/js/cko-paypal-integration.js',
			[ 'jquery', 'cko-paypal-script' ],
			WC_CHECKOUTCOM_PLUGIN_VERSION
        );

		wp_enqueue_script( 'cko-paypal-integration-script' );

    }

	/**
	 * Show frames js on checkout page.
	 */
	public function payment_fields() {

		if ( ! empty( $this->get_option( 'description' ) ) ) {
			echo  $this->get_option( 'description' );
		}
	}

	/**
	 * Handle PayPal refund.
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
