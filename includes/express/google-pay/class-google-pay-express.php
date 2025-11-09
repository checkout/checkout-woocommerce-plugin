<?php
/**
 * Google Pay Express handler.
 *
 * @package wc_checkout_com
 */

defined( 'ABSPATH' ) || exit;

class CKO_Google_Pay_Express {

	private static $instance = null;

	public static function get_instance(): CKO_Google_Pay_Express {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		$google_pay_settings = get_option( 'woocommerce_wc_checkout_com_google_pay_settings', array() );

		// Check if master toggle is enabled - must be explicitly 'yes'
		// Handle cases: not set, empty string, false, 'no', or anything else = disabled
		$is_express_enable = isset( $google_pay_settings['google_pay_express'] ) 
			&& 'yes' === $google_pay_settings['google_pay_express']
			&& ! empty( $google_pay_settings['google_pay_express'] );
		$google_pay_enabled    = ! empty( $google_pay_settings['enabled'] ) && 'yes' === $google_pay_settings['enabled'];

		$checkout_setting = get_option( 'woocommerce_wc_checkout_com_cards_settings', array() );
		$checkout_mode    = isset( $checkout_setting['ckocom_checkout_mode'] ) ? $checkout_setting['ckocom_checkout_mode'] : 'classic';

		// If Express is disabled, don't add any hooks (regardless of mode)
		if ( ! $is_express_enable ) {
			return;
		}

		// For classic mode, also check if Google Pay gateway is enabled
		if ( $checkout_mode === 'classic' && ! $google_pay_enabled ) {
			return;
		}

		add_action( 'woocommerce_after_add_to_cart_form', [ $this, 'display_payment_request_button_html' ], 1 );
		
		// Add Google Pay Express buttons to shop/listing pages
		add_action( 'woocommerce_after_shop_loop_item', [ $this, 'display_shop_payment_request_button_html' ], 15 );
		
		// Add Google Pay Express button to cart page (classic cart)
		add_action( 'woocommerce_proceed_to_checkout', [ $this, 'display_cart_payment_request_button_html' ], 5 );
		
		// Add Google Pay Express button to Blocks cart page
		add_action( 'woocommerce_blocks_cart_block_render', [ $this, 'display_cart_payment_request_button_html' ], 10 );
		
		// Add Google Pay Express button after cart table (fallback for Blocks)
		add_action( 'woocommerce_after_cart_table', [ $this, 'display_cart_payment_request_button_html' ], 5 );
		
		// Add Google Pay Express button in cart collaterals (another fallback)
		add_action( 'woocommerce_cart_collaterals', [ $this, 'display_cart_payment_request_button_html' ], 15 );

		add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );

		add_filter( 'woocommerce_available_payment_gateways', [ $this, 'disable_other_gateways' ] );

		add_action( 'woocommerce_review_order_after_submit', [ $this, 'cancel_google_pay_session_markup' ] );

		add_action( 'woocommerce_init', [ $this, 'express_cancel_session' ] );

		// Clear Google Pay session if any of the below action run.
		add_action( 'woocommerce_cart_emptied', [ $this, 'empty_google_pay_session' ], 1 );
		add_action( 'woocommerce_cart_item_removed', [ $this, 'empty_google_pay_session' ], 1 );
		add_action( 'woocommerce_update_cart_action_cart_updated', [ $this, 'empty_google_pay_session' ], 1 );
		add_action( 'woocommerce_cart_item_set_quantity', [ $this, 'empty_google_pay_session' ], 1 );
		add_action( 'woocommerce_add_to_cart', [ $this, 'empty_google_pay_session' ], 1 );

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

