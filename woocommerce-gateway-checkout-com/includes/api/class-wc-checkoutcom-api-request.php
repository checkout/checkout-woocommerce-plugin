<?php

include 'class-wc-checkoutcom-utility.php';

use Checkout\CheckoutApi;
use Checkout\Models\Address;
use Checkout\Models\Payments\Capture;
use Checkout\Models\Payments\Payment;
use Checkout\Models\Payments\Refund;
use Checkout\Models\Payments\Shipping;
use Checkout\Models\Payments\ThreeDs;
use Checkout\Models\Payments\TokenSource;
use Checkout\Models\Payments\Voids;
use Checkout\Models\Payments\BillingDescriptor;
use Checkout\Models\Phone;
use Checkout\Models\Payments\IdSource;
use Checkout\Models\Tokens\ApplePay;
use Checkout\Models\Tokens\ApplePayHeader;
use Checkout\Models\Tokens\GooglePay;
use Checkout\Library\Exceptions\CheckoutHttpException;
use Checkout\Library\Exceptions\CheckoutModelException;

class WC_Checkoutcom_Api_request
{
    public static function create_payment( WC_Order $order, $arg )
    {
        $logger = wc_get_logger();
        $context = array( 'source' => 'wc_checkoutcom_gateway_log' );

        // Get payment request parameter
        $request_param = WC_Checkoutcom_Api_request::get_request_param($order, $arg);
        $core_settings = get_option('woocommerce_wc_checkout_com_cards_settings');

        $environment =  $core_settings['ckocom_environment'] == 'sandbox' ? true : false;

        // Initialize the Checkout Api
        $checkout = new CheckoutApi($core_settings['ckocom_sk'], $environment);

        try {
            // Call to create charge
            $response = $checkout->payments()->request($request_param);

            // Check if payment successful
            if ($response->isSuccessful()) {
                // Check if payment is 3Dsecure
                if ($response->isPending()) {
                    // Check if redirection link exist
                    if ($response->getRedirection()) {
                        // return 3d redirection url
                        return array('3d' => $response->getRedirection());

                    } else {
                        $errorMessage = "An error has occurred while processing your payment. Redirection link not found";
                        $logger->error($errorMessage, $context );

                        return array('error' => $errorMessage);
                    }
                } else {

                    return $response;
                }
            } else {
                $errorMessage = "An error has occurred while processing your payment. Please check your card details and try again.";
                //Log error
                $logger->error($errorMessage, $context );
                $logger->error(wc_print_r($response, true), $context );

                return array('error' => $errorMessage);
            }
        } catch (CheckoutModelException $ex) {
            $errorMessage = "An error has occurred while processing your payment. ";
            $logger->error($errorMessage, $context );
            $logger->error(wc_print_r($ex, true), $context );

            return array('error' => $errorMessage);
        } catch (CheckoutHttpException $ex) {
            $errorMessage = "An error has occurred while processing your payment. ";
            $logger->error($errorMessage, $context );
            $logger->error(wc_print_r($ex->getBody(), true), $context );

            return array('error' => $errorMessage);
        }
    }

