<?php
/**
 * WooCommerce Blocks Integration for Checkout.com
 *
 * @package wc_checkout_com
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Checkoutcom_Blocks_Integration
 */
class WC_Checkoutcom_Blocks_Integration {

    /**
     * Initialize the integration.
     */
    public static function init() {
        // Check if WooCommerce Blocks is active - use multiple checks for compatibility
        if ( ! self::is_woocommerce_blocks_active() ) {
            return;
        }

        // Hook into blocks loading. NOTE: do not guard this with has_action() —
        // has_action( $hook ) reports whether a callback is already attached, not whether
        // the hook will fire, so guarding our own add_action() behind it meant we only
        // registered if something else had hooked first (usually nothing → never registered).
        add_action( 'woocommerce_blocks_loaded', [ __CLASS__, 'register_blocks_integration' ] );
    }

    /**
     * Load the individual Blocks payment-method integration classes.
     *
     * These files are not covered by any autoloader, so they must be required
     * explicitly — otherwise the class_exists() checks in register_payment_methods()
     * all return false and nothing registers.
     */
    private static function load_payment_method_classes() {
        $dir = __DIR__ . '/payment-methods/';
        $files = [
            'class-wc-checkoutcom-cards-blocks.php',
            'class-wc-checkoutcom-paypal-blocks.php',
            'class-wc-checkoutcom-googlepay-blocks.php',
            'class-wc-checkoutcom-applepay-blocks.php',
            'class-wc-checkoutcom-flow-blocks.php',
        ];
        foreach ( $files as $file ) {
            if ( file_exists( $dir . $file ) ) {
                require_once $dir . $file;
            }
        }
    }

    /**
     * Check if WooCommerce Blocks is active.
     *
     * @return bool
     */
    private static function is_woocommerce_blocks_active() {
        // Multiple checks for different versions
        return (
            class_exists( 'Automattic\WooCommerce\Blocks\Package' ) ||
            class_exists( 'WooCommerce\Blocks\Package' ) ||
            function_exists( 'woocommerce_blocks_loaded' ) ||
            defined( 'WC_BLOCKS_VERSION' )
        );
    }

    /**
     * Register blocks integration.
     */
    public static function register_blocks_integration() {
        // Make the payment-method classes available before registration runs.
        self::load_payment_method_classes();

        // Hook the registration callback. As above, do not gate this behind has_action().
        add_action( 'woocommerce_blocks_payment_method_type_registration', [ __CLASS__, 'register_payment_methods' ] );
    }

    /**
     * Register payment methods for blocks.
     *
     * @param mixed $payment_method_registry
     */
    public static function register_payment_methods( $payment_method_registry ) {
        try {
            // Only proceed if we have a valid registry object
            if ( ! is_object( $payment_method_registry ) || ! method_exists( $payment_method_registry, 'register' ) ) {
                return;
            }

            // Register Checkout.com Cards
            if ( class_exists( 'WC_Checkoutcom_Cards_Blocks_Integration' ) ) {
                $payment_method_registry->register(
                    new WC_Checkoutcom_Cards_Blocks_Integration()
                );
            }

            // Register Checkout.com PayPal
            if ( class_exists( 'WC_Checkoutcom_PayPal_Blocks_Integration' ) ) {
                $payment_method_registry->register(
                    new WC_Checkoutcom_PayPal_Blocks_Integration()
                );
            }

            // Register Checkout.com Google Pay
            if ( class_exists( 'WC_Checkoutcom_GooglePay_Blocks_Integration' ) ) {
                $payment_method_registry->register(
                    new WC_Checkoutcom_GooglePay_Blocks_Integration()
                );
            }

            // Register Checkout.com Apple Pay
            if ( class_exists( 'WC_Checkoutcom_ApplePay_Blocks_Integration' ) ) {
                $payment_method_registry->register(
                    new WC_Checkoutcom_ApplePay_Blocks_Integration()
                );
            }

            // Register Checkout.com Flow (if enabled)
            if ( class_exists( 'WC_Gateway_Checkout_Com_Flow' ) && class_exists( 'WC_Checkoutcom_Flow_Blocks_Integration' ) ) {
                $payment_method_registry->register(
                    new WC_Checkoutcom_Flow_Blocks_Integration()
                );
            }
        } catch ( Exception $e ) {
            // Log error but don't break the site
            if ( class_exists( 'WC_Checkoutcom_Utility' ) ) {
                WC_Checkoutcom_Utility::logger( 'Checkout.com Blocks Integration Error: ' . $e->getMessage() );
            }
        }
    }
}

// Initialize the integration only if WooCommerce is active
if ( class_exists( 'WooCommerce' ) ) {
    WC_Checkoutcom_Blocks_Integration::init();
}
