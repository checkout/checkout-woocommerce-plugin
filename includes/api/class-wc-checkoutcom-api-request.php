<?php

include_once 'class-wc-checkoutcom-utility.php';
include 'class-wc-checkoutcom-apm-method.php';

use Checkout\Apm\Klarna\CreditSessionRequest;
use Checkout\CheckoutApiException;
use Checkout\CheckoutUtils;
use Checkout\Common\Address;
use Checkout\Common\ChallengeIndicatorType;
use Checkout\Common\CustomerRequest;
use Checkout\Payments\BillingDescriptor;
use Checkout\Payments\PaymentRequest;
use Checkout\Payments\PaymentType;
use Checkout\Payments\RefundRequest;
use Checkout\Payments\ShippingDetails;
use Checkout\Payments\Source\Apm\FawryProduct;
use Checkout\Payments\Source\RequestIdSource;
use Checkout\Payments\Source\RequestTokenSource;
use Checkout\Payments\ThreeDsRequest;
use Checkout\Payments\VoidRequest;
use Checkout\Tokens\ApplePayTokenData;
use Checkout\Tokens\ApplePayTokenRequest;
use Checkout\Tokens\GooglePayTokenData;
use Checkout\Tokens\GooglePayTokenRequest;


class WC_Checkoutcom_Api_request
{
    /**
     * Create payment and return response
     *
     * @param WC_Order $order
     * @param $arg
     * @param $subscription subscription renewal flag
     * @return array
     */
    public static function create_payment( WC_Order $order, $arg, $subscription = null )
    {
        // Get payment request parameter
	    $request_param = WC_Checkoutcom_Api_request::get_request_param( $order, $arg, $subscription );
        $gateway_debug = WC_Admin_Settings::get_option('cko_gateway_responses') == 'yes';

        $is_sepa_renewal = ( 'wc_checkout_com_alternative_payments_sepa' === $order->get_payment_method() ) && ( ! is_null( $subscription ) );

        // Initialize the Checkout Api.
	    $checkout = new Checkout_SDK();

        try {
            // Call to create charge.
	        $response = $checkout->get_builder()->getPaymentsClient()->requestPayment( $request_param );

            // Check if payment successful.
	        if ( WC_Checkoutcom_Utility::is_successful( $response ) ) {
		        // Check if payment is 3D secure.
		        if ( WC_Checkoutcom_Utility::is_pending( $response ) ) {
			        // Check if SEPA renewal order.
			        if ( $is_sepa_renewal ) {
				        return $response;
			        }
			        // Check if redirection link exist.
			        if ( WC_Checkoutcom_Utility::getRedirectUrl( $response ) ) {
				        // return 3d redirection url.
				        return array( '3d' => WC_Checkoutcom_Utility::getRedirectUrl( $response ) );

			        } else {
				        $error_message = __( 'An error has occurred while processing your payment. Redirection link not found', 'wc_checkout_com' );

				        WC_Checkoutcom_Utility::logger( $error_message, null );

				        return array( 'error' => $error_message );
			        }
		        } else {

			        return $response;
		        }
	        } else {

		        // Set payment id post meta if the payment id declined.
		        if ( 'Declined' === $response['status'] ) {
			        update_post_meta( $order->get_id(), '_cko_payment_id', $response['id'] );
		        }

		        $error_message = __( 'An error has occurred while processing your payment. Please check your card details and try again. ', 'wc_checkout_com' );

		        // If the merchant enabled gateway response.
		        if ( $gateway_debug ) {
			        // Only show the decline reason in case the response code is not from a risk rule
			        if ( ! preg_match( "/^(?:40)\d+$/", $response['response_code'] ) ) {
				        $error_message .= sprintf( __( 'Status : %s, Response summary : %s', 'wc_checkout_com' ), $response['status'], $response['response_summary'] );
			        }
		        }

		        WC_Checkoutcom_Utility::logger( $error_message, $response );

		        return array( 'error' => $error_message );
            }
        } catch ( CheckoutApiException $ex ) {
	        $error_message = __( "An error has occurred while processing your payment. ", 'wc_checkout_com' );

	        // check if gateway response is enable from module settings
	        if ( $gateway_debug ) {
		        $error_message .= $ex->getMessage();
	        }

	        WC_Checkoutcom_Utility::logger( $error_message, $ex );

	        return array( 'error' => $error_message );
        }
    }

