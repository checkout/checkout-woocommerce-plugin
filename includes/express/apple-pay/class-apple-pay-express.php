<?php
/**
 * Apple Pay Express handler.
 *
 * @package wc_checkout_com
 */

defined( 'ABSPATH' ) || exit;

class CKO_Apple_Pay_Express {

	private static $instance = null;

	public static function get_instance(): CKO_Apple_Pay_Express {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		$apple_pay_settings = get_option( 'woocommerce_wc_checkout_com_apple_pay_settings', array() );

		// Check if master toggle is enabled - must be explicitly 'yes'
		// Handle cases: not set, empty string, false, 'no', or anything else = disabled
		$is_express_enable = isset( $apple_pay_settings['apple_pay_express'] ) 
			&& 'yes' === $apple_pay_settings['apple_pay_express']
			&& ! empty( $apple_pay_settings['apple_pay_express'] );
		$apple_pay_enabled    = ! empty( $apple_pay_settings['enabled'] ) && 'yes' === $apple_pay_settings['enabled'];

		$checkout_setting = get_option( 'woocommerce_wc_checkout_com_cards_settings', array() );
		$checkout_mode    = isset( $checkout_setting['ckocom_checkout_mode'] ) ? $checkout_setting['ckocom_checkout_mode'] : 'classic';

		// If Express is disabled, don't add any hooks (regardless of mode)
		if ( ! $is_express_enable ) {
			return;
		}

		// For classic mode, also check if Apple Pay gateway is enabled
		if ( $checkout_mode === 'classic' && ! $apple_pay_enabled ) {
			return;
		}

		add_action( 'woocommerce_after_add_to_cart_form', [ $this, 'display_payment_request_button_html' ], 1 );
		
		// Shop page rendering is now handled by WC_Checkoutcom_Express_Checkout_Element
		// Keep this hook commented out to prevent duplicate rendering
		// add_action( 'woocommerce_after_shop_loop_item', [ $this, 'display_shop_payment_request_button_html' ], 15 );
		
		// Add Apple Pay Express button to cart page (classic cart)
		add_action( 'woocommerce_proceed_to_checkout', [ $this, 'display_cart_payment_request_button_html' ], 5 );
		
		// Add Apple Pay Express button to Blocks cart page
		add_action( 'woocommerce_blocks_cart_block_render', [ $this, 'display_cart_payment_request_button_html' ], 10 );
		
		// Add Apple Pay Express button after cart table (fallback for Blocks)
		add_action( 'woocommerce_after_cart_table', [ $this, 'display_cart_payment_request_button_html' ], 5 );
		
		// Add Apple Pay Express button in cart collaterals (another fallback)
		add_action( 'woocommerce_cart_collaterals', [ $this, 'display_cart_payment_request_button_html' ], 15 );

		add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );

		add_filter( 'woocommerce_available_payment_gateways', [ $this, 'disable_other_gateways' ] );

		add_action( 'woocommerce_review_order_after_submit', [ $this, 'cancel_apple_pay_session_markup' ] );

		add_action( 'woocommerce_init', [ $this, 'express_cancel_session' ] );

		// Clear Apple Pay session if any of the below action run.
		add_action( 'woocommerce_cart_emptied', [ $this, 'empty_apple_pay_session' ], 1 );
		add_action( 'woocommerce_cart_item_removed', [ $this, 'empty_apple_pay_session' ], 1 );
		add_action( 'woocommerce_update_cart_action_cart_updated', [ $this, 'empty_apple_pay_session' ], 1 );
		add_action( 'woocommerce_cart_item_set_quantity', [ $this, 'empty_apple_pay_session' ], 1 );
		add_action( 'woocommerce_add_to_cart', [ $this, 'empty_apple_pay_session' ], 1 );

		/**
		 * Filters.
		 */
		add_filter( 'woocommerce_checkout_get_value', [ $this, 'fill_apple_pay_selected_address_field' ], 10, 2 );

