<?php
/**
 * Plugin admin settings class.
 *
 * @package wc_checkout_com
 */

/**
 * The admin-specific functionality of the plugin.
 */
class WC_Checkoutcom_Admin {

	/**
	 * Generate HTML links for the settings page.
	 *
	 * @param string $key   Key.
	 * @param array  $value Value.
	 *
	 * @return void
	 */
	public static function generate_links( $key, $value ) {

		$screen = ! empty( $_GET['screen'] ) ? sanitize_text_field( wp_unslash( $_GET['screen'] ) ) : '';
		$section = ! empty( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';
		
		// Map sections to screens for proper tab highlighting
		$section_to_screen_map = array(
			'wc_checkout_com_cards' => 'quick_settings',
			'wc_checkout_com_google_pay' => 'express_payments',
			'wc_checkout_com_apple_pay' => 'express_payments',
			'wc_checkout_com_paypal' => 'express_payments',
			'wc_checkout_com_alternative_payments' => 'wc_checkout_com_alternative_payments',
			'wc_checkout_com_flow' => 'wc_checkout_com_flow_settings',
		);
		
		// If no screen but we have a section, map it to screen
		if ( empty( $screen ) && ! empty( $section ) ) {
			if ( isset( $section_to_screen_map[ $section ] ) ) {
				$screen = $section_to_screen_map[ $section ];
			} elseif ( 'wc_checkout_com_cards' === $section ) {
				$screen = 'quick_settings';
			}
		}
		
		// If still empty, default to quick_settings
		if ( empty( $screen ) ) {
			$screen = 'quick_settings';
		}

		$checkout_setting = get_option( 'woocommerce_wc_checkout_com_cards_settings' );
		$checkout_mode    = isset( $checkout_setting['ckocom_checkout_mode'] ) ? $checkout_setting['ckocom_checkout_mode'] : 'flow';
		
		// Determine if we're in express payments sub-tab
		$express_subtab = '';
		$is_express_payment = in_array( $section, array( 'wc_checkout_com_google_pay', 'wc_checkout_com_apple_pay', 'wc_checkout_com_paypal' ), true );
		if ( $is_express_payment ) {
			$express_subtab = $section; // Store the original section
			$screen         = 'express_payments'; // Mark Express Payments tab as active
		} elseif ( 'express_payments' === $screen ) {
			// If section is set, use it; otherwise use subtab; otherwise default to Google Pay
			$express_subtab = ! empty( $section ) ? $section : ( ! empty( $_GET['subtab'] ) ? sanitize_text_field( wp_unslash( $_GET['subtab'] ) ) : 'wc_checkout_com_google_pay' );
		}
		
		// Determine if we're in advanced sub-tab
		$advanced_subtab = '';
		if ( in_array( $screen, array( 'webhook_queue', 'debug_settings' ), true ) ) {
			$advanced_subtab = $screen;
			$screen          = 'advanced';
		} elseif ( 'advanced' === $screen ) {
			$advanced_subtab = ! empty( $_GET['subtab'] ) ? sanitize_text_field( wp_unslash( $_GET['subtab'] ) ) : 'webhook_queue';
		}
		
		// Handle direct access to alternative payments
		if ( 'wc_checkout_com_alternative_payments' === $section ) {
			$screen = 'wc_checkout_com_alternative_payments';
		}
		
		// Handle direct access to flow settings
		if ( 'wc_checkout_com_flow' === $section ) {
			$screen = 'wc_checkout_com_flow_settings';
		}
		
		?>
		<div class="cko-settings-nav">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_cards&screen=quick_settings' ) ); ?>"
				class="cko-nav-tab <?php echo 'quick_settings' === $screen ? 'active' : ''; ?>">
				<span class="cko-nav-icon">⚡</span>
				<span class="cko-nav-label"><?php esc_html_e( 'Quick Setup', 'checkout-com-unified-payments-api' ); ?></span>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_cards&screen=card_settings' ) ); ?>"
				class="cko-nav-tab <?php echo 'card_settings' === $screen ? 'active' : ''; ?>">
				<span class="cko-nav-icon">💳</span>
				<span class="cko-nav-label"><?php esc_html_e( 'Card Settings', 'checkout-com-unified-payments-api' ); ?></span>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_cards&screen=express_payments&subtab=wc_checkout_com_google_pay' ) ); ?>"
				class="cko-nav-tab <?php echo 'express_payments' === $screen ? 'active' : ''; ?>">
				<span class="cko-nav-icon">📱</span>
				<span class="cko-nav-label"><?php esc_html_e( 'Express Payments', 'checkout-com-unified-payments-api' ); ?></span>
			</a>
			<?php if ( 'flow' !== $checkout_mode ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_alternative_payments' ) ); ?>"
					class="cko-nav-tab <?php echo 'wc_checkout_com_alternative_payments' === $screen ? 'active' : ''; ?>">
					<span class="cko-nav-icon">🌍</span>
					<span class="cko-nav-label"><?php esc_html_e( 'Alternative Payments', 'checkout-com-unified-payments-api' ); ?></span>
				</a>
			<?php endif; ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_cards&screen=orders_settings' ) ); ?>"
				class="cko-nav-tab <?php echo 'orders_settings' === $screen ? 'active' : ''; ?>">
				<span class="cko-nav-icon">📦</span>
				<span class="cko-nav-label"><?php esc_html_e( 'Order Settings', 'checkout-com-unified-payments-api' ); ?></span>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_cards&screen=advanced&subtab=webhook_queue' ) ); ?>"
				class="cko-nav-tab <?php echo 'advanced' === $screen ? 'active' : ''; ?>">
				<span class="cko-nav-icon">🔧</span>
				<span class="cko-nav-label"><?php esc_html_e( 'Advanced', 'checkout-com-unified-payments-api' ); ?></span>
			</a>
			<?php if ( 'flow' === $checkout_mode ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_flow' ) ); ?>"
					class="cko-nav-tab <?php echo 'wc_checkout_com_flow_settings' === $screen ? 'active' : ''; ?>">
					<span class="cko-nav-icon">🎨</span>
					<span class="cko-nav-label"><?php esc_html_e( 'Flow Settings', 'checkout-com-unified-payments-api' ); ?></span>
				</a>
			<?php endif; ?>
		</div>
		
		<?php
		// Show sub-tabs for Express Payments
		if ( 'express_payments' === $screen || $is_express_payment ) {
			// Determine active sub-tab - $express_subtab should already be set correctly above
			if ( empty( $express_subtab ) ) {
				// Fallback: try to get from section or subtab
				$express_subtab = ! empty( $section ) ? $section : ( ! empty( $_GET['subtab'] ) ? sanitize_text_field( wp_unslash( $_GET['subtab'] ) ) : 'wc_checkout_com_google_pay' );
			}
			?>
			<div class="cko-sub-nav">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_google_pay' ) ); ?>"
					class="cko-sub-nav-tab <?php echo 'wc_checkout_com_google_pay' === $express_subtab ? 'active' : ''; ?>">
					<?php esc_html_e( 'Google Pay', 'checkout-com-unified-payments-api' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_apple_pay' ) ); ?>"
					class="cko-sub-nav-tab <?php echo 'wc_checkout_com_apple_pay' === $express_subtab ? 'active' : ''; ?>">
					<?php esc_html_e( 'Apple Pay', 'checkout-com-unified-payments-api' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_paypal' ) ); ?>"
					class="cko-sub-nav-tab <?php echo 'wc_checkout_com_paypal' === $express_subtab ? 'active' : ''; ?>">
					<?php esc_html_e( 'PayPal', 'checkout-com-unified-payments-api' ); ?>
				</a>
			</div>
			<?php
		}
		
		// Show sub-tabs for Advanced
		if ( 'advanced' === $screen || in_array( $screen, array( 'webhook_queue', 'debug_settings' ), true ) ) {
			// Determine active sub-tab
			if ( in_array( $screen, array( 'webhook_queue', 'debug_settings' ), true ) ) {
				$advanced_subtab = $screen;
			} else {
				$advanced_subtab = ! empty( $_GET['subtab'] ) ? sanitize_text_field( wp_unslash( $_GET['subtab'] ) ) : 'webhook_queue';
			}
			?>
			<div class="cko-sub-nav">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_cards&screen=advanced&subtab=webhook_queue' ) ); ?>"
					class="cko-sub-nav-tab <?php echo 'webhook_queue' === $advanced_subtab ? 'active' : ''; ?>">
					<?php esc_html_e( 'Webhook Queue', 'checkout-com-unified-payments-api' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_cards&screen=advanced&subtab=debug_settings' ) ); ?>"
					class="cko-sub-nav-tab <?php echo 'debug_settings' === $advanced_subtab ? 'active' : ''; ?>">
					<?php esc_html_e( 'Debug Settings', 'checkout-com-unified-payments-api' ); ?>
				</a>
			</div>
			<?php
		}
	}
}
