<?php
/**
 * Plugin Name: Checkout.com Payment Gateway
 * Plugin URI: https://www.checkout.com/
 * Description: Extends WooCommerce by Adding the Checkout.com Gateway.
 * Author: Checkout.com
 * Author URI: https://www.checkout.com/
 * Version: 4.4.2
 * Requires at least: 4.0
 * Stable tag: 4.4.2
 * Tested up to: 6.0
 * WC tested up to: 6.5.1
 * Requires PHP: 7.2
 * Text Domain: wc_checkout_com
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Constants.
 */
define( 'WC_CHECKOUTCOM_PLUGIN_VERSION', '4.4.2' );
define( 'WC_CHECKOUTCOM_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'WC_CHECKOUTCOM_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_action( 'plugins_loaded', 'init_checkout_com_gateway_class', 0 );
function init_checkout_com_gateway_class()
{
    if (!class_exists('WC_Payment_Gateway')) return;

    load_plugin_textdomain( 'wc_checkout_com', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );

    include_once('includes/class-wc-gateway-checkout-com-cards.php');
    include_once('includes/class-wc-gateway-checkout-com-apple-pay.php');
    include_once('includes/class-wc-gateway-checkout-com-google-pay.php');
    include_once('includes/class-wc-gateway-checkout-com-alternative-payments.php');

    // Add payment method class to WooCommerce
    add_filter( 'woocommerce_payment_gateways', 'checkout_com_add_gateway' );
    function checkout_com_add_gateway( $methods ) {

        $array = get_selected_apms_Class();

        $methods[] = 'WC_Gateway_Checkout_Com_Cards';
        $methods[] = 'WC_Gateway_Checkout_Com_Apple_Pay';
        $methods[] = 'WC_Gateway_Checkout_Com_Google_Pay';
        $methods[] = 'WC_Gateway_Checkout_Com_Alternative_Payments';

        $methods = sizeof($array) > 0 ? array_merge($methods, $array) : $methods;

        return $methods;
    }

    // Hide Apple pay, Google pay from payment method tab
    wc_enqueue_js( "
        jQuery( function()
        {
            setTimeout(function(){ 
                if(jQuery('[data-gateway_id=\"wc_checkout_com_apple_pay\"]').length > 0) {
                    jQuery('[data-gateway_id=\"wc_checkout_com_apple_pay\"]').hide();
                }
                if(jQuery('[data-gateway_id=\"wc_checkout_com_google_pay\"]').length > 0) {
                    jQuery('[data-gateway_id=\"wc_checkout_com_google_pay\"]').hide();
                }
                
                if(jQuery('[data-gateway_id*=\"wc_checkout_com_alternative_payments\"]').length > 0) {
                    jQuery('[data-gateway_id*=\"wc_checkout_com_alternative_payments\"]').hide();
                }
            }, 1500);
        });
    ");
}

/**
 *  return the class name of the apm selected
 * @return array
 */
function get_selected_apms_Class() {

    $apms_settings = get_option('woocommerce_wc_checkout_com_alternative_payments_settings');
    $selected_apms_class = array();

    // check if alternative payment method is enabled
    if ($apms_settings['enabled'] == true) {
        $apm_selected = ! empty( $apms_settings['ckocom_apms_selector'] ) ? $apms_settings['ckocom_apms_selector'] : array();

        // get apm selected and add the class name in array
        foreach($apm_selected as $value) {
            $selected_apms_class[] = 'WC_Gateway_Checkout_Com_Alternative_Payments'.'_'.$value;
        }
    }

    return $selected_apms_class;
}

/*
 * Add settings link
 */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'checkout_com_action_links' );
function checkout_com_action_links($links) {
    $plugin_links = array(
        '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_cards' ) . '">' . __( 'Settings', 'wc_checkout_com' ) . '</a>',
    );

    return array_merge( $plugin_links, $links );
}


/*
 * New order status AFTER woo 2.2
 * This action will register Flagged order status in woocommerce
 */
add_action( 'init', 'register_cko_new_order_statuses' );
function register_cko_new_order_statuses()
{
    register_post_status( 'wc-flagged', array(
        'label'                     => _x( 'Suspected Fraud', 'Order status', 'woocommerce' ),
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Suspected Fraud <span class="count">(%s)</span>', 'Suspected Fraud<span class="count">(%s)</span>', 'woocommerce' )
    ) );
}

/*
 * Register Flagged status in wc_order_statuses.
 */
add_filter( 'wc_order_statuses', 'my_new_wc_order_statuses' );
function my_new_wc_order_statuses( $order_statuses )
{
    $order_statuses['wc-flagged'] = _x( 'Suspected Fraud', 'Order status', 'woocommerce' );

    return $order_statuses;
}

/*
 * Load frames js on checkout page
 */
