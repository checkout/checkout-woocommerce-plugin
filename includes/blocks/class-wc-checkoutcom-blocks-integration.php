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
        // Check if WooCommerce Blocks is active
        if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Package' ) ) {
            return;
        }

        add_action( 'woocommerce_blocks_loaded', [ __CLASS__, 'register_blocks_integration' ] );
    }

    /**
     * Register blocks integration.
     */
    public static function register_blocks_integration() {
        // Register payment method integrations
        add_action( 'woocommerce_blocks_payment_method_type_registration', [ __CLASS__, 'register_payment_methods' ] );
    }

    /**
     * Register payment methods for blocks.
     *
     * @param Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry
     */
    public static function register_payment_methods( $payment_method_registry ) {
        // Register Checkout.com Cards
        $payment_method_registry->register(
            new WC_Checkoutcom_Cards_Blocks_Integration()
        );

        // Register Checkout.com PayPal
        $payment_method_registry->register(
            new WC_Checkoutcom_PayPal_Blocks_Integration()
        );

        // Register Checkout.com Google Pay
        $payment_method_registry->register(
            new WC_Checkoutcom_GooglePay_Blocks_Integration()
        );

        // Register Checkout.com Apple Pay
        $payment_method_registry->register(
            new WC_Checkoutcom_ApplePay_Blocks_Integration()
        );

        // Register Checkout.com Flow (if enabled)
        if ( class_exists( 'WC_Gateway_Checkout_Com_Flow' ) ) {
            $payment_method_registry->register(
                new WC_Checkoutcom_Flow_Blocks_Integration()
            );
        }
    }
}

// Initialize the integration
WC_Checkoutcom_Blocks_Integration::init();
