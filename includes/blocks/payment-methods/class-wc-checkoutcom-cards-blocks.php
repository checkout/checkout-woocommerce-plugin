<?php
/**
 * Checkout.com Cards Block Integration
 *
 * @package wc_checkout_com
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Class WC_Checkoutcom_Cards_Blocks_Integration
 */
final class WC_Checkoutcom_Cards_Blocks_Integration extends AbstractPaymentMethodType {

    /**
     * Payment method name.
     *
     * @var string
     */
    protected $name = 'wc_checkout_com_cards';

    /**
     * Initialize the payment method type.
     */
    public function initialize() {
        $this->settings = get_option( 'woocommerce_wc_checkout_com_cards_settings', [] );
    }

    /**
     * Returns if this payment method should be active.
     *
     * @return boolean
     */
    public function is_active() {
        return ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles() {
        $asset_path   = WC_CHECKOUTCOM_PLUGIN_PATH . '/build/index.asset.php';
        $version      = WC_CHECKOUTCOM_PLUGIN_VERSION;
        $dependencies = [ 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ];

        if ( file_exists( $asset_path ) ) {
            $asset        = require $asset_path;
            $version      = is_array( $asset ) && isset( $asset['version'] ) ? $asset['version'] : $version;
            $dependencies = is_array( $asset ) && isset( $asset['dependencies'] ) ? $asset['dependencies'] : $dependencies;
        }

        wp_register_script(
            'wc-checkoutcom-cards-blocks',
            WC_CHECKOUTCOM_PLUGIN_URL . '/assets/js/blocks/cards-blocks.js',
            $dependencies,
            $version,
            true
        );

        return [ 'wc-checkoutcom-cards-blocks' ];
    }

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     *
     * @return array
     */
    public function get_payment_method_data() {
        return [
            'title'       => $this->get_setting( 'title' ),
            'description' => $this->get_setting( 'description' ),
            'supports'    => $this->get_supported_features(),
            'environment' => $this->get_setting( 'ckocom_environment' ),
            'public_key'  => $this->get_setting( 'ckocom_pk' ),
            'checkout_mode' => $this->get_setting( 'ckocom_checkout_mode' ),
        ];
    }

    /**
     * Returns an array of supported features.
     *
     * @return string[]
     */
    public function get_supported_features() {
        return apply_filters( 'wc_checkoutcom_cards_supported_features', [
            'products',
            'refunds',
        ] );
    }
}
