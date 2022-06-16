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

add_action( 'plugins_loaded', 'init_checkout_com_gateway_class', 0 );

/**
 * This function registers our PHP class as a WooCommerce payment gateway.
 */
function init_checkout_com_gateway_class() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	load_plugin_textdomain( 'wc_checkout_com', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );

	include_once( 'includes/class-wc-gateway-checkout-com-cards.php' );
	include_once( 'includes/class-wc-gateway-checkout-com-apple-pay.php' );
	include_once( 'includes/class-wc-gateway-checkout-com-google-pay.php' );
	include_once( 'includes/class-wc-gateway-checkout-com-alternative-payments.php' );

	// Load payment gateway class.
	add_filter( 'woocommerce_payment_gateways', 'checkout_com_add_gateway' );

	// Hide Apple Pay, Google Pay from payment method tab.
	wc_enqueue_js(
		"
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
	"
	);
}

/**
 * Add payment method class to WooCommerce.
 *
 * @param array $methods Array of payment methods.
 *
 * @return array
 */
function checkout_com_add_gateway( $methods ) {

	$array = get_selected_apms_class();

	$methods[] = 'WC_Gateway_Checkout_Com_Cards';
	$methods[] = 'WC_Gateway_Checkout_Com_Apple_Pay';
	$methods[] = 'WC_Gateway_Checkout_Com_Google_Pay';
	$methods[] = 'WC_Gateway_Checkout_Com_Alternative_Payments';

	$methods = sizeof( $array ) > 0 ? array_merge( $methods, $array ) : $methods;

	return $methods;
}

/**
 * Return the class name of the apm selected.
 *
 * @return array
 */
function get_selected_apms_class() {

	$apms_settings       = get_option( 'woocommerce_wc_checkout_com_alternative_payments_settings' );
	$selected_apms_class = [];

	// Check if alternative payment method is enabled.
	if ( ! empty( $apms_settings['enabled'] ) && 'yes' === $apms_settings['enabled'] ) {
		$apm_selected = ! empty( $apms_settings['ckocom_apms_selector'] ) ? $apms_settings['ckocom_apms_selector'] : [];

		// Get apm selected and add the class name in array.
		foreach ( $apm_selected as $value ) {
			$selected_apms_class[] = 'WC_Gateway_Checkout_Com_Alternative_Payments' . '_' . $value;
		}
	}

	return $selected_apms_class;
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'checkout_com_action_links' );

/**
 * Add settings link.
 *
 * @param mixed $links Plugin Action links.
 *
 * @return array
 */
function checkout_com_action_links( $links ) {
	$plugin_links = [
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_cards' ) . '">' . __( 'Settings', 'wc_checkout_com' ) . '</a>',
	];

	return array_merge( $plugin_links, $links );
}

// This action will register flagged order status in woocommerce.
add_action( 'init', 'register_cko_new_order_statuses' );

/**
 * Register flagged order status.
 */
function register_cko_new_order_statuses() {
	register_post_status(
		'wc-flagged',
		[
			'label'                     => _x( 'Suspected Fraud', 'Order status', 'wc_checkout_com' ),
			'public'                    => true,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: number of orders */
			'label_count'               => _n_noop( 'Suspected Fraud <span class="count">(%s)</span>', 'Suspected Frauds <span class="count">(%s)</span>', 'wc_checkout_com' ),
		]
	);
}


add_filter( 'wc_order_statuses', 'my_new_wc_order_statuses' );

/**
 * Register flagged status in wc_order_statuses.
 *
 * @param array $order_statuses Array of order statuses.
 *
 * @return array
 */
function my_new_wc_order_statuses( $order_statuses ) {
	$order_statuses['wc-flagged'] = _x( 'Suspected Fraud', 'Order status', 'wc_checkout_com' );

	return $order_statuses;
}

add_action( 'wp_head', 'cko_frames_js' );

/**
 * Load frames js on checkout page.
 */
