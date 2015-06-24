<?php
class models_methods_creditcard extends models_methods_Abstract
{

    protected $_code = 'creditcard';
    private $_paymentToken = '';

    public function __construct()
    {
        $this ->id = 'checkoutapipayment';
        $this->has_fields = true;
        $this->checkoutapipayment_ispci = 'no';


        add_action ( 'woocommerce_checkout_order_review' , array ( $this , 'setJsInit' ) );
        add_action ( 'woocommerce_after_checkout_validation' , array ( $this , 'validateToken' ) );


    }
    public function _initCode(){}

    public function setJsInit()
    {
        ?>
        <script type="text/javascript">


            var loaderJs = 0;
            jQuery(document).ajaxComplete(function( event,request, settings ) {
                if( jQuery('#payment_method_checkoutapipayment').length
                    &&( typeof request.responseJSON!='undefined'
                    &&  ( typeof request.responseJSON.fragments !='undefined' &&
                    (typeof request.responseJSON.fragments['.woocommerce-checkout-payment'] !='undefined'
                    || typeof  request.responseJSON.fragments['.woocommerce-checkout-review-order-table'] )

                    )

                    ) ){
                    if( jQuery('#payment_method_checkoutapipayment').length) {
                        if (!document.getElementById('cko-checkoutjs') ) {

                            var script = document.createElement("script");
                            script.type = "text/javascript";
                            if('<?php echo CHECKOUTAPI_ENDPOINT?>' == 'live') {
                                script.src = "//checkout.com/cdn/js/checkout-live.js";
                            }else {
                                script.src = "//sandbox.checkout.com/js/v1/checkout.js";
                            }
                            script.id = "cko-checkoutjs";
                            script.async = "true";
                            script.setAttribute("data-namespace","CheckoutIntegration");
                            document.getElementsByTagName("head")[0].appendChild(script);
                            jQuery(function(){
                                jQuery('#billing_email,#billing_phone').blur(function() {
                                    jQuery('body').trigger('update_checkout');
                                    document.getElementById('cko-cc-redirectUrl').value = CheckoutIntegration.getRedirectionUrl();
                                    var transactionValue = CheckoutIntegration.getTransactionValue();
                                    document.getElementById('cko-cc-paymenToken').value =transactionValue.paymentToken;
                                });


                            });
                        }else{
                            if(typeof CheckoutIntegration !='undefined' && loaderJs<1) {
                                CheckoutIntegration.render(window.CKOConfig);
                                document.getElementById('cko-cc-redirectUrl').value = CheckoutIntegration.getRedirectionUrl();
                                var transactionValue = CheckoutIntegration.getTransactionValue();
                                document.getElementById('cko-cc-paymenToken').value =transactionValue.paymentToken;
                            }
                        }
                        // setTimeout(function(){CheckoutIntegration.render(window.CKOConfig)},3000);

                    }
                    // CheckoutIntegration.render(window.CKOConfig);


                }

                if(typeof settings.url !='undefined' && settings.url.indexOf('woocommerce_checkout') > -1 ) {

                    var respondtxt = request.responseText, error = false;

                    if (respondtxt.indexOf('<!--WC_START-->') >= 0 &&
                        (respondtxt = respondtxt.split('<!--WC_START-->') [1])) {

                        if(respondtxt.indexOf('<!--WC_END-->') >= 0
                            && (respondtxt = respondtxt.split('<!--WC_END-->')[0])) {

                            var d = jQuery.parseJSON(respondtxt);
                            error = (d.messages.indexOf('loadLight') < 0 );

                            jQuery('.woocommerce-error li div').hide();

                            if(jQuery('#payment_method_checkoutapipayment:checked').length
                                && loaderJs < 1 && !error ) {
                                if( jQuery('#terms').length) {
                                    jQuery('#terms').attr('checked', 'checked');
                                }
                                if(!CheckoutIntegration.isMobile()) {
                                    CheckoutIntegration.open();
                                }else {
                                    document.getElementById('cko-cc-redirectUrl').value = CheckoutIntegration.getRedirectionUrl();
                                    var transactionValue = CheckoutIntegration.getTransactionValue();
                                    document.getElementById('cko-cc-paymenToken').value =transactionValue.paymentToken;
                                    jQuery('#place_order').trigger('submit');


                                }

                                loaderJs++;
                            }
                        }
                    }


                }
            });
        </script>
    <?php
    }



