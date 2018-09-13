<?php

include_once('../../../../wp-load.php');

global $woocommerce;

$result = createPaymentToken($_POST);

echo json_encode($result);

function createPaymentToken($data){ 
    $applePayPaymentData = $data['paymentToken']['paymentData'];

    if(empty($applePayPaymentData)){
        $errorMessage = 'Network error. Empty payment data';
        WC_Checkout_Apple_Pay::log($errorMessage);
        return $errorMessage;
    }

    $myPluginGateway = new WC_Checkout_Apple_Pay();
	$settings = $myPluginGateway->settings;

    $publicKey = $settings['public_key'];
    $endPointMode = $settings['mode'];
    $createTokenUrl = "https://sandbox.checkout.com/api2/tokens";

    if($endPointMode == 'live'){
        $createTokenUrl = "https://api2.checkout.com/tokens";
    }

    $config = array(
        'type' => 'applepay',
        'token_data' => $applePayPaymentData
    );

    // curl to create apple pay token.
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $createTokenUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: '.$publicKey,
        'Content-Type:application/json;charset=UTF-8'
        ));
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($config));

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    $server_output = curl_exec($ch);
    curl_close ($ch);

    $response = json_decode($server_output);

    if(!empty($response->token)){

    	$config = _getChargeData($settings);

    	$postedParam = $config['postedParam'];
        $postedParam['cardToken'] = $response->token;
        $secretKey = $settings['secret_key'];
        $createChargeUrl = "https://sandbox.checkout.com/api2/v2/charges/token";

        if($endPointMode == 'live'){
            $createChargeUrl = "https://api2.checkout.com/v2/charges/token";
        }

        // curl to create apple pay charge at cko
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$createChargeUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: '.$secretKey,
            'Content-Type:application/json;charset=UTF-8'
            ));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postedParam));

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        $server_output = curl_exec($ch);
        curl_close ($ch);

        $response = json_decode($server_output);

    	if($response){
    		if (preg_match('/^1[0-9]+$/', $response->responseCode)) {
    			$order_id = createWoocommerceOrder($response);
                WC_Checkout_Apple_Pay::log('order_id : '.$order_id);

    			if($order_id){
    				global $woocommerce;
    				$order = new WC_Order($order_id);

    				update_post_meta($order_id, '_transaction_id', $response->id);

		            $order->update_status($settings['order_status'], __("Checkout.com Charge Approved (Transaction ID - {$response->id}", 'woocommerce-checkout-apple-pay'));
		            $order->reduce_order_stock();
		            $woocommerce->cart->empty_cart();

		            $url = esc_url($order->get_checkout_order_received_url());

                    // Update charge with trackid
                    $updateChargeUrl = "https://sandbox.checkout.com/api2/v2/charges/".$response->id;
                
                    if($endPointMode == 'live'){
                        $updateChargeUrl = "https://api2.checkout.com/v2/charges/".$response->id;
                    }

                    $data = array("trackId" => $order_id);

                    $request_headers = array();
                    $request_headers[] = 'Authorization: '. $secretKey;
                    $request_headers[] = 'content: application/json';

                    $ch = curl_init($updateChargeUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                    curl_setopt($ch, CURLOPT_POSTFIELDS,http_build_query($data));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    $response = curl_exec($ch);

                    if (curl_error($ch)) {
                        $error_msg = curl_error($ch);
                        $errorMessage = 'Curl error : '.$error_msg;
                        
                        WC_Checkout_Apple_Pay::log($errorMessage);

                        $result = array(
                            'result' => 'ERROR',
                            'errorMessage'=> $errorMessage
                        );
                        
                        return json_encode($result);
                    }
    			}

                $result = array(
                    'result' => 'SUCCESS',
                    'url' => $url
                );

    		} else {
                $errorMessage = 'An error has occured, please verify your payment details and try again. Error code : ';

                $result = array(
                    'result' => 'ERROR',
                    'errorMessage'=> $errorMessage
                );
            }

            return json_encode($result);
    	} else {
            $result = "ERROR";
            $errorMessage = 'An error has occured, please verify your payment details and try again. Response Not Valid';
            WC_Checkout_Apple_Pay::log($errorMessage);

            $result = array(
                'result' => 'ERROR',
                'errorMessage'=> $errorMessage
            );

            return json_encode($result);
        }
    } else {
        $errorMessage = 'An error has occured.';
       	WC_Checkout_Apple_Pay::log($errorMessage);

         $result = array(
                'result' => 'ERROR',
                'errorMessage'=> $errorMessage
            );

        return json_encode($result);
    }
}