    /**
     * Build payment request parameter
     *
     * @param $order
     * @param $card_token
     * @param $subscription subscription renewal flag
     *
     * @return PaymentRequest
     */
	private static function get_request_param( WC_Order $order, $arg, $subscription = null ) {
		global $woocommerce, $wp_version;

		$auto_capture       = WC_Admin_Settings::get_option( 'ckocom_card_autocap', 1 ) == 1;
		$amount             = $order->get_total();
		$amount_cents       = WC_Checkoutcom_Utility::valueToDecimal( $amount, $order->get_currency() );
		$three_d            = WC_Admin_Settings::get_option( 'ckocom_card_threed' ) == 1 && $subscription == null ? true : false;
		$attempt_no_threeD  = WC_Admin_Settings::get_option( 'ckocom_card_notheed' ) == 1 ? true : false;
		$dynamic_descriptor = WC_Admin_Settings::get_option( 'ckocom_card_desctiptor' ) == 1 ? true : false;
		$mada_enable        = WC_Admin_Settings::get_option( 'ckocom_card_mada' ) == 1 ? true : false;
		$save_card          = WC_Admin_Settings::get_option( 'ckocom_card_saved' );
		$google_settings    = get_option( 'woocommerce_wc_checkout_com_google_pay_settings' );
		$is_google_threeds  = 1 === absint( $google_settings['ckocom_google_threed'] );

		$is_save_card   = false;
		$payment_option = 'FramesJs';
		$apms_settings  = get_option( 'woocommerce_wc_checkout_com_alternative_payments_settings' );
		$apms_selected  = ! empty( $apms_settings['ckocom_apms_selector'] ) ? $apms_settings['ckocom_apms_selector'] : array();

		$postData = sanitize_post( $_POST );
		$getData  = sanitize_post( $_GET );

		$customer_address = WC_Checkoutcom_Api_request::customer_address( $postData );

		// Prepare payment parameters.
		if ( 'wc_checkout_com_cards' === $postData['payment_method'] ) {
			if ( $postData['wc-wc_checkout_com_cards-payment-token'] ) {
				if ( 'new' === $postData['wc-wc_checkout_com_cards-payment-token'] ) {
					// New card used.

					$method        = new RequestTokenSource();
					$method->token = $arg;
				} else {
					// Saved card used.

					// Load token by id ($arg)
					$token = WC_Payment_Tokens::get( $arg );

					// Get source_id from $token
					$source_id = $token->get_token();

					$method     = new RequestIdSource();
					$method->id = $source_id;

					$is_save_card = true;

					if ( WC_Admin_Settings::get_option( 'ckocom_card_require_cvv' ) ) {
						$method->cvv = $postData['wc_checkout_com_cards-card-cvv'];
					}
				}
			} else {
				$method        = new RequestTokenSource();
				$method->token = $arg;
			}
		} elseif ( 'wc_checkout_com_google_pay' === $postData['payment_method'] ) {
			$payment_option = 'Google Pay';

			$method        = new RequestTokenSource();
			$method->token = trim( $arg['token'] );

		} elseif ( 'wc_checkout_com_apple_pay' === $postData['payment_method'] ) {
			$payment_option = 'Apple Pay';

			$method        = new RequestTokenSource();
			$method->token = trim( $arg );

		} elseif ( in_array( $arg, $apms_selected ) ) {
			// Alternative payment method selected.
			$method = WC_Checkoutcom_Api_request::get_apm_method( $postData, $order, $arg );

			$payment_option = $method->type;
		} elseif ( ! is_null( $subscription ) ) {

			$method     = new RequestIdSource();
			$method->id = $arg['source_id'];
		}

		if ( $method->type !== 'klarna' ) {
			// Set billing address in $method.
			if ( ! empty( $customer_address['billing_address_1'] ) && ! empty( $customer_address['billing_country'] ) ) {
				$billingAddressParam                = new Address();
				$billingAddressParam->address_line1 = $customer_address['billing_address_1'];
				$billingAddressParam->address_line2 = $customer_address['billing_address_2'];
				$billingAddressParam->city          = $customer_address['billing_city'];
				$billingAddressParam->state         = $customer_address['billing_state'];
				$billingAddressParam->zip           = $customer_address['billing_postcode'];
				$billingAddressParam->country       = $customer_address['billing_country'];
				$method->billing_address            = $billingAddressParam;
			}
		}

		$checkout              = new Checkout_SDK();
		$payment               = $checkout->get_payment_request();
		$payment->source       = $method;
		$payment->capture      = $auto_capture;
		$payment->amount       = $amount_cents;
		$payment->currency     = $order->get_currency();
		$payment->reference    = $order->get_order_number();
		$payment->payment_type = PaymentType::$regular;

		$email = $postData['billing_email'];
		$name  = $postData['billing_first_name'] . ' ' . $postData['billing_last_name'];

		// Pay Order Page.
		$is_pay_order = ! empty( $getData['pay_for_order'] ) ? (boolean) $getData['pay_for_order'] : false;

		if ( $is_pay_order ) {
			if ( ! empty( $getData['order_id'] ) ) {
				$order_id = $getData['order_id'];
			} else if ( ! empty( $getData['key'] ) ) {
				$order_id = wc_get_order_id_by_order_key( $getData['key'] );
			}

			$order = wc_get_order( $order_id );

			$email = $order->get_billing_email();
			$name  = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
		}

		// Customer.
		$customer        = new CustomerRequest();
		$customer->email = $email;
		$customer->name  = $name;

		$payment->customer = $customer;

		// Check for the subscription flag
		if ( ! is_null( $subscription ) ) {
			$payment->merchant_initiated  = true;
			$payment->payment_type        = 'Recurring';
			$payment->previous_payment_id = get_post_meta( $arg['parent_order_id'], '_cko_payment_id', true ) ?? null;
			$payment->capture             = true;

		} elseif ( function_exists( 'wcs_order_contains_subscription' ) ) {
			if ( wcs_order_contains_subscription( $order, 'parent' ) ) {
				$payment->merchant_initiated = false;
				$payment->payment_type       = 'Recurring';
			}
		}

		$three_ds              = new ThreeDsRequest();
		$three_ds->enabled     = $three_d;
		$three_ds->attempt_n3d = $attempt_no_threeD;

		if ( 'wc_checkout_com_cards' === $postData['payment_method']
		     && $three_d
		     && $save_card
		     && (
			     isset( $postData['wc-wc_checkout_com_cards-new-payment-method'] )
			     && sanitize_text_field( $postData['wc-wc_checkout_com_cards-new-payment-method'] )
		     )
		) {
			$three_ds->challenge_indicator = ChallengeIndicatorType::$challenge_requested_mandate;
		}

		if ( $dynamic_descriptor ) {
			$descriptor_name = WC_Admin_Settings::get_option( 'ckocom_card_desctiptor_name' );
			$descriptor_city = WC_Admin_Settings::get_option( 'ckocom_card_desctiptor_city' );

			$descriptor       = new BillingDescriptor();
			$descriptor->name = $descriptor_name;
			$descriptor->city = $descriptor_city;

			$payment->billing_descriptor = $descriptor;
		}

		// Set 3Ds to payment request.
		if ( 'wc_checkout_com_google_pay' === $postData['payment_method'] && ( $is_google_threeds && 'pan_only' === $arg['token_format'] ) ) {
			$payment->three_ds = new ThreeDsRequest();
		} else if ( 'wc_checkout_com_google_pay' !== $postData['payment_method'] ) {
			$payment->three_ds = $three_ds;
		}

		// Set shipping Address.
		if ( ! empty( $customer_address['shipping_address_1'] ) && ! empty( $customer_address['shipping_country'] ) ) {
			$shipping_address_param                = new Address();
			$shipping_address_param->address_line1 = $customer_address['shipping_address_1'];
			$shipping_address_param->address_line2 = $customer_address['shipping_address_2'];
			$shipping_address_param->city          = $customer_address['shipping_city'];
			$shipping_address_param->state         = $customer_address['shipping_state'];
			$shipping_address_param->zip           = $customer_address['shipping_postcode'];
			$shipping_address_param->country       = $customer_address['shipping_country'];

			$shipping_details          = new ShippingDetails();
			$shipping_details->address = $shipping_address_param;

			$payment->shipping = $shipping_details;
		}

		// Set redirection url in payment request.
		$redirection_url = add_query_arg( 'wc-api', 'wc_checkoutcom_callback', home_url( '/' ) );

		if ( cko_is_nas_account() ) {
			$redirection_url = home_url( '/checkoutcom-callback' );
		}

		$payment->success_url = $redirection_url;
		$payment->failure_url = $redirection_url;

		$udf5 = sprintf(
			'Platform Data - Wordpress %s / Woocommerce %s, Integration Data - Checkout.com %s, SDK Data - PHP SDK %s, Order ID - %s, Server - %s',
			$wp_version,
			$woocommerce->version,
			WC_Gateway_Checkout_Com_Cards::PLUGIN_VERSION,
			CheckoutUtils::PROJECT_VERSION,
			$order->get_order_number(),
			get_site_url()
		);

		$metadata = array(
			'udf5'     => $udf5,
			'order_id' => $order->get_id(),
		);

		// set capture delay if payment action is authorise and capture.
		if ( $auto_capture ) {
			$captureDelay        = WC_Checkoutcom_Utility::getDelayedCaptureTimestamp();
			$payment->capture_on = $captureDelay;
		}

		// check if mada is enabled in module setting.
		if ( $mada_enable ) {
			$is_mada = false;

			if ( ! empty( $postData['cko-card-bin'] ) ) {
				$is_mada = WC_Checkoutcom_Utility::isMadaCard( $postData['cko-card-bin'] );
			} else {

				if ( $is_save_card ) {
					// check if souce_id is a mada card.
					// load token by id ($arg).
					$token = WC_Payment_Tokens::get( $arg );
					// check if source_id is mada.
					$is_mada = $token->get_meta( 'is_mada' );

					if ( $is_mada ) {
						$method->cvv = $postData['wc_checkout_com_cards-card-cvv'];
					}
				}
			}

			if ( $is_mada ) {
				$payment->capture    = true;
				$payment->capture_on = null;
				$payment->three_ds   = new ThreeDsRequest();
				$metadata            = array_merge( $metadata, array( 'udf1' => 'Mada' ) );
			}

			// Set is_mada in session.
			$_SESSION['cko-is-mada'] = $is_mada;
		}

		// Set metadata info in payment request.
		$payment->metadata = $metadata;

		// Set customer ip address in payment request.
		$payment->payment_ip = $order->get_customer_ip_address();

		return $payment;
	}

