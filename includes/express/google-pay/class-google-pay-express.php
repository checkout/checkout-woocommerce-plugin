<?php
/**
 * Google Pay Express Checkout class.
 *
 * @package wc_checkout_com
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CKO_Google_Pay_Express for Google Pay Express checkout.
 */
class CKO_Google_Pay_Express {

	private static $instance = null;

	public static function get_instance(): CKO_Google_Pay_Express {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * CKO_Google_Pay_Express constructor.
	 */
	public function __construct() {
		$google_pay_settings = get_option( 'woocommerce_wc_checkout_com_google_pay_settings' );

		$is_express_enable = ! empty( $google_pay_settings['google_pay_express'] ) && 'yes' === $google_pay_settings['google_pay_express'];
		$google_pay_enabled = ! empty( $google_pay_settings['enabled'] ) && 'yes' === $google_pay_settings['enabled'];

		$checkout_setting = get_option( 'woocommerce_wc_checkout_com_cards_settings' );
		$checkout_mode    = $checkout_setting['ckocom_checkout_mode'];

		if ( ! $google_pay_enabled || ! $is_express_enable ) {
			if ( $checkout_mode === 'classic' ) {
				return;
			}
		}

		// Add hooks for Google Pay Express.
		add_action( 'woocommerce_after_add_to_cart_form', [ $this, 'display_payment_request_button_html' ], 1 );

		add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );

		/**
		 * Filters.
		 */
		add_filter( 'woocommerce_checkout_get_value', [ $this, 'fill_google_pay_selected_address_field' ], 10, 2 );

		// Add dynamic Google Pay enablement for FLOW mode.
		$this->maybe_enable_google_pay_in_flow_mode();
	}

	/**
	 * Check if Express Checkout session exists.
	 *
	 * @return bool
	 */
	private function has_express_checkout_session(): bool {
		$cko_google_pay_order_id = WC_Checkoutcom_Utility::cko_get_session( 'cko_google_pay_order_id' );
		$cko_gc_id               = WC_Checkoutcom_Utility::cko_get_session( 'cko_gc_id' );

		return ( ! empty( $cko_gc_id ) && ! empty( $cko_google_pay_order_id ) );
	}

	/**
	 * Dynamically enable Google Pay in FLOW mode if session exists.
	 */
	private function maybe_enable_google_pay_in_flow_mode() {

		$checkout_setting = get_option( 'woocommerce_wc_checkout_com_cards_settings' );
		$checkout_mode    = $checkout_setting['ckocom_checkout_mode'] ?? '';

		// Run only for FLOW mode.
		if ( 'flow' !== $checkout_mode ) {
			return;
		}

		// Add hooks for FLOW Google Pay enablement.
		add_filter( 'woocommerce_payment_gateways', [ $this, 'add_google_pay_gateway' ] );
		add_filter( 'option_woocommerce_wc_checkout_com_google_pay_settings', [ $this, 'force_enable_google_pay_settings' ] );
		add_filter( 'woocommerce_available_payment_gateways', [ $this, 'restrict_gateways_to_google_pay' ] );
	}

	/**
	 * Step 1: Add Google Pay gateway dynamically.
	 *
	 * @param array $gateways
	 * @return array
	 */
	public function add_google_pay_gateway( $gateways ) {
		if ( $this->has_express_checkout_session() ) {
			if ( ! in_array( 'WC_Gateway_Checkout_Com_Google_Pay', $gateways, true ) ) {
				$gateways[] = 'WC_Gateway_Checkout_Com_Google_Pay';
			}
		}
		return $gateways;
	}

	/**
	 * Step 2: Force-enable Google Pay settings.
	 *
	 * @param array $settings
	 * @return array
	 */
	public function force_enable_google_pay_settings( $settings ) {
		if ( $this->has_express_checkout_session() ) {
			$settings['enabled'] = 'yes';
		}
		return $settings;
	}

	/**
	 * Step 3: Restrict available payment gateways to Google Pay only.
	 *
	 * @param array $methods
	 * @return array
	 */
	public function restrict_gateways_to_google_pay( $methods ) {
		if ( $this->has_express_checkout_session() ) {
			return [ 'wc_checkout_com_google_pay' => $methods['wc_checkout_com_google_pay'] ];
		}
		return $methods;
	}

	/**
	 * Load Google Pay Express scripts.
	 */
	public function payment_scripts() {

		// Load on Cart, Checkout, pay for order or add payment method pages.
		if ( ! is_product() || ! WC_Checkoutcom_Utility::is_google_pay_express_available() ) {
			return;
		}

		$core_settings     = get_option( 'woocommerce_wc_checkout_com_cards_settings' );
		$google_pay_settings = get_option( 'woocommerce_wc_checkout_com_google_pay_settings' );

		$environment = 'sandbox' === $core_settings['ckocom_environment'] ? 'TEST' : 'PRODUCTION';

		$google_pay_js_url = 'https://pay.google.com/gp/p/js/pay.js';

		wp_register_script( 'cko-google-pay-script', $google_pay_js_url, [ 'jquery' ], null );

		$vars = [
			'direct_payment_url'                  => add_query_arg( [ 'cko_google_pay_action' => 'direct_payment' ], WC()->api_request_url( 'CKO_Google_Pay_Woocommerce' ) ),
			'woocommerce_process_checkout'        => wp_create_nonce( 'woocommerce-process_checkout' ),
			'google_pay_button_selector'          => '#cko-google-pay-button-wrapper',
			'debug'                               => 'yes' === WC_Admin_Settings::get_option( 'cko_console_logging', 'no' ),
			'environment'                         => $environment,
			'merchant_id'                         => $google_pay_settings['ckocom_google_merchant_id'] ?? '',
			'merchant_name'                       => get_bloginfo( 'name' ),
			'currency_code'                       => get_woocommerce_currency(),
		];

		wp_localize_script( 'cko-google-pay-script', 'cko_google_pay_vars', $vars );

		wp_enqueue_script( 'cko-google-pay-script' );

		wp_register_script(
			'cko-google-pay-express-integration-script',
			WC_CHECKOUTCOM_PLUGIN_URL . '/assets/js/cko-google-pay-express-integration.js',
			[ 'jquery', 'cko-google-pay-script' ],
			WC_CHECKOUTCOM_PLUGIN_VERSION
		);

		wp_enqueue_script( 'cko-google-pay-express-integration-script' );
	}

