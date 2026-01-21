<?php
/**
 * Checkout.com Diagnostics admin page.
 *
 * @package wc_checkout_com
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Checkoutcom_Diagnostics
 */
class WC_Checkoutcom_Diagnostics {

	/**
	 * Initialize admin hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
	}

	/**
	 * Add diagnostics submenu under WooCommerce.
	 *
	 * @return void
	 */
	public static function add_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Checkout.com Diagnostics', 'checkout-com-unified-payments-api' ),
			__( 'Checkout.com Diagnostics', 'checkout-com-unified-payments-api' ),
			'manage_woocommerce',
			'checkoutcom-diagnostics',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Render diagnostics page.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'checkout-com-unified-payments-api' ) );
		}

		$csr_result = null;
		if ( isset( $_POST['cko_run_csr_test'] ) ) {
			check_admin_referer( 'cko_run_csr_test' );
			$csr_result = self::run_csr_test();
		}

		$domain_result = self::get_domain_association_status();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Checkout.com Diagnostics', 'checkout-com-unified-payments-api' ); ?></h1>

			<div class="notice notice-info">
				<p><?php esc_html_e( 'These tools are intended for troubleshooting by administrators.', 'checkout-com-unified-payments-api' ); ?></p>
			</div>

			<h2><?php esc_html_e( 'Apple Pay Domain Association', 'checkout-com-unified-payments-api' ); ?></h2>
			<table class="widefat striped">
				<tbody>
					<tr>
						<th><?php esc_html_e( 'Directory', 'checkout-com-unified-payments-api' ); ?></th>
						<td><?php echo esc_html( $domain_result['well_known_dir'] ); ?></td>
						<td><?php echo wp_kses_post( $domain_result['dir_status'] ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'File', 'checkout-com-unified-payments-api' ); ?></th>
						<td><?php echo esc_html( $domain_result['file_path'] ); ?></td>
						<td><?php echo wp_kses_post( $domain_result['file_status'] ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'File URL', 'checkout-com-unified-payments-api' ); ?></th>
						<td><a href="<?php echo esc_url( $domain_result['file_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $domain_result['file_url'] ); ?></a></td>
						<td><?php echo wp_kses_post( $domain_result['url_status'] ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Rewrite Rules', 'checkout-com-unified-payments-api' ); ?></th>
						<td><?php esc_html_e( '.well-known rule', 'checkout-com-unified-payments-api' ); ?></td>
						<td><?php echo wp_kses_post( $domain_result['rewrite_status'] ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( '.htaccess', 'checkout-com-unified-payments-api' ); ?></th>
						<td><?php esc_html_e( 'Server rule check', 'checkout-com-unified-payments-api' ); ?></td>
						<td><?php echo wp_kses_post( $domain_result['htaccess_status'] ); ?></td>
					</tr>
				</tbody>
			</table>

			<?php if ( ! empty( $domain_result['file_preview'] ) ) : ?>
				<p><strong><?php esc_html_e( 'File preview (first 200 chars):', 'checkout-com-unified-payments-api' ); ?></strong></p>
				<pre><?php echo esc_html( $domain_result['file_preview'] ); ?></pre>
			<?php endif; ?>

			<hr />

			<h2><?php esc_html_e( 'Apple Pay CSR Generation Test', 'checkout-com-unified-payments-api' ); ?></h2>
			<p><?php esc_html_e( 'This will call Checkout.com and generate a CSR using your configured secret key.', 'checkout-com-unified-payments-api' ); ?></p>

			<form method="post">
				<?php wp_nonce_field( 'cko_run_csr_test' ); ?>
				<p>
					<button type="submit" class="button button-primary" name="cko_run_csr_test" value="1">
						<?php esc_html_e( 'Run CSR Test', 'checkout-com-unified-payments-api' ); ?>
					</button>
				</p>
			</form>

			<?php if ( is_array( $csr_result ) ) : ?>
				<div class="notice <?php echo esc_attr( $csr_result['success'] ? 'notice-success' : 'notice-error' ); ?>">
					<p><?php echo esc_html( $csr_result['message'] ); ?></p>
				</div>
				<?php if ( ! empty( $csr_result['details'] ) ) : ?>
					<pre><?php echo esc_html( $csr_result['details'] ); ?></pre>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Run CSR generation test via Checkout.com API.
	 *
	 * @return array
	 */
	private static function run_csr_test() {
		$core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings', array() );
		$secret_key_raw = isset( $core_settings['ckocom_sk'] ) ? $core_settings['ckocom_sk'] : '';

		if ( empty( $secret_key_raw ) ) {
			return array(
				'success' => false,
				'message' => __( 'Secret key is not configured. Please configure Checkout.com settings first.', 'checkout-com-unified-payments-api' ),
			);
		}

		$environment = isset( $core_settings['ckocom_environment'] ) ? $core_settings['ckocom_environment'] : 'sandbox';
		$account_type = isset( $core_settings['ckocom_account_type'] ) ? $core_settings['ckocom_account_type'] : 'ABC';
		$region = isset( $core_settings['ckocom_region'] ) ? $core_settings['ckocom_region'] : 'global';

		if ( function_exists( 'cko_is_nas_account' ) && cko_is_nas_account() ) {
			$account_type = 'NAS';
		}

		$base_url = ( 'sandbox' === $environment ) ? 'https://api.sandbox.checkout.com' : 'https://api.checkout.com';
		if ( 'global' !== $region && ! empty( $region ) ) {
			$base_url = str_replace( 'api.', $region . '.api.', $base_url );
		}

		$api_url = $base_url . '/applepay/signing-requests';
		$secret_key_clean = str_replace( 'Bearer ', '', trim( $secret_key_raw ) );
		$auth_header = ( 'NAS' === $account_type ) ? 'Bearer ' . $secret_key_clean : $secret_key_clean;

		$request_body = array(
			'protocol_version' => 'ec_v1',
		);

		$response = wp_remote_post(
			$api_url,
			array(
				'headers' => array(
					'Authorization' => $auth_header,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $request_body ),
				'timeout' => 30,
			)
		);

		// Retry with Bearer for ABC accounts on auth errors.
		if ( ( is_wp_error( $response ) || 401 === wp_remote_retrieve_response_code( $response ) || 400 === wp_remote_retrieve_response_code( $response ) ) && 'ABC' === $account_type ) {
			$response = wp_remote_post(
				$api_url,
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $secret_key_clean,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode( $request_body ),
					'timeout' => 30,
				)
			);
		}

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => __( 'CSR request failed.', 'checkout-com-unified-payments-api' ),
				'details' => $response->get_error_message(),
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 200 === $response_code || 201 === $response_code ) {
			$response_data = json_decode( $response_body, true );
			$csr = isset( $response_data['csr'] ) ? $response_data['csr'] : '';

			return array(
				'success' => true,
				'message' => __( 'CSR generated successfully.', 'checkout-com-unified-payments-api' ),
				'details' => $csr ? $csr : $response_body,
			);
		}

		return array(
			'success' => false,
			'message' => sprintf(
				/* translators: %s response code */
				__( 'CSR request failed with status %s.', 'checkout-com-unified-payments-api' ),
				(string) $response_code
			),
			'details' => $response_body,
		);
	}

	/**
	 * Collect domain association diagnostics.
	 *
	 * @return array
	 */
	private static function get_domain_association_status() {
		$well_known_dir = ABSPATH . '.well-known';
		$file_path = $well_known_dir . '/apple-developer-merchantid-domain-association';
		$file_url = home_url( '/.well-known/apple-developer-merchantid-domain-association' );

		$dir_status = file_exists( $well_known_dir )
			? '<span style="color:#00a32a;">' . esc_html__( 'Directory exists', 'checkout-com-unified-payments-api' ) . '</span>'
			: '<span style="color:#d63638;">' . esc_html__( 'Directory missing', 'checkout-com-unified-payments-api' ) . '</span>';

		$file_status = file_exists( $file_path )
			? '<span style="color:#00a32a;">' . esc_html__( 'File exists', 'checkout-com-unified-payments-api' ) . '</span>'
			: '<span style="color:#d63638;">' . esc_html__( 'File missing', 'checkout-com-unified-payments-api' ) . '</span>';

		$file_preview = '';
		if ( file_exists( $file_path ) ) {
			$file_content = file_get_contents( $file_path );
			if ( $file_content ) {
				$file_preview = substr( $file_content, 0, 200 ) . '...';
			}
		}

		$url_status = '<span style="color:#d63638;">' . esc_html__( 'Not checked', 'checkout-com-unified-payments-api' ) . '</span>';
		$response = wp_remote_get( $file_url, array( 'timeout' => 10 ) );
		if ( ! is_wp_error( $response ) ) {
			$status_code = wp_remote_retrieve_response_code( $response );
			if ( 200 === $status_code ) {
				$url_status = '<span style="color:#00a32a;">' . esc_html__( 'Accessible (200)', 'checkout-com-unified-payments-api' ) . '</span>';
			} else {
				$url_status = '<span style="color:#d63638;">' . esc_html__( 'HTTP status: ', 'checkout-com-unified-payments-api' ) . esc_html( (string) $status_code ) . '</span>';
			}
		}

		$rewrite_rules = get_option( 'rewrite_rules' );
		$has_well_known_rule = false;
		if ( is_array( $rewrite_rules ) ) {
			foreach ( $rewrite_rules as $pattern => $rewrite ) {
				if ( false !== strpos( $pattern, 'well-known' ) || false !== strpos( $rewrite, 'well-known' ) ) {
					$has_well_known_rule = true;
					break;
				}
			}
		}

		$rewrite_status = $has_well_known_rule
			? '<span style="color:#00a32a;">' . esc_html__( 'Rule found', 'checkout-com-unified-payments-api' ) . '</span>'
			: '<span style="color:#d63638;">' . esc_html__( 'Rule not found', 'checkout-com-unified-payments-api' ) . '</span>';

		$htaccess_file = ABSPATH . '.htaccess';
		if ( file_exists( $htaccess_file ) ) {
			$htaccess_content = file_get_contents( $htaccess_file );
			$htaccess_status = ( false !== strpos( $htaccess_content, 'well-known' ) )
				? '<span style="color:#00a32a;">' . esc_html__( '.well-known rule present', 'checkout-com-unified-payments-api' ) . '</span>'
				: '<span style="color:#dba617;">' . esc_html__( '.well-known rule not found', 'checkout-com-unified-payments-api' ) . '</span>';
		} else {
			$htaccess_status = '<span style="color:#dba617;">' . esc_html__( '.htaccess not found (common on Nginx)', 'checkout-com-unified-payments-api' ) . '</span>';
		}

		return array(
			'well_known_dir' => $well_known_dir,
			'file_path'      => $file_path,
			'file_url'       => $file_url,
			'dir_status'     => $dir_status,
			'file_status'    => $file_status,
			'url_status'     => $url_status,
			'rewrite_status' => $rewrite_status,
			'htaccess_status'=> $htaccess_status,
			'file_preview'   => $file_preview,
		);
	}
}

// Initialize diagnostics page.
WC_Checkoutcom_Diagnostics::init();

