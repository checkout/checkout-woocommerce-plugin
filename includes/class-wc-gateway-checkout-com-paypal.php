<?php
/**
 * PayPal's method class.
 *
 * @package wc_checkout_com
 */

defined( 'ABSPATH' ) || exit;

use Checkout\CheckoutApiException;

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

		add_action('woocommerce_api_' . strtolower( 'CKO_Paypal_Woocommerce' ), [ $this, 'handle_wc_api' ] );
	}

    public function handle_wc_api() {

	    if ( ! empty( $_GET['cko_paypal_action'] ) ) {
		    switch ( $_GET['cko_paypal_action'] ) {
			    case "create_order":

				    if (isset($_POST) && !empty($_POST)) {
//					    add_action('woocommerce_after_checkout_validation', array($this, 'maybe_start_checkout'), 10, 2);

                        WC()->checkout->process_checkout();

                        if ( wc_notice_count( 'error' ) > 0 ) {
						    WC()->session->set( 'reload_checkout', true );
						    $error_messages_data = wc_get_notices('error');
						    $error_messages = array();
						    foreach ($error_messages_data as $key => $value) {
							    $error_messages[] = $value['notice'];
						    }
						    wc_clear_notices();
						    ob_start();
						    wp_send_json_error(array('messages' => $error_messages));
						    exit;
					    }
					    exit();
				    }

//                    $this->cko_create_order_request();
                    break;

			    case "cc_capture":
//				    wc_clear_notices();
				    WC_Checkoutcom_Utility::cko_set_session( 'cko_paypal_order_id', wc_clean( $_GET['paypal_order_id'] ) );
				    $this->cko_cc_capture();
				    break;
            }
        }

	    exit();
    }

    public function cko_create_order_request() {

	    $paymentContextsRequest = new Checkout\Payments\Contexts\PaymentContextsRequest();
	    $paymentContextsRequest->source = new Checkout\Payments\Request\Source\Apm\RequestPayPalSource();
	    $paymentContextsRequest->currency = get_woocommerce_currency();

	    $paymentContextsRequest->processing = new Checkout\Payments\Contexts\PaymentContextsProcessing();
	    $paymentContextsRequest->processing->shipping_preference = Checkout\Payments\ShippingPreference::$NO_SHIPPING;

	    $total_amount = WC()->cart->total;
	    $amount_cents = WC_Checkoutcom_Utility::value_to_decimal( $total_amount, get_woocommerce_currency() );

	    $paymentContextsRequest->amount  = $amount_cents;
	    $paymentContextsRequest->capture = true;

	    $items = new Checkout\Payments\Contexts\PaymentContextsItems();
	    $items->name = 'All Products';
	    $items->unit_price = $amount_cents;
	    $items->quantity = '1';

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

    public function cko_cc_capture() {
	    $cko_paypal_order_id = WC_Checkoutcom_Utility::cko_get_session( 'cko_paypal_order_id' );
	    $cko_pc_id = WC_Checkoutcom_Utility::cko_get_session( 'cko_pc_id' );

	    if ( ! empty( $cko_pc_id ) ) {

		    $checkout = new Checkout_SDK();

		    try {
                $response = $checkout->get_builder()->getPaymentContextsClient()->getPaymentContextDetails( $cko_pc_id );

			    $order_id = absint( WC()->session->get( 'order_awaiting_payment' ) );
			    $order    = wc_get_order( $order_id );

			    $payment_context_id = $cko_pc_id;
                $processing_channel_id = $response['payment_request']['processing_channel_id'];

                // Capture payment.
                $return_response = $this->request_payment( $order, $payment_context_id, $processing_channel_id );

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

		    exit();
        }
    }

    public function request_payment( $order, $payment_context_id, $processing_channel_id ) {

	    $core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings' );
	    $environment   = ( 'sandbox' === $core_settings['ckocom_environment'] );
        $url           = $environment ? 'https://api.sandbox.checkout.com/payments' : 'https://api.checkout.com/payments';

        $core_settings['ckocom_sk'] = cko_is_nas_account() ? 'Bearer ' . $core_settings['ckocom_sk'] : $core_settings['ckocom_sk'];

	    $response = wp_remote_post(
		    $url,
		    [
			    'headers' => [ 'Authorization' => $core_settings['ckocom_sk'], 'Content-Type' => 'application/json', ],
			    'body'    => json_encode( [
				    'payment_context_id'    => $payment_context_id,
				    'processing_channel_id' => $processing_channel_id,
				    'reference'             => $order->get_order_number(),
			    ] ),
		    ]
	    );

	    if ( is_wp_error( $response ) ) {

		    WC_Checkoutcom_Utility::logger(
			    sprintf(
				    'An error has occurred while PayPal payment Order # %d request.',
				    $order->get_id(),
			    ),
		    );

		    wp_send_json_error( [ 'messages' => esc_html__( 'An error has occurred while PayPal payment request.', 'checkout-com-unified-payments-api' ) ] );
        }

        $response_code = wp_remote_retrieve_response_code( $response );

	    /**
	     * Full Success = 201
         * Partial Success = 202
         *
         * Ref : https://api-reference.checkout.com/#operation/requestAPaymentOrPayout!path=1/payment_context_id&t=request
	     */
	    if ( in_array( $response_code, [ 201, 202 ], true ) ) {

            $result = json_decode( wp_remote_retrieve_body( $response ), true );

            $order->set_transaction_id( $result['id'] );
            $order->update_meta_data( '_cko_payment_id', $result['id'] );

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

	    } else {

		    WC_Checkoutcom_Utility::logger(
			    sprintf(
				    'An error has occurred while PayPal payment Order # %d request. Response code: %d',
				    $order->get_id(),
				    $response_code
			    )
		    );

		    wp_send_json_error( [ 'messages' => esc_html__( sprintf( 'An error has occurred while PayPal payment request. Response code: %d', $response_code ), 'checkout-com-unified-payments-api' ) ] );
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
		// Load on Cart, Checkout, pay for order or add payment method pages.
		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) && ! is_add_payment_method_page() ) {
			return;
		}

		$paypal_js_arg['client-id'] = 'ASLqLf4pnWuBshW8Qh8z_DRUbIv2Cgs3Ft8aauLm9Z-MO9FZx1INSo38nW109o_Xvu88P3tly88XbJMR';

        // @TODO = Remove merchant-id and add new setting to set it. !!!!!!!! --^-- !!!!!!!
//		$paypal_js_arg['merchant-id'] = 'X89STVWALZQNY';
		$paypal_js_arg['merchant-id'] = 'F8LE5W9EVEYLQ';

		$paypal_js_arg['disable-funding'] = 'credit,card,sepa';
		$paypal_js_arg['commit'] = 'false';
		$paypal_js_arg['currency'] = get_woocommerce_currency();
		$paypal_js_arg['intent'] = 'capture'; // 'authorize' // ???

		$paypal_js_url = add_query_arg($paypal_js_arg, 'https://www.paypal.com/sdk/js');


		wp_register_script( 'cko-paypal-script', $paypal_js_url, [ 'jquery' ], null );

		$vars = [
			'create_order_url' => add_query_arg( [ 'cko_paypal_action' => 'create_order'], WC()->api_request_url( 'CKO_Paypal_Woocommerce' ) ),
			'cc_capture'       => add_query_arg( [ 'cko_paypal_action' => 'cc_capture' ], WC()->api_request_url( 'CKO_Paypal_Woocommerce' ) ),
			'woocommerce_process_checkout' => wp_create_nonce('woocommerce-process_checkout'),
            'paypal_button_selector' => '#paypal-button-container',
		];

		wp_localize_script( 'cko-paypal-script', 'cko_paypal_vars', $vars );

		wp_enqueue_script( 'cko-paypal-script' );
    }

	/**
	 * Show frames js on checkout page.
	 */
	public function payment_fields() {

		if ( ! empty( $this->get_option( 'description' ) ) ) {
			echo  $this->get_option( 'description' );
		}

		?>

        <script>
            jQuery( '#payment' ).append( '<div id="paypal-button-container" style="margin-top:15px;"></div>' );

            jQuery( cko_paypal_vars.paypal_button_selector ).hide();

            if ( jQuery( '#payment_method_wc_checkout_com_paypal' ).is( ':checked' ) ) {
                // Disable place order button.
                jQuery( '#place_order' ).hide();
                // Show Google Pay button.
                jQuery( cko_paypal_vars.paypal_button_selector ).show();
            }

            jQuery( document ).on( 'change', "input[name='payment_method']", function ( e ) {

                if ( jQuery( this ).val() === 'wc_checkout_com_paypal' ) {
                    // PayPay selected.
                    jQuery( cko_paypal_vars.paypal_button_selector ).show();

                    jQuery( "#place_order" ).hide();
                    jQuery( '#place_order' ).prop( "disabled", true );

                } else if ( 'wc_checkout_com_apple_pay' === this.value ) {
                    jQuery( cko_paypal_vars.paypal_button_selector ).hide();
                    jQuery( '#ckocom_googlePay' ).hide();
                    jQuery( "#place_order" ).hide();
                } else if ( 'wc_checkout_com_google_pay' === this.value ) {
                    jQuery( cko_paypal_vars.paypal_button_selector ).hide();
                    jQuery( '#ckocom_applePay' ).hide();
                    jQuery( "#place_order" ).hide();
                } else {
                    jQuery( cko_paypal_vars.paypal_button_selector ).hide();
                    jQuery( '#ckocom_googlePay' ).hide();

                    jQuery( '#place_order' ).prop( "disabled", false );
                    jQuery( '#place_order' ).show()
                }
            } )

            // Initialise PayPal when page is ready.
            jQuery( document ).ready(function() {
                paypal.Buttons({
                    createOrder( data, actions ) {
                        // Create order on PayPal to get Order ID.
                        var data;

                        data = jQuery( cko_paypal_vars.paypal_button_selector ).closest('form').serialize();

                        return fetch( cko_paypal_vars.create_order_url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: data
                        }).then(function (res) {
                            return res.json();
                        }).then(function (data) {
                            if (typeof data.success !== 'undefined') {
                                var messages = data.data.messages ? data.data.messages : data.data;
                                if ('string' === typeof messages) {
                                    showError('<ul class="woocommerce-error" role="alert">' + messages + '</ul>', jQuery('form'));
                                }
                                return null;
                            } else {
                                return data.order_id;
                            }
                        });

                    },
                    onApprove: async function (data) {

                        jQuery('.woocommerce').block({message: null, overlayCSS: {background: '#fff', opacity: 0.6}});

                        jQuery.post(cko_paypal_vars.cc_capture + "&paypal_order_id=" + data.orderID + "&woocommerce-process-checkout-nonce=" + cko_paypal_vars.woocommerce_process_checkout, function (data) {
                            if (typeof data.success !== 'undefined' && data.success !== true ) {
                                var messages = data.data.messages ? data.data.messages : data.data;
                                if ('string' === typeof messages) {
                                    showError('<ul class="woocommerce-error" role="alert">' + messages + '</ul>', jQuery('form'));
                                }
                            } else {
                                window.location.href = data.data.redirect;
                            }
                        });
                    },
                    onCancel: function (data, actions) {
                        jQuery('.woocommerce').unblock();
                    },
                    onError: function (err) {
                        console.log(err);
                        jQuery('.woocommerce').unblock();
                    },
                }).render( cko_paypal_vars.paypal_button_selector );
            });

            var showError = function (error_message) {

                jQuery('form.checkout .woocommerce-NoticeGroup').remove();
                jQuery('form.checkout').prepend('<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + error_message + '</div>');
                jQuery('.woocommerce, .form.checkout').removeClass('processing').unblock();
                // $('.woocommerce').find('.input-text, select, input:checkbox').trigger('validate').trigger('blur');
                jQuery('html, body').animate({
                    scrollTop: (jQuery('form.checkout').offset().top - 100 )
                }, 1000 );
            };

        </script>
		<?php
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

		$this->cko_create_order_request();
        exit();

		$order = new WC_Order( $order_id );

		// Create payment with PayPal token.
		$result = (array) ( new WC_Checkoutcom_Api_Request )->create_payment( $order, [] );

		// Redirect to apm if redirection url is available.
		if ( isset( $result['3d'] ) && ! empty( $result['3d'] ) ) {

			return [
				'result'   => 'success',
				'redirect' => $result['3d'],
			];
		}

		// check if result has error and return error message.
		if ( ! empty( $result['error'] ) ) {
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
