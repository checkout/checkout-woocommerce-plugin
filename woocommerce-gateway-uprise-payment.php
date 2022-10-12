<?php
/**
 * Plugin Name: Uprise Payment
 * Plugin URI: https://uprisepay.com/
 * Description: Extends WooCommerce by Adding the Uprise Payment.
 * Author: Uprise Payment
 * Author URI: https://uprisepay.com/
 * Version: 4.4.7
 * Requires at least: 4.0
 * Stable tag: 4.4.7
 * Tested up to: 6.0
 * WC tested up to: 6.5.1
 * Requires PHP: 7.2
 * Text Domain: uprise-payment-woocommerce
 * Domain Path: /languages
 *
 * @package wc_uprise_payment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Constants.
 */
define( 'WC_UPRISEPAY_PLUGIN_VERSION', '4.4.7-a' );
define( 'WC_UPRISEPAY_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'WC_UPRISEPAY_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

add_action( 'plugins_loaded', 'init_uprise_payment_gateway_class', 0 );

/**
 * This function registers our PHP class as a WooCommerce payment gateway.
 */
function init_uprise_payment_gateway_class() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	load_plugin_textdomain( 'uprise-payment-woocommerce', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );

	include_once( 'includes/class-wc-gateway-uprise-payment-cards.php' );
	include_once( 'includes/class-wc-gateway-uprise-payment-apple-pay.php' );
	include_once( 'includes/class-wc-gateway-uprise-payment-google-pay.php' );
	include_once( 'includes/class-wc-gateway-uprise-payment-paypal.php' );
	include_once( 'includes/class-wc-gateway-uprise-payment-alternative-payments.php' );

	// Load payment gateway class.
	add_filter( 'woocommerce_payment_gateways', 'uprise_payment_add_gateway' );
}

/**
 * Add payment method class to WooCommerce.
 *
 * @param array $methods Array of payment methods.
 *
 * @return array
 */
function uprise_payment_add_gateway( $methods ) {

	$array = get_selected_apms_class();

	$methods[] = 'WC_Gateway_Uprise_Payment_Cards';
	$methods[] = 'WC_Gateway_Uprise_Payment_Apple_Pay';
	$methods[] = 'WC_Gateway_Uprise_Payment_Google_Pay';
	$methods[] = 'WC_Gateway_Uprise_Payment_PayPal';
	$methods[] = 'WC_Gateway_Uprise_Payment_Alternative_Payments';

	$methods = sizeof( $array ) > 0 ? array_merge( $methods, $array ) : $methods;

	return $methods;
}

/**
 * Return the class name of the apm selected.
 *
 * @return array
 */
function get_selected_apms_class() {

	$apms_settings       = get_option( 'woocommerce_wc_uprise_payment_alternative_payments_settings' );
	$selected_apms_class = [];

	// Check if alternative payment method is enabled.
	if ( ! empty( $apms_settings['enabled'] ) && 'yes' === $apms_settings['enabled'] ) {
		$apm_selected = ! empty( $apms_settings['upycom_apms_selector'] ) ? $apms_settings['upycom_apms_selector'] : [];

		// Get apm selected and add the class name in array.
		foreach ( $apm_selected as $value ) {
			$selected_apms_class[] = 'WC_Gateway_Uprise_Payment_Alternative_Payments' . '_' . $value;
		}
	}

	return $selected_apms_class;
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'uprise_payment_action_links' );

/**
 * Add settings link.
 *
 * @param mixed $links Plugin Action links.
 *
 * @return array
 */
function uprise_payment_action_links( $links ) {
	$plugin_links = [
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_uprise_payment_cards' ) . '">' . __( 'Settings', 'uprise-payment-woocommerce' ) . '</a>',
	];

	return array_merge( $plugin_links, $links );
}

// This action will register flagged order status in woocommerce.
add_action( 'init', 'register_upy_new_order_statuses' );

/**
 * Register flagged order status.
 */
