<?php
include_once "lib/checkout-sdk-php/checkout.php";
include_once('settings/class-wc-checkoutcom-cards-settings.php');
include_once('settings/admin/class-wc-checkoutcom-admin.php');
include_once('api/class-wc-checkoutcom-api-request.php');
include_once ('class-wc-gateway-checkout-com-webhook.php');

use Checkout\Library\Exceptions\CheckoutHttpException;
use Checkout\Library\Exceptions\CheckoutModelException;


class WC_Gateway_Checkout_Com_Cards extends WC_Payment_Gateway_CC
{
    const PLUGIN_VERSION = '4.0.3';

    /**
     * WC_Gateway_Checkout_Com_Cards constructor.
     */
    public function __construct()
    {
        $this->id                   = 'wc_checkout_com_cards';
        $this->method_title         = __("Checkout.com", 'wc_checkout_com_cards');
        $this->method_description   = __("The Checkout.com extension allows shop owners to process online payments through the <a href=\"https://www.checkout.com\">Checkout.com Payment Gateway.</a>", 'wc_checkout_com_cards');
        $this->title                = __("Cards payment and general configuration", 'wc_checkout_com_cards');
        $this->has_fields = true;
        $this->supports = array(
            'products',
            'refunds',
            'tokenization',
        );

        $this->new_method_label   = __( 'Use a new card', 'woocommerce' );

        $this->init_form_fields();
        $this->init_settings();

        // Turn these settings into variables we can use
        foreach ( $this->settings as $setting_key => $value ) {
            $this->$setting_key = $value;
        }

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        // Redirection hook
        add_action( 'woocommerce_api_wc_checkoutcom_callback', array( $this, 'callback_handler' ) );

        // webhook
        add_action( 'woocommerce_api_wc_checkoutcom_webhook', array( $this, 'webhook_handler' ) );
    }

    /**
     * Show module configuration in backend
     *
     * @return string|void
     */
    public function init_form_fields()
    {
        $this->form_fields = (new WC_Checkoutcom_Cards_Settings)->core_settings();

        $this->form_fields = array_merge( $this->form_fields, array(
            'screen_button' => array(
                'id'    => 'screen_button',
                'type'  => 'screen_button',
                'title' => __( 'Other Settings', 'configuration_setting' ),
            )
        ));
    }

    /**
     * @param $key
     * @param $value
     */
    public function generate_screen_button_html( $key, $value )
    {
        WC_Checkoutcom_Admin::generate_links($key, $value);
    }

    /**
     * Show module settings links
     */
    public function admin_options()
    {
        if( ! isset( $_GET['screen'] ) || '' === $_GET['screen'] ) {
            parent::admin_options();
        } else {

            $test = array(
                'screen_button' => array(
                    'id'    => 'screen_button',
                    'type'  => 'screen_button',
                    'title' => __( 'Settings', 'configuration_setting' ),
                )
            );

            echo '<h3>'. $this->method_title.' </h3>';
            echo '<p>'. $this->method_description.' </p>';
            $this->generate_screen_button_html($key = 'screen_button', $test);

            if ('orders_settings' === $_GET['screen']) {
                echo '<table class="form-table">';
                WC_Admin_Settings::output_fields(WC_Checkoutcom_Cards_Settings::order_settings());
                echo '</table>';
            } elseif ('card_settings' === $_GET['screen']) {

                echo '<table class="form-table">';
                WC_Admin_Settings::output_fields( WC_Checkoutcom_Cards_Settings::cards_settings() );
                echo '</table>';
            } elseif ('debug_settings' === $_GET['screen']) {

                echo '<table class="form-table">';
                WC_Admin_Settings::output_fields( WC_Checkoutcom_Cards_Settings::debug_settings() );
                echo '</table>';
            } else {

                echo '<table class="form-table">';
                WC_Admin_Settings::output_fields( WC_Checkoutcom_Cards_Settings::core_settings() );
                echo '</table>';
            }
        }
    }