		// Add dynamic Apple Pay enablement for FLOW mode.
		$this->maybe_enable_apple_pay_in_flow_mode();
	}

	/**
	 * Check if Express Checkout session exists.
	 *
	 * @return bool
	 */
	private function has_express_checkout_session(): bool {
		$cko_apple_pay_order_id = WC_Checkoutcom_Utility::cko_get_session( 'cko_apple_pay_order_id' );
		$cko_ap_id               = WC_Checkoutcom_Utility::cko_get_session( 'cko_ap_id' );

		return ( ! empty( $cko_ap_id ) && ! empty( $cko_apple_pay_order_id ) );
	}

	/**
	 * Dynamically enable Apple Pay in FLOW mode if session exists.
	 */
	private function maybe_enable_apple_pay_in_flow_mode() {

		$checkout_setting = get_option( 'woocommerce_wc_checkout_com_cards_settings', array() );
		$checkout_mode    = isset( $checkout_setting['ckocom_checkout_mode'] ) ? $checkout_setting['ckocom_checkout_mode'] : '';

		// Run only for FLOW mode.
		if ( 'flow' !== $checkout_mode ) {
			return;
		}

		// Add hooks for FLOW Apple Pay enablement.
		add_filter( 'woocommerce_payment_gateways', [ $this, 'add_apple_pay_gateway' ] );
		add_filter( 'option_woocommerce_wc_checkout_com_apple_pay_settings', [ $this, 'force_enable_apple_pay_settings' ] );
		add_filter( 'woocommerce_available_payment_gateways', [ $this, 'restrict_gateways_to_apple_pay' ] );
	}

	/**
	 * Step 1: Add Apple Pay gateway dynamically.
	 *
	 * @param array $gateways
	 * @return array
	 */
	public function add_apple_pay_gateway( $gateways ) {
		if ( $this->has_express_checkout_session() ) {
			if ( ! in_array( 'WC_Gateway_Checkout_Com_Apple_Pay', $gateways, true ) ) {
				$gateways[] = 'WC_Gateway_Checkout_Com_Apple_Pay';
			}
		}
		return $gateways;
	}

	/**
	 * Step 2: Force-enable Apple Pay settings.
	 *
	 * @param array $settings
	 * @return array
	 */
	public function force_enable_apple_pay_settings( $settings ) {
		if ( $this->has_express_checkout_session() ) {
			$settings['enabled'] = 'yes';
		}
		return $settings;
	}

	/**
	 * Step 3: Restrict available payment gateways to Apple Pay only.
	 *
	 * @param array $methods
	 * @return array
	 */
	public function restrict_gateways_to_apple_pay( $methods ) {
		if ( $this->has_express_checkout_session() && isset( $methods['wc_checkout_com_apple_pay'] ) ) {
			return [ 'wc_checkout_com_apple_pay' => $methods['wc_checkout_com_apple_pay'] ];
		}
		return $methods;
	}

	public function payment_scripts() {
		$apple_pay_settings = get_option( 'woocommerce_wc_checkout_com_apple_pay_settings', array() );

		// First check if master toggle is enabled - must be explicitly 'yes'
		// Handle cases: not set, empty string, false, 'no', or anything else = disabled
		$is_express_enabled = isset( $apple_pay_settings['apple_pay_express'] ) 
			&& 'yes' === $apple_pay_settings['apple_pay_express']
			&& ! empty( $apple_pay_settings['apple_pay_express'] );
		if ( ! $is_express_enabled ) {
			return;
		}

		// Check which pages should load scripts - must be explicitly 'yes'
		// Handle cases: not set (default enabled), empty string, false, 'no' = disabled
		$show_on_product = false;
		if ( ! isset( $apple_pay_settings['apple_pay_express_product_page'] ) ) {
			$show_on_product = true; // Default to yes if setting doesn't exist (backward compatibility)
		} elseif ( isset( $apple_pay_settings['apple_pay_express_product_page'] ) 
			&& 'yes' === $apple_pay_settings['apple_pay_express_product_page']
			&& ! empty( $apple_pay_settings['apple_pay_express_product_page'] ) ) {
			$show_on_product = true;
		}
		
		$show_on_shop = false;
		if ( ! isset( $apple_pay_settings['apple_pay_express_shop_page'] ) ) {
			$show_on_shop = true; // Default to yes if setting doesn't exist (backward compatibility)
		} elseif ( isset( $apple_pay_settings['apple_pay_express_shop_page'] ) 
			&& 'yes' === $apple_pay_settings['apple_pay_express_shop_page']
			&& ! empty( $apple_pay_settings['apple_pay_express_shop_page'] ) ) {
			$show_on_shop = true;
		}
		
		$show_on_cart = false;
		if ( ! isset( $apple_pay_settings['apple_pay_express_cart_page'] ) ) {
			$show_on_cart = true; // Default to yes if setting doesn't exist (backward compatibility)
		} elseif ( isset( $apple_pay_settings['apple_pay_express_cart_page'] ) 
			&& 'yes' === $apple_pay_settings['apple_pay_express_cart_page']
			&& ! empty( $apple_pay_settings['apple_pay_express_cart_page'] ) ) {
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

		$core_settings      = get_option( 'woocommerce_wc_checkout_com_cards_settings', array() );
		$apple_pay_settings = get_option( 'woocommerce_wc_checkout_com_apple_pay_settings', array() );
		$environment         = isset( $core_settings['ckocom_environment'] ) && 'sandbox' === $core_settings['ckocom_environment'];

		// Load Apple Pay JS API script
		wp_enqueue_script(
			'apple-pay-api',
			'https://applepay.cdn-apple.com/jsapi/1.latest/apple-pay-sdk.js',
			array(),
			null,
			true
		);

		$session_url        = str_replace( 'https:', 'https:', add_query_arg( 'wc-api', 'wc_checkoutcom_session', home_url( '/' ) ) );
		$generate_token_url = str_replace( 'https:', 'https:', add_query_arg( 'wc-api', 'wc_checkoutcom_generate_token', home_url( '/' ) ) );

		$vars = [
			'add_to_cart_url'                     => add_query_arg( [ 'cko_apple_pay_action' => 'express_add_to_cart' ], WC()->api_request_url( 'CKO_Apple_Pay_Woocommerce' ) ),
			'get_cart_total_url'                  => add_query_arg( [ 'cko_apple_pay_action' => 'express_get_cart_total' ], WC()->api_request_url( 'CKO_Apple_Pay_Woocommerce' ) ),
			'apple_pay_order_session_url'       => add_query_arg( [ 'cko_apple_pay_action' => 'express_apple_pay_order_session' ], WC()->api_request_url( 'CKO_Apple_Pay_Woocommerce' ) ),
			'session_url'                          => $session_url,
			'generate_token_url'                   => $generate_token_url,
			'woocommerce_process_checkout'        => wp_create_nonce( 'woocommerce-process_checkout' ),
			'is_cart_contains_subscription'      => WC_Checkoutcom_Utility::is_cart_contains_subscription(),
			'apple_pay_button_selector'          => '#cko-apple-pay-button-wrapper',
			'redirect'                            => wc_get_checkout_url(),
			'apple_pay_express_add_to_cart_nonce' => wp_create_nonce( 'checkoutcom_apple_pay_express_add_to_cart' ),
			'debug'                               => 'yes' === WC_Admin_Settings::get_option( 'cko_console_logging', 'no' ),
			'environment'                         => $environment ? 'TEST' : 'PRODUCTION',
			'merchant_id'                         => $apple_pay_settings['ckocom_apple_mercahnt_id'] ?? '',
			'currency_code'                       => get_woocommerce_currency(),
			'button_type'                         => 'plain',
			'button_theme'                        => $apple_pay_settings['ckocom_apple_theme'] ?? 'black',
			'button_language'                     => $apple_pay_settings['ckocom_apple_language'] ?? '',
			'enable_mada'                         => isset( $apple_pay_settings['enable_mada'] ) && 'yes' === $apple_pay_settings['enable_mada'],
		];

		wp_localize_script( 'apple-pay-api', 'cko_apple_pay_vars', $vars );

		wp_register_script(
			'cko-apple-pay-express-integration-script',
			WC_CHECKOUTCOM_PLUGIN_URL . '/assets/js/cko-apple-pay-express-integration.js',
			array( 'jquery', 'apple-pay-api' ),
			WC_CHECKOUTCOM_PLUGIN_VERSION
		);

		wp_enqueue_script( 'cko-apple-pay-express-integration-script' );
	}

	public function display_payment_request_button_html() {
		static $container_rendered = false;
		
		$apple_pay_settings = get_option( 'woocommerce_wc_checkout_com_apple_pay_settings', array() );
		
		// First check if master toggle is enabled - must be explicitly 'yes'
		// Handle cases: not set, empty string, false, 'no', or anything else = disabled
		$is_express_enabled = isset( $apple_pay_settings['apple_pay_express'] ) 
			&& 'yes' === $apple_pay_settings['apple_pay_express']
			&& ! empty( $apple_pay_settings['apple_pay_express'] );
		if ( ! $is_express_enabled ) {
			return;
		}
		
		// Check if Apple Pay Express is enabled for product pages - must be explicitly 'yes'
		// Handle cases: not set (default enabled), empty string, false, 'no' = disabled
		$show_on_product = false;
		if ( ! isset( $apple_pay_settings['apple_pay_express_product_page'] ) ) {
			// Default to yes if setting doesn't exist (backward compatibility)
			$show_on_product = true;
		} elseif ( isset( $apple_pay_settings['apple_pay_express_product_page'] ) 
			&& 'yes' === $apple_pay_settings['apple_pay_express_product_page']
			&& ! empty( $apple_pay_settings['apple_pay_express_product_page'] ) ) {
			$show_on_product = true;
		}

		if ( ! is_product() || ! $show_on_product ) {
			return;
		}

		// Check if unified express checkout container should be rendered
		$paypal_settings = get_option( 'woocommerce_wc_checkout_com_paypal_settings', array() );
		$google_pay_settings = get_option( 'woocommerce_wc_checkout_com_google_pay_settings', array() );
		
		$paypal_express_enabled = isset( $paypal_settings['paypal_express'] ) 
			&& 'yes' === $paypal_settings['paypal_express']
			&& ! empty( $paypal_settings['paypal_express'] );
		$paypal_available = WC_Checkoutcom_Utility::is_paypal_express_available();
		$paypal_show_on_product = ! isset( $paypal_settings['paypal_express_product_page'] ) 
			|| ( isset( $paypal_settings['paypal_express_product_page'] ) 
				&& 'yes' === $paypal_settings['paypal_express_product_page']
				&& ! empty( $paypal_settings['paypal_express_product_page'] ) );
		
		$google_pay_express_enabled = isset( $google_pay_settings['google_pay_express'] ) 
			&& 'yes' === $google_pay_settings['google_pay_express']
			&& ! empty( $google_pay_settings['google_pay_express'] );
		$google_pay_available = WC_Checkoutcom_Utility::is_google_pay_express_available();
		$google_pay_show_on_product = ! isset( $google_pay_settings['google_pay_express_product_page'] ) 
			|| ( isset( $google_pay_settings['google_pay_express_product_page'] ) 
				&& 'yes' === $google_pay_settings['google_pay_express_product_page']
				&& ! empty( $google_pay_settings['google_pay_express_product_page'] ) );
		
		// Check if unified container has been rendered
		$unified_container_rendered = WC_Checkoutcom_Utility::express_checkout_container_rendered();
		
		// Determine if we should render unified container
		$should_render_unified = ! $unified_container_rendered && (
			( $paypal_express_enabled && $paypal_available && $paypal_show_on_product ) ||
			( $google_pay_express_enabled && $google_pay_available && $google_pay_show_on_product )
		);
		
		// Render unified container only once if at least one other express method is enabled
		if ( $should_render_unified ) {
			WC_Checkoutcom_Utility::express_checkout_container_rendered( true );
			$container_rendered = true;
			?>
			<div class="cko-express-checkout-container">
				<div class="cko-express-checkout-buttons">
					<div id="cko-apple-pay-button-wrapper"></div>
					<?php if ( $google_pay_express_enabled && $google_pay_available && $google_pay_show_on_product ) : ?>
						<div id="cko-google-pay-button-wrapper" style="display: block;"></div>
					<?php endif; ?>
					<?php if ( $paypal_express_enabled && $paypal_available && $paypal_show_on_product ) : ?>
						<div id="cko-paypal-button-wrapper"></div>
					<?php endif; ?>
				</div>
			</div>
			<?php
		} elseif ( ! $container_rendered && ! WC_Checkoutcom_Utility::express_checkout_container_rendered() ) {
			// Only Apple Pay is enabled, render Apple Pay's own container (only if unified container wasn't already rendered)
			$container_rendered = true;
			?>
			<div class="cko-apple-pay-product-button">
				<div id="cko-apple-pay-button-wrapper" style="display: block;"></div>
			</div>
			<?php
		} else {
			// Unified container already rendered by another express method, add Apple Pay button via JavaScript
			?>
			<script>
			(function() {
				var buttonsContainer = document.querySelector('.cko-express-checkout-buttons');
				if (buttonsContainer && !document.getElementById('cko-apple-pay-button-wrapper')) {
					var appleWrapper = document.createElement('div');
					appleWrapper.id = 'cko-apple-pay-button-wrapper';
					appleWrapper.style.display = 'block';
					// Insert at the beginning to maintain Apple Pay -> Google Pay -> PayPal order
					buttonsContainer.insertBefore(appleWrapper, buttonsContainer.firstChild);
				}
			})();
			</script>
			<?php
		}
	}

	/**
	 * Display Apple Pay Express button on shop/listing pages
	 */
	public function display_shop_payment_request_button_html() {
		global $product;

		$apple_pay_settings = get_option( 'woocommerce_wc_checkout_com_apple_pay_settings', array() );
		
		// First check if master toggle is enabled - must be explicitly 'yes'
		// Handle cases: not set, empty string, false, 'no', or anything else = disabled
		$is_express_enabled = isset( $apple_pay_settings['apple_pay_express'] ) 
			&& 'yes' === $apple_pay_settings['apple_pay_express']
			&& ! empty( $apple_pay_settings['apple_pay_express'] );
		if ( ! $is_express_enabled ) {
			return;
		}
		
		// Check if Apple Pay Express is enabled for shop pages - must be explicitly 'yes'
		// Handle cases: not set (default enabled), empty string, false, 'no' = disabled
		$show_on_shop = false;
		if ( ! isset( $apple_pay_settings['apple_pay_express_shop_page'] ) ) {
			// Default to yes if setting doesn't exist (backward compatibility)
			$show_on_shop = true;
		} elseif ( isset( $apple_pay_settings['apple_pay_express_shop_page'] ) 
			&& 'yes' === $apple_pay_settings['apple_pay_express_shop_page']
			&& ! empty( $apple_pay_settings['apple_pay_express_shop_page'] ) ) {
			$show_on_shop = true;
		}
		
		// Only show on shop pages and if Apple Pay Express is enabled for shop pages
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
			.cko-apple-pay-shop-button .cko-disabled {
				cursor: not-allowed;
				-webkit-filter: grayscale(100%);
				filter: grayscale(100%);
			}
			.cko-apple-pay-shop-button #cko-apple-pay-button-wrapper-<?php echo esc_attr( $product_id ); ?> {
				display: none;
			}
		</style>
		<div class="cko-apple-pay-shop-button">
			<div id="cko-apple-pay-button-wrapper-<?php echo esc_attr( $product_id ); ?>" data-product-id="<?php echo esc_attr( $product_id ); ?>"></div>
		</div>
		<?php
	}

	/**
	 * Display Apple Pay Express button on cart page
	 */
	public function display_cart_payment_request_button_html() {
		static $container_rendered = false;
		
		$apple_pay_settings = get_option( 'woocommerce_wc_checkout_com_apple_pay_settings', array() );
		
		// First check if master toggle is enabled - must be explicitly 'yes'
		// Handle cases: not set, empty string, false, 'no', or anything else = disabled
		$is_express_enabled = isset( $apple_pay_settings['apple_pay_express'] ) 
			&& 'yes' === $apple_pay_settings['apple_pay_express']
			&& ! empty( $apple_pay_settings['apple_pay_express'] );
		if ( ! $is_express_enabled ) {
			return;
		}

		// Check if Apple Pay Express is available (gateway check)
		$is_available = WC_Checkoutcom_Utility::is_apple_pay_express_available();
		if ( ! $is_available ) {
			return;
		}
		
		// Check if Apple Pay Express is enabled for cart page - must be explicitly 'yes'
		// Handle cases: not set (default enabled), empty string, false, 'no' = disabled
		$show_on_cart = false;
		if ( ! isset( $apple_pay_settings['apple_pay_express_cart_page'] ) ) {
			// Default to yes if setting doesn't exist (backward compatibility)
			$show_on_cart = true;
		} elseif ( isset( $apple_pay_settings['apple_pay_express_cart_page'] ) 
			&& 'yes' === $apple_pay_settings['apple_pay_express_cart_page']
			&& ! empty( $apple_pay_settings['apple_pay_express_cart_page'] ) ) {
			$show_on_cart = true;
		}
		
		// Only show on cart page and if Apple Pay Express is enabled for cart page
		if ( ! is_cart() || ! $show_on_cart ) {
			return;
		}

		// Don't show if cart is empty
		if ( WC()->cart->is_empty() ) {
			return;
		}
		
		// Check if unified express checkout container should be rendered
		$paypal_settings = get_option( 'woocommerce_wc_checkout_com_paypal_settings', array() );
		$google_pay_settings = get_option( 'woocommerce_wc_checkout_com_google_pay_settings', array() );
		
		$paypal_express_enabled = isset( $paypal_settings['paypal_express'] ) 
			&& 'yes' === $paypal_settings['paypal_express']
			&& ! empty( $paypal_settings['paypal_express'] );
		$paypal_available = WC_Checkoutcom_Utility::is_paypal_express_available();
		$paypal_show_on_cart = ! isset( $paypal_settings['paypal_express_cart_page'] ) 
			|| ( isset( $paypal_settings['paypal_express_cart_page'] ) 
				&& 'yes' === $paypal_settings['paypal_express_cart_page']
				&& ! empty( $paypal_settings['paypal_express_cart_page'] ) );
		
		$google_pay_express_enabled = isset( $google_pay_settings['google_pay_express'] ) 
			&& 'yes' === $google_pay_settings['google_pay_express']
			&& ! empty( $google_pay_settings['google_pay_express'] );
		$google_pay_available = WC_Checkoutcom_Utility::is_google_pay_express_available();
		$google_pay_show_on_cart = ! isset( $google_pay_settings['google_pay_express_cart_page'] ) 
			|| ( isset( $google_pay_settings['google_pay_express_cart_page'] ) 
				&& 'yes' === $google_pay_settings['google_pay_express_cart_page']
				&& ! empty( $google_pay_settings['google_pay_express_cart_page'] ) );
		
		// Check if unified container has been rendered
		$unified_container_rendered = WC_Checkoutcom_Utility::express_checkout_container_rendered();
		
		// Determine if we should render unified container
		$should_render_unified = ! $unified_container_rendered && (
			( $paypal_express_enabled && $paypal_available && $paypal_show_on_cart ) ||
			( $google_pay_express_enabled && $google_pay_available && $google_pay_show_on_cart )
		);
		
		// Render unified container only once if at least one other express method is enabled
		if ( $should_render_unified ) {
			WC_Checkoutcom_Utility::express_checkout_container_rendered( true );
			$container_rendered = true;
			?>
			<div class="cko-express-checkout-container">
				<div class="cko-express-checkout-buttons">
					<div id="cko-apple-pay-button-wrapper-cart"></div>
					<?php if ( $google_pay_express_enabled && $google_pay_available && $google_pay_show_on_cart ) : ?>
						<div id="cko-google-pay-button-wrapper-cart"></div>
					<?php endif; ?>
					<?php if ( $paypal_express_enabled && $paypal_available && $paypal_show_on_cart ) : ?>
						<div id="cko-paypal-button-wrapper-cart"></div>
					<?php endif; ?>
				</div>
			</div>
			<?php
		} elseif ( ! $container_rendered && ! WC_Checkoutcom_Utility::express_checkout_container_rendered() ) {
			// Only Apple Pay is enabled, render Apple Pay's own container (only if unified container wasn't already rendered)
			$container_rendered = true;
			?>
			<div class="cko-apple-pay-cart-button">
				<div id="cko-apple-pay-button-wrapper-cart"></div>
			</div>
			<?php
		} else {
			// Unified container already rendered by another express method, add Apple Pay button via JavaScript
			?>
			<script>
			(function() {
				var buttonsContainer = document.querySelector('.cko-express-checkout-buttons');
				if (buttonsContainer && !document.getElementById('cko-apple-pay-button-wrapper-cart')) {
					var appleWrapper = document.createElement('div');
					appleWrapper.id = 'cko-apple-pay-button-wrapper-cart';
					// Insert at the beginning to maintain Apple Pay -> Google Pay -> PayPal order
					buttonsContainer.insertBefore(appleWrapper, buttonsContainer.firstChild);
				}
			})();
			</script>
			<?php
		}
	}

	public function disable_other_gateways( array $methods ) {

		if ( ! isset( $methods['wc_checkout_com_apple_pay'] ) ) {
			return $methods;
		}

		$cko_apple_pay_order_id = WC_Checkoutcom_Utility::cko_get_session( 'cko_apple_pay_order_id' );
		$cko_ap_id               = WC_Checkoutcom_Utility::cko_get_session( 'cko_ap_id' );

		// Check if Apple Pay session variable exist for current customer.
		$disable_all_gateway = ! empty( $cko_ap_id ) && ! empty( $cko_apple_pay_order_id );

		if ( $disable_all_gateway ) {
			return [ 'wc_checkout_com_apple_pay' => $methods['wc_checkout_com_apple_pay'] ];
		}

		return $methods;
	}

	public function cancel_apple_pay_session_markup() {
		$cko_apple_pay_order_id = WC_Checkoutcom_Utility::cko_get_session( 'cko_apple_pay_order_id' );
		$cko_ap_id               = WC_Checkoutcom_Utility::cko_get_session( 'cko_ap_id' );

		// Check if Apple Pay session variable exist for current customer.
		$apple_pay_session_exist = ! empty( $cko_ap_id ) && ! empty( $cko_apple_pay_order_id );

		$cancel_url = add_query_arg(
			[
				'cko-apple-pay-session-cancel'       => '1',
				'cko-apple-pay-session-cancel-nonce' => wp_create_nonce( 'checkoutcom_apple_pay_cancel' ),
			],
			wc_get_checkout_url()
		);

		if ( ! $apple_pay_session_exist ) {
			return;
		}

		ob_start();
		?>
		<p id="cko-apple-pay-cancel" class="has-text-align-center">
		<?php

		printf(
		// translators: %3$ is funding source like "Apple Pay", other placeholders are html tags for a link.
			esc_html__(
				'You are currently paying with Apple Pay. %1$s%2$sChoose another payment method%3$s.',
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
	 * Handle cancel Apple Pay express session request.
	 */
	public function express_cancel_session() {

		if (
				! isset( $_GET['cko-apple-pay-session-cancel-nonce'] )
				|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['cko-apple-pay-session-cancel-nonce'] ) ), 'checkoutcom_apple_pay_cancel' )
		) {
			return;
		}

		self::empty_apple_pay_session();
	}

	/**
	 * Empty Apple Pay session variable.
	 */
	public function empty_apple_pay_session() {
		WC_Checkoutcom_Utility::cko_set_session( 'cko_apple_pay_order_id', '' );
		WC_Checkoutcom_Utility::cko_set_session( 'cko_ap_id', '' );
		WC_Checkoutcom_Utility::cko_set_session( 'cko_ap_details', '' );
	}

	/**
	 * Set default checkout field value from Apple Pay Express.
	 *
	 * @param  string $value Default checkout field value.
	 * @param  string $key   The checkout form field name/key
	 *
	 * @return string $value Checkout field value.
	 */
	public function fill_apple_pay_selected_address_field( $value, $key ) {

		if ( ! session_id() ) {
			session_start();
		}

		$cko_apple_pay_order_id = WC_Checkoutcom_Utility::cko_get_session( 'cko_apple_pay_order_id' );
		$cko_ap_id               = WC_Checkoutcom_Utility::cko_get_session( 'cko_ap_id' );
		$cko_ap_details          = WC_Checkoutcom_Utility::cko_get_session( 'cko_ap_details' );

		if ( empty( $cko_ap_id ) ) {
			return $value;
		}

		if ( empty( $cko_ap_details ) ) {
			// For Apple Pay Express, we extract address from payment data directly
			// This is different from Google Pay which uses payment contexts
			return $value;
		}

		// Extract address from Apple Pay payment data if available
		if ( isset( $cko_ap_details['shippingContact'] ) ) {
			$apple_pay_shipping = $cko_ap_details['shippingContact'];
			$apple_pay_first_name = $apple_pay_shipping['givenName'] ?? '';
			$apple_pay_last_name = $apple_pay_shipping['familyName'] ?? '';

			switch ( $key ) {
				case 'billing_first_name':
				case 'shipping_first_name':
					return $apple_pay_first_name;

				case 'billing_last_name':
				case 'shipping_last_name':
					return $apple_pay_last_name;

				case 'billing_address_1':
				case 'shipping_address_1':
					return $apple_pay_shipping['addressLines'][0] ?? '';

				case 'billing_address_2':
				case 'shipping_address_2':
					return $apple_pay_shipping['addressLines'][1] ?? '';

				case 'billing_city':
				case 'shipping_city':
					return $apple_pay_shipping['locality'] ?? '';

				case 'billing_postcode':
				case 'shipping_postcode':
					return $apple_pay_shipping['postalCode'] ?? '';

				case 'billing_country':
				case 'shipping_country':
					return $apple_pay_shipping['countryCode'] ?? '';

				case 'billing_state':
				case 'shipping_state':
					return $apple_pay_shipping['administrativeArea'] ?? '';

				case 'billing_email':
					// For logged-in users, use their account email
					if ( is_user_logged_in() ) {
						$current_user = wp_get_current_user();
						if ( $current_user && $current_user->user_email ) {
							return $current_user->user_email;
						}
					}
					// For guest users, get email from Apple Pay data if available
					if ( isset( $cko_ap_details['email'] ) ) {
						return $cko_ap_details['email'];
					}
					return $value;
			}
		}

		return $value;
	}
}

CKO_Apple_Pay_Express::get_instance();