function register_upy_new_order_statuses() {
	register_post_status(
		'wc-flagged',
		[
			'label'                     => _x( 'Suspected Fraud', 'Order status', 'uprise-payment-woocommerce' ),
			'public'                    => true,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: number of orders */
			'label_count'               => _n_noop( 'Suspected Fraud <span class="count">(%s)</span>', 'Suspected Frauds <span class="count">(%s)</span>', 'uprise-payment-woocommerce' ),
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
	$order_statuses['wc-flagged'] = _x( 'Suspected Fraud', 'Order status', 'uprise-payment-woocommerce' );

	return $order_statuses;
}

add_action( 'woocommerce_checkout_process', 'upy_check_if_empty' );

/**
 * Validate cvv on checkout page.
 */
function upy_check_if_empty() {
	if ( 'wc_uprise_payment_cards' === $_POST['payment_method'] ) {

		// Check if require cvv is enabled in module setting.
		if ( WC_Admin_Settings::get_option( 'upycom_card_saved' )
			&& WC_Admin_Settings::get_option( 'upycom_card_require_cvv' )
			&& 'new' !== $_POST['wc-wc_uprise_payment_cards-payment-token'] ) {
			// check if cvv is empty on checkout page.
			if ( empty( $_POST['wc_uprise_payment_cards-card-cvv'] ) ) {
				wc_add_notice( esc_html__( 'Please enter a valid cvv.', 'uprise-payment-woocommerce' ), 'error' );
			}
		}
	}
}

add_action( 'admin_enqueue_scripts', 'upy_admin_enqueue_scripts' );

/**
 * Load admin scripts.
 *
 * @return void
 */
function upy_admin_enqueue_scripts() {
	// Load admin scripts.
	wp_enqueue_script( 'cko-admin-script', WC_UPRISEPAY_PLUGIN_URL . '/assets/js/admin.js', [ 'jquery' ], WC_UPRISEPAY_PLUGIN_VERSION );

	$vars = [
		'nas_docs'                           => 'https://docs.uprisepay.com/',
		'abc_docs'                           => 'https://docs.uprisepay.com/',

		'webhook_check_error'                => esc_html__( 'An error occurred while fetching the webhooks. Please try again.', 'uprise-payment-woocommerce' ),
		'webhook_register_error'             => esc_html__( 'An error occurred while registering the webhook. Please try again.', 'uprise-payment-woocommerce' ),

		'uprisepay_check_webhook_nonce'    => wp_create_nonce( 'uprisepay_check_webhook' ),
		'uprisepay_register_webhook_nonce' => wp_create_nonce( 'uprisepay_register_webhook' ),
	];

	wp_localize_script( 'cko-admin-script', 'upy_admin_vars', $vars );
}

add_action( 'wp_enqueue_scripts', 'callback_for_setting_up_scripts' );

/**
 * Load Uprise style sheet.
 * Load Google Pay js.
 *
 * Only on Checkout related pages.
 */
function callback_for_setting_up_scripts() {

	// Load on Cart, Checkout, pay for order or add payment method pages.
	if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) && ! is_add_payment_method_page() ) {
		return;
	}

	// Register adn enqueue checkout css.
	wp_register_style( 'uprisepay-style', WC_UPRISEPAY_PLUGIN_URL . '/assets/css/uprisepay-styles.css', [], WC_UPRISEPAY_PLUGIN_VERSION );
	wp_register_style( 'normalize', WC_UPRISEPAY_PLUGIN_URL . '/assets/css/normalize.css', [], WC_UPRISEPAY_PLUGIN_VERSION );
	wp_enqueue_style( 'uprisepay-style' );
	wp_enqueue_style( 'normalize' );

	// load cko apm settings.
	$apm_settings = get_option( 'woocommerce_wc_uprise_payment_alternative_payments_settings' );
	$apm_enable   = ! empty( $apms_settings['enabled'] ) && 'yes' === $apms_settings['enabled'];

	if ( $apm_enable && ! empty( $apm_settings['upycom_apms_selector'] ) ) {
		foreach ( $apm_settings['upycom_apms_selector'] as $value ) {
			if ( 'klarna' === $value ) {
				wp_enqueue_script( 'cko-klarna-script', 'https://x.klarnacdn.net/kp/lib/v1/api.js', [ 'jquery' ] );
			}
		}
	}

}

