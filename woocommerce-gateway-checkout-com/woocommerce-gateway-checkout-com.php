<?php
/*
Plugin Name: WooCommerce Checkout.com Gateway
Plugin URI: https://www.checkout.com/
Description: Extends WooCommerce by Adding the Checkout.com Gateway.
Version: 4.0.3
Author: Checkout.com
Author URI: https://www.checkout.com/
*/

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_action( 'plugins_loaded', 'init_checkout_com_gateway_class', 0 );
function init_checkout_com_gateway_class()
{
    if (!class_exists('WC_Payment_Gateway')) return;

    include_once('includes/class-wc-gateway-checkout-com-cards.php');
    include_once('includes/class-wc-gateway-checkout-com-apple-pay.php');
    include_once('includes/class-wc-gateway-checkout-com-google-pay.php');
    include_once('includes/class-wc-gateway-checkout-com-alternative-payments.php');

    // Add payment method class to WooCommerce
    add_filter( 'woocommerce_payment_gateways', 'checkout_com_add_gateway' );
    function checkout_com_add_gateway( $methods ) {
        $methods[] = 'WC_Gateway_Checkout_Com_Cards';
//        $methods[] = 'WC_Gateway_Checkout_Com_Apple_Pay';
        $methods[] = 'WC_Gateway_Checkout_Com_Google_Pay';
        $methods[] = 'WC_Gateway_Checkout_Com_Alternative_Payments';
        return $methods;
    }

    // Hide Apple pay, Google pay from payment method tab
    wc_enqueue_js( "
        jQuery( function(){
            setTimeout(function(){ 
                if(jQuery('[data-gateway_id=\"wc_checkout_com_apple_pay\"]').length > 0) {
                    jQuery('[data-gateway_id=\"wc_checkout_com_apple_pay\"]').hide();
                }
                
                if(jQuery('[data-gateway_id=\"wc_checkout_com_google_pay\"]').length > 0) {
                    jQuery('[data-gateway_id=\"wc_checkout_com_google_pay\"]').hide();
                }
                
                if(jQuery('[data-gateway_id=\"wc_checkout_com_alternative_payments\"]').length > 0) {
                    jQuery('[data-gateway_id=\"wc_checkout_com_alternative_payments\"]').hide();
                }
            }, 1500);
        });
    ");
}

/*
 * Add settings link
 */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'checkout_com_action_links' );
function checkout_com_action_links($links) {
    $plugin_links = array(
        '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_cards' ) . '">' . __( 'Settings', 'wc_checkout_com_cards' ) . '</a>',
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
}

/*
 * validate cvv on checkout page
 */
add_action('woocommerce_checkout_process', 'cko_check_if_empty');
function cko_check_if_empty()
{
    // check if require cvv is enable in module setting
    if(WC_Admin_Settings::get_option('ckocom_card_require_cvv') && $_POST['wc-wc_checkout_com_cards-payment-token'] != 'new' ){
        // check if cvv is empty on checkout page
        if ( empty( $_POST['wc_checkout_com_cards-card-cvv'] ) )
        wc_add_notice( 'Please enter a valid cvv.', 'error' );
    }
}

/*
 * load checkout.com style sheet
 * load google pay js
 */