function _getChargeData($settings) { 
    global $woocommerce;

    $secretKey = $settings['secret_key'];
    $Api = CheckoutApi_Api::getApi(array('mode' => $settings['mode']));
    $orderTotal = $woocommerce->cart->total;
    $currencyCode = get_woocommerce_currency();
    $amount = $Api->valueToDecimal($orderTotal, $currencyCode);
    $config = array();
    $autoCapture = $settings['payment_action'];
    $config = $_POST['paymentData'];
    $billingAddressConfig = $config['billingDetails'];

    if(isset($config['shippingDetails'])){
    	$shippingAddressConfig = $config['shippingDetails'];
    } else {
    	$shippingAddressConfig = $billingAddressConfig;
    }
    

    /* START: Prepare data */

    /* Get products */
    $items = $woocommerce->cart->get_cart();
	foreach($items as $item => $values) { 
	    $_product =  wc_get_product( $values['data']->get_id()); 
	    $price = get_post_meta($values['product_id'] , '_price', true);

	        $products[] = array(
            'description'   => $_product->get_title(),
            'name'          => $_product->get_title(),
            'price'         => $price,
            'quantity'      => $values['quantity'],
            'sku'           => $values['data']->get_sku()
        );
	} 

	if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }

    $tmp = explode(',', $ip);
	$ip = end($tmp);

    /* END: Prepare data */
    $config['autoCapTime']  = $settings['auto_cap_time'];
    $config['autoCapture']  = $settings['payment_action'] == 'authorize' ? 'N' : 'Y';
    $config['value']        = $amount;
    $config['currency']     = $currencyCode;
    $config['customerName'] = $_POST['firstName'] .' '.$_POST['lastName'];
    $config['customerIp']   = $ip;
    $config['email'] = $_POST['email'];

    $config['shippingDetails']  = $shippingAddressConfig;
    $config['products']         = $products;

    /* Meta */
    $config['metadata'] = array(
        'server'            => get_site_url(),
        'woo_version'       => property_exists($woocommerce, 'version') ? $woocommerce->version : '2.0',
        'plugin_version'    => WC_Checkout_Non_Pci::VERSION,
        'lib_version'       => CheckoutApi_Client_Constant::LIB_VERSION,
        'integration_type'  => 'ApplePay',
        'time'              => date('Y-m-d H:i:s')
    );

    $result['authorization']    = $secretKey;
    $result['postedParam']      = $config;

    return $result;
}

function createWoocommerceOrder($response){
	global $woocommerce;

	$paymentData = $_POST['paymentData']['billingDetails'];

    $package = $woocommerce->shipping->get_packages();
    $shippingMethod = $package[0];
    $chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
    $chosen_shipping = $chosen_methods[0]; 
    $choosenShippingCost = $shippingMethod['rates'][$chosen_shipping]->cost;
    $choosenShippingLabel = $shippingMethod['rates'][$chosen_shipping]->label;

    if(empty($choosenShippingCost) && empty($choosenShippingLabel)){
        $choosenShippingCost = $_POST['paymentData']['chosen_shipping_method']['choosenShippingCost'];
        $choosenShippingLabel = $_POST['paymentData']['chosen_shipping_method']['choosenShippingLabel'];
    }
    
	$address = array(
        'first_name' => $_POST['firstName'],
        'last_name'  => $_POST['lastName'],
        'company'    => '',
        'email'      => $_POST['email'],
        'phone'      => $_POST['phone'],
        'address_1'  => $paymentData['addressLine1'],
        'address_2'  => $paymentData['addressLine2'], 
        'city'       => $paymentData['city'],
        'state'      => $paymentData['state'],
        'postcode'   => $paymentData['postcode'],
        'country'    => $paymentData['country'],
    );

	$items = $woocommerce->cart->get_cart();
    $order = wc_create_order();

	foreach($items as $item => $values) { 
		$_product =  wc_get_product( $values['data']->get_id()); 
		$productId = $values['data']->get_id();
		$qty = $values['quantity'];

		$order->add_product( get_product( $productId ), $qty );
	} 

    $order->set_address( $address, 'billing' );
    $order->set_address( $address, 'shipping' );

    $shipping_tax = array(); 
    $shipping_rate = new WC_Shipping_Rate( '', $choosenShippingLabel, 
                                  $choosenShippingCost, $shipping_tax, 
                                  'custom_shipping_method' );
    $order->add_shipping($shipping_rate);

    $order->calculate_totals();

    update_post_meta( $order->id, '_payment_method', 'woocommerce_checkout_apple_pay' );
    update_post_meta( $order->id, '_payment_method_title', 'Checkout.com Apple Pay' );

    // Store Order ID in session so it can be re-used after payment failure
    WC()->session->order_awaiting_payment = $order->id;

    // Process Payment
    $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
    $result = $available_gateways[ 'woocommerce_checkout_apple_pay' ]->process_payment( $order->id );

    return $order->id;
}