<?php
/**
 * Card payment method settings class.
 *
 * @package wc_checkout_com
 */

/**
 * Class WC_Checkoutcom_Cards_Settings
 */
class WC_Checkoutcom_Cards_Settings {

	/**
	 * Constructor
	 */
	public function __construct() {

		/**
		 * Actions.
		 */
		add_action( 'woocommerce_admin_field_checkoutcom_webhook_settings', [ $this, 'checkoutcom_cards_settings_html' ] );
	}

	/**
	 * Custom markup for webhook settings.
	 *
	 * @param array $value Admin field information.
	 *
	 * @return void
	 */
	public function checkoutcom_cards_settings_html( $value ) {

		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<?php esc_html_e( 'Webhook Status', 'checkout-com-unified-payments-api' ); ?>
			</th>
			<td class="forminp forminp-checkoutcom_webhook_settings">
				<p>
					<button type="button" class="button button-primary" id="checkoutcom-is-register-webhook"><?php esc_html_e( 'Run Webhook check', 'checkout-com-unified-payments-api' ); ?></button>
					<span class="dashicons dashicons-yes hidden" style="font-size: 30px;height: 30px;width: 30px;color: #008000;"></span>
					<span class="spinner" style="float: none;"></span>
					<p><?php esc_html_e( 'This action will check if webhook is configured for current site.', 'checkout-com-unified-payments-api' ); ?></p>
				</p>
				<p class="checkoutcom-is-register-webhook-text"></p>
			</td>
		</tr>

		<tr valign="top" class="checkoutcom-new-webhook-setting">
			<th scope="row" class="titledesc">
				<?php esc_html_e( 'Register New Webhook', 'checkout-com-unified-payments-api' ); ?>
			</th>
			<td class="forminp forminp-checkoutcom_webhook_settings">
				<p>
					<button type="button" class="button button-primary" id="checkoutcom-register-webhook"><?php esc_html_e( 'Register Webhook', 'checkout-com-unified-payments-api' ); ?></button>
					<span class="dashicons dashicons-yes hidden" style="font-size: 30px;height: 30px;width: 30px;color: #008000;"></span>
					<span class="spinner" style="float: none;"></span>
				</p>
				<?php
				printf(
					'<p style="margin-top: 10px;">%s</p><br><code>%s</code><div class="cko-ajax-data"></div>',
					esc_html__( 'Click above button to register webhook URL', 'checkout-com-unified-payments-api' ),
					esc_url( WC_Checkoutcom_Webhook::get_instance()->generate_current_webhook_url() )
				);
				?>
			</td>
		</tr>

		<?php
	}