    /**
     * @param $data
     * @return array
     */
    private static function customer_address($data)
    {
        // Pay Order Page
        $isPayOrder = !empty($_GET['pay_for_order']) ? (boolean)$_GET['pay_for_order'] : false;

        $billing_first_name = empty( $data[ 'billing_first_name' ] ) ? '' : wc_clean( $data[ 'billing_first_name' ] );
        $billing_last_name  = empty( $data[ 'billing_last_name' ] )  ? '' : wc_clean( $data[ 'billing_last_name' ] );
        $billing_address_1  = empty( $data[ 'billing_address_1' ] )  ? '' : wc_clean( $data[ 'billing_address_1' ] );
        $billing_address_2  = empty( $data[ 'billing_address_2' ] )  ? '' : wc_clean( $data[ 'billing_address_2' ] );
        $billing_city       = empty( $data[ 'billing_city' ] )       ? '' : wc_clean( $data[ 'billing_city' ] );
        $billing_state      = empty( $data[ 'billing_state' ] )      ? '' : wc_clean( $data[ 'billing_state' ] );
        $billing_postcode   = empty( $data[ 'billing_postcode' ] )   ? '' : wc_clean( $data[ 'billing_postcode' ] );
        $billing_country    = empty( $data[ 'billing_country' ] )    ? '' : wc_clean( $data[ 'billing_country' ] );

        if ( isset( $data['ship_to_different_address'] ) ) {
            $shipping_first_name = empty( $data[ 'shipping_first_name' ] ) ? '' : wc_clean( $data[ 'shipping_first_name' ] );
            $shipping_last_name  = empty( $data[ 'shipping_last_name' ] )  ? '' : wc_clean( $data[ 'shipping_last_name' ] );
            $shipping_address_1  = empty( $data[ 'shipping_address_1' ] )  ? '' : wc_clean( $data[ 'shipping_address_1' ] );
            $shipping_address_2  = empty( $data[ 'shipping_address_2' ] )  ? '' : wc_clean( $data[ 'shipping_address_2' ] );
            $shipping_city       = empty( $data[ 'shipping_city' ] )       ? '' : wc_clean( $data[ 'shipping_city' ] );
            $shipping_state      = empty( $data[ 'shipping_state' ] )      ? '' : wc_clean( $data[ 'shipping_state' ] );
            $shipping_postcode   = empty( $data[ 'shipping_postcode' ] )   ? '' : wc_clean( $data[ 'shipping_postcode' ] );
            $shipping_country    = empty( $data[ 'shipping_country' ] )    ? '' : wc_clean( $data[ 'shipping_country' ] );
        } elseif ($isPayOrder) {

            // In case payment is from pay_order
            // Get billing and shipping details from order
            if(!empty($_GET['order_id'])) {
                $order_id    = $_GET['order_id'];
            } else if (!empty($_GET['key'])){
                $order_id    = wc_get_order_id_by_order_key($_GET['key']);
            }

            $order = wc_get_order( $order_id );

            $billing_first_name = $order->billing_first_name;
            $billing_last_name  = $order->billing_last_name;
            $billing_address_1  = $order->billing_address_1;
            $billing_address_2  = $order->billing_address_2;
            $billing_city       = $order->billing_city;
            $billing_state      = $order->billing_state;
            $billing_postcode   = $order->billing_postcode;
            $billing_country    = $order->billing_country;

            $shipping_first_name = $order->shipping_first_name;
            $shipping_last_name  = $order->shipping_last_name;
            $shipping_address_1  = $order->shipping_address_1;
            $shipping_address_2  = $order->shipping_address_2;
            $shipping_city       = $order->shipping_city;
            $shipping_state      = $order->shipping_state;
            $shipping_postcode   = $order->shipping_postcode;
            $shipping_country    = $order->shipping_country;


        } else {
            $shipping_first_name = $billing_first_name;
            $shipping_last_name  = $billing_last_name;
            $shipping_address_1  = $billing_address_1;
            $shipping_address_2  = $billing_address_2;
            $shipping_city       = $billing_city;
            $shipping_state      = $billing_state;
            $shipping_postcode   = $billing_postcode;
            $shipping_country    = $billing_country;
        }

        return array(
            'billing_first_name' => $billing_first_name,
            'billing_last_name' => $billing_last_name,
            'billing_address_1'  => $billing_address_1,
            'billing_address_2' => $billing_address_2,
            'billing_city'       => $billing_city,
            'billing_state'      => $billing_state,
            'billing_postcode'   => $billing_postcode,
            'billing_country'    => $billing_country,
            'shipping_first_name' => $shipping_first_name,
            'shipping_last_name'  => $shipping_last_name,
            'shipping_address_1'  => $shipping_address_1,
            'shipping_address_2'  => $shipping_address_2,
            'shipping_city'       => $shipping_city,
            'shipping_state'      => $shipping_state,
            'shipping_postcode'   => $shipping_postcode,
            'shipping_country'    => $shipping_country,
        );
    }