    /**
     * Build payment request parameter
     * @param $order
     * @param $card_token
     * @return Payment
     */
    private static function get_request_param(WC_Order $order, $arg)
    {
        global $woocommerce;

        $auto_capture = WC_Admin_Settings::get_option('ckocom_card_autocap') == 1 ? true : false;
        $amount = $order->get_total();
        $amount_cents = WC_Checkoutcom_Utility::valueToDecimal($amount, $order->get_currency());
        $three_d = WC_Admin_Settings::get_option('ckocom_card_threed') == 1 ? true : false;
        $attempt_no_threeD = WC_Admin_Settings::get_option('ckocom_card_notheed') == 1 ? true : false;
        $dynamic_descriptor = WC_Admin_Settings::get_option('ckocom_card_desctiptor') == 1 ? true : false;
        $mada_enable = WC_Admin_Settings::get_option('ckocom_card_mada') == 1 ? true : false;
        $is_save_card = false;
        $payment_option = 'FramesJs';

        $customerAddress = WC_Checkoutcom_Api_request::customer_address($_POST);

        // Prepare payment parameters
        if($_POST['payment_method'] == 'wc_checkout_com_cards'){
            if($_POST['wc-wc_checkout_com_cards-payment-token']) {
                if($_POST['wc-wc_checkout_com_cards-payment-token'] == 'new') {
                    $method = new TokenSource($arg);
                } else {
                    // load token by id ($arg)
                    $token = WC_Payment_Tokens::get( $arg );
                    // Get source_id from $token
                    $source_id = $token->get_token();

                    $method = new IdSource($source_id);

                    $is_save_card = true;

                    if(WC_Admin_Settings::get_option('ckocom_card_require_cvv')) {
                        $method->cvv = $_POST['wc_checkout_com_cards-card-cvv'];
                    }
                }
            } else {
                $method = new TokenSource($arg);
            }
        } elseif ($_POST['payment_method'] == 'wc_checkout_com_google_pay') {
            $payment_option = 'Google Pay';

            $method = new TokenSource($arg);
        }

        // Set billing address in $method
        $billingAddressParam = new Address();
        $billingAddressParam->address_line1 = $customerAddress['billing_address_1'];
        $billingAddressParam->address_line2 = $customerAddress['billing_address_2'];
        $billingAddressParam->city = $customerAddress['billing_city'];
        $billingAddressParam->state = $customerAddress['billing_state'];
        $billingAddressParam->zip = $customerAddress['billing_postcode'];
        $billingAddressParam->country = $customerAddress['billing_country'];
        $method->billing_address = $billingAddressParam;

        $payment = new Payment($method, $order->get_currency());
        $payment->capture = $auto_capture;
        $payment->amount = $amount_cents;
        $payment->reference = $order->get_order_number();

        // Customer
        $payment->customer = array(
          'email' => $_POST['billing_email'],
          'name' => $_POST['billing_first_name'] . ' ' . $_POST['billing_last_name']
        );

        $three_ds = new ThreeDs($three_d);

        if ($three_ds) {
            $three_ds->attempt_n3d = $attempt_no_threeD;
        }

        if($dynamic_descriptor){
            $descriptor_name = WC_Admin_Settings::get_option('ckocom_card_desctiptor_name');
            $descriptor_city = WC_Admin_Settings::get_option('ckocom_card_desctiptor_city');
            $descriptor = new BillingDescriptor($descriptor_name, $descriptor_city);
            $payment->billing_descriptor = $descriptor;
        }

        // Set 3Ds to payment request
        $payment->threeDs = $three_ds;

        // Set shipping Address
        $shippingAddressParam = new Address();
        $shippingAddressParam->address_line1 = $customerAddress['shipping_address_1'];
        $shippingAddressParam->address_line2 = $customerAddress['shipping_address_2'];
        $shippingAddressParam->city = $customerAddress['shipping_city'];
        $shippingAddressParam->state = $customerAddress['shipping_state'];
        $shippingAddressParam->zip = $customerAddress['shipping_postcode'];
        $shippingAddressParam->country = $customerAddress['shipping_country'];

        $phone = new Phone();
        $phone->number = $_POST['billing_phone'];

        $payment->shipping = new Shipping($shippingAddressParam, $phone);

        // Set redirection url in payment request
        $redirection_url = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'wc_checkoutcom_callback', home_url( '/' ) ) );
        $payment->success_url = $redirection_url;
        $payment->failure_url = $redirection_url;

        $metadata = array(
            'server' => get_site_url(),
            'order_id' => $order->get_order_number(),
            'woo_version' =>  $woocommerce->version,
            'plugin_version' => WC_Gateway_Checkout_Com_Cards::PLUGIN_VERSION,
            'lib_version' => CheckoutApi::VERSION,
            'integration_type' => $payment_option,
            'time' => date('Y-m-d H:i:s'),
            'udf5' => 'Woocommerce - '. $woocommerce->version
                . ', Checkout Plugin - ' . WC_Gateway_Checkout_Com_Cards::PLUGIN_VERSION
                . ', Php Sdk - '. CheckoutApi::VERSION
        );

        // set capture delay if payment action is authorise and capture
        if($auto_capture){
            $captureDelay =   WC_Checkoutcom_Utility::getDelayedCaptureTimestamp();
            $payment->capture_on = $captureDelay;
        }

        // check if mada is enable in module setting
        if($mada_enable){
            $is_mada = false;

            if(!empty($_POST['cko-card-bin'])){
                $is_mada = WC_Checkoutcom_Utility::isMadaCard($_POST['cko-card-bin']);
            } else {

                if($is_save_card) {
                    // check if souce_id is a mada card
                    // load token by id ($arg)
                    $token = WC_Payment_Tokens::get( $arg );
                    // check if source_id is mada
                    $is_mada = $token->get_meta('is_mada');

                    if($is_mada){
                        $method->cvv = $_POST['wc_checkout_com_cards-card-cvv'];
                    }
                }
            }

            if($is_mada) {
                $payment->capture = true;
                $payment->capture_on = null;
                $payment->threeDs =  new ThreeDs(true);
                $metadata = array_merge($metadata, array('udf1' => 'Mada'));
            }

            // Set is_mada in session
            $_SESSION['cko-is-mada'] = $is_mada;
        }

        // Set metadata info in payment request
        $payment->metadata = $metadata;

        return $payment;
    }

    /**
     * @param $data
     * @return array
     */
    private static function customer_address($data)
    {
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

    public static function verify_session( $session_id )
    {
        $logger = wc_get_logger();
        $context = array( 'source' => 'wc_checkoutcom_gateway_log' );
        $core_settings = get_option('woocommerce_wc_checkout_com_cards_settings');

        $environment =  $core_settings['ckocom_environment'] == 'sandbox' ? true : false;

        // Initialize the Checkout Api
        $checkout = new CheckoutApi($core_settings['ckocom_sk'], $environment);

        try {

            $response = $checkout->payments()->details($session_id);

            if ($response->isSuccessful()) {
                return $response;
            } else {
                $errorMessage = "An error has occurred while processing your payment. Please check your card details and try again.";
                //Log error
                $logger->error($errorMessage, $context );
                $logger->error(wc_print_r($response, true), $context );

                return array('error' => $errorMessage);
            }

        } catch (CheckoutModelException $ex) {
            $errorMessage = "An error has occurred while processing your payment. ";
            $logger->error($errorMessage, $context );
            $logger->error(wc_print_r($ex, true), $context );

            return array('error' => $errorMessage);
        } catch (CheckoutHttpException $ex) {
            $errorMessage = "An error has occurred while processing your payment. ";
            $logger->error($errorMessage, $context );
            $logger->error(wc_print_r($ex->getBody(), true), $context );

            return array('error' => $errorMessage);
        }

    }

    /**
     * Generate the google token from google payment data
     * @return mixed
     */
    public static function generate_google_token()
    {
        $core_settings = get_option('woocommerce_wc_checkout_com_cards_settings');
        $googleToken = $_REQUEST['token'];
        $publicKey = $core_settings['ckocom_pk'];
        $protocolVersion = $_POST["cko-google-protocolVersion"];
        $signature = $_POST["cko-google-signature"];
        $signedMessage = stripslashes($_POST['cko-google-signedMessage']);
        $environment =  $core_settings['ckocom_environment'] == 'sandbox' ? true : false;

        $checkout = new CheckoutApi();
        $checkout->configuration()->setPublicKey($publicKey);
        $checkout->configuration()->setSandbox($environment);

        $googlepay = new GooglePay($protocolVersion, $signature, $signedMessage);

        try {
            $token = $checkout->tokens()->request($googlepay);

            return $token->getId();
        } catch (CheckoutModelException $ex) {
            $error_message = __('An error has occured while processing your payment.',wc_checkout_com_cards );
            WC_Checkoutcom_Utility::logger($error_message , $ex->getBody());
        } catch (CheckoutHttpException $ex) {
            $error_message = __('An error has occured while processing your payment. 2',wc_checkout_com_cards );
            WC_Checkoutcom_Utility::logger($error_message , $ex->getBody());
        }
    }

    /**
     * Perform capture
     *
     * @return array|mixed
     */
    public static function capture_payment()
    {
        $order_id = $_POST['post_ID'];
        $cko_payment_id = get_post_meta($order_id, '_cko_payment_id', true );

        // Check if cko_payment_id is empty
        if(empty($cko_payment_id)){
            $error_message = __('An error has occured. No Cko Payment Id');
            return array('error' => $error_message);
        }

        $order = wc_get_order( $order_id );
        $amount = $order->get_total();
        $amount_cents = WC_Checkoutcom_Utility::valueToDecimal($amount, $order->get_currency());
        $core_settings = get_option('woocommerce_wc_checkout_com_cards_settings');
        $environment =  $core_settings['ckocom_environment'] == 'sandbox' ? true : false;

        // Initialize the Checkout Api
        $checkout = new CheckoutApi($core_settings['ckocom_sk'], $environment);

        try {
            // Check if payment is already voided or captured on checkout.com hub
            $details = $checkout->payments()->details($cko_payment_id);

            if ($details->status == 'Voided' || $details->status == 'Captured') {
                $error_message = 'Payment has already been voided or captured on Checkout.com hub for order Id : ' . $order_id;

                return array('error' => $error_message);
            }

            $ckoPayment = new Capture($cko_payment_id);
            $ckoPayment->amount = $amount_cents;
            $ckoPayment->reference = $order_id;

            $response = $checkout->payments()->capture($ckoPayment);

            if (!$response->isSuccessful()) {
                $error_message = 'An error has occurred while processing your capture payment on Checkout.com hub. Order Id : ' . $order_id;

                return array('error' => $error_message);
            } else {
                return $response;
            }
        } catch (CheckoutModelException $ex) {
            $error_message = "An error has occurred while processing your capture request.";
            WC_Checkoutcom_Utility::logger($error_message , $ex->getBody());

            return array('error' => $error_message);

        } catch (CheckoutHttpException $ex) {
            $error_message = "An error has occurred while processing your capture request.";
            WC_Checkoutcom_Utility::logger($error_message , $ex->getBody());

            return array('error' => $error_message);
        }
    }

    /**
     * Perform Void
     * @return array|mixed
     */
    public static function void_payment()
    {
        $order_id = $_POST['post_ID'];
        $cko_payment_id = get_post_meta($order_id, '_cko_payment_id', true );

        // Check if cko_payment_id is empty
        if(empty($cko_payment_id)){
            $error_message = __('An error has occured. No Cko Payment Id');
            return array('error' => $error_message);
        }

        $order = wc_get_order( $order_id );
        $core_settings = get_option('woocommerce_wc_checkout_com_cards_settings');
        $environment =  $core_settings['ckocom_environment'] == 'sandbox' ? true : false;

        // Initialize the Checkout Api
        $checkout = new CheckoutApi($core_settings['ckocom_sk'], $environment);

        try {
            // Check if payment is already voided or captured on checkout.com hub
            $details = $checkout->payments()->details($cko_payment_id);

            if ($details->status == 'Voided' || $details->status == 'Captured') {
                $error_message = 'Payment has already been voided or captured on Checkout.com hub for order Id : ' . $order_id;

                return array('error' => $error_message);
            }

            // Prepare void payload
            $ckoPayment = new Voids($cko_payment_id, $order_id);

            // Process void payment on checkout.com
            $response = $checkout->payments()->void($ckoPayment);

            if (!$response->isSuccessful()) {
                $error_message = 'An error has occurred while processing your void payment on Checkout.com hub. Order Id : ' . $order_id;

                return array('error' => $error_message);
            } else {
                return $response;
            }
        } catch (CheckoutModelException $ex) {
            $error_message = "An error has occurred while processing your void request.";
            WC_Checkoutcom_Utility::logger($error_message , $ex->getBody());

            return array('error' => $error_message);
        } catch (CheckoutHttpException $ex) {
            $error_message = "An error has occurred while processing your void request.";
            WC_Checkoutcom_Utility::logger($error_message , $ex->getBody());

            return array('error' => $error_message);
        }
    }

    /**
     * Perform Refund
     * @param $order_id
     * @param $order
     * @return array|mixed
     */
    public static function refund_payment($order_id, $order)
    {
        $cko_payment_id = get_post_meta($order_id, '_cko_payment_id', true );

        // Check if cko_payment_id is empty
        if(empty($cko_payment_id)){
            $error_message = __('An error has occured. No Cko Payment Id');
            return array('error' => $error_message);
        }

        $order_amount = $order->get_total();
        $order_amount_cents = WC_Checkoutcom_Utility::valueToDecimal($order_amount, $order->get_currency());
        $refund_amount = $_POST['refund_amount'];
        $refund_amount_cents = WC_Checkoutcom_Utility::valueToDecimal($refund_amount, $order->get_currency());

        // Check if refund amount is less than order amount
        $refund_is_less = $refund_amount_cents < $order_amount_cents ? true : false;

        $core_settings = get_option('woocommerce_wc_checkout_com_cards_settings');
        $environment =  $core_settings['ckocom_environment'] == 'sandbox' ? true : false;

        // Initialize the Checkout Api
        $checkout = new CheckoutApi($core_settings['ckocom_sk'], $environment);

        try {
            // Check if payment is already voided or captured on checkout.com hub
            $details = $checkout->payments()->details($cko_payment_id);

            if ($details->status == 'Refunded' && !$refund_is_less) {
                $error_message = 'Payment has already been refunded on Checkout.com hub for order Id : ' . $order_id;

                return array('error' => $error_message);
            }

            $ckoPayment = new Refund($cko_payment_id);

            // Process partial refund if amount is less than order amount
            if ($refund_is_less) {
                $ckoPayment->amount = $refund_amount_cents;
                $ckoPayment->reference = $order_id;

                // Set is_mada in session
                $_SESSION['cko-refund-is-less'] = $refund_is_less;
            }

            $response = $checkout->payments()->refund($ckoPayment);

            if (!$response->isSuccessful()) {
                $error_message = 'An error has occurred while processing your refund payment on Checkout.com hub. Order Id : ' . $order_id;

                return array('error' => $error_message);
            } else {
                return $response;
            }
        } catch (CheckoutModelException $ex) {
            $error_message = "An error has occurred while processing your refund request. ";
            WC_Checkoutcom_Utility::logger($error_message , $ex->getBody());

            return array('error' => $error_message);
        } catch (CheckoutHttpException $ex) {
            $error_message = "An error has occurred while processing your refund request. ";
            WC_Checkoutcom_Utility::logger($error_message , $ex->getBody());

            return array('error' => $error_message);
        }
    }
}