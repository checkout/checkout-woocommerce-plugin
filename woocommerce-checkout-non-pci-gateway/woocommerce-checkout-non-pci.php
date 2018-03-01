<?php

include_once ('lib/autoload.php');

/**
 * Class WC_Checkout_Non_Pci
 *
 * @version 20160304
 */
class WC_Checkout_Non_Pci extends WC_Payment_Gateway {

    const PAYMENT_METHOD_CODE       = 'woocommerce_checkout_non_pci';
    const PAYMENT_ACTION_AUTHORIZE  = 'authorize';
    const PAYMENT_ACTION_CAPTURE    = 'authorize_capture';
    const PAYMENT_CARD_NEW_CARD     = 'new_card';
    const AUTO_CAPTURE_TIME         = 0;
    const RENDER_MODE               = 2;
    const VERSION                   = '2.5.5';
    const RENDER_NAMESPACE          = 'Checkout';
    const CARD_FORM_MODE            = 'cardTokenisation';
    const JS_PATH_CARD_TOKEN        = 'https://cdn.checkout.com/sandbox/js/checkout.js';
    const JS_PATH_CARD_TOKEN_LIVE   = 'https://cdn.checkout.com/js/checkout.js';
    const REDIRECTION_URL           = '/wp-content/plugins/woocommerce-checkout-non-pci-gateway/controllers/api/3dsecure.php';
    const HOSTED_URL_SANDOX         = 'https://secure.checkout.com/sandbox/payment/';
    const HOSTED_URL_LIVE           = 'https://secure.checkout.com/payment/';
    const FRAMES_SANDBOX_URL      = 'https://cdn.checkout.com/js/frames.js';
    const FRAMES_LIVE_URL         = 'https://cdn.checkout.com/js/frames.js';
    const TRANSACTION_INDICATOR_REGULAR = 1;

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
        $this->method_title         = __("Checkout.com Credit Card (Non PCI Version)", 'woocommerce-checkout-non-pci');
        $this->method_description   = __("Checkout.com Credit Card (Non PCI Version) Plug-in for WooCommerce", 'woocommerce-checkout-non-pci');
        $this->title                = __("Checkout.com Credit Card (Non PCI Version)", 'woocommerce-checkout-non-pci');

        $this->icon         = null;
        $this->supports     = array(
            'products',
            'refunds',
            'subscriptions',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_cancellation'
        );
        $this->has_fields   = true;

        // This basically defines your settings which are then loaded with init_settings()
        $this->init_form_fields();
        $this->init_settings();

        // Turn these settings into variables we can use
        foreach ( $this->settings as $setting_key => $value ) {
            $this->$setting_key = $value;
        }

        // Check if saved cards is enabled from backend
        $this->saved_cards = $this->get_option( 'saved_cards' ) === "yes" ? true : false;

        // Save settings
        if (is_admin()) {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }

