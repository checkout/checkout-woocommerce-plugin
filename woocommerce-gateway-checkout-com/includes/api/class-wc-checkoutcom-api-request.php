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
use Checkout\Models\Payments\IdealSource;
use Checkout\Models\Product;
use Checkout\Models\Sources\Klarna;
use Checkout\Models\Payments\KlarnaSource;
use Checkout\Models\Payments\GiropaySource;
use Checkout\Models\Payments\BoletoSource;
use Checkout\Models\Payments\AlipaySource;
use Checkout\Models\Payments\PoliSource;
use Checkout\Models\Payments\EpsSource;
use Checkout\Models\Payments\BancontactSource;
use Checkout\Models\Payments\KnetSource;
use Checkout\Models\Payments\FawrySource;
use Checkout\Models\Payments\SofortSource;
use Checkout\Models\Payments\QpaySource;
use Checkout\Models\Tokens\ApplePay;
use Checkout\Models\Tokens\ApplePayHeader;
use Checkout\Models\Tokens\GooglePay;
use Checkout\Library\Exceptions\CheckoutHttpException;
use Checkout\Library\Exceptions\CheckoutModelException;


class WC_Checkoutcom_Api_request
{
    /**
     * Create payment and return response
     *
     * @param WC_Order $order
     * @param $arg
     * @return array
     */
    public static function create_payment( WC_Order $order, $arg )
    {
        // Get payment request parameter
        $request_param = WC_Checkoutcom_Api_request::get_request_param($order, $arg);
        $core_settings = get_option('woocommerce_wc_checkout_com_cards_settings');
        $gateway_debug = WC_Admin_Settings::get_option('cko_gateway_responses') == 'yes' ? true : false;

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
                        $error_message = __("An error has occurred while processing your payment. Redirection link not found", 'wc_checkout_com_cards');
                        // Log message
                        WC_Checkoutcom_Utility::logger($error_message , null);

                        return array('error' => $error_message);
                    }
                } else {

                    return $response;
                }
            } else {
                $error_message = __("An error has occurred while processing your payment. Please check your card details and try again. ", 'wc_checkout_com_cards');

                // check if gateway response is enable from module settings
                if ($gateway_debug) {
                    $error_message .= __('Status : ' . $response->status . ', Response summary : ' . $response->response_summary , 'wc_checkout_com_cards');
                }

                // Log message
                WC_Checkoutcom_Utility::logger($error_message , $response);

                return array('error' => $error_message);
            }
        } catch (CheckoutHttpException $ex) {
            $error_message = _("An error has occurred while processing your payment. ", 'wc_checkout_com_cards');

            // check if gateway response is enable from module settings
            if ($gateway_debug) {
                $error_message .= __($ex->getMessage() , 'wc_checkout_com_cards');
            }

            // Log message
            WC_Checkoutcom_Utility::logger($error_message, $ex);

            return array('error' => $error_message);
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
        global $woocommerce, $wp_version;

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
        } elseif($_POST['payment_method'] == 'wc_checkout_com_alternative_payments') {

            $method = WC_Checkoutcom_Api_request::get_apm_method($_POST, $order);
            $payment_option = $method->type;
        }

        if ($method->type != 'klarna') {
            // Set billing address in $method
            $billingAddressParam = new Address();
            $billingAddressParam->address_line1 = $customerAddress['billing_address_1'];
            $billingAddressParam->address_line2 = $customerAddress['billing_address_2'];
            $billingAddressParam->city = $customerAddress['billing_city'];
            $billingAddressParam->state = $customerAddress['billing_state'];
            $billingAddressParam->zip = $customerAddress['billing_postcode'];
            $billingAddressParam->country = $customerAddress['billing_country'];
            $method->billing_address = $billingAddressParam;
        }

        $payment = new Payment($method, $order->get_currency());
        $payment->capture = $auto_capture;
        $payment->amount = $amount_cents;
        $payment->reference = $order->get_order_number();

        $email = $_POST['billing_email'];
        $name = $_POST['billing_first_name'] . ' ' . $_POST['billing_last_name'];

        // Pay Order Page
        $isPayOrder = !empty($_GET['pay_for_order']) ? (boolean)$_GET['pay_for_order'] : false;

        if($isPayOrder) {
            if(!empty($_GET['order_id'])) {
                $order_id    = $_GET['order_id'];
            } else if (!empty($_GET['key'])){
                $order_id    = wc_get_order_id_by_order_key($_GET['key']);
            }

            $order = wc_get_order( $order_id );

            $email = $order->billing_email;
            $name = $order->billing_first_name. ' ' . $order->billing_last_name;
        }


        // Customer
        $payment->customer = array(
          'email' => $email,
          'name' => $name
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
            'order_id' => $order->get_order_number(),
            'server' => get_site_url(),
            'sdk_data' => "PHP SDK v".CheckoutApi::VERSION,
            'integration_data' => "Checkout.com Woocommerce Plugin v".WC_Gateway_Checkout_Com_Cards::PLUGIN_VERSION,
            'platform_data' => "Wordpress v".$wp_version. ", WooCommerce v". $woocommerce->version,
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
     * @return array|mixed
     */
    public static function verify_session( $session_id )
    {
        $core_settings = get_option('woocommerce_wc_checkout_com_cards_settings');
        $environment =  $core_settings['ckocom_environment'] == 'sandbox' ? true : false;
        $gateway_debug = WC_Admin_Settings::get_option('cko_gateway_responses') == 'yes' ? true : false;

        // Initialize the Checkout Api
        $checkout = new CheckoutApi($core_settings['ckocom_sk'], $environment);

        try {

            // Get payment response
            $response = $checkout->payments()->details($session_id);

            // Check if payment is successful
            if ($response->isSuccessful()) {
                return $response;
            } else {
                $error_message = __("An error has occurred while processing your payment. Please check your card details and try again.", 'wc_checkout_com_cards');

                // check if gateway response is enable from module settings
                if ($gateway_debug) {
                    if(isset($response->actions)){
                        $action = $response->actions[0];
                        $error_message .= __('Status : ' . $response->status . ', Response summary : ' . $action['response_summary'] , 'wc_checkout_com_cards');
                    }
                }

                // Log message
                WC_Checkoutcom_Utility::logger($error_message, $response);

                $arr = array('error' => $error_message);

                $metadata = $response->metadata;
                // check if card verification
                if(isset($metadata['card_verification'])){
                    $arr = array(
                        'card_verification' => 'error',
                        'redirection_url' => $metadata['redirection_url']
                        );
                }

                return $arr;
            }

        } catch (CheckoutHttpException $ex) {
            $error_message = _("An error has occurred while processing your payment. ", 'wc_checkout_com_cards');

            // check if gateway response is enable from module settings
            if ($gateway_debug) {
                $error_message .= __($ex->getMessage() , 'wc_checkout_com_cards');
            }

            // Log message
            WC_Checkoutcom_Utility::logger($error_message, $ex);

            return array('error' => $error_message);
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
            $error_message = __('An error has occured while processing your payment.', 'wc_checkout_com_cards' );
            WC_Checkoutcom_Utility::logger($error_message , $ex);
        } catch (CheckoutHttpException $ex) {
            $error_message = __('An error has occured while processing your payment.', 'wc_checkout_com_cards' );
            WC_Checkoutcom_Utility::logger($error_message , $ex);
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
        $gateway_debug = WC_Admin_Settings::get_option('cko_gateway_responses') == 'yes' ? true : false;

        // Initialize the Checkout Api
        $checkout = new CheckoutApi($core_settings['ckocom_sk'], $environment);

        try {
            // Check if payment is already voided or captured on checkout.com hub
            $details = $checkout->payments()->details($cko_payment_id);

            if ($details->status == 'Voided' || $details->status == 'Captured') {
                $error_message = __('Payment has already been voided or captured on Checkout.com hub for order Id : ' . $order_id, 'wc_checkout_com_cards');

                return array('error' => $error_message);
            }

            $ckoPayment = new Capture($cko_payment_id);
            $ckoPayment->amount = $amount_cents;
            $ckoPayment->reference = $order_id;

            $response = $checkout->payments()->capture($ckoPayment);

            if (!$response->isSuccessful()) {
                $error_message = __('An error has occurred while processing your capture payment on Checkout.com hub. Order Id : ' . $order_id, 'wc_checkout_com_cards');

                // check if gateway response is enable from module settings
                if ($gateway_debug) {
                    $error_message .= __($response , 'wc_checkout_com_cards');
                }

                // Log message
                WC_Checkoutcom_Utility::logger($error_message, $response);

                return array('error' => $error_message);
            } else {
                return $response;
            }
        } catch (CheckoutHttpException $ex) {
            $error_message = _("An error has occurred while processing your capture request. ", 'wc_checkout_com_cards');

            // check if gateway response is enable from module settings
            if ($gateway_debug) {
                $error_message .= __($ex->getMessage() , 'wc_checkout_com_cards');
            }

            // Log message
            WC_Checkoutcom_Utility::logger($error_message, $ex);

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
        $gateway_debug = WC_Admin_Settings::get_option('cko_gateway_responses') == 'yes' ? true : false;

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
                $error_message = __('An error has occurred while processing your void payment on Checkout.com hub. Order Id : ' . $order_id, 'wc_checkout_com_cards');

                // check if gateway response is enable from module settings
                if ($gateway_debug) {
                    $error_message .= __($response , 'wc_checkout_com_cards');
                }

                // Log message
                WC_Checkoutcom_Utility::logger($error_message, $response);

                return array('error' => $error_message);
            } else {
                return $response;
            }
        } catch (CheckoutHttpException $ex) {
            $error_message = "An error has occurred while processing your void request.";

            // check if gateway response is enable from module settings
            if ($gateway_debug) {
                $error_message .= __($ex->getMessage() , 'wc_checkout_com_cards');
            }

            // Log message
            WC_Checkoutcom_Utility::logger($error_message, $ex);

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
        $gateway_debug = WC_Admin_Settings::get_option('cko_gateway_responses') == 'yes' ? true : false;

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

                // check if gateway response is enable from module settings
                if ($gateway_debug) {
                    $error_message .= __($response , 'wc_checkout_com_cards');
                }

                // Log message
                WC_Checkoutcom_Utility::logger($error_message, $response);

                return array('error' => $error_message);
            } else {
                return $response;
            }
        } catch (CheckoutHttpException $ex) {
            $error_message = "An error has occurred while processing your refund.";

            // check if gateway response is enable from module settings
            if ($gateway_debug) {
                $error_message .= __($ex->getMessage() , 'wc_checkout_com_cards');
            }

            // Log message
            WC_Checkoutcom_Utility::logger($error_message, $ex);

            return array('error' => $error_message);
        }
    }

    /**
     * Return ideal banks info
     *
     * @return array
     */
    public static function get_ideal_bank()
    {
        $core_settings = get_option('woocommerce_wc_checkout_com_cards_settings');
        $environment =  $core_settings['ckocom_environment'] == 'sandbox' ? true : false;
        $gateway_debug = WC_Admin_Settings::get_option('cko_gateway_responses') == 'yes' ? true : false;

        // Initialize the Checkout Api
        $checkout = new CheckoutApi($core_settings['ckocom_pk'], $environment);

        try {
            $result = $checkout->payments()->issuers(IdealSource::QUALIFIED_NAME);

            return $result;

        } catch (CheckoutHttpException $ex) {
            $error_message = "An error has occured while retrieving ideal bank details.";

            // check if gateway response is enable from module settings
            if ($gateway_debug) {
                $error_message .= __($ex->getMessage() , 'wc_checkout_com_cards');
            }

            // Log message
            WC_Checkoutcom_Utility::logger($error_message, $ex);

            return array('error' => $error_message);
        }
    }

    /**
     * @return array
     */
    public static function get_giropay_bank()
    {
        $core_settings = get_option('woocommerce_wc_checkout_com_cards_settings');
        $environment =  $core_settings['ckocom_environment'] == 'sandbox' ? true : false;
        $gateway_debug = WC_Admin_Settings::get_option('cko_gateway_responses') == 'yes' ? true : false;

        // Initialize the Checkout Api
        $checkout = new CheckoutApi($core_settings['ckocom_sk'], $environment);

        try {
            $result = $checkout->payments()->issuers(GiropaySource::QUALIFIED_NAME);

            return $result;

        } catch (CheckoutHttpException $ex) {
            $error_message = "An error has occured while retrieving giropay bank details..";

            // check if gateway response is enable from module settings
            if ($gateway_debug) {
                $error_message .= __($ex->getMessage() , 'wc_checkout_com_cards');
            }

            // Log message
            WC_Checkoutcom_Utility::logger($error_message, $ex);

            return array('error' => $error_message);
        }
    }

    /**
     * Return EPS Bank
     *
     * @return array
     */
    public static function get_eps_bank()
    {
        $core_settings = get_option('woocommerce_wc_checkout_com_cards_settings');
        $environment =  $core_settings['ckocom_environment'] == 'sandbox' ? true : false;
        $gateway_debug = WC_Admin_Settings::get_option('cko_gateway_responses') == 'yes' ? true : false;

        // Initialize the Checkout Api
        $checkout = new CheckoutApi($core_settings['ckocom_sk'], $environment);

        try {
            $result = $checkout->payments()->issuers(EpsSource::QUALIFIED_NAME);

            return $result;

        } catch (CheckoutHttpException $ex) {
            $error_message = "An error has occured while retrieving eps bank details..";

            // check if gateway response is enable from module settings
            if ($gateway_debug) {
                $error_message .= __($ex->getMessage() , 'wc_checkout_com_cards');
            }

            // Log message
            WC_Checkoutcom_Utility::logger($error_message, $ex);

            return array('error' => $error_message);
        }
    }

    /**
     * @param WC_Order $order
     * @param $arg
     * @return array
     */
    public static function create_apm_payment(WC_Order $order, $arg)
    {
        // Get payment request parameter
        $request_param = WC_Checkoutcom_Api_request::get_request_param($order, $arg);
        $core_settings = get_option('woocommerce_wc_checkout_com_cards_settings');
        $environment =  $core_settings['ckocom_environment'] == 'sandbox' ? true : false;
        $gateway_debug = WC_Admin_Settings::get_option('cko_gateway_responses') == 'yes' ? true : false;

        // Initialize the Checkout Api
        $checkout = new CheckoutApi($core_settings['ckocom_sk'], $environment);


        try {
            // Call to create charge
            $response = $checkout->payments()->request($request_param);

            // Check if payment successful
            if ($response->isSuccessful()) {
                // Check if payment is 3Dsecure
                if ($response->isPending() || $response->status == 'Authorized') {
                    // Check if redirection link exist
                    if ($response->getRedirection()) {
                        // return apm redirection url
                        return array('apm_redirection' => $response->getRedirection());

                    } else {

                        // Verify payment id
                        $verifyPayment = $checkout->payments()->details($response->id);
                        $source = $verifyPayment->source;

                        // Check if payment source if Fawry
                        if ($source['type'] == 'fawry'){

                            return $verifyPayment;
                        }

                        $error_message = "An error has occurred while processing your payment. Redirection link not found";

                        return array('error' => $error_message);
                    }
                } else {

                    return $response;
                }
            } else {
                $error_message = "An error has occurred while processing your payment. Please check your card details and try again.";

                // check if gateway response is enable from module settings
                if ($gateway_debug) {
                    $error_message .= __($response , 'wc_checkout_com_cards');
                }

                // Log message
                WC_Checkoutcom_Utility::logger($error_message, $response);

                return array('error' => $error_message);
            }
        } catch (CheckoutHttpException $ex) {
            $error_message = "An error has occurred while creating apm payments.";

            // check if gateway response is enable from module settings
            if ($gateway_debug) {
                $error_message .= __($ex->getMessage() , 'wc_checkout_com_cards');
            }

            // Log message
            WC_Checkoutcom_Utility::logger($error_message, $ex);

            return array('error' => $error_message);
        }
    }

    /**
     * @param $data
     * @param $order
     * @return IdealSource
     */
    private static function get_apm_method($data, $order)
    {
        $apm_name = $data['cko-apm'];

        switch ($apm_name) {
            case 'ideal':
                $bic = $data['issuer-id'];
                $description = $order->get_order_number();

                $method = new IdealSource($bic, $description);

                break;
            case 'klarna':
                $klarna_token = $_POST['cko-klarna-token'];
                $country_code = $_POST['billing_country'];
                $locale = str_replace("_", "-", get_locale());

                $products = array();
                foreach ($order->get_items() as $item_id => $item_data) {
                    // Get an instance of corresponding the WC_Product object
                    $product = $item_data->get_product();
                    $items = wc_get_product( $product->get_id() );
                    
                    $unit_price = $items->get_price();
                    $amount_cents = WC_Checkoutcom_Utility::valueToDecimal($unit_price, get_woocommerce_currency());
                    $items_total = $unit_price * $item_data->get_quantity();
                    $total_amount_cents = WC_Checkoutcom_Utility::valueToDecimal($items_total, get_woocommerce_currency());

                    // Displaying this data (to check)
                    $products[] = array(
                        "name" => $product->get_name(),
                        "quantity" => $item_data->get_quantity(),
                        "unit_price" => $amount_cents,
                        "tax_rate" => 0,
                        "total_amount" => $total_amount_cents,
                        "total_tax_amount" => 0,
                        "type" => "physical",
                        "reference" => $product->get_name(),
                        "total_discount_amount" => 0
                    );
                }

                $chosen_methods = wc_get_chosen_shipping_method_ids();
                $chosen_shipping = $chosen_methods[0];

                if($chosen_shipping != 'free_shipping') {
                    $shipping_amount = WC()->cart->get_shipping_total();
                    $shipping_amount_cents = WC_Checkoutcom_Utility::valueToDecimal($shipping_amount, get_woocommerce_currency());

                    $products[] = array(
                        "name" => $chosen_shipping,
                        "quantity" => 1,
                        "unit_price" => $shipping_amount_cents,
                        "tax_rate" => 0,
                        "total_amount" => $shipping_amount_cents,
                        "total_tax_amount" => 0,
                        "type" => "shipping_fee",
                        "reference" => $chosen_shipping,
                        "total_discount_amount" => 0
                    );
                }

                // Set Billing address
                $billingAddressParam = new Address();
                $billingAddressParam->given_name = $_POST['billing_first_name'];
                $billingAddressParam->family_name = $_POST['billing_last_name'];
                $billingAddressParam->email = $_POST['billing_email'];
                $billingAddressParam->street_address = $_POST['billing_address_1'];
                // $billingAddressParam->street_address2 = $_POST['billing_address_2'];
                $billingAddressParam->postal_code = $_POST['billing_postcode'];
                $billingAddressParam->city = $_POST['billing_city'];
                $billingAddressParam->region = $_POST['billing_city'];
                $billingAddressParam->phone = $_POST['billing_phone'];
                $billingAddressParam->country = $_POST['billing_country'];

                $method = new KlarnaSource($klarna_token, $country_code, strtolower($locale), $billingAddressParam, 0, $products);

                break;
            case 'giropay' :
                $bic = $data['giropay-bank-details'];
                $purpose = "#{$order->get_order_number()}-{$_SERVER['HTTP_HOST']}";

                $method = new GiropaySource($purpose, $bic);

                break;
            case 'boleto':
                $customerName = $data['name'];
                $birthData = $data['birthDate'];
                $cpf = $data['cpf'];

                $method = new BoletoSource($customerName, $birthData, $cpf);

                break;
            case 'alipay':

                $method = new AlipaySource();

                break;
            case 'poli':

                $method = new PoliSource();

                break;
            case 'sofort':
                $method = new SofortSource();
                break;
            case 'eps':
                $purpose = get_bloginfo( 'name' );

                $method =  new EpsSource($purpose);
                break;
            case 'bancontact':
                $accountHolder = $_POST['billing_first_name'] . ' '. $_POST['billing_last_name'];
                $countryCode = $_POST['billing_country'];

                $method = new BancontactSource($accountHolder, $countryCode);
                break;
            case 'knet':
                $language = get_locale();

                switch ($language) {
                    case 'ar_SA':
                        $language = 'ar';
                        break;
                    default:
                        $language = 'en';
                        break;
                }

                $method = new KnetSource($language);
                break;
            case 'fawry':
                $email = $_POST['billing_email'];
                $phone = $_POST['billing_phone'];

                $products = array();
                foreach ($order->get_items() as $item_id => $item_data) {
                    // Get an instance of corresponding the WC_Product object
                    $product = $item_data->get_product();
                    $item_total = $item_data->get_total(); // Get the item line total
                    $amount_cents = WC_Checkoutcom_Utility::valueToDecimal($item_total, get_woocommerce_currency());
                    $items_total = $item_data->get_total() * $item_data->get_quantity();
                    $total_amount_cents = WC_Checkoutcom_Utility::valueToDecimal($items_total, get_woocommerce_currency());

                    $products[] = array(
                        "product_id" => $product->get_id(),
                        "quantity" => $item_data->get_quantity(),
                        "price" => $amount_cents,
                        "description" => $product->get_name(),
                    );
                }

                $chosen_methods = wc_get_chosen_shipping_method_ids();
                $chosen_shipping = $chosen_methods[0];

                if($chosen_shipping != 'free_shipping') {
                    $shipping_amount = WC()->cart->get_shipping_total();
                    $shipping_amount_cents = WC_Checkoutcom_Utility::valueToDecimal($shipping_amount, get_woocommerce_currency());

                    $products[] = array(
                        "product_id" => $chosen_shipping,
                        "quantity" => 1,
                        "price" => $shipping_amount_cents,
                        "description" => $chosen_shipping,
                    );
                }

                $method = new FawrySource($email, $phone, $order->get_order_number(), $products);
                break;
            case 'qpay':
                $method = new QpaySource(get_bloginfo( 'name' ));
        }

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
            $unit_price = get_post_meta($values['product_id'] , '_price', true);
            $unit_price_cents = WC_Checkoutcom_Utility::valueToDecimal($unit_price, get_woocommerce_currency());

            $products[] = array(
                "name" => $_product->get_title(),
                "quantity" => $values['quantity'],
                "unit_price" => $unit_price_cents,
                "tax_rate" => 0,
                "total_amount" => $unit_price_cents * $values['quantity'],
                "total_tax_amount" => 0,
                "type" => "physical",
                "reference" => $_product->get_sku(),
                "total_discount_amount" => 0

            );
        }

        $chosen_methods = wc_get_chosen_shipping_method_ids();
        $chosen_shipping = $chosen_methods[0];

        if($chosen_shipping != 'free_shipping') {
            $shipping_amount = WC()->cart->get_shipping_total();
            $shipping_amount_cents = WC_Checkoutcom_Utility::valueToDecimal($shipping_amount, get_woocommerce_currency());

            $products[] = array(
                "name" => $chosen_shipping,
                "quantity" => 1,
                "unit_price" => $shipping_amount_cents,
                "tax_rate" => 0,
                "total_amount" => $shipping_amount_cents,
                "total_tax_amount" => 0,
                "type" => "shipping_fee",
                "reference" => $chosen_shipping,
                "total_discount_amount" => 0
            );

        }

        $core_settings = get_option('woocommerce_wc_checkout_com_cards_settings');
        $environment =  $core_settings['ckocom_environment'] == 'sandbox' ? true : false;
        $gateway_debug = WC_Admin_Settings::get_option('cko_gateway_responses') == 'yes' ? true : false;
        $locale = str_replace("_", "-", get_locale());
        $country = WC()->customer->get_billing_country();

        // Initialize the Checkout Api
        $checkout = new CheckoutApi($core_settings['ckocom_sk'], $environment);

        try{
            $klarna = new Klarna($country, get_woocommerce_currency(), strtolower($locale), $amount_cents, 0, $products);
            $source = $checkout->sources()
                ->add($klarna);

            return $source;

        } catch (CheckoutHttpException $ex) {
            $error_message = "An error has occured while creating klarna session.";

            // check if gateway response is enable from module settings
            if ($gateway_debug) {
                $error_message .= __($ex->getMessage() , 'wc_checkout_com_cards');
            }

            // Log message
            WC_Checkoutcom_Utility::logger($error_message, $ex);

            return array('error' => $error_message);
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
            $unit_price = get_post_meta($values['product_id'] , '_price', true);
            $unit_price_cents = WC_Checkoutcom_Utility::valueToDecimal($unit_price, get_woocommerce_currency());

            $products[] = array(
                "name" => $_product->get_title(),
                "quantity" => $values['quantity'],
                "unit_price" => $unit_price_cents,
                "tax_rate" => 0,
                "total_amount" => $unit_price_cents * $values['quantity'],
                "total_tax_amount" => 0,
                "type" => "physical",
                "reference" => $_product->get_sku(),
                "total_discount_amount" => 0

            );
        }

        $chosen_methods = wc_get_chosen_shipping_method_ids();
        $chosen_shipping = $chosen_methods[0];

        if($chosen_shipping != 'free_shipping') {
            $shipping_amount = WC()->cart->get_shipping_total();
            $shipping_amount_cents = WC_Checkoutcom_Utility::valueToDecimal($shipping_amount, get_woocommerce_currency());

            $products[] = array(
                "name" => $chosen_shipping,
                "quantity" => 1,
                "unit_price" => $shipping_amount_cents,
                "tax_rate" => 0,
                "total_amount" => $shipping_amount_cents,
                "total_tax_amount" => 0,
                "type" => "shipping_fee",
                "reference" => $chosen_shipping,
                "total_discount_amount" => 0
            );

        }

        $locale = str_replace("_", "-", get_locale());

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
            "order_tax_amount" => 0,
            "order_lines" => $products
        );

        return $cartInfo;
    }
}