<?php

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
		if ( empty( $screen ) ) {
			$screen = ! empty( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';
		}

		?>
		<style>
			.cko-admin-settings__links {
				padding-bottom: 20px;
				padding-top: 10px;
			}
			.cko-admin-settings__links li {
				color: #646970;
				font-size: 17px;
				font-weight: 600;
				display: inline-block;
				margin: 0;
				padding: 0;
				white-space: nowrap;
			}
			.cko-admin-settings__links a {
				text-decoration: none;
			}
			.cko-admin-settings__links .current {
				color: #000;
			}
		</style>
		<div class="cko-admin-settings__links">
			<ul>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_cards' ) ); ?>"
						class="<?php echo 'wc_checkout_com_cards' === $screen ? 'current' : null; ?>">
						<?php esc_html_e( 'Core Settings', 'wc_checkout_com' ); ?></a> | </li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_cards&screen=card_settings' ) ); ?>"
						class="<?php echo 'card_settings' === $screen ? 'current' : null; ?>">
						<?php esc_html_e( 'Card Settings', 'wc_checkout_com' ); ?></a> | </li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_cards&screen=orders_settings' ) ); ?>"
						class="<?php echo 'orders_settings' === $screen ? 'current' : null; ?>">
						<?php esc_html_e( 'Order Settings', 'wc_checkout_com' ); ?></a> | </li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_google_pay' ) ); ?>"
						class="<?php echo 'wc_checkout_com_google_pay' === $screen ? 'current' : null; ?>">
						<?php esc_html_e( 'Google Pay', 'wc_checkout_com' ); ?></a> | </li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_apple_pay' ) ); ?>"
						class="<?php echo 'wc_checkout_com_apple_pay' === $screen ? 'current' : null; ?>">
						<?php esc_html_e( 'Apple Pay', 'wc_checkout_com' ); ?></a> | </li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_alternative_payments' ) ); ?>"
						class="<?php echo 'wc_checkout_com_alternative_payments' === $screen ? 'current' : null; ?>">
						<?php esc_html_e( 'Alternative Payments', 'wc_checkout_com' ); ?></a> | </li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_cards&screen=debug_settings' ) ); ?>"
						class="<?php echo 'debug_settings' === $screen ? 'current' : null; ?>">
						<?php esc_html_e( 'Debug Settings', 'wc_checkout_com' ); ?></a> | </li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_cards&screen=webhook' ) ); ?>"
						class="<?php echo 'webhook' === $screen ? 'current' : null; ?>">
						<?php esc_html_e( 'Webhook', 'wc_checkout_com' ); ?></a></li>
			</ul>
		</div>
		<?php
	}
}