function cko_frames_js() {
	wp_register_script( 'cko-frames-script', 'https://cdn.checkout.com/js/framesv2.min.js', [ 'jquery' ] );
	wp_enqueue_script( 'cko-frames-script' );

	$vars = [
		'card-number' => esc_html__( 'Please enter a valid card number', 'wc_checkout_com' ),
		'expiry-date' => esc_html__( 'Please enter a valid expiry date', 'wc_checkout_com' ),
		'cvv'         => esc_html__( 'Please enter a valid cvv code', 'wc_checkout_com' ),
	];

	wp_localize_script( 'cko-frames-script', 'cko_frames_vars', $vars );
}

add_action( 'woocommerce_checkout_process', 'cko_check_if_empty' );

/**
 * Validate cvv on checkout page.
 */
function cko_check_if_empty() {
	if ( 'wc_checkout_com_cards' === $_POST['payment_method'] ) {

		// Check if require cvv is enabled in module setting.
		if ( WC_Admin_Settings::get_option( 'ckocom_card_saved' )
			&& WC_Admin_Settings::get_option( 'ckocom_card_require_cvv' )
			&& 'new' !== $_POST['wc-wc_checkout_com_cards-payment-token'] ) {
			// check if cvv is empty on checkout page.
			if ( empty( $_POST['wc_checkout_com_cards-card-cvv'] ) ) {
				wc_add_notice( esc_html__( 'Please enter a valid cvv.', 'wc_checkout_com' ), 'error' );
			}
		}
	}
}

add_action( 'wp_enqueue_scripts', 'callback_for_setting_up_scripts' );

/**
 * Load checkout.com style sheet.
 * Load Google Pay js.
 */
function callback_for_setting_up_scripts() {
	// load cko custom css.
	$css_path     = WC_CHECKOUTCOM_PLUGIN_URL . '/assets/css/checkoutcom-styles.css';
	$normalize    = WC_CHECKOUTCOM_PLUGIN_URL . '/assets/css/normalize.css';
	$frames_style = WC_CHECKOUTCOM_PLUGIN_URL . '/assets/css/style.css';
	$multi_frame  = WC_CHECKOUTCOM_PLUGIN_URL . '/assets/css/multi-iframe.css';

	// register cko css.
	wp_register_style( 'checkoutcom-style', $css_path );
	wp_register_style( 'normalize', $normalize );
	wp_enqueue_style( 'checkoutcom-style' );
	wp_enqueue_style( 'normalize' );

	if ( WC_Admin_Settings::get_option( 'ckocom_iframe_style' ) ) {
		wp_register_style( 'frames_style', $multi_frame );
	} else {
		wp_register_style( 'frames_style', $frames_style );
	}

	wp_enqueue_style( 'frames_style' );

	// Load cko google pay setting.
	$google_settings    = get_option( 'woocommerce_wc_checkout_com_google_pay_settings' );
	$google_pay_enabled = ! empty( $google_settings['enabled'] ) && 'yes' === $google_settings['enabled'];

	// Enqueue google pay script.
	if ( $google_pay_enabled ) {
		wp_enqueue_script( 'cko-google-script', 'https://pay.google.com/gp/p/js/pay.js', [ 'jquery' ] );
	}

	// load cko apm settings.
	$apm_settings = get_option( 'woocommerce_wc_checkout_com_alternative_payments_settings' );
	$apm_enable   = ! empty( $apms_settings['enabled'] ) && 'yes' === $apms_settings['enabled'];

	if ( $apm_enable && ! empty( $apm_settings['ckocom_apms_selector'] ) ) {
		foreach ( $apm_settings['ckocom_apms_selector'] as $value ) {
			if ( 'klarna' === $value ) {
				wp_enqueue_script( 'cko-klarna-script', 'https://x.klarnacdn.net/kp/lib/v1/api.js', [ 'jquery' ] );
			}
		}
	}

}