add_action( 'woocommerce_order_item_add_action_buttons', 'action_woocommerce_order_item_add_action_buttons', 10, 1 );

/**
 * Add custom button to admin order.
 * Button capture and button void.
 *
 * @param WC_Order $order The order being edited.
 */
function action_woocommerce_order_item_add_action_buttons( $order ) {

	// Check order payment method is checkout.
	if ( false === strpos( $order->get_payment_method(), 'wc_uprise_payment_' ) ) {
		return;
	}

	$order_status   = $order->get_status();
	$auth_status    = str_replace( 'wc-', '', WC_Admin_Settings::get_option( 'upycom_order_authorised', 'on-hold' ) );
	$capture_status = str_replace( 'wc-', '', WC_Admin_Settings::get_option( 'upycom_order_captured', 'processing' ) );

	if ( $order->get_payment_method() === 'wc_uprise_payment_cards' || $order->get_payment_method() === 'wc_uprise_payment_google_pay' ) {
		?>

<script type="text/javascript">
	var ckoCustomButtonValues = {
		order_status: "<?php echo $order_status; ?>",
		auth_status: "<?php echo $auth_status; ?>",
		capture_status: "<?php echo $capture_status; ?>"
	}
</script>

<input type="hidden" value="" name="upy_payment_action" id="upy_payment_action" />
<button class="button" id="cko-capture" style="display:none;">Capture</button>
<button class="button" id="cko-void" style="display:none;">Void</button>
		<?php
	}
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
		if ( isset( $_POST['upy_payment_action'] ) && sanitize_text_field( $_POST['upy_payment_action'] ) ) {

			$order = wc_get_order( sanitize_text_field( $_POST['post_ID'] ) );

			WC_Admin_Notices::remove_notice( 'wc_uprise_payment_cards' );

			// check if post is capture.
			if ( 'cko-capture' === sanitize_text_field( $_POST['upy_payment_action'] ) ) {

				// send capture request to cko.
				$result = (array) WC_Uprisepay_Api_Request::capture_payment();

				if ( isset( $result['error'] ) && ! empty( $result['error'] ) ) {
					WC_Admin_Notices::add_custom_notice( 'wc_uprise_payment_cards', $result['error'] );

					return false;
				}

				// Set action id as woo transaction id.
				update_post_meta( sanitize_text_field( $_POST['post_ID'] ), '_transaction_id', $result['action_id'] );
				update_post_meta( sanitize_text_field( $_POST['post_ID'] ), 'upy_payment_captured', true );

				// Get cko capture status configured in admin.
				$status = WC_Admin_Settings::get_option( 'upycom_order_captured', 'processing' );

				/* translators: %s: Action id. */
				$message = sprintf( esc_html__( 'Uprise Payment Captured from Admin - Action ID : %s', 'uprise-payment-woocommerce' ), $result['action_id'] );

				// add notes for the order and update status.
				$order->add_order_note( $message );
				$order->update_status( $status );

				return true;

			} elseif ( 'cko-void' === sanitize_text_field( $_POST['upy_payment_action'] ) ) {
				// check if post is void.
				// send void request to cko.
				$result = (array) WC_Uprisepay_Api_Request::void_payment();

				if ( isset( $result['error'] ) && ! empty( $result['error'] ) ) {
					WC_Admin_Notices::add_custom_notice( 'wc_uprise_payment_cards', $result['error'] );

					return false;
				}

				// Set action id as woo transaction id.
				update_post_meta( sanitize_text_field( $_POST['post_ID'] ), '_transaction_id', $result['action_id'] );
				update_post_meta( sanitize_text_field( $_POST['post_ID'] ), 'upy_payment_voided', true );

				// Get cko capture status configured in admin.
				$status = WC_Admin_Settings::get_option( 'upycom_order_void', 'cancelled' );

				/* translators: %s: Action id. */
				$message = sprintf( esc_html__( 'Uprise Payment Voided from Admin - Action ID : %s', 'uprise-payment-woocommerce' ), $result['action_id'] );

				// add notes for the order and update status.
				$order->add_order_note( $message );
				$order->update_status( $status );

				// increase stock level.
				wc_increase_stock_levels( sanitize_text_field( $_POST['post_ID'] ) );

				return true;

			} else {
				WC_Admin_Notices::add_custom_notice( 'wc_uprise_payment_cards', esc_html__( 'An error has occurred.', 'uprise-payment-woocommerce' ) );

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
	$fawry_number = get_post_meta( $order_id, 'upy_fawry_reference_number', $single = true );
	$fawry        = __( 'Fawry reference number: ', 'uprise-payment-woocommerce' );
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

add_filter( 'woocommerce_gateway_icon', 'upy_gateway_icon', 10, 2 );

/**
 * Filter for custom gateway icons.
 *
 * @param string $icons Icons markup.
 * @param string $id Gateway ID.
 *
 * @return false|string|void
 */
function upy_gateway_icon( $icons, $id ) {

	$plugin_url = WC_UPRISEPAY_PLUGIN_URL . '/assets/images/';

	/* Check if uprise payment gateway */
	if ( 'wc_uprise_payment_cards' === $id ) {
		$display_card_icon = WC_Admin_Settings::get_option( 'upycom_display_icon', '0' ) === '1';

		/* check if display card option is selected */
		if ( $display_card_icon ) {
			$card_icon = WC_Admin_Settings::get_option( 'upycom_card_icons' );

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

		$apm_available = WC_Uprisepay_Utility::get_alternative_payment_methods();

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
function upy_is_nas_account() {

	$core_settings = get_option( 'woocommerce_wc_uprise_payment_cards_settings' );

	return isset( $core_settings['upycom_account_type'] ) && ( 'NAS' === $core_settings['upycom_account_type'] );
}

add_action( 'woocommerce_scheduled_subscription_payment_wc_uprise_payment_cards', 'subscription_payment', 10, 2 );
add_action( 'woocommerce_scheduled_subscription_payment_wc_uprise_payment_alternative_payments_sepa', 'subscription_payment', 10, 2 );
add_action( 'woocommerce_scheduled_subscription_payment_wc_uprise_payment_google_pay', 'subscription_payment', 10, 2 );
add_action( 'woocommerce_scheduled_subscription_payment_wc_uprise_payment_apple_pay', 'subscription_payment', 10, 2 );
add_action( 'woocommerce_scheduled_subscription_payment_wc_uprise_payment_paypal', 'subscription_payment', 10, 2 );

/**
 * Function to handle subscription renewal payment for card, SEPA APM, Google Pay & Apple Pay.
 *
 * @param float    $renewal_total The amount to charge.
 * @param WC_Order $renewal_order A WC_Order object created to record the renewal payment.
 */
function subscription_payment( $renewal_total, $renewal_order ) {
	include_once( 'includes/subscription/class-wc-uprisepay-subscription.php' );

	WC_Uprisepay_Subscription::renewal_payment( $renewal_total, $renewal_order );
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
	include_once( 'includes/subscription/class-wc-uprisepay-subscription.php' );

	WC_Uprisepay_Subscription::subscription_cancelled( $subscription );
}

// @TODO : Remove all below functions and logic once product is fixed.
if ( upy_is_nas_account() ) {
	add_filter( 'rewrite_rules_array', 'upy_add_rewrite_rules', -1 );
	add_filter( 'query_vars', 'upy_add_query_vars' );
	add_action( 'parse_request', 'upy_set_query_vars', -1, 1 );
}

/**
 * Add rewrite rules for NAS.
 *
 * @param array $rules Rules.
 *
 * @return array
 */
function upy_add_rewrite_rules( $rules ) {

	$new_rules = [];
	foreach ( $rules as $rule => $value ) {

		if ( '(.?.+?)(?:/([0-9]+))?/?$' === $rule ) {
			$new_rules['uprisepay-callback'] = 'index.php?&cko-callback=true';
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
function upy_add_query_vars( $vars ) {
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
function upy_set_query_vars( $wp ) {

	if ( ! empty( $wp->query_vars['cko-callback'] ) ) {
		$wp->set_query_var( 'wc-api', 'wc_uprisepay_callback' );
	}
}