add_action( 'wp_head', 'cko_frames_js');
function cko_frames_js()
{
    wp_register_script( 'cko-frames-script', 'https://cdn.checkout.com/js/framesv2.min.js', array( 'jquery' ) );
    wp_enqueue_script( 'cko-frames-script' );

	$vars = array(
		'card-number' => esc_html__( 'Please enter a valid card number', 'wc_checkout_com' ),
		'expiry-date' => esc_html__( 'Please enter a valid expiry date', 'wc_checkout_com' ),
		'cvv'         => esc_html__( 'Please enter a valid cvv code', 'wc_checkout_com' ),
	);

	wp_localize_script( 'cko-frames-script', 'cko_frames_vars', $vars );
}

/*
 * validate cvv on checkout page
 */
add_action('woocommerce_checkout_process', 'cko_check_if_empty');
function cko_check_if_empty()
{
    if($_POST['payment_method'] == 'wc_checkout_com_cards'){

         // check if require cvv is enable in module setting
        if(WC_Admin_Settings::get_option('ckocom_card_saved')
                && WC_Admin_Settings::get_option('ckocom_card_require_cvv')
                && $_POST['wc-wc_checkout_com_cards-payment-token'] !== 'new' ){
            // check if cvv is empty on checkout page
            if ( empty( $_POST['wc_checkout_com_cards-card-cvv']  ) ) {
                wc_add_notice( esc_html__( 'Please enter a valid cvv.', 'wc_checkout_com' ), 'error' );
            }
        }
    }
}

/*
 * load checkout.com style sheet
 * load google pay js
 */
add_action('wp_enqueue_scripts', 'callback_for_setting_up_scripts');
function callback_for_setting_up_scripts() {
    // load cko custom css
	$css_path     = WC_CHECKOUTCOM_PLUGIN_URL . '/assets/css/checkoutcom-styles.css';
	$normalize    = WC_CHECKOUTCOM_PLUGIN_URL . '/assets/css/normalize.css';
	$frames_style = WC_CHECKOUTCOM_PLUGIN_URL . '/assets/css/style.css';
	$multi_frame  = WC_CHECKOUTCOM_PLUGIN_URL . '/assets/css/multi-iframe.css';

    // register cko css
    wp_register_style( 'checkoutcom-style', $css_path);
    wp_register_style( 'normalize', $normalize);
    wp_enqueue_style( 'checkoutcom-style' );
    wp_enqueue_style( 'normalize' );


    if (WC_Admin_Settings::get_option('ckocom_iframe_style') ) {
        wp_register_style( 'frames_style', $multi_frame);
    } else {
        wp_register_style( 'frames_style', $frames_style);
    }

    wp_enqueue_style( 'frames_style' );

    // load cko google pay setting
    $google_settings = get_option('woocommerce_wc_checkout_com_google_pay_settings');
    $google_pay_enabled = $google_settings['enabled'] == true ? true : false;

    // Enqueue google pay script
    if ($google_pay_enabled){
        wp_enqueue_script( 'cko-google-script', 'https://pay.google.com/gp/p/js/pay.js', array( 'jquery' ) );
    }

    // load cko apm settings
    $apm_settings = get_option('woocommerce_wc_checkout_com_alternative_payments_settings');
    $apm_enable = $apm_settings['enabled'] == true ? true : false;

    if ($apm_enable && !empty($apm_settings['ckocom_apms_selector'])) {
        foreach ($apm_settings['ckocom_apms_selector'] as $value) {
            if($value == 'klarna') {
                wp_enqueue_script( 'cko-klarna-script', 'https://x.klarnacdn.net/kp/lib/v1/api.js', array( 'jquery' ) );
            }
        }
    }

}

/**
 * Disable cko refund button to prevent refund of 0.00
 */
