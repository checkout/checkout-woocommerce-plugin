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
		$apple_pay_settings = get_option( 'woocommerce_wc_checkout_com_apple_pay_settings', array() );
		$google_pay_settings = get_option( 'woocommerce_wc_checkout_com_google_pay_settings', array() );
		$paypal_settings = get_option( 'woocommerce_wc_checkout_com_paypal_settings', array() );
		
		// Check if at least one express checkout method is enabled
		$apple_pay_enabled = isset( $apple_pay_settings['apple_pay_express'] ) 
			&& 'yes' === $apple_pay_settings['apple_pay_express']
			&& ! empty( $apple_pay_settings['apple_pay_express'] );
		
		$google_pay_enabled = isset( $google_pay_settings['google_pay_express'] ) 
			&& 'yes' === $google_pay_settings['google_pay_express']
			&& ! empty( $google_pay_settings['google_pay_express'] );
		
		$paypal_enabled = isset( $paypal_settings['paypal_express'] ) 
			&& 'yes' === $paypal_settings['paypal_express']
			&& ! empty( $paypal_settings['paypal_express'] );
		
		return $apple_pay_enabled || $google_pay_enabled || $paypal_enabled;
	}

	/**
	 * Check if we should show express checkout buttons on current page.
	 *
	 * @param string $page_type Page type: 'product', 'shop', 'cart', 'checkout'
	 * @return bool
	 */
	private function should_show_express_checkout_button( $page_type = 'product' ) {
		$apple_pay_settings = get_option( 'woocommerce_wc_checkout_com_apple_pay_settings', array() );
		$google_pay_settings = get_option( 'woocommerce_wc_checkout_com_google_pay_settings', array() );
		$paypal_settings = get_option( 'woocommerce_wc_checkout_com_paypal_settings', array() );
		
		// Check if at least one express method is enabled for this page type
		switch ( $page_type ) {
			case 'product':
				$apple_show = ! isset( $apple_pay_settings['apple_pay_express_product_page'] ) 
					|| ( isset( $apple_pay_settings['apple_pay_express_product_page'] ) 
						&& 'yes' === $apple_pay_settings['apple_pay_express_product_page']
						&& ! empty( $apple_pay_settings['apple_pay_express_product_page'] ) );
				$google_show = ! isset( $google_pay_settings['google_pay_express_product_page'] ) 
					|| ( isset( $google_pay_settings['google_pay_express_product_page'] ) 
						&& 'yes' === $google_pay_settings['google_pay_express_product_page']
						&& ! empty( $google_pay_settings['google_pay_express_product_page'] ) );
				$paypal_show = ! isset( $paypal_settings['paypal_express_product_page'] ) 
					|| ( isset( $paypal_settings['paypal_express_product_page'] ) 
						&& 'yes' === $paypal_settings['paypal_express_product_page']
						&& ! empty( $paypal_settings['paypal_express_product_page'] ) );
				return $apple_show || $google_show || $paypal_show;
			
			case 'shop':
				$apple_show = ! isset( $apple_pay_settings['apple_pay_express_shop_page'] ) 
					|| ( isset( $apple_pay_settings['apple_pay_express_shop_page'] ) 
						&& 'yes' === $apple_pay_settings['apple_pay_express_shop_page']
						&& ! empty( $apple_pay_settings['apple_pay_express_shop_page'] ) );
				$google_show = ! isset( $google_pay_settings['google_pay_express_shop_page'] ) 
					|| ( isset( $google_pay_settings['google_pay_express_shop_page'] ) 
						&& 'yes' === $google_pay_settings['google_pay_express_shop_page']
						&& ! empty( $google_pay_settings['google_pay_express_shop_page'] ) );
				$paypal_show = ! isset( $paypal_settings['paypal_express_shop_page'] ) 
					|| ( isset( $paypal_settings['paypal_express_shop_page'] ) 
						&& 'yes' === $paypal_settings['paypal_express_shop_page']
						&& ! empty( $paypal_settings['paypal_express_shop_page'] ) );
				return $apple_show || $google_show || $paypal_show;
			
			case 'cart':
				$apple_show = ! isset( $apple_pay_settings['apple_pay_express_cart_page'] ) 
					|| ( isset( $apple_pay_settings['apple_pay_express_cart_page'] ) 
						&& 'yes' === $apple_pay_settings['apple_pay_express_cart_page']
						&& ! empty( $apple_pay_settings['apple_pay_express_cart_page'] ) );
				$google_show = ! isset( $google_pay_settings['google_pay_express_cart_page'] ) 
					|| ( isset( $google_pay_settings['google_pay_express_cart_page'] ) 
						&& 'yes' === $google_pay_settings['google_pay_express_cart_page']
						&& ! empty( $google_pay_settings['google_pay_express_cart_page'] ) );
				$paypal_show = ! isset( $paypal_settings['paypal_express_cart_page'] ) 
					|| ( isset( $paypal_settings['paypal_express_cart_page'] ) 
						&& 'yes' === $paypal_settings['paypal_express_cart_page']
						&& ! empty( $paypal_settings['paypal_express_cart_page'] ) );
				return $apple_show || $google_show || $paypal_show;
			
			case 'checkout':
				$apple_show = ! isset( $apple_pay_settings['apple_pay_express_checkout_page'] ) 
					|| ( isset( $apple_pay_settings['apple_pay_express_checkout_page'] ) 
						&& 'yes' === $apple_pay_settings['apple_pay_express_checkout_page']
						&& ! empty( $apple_pay_settings['apple_pay_express_checkout_page'] ) );
				$google_show = ! isset( $google_pay_settings['google_pay_express_checkout_page'] ) 
					|| ( isset( $google_pay_settings['google_pay_express_checkout_page'] ) 
						&& 'yes' === $google_pay_settings['google_pay_express_checkout_page']
						&& ! empty( $google_pay_settings['google_pay_express_checkout_page'] ) );
				$paypal_show = ! isset( $paypal_settings['paypal_express_checkout_page'] ) 
					|| ( isset( $paypal_settings['paypal_express_checkout_page'] ) 
						&& 'yes' === $paypal_settings['paypal_express_checkout_page']
						&& ! empty( $paypal_settings['paypal_express_checkout_page'] ) );
				return $apple_show || $google_show || $paypal_show;
			
			default:
				return false;
		}
	}

	/**
	 * Get available express checkout methods.
	 *
	 * @param string $page_type Page type: 'product', 'shop', 'cart', 'checkout'
	 * @return array Array of available express checkout methods
	 */
	private function get_available_express_methods( $page_type = 'product' ) {
		$methods = array();
		
		$apple_pay_settings = get_option( 'woocommerce_wc_checkout_com_apple_pay_settings', array() );
		$google_pay_settings = get_option( 'woocommerce_wc_checkout_com_google_pay_settings', array() );
		$paypal_settings = get_option( 'woocommerce_wc_checkout_com_paypal_settings', array() );

		// Check Apple Pay
		$apple_pay_enabled = isset( $apple_pay_settings['apple_pay_express'] ) 
			&& 'yes' === $apple_pay_settings['apple_pay_express']
			&& ! empty( $apple_pay_settings['apple_pay_express'] );
		
		if ( $apple_pay_enabled && WC_Checkoutcom_Utility::is_apple_pay_express_available() ) {
			// Check page-specific setting
			$show_on_page = true;
			if ( 'shop' === $page_type ) {
				$show_on_page = ! isset( $apple_pay_settings['apple_pay_express_shop_page'] ) 
					|| ( isset( $apple_pay_settings['apple_pay_express_shop_page'] ) 
						&& 'yes' === $apple_pay_settings['apple_pay_express_shop_page']
						&& ! empty( $apple_pay_settings['apple_pay_express_shop_page'] ) );
			} elseif ( 'product' === $page_type ) {
				$show_on_page = ! isset( $apple_pay_settings['apple_pay_express_product_page'] ) 
					|| ( isset( $apple_pay_settings['apple_pay_express_product_page'] ) 
						&& 'yes' === $apple_pay_settings['apple_pay_express_product_page']
						&& ! empty( $apple_pay_settings['apple_pay_express_product_page'] ) );
			} elseif ( 'cart' === $page_type ) {
				$show_on_page = ! isset( $apple_pay_settings['apple_pay_express_cart_page'] ) 
					|| ( isset( $apple_pay_settings['apple_pay_express_cart_page'] ) 
						&& 'yes' === $apple_pay_settings['apple_pay_express_cart_page']
						&& ! empty( $apple_pay_settings['apple_pay_express_cart_page'] ) );
			} elseif ( 'checkout' === $page_type ) {
				$show_on_page = ! isset( $apple_pay_settings['apple_pay_express_checkout_page'] ) 
					|| ( isset( $apple_pay_settings['apple_pay_express_checkout_page'] ) 
						&& 'yes' === $apple_pay_settings['apple_pay_express_checkout_page']
						&& ! empty( $apple_pay_settings['apple_pay_express_checkout_page'] ) );
			}
			
			if ( $show_on_page ) {
				$methods['apple_pay'] = true;
			}
		}

		// Check Google Pay
		$google_pay_enabled = isset( $google_pay_settings['google_pay_express'] ) 
			&& 'yes' === $google_pay_settings['google_pay_express']
			&& ! empty( $google_pay_settings['google_pay_express'] );
		
		if ( $google_pay_enabled && WC_Checkoutcom_Utility::is_google_pay_express_available() ) {
			// Check page-specific setting
			$show_on_page = true;
			if ( 'shop' === $page_type ) {
				$show_on_page = ! isset( $google_pay_settings['google_pay_express_shop_page'] ) 
					|| ( isset( $google_pay_settings['google_pay_express_shop_page'] ) 
						&& 'yes' === $google_pay_settings['google_pay_express_shop_page']
						&& ! empty( $google_pay_settings['google_pay_express_shop_page'] ) );
			} elseif ( 'product' === $page_type ) {
				$show_on_page = ! isset( $google_pay_settings['google_pay_express_product_page'] ) 
					|| ( isset( $google_pay_settings['google_pay_express_product_page'] ) 
						&& 'yes' === $google_pay_settings['google_pay_express_product_page']
						&& ! empty( $google_pay_settings['google_pay_express_product_page'] ) );
			} elseif ( 'cart' === $page_type ) {
				$show_on_page = ! isset( $google_pay_settings['google_pay_express_cart_page'] ) 
					|| ( isset( $google_pay_settings['google_pay_express_cart_page'] ) 
						&& 'yes' === $google_pay_settings['google_pay_express_cart_page']
						&& ! empty( $google_pay_settings['google_pay_express_cart_page'] ) );
			} elseif ( 'checkout' === $page_type ) {
				$show_on_page = ! isset( $google_pay_settings['google_pay_express_checkout_page'] ) 
					|| ( isset( $google_pay_settings['google_pay_express_checkout_page'] ) 
						&& 'yes' === $google_pay_settings['google_pay_express_checkout_page']
						&& ! empty( $google_pay_settings['google_pay_express_checkout_page'] ) );
			}
			
			if ( $show_on_page ) {
				$methods['google_pay'] = true;
			}
		}

		// Check PayPal
		$paypal_enabled = isset( $paypal_settings['paypal_express'] ) 
			&& 'yes' === $paypal_settings['paypal_express']
			&& ! empty( $paypal_settings['paypal_express'] );
		
		if ( $paypal_enabled && WC_Checkoutcom_Utility::is_paypal_express_available() ) {
			// Check page-specific setting
			$show_on_page = true;
			if ( 'shop' === $page_type ) {
				$show_on_page = ! isset( $paypal_settings['paypal_express_shop_page'] ) 
					|| ( isset( $paypal_settings['paypal_express_shop_page'] ) 
						&& 'yes' === $paypal_settings['paypal_express_shop_page']
						&& ! empty( $paypal_settings['paypal_express_shop_page'] ) );
			} elseif ( 'product' === $page_type ) {
				$show_on_page = ! isset( $paypal_settings['paypal_express_product_page'] ) 
					|| ( isset( $paypal_settings['paypal_express_product_page'] ) 
						&& 'yes' === $paypal_settings['paypal_express_product_page']
						&& ! empty( $paypal_settings['paypal_express_product_page'] ) );
			} elseif ( 'cart' === $page_type ) {
				$show_on_page = ! isset( $paypal_settings['paypal_express_cart_page'] ) 
					|| ( isset( $paypal_settings['paypal_express_cart_page'] ) 
						&& 'yes' === $paypal_settings['paypal_express_cart_page']
						&& ! empty( $paypal_settings['paypal_express_cart_page'] ) );
			} elseif ( 'checkout' === $page_type ) {
				$show_on_page = ! isset( $paypal_settings['paypal_express_checkout_page'] ) 
					|| ( isset( $paypal_settings['paypal_express_checkout_page'] ) 
						&& 'yes' === $paypal_settings['paypal_express_checkout_page']
						&& ! empty( $paypal_settings['paypal_express_checkout_page'] ) );
			}
			
			if ( $show_on_page ) {
				$methods['paypal'] = true;
			}
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

		$methods = $this->get_available_express_methods( 'product' );
		if ( empty( $methods ) ) {
			return;
		}

		// Use existing unified container from individual classes (they handle product page)
		// This method is kept for compatibility but won't render to avoid duplicates
		return;
	}

	/**
	 * Display express checkout button HTML on shop/listing pages.
	 * Compact horizontal layout for space efficiency.
	 */
	public function display_shop_express_checkout_button_html() {
		global $product;
		
		// Don't show for variable products on shop pages (too complex)
		if ( $product && $product->is_type( 'variable' ) ) {
			return;
		}
		
		if ( ! ( is_shop() || is_product_category() || is_product_tag() || is_product_taxonomy() ) ) {
			return;
		}
		
		if ( ! $this->should_show_express_checkout_button( 'shop' ) ) {
			return;
		}

		$methods = $this->get_available_express_methods( 'shop' );
		if ( empty( $methods ) ) {
			return;
		}

		$product_id = $product ? $product->get_id() : 0;
		if ( ! $product_id ) {
			return;
		}
		
		// Use product-specific container key to prevent duplicates
		static $rendered_products = array();
		$container_key = 'shop_' . $product_id;
		
		if ( isset( $rendered_products[ $container_key ] ) ) {
			return; // Already rendered for this product
		}
		
		$rendered_products[ $container_key ] = true;

		?>
		<div class="cko-express-checkout-shop-compact" data-product-id="<?php echo esc_attr( $product_id ); ?>">
			<?php if ( isset( $methods['apple_pay'] ) ) : ?>
				<div id="cko-apple-pay-button-wrapper-<?php echo esc_attr( $product_id ); ?>" data-product-id="<?php echo esc_attr( $product_id ); ?>"></div>
			<?php endif; ?>
			<?php if ( isset( $methods['google_pay'] ) ) : ?>
				<div id="cko-google-pay-button-wrapper-<?php echo esc_attr( $product_id ); ?>" data-product-id="<?php echo esc_attr( $product_id ); ?>"></div>
			<?php endif; ?>
			<?php if ( isset( $methods['paypal'] ) ) : ?>
				<div id="cko-paypal-button-wrapper-<?php echo esc_attr( $product_id ); ?>" data-product-id="<?php echo esc_attr( $product_id ); ?>"></div>
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

		// Use existing unified container from individual classes (they handle cart page)
		// This method is kept for compatibility but won't render to avoid duplicates
		return;
	}

	/**
	 * Display express checkout button HTML on checkout page.
	 */
	public function display_checkout_express_checkout_button_html() {
		if ( ! is_checkout() || ! $this->should_show_express_checkout_button( 'checkout' ) ) {
			return;
		}

		$methods = $this->get_available_express_methods( 'checkout' );
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
	}
}