    public function payment_fields()
    {
        global $woocommerce;
        get_currentuserinfo();
        $grand_total = (float) WC()->cart->total;
        $amount =$grand_total*100;
        $current_user = wp_get_current_user();
        $post = array();
        if(isset($_POST['post_data'])) {
            $vars = explode ( '&' , $_POST[ 'post_data' ] );
            foreach ( $vars as $k => $value ) {
                $v = explode ( '=' , urldecode ( $value ) );
                $post[ $v[ 0 ] ] = $v[ 1 ];
            }
        }
        $email = "Email@youremail.com";

        $name = 'Your card holder name';

        if(isset($current_user->user_email) && $current_user->user_email){
            $email = $current_user->user_email;
        }

        if(isset($current_user->user_first_name)){
            $name = $current_user->user_first_name;
        }

        if(isset($post['billing_first_name']) && $post['billing_first_name']) {
            $name = $post['billing_first_name'];
        }

        if(isset($post['billing_last_name']) && $post['billing_last_name']) {
            $name = $post['billing_first_name']. ' '.$post['billing_last_name'];
        }

        if(isset($post['billing_email']) && $post['billing_email']) {
            $email = $post['billing_email'];
        }

        $paymentToken = $this->getPaymentToken();

        if (CHECKOUTAPI_LP == 'yes'){
            $paymentMode = 'mixed';
        } else {
            $paymentMode = 'card';
        }


        ?>

        <div style="" class="widget-container">

            <input type="hidden" name="cko_cc_paymenToken" id="cko-cc-paymenToken" value="">
            <input type="hidden" name="redirectUrl" id="cko-cc-redirectUrl" value="">

            <script type="text/javascript">

                var reload = false;
                var customerEMail = "<?php echo $email ?>";

                window.CKOConfig = {
                    debugMode: false,
                    renderMode: 2,
                    namespace: 'CheckoutIntegration',
                    publicKey: '<?php echo CHECKOUTAPI_PUBLIC_KEY ?>',
                    paymentToken: "<?php echo $paymentToken ?>",
                    value: '100',
                    currency: '<?php echo get_woocommerce_currency() ?>',
                    customerEmail: customerEMail,
                    forceMobileRedirect: true,
                    customerName: '<?php echo $name?>',
                    paymentMode: '<?php echo $paymentMode ?>',
                    logoUrl : '<?php echo CHECKOUTAPI_LOGOURL ?>',
                    themeColor : '<?php echo CHECKOUTAPI_THEMECOLOR ?>',
                    buttonColor : '<?php echo CHECKOUTAPI_BUTTONCOLOR ?>',
                    iconColor: '<?php echo CHECKOUTAPI_ICONCOLOR ?>',
                    useCurrencyCode: '<?php echo CHECKOUTAPI_CURRENCYCODE ?>',
                    title: '<?php  ?>',
                    subtitle: '<?php echo __('Please enter your credit card details') ?>',
                    widgetContainerSelector: '.widget-container',
                    ready: function (event) {
                        var cssAdded = jQuery('.widget-container link');
                        if (!cssAdded.hasClass('checkoutAPiCss')) {
                            cssAdded.addClass('checkoutAPiCss');
                        }
                        loaderJs++;
                        setTimeout(function(){
                            loaderJs = jQuery('#cko-widget').length;
                        },100);

                        jQuery('head').append(cssAdded);

                    },
                    cardCharged: function (event) {
                        document.getElementById('cko-cc-paymenToken').value = event.data.paymentToken;
                        reload = false;
                        CheckoutIntegration.setCustomerEmail(document.getElementById('billing_email').value);
                        if( jQuery('#terms').length) {
                            jQuery('#terms').attr('checked', 'checked');
                        }
                        jQuery('#place_order').removeAttr('disabled');
                        if(jQuery('[name^=checkout].checkout.wasActived').length<1) {
                            jQuery('#place_order').trigger('submit')
                        }


                    },
                    paymentTokenExpired: function(){
//                        alert('Your payment session has expired. We will reload you page thank you');
//                        window.location.reload();
                        reload = true;
                        if( jQuery('[name^=checkout].checkout').is('.cko-processing')) {
                            jQuery('[name^=checkout].checkout').removeClass('processing cko-processing');
                        }
                        jQuery('#place_order').removeAttr('disabled');
                        loaderJs = 0;
                    },
                    lightboxDeactivated: function() {
                        jQuery('#place_order').removeAttr('disabled');
                        if( jQuery('[name^=checkout].checkout').is('.cko-processing')) {
                            jQuery('[name^=checkout].checkout').removeClass('processing cko-processing');
                        }

                        if(reload) {
                            window.location.reload();
                        }
                        loaderJs = 0;

                    },
                    lightboxActivated: function() {

                        loaderJs =1;
                        CheckoutIntegration.setCustomerEmail(document.getElementById('billing_email').value);
                        if( jQuery('#terms').length) {
                            jQuery('#terms').attr('checked', 'checked');
                        }

                    },
                    lightboxLoadFailed: function(event) {


                    }


                };


                jQuery('#place_order').bind('click touchstart',function(event){
                    event.stopPropagation();
                    wc_checkout_params.is_checkout = 0;
                    if(jQuery('#payment_method_checkoutapipayment:checked').length &&  jQuery('.woocommerce-invalid')
                            .length < 1) {
                        //   event.preventDefault();
                        // jQuery('[name^=checkout].checkout')[0].onsubmit();
                        //  jQuery('#place_order').attr('disabled',true);
                    }else {
                        jQuery('#place_order').removeAttr('disabled');
                        if( jQuery('[name^=checkout].checkout').is('.cko-processing') && !jQuery('#payment_method_checkoutapipayment:checked').length) {
                            jQuery('[name^=checkout].checkout').removeClass('processing cko-processing');
                        }
                    }
                });

                jQuery('[name=payment_method]').bind('click touchstart',function(event){

                    if(!jQuery('#payment_method_checkoutapipayment:checked').length && jQuery('[name^=checkout].checkout').is('.cko-processing')) {
                        jQuery('#place_order').removeAttr('disabled');
                        jQuery('[name^=checkout].checkout').removeClass('processing cko-processing');
                    }
                });



            </script>

        </div>



    <?php

    }

