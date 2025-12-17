<?php
/**
 * Apple Pay method class.
 *
 * @package wc_checkout_com
 */

defined( 'ABSPATH' ) || exit;

require_once 'settings/class-wc-checkoutcom-cards-settings.php';
<<<<<<< HEAD
=======
require_once 'express/apple-pay/class-apple-pay-express.php';
>>>>>>> upstream/feature/flow-integration-v5.0.0-beta

/**
 * Class WC_Gateway_Checkout_Com_Apple_Pay for Apple Pay method.
 */
#[AllowDynamicProperties]
class WC_Gateway_Checkout_Com_Apple_Pay extends WC_Payment_Gateway {

	/**
	 * WC_Gateway_Checkout_Com_Apple_Pay constructor.
	 */
	public function __construct() {
		$this->id                 = 'wc_checkout_com_apple_pay';
		$this->method_title       = __( 'Checkout.com', 'checkout-com-unified-payments-api' );
		$this->method_description = __( 'The Checkout.com extension allows shop owners to process online payments through the <a href="https://www.checkout.com">Checkout.com Payment Gateway.</a>', 'checkout-com-unified-payments-api' );
		$this->title              = __( 'Apple Pay', 'checkout-com-unified-payments-api' );
		$this->has_fields         = true;
		$this->supports           = [
			'products',
			'refunds',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_date_changes',
		];

		$this->init_form_fields();
		$this->init_settings();

		// Turn these settings into variables we can use.
		foreach ( $this->settings as $setting_key => $value ) {
			$this->$setting_key = $value;
		}

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );

		// Redirection hook.
		add_action( 'woocommerce_api_wc_checkoutcom_session', [ $this, 'applepay_sesion' ] );

		add_action( 'woocommerce_api_wc_checkoutcom_generate_token', [ $this, 'applepay_token' ] );
<<<<<<< HEAD
=======

		add_action( 'woocommerce_api_' . strtolower( 'CKO_Apple_Pay_Woocommerce' ), [ $this, 'handle_wc_api' ] );

