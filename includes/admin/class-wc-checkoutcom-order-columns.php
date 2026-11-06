<?php
/**
 * Order Columns Admin
 *
 * Adds custom columns to the WooCommerce orders list table.
 *
 * @package wc_checkout_com
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Order Columns Admin class.
 */
class WC_Checkoutcom_Order_Columns {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		// HPOS (High-Performance Order Storage) - WooCommerce 7.1+
		add_filter( 'woocommerce_shop_order_list_table_columns', array( __CLASS__, 'add_order_columns' ) );
		add_action( 'woocommerce_shop_order_list_table_custom_column', array( __CLASS__, 'render_order_column_hpos' ), 10, 2 );

		// Legacy (CPT-based orders)
		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'add_order_columns' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'render_order_column_legacy' ), 10, 2 );

		// Make column sortable (both HPOS and legacy)
		add_filter( 'manage_edit-shop_order_sortable_columns', array( __CLASS__, 'make_columns_sortable' ) );
		add_filter( 'woocommerce_shop_order_list_table_sortable_columns', array( __CLASS__, 'make_columns_sortable' ) );

		// Add CSS for column width
		add_action( 'admin_head', array( __CLASS__, 'add_column_styles' ) );

		// Click-to-copy for the payment ID column.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
	}

	/**
	 * Whether the current admin screen is an orders list (HPOS or legacy).
	 *
	 * @return bool
	 */
	private static function is_orders_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return $screen && ( 'edit-shop_order' === $screen->id || 'woocommerce_page_wc-orders' === $screen->id );
	}

	/**
	 * Enqueue the click-to-copy script on the orders list.
	 */
	public static function enqueue_scripts() {
		if ( ! self::is_orders_screen() ) {
			return;
		}

		wp_enqueue_script(
			'cko-admin-order-columns',
			WC_CHECKOUTCOM_PLUGIN_URL . '/assets/js/admin-order-columns.js',
			array(),
			WC_CHECKOUTCOM_PLUGIN_VERSION,
			true
		);
		wp_localize_script(
			'cko-admin-order-columns',
			'ckoOrderColumns',
			array(
				'copied' => __( 'Copied!', 'checkout-com-unified-payments-api' ),
			)
		);
	}

	/**
	 * Add custom columns to orders list.
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public static function add_order_columns( $columns ) {
		$new_columns = array();

		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;

			// Add our column after the order_status column
			if ( 'order_status' === $key ) {
				$new_columns['cko_payment_id'] = __( 'CKO Payment ID', 'checkout-com-unified-payments-api' );
			}
		}

		return $new_columns;
	}

	/**
	 * Render column content for HPOS orders.
	 *
	 * @param string   $column_name Column identifier.
	 * @param WC_Order $order       Order object.
	 */
	public static function render_order_column_hpos( $column_name, $order ) {
		if ( 'cko_payment_id' === $column_name ) {
			self::render_payment_id_column( $order );
		}
	}

	/**
	 * Render column content for legacy (CPT) orders.
	 *
	 * @param string $column_name Column identifier.
	 * @param int    $post_id     Post/Order ID.
	 */
	public static function render_order_column_legacy( $column_name, $post_id ) {
		if ( 'cko_payment_id' === $column_name ) {
			$order = wc_get_order( $post_id );
			if ( $order ) {
				self::render_payment_id_column( $order );
			}
		}
	}

	/**
	 * Render the payment ID column content.
	 *
	 * @param WC_Order $order Order object.
	 */
	private static function render_payment_id_column( $order ) {
		// Try Flow payment ID first, then fallback to regular payment ID
		$payment_id = $order->get_meta( '_cko_flow_payment_id' );

		if ( empty( $payment_id ) ) {
			$payment_id = $order->get_meta( '_cko_payment_id' );
		}

		if ( ! empty( $payment_id ) && WC_Checkoutcom_Utility::is_valid_payment_id( $payment_id ) ) {
			// Copied by assets/js/admin-order-columns.js (no inline script).
			printf(
				'<span class="cko-payment-id" title="%s" style="cursor: pointer;" data-cko-payment-id="%s">%s</span>',
				esc_attr( __( 'Click to copy', 'checkout-com-unified-payments-api' ) ),
				esc_attr( $payment_id ),
				esc_html( $payment_id )
			);
		} elseif ( ! empty( $payment_id ) ) {
			printf( '<span class="cko-payment-id-other">%s</span>', esc_html( (string) $payment_id ) );
		} else {
			echo '<span class="cko-payment-id-empty" style="color: #999;">—</span>';
		}
	}

	/**
	 * Make the column sortable.
	 *
	 * @param array $columns Sortable columns.
	 * @return array Modified sortable columns.
	 */
	public static function make_columns_sortable( $columns ) {
		$columns['cko_payment_id'] = 'cko_payment_id';
		return $columns;
	}

	/**
	 * Add CSS styles for the column.
	 */
	public static function add_column_styles() {
		// Check if we're on the orders page (both HPOS and legacy)
		if ( self::is_orders_screen() ) {
			?>
			<style type="text/css">
				.column-cko_payment_id {
					width: 280px;
					word-break: break-all;
				}
				.cko-payment-id {
					font-family: monospace;
					font-size: 12px;
					background: #f0f0f1;
					padding: 2px 6px;
					border-radius: 3px;
					display: inline-block;
				}
				.cko-payment-id:hover {
					background: #dcdcde;
				}
			</style>
			<?php
		}
	}
}

// Initialize order columns.
WC_Checkoutcom_Order_Columns::init();