	/**
	 * Display Google Pay Express button HTML.
	 */
	public function display_payment_request_button_html() {

		if ( ! is_product() || ! WC_Checkoutcom_Utility::is_google_pay_express_available() ) {
			return;
		}

		?>
		<style>
			.cko-disabled {
				cursor: not-allowed;
				-webkit-filter: grayscale(100%);
				filter: grayscale(100%);
			}
		</style>
		<div id="cko-google-pay-button-wrapper" style="margin-top: 1em;clear:both;display:none;"></div>
		<?php
	}

	/**
	 * Disable other gateways when Google Pay Express session exists.
	 *
	 * @param array $methods
	 * @return array
	 */
	public function disable_other_gateways( array $methods ) {

		if ( ! isset( $methods['wc_checkout_com_google_pay'] ) ) {
			return $methods;
		}

		$cko_google_pay_order_id = WC_Checkoutcom_Utility::cko_get_session( 'cko_google_pay_order_id' );
		$cko_gc_id               = WC_Checkoutcom_Utility::cko_get_session( 'cko_gc_id' );

		// Check if Google Pay session variable exist for current customer.
		$disable_all_gateway = ! empty( $cko_gc_id ) && ! empty( $cko_google_pay_order_id );

		if ( $disable_all_gateway ) {
			return [ 'wc_checkout_com_google_pay' => $methods['wc_checkout_com_google_pay'] ];
		}

		return $methods;
	}

	/**
	 * Cancel Google Pay session markup.
	 */
	public function cancel_google_pay_session_markup() {
		$cko_google_pay_order_id = WC_Checkoutcom_Utility::cko_get_session( 'cko_google_pay_order_id' );
		$cko_gc_id               = WC_Checkoutcom_Utility::cko_get_session( 'cko_gc_id' );

		// Check if Google Pay session variable exist for current customer.
		$google_pay_session_exist = ! empty( $cko_gc_id ) && ! empty( $cko_google_pay_order_id );

		$cancel_url = add_query_arg(
			[
				'cko-google-pay-session-cancel'       => '1',
				'cko-google-pay-session-cancel-nonce' => wp_create_nonce( 'checkoutcom_google_pay_cancel' ),
			],
			wc_get_checkout_url()
		);

		if ( $google_pay_session_exist ) {
			?>
			<div class="woocommerce-info">
				<?php
				/* translators: %3$ is funding source like "Google Pay", other placeholders are html tags for a link. */
				printf(
					esc_html__( 'You are currently paying with Google Pay. %1$s%2$sChoose another payment method%3$s.', 'checkout-com-unified-payments-api' ),
					'<a href="' . esc_url( $cancel_url ) . '">',
					'<strong>',
					'</strong></a>'
				);
				?>
			</div>
			<?php
		}
	}

	/**
	 * Fill Google Pay selected address field.
	 *
	 * @param string $value
	 * @param string $input
	 * @return string
	 */
	public function fill_google_pay_selected_address_field( $value, $input ) {
		$cko_google_pay_order_id = WC_Checkoutcom_Utility::cko_get_session( 'cko_google_pay_order_id' );
		$cko_gc_id               = WC_Checkoutcom_Utility::cko_get_session( 'cko_gc_id' );

		// Check if Google Pay session variable exist for current customer.
		$google_pay_session_exist = ! empty( $cko_gc_id ) && ! empty( $cko_google_pay_order_id );

		if ( $google_pay_session_exist ) {
			$google_pay_data = WC_Checkoutcom_Utility::cko_get_session( 'cko_google_pay_data' );

			if ( ! empty( $google_pay_data ) ) {
				$google_pay_data = json_decode( $google_pay_data, true );

				if ( ! empty( $google_pay_data['shippingAddress'] ) ) {
					$shipping_address = $google_pay_data['shippingAddress'];

					switch ( $input ) {
						case 'billing_first_name':
							return $shipping_address['name'] ?? '';
						case 'billing_last_name':
							return '';
						case 'billing_address_1':
							return $shipping_address['address1'] ?? '';
						case 'billing_address_2':
							return $shipping_address['address2'] ?? '';
						case 'billing_city':
							return $shipping_address['locality'] ?? '';
						case 'billing_state':
							return $shipping_address['administrativeAreaLevel1'] ?? '';
						case 'billing_postcode':
							return $shipping_address['postalCode'] ?? '';
						case 'billing_country':
							return $shipping_address['countryCode'] ?? '';
						case 'billing_phone':
							return $shipping_address['phoneNumber'] ?? '';
						case 'billing_email':
							return $google_pay_data['email'] ?? '';
					}
				}
			}
		}

		return $value;
	}
}
