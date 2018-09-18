<?php

include_once ('lib/autoload.php');

/**
 * Class WC_Checkout_Non_Pci
 *
 * @version 20160304
 */
class WC_Checkout_Google_Pay extends WC_Payment_Gateway {

    const PAYMENT_METHOD_CODE       = 'woocommerce_checkout_google_pay';
    const PAYMENT_ACTION_AUTHORIZE  = 'authorize';
    const PAYMENT_ACTION_CAPTURE    = 'authorize_capture';
    const PAYMENT_CARD_NEW_CARD     = 'new_card';
    const AUTO_CAPTURE_TIME         = 0;
    const VERSION                   = '3.2.0';

    public static $log = false;

    /**
     * Constructor
     *
     * WC_Checkout_Non_Pci constructor.
     *
     * @version 20160304
     */
    public function __construct() {
        $this->id                   = self::PAYMENT_METHOD_CODE;
        $this->method_title         = __("Checkout.com Google Pay", 'woocommerce-checkout-google-pay');
        $this->method_description   = __("Checkout.com Google Pay Plug-in for WooCommerce", 'woocommerce-checkout-google-pay');
        $this->title                = __("Checkout.com Google Pay", 'woocommerce-checkout-google-pay');

        $this->icon         = plugins_url('/view/image/google-pay2.png',__FILE__);
        $this->supports     = array(
            'products',
            'refunds'
        );
        $this->has_fields   = true;

        // This basically defines your settings which are then loaded with init_settings()
        $this->init_form_fields();
        $this->init_settings();

        // Turn these settings into variables we can use
        foreach ( $this->settings as $setting_key => $value ) {
            $this->$setting_key = $value;
        }

        // Save settings
        if (is_admin()) {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }


        // Redirection hook
        $this->callback = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'WC_Checkout_Non_Pci_Callback', home_url( '/' ) ) );
        add_action( 'woocommerce_api_wc_checkout_non_pci_callback', array( $this, 'callback' ) );

        // Process webhook action hook
        $this->webhook = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'WC_Checkout_Non_Pci_Webhook', home_url( '/' ) ) );
        add_action( 'woocommerce_api_wc_checkout_non_pci_webhook', array( $this, 'webhook' ) );

    }

    /**
     * init admin settings form
     *
     * @version 20160304
     */
    public function init_form_fields() { 

        $this->form_fields = array(
            'enabled' => array(
                'title'     => __( 'Enable / Disable', 'woocommerce-checkout-google-pay' ),
                'label'     => __( 'Enable Payment Method', 'woocommerce-checkout-google-pay' ),
                'type'      => 'checkbox',
                'default'   => 'no',
            ),
            'title' => array(
                'title'     => __('Title', 'woocommerce-checkout-google-pay'),
                'type'      => 'text',
                'desc_tip'  => __('Payment title the customer will see during the checkout process.', 'woocommerce-checkout-google-pay'),
                'default'   => __( "Google Pay", 'woocommerce-checkout-google-pay' ),
            ),
            'gpay_merchant_id' => array(
                'title'     => __('Google Pay Merchant Id', 'woocommerce-checkout-google-pay'),
                'type'      => 'password',
                'desc_tip'  => __( 'Merchant Id from Google Pay', 'woocommerce-checkout-google-pay' ),
            ),
            'secret_key' => array(
                'title'     => __('Secret Key', 'woocommerce-checkout-google-pay'),
                'type'      => 'password',
                'desc_tip'  => __( 'Only used for requests from the merchant server to the Checkout API', 'woocommerce-checkout-google-pay' ),
            ),
            'public_key' => array(
                'title'     => __('Public Key', 'woocommerce-checkout-google-pay'),
                'type'      => 'password',
                'desc_tip'  => __( 'Used for JS Checkout API', 'woocommerce-checkout-google-pay' ),
            ),
            'private_shared_key' => array(
                'title'     => __('Private Shared Key', 'woocommerce-checkout-google-pay'),
                'type'      => 'password',
                'desc_tip'  => __( 'Used for webhooks from Checkout API', 'woocommerce-checkout-google-pay' ),
                'description' => __( 'To get the Private Shared Key please configure a Webhook URL in the Checkout HUB.', 'woocommerce-checkout-google-pay' ),
            ),
            'void_status' => array(
                'title'     => __( 'Enable / Disable', 'woocommerce-checkout-google-pay' ),
                'label'     => __( 'When voided change order status to Cancelled', 'woocommerce-checkout-google-pay' ),
                'type'      => 'checkbox',
                'default'   => 'no',
            ),
            'payment_action' => array(
                'title'       => __('Payment Action', 'woocommerce-checkout-google-pay'),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'description' => __('Choose whether you wish to capture funds immediately or authorize payment only.', 'woocommerce-checkout-google-pay'),
                'default'     => 'authorize',
                'desc_tip'    => true,
                'options'     => array(
                    self::PAYMENT_ACTION_CAPTURE    => __('Authorize and Capture', 'woocommerce-checkout-google-pay'),
                    self::PAYMENT_ACTION_AUTHORIZE  => __('Authorize Only', 'woocommerce-checkout-google-pay')
                )
            ),
            'auto_cap_time' => array(
                'title'     => __('Auto Capture Time', 'woocommerce-checkout-google-pay'),
                'type'      => 'text',
                'desc_tip'  => __('Time to automatically capture charge. It is recommended to set it to a minimun of 0.02', 'woocommerce-checkout-google-pay'),
                'default'   => __( '0.02', 'woocommerce-checkout-google-pay' ),
            ),
            'order_status' => array(
                'title'       => __('New Order Status', 'woocommerce-checkout-google-pay'),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'default'     => 'on-hold',
                'desc_tip'    => true,
                'options'     => array(
                    'on-hold'    => __('On Hold', 'woocommerce-checkout-google-pay'),
                    'processing' => __('Processing', 'woocommerce-checkout-google-pay')
                )
            ),
            'mode' => array(
                'title'       => __('Endpoint URL mode', 'woocommerce-checkout-google-pay'),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'description' => __('When going on live production, Endpoint url mode should be set to live.', 'woocommerce-checkout-google-pay'),
                'default'     => 'sandbox',
                'desc_tip'    => true,
                'options'     => array(
                    'sandbox'   => __('SandBox', 'woocommerce-checkout-google-pay'),
                    'live'      => __('Live', 'woocommerce-checkout-google-pay')
                )
            ),
            'timeout' => array(
                'title'     => __('Timeout value for a request to the gateway', 'woocommerce-checkout-google-pay'),
                'type'      => 'text',
                'desc_tip'  => __('The timeout value for a request to the gateway. Default is 60 seconds. Please notify checkout.com support team before increasing the value.', 'woocommerce-checkout-google-pay'),
                'default'   => __( '60', 'woocommerce-checkout-google-pay' ),
            ),
        );
    }

    /**
     * Create Charge on Checkout.com
     *
     * @param int $order_id
     *
     */
    public function process_payment($order_id) {
        include_once( 'includes/class-wc-gateway-checkout-non-pci-request.php');
        include_once( 'includes/class-wc-gateway-checkout-non-pci-validator.php');
        include_once( 'includes/class-wc-gateway-checkout-non-pci-customer-card.php');

        if (!session_id()) session_start();

        global $woocommerce;

        $googlePayData = $_POST["payment"];    

        $signature = $googlePayData["cko-google-signature"];
        $protocolVersion = $googlePayData["cko-google-protocolVersion"];
        $signedMessage = $googlePayData["cko-google-signedMessage"];
        $publicKey = $this->get_option('public_key');
        $endPointMode = $this->get_option('mode');

        $createTokenUrl = "https://sandbox.checkout.com/api2/tokens";

        if($endPointMode == 'live'){
            $createTokenUrl = "https://api2.checkout.com/tokens";
        }

        $token_data = array(
            'signature' => $signature,
            'protocolVersion' => $protocolVersion,
            'signedMessage' => stripslashes($signedMessage)
        );
        
        //  GET TOKEN
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $createTokenUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: '.$publicKey,
            'Content-Type:application/json;charset=UTF-8'
            ));
        curl_setopt($ch, CURLOPT_POSTFIELDS,
            json_encode( array(
                'type' => 'googlepay',
                'token_data' => $token_data,
            )));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        $server_output = curl_exec($ch);
        curl_close ($ch);

        $response = json_decode($server_output);
        $GoogleToken = $response->token;

        if(!empty($GoogleToken)){
            $order          = new WC_Order($order_id);
            $amount     = $order->get_total();
            $currencyCode = $order->get_currency();
            $Api        = CheckoutApi_Api::getApi(array('mode' => $this->get_option('mode')));
            $amount     = $Api->valueToDecimal($amount, $currencyCode);
            $config = $this->_getChargeData($order, $GoogleToken, $amount);
            $result     = $Api->createCharge($config);

            if ($Api->getExceptionState()->hasError()) {
                $errorMessage = '-Your payment was not completed. Try again or contact customer support.';

                WC_Checkout_Google_Pay::log($errorMessage. '-'.$errorCode);
                WC_Checkout_Google_Pay::log($Api->getExceptionState()->getErrorMessage());

                WC_Checkout_Non_Pci_Validator::wc_add_notice_self($this->gerProcessErrorMessage($errorMessage. '-'.$errorCode), 'error');
                return ;
            }

            if (!$result->isValid() || !WC_Checkout_Non_Pci_Validator::responseValidation($result)) {
                $errorMessage = "Please check you card details and try again. Thank you.";
                
                WC_Checkout_Google_Pay::log($errorMessage. '-' .$responseCode);
                WC_Checkout_Google_Pay::log($result->getResponseCode());

                WC_Checkout_Non_Pci_Validator::wc_add_notice_self($this->gerProcessErrorMessage($errorMessage. '-'.$errorCode), 'error');
                return ;
            }

            $entityId       = $result->getId();

            update_post_meta($order_id, '_transaction_id', $entityId);

            $order->update_status($this->getOrderStatus(), __("Checkout.com Charge Approved (Transaction ID - {$entityId}", 'woocommerce-checkout-google-pay'));
            $order->reduce_order_stock();
            $woocommerce->cart->empty_cart();

            return array(
                'result'        => 'success',
                'redirect'      => $this->get_return_url($order)
            );
            
        } else {
            $errorMessage = 'An error has occured, please verify your payment details and try again.';
            WC_Checkout_Google_Pay::log($response);

            WC_Checkout_Non_Pci_Validator::wc_add_notice_self($this->gerProcessErrorMessage($errorMessage), 'error');
                return ;
        }

    }

    // Get order status from settings
    public function getOrderStatus() {
        return $this->get_option('order_status');
    }

    // Get charge data
    private function _getChargeData(WC_Order $order, $chargeToken, $amount) { 
        global $woocommerce;

        $secretKey = $this->get_option('secret_key');
        $Api = CheckoutApi_Api::getApi(array('mode' => $this->get_option('mode')));
        $config = array();
        $autoCapture = $this->get_option('payment_action');
        $integrationType = $this->get_option('integration_type');

        /* START: Prepare data */
        $billingAddressConfig = array (
            'addressLine1'  => $order->get_billing_address_1(),
            'addressLine2'  => $order->get_billing_address_2(),
            'postcode'      => $order->get_billing_postcode(),
            'country'       => $order->get_billing_country(),
            'city'          => $order->get_billing_city(),
            'state'         => $order->get_billing_state(),
            'phone'         => array('number' => $order->get_billing_phone())
        );

        if(!empty($order->get_shipping_address_1())){
            $shippingAddressConfig = array (
                'addressLine1'  => $order->get_shipping_address_1(),
                'addressLine2'  => $order->get_shipping_address_2(),
                'postcode'      => $order->get_shipping_postcode(),
                'country'       => $order->get_shipping_country(),
                'city'          => $order->get_shipping_city(),
                'state'         => $order->get_shipping_state(),
                'phone'         => array('number' => $order->get_billing_phone())
            );
        } else {
            $shippingAddressConfig = $billingAddressConfig;
        }

        $products       = array();
        $productFactory = new WC_Product_Factory();

        foreach ($order->get_items() as $item) {
            $product        = $productFactory->get_product($item['product_id']);

            $productPrice = $product->get_price();
            if(is_null($productPrice)){
                $productPrice = 0;
            }

            $products[] = array(
                'description'   => (string)$product->post->post_content,
                'name'          => $item['name'],
                'price'         => $productPrice,
                'quantity'      => $item['qty'],
                'sku'           => $product->get_sku()
            );
        }

        /* END: Prepare data */
        $config['autoCapTime']  = $this->get_option('auto_cap_time');
        $config['autoCapture']  = $autoCapture ? CheckoutApi_Client_Constant::AUTOCAPUTURE_CAPTURE : CheckoutApi_Client_Constant::AUTOCAPUTURE_AUTH;
        $config['value']                = $amount;
        $config['currency']             = $order->get_currency();
        $config['trackId']              = $order->id;
        $config['customerName']         = $order->billing_first_name . ' ' . $order->billing_last_name;
        $config['customerIp']           = $this->get_ip_address();
        $config['cardToken'] = $chargeToken;
        $config['email'] = $order->billing_email;

        $config['shippingDetails']  = $shippingAddressConfig;
        $config['products']         = $products;

        /* Meta */
        $config['metadata'] = array(
            'server'            => get_site_url(),
            'quote_id'          => $order->id,
            'woo_version'       => property_exists($woocommerce, 'version') ? $woocommerce->version : '2.0',
            'plugin_version'    => WC_Checkout_Non_Pci::VERSION,
            'lib_version'       => CheckoutApi_Client_Constant::LIB_VERSION,
            'integration_type'  => 'GooglePay',
            'time'              => date('Y-m-d H:i:s')
        );

        $result['authorization']    = $secretKey;
        $result['postedParam']      = $config;

        return $result;
    }

    /**
     * Get current user IP Address.
     * @return string
     */
    public function get_ip_address() {
        if ( isset( $_SERVER['X-Real-IP'] ) ) {
            return $_SERVER['X-Real-IP'];
        } elseif ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            // Proxy servers can send through this header like this: X-Forwarded-For: client1, proxy1, proxy2
            // Make sure we always only send through the first IP in the list which should always be the client IP.
            return trim( current( explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
        } elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
            return $_SERVER['REMOTE_ADDR'];
        }
        return '';
    }

    public function gerProcessErrorMessage($errorMessage) {
        return __($errorMessage, 'woocommerce-checkout-google-pay');
    }

    /**
     * Payment form on checkout page.
     *
     * @version 20160304
     */
    public function payment_fields() {
        global $woocommerce;

        $checkoutFields = json_encode($woocommerce->checkout->checkout_fields,JSON_HEX_APOS);
        $endPointMode = $this->get_option('mode');
        $environment     = $endPointMode == 'sandbox' ? 'TEST' : 'PRODUCTION';
        $orderTotal = $woocommerce->cart->total;
        $currencyCode = get_woocommerce_currency();
        $merchantId = $this->get_option('gpay_merchant_id');
        $Api            = CheckoutApi_Api::getApi(array('mode' => $endPointMode));
        $amount         = $Api->valueToDecimal($orderTotal, $currencyCode);

        ?>
        <div id="container"></div>
        <input type="hidden" id="cko-google-signature" name="payment[cko-google-signature]" value=""/>
        <input type="hidden" id="cko-google-protocolVersion" name="payment[cko-google-protocolVersion]" value=""/>
        <input type="hidden" id="cko-google-signedMessage" name="payment[cko-google-signedMessage]" value=""/>
        <p>Pay using your google pay wallet.</p>
        <script type="text/javascript">
            
            jQuery(document).ready(function(){

                if(jQuery('#payment_method_woocommerce_checkout_google_pay').is(':checked')){
                    jQuery('#place_order').prop("disabled",true);
                } else {
                    jQuery('#place_order').prop("disabled",false);
                }

                var scriptG = document.createElement('script');
                scriptG.type = "text/javascript";
                scriptG.src = 'https://pay.google.com/gp/p/js/pay.js';
                scriptG.async = true;
                document.getElementById('container').appendChild(scriptG);
                scriptG.onload=onGooglePayLoaded;

                var allowedPaymentMethods = ['CARD', 'TOKENIZED_CARD'];
                var allowedCardNetworks = ['MASTERCARD', 'VISA'];

                var tokenizationParameters = {
                    tokenizationType: 'PAYMENT_GATEWAY',
                    parameters: {
                        'gateway': 'checkoutltd',
                        'gatewayMerchantId': '<?php echo $this->get_option('public_key');?>'
                    }
                }

                /**
                 * Initialize a Google Pay API client
                 *
                 * @returns {google.payments.api.PaymentsClient} Google Pay API client
                 */
                function getGooglePaymentsClient() {
                    return (new google.payments.api.PaymentsClient({ environment: '<?php echo $environment; ?>' }));
                }

                /**
                 * Initialize Google PaymentsClient after Google-hosted JavaScript has loaded
                 */
                function onGooglePayLoaded() {
                    var paymentsClient = getGooglePaymentsClient();
                    paymentsClient.isReadyToPay({ allowedPaymentMethods: allowedPaymentMethods })
                        .then(function (response) {
                            if (response.result) {
                                addGooglePayButton();
                                prefetchGooglePaymentData();
                            }
                        })
                        .catch(function (err) {
                            // show error in developer console for debugging
                            console.error(err);
                        });
                }

                /**
                 * Add a Google Pay purchase button alongside an existing checkout button
                 *
                 * @see {@link https://developers.google.com/pay/api/web/guides/brand-guidelines|Google Pay brand guidelines}
                 */
                function addGooglePayButton() {     

                    if(jQuery('.gpay-button').length > 0){
                        jQuery('.gpay-button').remove();
                    }             
                    var paymentsClient = getGooglePaymentsClient();
                    var button = paymentsClient.createButton({onClick:onGooglePaymentButtonClicked});
                    document.getElementById('container').appendChild(button);
                    jQuery('.gpay-button').hide();              
                }

                /**
                 * Configure support for the Google Pay API
                 *
                 * @see {@link https://developers.google.com/pay/api/web/reference/object#PaymentDataRequest|PaymentDataRequest}
                 * @returns {object} PaymentDataRequest fields
                 */
                function getGooglePaymentDataConfiguration() {
                    return {
                        // @todo a merchant ID is available for a production environment after approval by Google
                        // @see {@link https://developers.google.com/pay/api/web/guides/test-and-deploy/overview|Test and deploy}
                        merchantId: '<?php echo $merchantId;?>',
                        paymentMethodTokenizationParameters: tokenizationParameters,
                        allowedPaymentMethods: allowedPaymentMethods,
                        cardRequirements: {
                            allowedCardNetworks: allowedCardNetworks
                        }
                    };
                }

                /**
                 * Provide Google Pay API with a payment amount, currency, and amount status
                 *
                 * @see {@link https://developers.google.com/pay/api/web/reference/object#TransactionInfo|TransactionInfo}
                 * @returns {object} transaction info, suitable for use as transactionInfo property of PaymentDataRequest
                 */
                function getGoogleTransactionInfo() {
                    return {
                        currencyCode: '<?php echo $currencyCode; ?>',
                        totalPriceStatus: 'FINAL',
                        // set to cart total
                        totalPrice: '<?php echo $amount; ?>'
                    };
                }

                /**
                 * Prefetch payment data to improve performance
                 */
                function prefetchGooglePaymentData() {
                    var paymentDataRequest = getGooglePaymentDataConfiguration();
                    // transactionInfo must be set but does not affect cache
                    paymentDataRequest.transactionInfo = {
                        totalPriceStatus: 'NOT_CURRENTLY_KNOWN',
                        currencyCode: '<?php echo $currencyCode; ?>'
                    };
                    var paymentsClient = getGooglePaymentsClient();
                    paymentsClient.prefetchPaymentData(paymentDataRequest);
                }

                /**
                 * Show Google Pay chooser when Google Pay purchase button is clicked
                 */
                function onGooglePaymentButtonClicked(event) {
                    event.preventDefault(); // prevent woocommerce form to be submitted
                    var checkoutFields = '<?php echo $checkoutFields?>';
                    var result = isValidFormField(checkoutFields);

                    if(result){
                        var paymentDataRequest = getGooglePaymentDataConfiguration();
                        paymentDataRequest.transactionInfo = getGoogleTransactionInfo();

                        var paymentsClient = getGooglePaymentsClient();
                        paymentsClient.loadPaymentData(paymentDataRequest)
                                .then(function (paymentData) {
                                // handle the response
                                processPayment(paymentData);
                            })
                            .catch(function (err) {
                                // show error in developer console for debugging
                                console.error(err);
                            });
                    }
                }

                /**
                 * Process payment data returned by the Google Pay API
                 *
                 * @param {object} paymentData response from Google Pay API after shopper approves payment
                 * @see {@link https://developers.google.com/pay/api/web/reference/object#PaymentData|PaymentData object reference}
                 */
                function processPayment(paymentData) {
                    document.getElementById('cko-google-signature').value = JSON.parse(paymentData.paymentMethodToken.token).signature;
                    document.getElementById('cko-google-protocolVersion').value = JSON.parse(paymentData.paymentMethodToken.token).protocolVersion;
                    document.getElementById('cko-google-signedMessage').value = JSON.parse(paymentData.paymentMethodToken.token).signedMessage;

                    jQuery('#place_order').prop("disabled",false);
                    jQuery('#place_order').trigger('click');
                }

                function isValidFormField(fieldList) {
                    var result = {error: false, messages: []};
                    var fields = JSON.parse(fieldList);

                    if(jQuery('#terms').length === 1 && jQuery('#terms:checked').length === 0){ 
                        result.error = true;
                        result.messages.push({target: 'terms', message : 'You must accept our Terms & Conditions.'});
                    }
                    
                    if (fields) {
                        jQuery.each(fields, function(group, groupValue) {
                            if (group === 'shipping' && jQuery('#ship-to-different-address-checkbox:checked').length === 0) {
                                return true;
                            }

                            jQuery.each(groupValue, function(name, value ) {
                                if (!value.hasOwnProperty('required')) {
                                    return true;
                                }

                                if (name === 'account_password' && jQuery('#createaccount:checked').length === 0) {
                                    return true;
                                }

                                var inputValue = jQuery('#' + name).length > 0 && jQuery('#' + name).val().length > 0 ? jQuery('#' + name).val() : '';

                                if (value.required && jQuery('#' + name).length > 0 && jQuery('#' + name).val().length === 0) {
                                    result.error = true;
                                    result.messages.push({target: name, message : value.label + ' is a required field.'});
                                }

                                if (value.hasOwnProperty('type')) {
                                    switch (value.type) {
                                        case 'email':
                                            var reg     = /^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/;
                                            var correct = reg.test(inputValue);

                                            if (!correct) {
                                                result.error = true;
                                                result.messages.push({target: name, message : value.label + ' is not correct email.'});
                                            }

                                            break;
                                        case 'tel':
                                            var tel         = inputValue;
                                            var filtered    = tel.replace(/[\s\#0-9_\-\+\(\)]/g, '').trim();

                                            if (filtered.length > 0) {
                                                result.error = true;
                                                result.messages.push({target: name, message : value.label + ' is not correct phone number.'});
                                            }

                                            break;
                                    }
                                }
                            });
                        });
                    } else {
                        result.error = true;
                        result.messages.push({target: false, message : 'Empty form data.'});
                    }

                    if (!result.error) {
                        return true;
                    }

                    jQuery('.woocommerce-error, .woocommerce-message').remove();

                    jQuery.each(result.messages, function(index, value) {
                        jQuery('form.checkout').prepend('<div class="woocommerce-error">' + value.message + '</div>');
                    });

                    jQuery('html, body').animate({
                        scrollTop: (jQuery('form.checkout').offset().top - 100 )
                    }, 1000 );

                    jQuery(document.body).trigger('checkout_error');

                    return false;
                }

                setTimeout(function(){
                    if( jQuery('.gpay-button').length > 0){
                        var gpaybutton = jQuery('.gpay-button');
                        jQuery('.gpay-button').attr('style','margin-top: 20px; width:100%');
                        jQuery('.form-row.place-order').append(gpaybutton);
                        jQuery('.gpay-button').hide();

                        if(jQuery('#payment_method_woocommerce_checkout_google_pay').is(':checked')){
                            jQuery('.gpay-button').show();
                            jQuery('#place_order').prop("disabled",true);
                        } 

                        jQuery('.wc_payment_methods.payment_methods.methods li').click(function(){
                            if(jQuery('#payment_method_woocommerce_checkout_google_pay').is(':checked')){
                               jQuery('.gpay-button').show();
                               jQuery('#place_order').prop("disabled",true);
                            } else if(!jQuery('#payment_method_woocommerce_checkout_apple_pay').is(':checked')){
                               jQuery('.gpay-button').hide();
                               jQuery('#place_order').prop("disabled",false);
                            } else {
                               jQuery('.gpay-button').hide();
                            }
                        });
                    }
                }, 3000);
            })

        </script>
        <?php
    }

    
    /**
     * Logging method.
     *
     * @param string $message
     *
     * @version 20160403
     */
    public static function log($message) {
        error_log(print_r($message, true) . "\r\n", 3, plugin_dir_path (__FILE__) . DIRECTORY_SEPARATOR . self::PAYMENT_METHOD_CODE . '.log');
    }

    /**
     * Refund order
     *
     * Process a refund if supported.
     * @param  int    $order_id
     * @param  float  $amount
     * @param  string $reason
     * @return bool True or false based on success, or a WP_Error object
     *
     * @version 20160316
     */
    public function process_refund($order_id, $amount = null, $reason = '') {
        include_once( 'includes/class-wc-gateway-checkout-non-pci-request.php');
        include_once( 'includes/class-wc-gateway-checkout-non-pci-validator.php');

        $order      = new WC_Order($order_id);;
        $request    = new WC_Checkout_Non_Pci_Request($this);

        $result = $request->refund($order, $amount, $reason);

        if ($result['status'] === 'error') {
            return new WP_Error('error', __($result['message'], 'woocommerce-checkout-google-pay'));
        }

        return true;
    }

    /**
    * Redirection page
    * Used in case of Hosted solution, 3D secure payment and APMs
    **/

    public function callback(){
        include_once('includes/class-wc-gateway-checkout-non-pci-request.php');
        include_once('includes/class-wc-gateway-checkout-non-pci-validator.php');
        include_once('includes/class-wc-gateway-checkout-non-pci-customer-card.php');
        
        if (!session_id()) session_start();

        if(empty($_REQUEST['cko-payment-token']) && empty($_REQUEST['cko-card-token'])){
            wp_redirect( esc_url(home_url()) );
            exit;
        }

        if(isset($_REQUEST['cko-payment-token'])){
            $paymentToken = $_REQUEST['cko-payment-token'];
        }

        if(isset($_SESSION['checkout_local_payment_token'])){
            $localPaymentToken = $_REQUEST['checkout_local_payment_token'];
        }

        $savedCardData  = array();

        if(isset($_REQUEST['cko-card-token'])){
            global $woocommerce;

            $orderId    = $woocommerce->session->order_awaiting_payment;

            if (empty($orderId)) {
                $orderId = $_REQUEST['cko-context-id'];
                
                if(empty($orderId)){
                    WC_Checkout_Non_Pci::log('Empty OrderId');
                    WC_Checkout_Non_Pci_Validator::wc_add_notice_self('An error has occured while processing your transaction.', 'error');
                    wp_redirect(WC_Cart::get_checkout_url());
                    exit();
                }
            }

            $order      = new WC_Order( $orderId );
            $checkout   = new WC_Checkout_Non_Pci();
            $request    = new WC_Checkout_Non_Pci_Request($checkout);
            $cardRequest = new WC_Checkout_Non_Pci_Customer_Card();
            $order_status = $order->get_status(); 

            if($order_status == 'pending'){   

                $result     = $request->createCharge($order,$_REQUEST['cko-card-token'],$savedCardData);

                if (!empty($result['error'])) {
                    WC_Checkout_Non_Pci::log($result);
                    WC_Checkout_Non_Pci_Validator::wc_add_notice_self($result['error'], 'error');
                    wp_redirect(esc_url(WC_Cart::get_checkout_url()));
                    exit();
                }

                $entityId       = $result->getId();
                $redirectUrl    = esc_url($result->getRedirectUrl());

                if ($redirectUrl) {
                    $_SESSION['checkout_payment_token'] =  $entityId;
                    $url = $redirectUrl;
                    wp_redirect($url);
                    exit();
                }

                update_post_meta($orderId, '_transaction_id', $entityId);

                $order->update_status($request->getOrderStatus(), __("Checkout.com Charge Approved (Transaction ID - {$entityId}", 'woocommerce-checkout-google-pay'));
                $order->reduce_order_stock();
                $woocommerce->cart->empty_cart();

                if (is_user_logged_in() && $checkout->saved_cards) {
                    $cardRequest->saveCard($result, $order->user_id, $_SESSION['checkout_save_card_checked']);
                }

                $url = esc_url($checkout->get_return_url($order));
                wp_redirect($url);
            }

        } else {

            if (!empty($paymentToken) && $paymentToken == $localPaymentToken) {
                unset($_SESSION['checkout_local_payment_token']);

                WC_Checkout_Non_Pci_Validator::wc_add_notice_self('Thank you for your purchase! Thanks you for completing the payment. Once we confirm the we have successfully received the payment, you will be notified by email.', 'notice');
                
                $checkout   = new WC_Checkout_Non_Pci();
                $url = esc_url($checkout->get_return_url($order));
                wp_redirect($url);

                exit();
            }

            $checkout   = new WC_Checkout_Non_Pci();
            $request    = new WC_Checkout_Non_Pci_Request($checkout);
            $result     = $request->verifyCharge($paymentToken);

            $order      = new WC_Order( $result['orderId'] );

            if ($result['status'] === 'error') {
                WC_Checkout_Non_Pci_Validator::wc_add_notice_self($result['message'], 'error');
                wp_redirect(esc_url(WC_Cart::get_checkout_url()));
                exit();
            }

            unset($_SESSION['checkout_payment_token']);

            $url = esc_url($order->get_checkout_order_received_url());
            wp_redirect($url);

        }

        exit();
    }

    /**
    * Process webhook actions
    *
    **/

    public function webhook(){
        include_once('includes/class-wc-gateway-checkout-non-pci-web-hook.php');
        include_once('includes/class-wc-gateway-checkout-non-pci-validator.php');
        //include_once('woocommerce-checkout-google-pay.php');

        if (!function_exists('getallheaders'))
        {
            function getallheaders()
            {
                $headers = '';
                foreach ($_SERVER as $name => $value)
                {
                    if (substr($name, 0, 5) == 'HTTP_')
                    {
                        $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                    }
                }
                return $headers;
            }
        }

        $headers = getallheaders();

        foreach ($headers as $header => $value) {
            $lowHeaders[strtolower($header)] = $value;
        }

        $secretKey  = !empty($lowHeaders['authorization']) ? $lowHeaders['authorization'] : '';
        $checkout   = new WC_Checkout_Non_Pci();
        $webHook    = new WC_Checkout_Non_Pci_Web_Hook($checkout);
        $storedKey  = $webHook->getPrivateSharedKey();

        if (empty($secretKey) || (string)$secretKey !== (string)$storedKey) {
            WC_Checkout_Non_Pci::log("{$secretKey} and {$storedKey} is not match");
            http_response_code(401);
            return;
        }

        $data = json_decode(file_get_contents('php://input'));

        WC_Checkout_Non_Pci::log($data);

        $eventType = $data->eventType;


        if (empty($data) || !WC_Checkout_Non_Pci_Validator::webHookValidation($data)) {
            $responseCode       = (int)$data->message->responseCode;
            $status             = (string)$data->message->status;
            $responseMessage    = (string)$data->message->responseMessage;
            $trackId            = (string)$data->message->trackId;

            WC_Checkout_Non_Pci::log("Error Code - {$responseCode}. Message - {$responseMessage}. Status - {$status}. Order - {$trackId}");

            http_response_code(400);

            return;
        }

        switch ($eventType) {
            case WC_Checkout_Non_Pci_Web_Hook::EVENT_TYPE_CHARGE_CAPTURED:
                $result = $webHook->captureOrder($data);
                break;
            case WC_Checkout_Non_Pci_Web_Hook::EVENT_TYPE_CHARGE_REFUNDED:
                $result = $webHook->refundOrder($data);
                break;
            case WC_Checkout_Non_Pci_Web_Hook::EVENT_TYPE_CHARGE_VOIDED:
                $result = $webHook->voidOrder($data);
                break;
            case WC_Checkout_Non_Pci_Web_Hook::EVENT_TYPE_CHARGE_SUCCEEDED:
                $result = $webHook->authorisedOrder($data);
                break;
            case WC_Checkout_Non_Pci_Web_Hook::EVENT_TYPE_CHARGE_FAILED:
                $result = $webHook->failOrder($data);
                break;
            case WC_Checkout_Non_Pci_Web_Hook::EVENT_TYPE_INVOICE_CANCELLED:
                $result = $webHook->invoiceCancelOrder($data);
                break;
            default:
                http_response_code(500);
                return;
        }

        $httpCode = $result ? 200 : 400;

        return http_response_code($httpCode);
        
    }

}
