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
    const AUTO_CAPTURE_TIME         = 0;
    const RENDER_MODE               = 1;
    const VERSION                   = '2.3.1';
    const RENDER_NAMESPACE          = 'Checkout';
    const CARD_FORM_MODE            = 'cardTokenisation';
    const JS_PATH_CARD_TOKEN        = 'https://cdn3.checkout.com/sandbox/js/checkout.js';
    const JS_PATH_CARD_TOKEN_LIVE   = 'https://cdn3.checkout.com/js/checkout.js';

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
            'subscriptions'
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
            'icon_url' => array(
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
                'title'		=> __('Title', 'woocommerce-checkout-non-pci'),
                'type'		=> 'text',
                'desc_tip'	=> __('The title of your payment form.', 'woocommerce-checkout-non-pci'),
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
                    'card'          => __('Card', 'woocommerce-checkout-non-pci'),
                    'localpayment'  => __('Local Payment', 'woocommerce-checkout-non-pci'),
                )
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

        global $woocommerce;

        $order          = new WC_Order($order_id);
        $request        = new WC_Checkout_Non_Pci_Request($this);
        $cardToken      = !empty($_POST["{$request->gateway->id}-card-token"]) ? $_POST["{$request->gateway->id}-card-token"] : '';
        $lpRedirectUrl  = !empty($_POST["{$request->gateway->id}-lp-redirect-url"]) ? $_POST["{$request->gateway->id}-lp-redirect-url"] : NULL;
        $lpName         = !empty($_POST["{$request->gateway->id}-lp-name"]) ? $_POST["{$request->gateway->id}-lp-name"] : NULL;

        if (!is_null($lpRedirectUrl) && !is_null($lpName)) {
            $parts = parse_url($lpRedirectUrl);
            parse_str($parts['query'], $query);

            $paymentToken   = $query['paymentToken'];
            $result         = $request->verifyChargePaymentToken($order, $paymentToken);

            if (!empty($result['error'])) {
                WC()->session->refresh_totals = true;
                WC_Checkout_Non_Pci_Validator::wc_add_notice_self($this->gerProcessErrorMessage($result['error']), 'error');
                return;
            }

            $entityId = $result->getId();

            $order->update_status($request->getOrderStatus(), __("Checkout.com Charge Approved (Transaction ID - {$entityId}", 'woocommerce-checkout-non-pci'));
            $order->reduce_order_stock();

            update_post_meta($order_id, '_transaction_id', $entityId);

            $_SESSION['checkout_local_payment_token'] = strtolower($paymentToken);
            WC()->session->order_awaiting_payment = 1;

            return array(
                'result'        => 'success',
                'redirect'      => $lpRedirectUrl
            );
        }

        if (empty($cardToken)) {
            WC_Checkout_Non_Pci_Validator::wc_add_notice_self($this->gerProcessErrorMessage('Please use the "Add Card" button to complete your payment.'), 'error');
            return;
        }

        $result = $request->createCharge($order, $cardToken);

        if (!empty($result['error'])) {
            WC()->session->refresh_totals = true;
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

    /**
     * Custom credit card form
     *
     * @param array $args
     * @param array $fields
     * @return bool
     */
    public function credit_card_form($args = array(), $fields = array()) {
        include_once( 'includes/class-wc-gateway-checkout-non-pci-request.php');
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

            $orderTotal = $order->get_total();
        } else {
            $orderTotal = $woocommerce->cart->total;
        }

        $requestModel   = new WC_Checkout_Non_Pci_Request($this);
        $paymentToken   = $requestModel->createPaymentToken($orderTotal, get_woocommerce_currency());
        ?>
        <fieldset id="<?php echo $this->id; ?>-cc-form" class="checkout-widget">
            <?php do_action( 'woocommerce_credit_card_form_start', $this->id ); ?>
                <?php if(!empty($paymentToken)):?>
                    <?php if($isPayOrder):?>
                        <input type="hidden" id="billing_email" value="<?php echo $billingEmail?>"/>
                        <input type="hidden" id="billing_first_name" value="<?php echo $customerName?>"/>
                        <input type="hidden" id="billing_last_name" value="<?php echo $customerLastName?>"/>
                    <?php endif?>
                    <input type="hidden" id="cko-card-token" name="<?php echo esc_attr( $this->id ) ?>-card-token" value=""/>
                    <input type="hidden" id="cko-lp-redirectUrl" name="<?php echo esc_attr( $this->id ) ?>-lp-redirect-url" value=""/>
                    <input type="hidden" id="cko-lp-lpName" name="<?php echo esc_attr( $this->id ) ?>-lp-name" value=""/>
                    <div id="checkout-api-js-hover" style="display: none; z-index: 100; position: fixed; width: 100%; height: 100%; top: 0;left: 0; background-color: <?php echo $this->get_option('overlay_shade') === 'dark' ? '#000' : '#fff' ?>; opacity:<?php echo $this->get_option('overlay_opacity') ?>;"></div>
                    <script type="text/javascript">
                        window.checkoutIntegrationCurrentConfig = {
                            debugMode:                  'false',
                            renderMode:                 <?php echo self::RENDER_MODE ?>,
                            namespace:                  '<?php echo self::RENDER_NAMESPACE ?>',
                            publicKey:                  '<?php echo $this->get_option('public_key') ?>',
                            paymentToken:               '<?php echo $paymentToken['token'] ?>',
                            value:                      '<?php echo $paymentToken['amount'] ?>',
                            currency:                   '<?php echo $paymentToken['currency'] ?>',
                            widgetContainerSelector:    '#<?php echo $this->id ?>-cc-form',
                            paymentMode:                '<?php echo $this->get_option('payment_mode') ?>',
                            logoUrl:                    '<?php echo $this->get_option('icon_url') ?>',
                            themeColor:                 '<?php echo $this->get_option('theme_color') ?>',
                            useCurrencyCode:            '<?php echo $this->get_option('use_currency_code') != 'no' ? 'true' : 'false';?>',
                            title:                      '<?php echo $this->get_option('form_title') ?>',
                            styling:                    {
                                formButtonColor:        '<?php echo $this->get_option('form_button_color') ?>',
                                formButtonColorLabel:   '<?php echo $this->get_option('form_button_color_label') ?>',
                                overlayShade:           '<?php echo $this->get_option('overlay_shade') ?>',
                                overlayOpacity:         '<?php echo $this->get_option('overlay_opacity') ;?>',
                                showMobileIcons:        '<?php echo $this->get_option('show_mobile_icons') != 'no' ? 'true' : 'false'?>',
                                widgetIconSize:         '<?php echo $this->get_option('widget_icon_size') ?>'
                            },
                            cardFormMode:               '<?php echo self::CARD_FORM_MODE ?>',
                            enableIframePreloading:     false,
                            lpCharged: function (event){
                                if (document.getElementById('cko-lp-redirectUrl').value.length === 0) {
                                    document.getElementById('cko-card-token').value = event.data.lpName;
                                    document.getElementById('cko-lp-redirectUrl').value = event.data.redirectUrl;
                                    document.getElementById('cko-lp-lpName').value = event.data.lpName;

                                    if (jQuery('#place_order').length > 0) {
                                        jQuery('#place_order').trigger('click');
                                    }
                                }
                            },
                            cardTokenised: function(event){
                                if (document.getElementById('cko-card-token').value.length === 0 || document.getElementById('cko-card-token').value != event.data.cardToken) {
                                    document.getElementById('cko-card-token').value = event.data.cardToken;

                                    if (jQuery('#place_order').length > 0) {
                                        jQuery('#place_order').trigger('click');
                                    }
                                }
                            }
                        };

                        if (document.getElementById('billing_email').value.length > 0) {
                            window.checkoutIntegrationCurrentConfig.customerEmail = document.getElementById('billing_email').value;
                        }

                        if (document.getElementById('billing_first_name').value.length > 0 && document.getElementById('billing_last_name').value.length > 0) {
                            window.checkoutIntegrationCurrentConfig.customerName = document.getElementById('billing_first_name').value
                                + ' ' + document.getElementById('billing_last_name').value;
                        }

                        window.checkoutIntegrationIsReady = window.checkoutIntegrationIsReady || false;
                        if (!window.checkoutIntegrationIsReady) {
                            window.CKOConfig = {
                                ready: function () {
                                    if (window.checkoutIntegrationIsReady) {
                                        return false;
                                    }

                                    if (typeof Checkout == 'undefined') {
                                        return false;
                                    }

                                    Checkout.render(window.checkoutIntegrationCurrentConfig);

                                    window.checkoutIntegrationIsReady = true;
                                }
                            };

                            var script = document.createElement('script');
                            script.src = '<?php echo $this->getJsLibUrl();?>';
                            script.async = true;
                            document.head.appendChild(script);

                            var link    = document.createElement('link');
                            link.type   = 'text/css';
                            link.rel    = 'stylesheet';
                            link.href   = '<?php echo $this->getCssLink() ?>';
                            document.head.appendChild(link);
                        } else {
                            Checkout.render(window.checkoutIntegrationCurrentConfig);
                        }
                    </script>
                <?php else:?>
                    <p><?php echo __('Error creating Payment Token.', 'woocommerce-checkout-non-pci')?></p>
                <?php endif?>
			<?php do_action( 'woocommerce_credit_card_form_end', $this->id ); ?>
            <div class="clear"></div>
        </fieldset>
        <?php
    }

    /**
     * Get js lib URL
     *
     * @return string
     */
    public function getJsLibUrl() {
        return $this->get_option('mode') == 'sandbox' ? self::JS_PATH_CARD_TOKEN : self::JS_PATH_CARD_TOKEN_LIVE;
    }

    /**
     * Get css file URL
     *
     * @return string
     */
    public function getCssLink() {
        return plugins_url() . '/woocommerce-checkout-non-pci-gateway/view/css/checkout-styles.css';
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
}