    /**
     * Save module settings in  woocommerce db
     * @return bool|void
     */
    public function process_admin_options()
    {
        if( isset( $_GET['screen'] ) && '' !== $_GET['screen'] ) {
            if ('card_settings' == $_GET['screen']) {
                WC_Admin_Settings::save_fields( WC_Checkoutcom_Cards_Settings::cards_settings());
            } elseif ('orders_settings' == $_GET['screen']) {
                WC_Admin_Settings::save_fields( WC_Checkoutcom_Cards_Settings::order_settings());
            } elseif ('debug_settings' == $_GET['screen']) {
                WC_Admin_Settings::save_fields(WC_Checkoutcom_Cards_Settings::debug_settings());
            } else {
                WC_Admin_Settings::save_fields( WC_Checkoutcom_Cards_Settings::core_settings());
            }
            do_action( 'woocommerce_update_options_' . $this->id  );
        } else {
            parent::process_admin_options();
        }
    }

    /**
     * Show frames js on checkout page
     */
    public function payment_fields()
    {
        $save_card =  WC_Admin_Settings::get_option('ckocom_card_saved');
        $mada_enable = WC_Admin_Settings::get_option('ckocom_card_mada') == 1 ? true : false;
        $require_cvv = WC_Admin_Settings::get_option('ckocom_card_require_cvv');

        // check if saved card enable from module setting
        if($save_card) {
            // Show available saved cards
            $this->saved_payment_methods();

            // check if mada enable in module settings
            if($mada_enable){
                foreach ($this->get_tokens() as $item) {
                    $token_id = $item->get_id();
                    $token = WC_Payment_Tokens::get( $token_id );
                    // check if token is mada
                    $is_mada = $token->get_meta('is_mada');
                    if($is_mada){
                        $is_mada_token = $token_id;
                    }
                }
            }
        }

        // Check if require cvv or mada is enable from module setting
        if($require_cvv || $mada_enable) {
        ?>
        <div class="cko-cvv" style="display: none;padding-top: 10px;">
            <p class="validate-required" id="cko-cvv" data-priority="10">
                <label for="cko-cvv"><?php esc_html_e( 'Card Code', 'woocommerce-square' ); ?> <span class="required">*</span></label>
                <input id="cko-cvv" type="text" autocomplete="off" class="input-text"
                       placeholder="<?php esc_attr_e( 'CVV', 'wc_checkout_com_cards' ); ?>"
                       name="<?php echo esc_attr( $this->id ); ?>-card-cvv" />
            </p>
        </div>
        <?php } ?>
        <div class="cko-form" style="display: none; padding-top: 10px;">
            <input type="hidden" id="cko-card-token" name="cko-card-token" value=""/>
            <input type="hidden" id="cko-card-bin" name="cko-card-bin" value=""/>

            <!-- frames will be loaded here -->
            <div class="one-liner">
                <div class="card-frame"></div>
            </div>
            
            <script type="text/javascript">
                // Get debug mode from module config
                var debug = '<?php echo WC_Admin_Settings::get_option('cko_console_logging'); ?>';

                jQuery( function(){
                    // Set default ul to auto
                    jQuery('.payment_box.payment_method_wc_checkout_com_cards > ul').css('margin','auto')

                    // Initialise frames v2
                    Frames.init({
                        publicKey: "<?php echo $this->get_option( 'ckocom_pk' );?>",
                        debug : debug == 'yes' ? true : false
                    });

                    Frames.addEventHandler(
                        Frames.Events.CARD_TOKENIZED,
                        onCardTokenized
                    );

                    function onCardTokenized(event) {
                        if (document.getElementById('cko-card-token').value.length === 0
                            || document.getElementById('cko-card-token').value != event.token) {
                            document.getElementById('cko-card-token').value = event.token;
                            document.getElementById('cko-card-bin').value = event.bin;
                            jQuery('#place_order').trigger('click');
                            document.getElementById("cko-card-token").value = "";
                            Frames.enableSubmitForm();
                        }
                    }

                    // check if saved card exist
                    if(jQuery('.payment_box.payment_method_wc_checkout_com_cards').
                        children('ul.woocommerce-SavedPaymentMethods.wc-saved-payment-methods').attr('data-count') > 0) {
                        jQuery('.cko-form').hide();

                        jQuery('input[type=radio][name=wc-wc_checkout_com_cards-payment-token]').change(function() {
                            if(this.value == 'new'){
                                // display frames if new card is selected
                                jQuery('.cko-form').show();
                                jQuery('.cko-cvv').hide();
                            } else {
                                jQuery('.cko-form').hide();
                                jQuery('.cko-cvv').show();

                                var is_mada = '<?php echo $mada_enable; ?>';

                                if(is_mada == 1){
                                    if(this.value === '<?php echo $is_mada_token;?>'){
                                        jQuery('.cko-form').hide();
                                        jQuery('.cko-cvv').show();
                                    } else {
                                        jQuery('.cko-cvv').hide();
                                    }
                                }
                            }
                        });
                    } else {
                        jQuery('.cko-form').show();
                    }

                    // check if add-payment-method exist
                    if(jQuery('#add_payment_method').length > 0) {
                        jQuery('.woocommerce-SavedPaymentMethods.wc-saved-payment-methods').hide();
                        jQuery('.cko-save-card-checkbox').hide();
                        jQuery('.cko-form').show();
                    }

                });

                setTimeout(function(){
                    // hook place order button
                    jQuery('#place_order').on('click', function(e){
                        // check if checkout.com is selected
                        if (jQuery('#payment_method_wc_checkout_com_cards').is(':checked')) {
                            // check if new card exist
                            if(jQuery('#wc-wc_checkout_com_cards-payment-token-new').length > 0 ) {
                                // check if new card is selected else process with saved card
                                if(jQuery('#wc-wc_checkout_com_cards-payment-token-new').is(':checked')){
                                    if(document.getElementById('cko-card-token').value.length > 0 ){
                                        return true;
                                    } else if(Frames.isCardValid()) {
                                        Frames.submitCard();
                                    }
                                } else if (jQuery('#add_payment_method').length > 0) {
                                    // check if card is valid from add-payment-method
                                    if (jQuery('#payment_method_wc_checkout_com_cards').is(':checked')) {
                                        if(Frames.isCardValid()) {
                                            Frames.submitCard();
                                        }
                                    }
                                } else {
                                    return true;
                                }
                            }

                            if(document.getElementById('cko-card-token').value.length > 0 ){
                                return true;
                            } else if(Frames.isCardValid()) {
                                Frames.submitCard();
                            }

                            return false;
                        }
                    });
                }, 1500);
            </script>

        </div>

        <!-- Show save card checkbox if this is selected on admin-->
        <div class="cko-save-card-checkbox">
            <?php
            if($save_card){
                $this->save_payment_method_checkbox();
            }
            ?>
        </div>
        <?php


    }