    public function process_payment($order_id)
    {

        global $woocommerce;
        $order = new WC_Order( $order_id );
        $grand_total = $order->order_total;
        $amount = $grand_total*100;
        $config['authorization'] = CHECKOUTAPI_SECRET_KEY;

        if(!( parent::get_post('cko_cc_paymenToken'))) {

            $error_message =__ ('Please enter your credit card details');

            return array (
                'result' => 'failure' ,
                'messages' => '<div style="display:none">loadLight</div>' ,
                'reload' => false ,
                'loadLight' => true ,

            );
        }

        if(parent::get_post('redirectUrl')!='' ) {
            $urlRedirect = parent::get_post('redirectUrl').'&trackId='.$order_id;
            if ( !session_id() )
                session_start();
            $_SESSION['trackId'] = $order_id;
            $_SESSION['cko_cc_paymenToken'] = parent::get_post('cko_cc_paymenToken');
            return array('result' => 'success', 'redirect' => parent::get_post('redirectUrl'), 'order_status'=>$order);
        }

        $config['paymentToken'] = parent::get_post('cko_cc_paymenToken');
        $Api = CheckoutApi_Api::getApi(array('mode'=>CHECKOUTAPI_ENDPOINT));

        $respondCharge = $Api->verifyChargePaymentToken($config);
        $customerConfig['authorization'] = CHECKOUTAPI_SECRET_KEY;
        $customerConfig['customerId'] = $respondCharge->getCard()->getCustomerId() ;
        $customerConfig['postedParam'] = array ('phone'=>array('number'=>$_POST['billing_phone']));
        $customerCharge = $Api->updateCustomer($customerConfig);



        return parent::_validateChrage($order, $respondCharge);
    }

