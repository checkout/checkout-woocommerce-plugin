<?php
/**
 * Plugin Name: Checkout.com Payment Gateway
 * Plugin URI: https://www.checkout.com/
 * Description: Extends WooCommerce by Adding the Checkout.com Gateway.
 * Author: Checkout.com
 * Author URI: https://www.checkout.com/
 * Version: 5.0.1
 * Requires at least: 5.0
 * Tested up to: 6.7.0
 * WC requires at least: 3.0
 * WC tested up to: 8.3.1
 * Requires PHP: 7.3
 * Text Domain: checkout-com-unified-payments-api
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * IMPORTANT: Plugin Update Compatibility
 * WordPress identifies plugins by these three identifiers (must match existing installation):
 * 1. Plugin folder name: checkout-com-unified-payments-api
 * 2. Main plugin file: woocommerce-gateway-checkout-com.php
 * 3. Plugin Name header: Checkout.com Payment Gateway
 * 
 * These identifiers ensure the plugin updates over existing installations instead of creating duplicates.
 *
 * @package wc_checkout_com
 */

// use Checkout\CheckoutUtils;
// use Checkout\Payments\PaymentType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load Composer autoloader for Checkout.com SDK
$autoloader_path = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $autoloader_path ) ) {
	require_once $autoloader_path;
	// Verify SDK classes are available (only log if WP_DEBUG is enabled)
	if ( ! class_exists( 'Checkout\CheckoutSdk' ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'Checkout.com SDK classes not loaded after autoloader inclusion. Path: ' . esc_html( $autoloader_path ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	error_log( 'Checkout.com SDK autoloader not found at: ' . esc_html( $autoloader_path ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
}

// Load utility class early (needed for logging in early hooks)
$utility_path = __DIR__ . '/includes/api/class-wc-checkoutcom-utility.php';
if ( file_exists( $utility_path ) ) {
	require_once $utility_path;
}

add_filter( 'woocommerce_checkout_registration_enabled', '__return_true' );

/**
 * Handler for cleanup of old webhooks.
 */
function cko_cleanup_old_webhooks_handler() {
	if ( class_exists( 'WC_Checkout_Com_Webhook_Queue' ) ) {
		// Cleanup processed webhooks older than 7 days
		WC_Checkout_Com_Webhook_Queue::cleanup_old_webhooks( 7 );
		// Cleanup unprocessed webhooks older than 7 days (orphaned)
		WC_Checkout_Com_Webhook_Queue::cleanup_old_unprocessed_webhooks( 7 );
	}
}

/**
 * Force Flow gateway to be available if checkout mode is 'flow'.
 *
 * @param array $available_gateways Available payment gateways.
 * @return array Filtered available payment gateways.
 */
function cko_force_flow_gateway_available( $available_gateways ) {
	// Process on checkout page, order-pay page, and during checkout processing (when POST data exists)
	$is_checkout_context = is_checkout() || is_wc_endpoint_url( 'order-pay' ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( isset( $_POST['payment_method'] ) && 'wc_checkout_com_flow' === $_POST['payment_method'] );
	
	if ( $is_checkout_context ) {
		$checkout_setting = get_option( 'woocommerce_wc_checkout_com_cards_settings', array() );
		$checkout_mode = isset( $checkout_setting['ckocom_checkout_mode'] ) ? $checkout_setting['ckocom_checkout_mode'] : 'classic';
		
		if ( 'flow' === $checkout_mode ) {
			// Log environment versions for debugging (only once per request to avoid spam)
			static $version_logged = false;
			if ( ! $version_logged && ( is_checkout() || is_wc_endpoint_url( 'order-pay' ) ) ) {
				global $wp_version;
				WC_Checkoutcom_Utility::logger( '[FLOW DEBUG] ========== ENVIRONMENT INFO ==========' );
				WC_Checkoutcom_Utility::logger( '[FLOW DEBUG] WordPress Version: ' . ( isset( $wp_version ) ? $wp_version : 'UNKNOWN' ) );
				WC_Checkoutcom_Utility::logger( '[FLOW DEBUG] WooCommerce Version: ' . ( defined( 'WC_VERSION' ) ? WC_VERSION : ( function_exists( 'WC' ) && method_exists( WC(), 'version' ) ? WC()->version : 'UNKNOWN' ) ) );
				WC_Checkoutcom_Utility::logger( '[FLOW DEBUG] PHP Version: ' . PHP_VERSION );
				WC_Checkoutcom_Utility::logger( '[FLOW DEBUG] ========================================' );
				$version_logged = true;
			}
			
			// Log during checkout processing
			if ( isset( $_POST['payment_method'] ) && 'wc_checkout_com_flow' === $_POST['payment_method'] ) {
				WC_Checkoutcom_Utility::logger( '[FLOW DEBUG] ========== CHECKOUT PROCESSING ==========' );
				WC_Checkoutcom_Utility::logger( '[FLOW DEBUG] Payment method in POST: ' . sanitize_text_field( $_POST['payment_method'] ) );
				WC_Checkoutcom_Utility::logger( '[FLOW DEBUG] Available payment gateways count: ' . count( $available_gateways ) );
			}
			
			// Always ensure Flow gateway is in the list if checkout mode is 'flow' and gateway is enabled
			// This bypasses ALL other checks (country, currency, etc.) to ensure Flow is always available
			$all_gateways = WC()->payment_gateways()->payment_gateways();
			if ( isset( $all_gateways['wc_checkout_com_flow'] ) ) {
				$flow_gateway = $all_gateways['wc_checkout_com_flow'];
				
				// Check if gateway is enabled (basic check)
				$is_enabled = isset( $flow_gateway->enabled ) && 'yes' === $flow_gateway->enabled;
				
				if ( $is_enabled ) {
					// Force add Flow gateway to available list REGARDLESS of other checks
					// This ensures Flow is always available when enabled and checkout mode is 'flow'
					$available_gateways['wc_checkout_com_flow'] = $flow_gateway;
					
					if ( isset( $_POST['payment_method'] ) && 'wc_checkout_com_flow' === $_POST['payment_method'] ) {
						WC_Checkoutcom_Utility::logger( '[FLOW DEBUG] FORCING Flow gateway into available gateways list!' );
						WC_Checkoutcom_Utility::logger( '[FLOW DEBUG] Flow gateway enabled: ' . ( $is_enabled ? 'YES' : 'NO' ) );
						WC_Checkoutcom_Utility::logger( '[FLOW DEBUG] Flow gateway is_available() result: ' . ( method_exists( $flow_gateway, 'is_available' ) ? ( $flow_gateway->is_available() ? 'TRUE' : 'FALSE' ) : 'METHOD NOT FOUND' ) );
						if ( method_exists( $flow_gateway, 'valid_for_use' ) ) {
							WC_Checkoutcom_Utility::logger( '[FLOW DEBUG] Flow gateway valid_for_use() result: ' . ( $flow_gateway->valid_for_use() ? 'TRUE' : 'FALSE' ) );
						}
					}
				} else {
					if ( isset( $_POST['payment_method'] ) && 'wc_checkout_com_flow' === $_POST['payment_method'] ) {
						WC_Checkoutcom_Utility::logger( '[FLOW DEBUG] Flow gateway is NOT enabled - not adding to available list' );
					}
				}
			} else {
				if ( isset( $_POST['payment_method'] ) && 'wc_checkout_com_flow' === $_POST['payment_method'] ) {
					WC_Checkoutcom_Utility::logger( '[FLOW DEBUG] ERROR: Flow gateway does NOT exist in all gateways!' );
				}
			}
			
			// Log final state
			if ( isset( $_POST['payment_method'] ) && 'wc_checkout_com_flow' === $_POST['payment_method'] ) {
				if ( isset( $available_gateways['wc_checkout_com_flow'] ) ) {
					WC_Checkoutcom_Utility::logger( '[FLOW DEBUG] SUCCESS: Flow gateway IS in available gateways list!' );
				} else {
					WC_Checkoutcom_Utility::logger( '[FLOW DEBUG] CRITICAL: Flow gateway NOT in available gateways list after filter!' );
				}
			}
		}
	}
	return $available_gateways;
}
add_filter( 'woocommerce_available_payment_gateways', 'cko_force_flow_gateway_available', 1 );

/**
 * Backup filter to ensure Flow gateway is added AFTER WooCommerce's internal filtering.
 *
 * @param array $available_gateways Available payment gateways.
 * @return array Filtered available payment gateways.
 */
function cko_backup_force_flow_gateway_available( $available_gateways ) {
	// Only process if checkout mode is 'flow' and gateway is not already in list
	$checkout_setting = get_option( 'woocommerce_wc_checkout_com_cards_settings', array() );
	$checkout_mode = isset( $checkout_setting['ckocom_checkout_mode'] ) ? $checkout_setting['ckocom_checkout_mode'] : 'classic';
	
	if ( 'flow' === $checkout_mode && ! isset( $available_gateways['wc_checkout_com_flow'] ) ) {
		$all_gateways = WC()->payment_gateways()->payment_gateways();
		if ( isset( $all_gateways['wc_checkout_com_flow'] ) ) {
			$flow_gateway = $all_gateways['wc_checkout_com_flow'];
			if ( isset( $flow_gateway->enabled ) && 'yes' === $flow_gateway->enabled ) {
				// Force add Flow gateway - this is a backup in case it was removed by WooCommerce or other plugins
				$available_gateways['wc_checkout_com_flow'] = $flow_gateway;
				if ( isset( $_POST['payment_method'] ) && 'wc_checkout_com_flow' === $_POST['payment_method'] ) {
					WC_Checkoutcom_Utility::logger( '[FLOW DEBUG] BACKUP FILTER: Re-adding Flow gateway at priority 999' );
				}
			}
		}
	}
	
	return $available_gateways;
}
add_filter( 'woocommerce_available_payment_gateways', 'cko_backup_force_flow_gateway_available', 999 );

/**
 * Log before checkout process starts for Flow payments.
 */
function cko_log_before_checkout_process() {
	try {
		if ( isset( $_POST['payment_method'] ) && 'wc_checkout_com_flow' === $_POST['payment_method'] ) {
			WC_Checkoutcom_Utility::logger( '[FLOW SERVER] ========== BEFORE CHECKOUT PROCESS ==========' );
			WC_Checkoutcom_Utility::logger( '[FLOW SERVER] Payment method in POST: ' . sanitize_text_field( $_POST['payment_method'] ) );
			
			// Check available gateways at this point to see if our filter is working
			if ( function_exists( 'WC' ) && WC() && method_exists( WC(), 'payment_gateways' ) ) {
				$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
				WC_Checkoutcom_Utility::logger( '[FLOW SERVER] Available gateways before validation: ' . count( $available_gateways ) );
				if ( isset( $available_gateways['wc_checkout_com_flow'] ) ) {
					WC_Checkoutcom_Utility::logger( '[FLOW SERVER] SUCCESS: Flow gateway IS available before validation' );
				} else {
					WC_Checkoutcom_Utility::logger( '[FLOW SERVER] WARNING: Flow gateway NOT available before validation' );
				}
			}
		}
	} catch ( Exception $e ) {
		WC_Checkoutcom_Utility::logger( '[FLOW SERVER] ERROR in before_checkout_process hook: ' . $e->getMessage() );
		WC_Checkoutcom_Utility::logger( '[FLOW SERVER] ERROR stack trace: ' . $e->getTraceAsString() );
	}
}
add_action( 'woocommerce_before_checkout_process', 'cko_log_before_checkout_process', 1 );

/**
 * Log when WooCommerce validates payment method during checkout processing.
 */
function cko_log_checkout_process() {
	try {
		if ( isset( $_POST['payment_method'] ) && 'wc_checkout_com_flow' === $_POST['payment_method'] ) {
			WC_Checkoutcom_Utility::logger( '[FLOW SERVER] ========== WOOCOMMERCE CHECKOUT PROCESS ==========' );
			WC_Checkoutcom_Utility::logger( '[FLOW SERVER] Payment method in POST: ' . sanitize_text_field( $_POST['payment_method'] ) );
			
			// Check if WooCommerce is available
			if ( ! function_exists( 'WC' ) || ! WC() ) {
				WC_Checkoutcom_Utility::logger( '[FLOW SERVER] WARNING: WooCommerce not available in checkout_process hook' );
				return;
			}
			
			// Check if payment gateways is available
			if ( ! method_exists( WC(), 'payment_gateways' ) ) {
				WC_Checkoutcom_Utility::logger( '[FLOW SERVER] WARNING: payment_gateways() method not available' );
				return;
			}
			
			// Check available gateways at this point
			$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
			WC_Checkoutcom_Utility::logger( '[FLOW SERVER] Available payment gateways count: ' . count( $available_gateways ) );
			
			if ( isset( $available_gateways['wc_checkout_com_flow'] ) ) {
				WC_Checkoutcom_Utility::logger( '[FLOW SERVER] SUCCESS: Flow gateway IS available during checkout process' );
			} else {
				WC_Checkoutcom_Utility::logger( '[FLOW SERVER] ERROR: Flow gateway is NOT available during checkout process!' );
				WC_Checkoutcom_Utility::logger( '[FLOW SERVER] This is a SERVER-SIDE (PHP) validation error' );
				WC_Checkoutcom_Utility::logger( '[FLOW SERVER] Available gateways: ' . implode( ', ', array_keys( $available_gateways ) ) );
			}
		}
	} catch ( Exception $e ) {
		WC_Checkoutcom_Utility::logger( '[FLOW SERVER] ERROR in checkout_process hook: ' . $e->getMessage() );
		WC_Checkoutcom_Utility::logger( '[FLOW SERVER] ERROR stack trace: ' . $e->getTraceAsString() );
		// Don't throw - just log the error to prevent breaking checkout
	}
}
add_action( 'woocommerce_checkout_process', 'cko_log_checkout_process', 5 );

/**
 * Update order ID in session after order creation for Classic Cards payments.
 *
 * @param int $order_id Order ID.
 */
function cko_update_order_id_in_session( $order_id ) {
	// Only apply to Classic Cards payment method
	if ( ! isset( $_POST['payment_method'] ) || 'wc_checkout_com_cards' !== wp_unslash( $_POST['payment_method'] ) ) {
		return;
	}
	
	// Check if there's a different order ID in session (meaning order was replaced)
	if ( WC()->session ) {
		$session_order_id = WC()->session->get( 'order_awaiting_payment' );
		if ( ! empty( $session_order_id ) && $session_order_id != $order_id ) {
			WC_Checkoutcom_Utility::logger( '[CLASSIC CARDS] Order ID mismatch detected - Session: ' . $session_order_id . ', New Order: ' . $order_id . ' - Order was likely replaced with existing one' );
		}
	}
}
add_action( 'woocommerce_new_order', 'cko_update_order_id_in_session', 5 );

/**
 * Constants.
 */
define( 'WC_CHECKOUTCOM_PLUGIN_VERSION', '5.0.1' );
define( 'WC_CHECKOUTCOM_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'WC_CHECKOUTCOM_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

/**
 * This function registers our PHP class as a WooCommerce payment gateway.
 */
if ( ! function_exists( 'init_checkout_com_gateway_class' ) ) {
add_action( 'plugins_loaded', 'init_checkout_com_gateway_class', 0 );
	function init_checkout_com_gateway_class() {
		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return;
		}

		load_plugin_textdomain( 'checkout-com-unified-payments-api', false, plugin_basename( __DIR__ ) . '/languages' );

		// Core payment gateway classes
		include_once 'includes/class-wc-checkout-com-webhook.php';
		include_once 'includes/class-wc-checkout-com-webhook-queue.php';
		
		// Admin pages
		if ( is_admin() ) {
			include_once 'includes/admin/class-wc-checkoutcom-webhook-queue-admin.php';
			include_once 'includes/admin/class-wc-checkoutcom-diagnostics.php';
		}
		
		// Note: You can also access the webhook queue table directly using:
		// - view-webhook-queue.php script (command line or browser)
		// - WordPress Admin: WooCommerce > Webhook Queue
		// - Direct SQL queries to wp_cko_pending_webhooks table
		
		// Create webhook queue table if it doesn't exist
		if ( class_exists( 'WC_Checkout_Com_Webhook_Queue' ) ) {
			WC_Checkout_Com_Webhook_Queue::create_table();
			
			// Schedule cleanup of old webhooks (daily)
			if ( ! wp_next_scheduled( 'cko_cleanup_old_webhooks' ) ) {
				wp_schedule_event( time(), 'daily', 'cko_cleanup_old_webhooks' );
			}
		}
		
		// Hook for cleanup of old webhooks
		add_action( 'cko_cleanup_old_webhooks', 'cko_cleanup_old_webhooks_handler' );
		
		include_once 'includes/class-wc-gateway-checkout-com-cards.php';
		include_once 'includes/class-wc-gateway-checkout-com-apple-pay.php';
		
		// Register Apple Pay CSR generation AJAX handler early
		// Use a wrapper function to ensure it's always available
		if ( class_exists( 'WC_Gateway_Checkout_Com_Apple_Pay' ) ) {
		add_action( 'wp_ajax_cko_generate_apple_pay_csr', 'cko_ajax_generate_apple_pay_csr' );
		add_action( 'wp_ajax_cko_upload_apple_pay_certificate', 'cko_ajax_upload_apple_pay_certificate' );
		add_action( 'wp_ajax_cko_generate_apple_pay_merchant_certificate', 'cko_ajax_generate_apple_pay_merchant_certificate' );
		add_action( 'wp_ajax_cko_upload_apple_pay_domain_association', 'cko_ajax_upload_apple_pay_domain_association' );
		add_action( 'wp_ajax_cko_generate_apple_pay_merchant_identity_csr', 'cko_ajax_generate_apple_pay_merchant_identity_csr' );
		add_action( 'wp_ajax_cko_upload_apple_pay_merchant_identity_certificate', 'cko_ajax_upload_apple_pay_merchant_identity_certificate' );
		add_action( 'wp_ajax_cko_test_apple_pay_certificate', 'cko_ajax_test_apple_pay_certificate' );
	}
	
		include_once 'includes/class-wc-gateway-checkout-com-google-pay.php';
		include_once 'includes/class-wc-gateway-checkout-com-paypal.php';
		include_once 'includes/class-wc-gateway-checkout-com-alternative-payments.php';
		
		// Flow integration
		include_once 'flow-integration/class-wc-gateway-checkout-com-flow.php';
		
		// Unified Express Checkout Element Handler
		include_once 'includes/express/class-wc-checkoutcom-express-checkout-element.php';
		
		// Initialize unified express checkout element
		if ( class_exists( 'WC_Checkoutcom_Express_Checkout_Element' ) ) {
			$express_checkout_element = new WC_Checkoutcom_Express_Checkout_Element();
			$express_checkout_element->init();
		}

		// Enhanced logging classes (commented out temporarily)
		// include_once 'includes/logging/class-wc-checkoutcom-enhanced-logger.php';
		// include_once 'includes/logging/class-wc-checkoutcom-log-manager.php';
		// include_once 'includes/logging/class-wc-checkoutcom-performance-monitor.php';
		// include_once 'includes/settings/class-wc-checkoutcom-logging-settings.php';

		// Initialize enhanced logging (commented out temporarily)
		// WC_Checkoutcom_Logging_Settings::init();

		// WooCommerce Blocks integration (safe/conditional)
		// Check for safe version first, fallback to regular version
		$blocks_safe_file = __DIR__ . '/includes/blocks/class-wc-checkoutcom-blocks-integration-safe.php';
		$blocks_file = __DIR__ . '/includes/blocks/class-wc-checkoutcom-blocks-integration.php';
		
		if ( file_exists( $blocks_safe_file ) ) {
			include_once $blocks_safe_file;
		} elseif ( file_exists( $blocks_file ) ) {
			include_once $blocks_file;
		} else {
			// Log error but don't break the site - Blocks integration is optional
			error_log( 'Checkout.com Blocks integration file not found. Expected: ' . $blocks_file );
		}

		// Load payment gateway class.
		add_filter( 'woocommerce_payment_gateways', 'checkout_com_add_gateway' );
	}
}

/**
 * Make billing details read-only on order-pay page
 * This ensures customers can't modify billing information that was set when the order was created
 */
if ( ! function_exists( 'cko_make_order_pay_billing_readonly' ) ) {
add_action( 'wp_enqueue_scripts', 'cko_make_order_pay_billing_readonly' );
function cko_make_order_pay_billing_readonly() {
	// Only on order-pay page
	if ( ! is_wc_endpoint_url( 'order-pay' ) ) {
		return;
	}
	
	// Add CSS to make billing details read-only
	wp_add_inline_style( 'woocommerce-general', '
		.woocommerce-order-pay #billing_first_name, 
		.woocommerce-order-pay #billing_last_name, 
		.woocommerce-order-pay #billing_company,
		.woocommerce-order-pay #billing_address_1, 
		.woocommerce-order-pay #billing_address_2, 
		.woocommerce-order-pay #billing_city,
		.woocommerce-order-pay #billing_state, 
		.woocommerce-order-pay #billing_postcode, 
		.woocommerce-order-pay #billing_country,
		.woocommerce-order-pay #billing_phone, 
		.woocommerce-order-pay #billing_email,
		.woocommerce-order-pay .woocommerce-billing-fields input,
		.woocommerce-order-pay .woocommerce-billing-fields select,
		.woocommerce-order-pay .woocommerce-billing-fields textarea {
			background-color: #f9f9f9 !important;
			border: 1px solid #ddd !important;
			color: #666 !important;
			cursor: not-allowed !important;
			pointer-events: none !important;
			opacity: 0.7 !important;
		}
		.woocommerce-order-pay .woocommerce-billing-fields label {
			color: #666 !important;
			font-weight: normal !important;
		}
		.woocommerce-order-pay .woocommerce-billing-fields h3::after {
			content: " (from your order - read only)";
			font-size: 12px;
			font-weight: normal;
			color: #999;
		}
	' );
	
	// Add JavaScript to disable billing fields
	wp_add_inline_script( 'wc-checkout', '
		jQuery(document).ready(function($) {
			console.log("🔥🔥🔥 [CKO DEBUG] Order-pay page detected - making billing fields read-only 🔥🔥🔥");
			
			// Disable all billing fields
			var billingFields = $("#billing_first_name, #billing_last_name, #billing_company, #billing_address_1, #billing_address_2, #billing_city, #billing_state, #billing_postcode, #billing_country, #billing_phone, #billing_email");
			console.log("[CKO DEBUG] Found billing fields:", billingFields.length);
			billingFields.prop("disabled", true);
			
			// Also disable any selects in billing fields
			var billingSelects = $(".woocommerce-billing-fields select");
			console.log("[CKO DEBUG] Found billing selects:", billingSelects.length);
			billingSelects.prop("disabled", true);
			
			console.log("[CKO DEBUG] Billing fields disabled successfully on order-pay page");
		});
	' );
	}
}



/**
 * Declare compatibility with HPOS.
 * https://github.com/woocommerce/woocommerce/wiki/High-Performance-Order-Storage-Upgrade-Recipe-Book#declaring-extension-incompatibility
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);


/**
 * AJAX handler wrapper for Apple Pay CSR generation.
 * This ensures the handler is always available, even if the gateway class isn't fully instantiated.
 */
if ( ! function_exists( 'cko_ajax_generate_apple_pay_csr' ) ) {
	function cko_ajax_generate_apple_pay_csr() {
		// Capability check for admin-only AJAX handler
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 
				'message' => __( 'Permission denied.', 'checkout-com-unified-payments-api' ),
			] );
			return;
		}

		if ( ! class_exists( 'WC_Gateway_Checkout_Com_Apple_Pay' ) ) {
			wp_send_json_error( [ 
				'message' => __( 'Apple Pay gateway class not found.', 'checkout-com-unified-payments-api' ),
			] );
			return;
		}

		// Get the gateway instance
		$gateways = WC()->payment_gateways()->payment_gateways();
		if ( isset( $gateways['wc_checkout_com_apple_pay'] ) ) {
			$gateways['wc_checkout_com_apple_pay']->ajax_generate_csr();
		} else {
			// Fallback: create a temporary instance
			$gateway = new WC_Gateway_Checkout_Com_Apple_Pay();
			$gateway->ajax_generate_csr();
		}
	}
}

/**
 * AJAX handler wrapper for Apple Pay certificate upload.
 * This ensures the handler is always available, even if the gateway class isn't fully instantiated.
 */
if ( ! function_exists( 'cko_ajax_upload_apple_pay_certificate' ) ) {
	function cko_ajax_upload_apple_pay_certificate() {
		// Capability check for admin-only AJAX handler
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 
				'message' => __( 'Permission denied.', 'checkout-com-unified-payments-api' ),
			] );
			return;
		}

		if ( ! class_exists( 'WC_Gateway_Checkout_Com_Apple_Pay' ) ) {
			wp_send_json_error( [ 
				'message' => __( 'Apple Pay gateway class not found.', 'checkout-com-unified-payments-api' ),
			] );
			return;
		}
	
		// Get the gateway instance
		$gateways = WC()->payment_gateways()->payment_gateways();
		if ( isset( $gateways['wc_checkout_com_apple_pay'] ) ) {
			$gateways['wc_checkout_com_apple_pay']->ajax_upload_certificate();
		} else {
			// Fallback: create a temporary instance
			$gateway = new WC_Gateway_Checkout_Com_Apple_Pay();
			$gateway->ajax_upload_certificate();
		}
	}
}

/**
 * AJAX handler wrapper for Apple Pay merchant certificate generation.
 * This ensures the handler is always available, even if the gateway class isn't fully instantiated.
 */
if ( ! function_exists( 'cko_ajax_generate_apple_pay_merchant_certificate' ) ) {
	function cko_ajax_generate_apple_pay_merchant_certificate() {
		// Capability check for admin-only AJAX handler
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 
				'message' => __( 'Permission denied.', 'checkout-com-unified-payments-api' ),
			] );
			return;
		}

		if ( ! class_exists( 'WC_Gateway_Checkout_Com_Apple_Pay' ) ) {
			wp_send_json_error( [ 
				'message' => __( 'Apple Pay gateway class not found.', 'checkout-com-unified-payments-api' ),
			] );
			return;
		}
	
		// Get the gateway instance
		$gateways = WC()->payment_gateways()->payment_gateways();
		if ( isset( $gateways['wc_checkout_com_apple_pay'] ) ) {
			$gateways['wc_checkout_com_apple_pay']->ajax_generate_merchant_certificate();
		} else {
			// Fallback: create a temporary instance
			$gateway = new WC_Gateway_Checkout_Com_Apple_Pay();
			$gateway->ajax_generate_merchant_certificate();
		}
	}
}

