<?php
/**
 * Webhook Queue Admin Page
 * 
 * Provides admin interface to view and manage the webhook queue table.
 *
 * @package wc_checkout_com
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Webhook Queue Admin class.
 */
class WC_Checkoutcom_Webhook_Queue_Admin {

	/**
	 * Initialize admin page.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
	}

	/**
	 * Add admin menu page.
	 * Note: This is now integrated into Checkout.com settings navigation.
	 * The menu is kept for backward compatibility but the main access is via settings.
	 */
	public static function add_admin_menu() {
		// Menu is now integrated into Checkout.com settings navigation
		// This method is kept for backward compatibility but won't create a separate menu
	}

	/**
	 * Handle admin actions (cleanup, etc.).
	 */
	public static function handle_actions() {
		// Handle actions from both settings page and standalone menu
		$is_settings_page = isset( $_GET['page'] ) && 'wc-settings' === $_GET['page'] && isset( $_GET['screen'] ) && 'webhook_queue' === $_GET['screen'];
		$is_standalone_page = isset( $_GET['page'] ) && 'checkout-com-webhook-queue' === $_GET['page'];
		
		if ( ! $is_settings_page && ! $is_standalone_page ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Handle cleanup action
		if ( isset( $_GET['action'] ) && 'cleanup' === $_GET['action'] ) {
			check_admin_referer( 'cko_webhook_queue_cleanup' );
			
			if ( class_exists( 'WC_Checkout_Com_Webhook_Queue' ) ) {
				WC_Checkout_Com_Webhook_Queue::cleanup_old_webhooks( 7 );
				WC_Checkout_Com_Webhook_Queue::cleanup_old_unprocessed_webhooks( 7 );
				
				add_action( 'admin_notices', function() {
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Old webhooks cleaned up successfully.', 'checkout-com-unified-payments-api' ) . '</p></div>';
				} );
			}
		}
	}

	/**
	 * Render admin page.
	 */
	public static function render_admin_page() {
		global $wpdb;
		
		if ( ! class_exists( 'WC_Checkout_Com_Webhook_Queue' ) ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Webhook Queue', 'checkout-com-unified-payments-api' ) . '</h1>';
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Webhook Queue class not found.', 'checkout-com-unified-payments-api' ) . '</p></div></div>';
			return;
		}

		$table_name = $wpdb->prefix . 'cko_pending_webhooks';
		
		// Check if table exists
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		
		if ( ! $table_exists ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Webhook Queue', 'checkout-com-unified-payments-api' ) . '</h1>';
			echo '<div class="notice notice-error"><p>' . sprintf( 
				esc_html__( 'Table %s does not exist. Please ensure the plugin is activated.', 'checkout-com-unified-payments-api' ),
				'<code>' . esc_html( $table_name ) . '</code>'
			) . '</p></div></div>';
			return;
		}

		// Get statistics
		$total_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
		$pending_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE processed_at IS NULL" );
		$processed_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE processed_at IS NOT NULL" );

		// Get records
		$records = $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT 100" );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Webhook Queue', 'checkout-com-unified-payments-api' ); ?></h1>
			
			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;">
				<div style="background: #f6f7f7; padding: 15px; border-radius: 4px; border-left: 4px solid #2271b1;">
					<h3 style="margin: 0 0 5px 0; font-size: 14px; color: #646970; text-transform: uppercase;"><?php esc_html_e( 'Total Webhooks', 'checkout-com-unified-payments-api' ); ?></h3>
					<div style="font-size: 32px; font-weight: bold; color: #1d2327;"><?php echo esc_html( $total_count ); ?></div>
				</div>
				<div style="background: #f6f7f7; padding: 15px; border-radius: 4px; border-left: 4px solid #f0b849;">
					<h3 style="margin: 0 0 5px 0; font-size: 14px; color: #646970; text-transform: uppercase;"><?php esc_html_e( 'Pending', 'checkout-com-unified-payments-api' ); ?></h3>
					<div style="font-size: 32px; font-weight: bold; color: #f0b849;"><?php echo esc_html( $pending_count ); ?></div>
				</div>
				<div style="background: #f6f7f7; padding: 15px; border-radius: 4px; border-left: 4px solid #00a32a;">
					<h3 style="margin: 0 0 5px 0; font-size: 14px; color: #646970; text-transform: uppercase;"><?php esc_html_e( 'Processed', 'checkout-com-unified-payments-api' ); ?></h3>
					<div style="font-size: 32px; font-weight: bold; color: #00a32a;"><?php echo esc_html( $processed_count ); ?></div>
				</div>
			</div>

			<div style="margin: 20px 0;">
				<?php
				// Determine the correct refresh URL based on how we got here
				$is_settings_page = isset( $_GET['page'] ) && 'wc-settings' === $_GET['page'] && isset( $_GET['screen'] ) && 'webhook_queue' === $_GET['screen'];
				if ( $is_settings_page ) {
					$refresh_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_cards&screen=webhook_queue' );
					$cleanup_url = wp_nonce_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_cards&screen=webhook_queue&action=cleanup' ), 'cko_webhook_queue_cleanup' );
				} else {
					$refresh_url = admin_url( 'admin.php?page=checkout-com-webhook-queue' );
					$cleanup_url = wp_nonce_url( admin_url( 'admin.php?page=checkout-com-webhook-queue&action=cleanup' ), 'cko_webhook_queue_cleanup' );
				}
				?>
				<a href="<?php echo esc_url( $refresh_url ); ?>" class="button"><?php esc_html_e( '🔄 Refresh', 'checkout-com-unified-payments-api' ); ?></a>
				<?php if ( $processed_count > 0 ) : ?>
					<a href="<?php echo esc_url( $cleanup_url ); ?>" 
					   class="button" 
					   onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete all processed webhooks older than 7 days?', 'checkout-com-unified-payments-api' ); ?>');">
						<?php esc_html_e( '🗑️ Cleanup Old Processed', 'checkout-com-unified-payments-api' ); ?>
					</a>
				<?php endif; ?>
			</div>

			<?php if ( empty( $records ) ) : ?>
				<div style="text-align: center; padding: 40px; color: #646970;">
					<h2><?php esc_html_e( '📭 No Webhooks Found', 'checkout-com-unified-payments-api' ); ?></h2>
					<p><?php esc_html_e( 'The table exists but contains no records.', 'checkout-com-unified-payments-api' ); ?></p>
				</div>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th style="width: 60px;"><?php esc_html_e( 'ID', 'checkout-com-unified-payments-api' ); ?></th>
							<th><?php esc_html_e( 'Payment ID', 'checkout-com-unified-payments-api' ); ?></th>
							<th><?php esc_html_e( 'Order ID', 'checkout-com-unified-payments-api' ); ?></th>
							<th><?php esc_html_e( 'Payment Session ID', 'checkout-com-unified-payments-api' ); ?></th>
							<th><?php esc_html_e( 'Type', 'checkout-com-unified-payments-api' ); ?></th>
							<th><?php esc_html_e( 'Status', 'checkout-com-unified-payments-api' ); ?></th>
							<th><?php esc_html_e( 'Created At', 'checkout-com-unified-payments-api' ); ?></th>
							<th><?php esc_html_e( 'Processed At', 'checkout-com-unified-payments-api' ); ?></th>
							<th><?php esc_html_e( 'Webhook Data', 'checkout-com-unified-payments-api' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $records as $record ) : 
							$webhook_data = json_decode( $record->webhook_data );
							$data_preview = substr( wp_json_encode( $webhook_data, JSON_PRETTY_PRINT ), 0, 100 );
							?>
							<tr>
								<td><?php echo esc_html( $record->id ); ?></td>
								<td><code><?php echo esc_html( $record->payment_id ); ?></code></td>
								<td><?php echo esc_html( $record->order_id ?: '—' ); ?></td>
								<td><code><?php echo esc_html( $record->payment_session_id ?: '—' ); ?></code></td>
								<td>
									<span class="button button-small" style="background: #2271b1; color: white; border: none; cursor: default;">
										<?php echo esc_html( $record->webhook_type ); ?>
									</span>
								</td>
								<td>
									<?php if ( $record->processed_at ) : ?>
										<span class="button button-small" style="background: #00a32a; color: white; border: none; cursor: default;">
											<?php esc_html_e( 'Processed', 'checkout-com-unified-payments-api' ); ?>
										</span>
									<?php else : ?>
										<span class="button button-small" style="background: #f0b849; color: #1d2327; border: none; cursor: default;">
											<?php esc_html_e( 'Pending', 'checkout-com-unified-payments-api' ); ?>
										</span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $record->created_at ); ?></td>
								<td><?php echo esc_html( $record->processed_at ?: '—' ); ?></td>
								<td>
									<div style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-family: monospace; font-size: 11px;" title="<?php echo esc_attr( wp_json_encode( $webhook_data, JSON_PRETTY_PRINT ) ); ?>">
										<?php echo esc_html( $data_preview ); ?>...
									</div>
									<details style="margin-top: 5px;">
										<summary style="cursor: pointer; color: #2271b1; font-size: 12px;"><?php esc_html_e( 'View Full JSON', 'checkout-com-unified-payments-api' ); ?></summary>
										<pre style="max-width: 100%; white-space: pre-wrap; word-break: break-all; font-family: monospace; font-size: 11px; background: #f6f7f7; padding: 10px; border-radius: 4px; max-height: 300px; overflow-y: auto;"><?php echo esc_html( wp_json_encode( $webhook_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
									</details>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php if ( $total_count > 100 ) : ?>
					<p style="margin-top: 20px; color: #646970;">
						<em><?php printf( esc_html__( 'Showing latest 100 records. Total: %d records.', 'checkout-com-unified-payments-api' ), esc_html( $total_count ) ); ?></em>
					</p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}
}

// Initialize admin page
WC_Checkoutcom_Webhook_Queue_Admin::init();

