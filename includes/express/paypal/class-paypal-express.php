<?php
/**
 * PayPal Express handler.
 *
 * @package wc_checkout_com
 */

defined( 'ABSPATH' ) || exit;

class CKO_Paypal_Express {

	private static $instance = null;

	public static function get_instance(): CKO_Paypal_Express {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {

		add_action( 'woocommerce_after_add_to_cart_form', [ $this, 'display_payment_request_button_html' ], 1 );

		add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );

		add_filter( 'woocommerce_available_payment_gateways', [ $this, 'disable_other_gateways' ] );

		add_action( 'woocommerce_review_order_after_submit', [ $this, 'cancel_paypal_session_markup' ] );

        add_action( 'woocommerce_init', [ $this, 'express_cancel_session' ] );
	}

    public function payment_scripts() {

	    // Load on Cart, Checkout, pay for order or add payment method pages.
	    if ( ! is_product() || ! WC_Checkoutcom_Utility::is_paypal_express_available() ) {
		    return;
	    }

	    $core_settings   = get_option( 'woocommerce_wc_checkout_com_cards_settings' );
	    $paypal_settings = get_option( 'woocommerce_wc_checkout_com_paypal_settings' );
	    $environment     = 'sandbox' === $core_settings['ckocom_environment'];

	    if ( $environment ) {
		    // sandbox.
		    $paypal_js_arg['client-id'] = 'ASLqLf4pnWuBshW8Qh8z_DRUbIv2Cgs3Ft8aauLm9Z-MO9FZx1INSo38nW109o_Xvu88P3tly88XbJMR';
	    } else {
		    // live.
		    $paypal_js_arg['client-id'] = 'ATbi1ysGm-jp4RmmAFz1EWH4dFpPd-VdXIWWzR4QZK5LAvDu_5atDY9dsUEJcLS5mTpR8Wb1l_m6Ameq';
	    }

	    $paypal_js_arg['merchant-id'] = $paypal_settings[ 'ckocom_paypal_merchant_id' ] ?? '';

	    $paypal_js_arg['disable-funding'] = 'credit,card,sepa';
	    $paypal_js_arg['commit']          = 'false';
	    $paypal_js_arg['currency']        = get_woocommerce_currency();
	    $paypal_js_arg['intent']          = 'capture'; // 'authorize' // ???

	    if ( WC_Checkoutcom_Utility::is_cart_contains_subscription() ) {
		    $paypal_js_arg['intent'] = 'tokenize';
		    $paypal_js_arg['vault']  = 'true';
	    }

	    $paypal_js_url = add_query_arg( $paypal_js_arg, 'https://www.paypal.com/sdk/js' );

	    wp_register_script( 'cko-paypal-script', $paypal_js_url, [ 'jquery' ], null );

	    $vars = [
		    'add_to_cart_url'               => add_query_arg( [ 'cko_paypal_action' => 'express_add_to_cart'], WC()->api_request_url( 'CKO_Paypal_Woocommerce' ) ),
		    'create_order_url'              => add_query_arg( [ 'cko_paypal_action' => 'express_create_order'], WC()->api_request_url( 'CKO_Paypal_Woocommerce' ) ),
		    'paypal_order_session_url'      => add_query_arg( [ 'cko_paypal_action' => 'express_paypal_order_session'], WC()->api_request_url( 'CKO_Paypal_Woocommerce' ) ),
		    'cc_capture'                    => add_query_arg( [ 'cko_paypal_action' => 'cc_capture' ], WC()->api_request_url( 'CKO_Paypal_Woocommerce' ) ),
		    'woocommerce_process_checkout'  => wp_create_nonce('woocommerce-process_checkout'),
		    'is_cart_contains_subscription' => WC_Checkoutcom_Utility::is_cart_contains_subscription(),
		    'paypal_button_selector'        => '#cko-paypal-button-wrapper',
		    'redirect'                      => wc_get_checkout_url(),
	    ];

	    wp_localize_script( 'cko-paypal-script', 'cko_paypal_vars', $vars );

	    wp_enqueue_script( 'cko-paypal-script' );

	    wp_register_script(
		    'cko-paypal-express-integration-script',
		    WC_CHECKOUTCOM_PLUGIN_URL . '/assets/js/cko-paypal-express-integration.js',
		    [ 'jquery', 'cko-paypal-script' ],
		    WC_CHECKOUTCOM_PLUGIN_VERSION
	    );

	    wp_enqueue_script( 'cko-paypal-express-integration-script' );
    }

	public function display_payment_request_button_html() {

		if ( ! is_product() || ! WC_Checkoutcom_Utility::is_paypal_express_available() ) {
			return;
		}

		?>
		<div id="cko-paypal-button-wrapper" style="margin-top: 1em;clear:both;display:none;"></div>
		<?php
	}

    public function disable_other_gateways( array $methods ) {

	    if ( ! isset( $methods[ 'wc_checkout_com_paypal' ] ) ) {
		    return $methods;
	    }

	    $cko_paypal_order_id = WC_Checkoutcom_Utility::cko_get_session( 'cko_paypal_order_id' );
	    $cko_pc_id           = WC_Checkoutcom_Utility::cko_get_session( 'cko_pc_id' );

	    // Check if PayPal session variable exist for current customer.
	    $disable_all_gateway = ! empty( $cko_pc_id ) && ! empty( $cko_paypal_order_id );

        if ( $disable_all_gateway ) {
            return [ 'wc_checkout_com_paypal' => $methods[ 'wc_checkout_com_paypal' ] ];
        }

        return $methods;
    }

    public function cancel_paypal_session_markup() {
	    $cko_paypal_order_id = WC_Checkoutcom_Utility::cko_get_session( 'cko_paypal_order_id' );
	    $cko_pc_id           = WC_Checkoutcom_Utility::cko_get_session( 'cko_pc_id' );

	    // Check if PayPal session variable exist for current customer.
	    $paypal_session_exist = ! empty( $cko_pc_id ) && ! empty( $cko_paypal_order_id );

	    $cancel_url = add_query_arg( [ 'cko-paypal-session-cancel' => '1' ], wc_get_checkout_url() );

        if ( ! $paypal_session_exist ) {
            return;
        }

        ob_start();
        ?>
        <p id="cko-paypal-cancel" class="has-text-align-center">
	    <?php

	    printf(
	    // translators: %3$ is funding source like "PayPal" or "Venmo", other placeholders are html tags for a link.
		    esc_html__(
			    'You are currently paying with PayPal. %1$s%2$sChoose another payment method%3$s.',
			    'woocommerce-paypal-payments'
		    ),
		    '<br/>',
		    '<a href="' . esc_url( $cancel_url ) . '">',
		    '</a>',
	    );

        ?></p><?php
	    echo ob_get_clean();
    }

	public function express_cancel_session() {
		// TODO: Nonce;

		if ( isset( $_GET[ 'cko-paypal-session-cancel' ] ) ) {
			WC_Checkoutcom_Utility::cko_set_session( 'cko_paypal_order_id', '' );
			WC_Checkoutcom_Utility::cko_set_session( 'cko_pc_id', '' );
        }
	}
}

CKO_Paypal_Express::get_instance();