add_action('woocommerce_order_item_add_line_buttons','cko_refund');
function cko_refund() {
    wc_enqueue_js("
        // disable button by default
        const refund_button = document.getElementsByClassName('button-primary do-api-refund')[0];
        refund_button.disabled = true

        $('#refund_amount').on('change', function(){
            $(this).val() <= 0 ?  refund_button.disabled = true : refund_button.disabled = false;
       });
    ");
};

/*
 * Add custom button to admin order
 * Button capture and button void
 */
add_action( 'woocommerce_order_item_add_action_buttons', 'action_woocommerce_order_item_add_action_buttons', 10, 1 );
function action_woocommerce_order_item_add_action_buttons( $order ) {

    // Check order payment method is checkout.
    if ( false === strpos( $order->get_payment_method(), 'wc_checkout_com_' ) ) {
        return;
    }

    $order_status = $order->get_status();
    $auth_status = str_replace('wc-', '', WC_Admin_Settings::get_option('ckocom_order_authorised', 'on-hold'));
    $capture_status = str_replace('wc-', '', WC_Admin_Settings::get_option('ckocom_order_captured', 'processing'));

    if($order->get_payment_method() == 'wc_checkout_com_cards'
        || $order->get_payment_method() == 'wc_checkout_com_google_pay') {

            ?>
<input type="hidden" value="" name="cko_payment_action" id="cko_payment_action" />
<button class="button" id="cko-capture" style="display:none;">Capture</button>
<button class="button" id="cko-void" style="display:none;">Void</button>
<?php
    }

    wc_enqueue_js( "
            jQuery( function(){
                setTimeout(function(){
                    
                    var order_status = '$order_status';
                    var auth_status = '$auth_status';
                    var capture_status = '$capture_status';
                  
                    // check if order status is same as auth status in cko settings
                    // hide refund button and show capture and void button
                    if(order_status == auth_status){
                        jQuery('.refund-items').hide();
                        jQuery('#cko-capture').show();
                        jQuery('#cko-void').show();
                    } else if (order_status === capture_status || 'completed' === order_status){
                        jQuery('.refund-items').show();
                    } else {
                        jQuery('.refund-items').hide();
                    }
                
                    if(jQuery('#cko-void').length > 0){
                         jQuery('#cko-void').click(function(){
                            document.getElementById('cko_payment_action').value = this.id;
                         })
                    }
                    
                    if(jQuery('#cko-capture').length > 0){
                         jQuery('#cko-capture').click(function(){
                            document.getElementById('cko_payment_action').value = this.id;
                         })
                    }
                }, 1500);
            });
        ");
};

/*
 * Do action for capture and void button
 */
add_action( 'save_post', 'renew_save_again', 10, 3 );

function renew_save_again( $post_id, $post, $update ) {

    if( is_admin() ) {
        // If this isn't a 'WooCommerce Order' post, don't update it.
        if ( 'shop_order' !== $post->post_type ) {
            return;
        }
	    if ( isset( $_POST['cko_payment_action'] ) && sanitize_text_field( $_POST['cko_payment_action'] ) ) {

		    $order = wc_get_order( sanitize_text_field( $_POST['post_ID'] ) );

		    WC_Admin_Notices::remove_notice( 'wc_checkout_com_cards' );

		    // check if post is capture.
		    if ( 'cko-capture' === sanitize_text_field( $_POST['cko_payment_action'] ) ) {

			    // send capture request to cko.
			    $result = (array) WC_Checkoutcom_Api_request::capture_payment();

			    if ( isset( $result['error'] ) && ! empty( $result['error'] ) ) {
				    WC_Admin_Notices::add_custom_notice( 'wc_checkout_com_cards', $result['error'] );

				    return false;
			    }

			    // Set action id as woo transaction id.
			    update_post_meta( sanitize_text_field( $_POST['post_ID'] ), '_transaction_id', $result['action_id'] );
			    update_post_meta( sanitize_text_field( $_POST['post_ID'] ), 'cko_payment_captured', true );

			    // Get cko capture status configured in admin.
			    $status  = WC_Admin_Settings::get_option( 'ckocom_order_captured', 'processing' );
			    $message = sprintf( esc_html__( "Checkout.com Payment Captured from Admin - Action ID : %s", 'wc_checkout_com' ), $result['action_id'] );

			    // add notes for the order and update status.
			    $order->add_order_note( $message );
			    $order->update_status( $status );

			    return true;

		    } elseif ( 'cko-void' === sanitize_text_field( $_POST['cko_payment_action'] ) ) {
			    // check if post is void.
			    // send void request to cko.
			    $result = (array) WC_Checkoutcom_Api_request::void_payment();

			    if ( isset( $result['error'] ) && ! empty( $result['error'] ) ) {
				    WC_Admin_Notices::add_custom_notice( 'wc_checkout_com_cards', $result['error'] );

				    return false;
			    }

			    // Set action id as woo transaction id.
			    update_post_meta( sanitize_text_field( $_POST['post_ID'] ), '_transaction_id', $result['action_id'] );
			    update_post_meta( sanitize_text_field( $_POST['post_ID'] ), 'cko_payment_voided', true );

			    // Get cko capture status configured in admin.
			    $status  = WC_Admin_Settings::get_option( 'ckocom_order_void', 'cancelled' );
			    $message = sprintf( esc_html__( "Checkout.com Payment Voided from Admin - Action ID : %s", 'wc_checkout_com' ), $result['action_id'] );

			    // add notes for the order and update status.
			    $order->add_order_note( $message );
			    $order->update_status( $status );

			    // increase stock level.
			    wc_increase_stock_levels( sanitize_text_field( $_POST['post_ID'] ) );

			    return true;

		    } else {
			    WC_Admin_Notices::add_custom_notice( 'wc_checkout_com_cards', esc_html__( 'An error has occurred.', 'wc_checkout_com' ) );

			    return false;
		    }
        }
    }
}

// add the fawry reference number in the "thank you" page
add_action( 'woocommerce_thankyou', 'addFawryNumber');
function addFawryNumber($order_id) {
    $fawryNumber = get_post_meta($order_id, "cko_fawry_reference_number", $single = true );
    $fawry = __('Fawry reference number: ', 'wc_checkout_com');
    if ($fawryNumber) {
        wc_enqueue_js("
            jQuery( function(){
                jQuery('.woocommerce-thankyou-order-details.order_details').append('<li class=\"woocommerce-order-overview\">$fawry<strong>$fawryNumber</strong></li>')
            })
        ");
    }
}


/**
 * filter for custom gateway icons
 */
add_filter( 'woocommerce_gateway_icon', 'cko_gateway_icon', 10, 2 );
function cko_gateway_icon( $icons, $id ) {

    $plugin_url = WC_CHECKOUTCOM_PLUGIN_URL . '/assets/images/';

    /* Check if checkoutcom gateway */
    if ($id == 'wc_checkout_com_cards') {
        $display_card_icon = WC_Admin_Settings::get_option('ckocom_display_icon') == 1 ? true : false;

        /* check if display card option is selected */
        if ($display_card_icon ) {
            $card_icon = WC_Admin_Settings::get_option('ckocom_card_icons');

            $icons = '';

            foreach ($card_icon as $key => $value) {
                $card_icons = $plugin_url . $value.'.svg';
                $icons .= "<img src='$card_icons' id='cards-icon'>";
            }

            return $icons;
        }

        return false;
    }
    /**
     *  Display logo for APM available for payment
     */
    if (strpos($id, 'alternative_payments')) {

        $apm_available = WC_Checkoutcom_Utility::get_alternative_payment_methods();

        foreach($apm_available as $value) {
            if (strpos($id, $value)) {
                $apm_icons = $plugin_url . $value .'.svg';
                $icons .= "<img src='$apm_icons' id='apm-icon'>";

                return $icons;
            }
        }

        return false;
    }
}

/**
 * Check if account is NAS.
 *
 * @return bool
 */
function cko_is_nas_account() {

    $core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings' );

    return isset( $core_settings['ckocom_account_type'] ) && ( 'NAS' === $core_settings['ckocom_account_type'] );
}

/**
 *  Hooked function to handle subscription renewal payment
 */
add_action( 'woocommerce_scheduled_subscription_payment_wc_checkout_com_cards', 'subscriptionPayment', 10, 2);
function subscriptionPayment($renewal_total, $renewal_order) {
    include_once('includes/subscription/class-wc-checkout-com-subscription.php');

    WC_Checkoutcom_Subscription::renewal_payment($renewal_total, $renewal_order);

}

/**
 *  Hooked function to handle subscription renewal payment
 */
add_action( 'woocommerce_scheduled_subscription_payment_wc_checkout_com_alternative_payments_sepa', 'subscriptionPaymentSepa', 10, 2);
function subscriptionPaymentSepa($renewal_total, $renewal_order) {

    include_once('includes/subscription/class-wc-checkout-com-subscription.php');

    WC_Checkoutcom_Subscription::renewal_payment($renewal_total, $renewal_order);

}

add_action( 'woocommerce_subscription_status_cancelled', 'subscriptionCancelled', 20 );
function subscriptionCancelled( $subscription ) {
	include_once( 'includes/subscription/class-wc-checkout-com-subscription.php' );

	WC_Checkoutcom_Subscription::subscription_cancelled( $subscription );
}

/**
 *  @TODO : Remove all below functions and logic once product is fixed.
 */
if ( cko_is_nas_account() ) {
    add_filter( 'rewrite_rules_array', 'cko_add_rewrite_rules', -1 );
    add_filter( 'query_vars', 'cko_add_query_vars' );
    add_action( 'parse_request', 'cko_set_query_vars', -1, 1 );
}

function cko_add_rewrite_rules( $rules ) {

    $new_rules = [];
    foreach ( $rules as $rule => $value ) {

        if ( '(.?.+?)(?:/([0-9]+))?/?$' === $rule ) {
	        $new_rules['checkoutcom-callback'] = 'index.php?&cko-callback=true';
        }
	    $new_rules[ $rule ] = $value;
    }

    return $new_rules;
}

function cko_add_query_vars( $vars ) {
    $vars[] = 'cko-callback';
    $vars[] = 'cko-session-id';

    return $vars;
}

function cko_set_query_vars( $wp ) {

    if ( ! empty( $wp->query_vars['cko-callback'] ) ) {
        $wp->set_query_var( 'wc-api', 'wc_checkoutcom_callback' );
    }
}