    public  function validateToken()
    {
        if(!( parent::get_post('cko_cc_paymenToken')) && parent::get_post('payment_method') =='checkoutapipayment' &&
            wc_notice_count( 'error' ) ==0) {

            $error_message =__ ('Please enter your credit card details');
            wc_add_notice(__('Payment Notice: ', 'woothemes').$error_message.'<div
            style="display:none">loadLight</div>', 'error' );

            return array (
                'result' => 'failure' ,
                'messages' => '<div style="display:none">loadLight</div>' ,
                'reload' => false ,
                'loadLight' => true ,

            );
        }
    }

    private function renderJsConfigrenderJsConfig($email, $amount, $name)
    {

    }

    public function setPaymentToken()
    {

        if(! WC()->cart) {
            return false;
        }
        $Api = CheckoutApi_Api::getApi(array('mode'=>CHECKOUTAPI_ENDPOINT));

        global $woocommerce;
        $cart = WC()->cart;
        $customer = WC()->customer;
        $productCart = WC()->cart->cart_contents;
        $current_user = wp_get_current_user();
        $post = array();
        if(isset($_POST['post_data'])) {
            $vars = explode ( '&' , $_POST[ 'post_data' ] );
            foreach ( $vars as $k => $value ) {
                $v = explode ( '=' , urldecode ( $value ) );
                $post[ $v[ 0 ] ] = $v[ 1 ];
            }
        }
        $email = "Email@youremail.com";

        $name = 'Your card holder name';

        if(isset($current_user->user_email) && $current_user->user_email){
            $email = $current_user->user_email;
        }

        if(isset($current_user->user_first_name)){
            $name = $current_user->user_first_name;
        }

        if(isset($post['billing_first_name']) && $post['billing_first_name']) {
            $name = $post['billing_first_name'];
        }

        if(isset($post['billing_last_name']) && $post['billing_last_name']) {
            $name = $post['billing_first_name']. ' '.$post['billing_last_name'];
        }

        if(isset($post['billing_email']) && $post['billing_email']) {
            $email = $post['billing_email'];
        }

        $grand_total = $cart->total;
        $amount = $grand_total*100;
        $config['authorization'] = CHECKOUTAPI_SECRET_KEY;

        $config['timeout'] = CHECKOUTAPI_TIMEOUT;



        $config['postedParam'] = array(
            'email'       =>    $email,
            'value'       =>    $amount,
            "metadata"    =>    array('userAgent'=>$_SERVER['HTTP_USER_AGENT']),
            'currency'    =>    get_woocommerce_currency()
        );

        $extraConfig = array();
        if(CHECKOUTAPI_PAYMENTACTION == 'Capture'){
            $extraConfig = parent::_captureConfig();
        }
        else {
            $extraConfig= parent::_authorizeConfig();
        }

        $config = array_merge_recursive($extraConfig,$config);

        if($customer) {
            $config['postedParam']['card']['billingDetails'] = array (
                'addressline1'  =>    $customer->address,
                'addressline2'  =>    $customer->address_2,
                'city'          =>    $customer->city,
                'country'       =>    $customer->country,

                'state'         =>    $customer->country,

            );

        }
        $products = null;

        foreach ($productCart as $item ) {
            $variation = $item['variation'];
            $extraTxt = '';
            if(!empty($variation)) {
               foreach($variation as $kv =>$vv) {
                   $extraTxt .= $kv.': '.$vv.',';
               }
            }
            $products[] = array (
                'name'       =>     $item['data']->post->post_title.' '.$extraTxt,
                'sku'        =>     $item['product_id'],
                'price'      =>     $item['line_total'],
                'quantity'   =>     $item['quantity'],

            );
        }

        if($products){
            $config[ 'postedParam' ][ 'products' ]  =   $products;
        }

        if($customer) {
            $config[ 'postedParam' ][ 'shippingDetails' ] = array (
                'addressline1'  =>    $customer->shipping_address ,
                'addressline2'  =>    $customer->shipping_address_2 ,
                'city'          =>    $customer->shipping_city ,
                'country'       =>    $customer->shipping_country ,
                'postcode'      =>    $customer->shipping_postcode ,
                'state'         =>    $customer->shipping_state
            );
        }

        $paymentTokenCharge = $Api->getPaymentToken($config);

        $paymentToken    =   '';
        if($paymentTokenCharge->isValid()){
            $paymentToken = $paymentTokenCharge->getId();
        }

        if(!$paymentToken) {

            $error_message = $paymentTokenCharge->getExceptionState()->getErrorMessage().
                ' ( '.$paymentTokenCharge->getEventId().')';
            wc_add_notice( __('Payment error: ', 'woothemes') . $error_message, 'error' );
        }

        $this->_paymentToken = $paymentToken;

    }

    public function getPaymentToken()
    {
        if(!$this->_paymentToken) {
            $this->setPaymentToken();
        }
        return $this->_paymentToken;
    }
}