        $this->notify_url        = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'WC_Checkout_Non_Pci', home_url( '/' ) ) );
        // Payment listener/API hook
        add_action( 'woocommerce_api_wc_checkout_non_pci', array( $this, 'callback' ) );
   
        add_action('woocommerce_subscription_status_on-hold',array($this, 'updatePlan'));
        add_action('woocommerce_subscription_status_active',array($this, 'updatePlan'));
        add_action('woocommerce_subscription_status_cancelled',array($this, 'updatePlan'));
    }

    /**
     * init admin settings form
     *
     * @version 20160304
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'		=> __( 'Enable / Disable', 'woocommerce-checkout-non-pci' ),
                'label'		=> __( 'Enable Payment Method', 'woocommerce-checkout-non-pci' ),
                'type'		=> 'checkbox',
                'default'	=> 'no',
            ),
            'title' => array(
                'title'		=> __('Title', 'woocommerce-checkout-non-pci'),
                'type'		=> 'text',
                'desc_tip'	=> __('Payment title the customer will see during the checkout process.', 'woocommerce-checkout-non-pci'),
                'default'	=> __( 'Credit Card Non PCI (Checkout.com)', 'woocommerce-checkout-non-pci' ),
            ),
            'secret_key' => array(
                'title'		=> __('Secret Key', 'woocommerce-checkout-non-pci'),
                'type'		=> 'password',
                'desc_tip'	=> __( 'Only used for requests from the merchant server to the Checkout API', 'woocommerce-checkout-non-pci' ),
            ),
            'private_shared_key' => array(
                'title'		=> __('Private Shared Key', 'woocommerce-checkout-non-pci'),
                'type'		=> 'password',
                'desc_tip'	=> __( 'Used for webhooks from Checkout API', 'woocommerce-checkout-non-pci' ),
            ),
            'public_key' => array(
                'title'		=> __('Public Key', 'woocommerce-checkout-non-pci'),
                'type'		=> 'password',
                'desc_tip'	=> __( 'Used for JS Checkout API', 'woocommerce-checkout-non-pci' ),
            ),
            'void_status' => array(
                'title'		=> __( 'Enable / Disable', 'woocommerce-checkout-non-pci' ),
                'label'		=> __( 'When voided change order status to Cancelled', 'woocommerce-checkout-non-pci' ),
                'type'		=> 'checkbox',
                'default'	=> 'no',
            ),
            'payment_action' => array(
                'title'       => __('Payment Action', 'woocommerce-checkout-non-pci'),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'description' => __('Choose whether you wish to capture funds immediately or authorize payment only.', 'woocommerce-checkout-non-pci'),
                'default'     => 'authorize',
                'desc_tip'    => true,
                'options'     => array(
                    self::PAYMENT_ACTION_CAPTURE    => __('Authorize and Capture', 'woocommerce-checkout-non-pci'),
                    self::PAYMENT_ACTION_AUTHORIZE  => __('Authorize Only', 'woocommerce-checkout-non-pci')
                )
            ),
            'auto_cap_time' => array(
                'title'     => __('Auto Capture Time', 'woocommerce-checkout-non-pci'),
                'type'      => 'text',
                'desc_tip'  => __('Time to automatically capture charge. It is recommended to set it to a minimun of 0.02', 'woocommerce-checkout-non-pci'),
                'default'   => __( '0.02', 'woocommerce-checkout-non-pci' ),
            ),
            'order_status' => array(
                'title'       => __('New Order Status', 'woocommerce-checkout-non-pci'),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'default'     => 'on-hold',
                'desc_tip'    => true,
                'options'     => array(
                    'on-hold'    => __('On Hold', 'woocommerce-checkout-non-pci'),
                    'processing' => __('Processing', 'woocommerce-checkout-non-pci')
                )
            ),
            'mode' => array(
                'title'       => __('Endpoint URL mode', 'woocommerce-checkout-non-pci'),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'description' => __('When going on live production, Endpoint url mode should be set to live.', 'woocommerce-checkout-non-pci'),
                'default'     => 'sandbox',
                'desc_tip'    => true,
                'options'     => array(
                    'sandbox'   => __('SandBox', 'woocommerce-checkout-non-pci'),
                    'live'      => __('Live', 'woocommerce-checkout-non-pci')
                )
            ),
            'is_3d' => array(
                'title'       => __('Is 3D', 'woocommerce-checkout-non-pci'),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'description' => __('3D Secure Card Validation.', 'woocommerce-checkout-non-pci'),
                'default'     => '1',
                'desc_tip'    => true,
                'options'     => array(
                    '1' => __('No', 'woocommerce-checkout-non-pci'),
                    '2' => __('Yes', 'woocommerce-checkout-non-pci')
                )
            ),
            'timeout' => array(
                'title'		=> __('Timeout value for a request to the gateway', 'woocommerce-checkout-non-pci'),
                'type'		=> 'text',
                'desc_tip'	=> __('The timeout value for a request to the gateway. Default is 60 seconds. Please notify checkout.com support team before increasing the value.', 'woocommerce-checkout-non-pci'),
                'default'	=> __( '60', 'woocommerce-checkout-non-pci' ),
            ),

            'integration_type' => array(
                'title'       => __('Integration Type', 'woocommerce-checkout-non-pci'),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'default'     => 'checkoutjs',
                'desc_tip'    => 'Customise the Integration type that will display on your checkout page',
                'options'     => array(
                    'checkoutjs'         => __('CheckoutJs', 'woocommerce-checkout-non-pci'),
                    'hosted'          => __('Hosted', 'woocommerce-checkout-non-pci'),
                    'frames'  => __('Frames', 'woocommerce-checkout-non-pci'),
                )
            ),

            'saved_cards' => array(
                'title'       => __( 'Saved Cards', 'woocommerce-checkout-non-pci' ),
                'label'       => __( 'Enable Payment via Saved Cards', 'woocommerce-checkout-non-pci' ),
                'type'        => 'checkbox',
                'description' => __( 'If enabled, users will be able to pay with a saved card during checkout.', 'woocommerce-checkout-non-pci' ),
                'default'     => 'no'
            ),

            'adv_setting_js_hpp' => array(
                'title'       => __( 'Advance option for CheckoutJs and Hosted solution', 'woocommerce' ),
                'type'        => 'title',
            ),

            'logo_url' => array(
                'title'		=> __('Lightbox logo url', 'woocommerce-checkout-non-pci'),
                'type'		=> 'text',
                'desc_tip'	=> __('The URL of your company logo. Must be 180 x 36 pixels. Default: Checkout logo.', 'woocommerce-checkout-non-pci'),
            ),
            'theme_color' => array(
                'title'		=> __('Theme color', 'woocommerce-checkout-non-pci'),
                'type'		=> 'text',
                'desc_tip'	=> __('#HEX value of your chosen theme color.', 'woocommerce-checkout-non-pci'),
                'default'	=> __( '#00b660', 'woocommerce-checkout-non-pci' ),
            ),
            'use_currency_code' => array(
                'title'		=> __( 'Enable / Disable', 'woocommerce-checkout-non-pci' ),
                'label'		=> __( 'Use Currency Code', 'woocommerce-checkout-non-pci' ),
                'type'		=> 'checkbox',
                'default'	=> 'no',
                'desc_tip'  => __('Use ISO3 currency code (e.g. GBP) instead of the currency symbol (e.g. £)', 'woocommerce-checkout-non-pci'),
            ),
            'form_title' => array(
                'title'		=> __('Js Title', 'woocommerce-checkout-non-pci'),
                'type'		=> 'text',
                'desc_tip'	=> __('The title of your payment form.', 'woocommerce-checkout-non-pci'),
            ),
            'widget_color' => array(
                'title'		=> __('Widget Color', 'woocommerce-checkout-non-pci'),
                'type'		=> 'text',
                'desc_tip'	=> __('#HEX value of your chosen widget color.', 'woocommerce-checkout-non-pci'),
                'default'	=> __('#333', 'woocommerce-checkout-non-pci' ),
            ),
            'form_button_color' => array(
                'title'		=> __('Form Button Color', 'woocommerce-checkout-non-pci'),
                'type'		=> 'text',
                'desc_tip'	=> __('#HEX value of your chosen lightbox submit button color.', 'woocommerce-checkout-non-pci'),
                'default'	=> __('#00b660', 'woocommerce-checkout-non-pci' ),
            ),
            'form_button_color_label' => array(
                'title'		=> __('Form Button Color Label', 'woocommerce-checkout-non-pci'),
                'type'		=> 'text',
                'desc_tip'	=> __('#HEX value of your chosen lightbox submit button label color.', 'woocommerce-checkout-non-pci'),
                'default'	=> __('#ffffff', 'woocommerce-checkout-non-pci' ),
            ),
            'overlay_shade' => array(
                'title'       => __('Overlay Shade', 'woocommerce-checkout-non-pci'),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'default'     => 'dark',
                'desc_tip'    => true,
                'options'     => array(
                    'dark'  => __('Dark', 'woocommerce-checkout-non-pci'),
                    'light' => __('Light', 'woocommerce-checkout-non-pci')
                )
            ),
            'overlay_opacity' => array(
                'title'		=> __('Overlay Opacity', 'woocommerce-checkout-non-pci'),
                'type'		=> 'text',
                'desc_tip'	=> __('A number between 0.7 and 1', 'woocommerce-checkout-non-pci'),
                'default'	=> __('0.8', 'woocommerce-checkout-non-pci' ),
            ),
            'show_mobile_icons' => array(
                'title'		=> __( 'Enable / Disable', 'woocommerce-checkout-non-pci' ),
                'label'		=> __( 'Show Mobile Icons', 'woocommerce-checkout-non-pci' ),
                'type'		=> 'checkbox',
                'default'	=> 'no',
                'desc_tip'  => __('Show widget icons on mobile.', 'woocommerce-checkout-non-pci'),
            ),
            'widget_icon_size' => array(
                'title'       => __('Widget Icon Size', 'woocommerce-checkout-non-pci'),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'default'     => 'small',
                'desc_tip'    => true,
                'options'     => array(
                    'small'     => __('Small', 'woocommerce-checkout-non-pci'),
                    'medium'    => __('Medium', 'woocommerce-checkout-non-pci'),
                    'large'     => __('Large', 'woocommerce-checkout-non-pci'),
                )
            ),
            'payment_mode' => array(
                'title'       => __('Payment Mode', 'woocommerce-checkout-non-pci'),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'default'     => 'mixed',
                'desc_tip'    => 'Customise the payment mode: mixed , card, localpayment.',
                'options'     => array(
                    'mixed'         => __('Mixed', 'woocommerce-checkout-non-pci'),
                    'cards'          => __('Cards', 'woocommerce-checkout-non-pci'),
                    'localpayment'  => __('Local Payment', 'woocommerce-checkout-non-pci'),
                )
            ),

            'adv_setting_frames' => array(
                'title'       => __( 'Advance option for FramesJs Solution', 'woocommerce' ),
                'type'        => 'title',
            ),

            'custom_css' => array(
                'title'     => __('Custom Css Url', 'woocommerce-checkout-non-pci'),
                'type'      => 'text',
                'desc_tip'  => __('Custom css link', 'woocommerce-checkout-non-pci'),
            ),

            'frames_theme' => array(
                'title'       => __('Theme', 'woocommerce-checkout-non-pci'),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'default'     => 'standard',
                'desc_tip'    => 'Customise the payment mode: mixed , card, localpayment.',
                'options'     => array(
                    'standard'         => __('Standard', 'woocommerce-checkout-non-pci'),
                    'simple'          => __('Simple', 'woocommerce-checkout-non-pci'),
                )
            ),

            'custom_css' => array(
                'title'     => __('Custom Css', 'woocommerce-checkout-non-pci'),
                'type'      => 'textarea',
                'desc_tip'  => __('Custom css to customise FramesJs layout', 'woocommerce-checkout-non-pci'),
            ),

            'adv_setting_subscription' => array(
                'title'       => __( 'Advance option for Subscription', 'woocommerce' ),
                'type'        => 'title',
            ),

            'reactivate_cancel' => array(
                'title'     => __( 'Enable / Disable', 'woocommerce-checkout-non-pci' ),
                'label'     => __( 'Allow customer to reactivate or cancel subscription from his/her Account', 'woocommerce-checkout-non-pci' ),
                'type'      => 'checkbox',
                'default'   => 'no',
                'desc_tip'  => __('Allow customer to reactivate or cancel subscription from his/her Account', 'woocommerce-checkout-non-pci'),
            ),
        );
    }



    /**
     * Create Charge on Checkout.com
     *
     * @param int $order_id
     * @return array|void
     *
     * @version 20160316
     */
    public function process_payment($order_id) {
        include_once( 'includes/class-wc-gateway-checkout-non-pci-request.php');
        include_once( 'includes/class-wc-gateway-checkout-non-pci-validator.php');
        include_once( 'includes/class-wc-gateway-checkout-non-pci-customer-card.php');

        global $woocommerce;

        $order          = new WC_Order($order_id);
        $request        = new WC_Checkout_Non_Pci_Request($this);
        $cardToken      = !empty($_POST["{$request->gateway->id}-card-token"]) ? $_POST["{$request->gateway->id}-card-token"] : '';
        $lpRedirectUrl  = !empty($_POST["{$request->gateway->id}-lp-redirect-url"]) ? $_POST["{$request->gateway->id}-lp-redirect-url"] : NULL;
        $lpName         = !empty($_POST["{$request->gateway->id}-lp-name"]) ? $_POST["{$request->gateway->id}-lp-name"] : NULL;
        $savedCardData  = array();
        $savedCard      = !empty($_POST["{$request->gateway->id}-saved-card"]) ? $_POST["{$request->gateway->id}-saved-card"] : '';



        $mobileRedirectUrl  = !empty($_POST["{$request->gateway->id}-mobile-redirectUrl"]) ? $_POST["{$request->gateway->id}-mobile-redirectUrl"] : NULL;

        $integrationType = $this->get_option('integration_type');

        if($integrationType == 'hosted' && $savedCard == self::PAYMENT_CARD_NEW_CARD){

            $_SESSION['checkout_save_card_checked'] = isset($_POST['save-card-checkbox']);

            return array(
                'result'        => 'success',
                'redirect'      => $this->notify_url
            );

        }

        if($integrationType =='frames'  && $savedCard == self::PAYMENT_CARD_NEW_CARD){ 
            $checkout   = new WC_Checkout_Non_Pci();
            $request    = new WC_Checkout_Non_Pci_Request($checkout);
            $cardRequest = new WC_Checkout_Non_Pci_Customer_Card();
            $order_status = $order->get_status();
            if($order_status == 'pending'){   

                $result     = $request->createCharge($order,$cardToken,$savedCardData);

                if (!empty($result['error'])) {
                    WC_Checkout_Non_Pci::log($result);
                    WC_Checkout_Non_Pci_Validator::wc_add_notice_self($result['error'], 'error');

                    $checkoutUrl = WC_Cart::get_checkout_url();

                    return array(
                        'result'        => 'success',
                        'redirect'      => $checkoutUrl,
                    );
                }

                $entityId       = $result->getId();
                $redirectUrl    = $result->getRedirectUrl();

                if ($redirectUrl) {
                    $_SESSION['checkout_payment_token'] =  $entityId;
                    $url = $redirectUrl;

                    return array(
                        'result'        => 'success',
                        'redirect'      => $url,
                    );
                }

                update_post_meta($orderId, '_transaction_id', $entityId);

                $order->update_status($request->getOrderStatus(), __("Checkout.com Charge Approved (Transaction ID - {$entityId}", 'woocommerce-checkout-non-pci'));
                $order->reduce_order_stock();
                $woocommerce->cart->empty_cart();

                if (is_user_logged_in() && $this->saved_cards) {
                    $cardRequest->saveCard($result, $order->user_id, isset($_POST['save-card-checkbox']));
                }

                $url = $checkout->get_return_url($order);

                return array(
                        'result'        => 'success',
                        'redirect'      => $url,
                    );
            }
        }

        if(!is_null($mobileRedirectUrl) && $savedCard == self::PAYMENT_CARD_NEW_CARD){

            $_SESSION['checkout_save_card_checked'] = isset($_POST['save-card-checkbox']);

            return array(
                'result'        => 'success',
                'redirect'      => $mobileRedirectUrl.'&customerEmail='.$_POST['billing_email'].'&contextId='.$order_id,
            );
        }

        if (!is_null($lpRedirectUrl) && !is_null($lpName)) {
            $parts = parse_url($lpRedirectUrl);
            parse_str($parts['query'], $query);

            if($lpName == 'Boleto'){
                $paymentToken   = $query['cko-payment-token'];
            } else {
                $paymentToken   = $query['paymentToken'];
            }


            $result         = $request->verifyChargePaymentToken($order, $paymentToken);

            if (!empty($result['error'])) {
                WC_Checkout_Non_Pci_Validator::wc_add_notice_self($this->gerProcessErrorMessage($result['error']), 'error');
                return;
            }

            $entityId = $result->getId();

            $order->update_status($request->getOrderStatus(), __("Checkout.com Charge Approved (Transaction ID - {$entityId}", 'woocommerce-checkout-non-pci'));
            $order->reduce_order_stock();
            $woocommerce->cart->empty_cart();

            update_post_meta($order_id, '_transaction_id', $entityId);

            $_SESSION['checkout_local_payment_token'] = strtolower($paymentToken);

            return array(
                'result'        => 'success',
                'redirect'      => $lpRedirectUrl
            );
        }

        if ($savedCard !== self::PAYMENT_CARD_NEW_CARD) {
            $savedCardData = WC_Checkout_Non_Pci_Customer_Card::getCustomerCardData($savedCard, $order->user_id);

            if (!$savedCardData) {
                WC_Checkout_Non_Pci_Validator::wc_add_notice_self('Payment error: Please check your card data.', 'error' );
                return;
            }
        }
        else if (empty($cardToken)) {
            WC_Checkout_Non_Pci_Validator::wc_add_notice_self($this->gerProcessErrorMessage('Payment error: Please check your card data.'), 'error');
            return;
        }

        $result = $request->createCharge($order, $cardToken, $savedCardData);

        if (!empty($result['error'])) {
            WC_Checkout_Non_Pci_Validator::wc_add_notice_self($this->gerProcessErrorMessage($result['error']), 'error');

            return;
        }

        $entityId       = $result->getId();
        $redirectUrl    = $result->getRedirectUrl();

        if ($redirectUrl) {
            $_SESSION['checkout_payment_token'] = strtolower($entityId);

            return array(
                'result'    => 'success',
                'redirect'  => $redirectUrl
            );
        }

        update_post_meta($order_id, '_transaction_id', $entityId);

        $order->update_status($request->getOrderStatus(), __("Checkout.com Charge Approved (Transaction ID - {$entityId}", 'woocommerce-checkout-non-pci'));
        $order->reduce_order_stock();
        $woocommerce->cart->empty_cart();

        if (is_user_logged_in() && $this->saved_cards) {
            WC_Checkout_Non_Pci_Customer_Card::saveCard($result, $order->user_id, isset($_POST['save-card-checkbox']));
        }

        return array(
            'result'        => 'success',
            'redirect'      => $this->get_return_url($order)
        );
    }

    public function gerProcessErrorMessage($errorMessage) {
        return __($errorMessage, 'woocommerce-checkout-non-pci');
    }

    /**
     * Payment form on checkout page.
     *
     * @version 20160304
     */
    public function payment_fields() {
        $this->credit_card_form();
    }

    public function callback(){
        include_once('includes/class-wc-gateway-checkout-non-pci-request.php');
        include_once('includes/class-wc-gateway-checkout-non-pci-validator.php');
        include_once('includes/class-wc-gateway-checkout-non-pci-customer-card.php');
        
        global $woocommerce;
        $orderId    = $woocommerce->session->order_awaiting_payment;

        if (empty($orderId)) {
            WC_Checkout_Non_Pci::log('Empty OrderId');
            WC_Checkout_Non_Pci_Validator::wc_add_notice_self('An error has occured while processing your transaction.', 'error');
            wp_redirect(WC_Cart::get_checkout_url());
            exit();
        }

        $order      = new WC_Order( $orderId );
        $checkout   = new WC_Checkout_Non_Pci();
        $amount     = $order->get_total();
        $mode       =  $checkout->settings['mode'];
        $hppUrl     = $mode == 'sandbox' ? self::HOSTED_URL_SANDOX : self::HOSTED_URL_LIVE;
        $paymentMode = $checkout->settings['payment_mode'];

        if (class_exists('WC_Subscriptions_Order')) {
            if(WC_Subscriptions_Cart::cart_contains_subscription()){
                $paymentMode = 'cards';
            }
        }

        $Api            = CheckoutApi_Api::getApi(array('mode' => $mode));
        $orderTotal     = $Api->valueToDecimal($amount, get_woocommerce_currency());
        $requestModel   = new WC_Checkout_Non_Pci_Request($this);
        $paymentToken   = $requestModel->createPaymentToken($amount, get_woocommerce_currency());
        $redirectUrl    = get_home_url(). self::REDIRECTION_URL;
        $imageUrl       = get_home_url().'/wp-content/plugins/woocommerce-checkout-non-pci-gateway/view/image/load.gif';

        $billingDetails = array (
            'addressLine1'  => $order->billing_address_1,
            'addressLine2'  => $order->billing_address_2,
            'postcode'      => $order->billing_postcode,
            'country'       => $order->billing_country,
            'city'          => $order->billing_city,
            'state'         => $order->billing_state,
            'phone'         => array('number' => $order->billing_phone)
        );
       
        echo '<p><center>You will be redirected to the payment gateway.</center></p>';
        echo '<p><center><img src="'.$imageUrl.'" /></center></p>';

        echo'<form id="payment-form" style="display:none" action="'.$hppUrl.'" method="POST">';
        echo '<input name="publicKey" value="'.$checkout->settings["public_key"].'"/>';
        echo '<input name="paymentToken" value="'.$paymentToken["token"].'"/>';
        echo '<input name="customerEmail" value="'.$order->billing_email.'"/>';
        echo '<input name="value" value="'.$orderTotal.'"/>';
        echo '<input name="currency" value="'.get_woocommerce_currency().'"/>';
        echo '<input name="cardFormMode" value="'.self::CARD_FORM_MODE.'"/></input>';
        echo '<input name="paymentMode" value="'.$paymentMode.'"/>';
        echo '<input name="environment" value="'.$checkout->settings["mode"].'"/>';
        echo '<input name="redirectUrl" value="'.$redirectUrl.'"/>';
        echo '<input name="cancelUrl" value="'.$redirectUrl.'"/>';
        echo '<input name="contextId" id="contextId" value="'.$orderId.'"/>';
        echo '<input name="logoUrl" value="'.$checkout->settings["logo_url"].'"/>';
        echo '<input name="title" value="'.$checkout->settings["form_title"].'"/>';
        echo '<input name="themeColor" value="'.$checkout->settings["theme_color"].'"/>';
        echo '<input name="billingDetails" id="billingDetails" value="'.$billingDetails.'"/>';
        echo'</form>';

        echo'<script>';
        echo'document.getElementById("payment-form").submit()';
        echo'</script>';

        exit();
    }

    /**
     * Custom credit card form
     *
     * @param array $args
     * @param array $fields
     * @return bool
     */
    public function credit_card_form($args = array(), $fields = array()) {
        include_once( 'includes/class-wc-gateway-checkout-non-pci-request.php');
        include_once( 'includes/class-wc-gateway-checkout-non-pci-customer-card.php');
        global $woocommerce;

        wp_enqueue_script( 'wc-credit-card-form' );

        // Pay Order Page
        $isPayOrder = !empty($_GET['pay_for_order']) ? (boolean)$_GET['pay_for_order'] : false;

        if ($isPayOrder) {
            if(!empty($_GET['order_id'])) {
                $orderId    = $_GET['order_id'];
            } else if (!empty($_GET['key'])){
                $orderKey   = $_GET['key'];
                $orderId    = wc_get_order_id_by_order_key($orderKey);
            } else {
                return false;
            }

            if (empty($orderId)) return false;

            $order = new WC_Order($orderId);

            if (!is_object($order)) return false;

            $billingEmail       = $order->billing_email;
            $customerName       = $order->billing_first_name;
            $customerLastName   = $order->billing_last_name;

            if (empty($billingEmail) || empty($customerName) || empty($customerLastName)) {
                echo '<p>' . __('Some required fields are empty.', 'woocommerce-checkout-non-pci') . '</p>';
                return false;
            }

            WC()->session->set( 'order_awaiting_payment' , $orderId );

            $orderTotal = $order->get_total();
        } else {
            $orderTotal = $woocommerce->cart->total;
        }

        $paymentMode = $this->get_option('payment_mode');

        if (class_exists('WC_Subscriptions_Order')) {
              if(WC_Subscriptions_Cart::cart_contains_subscription()){
                $paymentMode = 'cards';
              }
        }

        $integrationType = $this->get_option('integration_type');
        $redirectUrl = get_home_url(). self::REDIRECTION_URL;
        $requestModel   = new WC_Checkout_Non_Pci_Request($this);
        $paymentToken   = $requestModel->createPaymentToken($orderTotal, get_woocommerce_currency());
        $checkoutFields = !$isPayOrder ? json_encode($woocommerce->checkout->checkout_fields,JSON_HEX_APOS) : json_encode(array());
        $cardList = (is_user_logged_in() && $this->saved_cards) ? WC_Checkout_Non_Pci_Customer_Card::getCustomerCardList(get_current_user_id()) : array();
        ?>
        
        <fieldset id="<?php echo $this->id; ?>-cc-form">
            <?php do_action( 'woocommerce_credit_card_form_start', $this->id ); ?>
            <?php if(!empty($cardList)): ?>
                <ul class="wc_payment_methods payment_methods">
                    <?php foreach($cardList as $index => $card):?>
                        <li>
                            <p>
                                <input id="checkout-saved-card-<?php echo $index?>" class="checkout-saved-card-radio" type="radio" name="<?php echo $this->id . '-saved-card'?>" value="<?php echo md5($card->entity_id . '_' . $card->card_number . '_' . $card->card_type)?>"/>
                                <label for="checkout-saved-card-<?php echo $index?>"><?php echo sprintf('xxxx-%s', $card->card_number) . ' ' . $card->card_type?></label>
                        </li>
                    <?php endforeach?>
                    <li>
                        <p>
                            <input id="checkout-new-card" class="checkout-new-card-radio" type="radio" name="<?php echo $this->id . '-saved-card'?>" value="<?php echo self::PAYMENT_CARD_NEW_CARD?>"/>
                            <label for="checkout-new-card"><?php echo __('Use New Card', 'woocommerce') ?></label>
                        
                    </li>
                </ul>
            <?php else:?>
             <input id="checkout-new-card" class="checkout-new-card-input" type="hidden" name="<?php echo $this->id . '-saved-card'?>" value="<?php echo self::PAYMENT_CARD_NEW_CARD?>"/>
            <?php endif?>
            <p class="form-row form-row-wide checkout-non-pci-new-card-row">
                <?php if($this->saved_cards):?>
                        <?php if(is_user_logged_in()): ?>
                            <input type="checkbox" name="save-card-checkbox" id="save-card-checkbox" value="1">
                            <label for="save-card-checkbox" style="position:relative; display:inline-block; margin-bottom: 10px; margin-top: 10px">Save card for future payments</label>

                            <?php else: ?>
                            <div id="save-card-check" style="display: none;">
                                <input type="checkbox" name="save-card-checkbox" id="save-card-checkbox" value="1"  >
                                <label for="save-card-checkbox" style="position:relative; display:inline-block; margin-bottom: 10px; margin-top: 10px">Save card for future payments</label>
                            </div>
                        <?php endif?>
                <?php endif?>
                <?php if(!empty($paymentToken)):?>
                    <?php if($isPayOrder):?>
                        <input type="hidden" id="billing_email" value="<?php echo $billingEmail?>"/>
                        <input type="hidden" id="billing_first_name" value="<?php echo $customerName?>"/>
                        <input type="hidden" id="billing_last_name" value="<?php echo $customerLastName?>"/>
                    <?php endif?>
                    <input type="hidden" id="cko-hosted-url" name="<?php echo esc_attr( $this->id ) ?>-hosted-url" value="<?php echo $integrationType ?>"/>
                    <input type="hidden" id="cko-card-token" name="<?php echo esc_attr( $this->id ) ?>-card-token" value=""/>
                    <input type="hidden" id="cko-is-mobile" name="<?php echo esc_attr( $this->id ) ?>-is-mobile" value=""/>
                    <input type="hidden" id="cko-mobile-redirectUrl" name="<?php echo esc_attr( $this->id ) ?>-mobile-redirectUrl" value=""/>
                    <input type="hidden" id="cko-inv-redirectUrl" name="cko-inv-redirectUrl" value=""/>
                    <input type="hidden" id="cko-lp-redirectUrl" name="<?php echo esc_attr( $this->id ) ?>-lp-redirect-url" value=""/>
                    <input type="hidden" id="cko-lp-lpName" name="<?php echo esc_attr( $this->id ) ?>-lp-name" value=""/>
                    <div id="checkout-api-js-hover" style="display: none; z-index: 100; position: fixed; width: 100%; height: 100%; top: 0;left: 0; background-color: <?php echo $this->get_option('overlay_shade') === 'dark' ? '#000' : '#fff' ?>; opacity:<?php echo $this->get_option('overlay_opacity') ?>;"></div>

                    <?php if($integrationType != 'frames'): ?>

                            <script type="text/javascript">
                                window.CheckoutApiJsConfig = {
                                    debugMode:                  'false',
                                    renderMode:                 '<?php echo self::RENDER_MODE ?>',
                                    namespace:                  '<?php echo self::RENDER_NAMESPACE ?>',
                                    publicKey:                  '<?php echo $this->get_option('public_key') ?>',
                                    paymentToken:               '<?php echo $paymentToken['token'] ?>',
                                    value:                      '<?php echo $paymentToken['amount'] ?>',
                                    currency:                   '<?php echo $paymentToken['currency'] ?>',
                                    widgetContainerSelector:    '.checkout-non-pci-new-card-row',
                                    paymentMode:                '<?php echo $paymentMode ?>',
                                    logoUrl:                    '<?php echo $this->get_option('logo_url') ?>',
                                    themeColor:                 '<?php echo $this->get_option('theme_color') ?>',
                                    useCurrencyCode:            '<?php echo $this->get_option('use_currency_code') != 'no' ? 'true' : 'false';?>',
                                    title:                      '<?php echo $this->get_option('form_title') ?>',
                                    widgetColor:                '<?php echo $this->get_option('widget_color') ?>',
                                    forceMobileRedirect:        true,
                                    redirectUrl:                '<?php echo $redirectUrl ?>',
                                    styling:                    {
                                        formButtonColor:        '<?php echo $this->get_option('form_button_color') ?>',
                                        formButtonColorLabel:   '<?php echo $this->get_option('form_button_color_label') ?>',
                                        overlayShade:           '<?php echo $this->get_option('overlay_shade') ?>',
                                        overlayOpacity:         '<?php echo $this->get_option('overlay_opacity') ;?>',
                                        showMobileIcons:        '<?php echo $this->get_option('show_mobile_icons') != 'no' ? 'true' : 'false'?>'
                                    },
                                    widgetIconSize:         '<?php echo $this->get_option('widget_icon_size') ?>',
                                    cardFormMode:               '<?php echo self::CARD_FORM_MODE ?>',
                                    lightboxDeactivated: function(event) {
                                        if (jQuery('#checkout-api-js-hover').length > 0) jQuery('#checkout-api-js-hover').hide();
                                    },
                                    lpCharged: function (event){
                                        if (document.getElementById('cko-lp-redirectUrl').value.length === 0) {
                                            document.getElementById('cko-card-token').value = event.data.lpName;
                                            document.getElementById('cko-lp-redirectUrl').value = event.data.redirectUrl;
                                            document.getElementById('cko-lp-lpName').value = event.data.lpName;
                                            jQuery('#place_order').trigger('click');
                                        }
                                    },
                                    cardTokenised: function(event){
                                        if (document.getElementById('cko-card-token').value.length === 0 || document.getElementById('cko-card-token').value != event.data.cardToken) {
                                            document.getElementById('cko-card-token').value = event.data.cardToken;

                                            jQuery('#place_order').trigger('click');

                                            document.getElementById("cko-card-token").value = "";

                                            if (Checkout.isMobile()) {
                                                jQuery('#checkout-api-js-hover').hide();
                                            }
                                        }
                                    }
                                };

                                window.checkoutFields = '<?php echo $checkoutFields?>';
                            </script>
                            <script type="text/javascript" src="<?php echo $this->get_option('mode') == 'sandbox' ? self::JS_PATH_CARD_TOKEN : self::JS_PATH_CARD_TOKEN_LIVE?>"></script>

                            <script type="text/javascript" src="<?php echo plugins_url() . '/woocommerce-checkout-non-pci-gateway/view/js/' . 'checkout_api.js'?>"></script>
                     <?php else:?>
                                <script type="text/javascript">
                                    var style = {<?php echo $this->get_option('custom_css');?>}
                                    window.CheckoutApiEmbConfig = {
                                        debug: false,
                                        publicKey: "<?php echo $this->get_option('public_key') ?>",
                                        theme: "<?php echo $this->get_option('frames_theme') ?>",
                                        style: style,
                                        lightboxActivated: function(){
                                             document.getElementById("cko-iframe-id").setAttribute("style","border-left-width: 0px;border-top-width: 0px;   border-right-width: 0px;border-bottom-width: 0px;");
                                             jQuery('.cko-md-overlay').remove();
                                             document.getElementById("cko-iframe-id").style.position="relative";

                                        },
                                        cardTokenised: function(event) {
                                            
                                            if (document.getElementById('cko-card-token').value.length === 0 || document.getElementById('cko-card-token').value != event.data.cardToken) {
                                               document.getElementById('cko-card-token').value = event.data.cardToken;
                                               jQuery('#place_order').trigger('click');
                                                
                                            }
                                        },
                                        cardValidationChanged: function (event) {
                                            document.getElementById("place_order").disabled = !Frames.isCardValid()
                                        },
                                        ready: function(event){

                                            if(jQuery('#woocommerce_checkout_non_pci-cc-form').children("ul").length>0 && jQuery('#checkout-new-card').is(':checked')== false){

                                                checkoutHideNewNoPciCard();

                                                function checkoutHideNewNoPciCard() {
                                                    jQuery('.checkout-non-pci-new-card-row').hide();
                                                    document.getElementById("place_order").disabled = false;
                                                }

                                                function checkoutShowNewNoPciCard() {
                                                    jQuery('.checkout-non-pci-new-card-row').show();
                                                    document.getElementById("place_order").disabled = true;
                                                    CKOConfig.createBindings();
                                                }

                                                jQuery('.checkout-saved-card-radio').on("change", function() {
                                                    jQuery('form.checkout').unbind('#place_order, checkout_place_order');
                                                    jQuery('form#order_review').unbind();
                                                    jQuery('#place_order').unbind();
                                                    checkoutHideNewNoPciCard();
                                                });

                                                jQuery('.checkout-new-card-radio').on("change", function() {
                                                    checkoutShowNewNoPciCard();
                                                });
                                           } else {

                                                function checkoutShowNewNoPciCard() {
                                                    jQuery('.checkout-non-pci-new-card-row').show();
                                                    document.getElementById("place_order").disabled = true;
                                                    CKOConfig.createBindings();
                                                }

                                                function checkoutHideNewNoPciCard() {
                                                    jQuery('.checkout-non-pci-new-card-row').hide();
                                                    document.getElementById("place_order").disabled = false;
                                                }
                                                 jQuery('.checkout-saved-card-radio').on("change", function() {
                                                    jQuery('form.checkout').unbind('#place_order, checkout_place_order');
                                                    jQuery('form#order_review').unbind();
                                                    jQuery('#place_order').unbind();
                                                    checkoutHideNewNoPciCard();
                                                });

                                                  jQuery('.checkout-new-card-radio').on("change", function() {
                                                    checkoutShowNewNoPciCard();
                                                });
                                           }
                                        }
                                    };
                                    window.checkoutFields = '<?php echo $checkoutFields?>';
                                </script>
                                <script type="text/javascript">
                                    var script = document.createElement('script');
                                    script.type = 'text/javascript';
                                    script.async = true;
                                    script.src = "<?php echo $this->get_option('mode') == 'sandbox'? self::FRAMES_SANDBOX_URL : self::FRAMES_LIVE_URL?>";   
                                    document.getElementsByClassName('form-row form-row-wide checkout-non-pci-new-card-row')[0].appendChild(script);
                                </script>
                                <script type="text/javascript" src="<?php echo plugins_url() . '/woocommerce-checkout-non-pci-gateway/view/js/' . 'checkout_frames.js'?>"></script>

                    <?php endif;?>
                <?php else: ?>
                    <p><?php echo __('Error creating Payment Token.', 'woocommerce-checkout-non-pci')?>
                <?php endif;?>
			<?php do_action( 'woocommerce_credit_card_form_end', $this->id ); ?>
            <div class="clear"></div>
        </fieldset>
        <?php if(!empty($cardList)):?>
              <?php if($this->get_option('integration_type') != 'frames'): ?>
                    <script type="application/javascript"> 
                        checkoutHideNewNoPciCard();
                        function checkoutHideNewNoPciCard() {
                            jQuery('.checkout-non-pci-new-card-row').hide();
                        }

                        function checkoutShowNewNoPciCard() {
                            jQuery('.checkout-non-pci-new-card-row').show();
                            CKOWoocommerce.createBindings();
                        }

                        jQuery('.checkout-saved-card-radio').on("change", function() {
                            jQuery('form.checkout').unbind('#place_order, checkout_place_order');
                            jQuery('form#order_review').unbind();
                            jQuery('#place_order').unbind();
                            checkoutHideNewNoPciCard();
                        });

                        jQuery('.checkout-new-card-radio').on("change", function() {
                            checkoutShowNewNoPciCard();
                        });
                    </script>
        <?php endif?>
        <?php endif?>
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
            return new WP_Error('error', __($result['message'], 'woocommerce-checkout-non-pci'));
        }

        return true;
    }

    //Activate, suspend or cancel recurring payment plan
    public function updatePlan($subscription){
        $subscriptionParentId = $subscription->parent_id;

        if(!$subscriptionParentId){
            return false;
        }
        
        $order          = new WC_Order($subscriptionParentId);
        $transactionId = $order->transaction_id;

        $checkout   = new WC_Checkout_Non_Pci();
        $mode       =  $checkout->settings['mode'];

        //Api call getChargeHistory in order to get the auth chargeId
        $Api            = CheckoutApi_Api::getApi(array('mode' => $checkout->settings['mode']));
        $verifyParamsHistory   = array('chargeId' => $transactionId, 'authorization' => $checkout->settings['secret_key']);
        $resultHistory         = $Api->getChargeHistory($verifyParamsHistory);
        $charges = $resultHistory->getCharges();

        if(!empty($charges)) {
            $chargesArray = $charges->toArray();

            foreach ($chargesArray as $key=> $charge) {
                if (in_array('Authorised', $charge)) {
                    $authChargeId = $charge['id'];
                    break;
                }
            }

            //Api Call getCharge in order to get the recurring planId
            $verifyParamsCharge   = array('chargeId' => $authChargeId, 'authorization' => $checkout->settings['secret_key']);
            $resultCharge         = $Api->getCharge($verifyParamsCharge);

            if(!empty($resultCharge)){
                if($subscription->status == 'cancelled'){
                    $customerPlanId = $resultCharge['customerPaymentPlans'][0]['customerPlanId'];
                    $param   = array('customerPlanId' => $customerPlanId, 'authorization' => $checkout->settings['secret_key']);

                    //Api call to delete customer plan
                    $resultCancel = $Api->cancelCustomerPaymentPlan($param);

                    if($resultCancel['message'] != 'ok'){
                        WC_Checkout_Non_Pci::log('Failed to cancel Customer PlanId :'.$customerPlanId. ' for orderId:'.$subscriptionParentId);
                    } else {
                         WC_Checkout_Non_Pci::log('Customer plan cancelled successfully. Customer PlanId:'.$customerPlanId. ' for orderId:'.$subscriptionParentId);
                    }

                }else{

                    $recPlanId = $resultCharge['customerPaymentPlans'][0]['planId'];

                    if($subscription->status == 'active'){
                        $postedParam['status'] = 1;
                        $failMessage = 'Failed to activate Recuring PlanId ';
                        $successMessage = 'Account successfully activated. Recuring PlanId ';
                    } elseif ($subscription->status == 'on-hold') {
                        $postedParam['status'] = 4;
                        $failMessage = 'Failed to suspend Recurring PlanId ';
                        $successMessage = 'Account successfully suspended. Recurring PlanId ';
                    }
                    
                    $param   = array('planId' => $recPlanId, 'postedParam' =>$postedParam, 'authorization' => $checkout->settings['secret_key']);

                    //Api call to update payment plan and set status to 4(Suspended) or 1(Activate)
                    $resultRec = $Api->updatePaymentPlan($param);

                    if($resultRec['message'] != 'ok'){
                        WC_Checkout_Non_Pci::log($failMessage.':'.$recPlanId. ' for orderId:'.$subscriptionParentId);
                    } else {
                        WC_Checkout_Non_Pci::log($successMessage.':'.$recPlanId. ' for orderId:'.$subscriptionParentId);
                    }
                }
            }
        }
    }

}