add_action('wp_enqueue_scripts', 'callback_for_setting_up_scripts');
function callback_for_setting_up_scripts() {
    // load cko custom css
    $checkoutcom_style = plugins_url().'/woocommerce-gateway-checkout-com/assets/css/checkoutcom-styles.css';
    $normalize = plugins_url().'/woocommerce-gateway-checkout-com/assets/css/normalize.css';
    $frames_style = plugins_url().'/woocommerce-gateway-checkout-com/assets/css/style.css';


    // register cko css
    wp_register_style( 'checkoutcom-style', $checkoutcom_style);
    wp_register_style( 'normalize', $normalize);
    wp_register_style( 'frames_style', $frames_style);
    wp_enqueue_style( 'checkoutcom-style' );
    wp_enqueue_style( 'normalize' );
    wp_enqueue_style( 'frames_style' );
    // Enqueue google pay script
    wp_enqueue_script( 'cko-google-script', 'https://pay.google.com/gp/p/js/pay.js', array( 'jquery' ) );

    // load cko apm settings
    $apm_settings = get_option('woocommerce_wc_checkout_com_alternative_payments_settings');
    $apm_enable = $apm_settings['enabled'] == 'yes' ? true : false;

    if ($apm_enable) {
        foreach ($apm_settings['ckocom_apms_selector'] as $value) {
            if($value == 'klarna') {
                wp_enqueue_script( 'cko-klarna-script', 'https://x.klarnacdn.net/kp/lib/v1/api.js', array( 'jquery' ) );
            }
        }
    }

}

/*
 * Add custom button to admin order
 * Button capture and button void
 */
add_action( 'woocommerce_order_item_add_action_buttons', 'action_woocommerce_order_item_add_action_buttons', 10, 1 );
function action_woocommerce_order_item_add_action_buttons( $order ) {

    $order_status = $order->get_status();
    $auth_status = str_replace('wc-', '', WC_Admin_Settings::get_option('ckocom_order_authorised'));
    $capture_status = str_replace('wc-', '', WC_Admin_Settings::get_option('ckocom_order_captured'));



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
                    } else if (order_status == capture_status){
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
add_action('save_post', 'renew_save_again', 10, 3);
function renew_save_again($post_id, $post, $update){
    global $woocommerce;

    $slug = 'shop_order';
    if(is_admin()){
        // If this isn't a 'woocommercer order' post, don't update it.
        if ( $slug != $post->post_type ) {
            return;
        }
        if(isset($_POST['cko_payment_action']) && $_POST['cko_payment_action']){

            $order = wc_get_order( $_POST['post_ID'] );

            WC_Admin_Notices::remove_notice('wc_checkout_com_cards');

            // check if post is capture
            if($_POST['cko_payment_action'] == 'cko-capture'){

                // send capture request to cko
                $result = (array) WC_Checkoutcom_Api_request::capture_payment();

                if (isset($result['error']) && !empty($result['error'])){
                    WC_Admin_Notices::add_custom_notice('wc_checkout_com_cards', __($result['error']));
                    return false;
                }

                // Set action id as woo transaction id
                update_post_meta($_POST['post_ID'], '_transaction_id', $result['action_id']);
                update_post_meta($_POST['post_ID'], 'cko_payment_captured', true);

                // Get cko capture status configured in admin
                $status = WC_Admin_Settings::get_option('ckocom_order_captured');
                $message = __("Checkout.com Payment Captured (Transaction ID - {$result['action_id']}) ", 'wc_checkout_com_cards');

                // Update order status on woo backend
                $order->update_status($status,$message);

                return true;

            } elseif ($_POST['cko_payment_action'] == 'cko-void') {
                // check if post is void
                // send void request to cko
                $result = (array) WC_Checkoutcom_Api_request::void_payment();

                if (isset($result['error']) && !empty($result['error'])){
                    WC_Admin_Notices::add_custom_notice('wc_checkout_com_cards', __($result['error']));
                    return false;
                }

                // Set action id as woo transaction id
                update_post_meta($_POST['post_ID'], '_transaction_id', $result['action_id']);

                // Get cko capture status configured in admin
                $status = WC_Admin_Settings::get_option('ckocom_order_void');
                $message = __("Checkout.com Payment Voided (Transaction ID - {$result['action_id']}) ", 'wc_checkout_com_cards');

                // Update order status on woo backend
                $order->update_status($status,$message);

                // increase stock level
                wc_increase_stock_levels($_POST['post_ID']);

                return true;

            } else {
                WC_Admin_Notices::add_custom_notice('wc_checkout_com_cards', __('An error has occured'));
                return false;
            }
        }
    }
}