		// CSR generation scripts (AJAX handler is registered in main plugin file)
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_csr_scripts' ] );
	}

	/**
	 * Check if existing Apple Pay configuration is detected.
	 *
	 * @return array Array with 'detected' (boolean) and 'details' (array of detected fields).
	 */
	private function detect_existing_configuration() {
		$detected_fields = [];
		
		// Check for migrated configuration fields
		$merchant_id = $this->get_option( 'ckocom_apple_mercahnt_id' );
		$certificate_path = $this->get_option( 'ckocom_apple_certificate' );
		$key_path = $this->get_option( 'ckocom_apple_key' );
		$domain_name = $this->get_option( 'apple_pay_domain_name' );
		$display_name = $this->get_option( 'apple_pay_display_name' );
		
		if ( ! empty( $merchant_id ) ) {
			$detected_fields['merchant_id'] = true;
		}
		if ( ! empty( $certificate_path ) && file_exists( $certificate_path ) ) {
			$detected_fields['certificate'] = true;
		}
		if ( ! empty( $key_path ) && file_exists( $key_path ) ) {
			$detected_fields['key'] = true;
		}
		if ( ! empty( $domain_name ) ) {
			$detected_fields['domain'] = true;
		}
		if ( ! empty( $display_name ) ) {
			$detected_fields['display_name'] = true;
		}
		
		// Configuration is detected if we have merchant ID, certificate, and key
		$is_detected = ! empty( $merchant_id ) && 
		              ! empty( $certificate_path ) && 
		              ! empty( $key_path );
		
		return [
			'detected' => $is_detected,
			'details' => $detected_fields,
		];
>>>>>>> upstream/feature/flow-integration-v5.0.0-beta
	}

	/**
	 * Show module configuration in backend.
	 *
	 * @return string|void
	 */
	public function init_form_fields() {
<<<<<<< HEAD
		$this->form_fields = WC_Checkoutcom_Cards_Settings::apple_settings();
		$this->form_fields = array_merge(
			$this->form_fields,
=======
		$settings = WC_Checkoutcom_Cards_Settings::apple_settings();
		// Add navigation links at the beginning, right after the first title section
		$this->form_fields = array_merge(
>>>>>>> upstream/feature/flow-integration-v5.0.0-beta
			[
				'screen_button' => [
					'id'    => 'screen_button',
					'type'  => 'screen_button',
					'title' => __( 'Other Settings', 'checkout-com-unified-payments-api' ),
				],
<<<<<<< HEAD
			]
=======
				'existing_config_banner' => [
					'id'    => 'existing_config_banner',
					'type'  => 'existing_config_banner',
					'title' => '',
				],
				'two_system_notice' => [
					'id'    => 'two_system_notice',
					'type'  => 'two_system_notice',
					'title' => '',
				],
			],
			$settings
>>>>>>> upstream/feature/flow-integration-v5.0.0-beta
		);
	}

	/**
	 * Generate links for the admin page.
	 *
	 * @param string $key The key.
	 * @param array  $value The value.
	 */
	public function generate_screen_button_html( $key, $value ) {
		WC_Checkoutcom_Admin::generate_links( $key, $value );
	}

	/**
<<<<<<< HEAD
=======
	 * Generate existing configuration banner HTML.
	 *
	 * @param string $key The key.
	 * @param array  $value The value.
	 */
	public function generate_existing_config_banner_html( $key, $value ) {
		$config_status = $this->detect_existing_configuration();
		
		if ( ! $config_status['detected'] ) {
			return; // Don't show banner if no existing config detected
		}
		?>
		<tr valign="top">
			<td colspan="2" class="forminp">
				<div class="cko-existing-config-banner notice notice-success inline" style="margin: 20px 0; padding: 16px 20px; border-left: 4px solid #00a32a; background: #f0f9f4;">
					<p style="margin: 0; font-size: 14px; font-weight: 600; color: #1d2327;">
						✅ <?php esc_html_e( 'Setup Detected!', 'checkout-com-unified-payments-api' ); ?>
					</p>
					<p style="margin: 8px 0 0 0; font-size: 13px; color: #50575e; line-height: 1.6;">
						<?php esc_html_e( 'We found an existing Apple Pay configuration. Your next step is to click "Test Certificate and Key" (Step 6) to verify compatibility with the new plugin version. No other changes are needed unless the test fails.', 'checkout-com-unified-payments-api' ); ?>
					</p>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Generate two-system workflow notice HTML.
	 *
	 * @param string $key The key.
	 * @param array  $value The value.
	 */
	public function generate_two_system_notice_html( $key, $value ) {
		?>
		<tr valign="top">
			<td colspan="2" class="forminp">
				<div class="cko-two-system-notice notice notice-info inline" style="margin: 20px 0; padding: 16px 20px; border-left: 4px solid #2271b1; background: #f0f6fc;">
					<p style="margin: 0; font-size: 14px; font-weight: 600; color: #1d2327;">
						ℹ️ <?php esc_html_e( 'Important: Two-System Setup Process', 'checkout-com-unified-payments-api' ); ?>
					</p>
					<p style="margin: 8px 0 0 0; font-size: 13px; color: #50575e; line-height: 1.6;">
						<?php esc_html_e( 'Apple Pay setup requires moving back and forth between Checkout.com Settings (this page) and your Apple Developer Account. You will generate files here, upload them to Apple Developer, download certificates from Apple, and then upload them back here. This is normal and expected.', 'checkout-com-unified-payments-api' ); ?>
					</p>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Generate CSR button HTML for admin page.
	 *
	 * @param string $key The key.
	 * @param array  $value The value.
	 */
	public function generate_apple_pay_csr_button_html( $key, $value ) {
		$field_key = $this->get_field_key( $key );
		$config_status = $this->detect_existing_configuration();
		$is_collapsible = $config_status['detected'];
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo esc_html( $value['title'] ); ?></label>
			</th>
			<td class="forminp">
				<div class="cko-settings-card" data-card-id="payment-processing-certificate" <?php echo $is_collapsible ? 'data-collapsible="true"' : ''; ?>>
					<div class="cko-card-header">
						<h3 class="cko-card-title">
							<span class="cko-card-title-icon"></span>
							<?php echo esc_html( $value['title'] ); ?>
						</h3>
						<span class="cko-status-badge required">Required</span>
						<?php if ( $is_collapsible ) : ?>
							<span class="cko-config-detected-label" style="font-size: 12px; color: #00a32a; font-weight: 500; margin-left: 12px;">✓ Configuration detected, click to renew</span>
							<button type="button" class="button button-secondary cko-toggle-section" style="margin-left: 12px; font-size: 12px;">
								<span class="toggle-text">Show</span> <span class="toggle-icon">▼</span>
							</button>
						<?php endif; ?>
					</div>
					
					<?php if ( ! empty( $value['description'] ) ) : ?>
						<div class="cko-card-description">
							<?php echo wp_kses_post( $value['description'] ); ?>
						</div>
					<?php endif; ?>

					<?php if ( $is_collapsible ) : ?>
					<div class="cko-collapsible-content" style="display: none;">
					<?php endif; ?>
					
					<!-- Progress Steps -->
					<div class="cko-progress-steps">
						<div class="cko-progress-step">
							<div class="cko-progress-step-number">1a</div>
							<div class="cko-progress-step-label"><?php esc_html_e( 'Generate CSR', 'checkout-com-unified-payments-api' ); ?></div>
						</div>
						<div class="cko-progress-step">
							<div class="cko-progress-step-number">1b</div>
							<div class="cko-progress-step-label"><?php esc_html_e( 'Upload Certificate', 'checkout-com-unified-payments-api' ); ?></div>
						</div>
					</div>

					<div class="cko-certificate-generation">
						<button type="button" id="cko-generate-csr-button" class="button button-primary cko-action-button">
							<?php esc_html_e( 'Generate CSR', 'checkout-com-unified-payments-api' ); ?>
						</button>
						<div id="cko-csr-status" class="cko-status-message" style="display: none;"></div>
					</div>

					<div id="cko-csr-instructions" class="cko-step-instructions" style="display: none;">
						<h4><?php esc_html_e( 'Next Steps:', 'checkout-com-unified-payments-api' ); ?></h4>
						<ol>
							<li><?php esc_html_e( 'If you haven\'t created a Merchant ID yet, go to your', 'checkout-com-unified-payments-api' ); ?> 
								<a href="https://developer.apple.com/account/resources/identifiers/add/merchant" target="_blank"><?php esc_html_e( 'Apple Developer account', 'checkout-com-unified-payments-api' ); ?></a>
								<?php esc_html_e( 'and create a new Merchant ID', 'checkout-com-unified-payments-api' ); ?>
							</li>
							<li><?php esc_html_e( 'Go to your Apple Developer account:', 'checkout-com-unified-payments-api' ); ?> 
								<a href="https://developer.apple.com/account/resources/identifiers/list/merchantId" target="_blank"><?php esc_html_e( 'Merchant IDs', 'checkout-com-unified-payments-api' ); ?></a>
							</li>
							<li><?php esc_html_e( 'Select your Merchant ID', 'checkout-com-unified-payments-api' ); ?></li>
							<li><?php esc_html_e( 'In the "Apple Pay Payment Processing Certificate" section, click "Create Certificate"', 'checkout-com-unified-payments-api' ); ?></li>
							<li><?php esc_html_e( 'Answer "No" to processing in China and click "Continue"', 'checkout-com-unified-payments-api' ); ?></li>
							<li><?php esc_html_e( 'Upload the downloaded CSR file', 'checkout-com-unified-payments-api' ); ?></li>
							<li><?php esc_html_e( 'Download the signed certificate (apple_pay.cer) from Apple', 'checkout-com-unified-payments-api' ); ?></li>
							<li><?php esc_html_e( 'Upload the certificate in Step 1b below to complete the setup', 'checkout-com-unified-payments-api' ); ?></li>
						</ol>
						<div class="cko-info-box warning">
							<p><strong><?php esc_html_e( 'Important:', 'checkout-com-unified-payments-api' ); ?></strong> <?php esc_html_e( 'The CSR is valid for 24 hours. Complete the certificate creation within this timeframe.', 'checkout-com-unified-payments-api' ); ?></p>
						</div>
					</div>
					
					<?php if ( $is_collapsible ) : ?>
					</div>
					<?php endif; ?>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Generate certificate upload HTML for admin page.
	 *
	 * @param string $key The key.
	 * @param array  $value The value.
	 */
	public function generate_apple_pay_certificate_upload_html( $key, $value ) {
		$field_key = $this->get_field_key( $key );
		$config_status = $this->detect_existing_configuration();
		$is_collapsible = $config_status['detected'];
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo esc_html( $value['title'] ); ?></label>
			</th>
			<td class="forminp">
				<?php if ( $is_collapsible ) : ?>
				<div class="cko-collapsible-content" style="display: none;">
				<?php endif; ?>
					
					<?php if ( ! empty( $value['description'] ) ) : ?>
						<div class="cko-info-box">
							<p><?php echo wp_kses_post( $value['description'] ); ?></p>
						</div>
					<?php endif; ?>

					<div class="cko-file-upload-area">
						<input type="file" id="cko-certificate-upload" name="apple_pay_certificate" accept=".cer">
						<button type="button" id="cko-upload-certificate-button" class="button button-primary cko-action-button">
							<?php esc_html_e( 'Upload Certificate', 'checkout-com-unified-payments-api' ); ?>
						</button>
						<div id="cko-certificate-status" class="cko-status-message" style="display: none;"></div>
					</div>

					<div class="cko-info-box">
						<p>
							<?php esc_html_e( 'The certificate file will be automatically converted to base64 format and uploaded to Checkout.com. This process is secure and handled automatically.', 'checkout-com-unified-payments-api' ); ?>
							<a href="https://www.checkout.com/docs/payments/add-payment-methods/apple-pay/api-only#Upload_the_signed_payment_processing_certificate" target="_blank"><?php esc_html_e( 'Learn more about certificate upload', 'checkout-com-unified-payments-api' ); ?></a>
						</p>
					</div>
				
				<?php if ( $is_collapsible ) : ?>
				</div>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Generate merchant certificate button HTML for admin page.
	 *
	 * @param string $key The key.
	 * @param array  $value The value.
	 */
	public function generate_apple_pay_merchant_certificate_button_html( $key, $value ) {
		$field_key = $this->get_field_key( $key );
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo esc_html( $value['title'] ); ?></label>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo esc_html( $value['title'] ); ?></span></legend>
					
					<?php if ( ! empty( $value['description'] ) ) : ?>
						<div class="cko-info-box">
							<p><?php echo wp_kses_post( $value['description'] ); ?></p>
						</div>
					<?php endif; ?>

					<div class="cko-certificate-generation">
						<button type="button" id="cko-generate-merchant-certificate-button" class="button button-primary cko-action-button">
							<?php esc_html_e( 'Generate Certificate and Key', 'checkout-com-unified-payments-api' ); ?>
						</button>
						<div id="cko-merchant-certificate-status" class="cko-status-message" style="display: none;"></div>
					</div>

					<div class="cko-info-box">
						<h4><?php esc_html_e( 'What happens after generation?', 'checkout-com-unified-payments-api' ); ?></h4>
						<ol>
							<li><?php esc_html_e( 'Two files will be automatically downloaded: the certificate (.pem) and private key (.key)', 'checkout-com-unified-payments-api' ); ?></li>
							<li><?php esc_html_e( 'Save both files securely on your server in a location accessible to WordPress', 'checkout-com-unified-payments-api' ); ?></li>
							<li><?php esc_html_e( 'Enter the absolute server paths to these files in the "Merchant Certificate Path" and "Merchant Certificate Key Path" fields above', 'checkout-com-unified-payments-api' ); ?></li>
							<li><?php esc_html_e( 'Keep your private key secure and never share it publicly', 'checkout-com-unified-payments-api' ); ?></li>
						</ol>
						<p>
							<a href="https://www.checkout.com/docs/payments/add-payment-methods/apple-pay/api-only#Create_your_Apple_Pay_certificate_and_private_keys" target="_blank"><?php esc_html_e( 'Learn more about merchant certificates', 'checkout-com-unified-payments-api' ); ?></a>
						</p>
					</div>
				</fieldset>
			</td>
		</tr>
		<?php
	}

	/**
	 * Generate domain association upload HTML for admin page.
	 *
	 * @param string $key The key.
	 * @param array  $value The value.
	 */
	public function generate_apple_pay_domain_association_upload_html( $key, $value ) {
		$field_key = $this->get_field_key( $key );
		
		// Get the correct path based on server configuration
		$well_known_info = $this->get_well_known_path();
		$well_known_dir = $well_known_info['dir'];
		$is_bitnami = $well_known_info['is_bitnami'];
		
		// Apple now requires .txt extension
		$well_known_url = home_url( '/.well-known/apple-developer-merchantid-domain-association.txt' );
		$file_path = $well_known_dir . '/apple-developer-merchantid-domain-association.txt';
		
		// Check if config is detected for collapsible sections
		$config_status = $this->detect_existing_configuration();
		$is_collapsible = $config_status['detected'];
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo esc_html( $value['title'] ); ?></label>
			</th>
			<td class="forminp">
				<div class="cko-settings-card" data-card-id="domain-association" <?php echo $is_collapsible ? 'data-collapsible="true"' : ''; ?>>
					<div class="cko-card-header">
						<h3 class="cko-card-title">
							<span class="cko-card-title-icon"></span>
							<?php echo esc_html( $value['title'] ); ?>
						</h3>
						<span class="cko-status-badge required">Required</span>
						<?php if ( $is_collapsible ) : ?>
							<span class="cko-config-detected-label" style="font-size: 12px; color: #00a32a; font-weight: 500; margin-left: 12px;">✓ Configuration detected, click to renew</span>
							<button type="button" class="button button-secondary cko-toggle-section" style="margin-left: 12px; font-size: 12px;">
								<span class="toggle-text">Show</span> <span class="toggle-icon">▼</span>
							</button>
						<?php endif; ?>
					</div>
					
					<?php if ( ! empty( $value['description'] ) ) : ?>
						<div class="cko-card-description">
							<?php echo wp_kses_post( $value['description'] ); ?>
						</div>
					<?php endif; ?>

					<?php if ( $is_collapsible ) : ?>
					<div class="cko-collapsible-content" style="display: none;">
					<?php endif; ?>
					
					<?php if ( $is_bitnami ) : ?>
						<div class="cko-info-box info">
							<p>
								<strong><?php esc_html_e( 'Bitnami Installation Detected', 'checkout-com-unified-payments-api' ); ?></strong><br>
								<?php esc_html_e( 'Your server is configured to use the Bitnami Let\'s Encrypt directory for .well-known files.', 'checkout-com-unified-payments-api' ); ?>
								<?php esc_html_e( 'The file will be saved to:', 'checkout-com-unified-payments-api' ); ?>
								<code><?php echo esc_html( $file_path ); ?></code>
							</p>
						</div>
					<?php endif; ?>

					<div class="cko-step-instructions">
						<h4><?php esc_html_e( 'How to get the domain association file:', 'checkout-com-unified-payments-api' ); ?></h4>
						<ol>
							<li><?php esc_html_e( 'Sign in to your Apple Developer account', 'checkout-com-unified-payments-api' ); ?></li>
							<li><?php esc_html_e( 'Go to the', 'checkout-com-unified-payments-api' ); ?> 
								<a href="https://developer.apple.com/account/resources/identifiers/list/merchant" target="_blank"><?php esc_html_e( 'Merchant IDs list section', 'checkout-com-unified-payments-api' ); ?></a>
								<?php esc_html_e( 'and select your Merchant ID', 'checkout-com-unified-payments-api' ); ?>
							</li>
							<li><?php esc_html_e( 'Under the Merchant Domains section, select Add Domain', 'checkout-com-unified-payments-api' ); ?></li>
							<li><?php esc_html_e( 'Enter your domain and select Save', 'checkout-com-unified-payments-api' ); ?></li>
							<li><?php esc_html_e( 'Select Download to get the .txt file', 'checkout-com-unified-payments-api' ); ?></li>
							<li><?php esc_html_e( 'Upload the file below', 'checkout-com-unified-payments-api' ); ?></li>
						</ol>
					</div>

					<div class="cko-file-upload-area">
						<input type="file" id="cko-domain-association-upload" name="apple_pay_domain_association" accept=".txt">
						<button type="button" id="cko-upload-domain-association-button" class="button button-primary cko-action-button">
							<?php esc_html_e( 'Upload Domain Association File', 'checkout-com-unified-payments-api' ); ?>
						</button>
						<div id="cko-domain-association-status" class="cko-status-message" style="display: none;"></div>
					</div>
					
					<?php if ( $is_collapsible ) : ?>
					</div>
					<?php endif; ?>

					<div class="cko-info-box">
						<p>
							<strong><?php esc_html_e( 'File Location (Server Reference):', 'checkout-com-unified-payments-api' ); ?></strong>
							<?php esc_html_e( 'The file is saved to your web root at:', 'checkout-com-unified-payments-api' ); ?>
							<code>[Your Server Path]/.well-known/apple-developer-merchantid-domain-association.txt</code>
							<br>
							<strong><?php esc_html_e( 'Public Access URL (Verify This):', 'checkout-com-unified-payments-api' ); ?></strong>
							<a href="<?php echo esc_url( $well_known_url ); ?>" target="_blank">https://[YOUR-DOMAIN.COM]/.well-known/apple-developer-merchantid-domain-association.txt</a>
						</p>
						<p>
							<strong><?php esc_html_e( 'Important:', 'checkout-com-unified-payments-api' ); ?></strong>
							<?php esc_html_e( 'Apple now requires the file to have a .txt extension. After uploading the file, go back to Apple Developer and click "Verify" to complete the domain verification process.', 'checkout-com-unified-payments-api' ); ?>
						</p>
						<?php if ( $is_bitnami ) : ?>
							<div class="cko-info-box info" style="margin-top: 12px;">
								<p>
									<strong><?php esc_html_e( 'Bitnami Installation Detected:', 'checkout-com-unified-payments-api' ); ?></strong>
									<?php esc_html_e( 'Your server uses a custom path for .well-known files. If the upload fails due to permissions, you may need to manually place the file via SSH:', 'checkout-com-unified-payments-api' ); ?>
									<br>
									<code>sudo cp /path/to/apple-developer-merchantid-domain-association.txt <?php echo esc_html( $file_path ); ?></code><br>
									<code>sudo chmod 644 <?php echo esc_html( $file_path ); ?></code>
						</p>
					</div>
						<?php endif; ?>
					</div>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Generate merchant identity CSR button HTML for admin page.
	 *
	 * @param string $key The key.
	 * @param array  $value The value.
	 */
	public function generate_apple_pay_merchant_identity_csr_button_html( $key, $value ) {
		$field_key = $this->get_field_key( $key );
		$config_status = $this->detect_existing_configuration();
		$is_collapsible = $config_status['detected'];
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo esc_html( $value['title'] ); ?></label>
			</th>
			<td class="forminp">
				<div class="cko-settings-card" data-card-id="merchant-identity-certificate" <?php echo $is_collapsible ? 'data-collapsible="true"' : ''; ?>>
					<div class="cko-card-header">
						<h3 class="cko-card-title">
							<span class="cko-card-title-icon"></span>
							<?php echo esc_html( $value['title'] ); ?>
						</h3>
						<span class="cko-status-badge required">Required</span>
						<?php if ( $is_collapsible ) : ?>
							<span class="cko-config-detected-label" style="font-size: 12px; color: #00a32a; font-weight: 500; margin-left: 12px;">✓ Configuration detected, click to renew</span>
							<button type="button" class="button button-secondary cko-toggle-section" style="margin-left: 12px; font-size: 12px;">
								<span class="toggle-text">Show</span> <span class="toggle-icon">▼</span>
							</button>
						<?php endif; ?>
					</div>
					
					<?php if ( ! empty( $value['description'] ) ) : ?>
						<div class="cko-card-description">
							<?php echo wp_kses_post( $value['description'] ); ?>
						</div>
					<?php endif; ?>
					
					<?php if ( $is_collapsible ) : ?>
					<div class="cko-collapsible-content" style="display: none;">
					<?php endif; ?>
					
					<!-- Progress Steps -->
					<div class="cko-progress-steps">
						<div class="cko-progress-step">
							<div class="cko-progress-step-number">3a</div>
							<div class="cko-progress-step-label"><?php esc_html_e( 'Generate CSR & Key', 'checkout-com-unified-payments-api' ); ?></div>
						</div>
						<div class="cko-progress-step">
							<div class="cko-progress-step-number">3b</div>
							<div class="cko-progress-step-label"><?php esc_html_e( 'Upload Certificate', 'checkout-com-unified-payments-api' ); ?></div>
						</div>
					</div>

					<div class="cko-certificate-generation">
						<button type="button" id="cko-generate-merchant-identity-csr-button" class="button button-primary cko-action-button">
							<?php esc_html_e( 'Generate CSR and Key', 'checkout-com-unified-payments-api' ); ?>
						</button>
						<div id="cko-merchant-identity-csr-status" class="cko-status-message" style="display: none;"></div>
					</div>

					<div id="cko-merchant-identity-csr-instructions" class="cko-step-instructions" style="display: none;">
						<h4><?php esc_html_e( 'Next Steps:', 'checkout-com-unified-payments-api' ); ?></h4>
						<ol>
							<li><?php esc_html_e( 'Sign in to your Apple Developer account:', 'checkout-com-unified-payments-api' ); ?>
								<a href="https://developer.apple.com/account/resources/identifiers/list/merchantId" target="_blank"><?php esc_html_e( 'Merchant IDs', 'checkout-com-unified-payments-api' ); ?></a>
							</li>
							<li><?php esc_html_e( 'Select your Merchant ID', 'checkout-com-unified-payments-api' ); ?></li>
							<li><?php esc_html_e( 'In the "Apple Pay Merchant Identity Certificate" section, click "Create Certificate"', 'checkout-com-unified-payments-api' ); ?></li>
							<li><?php esc_html_e( 'Upload the downloaded CSR file (uploadMe.csr)', 'checkout-com-unified-payments-api' ); ?></li>
							<li><?php esc_html_e( 'Download the signed certificate (.cer file) from Apple', 'checkout-com-unified-payments-api' ); ?></li>
							<li><?php esc_html_e( 'Upload the certificate in Step 3b below to convert it to PEM format', 'checkout-com-unified-payments-api' ); ?></li>
						</ol>
						<div class="cko-info-box warning">
							<p><strong><?php esc_html_e( 'Important:', 'checkout-com-unified-payments-api' ); ?></strong> 
								<?php esc_html_e( 'Save the private key file (certificate_sandbox.key) securely on your server. You will need it for testing and production use.', 'checkout-com-unified-payments-api' ); ?>
							</p>
						</div>
					</div>
					
					<?php if ( $is_collapsible ) : ?>
					</div>
					<?php endif; ?>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Generate merchant identity certificate upload HTML for admin page.
	 *
	 * @param string $key The key.
	 * @param array  $value The value.
	 */
	public function generate_apple_pay_merchant_identity_certificate_upload_html( $key, $value ) {
		$field_key = $this->get_field_key( $key );
		$config_status = $this->detect_existing_configuration();
		$is_collapsible = $config_status['detected'];
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo esc_html( $value['title'] ); ?></label>
			</th>
			<td class="forminp">
				<?php if ( $is_collapsible ) : ?>
				<div class="cko-collapsible-content" style="display: none;">
				<?php endif; ?>
					
					<?php if ( ! empty( $value['description'] ) ) : ?>
						<div class="cko-info-box">
							<p><?php echo wp_kses_post( $value['description'] ); ?></p>
						</div>
					<?php endif; ?>

					<div class="cko-file-upload-area">
						<input type="file" id="cko-merchant-identity-certificate-upload" name="apple_pay_merchant_identity_certificate" accept=".cer">
						<button type="button" id="cko-upload-merchant-identity-certificate-button" class="button button-primary cko-action-button">
							<?php esc_html_e( 'Upload and Convert Certificate', 'checkout-com-unified-payments-api' ); ?>
						</button>
						<div id="cko-merchant-identity-certificate-status" class="cko-status-message" style="display: none;"></div>
					</div>

					<div class="cko-info-box">
						<p>
							<strong><?php esc_html_e( 'Important:', 'checkout-com-unified-payments-api' ); ?></strong>
						</p>
						<ol>
							<li><?php esc_html_e( 'The certificate will be automatically converted from DER (.cer) format to PEM format and saved to your server as certificate_sandbox.pem', 'checkout-com-unified-payments-api' ); ?></li>
						<li><?php esc_html_e( 'Make sure you have saved the certificate_sandbox.key file from Step 3a on your server', 'checkout-com-unified-payments-api' ); ?></li>
						<li><?php esc_html_e( 'Configure the paths to both files in the "Merchant Identity Certificate Path" and "Merchant Identity Certificate Key Path" fields in the Apple Pay Configuration section above', 'checkout-com-unified-payments-api' ); ?></li>
						</ol>
					</div>
				
				<?php if ( $is_collapsible ) : ?>
				</div>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Generate test certificate button HTML for admin page.
	 *
	 * @param string $key The key.
	 * @param array  $value The value.
	 */
	public function generate_apple_pay_test_certificate_button_html( $key, $value ) {
		$field_key = $this->get_field_key( $key );
		$config_status = $this->detect_existing_configuration();
		$is_existing_config = $config_status['detected'];
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo esc_html( $value['title'] ); ?></label>
			</th>
			<td class="forminp">
				<div class="cko-settings-card cko-test-certificate-section" data-card-id="test-certificate" style="border: 2px solid <?php echo $is_existing_config ? '#00a32a' : '#2271b1'; ?>; background: <?php echo $is_existing_config ? '#f0f9f4' : '#f0f6fc'; ?>; box-shadow: 0 4px 12px rgba(<?php echo $is_existing_config ? '0, 163, 42' : '34, 113, 177'; ?>, 0.15);">
					<div class="cko-card-header">
						<h3 class="cko-card-title">
							<span class="cko-card-title-icon">✅</span>
							<?php echo esc_html( $value['title'] ); ?>
							<?php if ( $is_existing_config ) : ?>
								<strong style="color: #00a32a; margin-left: 12px;">- YOUR NEXT STEP</strong>
							<?php endif; ?>
						</h3>
						<span class="cko-status-badge required">Required</span>
					</div>
					
					<?php if ( ! empty( $value['description'] ) ) : ?>
						<div class="cko-card-description">
							<?php echo wp_kses_post( $value['description'] ); ?>
						</div>
					<?php endif; ?>
					
					<?php if ( $is_existing_config ) : ?>
						<div class="cko-info-box success" style="margin: 16px 0;">
							<p><strong><?php esc_html_e( 'This is the most important step for existing merchants.', 'checkout-com-unified-payments-api' ); ?></strong> <?php esc_html_e( 'Click below to verify your existing configuration works with the new plugin version.', 'checkout-com-unified-payments-api' ); ?></p>
						</div>
					<?php endif; ?>

					<div class="cko-certificate-generation">
						<button type="button" id="cko-test-certificate-button" class="button button-primary cko-action-button" style="background: <?php echo $is_existing_config ? '#00a32a' : '#2271b1'; ?>; box-shadow: 0 4px 12px rgba(<?php echo $is_existing_config ? '0, 163, 42' : '34, 113, 177'; ?>, 0.3); font-size: 16px; padding: 14px 28px;">
							<?php esc_html_e( 'Test Certificate and Key', 'checkout-com-unified-payments-api' ); ?>
						</button>
						<div id="cko-test-certificate-status" class="cko-status-message" style="display: none;"></div>
					</div>

					<div class="cko-info-box">
						<p>
							<strong><?php esc_html_e( 'Requirements:', 'checkout-com-unified-payments-api' ); ?></strong>
						</p>
						<ul>
							<li><?php esc_html_e( 'Merchant Identifier must be configured', 'checkout-com-unified-payments-api' ); ?></li>
							<li><?php esc_html_e( 'Domain Name must be configured', 'checkout-com-unified-payments-api' ); ?></li>
							<li><?php esc_html_e( 'Display Name must be configured', 'checkout-com-unified-payments-api' ); ?></li>
							<li><?php esc_html_e( 'Merchant Identity Certificate (.pem) must be uploaded and saved', 'checkout-com-unified-payments-api' ); ?></li>
							<li><?php esc_html_e( 'Private Key (.key) must be saved on the server', 'checkout-com-unified-payments-api' ); ?></li>
						</ul>
					</div>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
>>>>>>> upstream/feature/flow-integration-v5.0.0-beta
	 * Show frames js on checkout page.
	 */
	public function payment_fields() {
		global $woocommerce;

		$chosen_methods     = wc_get_chosen_shipping_method_ids();
		$chosen_shipping    = $chosen_methods[0] ?? '';
		$shipping_amount    = WC()->cart->get_shipping_total();
		$checkout_fields    = wp_json_encode( $woocommerce->checkout->checkout_fields, JSON_HEX_APOS );
		$session_url        = str_replace( 'https:', 'https:', add_query_arg( 'wc-api', 'wc_checkoutcom_session', home_url( '/' ) ) );
		$generate_token_url = str_replace( 'https:', 'https:', add_query_arg( 'wc-api', 'wc_checkoutcom_generate_token', home_url( '/' ) ) );
		$apple_settings     = get_option( 'woocommerce_wc_checkout_com_apple_pay_settings' );
		$mada_enabled       = isset( $apple_settings['enable_mada'] ) && ( 'yes' === $apple_settings['enable_mada'] );

		if ( ! empty( $this->get_option( 'description' ) ) ) {
			echo esc_html( $this->get_option( 'description' ) );
		}

		// get country of current user.
		$country_code          = WC()->customer->get_billing_country();
		$supported_networks    = [ 'amex', 'masterCard', 'visa' ];
		$merchant_capabilities = [ 'supports3DS', 'supportsEMV', 'supportsCredit', 'supportsDebit' ];

		if ( $mada_enabled ) {
			array_push( $supported_networks, 'mada' );
			$country_code = 'SA';

			$merchant_capabilities = array_values( array_diff( $merchant_capabilities, [ 'supportsEMV' ] ) );
		}

		?>
		<!-- Input needed to sent the card token -->
		<input type="hidden" id="cko-apple-card-token" name="cko-apple-card-token" value="" />

		<!-- ApplePay warnings -->
		<p style="display:none" id="ckocom_applePay_not_actived">ApplePay is possible on this browser, but not currently activated.</p>
		<p style="display:none" id="ckocom_applePay_not_possible">ApplePay is not available on this browser</p>

		<script crossorigin
            src="https://applepay.cdn-apple.com/jsapi/1.latest/apple-pay-sdk.js"
        ></script>

		<script type="text/javascript">
			// Magic strings used in file
			var applePayOptionSelector = 'li.payment_method_wc_checkout_com_apple_pay';
			var applePayButtonId = 'ckocom_applePay';

			// Warning messages for ApplePay
			var applePayNotActivated = document.getElementById('ckocom_applePay_not_actived');
			var applePayNotPossible = document.getElementById('ckocom_applePay_not_possible');

			// Initially hide the Apple Pay as a payment option.
			hideAppleApplePayOption();
			// If Apple Pay is available as a payment option, and enabled on the checkout page, un-hide the payment option.
			if (window.ApplePaySession) {
				var canMakePayments = ApplePaySession.canMakePayments("<?php echo esc_js( $this->get_option( 'ckocom_apple_mercahnt_id' ) ); ?>");

				if ( canMakePayments ) {
					setTimeout( function() {
						showAppleApplePayOption();
					}, 500 );
				} else {
					displayApplePayNotPossible();
				}

			} else {
				displayApplePayNotPossible();
			}

			// Display the button and remove the default place order.
			checkoutInitialiseApplePay = function () {
				jQuery( '#payment' ) . append(
<<<<<<< HEAD
					'<apple-pay-button id="' + applePayButtonId + '" onclick="onApplePayButtonClicked()" type="' 
					+ "<?php echo esc_js( $this->get_option( 'ckocom_apple_type' ) ); ?>" + '" buttonstyle="' 
=======
					'<apple-pay-button id="' + applePayButtonId + '" onclick="onApplePayButtonClicked()" type="plain" buttonstyle="' 
>>>>>>> upstream/feature/flow-integration-v5.0.0-beta
					+ "<?php echo esc_js( $this->get_option( 'ckocom_apple_theme' ) ); ?>" + '" locale="' 
					+ "<?php echo esc_js( $this->get_option( 'ckocom_apple_language' ) ); ?>" + '"></apple-pay-button>'
				);

				jQuery('#ckocom_applePay').hide();
			};

			// Listen for when the Apple Pay button is pressed.
			function onApplePayButtonClicked() {
				var isOrderPayPage = jQuery(document.body).hasClass('woocommerce-order-pay');
				
				if( !isOrderPayPage ) {
					var checkoutFields = '<?php echo $checkout_fields; ?>';
					var result = isValidFormField(checkoutFields);
				}
				
				if(result || isOrderPayPage){
					var applePaySession = new ApplePaySession(3, getApplePayConfig());
					handleApplePayEvents(applePaySession);
					applePaySession.begin();
				} 
			}

			/**
			 * Get the configuration needed to initialise the Apple Pay session.
			 *
			 * @param {function} callback
			 */
			function getApplePayConfig() {

				var networksSupported = <?php echo wp_json_encode( $supported_networks ); ?>;
				var merchantCapabilities = <?php echo wp_json_encode( $merchant_capabilities ); ?>;

				<?php

				// Logic for order-pay page.

				$total_price    = $woocommerce->cart->total;
				$subtotal_price = $woocommerce->cart->subtotal;

				// If on order-pay page, try fetching order total from the last order.
				if ( is_wc_endpoint_url( 'order-pay' ) ) {

					global $wp;

					// Get order ID from URL if available.
					$order_id = absint( $wp->query_vars['order-pay'] );

					if ( ! $order_id && isset( $_GET['key'] ) ) {
						$pay_order = wc_get_order( wc_get_order_id_by_order_key( sanitize_text_field( $_GET['key'] ) ) );
					} else {
						$pay_order = wc_get_order( $order_id );
					}
							
					if ( $pay_order ) {
						$total_price     = $pay_order->get_total();
						$subtotal_price  = $pay_order->get_subtotal();
						$shipping_amount = $pay_order->get_shipping_total();
					}
				}

				?>

				return {
					currencyCode: "<?php echo esc_js( get_woocommerce_currency() ); ?>",
					countryCode: "<?php echo esc_js( $country_code ); ?>",
					merchantCapabilities: merchantCapabilities,
					supportedNetworks: networksSupported,
					total: {
						label: window.location.host,
						amount: "<?php echo esc_js( $total_price ); ?>",
						type: 'final'
					}
				}
			}

			/**
			* Handle Apple Pay events.
			*/
			function handleApplePayEvents(session) {
				/**
				* An event handler that is called when the payment sheet is displayed.
				*
				* @param {object} event - The event contains the validationURL.
				*/
				session.onvalidatemerchant = function (event) {
					performAppleUrlValidation(event.validationURL, function (merchantSession) {
						session.completeMerchantValidation(merchantSession);
					});
				};


				/**
				* An event handler that is called when a new payment method is selected.
				*
				* @param {object} event - The event contains the payment method selected.
				*/
				session.onpaymentmethodselected = function (event) {
					// base on the card selected the total can be change, if for example you.
					// plan to charge a fee for credit cards for example.
					var newTotal = {
						type: 'final',
						label: window.location.host,
						amount: "<?php echo esc_js( $total_price ); ?>",
					};

					var newLineItems = [
						{
							type: 'final',
							label: 'Subtotal',
							amount: "<?php echo esc_js( $subtotal_price ); ?>"
						},
						{
							type: 'final',
							label: 'Shipping - ' + "<?php echo esc_js( $chosen_shipping ); ?>",
							amount: "<?php echo esc_js( $shipping_amount ); ?>"
						}
					];

					session.completePaymentMethodSelection(newTotal, newLineItems);
				};

				/**
				* An event handler that is called when the user has authorized the Apple Pay payment
				*  with Touch ID, Face ID, or passcode.
				*/
				session.onpaymentauthorized = function (event) {
					generateCheckoutToken(event.payment.token.paymentData, function (outcome) {

						if (outcome) {
							document.getElementById('cko-apple-card-token').value = outcome;
							status = ApplePaySession.STATUS_SUCCESS;
							jQuery('#place_order').prop("disabled",false);
							jQuery('#place_order').trigger('click');
						} else {
							status = ApplePaySession.STATUS_FAILURE;
						}

						session.completePayment(status);
					});
				};

				/**
				* An event handler that is automatically called when the payment UI is dismissed.
				*/
				session.oncancel = function (event) {
					// popup dismissed
				};

			}

			/**
			 * Perform the session validation.
			 *
			 * @param {string} valURL validation URL from Apple
			 * @param {function} callback
			 */
			function performAppleUrlValidation(valURL, callback) {
				jQuery.ajax({
					type: 'POST',
					url: "<?php echo esc_url( $session_url ); ?>",
					data: {
						url: valURL,
						merchantId: "<?php echo esc_js( $this->get_option( 'ckocom_apple_mercahnt_id' ) ); ?>",
						domain: window.location.host,
						displayName: window.location.host,
					},
					success: function (outcome) {
						var data = JSON.parse(outcome);
						callback(data);
					}
				});
			}

			/**
			 * Generate the checkout.com token based on the Apple Pay payload.
			 *
			 * @param {function} callback
			 */
			function generateCheckoutToken(token, callback) {
				jQuery.ajax({
					type: 'POST',
					url: "<?php echo esc_url( $generate_token_url ); ?>",
					data: {
						token: token
					},
					success: function (outcome) {
						callback(outcome);
					},
					error: function () {
						callback('');
					}
				});
			}

			/**
			* This will display the Apple Pay not activated message.
			*/
			function displayApplePayNotActivated() {
				applePayNotActivated.style.display = '';
			}

			/**
			* This will display the Apple Pay not possible message.
			*/
			function displayApplePayNotPossible() {
				applePayNotPossible.style.display = '';
			}

			/**
			* Hide the Apple Pay payment option from the checkout page.
			*/
			function hideAppleApplePayOption() {
				jQuery(applePayOptionSelector).hide();
				// jQuery('#ckocom_applePay').hide();
				// jQuery(applePayOptionBodySelector).hide();
			}

			/**
			* Show the Apple Pay payment option on the checkout page.
			*/
			function showAppleApplePayOption() {
				jQuery( applePayOptionSelector ).show();
				// jQuery('.apple-pay-button').show();
				// jQuery(applePayOptionBodySelector).show();

				if ( jQuery( '.payment_method_wc_checkout_com_apple_pay' ).is( ':visible' ) ) {

					//check if Apple Pay method is checked.
					if ( jQuery( '#payment_method_wc_checkout_com_apple_pay' ).is( ':checked' ) ) {
						// Disable place order button.
						// jQuery('#place_order').prop("disabled",true);
						jQuery( '#place_order' ).hide();
						// Show Apple Pay button.
						jQuery( '#ckocom_applePay' ).show();
					} else {
						// Show default place order button.
						jQuery( '#place_order' ).show();
						// Hide apple pay button.
						// jQuery('#place_order').prop("disabled",false);
						jQuery( '#ckocom_applePay' ).hide();
					}

					// On payment radio button click.
					jQuery( "input[name='payment_method']" ).on( 'click', function () {
						// Check if payment method is Google Pay.
						if ( this.value == 'wc_checkout_com_apple_pay' ) {
							// Hide default place order button.
							// jQuery('#place_order').prop("disabled",true);
							jQuery( '#place_order' ).hide();
							// Show Apple Pay button.
							jQuery( '#ckocom_applePay' ).show();

						} else {
							// Enable place order button.
							// jQuery('#place_order').prop("disabled",false);
							jQuery( '#place_order' ).show();
							// Hide apple pay button.
							jQuery( '#ckocom_applePay' ).hide();
						}
					} )
				} else {
					jQuery( '#place_order' ).prop( "disabled", false );
				}
			}

			// Initialise apple pay when page is ready
			jQuery( document ).ready(function() {
				checkoutInitialiseApplePay();
			});

			// Validate checkout form before submitting order
			function isValidFormField(fieldList) {
				var result = {error: false, messages: []};
				var fields = JSON.parse(fieldList);

				if(jQuery('#terms').length === 1 && jQuery('#terms:checked').length === 0){
					result.error = true;
					result.messages.push({target: 'terms', message : 'You must accept our Terms & Conditions.'});
				}

				if (fields) {
					jQuery.each(fields, function(group, groupValue) {
						if (group === 'shipping' && jQuery('#ship-to-different-address-checkbox:checked').length === 0) {
							return true;
						}

						jQuery.each(groupValue, function(name, value ) {
							if (!value.hasOwnProperty('required')) {
								return true;
							}

							if (name === 'account_password' && jQuery('#createaccount:checked').length === 0) {
								return true;
							}

							var inputValue = jQuery('#' + name).length > 0 && jQuery('#' + name).val().length > 0 ? jQuery('#' + name).val() : '';

							if (value.required && jQuery('#' + name).length > 0 && jQuery('#' + name).val().length === 0) {
								result.error = true;
								result.messages.push({target: name, message : value.label + ' is a required field.'});
							}

							if (value.hasOwnProperty('type')) {
								switch (value.type) {
									case 'email':
										var reg     = /^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/;
										var correct = reg.test(inputValue);

										if (!correct) {
											result.error = true;
											result.messages.push({target: name, message : value.label + ' is not correct email.'});
										}

										break;
									case 'tel':
										var tel      = inputValue;
										var filtered = tel.replace(/[\s\#0-9_\-\+\(\)]/g, '').trim();

										if (filtered.length > 0) {
											result.error = true;
											result.messages.push({target: name, message : value.label + ' is not correct phone number.'});
										}

										break;
								}
							}
						});
					});
				} else {
					result.error = true;
					result.messages.push({target: false, message : 'Empty form data.'});
				}

				if (!result.error) {
					return true;
				}

				jQuery('.woocommerce-error, .woocommerce-message').remove();

				jQuery.each(result.messages, function(index, value) {
					jQuery('form.checkout').prepend('<div class="woocommerce-error">' + value.message + '</div>');
				});

				jQuery('html, body').animate({
					scrollTop: (jQuery('form.checkout').offset().top - 100 )
				}, 1000 );

				jQuery(document.body).trigger('checkout_error');

				return false;
			}

		</script>
		<?php
	}

	/**
<<<<<<< HEAD
=======
	 * Handle Apple Pay method API requests.
	 *
	 * @return void
	 */
	public function handle_wc_api() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
		if ( ! empty( $_GET['cko_apple_pay_action'] ) ) {
			switch ( $_GET['cko_apple_pay_action'] ) {

				case 'express_add_to_cart':
					$this->cko_express_add_to_cart();
					break;

				case 'express_get_cart_total':
					$this->cko_express_get_cart_total();
					break;

				case 'express_apple_pay_order_session':
					WC_Checkoutcom_Utility::cko_set_session( 'cko_apple_pay_order_id', isset( $_GET['apple_pay_order_id'] ) ? wc_clean( $_GET['apple_pay_order_id'] ) : '' );
					$this->cko_express_apple_pay_order_session();
					break;
			}
		}
		// phpcs:enable
		exit();
	}

	/**
	 * Add to cart for Apple Pay Express checkout.
	 *
	 * @return void
	 */
	public function cko_express_add_to_cart() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'checkoutcom_apple_pay_express_add_to_cart' ) ) {
			wp_send_json( [ 'result' => 'failed' ] );
		}

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		WC()->shipping->reset_shipping();

		$product_id   = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$qty          = ! isset( $_POST['qty'] ) ? 1 : absint( $_POST['qty'] );
		
		$product      = wc_get_product( $product_id );
		
		if ( ! $product ) {
			wp_send_json( [ 'result' => 'error', 'message' => 'Product not found' ] );
		}
		
		$product_type = $product->get_type();

		// First empty the cart to prevent wrong calculation.
		WC()->cart->empty_cart();

		if ( ( 'variable' === $product_type || 'variable-subscription' === $product_type ) && isset( $_POST['attributes'] ) ) {
			$attributes = wc_clean( wp_unslash( $_POST['attributes'] ) );

			$data_store   = WC_Data_Store::load( 'product' );
			$variation_id = $data_store->find_matching_product_variation( $product, $attributes );

			$is_added_to_cart = WC()->cart->add_to_cart( $product->get_id(), $qty, $variation_id, $attributes );
		}

		if ( in_array( $product_type, [ 'simple', 'variation', 'subscription', 'subscription_variation' ], true ) ) {
			$is_added_to_cart = WC()->cart->add_to_cart( $product->get_id(), $qty );
		}

		WC()->cart->calculate_totals();

		$data           = [];
		$data['result'] = 'success';
		$data['total']  = WC()->cart->total;

		wp_send_json( $data );
	}

	/**
	 * Get cart total for Apple Pay Express checkout.
	 *
	 * @return void
	 */
	public function cko_express_get_cart_total() {
		// Return cart total directly from WooCommerce
		// This works for both classic and Blocks cart pages
		
		// Ensure cart is loaded
		if ( ! WC()->cart ) {
			wp_send_json_error( array( 'messages' => 'Cart not initialized' ) );
			return;
		}
		
		if ( WC()->cart->is_empty() ) {
			wp_send_json_error( array( 'messages' => 'Cart is empty' ) );
			return;
		}

		// Recalculate totals to ensure we have the latest
		WC()->cart->calculate_totals();

		// Get cart total - use 'raw' to get the numeric value directly
		$cart_total_raw = WC()->cart->get_total( 'raw' );
		
		// Also get formatted total for display
		$cart_total_formatted = WC()->cart->get_total( '' );

		// Ensure we have a valid total
		if ( $cart_total_raw <= 0 ) {
			wp_send_json_error( array( 'messages' => 'Invalid cart total' ) );
			return;
		}

		wp_send_json_success( array(
			'total' => floatval( $cart_total_raw ),
			'total_formatted' => $cart_total_formatted,
			'currency' => get_woocommerce_currency(),
		) );
	}

	/**
	 * Process Apple Pay Express order session.
	 *
	 * @return void
	 */
	public function cko_express_apple_pay_order_session() {
		// Express now uses the same flow as classic Apple Pay
		// Classic Apple Pay sends: cko-apple-card-token
		// Express now sends the same field, so we can use the same process_payment method
		
		// Get payment data for email and address extraction
		$payment_data_json = isset( $_POST['payment_data'] ) ? wp_unslash( $_POST['payment_data'] ) : '';
		$payment_data = ! empty( $payment_data_json ) ? json_decode( $payment_data_json, true ) : array();
		
		// Extract email and address from payment data
		// IMPORTANT: For guest users, both email and address are MANDATORY from Apple Pay
		// Chrome may send email in a different structure than Safari, so we check multiple locations
		$email = '';
		$shipping_address = null;
		$billing_address = null;
		
		if ( ! empty( $payment_data ) ) {
			// Extract email - try multiple locations (Chrome may send it differently)
			// 1. Top-level email field
			if ( isset( $payment_data['email'] ) && ! empty( $payment_data['email'] ) ) {
				$email = sanitize_email( $payment_data['email'] );
			}
			// 2. billingContact.emailAddress
			elseif ( isset( $payment_data['billingContact']['emailAddress'] ) && ! empty( $payment_data['billingContact']['emailAddress'] ) ) {
				$email = sanitize_email( $payment_data['billingContact']['emailAddress'] );
			}
			// 3. shippingContact.emailAddress
			elseif ( isset( $payment_data['shippingContact']['emailAddress'] ) && ! empty( $payment_data['shippingContact']['emailAddress'] ) ) {
				$email = sanitize_email( $payment_data['shippingContact']['emailAddress'] );
			}
			// 4. Check if billingContact or shippingContact have email (without emailAddress key)
			elseif ( isset( $payment_data['billingContact']['email'] ) && ! empty( $payment_data['billingContact']['email'] ) ) {
				$email = sanitize_email( $payment_data['billingContact']['email'] );
			}
			elseif ( isset( $payment_data['shippingContact']['email'] ) && ! empty( $payment_data['shippingContact']['email'] ) ) {
				$email = sanitize_email( $payment_data['shippingContact']['email'] );
			}
			
			// Extract shipping address (MANDATORY for express checkout)
			if ( isset( $payment_data['shippingContact'] ) && ! empty( $payment_data['shippingContact'] ) ) {
				$shipping_address = $payment_data['shippingContact'];
			} elseif ( isset( $payment_data['billingContact'] ) && ! empty( $payment_data['billingContact'] ) ) {
				// Fallback to billing contact if shipping contact not available
				$shipping_address = $payment_data['billingContact'];
			}
			
			// Extract billing address - use billing contact if different, otherwise use shipping contact
			if ( isset( $payment_data['billingContact'] ) && ! empty( $payment_data['billingContact'] ) ) {
				$billing_address = $payment_data['billingContact'];
			} elseif ( isset( $payment_data['shippingContact'] ) && ! empty( $payment_data['shippingContact'] ) ) {
				$billing_address = $payment_data['shippingContact'];
			}
		}
		
		// Store payment data in session for checkout page field population
		// This ensures that if user is redirected to checkout, fields will be populated
		if ( ! empty( $payment_data ) ) {
			WC_Checkoutcom_Utility::cko_set_session( 'cko_ap_details', $payment_data );
			WC_Checkoutcom_Utility::cko_set_session( 'cko_ap_id', 'express_' . time() );
		}
		
		// Create the order from cart
		$order = $this->create_express_order_from_cart( $email, $shipping_address, $billing_address );
		
		if ( ! $order ) {
			wp_send_json_error( array( 'messages' => 'Failed to create order for Apple Pay Express checkout.' ) );
			return;
		}
		
		// Store order ID in session for checkout page reference
		WC_Checkoutcom_Utility::cko_set_session( 'cko_apple_pay_order_id', $order->get_id() );
		
		// process_payment expects the payment method to be set in $_POST
		$_POST['payment_method'] = 'wc_checkout_com_apple_pay';
		
		// Use the same process_payment method as classic Apple Pay
		$result = $this->process_payment( $order->get_id() );
		
		// process_payment returns an array with 'result' and 'redirect'
		if ( isset( $result['result'] ) && 'success' === $result['result'] ) {
			// Payment successful - return success
			wp_send_json_success(
				array(
					'result'      => 'success',
					'redirect_url' => isset( $result['redirect'] ) ? $result['redirect'] : $this->get_return_url( $order ),
				)
			);
		} else {
			// Payment failed
			$error_message = isset( $result['messages'] ) ? $result['messages'] : 'Payment failed.';
			wp_send_json_error( array( 'messages' => $error_message ) );
		}
	}

	/**
	 * Create express order from cart.
	 *
	 * @param string $email Email address from Apple Pay.
	 * @param array  $shipping_address Shipping address from Apple Pay.
	 * @param array  $billing_address Billing address from Apple Pay (optional, defaults to shipping address).
	 * @return WC_Order|false
	 */
	private function create_express_order_from_cart( $email = '', $shipping_address = null, $billing_address = null ) {
		try {
			// Determine customer ID and email
			// IMPORTANT: For Apple Pay Express:
			// - If user is logged in: Use logged-in user's email (NOT Apple Pay email), address from Apple Pay
			// - If user is guest: Use email and address from Apple Pay only (mandatory)
			$customer_id = 0;
			$customer_email = '';
			
			// Check if user is logged in
			if ( is_user_logged_in() ) {
				$current_user = wp_get_current_user();
				$customer_id = $current_user->ID;
				// For logged-in users, always use their account email (NOT Apple Pay email)
				$customer_email = $current_user->user_email;
			} else {
				// For guest users, use email from Apple Pay data only (mandatory)
				if ( ! empty( $email ) ) {
					$customer_email = $email;
				}
			}

			// Create order with proper customer ID
			$order = wc_create_order( array( 'customer_id' => $customer_id ) );
			
			if ( ! $order ) {
				return false;
			}

			// Set customer email
			if ( ! empty( $customer_email ) ) {
				$order->set_billing_email( $customer_email );
			}

			// Add cart items to order
			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
				$product = $cart_item['data'];
				$order->add_product( $product, $cart_item['quantity'] );
			}

			// Set order addresses from Apple Pay payment data (MANDATORY - addresses must always be copied from Apple Pay)
			// IMPORTANT: For Apple Pay Express:
			// - Addresses are MANDATORY from Apple Pay for both logged-in and guest users
			// - If logged in: Use logged-in user's email (already set above), but ALWAYS copy address from Apple Pay
			// - If guest: Use Apple Pay email and address (both mandatory from Apple Pay)
			if ( ! empty( $shipping_address ) ) {
				// For logged-in users: Don't pass email to set_order_addresses_from_apple_pay_data
				// because we want to keep the logged-in user's email (already set above)
				// Addresses are ALWAYS copied from Apple Pay regardless of login status (MANDATORY)
				// For guest users: Pass Apple Pay email so it gets set in the address
				$email_for_address = is_user_logged_in() ? '' : $customer_email;
				// Use billing address if different, otherwise use shipping address for both
				$billing_address_to_use = ! empty( $billing_address ) ? $billing_address : $shipping_address;
				
				$this->set_order_addresses_from_apple_pay_data( $order, $shipping_address, $email_for_address, $billing_address_to_use );
			}

			// Set shipping method if available
			$shipping_packages = WC()->shipping->get_packages();
			if ( ! empty( $shipping_packages ) ) {
				foreach ( $shipping_packages as $package ) {
					$chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
					if ( ! empty( $chosen_methods ) ) {
						$order->set_shipping_method( $chosen_methods[0] );
					}
				}
			}

			// Calculate totals
			$order->calculate_totals();

			// Set payment method to Apple Pay Express
			$order->set_payment_method( 'wc_checkout_com_apple_pay' );
			$order->set_payment_method_title( 'Apple Pay Express' );

			// Set order status
			$order->set_status( 'pending' );
			$order->save();

			// Store order ID in session for potential use
			WC()->session->set( 'order_awaiting_payment', $order->get_id() );

			return $order;

		} catch ( Exception $e ) {
			WC_Checkoutcom_Utility::logger( 'Error creating Apple Pay Express order: ' . $e->getMessage(), $e );
			return false;
		}
	}

	/**
	 * Set order addresses from Apple Pay payment data.
	 * MANDATORY: Addresses must always be copied from Apple Pay when available.
	 *
	 * @param WC_Order $order The order object.
	 * @param array    $shipping_contact Shipping contact from Apple Pay (MANDATORY).
	 * @param string   $email Email address (optional, for guest users).
	 * @param array    $billing_contact Billing contact from Apple Pay (optional, defaults to shipping contact).
	 * @return void
	 */
	private function set_order_addresses_from_apple_pay_data( $order, $shipping_contact, $email = '', $billing_contact = null ) {
		// Addresses are MANDATORY from Apple Pay - if not provided, return
		if ( empty( $shipping_contact ) ) {
			return;
		}

		// Use billing contact if provided, otherwise use shipping contact for billing address
		$billing_contact_to_use = ! empty( $billing_contact ) ? $billing_contact : $shipping_contact;

		// Extract address fields from Apple Pay shipping contact
		$shipping_first_name = isset( $shipping_contact['givenName'] ) ? sanitize_text_field( $shipping_contact['givenName'] ) : '';
		$shipping_last_name = isset( $shipping_contact['familyName'] ) ? sanitize_text_field( $shipping_contact['familyName'] ) : '';
		$shipping_address_lines = isset( $shipping_contact['addressLines'] ) ? $shipping_contact['addressLines'] : array();
		$shipping_address_1 = isset( $shipping_address_lines[0] ) ? sanitize_text_field( $shipping_address_lines[0] ) : '';
		$shipping_address_2 = isset( $shipping_address_lines[1] ) ? sanitize_text_field( $shipping_address_lines[1] ) : '';
		$shipping_city = isset( $shipping_contact['locality'] ) ? sanitize_text_field( $shipping_contact['locality'] ) : '';
		$shipping_state = isset( $shipping_contact['administrativeArea'] ) ? sanitize_text_field( $shipping_contact['administrativeArea'] ) : '';
		$shipping_postcode = isset( $shipping_contact['postalCode'] ) ? sanitize_text_field( $shipping_contact['postalCode'] ) : '';
		$shipping_country = isset( $shipping_contact['countryCode'] ) ? strtoupper( sanitize_text_field( $shipping_contact['countryCode'] ) ) : '';
		$shipping_phone = isset( $shipping_contact['phoneNumber'] ) ? sanitize_text_field( $shipping_contact['phoneNumber'] ) : '';

		// Extract address fields from Apple Pay billing contact
		$billing_first_name = isset( $billing_contact_to_use['givenName'] ) ? sanitize_text_field( $billing_contact_to_use['givenName'] ) : '';
		$billing_last_name = isset( $billing_contact_to_use['familyName'] ) ? sanitize_text_field( $billing_contact_to_use['familyName'] ) : '';
		$billing_address_lines = isset( $billing_contact_to_use['addressLines'] ) ? $billing_contact_to_use['addressLines'] : array();
		$billing_address_1 = isset( $billing_address_lines[0] ) ? sanitize_text_field( $billing_address_lines[0] ) : '';
		$billing_address_2 = isset( $billing_address_lines[1] ) ? sanitize_text_field( $billing_address_lines[1] ) : '';
		$billing_city = isset( $billing_contact_to_use['locality'] ) ? sanitize_text_field( $billing_contact_to_use['locality'] ) : '';
		$billing_state = isset( $billing_contact_to_use['administrativeArea'] ) ? sanitize_text_field( $billing_contact_to_use['administrativeArea'] ) : '';
		$billing_postcode = isset( $billing_contact_to_use['postalCode'] ) ? sanitize_text_field( $billing_contact_to_use['postalCode'] ) : '';
		$billing_country = isset( $billing_contact_to_use['countryCode'] ) ? strtoupper( sanitize_text_field( $billing_contact_to_use['countryCode'] ) ) : '';
		$billing_phone = isset( $billing_contact_to_use['phoneNumber'] ) ? sanitize_text_field( $billing_contact_to_use['phoneNumber'] ) : '';

		// Set billing address (MANDATORY from Apple Pay)
		// Always set all address fields from Apple Pay, even if some are empty
		$order->set_billing_first_name( $billing_first_name );
		$order->set_billing_last_name( $billing_last_name );
		$order->set_billing_address_1( $billing_address_1 );
		$order->set_billing_address_2( $billing_address_2 );
		$order->set_billing_city( $billing_city );
		$order->set_billing_state( $billing_state );
		$order->set_billing_postcode( $billing_postcode );
		$order->set_billing_country( $billing_country );
		$order->set_billing_phone( $billing_phone );
		
		// Email handling:
		// IMPORTANT: Only use Apple Pay email for guest users
		// - For logged-in users: Keep the account email (already set in create_express_order_from_cart)
		// - For guest users: Set email from Apple Pay data (mandatory)
		$current_billing_email = $order->get_billing_email();
		$is_guest = ! is_user_logged_in();
		
		if ( $is_guest && ! empty( $email ) ) {
			// For guest users, always use Apple Pay email (mandatory)
			$order->set_billing_email( sanitize_email( $email ) );
		}

		// Set shipping address (MANDATORY from Apple Pay)
		// Always set all address fields from Apple Pay, even if some are empty
		$order->set_shipping_first_name( $shipping_first_name );
		$order->set_shipping_last_name( $shipping_last_name );
		$order->set_shipping_address_1( $shipping_address_1 );
		$order->set_shipping_address_2( $shipping_address_2 );
		$order->set_shipping_city( $shipping_city );
		$order->set_shipping_state( $shipping_state );
		$order->set_shipping_postcode( $shipping_postcode );
		$order->set_shipping_country( $shipping_country );
		$order->set_shipping_phone( $shipping_phone );

		// Save order to persist address changes
		$order->save();
	}

	/**
>>>>>>> upstream/feature/flow-integration-v5.0.0-beta
	 * Apple pay session.
	 *
	 * @return void
	 */
	public function applepay_sesion() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$url          = isset( $_POST['url'] ) ? sanitize_text_field( $_POST['url'] ) : '';
		$domain       = isset( $_POST['domain'] ) ? sanitize_text_field( $_POST['domain'] ) : '';
		$display_name = isset( $_POST['displayName'] ) ? sanitize_text_field( $_POST['displayName'] ) : '';
		// phpcs:enable

		$merchant_id     = $this->get_option( 'ckocom_apple_mercahnt_id' );
		$certificate     = $this->get_option( 'ckocom_apple_certificate' );
		$certificate_key = $this->get_option( 'ckocom_apple_key' );

		if (
			'https' === wp_parse_url( $url, PHP_URL_SCHEME ) &&
			substr( wp_parse_url( $url, PHP_URL_HOST ), - 10 ) === '.apple.com'
		) {
			$ch = curl_init();

			$data =
				'{
				  "merchantIdentifier":"' . $merchant_id . '",
				  "domainName":"' . $domain . '",
				  "displayName":"' . $display_name . '"
			  }';

			curl_setopt( $ch, CURLOPT_URL, $url );
			curl_setopt( $ch, CURLOPT_SSLCERT, $certificate );
			curl_setopt( $ch, CURLOPT_SSLKEY, $certificate_key );

			curl_setopt( $ch, CURLOPT_POST, 1 );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );

			// TODO: throw error and log it.
			if ( curl_exec( $ch ) === false ) {
				echo '{"curlError":"' . curl_error( $ch ) . '"}';
			}

			// close cURL resource, and free up system resources.
			curl_close( $ch );

			exit();
		}
	}

	/**
	 * Generate Apple Pay token.
	 *
	 * @return void
	 */
	public function applepay_token() {
		// Generate apple token.
		$token = WC_Checkoutcom_Api_Request::generate_apple_token();

		echo $token;

		exit();
	}

	/**
	 * Process payment with Apple Pay.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return array|void
	 */
	public function process_payment( $order_id ) {
		if ( ! session_id() ) {
			session_start();
		}

		$order = new WC_Order( $order_id );

		// create apple token from apple payment data.
		$apple_token = $_POST['cko-apple-card-token'] ?? ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		// Check if apple token is not empty.
		if ( empty( $apple_token ) ) {
			WC_Checkoutcom_Utility::wc_add_notice_self( __( 'There was an issue completing the payment.', 'checkout-com-unified-payments-api' ), 'error' );

			return;
		}

		// Create payment with apple token.
		$result = (array) ( new WC_Checkoutcom_Api_Request() )->create_payment( $order, $apple_token );

		// check if result has error and return error message.
		if ( ! empty( $result['error'] ) ) {
			WC_Checkoutcom_Utility::wc_add_notice_self( $result['error'] );

			return;
		}

		if ( class_exists( 'WC_Subscriptions_Order' ) ) {
			// Save source id for subscription.
			WC_Checkoutcom_Subscription::save_source_id( $order_id, $order, $result['source']['id'] );
		}

		// Set action id as woo transaction id.
		$order->set_transaction_id( $result['action_id'] );
		$order->update_meta_data( '_cko_payment_id', $result['id'] );

		// Get cko auth status configured in admin.
		$status = WC_Admin_Settings::get_option( 'ckocom_order_authorised', 'on-hold' );

		/* translators: %s: Action ID. */
		$message = sprintf( esc_html__( 'Checkout.com Payment Authorised - Action ID : %s', 'checkout-com-unified-payments-api' ), $result['action_id'] );

		// check if payment was flagged.
		if ( $result['risk']['flagged'] ) {
			// Get cko auth status configured in admin.
			$status = WC_Admin_Settings::get_option( 'ckocom_order_flagged', 'flagged' );

			/* translators: %s: Action ID. */
			$message = sprintf( esc_html__( 'Checkout.com Payment Flagged - Action ID : %s', 'checkout-com-unified-payments-api' ), $result['action_id'] );
		}

		// add notes for the order and update status.
		$order->add_order_note( $message );
		$order->update_status( $status );

		// Reduce stock levels.
		wc_reduce_stock_levels( $order_id );

		// Remove cart.
		WC()->cart->empty_cart();

		// Return thank you page.
		return [
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		];
	}

	/**
	 * Process refund for the order.
	 *
	 * @param int    $order_id Order ID.
	 * @param int    $amount   Amount to refund.
	 * @param string $reason   Refund reason.
	 *
	 * @return bool
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {

		$order  = wc_get_order( $order_id );
		$result = (array) WC_Checkoutcom_Api_Request::refund_payment( $order_id, $order );

		// check if result has error and return error message.
		if ( ! empty( $result['error'] ) ) {
			WC_Checkoutcom_Utility::wc_add_notice_self( $result['error'] );

			return false;
		}

		// Set action id as woo transaction id.
		$order->set_transaction_id( $result['action_id'] );
		$order->update_meta_data( 'cko_payment_refunded', true );
		$order->save();

		if ( isset( $_SESSION['cko-refund-is-less'] ) ) {
			if ( $_SESSION['cko-refund-is-less'] ) {
				/* translators: %s: Action ID. */
				$order->add_order_note( sprintf( __( 'Checkout.com Payment Partially refunded from Admin - Action ID : %s', 'checkout-com-unified-payments-api' ), $result['action_id'] ) );

				unset( $_SESSION['cko-refund-is-less'] );

				return true;
			}
		}

		/* translators: %s: Action ID. */
		$order->add_order_note( sprintf( __( 'Checkout.com Payment refunded from Admin - Action ID : %s', 'checkout-com-unified-payments-api' ), $result['action_id'] ) );

		// when true is returned, status is changed to refunded automatically.
		return true;
	}
