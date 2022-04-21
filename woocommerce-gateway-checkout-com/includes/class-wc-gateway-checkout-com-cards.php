<?php
include_once "lib/checkout-sdk-php/checkout.php";
include_once('settings/class-wc-checkoutcom-cards-settings.php');
include_once('settings/class-wc-checkoutcom-webhook.php');
include_once('settings/admin/class-wc-checkoutcom-admin.php');
include_once('api/class-wc-checkoutcom-api-request.php');
include_once ('class-wc-gateway-checkout-com-webhook.php');
include_once('subscription/class-wc-checkout-com-subscription.php');

use Checkout\Library\Exceptions\CheckoutHttpException;
use Checkout\Library\Exceptions\CheckoutModelException;


class WC_Gateway_Checkout_Com_Cards extends WC_Payment_Gateway_CC
{
    const PLUGIN_VERSION = '4.3.9';

    /**
     * WC_Gateway_Checkout_Com_Cards constructor.
     */
    public function __construct()
    {
        $this->id                   = 'wc_checkout_com_cards';
        $this->method_title         = __("Checkout.com", 'wc_checkout_com');
        $this->method_description   = __("The Checkout.com extension allows shop owners to process online payments through the <a href=\"https://www.checkout.com\">Checkout.com Payment Gateway.</a>", 'wc_checkout_com');
        $this->title                = __("Cards payment and general configuration", 'wc_checkout_com');
        $this->has_fields = true;
        $this->supports = array(
            'products',
            'refunds',
            'tokenization',
            'subscriptions',
            'subscription_cancellation',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_amount_changes',
            'subscription_date_changes',
            'subscription_payment_method_change',
            'subscription_payment_method_change_customer',
            'subscription_payment_method_change_admin',
            'multiple_subscriptions',
        );

        $this->new_method_label   = __( 'Use a new card', 'wc_checkout_com' );

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
                'title' => __( 'Other Settings', 'wc_checkout_com' ),
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
        if( ! isset( $_GET['screen'] ) || '' === sanitize_text_field($_GET['screen']) ) {
            parent::admin_options();
        } else {

            $screen = sanitize_text_field($_GET['screen']);

            $test = array(
                'screen_button' => array(
                    'id'    => 'screen_button',
                    'type'  => 'screen_button',
                    'title' => __( 'Settings', 'wc_checkout_com' ),
                )
            );

            echo '<h3>'. $this->method_title.' </h3>';
            echo '<p>'. $this->method_description.' </p>';
            $this->generate_screen_button_html($key = 'screen_button', $test);

            if ('orders_settings' === $screen) {
                echo '<table class="form-table">';
                WC_Admin_Settings::output_fields(WC_Checkoutcom_Cards_Settings::order_settings());
                echo '</table>';
            } elseif ('card_settings' === $screen) {

                echo '<table class="form-table">';
                WC_Admin_Settings::output_fields( WC_Checkoutcom_Cards_Settings::cards_settings() );
                echo '</table>';
            } elseif ('debug_settings' === $screen) {

                echo '<table class="form-table">';
                WC_Admin_Settings::output_fields( WC_Checkoutcom_Cards_Settings::debug_settings() );
                echo '</table>';
            } elseif ('webhook' === $screen) {

                echo '<table class="form-table">';
                WC_Admin_Settings::output_fields( WC_Checkoutcom_Cards_Settings::webhook_settings() );
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
            do_action( 'woocommerce_update_options_' . $this->id  );
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
        $is_mada_token = false;
        $cardValidationAlert = __("Please enter your card details.", 'wc_checkout_com');
        $iframe_style =  WC_Admin_Settings::get_option('ckocom_iframe_style');

        ?>
<input type="hidden" id="debug" value='<?php echo WC_Admin_Settings::get_option('cko_console_logging'); ?>' ;></input>
<input type="hidden" id="public-key" value='<?php echo $this->get_option( 'ckocom_pk' );?>'></input>
<input type="hidden" id="localization" value='<?php echo $this->get_localisation(); ?>'></input>
<input type="hidden" id="multiFrame" value='<?php echo $iframe_style; ?>'></input>
<input type="hidden" id="cko-icons"
    value='<?php echo  plugins_url ('checkout-com-unified-payments-api/assets/images/card-icons/'); ?>'></input>
<input type="hidden" id="is-mada" value='<?php echo $mada_enable; ?>'></input>
<input type="hidden" id="mada-token" value='<?php echo $is_mada_token; ?>'></input>
<input type="hidden" id="user-logged-in" value='<?php echo is_user_logged_in(); ?>'></input>
<input type="hidden" id="card-validation-alert" value='<?php echo $cardValidationAlert; ?>'></input>
<?php

        // check if user is logged-in or a guest
        if (!is_user_logged_in()) {
            ?>
<script>
jQuery('.woocommerce-SavedPaymentMethods.wc-saved-payment-methods').hide()
</script>
<?php
        }

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
        <label for="cko-cvv"><?php esc_html_e( 'Card Code', 'wc_checkout_com' ); ?> <span
                class="required">*</span></label>
        <input id="cko-cvv" type="text" autocomplete="off" class="input-text"
            placeholder="<?php esc_attr_e( 'CVV', 'wc_checkout_com' ); ?>"
            name="<?php echo esc_attr( $this->id ); ?>-card-cvv" />
    </p>
</div>
<?php } ?>
<div class="cko-form" style="display: none; padding-top: 10px;padding-bottom: 5px;">
    <input type="hidden" id="cko-card-token" name="cko-card-token" value="" />
    <input type="hidden" id="cko-card-bin" name="cko-card-bin" value="" />

    <?php if ($iframe_style == 0) {
                ?>
    <div class="one-liner">
        <!-- frames will be loaded here -->
        <div class="card-frame"></div>
    </div>
    <?php
            } else { ?>
    <div class="multi-frame">
        <div class="input-container card-number">
            <div class="icon-container">
                <img id="icon-card-number"
                    src="<?php echo plugins_url ('checkout-com-unified-payments-api/assets/images/card-icons/card.svg'); ?>"
                    alt="PAN" />
            </div>
            <div class="card-number-frame"></div>
            <div class="icon-container payment-method">
                <img id="logo-payment-method" />
            </div>
            <div class="icon-container">
                <img id="icon-card-number-error"
                    src="<?php echo plugins_url ('checkout-com-unified-payments-api/assets/images/card-icons/error.svg'); ?>" />
            </div>
        </div>

        <div class="date-and-code">
            <div>
                <div class="input-container expiry-date">
                    <div class="icon-container">
                        <img id="icon-expiry-date"
                            src="<?php echo plugins_url ('checkout-com-unified-payments-api/assets/images/card-icons/exp-date.svg'); ?>"
                            alt="Expiry date" />
                    </div>
                    <div class="expiry-date-frame"></div>
                    <div class="icon-container">
                        <img id="icon-expiry-date-error"
                            src="<?php echo plugins_url ('checkout-com-unified-payments-api/assets/images/card-icons/error.svg'); ?>" />
                    </div>
                </div>
            </div>

            <div>
                <div class="input-container cvv">
                    <div class="icon-container">
                        <img id="icon-cvv"
                            src="<?php echo plugins_url ('checkout-com-unified-payments-api/assets/images/card-icons/cvv.svg'); ?>"
                            alt="CVV" />
                    </div>
                    <div class="cvv-frame"></div>
                    <div class="icon-container">
                        <img id="icon-cvv-error"
                            src="<?php echo plugins_url ('checkout-com-unified-payments-api/assets/images/card-icons/error.svg'); ?>" />
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php } ?>

    <!-- frame integration js file -->
    <script src='<?php echo plugins_url('../assets/js/cko-frames-integration.js',__FILE__) ?>'></script>

</div>

<!-- Show save card checkbox if this is selected on admin-->
<div class="cko-save-card-checkbox" style="display: none">
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
        if(sanitize_text_field($_POST['wc-wc_checkout_com_cards-payment-token'])) {
            if(sanitize_text_field($_POST['wc-wc_checkout_com_cards-payment-token']) == 'new') {
                $arg = sanitize_text_field($_POST['cko-card-token']);
            } else {
                $arg = sanitize_text_field($_POST['wc-wc_checkout_com_cards-payment-token']);
            }
        }

        // Check if empty card token and empty token_id
        if(empty($arg)){
            // check if card token exist
            if(sanitize_text_field($_POST['cko-card-token'])){
                $arg = sanitize_text_field($_POST['cko-card-token']);
            } else {
                WC_Checkoutcom_Utility::wc_add_notice_self(__('There was an issue completing the payment.', 'wc_checkout_com'), 'error');
                return;
            }
        }

        // Create payment with card token
        $result = (array)  WC_Checkoutcom_Api_request::create_payment($order, $arg);


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
            if ( $save_card && isset( $_POST['wc-wc_checkout_com_cards-new-payment-method'] ) && sanitize_text_field( $_POST['wc-wc_checkout_com_cards-new-payment-method'] ) ) {
                // save in session for 3D secure payment
                $_SESSION['wc-wc_checkout_com_cards-new-payment-method'] = isset($_POST['wc-wc_checkout_com_cards-new-payment-method']);
            }

            return array(
                'result'        => 'success',
                'redirect'      => $result['3d'],
            );
        }

        // save card in db
        if($save_card && sanitize_text_field($_POST['wc-wc_checkout_com_cards-new-payment-method'])){
            $this->save_token(get_current_user_id(), $result);
        }

        // save source id for subscription
        if (class_exists("WC_Subscriptions_Order")) {
            WC_Checkoutcom_Subscription::save_source_id($order_id, $order, $result['source']['id']);
        }

        // Set action id as woo transaction id
        update_post_meta($order_id, '_transaction_id', $result['action_id']);
        update_post_meta($order_id, '_cko_payment_id', $result['id']);

        // Get cko auth status configured in admin
        $status = WC_Admin_Settings::get_option('ckocom_order_authorised');
        $message = __("Checkout.com Payment Authorised " ."</br>". " Action ID : {$result['action_id']} ", 'wc_checkout_com');

        // check if payment was flagged
        if ($result['risk']['flagged']) {
            // Get cko auth status configured in admin
            $status = WC_Admin_Settings::get_option('ckocom_order_flagged');
            $message = __("Checkout.com Payment Flagged " ."</br>". " Action ID : {$result['action_id']} ", 'wc_checkout_com');
        }

        // add notes for the order
        $order->add_order_note($message);

        $order_status = $order->get_status();

	    if ( $order_status == 'pending' || $order_status == 'failed' ) {
            update_post_meta($order_id, 'cko_payment_authorized', true);
            $order->update_status($status);
        }

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

        $order_id = $result['metadata']['order_id'];
        $action = $result['actions'];

        // Get object as an instance of WC_Subscription
        $subscription_object = wc_get_order( $order_id );

        $order = new WC_Order( $order_id );

        // Query order by order number to check if order exist
        if (!$order) {
            $orders = wc_get_orders( array(
                    'order_number' =>  $order_id
                )
            );

            $order = $orders[0];
            $order_id = $order->get_id();
        }

        // Redirect to cart if an error occured
        if (isset($result['error']) && !empty($result['error'])) {
            WC_Checkoutcom_Utility::wc_add_notice_self(__($result['error'],'wc_checkout_com'), 'error');
            wp_redirect(wc_get_checkout_url());
            exit();
        }

        // Redirect to my-account/payment-method if card verification failed
        // show error to customer
        if(isset($result['card_verification']) == 'error'){
            WC_Checkoutcom_Utility::wc_add_notice_self(__('Unable to add payment method to your account.', 'wc_checkout_com'), 'error');
            wp_redirect($result['redirection_url']);
            exit;
        }

        // Redirect to my-account/payment-method if card verification successful
        // show notice to customer
        if(isset($result['status']) == 'Card Verified' && isset($result['metadata']['card_verification'])){

            $this->save_token(get_current_user_id(), $result);

            WC_Checkoutcom_Utility::wc_add_notice_self(__('Payment method successfully added.', 'wc_checkout_com'), 'notice');
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
        $message = __("Checkout.com Payment Authorised " ."</br>". " Action ID : {$action['0']['id']} ", 'wc_checkout_com');

        // check if payment was flagged
        if ($result['risk']['flagged']) {
            // Get cko auth status configured in admin
            $status = WC_Admin_Settings::get_option('ckocom_order_flagged');
            $message = __("Checkout.com Payment Flagged " ."</br>". " Action ID : {$action['0']['id']} ", 'wc_checkout_com');
        }

        if ( 'Canceled' === $result['status'] ) {
            $status  = WC_Admin_Settings::get_option( 'ckocom_order_void' );
            $message = __( "Checkout.com Payment Canceled" . "</br>" . " Action ID : {$action['0']['id']} ", 'wc_checkout_com' );
        }

        if ($result['status'] == 'Captured') {
            update_post_meta($order_id, 'cko_payment_captured', true);
            $status = WC_Admin_Settings::get_option('ckocom_order_captured');
            $message = __("Checkout.com Payment Captured" ."</br>". " Action ID : {$action['0']['id']} ", 'wc_checkout_com');
        }

        // save card to db
        $save_card =  WC_Admin_Settings::get_option('ckocom_card_saved');
        if ( $save_card && isset( $_SESSION['wc-wc_checkout_com_cards-new-payment-method'] ) && $_SESSION['wc-wc_checkout_com_cards-new-payment-method'] ) {
            $this->save_token($order->get_user_id(), $result);
            unset($_SESSION['wc-wc_checkout_com_cards-new-payment-method']);
        }

        // save source id for subscription
        if (class_exists("WC_Subscriptions_Order")) {
            WC_Checkoutcom_Subscription::save_source_id($order_id, $subscription_object, $result['source']['id']);
        }

        $order_status = $order->get_status();

        $order->add_order_note($message);

        if ( $order_status == 'pending' || $order_status == 'failed' ) {
            update_post_meta($order_id, 'cko_payment_authorized', true);
            $order->update_status($status);
        }

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

        $core_settings['ckocom_sk'] = cko_is_nas_account() ? 'Bearer ' . $core_settings['ckocom_sk'] : $core_settings['ckocom_sk'];

        // Initialize the Checkout Api
        $checkout = new Checkout\CheckoutApi($core_settings['ckocom_sk'], $environment);

        // Load method with card token
        $method = new Checkout\Models\Payments\TokenSource(sanitize_text_field($_POST['cko-card-token']));

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
        $redirection_url = add_query_arg( 'wc-api', 'wc_checkoutcom_callback', home_url( '/' ) );
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
                $error_message .= __($ex->getMessage() , 'wc_checkout_com');
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
            if( isset( $_SESSION['cko-is-mada'] ) ) {
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
        $message = __("Checkout.com Payment refunded from Admin " ."</br>". " Action ID : {$result['action_id']} ", 'wc_checkout_com');

        if(isset($_SESSION['cko-refund-is-less'])){
            if($_SESSION['cko-refund-is-less']){
                $status = WC_Admin_Settings::get_option('ckocom_order_captured');
                $order->add_order_note( __("Checkout.com Payment Partially refunded from Admin " ."</br>". " Action ID : {$result['action_id']}", 'wc_checkout_com') );

                unset($_SESSION['cko-refund-is-less']);

                return true;
            }
        }

        // add note for order
        $order->add_order_note($message);

        // when true is returned, status is changed to refunded automatically
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

        // Create apache function if not exist to get header authorization
        if( !function_exists('apache_request_headers') ) {
            function apache_request_headers() {
              $arh = array();
              $rx_http = '/\AHTTP_/';
              foreach($_SERVER as $key => $val) {
                    if( preg_match($rx_http, $key) ) {
                      $arh_key = preg_replace($rx_http, '', $key);
                      $rx_matches = array();
                      $rx_matches = explode('_', $arh_key);
                      if( count($rx_matches) > 0 and strlen($arh_key) > 2 ) {
                            foreach($rx_matches as $ak_key => $ak_val) $rx_matches[$ak_key] = ucfirst($ak_val);
                            $arh_key = implode('-', $rx_matches);
                      }
                      $arh[$arh_key] = $val;
                    }
              }
              return( $arh );
            }
        }

        $header = array_change_key_case(apache_request_headers(),CASE_LOWER);
        $header_signature = $header['cko-signature'];

        $core_settings = get_option('woocommerce_wc_checkout_com_cards_settings');
        $raw_event = file_get_contents('php://input');

        $core_settings['ckocom_sk'] = cko_is_nas_account() ? 'Bearer ' . $core_settings['ckocom_sk'] : $core_settings['ckocom_sk'];

        $signature =  WC_Checkoutcom_Utility::verifySignature($raw_event, $core_settings['ckocom_sk'], $header_signature);

        // check if cko signature matches
        if($signature === false){
            return http_response_code(401);
        }

        $payment_id = get_post_meta($data->data->metadata->order_id, '_cko_payment_id', true );

        // check if payment ID matches that of the webhook
        if($payment_id !== $data->data->id){
            $message = __('order payment Id ('. $payment_id .') does not match that of the webhook ('. $data->data->id .')', 'wc_checkout_com');
            WC_Checkoutcom_Utility::logger($message , null);

            return http_response_code(422);
        }


        // Get webhook event type from data
        $event_type = $data->type;

        switch ($event_type){
            case 'card_verified' :
                $response = WC_Checkout_Com_Webhook::card_verified($data);
                break;
            case 'payment_approved':
                $response = WC_Checkout_Com_Webhook::authorize_payment($data);
                break;
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
            case 'payment_declined':
                $response = WC_Checkout_Com_Webhook::decline_payment($data);
                break;

            default:
                $response = true;
                break;
        }

        $http_code = $response ? 200 : 400;

        return http_response_code($http_code);
    }

    /**
     * get_localisation
     *
     * @return void
     */
    public function get_localisation()
    {
        $woo_locale   = str_replace( "_", "-", get_locale() );
        $locale       = substr( $woo_locale, 0, 2 );
        $localization = "";

        switch ( $locale ) {
            case 'en':
                $localization = "EN-GB";
                break;
            case 'it':
                $localization = "IT-IT";
                break;
            case 'nl':
                $localization = "NL-NL";
                break;
            case 'fr':
                $localization = "FR-FR";
                break;
            case 'de':
                $localization = "DE-DE";
                break;
            case 'kr':
                $localization = "KR-KR";
                break;
            case 'es':
                $localization = "ES-ES";
                break;
            default:
                $localization = WC_Admin_Settings::get_option('ckocom_language_fallback');
        }

        return $localization;
    }

}