/**
 * AJAX handler wrapper for Apple Pay domain association upload.
 */
if ( ! function_exists( 'cko_ajax_upload_apple_pay_domain_association' ) ) {
	function cko_ajax_upload_apple_pay_domain_association() {
		// Capability check for admin-only AJAX handler
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 
				'message' => __( 'Permission denied.', 'checkout-com-unified-payments-api' ),
			] );
			return;
		}

		if ( ! class_exists( 'WC_Gateway_Checkout_Com_Apple_Pay' ) ) {
			wp_send_json_error( [ 
				'message' => __( 'Apple Pay gateway class not found.', 'checkout-com-unified-payments-api' ),
			] );
			return;
		}
	
		$gateways = WC()->payment_gateways()->payment_gateways();
		if ( isset( $gateways['wc_checkout_com_apple_pay'] ) ) {
			$gateways['wc_checkout_com_apple_pay']->ajax_upload_domain_association();
		} else {
			$gateway = new WC_Gateway_Checkout_Com_Apple_Pay();
			$gateway->ajax_upload_domain_association();
		}
	}
}

/**
 * AJAX handler wrapper for Apple Pay merchant identity CSR generation.
 */
if ( ! function_exists( 'cko_ajax_generate_apple_pay_merchant_identity_csr' ) ) {
	function cko_ajax_generate_apple_pay_merchant_identity_csr() {
		// Capability check for admin-only AJAX handler
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 
				'message' => __( 'Permission denied.', 'checkout-com-unified-payments-api' ),
			] );
			return;
		}

		if ( ! class_exists( 'WC_Gateway_Checkout_Com_Apple_Pay' ) ) {
			wp_send_json_error( [ 
				'message' => __( 'Apple Pay gateway class not found.', 'checkout-com-unified-payments-api' ),
			] );
			return;
		}
	
		$gateways = WC()->payment_gateways()->payment_gateways();
		if ( isset( $gateways['wc_checkout_com_apple_pay'] ) ) {
			$gateways['wc_checkout_com_apple_pay']->ajax_generate_merchant_identity_csr();
		} else {
			$gateway = new WC_Gateway_Checkout_Com_Apple_Pay();
			$gateway->ajax_generate_merchant_identity_csr();
		}
	}
}

