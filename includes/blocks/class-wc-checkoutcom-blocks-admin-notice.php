<?php
/**
 * WooCommerce Blocks Admin Notice
 *
 * @package wc_checkout_com
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Checkoutcom_Blocks_Admin_Notice
 */
class WC_Checkoutcom_Blocks_Admin_Notice {

    /**
     * Initialize the admin notice.
     */
    public static function init() {
        add_action( 'admin_notices', [ __CLASS__, 'show_blocks_compatibility_notice' ] );
        add_action( 'wp_ajax_checkoutcom_dismiss_blocks_notice', [ __CLASS__, 'dismiss_notice' ] );
    }

    /**
     * Show blocks compatibility notice.
     */
    public static function show_blocks_compatibility_notice() {
        // Only show on WooCommerce settings pages
        if ( ! self::is_woocommerce_settings_page() ) {
            return;
        }

        // Check if notice was dismissed
        if ( get_option( 'checkoutcom_blocks_notice_dismissed', false ) ) {
            return;
        }

        // Check if WooCommerce Blocks is active
        if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Package' ) ) {
            return;
        }

        ?>
        <div class="notice notice-info is-dismissible checkoutcom-blocks-notice" data-notice="blocks-compatibility">
            <p>
                <strong><?php esc_html_e( 'Checkout.com Payment Gateway', 'checkout-com-unified-payments-api' ); ?></strong>
            </p>
            <p>
                <?php esc_html_e( 'Great news! Your Checkout.com plugin now supports WooCommerce Block-based Checkout.', 'checkout-com-unified-payments-api' ); ?>
                <?php esc_html_e( 'You can use both traditional checkout and the new block checkout without any issues.', 'checkout-com-unified-payments-api' ); ?>
            </p>
            <p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ); ?>" class="button button-primary">
                    <?php esc_html_e( 'View Checkout Settings', 'checkout-com-unified-payments-api' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_cards' ) ); ?>" class="button">
                    <?php esc_html_e( 'Configure Payment Methods', 'checkout-com-unified-payments-api' ); ?>
                </a>
            </p>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $(document).on('click', '.checkoutcom-blocks-notice .notice-dismiss', function() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'checkoutcom_dismiss_blocks_notice',
                        nonce: '<?php echo wp_create_nonce( 'checkoutcom_dismiss_blocks_notice' ); ?>'
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Dismiss the notice.
     */
    public static function dismiss_notice() {
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'checkoutcom_dismiss_blocks_notice' ) ) {
            wp_die( 'Security check failed' );
        }

        update_option( 'checkoutcom_blocks_notice_dismissed', true );
        wp_die();
    }

    /**
     * Check if we're on a WooCommerce settings page.
     *
     * @return bool
     */
    private static function is_woocommerce_settings_page() {
        $screen = get_current_screen();
        
        if ( ! $screen ) {
            return false;
        }

        return (
            'woocommerce_page_wc-settings' === $screen->id ||
            'woocommerce_page_wc-orders' === $screen->id ||
            'woocommerce_page_wc-reports' === $screen->id
        );
    }
}

// Initialize the admin notice
WC_Checkoutcom_Blocks_Admin_Notice::init();
