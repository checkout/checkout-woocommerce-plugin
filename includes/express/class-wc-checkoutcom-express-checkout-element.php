<?php
/**
 * Unified Express Checkout Element Handler
 * 
 * Similar to Stripe's approach - handles all express checkout buttons (Apple Pay, Google Pay, PayPal)
 * in a unified way to avoid rendering and sizing issues.
 *
 * @package wc_checkout_com
 */

defined( 'ABSPATH' ) || exit;

/**
 * WC_Checkoutcom_Express_Checkout_Element class.
 */
class WC_Checkoutcom_Express_Checkout_Element {

	/**
	 * This Instance.
	 *
	 * @var WC_Checkoutcom_Express_Checkout_Element
	 */
	private static $_this;

	/**
	 * Constructor.
	 */
	public function __construct() {
		self::$_this = $this;
	}

	/**
	 * Get this instance.
	 *
	 * @return WC_Checkoutcom_Express_Checkout_Element
	 */
	public static function instance() {
		return self::$_this;
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init() {
		// Check if express checkout is enabled
		if ( ! $this->is_express_checkout_enabled() ) {
			return;
		}

		// Add hooks for button display
		add_action( 'woocommerce_after_add_to_cart_form', [ $this, 'display_express_checkout_button_html' ], 1 );
		add_action( 'woocommerce_after_shop_loop_item', [ $this, 'display_shop_express_checkout_button_html' ], 15 );
		add_action( 'woocommerce_proceed_to_checkout', [ $this, 'display_cart_express_checkout_button_html' ], 5 );
		add_action( 'woocommerce_blocks_cart_block_render', [ $this, 'display_cart_express_checkout_button_html' ], 10 );
		add_action( 'woocommerce_checkout_before_customer_details', [ $this, 'display_checkout_express_checkout_button_html' ], 1 );

		// Enqueue scripts
		add_action( 'wp_enqueue_scripts', [ $this, 'scripts' ] );
	}

	/**
	 * Check if express checkout is enabled.
	 *
	 * @return bool
	 */
	private function is_express_checkout_enabled() {
		$cards_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings', array() );
		
		// Check if express checkout master toggle is enabled
		return isset( $cards_settings['apple_pay_express'] ) 
			&& 'yes' === $cards_settings['apple_pay_express'];
	}

	/**
	 * Check if we should show express checkout buttons on current page.
	 *
	 * @param string $page_type Page type: 'product', 'shop', 'cart', 'checkout'
	 * @return bool
	 */
	private function should_show_express_checkout_button( $page_type = 'product' ) {
		$cards_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings', array() );
		
		// Check page-specific settings
		switch ( $page_type ) {
			case 'product':
				return ! isset( $cards_settings['apple_pay_express_product_page'] ) 
					|| 'yes' === $cards_settings['apple_pay_express_product_page'];
			
			case 'shop':
				return ! isset( $cards_settings['apple_pay_express_product_page'] ) 
					|| 'yes' === $cards_settings['apple_pay_express_product_page'];
			
			case 'cart':
				return ! isset( $cards_settings['apple_pay_express_cart_page'] ) 
					|| 'yes' === $cards_settings['apple_pay_express_cart_page'];
			
			case 'checkout':
				return ! isset( $cards_settings['apple_pay_express_checkout_page'] ) 
					|| 'yes' === $cards_settings['apple_pay_express_checkout_page'];
			
			default:
				return false;
		}
	}

	/**
	 * Get available express checkout methods.
	 *
	 * @return array Array of available express checkout methods
	 */
	private function get_available_express_methods() {
		$methods = array();

		// Check Apple Pay
		if ( WC_Checkoutcom_Utility::is_apple_pay_express_available() ) {
			$methods['apple_pay'] = true;
		}

		// Check Google Pay
		if ( WC_Checkoutcom_Utility::is_google_pay_express_available() ) {
			$methods['google_pay'] = true;
		}

		// Check PayPal
		if ( WC_Checkoutcom_Utility::is_paypal_express_available() ) {
			$methods['paypal'] = true;
		}

		return $methods;
	}

	/**
	 * Display express checkout button HTML on product page.
	 */
	public function display_express_checkout_button_html() {
		if ( ! is_product() || ! $this->should_show_express_checkout_button( 'product' ) ) {
			return;
		}

		$methods = $this->get_available_express_methods();
		if ( empty( $methods ) ) {
			return;
		}

		?>
		<div id="cko-express-checkout-element" style="margin-top: 1em; clear: both;">
			<?php if ( isset( $methods['apple_pay'] ) ) : ?>
				<div id="cko-apple-pay-button-wrapper"></div>
			<?php endif; ?>
			<?php if ( isset( $methods['google_pay'] ) ) : ?>
				<div id="cko-google-pay-button-wrapper"></div>
			<?php endif; ?>
			<?php if ( isset( $methods['paypal'] ) ) : ?>
				<div id="cko-paypal-button-wrapper"></div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Display express checkout button HTML on shop/listing pages.
	 */
	public function display_shop_express_checkout_button_html() {
		if ( ! $this->should_show_express_checkout_button( 'shop' ) ) {
			return;
		}

		$methods = $this->get_available_express_methods();
		if ( empty( $methods ) ) {
			return;
		}

		global $product;
		$product_id = $product ? $product->get_id() : 0;
		if ( ! $product_id ) {
			return;
		}

		?>
		<div class="cko-express-checkout-shop-button" data-product-id="<?php echo esc_attr( $product_id ); ?>">
			<?php if ( isset( $methods['apple_pay'] ) ) : ?>
				<div id="cko-apple-pay-button-wrapper-<?php echo esc_attr( $product_id ); ?>"></div>
			<?php endif; ?>
			<?php if ( isset( $methods['google_pay'] ) ) : ?>
				<div id="cko-google-pay-button-wrapper-<?php echo esc_attr( $product_id ); ?>"></div>
			<?php endif; ?>
			<?php if ( isset( $methods['paypal'] ) ) : ?>
				<div id="cko-paypal-button-wrapper-<?php echo esc_attr( $product_id ); ?>"></div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Display express checkout button HTML on cart page.
	 */
	public function display_cart_express_checkout_button_html() {
		if ( ! is_cart() || ! $this->should_show_express_checkout_button( 'cart' ) ) {
			return;
		}

		// Don't show if cart is empty
		if ( WC()->cart->is_empty() ) {
			return;
		}

		$methods = $this->get_available_express_methods();
		if ( empty( $methods ) ) {
			return;
		}

		?>
		<div id="cko-express-checkout-element-cart">
			<?php if ( isset( $methods['apple_pay'] ) ) : ?>
				<div id="cko-apple-pay-button-wrapper-cart"></div>
			<?php endif; ?>
			<?php if ( isset( $methods['google_pay'] ) ) : ?>
				<div id="cko-google-pay-button-wrapper-cart"></div>
			<?php endif; ?>
			<?php if ( isset( $methods['paypal'] ) ) : ?>
				<div id="cko-paypal-button-wrapper-cart"></div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Display express checkout button HTML on checkout page.
	 */
	public function display_checkout_express_checkout_button_html() {
		if ( ! is_checkout() || ! $this->should_show_express_checkout_button( 'checkout' ) ) {
			return;
		}

		$methods = $this->get_available_express_methods();
		if ( empty( $methods ) ) {
			return;
		}

		?>
		<div id="cko-express-checkout-element-checkout">
			<?php if ( isset( $methods['apple_pay'] ) ) : ?>
				<div id="cko-apple-pay-button-wrapper-checkout"></div>
			<?php endif; ?>
			<?php if ( isset( $methods['google_pay'] ) ) : ?>
				<div id="cko-google-pay-button-wrapper-checkout"></div>
			<?php endif; ?>
			<?php if ( isset( $methods['paypal'] ) ) : ?>
				<div id="cko-paypal-button-wrapper-checkout"></div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Enqueue scripts and styles.
	 */
	public function scripts() {
		// Only load on supported pages
		if ( ! is_product() && ! is_shop() && ! is_cart() && ! is_checkout() ) {
			return;
		}

		// Enqueue minimal CSS - let SDKs handle their own styling
		wp_add_inline_style( 'woocommerce-general', '
			#cko-express-checkout-element,
			#cko-express-checkout-element-cart,
			#cko-express-checkout-element-checkout,
			.cko-express-checkout-shop-button {
				margin-top: 1em;
				clear: both;
			}
			.cko-disabled {
				cursor: not-allowed;
				opacity: 0.5;
			}
		' );
	}
}