/**
 * AJAX handler wrapper for Apple Pay merchant identity certificate upload.
 */
if ( ! function_exists( 'cko_ajax_upload_apple_pay_merchant_identity_certificate' ) ) {
	function cko_ajax_upload_apple_pay_merchant_identity_certificate() {
		// Capability check for admin-only AJAX handler
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 
				'message' => __( 'Permission denied.', 'checkout-com-unified-payments-api' ),
			] );
			return;
		}

		if ( ! class_exists( 'WC_Gateway_Checkout_Com_Apple_Pay' ) ) {
			wp_send_json_error( [ 
				'message' => __( 'Apple Pay gateway class not found.', 'checkout-com-unified-payments-api' ),
			] );
			return;
		}
	
		$gateways = WC()->payment_gateways()->payment_gateways();
		if ( isset( $gateways['wc_checkout_com_apple_pay'] ) ) {
			$gateways['wc_checkout_com_apple_pay']->ajax_upload_merchant_identity_certificate();
		} else {
			$gateway = new WC_Gateway_Checkout_Com_Apple_Pay();
			$gateway->ajax_upload_merchant_identity_certificate();
		}
	}
}

/**
 * AJAX handler wrapper for Apple Pay certificate testing.
 */
if ( ! function_exists( 'cko_ajax_test_apple_pay_certificate' ) ) {
	function cko_ajax_test_apple_pay_certificate() {
		// Capability check for admin-only AJAX handler
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 
				'message' => __( 'Permission denied.', 'checkout-com-unified-payments-api' ),
			] );
			return;
		}

		if ( ! class_exists( 'WC_Gateway_Checkout_Com_Apple_Pay' ) ) {
			wp_send_json_error( [ 
				'message' => __( 'Apple Pay gateway class not found.', 'checkout-com-unified-payments-api' ),
			] );
			return;
		}
	
		$gateways = WC()->payment_gateways()->payment_gateways();
		if ( isset( $gateways['wc_checkout_com_apple_pay'] ) ) {
			$gateways['wc_checkout_com_apple_pay']->ajax_test_certificate();
		} else {
			$gateway = new WC_Gateway_Checkout_Com_Apple_Pay();
			$gateway->ajax_test_certificate();
		}
	}
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
	$methods[] = 'WC_Gateway_Checkout_Com_PayPal';
	$methods[] = 'WC_Gateway_Checkout_Com_Alternative_Payments';
	$methods[] = 'WC_Gateway_Checkout_Com_Flow';

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
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_cards' ) . '">' . __( 'Settings', 'checkout-com-unified-payments-api' ) . '</a>',
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
			'label'                     => _x( 'Suspected Fraud', 'Order status', 'checkout-com-unified-payments-api' ),
			'public'                    => true,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: number of orders */
			'label_count'               => _n_noop( 'Suspected Fraud <span class="count">(%s)</span>', 'Suspected Frauds <span class="count">(%s)</span>', 'checkout-com-unified-payments-api' ),
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
	$order_statuses['wc-flagged'] = _x( 'Suspected Fraud', 'Order status', 'checkout-com-unified-payments-api' );

	return $order_statuses;
}

add_action( 'woocommerce_checkout_process', 'cko_check_if_empty' );

/**
 * Validate cvv on checkout page.
 */
