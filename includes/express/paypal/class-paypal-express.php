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
		$paypal_settings = get_option( 'woocommerce_wc_checkout_com_paypal_settings', array() );

		// Check if master toggle is enabled - must be explicitly 'yes'
		// Handle cases: not set, empty string, false, 'no', or anything else = disabled
		$is_express_enable = isset( $paypal_settings['paypal_express'] ) 
			&& 'yes' === $paypal_settings['paypal_express']
			&& ! empty( $paypal_settings['paypal_express'] );
		$paypal_enabled    = ! empty( $paypal_settings['enabled'] ) && 'yes' === $paypal_settings['enabled'];

		$checkout_setting = get_option( 'woocommerce_wc_checkout_com_cards_settings', array() );
		$checkout_mode    = isset( $checkout_setting['ckocom_checkout_mode'] ) ? $checkout_setting['ckocom_checkout_mode'] : 'classic';

		// If Express is disabled, don't add any hooks (regardless of mode)
		if ( ! $is_express_enable ) {
			return;
		}

		// For classic mode, also check if PayPal gateway is enabled
		if ( $checkout_mode === 'classic' && ! $paypal_enabled ) {
			return;
		}

		add_action( 'woocommerce_after_add_to_cart_form', [ $this, 'display_payment_request_button_html' ], 1 );
		
		// Shop page rendering is now handled by WC_Checkoutcom_Express_Checkout_Element
		// Keep this hook commented out to prevent duplicate rendering
		// add_action( 'woocommerce_after_shop_loop_item', [ $this, 'display_shop_payment_request_button_html' ], 15 );
		
		// Add PayPal Express button to cart page (classic cart)
		add_action( 'woocommerce_proceed_to_checkout', [ $this, 'display_cart_payment_request_button_html' ], 5 );
		
		// Add PayPal Express button to Blocks cart page
		add_action( 'woocommerce_blocks_cart_block_render', [ $this, 'display_cart_payment_request_button_html' ], 10 );
		
		// Add PayPal Express button after cart table (fallback for Blocks)
		add_action( 'woocommerce_after_cart_table', [ $this, 'display_cart_payment_request_button_html' ], 5 );
		
		// Add PayPal Express button in cart collaterals (another fallback)
		add_action( 'woocommerce_cart_collaterals', [ $this, 'display_cart_payment_request_button_html' ], 15 );

		add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );

		add_filter( 'woocommerce_available_payment_gateways', [ $this, 'disable_other_gateways' ] );

		add_action( 'woocommerce_review_order_after_submit', [ $this, 'cancel_paypal_session_markup' ] );

		add_action( 'woocommerce_init', [ $this, 'express_cancel_session' ] );

		// Clear PayPal session if any of the below action run.
		add_action( 'woocommerce_cart_emptied', [ $this, 'empty_paypal_session' ], 1 );
		add_action( 'woocommerce_cart_item_removed', [ $this, 'empty_paypal_session' ], 1 );
		add_action( 'woocommerce_update_cart_action_cart_updated', [ $this, 'empty_paypal_session' ], 1 );
		add_action( 'woocommerce_cart_item_set_quantity', [ $this, 'empty_paypal_session' ], 1 );
		add_action( 'woocommerce_add_to_cart', [ $this, 'empty_paypal_session' ], 1 );

		/**
		 * Filters.
		 */
		add_filter( 'woocommerce_checkout_get_value', [ $this, 'fill_paypal_selected_address_field' ], 10, 2 );

		// Add dynamic PayPal enablement for FLOW mode.
		$this->maybe_enable_paypal_in_flow_mode();
	}

	/**
	 * Check if Express Checkout session exists.
	 *
	 * @return bool
	 */
	private function has_express_checkout_session(): bool {
		$cko_paypal_order_id = WC_Checkoutcom_Utility::cko_get_session( 'cko_paypal_order_id' );
		$cko_pc_id           = WC_Checkoutcom_Utility::cko_get_session( 'cko_pc_id' );

		return ( ! empty( $cko_pc_id ) && ! empty( $cko_paypal_order_id ) );
	}

	/**
	 * Dynamically enable PayPal in FLOW mode if session exists.
	 */
	private function maybe_enable_paypal_in_flow_mode() {

		$checkout_setting = get_option( 'woocommerce_wc_checkout_com_cards_settings' );
		$checkout_mode    = $checkout_setting['ckocom_checkout_mode'] ?? '';

		// Run only for FLOW mode.
		if ( 'flow' !== $checkout_mode ) {
			return;
		}

		// Add hooks for FLOW PayPal enablement.
		add_filter( 'woocommerce_payment_gateways', [ $this, 'add_paypal_gateway' ] );
		add_filter( 'option_woocommerce_wc_checkout_com_paypal_settings', [ $this, 'force_enable_paypal_settings' ] );
		add_filter( 'woocommerce_available_payment_gateways', [ $this, 'restrict_gateways_to_paypal' ] );
	}

	/**
	 * Step 1: Add PayPal gateway dynamically.
	 *
	 * @param array $gateways
	 * @return array
	 */
	public function add_paypal_gateway( $gateways ) {
		if ( $this->has_express_checkout_session() ) {
			if ( ! in_array( 'WC_Gateway_Checkout_Com_PayPal', $gateways, true ) ) {
				$gateways[] = 'WC_Gateway_Checkout_Com_PayPal';
			}
		}
		return $gateways;
	}

	/**
	 * Step 2: Force-enable PayPal settings.
	 *
	 * @param array $settings
	 * @return array
	 */
	public function force_enable_paypal_settings( $settings ) {
		if ( $this->has_express_checkout_session() ) {
			$settings['enabled'] = 'yes';
		}
		return $settings;
	}

	/**
	 * Step 3: Restrict available payment gateways to PayPal only.
	 *
	 * @param array $methods
	 * @return array
	 */
	public function restrict_gateways_to_paypal( $methods ) {
		if ( $this->has_express_checkout_session() && isset( $methods['wc_checkout_com_paypal'] ) ) {
			return [ 'wc_checkout_com_paypal' => $methods['wc_checkout_com_paypal'] ];
		}
		return $methods;
	}

	public function payment_scripts() {
		$paypal_settings = get_option( 'woocommerce_wc_checkout_com_paypal_settings', array() );

		// First check if master toggle is enabled - must be explicitly 'yes'
		// Handle cases: not set, empty string, false, 'no', or anything else = disabled
		$is_express_enabled = isset( $paypal_settings['paypal_express'] ) 
			&& 'yes' === $paypal_settings['paypal_express']
			&& ! empty( $paypal_settings['paypal_express'] );
		if ( ! $is_express_enabled ) {
			return;
		}

		// Load on Product, Cart, Shop, or Checkout pages if PayPal Express is available.
		if ( ! WC_Checkoutcom_Utility::is_paypal_express_available() ) {
			return;
		}

		// Check which pages should load scripts - must be explicitly 'yes'
		// Handle cases: not set (default enabled), empty string, false, 'no' = disabled
		$show_on_product = false;
		if ( ! isset( $paypal_settings['paypal_express_product_page'] ) ) {
			$show_on_product = true; // Default to yes if setting doesn't exist (backward compatibility)
		} elseif ( isset( $paypal_settings['paypal_express_product_page'] ) 
			&& 'yes' === $paypal_settings['paypal_express_product_page']
			&& ! empty( $paypal_settings['paypal_express_product_page'] ) ) {
			$show_on_product = true;
		}
		
		$show_on_shop = false;
		if ( ! isset( $paypal_settings['paypal_express_shop_page'] ) ) {
			$show_on_shop = true; // Default to yes if setting doesn't exist (backward compatibility)
		} elseif ( isset( $paypal_settings['paypal_express_shop_page'] ) 
			&& 'yes' === $paypal_settings['paypal_express_shop_page']
			&& ! empty( $paypal_settings['paypal_express_shop_page'] ) ) {
			$show_on_shop = true;
		}
		
		$show_on_cart = false;
		if ( ! isset( $paypal_settings['paypal_express_cart_page'] ) ) {
			$show_on_cart = true; // Default to yes if setting doesn't exist (backward compatibility)
		} elseif ( isset( $paypal_settings['paypal_express_cart_page'] ) 
			&& 'yes' === $paypal_settings['paypal_express_cart_page']
			&& ! empty( $paypal_settings['paypal_express_cart_page'] ) ) {
			$show_on_cart = true;
		}

		// Only load on relevant pages where Express is enabled
		$should_load = false;
		
		if ( ( is_product() && $show_on_product ) ||
		     ( is_cart() && $show_on_cart ) ||
		     ( ( is_shop() || is_product_category() || is_product_tag() || is_product_taxonomy() ) && $show_on_shop ) ) {
			$should_load = true;
		}

		if ( ! $should_load ) {
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

		// Always add merchant-id if available (PayPal requires it to match payee in transaction)
		// PayPal SDK validation error indicates payee mismatch when merchant-id is missing
		$merchant_id = $paypal_settings['ckocom_paypal_merchant_id'] ?? '';
		if ( ! empty( $merchant_id ) ) {
			$paypal_js_arg['merchant-id'] = $merchant_id;
		}

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

		// Get base API URL and validate it
		$base_api_url = WC()->api_request_url( 'CKO_Paypal_Woocommerce' );
		
		// Ensure base API URL is valid
		if ( empty( $base_api_url ) || ! filter_var( $base_api_url, FILTER_VALIDATE_URL ) ) {
			WC_Checkoutcom_Utility::logger( 'PayPal Express: Warning - API request URL is empty or invalid. Check if CKO_Paypal_Woocommerce API route is registered.' );
			$base_api_url = home_url( '/wc-api/CKO_Paypal_Woocommerce/' );
		}
		
		// Ensure base URL ends with a trailing slash for proper query string handling
		$base_api_url = trailingslashit( $base_api_url );

		$vars = [
			'add_to_cart_url'                  => add_query_arg( [ 'cko_paypal_action' => 'express_add_to_cart' ], $base_api_url ),
			'create_order_url'                 => add_query_arg( [ 'cko_paypal_action' => 'express_create_order' ], $base_api_url ),
			'paypal_order_session_url'         => add_query_arg( [ 'cko_paypal_action' => 'express_paypal_order_session' ], $base_api_url ),
			'cc_capture'                       => add_query_arg( [ 'cko_paypal_action' => 'cc_capture' ], $base_api_url ),
			'woocommerce_process_checkout'     => wp_create_nonce( 'woocommerce-process_checkout' ),
			'is_cart_contains_subscription'    => WC_Checkoutcom_Utility::is_cart_contains_subscription(),
			'paypal_button_selector'           => '#cko-paypal-button-wrapper',
			'redirect'                         => wc_get_checkout_url(),
			'paypal_express_add_to_cart_nonce' => wp_create_nonce( 'checkoutcom_paypal_express_add_to_cart' ),
			'debug'                            => 'yes' === WC_Admin_Settings::get_option( 'cko_console_logging', 'no' ),
		];

		wp_localize_script( 'cko-paypal-script', 'cko_paypal_vars', $vars );

		wp_enqueue_script( 'cko-paypal-script' );

		wp_register_script(
			'cko-paypal-express-integration-script',
			WC_CHECKOUTCOM_PLUGIN_URL . '/assets/js/cko-paypal-express-integration.js',
			[ 'jquery', 'cko-paypal-script' ],
			WC_CHECKOUTCOM_PLUGIN_VERSION
		);
		
		// Also localize on express integration script to ensure variables are available
		wp_localize_script( 'cko-paypal-express-integration-script', 'cko_paypal_vars', $vars );

		wp_enqueue_script( 'cko-paypal-express-integration-script' );
	}

	public function display_payment_request_button_html() {
		static $container_rendered = false;
		
		$paypal_settings = get_option( 'woocommerce_wc_checkout_com_paypal_settings', array() );
		
		// First check if master toggle is enabled - must be explicitly 'yes'
		// Handle cases: not set, empty string, false, 'no', or anything else = disabled
		$is_express_enabled = isset( $paypal_settings['paypal_express'] ) 
			&& 'yes' === $paypal_settings['paypal_express']
			&& ! empty( $paypal_settings['paypal_express'] );
		if ( ! $is_express_enabled ) {
			return;
		}
		
		// Check if PayPal Express is available (gateway check)
		if ( ! WC_Checkoutcom_Utility::is_paypal_express_available() ) {
			return;
		}
		
		// Check if PayPal Express is enabled for product pages - must be explicitly 'yes'
		// Handle cases: not set (default enabled), empty string, false, 'no' = disabled
		$show_on_product = false;
		if ( ! isset( $paypal_settings['paypal_express_product_page'] ) ) {
			// Default to yes if setting doesn't exist (backward compatibility)
			$show_on_product = true;
		} elseif ( isset( $paypal_settings['paypal_express_product_page'] ) 
			&& 'yes' === $paypal_settings['paypal_express_product_page']
			&& ! empty( $paypal_settings['paypal_express_product_page'] ) ) {
			$show_on_product = true;
		}

		if ( ! is_product() || ! $show_on_product ) {
			return;
		}

		// Check if unified express checkout container should be rendered
		$google_pay_settings = get_option( 'woocommerce_wc_checkout_com_google_pay_settings', array() );
		$apple_pay_settings = get_option( 'woocommerce_wc_checkout_com_apple_pay_settings', array() );
		
		$google_express_enabled = isset( $google_pay_settings['google_pay_express'] ) 
			&& 'yes' === $google_pay_settings['google_pay_express']
			&& ! empty( $google_pay_settings['google_pay_express'] );
		$google_available = WC_Checkoutcom_Utility::is_google_pay_express_available();
		$google_show_on_product = ! isset( $google_pay_settings['google_pay_express_product_page'] ) 
			|| ( isset( $google_pay_settings['google_pay_express_product_page'] ) 
				&& 'yes' === $google_pay_settings['google_pay_express_product_page']
				&& ! empty( $google_pay_settings['google_pay_express_product_page'] ) );
		
		$apple_pay_express_enabled = isset( $apple_pay_settings['apple_pay_express'] ) 
			&& 'yes' === $apple_pay_settings['apple_pay_express']
			&& ! empty( $apple_pay_settings['apple_pay_express'] );
		$apple_pay_available = WC_Checkoutcom_Utility::is_apple_pay_express_available();
		$apple_pay_show_on_product = ! isset( $apple_pay_settings['apple_pay_express_product_page'] ) 
			|| ( isset( $apple_pay_settings['apple_pay_express_product_page'] ) 
				&& 'yes' === $apple_pay_settings['apple_pay_express_product_page']
				&& ! empty( $apple_pay_settings['apple_pay_express_product_page'] ) );
		
		// Check if unified container has been rendered
		$unified_container_rendered = WC_Checkoutcom_Utility::express_checkout_container_rendered();
		
		// Determine if we should render unified container
		$should_render_container = ! $unified_container_rendered && (
			( $google_express_enabled && $google_available && $google_show_on_product ) ||
			( $apple_pay_express_enabled && $apple_pay_available && $apple_pay_show_on_product )
		);
		
		// Render unified container only once if both are enabled, or PayPal's own container
		if ( $should_render_container ) {
			WC_Checkoutcom_Utility::express_checkout_container_rendered( true );
			$container_rendered = true;
			?>
			<div class="cko-express-checkout-container">
				<div class="cko-express-checkout-buttons">
					<?php if ( $apple_pay_express_enabled && $apple_pay_available && $apple_pay_show_on_product ) : ?>
						<div id="cko-apple-pay-button-wrapper"></div>
					<?php endif; ?>
					<?php if ( $google_express_enabled && $google_available && $google_show_on_product ) : ?>
						<div id="cko-google-pay-button-wrapper" style="display: block;"></div>
					<?php endif; ?>
					<div id="cko-paypal-button-wrapper"></div>
				</div>
			</div>
			<?php
		} elseif ( ! $container_rendered && ! WC_Checkoutcom_Utility::express_checkout_container_rendered() ) {
			// Only PayPal is enabled, render PayPal's own container (only if unified container wasn't already rendered)
			$container_rendered = true;
			?>
			<div class="cko-paypal-product-button">
				<div id="cko-paypal-button-wrapper"></div>
			</div>
			<?php
		} else {
			// Unified container already rendered by another express method, add PayPal button via JavaScript
			?>
			<script>
			(function() {
				var buttonsContainer = document.querySelector('.cko-express-checkout-buttons');
				if (buttonsContainer && !document.getElementById('cko-paypal-button-wrapper')) {
					var paypalWrapper = document.createElement('div');
					paypalWrapper.id = 'cko-paypal-button-wrapper';
					// Insert at the end to maintain Apple Pay -> Google Pay -> PayPal order
					buttonsContainer.appendChild(paypalWrapper);
				}
			})();
			</script>
			<?php
		}
	}

	/**
	 * Display PayPal Express button on shop/listing pages
	 */
	public function display_shop_payment_request_button_html() {
		global $product;

		$paypal_settings = get_option( 'woocommerce_wc_checkout_com_paypal_settings', array() );
		
		// First check if master toggle is enabled - must be explicitly 'yes'
		// Handle cases: not set, empty string, false, 'no', or anything else = disabled
		$is_express_enabled = isset( $paypal_settings['paypal_express'] ) 
			&& 'yes' === $paypal_settings['paypal_express']
			&& ! empty( $paypal_settings['paypal_express'] );
		if ( ! $is_express_enabled ) {
			return;
		}

		// Check if PayPal Express is available (gateway check)
		$is_available = WC_Checkoutcom_Utility::is_paypal_express_available();
		if ( ! $is_available ) {
			return;
		}
		
		// Check if PayPal Express is enabled for shop pages - must be explicitly 'yes'
		// Handle cases: not set (default enabled), empty string, false, 'no' = disabled
		$show_on_shop = false;
		if ( ! isset( $paypal_settings['paypal_express_shop_page'] ) ) {
			// Default to yes if setting doesn't exist (backward compatibility)
			$show_on_shop = true;
		} elseif ( isset( $paypal_settings['paypal_express_shop_page'] ) 
			&& 'yes' === $paypal_settings['paypal_express_shop_page']
			&& ! empty( $paypal_settings['paypal_express_shop_page'] ) ) {
			$show_on_shop = true;
		}
		
		// Only show on shop pages and if PayPal Express is enabled for shop pages
		if ( ! ( is_shop() || is_product_category() || is_product_tag() || is_product_taxonomy() ) || ! $show_on_shop ) {
			return;
		}

		// Don't show for variable products on shop pages (too complex)
		if ( $product && $product->is_type( 'variable' ) ) {
			return;
		}

		$product_id = $product ? $product->get_id() : 0;
		?>
		<style>
			.cko-paypal-shop-button .cko-disabled {
				cursor: not-allowed;
				-webkit-filter: grayscale(100%);
				filter: grayscale(100%);
			}
			.cko-paypal-shop-button #cko-paypal-button-wrapper-<?php echo esc_attr( $product_id ); ?> {
				display: none;
			}
		</style>
		<div class="cko-paypal-shop-button">
			<div id="cko-paypal-button-wrapper-<?php echo esc_attr( $product_id ); ?>" data-product-id="<?php echo esc_attr( $product_id ); ?>"></div>
		</div>
		<?php
	}

	/**
	 * Display PayPal Express button on cart page
	 */
	public function display_cart_payment_request_button_html() {
		static $container_rendered = false;
		
		$paypal_settings = get_option( 'woocommerce_wc_checkout_com_paypal_settings', array() );
		
		// First check if master toggle is enabled - must be explicitly 'yes'
		// Handle cases: not set, empty string, false, 'no', or anything else = disabled
		$is_express_enabled = isset( $paypal_settings['paypal_express'] ) 
			&& 'yes' === $paypal_settings['paypal_express']
			&& ! empty( $paypal_settings['paypal_express'] );
		if ( ! $is_express_enabled ) {
			return;
		}

		// Check if PayPal Express is available (gateway check)
		$is_available = WC_Checkoutcom_Utility::is_paypal_express_available();
		if ( ! $is_available ) {
			return;
		}
		
		// Check if PayPal Express is enabled for cart page - must be explicitly 'yes'
		// Handle cases: not set (default enabled), empty string, false, 'no' = disabled
		$show_on_cart = false;
		if ( ! isset( $paypal_settings['paypal_express_cart_page'] ) ) {
			// Default to yes if setting doesn't exist (backward compatibility)
			$show_on_cart = true;
		} elseif ( isset( $paypal_settings['paypal_express_cart_page'] ) 
			&& 'yes' === $paypal_settings['paypal_express_cart_page']
			&& ! empty( $paypal_settings['paypal_express_cart_page'] ) ) {
			$show_on_cart = true;
		}
		
		// Only show on cart page and if PayPal Express is enabled for cart page
		if ( ! is_cart() || ! $show_on_cart ) {
			return;
		}

		// Don't show if cart is empty
		if ( WC()->cart->is_empty() ) {
			return;
		}
		
		// Check if unified express checkout container should be rendered
		$google_pay_settings = get_option( 'woocommerce_wc_checkout_com_google_pay_settings', array() );
		$apple_pay_settings = get_option( 'woocommerce_wc_checkout_com_apple_pay_settings', array() );
		
		$google_express_enabled = isset( $google_pay_settings['google_pay_express'] ) 
			&& 'yes' === $google_pay_settings['google_pay_express']
			&& ! empty( $google_pay_settings['google_pay_express'] );
		$google_available = WC_Checkoutcom_Utility::is_google_pay_express_available();
		$google_show_on_cart = ! isset( $google_pay_settings['google_pay_express_cart_page'] ) 
			|| ( isset( $google_pay_settings['google_pay_express_cart_page'] ) 
				&& 'yes' === $google_pay_settings['google_pay_express_cart_page']
				&& ! empty( $google_pay_settings['google_pay_express_cart_page'] ) );
		
		$apple_pay_express_enabled = isset( $apple_pay_settings['apple_pay_express'] ) 
			&& 'yes' === $apple_pay_settings['apple_pay_express']
			&& ! empty( $apple_pay_settings['apple_pay_express'] );
		$apple_pay_available = WC_Checkoutcom_Utility::is_apple_pay_express_available();
		$apple_pay_show_on_cart = ! isset( $apple_pay_settings['apple_pay_express_cart_page'] ) 
			|| ( isset( $apple_pay_settings['apple_pay_express_cart_page'] ) 
				&& 'yes' === $apple_pay_settings['apple_pay_express_cart_page']
				&& ! empty( $apple_pay_settings['apple_pay_express_cart_page'] ) );
		
		// Check if unified container has been rendered
		$unified_container_rendered = WC_Checkoutcom_Utility::express_checkout_container_rendered();
		
		// Determine if we should render unified container
		$should_render_container = ! $unified_container_rendered && (
			( $google_express_enabled && $google_available && $google_show_on_cart ) ||
			( $apple_pay_express_enabled && $apple_pay_available && $apple_pay_show_on_cart )
		);
		
		// Render unified container only once if both are enabled, or PayPal's own container
		if ( $should_render_container ) {
			WC_Checkoutcom_Utility::express_checkout_container_rendered( true );
			$container_rendered = true;
			?>
			<div class="cko-express-checkout-container">
				<div class="cko-express-checkout-buttons">
					<?php if ( $apple_pay_express_enabled && $apple_pay_available && $apple_pay_show_on_cart ) : ?>
						<div id="cko-apple-pay-button-wrapper-cart"></div>
					<?php endif; ?>
					<?php if ( $google_express_enabled && $google_available && $google_show_on_cart ) : ?>
						<div id="cko-google-pay-button-wrapper-cart"></div>
					<?php endif; ?>
					<div id="cko-paypal-button-wrapper-cart"></div>
				</div>
			</div>
			<?php
		} elseif ( ! $container_rendered && ! WC_Checkoutcom_Utility::express_checkout_container_rendered() ) {
			// Only PayPal is enabled, render PayPal's own container (only if unified container wasn't already rendered)
			$container_rendered = true;
			?>
			<div class="cko-paypal-cart-button">
				<div id="cko-paypal-button-wrapper-cart"></div>
			</div>
			<?php
		} else {
			// Unified container already rendered by another express method, add PayPal button via JavaScript
			?>
			<script>
			(function() {
				var buttonsContainer = document.querySelector('.cko-express-checkout-buttons');
				if (buttonsContainer && !document.getElementById('cko-paypal-button-wrapper-cart')) {
					var paypalWrapper = document.createElement('div');
					paypalWrapper.id = 'cko-paypal-button-wrapper-cart';
					// Insert at the end to maintain Apple Pay -> Google Pay -> PayPal order
					buttonsContainer.appendChild(paypalWrapper);
				}
			})();
			</script>
			<?php
		}
	}

	public function disable_other_gateways( array $methods ) {

		if ( ! isset( $methods['wc_checkout_com_paypal'] ) ) {
			return $methods;
		}

		$cko_paypal_order_id = WC_Checkoutcom_Utility::cko_get_session( 'cko_paypal_order_id' );
		$cko_pc_id           = WC_Checkoutcom_Utility::cko_get_session( 'cko_pc_id' );

		// Check if PayPal session variable exist for current customer.
		$disable_all_gateway = ! empty( $cko_pc_id ) && ! empty( $cko_paypal_order_id );

		if ( $disable_all_gateway ) {
			return [ 'wc_checkout_com_paypal' => $methods['wc_checkout_com_paypal'] ];
		}

		return $methods;
	}

	public function cancel_paypal_session_markup() {
		$cko_paypal_order_id = WC_Checkoutcom_Utility::cko_get_session( 'cko_paypal_order_id' );
		$cko_pc_id           = WC_Checkoutcom_Utility::cko_get_session( 'cko_pc_id' );

		// Check if PayPal session variable exist for current customer.
		$paypal_session_exist = ! empty( $cko_pc_id ) && ! empty( $cko_paypal_order_id );

		$cancel_url = add_query_arg(
			[
				'cko-paypal-session-cancel'       => '1',
				'cko-paypal-session-cancel-nonce' => wp_create_nonce( 'checkoutcom_paypal_cancel' ),
			],
			wc_get_checkout_url()
		);

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
				'checkout-com-unified-payments-api'
			),
			'<br/>',
			'<a href="' . esc_url( $cancel_url ) . '">',
			'</a>',
		);

		?>
		</p>
		<?php
		echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Handle cancel PayPal express session request.
	 */
	public function express_cancel_session() {

		if (
				! isset( $_GET['cko-paypal-session-cancel-nonce'] )
				|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['cko-paypal-session-cancel-nonce'] ) ), 'checkoutcom_paypal_cancel' )
		) {
			return;
		}

		self::empty_paypal_session();
	}

	/**
	 * Empty PayPal session variable.
	 */
	public function empty_paypal_session() {
		WC_Checkoutcom_Utility::cko_set_session( 'cko_paypal_order_id', '' );
		WC_Checkoutcom_Utility::cko_set_session( 'cko_pc_id', '' );
		WC_Checkoutcom_Utility::cko_set_session( 'cko_pc_details', '' );
	}

	/**
	 * Set default checkout field value from PayPal Express.
	 *
	 * @param  string $value Default checkout field value.
	 * @param  string $key   The checkout form field name/key
	 *
	 * @return string $value Checkout field value.
	 */
	public function fill_paypal_selected_address_field( $value, $key ) {

		if ( ! session_id() ) {
			session_start();
		}

		$cko_paypal_order_id = WC_Checkoutcom_Utility::cko_get_session( 'cko_paypal_order_id' );
		$cko_pc_id           = WC_Checkoutcom_Utility::cko_get_session( 'cko_pc_id' );
		$cko_pc_details      = WC_Checkoutcom_Utility::cko_get_session( 'cko_pc_details' );

		if ( empty( $cko_pc_id ) ) {
			return $value;
		}

		if ( empty( $cko_pc_details ) ) {
			try {
				$checkout       = new Checkout_SDK();
				$response       = $checkout->get_builder()->getPaymentContextsClient()->getPaymentContextDetails( $cko_pc_id );
				$cko_pc_details = $response;

				WC_Checkoutcom_Utility::cko_set_session( 'cko_pc_details', $response );

			} catch ( CheckoutApiException $ex ) {
			}
		}

		if ( isset( $cko_pc_details['payment_request']['shipping']['address'] ) ) {
			$paypal_shipping_address    = $cko_pc_details['payment_request']['shipping']['address'];
			$shipping_name              = $cko_pc_details['payment_request']['shipping']['first_name'] ? explode( ' ', $cko_pc_details['payment_request']['shipping']['first_name'] ) : [];
			$paypal_shipping_first_name = $shipping_name[0] ?? '';
			$paypal_shipping_last_name  = $shipping_name[1] ?? '';

			switch ( $key ) {
				case 'billing_first_name':
				case 'shipping_first_name':
					return $paypal_shipping_first_name;

				case 'billing_last_name':
				case 'shipping_last_name':
					return $paypal_shipping_last_name;

				case 'billing_address_1':
				case 'shipping_address_1':
					return $paypal_shipping_address['address_line1'];

				case 'billing_address_2':
				case 'shipping_address_2':
					return $paypal_shipping_address['address_line2'] ?? '';

				case 'billing_city':
				case 'shipping_city':
					return $paypal_shipping_address['city'];

				case 'billing_postcode':
				case 'shipping_postcode':
					return $paypal_shipping_address['zip'];

				case 'billing_country':
				case 'shipping_country':
					return $paypal_shipping_address['country'];

				case 'billing_email':
					// For logged-in users, use their account email
					if ( is_user_logged_in() ) {
						$current_user = wp_get_current_user();
						if ( $current_user && $current_user->user_email ) {
							return $current_user->user_email;
						}
					}
					// For guest users, get email from PayPal data
					// Check multiple possible locations
					if ( isset( $cko_pc_details['payment_request']['source']['account_holder']['email'] ) ) {
						return $cko_pc_details['payment_request']['source']['account_holder']['email'];
					}
					if ( isset( $cko_pc_details['payment_request']['shipping']['email'] ) ) {
						return $cko_pc_details['payment_request']['shipping']['email'];
					}
					if ( isset( $cko_pc_details['payment_request']['billing']['email'] ) ) {
						return $cko_pc_details['payment_request']['billing']['email'];
					}
					if ( isset( $cko_pc_details['payment_request']['payer']['email'] ) ) {
						return $cko_pc_details['payment_request']['payer']['email'];
					}
					if ( isset( $cko_pc_details['payment_request']['customer']['email'] ) ) {
						return $cko_pc_details['payment_request']['customer']['email'];
					}
					// Return original value if not found
					return $value;
			}
		}

		return $value;
	}
}

CKO_Paypal_Express::get_instance();