    /**
     * Process payment with card payment
     *
     * @param int $order_id
     * @return
     */
    public function process_payment( $order_id )
    {
        if (!session_id()) session_start();

        global $woocommerce;
        $order = wc_get_order( $order_id );

        // check if card token or token_id exist
        if($_POST['wc-wc_checkout_com_cards-payment-token']) {
            if($_POST['wc-wc_checkout_com_cards-payment-token'] == 'new') {
                $arg = $_POST['cko-card-token'];
            } else {
                $arg = $_POST['wc-wc_checkout_com_cards-payment-token'];
            }
        }

        // Check if empty card token and empty token_id
        if(empty($arg)){
            // check if card token exist
            if($_POST['cko-card-token']) {
                $arg = $_POST['cko-card-token'];
            } else {
                WC_Checkoutcom_Utility::wc_add_notice_self(__('There was an issue completing the payment.'), 'error');
                return;
            }
        }

        // Create payment with card token
        $result =  (array) (new WC_Checkoutcom_Api_request)->create_payment($order, $arg);

        // check if result has error and return error message
        if (isset($result['error']) && !empty($result['error'])) {
            WC_Checkoutcom_Utility::wc_add_notice_self(__($result['error']), 'error');
            return;
        }

        // Get save card config from module setting
        $save_card =  WC_Admin_Settings::get_option('ckocom_card_saved');

        // check if result contains 3d redirection url
        // Redirect to 3D secure page
        if (isset($result['3d']) &&!empty($result['3d'])) {

            // check if save card is enable and customer select to save card
            if($save_card && $_POST['wc-wc_checkout_com_cards-new-payment-method']){
                // save in session for 3D secure payment
                $_SESSION['wc-wc_checkout_com_cards-new-payment-method'] = isset($_POST['wc-wc_checkout_com_cards-new-payment-method']);
            }

            return array(
                'result'        => 'success',
                'redirect'      => $result['3d'],
            );
        }

        // save card in db
        if($save_card && $_POST['wc-wc_checkout_com_cards-new-payment-method']){
            $this->save_token(get_current_user_id(), $result);
        }

        // Set action id as woo transaction id
        update_post_meta($order_id, '_transaction_id', $result['action_id']);
        update_post_meta($order_id, '_cko_payment_id', $result['id']);

        // Get cko auth status configured in admin
        $status = WC_Admin_Settings::get_option('ckocom_order_authorised');
        $message = __("Checkout.com Payment Authorised (Transaction ID - {$result['action_id']}) ", 'wc_checkout_com_cards');

        // check if payment was flagged
        if ($result['risk']['flagged']) {
            // Get cko auth status configured in admin
            $status = WC_Admin_Settings::get_option('ckocom_order_flagged');
            $message = __("Checkout.com Payment Flagged (Transaction ID - {$result['action_id']}) ", 'wc_checkout_com_cards');
        }

        // Update order status on woo backend
        $order->update_status($status,$message);

        // Reduce stock levels
        wc_reduce_stock_levels( $order_id );

        // Remove cart
        $woocommerce->cart->empty_cart();

        // Return thank you page
        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url( $order )
        );
    }

    /**
     * Handle redirection callback
     */
    public function callback_handler()
    {
        if (!session_id()) session_start();

        global $woocommerce;

        if($_REQUEST['cko-session-id']){
            $cko_session_id = $_REQUEST['cko-session-id'];
        }

        // Verify session id
        $result =  (array) (new WC_Checkoutcom_Api_request)->verify_session($cko_session_id);

        $order_id = $result['reference'];
        $action = $result['actions'];

        $order = new WC_Order( $order_id );

        // Redirect to cart if an error occured
        if (isset($result['error']) && !empty($result['error'])) {
            WC_Checkoutcom_Utility::wc_add_notice_self(__($result['error'],'wc_checkout_com_cards_settings'), 'error');
            wp_redirect(WC_Cart::get_checkout_url());
            exit();
        }

        // Redirect to my-account/payment-method if card verification failed
        // show error to customer
        if(isset($result['card_verification']) == 'error'){
            WC_Checkoutcom_Utility::wc_add_notice_self(__('Unable to add payment method to your account.' ,'wc_checkout_com_cards_settings'), 'error');
            wp_redirect($result['redirection_url']);
            exit;
        }

        // Redirect to my-account/payment-method if card verification successful
        // show notice to customer
        if(isset($result['status']) == 'Card Verified' && isset($result['metadata']['card_verification'])){

            $this->save_token(get_current_user_id(), $result);

            WC_Checkoutcom_Utility::wc_add_notice_self(__('Payment method successfully added.' ,'wc_checkout_com_cards_settings'), 'notice');
            wp_redirect($result['metadata']['redirection_url']);
            exit;
        }

        // Set action id as woo transaction id
        update_post_meta($order_id, '_transaction_id', $action['0']['id']);

        // if no action id and source is boleto
        if($action['0']['id'] == null && $result['source']['type'] == 'boleto' ){
            update_post_meta($order_id, '_transaction_id', $result['id']);
        }

        update_post_meta($order_id, '_cko_payment_id', $result['id']);

        // Get cko auth status configured in admin
        $status = WC_Admin_Settings::get_option('ckocom_order_authorised');
        $message = __("Checkout.com Payment Authorised (Transaction ID - {$action['0']['id']}) ", 'wc_checkout_com_cards_settings');

        // check if payment was flagged
        if ($result['risk']['flagged']) {
            // Get cko auth status configured in admin
            $status = WC_Admin_Settings::get_option('ckocom_order_flagged');
            $message = __("Checkout.com Payment Flagged (Transaction ID - {$action['0']['id']}) ", 'wc_checkout_com_cards_settings');
        }

        if ($result['status'] == 'Captured') {
            $status = WC_Admin_Settings::get_option('ckocom_order_captured');
            $message = __("Checkout.com Payment Captured (Transaction ID - {$action['0']['id']}) ", 'wc_checkout_com_cards_settings');
        }

        // save card to db
        $save_card =  WC_Admin_Settings::get_option('ckocom_card_saved');
        if($save_card && $_SESSION['wc-wc_checkout_com_cards-new-payment-method']){
            $this->save_token($order->get_user_id(), $result);
            unset($_SESSION['wc-wc_checkout_com_cards-new-payment-method']);
        }

        // Update order status on woo backend
        $order->update_status($status,$message);

        // Reduce stock levels
        wc_reduce_stock_levels( $order_id );

        // Remove cart
        $woocommerce->cart->empty_cart();

        $url = esc_url($order->get_checkout_order_received_url());
        wp_redirect($url);

        exit();
    }

    public function add_payment_method()
    {
        // check if cko card token is not empty
        if (empty($_POST['cko-card-token'])) {
            return array(
                'result'   => 'failure', // success
                'redirect' => wc_get_endpoint_url( 'payment-methods' ),
            );
        }

        // load module settings
        $core_settings = get_option('woocommerce_wc_checkout_com_cards_settings');
        $environment = $core_settings['ckocom_environment'] == 'sandbox' ? true : false;
        $gateway_debug = WC_Admin_Settings::get_option('cko_gateway_responses') == 'yes' ? true : false;

        // Initialize the Checkout Api
        $checkout = new Checkout\CheckoutApi($core_settings['ckocom_sk'], $environment);

        // Load method with card token
        $method = new Checkout\Models\Payments\TokenSource($_POST['cko-card-token']);

        $payment = new Checkout\Models\Payments\Payment($method, get_woocommerce_currency());

        // Load current user
        $current_user = wp_get_current_user();
        // Set customer email and name to payment request
        $payment->customer = array(
            'email' => $current_user->user_email,
            'name' => $current_user->first_name. ' ' . $current_user->last_name
        );

        $metadata = array(
            'card_verification' => true,
            'redirection_url' => wc_get_endpoint_url( 'payment-methods' )
        );
        // Set Metadata in card verfication request
        // to use in callback handler
        $payment->metadata = $metadata;

        // Set redirection url in payment request
        $redirection_url = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'wc_checkoutcom_callback', home_url( '/' ) ) );
        $payment->success_url = $redirection_url;
        $payment->failure_url = $redirection_url;

        // to remove
        $three_ds = new Checkout\Models\Payments\ThreeDs(true);
        $payment->threeDs = $three_ds;
        // end to remove

        try {
            $response = $checkout->payments()->request($payment);

            // Check if payment successful
            if ($response->isSuccessful()) {

                // Check if payment is 3Dsecure
                if ($response->isPending()) {
                    // Check if redirection link exist
                    if ($response->getRedirection()) {
                        // return 3d redirection url
                        wp_redirect($response->getRedirection());
                        exit();

                    } else {
                        return array(
                            'result'   => 'failure',
                            'redirect' => wc_get_endpoint_url( 'payment-methods' ),
                        );
                    }
                } else {
                    $this->save_token($current_user->ID ,  (array) $response);

                    return array(
                        'result'   => 'success',
                    );
                }
            } else {
                return array(
                    'result'   => 'failure',
                    'redirect' => wc_get_endpoint_url( 'payment-methods' ),
                );
            }

        } catch (CheckoutHttpException $ex) {
            $error_message = "An error has occurred while processing your cancel request.";

            // check if gateway response is enable from module settings
            if ($gateway_debug) {
                $error_message .= __($ex->getMessage() , 'wc_checkout_com_cards');
            }

            // Log message
            WC_Checkoutcom_Utility::logger($error_message, $ex);

            return array(
                'result'   => 'failure',
                'redirect' => wc_get_endpoint_url( 'payment-methods' ),
            );
        }
    }

    /**
     * Save source_id in db
     * @param $order
     * @param $payment_response
     */
    public function save_token( $user_id, $payment_response )
    {
        //Check if payment response is not null
        if(!is_null($payment_response)){
            // argument to check token
            $arg = array(
                'user_id' => $user_id,
                'gateway_id' => $this->id
            );

            // Query token by userid and gateway id
            $token    = WC_Payment_Tokens::get_tokens( $arg );

            foreach ($token as $tok) {
                $token_data = $tok->get_data();
                // do not save source if it already exist in db
                if( $token_data['token'] == $payment_response['source']['id']) {
                    return;
                }
            }

            // Save source_id in db
            $token = new WC_Payment_Token_CC();
            $token->set_token( (string) $payment_response['source']['id'] );
            $token->set_gateway_id( $this->id );
            $token->set_card_type( (string) $payment_response['source']['scheme'] );
            $token->set_last4( $payment_response['source']['last4'] );
            $token->set_expiry_month( $payment_response['source']['expiry_month'] );
            $token->set_expiry_year( $payment_response['source']['expiry_year'] );
            $token->set_user_id( $user_id );

            // Check if session has is mada and set token metadata
            if($_SESSION['cko-is-mada']) {
                $token->add_meta_data( 'is_mada', true, true );
                unset($_SESSION['cko-is-mada']);
            }

            $token->save();
        }
    }

    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $order = wc_get_order( $order_id );
        $result = (array) WC_Checkoutcom_Api_request::refund_payment($order_id, $order);

        // check if result has error and return error message
        if (isset($result['error']) && !empty($result['error'])) {
            WC_Checkoutcom_Utility::wc_add_notice_self(__($result['error']), 'error');
            return false;
        }

        // Set action id as woo transaction id
        update_post_meta($order_id, '_transaction_id', $result['action_id']);
        update_post_meta($order_id, 'cko_payment_refunded', true);

        // Get cko auth status configured in admin
        $status = WC_Admin_Settings::get_option('ckocom_order_refunded');
        $message = __("Checkout.com Payment refunded (Transaction ID - {$result['action_id']}) ", 'wc_checkout_com_cards');

        if(isset($_SESSION['cko-refund-is-less'])){
            if($_SESSION['cko-refund-is-less']){
                $status = WC_Admin_Settings::get_option('ckocom_order_captured');
                $order->add_order_note( __("Checkout.com Payment Partially refunded (Transaction ID - {$result['action_id']})") );

                unset($_SESSION['cko-refund-is-less']);

                return true;
            }
        }

        // Update order status on woo backend
        $order->update_status($status,$message);

        return true;
    }

    public function webhook_handler()
    {
        // webhook_url_format = http://localhost/wordpress-5.0.2/wordpress/?wc-api=wc_checkoutcom_webhook

        // Get webhook data
        $data = json_decode(file_get_contents('php://input'));

        // Return to home page if empty data
        if (empty($data)) {
            wp_redirect(get_home_url());
            exit();
        }

        $header = apache_request_headers();
        $header_authorization = $header['Authorization'];

        $core_settings = get_option('woocommerce_wc_checkout_com_cards_settings');
        // Get private shared key from module settings
        $psk =  $core_settings['ckocom_psk'];

        // Check if private shared key is not empty
        if (!empty($psk)) {
            // check if header athorization equals
            // to private shared key configured in module settings
            if($header_authorization !== $psk){
                return http_response_code(401);
            }
        }

        // Get webhook event type from data
        $event_type = $data->type;

        switch ($event_type){
            case 'payment_captured':
                $response = WC_Checkout_Com_Webhook::capture_payment($data);
                break;
            case 'payment_voided':
                $response = WC_Checkout_Com_Webhook::void_payment($data);
                break;
            case 'payment_capture_declined':
                $response = WC_Checkout_Com_Webhook::capture_declined($data);
                break;
            case 'payment_refunded':
                $response = WC_Checkout_Com_Webhook::refund_payment($data);
                break;
            case 'payment_canceled':
                $response = WC_Checkout_Com_Webhook::cancel_payment($data);
                break;
            default:
                $response = true;
                break;
        }

        $http_code = $response ? 200 : 400;

        return http_response_code($http_code);
    }
}