<<<<<<< HEAD
=======

	/**
	 * Enqueue scripts for CSR generation.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_csr_scripts( $hook ) {
		// Only load on WooCommerce settings pages
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}

		// Only load on Apple Pay settings page
		if ( ! isset( $_GET['section'] ) || 'wc_checkout_com_apple_pay' !== $_GET['section'] ) {
			return;
		}

		wp_enqueue_script(
			'cko-apple-pay-csr',
			WC_CHECKOUTCOM_PLUGIN_URL . '/assets/js/admin-apple-pay-csr.js',
			[ 'jquery' ],
			WC_CHECKOUTCOM_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'cko-apple-pay-csr',
			'ckoCsrData',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'cko_generate_csr' ),
				'i18n'    => [
					'generating' => __( 'Generating CSR...', 'checkout-com-unified-payments-api' ),
					'success'    => __( 'CSR generated successfully! Download starting...', 'checkout-com-unified-payments-api' ),
					'error'      => __( 'Error generating CSR:', 'checkout-com-unified-payments-api' ),
				],
			]
		);

		// Enqueue certificate upload script
		wp_enqueue_script(
			'cko-apple-pay-certificate-upload',
			WC_CHECKOUTCOM_PLUGIN_URL . '/assets/js/admin-apple-pay-certificate-upload.js',
			[ 'jquery' ],
			WC_CHECKOUTCOM_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'cko-apple-pay-certificate-upload',
			'ckoCertificateData',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'cko_upload_certificate' ),
				'i18n'    => [
					'uploading' => __( 'Uploading certificate...', 'checkout-com-unified-payments-api' ),
					'success'   => __( 'Certificate uploaded successfully!', 'checkout-com-unified-payments-api' ),
					'error'     => __( 'Error uploading certificate:', 'checkout-com-unified-payments-api' ),
					'noFile'    => __( 'Please select a certificate file (.cer) to upload.', 'checkout-com-unified-payments-api' ),
				],
			]
		);

		// Enqueue merchant certificate generation script
		wp_enqueue_script(
			'cko-apple-pay-merchant-certificate',
			WC_CHECKOUTCOM_PLUGIN_URL . '/assets/js/admin-apple-pay-merchant-certificate.js',
			[ 'jquery' ],
			WC_CHECKOUTCOM_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'cko-apple-pay-merchant-certificate',
			'ckoMerchantCertificateData',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'cko_generate_merchant_certificate' ),
				'i18n'    => [
					'generating' => __( 'Generating certificate and key...', 'checkout-com-unified-payments-api' ),
					'success'    => __( 'Certificate and key generated successfully!', 'checkout-com-unified-payments-api' ),
					'error'      => __( 'Error generating certificate and key:', 'checkout-com-unified-payments-api' ),
				],
			]
		);

		// Enqueue domain association upload script
		wp_enqueue_script(
			'cko-apple-pay-domain-association',
			WC_CHECKOUTCOM_PLUGIN_URL . '/assets/js/admin-apple-pay-domain-association.js',
			[ 'jquery' ],
			WC_CHECKOUTCOM_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'cko-apple-pay-domain-association',
			'ckoDomainAssociationData',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'cko_upload_domain_association' ),
				'i18n'    => [
					'uploading' => __( 'Uploading domain association file...', 'checkout-com-unified-payments-api' ),
					'success'   => __( 'Domain association file uploaded successfully!', 'checkout-com-unified-payments-api' ),
					'error'     => __( 'Error uploading domain association file:', 'checkout-com-unified-payments-api' ),
					'noFile'    => __( 'Please select a domain association file (.txt) to upload.', 'checkout-com-unified-payments-api' ),
				],
			]
		);

		// Enqueue merchant identity CSR script
		wp_enqueue_script(
			'cko-apple-pay-merchant-identity-csr',
			WC_CHECKOUTCOM_PLUGIN_URL . '/assets/js/admin-apple-pay-merchant-identity-csr.js',
			[ 'jquery' ],
			WC_CHECKOUTCOM_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'cko-apple-pay-merchant-identity-csr',
			'ckoMerchantIdentityCsrData',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'cko_generate_merchant_identity_csr' ),
				'i18n'    => [
					'generating' => __( 'Generating CSR and key...', 'checkout-com-unified-payments-api' ),
					'success'    => __( 'CSR and key generated successfully!', 'checkout-com-unified-payments-api' ),
					'error'      => __( 'Error generating CSR and key:', 'checkout-com-unified-payments-api' ),
				],
			]
		);

		// Enqueue merchant identity certificate upload script
		wp_enqueue_script(
			'cko-apple-pay-merchant-identity-certificate',
			WC_CHECKOUTCOM_PLUGIN_URL . '/assets/js/admin-apple-pay-merchant-identity-certificate.js',
			[ 'jquery' ],
			WC_CHECKOUTCOM_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'cko-apple-pay-merchant-identity-certificate',
			'ckoMerchantIdentityCertificateData',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'cko_upload_merchant_identity_certificate' ),
				'i18n'    => [
					'uploading' => __( 'Uploading and converting certificate...', 'checkout-com-unified-payments-api' ),
					'success'   => __( 'Certificate uploaded and converted successfully!', 'checkout-com-unified-payments-api' ),
					'error'     => __( 'Error uploading certificate:', 'checkout-com-unified-payments-api' ),
					'noFile'    => __( 'Please select a certificate file (.cer) to upload.', 'checkout-com-unified-payments-api' ),
				],
			]
		);

		// Enqueue test certificate script
		wp_enqueue_script(
			'cko-apple-pay-test-certificate',
			WC_CHECKOUTCOM_PLUGIN_URL . '/assets/js/admin-apple-pay-test-certificate.js',
			[ 'jquery' ],
			WC_CHECKOUTCOM_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'cko-apple-pay-test-certificate',
			'ckoTestCertificateData',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'cko_test_certificate' ),
				'i18n'    => [
					'testing' => __( 'Testing certificate and key...', 'checkout-com-unified-payments-api' ),
					'success' => __( 'Certificate and key test successful!', 'checkout-com-unified-payments-api' ),
					'successDetails' => __( 'All paths and configurations are validated with Apple\'s services.', 'checkout-com-unified-payments-api' ),
					'error'   => __( 'Certificate and key test failed:', 'checkout-com-unified-payments-api' ),
					'errorDefault' => __( 'Test Failed. Please verify: 1. Your certificate paths are correct. 2. Your certificate has not expired. 3. You clicked "Verify" in your Apple Developer account for your domain.', 'checkout-com-unified-payments-api' ),
				],
			]
		);

		// Enqueue custom CSS for Apple Pay settings page
		wp_enqueue_style(
			'cko-apple-pay-settings',
			WC_CHECKOUTCOM_PLUGIN_URL . '/assets/css/admin-apple-pay-settings.css',
			[],
			WC_CHECKOUTCOM_PLUGIN_VERSION
		);

		// Enqueue UX enhancements JavaScript
		wp_enqueue_script(
			'cko-apple-pay-settings-ux',
			WC_CHECKOUTCOM_PLUGIN_URL . '/assets/js/admin-apple-pay-settings-ux.js',
			[ 'jquery' ],
			WC_CHECKOUTCOM_PLUGIN_VERSION,
			true
		);
	}

	/**
	 * AJAX handler for generating CSR.
	 */
	public function ajax_generate_csr() {
		// Log that the handler was called (for debugging)
		if ( function_exists( 'WC_Checkoutcom_Utility' ) && method_exists( 'WC_Checkoutcom_Utility', 'logger' ) ) {
			WC_Checkoutcom_Utility::logger( 
				'Apple Pay CSR AJAX Handler Called. POST data: ' . print_r( $_POST, true ),
				null
			);
		}
		
		// Verify nonce first
		if ( ! isset( $_POST['nonce'] ) ) {
			wp_send_json_error( [ 
				'message' => __( 'Security token is missing. Please refresh the page and try again.', 'checkout-com-unified-payments-api' ),
				'error_code' => 'nonce_missing'
			] );
			return;
		}
		
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'cko_generate_csr' ) ) {
			wp_send_json_error( [ 
				'message' => __( 'Security check failed. Please refresh the page and try again.', 'checkout-com-unified-payments-api' ),
				'error_code' => 'nonce_verification_failed'
			] );
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 
				'message' => __( 'Insufficient permissions.', 'checkout-com-unified-payments-api' ),
				'error_code' => 'insufficient_permissions'
			] );
		}

		// Get settings
		$core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings', array() );
		
		if ( empty( $core_settings['ckocom_sk'] ) ) {
			wp_send_json_error( [ 'message' => __( 'Secret key is not configured. Please configure Checkout.com settings first.', 'checkout-com-unified-payments-api' ) ] );
		}

		$secret_key_raw = $core_settings['ckocom_sk'];
		$environment = isset( $core_settings['ckocom_environment'] ) ? $core_settings['ckocom_environment'] : 'sandbox';
		$account_type = isset( $core_settings['ckocom_account_type'] ) ? $core_settings['ckocom_account_type'] : 'ABC';
		$region = isset( $core_settings['ckocom_region'] ) ? $core_settings['ckocom_region'] : 'global';
		$protocol_version = isset( $_POST['protocol_version'] ) ? sanitize_text_field( $_POST['protocol_version'] ) : 'ec_v1';

		// Check if NAS account
		if ( function_exists( 'cko_is_nas_account' ) && cko_is_nas_account() ) {
			$account_type = 'NAS';
		}

		// Build API URL
		$base_url = 'https://api.checkout.com';
		if ( 'sandbox' === $environment ) {
			$base_url = 'https://api.sandbox.checkout.com';
		}

		// Add region subdomain if not global
		if ( 'global' !== $region && ! empty( $region ) ) {
			$base_url = str_replace( 'api.', $region . '.api.', $base_url );
		}

		$api_url = $base_url . '/applepay/signing-requests';

		// Prepare authorization header
		// Remove any existing "Bearer " prefix from the secret key
		$secret_key_clean = str_replace( 'Bearer ', '', trim( $secret_key_raw ) );
		
		// Apple Pay signing-requests endpoint authentication
		// For NAS accounts, always use Bearer prefix
		// For ABC accounts, try direct key first (as per other API calls in the codebase)
		if ( 'NAS' === $account_type ) {
			$auth_header = 'Bearer ' . $secret_key_clean;
		} else {
			// ABC accounts - try direct key first (no Bearer prefix)
			// This matches how other ABC API calls work in the codebase
			$auth_header = $secret_key_clean;
		}

		// Prepare request body
		$request_body = [
			'protocol_version' => $protocol_version,
		];

		// Always log request details for debugging (help diagnose Bad Request errors)
		WC_Checkoutcom_Utility::logger( 
			'Apple Pay CSR Generation Request: ' . 
			'URL: ' . $api_url . ', ' .
			'Account Type: ' . $account_type . ', ' .
			'Environment: ' . $environment . ', ' .
			'Protocol Version: ' . $protocol_version . ', ' .
			'Auth Header Format: ' . ( strpos( $auth_header, 'Bearer' ) !== false ? 'Bearer' : 'Direct' ),
			null
		);

		// Make API request
		$response = wp_remote_post(
			$api_url,
			[
				'headers' => [
					'Authorization' => $auth_header,
					'Content-Type'  => 'application/json',
				],
				'body'    => json_encode( $request_body ),
				'timeout' => 30,
			]
		);

		// Check for errors
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( [
				'message' => __( 'Error making API request:', 'checkout-com-unified-payments-api' ) . ' ' . $response->get_error_message(),
			] );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code && 201 !== $response_code ) {
			// Try to parse error message for better user feedback
			$error_message = $response_body;
			$error_data = json_decode( $response_body, true );
			
			if ( $error_data && isset( $error_data['error_type'] ) ) {
				$error_message = $error_data['error_type'];
				if ( isset( $error_data['error_codes'] ) && is_array( $error_data['error_codes'] ) ) {
					$error_message .= ': ' . implode( ', ', $error_data['error_codes'] );
				}
				if ( isset( $error_data['message'] ) ) {
					$error_message .= ' - ' . $error_data['message'];
				}
			} elseif ( $error_data && isset( $error_data['message'] ) ) {
				$error_message = $error_data['message'];
			}
			
			// If 401 (Unauthorized) or 400 (Bad Request), try with Bearer for ABC accounts
			if ( ( 401 === $response_code || 400 === $response_code ) && 'ABC' === $account_type ) {
				// Retry with Bearer prefix for ABC accounts
				$auth_header_bearer = 'Bearer ' . $secret_key_clean;
				
				WC_Checkoutcom_Utility::logger( 
					'Apple Pay CSR Generation: Retrying with Bearer authentication for ABC account',
					null
				);
				
				$response_retry = wp_remote_post(
					$api_url,
					[
						'headers' => [
							'Authorization' => $auth_header_bearer,
							'Content-Type'  => 'application/json',
						],
						'body'    => json_encode( $request_body ),
						'timeout' => 30,
					]
				);
				
				if ( ! is_wp_error( $response_retry ) ) {
					$retry_code = wp_remote_retrieve_response_code( $response_retry );
					$retry_body = wp_remote_retrieve_body( $response_retry );
					if ( 200 === $retry_code || 201 === $retry_code ) {
						// Success with Bearer, use this response
						$response_code = $retry_code;
						$response_body = $retry_body;
					} else {
						// Log detailed error for debugging
						WC_Checkoutcom_Utility::logger( 
							'Apple Pay CSR Generation Failed (both direct key and Bearer): ' . 
							'Direct Key: ' . $response_code . ' - ' . $response_body . ', ' .
							'Bearer: ' . $retry_code . ' - ' . $retry_body,
							null
						);
					}
				}
			}
			
			// If still an error after retry, send error response
			if ( 200 !== $response_code && 201 !== $response_code ) {
				// Log detailed error for debugging
				WC_Checkoutcom_Utility::logger( 
					'Apple Pay CSR Generation Failed: ' . $response_code . ' - ' . $response_body . 
					' | URL: ' . $api_url . 
					' | Account Type: ' . $account_type . 
					' | Environment: ' . $environment,
					null
				);
				
				// For Bad Request (400), show full response to help diagnose
				if ( 400 === $response_code ) {
					$error_message = __( 'Bad Request. Please check your API credentials and account configuration.', 'checkout-com-unified-payments-api' );
					
					// Try to extract specific error from response
					if ( $error_data ) {
						if ( isset( $error_data['error_codes'] ) && is_array( $error_data['error_codes'] ) ) {
							$error_message .= ' Error codes: ' . implode( ', ', $error_data['error_codes'] );
						}
						if ( isset( $error_data['error_type'] ) ) {
							$error_message .= ' Type: ' . $error_data['error_type'];
						}
					}
				}
				
				wp_send_json_error( [
					'message' => sprintf(
						/* translators: 1: Response code, 2: Error message */
						__( 'API request failed (HTTP %1$s). %2$s', 'checkout-com-unified-payments-api' ),
						$response_code,
						$error_message
					),
					'details' => $response_body, // Include full response for debugging
					'debug_info' => [
						'url' => $api_url,
						'account_type' => $account_type,
						'environment' => $environment,
						'secret_key_prefix' => substr( $secret_key_clean, 0, 10 ) . '...', // First 10 chars for debugging (safe)
					],
				] );
			}
		}

		// Parse response
		$response_data = json_decode( $response_body, true );

		if ( ! isset( $response_data['content'] ) || empty( $response_data['content'] ) ) {
			wp_send_json_error( [
				'message' => __( 'Invalid response from API. Content field is missing.', 'checkout-com-unified-payments-api' ),
			] );
		}

		// Get CSR content
		$csr_content = $response_data['content'];

		// Ensure CSR content is properly formatted
		if ( false === strpos( $csr_content, '-----BEGIN CERTIFICATE REQUEST-----' ) ) {
			$csr_content = "-----BEGIN CERTIFICATE REQUEST-----\n" . $csr_content . "\n-----END CERTIFICATE REQUEST-----";
		}

		// Return CSR content for download
		wp_send_json_success( [
			'csr_content' => $csr_content,
			'filename'    => 'cko.csr',
		] );
	}

	/**
	 * AJAX handler for uploading Apple Pay certificate.
	 */
	public function ajax_upload_certificate() {
		// Log that the handler was called (for debugging)
		if ( function_exists( 'WC_Checkoutcom_Utility' ) && method_exists( 'WC_Checkoutcom_Utility', 'logger' ) ) {
			WC_Checkoutcom_Utility::logger( 
				'Apple Pay Certificate Upload AJAX Handler Called. POST data: ' . print_r( $_POST, true ),
				null
			);
		}
		
		// Verify nonce first
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'cko_upload_certificate' ) ) {
			wp_send_json_error( [ 
				'message' => __( 'Security check failed. Please refresh the page and try again.', 'checkout-com-unified-payments-api' ),
				'error_code' => 'nonce_verification_failed'
			] );
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 
				'message' => __( 'Insufficient permissions.', 'checkout-com-unified-payments-api' ),
				'error_code' => 'insufficient_permissions'
			] );
		}

		// Get certificate from POST data
		if ( ! isset( $_POST['certificate'] ) || empty( $_POST['certificate'] ) ) {
			wp_send_json_error( [ 
				'message' => __( 'Certificate data is missing. Please select a certificate file and try again.', 'checkout-com-unified-payments-api' ),
			] );
		}

		$certificate_base64_raw = wp_unslash( $_POST['certificate'] );
		
		// The certificate from Apple is in DER format (binary)
		// JavaScript FileReader already converts it to base64
		// According to Checkout.com docs: "Encode the apple_pay.cer in base64"
		// The command they mention: openssl x509 -inform der -in apple_pay.cer -out base64_converted.cer
		// This converts DER to PEM format (base64 with headers)
		// But the API likely expects just the base64 content (PEM without headers)
		
		// Decode base64 to get DER binary
		$certificate_der = base64_decode( $certificate_base64_raw, true );
		
		if ( false === $certificate_der ) {
			wp_send_json_error( [ 
				'message' => __( 'Invalid certificate format. Could not decode base64 certificate.', 'checkout-com-unified-payments-api' ),
			] );
		}
		
		// Convert DER to PEM format, then extract base64 content
		$certificate_final = false;
		
		// Try using OpenSSL PHP functions first
		if ( function_exists( 'openssl_x509_read' ) ) {
			$cert_resource = @openssl_x509_read( $certificate_der );
			if ( false !== $cert_resource ) {
				$pem_output = '';
				@openssl_x509_export( $cert_resource, $pem_output );
				if ( ! empty( $pem_output ) ) {
					// Extract base64 from PEM (remove headers and whitespace)
					$certificate_final = preg_replace( '/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s/', '', $pem_output );
				}
			}
		}
		
		// Fallback: use shell command to convert DER to PEM
		if ( false === $certificate_final && function_exists( 'shell_exec' ) ) {
			$temp_der = tmpfile();
			$temp_pem = tmpfile();
			
			if ( $temp_der && $temp_pem ) {
				$temp_der_path = stream_get_meta_data( $temp_der )['uri'];
				$temp_pem_path = stream_get_meta_data( $temp_pem )['uri'];
				
				file_put_contents( $temp_der_path, $certificate_der );
				
				// Convert DER to PEM: openssl x509 -inform der -in cert.cer -out cert.pem
				@shell_exec( sprintf( 'openssl x509 -inform der -in %s -out %s 2>&1', escapeshellarg( $temp_der_path ), escapeshellarg( $temp_pem_path ) ) );
				
				if ( file_exists( $temp_pem_path ) && filesize( $temp_pem_path ) > 0 ) {
					$pem_content = file_get_contents( $temp_pem_path );
					// Extract base64 from PEM (remove headers and whitespace)
					$certificate_final = preg_replace( '/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s/', '', $pem_content );
				}
				
				fclose( $temp_der );
				fclose( $temp_pem );
			}
		}
		
		// Final fallback: clean the original base64 (remove any whitespace)
		if ( false === $certificate_final ) {
			$certificate_final = preg_replace( '/\s/', '', $certificate_base64_raw );
		}

		// Get settings
		$core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings', array() );
		
		if ( empty( $core_settings['ckocom_sk'] ) ) {
			wp_send_json_error( [ 
				'message' => __( 'Secret key is not configured. Please configure Checkout.com settings first.', 'checkout-com-unified-payments-api' ),
			] );
		}

		$secret_key_raw = $core_settings['ckocom_sk'];
		$environment = isset( $core_settings['ckocom_environment'] ) ? $core_settings['ckocom_environment'] : 'sandbox';
		$account_type = isset( $core_settings['ckocom_account_type'] ) ? $core_settings['ckocom_account_type'] : 'ABC';
		$region = isset( $core_settings['ckocom_region'] ) ? $core_settings['ckocom_region'] : 'global';

		// Check if NAS account
		if ( function_exists( 'cko_is_nas_account' ) && cko_is_nas_account() ) {
			$account_type = 'NAS';
		}

		// Build API URL
		$base_url = 'https://api.checkout.com';
		if ( 'sandbox' === $environment ) {
			$base_url = 'https://api.sandbox.checkout.com';
		}

		// Add region subdomain if not global
		if ( 'global' !== $region && ! empty( $region ) ) {
			$base_url = str_replace( 'api.', $region . '.api.', $base_url );
		}

		$api_url = $base_url . '/applepay/certificates';

		// Prepare authorization header
		$secret_key_clean = str_replace( 'Bearer ', '', trim( $secret_key_raw ) );
		
		if ( 'NAS' === $account_type ) {
			$auth_header = 'Bearer ' . $secret_key_clean;
		} else {
			// ABC accounts - try direct key first
			$auth_header = $secret_key_clean;
		}

		// Prepare request body - According to Checkout.com docs:
		// "Encode the apple_pay.cer in base64 using: openssl x509 -inform der -in apple_pay.cer -out base64_converted.cer"
		// The command converts DER to PEM, but the API might expect:
		// Option 1: Raw DER certificate in base64 (what JavaScript FileReader already provides)
		// Option 2: PEM format (converted from DER)
		// Option 3: Base64 content from PEM (without headers)
		
		// Clean the base64 from JavaScript (remove any whitespace)
		$certificate_clean_base64 = preg_replace( '/\s/', '', $certificate_base64_raw );
		
		// Try format 1: Raw DER in base64 with field name "certificate"
		// The API might expect different field names or structures
		// Based on CSR response format {"content": "..."}, maybe upload expects different structure
		$request_body = [
			'certificate' => $certificate_clean_base64,
		];
		
		// Also prepare alternative structures for fallback
		$request_body_alternatives = [
			// Alternative 1: Using "content" field (like CSR response)
			[ 'content' => $certificate_clean_base64 ],
			// Alternative 2: Using "certificate_data" 
			[ 'certificate_data' => $certificate_clean_base64 ],
			// Alternative 3: Nested structure
			[ 'certificate' => [ 'content' => $certificate_clean_base64 ] ],
		];
		
		// Also prepare PEM format for fallback
		$pem_with_headers = false;
		if ( function_exists( 'openssl_x509_read' ) ) {
			$cert_resource = @openssl_x509_read( $certificate_der );
			if ( false !== $cert_resource ) {
				@openssl_x509_export( $cert_resource, $pem_with_headers );
			}
		}
		
		// Extract base64 from PEM if we have it
		$pem_base64_content = false;
		if ( false !== $pem_with_headers && ! empty( $pem_with_headers ) ) {
			$pem_base64_content = preg_replace( '/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s/', '', $pem_with_headers );
		}

		// Log request details for debugging
		WC_Checkoutcom_Utility::logger( 
			'Apple Pay Certificate Upload Request: ' . 
			'URL: ' . $api_url . ', ' .
			'Account Type: ' . $account_type . ', ' .
			'Environment: ' . $environment . ', ' .
			'Auth Header Format: ' . ( strpos( $auth_header, 'Bearer' ) !== false ? 'Bearer' : 'Direct' ) . ', ' .
			'Certificate Length: ' . strlen( $request_body['certificate'] ) . ', ' .
			'Certificate First 100 chars: ' . substr( $request_body['certificate'], 0, 100 ) . ', ' .
			'Certificate Format: ' . ( strpos( $request_body['certificate'], 'BEGIN' ) !== false ? 'PEM with headers' : 'Base64' ) . ', ' .
			'Request Body Structure: ' . json_encode( [ 'certificate' => substr( $request_body['certificate'], 0, 100 ) . '...' ] ),
			null
		);

		// Make API request
		$response = wp_remote_post(
			$api_url,
			[
				'headers' => [
					'Authorization' => $auth_header,
					'Content-Type'  => 'application/json',
				],
				'body'    => json_encode( $request_body ),
				'timeout' => 30,
			]
		);

		// Check for errors
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( [
				'message' => __( 'Error making API request:', 'checkout-com-unified-payments-api' ) . ' ' . $response->get_error_message(),
			] );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code && 201 !== $response_code ) {
			// Log the actual request body for debugging
			WC_Checkoutcom_Utility::logger( 
				'Apple Pay Certificate Upload Failed - Request Body: ' . json_encode( $request_body, JSON_UNESCAPED_SLASHES ),
				null
			);
			WC_Checkoutcom_Utility::logger( 
				'Apple Pay Certificate Upload Failed - Response: ' . $response_code . ' - ' . $response_body,
				null
			);
			
			// If 422, try alternative formats and structures
			if ( 422 === $response_code ) {
				// Try alternative request body structures first
				foreach ( $request_body_alternatives as $index => $alt_body ) {
					WC_Checkoutcom_Utility::logger( 
						'Apple Pay Certificate Upload: Retrying with alternative structure #' . ( $index + 1 ) . ': ' . json_encode( [ 'field' => array_keys( $alt_body )[0] ] ),
						null
					);
					
					$response_retry = wp_remote_post(
						$api_url,
						[
							'headers' => [
								'Authorization' => $auth_header,
								'Content-Type'  => 'application/json',
							],
							'body'    => json_encode( $alt_body ),
							'timeout' => 30,
						]
					);
					
					if ( ! is_wp_error( $response_retry ) ) {
						$retry_code = wp_remote_retrieve_response_code( $response_retry );
						$retry_body = wp_remote_retrieve_body( $response_retry );
						if ( 200 === $retry_code || 201 === $retry_code ) {
							wp_send_json_success( [
								'message' => __( 'Certificate uploaded successfully!', 'checkout-com-unified-payments-api' ),
							] );
							return;
						}
					}
				}
				
				// Try format 2: PEM base64 content (without headers)
				if ( false !== $pem_base64_content && ! empty( $pem_base64_content ) ) {
					WC_Checkoutcom_Utility::logger( 
						'Apple Pay Certificate Upload: Retrying with PEM base64 content (without headers)',
						null
					);
					
					$request_body_retry = [
						'certificate' => $pem_base64_content,
					];
					
					$response_retry = wp_remote_post(
						$api_url,
						[
							'headers' => [
								'Authorization' => $auth_header,
								'Content-Type'  => 'application/json',
							],
							'body'    => json_encode( $request_body_retry ),
							'timeout' => 30,
						]
					);
					
					if ( ! is_wp_error( $response_retry ) ) {
						$retry_code = wp_remote_retrieve_response_code( $response_retry );
						$retry_body = wp_remote_retrieve_body( $response_retry );
						if ( 200 === $retry_code || 201 === $retry_code ) {
							wp_send_json_success( [
								'message' => __( 'Certificate uploaded successfully!', 'checkout-com-unified-payments-api' ),
							] );
							return;
						}
					}
				}
				
				// Try format 3: Full PEM format (with headers)
				if ( false !== $pem_with_headers && ! empty( $pem_with_headers ) ) {
					WC_Checkoutcom_Utility::logger( 
						'Apple Pay Certificate Upload: Retrying with full PEM format (with headers)',
						null
					);
					
					$request_body_retry = [
						'certificate' => $pem_with_headers,
					];
					
					$response_retry = wp_remote_post(
						$api_url,
						[
							'headers' => [
								'Authorization' => $auth_header,
								'Content-Type'  => 'application/json',
							],
							'body'    => json_encode( $request_body_retry ),
							'timeout' => 30,
						]
					);
					
					if ( ! is_wp_error( $response_retry ) ) {
						$retry_code = wp_remote_retrieve_response_code( $response_retry );
						$retry_body = wp_remote_retrieve_body( $response_retry );
						if ( 200 === $retry_code || 201 === $retry_code ) {
							wp_send_json_success( [
								'message' => __( 'Certificate uploaded successfully!', 'checkout-com-unified-payments-api' ),
							] );
							return;
						}
					}
				}
			}
			
			// Try to parse error message
			$error_message = $response_body;
			$error_data = json_decode( $response_body, true );
			
			// Log full error details
			WC_Checkoutcom_Utility::logger( 
				'Apple Pay Certificate Upload - Full Error Data: ' . print_r( $error_data, true ),
				null
			);
			
			if ( $error_data && isset( $error_data['error_type'] ) ) {
				$error_message = $error_data['error_type'];
				if ( isset( $error_data['error_codes'] ) && is_array( $error_data['error_codes'] ) ) {
					$error_message .= ': ' . implode( ', ', $error_data['error_codes'] );
					
					// Log individual error codes for debugging
					foreach ( $error_data['error_codes'] as $error_code ) {
						WC_Checkoutcom_Utility::logger( 
							'Apple Pay Certificate Upload - Error Code: ' . $error_code,
							null
						);
					}
				}
				if ( isset( $error_data['message'] ) ) {
					$error_message .= ' - ' . $error_data['message'];
				}
				// Check for additional error details
				if ( isset( $error_data['details'] ) ) {
					WC_Checkoutcom_Utility::logger( 
						'Apple Pay Certificate Upload - Error Details: ' . print_r( $error_data['details'], true ),
						null
					);
				}
			} elseif ( $error_data && isset( $error_data['message'] ) ) {
				$error_message = $error_data['message'];
			}
			
			// If 401 (Unauthorized) or 400 (Bad Request), try with Bearer for ABC accounts
			if ( ( 401 === $response_code || 400 === $response_code ) && 'ABC' === $account_type ) {
				// Retry with Bearer prefix for ABC accounts
				$auth_header_bearer = 'Bearer ' . $secret_key_clean;
				
				WC_Checkoutcom_Utility::logger( 
					'Apple Pay Certificate Upload: Retrying with Bearer authentication for ABC account',
					null
				);
				
				$response_retry = wp_remote_post(
					$api_url,
					[
						'headers' => [
							'Authorization' => $auth_header_bearer,
							'Content-Type'  => 'application/json',
						],
						'body'    => json_encode( $request_body ),
						'timeout' => 30,
					]
				);
				
				if ( ! is_wp_error( $response_retry ) ) {
					$retry_code = wp_remote_retrieve_response_code( $response_retry );
					$retry_body = wp_remote_retrieve_body( $response_retry );
					if ( 200 === $retry_code || 201 === $retry_code ) {
						// Success with Bearer
						wp_send_json_success( [
							'message' => __( 'Certificate uploaded successfully!', 'checkout-com-unified-payments-api' ),
						] );
						return;
					} else {
						// Log detailed error for debugging
						WC_Checkoutcom_Utility::logger( 
							'Apple Pay Certificate Upload Failed (both direct key and Bearer): ' . 
							'Direct Key: ' . $response_code . ' - ' . $response_body . ', ' .
							'Bearer: ' . $retry_code . ' - ' . $retry_body,
							null
						);
					}
				}
			}
			
			// Log detailed error for debugging
			WC_Checkoutcom_Utility::logger( 
				'Apple Pay Certificate Upload Failed: ' . $response_code . ' - ' . $response_body . 
				' | URL: ' . $api_url . 
				' | Account Type: ' . $account_type . 
				' | Environment: ' . $environment,
				null
			);
			
			// Build detailed error message
			$detailed_error = $error_message;
			if ( $error_data ) {
				if ( isset( $error_data['error_codes'] ) && is_array( $error_data['error_codes'] ) ) {
					$detailed_error .= ' Error codes: ' . implode( ', ', $error_data['error_codes'] );
				}
				if ( isset( $error_data['details'] ) ) {
					$detailed_error .= ' Details: ' . ( is_array( $error_data['details'] ) ? json_encode( $error_data['details'] ) : $error_data['details'] );
				}
			}
			
			wp_send_json_error( [
				'message' => sprintf(
					/* translators: 1: Response code, 2: Error message */
					__( 'API request failed (HTTP %1$s). %2$s', 'checkout-com-unified-payments-api' ),
					$response_code,
					$detailed_error
				),
				'details' => $response_body,
				'error_data' => $error_data,
				'debug_info' => [
					'url' => $api_url,
					'account_type' => $account_type,
					'environment' => $environment,
					'certificate_length' => strlen( $request_body['certificate'] ),
					'certificate_format' => 'DER base64',
				],
			] );
			return;
		}

		// Success
		WC_Checkoutcom_Utility::logger( 
			'Apple Pay Certificate Upload Success: ' . $response_body,
			null
		);

		wp_send_json_success( [
			'message' => __( 'Certificate uploaded successfully!', 'checkout-com-unified-payments-api' ),
		] );
	}

	/**
	 * AJAX handler for generating merchant certificate and private key.
	 * Generates a self-signed certificate and private key pair for Apple Pay merchant validation.
	 *
	 * @return void
	 */
	public function ajax_generate_merchant_certificate() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'cko_generate_merchant_certificate' ) ) {
			wp_send_json_error( [ 
				'message' => __( 'Security check failed. Please refresh the page and try again.', 'checkout-com-unified-payments-api' ),
			] );
			return;
		}

		// Check if doing AJAX
		if ( ! wp_doing_ajax() ) {
			wp_send_json_error( [ 
				'message' => __( 'Invalid request. This action must be performed via AJAX.', 'checkout-com-unified-payments-api' ),
			] );
			return;
		}

		// Check if OpenSSL is available
		if ( ! function_exists( 'openssl_pkey_new' ) ) {
			wp_send_json_error( [ 
				'message' => __( 'OpenSSL is not available on this server. Please contact your hosting provider to enable OpenSSL.', 'checkout-com-unified-payments-api' ),
			] );
			return;
		}

		// Get site domain for certificate subject
		$site_domain = parse_url( home_url(), PHP_URL_HOST );
		if ( empty( $site_domain ) ) {
			$site_domain = 'example.com';
		}

		// Generate certificate configuration
		$config = [
			'digest_alg' => 'sha256',
			'private_key_bits' => 2048,
			'private_key_type' => OPENSSL_KEYTYPE_RSA,
			'x509_extensions' => 'v3_req',
		];

		// Create certificate subject
		$dn = [
			'countryName' => 'US',
			'stateOrProvinceName' => 'State',
			'localityName' => 'City',
			'organizationName' => get_bloginfo( 'name' ),
			'organizationalUnitName' => 'Apple Pay',
			'commonName' => $site_domain,
			'emailAddress' => get_option( 'admin_email', 'admin@example.com' ),
		];

		// Generate private key
		$private_key = openssl_pkey_new( $config );
		if ( false === $private_key ) {
			$openssl_error = openssl_error_string();
			wp_send_json_error( [ 
				'message' => __( 'Failed to generate private key. OpenSSL error: ', 'checkout-com-unified-payments-api' ) . $openssl_error,
			] );
			return;
		}

		// Generate certificate signing request
		$csr = openssl_csr_new( $dn, $private_key, $config );
		if ( false === $csr ) {
			$openssl_error = openssl_error_string();
			wp_send_json_error( [ 
				'message' => __( 'Failed to generate certificate signing request. OpenSSL error: ', 'checkout-com-unified-payments-api' ) . $openssl_error,
			] );
			return;
		}

		// Sign the certificate (self-signed, valid for 1 year)
		$cert = openssl_csr_sign( $csr, null, $private_key, 365, $config, time() );
		if ( false === $cert ) {
			$openssl_error = openssl_error_string();
			wp_send_json_error( [ 
				'message' => __( 'Failed to sign certificate. OpenSSL error: ', 'checkout-com-unified-payments-api' ) . $openssl_error,
			] );
			return;
		}

		// Export certificate to PEM format
		$certificate_pem = '';
		if ( ! openssl_x509_export( $cert, $certificate_pem ) ) {
			$openssl_error = openssl_error_string();
			wp_send_json_error( [ 
				'message' => __( 'Failed to export certificate. OpenSSL error: ', 'checkout-com-unified-payments-api' ) . $openssl_error,
			] );
			return;
		}

		// Export private key to PEM format
		$private_key_pem = '';
		if ( ! openssl_pkey_export( $private_key, $private_key_pem ) ) {
			$openssl_error = openssl_error_string();
			wp_send_json_error( [ 
				'message' => __( 'Failed to export private key. OpenSSL error: ', 'checkout-com-unified-payments-api' ) . $openssl_error,
			] );
			return;
		}

		// Return certificate and private key as base64 for download
		wp_send_json_success( [
			'message' => __( 'Certificate and private key generated successfully!', 'checkout-com-unified-payments-api' ),
			'certificate' => base64_encode( $certificate_pem ),
			'private_key' => base64_encode( $private_key_pem ),
			'certificate_filename' => 'apple-pay-merchant-certificate.pem',
			'private_key_filename' => 'apple-pay-merchant-private-key.key',
		] );
	}

	/**
	 * Get the correct .well-known directory path based on server configuration.
	 * Detects Bitnami installations and uses the Let's Encrypt path.
	 *
	 * @return array Array with 'dir' (directory path) and 'is_bitnami' (boolean).
	 */
	private function get_well_known_path() {
		// Check if this is a Bitnami installation
		$is_bitnami = file_exists( '/opt/bitnami/apps/letsencrypt/.well-known' ) || 
		              file_exists( '/opt/bitnami/apps/letsencrypt' ) ||
		              ( defined( 'ABSPATH' ) && strpos( ABSPATH, '/opt/bitnami/' ) !== false );
		
		if ( $is_bitnami && file_exists( '/opt/bitnami/apps/letsencrypt/.well-known' ) ) {
			// Bitnami uses Let's Encrypt directory for .well-known
			return array(
				'dir' => '/opt/bitnami/apps/letsencrypt/.well-known',
				'is_bitnami' => true,
			);
		}
		
		// Standard WordPress installation
		return array(
			'dir' => ABSPATH . '.well-known',
			'is_bitnami' => false,
		);
	}

	/**
	 * AJAX handler for uploading domain association file.
	 */
	public function ajax_upload_domain_association() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'cko_upload_domain_association' ) ) {
			wp_send_json_error( [ 
				'message' => __( 'Security check failed. Please refresh the page and try again.', 'checkout-com-unified-payments-api' ),
			] );
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 
				'message' => __( 'Insufficient permissions.', 'checkout-com-unified-payments-api' ),
			] );
			return;
		}

		// Get file content from POST data
		if ( ! isset( $_POST['file_content'] ) || empty( $_POST['file_content'] ) ) {
			wp_send_json_error( [ 
				'message' => __( 'File content is missing. Please select a domain association file and try again.', 'checkout-com-unified-payments-api' ),
			] );
			return;
		}

		$file_content = wp_unslash( $_POST['file_content'] );
		
		// Get the correct .well-known directory path
		$well_known_info = $this->get_well_known_path();
		$well_known_dir = $well_known_info['dir'];
		$is_bitnami = $well_known_info['is_bitnami'];
		
		// Create .well-known directory if it doesn't exist
		if ( ! file_exists( $well_known_dir ) ) {
			if ( $is_bitnami ) {
				// For Bitnami, try to create with sudo permissions (user will need to do this manually if it fails)
				if ( ! wp_mkdir_p( $well_known_dir ) && ! is_dir( $well_known_dir ) ) {
					wp_send_json_error( [ 
						'message' => sprintf(
							__( 'Failed to create .well-known directory at %s. For Bitnami installations, you may need to create it manually via SSH: sudo mkdir -p %s && sudo chmod 755 %s', 'checkout-com-unified-payments-api' ),
							$well_known_dir,
							$well_known_dir,
							$well_known_dir
						),
					] );
					return;
				}
			} else {
			if ( ! wp_mkdir_p( $well_known_dir ) ) {
				wp_send_json_error( [ 
					'message' => __( 'Failed to create .well-known directory. Please check file permissions.', 'checkout-com-unified-payments-api' ),
				] );
				return;
				}
			}
		}

		// Apple now requires the file to have .txt extension
		// Save file to .well-known/apple-developer-merchantid-domain-association.txt
		$file_path = $well_known_dir . '/apple-developer-merchantid-domain-association.txt';
		$file_saved = file_put_contents( $file_path, $file_content );
		
		if ( false === $file_saved ) {
			wp_send_json_error( [ 
				'message' => sprintf(
					__( 'Failed to save domain association file to %s. Please check file permissions. For Bitnami, you may need to set permissions manually: sudo chmod 644 %s', 'checkout-com-unified-payments-api' ),
					$file_path,
					$file_path
				),
			] );
			return;
		}

		// Set appropriate file permissions
		@chmod( $file_path, 0644 );
		
		// For non-Bitnami installations, ensure .well-known directory is accessible
		if ( ! $is_bitnami ) {
		$this->ensure_well_known_accessible();
		}

		// Also save without .txt extension for backward compatibility (if not Bitnami)
		if ( ! $is_bitnami ) {
			$file_path_no_ext = $well_known_dir . '/apple-developer-merchantid-domain-association';
			file_put_contents( $file_path_no_ext, $file_content );
			@chmod( $file_path_no_ext, 0644 );
		}

		wp_send_json_success( [
			'message' => __( 'Domain association file uploaded successfully!', 'checkout-com-unified-payments-api' ),
			'file_path' => $file_path,
			'file_url' => home_url( '/.well-known/apple-developer-merchantid-domain-association.txt' ),
			'is_bitnami' => $is_bitnami,
		] );
	}

	/**
	 * AJAX handler for generating Merchant Identity CSR and private key.
	 */
	public function ajax_generate_merchant_identity_csr() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'cko_generate_merchant_identity_csr' ) ) {
			wp_send_json_error( [ 
				'message' => __( 'Security check failed. Please refresh the page and try again.', 'checkout-com-unified-payments-api' ),
			] );
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 
				'message' => __( 'Insufficient permissions.', 'checkout-com-unified-payments-api' ),
			] );
			return;
		}

		// Check if OpenSSL is available
		if ( ! function_exists( 'openssl_pkey_new' ) ) {
			wp_send_json_error( [ 
				'message' => __( 'OpenSSL is not available on this server. Please contact your hosting provider to enable OpenSSL.', 'checkout-com-unified-payments-api' ),
			] );
			return;
		}

		// Get site domain for certificate subject
		$site_domain = parse_url( home_url(), PHP_URL_HOST );
		if ( empty( $site_domain ) ) {
			$site_domain = 'example.com';
		}

		// Generate certificate configuration
		$config = [
			'digest_alg' => 'sha256',
			'private_key_bits' => 2048,
			'private_key_type' => OPENSSL_KEYTYPE_RSA,
		];

		// Create certificate subject
		$dn = [
			'countryName' => 'US',
			'stateOrProvinceName' => 'State',
			'localityName' => 'City',
			'organizationName' => get_bloginfo( 'name' ),
			'organizationalUnitName' => 'Apple Pay',
			'commonName' => $site_domain,
			'emailAddress' => get_option( 'admin_email', 'admin@example.com' ),
		];

		// Generate private key
		$private_key = openssl_pkey_new( $config );
		if ( false === $private_key ) {
			$openssl_error = openssl_error_string();
			wp_send_json_error( [ 
				'message' => __( 'Failed to generate private key. OpenSSL error: ', 'checkout-com-unified-payments-api' ) . $openssl_error,
			] );
			return;
		}

		// Generate certificate signing request
		$csr = openssl_csr_new( $dn, $private_key, $config );
		if ( false === $csr ) {
			$openssl_error = openssl_error_string();
			wp_send_json_error( [ 
				'message' => __( 'Failed to generate certificate signing request. OpenSSL error: ', 'checkout-com-unified-payments-api' ) . $openssl_error,
			] );
			return;
		}

		// Export CSR to PEM format
		$csr_pem = '';
		if ( ! openssl_csr_export( $csr, $csr_pem ) ) {
			$openssl_error = openssl_error_string();
			wp_send_json_error( [ 
				'message' => __( 'Failed to export CSR. OpenSSL error: ', 'checkout-com-unified-payments-api' ) . $openssl_error,
			] );
			return;
		}

		// Export private key to PEM format
		$private_key_pem = '';
		if ( ! openssl_pkey_export( $private_key, $private_key_pem ) ) {
			$openssl_error = openssl_error_string();
			wp_send_json_error( [ 
				'message' => __( 'Failed to export private key. OpenSSL error: ', 'checkout-com-unified-payments-api' ) . $openssl_error,
			] );
			return;
		}

		// Save private key file on server
		$upload_dir = wp_upload_dir();
		if ( $upload_dir['error'] ) {
			wp_send_json_error( [ 
				'message' => __( 'Failed to access uploads directory.', 'checkout-com-unified-payments-api' ),
			] );
			return;
		}

		$certificate_dir = $upload_dir['basedir'] . '/checkout-com-apple-pay';
		if ( ! file_exists( $certificate_dir ) ) {
			wp_mkdir_p( $certificate_dir );
		}

		// Save key file on server
		$key_file = $certificate_dir . '/certificate_sandbox.key';
		$key_saved = file_put_contents( $key_file, $private_key_pem );
		
		if ( false === $key_saved ) {
			wp_send_json_error( [ 
				'message' => __( 'Failed to save private key file on server. Please check file permissions.', 'checkout-com-unified-payments-api' ),
			] );
			return;
		}

		// Set appropriate file permissions (readable only by owner)
		chmod( $key_file, 0600 );

		// Return CSR and private key as base64 for download, and also return server paths
		wp_send_json_success( [
			'message' => __( 'CSR and private key generated successfully! Both files have been saved on your server.', 'checkout-com-unified-payments-api' ),
			'csr' => base64_encode( $csr_pem ),
			'private_key' => base64_encode( $private_key_pem ),
			'csr_filename' => 'uploadMe.csr',
			'private_key_filename' => 'certificate_sandbox.key',
			'key_file_path' => $key_file,
			'key_file_url' => $upload_dir['baseurl'] . '/checkout-com-apple-pay/certificate_sandbox.key',
		] );
	}

	/**
	 * AJAX handler for uploading and converting Merchant Identity certificate.
	 */
	public function ajax_upload_merchant_identity_certificate() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'cko_upload_merchant_identity_certificate' ) ) {
			wp_send_json_error( [ 
				'message' => __( 'Security check failed. Please refresh the page and try again.', 'checkout-com-unified-payments-api' ),
			] );
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 
				'message' => __( 'Insufficient permissions.', 'checkout-com-unified-payments-api' ),
			] );
			return;
		}

		// Get certificate from POST data
		if ( ! isset( $_POST['certificate'] ) || empty( $_POST['certificate'] ) ) {
			wp_send_json_error( [ 
				'message' => __( 'Certificate data is missing. Please select a certificate file and try again.', 'checkout-com-unified-payments-api' ),
			] );
			return;
		}

		$certificate_base64 = wp_unslash( $_POST['certificate'] );
		
		// Decode base64 to get DER format
		$certificate_der = base64_decode( $certificate_base64 );
		if ( false === $certificate_der ) {
			wp_send_json_error( [ 
				'message' => __( 'Failed to decode certificate. Please ensure the file is valid.', 'checkout-com-unified-payments-api' ),
			] );
			return;
		}

		// Convert DER to PEM format
		$certificate_pem = '';
		if ( function_exists( 'openssl_x509_read' ) ) {
			// Try to read the certificate from DER format
			$cert = openssl_x509_read( $certificate_der );
			if ( false !== $cert ) {
				openssl_x509_export( $cert, $certificate_pem );
			}
		}

		// Fallback to shell command if PHP function fails
		if ( empty( $certificate_pem ) ) {
			// Create temporary DER file
			$temp_der = sys_get_temp_dir() . '/' . uniqid( 'cert_' ) . '.der';
			file_put_contents( $temp_der, $certificate_der );
			
			// Convert using openssl command
			$command = 'openssl x509 -inform DER -in ' . escapeshellarg( $temp_der ) . ' -outform PEM';
			$certificate_pem = shell_exec( $command );
			
			// Clean up temp file
			@unlink( $temp_der );
		}

		if ( empty( $certificate_pem ) ) {
			wp_send_json_error( [ 
				'message' => __( 'Failed to convert certificate from DER to PEM format. Please ensure OpenSSL is available.', 'checkout-com-unified-payments-api' ),
			] );
			return;
		}

		// Save certificate to WordPress uploads directory
		$upload_dir = wp_upload_dir();
		if ( $upload_dir['error'] ) {
			wp_send_json_error( [ 
				'message' => __( 'Failed to access uploads directory.', 'checkout-com-unified-payments-api' ),
			] );
			return;
		}

		$certificate_dir = $upload_dir['basedir'] . '/checkout-com-apple-pay';
		if ( ! file_exists( $certificate_dir ) ) {
			wp_mkdir_p( $certificate_dir );
		}

		// Save as certificate_sandbox.pem to match the key file name
		$certificate_file = $certificate_dir . '/certificate_sandbox.pem';
		$file_saved = file_put_contents( $certificate_file, $certificate_pem );
		
		if ( false === $file_saved ) {
			wp_send_json_error( [ 
				'message' => __( 'Failed to save certificate file. Please check file permissions.', 'checkout-com-unified-payments-api' ),
			] );
			return;
		}

		// Set appropriate file permissions
		chmod( $certificate_file, 0600 );

		// Check if key file exists from previous step
		$key_file = $certificate_dir . '/certificate_sandbox.key';
		$key_file_exists = file_exists( $key_file );

		$response_data = [
			'message' => __( 'Certificate uploaded and converted successfully! Both files are now saved on your server.', 'checkout-com-unified-payments-api' ),
			'certificate_path' => $certificate_file,
			'key_path' => $key_file_exists ? $key_file : null,
		];

		if ( $key_file_exists ) {
			$response_data['message'] = __( 'Certificate uploaded and converted successfully! Both certificate (.pem) and key (.key) files are now saved on your server.', 'checkout-com-unified-payments-api' );
		} else {
			$response_data['message'] = __( 'Certificate uploaded and converted successfully! The certificate (.pem) file is saved. Make sure the key (.key) file from Step 1 is also saved on your server.', 'checkout-com-unified-payments-api' );
		}

		wp_send_json_success( $response_data );
	}

	/**
	 * AJAX handler for testing Apple Pay certificate and key.
	 */
	public function ajax_test_certificate() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'cko_test_certificate' ) ) {
			wp_send_json_error( [ 
				'message' => __( 'Security check failed. Please refresh the page and try again.', 'checkout-com-unified-payments-api' ),
			] );
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 
				'message' => __( 'Insufficient permissions.', 'checkout-com-unified-payments-api' ),
			] );
			return;
		}

		// Get settings
		$apple_settings = get_option( 'woocommerce_wc_checkout_com_apple_pay_settings', [] );
		
		$merchant_id = isset( $apple_settings['ckocom_apple_mercahnt_id'] ) ? $apple_settings['ckocom_apple_mercahnt_id'] : '';
		$domain_name = isset( $apple_settings['apple_pay_domain_name'] ) ? $apple_settings['apple_pay_domain_name'] : '';
		$display_name = isset( $apple_settings['apple_pay_display_name'] ) ? $apple_settings['apple_pay_display_name'] : '';
		$certificate_path = isset( $apple_settings['ckocom_apple_certificate'] ) ? $apple_settings['ckocom_apple_certificate'] : '';
		$key_path = isset( $apple_settings['ckocom_apple_key'] ) ? $apple_settings['ckocom_apple_key'] : '';

		// Validate required fields
		if ( empty( $merchant_id ) ) {
			wp_send_json_error( [ 
				'message' => __( 'Merchant Identifier is required. Please configure it in the Apple Pay Configuration section.', 'checkout-com-unified-payments-api' ),
			] );
			return;
		}

		if ( empty( $domain_name ) ) {
			wp_send_json_error( [ 
				'message' => __( 'Domain Name is required. Please configure it in the Apple Pay Configuration section.', 'checkout-com-unified-payments-api' ),
			] );
			return;
		}

		if ( empty( $display_name ) ) {
			wp_send_json_error( [ 
				'message' => __( 'Display Name is required. Please configure it in the Apple Pay Configuration section.', 'checkout-com-unified-payments-api' ),
			] );
			return;
		}

		if ( empty( $certificate_path ) || ! file_exists( $certificate_path ) ) {
			wp_send_json_error( [ 
				'message' => __( 'Certificate file not found. Please ensure the certificate path is correct and the file exists.', 'checkout-com-unified-payments-api' ),
			] );
			return;
		}

		if ( empty( $key_path ) || ! file_exists( $key_path ) ) {
			wp_send_json_error( [ 
				'message' => __( 'Private key file not found. Please ensure the key path is correct and the file exists.', 'checkout-com-unified-payments-api' ),
			] );
			return;
		}

		// Prepare request data
		$request_data = [
			'merchantIdentifier' => $merchant_id,
			'displayName' => $display_name,
			'domainName' => $domain_name,
		];

		// Make request to Apple Pay payment session endpoint
		$api_url = 'https://apple-pay-gateway.apple.com/paymentservices/paymentSession';
		
		// Use cURL for certificate-based authentication
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $api_url );
		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $request_data ) );
		curl_setopt( $ch, CURLOPT_SSLCERT, $certificate_path );
		curl_setopt( $ch, CURLOPT_SSLKEY, $key_path );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_VERBOSE, true );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
		] );

		$response = curl_exec( $ch );
		$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$curl_error = curl_error( $ch );
		curl_close( $ch );

		if ( ! empty( $curl_error ) ) {
			wp_send_json_error( [ 
				'message' => __( 'cURL error: ', 'checkout-com-unified-payments-api' ) . $curl_error,
			] );
			return;
		}

		// Check response
		if ( 200 === $http_code ) {
			// Simplified success message - don't return full JSON response
			wp_send_json_success( [
				'message' => __( 'Certificate and key test successful! All paths and configurations are validated with Apple\'s services.', 'checkout-com-unified-payments-api' ),
			] );
		} else {
			// Simplified error message with actionable guidance
			$error_message = __( 'Test Failed. Please verify:', 'checkout-com-unified-payments-api' );
			$error_message .= '<ol style="margin: 8px 0 0 20px; padding-left: 20px;">';
			$error_message .= '<li>' . __( 'Your certificate paths are correct', 'checkout-com-unified-payments-api' ) . '</li>';
			$error_message .= '<li>' . __( 'Your certificate has not expired', 'checkout-com-unified-payments-api' ) . '</li>';
			$error_message .= '<li>' . __( 'You clicked "Verify" in your Apple Developer account for your domain', 'checkout-com-unified-payments-api' ) . '</li>';
			$error_message .= '</ol>';
			
			wp_send_json_error( [ 
				'message' => $error_message,
			] );
		}
	}

	/**
	 * Ensure .well-known directory is accessible by creating/updating .htaccess
	 * This allows the domain association file to be accessible via HTTP
	 */
	private function ensure_well_known_accessible() {
		$well_known_dir = ABSPATH . '.well-known';
		
		if ( ! file_exists( $well_known_dir ) ) {
			return;
		}
		
		// Create .htaccess in .well-known directory to allow access
		$htaccess_file = $well_known_dir . '/.htaccess';
		$htaccess_content = "# Allow access to .well-known directory\n";
		$htaccess_content .= "<IfModule mod_rewrite.c>\n";
		$htaccess_content .= "RewriteEngine On\n";
		$htaccess_content .= "RewriteRule ^apple-developer-merchantid-domain-association$ - [L]\n";
		$htaccess_content .= "</IfModule>\n";
		
		// Only write if file doesn't exist or doesn't have our rule
		if ( ! file_exists( $htaccess_file ) || strpos( file_get_contents( $htaccess_file ), 'apple-developer-merchantid-domain-association' ) === false ) {
			file_put_contents( $htaccess_file, $htaccess_content );
			chmod( $htaccess_file, 0644 );
		}
		
		// Also check main .htaccess and add rule if needed (before WordPress rules)
		$main_htaccess = ABSPATH . '.htaccess';
		if ( file_exists( $main_htaccess ) ) {
			$main_htaccess_content = file_get_contents( $main_htaccess );
			
			// Check if .well-known rule already exists
			if ( strpos( $main_htaccess_content, '# BEGIN Allow .well-known' ) === false ) {
				// Add rule before WordPress rules
				$well_known_rule = "\n# BEGIN Allow .well-known\n";
				$well_known_rule .= "<IfModule mod_rewrite.c>\n";
				$well_known_rule .= "RewriteEngine On\n";
				$well_known_rule .= "RewriteRule ^\.well-known/ - [L]\n";
				$well_known_rule .= "</IfModule>\n";
				$well_known_rule .= "# END Allow .well-known\n";
				
				// Insert before # BEGIN WordPress if it exists
				if ( strpos( $main_htaccess_content, '# BEGIN WordPress' ) !== false ) {
					$main_htaccess_content = str_replace( '# BEGIN WordPress', $well_known_rule . "\n# BEGIN WordPress", $main_htaccess_content );
				} else {
					// If no WordPress section, add at the beginning
					$main_htaccess_content = $well_known_rule . "\n" . $main_htaccess_content;
				}
				
				file_put_contents( $main_htaccess, $main_htaccess_content );
			}
		}
	}
>>>>>>> upstream/feature/flow-integration-v5.0.0-beta
}