	/**
	 * CKO admin core settings fields
	 *
	 * @return mixed
	 */
	public static function core_settings() {
		$core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings' );
		$nas_docs      = 'https://www.checkout.com/docs/four/resources/api-authentication/api-keys';
		$abc_docs      = 'https://www.checkout.com/docs/the-hub/update-your-hub-settings#Manage_the_API_keys';
		$docs_link     = $abc_docs;

		if ( isset( $core_settings['ckocom_account_type'] ) && 'NAS' === $core_settings['ckocom_account_type'] ) {
			$docs_link = $nas_docs;
		}

		$settings = [
			'core_setting'        => [
				'title'       => __( 'Core settings', 'checkout-com-unified-payments-api' ),
				'type'        => 'title',
				'description' => '',
			],
			'enabled'             => [
				'id'          => 'enable',
				'title'       => __( 'Enable/Disable', 'checkout-com-unified-payments-api' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable Checkout.com cards payment', 'checkout-com-unified-payments-api' ),
				'description' => __( 'This enables Checkout.com. cards payment', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => true,
				'default'     => 'yes',
			],
			'ckocom_environment'  => [
				'title'       => __( 'Environment', 'checkout-com-unified-payments-api' ),
				'type'        => 'select',
				'description' => __( 'When going to production, make sure to set this to Live', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => true,
				'options'     => [
					'sandbox' => __( 'SandBox', 'checkout-com-unified-payments-api' ),
					'live'    => __( 'Live', 'checkout-com-unified-payments-api' ),
				],
				'default'     => 'sandbox',
			],
			'title'               => [
				'title'       => __( 'Payment Option Title', 'checkout-com-unified-payments-api' ),
				'type'        => 'text',
				'label'       => __( 'Pay by Card with Checkout.com', 'checkout-com-unified-payments-api' ),
				'description' => __( 'Title that will be displayed on the checkout page', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => true,
				'default'     => 'Pay by Card with Checkout.com',
			],
			'ckocom_account_type' => [
				'title'       => __( 'Account Type', 'checkout-com-unified-payments-api' ),
				'type'        => 'select',
				'description' => __( 'Contact support team to know your account type.', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => true,
				'options'     => [
					'ABC' => __( 'ABC', 'checkout-com-unified-payments-api' ),
					'NAS' => __( 'NAS', 'checkout-com-unified-payments-api' ),
				],
				'default'     => 'ABC',
			],
			'ckocom_sk'           => [
				'title'       => __( 'Secret Key', 'checkout-com-unified-payments-api' ),
				'type'        => 'text',
				/* translators: 1: HTML anchor opening tag, 2: HTML anchor closing tag. */
				'description' => sprintf( __( 'You can %1$s find your secret key %2$s in the Checkout.com Hub', 'checkout-com-unified-payments-api' ), '<a class="checkoutcom-key-docs" target="_blank" href="' . esc_url( $docs_link ) . '">', '</a>' ),
				'placeholder' => 'sk_xxx',
			],
			'ckocom_pk'           => [
				'title'       => __( 'Public Key', 'checkout-com-unified-payments-api' ),
				'type'        => 'text',
				/* translators: 1: HTML anchor opening tag, 2: HTML anchor closing tag. */
				'description' => sprintf( __( 'You can %1$s find your public key %2$s in the Checkout.com Hub', 'checkout-com-unified-payments-api' ), '<a class="checkoutcom-key-docs" target="_blank" href="' . esc_url( $docs_link ) . '">', '</a>' ),
				'placeholder' => 'pk_xxx',
			],
			'enable_fallback_ac'  => [
				'id'      => 'enable',
				'title'   => __( 'Fallback Account', 'checkout-com-unified-payments-api' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Fallback Account(ABC account) for Refund', 'checkout-com-unified-payments-api' ),
				'default' => 'no',
			],
			'fallback_ckocom_sk'  => [
				'title'       => __( 'Secret Key', 'checkout-com-unified-payments-api' ),
				'type'        => 'text',
				'placeholder' => 'sk_xxx',
			],
			'fallback_ckocom_pk'  => [
				'title'       => __( 'Public Key', 'checkout-com-unified-payments-api' ),
				'type'        => 'text',
				'placeholder' => 'pk_xxx',
			],
		];

		return apply_filters( 'wc_checkout_com_cards', $settings );
	}

	/**
	 * CKO admin card setting fields
	 *
	 * @return mixed|void
	 */
	public static function cards_settings() {

		$settings = [
			'card_setting'                          => [
				'title'       => __( 'Card settings', 'checkout-com-unified-payments-api' ),
				'type'        => 'title',
				'description' => '',
			],
			'ckocom_card_autocap'                   => [
				'id'       => 'ckocom_card_autocap',
				'title'    => __( 'Payment Action', 'checkout-com-unified-payments-api' ),
				'type'     => 'select',
				'desc_tip' => true,
				'options'  => [
					0 => __( 'Authorize only', 'checkout-com-unified-payments-api' ),
					1 => __( 'Authorize and Capture', 'checkout-com-unified-payments-api' ),
				],
				'default'  => 1,
				'desc'     => 'Set this to Authorise only if you want to manually capture the payment.',
			],
			'ckocom_card_cap_delay'                 => [
				'id'       => 'ckocom_card_cap_delay',
				'title'    => __( 'Capture Delay', 'checkout-com-unified-payments-api' ),
				'type'     => 'text',
				'desc'     => __( 'The delay in hours (0 means immediately, 1.2 means one hour and 30 min)', 'checkout-com-unified-payments-api' ),
				'desc_tip' => true,
			],
			'ckocom_card_threed'                    => [
				'id'       => 'ckocom_card_threed',
				'title'    => __( 'Use 3D Secure', 'checkout-com-unified-payments-api' ),
				'type'     => 'select',
				'desc_tip' => true,
				'options'  => [
					0 => __( 'No', 'checkout-com-unified-payments-api' ),
					1 => __( 'Yes', 'checkout-com-unified-payments-api' ),
				],
				'default'  => 0,
				'desc'     => '3D secure payment',
			],
			'ckocom_card_notheed'                   => [
				'id'       => 'ckocom_card_notheed',
				'title'    => __( 'Attempt non-3D Secure', 'checkout-com-unified-payments-api' ),
				'type'     => 'select',
				'desc_tip' => true,
				'options'  => [
					0 => __( 'No', 'checkout-com-unified-payments-api' ),
					1 => __( 'Yes', 'checkout-com-unified-payments-api' ),
				],
				'default'  => 0,
				'desc'     => 'Attempt non-3D Secure payment',
			],
			'ckocom_card_saved'                     => [
				'id'       => 'ckocom_card_saved',
				'title'    => __( 'Enable Save Cards', 'checkout-com-unified-payments-api' ),
				'type'     => 'select',
				'desc_tip' => true,
				'options'  => [
					0 => __( 'No', 'checkout-com-unified-payments-api' ),
					1 => __( 'Yes', 'checkout-com-unified-payments-api' ),
				],
				'default'  => 0,
				'desc'     => 'Allow customers to save cards for future payments',
			],
			'ckocom_card_require_cvv'               => [
				'id'       => 'ckocom_card_require_cvv',
				'title'    => __( 'Require CVV For Saved Cards', 'checkout-com-unified-payments-api' ),
				'type'     => 'select',
				'desc_tip' => true,
				'options'  => [
					0 => __( 'No', 'checkout-com-unified-payments-api' ),
					1 => __( 'Yes', 'checkout-com-unified-payments-api' ),
				],
				'default'  => 0,
				'desc'     => 'Allow customers to save cards for future payments',
			],
			'ckocom_card_desctiptor'                => [
				'id'       => 'ckocom_card_desctiptor',
				'title'    => __( 'Enable Dynamic Descriptor', 'checkout-com-unified-payments-api' ),
				'type'     => 'select',
				'desc_tip' => true,
				'options'  => [
					0 => __( 'No', 'checkout-com-unified-payments-api' ),
					1 => __( 'Yes', 'checkout-com-unified-payments-api' ),
				],
				'default'  => 0,
				'desc'     => __( 'Dynamic Descriptor', 'checkout-com-unified-payments-api' ),
			],
			'ckocom_card_desctiptor_name'           => [
				'id'       => 'ckocom_card_desctiptor_name',
				'title'    => __( 'Descriptor Name', 'checkout-com-unified-payments-api' ),
				'type'     => 'text',
				'desc'     => __( 'Maximum 25 characters)', 'checkout-com-unified-payments-api' ),
				'desc_tip' => true,
			],
			'ckocom_card_desctiptor_city'           => [
				'id'       => 'ckocom_card_desctiptor_city',
				'title'    => __( 'Descriptor City', 'checkout-com-unified-payments-api' ),
				'type'     => 'text',
				'desc'     => __( 'Maximum 13 characters)', 'checkout-com-unified-payments-api' ),
				'desc_tip' => true,
			],
			'ckocom_card_mada'                      => [
				'id'       => 'ckocom_card_mada',
				'title'    => __( 'Enable MADA Bin Check', 'checkout-com-unified-payments-api' ),
				'type'     => 'select',
				'desc_tip' => true,
				'options'  => [
					0 => __( 'No', 'checkout-com-unified-payments-api' ),
					1 => __( 'Yes', 'checkout-com-unified-payments-api' ),
				],
				'default'  => 0,
				'desc'     => __( 'For processing MADA transactions, this option needs to be set to Yes', 'checkout-com-unified-payments-api' ),
			],
			'ckocom_display_icon'                   => [
				'id'       => 'ckocom_display_icon',
				'title'    => __( 'Display Card Icons', 'checkout-com-unified-payments-api' ),
				'type'     => 'select',
				'desc_tip' => true,
				'options'  => [
					0 => __( 'No', 'checkout-com-unified-payments-api' ),
					1 => __( 'Yes', 'checkout-com-unified-payments-api' ),
				],
				'default'  => 0,
				'desc'     => 'Enable/disable cards icon on checkout page',
			],
			'ckocom_card_icons'                     => [
				'id'      => 'ckocom_card_icons',
				'title'   => __( 'Card Icons', 'checkout-com-unified-payments-api' ),
				'type'    => 'multiselect',
				'options' => [
					'visa'            => __( 'Visa', 'checkout-com-unified-payments-api' ),
					'mastercard'      => __( 'Mastercard', 'checkout-com-unified-payments-api' ),
					'amex'            => __( 'American Express', 'checkout-com-unified-payments-api' ),
					'dinersclub'      => __( 'Diners Club International', 'checkout-com-unified-payments-api' ),
					'discover'        => __( 'Discover', 'checkout-com-unified-payments-api' ),
					'jcb'             => __( 'JCB', 'checkout-com-unified-payments-api' ),
					'cartesbancaires' => __( 'Cartes Bancaires', 'checkout-com-unified-payments-api' ),
					'mada'            => __( 'Mada', 'checkout-com-unified-payments-api' ),
				],
				'class'   => 'wc-enhanced-select',
				'css'     => 'width: 400px;',
			],
			'ckocom_language_type'                  => [
				'id'       => 'ckocom_language_type',
				'title'    => __( 'Language Support', 'checkout-com-unified-payments-api' ),
				'type'     => 'select',
				'desc_tip' => true,
				'options'  => [
					0 => __( 'Predefined Translation', 'checkout-com-unified-payments-api' ),
					1 => __( 'Custom Translation', 'checkout-com-unified-payments-api' ),
				],
				'default'  => 0,
				'desc'     => 'Select a translation type for card input fields',
			],
			'ckocom_language_fallback'              => [
				'id'       => 'ckocom_language_fallback',
				'title'    => __( 'Language Fallback', 'checkout-com-unified-payments-api' ),
				'type'     => 'select',
				'desc_tip' => true,
				'options'  => [
					'EN-GB' => __( 'English', 'checkout-com-unified-payments-api' ),
					'NL-NL' => __( 'Dutch', 'checkout-com-unified-payments-api' ),
					'FR-FR' => __( 'French', 'checkout-com-unified-payments-api' ),
					'DE-DE' => __( 'German', 'checkout-com-unified-payments-api' ),
					'IT-IT' => __( 'Italian', 'checkout-com-unified-payments-api' ),
					'KR-KR' => __( 'Korean', 'checkout-com-unified-payments-api' ),
					'ES-ES' => __( 'Spanish', 'checkout-com-unified-payments-api' ),
				],
				'default'  => 'EN-GB',
				'desc'     => 'Select the language to use by default if the one used by the shopper is not supported by the integration.',
			],
			'ckocom_card_number_placeholder'        => [
				'id'       => 'ckocom_card_number_placeholder',
				'title'    => __( 'Card Number Placeholder', 'checkout-com-unified-payments-api' ),
				'type'     => 'text',
				'default'  => 'Card number',
				'desc'     => __( 'Card number input box placeholder.', 'checkout-com-unified-payments-api' ),
				'desc_tip' => true,
			],
			'ckocom_card_expiry_month_placeholder'  => [
				'id'       => 'ckocom_card_expiry_month_placeholder',
				'title'    => __( 'Card Expiry Month Placeholder', 'checkout-com-unified-payments-api' ),
				'type'     => 'text',
				'default'  => 'MM',
				'desc'     => __( 'Card expiry month input box placeholder.', 'checkout-com-unified-payments-api' ),
				'desc_tip' => true,
			],
			'ckocom_card_expiry_year_placeholder'   => [
				'id'       => 'ckocom_card_expiry_year_placeholder',
				'title'    => __( 'Card Expiry Year Placeholder', 'checkout-com-unified-payments-api' ),
				'type'     => 'text',
				'default'  => 'YY',
				'desc'     => __( 'Card expiry year input box placeholder.', 'checkout-com-unified-payments-api' ),
				'desc_tip' => true,
			],
			'ckocom_card_cvv_placeholder'           => [
				'id'       => 'ckocom_card_cvv_placeholder',
				'title'    => __( 'Card CVV Placeholder', 'checkout-com-unified-payments-api' ),
				'type'     => 'text',
				'default'  => 'CVV',
				'desc'     => __( 'Card CVV input box placeholder.', 'checkout-com-unified-payments-api' ),
				'desc_tip' => true,
			],
			'ckocom_card_scheme_link_placeholder'   => [
				'id'       => 'ckocom_card_scheme_link_placeholder',
				'title'    => __( 'Card Scheme Link Placeholder', 'checkout-com-unified-payments-api' ),
				'type'     => 'text',
				'default'  => 'Click here to update your type of card',
				'desc'     => __( 'Card Scheme Link input box placeholder.', 'checkout-com-unified-payments-api' ),
				'desc_tip' => true,
			],
			'ckocom_card_scheme_header_placeholder' => [
				'id'       => 'ckocom_card_scheme_header_placeholder',
				'title'    => __( 'Card Scheme Header Placeholder', 'checkout-com-unified-payments-api' ),
				'type'     => 'text',
				'default'  => 'Choose your type of card',
				'desc'     => __( 'Card Scheme Header input box placeholder.', 'checkout-com-unified-payments-api' ),
				'desc_tip' => true,
			],
			'ckocom_iframe_style'                   => [
				'id'       => 'ckocom_iframe_style',
				'title'    => __( 'Iframe Style', 'checkout-com-unified-payments-api' ),
				'type'     => 'select',
				'desc_tip' => true,
				'options'  => [
					0 => __( 'Single Iframe', 'checkout-com-unified-payments-api' ),
					1 => __( 'Multiple Iframe', 'checkout-com-unified-payments-api' ),
				],
				'default'  => 0,
				'desc'     => 'Select the styling for card iframe',
			],
		];

		return apply_filters( 'wc_checkout_com_cards', $settings );
	}

	/**
	 * CKO admin order management settings fields
	 *
	 * @return mixed
	 */
	public static function order_settings() {

		$settings = [
			'order_setting'           => [
				'title'       => __( 'Order Management settings', 'checkout-com-unified-payments-api' ),
				'type'        => 'title',
				'description' => '',
			],
			'ckocom_order_authorised' => [
				'id'       => 'ckocom_order_authorised',
				'title'    => __( 'Authorised Order Status', 'checkout-com-unified-payments-api' ),
				'type'     => 'select',
				'desc_tip' => true,
				'options'  => wc_get_order_statuses(),
				'default'  => 'wc-on-hold',
				'desc'     => __( 'Select the status that should be used for orders with successful payment authorisation', 'checkout-com-unified-payments-api' ),
			],
			'ckocom_order_captured'   => [
				'id'       => 'ckocom_order_captured',
				'title'    => __( 'Captured Order Status', 'checkout-com-unified-payments-api' ),
				'type'     => 'select',
				'desc_tip' => true,
				'options'  => wc_get_order_statuses(),
				'default'  => 'wc-processing',
				'desc'     => __( 'Select the status that should be used for orders with successful payment capture', 'checkout-com-unified-payments-api' ),
			],
			'ckocom_order_void'       => [
				'id'       => 'ckocom_order_void',
				'title'    => __( 'Void Order Status', 'checkout-com-unified-payments-api' ),
				'type'     => 'select',
				'desc_tip' => true,
				'options'  => wc_get_order_statuses(),
				'default'  => 'wc-cancelled',
				'desc'     => __( 'Select the status that should be used for orders that have been voided', 'checkout-com-unified-payments-api' ),
			],
			'ckocom_order_flagged'    => [
				'id'       => 'ckocom_order_flagged',
				'title'    => __( 'Flagged Order Status', 'checkout-com-unified-payments-api' ),
				'type'     => 'select',
				'desc_tip' => true,
				'options'  => wc_get_order_statuses(),
				'default'  => 'wc-flagged',
				'desc'     => __( 'Select the status that should be used for flagged orders', 'checkout-com-unified-payments-api' ),
			],
			'ckocom_order_refunded'   => [
				'id'       => 'ckocom_order_refunded',
				'title'    => __( 'Refunded Order Status', 'checkout-com-unified-payments-api' ),
				'type'     => 'select',
				'desc_tip' => true,
				'options'  => wc_get_order_statuses(),
				'default'  => 'wc-refunded',
				'desc'     => __( 'Select the status that should be used for new orders with successful payment refund', 'checkout-com-unified-payments-api' ),
			],
		];

		return apply_filters( 'wc_checkout_com_cards', $settings );
	}

	/**
	 * CKO admin apple pay settting fields
	 *
	 * @return mixed|void
	 */
	public static function apple_settings() {
		$core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings' );
		$nas_docs      = 'https://www.checkout.com/docs/four/payments/payment-methods/apple-pay';
		$abc_docs      = 'https://www.checkout.com/docs/payments/payment-methods/wallets/apple-pay';
		$docs_link     = $abc_docs;

		if ( isset( $core_settings['ckocom_account_type'] ) && 'NAS' === $core_settings['ckocom_account_type'] ) {
			$docs_link = $nas_docs;
		}

		$settings = [
			'core_setting'             => [
				'title'       => __( 'Apple Pay settings', 'checkout-com-unified-payments-api' ),
				'type'        => 'title',
				'description' => '',
			],
			'enabled'                  => [
				'id'          => 'enable',
				'title'       => __( 'Enable/Disable', 'checkout-com-unified-payments-api' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable Checkout.com', 'checkout-com-unified-payments-api' ),
				'description' => __( 'This enables Checkout.com. cards payment', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => true,
				'default'     => 'yes',
			],
			'title'                    => [
				'title'       => __( 'Title', 'checkout-com-unified-payments-api' ),
				'type'        => 'text',
				'label'       => __( 'Card payment title', 'checkout-com-unified-payments-api' ),
				'description' => __( 'Title that will be displayed on the checkout page', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => true,
				'default'     => 'Core settings',
			],
			'description'              => [
				'title'       => __( 'Description', 'checkout-com-unified-payments-api' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout.', 'checkout-com-unified-payments-api' ),
				'default'     => 'Pay with Apple Pay.',
				'desc_tip'    => true,
			],
			'ckocom_apple_mercahnt_id' => [
				'title'       => __( 'Merchant Identifier', 'checkout-com-unified-payments-api' ),
				'type'        => 'text',
				/* translators: 1: HTML anchor opening tag, 2: HTML anchor closing tag. */
				'description' => sprintf( __( 'You can find this in your developer portal, or to generate one follow this %1$s guide %2$s', 'checkout-com-unified-payments-api' ), '<a target="_blank" href="' . esc_url( $docs_link ) . '">', '</a>' ),
				'default'     => '',
			],
			'ckocom_apple_certificate' => [
				'title'       => __( 'Merchant Certificate', 'checkout-com-unified-payments-api' ),
				'type'        => 'text',
				'description' => __( 'The absolute path to your .pem certificate.', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => true,
				'default'     => '',
			],
			'ckocom_apple_key'         => [
				'title'       => __( 'Merchant Certificate Key', 'checkout-com-unified-payments-api' ),
				'type'        => 'text',
				'description' => __( 'The absolute path to your .key certificate key.', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => true,
				'default'     => '',
			],
			'ckocom_apple_type'        => [
				'title'   => __( 'Button Type', 'checkout-com-unified-payments-api' ),
				'type'    => 'select',
				'options' => [
					'apple-pay-button-text-buy'       => __( 'Buy', 'checkout-com-unified-payments-api' ),
					'apple-pay-button-text-check-out' => __( 'Checkout out', 'checkout-com-unified-payments-api' ),
					'apple-pay-button-text-book'      => __( 'Book', 'checkout-com-unified-payments-api' ),
					'apple-pay-button-text-donate'    => __( 'Donate', 'checkout-com-unified-payments-api' ),
					'apple-pay-button'                => __( 'Plain', 'checkout-com-unified-payments-api' ),
				],
			],
			'ckocom_apple_theme'       => [
				'title'   => __( 'Button Theme', 'checkout-com-unified-payments-api' ),
				'type'    => 'select',
				'options' => [
					'apple-pay-button-black-with-text' => __( 'Black', 'checkout-com-unified-payments-api' ),
					'apple-pay-button-white-with-text' => __( 'White', 'checkout-com-unified-payments-api' ),
					'apple-pay-button-white-with-line-with-text' => __( 'White with outline', 'checkout-com-unified-payments-api' ),
				],
			],
			'ckocom_apple_language'    => [
				'title'       => __( 'Button Language', 'checkout-com-unified-payments-api' ),
				'type'        => 'text',
				/* translators: 1: HTML anchor opening tag, 2: HTML anchor closing tag. */
				'description' => sprintf( __( 'ISO 639-1 value of the language. See supported languages %1$s here. %2$s', 'checkout-com-unified-payments-api' ), '<a href="https://applepaydemo.apple.com/" target="_blank">', '</a>' ),
				'default'     => '',
			],
			'enable_mada'              => [
				'id'          => 'enable_mada_apple_pay',
				'title'       => __( 'Enable MADA', 'checkout-com-unified-payments-api' ),
				'type'        => 'checkbox',
				'desc_tip'    => true,
				'default'     => 'no',
				'description' => __( 'Please enable if entity is in Saudi Arabia', 'checkout-com-unified-payments-api' ),
			],
		];

		return apply_filters( 'wc_checkout_com_apple_pay', $settings );
	}

	/**
	 * CKO admin google pay setting fields
	 *
	 * @return mixed|void
	 */
	public static function google_settings() {
		$settings = [
			'google_setting'            => [
				'title'       => __( 'Google Pay Settings', 'checkout-com-unified-payments-api' ),
				'type'        => 'title',
				'description' => '',
			],
			'enabled'                   => [
				'id'          => 'enable',
				'title'       => __( 'Enable/Disable', 'checkout-com-unified-payments-api' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable Checkout.com', 'checkout-com-unified-payments-api' ),
				'description' => __( 'This enables google pay as a payment method', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => true,
				'default'     => 'no',
			],
			'title'                     => [
				'title'       => __( 'Title', 'checkout-com-unified-payments-api' ),
				'type'        => 'text',
				'label'       => __( 'Google Pay', 'checkout-com-unified-payments-api' ),
				'description' => __( 'Title that will be displayed on the checkout page', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => true,
				'default'     => 'Google Pay',
			],
			'description'               => [
				'title'       => __( 'Description', 'checkout-com-unified-payments-api' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout.', 'checkout-com-unified-payments-api' ),
				'default'     => 'Pay with Google Pay.',
				'desc_tip'    => true,
			],
			'ckocom_google_merchant_id' => [
				'title'       => __( 'Merchant Identifier', 'checkout-com-unified-payments-api' ),
				'type'        => 'text',
				'description' => __( 'Your production merchant identifier.', 'checkout-com-unified-payments-api' ) . '<br>' . __( 'For testing use the following value: 01234567890123456789', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => false,
				'default'     => '01234567890123456789',
			],
			'ckocom_google_threed'      => [
				'id'          => 'ckocom_google_threed',
				'title'       => __( 'Use 3D Secure', 'checkout-com-unified-payments-api' ),
				'type'        => 'select',
				'desc_tip'    => true,
				'options'     => [
					0 => __( 'No', 'checkout-com-unified-payments-api' ),
					1 => __( 'Yes', 'checkout-com-unified-payments-api' ),
				],
				'default'     => 0,
				'description' => '3D secure payment',
			],
			'ckocom_google_style'       => [
				'title'       => __( 'Button Style', 'checkout-com-unified-payments-api' ),
				'type'        => 'select',
				'class'       => 'wc-enhanced-select',
				'description' => __( 'Select button color.', 'checkout-com-unified-payments-api' ),
				'default'     => 'google-pay-black',
				'desc_tip'    => true,
				'options'     => [
					'google-pay-black' => __( 'Black', 'checkout-com-unified-payments-api' ),
					'google-pay-white' => __( 'White', 'checkout-com-unified-payments-api' ),
				],
			],
		];

		return apply_filters( 'wc_checkout_com_google_pay', $settings );
	}

	/**
	 * CKO admin paypal setting fields
	 *
	 * @return mixed|void
	 */
	public static function paypal_settings() {
		$settings = [
			'google_setting'            => [
				'title'       => __( 'PayPal Settings', 'checkout-com-unified-payments-api' ),
				'type'        => 'title',
				'description' => '',
			],
			'enabled'                   => [
				'id'          => 'enable',
				'title'       => __( 'Enable/Disable', 'checkout-com-unified-payments-api' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable Checkout.com', 'checkout-com-unified-payments-api' ),
				'description' => __( 'This enables PayPal as a payment method', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => true,
				'default'     => 'no',
			],
			'title'                     => [
				'title'       => __( 'Title', 'checkout-com-unified-payments-api' ),
				'type'        => 'text',
				'label'       => __( 'PayPal', 'checkout-com-unified-payments-api' ),
				'description' => __( 'Title that will be displayed on the checkout page', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => true,
				'default'     => 'PayPal',
			],
			'description'               => [
				'title'       => __( 'Description', 'checkout-com-unified-payments-api' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout.', 'checkout-com-unified-payments-api' ),
				'default'     => 'Pay with PayPal.',
				'desc_tip'    => true,
			],
			'ckocom_paypal_merchant_id' => [
				'title'       => __( 'Merchant ID', 'checkout-com-unified-payments-api' ),
				'type'        => 'text',
				'description' => __( 'Your Paypal merchant ID.', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => false,
				'default'     => '',
				'placeholder' => 'ABCD1EFGH2I3K',
			],
			'paypal_express' => [
				'title'       => __( 'PayPal Express', 'checkout-com-unified-payments-api' ),
				'label'       => __( 'Enable PayPal Express', 'checkout-com-unified-payments-api' ),
				'type'        => 'checkbox',
				'description' => __( 'Toggle to activate PayPal Express checkout for smoother checkout.', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => false,
				'default'     => 'no',
			],
		];

		return apply_filters( 'wc_checkout_com_paypal', $settings );
	}

	/**
	 * Alternative payment methods settings fields.
	 *
	 * @return mixed
	 */
	public static function apm_settings() {
		$settings = [
			'apm_setting'          => [
				'title'       => __( 'Alternative Payment Settings', 'checkout-com-unified-payments-api' ),
				'type'        => 'title',
				'description' => '',
			],
			'enabled'              => [
				'id'          => 'enable',
				'title'       => __( 'Enable/Disable', 'checkout-com-unified-payments-api' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable Checkout.com', 'checkout-com-unified-payments-api' ),
				'description' => __( 'This enables alternative payment methods', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => true,
				'default'     => 'no',
			],
			'title'                => [
				'title'       => __( 'Title', 'checkout-com-unified-payments-api' ),
				'type'        => 'text',
				'label'       => __( 'Alternative Payments', 'checkout-com-unified-payments-api' ),
				'description' => __( 'Title that will be displayed on the checkout page', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => true,
				'default'     => 'Alternative Payment Methods',
			],
			'ckocom_apms_selector' => [
				'title'   => __( 'Alternative Payment Methods', 'checkout-com-unified-payments-api' ),
				'type'    => 'multiselect',
				'options' => [
					'alipay'     => __( 'Alipay', 'checkout-com-unified-payments-api' ),
					'boleto'     => __( 'Boleto', 'checkout-com-unified-payments-api' ),
					'giropay'    => __( 'Giropay', 'checkout-com-unified-payments-api' ),
					'ideal'      => __( 'iDEAL', 'checkout-com-unified-payments-api' ),
					'klarna'     => __( 'Klarna', 'checkout-com-unified-payments-api' ),
					'poli'       => __( 'Poli', 'checkout-com-unified-payments-api' ),
					'sepa'       => __( 'Sepa Direct Debit', 'checkout-com-unified-payments-api' ),
					'sofort'     => __( 'Sofort', 'checkout-com-unified-payments-api' ),
					'eps'        => __( 'EPS', 'checkout-com-unified-payments-api' ),
					'bancontact' => __( 'Bancontact', 'checkout-com-unified-payments-api' ),
					'knet'       => __( 'KNET', 'checkout-com-unified-payments-api' ),
					'fawry'      => __( 'Fawry', 'checkout-com-unified-payments-api' ),
					'qpay'       => __( 'QPay', 'checkout-com-unified-payments-api' ),
					'multibanco' => __( 'Multibanco', 'checkout-com-unified-payments-api' ),
				],
				'class'   => 'wc-enhanced-select',
				'css'     => 'width: 400px;',
			],

		];

		return apply_filters( 'wc_checkout_com_alternative_payments', $settings );
	}

	/**
	 * Debugging settings.
	 *
	 * @return mixed
	 */
	public static function debug_settings() {
		$settings = [
			'debug_settings'        => [
				'title'       => __( 'Debug Settings', 'checkout-com-unified-payments-api' ),
				'type'        => 'title',
				'description' => '',
			],
			'cko_file_logging'      => [
				'id'       => 'cko_file_logging',
				'title'    => __( 'File Logging', 'checkout-com-unified-payments-api' ),
				'type'     => 'checkbox',
				'desc_tip' => true,
				'default'  => 'no',
				'desc'     => __( 'Check to enable file logging', 'checkout-com-unified-payments-api' ),
			],
			'cko_console_logging'   => [
				'id'       => 'cko_console_logging',
				'title'    => __( 'Console Logging', 'checkout-com-unified-payments-api' ),
				'type'     => 'checkbox',
				'desc_tip' => true,
				'default'  => 'no',
				'desc'     => __( 'Check to enable console logging', 'checkout-com-unified-payments-api' ),
			],
			'cko_gateway_responses' => [
				'id'       => 'cko_gateway_responses',
				'title'    => __( 'Gateway Responses', 'checkout-com-unified-payments-api' ),
				'type'     => 'checkbox',
				'desc_tip' => true,
				'default'  => 'no',
				'desc'     => __( 'Check to show gateway response.', 'checkout-com-unified-payments-api' ),
			],
		];

		return apply_filters( 'wc_checkout_com_cards', $settings );
	}

	/**
	 * CKO webhook settings fields.
	 *
	 * @return mixed
	 */
	public static function webhook_settings() {

		$settings = [
			'webhook_settings' => [
				'title'       => __( 'Webhook Details', 'checkout-com-unified-payments-api' ),
				'type'        => 'title',
				'description' => '',
			],
			'cko_webhook_set'  => [
				'id'   => 'cko_webhook_set',
				'type' => 'checkoutcom_webhook_settings',
			],
		];

		return apply_filters( 'wc_checkout_com_cards', $settings );
	}
}