		$checkout_setting = get_option( 'woocommerce_wc_checkout_com_cards_settings', array() );
		$checkout_mode    = isset( $checkout_setting['ckocom_checkout_mode'] ) ? $checkout_setting['ckocom_checkout_mode'] : '';

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
		if ( $this->has_express_checkout_session() && isset( $methods['wc_checkout_com_google_pay'] ) ) {
			return [ 'wc_checkout_com_google_pay' => $methods['wc_checkout_com_google_pay'] ];
		}
		return $methods;
	}

	public function payment_scripts() {
		$google_pay_settings = get_option( 'woocommerce_wc_checkout_com_google_pay_settings', array() );

		// First check if master toggle is enabled - must be explicitly 'yes'
		// Handle cases: not set, empty string, false, 'no', or anything else = disabled
		$is_express_enabled = isset( $google_pay_settings['google_pay_express'] ) 
			&& 'yes' === $google_pay_settings['google_pay_express']
			&& ! empty( $google_pay_settings['google_pay_express'] );
		if ( ! $is_express_enabled ) {
			return;
		}

		// Check which pages should load scripts - must be explicitly 'yes'
		// Handle cases: not set (default enabled), empty string, false, 'no' = disabled
		$show_on_product = false;
		if ( ! isset( $google_pay_settings['google_pay_express_product_page'] ) ) {
			$show_on_product = true; // Default to yes if setting doesn't exist (backward compatibility)
		} elseif ( isset( $google_pay_settings['google_pay_express_product_page'] ) 
			&& 'yes' === $google_pay_settings['google_pay_express_product_page']
			&& ! empty( $google_pay_settings['google_pay_express_product_page'] ) ) {
			$show_on_product = true;
		}
		
		$show_on_shop = false;
		if ( ! isset( $google_pay_settings['google_pay_express_shop_page'] ) ) {
			$show_on_shop = true; // Default to yes if setting doesn't exist (backward compatibility)
		} elseif ( isset( $google_pay_settings['google_pay_express_shop_page'] ) 
			&& 'yes' === $google_pay_settings['google_pay_express_shop_page']
			&& ! empty( $google_pay_settings['google_pay_express_shop_page'] ) ) {
			$show_on_shop = true;
		}
		
		$show_on_cart = false;
		if ( ! isset( $google_pay_settings['google_pay_express_cart_page'] ) ) {
			$show_on_cart = true; // Default to yes if setting doesn't exist (backward compatibility)
		} elseif ( isset( $google_pay_settings['google_pay_express_cart_page'] ) 
			&& 'yes' === $google_pay_settings['google_pay_express_cart_page']
			&& ! empty( $google_pay_settings['google_pay_express_cart_page'] ) ) {
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
		$google_pay_settings = get_option( 'woocommerce_wc_checkout_com_google_pay_settings', array() );
		$environment         = isset( $core_settings['ckocom_environment'] ) && 'sandbox' === $core_settings['ckocom_environment'];

		// Load Google Pay API script
		wp_enqueue_script(
			'google-pay-api',
			'https://pay.google.com/gp/p/js/pay.js',
			array(),
			null,
			true
		);

		$vars = [
			'add_to_cart_url'                     => add_query_arg( [ 'cko_google_pay_action' => 'express_add_to_cart' ], WC()->api_request_url( 'CKO_Google_Pay_Woocommerce' ) ),
			'create_payment_context_url'          => add_query_arg( [ 'cko_google_pay_action' => 'express_create_payment_context' ], WC()->api_request_url( 'CKO_Google_Pay_Woocommerce' ) ),
			'get_cart_total_url'                  => add_query_arg( [ 'cko_google_pay_action' => 'express_get_cart_total' ], WC()->api_request_url( 'CKO_Google_Pay_Woocommerce' ) ),
			'google_pay_order_session_url'       => add_query_arg( [ 'cko_google_pay_action' => 'express_google_pay_order_session' ], WC()->api_request_url( 'CKO_Google_Pay_Woocommerce' ) ),
			'woocommerce_process_checkout'        => wp_create_nonce( 'woocommerce-process_checkout' ),
			'is_cart_contains_subscription'      => WC_Checkoutcom_Utility::is_cart_contains_subscription(),
			'google_pay_button_selector'          => '#cko-google-pay-button-wrapper',
			'redirect'                            => wc_get_checkout_url(),
			'google_pay_express_add_to_cart_nonce' => wp_create_nonce( 'checkoutcom_google_pay_express_add_to_cart' ),
			'debug'                               => 'yes' === WC_Admin_Settings::get_option( 'cko_console_logging', 'no' ),
			'environment'                         => $environment ? 'TEST' : 'PRODUCTION',
			'public_key'                          => $core_settings['ckocom_pk'] ?? '',
			'merchant_id'                         => $google_pay_settings['ckocom_google_merchant_id'] ?? '',
			'currency_code'                       => get_woocommerce_currency(),
			'button_style'                        => $google_pay_settings['ckocom_google_style'] ?? 'google-pay-black',
		];

		wp_localize_script( 'google-pay-api', 'cko_google_pay_vars', $vars );

		wp_register_script(
			'cko-google-pay-express-integration-script',
			WC_CHECKOUTCOM_PLUGIN_URL . '/assets/js/cko-google-pay-express-integration.js',
			array( 'jquery', 'google-pay-api' ),
			WC_CHECKOUTCOM_PLUGIN_VERSION
		);

		wp_enqueue_script( 'cko-google-pay-express-integration-script' );
	}

	public function display_payment_request_button_html() {
		static $container_rendered = false;
		
		$google_pay_settings = get_option( 'woocommerce_wc_checkout_com_google_pay_settings', array() );
		
		// First check if master toggle is enabled - must be explicitly 'yes'
		// Handle cases: not set, empty string, false, 'no', or anything else = disabled
		$is_express_enabled = isset( $google_pay_settings['google_pay_express'] ) 
			&& 'yes' === $google_pay_settings['google_pay_express']
			&& ! empty( $google_pay_settings['google_pay_express'] );
		if ( ! $is_express_enabled ) {
			return;
		}
		
		// Check if Google Pay Express is enabled for product pages - must be explicitly 'yes'
		// Handle cases: not set (default enabled), empty string, false, 'no' = disabled
		$show_on_product = false;
		if ( ! isset( $google_pay_settings['google_pay_express_product_page'] ) ) {
			// Default to yes if setting doesn't exist (backward compatibility)
			$show_on_product = true;
		} elseif ( isset( $google_pay_settings['google_pay_express_product_page'] ) 
			&& 'yes' === $google_pay_settings['google_pay_express_product_page']
			&& ! empty( $google_pay_settings['google_pay_express_product_page'] ) ) {
			$show_on_product = true;
		}

		if ( ! is_product() || ! $show_on_product ) {
			return;
		}

		// Check if unified express checkout container should be rendered
		$paypal_settings = get_option( 'woocommerce_wc_checkout_com_paypal_settings', array() );
		$apple_pay_settings = get_option( 'woocommerce_wc_checkout_com_apple_pay_settings', array() );
		
		$paypal_express_enabled = isset( $paypal_settings['paypal_express'] ) 
			&& 'yes' === $paypal_settings['paypal_express']
			&& ! empty( $paypal_settings['paypal_express'] );
		$paypal_available = WC_Checkoutcom_Utility::is_paypal_express_available();
		$paypal_show_on_product = ! isset( $paypal_settings['paypal_express_product_page'] ) 
			|| ( isset( $paypal_settings['paypal_express_product_page'] ) 
				&& 'yes' === $paypal_settings['paypal_express_product_page']
				&& ! empty( $paypal_settings['paypal_express_product_page'] ) );
		
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
			( $paypal_express_enabled && $paypal_available && $paypal_show_on_product ) ||
			( $apple_pay_express_enabled && $apple_pay_available && $apple_pay_show_on_product )
		);
		
		// Render unified container only once if both are enabled, or Google Pay's own container
		if ( $should_render_container ) {
			WC_Checkoutcom_Utility::express_checkout_container_rendered( true );
			$container_rendered = true;
			?>
			<style>
				.cko-express-checkout-container {
					margin: 15px 0;
					text-align: center;
					padding: 15px;
					background: #f8f9fa;
					border: 1px solid #e9ecef;
					border-radius: 5px;
				}
				.cko-express-checkout-container h3 {
					margin: 0 0 15px 0;
					font-size: 16px;
					color: #333;
				}
				.cko-express-checkout-buttons {
					display: flex;
					flex-direction: column;
					gap: 10px;
					align-items: center;
					justify-content: center;
				}
				.cko-express-checkout-buttons > div {
					width: 100%;
					max-width: 300px;
				}
				.cko-express-checkout-buttons #cko-apple-pay-button-wrapper,
				.cko-express-checkout-buttons #cko-google-pay-button-wrapper,
				.cko-express-checkout-buttons #cko-paypal-button-wrapper {
					display: block !important;
				}
				.cko-express-checkout-buttons .cko-disabled {
					cursor: not-allowed;
					-webkit-filter: grayscale(100%);
					filter: grayscale(100%);
				}
			</style>
			<div class="cko-express-checkout-container">
				<h3><?php _e( 'Express Checkout', 'checkout-com-unified-payments-api' ); ?></h3>
				<div class="cko-express-checkout-buttons">
					<?php if ( $apple_pay_express_enabled && $apple_pay_available && $apple_pay_show_on_product ) : ?>
						<div id="cko-apple-pay-button-wrapper"></div>
					<?php endif; ?>
					<div id="cko-google-pay-button-wrapper" style="display: block;"></div>
					<?php if ( $paypal_express_enabled && $paypal_available && $paypal_show_on_product ) : ?>
						<div id="cko-paypal-button-wrapper"></div>
					<?php endif; ?>
				</div>
			</div>
			<?php
		} elseif ( ! $container_rendered && ! WC_Checkoutcom_Utility::express_checkout_container_rendered() ) {
			// Only Google Pay is enabled, render Google Pay's own container (only if unified container wasn't already rendered)
			$container_rendered = true;
			?>
			<style>
				.cko-google-pay-product-button {
					margin: 15px 0;
					text-align: center;
					padding: 15px;
					background: #f8f9fa;
					border: 1px solid #e9ecef;
					border-radius: 5px;
				}
				.cko-google-pay-product-button .cko-disabled {
					cursor: not-allowed;
					-webkit-filter: grayscale(100%);
					filter: grayscale(100%);
				}
				.cko-google-pay-product-button #cko-google-pay-button-wrapper {
					display: block;
					max-width: 300px;
					margin: 0 auto;
				}
				.cko-google-pay-product-button h3 {
					margin: 0 0 10px 0;
					font-size: 16px;
					color: #333;
				}
			</style>
			<div class="cko-google-pay-product-button">
				<h3><?php _e( 'Express Checkout', 'checkout-com-unified-payments-api' ); ?></h3>
				<div id="cko-google-pay-button-wrapper" style="display: block;"></div>
			</div>
			<?php
		} else {
			// Unified container already rendered by another express method, add Google Pay button via JavaScript
			?>
			<script>
			(function() {
				var buttonsContainer = document.querySelector('.cko-express-checkout-buttons');
				if (buttonsContainer && !document.getElementById('cko-google-pay-button-wrapper')) {
					var googleWrapper = document.createElement('div');
					googleWrapper.id = 'cko-google-pay-button-wrapper';
					googleWrapper.style.display = 'block';
					// Insert after Apple Pay if it exists, otherwise at the beginning
					var applePayWrapper = buttonsContainer.querySelector('#cko-apple-pay-button-wrapper');
					if (applePayWrapper) {
						buttonsContainer.insertBefore(googleWrapper, applePayWrapper.nextSibling);
					} else {
						buttonsContainer.insertBefore(googleWrapper, buttonsContainer.firstChild);
					}
				}
			})();
			</script>
			<?php
		}
	}

	/**
	 * Display Google Pay Express button on shop/listing pages
	 */
	public function display_shop_payment_request_button_html() {
		global $product;

		$google_pay_settings = get_option( 'woocommerce_wc_checkout_com_google_pay_settings', array() );
		
		// First check if master toggle is enabled - must be explicitly 'yes'
		// Handle cases: not set, empty string, false, 'no', or anything else = disabled
		$is_express_enabled = isset( $google_pay_settings['google_pay_express'] ) 
			&& 'yes' === $google_pay_settings['google_pay_express']
			&& ! empty( $google_pay_settings['google_pay_express'] );
		if ( ! $is_express_enabled ) {
			return;
		}
		
		// Check if Google Pay Express is enabled for shop pages - must be explicitly 'yes'
		// Handle cases: not set (default enabled), empty string, false, 'no' = disabled
		$show_on_shop = false;
		if ( ! isset( $google_pay_settings['google_pay_express_shop_page'] ) ) {
			// Default to yes if setting doesn't exist (backward compatibility)
			$show_on_shop = true;
		} elseif ( isset( $google_pay_settings['google_pay_express_shop_page'] ) 
			&& 'yes' === $google_pay_settings['google_pay_express_shop_page']
			&& ! empty( $google_pay_settings['google_pay_express_shop_page'] ) ) {
			$show_on_shop = true;
		}
		
		// Only show on shop pages and if Google Pay Express is enabled for shop pages
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
			.cko-google-pay-shop-button .cko-disabled {
				cursor: not-allowed;
				-webkit-filter: grayscale(100%);
				filter: grayscale(100%);
			}
			.cko-google-pay-shop-button #cko-google-pay-button-wrapper-<?php echo esc_attr( $product_id ); ?> {
				display: none;
			}
		</style>
		<div class="cko-google-pay-shop-button">
			<div id="cko-google-pay-button-wrapper-<?php echo esc_attr( $product_id ); ?>" data-product-id="<?php echo esc_attr( $product_id ); ?>"></div>
		</div>
		<?php
	}

	/**
	 * Display Google Pay Express button on cart page
	 */
	public function display_cart_payment_request_button_html() {
		static $container_rendered = false;
		
		$google_pay_settings = get_option( 'woocommerce_wc_checkout_com_google_pay_settings', array() );
		
		// First check if master toggle is enabled - must be explicitly 'yes'
		// Handle cases: not set, empty string, false, 'no', or anything else = disabled
		$is_express_enabled = isset( $google_pay_settings['google_pay_express'] ) 
			&& 'yes' === $google_pay_settings['google_pay_express']
			&& ! empty( $google_pay_settings['google_pay_express'] );
		if ( ! $is_express_enabled ) {
			return;
		}

		// Check if Google Pay Express is available (gateway check)
		$is_available = WC_Checkoutcom_Utility::is_google_pay_express_available();
		if ( ! $is_available ) {
			return;
		}
		
		// Check if Google Pay Express is enabled for cart page - must be explicitly 'yes'
		// Handle cases: not set (default enabled), empty string, false, 'no' = disabled
		$show_on_cart = false;
		if ( ! isset( $google_pay_settings['google_pay_express_cart_page'] ) ) {
			// Default to yes if setting doesn't exist (backward compatibility)
			$show_on_cart = true;
		} elseif ( isset( $google_pay_settings['google_pay_express_cart_page'] ) 
			&& 'yes' === $google_pay_settings['google_pay_express_cart_page']
			&& ! empty( $google_pay_settings['google_pay_express_cart_page'] ) ) {
			$show_on_cart = true;
		}
		
		// Only show on cart page and if Google Pay Express is enabled for cart page
		if ( ! is_cart() || ! $show_on_cart ) {
			return;
		}

		// Don't show if cart is empty
		if ( WC()->cart->is_empty() ) {
			return;
		}
		
		// Check if unified express checkout container should be rendered
		$paypal_settings = get_option( 'woocommerce_wc_checkout_com_paypal_settings', array() );
		$apple_pay_settings = get_option( 'woocommerce_wc_checkout_com_apple_pay_settings', array() );
		
		$paypal_express_enabled = isset( $paypal_settings['paypal_express'] ) 
			&& 'yes' === $paypal_settings['paypal_express']
			&& ! empty( $paypal_settings['paypal_express'] );
		$paypal_available = WC_Checkoutcom_Utility::is_paypal_express_available();
		$paypal_show_on_cart = ! isset( $paypal_settings['paypal_express_cart_page'] ) 
			|| ( isset( $paypal_settings['paypal_express_cart_page'] ) 
				&& 'yes' === $paypal_settings['paypal_express_cart_page']
				&& ! empty( $paypal_settings['paypal_express_cart_page'] ) );
		
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
			( $paypal_express_enabled && $paypal_available && $paypal_show_on_cart ) ||
			( $apple_pay_express_enabled && $apple_pay_available && $apple_pay_show_on_cart )
		);
		
		// Render unified container only once if both are enabled, or Google Pay's own container
		if ( $should_render_container ) {
			WC_Checkoutcom_Utility::express_checkout_container_rendered( true );
			$container_rendered = true;
			?>
			<style>
				.cko-express-checkout-container {
					margin: 15px 0;
					text-align: center;
					padding: 15px;
					background: #f8f9fa;
					border: 1px solid #e9ecef;
					border-radius: 5px;
				}
				.cko-express-checkout-container h3 {
					margin: 0 0 15px 0;
					font-size: 16px;
					color: #333;
				}
				.cko-express-checkout-buttons {
					display: flex;
					flex-direction: column;
					gap: 10px;
					align-items: center;
					justify-content: center;
				}
				.cko-express-checkout-buttons > div {
					width: 100%;
					max-width: 300px;
				}
				.cko-express-checkout-buttons #cko-apple-pay-button-wrapper-cart,
				.cko-express-checkout-buttons #cko-google-pay-button-wrapper-cart,
				.cko-express-checkout-buttons #cko-paypal-button-wrapper-cart {
					display: block !important;
				}
				.cko-express-checkout-buttons .cko-disabled {
					cursor: not-allowed;
					-webkit-filter: grayscale(100%);
					filter: grayscale(100%);
				}
			</style>
			<div class="cko-express-checkout-container">
				<h3><?php _e( 'Express Checkout', 'checkout-com-unified-payments-api' ); ?></h3>
				<div class="cko-express-checkout-buttons">
					<?php if ( $apple_pay_express_enabled && $apple_pay_available && $apple_pay_show_on_cart ) : ?>
						<div id="cko-apple-pay-button-wrapper-cart"></div>
					<?php endif; ?>
					<div id="cko-google-pay-button-wrapper-cart" style="display: block;"></div>
					<?php if ( $paypal_express_enabled && $paypal_available && $paypal_show_on_cart ) : ?>
						<div id="cko-paypal-button-wrapper-cart"></div>
					<?php endif; ?>
				</div>
			</div>
			<?php
		} elseif ( ! $container_rendered && ! WC_Checkoutcom_Utility::express_checkout_container_rendered() ) {
			// Only Google Pay is enabled, render Google Pay's own container (only if unified container wasn't already rendered)
			$container_rendered = true;
			?>
			<style>
				.cko-google-pay-cart-button {
					margin: 15px 0;
					text-align: center;
					padding: 15px;
					background: #f8f9fa;
					border: 1px solid #e9ecef;
					border-radius: 5px;
				}
				.cko-google-pay-cart-button .cko-disabled {
					cursor: not-allowed;
					-webkit-filter: grayscale(100%);
					filter: grayscale(100%);
				}
				.cko-google-pay-cart-button #cko-google-pay-button-wrapper-cart {
					display: block;
					max-width: 300px;
					margin: 0 auto;
				}
				.cko-google-pay-cart-button h3 {
					margin: 0 0 10px 0;
					font-size: 16px;
					color: #333;
				}
			</style>
			<div class="cko-google-pay-cart-button">
				<h3><?php _e( 'Express Checkout', 'checkout-com-unified-payments-api' ); ?></h3>
				<div id="cko-google-pay-button-wrapper-cart" style="display: block;"></div>
			</div>
			<?php
		} else {
			// Unified container already rendered by another express method, add Google Pay button via JavaScript
			?>
			<script>
			(function() {
				var buttonsContainer = document.querySelector('.cko-express-checkout-buttons');
				if (buttonsContainer && !document.getElementById('cko-google-pay-button-wrapper-cart')) {
					var googleWrapper = document.createElement('div');
					googleWrapper.id = 'cko-google-pay-button-wrapper-cart';
					googleWrapper.style.display = 'block';
					// Insert after Apple Pay if it exists, otherwise at the beginning
					var applePayWrapper = buttonsContainer.querySelector('#cko-apple-pay-button-wrapper-cart');
					if (applePayWrapper) {
						buttonsContainer.insertBefore(googleWrapper, applePayWrapper.nextSibling);
					} else {
						buttonsContainer.insertBefore(googleWrapper, buttonsContainer.firstChild);
					}
				}
			})();
			</script>
			<?php
		}
	}

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

		if ( ! $google_pay_session_exist ) {
			return;
		}

		ob_start();
		?>
		<p id="cko-google-pay-cancel" class="has-text-align-center">
		<?php

		printf(
		// translators: %3$ is funding source like "Google Pay", other placeholders are html tags for a link.
			esc_html__(
				'You are currently paying with Google Pay. %1$s%2$sChoose another payment method%3$s.',
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
	 * Handle cancel Google Pay express session request.
	 */
	public function express_cancel_session() {

		if (
				! isset( $_GET['cko-google-pay-session-cancel-nonce'] )
				|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['cko-google-pay-session-cancel-nonce'] ) ), 'checkoutcom_google_pay_cancel' )
		) {
			return;
		}

		self::empty_google_pay_session();
	}

	/**
	 * Empty Google Pay session variable.
	 */
	public function empty_google_pay_session() {
		WC_Checkoutcom_Utility::cko_set_session( 'cko_google_pay_order_id', '' );
		WC_Checkoutcom_Utility::cko_set_session( 'cko_gc_id', '' );
		WC_Checkoutcom_Utility::cko_set_session( 'cko_gc_details', '' );
	}

	/**
	 * Set default checkout field value from Google Pay Express.
	 *
	 * @param  string $value Default checkout field value.
	 * @param  string $key   The checkout form field name/key
	 *
	 * @return string $value Checkout field value.
	 */
	public function fill_google_pay_selected_address_field( $value, $key ) {

		if ( ! session_id() ) {
			session_start();
		}

		$cko_google_pay_order_id = WC_Checkoutcom_Utility::cko_get_session( 'cko_google_pay_order_id' );
		$cko_gc_id               = WC_Checkoutcom_Utility::cko_get_session( 'cko_gc_id' );
		$cko_gc_details          = WC_Checkoutcom_Utility::cko_get_session( 'cko_gc_details' );

		if ( empty( $cko_gc_id ) ) {
			return $value;
		}

		if ( empty( $cko_gc_details ) ) {
			try {
				$checkout       = new Checkout_SDK();
				$response       = $checkout->get_builder()->getPaymentContextsClient()->getPaymentContextDetails( $cko_gc_id );
				$cko_gc_details = $response;

				WC_Checkoutcom_Utility::cko_set_session( 'cko_gc_details', $response );

			} catch ( CheckoutApiException $ex ) {
			}
		}

		if ( isset( $cko_gc_details['payment_request']['shipping']['address'] ) ) {
			$google_pay_shipping_address    = $cko_gc_details['payment_request']['shipping']['address'];
			$shipping_name                  = $cko_gc_details['payment_request']['shipping']['first_name'] ? explode( ' ', $cko_gc_details['payment_request']['shipping']['first_name'] ) : [];
			$google_pay_shipping_first_name = $shipping_name[0] ?? '';
			$google_pay_shipping_last_name  = $shipping_name[1] ?? '';

			switch ( $key ) {
				case 'billing_first_name':
				case 'shipping_first_name':
					return $google_pay_shipping_first_name;

				case 'billing_last_name':
				case 'shipping_last_name':
					return $google_pay_shipping_last_name;

				case 'billing_address_1':
				case 'shipping_address_1':
					return $google_pay_shipping_address['address_line1'];

				case 'billing_address_2':
				case 'shipping_address_2':
					return $google_pay_shipping_address['address_line2'] ?? '';

				case 'billing_city':
				case 'shipping_city':
					return $google_pay_shipping_address['city'];

				case 'billing_postcode':
				case 'shipping_postcode':
					return $google_pay_shipping_address['zip'];

				case 'billing_country':
				case 'shipping_country':
					return $google_pay_shipping_address['country'];

				case 'billing_email':
					// For logged-in users, use their account email
					if ( is_user_logged_in() ) {
						$current_user = wp_get_current_user();
						if ( $current_user && $current_user->user_email ) {
							return $current_user->user_email;
						}
					}
					// For guest users, get email from Google Pay data
					// Check multiple possible locations
					if ( isset( $cko_gc_details['payment_request']['source']['account_holder']['email'] ) ) {
						return $cko_gc_details['payment_request']['source']['account_holder']['email'];
					}
					if ( isset( $cko_gc_details['payment_request']['shipping']['email'] ) ) {
						return $cko_gc_details['payment_request']['shipping']['email'];
					}
					if ( isset( $cko_gc_details['payment_request']['billing']['email'] ) ) {
						return $cko_gc_details['payment_request']['billing']['email'];
					}
					if ( isset( $cko_gc_details['payment_request']['payer']['email'] ) ) {
						return $cko_gc_details['payment_request']['payer']['email'];
					}
					if ( isset( $cko_gc_details['payment_request']['customer']['email'] ) ) {
						return $cko_gc_details['payment_request']['customer']['email'];
					}
					// Return original value if not found
					return $value;
			}
		}

		return $value;
	}
}

CKO_Google_Pay_Express::get_instance();

