<?php

include_once ('lib/autoload.php');

/**
 * Class WC_Checkout_Non_Pci
 *
 * @version 20160304
 */
class WC_Checkout_Apple_Pay extends WC_Payment_Gateway {

    const PAYMENT_METHOD_CODE       = 'woocommerce_checkout_apple_pay';
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
        $this->method_title         = __("Checkout.com Apple Pay", 'woocommerce_checkout_apple_pay');
        $this->method_description   = __("Checkout.com Apple Pay Plug-in for WooCommerce", 'woocommerce_checkout_apple_pay');
        $this->title                = __("Checkout.com Apple Pay", 'woocommerce_checkout_apple_pay');

        $this->icon         = plugins_url('/view/image/apple-pay.png',__FILE__);
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
                'title'     => __( 'Enable / Disable', 'woocommerce_checkout_apple_pay' ),
                'label'     => __( 'Enable Payment Method', 'woocommerce_checkout_apple_pay' ),
                'type'      => 'checkbox',
                'default'   => 'no',
            ),
            'title' => array(
                'title'     => __('Title', 'woocommerce_checkout_apple_pay'),
                'type'      => 'text',
                'desc_tip'  => __('Payment title the customer will see during the checkout process.', 'woocommerce_checkout_apple_pay'),
                'default'   => __( "Apple Pay", 'woocommerce_checkout_apple_pay' ),
            ),
            'applepay_merchant_id' => array(
                'title'     => __('Apple Pay Merchant identifier', 'woocommerce_checkout_apple_pay'),
                'type'      => 'text',
                'desc_tip'  => __( 'Merchant Id from Apple Pay', 'woocommerce_checkout_apple_pay' ),
            ),
            'applepay_cert_path' => array(
                'title'     => __('Apple Pay certificate path', 'woocommerce_checkout_apple_pay'),
                'type'      => 'text',
                'desc_tip'  => __( 'Path to merchant apple pay certificate file. E.g /home/www/mysite/htdocs/certificates/certificate_key.pem', 'woocommerce_checkout_apple_pay' ),
                'description' => __( 'Path to merchant apple pay certificate file. E.g /home/www/mysite/htdocs/certificates/certificate_key.pem', 'woocommerce_checkout_apple_pay' ),
            ),
            'applepay_cert_key' => array(
                'title'     => __('Apple Pay certificate key', 'woocommerce_checkout_apple_pay'),
                'type'      => 'text',
                'desc_tip'  => __( 'Path to merchant apple pay certificate key file. E.g /home/www/mysite/htdocs/certificates/certificate_key.key', 'woocommerce_checkout_apple_pay' ),
                'description' =>__( 'Path to merchant apple pay certificate key file. E.g /home/www/mysite/htdocs/certificates/certificate_key.key', 'woocommerce_checkout_apple_pay' ),
            ),
            'secret_key' => array(
                'title'     => __('Secret Key', 'woocommerce_checkout_apple_pay'),
                'type'      => 'password',
                'desc_tip'  => __( 'Only used for requests from the merchant server to the Checkout API', 'woocommerce_checkout_apple_pay' ),
            ),
            'public_key' => array(
                'title'     => __('Public Key', 'woocommerce_checkout_apple_pay'),
                'type'      => 'password',
                'desc_tip'  => __( 'Used for JS Checkout API', 'woocommerce_checkout_apple_pay' ),
            ),
            'private_shared_key' => array(
                'title'     => __('Private Shared Key', 'woocommerce_checkout_apple_pay'),
                'type'      => 'password',
                'desc_tip'  => __( 'Used for webhooks from Checkout API', 'woocommerce_checkout_apple_pay' ),
                'description' => __( 'To get the Private Shared Key please configure a Webhook URL in the Checkout HUB.', 'woocommerce_checkout_apple_pay' ),
            ),
            'void_status' => array(
                'title'     => __( 'Enable / Disable', 'woocommerce_checkout_apple_pay' ),
                'label'     => __( 'When voided change order status to Cancelled', 'woocommerce_checkout_apple_pay' ),
                'type'      => 'checkbox',
                'default'   => 'no',
            ),
            'payment_action' => array(
                'title'       => __('Payment Action', 'woocommerce_checkout_apple_pay'),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'description' => __('Choose whether you wish to capture funds immediately or authorize payment only.', 'woocommerce_checkout_apple_pay'),
                'default'     => 'authorize',
                'desc_tip'    => true,
                'options'     => array(
                    self::PAYMENT_ACTION_CAPTURE    => __('Authorize and Capture', 'woocommerce_checkout_apple_pay'),
                    self::PAYMENT_ACTION_AUTHORIZE  => __('Authorize Only', 'woocommerce_checkout_apple_pay')
                )
            ),
            'auto_cap_time' => array(
                'title'     => __('Auto Capture Time', 'woocommerce_checkout_apple_pay'),
                'type'      => 'text',
                'desc_tip'  => __('Time to automatically capture charge. It is recommended to set it to a minimun of 0.02', 'woocommerce_checkout_apple_pay'),
                'default'   => __( '0.02', 'woocommerce_checkout_apple_pay' ),
            ),
            'order_status' => array(
                'title'       => __('New Order Status', 'woocommerce_checkout_apple_pay'),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'default'     => 'on-hold',
                'desc_tip'    => true,
                'options'     => array(
                    'on-hold'    => __('On Hold', 'woocommerce_checkout_apple_pay'),
                    'processing' => __('Processing', 'woocommerce_checkout_apple_pay')
                )
            ),
            'mode' => array(
                'title'       => __('Endpoint URL mode', 'woocommerce_checkout_apple_pay'),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'description' => __('When going on live production, Endpoint url mode should be set to live.', 'woocommerce_checkout_apple_pay'),
                'default'     => 'sandbox',
                'desc_tip'    => true,
                'options'     => array(
                    'sandbox'   => __('SandBox', 'woocommerce_checkout_apple_pay'),
                    'live'      => __('Live', 'woocommerce_checkout_apple_pay')
                )
            ),
            'timeout' => array(
                'title'     => __('Timeout value for a request to the gateway', 'woocommerce_checkout_apple_pay'),
                'type'      => 'text',
                'desc_tip'  => __('The timeout value for a request to the gateway. Default is 60 seconds. Please notify checkout.com support team before increasing the value.', 'woocommerce_checkout_apple_pay'),
                'default'   => __( '60', 'woocommerce_checkout_apple_pay' ),
            ),
        );
    }

    // Get order status from settings
    public function getOrderStatus() {
        return $this->get_option('order_status');
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
        $environment = $endPointMode == 'sandbox' ? 'TEST' : 'PRODUCTION';
        $orderTotal = $woocommerce->cart->total;
        $currencyCode = get_woocommerce_currency();
        $Api = CheckoutApi_Api::getApi(array('mode' => $endPointMode));
        $amount = $Api->valueToDecimal($orderTotal, $currencyCode);
        $package = $woocommerce->shipping->get_packages();
        $shippingMethod = $package[0];
        $chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
        $chosen_shipping = $chosen_methods[0]; 
        $choosenShippingCost = $shippingMethod['rates'][$chosen_shipping]->cost;
        $choosenShippingLabel = $shippingMethod['rates'][$chosen_shipping]->label;
        $cartSubtotal = WC()->cart->get_subtotal();
        $countryCode = $woocommerce->customer->get_shipping_country();

        $config = array();
        $config['billingDetails'] = array (
            'addressLine1'  => $_POST['address'],
            'addressLine2'  => $_POST['address_2'],
            'postcode'      => $_POST['postcode'],
            'country'       => $_POST['country'],
            'city'          => $_POST['city'],
            'state'         => $_POST['state'],
        );

        if(isset($_POST['s_country'])){
            $config['shippingDetails'] = array (
                'addressLine1'  => $_POST['s_address'],
                'addressLine2'  => $_POST['s_address_2'],
                'postcode'      => $_POST['s_postcode'],
                'country'       => $_POST['s_country'],
                'city'          => $_POST['s_city'],
                'state'         => $_POST['s_state'],
            );
        };

        $config['chosen_shipping_method'] = array(
            'choosenShippingLabel' => $choosenShippingLabel,
            'choosenShippingCost' => $choosenShippingCost
        );

        ?>
        <p style="display:none" id="got_notactive">ApplePay is possible on this browser, but not currently activated.</p>
        <p style="display:none" id="notgot">ApplePay is not available on this browser</p>

        <div class="cko-apple-pay" style="display: none">
            <div class="apple-pay-button-with-text apple-pay-button-black-with-text" id="cko-apple-pay-button" >
              <span class="text">Buy with</span>
              <span class="logo"></span>
            </div>
        </div>
        <link rel="stylesheet" href="<?php echo plugins_url('/view/css/apple_pay_button.css',__FILE__)?>" type="text/css" media="screen" />
        <script type="text/javascript">
            if (window.ApplePaySession) {
                var merchantIdentifier = '<?php echo $this->get_option('applepay_merchant_id') ;?>';

                var promise = ApplePaySession.canMakePaymentsWithActiveCard(merchantIdentifier);
                promise.then(function (canMakePayments) {
                    if (canMakePayments) {
                         if( jQuery('.cko-apple-pay').length > 0){
                            var button = jQuery('.cko-apple-pay');
                            jQuery('.cko-apple-pay').attr('style','margin-top: 20px; width:100%');
                            jQuery('.form-row.place-order').append(button);
                            jQuery('.cko-apple-pay').hide();

                            if(jQuery('#payment_method_woocommerce_checkout_apple_pay').is(':checked')){
                                jQuery('.cko-apple-pay').show();
                                jQuery('#place_order').prop("disabled",true);
                            } 

                            jQuery('.wc_payment_methods.payment_methods.methods li').click(function(){
                                if(jQuery('#payment_method_woocommerce_checkout_apple_pay').is(':checked')){
                                    jQuery('.cko-apple-pay').show();
                                    jQuery('#place_order').prop("disabled",true);
                                } else if(!jQuery('#payment_method_woocommerce_checkout_google_pay').is(':checked')){
                                    jQuery('.cko-apple-pay').hide();
                                    jQuery('#place_order').prop("disabled",false);
                                }else {
                                    jQuery('.cko-apple-pay').hide();
                                }
                            });
                        }
                    } else {   
                        document.getElementById("got_notactive").style.display = "block";
                        jQuery('.payment_method_woocommerce_checkout_apple_pay').css('opacity', '0.6');
                        jQuery('#place_order').prop("disabled",true);
                    }
                }); 
            } else {
                document.getElementById("notgot").style.display = "block";
                jQuery('.payment_method_woocommerce_checkout_apple_pay').css('opacity', '0.6');
                jQuery('#place_order').prop("disabled",true);
            }

            document.getElementById("cko-apple-pay-button").onclick = function(evt){

                var checkoutFields = '<?php echo $checkoutFields?>';
                var result = isValidFormField(checkoutFields);

                if(result){
                    var shippingMethods = [];
                    var successUrl = "";

                    <?php if($shippingMethod): ?>
                        <?php foreach ($shippingMethod['rates'] as $key => $shippingMethods): ?>
                                
                                shippingMethods.push({
                                    label: "<?php echo $shippingMethods->get_label(); ?>",
                                    detail: "",
                                    amount: "<?php echo $shippingMethods->get_cost(); ?>",
                                    identifier: "<?php echo $shippingMethods->get_label(); ?>"
                                });
                        <?php endforeach; ?>
                    <?php endif; ?>

                    var lineItems = [];
                    <?php if($choosenShippingLabel): ?>
                            lineItems.push({
                                        type: "final",
                                        label: "<?php echo $choosenShippingLabel; ?>",
                                        amount: "<?php echo $choosenShippingCost; ?>"
                                    });
                    <?php endif; ?>

                    <?php if($cartSubtotal): ?>
                            lineItems.push({
                                type: "final",
                                label: "Bag Subtotal",
                                amount: "<?php echo $cartSubtotal; ?>"
                            });
                    <?php endif; ?>

                     var paymentRequest = {
                       currencyCode: '<?php echo $currencyCode; ?>',
                       countryCode: '<?php echo $countryCode; ?>',
                       requiredShippingContactFields: [],
                       lineItems: lineItems,
                       total: {
                          label: "<?php echo $_SERVER['HTTP_HOST']; ?>",
                          type: "final",
                          amount: "<?php echo $orderTotal;?>"
                       },
                       supportedNetworks: ['amex', 'masterCard', 'visa' ],
                       merchantCapabilities: [ 'supports3DS', 'supportsEMV', 'supportsCredit', 'supportsDebit' ]
                    };

                    var session = new ApplePaySession(1, paymentRequest);

                    // Merchant Validation
                    session.onvalidatemerchant = function (event) { console.log('onvalidatemerchant');
                        var promise = performValidation(event.validationURL);

                        promise.then(function (merchantSession) { console.log(merchantSession);
                             session.completeMerchantValidation(merchantSession);
                        }); 
                    }
                    
                    function performValidation(validationURL) {
                        return new Promise(function(resolve, reject) {
                            var url = '<?php echo plugins_url('/includes/request-merchant-session.php',__FILE__); ?>';

                            jQuery.ajax({
                                method: 'post',
                                url : url, 
                                dataType: "json",
                                data: {
                                    'validationURL': validationURL,
                                    'domainName' : location.host,
                                },

                                success:function(response) { 
                                    // This outputs the result of the ajax request
                                    if(response) {
                                        resolve(response);
                                    } else {
                                        reject(Error(response));
                                    }
                                },
                                error: function(response){
                                    reject(Error("Network Error"));
                                }

                            });

                        });
                    }

                    session.onshippingcontactselected = function(event) {
                        var status = ApplePaySession.STATUS_SUCCESS;
                        var newShippingMethods = shippingMethods;
                        var newTotal = { type: 'final', label: "<?php echo $_SERVER['HTTP_HOST']; ?>", amount: '<?php echo $orderTotal; ?>' };
                        var newLineItems = lineItems ;

                        session.completeShippingContactSelection(status, newShippingMethods, newTotal, newLineItems );
                    }

                    session.onshippingmethodselected = function(event) {
                        var status = ApplePaySession.STATUS_SUCCESS;
                        var newTotal = { type: 'final', label: "<?php echo $_SERVER['HTTP_HOST']; ?>", amount: '<?php echo $orderTotal; ?>' };
                        var newLineItems =lineItems;
                        
                        session.completeShippingMethodSelection(status, newTotal, newLineItems );
                    }

                    session.onpaymentmethodselected = function(event) {
                        var newTotal = { type: 'final', label: "<?php echo $_SERVER['HTTP_HOST']; ?>", amount: '<?php echo $orderTotal; ?>' };
                        var newLineItems =lineItems;
                        
                        session.completePaymentMethodSelection( newTotal, newLineItems );
                    }

                    session.onpaymentauthorized = function (event) {
                        var promise = sendPaymentToken(event.payment.token);
                        
                        promise.then(function (success) {   
                            var status;
                            if (success){
                                status = ApplePaySession.STATUS_SUCCESS;
                            } else {
                                status = ApplePaySession.STATUS_FAILURE;
                            }
                            
                            session.completePayment(status);

                            if(success) {
                                // redirect to success page
                                window.location= successUrl;
                            }

                        }, function(reason) { 
                            if(reason.message == "ERROR") {
                                var status = session.STATUS_FAILURE;
                            } else {
                                var status = session.STATUS_FAILURE;
                            }
                            session.completePayment(status);
                        });
                    }

                    function sendPaymentToken(paymentToken) {
                      
                         return new Promise(function(resolve, reject) {
                            var url = '<?php echo plugins_url('/includes/apple-pay-charge.php',__FILE__); ?>';
                            var paymentData = '<?php echo json_encode($config); ?>';

                            jQuery.ajax({
                                method: 'post',
                                url : url, 
                                dataType: "json",
                                data: {
                                    'paymentData' : JSON.parse(paymentData),
                                    'paymentToken' : paymentToken,
                                    'firstName' : document.getElementById('billing_first_name').value,
                                    'lastName' : document.getElementById('billing_last_name').value,
                                    'email' : document.getElementById('billing_email').value,
                                    'phone': document.getElementById('billing_phone').value
                                },
                                success:function(response) {
                                    // This outputs the result of the ajax request
                                    var response = jQuery.parseJSON(response);

                                    if(response.result == 'SUCCESS'){ console.log('inside success');
                                        successUrl = response.url;

                                        resolve(true);
                                    } else { console.log('inside fail');
                                        reject(Error(response.result));
                                    }
                                },
                                error: function(response){ console.log('error');
                                    var response = jQuery.parseJSON(response);
                                    reject(Error(response.result));
                                }

                            });

                        });
                    }
                    
                    session.oncancel = function(event) {
                        console.log(event);
                    }
                    
                    session.begin();
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
            };

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
            return new WP_Error('error', __($result['message'], 'woocommerce_checkout_apple_pay'));
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

                $order->update_status($request->getOrderStatus(), __("Checkout.com Charge Approved (Transaction ID - {$entityId}", 'woocommerce_checkout_apple_pay'));
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
        //include_once('woocommerce_checkout_apple_pay.php');

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