add_action( 'woocommerce_order_item_add_line_buttons', 'cko_refund' );

/**
 * Disable cko refund button to prevent refund of 0.00
 */
function cko_refund() {
	wc_enqueue_js(
		"
		// disable button by default
		const refund_button = document.getElementsByClassName('button-primary do-api-refund')[0];
		refund_button.disabled = true

		$('#refund_amount').on('change', function(){
			$(this).val() <= 0 ?  refund_button.disabled = true : refund_button.disabled = false;
	   });
	"
	);
};

add_action( 'woocommerce_order_item_add_action_buttons', 'action_woocommerce_order_item_add_action_buttons', 10, 1 );

/**
 * Add custom button to admin order.
 * Button capture and button void.
 *
 * @param WC_Order $order The order being edited.
 */
function action_woocommerce_order_item_add_action_buttons( $order ) {

	// Check order payment method is checkout.
	if ( false === strpos( $order->get_payment_method(), 'wc_checkout_com_' ) ) {
		return;
	}

	$order_status   = $order->get_status();
	$auth_status    = str_replace( 'wc-', '', WC_Admin_Settings::get_option( 'ckocom_order_authorised', 'on-hold' ) );
	$capture_status = str_replace( 'wc-', '', WC_Admin_Settings::get_option( 'ckocom_order_captured', 'processing' ) );

	if ( $order->get_payment_method() === 'wc_checkout_com_cards' || $order->get_payment_method() === 'wc_checkout_com_google_pay' ) {
		?>
<input type="hidden" value="" name="cko_payment_action" id="cko_payment_action" />
<button class="button" id="cko-capture" style="display:none;">Capture</button>
<button class="button" id="cko-void" style="display:none;">Void</button>
		<?php
	}

	wc_enqueue_js(
		"
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
		"
	);
};

add_action( 'save_post', 'renew_save_again', 10, 2 );

/**
 * Do action for capture and void button.
 *
 * @param int    $post_id Post ID being saved.
 * @param object $post Post object being saved.
 *
 * @return bool|void
 */
