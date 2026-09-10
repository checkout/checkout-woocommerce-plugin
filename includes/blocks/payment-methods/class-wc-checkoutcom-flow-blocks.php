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

        // The submit step (server finalises amount/capture_on and calls Checkout.com's
        // /payment-sessions/{id}/submit) — same nonce action as create.
        $submit_session_url = class_exists( 'WC_AJAX' )
            ? WC_AJAX::get_endpoint( 'cko_flow_submit_payment_session' )
            : admin_url( 'admin-ajax.php?action=cko_flow_submit_payment_session' );

        // Both create and submit verify this nonce (action 'cko_flow_payment_session').
        $create_session_nonce = wp_create_nonce( 'cko_flow_payment_session' );

        // admin-ajax endpoint that persists the "save card" choice into the WC session
        // (action cko_flow_store_save_card_preference, same nonce). Needed so the preference
        // survives the 3DS full-page redirect — on that path the Blocks paymentMethodData is
        // lost, exactly as in the classic front-end.
        $store_save_card_url = admin_url( 'admin-ajax.php' );

        // Whether a CVV is required when paying with a saved card. Mirrors the classic
        // create_payment() check (WC_Checkoutcom_Api_Request), which reads
        // wc_checkout_com_cards-card-cvv when this admin setting is on.
        $require_cvv = (bool) WC_Admin_Settings::get_option( 'ckocom_card_require_cvv' );

        // Whether the "Enable Save Cards" admin feature is on. Classic Flow vaults the card by
        // sending payment_method_configuration.card.store_payment_details = "enabled" in the
        // create-session body whenever this admin setting is on (not the per-order checkbox);
        // that is what makes Checkout.com return source.id so a token can be stored later.
        $save_card_enabled = (bool) WC_Admin_Settings::get_option( 'ckocom_card_saved' );

        return [
            'title'       => $this->get_setting( 'title' ),
            'description' => $this->get_setting( 'description' ),
            'supports'    => $this->get_supported_features(),
            'environment' => $environment ? 'TEST' : 'PRODUCTION',
            'public_key'  => $core_settings['ckocom_pk'] ?? '',
            'currency'    => get_woocommerce_currency(),
            'create_session_url' => $create_session_url,
            'submit_session_url' => $submit_session_url,
            'store_save_card_url' => $store_save_card_url,
            // Nonce-protected REST route to read a payment's status/decline reason by id, used to
            // show the real decline message after a 3DS-failure return. Same nonce as create/submit.
            'payment_status_url' => rest_url( 'ckoplugin/v1/payment-status' ),
            'create_session_nonce' => $create_session_nonce,
            'is_user_logged_in' => is_user_logged_in(),
            'enabled_payment_methods' => $this->get_setting( 'flow_enabled_payment_methods', [] ),
            'saved_payment_display_order' => $this->get_setting( 'saved_payment_display_order', 'saved_cards_first' ),
            'require_cvv' => $require_cvv,
            'save_card_enabled' => $save_card_enabled,
        ];
    }

    /**
     * Returns an array of supported features.
     *
     * 'tokenization' lets WooCommerce Blocks render the saved-card radio list and the
     * "save payment information" checkbox for this method. Saved Flow cards are stored as
     * WC_Payment_Token_CC rows under this gateway id ('wc_checkout_com_flow'), so Blocks
     * matches and displays them automatically.
     *
     * @return string[]
     */
    public function get_supported_features() {
        $features = [
            'products',
            'refunds',
        ];

        // Only advertise tokenization when the admin "Enable Save Cards" setting is on. With it
        // off, Blocks must not render the saved-card list or the save-card checkbox (matches
        // classic, which hides both). Declaring tokenization is what makes Blocks surface saved
        // tokens, so gate it here rather than only on the JS supports flags.
        if ( (bool) WC_Admin_Settings::get_option( 'ckocom_card_saved' ) ) {
            $features[] = 'tokenization';
        }

        return apply_filters( 'wc_checkoutcom_flow_supported_features', $features );
    }
}