function cko_check_if_empty() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing
	if ( isset( $_POST['payment_method'] ) && 'wc_checkout_com_cards' === $_POST['payment_method'] ) {

		// Check if require cvv is enabled in module setting.
		if (
			WC_Admin_Settings::get_option( 'ckocom_card_saved' )
			&& WC_Admin_Settings::get_option( 'ckocom_card_require_cvv' )
			&& isset( $_POST['wc-wc_checkout_com_cards-payment-token'] )
			&& 'new' !== $_POST['wc-wc_checkout_com_cards-payment-token']
		) {
			// check if cvv is empty on checkout page.
			if ( empty( $_POST['wc_checkout_com_cards-card-cvv'] ) ) {
                // phpcs:enable
				wc_add_notice( esc_html__( 'Please enter a valid cvv.', 'checkout-com-unified-payments-api' ), 'error' );
			}
		}
	}
}

// Hook into order creation and updates
add_action( 'woocommerce_new_order', 'cko_validate_manual_order_creation' );
add_action( 'woocommerce_update_order', 'cko_validate_manual_order_creation' );
add_action( 'save_post', 'cko_validate_manual_order_on_save' );
add_action( 'admin_init', 'cko_check_incomplete_orders' );

/**
 * Validate manual order creation to ensure billing address and email are set.
 * This prevents issues when using Flow payment on order-pay page.
 *
 * @param int $order_id Order ID.
 */
function cko_validate_manual_order_creation( $order_id ) {
	$order = wc_get_order( $order_id );
	
	// Only validate admin-created orders (manual orders)
	if ( ! $order || ! $order->is_created_via( 'admin' ) ) {
		return;
	}
	
	// Log for debugging
	WC_Checkoutcom_Utility::logger( 'Validating manual order creation. Order ID: ' . $order_id );
	
	// Check if billing email is missing or invalid
	$billing_email = $order->get_billing_email();
	if ( empty( $billing_email ) || ! is_email( $billing_email ) ) {
		// Log the issue
		WC_Checkoutcom_Utility::logger( 'Manual order created without valid billing email. Order ID: ' . $order_id );
		
		// Set a transient to show notice on next admin page load
		set_transient( 'cko_validation_email_' . $order_id, true, 60 );
	}
	
	// Check if billing address is missing
	$billing_address_1 = $order->get_billing_address_1();
	$billing_city = $order->get_billing_city();
	$billing_country = $order->get_billing_country();
	
	if ( empty( $billing_address_1 ) || empty( $billing_city ) || empty( $billing_country ) ) {
		// Log the issue
		WC_Checkoutcom_Utility::logger( 'Manual order created without complete billing address. Order ID: ' . $order_id );
		
		// Set a transient to show notice on next admin page load
		set_transient( 'cko_validation_address_' . $order_id, true, 60 );
	}
}

/**
 * Validate order when saved via admin (different hook)
 *
 * @param int $post_id Post ID.
 */
function cko_validate_manual_order_on_save( $post_id ) {
	// Only process shop_order posts
	if ( get_post_type( $post_id ) !== 'shop_order' ) {
		return;
	}
	
	$order = wc_get_order( $post_id );
	
	// Only validate admin-created orders (manual orders)
	if ( ! $order || ! $order->is_created_via( 'admin' ) ) {
		return;
	}
	
	// Log for debugging
	WC_Checkoutcom_Utility::logger( 'Validating manual order on save. Order ID: ' . $post_id );
	
	$validation_errors = array();
	
	// Check if billing email is missing or invalid
	$billing_email = $order->get_billing_email();
	if ( empty( $billing_email ) || ! is_email( $billing_email ) ) {
		$validation_errors[] = __( 'Billing email is required for Flow payments to work properly.', 'checkout-com-unified-payments-api' );
		WC_Checkoutcom_Utility::logger( 'Manual order saved without valid billing email. Order ID: ' . $post_id );
	}
	
	// Check if billing address is missing
	$billing_address_1 = $order->get_billing_address_1();
	$billing_city = $order->get_billing_city();
	$billing_country = $order->get_billing_country();
	
	if ( empty( $billing_address_1 ) || empty( $billing_city ) || empty( $billing_country ) ) {
		$validation_errors[] = __( 'Complete billing address is required for Flow payments to work properly.', 'checkout-com-unified-payments-api' );
		WC_Checkoutcom_Utility::logger( 'Manual order saved without complete billing address. Order ID: ' . $post_id );
	}
	
	// If there are validation errors, prevent saving and show error
	if ( ! empty( $validation_errors ) ) {
		// Log the blocking
		WC_Checkoutcom_Utility::logger( 'BLOCKING order creation due to validation errors: ' . implode( ', ', $validation_errors ) );
		
		// Show error message and stop execution
		$error_message = '<h2>❌ Order Creation Blocked</h2>';
		$error_message .= '<p><strong>Checkout.com Flow:</strong> Cannot create order with missing required information.</p>';
		$error_message .= '<ul>';
		foreach ( $validation_errors as $error ) {
			$error_message .= '<li>' . esc_html( $error ) . '</li>';
		}
		$error_message .= '</ul>';
		$error_message .= '<p><strong>Please:</strong></p>';
		$error_message .= '<ol>';
		$error_message .= '<li>Go back to the order form</li>';
		$error_message .= '<li>Add the missing billing email and address</li>';
		$error_message .= '<li>Try creating the order again</li>';
		$error_message .= '</ol>';
		$error_message .= '<p><a href="javascript:history.back()">← Go Back</a></p>';
		
		wp_die( $error_message, 'Order Creation Blocked', array( 'response' => 400 ) );
	}
}

/**
 * Check for incomplete orders and show persistent warnings
 */