function renew_save_again( $post_id, $post ) {

	if ( is_admin() ) {
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
				$status = WC_Admin_Settings::get_option( 'ckocom_order_captured', 'processing' );

				/* translators: %s: Action id. */
				$message = sprintf( esc_html__( 'Checkout.com Payment Captured from Admin - Action ID : %s', 'wc_checkout_com' ), $result['action_id'] );

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
				$status = WC_Admin_Settings::get_option( 'ckocom_order_void', 'cancelled' );

				/* translators: %s: Action id. */
				$message = sprintf( esc_html__( 'Checkout.com Payment Voided from Admin - Action ID : %s', 'wc_checkout_com' ), $result['action_id'] );

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

add_action( 'woocommerce_thankyou', 'add_fawry_number' );

/**
 * Add the fawry reference number in the "thank you" page.
 *
 * @param mixed $order_id Order ID.
 *
 * @return void
 */
function add_fawry_number( $order_id ) {
	$fawry_number = get_post_meta( $order_id, 'cko_fawry_reference_number', $single = true );
	$fawry        = __( 'Fawry reference number: ', 'wc_checkout_com' );
	if ( $fawry_number ) {
		wc_enqueue_js(
			"
			jQuery( function(){
				jQuery('.woocommerce-thankyou-order-details.order_details').append('<li class=\"woocommerce-order-overview\">$fawry<strong>$fawry_number</strong></li>')
			})
		"
		);
	}
}

add_filter( 'woocommerce_gateway_icon', 'cko_gateway_icon', 10, 2 );

/**
 * Filter for custom gateway icons.
 *
 * @param string $icons Icons markup.
 * @param string $id Gateway ID.
 *
 * @return false|string|void
 */
function cko_gateway_icon( $icons, $id ) {

	$plugin_url = WC_CHECKOUTCOM_PLUGIN_URL . '/assets/images/';

	/* Check if checkoutcom gateway */
	if ( 'wc_checkout_com_cards' === $id ) {
		$display_card_icon = WC_Admin_Settings::get_option( 'ckocom_display_icon', '0' ) === '1';

		/* check if display card option is selected */
		if ( $display_card_icon ) {
			$card_icon = WC_Admin_Settings::get_option( 'ckocom_card_icons' );

			$icons = '';

			foreach ( $card_icon as $key => $value ) {
				$card_icons = $plugin_url . $value . '.svg';
				$icons     .= "<img src='$card_icons' id='cards-icon'>";
			}

			return $icons;
		}

		return false;
	}
	/**
	 *  Display logo for APM available for payment
	 */
	if ( strpos( $id, 'alternative_payments' ) ) {

		$apm_available = WC_Checkoutcom_Utility::get_alternative_payment_methods();

		foreach ( $apm_available as $value ) {
			if ( strpos( $id, $value ) ) {
				$apm_icons = $plugin_url . $value . '.svg';
				$icons    .= "<img src='$apm_icons' id='apm-icon'>";

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

add_action( 'woocommerce_scheduled_subscription_payment_wc_checkout_com_cards', 'subscription_payment', 10, 2 );

/**
 * Function to handle subscription renewal payment for card.
 *
 * @param float    $renewal_total The amount to charge.
 * @param WC_Order $renewal_order A WC_Order object created to record the renewal payment.
 */
function subscription_payment( $renewal_total, $renewal_order ) {
	include_once( 'includes/subscription/class-wc-checkout-com-subscription.php' );

	WC_Checkoutcom_Subscription::renewal_payment( $renewal_total, $renewal_order );
}

add_action( 'woocommerce_scheduled_subscription_payment_wc_checkout_com_alternative_payments_sepa', 'subscription_payment_sepa', 10, 2 );

/**
 * Function to handle subscription renewal payment for APM SEPA.
 *
 * @param float    $renewal_total The amount to charge.
 * @param WC_Order $renewal_order A WC_Order object created to record the renewal payment.
 */
function subscription_payment_sepa( $renewal_total, $renewal_order ) {

	include_once( 'includes/subscription/class-wc-checkout-com-subscription.php' );

	WC_Checkoutcom_Subscription::renewal_payment( $renewal_total, $renewal_order );
}

add_action( 'woocommerce_subscription_status_cancelled', 'subscription_cancelled', 20 );

/**
 * Function to handle subscription cancelled.
 *
 * @param WC_Subscription|WC_Order $subscription The subscription or order for which the status has changed.
 *
 * @return void
 */
function subscription_cancelled( $subscription ) {
	include_once( 'includes/subscription/class-wc-checkout-com-subscription.php' );

	WC_Checkoutcom_Subscription::subscription_cancelled( $subscription );
}

// @TODO : Remove all below functions and logic once product is fixed.
if ( cko_is_nas_account() ) {
	add_filter( 'rewrite_rules_array', 'cko_add_rewrite_rules', -1 );
	add_filter( 'query_vars', 'cko_add_query_vars' );
	add_action( 'parse_request', 'cko_set_query_vars', -1, 1 );
}

/**
 * Add rewrite rules for NAS.
 *
 * @param array $rules Rules.
 *
 * @return array
 */
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

/**
 * Add query vars for NAS.
 *
 * @param array $vars Query vars.
 *
 * @return array
 */
function cko_add_query_vars( $vars ) {
	$vars[] = 'cko-callback';
	$vars[] = 'cko-session-id';

	return $vars;
}

/**
 * Set query vars for NAS.
 *
 * @param WP $wp WordPress environment object.
 *
 * @return void
 */
function cko_set_query_vars( $wp ) {

	if ( ! empty( $wp->query_vars['cko-callback'] ) ) {
		$wp->set_query_var( 'wc-api', 'wc_checkoutcom_callback' );
	}
}
