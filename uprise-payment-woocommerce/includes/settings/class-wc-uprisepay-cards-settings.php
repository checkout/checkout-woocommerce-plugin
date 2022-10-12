<?php
/**
 * Card payment method settings class.
 *
 * @package wc_uprise_payment
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
		add_action( 'woocommerce_admin_field_uprisepay_webhook_settings', [ $this, 'uprisepay_cards_settings_html' ] );
	}

	/**
	 * Custom markup for webhook settings.
	 *
	 * @param array $value Admin field information.
	 *
	 * @return void
	 */
	public function uprisepay_cards_settings_html( $value ) {

		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<?php esc_html_e( 'Webhook Status', 'uprise-payment-woocommerce' ); ?>
			</th>
			<td class="forminp forminp-uprisepay_webhook_settings">
				<p>
					<button type="button" class="button button-primary" id="uprisepay-is-register-webhook"><?php esc_html_e( 'Run Webhook check', 'uprise-payment-woocommerce' ); ?></button>
					<span class="dashicons dashicons-yes hidden" style="font-size: 30px;height: 30px;width: 30px;color: #008000;"></span>
					<span class="spinner" style="float: none;"></span>
					<p><?php esc_html_e( 'This action will check if webhook is configured for current site.', 'uprise-payment-woocommerce' ); ?></p>
				</p>
				<p class="uprisepay-is-register-webhook-text"></p>
			</td>
		</tr>

		<tr valign="top" class="uprisepay-new-webhook-setting">
			<th scope="row" class="titledesc">
				<?php esc_html_e( 'Register New Webhook', 'uprise-payment-woocommerce' ); ?>
			</th>
			<td class="forminp forminp-uprisepay_webhook_settings">
				<p>
					<button type="button" class="button button-primary" id="uprisepay-register-webhook"><?php esc_html_e( 'Register Webhook', 'uprise-payment-woocommerce' ); ?></button>
					<span class="dashicons dashicons-yes hidden" style="font-size: 30px;height: 30px;width: 30px;color: #008000;"></span>
					<span class="spinner" style="float: none;"></span>
				</p>
				<?php
				printf(
					'<p style="margin-top: 10px;">%s</p><br><code>%s</code><div class="cko-ajax-data"></div>',
					esc_html__( 'Click above button to register webhook URL', 'uprise-payment-woocommerce' ),
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
		$core_settings = get_option( 'woocommerce_wc_uprise_payment_cards_settings' );
		$nas_docs      = 'https://docs.uprisepay.com/';
		$abc_docs      = 'https://docs.uprisepay.com/';
		$docs_link     = $abc_docs;

		if ( isset( $core_settings['upycom_account_type'] ) && 'NAS' === $core_settings['upycom_account_type'] ) {
			$docs_link = $nas_docs;
		}

		$settings = [
			'core_setting'        => [
				'title'       => __( 'Core settings', 'uprise-payment-woocommerce' ),
				'type'        => 'title',
				'description' => '',
			],
			'enabled'             => [
				'id'          => 'enable',
				'title'       => __( 'Enable/Disable', 'uprise-payment-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable Uprise Payment cards payment', 'uprise-payment-woocommerce' ),
				'description' => __( 'This enables Uprise cards payment', 'uprise-payment-woocommerce' ),
				'desc_tip'    => true,
				'default'     => 'yes',
			],
			'upycom_environment'  => [
//				'title'       => __( 'Environment', 'uprise-payment-woocommerce' ),
//				'type'        => 'select',
//				'description' => __( 'When going to production, make sure to set this to Live', 'uprise-payment-woocommerce' ),
//				'desc_tip'    => true,
//				'options'     => [
//					'sandbox' => __( 'SandBox', 'uprise-payment-woocommerce' ),
//					'live'    => __( 'Live', 'uprise-payment-woocommerce' ),
//				],
                'type'        => 'title',
				'default'     => 'live',
			],
            'upycom_account_type' => [
//                'title'       => __( 'Account type', 'uprise-payment-woocommerce' ),
//                'type'        => 'select',
//                'description' => __( 'Contact support team to know your account type.', 'uprise-payment-woocommerce' ),
//                'desc_tip'    => true,
//                'options'     => [
                    'ABC' => __( 'ABC', 'uprise-payment-woocommerce' ),
//					'NAS' => __( 'NAS', 'uprise-payment-woocommerce' ),
//                ],
                'type'        => 'title',
                'default'     => 'ABC',
            ],
			'title'               => [
				'title'       => __( 'Payment Option Title', 'uprise-payment-woocommerce' ),
				'type'        => 'text',
				'label'       => __( 'Pay by Card with Uprise', 'uprise-payment-woocommerce' ),
				'description' => __( 'Title that will be displayed on the checkout page', 'uprise-payment-woocommerce' ),
				'desc_tip'    => true,
				'default'     => 'VISA/MasterCard',
			],
			'upycom_sk'           => [
				'title'       => __( 'Secret Key', 'uprise-payment-woocommerce' ),
				'type'        => 'text',
				/* translators: 1: HTML anchor opening tag, 2: HTML anchor closing tag. */
				'description' => sprintf( __( 'You can %1$s find your secret key %2$s in the Uprise Payment portal', 'uprise-payment-woocommerce' ), '<a class="uprisepay-key-docs" target="_blank" href="' . esc_url( $docs_link ) . '">', '</a>' ),
				'placeholder' => 'sk_xxx',
			],
			'upycom_pk'           => [
				'title'       => __( 'Public Key', 'uprise-payment-woocommerce' ),
				'type'        => 'text',
				/* translators: 1: HTML anchor opening tag, 2: HTML anchor closing tag. */
				'description' => sprintf( __( 'You can %1$s find your public key %2$s in the Uprise Payment portal', 'uprise-payment-woocommerce' ), '<a class="uprisepay-key-docs" target="_blank" href="' . esc_url( $docs_link ) . '">', '</a>' ),
				'placeholder' => 'pk_xxx',
			],
		];

		return apply_filters( 'wc_uprise_payment_cards', $settings );
	}

	/**
	 * CKO admin card setting fields
	 *
	 * @return mixed|void
	 */
	public static function cards_settings() {

		$settings = [
			'card_setting'                => [
				'title'       => __( 'Card settings', 'uprise-payment-woocommerce' ),
				'type'        => 'title',
				'description' => '',
			],
			'upycom_card_autocap'         => [
				'id'       => 'upycom_card_autocap',
				'title'    => __( 'Payment Action', 'uprise-payment-woocommerce' ),
				'type'     => 'select',
				'desc_tip' => true,
				'options'  => [
					0 => __( 'Authorize only', 'uprise-payment-woocommerce' ),
					1 => __( 'Authorize and Capture', 'uprise-payment-woocommerce' ),
				],
				'default'  => 1,
				'desc'     => 'Set this to Authorise only if you want to manually capture the payment.',
			],
			'upycom_card_cap_delay'       => [
				'id'       => 'upycom_card_cap_delay',
				'title'    => __( 'Capture Delay', 'uprise-payment-woocommerce' ),
				'type'     => 'text',
				'desc'     => __( 'The delay in hours (0 means immediately, 1.2 means one hour and 30 min)', 'uprise-payment-woocommerce' ),
				'desc_tip' => true,
			],
			'upycom_card_threed'          => [
				'id'       => 'upycom_card_threed',
				'title'    => __( 'Use 3D Secure', 'uprise-payment-woocommerce' ),
				'type'     => 'select',
				'desc_tip' => true,
				'options'  => [
					0 => __( 'No', 'uprise-payment-woocommerce' ),
					1 => __( 'Yes', 'uprise-payment-woocommerce' ),
				],
				'default'  => 0,
				'desc'     => '3D secure payment',
			],
			'upycom_card_notheed'         => [
				'id'       => 'upycom_card_notheed',
				'title'    => __( 'Attempt non-3D Secure', 'uprise-payment-woocommerce' ),
				'type'     => 'select',
				'desc_tip' => true,
				'options'  => [
					0 => __( 'No', 'uprise-payment-woocommerce' ),
					1 => __( 'Yes', 'uprise-payment-woocommerce' ),
				],
				'default'  => 0,
				'desc'     => 'Attempt non-3D Secure payment',
			],
			'upycom_card_saved'           => [
				'id'       => 'upycom_card_saved',
				'title'    => __( 'Enable Save Cards', 'uprise-payment-woocommerce' ),
				'type'     => 'select',
				'desc_tip' => true,
				'options'  => [
					0 => __( 'No', 'uprise-payment-woocommerce' ),
					1 => __( 'Yes', 'uprise-payment-woocommerce' ),
				],
				'default'  => 0,
				'desc'     => 'Allow customers to save cards for future payments',
			],
			'upycom_card_require_cvv'     => [
				'id'       => 'upycom_card_require_cvv',
				'title'    => __( 'Require CVV For Saved Cards', 'uprise-payment-woocommerce' ),
				'type'     => 'select',
				'desc_tip' => true,
				'options'  => [
					0 => __( 'No', 'uprise-payment-woocommerce' ),
					1 => __( 'Yes', 'uprise-payment-woocommerce' ),
				],
				'default'  => 0,
				'desc'     => 'Allow customers to save cards for future payments',
			],
			'upycom_card_desctiptor'      => [
				'id'       => 'upycom_card_desctiptor',
				'title'    => __( 'Enable Dynamic Descriptor', 'uprise-payment-woocommerce' ),
				'type'     => 'select',
				'desc_tip' => true,
				'options'  => [
					0 => __( 'No', 'uprise-payment-woocommerce' ),
					1 => __( 'Yes', 'uprise-payment-woocommerce' ),
				],
				'default'  => 0,
				'desc'     => __( 'Dynamic Descriptor', 'uprise-payment-woocommerce' ),
			],
			'upycom_card_desctiptor_name' => [
				'id'       => 'upycom_card_desctiptor_name',
				'title'    => __( 'Descriptor Name', 'uprise-payment-woocommerce' ),
				'type'     => 'text',
				'desc'     => __( 'Maximum 25 characters)', 'uprise-payment-woocommerce' ),
				'desc_tip' => true,
			],
			'upycom_card_desctiptor_city' => [
				'id'       => 'upycom_card_desctiptor_city',
				'title'    => __( 'Descriptor City', 'uprise-payment-woocommerce' ),
				'type'     => 'text',
				'desc'     => __( 'Maximum 13 characters)', 'uprise-payment-woocommerce' ),
				'desc_tip' => true,
			],
			'upycom_card_mada'            => [
				'id'       => 'upycom_card_mada',
				'title'    => __( 'Enable MADA Bin Check', 'uprise-payment-woocommerce' ),
				'type'     => 'select',
				'desc_tip' => true,
				'options'  => [
					0 => __( 'No', 'uprise-payment-woocommerce' ),
					1 => __( 'Yes', 'uprise-payment-woocommerce' ),
				],
				'default'  => 0,
				'desc'     => __( 'For processing MADA transactions, this option needs to be set to Yes', 'uprise-payment-woocommerce' ),
			],
			'upycom_display_icon'         => [
				'id'       => 'upycom_display_icon',
				'title'    => __( 'Display Card Icons', 'uprise-payment-woocommerce' ),
				'type'     => 'select',
				'desc_tip' => true,
				'options'  => [
					0 => __( 'No', 'uprise-payment-woocommerce' ),
					1 => __( 'Yes', 'uprise-payment-woocommerce' ),
				],
				'default'  => 0,
				'desc'     => 'Enable/disable cards icon on checkout page',
			],
			'upycom_card_icons'           => [
				'id'      => 'upycom_card_icons',
				'title'   => __( 'Card Icons', 'uprise-payment-woocommerce' ),
				'type'    => 'multiselect',
				'options' => [
					'visa'            => __( 'Visa', 'uprise-payment-woocommerce' ),
					'mastercard'      => __( 'Mastercard', 'uprise-payment-woocommerce' ),
					'amex'            => __( 'American Express', 'uprise-payment-woocommerce' ),
					'dinersclub'      => __( 'Diners Club International', 'uprise-payment-woocommerce' ),
					'discover'        => __( 'Discover', 'uprise-payment-woocommerce' ),
					'jcb'             => __( 'JCB', 'uprise-payment-woocommerce' ),
					'cartesbancaires' => __( 'Cartes Bancaires', 'uprise-payment-woocommerce' ),
				],
				'class'   => 'wc-enhanced-select',
				'css'     => 'width: 400px;',
			],
			'upycom_language_fallback'    => [
				'id'       => 'upycom_language_fallback',
				'title'    => __( 'Language Fallback', 'uprise-payment-woocommerce' ),
				'type'     => 'select',
				'desc_tip' => true,
				'options'  => [
					'EN-GB' => __( 'English', 'uprise-payment-woocommerce' ),
					'NL-NL' => __( 'Dutch', 'uprise-payment-woocommerce' ),
					'FR-FR' => __( 'French', 'uprise-payment-woocommerce' ),
					'DE-DE' => __( 'German', 'uprise-payment-woocommerce' ),
					'IT-IT' => __( 'Italian', 'uprise-payment-woocommerce' ),
					'KR-KR' => __( 'Korean', 'uprise-payment-woocommerce' ),
					'ES-ES' => __( 'Spanish', 'uprise-payment-woocommerce' ),
				],
				'default'  => 'EN-GB',
				'desc'     => 'Select the language to use by default if the one used by the shopper is not supported by the integration.',
			],
			'upycom_iframe_style'         => [
				'id'       => 'upycom_iframe_style',
				'title'    => __( 'Iframe Style', 'uprise-payment-woocommerce' ),
				'type'     => 'select',
				'desc_tip' => true,
				'options'  => [
					0 => __( 'Single Iframe', 'uprise-payment-woocommerce' ),
					1 => __( 'Multiple Iframe', 'uprise-payment-woocommerce' ),
				],
				'default'  => 0,
				'desc'     => 'Select the styling for card iframe',
			],
		];

		return apply_filters( 'wc_uprise_payment_cards', $settings );
	}

	/**
	 * CKO admin order management settings fields
	 *
	 * @return mixed
	 */
	public static function order_settings() {

		$settings = [
			'order_setting'           => [
				'title'       => __( 'Order Management settings', 'uprise-payment-woocommerce' ),
				'type'        => 'title',
				'description' => '',
			],
			'upycom_order_authorised' => [
				'id'       => 'upycom_order_authorised',
				'title'    => __( 'Authorised Order Status', 'uprise-payment-woocommerce' ),
				'type'     => 'select',
				'desc_tip' => true,
				'options'  => wc_get_order_statuses(),
				'default'  => 'wc-on-hold',
				'desc'     => __( 'Select the status that should be used for orders with successful payment authorisation', 'uprise-payment-woocommerce' ),
			],
			'upycom_order_captured'   => [
				'id'       => 'upycom_order_captured',
				'title'    => __( 'Captured Order Status', 'uprise-payment-woocommerce' ),
				'type'     => 'select',
				'desc_tip' => true,
				'options'  => wc_get_order_statuses(),
				'default'  => 'wc-processing',
				'desc'     => __( 'Select the status that should be used for orders with successful payment capture', 'uprise-payment-woocommerce' ),
			],
			'upycom_order_void'       => [
				'id'       => 'upycom_order_void',
				'title'    => __( 'Void Order Status', 'uprise-payment-woocommerce' ),
				'type'     => 'select',
				'desc_tip' => true,
				'options'  => wc_get_order_statuses(),
				'default'  => 'wc-cancelled',
				'desc'     => __( 'Select the status that should be used for orders that have been voided', 'uprise-payment-woocommerce' ),
			],
			'upycom_order_flagged'    => [
				'id'       => 'upycom_order_flagged',
				'title'    => __( 'Flagged Order Status', 'uprise-payment-woocommerce' ),
				'type'     => 'select',
				'desc_tip' => true,
				'options'  => wc_get_order_statuses(),
				'default'  => 'wc-flagged',
				'desc'     => __( 'Select the status that should be used for flagged orders', 'uprise-payment-woocommerce' ),
			],
			'upycom_order_refunded'   => [
				'id'       => 'upycom_order_refunded',
				'title'    => __( 'Refunded Order Status', 'uprise-payment-woocommerce' ),
				'type'     => 'select',
				'desc_tip' => true,
				'options'  => wc_get_order_statuses(),
				'default'  => 'wc-refunded',
				'desc'     => __( 'Select the status that should be used for new orders with successful payment refund', 'uprise-payment-woocommerce' ),
			],
		];

		return apply_filters( 'wc_uprise_payment_cards', $settings );
	}

	/**
	 * CKO admin apple pay settting fields
	 *
	 * @return mixed|void
	 */
	public static function apple_settings() {
		$core_settings = get_option( 'woocommerce_wc_uprise_payment_cards_settings' );
		$nas_docs      = 'https://docs.uprisepay.com/';
		$abc_docs      = 'https://docs.uprisepay.com/';
		$docs_link     = $abc_docs;

		if ( isset( $core_settings['upycom_account_type'] ) && 'NAS' === $core_settings['upycom_account_type'] ) {
			$docs_link = $nas_docs;
		}

		$settings = [
			'core_setting'             => [
				'title'       => __( 'Apple Pay settings', 'uprise-payment-woocommerce' ),
				'type'        => 'title',
				'description' => '',
			],
			'enabled'                  => [
				'id'          => 'enable',
				'title'       => __( 'Enable/Disable', 'uprise-payment-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable Uprise', 'uprise-payment-woocommerce' ),
				'description' => __( 'This enables Uprise cards payment', 'uprise-payment-woocommerce' ),
				'desc_tip'    => true,
				'default'     => 'yes',
			],
			'title'                    => [
				'title'       => __( 'Title', 'uprise-payment-woocommerce' ),
				'type'        => 'text',
				'label'       => __( 'Card payment title', 'uprise-payment-woocommerce' ),
				'description' => __( 'Title that will be displayed on the checkout page', 'uprise-payment-woocommerce' ),
				'desc_tip'    => true,
				'default'     => 'Core settings',
			],
			'description'              => [
				'title'       => __( 'Description', 'uprise-payment-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout.', 'uprise-payment-woocommerce' ),
				'default'     => 'Pay with Apple Pay.',
				'desc_tip'    => true,
			],
			'upycom_apple_mercahnt_id' => [
				'title'       => __( 'Merchant Identifier', 'uprise-payment-woocommerce' ),
				'type'        => 'text',
				/* translators: 1: HTML anchor opening tag, 2: HTML anchor closing tag. */
				'description' => sprintf( __( 'You can find this in your developer portal, or to generate one follow this %1$s guide %2$s', 'uprise-payment-woocommerce' ), '<a target="_blank" href="' . esc_url( $docs_link ) . '">', '</a>' ),
				'default'     => '',
			],
			'upycom_apple_certificate' => [
				'title'       => __( 'Merchant Certificate', 'uprise-payment-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'The absolute path to your .pem certificate.', 'uprise-payment-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
			],
			'upycom_apple_key'         => [
				'title'       => __( 'Merchant Certificate Key', 'uprise-payment-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'The absolute path to your .key certificate key.', 'uprise-payment-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
			],
			'upycom_apple_type'        => [
				'title'   => __( 'Button Type', 'uprise-payment-woocommerce' ),
				'type'    => 'select',
				'options' => [
					'apple-pay-button-text-buy'       => __( 'Buy', 'uprise-payment-woocommerce' ),
					'apple-pay-button-text-check-out' => __( 'Checkout out', 'uprise-payment-woocommerce' ),
					'apple-pay-button-text-book'      => __( 'Book', 'uprise-payment-woocommerce' ),
					'apple-pay-button-text-donate'    => __( 'Donate', 'uprise-payment-woocommerce' ),
					'apple-pay-button'                => __( 'Plain', 'uprise-payment-woocommerce' ),
				],
			],
			'upycom_apple_theme'       => [
				'title'   => __( 'Button Theme', 'uprise-payment-woocommerce' ),
				'type'    => 'select',
				'options' => [
					'apple-pay-button-black-with-text' => __( 'Black', 'uprise-payment-woocommerce' ),
					'apple-pay-button-white-with-text' => __( 'White', 'uprise-payment-woocommerce' ),
					'apple-pay-button-white-with-line-with-text' => __( 'White with outline', 'uprise-payment-woocommerce' ),
				],
			],
			'upycom_apple_language'    => [
				'title'       => __( 'Button Language', 'uprise-payment-woocommerce' ),
				'type'        => 'text',
				/* translators: 1: HTML anchor opening tag, 2: HTML anchor closing tag. */
				'description' => sprintf( __( 'ISO 639-1 value of the language. See supported languages %1$s here. %2$s', 'uprise-payment-woocommerce' ), '<a href="https://applepaydemo.apple.com/" target="_blank">', '</a>' ),
				'default'     => '',
			],
			'enable_mada'              => [
				'id'          => 'enable_mada_apple_pay',
				'title'       => __( 'Enable MADA', 'uprise-payment-woocommerce' ),
				'type'        => 'checkbox',
				'desc_tip'    => true,
				'default'     => 'no',
				'description' => __( 'Please enable if entity is in Saudi Arabia', 'uprise-payment-woocommerce' ),
			],
		];

		return apply_filters( 'wc_uprise_payment_apple_pay', $settings );
	}

	/**
	 * CKO admin google pay setting fields
	 *
	 * @return mixed|void
	 */
	public static function google_settings() {
		$settings = [
			'google_setting'            => [
				'title'       => __( 'Google Pay Settings', 'uprise-payment-woocommerce' ),
				'type'        => 'title',
				'description' => '',
			],
			'enabled'                   => [
				'id'          => 'enable',
				'title'       => __( 'Enable/Disable', 'uprise-payment-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable Uprise', 'uprise-payment-woocommerce' ),
				'description' => __( 'This enables google pay as a payment method', 'uprise-payment-woocommerce' ),
				'desc_tip'    => true,
				'default'     => 'no',
			],
			'title'                     => [
				'title'       => __( 'Title', 'uprise-payment-woocommerce' ),
				'type'        => 'text',
				'label'       => __( 'Google Pay', 'uprise-payment-woocommerce' ),
				'description' => __( 'Title that will be displayed on the checkout page', 'uprise-payment-woocommerce' ),
				'desc_tip'    => true,
				'default'     => 'Google Pay',
			],
			'description'               => [
				'title'       => __( 'Description', 'uprise-payment-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout.', 'uprise-payment-woocommerce' ),
				'default'     => 'Pay with Google Pay.',
				'desc_tip'    => true,
			],
			'upycom_google_merchant_id' => [
				'title'       => __( 'Merchant Identifier', 'uprise-payment-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Your production merchant identifier.', 'uprise-payment-woocommerce' ) . '<br>' . __( 'For testing use the following value: 01234567890123456789', 'uprise-payment-woocommerce' ),
				'desc_tip'    => false,
				'default'     => '01234567890123456789',
			],
			'upycom_google_threed'      => [
				'id'          => 'upycom_google_threed',
				'title'       => __( 'Use 3D Secure', 'uprise-payment-woocommerce' ),
				'type'        => 'select',
				'desc_tip'    => true,
				'options'     => [
					0 => __( 'No', 'uprise-payment-woocommerce' ),
					1 => __( 'Yes', 'uprise-payment-woocommerce' ),
				],
				'default'     => 0,
				'description' => '3D secure payment',
			],
			'upycom_google_style'       => [
				'title'       => __( 'Button Style', 'uprise-payment-woocommerce' ),
				'type'        => 'select',
				'class'       => 'wc-enhanced-select',
				'description' => __( 'Select button color.', 'uprise-payment-woocommerce' ),
				'default'     => 'authorize',
				'desc_tip'    => true,
				'options'     => [
					'google-pay-black' => __( 'Black', 'uprise-payment-woocommerce' ),
					'google-pay-white' => __( 'White', 'uprise-payment-woocommerce' ),
				],
			],
		];

		return apply_filters( 'wc_uprise_payment_google_pay', $settings );
	}

	/**
	 * CKO admin paypal setting fields
	 *
	 * @return mixed|void
	 */
	public static function paypal_settings() {
		$settings = [
			'google_setting' => [
				'title'       => __( 'PayPal Settings', 'uprise-payment-woocommerce' ),
				'type'        => 'title',
				'description' => '',
			],
			'enabled'        => [
				'id'          => 'enable',
				'title'       => __( 'Enable/Disable', 'uprise-payment-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable Uprise', 'uprise-payment-woocommerce' ),
				'description' => __( 'This enables PayPal as a payment method', 'uprise-payment-woocommerce' ),
				'desc_tip'    => true,
				'default'     => 'no',
			],
			'title'          => [
				'title'       => __( 'Title', 'uprise-payment-woocommerce' ),
				'type'        => 'text',
				'label'       => __( 'PayPal', 'uprise-payment-woocommerce' ),
				'description' => __( 'Title that will be displayed on the checkout page', 'uprise-payment-woocommerce' ),
				'desc_tip'    => true,
				'default'     => 'PayPal',
			],
			'description'    => [
				'title'       => __( 'Description', 'uprise-payment-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout.', 'uprise-payment-woocommerce' ),
				'default'     => 'Pay with PayPal.',
				'desc_tip'    => true,
			],
		];

		return apply_filters( 'wc_uprise_payment_paypal', $settings );
	}

	/**
	 * Alternative payment methods settings fields.
	 *
	 * @return mixed
	 */
	public static function apm_settings() {
		$settings = [
			'apm_setting'          => [
				'title'       => __( 'Alternative Payment Settings', 'uprise-payment-woocommerce' ),
				'type'        => 'title',
				'description' => '',
			],
			'enabled'              => [
				'id'          => 'enable',
				'title'       => __( 'Enable/Disable', 'uprise-payment-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable Uprise', 'uprise-payment-woocommerce' ),
				'description' => __( 'This enables alternative payment methods', 'uprise-payment-woocommerce' ),
				'desc_tip'    => true,
				'default'     => 'no',
			],
			'title'                => [
				'title'       => __( 'Title', 'uprise-payment-woocommerce' ),
				'type'        => 'text',
				'label'       => __( 'Alternative Payments', 'uprise-payment-woocommerce' ),
				'description' => __( 'Title that will be displayed on the checkout page', 'uprise-payment-woocommerce' ),
				'desc_tip'    => true,
				'default'     => 'Alternative Payment Methods',
			],
			'upycom_apms_selector' => [
				'title'   => __( 'Alternative Payment Methods', 'uprise-payment-woocommerce' ),
				'type'    => 'multiselect',
				'options' => [
					'alipay'     => __( 'Alipay', 'uprise-payment-woocommerce' ),
					'boleto'     => __( 'Boleto', 'uprise-payment-woocommerce' ),
					'giropay'    => __( 'Giropay', 'uprise-payment-woocommerce' ),
					'ideal'      => __( 'iDEAL', 'uprise-payment-woocommerce' ),
					'klarna'     => __( 'Klarna', 'uprise-payment-woocommerce' ),
					'poli'       => __( 'Poli', 'uprise-payment-woocommerce' ),
					'sepa'       => __( 'Sepa Direct Debit', 'uprise-payment-woocommerce' ),
					'sofort'     => __( 'Sofort', 'uprise-payment-woocommerce' ),
					'eps'        => __( 'EPS', 'uprise-payment-woocommerce' ),
					'bancontact' => __( 'Bancontact', 'uprise-payment-woocommerce' ),
					'knet'       => __( 'KNET', 'uprise-payment-woocommerce' ),
					'fawry'      => __( 'Fawry', 'uprise-payment-woocommerce' ),
					'qpay'       => __( 'QPay', 'uprise-payment-woocommerce' ),
					'multibanco' => __( 'Multibanco', 'uprise-payment-woocommerce' ),
				],
				'class'   => 'wc-enhanced-select',
				'css'     => 'width: 400px;',
			],

		];

		return apply_filters( 'wc_uprise_payment_alternative_payments', $settings );
	}

	/**
	 * Debugging settings.
	 *
	 * @return mixed
	 */
	public static function debug_settings() {
		$settings = [
			'debug_settings'        => [
				'title'       => __( 'Debug Settings', 'uprise-payment-woocommerce' ),
				'type'        => 'title',
				'description' => '',
			],
			'upy_file_logging'      => [
				'id'       => 'upy_file_logging',
				'title'    => __( 'File Logging', 'uprise-payment-woocommerce' ),
				'type'     => 'checkbox',
				'desc_tip' => true,
				'default'  => 'no',
				'desc'     => __( 'Check to enable file logging', 'uprise-payment-woocommerce' ),
			],
			'upy_console_logging'   => [
				'id'       => 'upy_console_logging',
				'title'    => __( 'Console Logging', 'uprise-payment-woocommerce' ),
				'type'     => 'checkbox',
				'desc_tip' => true,
				'default'  => 'no',
				'desc'     => __( 'Check to enable console logging', 'uprise-payment-woocommerce' ),
			],
			'upy_gateway_responses' => [
				'id'       => 'upy_gateway_responses',
				'title'    => __( 'Gateway Responses', 'uprise-payment-woocommerce' ),
				'type'     => 'checkbox',
				'desc_tip' => true,
				'default'  => 'no',
				'desc'     => __( 'Check to show gateway response.', 'uprise-payment-woocommerce' ),
			],
		];

		return apply_filters( 'wc_uprise_payment_cards', $settings );
	}

	/**
	 * CKO webhook settings fields.
	 *
	 * @return mixed
	 */
	public static function webhook_settings() {

		$settings = [
			'webhook_settings' => [
				'title'       => __( 'Webhook Details', 'uprise-payment-woocommerce' ),
				'type'        => 'title',
				'description' => '',
			],
			'upy_webhook_set'  => [
				'id'   => 'upy_webhook_set',
				'type' => 'uprisepay_webhook_settings',
			],
		];

		return apply_filters( 'wc_uprise_payment_cards', $settings );
	}
}