function cko_check_incomplete_orders() {
	// Only run in admin and on order-related pages
	if ( ! is_admin() || ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	
	// Get current screen
	$screen = get_current_screen();
	if ( ! $screen || ( $screen->id !== 'shop_order' && $screen->id !== 'edit-shop_order' ) ) {
		return;
	}
	
	// Get order ID from URL
	$order_id = 0;
	if ( isset( $_GET['post'] ) && get_post_type( $_GET['post'] ) === 'shop_order' ) {
		$order_id = intval( $_GET['post'] );
	}
	
	if ( ! $order_id ) {
		return;
	}
	
	$order = wc_get_order( $order_id );
	if ( ! $order || ! $order->is_created_via( 'admin' ) ) {
		return;
	}
	
	// Check if order is incomplete
	$billing_email = $order->get_billing_email();
	$billing_address_1 = $order->get_billing_address_1();
	$billing_city = $order->get_billing_city();
	$billing_country = $order->get_billing_country();
	
	$is_incomplete = false;
	$missing_fields = array();
	
	if ( empty( $billing_email ) || ! is_email( $billing_email ) ) {
		$is_incomplete = true;
		$missing_fields[] = 'billing email';
	}
	
	if ( empty( $billing_address_1 ) || empty( $billing_city ) || empty( $billing_country ) ) {
		$is_incomplete = true;
		$missing_fields[] = 'complete billing address';
	}
	
	if ( $is_incomplete ) {
		// Store notice data in transient for display
		set_transient( 'cko_flow_incomplete_order_notice_' . $order_id, array(
			'missing_fields' => $missing_fields,
			'order_id' => $order_id,
		), 3600 );
		// Set a persistent notice
		add_action( 'admin_notices', 'cko_show_flow_incomplete_order_notice' );
	}
}

/**
 * Show admin notice for incomplete Flow orders.
 */
function cko_show_flow_incomplete_order_notice() {
	// Check if we're on the order edit page
	if ( ! isset( $_GET['post'] ) || ! isset( $_GET['action'] ) || 'edit' !== $_GET['action'] ) {
		return;
	}
	
	$order_id = absint( $_GET['post'] );
	$notice_data = get_transient( 'cko_flow_incomplete_order_notice_' . $order_id );
	
	if ( ! $notice_data || ! is_array( $notice_data ) ) {
		return;
	}
	
	$missing_fields = isset( $notice_data['missing_fields'] ) ? $notice_data['missing_fields'] : array();
	
	echo '<div class="notice notice-error is-dismissible">';
	echo '<p><strong>⚠️ Checkout.com Flow Warning:</strong> ';
	echo 'This manual order is missing: ' . esc_html( implode( ', ', $missing_fields ) ) . '. ';
	echo 'Flow payments will fail without this information. ';
	echo 'Please complete the order details before proceeding with payment.';
	echo '</p></div>';
	
	// Delete transient after showing notice once
	delete_transient( 'cko_flow_incomplete_order_notice_' . $order_id );
}

/**
 * Add resource hints for Flow checkout in wp_head.
 */
function cko_add_flow_resource_hints() {
	$core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings', array() );
	$environment = isset( $core_settings['ckocom_environment'] ) ? $core_settings['ckocom_environment'] : 'sandbox';
	$api_domain = 'sandbox' === $environment ? 'api.sandbox.checkout.com' : 'api.checkout.com';
	?>
	<link rel="dns-prefetch" href="//checkout-web-components.checkout.com">
	<link rel="preconnect" href="https://checkout-web-components.checkout.com" crossorigin>
	<link rel="preconnect" href="https://<?php echo esc_attr( $api_domain ); ?>" crossorigin>
	<!-- CDN resource hints for risk.js and other SDK resources -->
	<link rel="dns-prefetch" href="//cdn.checkout.com">
	<link rel="preconnect" href="https://cdn.checkout.com" crossorigin>
	<link rel="dns-prefetch" href="//devices.checkout.com">
	<link rel="preconnect" href="https://devices.checkout.com" crossorigin>
	<?php
}

/**
 * Add async attribute to Flow SDK script tag.
 *
 * @param string $tag    Script tag HTML.
 * @param string $handle Script handle.
 * @return string Modified script tag.
 */
function cko_add_async_to_flow_script( $tag, $handle ) {
	if ( 'checkout-com-flow-script' === $handle ) {
		return str_replace( ' src', ' async src', $tag );
	}
	return $tag;
}

// Show admin notices based on transients
add_action( 'admin_notices', 'cko_show_validation_notices' );

/**
 * Show validation notices in admin
 */
function cko_show_validation_notices() {
	// Get current order ID if we're on an order edit page
	global $post;
	$order_id = 0;
	
	if ( isset( $_GET['post'] ) && get_post_type( $_GET['post'] ) === 'shop_order' ) {
		$order_id = intval( $_GET['post'] );
	} elseif ( $post && $post->post_type === 'shop_order' ) {
		$order_id = $post->ID;
	}
	
	if ( ! $order_id ) {
		return;
	}
	
	// Check for email validation notice
	if ( get_transient( 'cko_validation_email_' . $order_id ) ) {
		delete_transient( 'cko_validation_email_' . $order_id );
		echo '<div class="notice notice-warning is-dismissible">';
		echo '<p><strong>Checkout.com Flow:</strong> ';
		echo esc_html__( 'Manual orders require a valid billing email address for Flow payments to work properly. Please add a billing email to this order.', 'checkout-com-unified-payments-api' );
		echo ' <a href="' . esc_url( admin_url( 'post.php?post=' . $order_id . '&action=edit' ) ) . '">' . esc_html__( 'Edit Order', 'checkout-com-unified-payments-api' ) . '</a>';
		echo '</p></div>';
	}
	
	// Check for address validation notice
	if ( get_transient( 'cko_validation_address_' . $order_id ) ) {
		delete_transient( 'cko_validation_address_' . $order_id );
		echo '<div class="notice notice-warning is-dismissible">';
		echo '<p><strong>Checkout.com Flow:</strong> ';
		echo esc_html__( 'Manual orders require a complete billing address for Flow payments to work properly. Please add billing address details to this order.', 'checkout-com-unified-payments-api' );
		echo ' <a href="' . esc_url( admin_url( 'post.php?post=' . $order_id . '&action=edit' ) ) . '">' . esc_html__( 'Edit Order', 'checkout-com-unified-payments-api' ) . '</a>';
		echo '</p></div>';
	}
}

add_action( 'admin_enqueue_scripts', 'cko_admin_enqueue_scripts' );

/**
 * Load admin scripts.
 *
 * @return void
 */
function cko_admin_enqueue_scripts() {

	$core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings', array() );
	$checkout_mode = isset( $core_settings['ckocom_checkout_mode'] ) ? $core_settings['ckocom_checkout_mode'] : 'classic';
	$flow_enabled  = false;

	if( $checkout_mode === 'flow' ) {
		$flow_enabled = true;
	}

	// Load admin scripts.
	wp_enqueue_script( 'cko-admin-script', WC_CHECKOUTCOM_PLUGIN_URL . '/assets/js/admin.js', [ 'jquery' ], WC_CHECKOUTCOM_PLUGIN_VERSION );
	
	// Load checkout mode toggle script for Quick Settings page
	wp_enqueue_script( 'cko-admin-checkout-mode-toggle', WC_CHECKOUTCOM_PLUGIN_URL . '/assets/js/admin-checkout-mode-toggle.js', [ 'jquery' ], WC_CHECKOUTCOM_PLUGIN_VERSION );
	
	// Load admin settings CSS
	wp_enqueue_style( 'cko-admin-settings', WC_CHECKOUTCOM_PLUGIN_URL . '/assets/css/admin-settings.css', [], WC_CHECKOUTCOM_PLUGIN_VERSION );

	$vars = [
		'nas_docs'                           => 'https://www.checkout.com/docs/four/resources/api-authentication/api-keys',
		'abc_docs'                           => 'https://www.checkout.com/docs/the-hub/update-your-hub-settings#Manage_the_API_keys',

		'webhook_check_error'                => esc_html__( 'An error occurred while fetching the webhooks. Please try again.', 'checkout-com-unified-payments-api' ),
		'webhook_register_error'             => esc_html__( 'An error occurred while registering the webhook. Please try again.', 'checkout-com-unified-payments-api' ),

		'checkoutcom_check_webhook_nonce'    => wp_create_nonce( 'checkoutcom_check_webhook' ),
		'checkoutcom_register_webhook_nonce' => wp_create_nonce( 'checkoutcom_register_webhook' ),

		'flow_enabled'						 => $flow_enabled,
	];

	wp_localize_script( 'cko-admin-script', 'cko_admin_vars', $vars );
}

add_action( 'wp_enqueue_scripts', 'callback_for_setting_up_scripts' );

/**
 * Load checkout.com style sheet.
 * Load Google Pay js.
 *
 * Only on Checkout related pages.
 */
function callback_for_setting_up_scripts() {

	// Load on Cart, Checkout, pay for order or add payment method pages.
	if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) && ! is_add_payment_method_page() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	// Register adn enqueue checkout css.
	wp_register_style( 'checkoutcom-style', WC_CHECKOUTCOM_PLUGIN_URL . '/assets/css/checkoutcom-styles.css', [], WC_CHECKOUTCOM_PLUGIN_VERSION );
	wp_register_style( 'normalize', WC_CHECKOUTCOM_PLUGIN_URL . '/assets/css/normalize.css', [], WC_CHECKOUTCOM_PLUGIN_VERSION );
	// Don't enqueue checkoutcom-style here - will be enqueued after flow.css to ensure overrides work
	wp_enqueue_style( 'normalize' );

	// load cko apm settings.
	$apm_settings = get_option( 'woocommerce_wc_checkout_com_alternative_payments_settings' );
	$apm_enable   = ! empty( $apm_settings['enabled'] ) && 'yes' === $apm_settings['enabled'];

	if ( $apm_enable && ! empty( $apm_settings['ckocom_apms_selector'] ) ) {
		foreach ( $apm_settings['ckocom_apms_selector'] as $value ) {
			if ( 'klarna' === $value ) {
				wp_enqueue_script( 'cko-klarna-script', 'https://x.klarnacdn.net/kp/lib/v1/api.js', [ 'jquery' ] );
			}
		}
	}

	// Enqueue FLOW scripts.
	$core_settings      = get_option( 'woocommerce_wc_checkout_com_cards_settings', array() );
	$checkout_mode      = isset( $core_settings['ckocom_checkout_mode'] ) ? $core_settings['ckocom_checkout_mode'] : 'classic';
	$flow_customization = get_option( 'woocommerce_wc_checkout_com_flow_settings', array() );
	
	// Merge Card settings into Flow customization vars (for Card Holder Name, Position, and Saved Payment Display Order)
	// These settings were moved from Flow settings to Card settings
	if ( isset( $core_settings['flow_show_card_holder_name'] ) ) {
		$flow_customization['flow_show_card_holder_name'] = $core_settings['flow_show_card_holder_name'];
	}
	if ( isset( $core_settings['flow_component_cardholder_name_position'] ) ) {
		$flow_customization['flow_component_cardholder_name_position'] = $core_settings['flow_component_cardholder_name_position'];
	}
	if ( isset( $core_settings['flow_saved_payment'] ) ) {
		$flow_customization['flow_saved_payment'] = $core_settings['flow_saved_payment'];
	}
	
	// Ensure flow_component_name is always set with a default value
	if ( empty( $flow_customization['flow_component_name'] ) ) {
		$flow_customization['flow_component_name'] = 'flow';
	}

	if ( 'flow' === $checkout_mode ) {
		// Add resource hints for faster DNS resolution and connection to Checkout.com
		add_action( 'wp_head', 'cko_add_flow_resource_hints', 1 );
		
		// Load Checkout.com SDK asynchronously for better performance
		wp_enqueue_script(
			'checkout-com-flow-script', 
			'https://checkout-web-components.checkout.com/index.js', 
			array(), 
			null,
			true // Load in footer for better page load performance
		);
		
		// Add async attribute to SDK script for non-blocking load
		add_filter( 'script_loader_tag', 'cko_add_async_to_flow_script', 10, 2 );

		wp_enqueue_script(
			'flow-customization-script',
			WC_CHECKOUTCOM_PLUGIN_URL . '/flow-integration/assets/js/flow-customization.js',
			array(), 
			WC_CHECKOUTCOM_PLUGIN_VERSION,
			true 
		);

		wp_localize_script( 'flow-customization-script', 'cko_flow_customization_vars', $flow_customization );

		wp_register_style( 'cko-flow-style', WC_CHECKOUTCOM_PLUGIN_URL . '/flow-integration/assets/css/flow.css', array(), WC_CHECKOUTCOM_PLUGIN_VERSION );
		wp_enqueue_style( 'cko-flow-style' );
		// Enqueue checkoutcom-styles.css after flow.css to ensure spacing overrides work
		wp_enqueue_style( 'checkoutcom-style' );

		// REFACTORED: Enqueue logger module FIRST (before other Flow scripts)
		// Logger module has no dependencies - must load before payment-session.js
		wp_enqueue_script(
			'checkout-com-flow-logger-script', 
			WC_CHECKOUTCOM_PLUGIN_URL . '/flow-integration/assets/js/modules/flow-logger.js', 
			array(), // No dependencies - must load first
			WC_CHECKOUTCOM_PLUGIN_VERSION,
			false // Load in header to ensure it's available early
		);

		// REFACTORED: Enqueue validation module (needs jQuery and logger)
		// Must load before payment-session.js
		wp_enqueue_script(
			'checkout-com-flow-validation-script', 
			WC_CHECKOUTCOM_PLUGIN_URL . '/flow-integration/assets/js/modules/flow-validation.js', 
			array( 'jquery', 'checkout-com-flow-logger-script' ), // jQuery and logger are dependencies
			WC_CHECKOUTCOM_PLUGIN_VERSION,
			false // Load in header
		);

		// REFACTORED: Enqueue state management module (needs logger)
		// Must load before payment-session.js to provide centralized state
		wp_enqueue_script(
			'checkout-com-flow-state-script', 
			WC_CHECKOUTCOM_PLUGIN_URL . '/flow-integration/assets/js/modules/flow-state.js', 
			array( 'checkout-com-flow-logger-script' ), // Logger is dependency
			WC_CHECKOUTCOM_PLUGIN_VERSION,
			false // Load in header
		);

		// REFACTORED: Enqueue early 3DS detection module (needs logger, state)
		// Must load before payment-session.js to prevent 3DS return initialization
		wp_enqueue_script(
			'checkout-com-flow-3ds-detection-script',
			WC_CHECKOUTCOM_PLUGIN_URL . '/flow-integration/assets/js/modules/flow-3ds-detection.js',
			array( 'checkout-com-flow-logger-script', 'checkout-com-flow-state-script' ), // Logger and state are dependencies
			WC_CHECKOUTCOM_PLUGIN_VERSION,
			false // Load in header
		);

		// REFACTORED: Enqueue updated_checkout guard module (needs jQuery, logger, state)
		// Must load before payment-session.js to protect Flow component lifecycle
		wp_enqueue_script(
			'checkout-com-flow-updated-checkout-guard-script',
			WC_CHECKOUTCOM_PLUGIN_URL . '/flow-integration/assets/js/modules/flow-updated-checkout-guard.js',
			array( 'jquery', 'checkout-com-flow-logger-script', 'checkout-com-flow-state-script' ), // jQuery, logger, and state are dependencies
			WC_CHECKOUTCOM_PLUGIN_VERSION,
			false // Load in header
		);

		// REFACTORED: Enqueue container-ready handler (needs logger, state)
		// Must load before payment-session.js to react to container lifecycle events
		wp_enqueue_script(
			'checkout-com-flow-container-ready-handler-script',
			WC_CHECKOUTCOM_PLUGIN_URL . '/flow-integration/assets/js/modules/flow-container-ready-handler.js',
			array( 'checkout-com-flow-logger-script', 'checkout-com-flow-state-script' ), // Logger and state are dependencies
			WC_CHECKOUTCOM_PLUGIN_VERSION,
			false // Load in header
		);

		// REFACTORED: Enqueue field change handler (needs jQuery, logger, state)
		// Must load before payment-session.js to wire input listeners
		wp_enqueue_script(
			'checkout-com-flow-field-change-handler-script',
			WC_CHECKOUTCOM_PLUGIN_URL . '/flow-integration/assets/js/modules/flow-field-change-handler.js',
			array( 'jquery', 'checkout-com-flow-logger-script', 'checkout-com-flow-state-script' ), // jQuery, logger, and state are dependencies
			WC_CHECKOUTCOM_PLUGIN_VERSION,
			false // Load in header
		);

		// REFACTORED: Enqueue saved card handler (needs jQuery, logger)
		// Must load before payment-session.js to handle saved card selection
		wp_enqueue_script(
			'checkout-com-flow-saved-card-handler-script',
			WC_CHECKOUTCOM_PLUGIN_URL . '/flow-integration/assets/js/modules/flow-saved-card-handler.js',
			array( 'jquery', 'checkout-com-flow-logger-script' ), // jQuery and logger are dependencies
			WC_CHECKOUTCOM_PLUGIN_VERSION,
			false // Load in header
		);

		// REFACTORED: Enqueue initialization helper module (needs jQuery, logger, validation, state)
		// Must load before payment-session.js to provide initialization helpers
		wp_enqueue_script(
			'checkout-com-flow-initialization-script', 
			WC_CHECKOUTCOM_PLUGIN_URL . '/flow-integration/assets/js/modules/flow-initialization.js', 
			array( 'jquery', 'checkout-com-flow-logger-script', 'checkout-com-flow-validation-script', 'checkout-com-flow-state-script' ), // jQuery, logger, validation, and state are dependencies
			WC_CHECKOUTCOM_PLUGIN_VERSION,
			false // Load in header
		);

		wp_enqueue_script(
			'checkout-com-flow-container-script', 
			WC_CHECKOUTCOM_PLUGIN_URL . '/flow-integration/assets/js/flow-container.js', 
			array( 'jquery', 'checkout-com-flow-logger-script' ), // Logger is dependency
			WC_CHECKOUTCOM_PLUGIN_VERSION
		);

	// Get file modification time for cache-busting (only changes when file changes)
	$payment_session_js_path = WC_CHECKOUTCOM_PLUGIN_PATH . '/flow-integration/assets/js/payment-session.js';
	// Use timestamp for aggressive cache-busting - ensures new version loads immediately
	$payment_session_version = WC_CHECKOUTCOM_PLUGIN_VERSION . '-' . ( file_exists( $payment_session_js_path ) ? filemtime( $payment_session_js_path ) : time() );
	
	// Force version refresh by adding current timestamp if file doesn't exist
	if ( ! file_exists( $payment_session_js_path ) ) {
		$payment_session_version = WC_CHECKOUTCOM_PLUGIN_VERSION . '-' . time();
	}
	
	$card_settings            = get_option( 'woocommerce_wc_checkout_com_cards_settings', array() );
	$terms_prevention_value   = WC_Admin_Settings::get_option( 'flow_terms_prevention_enabled', '' );
	if ( '' === $terms_prevention_value && isset( $card_settings['flow_terms_prevention_enabled'] ) ) {
		$terms_prevention_value = $card_settings['flow_terms_prevention_enabled'];
	}
	$terms_prevention_enabled = 'yes' === $terms_prevention_value;
	$payment_session_deps      = array(
		'jquery',
		'flow-customization-script',
		'checkout-com-flow-container-script',
		'checkout-com-flow-logger-script',
		'checkout-com-flow-validation-script',
		'checkout-com-flow-state-script',
		'checkout-com-flow-3ds-detection-script',
		'checkout-com-flow-updated-checkout-guard-script',
		'checkout-com-flow-container-ready-handler-script',
		'checkout-com-flow-field-change-handler-script',
		'checkout-com-flow-saved-card-handler-script',
		'checkout-com-flow-initialization-script',
		'wp-i18n',
	);

	if ( $terms_prevention_enabled ) {
		wp_enqueue_script(
			'checkout-com-flow-terms-prevention-script',
			WC_CHECKOUTCOM_PLUGIN_URL . '/flow-integration/assets/js/modules/flow-terms-prevention.js',
			array( 'jquery', 'checkout-com-flow-logger-script' ), // jQuery and logger are dependencies
			WC_CHECKOUTCOM_PLUGIN_VERSION,
			false // Load in header to intercept events early
		);
		$payment_session_deps[] = 'checkout-com-flow-terms-prevention-script';
	}

	wp_enqueue_script(
		'checkout-com-flow-payment-session-script',
		WC_CHECKOUTCOM_PLUGIN_URL . '/flow-integration/assets/js/payment-session.js',
		$payment_session_deps,
		$payment_session_version
	);

		$url = 'https://api.checkout.com/payment-sessions';

		if ( 'sandbox' === $core_settings['ckocom_environment'] ) {
			$url = 'https://api.sandbox.checkout.com/payment-sessions';
		}

		global $woocommerce, $wp_version;

		if ( class_exists( 'WooCommerce' ) ) {
			$woo_version = isset( $woocommerce ) ? $woocommerce->version : null;
		} else {
			$woo_version = null;
		}

		$udf5 = sprintf(
			'Platform Data - WordPress %s / Woocommerce %s, Integration Data - Checkout.com %s, SDK Data - PHP SDK %s, Server - %s',
			$wp_version,
			$woo_version, 
			WC_CHECKOUTCOM_PLUGIN_VERSION,
			( class_exists('Checkout\\CheckoutUtils') && defined('Checkout\\CheckoutUtils::PROJECT_VERSION') ) ? \Checkout\CheckoutUtils::PROJECT_VERSION : 'unknown',
			get_site_url()
		);

		$regular_payment_type = class_exists('Checkout\\Payments\\PaymentType') ? \Checkout\Payments\PaymentType::$regular : 'Regular';
		$recurring_payment_type = class_exists('Checkout\\Payments\\PaymentType') ? \Checkout\Payments\PaymentType::$recurring : 'Recurring';

		$ref_session = is_array( WC()->session->get_session_cookie() ) ? substr( WC()->session->get_session_cookie()[3], 0, 25 ) : '';
		$ref_session = preg_match( '/^[a-zA-Z0-9]{25}$/', $ref_session ) ? $ref_session : substr( bin2hex( random_bytes(13) ), 0, 25 );


		// Get 3DS settings
		$three_d_enabled = '1' === WC_Admin_Settings::get_option( 'ckocom_card_threed', '0' );
		$attempt_no_three_d = '1' === WC_Admin_Settings::get_option( 'ckocom_card_notheed', '0' );
		$save_card = WC_Admin_Settings::get_option( 'ckocom_card_saved' );

		// Get capture settings
		$auto_capture = '1' === WC_Admin_Settings::get_option( 'ckocom_card_autocap', '1' );
		$capture_delay_hours = WC_Admin_Settings::get_option( 'ckocom_card_cap_delay', '0' );
		
	// Get Flow-specific settings
	$flow_settings = get_option( 'woocommerce_wc_checkout_com_flow_settings' );
	$debug_logging = isset( $flow_settings['flow_debug_logging'] ) && 'yes' === $flow_settings['flow_debug_logging'];
	
	// Get enabled payment methods from Flow settings
		$enabled_payment_methods = isset( $flow_settings['flow_enabled_payment_methods'] ) ? $flow_settings['flow_enabled_payment_methods'] : array();
		// Ensure it's an array
		if ( ! is_array( $enabled_payment_methods ) ) {
			$enabled_payment_methods = array();
		}
		
		// Note: Cards are always available in Flow and are automatically included
		// The enabled_payment_methods setting only controls APMs (Alternative Payment Methods)
		// If empty, all available methods will be shown
		
		// Get additional 3DS configuration from card settings
		$challenge_indicator = WC_Admin_Settings::get_option( 'ckocom_card_3ds_challenge_indicator', 'no_preference' );
		$exemption = WC_Admin_Settings::get_option( 'ckocom_card_3ds_exemption', '' );
		$allow_upgrade = 'yes' === WC_Admin_Settings::get_option( 'ckocom_card_3ds_allow_upgrade', 'yes' );
		
		// Debug: Log 3DS settings for troubleshooting (only if WP_DEBUG is enabled)
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$allow_upgrade_raw = WC_Admin_Settings::get_option( 'ckocom_card_3ds_allow_upgrade', 'yes' );
			error_log( '[FLOW] 3DS Settings Debug: ' . print_r( array( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				'three_d_enabled_raw' => WC_Admin_Settings::get_option( 'ckocom_card_threed', '0' ),
				'three_d_enabled' => $three_d_enabled,
				'attempt_no_three_d_raw' => WC_Admin_Settings::get_option( 'ckocom_card_notheed', '0' ),
				'attempt_no_three_d' => $attempt_no_three_d,
				'challenge_indicator' => $challenge_indicator,
				'exemption' => $exemption,
				'allow_upgrade_raw' => $allow_upgrade_raw,
				'allow_upgrade_raw_type' => gettype( $allow_upgrade_raw ),
				'allow_upgrade_comparison' => ( $allow_upgrade_raw === 'yes' ),
				'allow_upgrade' => $allow_upgrade,
				'settings_source' => 'card_settings',
			), true ) );
		}
		
		// Validate 3DS values according to Checkout.com API requirements
		$valid_challenge_indicators = ['no_preference', 'no_challenge_requested', 'challenge_requested', 'challenge_requested_mandate'];
		$valid_exemptions = [
			'low_value', 'trusted_listing', 'trusted_listing_prompt', 
			'transaction_risk_assessment', '3ds_outage', 'sca_delegation', 
			'out_of_sca_scope', 'low_risk_program', 'recurring_operation', 
			'data_share', 'other'
		];
		
		// Ensure challenge indicator is valid
		if ( ! in_array( $challenge_indicator, $valid_challenge_indicators, true ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[FLOW] Invalid challenge_indicator: ' . esc_html( $challenge_indicator ) . ', using default: no_preference' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			$challenge_indicator = 'no_preference';
		}
		
		// Ensure exemption is valid - use empty string if not valid (no exemption)
		if ( empty( $exemption ) || ! in_array( $exemption, $valid_exemptions, true ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[FLOW] Invalid or empty exemption: ' . esc_html( $exemption ) . ', using empty string (no exemption)' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			$exemption = '';
		}
		
		// Ensure boolean values are properly formatted for JavaScript
		$three_d_enabled = $three_d_enabled ? true : false;
		$attempt_no_three_d = $attempt_no_three_d ? true : false;
		$allow_upgrade = $allow_upgrade ? true : false;

		// Map 'live' to 'production' for SDK compatibility
		// SDK might expect 'production' instead of 'live' to properly construct URLs
		$sdk_env = $core_settings['ckocom_environment'];
		if ( 'live' === $sdk_env ) {
			$sdk_env = 'production'; // SDK might need 'production' to construct URLs correctly
		}
		
		$flow_vars = array(
			'checkoutSlug' => get_post_field( 'post_name', get_option( 'woocommerce_checkout_page_id' ) ),
			'orderPaySlug' => WC()->query->query_vars['order-pay'],
			// Removed apiURL and SKey - payment session creation now handled securely via AJAX backend
			'PKey'         => $core_settings['ckocom_pk'],
			'env'          => $sdk_env, // Use mapped environment value
			'ajax_url'     => admin_url( 'admin-ajax.php' ),
			// Security nonce for payment session creation
			'payment_session_nonce' => wp_create_nonce( 'cko_flow_payment_session' ),
			'woo_version'  => $woo_version,
			'ref_session'  => $ref_session,
			'site_url'	   => get_home_url(),
			'async_url'    => '/wp-json/ckoplugin/v1/payment-status',
			'udf5'		   => $udf5,
			'regular_payment_type' => $regular_payment_type,
			'recurring_payment_type' => $recurring_payment_type,
			'three_d_enabled' => $three_d_enabled,
			'attempt_no_three_d' => $attempt_no_three_d,
			'save_card' => $save_card,
			'challenge_indicator' => $challenge_indicator,
			'exemption' => $exemption,
			'allow_upgrade' => $allow_upgrade,
		// Capture settings
		'auto_capture' => $auto_capture ? true : false,
		'capture_delay_hours' => $capture_delay_hours,
		// Debug logging (enables detailed console logs)
		'debug_logging' => $debug_logging ? true : false,
		// Enabled payment methods
		'enabled_payment_methods' => $enabled_payment_methods,
		);

		wp_set_script_translations( 'checkout-com-flow-payment-session-script', 'checkout-com-unified-payments-api' );

		wp_localize_script( 'checkout-com-flow-payment-session-script', 'cko_flow_vars', $flow_vars );
	} else {
		// Classic mode: enqueue checkoutcom-styles.css for card payment method styling
		wp_enqueue_style( 'checkoutcom-style' );
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
	if ( false === strpos( $order->get_payment_method(), 'wc_checkout_com_' ) ) {
		return;
	}

	$order_status   = $order->get_status();
	$auth_status    = str_replace( 'wc-', '', WC_Admin_Settings::get_option( 'ckocom_order_authorised', 'on-hold' ) );
	$capture_status = str_replace( 'wc-', '', WC_Admin_Settings::get_option( 'ckocom_order_captured', 'processing' ) );

	if ( $order->get_payment_method() === 'wc_checkout_com_cards' || $order->get_payment_method() === 'wc_checkout_com_google_pay' ) {
		?>
<script type="text/javascript">
	var ckoCustomButtonValues = {
		order_status: "<?php echo esc_js( $order_status ); ?>",
		auth_status: "<?php echo esc_js( $auth_status ); ?>",
		capture_status: "<?php echo esc_js( $capture_status ); ?>"
	}
</script>

<input type="hidden" value="" name="cko_payment_action" id="cko_payment_action" />
<button class="button" id="cko-capture" style="display:none;">Capture</button>
<button class="button" id="cko-void" style="display:none;">Void</button>
		<?php
	}
}

add_action( 'woocommerce_process_shop_order_meta', 'handle_order_capture_void_action', 50, 2 );

/**
 * Do action for capture and void button.
 *
 * @param int       $order_id Order ID being saved.
 * @param \WC_Order $order Order object being saved.
 *
 * @return bool|void
 */
function handle_order_capture_void_action( $order_id, $order ) {

	if ( ! is_admin() ) {
		return;
	}

	if ( ! isset( $_POST['cko_payment_action'] ) || ! sanitize_text_field( $_POST['cko_payment_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return;
	}

	// Handle case where HPOS not enable and WP Post object is given.
	if ( ! is_a( $order, 'WC_Order' ) ) {
		$order = wc_get_order( $order_id );
	}

	WC_Admin_Notices::remove_notice( 'wc_checkout_com_cards' );

	// check if post is capture.
	if ( 'cko-capture' === sanitize_text_field( $_POST['cko_payment_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing

		// send capture request to cko.
		$result = (array) WC_Checkoutcom_Api_Request::capture_payment( $order_id );

		if ( ! empty( $result['error'] ) ) {
			WC_Admin_Notices::add_custom_notice( 'wc_checkout_com_cards', $result['error'] );

			return false;
		}

		// Set action id as woo transaction id.
		$order->set_transaction_id( $result['action_id'] );
		$order->update_meta_data( 'cko_payment_captured', true );

		// Get cko capture status configured in admin.
		$status = WC_Admin_Settings::get_option( 'ckocom_order_captured', 'processing' );

		/* translators: %s: Action id. */
		$message = sprintf( esc_html__( 'Checkout.com Payment Captured from Admin - Action ID : %s', 'checkout-com-unified-payments-api' ), $result['action_id'] );

		// add notes for the order and update status.
		$order->add_order_note( $message );
		$order->update_status( $status );

		return true;

	} elseif ( 'cko-void' === sanitize_text_field( $_POST['cko_payment_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		// check if post is void.
		// send void request to cko.
		$result = (array) WC_Checkoutcom_Api_Request::void_payment( $order_id );

		if ( ! empty( $result['error'] ) ) {
			WC_Admin_Notices::add_custom_notice( 'wc_checkout_com_cards', $result['error'] );

			return false;
		}

		// Set action id as woo transaction id.
		$order->set_transaction_id( $result['action_id'] );
		$order->update_meta_data( 'cko_payment_voided', true );

		// Get cko capture status configured in admin.
		$status = WC_Admin_Settings::get_option( 'ckocom_order_void', 'cancelled' );

		/* translators: %s: Action id. */
		$message = sprintf( esc_html__( 'Checkout.com Payment Voided from Admin - Action ID : %s', 'checkout-com-unified-payments-api' ), $result['action_id'] );

		// add notes for the order and update status.
		$order->add_order_note( $message );
		$order->update_status( $status );

		// increase stock level.
		wc_increase_stock_levels( $order );

		return true;

	} else {
		WC_Admin_Notices::add_custom_notice( 'wc_checkout_com_cards', esc_html__( 'An error has occurred.', 'checkout-com-unified-payments-api' ) );

		return false;
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

	$order = wc_get_order( $order_id );

	$fawry_number = $order->get_meta( 'cko_fawry_reference_number' );
	$fawry        = __( 'Fawry reference number: ', 'checkout-com-unified-payments-api' );
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
 * @return string
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
				$icons     .= sprintf( '<img src="%1$s" id="cards-icon" alt="%2$s">', $card_icons, $value );
			}

			return $icons;
		}

		return $icons;
	}

	/**
	 *  Display logo for APM available for payment
	 */
	if ( strpos( $id, 'alternative_payments' ) ) {

		$apm_available = WC_Checkoutcom_Utility::get_alternative_payment_methods();

		foreach ( $apm_available as $value ) {
			if ( strpos( $id, $value ) ) {
				$apm_icons = $plugin_url . $value . '.svg';
				$icons    .= sprintf( '<img src="%1$s" id="apm-icon" alt="%2$s">', $apm_icons, $value );

				return $icons;
			}
		}

		return $icons;
	}

	/* Check if Google Pay gateway */
	if ( 'wc_checkout_com_google_pay' === $id ) {
		$display_card_icon = WC_Admin_Settings::get_option( 'ckocom_display_icon', '0' ) === '1';

		/* check if display card option is selected */
		if ( $display_card_icon ) {

			$value      = 'googlepay';
			$card_icons = $plugin_url . $value . '.svg';

			return sprintf( '<img src="%1$s" id="google-icon" alt="%2$s">', $card_icons, $value );
		}

		return $icons;
	}

	/* Check if Google Pay gateway */
	if ( 'wc_checkout_com_apple_pay' === $id ) {
		$display_card_icon = WC_Admin_Settings::get_option( 'ckocom_display_icon', '0' ) === '1';

		/* check if display card option is selected */
		if ( $display_card_icon ) {

			$value      = 'applepay';
			$card_icons = $plugin_url . $value . '.svg';

			return sprintf( '<img src="%1$s" id="apple-icon" alt="%2$s">', $card_icons, $value );
		}

		return $icons;
	}

	return $icons;
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
add_action( 'woocommerce_scheduled_subscription_payment_wc_checkout_com_alternative_payments_sepa', 'subscription_payment', 10, 2 );
add_action( 'woocommerce_scheduled_subscription_payment_wc_checkout_com_google_pay', 'subscription_payment', 10, 2 );
add_action( 'woocommerce_scheduled_subscription_payment_wc_checkout_com_apple_pay', 'subscription_payment', 10, 2 );
add_action( 'woocommerce_scheduled_subscription_payment_wc_checkout_com_paypal', 'subscription_payment', 10, 2 );
add_action( 'woocommerce_scheduled_subscription_payment_wc_checkout_com_flow', 'subscription_payment', 10, 2 );

/**
 * Function to handle subscription renewal payment for card, SEPA APM, Google Pay & Apple Pay.
 *
 * @param float    $renewal_total The amount to charge.
 * @param WC_Order $renewal_order A WC_Order object created to record the renewal payment.
 */
function subscription_payment( $renewal_total, $renewal_order ) {
	include_once 'includes/subscription/class-wc-checkoutcom-subscription.php';

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
	include_once 'includes/subscription/class-wc-checkoutcom-subscription.php';

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

add_action( 'wp_ajax_cko_validate_checkout', 'cko_validate_checkout' );
add_action( 'wp_ajax_nopriv_cko_validate_checkout', 'cko_validate_checkout' );

/**
 * AJAX handler wrapper for Flow payment session creation.
 * This ensures the handler is always available, even if the gateway class isn't fully instantiated.
 */
if ( ! function_exists( 'cko_ajax_flow_create_payment_session' ) ) {
	function cko_ajax_flow_create_payment_session() {
		if ( ! class_exists( 'WC_Gateway_Checkout_Com_Flow' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Flow gateway class not found.', 'checkout-com-unified-payments-api' ),
			) );
			return;
		}

		// Get the gateway instance
		$gateways = WC()->payment_gateways()->payment_gateways();
		if ( isset( $gateways['wc_checkout_com_flow'] ) ) {
			$gateways['wc_checkout_com_flow']->ajax_create_payment_session();
		} else {
			// Fallback: create a temporary instance
			$gateway = new WC_Gateway_Checkout_Com_Flow();
			$gateway->ajax_create_payment_session();
		}
	}
}

/**
 * AJAX handler wrapper for Flow order creation.
 * This ensures the handler is always available, even if the gateway class isn't fully instantiated.
 */
	if ( ! function_exists( 'cko_ajax_flow_create_order' ) ) {
	function cko_ajax_flow_create_order() {
		if ( ! class_exists( 'WC_Gateway_Checkout_Com_Flow' ) ) {
			wp_send_json_error( array(
				'message' => __( 'Flow gateway class not found.', 'checkout-com-unified-payments-api' ),
			) );
			return;
		}

		// Get the gateway instance
		$gateways = WC()->payment_gateways()->payment_gateways();
		if ( isset( $gateways['wc_checkout_com_flow'] ) ) {
			$gateways['wc_checkout_com_flow']->ajax_create_order();
		} else {
			// Fallback: create a temporary instance
			$gateway = new WC_Gateway_Checkout_Com_Flow();
			$gateway->ajax_create_order();
		}
	}
}

/**
 * Register Flow AJAX handlers VERY early (before gateway class instantiation).
 */
function cko_register_flow_ajax_handlers() {
	add_action( 'wp_ajax_cko_flow_create_order', 'cko_ajax_flow_create_order', 1 );
	add_action( 'wp_ajax_nopriv_cko_flow_create_order', 'cko_ajax_flow_create_order', 1 );
}
// Use 'init' hook with priority 0 to ensure registration happens as early as possible
add_action( 'init', 'cko_register_flow_ajax_handlers', 0 );

// Also register directly (in case init hook doesn't work for AJAX)
add_action( 'wp_ajax_cko_flow_create_payment_session', 'cko_ajax_flow_create_payment_session' );
add_action( 'wp_ajax_nopriv_cko_flow_create_payment_session', 'cko_ajax_flow_create_payment_session' );
add_action( 'wp_ajax_cko_flow_create_order', 'cko_ajax_flow_create_order', 1 );
add_action( 'wp_ajax_nopriv_cko_flow_create_order', 'cko_ajax_flow_create_order', 1 );

/**
 * Validates the WooCommerce checkout form via AJAX.
 */
function cko_validate_checkout() {
	try {
		WC_Checkoutcom_Utility::logger( '[VALIDATE CHECKOUT] ========== ENTRY POINT ==========' );
		WC_Checkoutcom_Utility::logger( '[VALIDATE CHECKOUT] Payment method in POST: ' . ( isset( $_POST['payment_method'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_method'] ) ) : 'NOT SET' ) );
		
	// Load WooCommerce checkout class.
		if ( ! function_exists( 'WC' ) || ! WC() ) {
			WC_Checkoutcom_Utility::logger( '[VALIDATE CHECKOUT] ERROR: WooCommerce not available' );
			wp_send_json_error( array( 'message' => __( 'WooCommerce not available.', 'checkout-com-unified-payments-api' ) ) );
			return;
		}
		
	$checkout = WC()->checkout();
		if ( ! $checkout ) {
			WC_Checkoutcom_Utility::logger( '[VALIDATE CHECKOUT] ERROR: Checkout class not available' );
			wp_send_json_error( array( 'message' => __( 'Checkout class not available.', 'checkout-com-unified-payments-api' ) ) );
			return;
		}

	// Validate nonce.
	$nonce_value = wc_get_var( $_REQUEST['woocommerce-process-checkout-nonce'], wc_get_var( $_REQUEST['_wpnonce'], '' ) ); // phpcs:ignore
	if ( empty( $nonce_value ) || ! wp_verify_nonce( $nonce_value, 'woocommerce-process_checkout' ) ) {
			WC_Checkoutcom_Utility::logger( '[VALIDATE CHECKOUT] ERROR: Invalid nonce' );
		wp_send_json_error( array( 'message' => __( 'Session expired. Please refresh.', 'woocommerce' ) ) );
			return;
	}

		WC_Checkoutcom_Utility::logger( '[VALIDATE CHECKOUT] Nonce validated successfully' );

	// Pre-check actions.
		try {
			WC_Checkoutcom_Utility::logger( '[VALIDATE CHECKOUT] Running woocommerce_before_checkout_process hook' );
	do_action( 'woocommerce_before_checkout_process' );
			WC_Checkoutcom_Utility::logger( '[VALIDATE CHECKOUT] Running woocommerce_checkout_process hook' );
	do_action( 'woocommerce_checkout_process' );
			WC_Checkoutcom_Utility::logger( '[VALIDATE CHECKOUT] Hooks executed successfully' );
		} catch ( Exception $e ) {
			WC_Checkoutcom_Utility::logger( '[VALIDATE CHECKOUT] ERROR in hooks: ' . $e->getMessage() );
			WC_Checkoutcom_Utility::logger( '[VALIDATE CHECKOUT] ERROR stack trace: ' . $e->getTraceAsString() );
			wp_send_json_error( array( 'message' => __( 'Error during checkout validation: ', 'checkout-com-unified-payments-api' ) . $e->getMessage() ) );
			return;
		}

	// Run hook-based validation only (public API).
	$notices = wc_get_notices( 'error' );
	if ( ! empty( $notices ) ) {
		WC_Checkoutcom_Utility::logger( '[VALIDATE CHECKOUT] Validation errors found: ' . count( $notices ) );
		$messages = array();
		foreach ( $notices as $notice ) {
			if ( isset( $notice['notice'] ) ) {
				$messages[] = $notice['notice'];
			}
		}
		wc_clear_notices();
		wp_send_json_error( array( 'message' => implode( "\n", $messages ) ) );
		return;
	}

	// If everything passed, return success.
	WC_Checkoutcom_Utility::logger( '[VALIDATE CHECKOUT] Validation successful' );
	wp_send_json_success( array( 'message' => __( 'Validation successful', 'checkout-com-unified-payments-api' ) ) );
	} catch ( Exception $e ) {
		WC_Checkoutcom_Utility::logger( '[VALIDATE CHECKOUT] FATAL ERROR: ' . $e->getMessage() );
		WC_Checkoutcom_Utility::logger( '[VALIDATE CHECKOUT] FATAL ERROR stack trace: ' . $e->getTraceAsString() );
		wp_send_json_error( array( 'message' => __( 'Fatal error during validation: ', 'checkout-com-unified-payments-api' ) . $e->getMessage() ) );
	}
}

add_action( 'wp_ajax_cko_get_payment_session', 'cko_get_payment_session' );
add_action( 'wp_ajax_nopriv_cko_get_payment_session', 'cko_get_payment_session' );

/**
 * Retrieves payment session details from Checkout.com via AJAX.
 */
function cko_get_payment_session() {
	// Get the session ID from the request
	$session_id = isset( $_REQUEST['session_id'] ) ? sanitize_text_field( $_REQUEST['session_id'] ) : '';
	
	if ( empty( $session_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Session ID is required', 'checkout-com-unified-payments-api' ) ) );
	}
	
	WC_Checkoutcom_Utility::logger( '=== GET PAYMENT SESSION ===' );
	WC_Checkoutcom_Utility::logger( 'Session ID: ' . $session_id );
	
	try {
		// Get Checkout.com API settings
		$core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings' );
		$environment   = ! empty( $core_settings['ckocom_environment'] ) ? $core_settings['ckocom_environment'] : 'sandbox';
		$secret_key    = $environment === 'sandbox' 
			? ( ! empty( $core_settings['ckocom_sk'] ) ? $core_settings['ckocom_sk'] : '' )
			: ( ! empty( $core_settings['ckocom_sk_live'] ) ? $core_settings['ckocom_sk_live'] : '' );
		
		if ( empty( $secret_key ) ) {
			WC_Checkoutcom_Utility::logger( 'Error: Secret key not configured' );
			wp_send_json_error( array( 'message' => __( 'Payment gateway not properly configured', 'checkout-com-unified-payments-api' ) ) );
		}
		
		// Make API call to Checkout.com to get payment session details
		$api_url = $environment === 'sandbox' 
			? 'https://api.sandbox.checkout.com/payment-sessions/' . $session_id
			: 'https://api.checkout.com/payment-sessions/' . $session_id;
		
		WC_Checkoutcom_Utility::logger( 'API URL: ' . $api_url );
		
		$response = wp_remote_get(
			$api_url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $secret_key,
					'Content-Type'  => 'application/json',
				),
				'timeout' => 30,
			)
		);
		
		if ( is_wp_error( $response ) ) {
			WC_Checkoutcom_Utility::logger( 'WP Error: ' . $response->get_error_message() );
			wp_send_json_error( array( 'message' => __( 'Could not retrieve payment session', 'checkout-com-unified-payments-api' ) ) );
		}
		
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		
		WC_Checkoutcom_Utility::logger( 'API Response: ' . print_r( $data, true ) );
		
		// Check if we have payment data
		if ( isset( $data['payment'] ) && isset( $data['payment']['id'] ) ) {
			$payment_data = array(
				'payment_id'      => $data['payment']['id'],
				'payment_type'    => isset( $data['payment']['source']['type'] ) ? $data['payment']['source']['type'] : 'card',
				'three_ds_status' => isset( $data['payment']['3ds']['status'] ) ? $data['payment']['3ds']['status'] : '',
				'three_ds_auth_id' => isset( $data['payment']['3ds']['authentication_id'] ) ? $data['payment']['3ds']['authentication_id'] : '',
			);
			
			WC_Checkoutcom_Utility::logger( 'Payment data extracted: ' . print_r( $payment_data, true ) );
			
			wp_send_json_success( $payment_data );
		} else {
			WC_Checkoutcom_Utility::logger( 'Error: Payment data not found in response' );
			wp_send_json_error( array( 'message' => __( 'Payment data not found', 'checkout-com-unified-payments-api' ) ) );
		}
	} catch ( Exception $e ) {
		WC_Checkoutcom_Utility::logger( 'Exception: ' . $e->getMessage() );
		wp_send_json_error( array( 'message' => __( 'An error occurred while processing the payment session', 'checkout-com-unified-payments-api' ) ) );
	}
}

/**
 * Register a custom REST API route for checking payment status.
 */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'ckoplugin/v1',
			'/payment-status',
			array(
				'methods'             => 'GET',
				'callback'            => 'cko_get_payment_status',
				'permission_callback' => '__return_true',
			)
		);
	}
);

/**
 * Callback function to get payment status from Checkout.com API.
 *
 * @param WP_REST_Request $request The REST API request object.
 * @return WP_REST_Response JSON response with payment status or error.
 */
function cko_get_payment_status( $request ) {
	$payment_id    = sanitize_text_field( $request->get_param( 'paymentId' ) );
	$core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings' );
	$env           = $core_settings['ckocom_environment'];
	$secret_key    = $core_settings['ckocom_sk'];

	if ( empty( $payment_id ) ) {
		return new WP_REST_Response( array( 'error' => 'Missing paymentId' ), 400 );
	}

	$url = "https://api.checkout.com/payments/{$payment_id}";

	if ( 'sandbox' === $env ) {
		$url = "https://api.sandbox.checkout.com/payments/{$payment_id}";
	}

	// Send the GET request to Checkout.com.
	$response = wp_remote_get(
		$url,
		array(
			'headers' => array(
				'Authorization' => "Bearer $secret_key",
				'Content-Type'  => 'application/json',
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return new WP_REST_Response( array( 'error' => $response->get_error_message() ), 500 );
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	// Return API response.
	return new WP_REST_Response( $body, $code );
}

/**
 * Sends the updated cart information as a JSON response.
 *
 * Used in AJAX handlers to fetch updated cart data.
 *
 * @return void
 */
function get_updated_cart_info() {
	wp_send_json_success( WC_Checkoutcom_Api_Request::get_cart_info(true) );
}

add_action('wp_ajax_get_cart_info', 'get_updated_cart_info');
add_action('wp_ajax_nopriv_get_cart_info', 'get_updated_cart_info');