	/**
	 * @param $session_id
	 *
	 * @return array|mixed
	 */
	public static function verify_session( $session_id ) {
		$gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) == 'yes';

		// Initialize the Checkout Api.
		$checkout = new Checkout_SDK();

		try {

			// Get payment response
			$response = $checkout->get_builder()->getPaymentsClient()->getPaymentDetails( $session_id );

			// Check if payment is successful.
			if ( WC_Checkoutcom_Utility::is_successful( $response ) ) {

				return $response;
			} else {

				// Set payment id post meta if the payment id declined.
				if ( 'Declined' === $response['status'] ) {
					update_post_meta( $response['metadata']['order_id'], '_cko_payment_id', $response['id'] );
				}

				$error_message = __( 'An error has occurred while processing your payment. Please check your card details and try again.', 'wc_checkout_com' );

				// Check if gateway response is enabled from module settings.
				if ( $gateway_debug ) {
					if ( ! empty( $response['actions'] ) ) {
						$action        = $response['actions'][0];
						$error_message .= sprintf( __( 'Status : %s, Response summary : %s', 'wc_checkout_com' ), $response['status'], $action['response_summary'] );
					}
				}

				WC_Checkoutcom_Utility::logger( $error_message, $response );

				$arr = array( 'error' => $error_message );

				$metadata = $response['metadata'];

				// Check if card verification.
				if ( isset( $metadata['card_verification'] ) ) {
					$arr = array(
						'card_verification' => 'error',
						'redirection_url'   => $metadata['redirection_url'],
					);
				}

				return $arr;
			}

		} catch ( CheckoutApiException $ex ) {

			$error_message = __( 'An error has occurred while processing your payment. ', 'wc_checkout_com' );

			// Check if gateway response is enabled from module settings.
			if ( $gateway_debug ) {
				$error_message .= $ex->getMessage();
			}

			WC_Checkoutcom_Utility::logger( $error_message, $ex );

			return array( 'error' => $error_message );
		}
	}

    /**
     * Generate the Google token from Google payment data
     * @return mixed
     */
	public static function generate_google_token() {
		$protocolVersion = sanitize_text_field( $_POST['cko-google-protocolVersion'] );
		$signature       = sanitize_text_field( $_POST['cko-google-signature'] );
		$signedMessage   = stripslashes( $_POST['cko-google-signedMessage'] );

		$checkout = new Checkout_SDK();

		$google_pay                  = new GooglePayTokenData();
		$google_pay->protocolVersion = $protocolVersion;
		$google_pay->signature       = $signature;
		$google_pay->signedMessage   = $signedMessage;

		try {

			$google_pay_token_request             = new GooglePayTokenRequest();
			$google_pay_token_request->token_data = $google_pay;

			$token = $checkout->get_builder()->getTokensClient()->requestWalletToken( $google_pay_token_request );

			return [
				'token'        => $token['token'],
				'token_format' => $token['token_format'],
			];
		} catch ( CheckoutApiException $ex ) {
			$error_message = __( 'An error has occurred while processing your Google pay payment.', 'wc_checkout_com' );
			WC_Checkoutcom_Utility::logger( $error_message, $ex );
		}
	}

    /**
     * Perform capture.
     *
     * @return array|mixed
     */
	public static function capture_payment() {
		$order_id       = sanitize_text_field( $_POST['post_ID'] );
		$cko_payment_id = get_post_meta( $order_id, '_cko_payment_id', true );

		// Check if cko_payment_id is empty.
		if ( empty( $cko_payment_id ) ) {
			$error_message = esc_html__( 'An error has occurred. No Cko Payment Id', 'wc_checkout_com' );

			return array( 'error' => $error_message );
		}

		$order         = wc_get_order( $order_id );
		$amount        = $order->get_total();
		$amount_cents  = WC_Checkoutcom_Utility::valueToDecimal( $amount, $order->get_currency() );
		$gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) == 'yes';

		// Initialize the Checkout Api.
		$checkout = new Checkout_SDK();

		try {
			// Check if payment is already voided or captured on checkout.com hub.
			$details = $checkout->get_builder()->getPaymentsClient()->getPaymentDetails( $cko_payment_id );

			if ( 'Voided' === $details['status'] || 'Captured' === $details['status'] ) {
				$error_message = sprintf(
					esc_html__( 'Payment has already been voided or captured on Checkout.com hub for order Id : %s', 'wc_checkout_com' ),
					$order_id
				);

				return array( 'error' => $error_message );
			}

			// Get capture request object for NAS or ABC.
			$capture_request            = $checkout->get_capture_request();
			$capture_request->amount    = $amount_cents;
			$capture_request->reference = $order->get_order_number();

			$response = $checkout->get_builder()->getPaymentsClient()->capturePayment( $cko_payment_id, $capture_request );

			if ( ! WC_Checkoutcom_Utility::is_successful( $response ) ) {
				$error_message = sprintf(
					esc_html__( 'An error has occurred while processing your capture payment on Checkout.com hub. Order Id : %s', 'wc_checkout_com' ),
					$order_id
				);

				// Check if gateway response is enabled from module settings.
				if ( $gateway_debug ) {
					$error_message .= $response;
				}

				WC_Checkoutcom_Utility::logger( $error_message, $response );

				return array( 'error' => $error_message );
			} else {
				return $response;
			}
		} catch ( CheckoutApiException $ex ) {
			$error_message = esc_html__( 'An error has occurred while processing your capture request.', 'wc_checkout_com' );

			// Check if gateway response is enabled from module settings.
			if ( $gateway_debug ) {
				$error_message .= $ex->getMessage();
			}

			WC_Checkoutcom_Utility::logger( $error_message, $ex );

			return array( 'error' => $error_message );
		}
	}

    /**
     * Perform Void.
     * @return array|mixed
     */
	public static function void_payment() {
		$order_id       = sanitize_text_field( $_POST['post_ID'] );
		$cko_payment_id = get_post_meta( $order_id, '_cko_payment_id', true );

		// Check if cko_payment_id is empty.
		if ( empty( $cko_payment_id ) ) {
			$error_message = esc_html__( 'An error has occurred. No Cko Payment Id', 'wc_checkout_com' );

			return array( 'error' => $error_message );
		}

		$gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) == 'yes';

		// Initialize the Checkout Api.
		$checkout = new Checkout_SDK();

		try {
			// Check if payment is already voided or captured on checkout.com hub.
			$details = $checkout->get_builder()->getPaymentsClient()->getPaymentDetails( $cko_payment_id );

			if ( 'Voided' === $details['status'] || 'Captured' === $details['status'] ) {
				$error_message = sprintf(
					esc_html__( 'Payment has already been voided or captured on Checkout.com hub for order Id : %s', 'wc_checkout_com' ),
					$order_id
				);

				return array( 'error' => $error_message );
			}

			// Prepare void payload.
			$void_request            = new VoidRequest();
			$void_request->reference = $order_id;

			// Process void payment on checkout.com
			$response = $checkout->get_builder()->getPaymentsClient()->voidPayment( $cko_payment_id, $void_request );

			if ( ! WC_Checkoutcom_Utility::is_successful( $response ) ) {
				$error_message = sprintf(
					esc_html__( 'An error has occurred while processing your void payment on Checkout.com hub. Order Id : %s', 'wc_checkout_com' ),
					$order_id
				);

				// check if gateway response is enabled from module settings.
				if ( $gateway_debug ) {
					$error_message .= $response;
				}

				WC_Checkoutcom_Utility::logger( $error_message, $response );

				return array( 'error' => $error_message );
			} else {
				return $response;
			}
		} catch ( CheckoutApiException $ex ) {
			$error_message = esc_html__( 'An error has occurred while processing your void request.', 'wc_checkout_com' );

			// Check if gateway response is enabled from module settings.
			if ( $gateway_debug ) {
				$error_message .= $ex->getMessage();
			}

			WC_Checkoutcom_Utility::logger( $error_message, $ex );

			return array( 'error' => $error_message );
		}
	}

	/**
	 * Perform Refund
	 *
	 * @param $order_id
	 * @param $order
	 *
	 * @return array|mixed
	 */
	public static function refund_payment( $order_id, $order ) {
		$cko_payment_id = get_post_meta( $order_id, '_cko_payment_id', true );

		// Check if cko_payment_id is empty.
		if ( empty( $cko_payment_id ) ) {
			$error_message = __( 'An error has occurred. No Cko Payment Id', 'wc_checkout_com' );

			return array( 'error' => $error_message );
		}

		// check for decimal separator.
		$order_amount        = str_replace( ',', '.', $order->get_total() );
		$order_amount_cents  = WC_Checkoutcom_Utility::valueToDecimal( $order_amount, $order->get_currency() );
		$refund_amount       = str_replace( ',', '.', sanitize_text_field( $_POST['refund_amount'] ) );
		$refund_amount_cents = WC_Checkoutcom_Utility::valueToDecimal( $refund_amount, $order->get_currency() );

		// Check if refund amount is less than order amount.
		$refund_is_less = $refund_amount_cents < $order_amount_cents;

		$gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) == 'yes';

		// Initialize the Checkout Api.
		$checkout = new Checkout_SDK();

		try {
			// Check if payment is already voided or captured on checkout.com hub.
			$details = $checkout->get_builder()->getPaymentsClient()->getPaymentDetails( $cko_payment_id );

			if ( 'Refunded' === $details['status'] && ! $refund_is_less ) {
				$error_message = 'Payment has already been refunded on Checkout.com hub for order Id : ' . $order_id;

				return array( 'error' => $error_message );
			}

			$refund_request            = new RefundRequest();
			$refund_request->reference = $order->get_order_number();

			// Process partial refund if amount is less than order amount.
			if ( $refund_is_less ) {
				$refund_request->amount = $refund_amount_cents;

				// Set is_mada in session.
				$_SESSION['cko-refund-is-less'] = $refund_is_less;
			}

			$response = $checkout->get_builder()->getPaymentsClient()->refundPayment( $cko_payment_id, $refund_request );

			if ( ! WC_Checkoutcom_Utility::is_successful( $response ) ) {
				$error_message = sprintf( esc_html__( 'An error has occurred while processing your refund payment on Checkout.com hub. Order Id : %s', 'wc_checkout_com' ), $order_id );

				// Check if gateway response is enabled from module settings.
				if ( $gateway_debug ) {
					$error_message .= $response;
				}

				WC_Checkoutcom_Utility::logger( $error_message, $response );

				return array( 'error' => $error_message );
			} else {
				return $response;
			}
		} catch ( CheckoutApiException $ex ) {
			$error_message = esc_html__( 'An error has occurred while processing your refund. ', 'wc_checkout_com' );

			// check if gateway response is enabled from module settings.
			if ( $gateway_debug ) {
				$error_message .= $ex->getMessage();
			}

			WC_Checkoutcom_Utility::logger( $error_message, $ex );

			return array( 'error' => $error_message );
		}
	}

    /**
     * Return ideal banks info.
     *
     * @return array
     */
    public static function get_ideal_bank() {
        $gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) == 'yes';

        // Initialize the Checkout Api.
        $checkout = new Checkout_SDK();

        try {

            return $checkout->get_builder()->getIdealClient()->getIssuers();

        } catch ( CheckoutApiException $ex ) {
            $error_message = __( 'An error has occured while retrieving ideal bank details.', 'wc_checkout_com' );

            // check if gateway response is enabled from module settings.
            if ( $gateway_debug ) {
                $error_message .= $ex->getMessage();
            }

            WC_Checkoutcom_Utility::logger( $error_message, $ex );

            return array( 'error' => $error_message );
        }
    }

    /**
     * @param WC_Order $order
     * @param $payment_method
     * @return array
     */
	public static function create_apm_payment( WC_Order $order, $payment_method ) {
	    // Get payment request parameter.
	    $request_param = WC_Checkoutcom_Api_request::get_request_param( $order, $payment_method );

	    WC_Checkoutcom_Utility::logger( 'Apm request payload,', $request_param );

	    $gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) == 'yes';

	    // Initialize the Checkout Api.
	    $checkout = new Checkout_SDK();

        try {
            // Call to create charge.
	        $response = $checkout->get_builder()->getPaymentsClient()->requestPayment( $request_param );

            // Check if payment successful.
	         if ( WC_Checkoutcom_Utility::is_successful( $response ) ) {
                // Check if payment is 3Dsecure.
                if ( WC_Checkoutcom_Utility::is_pending( $response ) || 'Authorized' === $response['status'] ) {
                    // Check if redirection link exist.
                    if ( WC_Checkoutcom_Utility::getRedirectUrl( $response ) ) {

                        // Return apm redirection url.
                        return array( 'apm_redirection' => WC_Checkoutcom_Utility::getRedirectUrl( $response ) );

                    } else {

                        // Verify payment id.
                        $verifyPayment = $checkout->get_builder()->getPaymentsClient()->getPaymentDetails( $response['id'] );
                        $source        = $verifyPayment['source'];

                        // Check if payment source is Fawry or SEPA.
                        if ( 'fawry' === $source['type'] || 'sepa' === $source['type'] ) {
                            return $verifyPayment;
                        }

                        $error_message = esc_html__( 'An error has occurred while processing your payment. Redirection link not found', 'wc_checkout_com' );

                        return array( 'error' => $error_message );
                    }
                } else {

                    return $response;
                }
            } else {
                $error_message = esc_html__( 'An error has occurred while processing your payment. Please check your card details and try again.', 'wc_checkout_com' );

                // check if gateway response is enabled from module settings.
                if ( $gateway_debug ) {
                    $error_message .= $response;
                }

                WC_Checkoutcom_Utility::logger( $error_message, $response );

                return array( 'error' => $error_message );
            }
        } catch ( CheckoutApiException $ex ) {
            $error_message = esc_html__( 'An error has occurred while creating apm payments.', 'wc_checkout_com' );

            // Check if gateway response is enabled from module settings.
            if ( $gateway_debug ) {
                $error_message .= $ex->getMessage();
            }

            WC_Checkoutcom_Utility::logger( $error_message, $ex );

            return array( 'error' => $error_message );
        }
    }

    /**
     * @param $data
     * @param $order
     * @return array
     */
    private static function get_apm_method($data, $order, $payment_method)
    {
        if (!session_id()) session_start();

        $obj = new WC_Gateway_Checkout_Com_APM_Method($data, $order);
        $method = $obj->$payment_method();

        return $method;
    }

    /**
     * Return klarna session
     *
     * @return array|mixed
     */
    public static function klarna_session()
    {
        global $woocommerce;
        $items = $woocommerce->cart->get_cart();
        $products = array();

        $total_amount = $woocommerce->cart->total;
        $amount_cents = WC_Checkoutcom_Utility::valueToDecimal($total_amount, get_woocommerce_currency());

        foreach($items as $item => $values) {
            $_product =  wc_get_product( $values['data']->get_id());

            $getProductDetail = wc_get_product( $values['product_id'] );

            $price_excl_tax = wc_get_price_excluding_tax($getProductDetail);
            $unit_price_cents = WC_Checkoutcom_Utility::valueToDecimal($price_excl_tax, get_woocommerce_currency());

            if($getProductDetail->is_taxable()) {

                $price_incl_tax = wc_get_price_including_tax($getProductDetail);
                $unit_price_cents = WC_Checkoutcom_Utility::valueToDecimal($price_incl_tax, get_woocommerce_currency());
                $tax_amount = $price_incl_tax - $price_excl_tax;
                $total_tax_amount_cents = WC_Checkoutcom_Utility::valueToDecimal($tax_amount, get_woocommerce_currency());
                $tax = WC_Tax::get_rates();
                $reset_tax = reset($tax)['rate'];
                $tax_rate = round($reset_tax);
            } else {
                $tax_rate = 0;
                $total_tax_amount_cents = 0;
            }

            $products[] = array(
                "name" => $_product->get_title(),
                "quantity" => $values['quantity'],
                "unit_price" => $unit_price_cents,
                "tax_rate" => $tax_rate * 100,
                "total_amount" => $unit_price_cents * $values['quantity'],
                "total_tax_amount" => $total_tax_amount_cents ,
                "type" => "physical",
                "reference" => $_product->get_sku(),
                "total_discount_amount" => 0

            );
        }

        $chosen_methods = wc_get_chosen_shipping_method_ids();
        $chosen_shipping = $chosen_methods[0];

        if($chosen_shipping != 'free_shipping') {
            $shipping_amount = WC()->cart->get_shipping_total() ;
            $shipping_amount_cents = WC_Checkoutcom_Utility::valueToDecimal($shipping_amount, get_woocommerce_currency());

            if(WC()->cart->get_shipping_tax() > 0){
                $shipping_amount = WC()->cart->get_shipping_total() + WC()->cart->get_shipping_tax();
                $shipping_amount_cents = WC_Checkoutcom_Utility::valueToDecimal($shipping_amount, get_woocommerce_currency());

                $total_tax_amount = WC()->cart->get_shipping_tax();
                $total_tax_amount_cents = WC_Checkoutcom_Utility::valueToDecimal($total_tax_amount, get_woocommerce_currency());

                $shipping_rates = WC_Tax::get_shipping_tax_rates();
                $vat            = array_shift( $shipping_rates );

                if ( isset( $vat['rate'] ) ) {
                    $shipping_tax_rate = round( $vat['rate'] * 100 );
                } else {
                    $shipping_tax_rate = 0;
                }

            } else {
                $shipping_tax_rate = 0;
                $total_tax_amount_cents = 0;
            }

            $products[] = array(
                "name" => $chosen_shipping,
                "quantity" => 1,
                "unit_price" => $shipping_amount_cents,
                "tax_rate" => $shipping_tax_rate,
                "total_amount" => $shipping_amount_cents,
                "total_tax_amount" => $total_tax_amount_cents,
                "type" => "shipping_fee",
                "reference" => $chosen_shipping,
                "total_discount_amount" => 0
            );
        }

        $total_tax_amount_cents = WC_Checkoutcom_Utility::valueToDecimal( WC()->cart->get_total_tax(), get_woocommerce_currency() );

        $gateway_debug = WC_Admin_Settings::get_option( 'cko_gateway_responses' ) == 'yes';
        $woo_locale    = str_replace( '_', '-', get_locale() );
        $locale        = substr( $woo_locale, 0, 5 );
        $country       = WC()->customer->get_billing_country();

        // Initialize the Checkout Api.
	    $checkout = new Checkout_SDK();

	    try {
		    $CreditSessionRequest                   = new CreditSessionRequest();
		    $CreditSessionRequest->purchase_country = $country;
		    $CreditSessionRequest->currency         = get_woocommerce_currency();
		    $CreditSessionRequest->locale           = strtolower( $locale );
		    $CreditSessionRequest->amount           = $amount_cents;
		    $CreditSessionRequest->tax_amount       = $total_tax_amount_cents;
		    $CreditSessionRequest->products         = $products;

			$builder = $checkout->get_builder();

		    // Klarna support for ABC AC type only.
			if ( method_exists( $builder, 'getKlarnaClient' ) ) {
		        return $builder->getKlarnaClient()->createCreditSession( $CreditSessionRequest );
			}

        } catch ( CheckoutApiException $ex ) {
            $error_message = esc_html__( 'An error has occurred while creating klarna session.', 'wc_checkout_com' );

            // Check if gateway response is enabled from module settings.
            if ( $gateway_debug ) {
                $error_message .= $ex->getMessage();
            }

            WC_Checkoutcom_Utility::logger( $error_message, $ex );

            return array( 'error' => $error_message );
        }
    }

    /**
     * Return cart information
     *
     * @return array
     */
    public static function get_cart_info()
    {
        global $woocommerce;
        $billingAddress = WC()->customer->get_billing();

        $items = $woocommerce->cart->get_cart();
        $products = array();

        $total_amount = $woocommerce->cart->total;
        $amount_cents = WC_Checkoutcom_Utility::valueToDecimal($total_amount, get_woocommerce_currency());

        foreach($items as $item => $values) {
            $_product =  wc_get_product( $values['data']->get_id());

            $getProductDetail = wc_get_product( $values['product_id'] );

            $price_excl_tax = wc_get_price_excluding_tax($getProductDetail);
            $unit_price_cents = WC_Checkoutcom_Utility::valueToDecimal($price_excl_tax, get_woocommerce_currency());

            if($getProductDetail->is_taxable()) {

                $price_incl_tax = wc_get_price_including_tax($getProductDetail);
                $unit_price_cents = WC_Checkoutcom_Utility::valueToDecimal($price_incl_tax, get_woocommerce_currency());
                $tax_amount = $price_incl_tax - $price_excl_tax;
                $total_tax_amount_cents = WC_Checkoutcom_Utility::valueToDecimal($tax_amount, get_woocommerce_currency());

                $tax = WC_Tax::get_rates();
                $reset_tax = reset($tax)['rate'];
                $tax_rate = round($reset_tax);

            } else {
                $tax_rate = 0;
                $total_tax_amount_cents = 0;
            }

            $products[] = array(
                "name" => $_product->get_title(),
                "quantity" => $values['quantity'],
                "unit_price" => $unit_price_cents,
                "tax_rate" => $tax_rate * 100,
                "total_amount" => $unit_price_cents * $values['quantity'],
                "total_tax_amount" => $total_tax_amount_cents ,
                "type" => "physical",
                "reference" => $_product->get_sku(),
                "total_discount_amount" => 0

            );
        }

        $chosen_methods = wc_get_chosen_shipping_method_ids();
        $chosen_shipping = $chosen_methods[0];

        if($chosen_shipping != 'free_shipping') {
            $shipping_amount = WC()->cart->get_shipping_total() ;
            $shipping_amount_cents = WC_Checkoutcom_Utility::valueToDecimal($shipping_amount, get_woocommerce_currency());

            if($shipping_amount_cents > 0) {
                if(WC()->cart->get_shipping_tax() > 0){
                    $shipping_amount = WC()->cart->get_shipping_total() + WC()->cart->get_shipping_tax();
                    $shipping_amount_cents = WC_Checkoutcom_Utility::valueToDecimal($shipping_amount, get_woocommerce_currency());

                    $total_tax_amount = WC()->cart->get_shipping_tax();
                    $total_tax_amount_cents = WC_Checkoutcom_Utility::valueToDecimal($total_tax_amount, get_woocommerce_currency());

                    $shipping_rates = WC_Tax::get_shipping_tax_rates();
                    $vat            = array_shift( $shipping_rates );

                    if ( isset( $vat['rate'] ) ) {
                        $shipping_tax_rate = round( $vat['rate'] * 100 );
                    } else {
                        $shipping_tax_rate = 0;
                    }

                } else {
                    $shipping_tax_rate = 0;
                    $total_tax_amount_cents = 0;
                }

                $products[] = array(
                    "name" => $chosen_shipping,
                    "quantity" => 1,
                    "unit_price" => $shipping_amount_cents,
                    "tax_rate" => $shipping_tax_rate,
                    "total_amount" => $shipping_amount_cents,
                    "total_tax_amount" => $total_tax_amount_cents,
                    "type" => "shipping_fee",
                    "reference" => $chosen_shipping,
                    "total_discount_amount" => 0
                );
            }
        }

        $woo_locale = str_replace("_", "-", get_locale());
        $locale = substr($woo_locale, 0, 5);
        $total_tax_amount_cents = WC_Checkoutcom_Utility::valueToDecimal(WC()->cart->get_total_tax(), get_woocommerce_currency());

        $cartInfo = array(
            "purchase_country" =>  WC()->customer->get_billing_country(),
            "purchase_currency" => get_woocommerce_currency(),
            "locale" => strtolower($locale),
            "billing_address" => array(
                "given_name" => WC()->customer->get_billing_first_name(),
                "family_name" => WC()->customer->get_billing_last_name(),
                "email" => WC()->customer->get_billing_email(),
                "street_address" => WC()->customer->get_billing_address_1(),
                "street_address2" => WC()->customer->get_billing_address_2(),
                "postal_code" => WC()->customer->get_billing_postcode(),
                "city" => WC()->customer->get_billing_city(),
                "region" => WC()->customer->get_billing_city(),
                "phone" => WC()->customer->get_billing_phone(),
                "country" => WC()->customer->get_billing_country(),
            ),
            "order_amount" => $amount_cents,
            "order_tax_amount" => $total_tax_amount_cents,
            "order_lines" => $products
        );

        return $cartInfo;
    }

	public static function generate_apple_token() {
		$apple_token          = $_POST['token'];
		$transaction_id       = $apple_token["header"]["transactionId"];
		$public_key_hash      = $apple_token["header"]["publicKeyHash"];
		$ephemeral_public_key = $apple_token["header"]["ephemeralPublicKey"];
		$version              = $apple_token["version"];
		$signature            = $apple_token["signature"];
		$data                 = $apple_token["data"];

		$checkout = new Checkout_SDK();

		$header = array(
			'transactionId'      => $transaction_id,
			'publicKeyHash'      => $public_key_hash,
			'ephemeralPublicKey' => $ephemeral_public_key,
		);

		try {
			$apple_pay_token_data            = new ApplePayTokenData();
			$apple_pay_token_data->data      = $data;
			$apple_pay_token_data->header    = $header;
			$apple_pay_token_data->signature = $signature;
			$apple_pay_token_data->version   = $version;

			$apple_pay_token_request = new ApplePayTokenRequest();
			$apple_pay_token_request->token_data = $apple_pay_token_data;

			$token = $checkout->get_builder()->getTokensClient()->requestWalletToken( $apple_pay_token_request );

			return $token['token'];

		} catch ( CheckoutApiException $ex ) {
			$error_message = esc_html__( 'An error has occurred while processing your payment.', 'wc_checkout_com' );
			WC_Checkoutcom_Utility::logger( $error_message, $ex );
			die( 'here' );
		}
	}

    /**
     * format_fawry_product
     *
     * @param  mixed $products
     * @param  mixed $amount
     * @return FawryProduct
     */
	public static function format_fawry_product( $products, $amount ) {
		$fawry_product = new FawryProduct();

		foreach ( $products as $product ) {
			$fawry_product->product_id  = 'All_Products';
			$fawry_product->quantity    = 1;
			$fawry_product->price       = $amount;
			$fawry_product->description .= $product['description'] . ',';
		}

		return $fawry_product;
	}

	/**
	 * @param $url
	 * @param $subscription_id
	 *
	 * @return bool
	 */
	public static function mandate_cancel_request( $url, $subscription_id ) {

		if ( empty( $url ) || empty( $subscription_id ) ) {
			return false;
		}

		$core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings' );

        $core_settings['ckocom_sk'] = cko_is_nas_account() ? 'Bearer ' . $core_settings['ckocom_sk'] : $core_settings['ckocom_sk'];

		$wp_request_headers = array(
			'Authorization' => $core_settings['ckocom_sk']
		);

		$wp_response = wp_remote_post(
			$url,
			[
				'headers' => $wp_request_headers
			]
		);

		if ( 200 !== wp_remote_retrieve_response_code( $wp_response ) ) {
			WC_Checkoutcom_Utility::logger(
				sprintf(
					'An error has occurred while mandate cancel Order # %d request. Response code: %d',
					$subscription_id,
					wp_remote_retrieve_response_code( $wp_response )
				),
				null
			);
		}

		return 'OK' === wp_remote_retrieve_response_message( $wp_response ) && 200 === wp_remote_retrieve_response_code( $wp_response );
	}
}
