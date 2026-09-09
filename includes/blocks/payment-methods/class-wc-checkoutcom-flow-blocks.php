<?php
/**
 * Checkout.com Flow Block Integration
 *
 * @package wc_checkout_com
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Class WC_Checkoutcom_Flow_Blocks_Integration
 */
final class WC_Checkoutcom_Flow_Blocks_Integration extends AbstractPaymentMethodType {

    /**
     * Payment method name.
     *
     * @var string
     */
    protected $name = 'wc_checkout_com_flow';

    /**
     * Initialize the payment method type.
     */
    public function initialize() {
        $this->settings = get_option( 'woocommerce_wc_checkout_com_flow_settings', [] );
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
        $asset_path   = WC_CHECKOUTCOM_PLUGIN_PATH . '/build/flow-blocks.asset.php';
        $version      = WC_CHECKOUTCOM_PLUGIN_VERSION;
        $dependencies = [ 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ];

        if ( file_exists( $asset_path ) ) {
            $asset        = require $asset_path;
            $version      = is_array( $asset ) && isset( $asset['version'] ) ? $asset['version'] : $version;
            $dependencies = is_array( $asset ) && isset( $asset['dependencies'] ) ? $asset['dependencies'] : $dependencies;
        }

        wp_register_script(
            'wc-checkoutcom-flow-blocks',
            WC_CHECKOUTCOM_PLUGIN_URL . '/build/flow-blocks.js',
            $dependencies,
            $version,
            true
        );

        return [ 'wc-checkoutcom-flow-blocks' ];
    }

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     *
     * @return array
     */
    public function get_payment_method_data() {
        $core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings', [] );
        $environment   = 'sandbox' === ( $core_settings['ckocom_environment'] ?? 'sandbox' );

        // AJAX endpoint for creating the Flow payment session. The Blocks integration must
        // NOT rely on the classic cko_flow_vars global (that script is not loaded on block
        // checkout), so provide the endpoint URL directly here.
        $create_session_url = class_exists( 'WC_AJAX' )
            ? WC_AJAX::get_endpoint( 'cko_flow_create_payment_session' )
            : admin_url( 'admin-ajax.php?action=cko_flow_create_payment_session' );

        // ajax_create_payment_session() verifies this nonce (action 'cko_flow_payment_session').
        $create_session_nonce = wp_create_nonce( 'cko_flow_payment_session' );

        return [
            'title'       => $this->get_setting( 'title' ),
            'description' => $this->get_setting( 'description' ),
            'supports'    => $this->get_supported_features(),
            'environment' => $environment ? 'TEST' : 'PRODUCTION',
            'public_key'  => $core_settings['ckocom_pk'] ?? '',
            'currency'    => get_woocommerce_currency(),
            'create_session_url' => $create_session_url,
            'create_session_nonce' => $create_session_nonce,
            'enabled_payment_methods' => $this->get_setting( 'flow_enabled_payment_methods', [] ),
            'saved_payment_display_order' => $this->get_setting( 'saved_payment_display_order', 'saved_cards_first' ),
        ];
    }

    /**
     * Returns an array of supported features.
     *
     * @return string[]
     */
    public function get_supported_features() {
        return apply_filters( 'wc_checkoutcom_flow_supported_features', [
            'products',
            'refunds',
        ] );
    }
}
