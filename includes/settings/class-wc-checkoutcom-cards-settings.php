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

		$checkout_mode           = $core_settings['ckocom_checkout_mode'];
		$should_disable_checkbox = false;
				
		if ( 'flow' === $checkout_mode ) {
			$should_disable_checkbox = true;
		}

		$settings = [
			'core_setting'        => [
				'title'       => __( 'Core settings', 'checkout-com-unified-payments-api' ),
				'type'        => 'title',
				'description' => '',
			],
			'enabled'             => [
				'id'          		=> 'enable',
				'title'       		=> __( 'Enable/Disable', 'checkout-com-unified-payments-api' ),
				'type'        		=> 'checkbox',
				'label'       		=> __( 'Enable Checkout.com cards payment', 'checkout-com-unified-payments-api' ),
				'description' 		=> __( 'This enables Checkout.com. cards payment', 'checkout-com-unified-payments-api' ),
				'desc_tip'    		=> true,
				'default'     		=> 'yes',
				'custom_attributes' => $should_disable_checkbox ? [ 'disabled' => 'disabled' ] : [],
			],
			'ckocom_checkout_mode' => [
				'title'       => __( 'Checkout Mode', 'checkout-com-unified-payments-api' ),
				'type'        => 'select',
				'description' => __( 'Select the checkout mode for payment processing.', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => true,
				'options'     => [
					'classic' => __( 'Classic', 'checkout-com-unified-payments-api' ),
					'flow'    => __( 'Flow', 'checkout-com-unified-payments-api' ),
				],
				'default'     => 'classic',
			],
			'ckocom_region' => [
				'title'       => __( 'Region', 'checkout-com-unified-payments-api' ),
				'type'        => 'select',
				'description' => __( 'Choose subdomain for multi-region configuration', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => true,
				'options'     => [
					'global' => __( 'Global', 'checkout-com-unified-payments-api' ),
					'ksa'    => __( 'KSA', 'checkout-com-unified-payments-api' ),
				],
				'default'     => 'global',
				'custom_attributes' => [
					'disabled' => 'disabled',
				],
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
				'type'        => 'password',
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
		$core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings' );
		$checkout_mode = $core_settings['ckocom_checkout_mode'];
		$should_disable_flow_incompatible = false;
				
		if ( 'flow' === $checkout_mode ) {
			$should_disable_flow_incompatible = true;
		}

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
			'ckocom_card_3ds_challenge_indicator'    => [
				'id'       => 'ckocom_card_3ds_challenge_indicator',
				'title'    => __( '3DS Challenge Indicator', 'checkout-com-unified-payments-api' ),
				'type'     => 'select',
				'desc_tip' => true,
				'options'  => [
					'no_preference'              => __( 'No Preference', 'checkout-com-unified-payments-api' ),
					'no_challenge_requested'     => __( 'No Challenge Requested', 'checkout-com-unified-payments-api' ),
					'challenge_requested'        => __( 'Challenge Requested', 'checkout-com-unified-payments-api' ),
					'challenge_requested_mandate' => __( 'Challenge Requested Mandate', 'checkout-com-unified-payments-api' ),
				],
				'default'  => 'no_preference',
				'desc'     => __( 'Specifies the preference for whether a challenge should be performed', 'checkout-com-unified-payments-api' ),
			],
			'ckocom_card_3ds_exemption'              => [
				'id'       => 'ckocom_card_3ds_exemption',
				'title'    => __( '3DS Exemption', 'checkout-com-unified-payments-api' ),
				'type'     => 'select',
				'desc_tip' => true,
				'options'  => [
					''                           => __( 'No Exemption', 'checkout-com-unified-payments-api' ),
					'low_value'                  => __( 'Low Value', 'checkout-com-unified-payments-api' ),
					'trusted_listing'            => __( 'Trusted Listing', 'checkout-com-unified-payments-api' ),
					'trusted_listing_prompt'     => __( 'Trusted Listing Prompt', 'checkout-com-unified-payments-api' ),
					'transaction_risk_assessment' => __( 'Transaction Risk Assessment', 'checkout-com-unified-payments-api' ),
					'3ds_outage'                 => __( '3DS Outage', 'checkout-com-unified-payments-api' ),
					'sca_delegation'             => __( 'SCA Delegation', 'checkout-com-unified-payments-api' ),
					'out_of_sca_scope'           => __( 'Out of SCA Scope', 'checkout-com-unified-payments-api' ),
					'low_risk_program'           => __( 'Low Risk Program', 'checkout-com-unified-payments-api' ),
					'recurring_operation'        => __( 'Recurring Operation', 'checkout-com-unified-payments-api' ),
					'data_share'                 => __( 'Data Share', 'checkout-com-unified-payments-api' ),
					'other'                      => __( 'Other', 'checkout-com-unified-payments-api' ),
				],
				'default'  => '',
				'desc'     => __( 'Specifies the exemption type for 3DS authentication', 'checkout-com-unified-payments-api' ),
			],
			'ckocom_card_3ds_allow_upgrade'          => [
				'id'       => 'ckocom_card_3ds_allow_upgrade',
				'title'    => __( 'Allow 3DS Upgrade', 'checkout-com-unified-payments-api' ),
				'type'     => 'select',
				'desc_tip' => true,
				'options'  => [
					'yes' => __( 'Yes', 'checkout-com-unified-payments-api' ),
					'no'  => __( 'No', 'checkout-com-unified-payments-api' ),
				],
				'default'  => 'yes',
				'desc'     => __( 'Allow 3DS to be upgraded to a higher version if available', 'checkout-com-unified-payments-api' ),
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
				'custom_attributes' => $should_disable_flow_incompatible ? [ 'disabled' => 'disabled' ] : [],
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
				'custom_attributes' => $should_disable_flow_incompatible ? [ 'disabled' => 'disabled' ] : [],
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
				'custom_attributes' => $should_disable_flow_incompatible ? [ 'disabled' => 'disabled' ] : [],
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
				'custom_attributes' => $should_disable_flow_incompatible ? [ 'disabled' => 'disabled' ] : [],
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
				'custom_attributes' => $should_disable_flow_incompatible ? [ 'disabled' => 'disabled' ] : [],
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

		$checkout_mode           = $core_settings['ckocom_checkout_mode'];
		$should_disable_checkbox = false;
				
		if ( 'flow' === $checkout_mode ) {
			$should_disable_checkbox = true;
		}

		$settings = [
			// ==================== APPLE PAY SETTINGS ====================
			'apple_setting'            => [
				'title'       => __( 'Apple Pay Settings', 'checkout-com-unified-payments-api' ),
				'type'        => 'title',
				'description' => '',
			],
			'enabled'                  => [
				'id'          		=> 'enable',
				'title'       		=> __( 'Enable Apple Pay', 'checkout-com-unified-payments-api' ),
				'type'        		=> 'checkbox',
				'label'       		=> __( 'Enable Apple Pay payment method', 'checkout-com-unified-payments-api' ),
				'description' 		=> __( 'Enable this option to activate Apple Pay as a payment method on your checkout page. When enabled, customers with compatible Apple devices can use Apple Pay to complete their purchase.', 'checkout-com-unified-payments-api' ),
				'desc_tip'    		=> false,
				'default'     		=> 'yes',
				'custom_attributes' => $should_disable_checkbox ? [ 'disabled' => 'disabled' ] : [],
			],
			'title'                    => [
				'title'       => __( 'Payment Method Title', 'checkout-com-unified-payments-api' ),
				'type'        => 'text',
				'description' => __( 'This is the title that customers will see on the checkout page. Choose a clear, recognizable name for the payment method (e.g., "Apple Pay" or "Pay with Apple Pay").', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => false,
				'default'     => 'Apple Pay',
			],
			'description'              => [
				'title'       => __( 'Payment Method Description', 'checkout-com-unified-payments-api' ),
				'type'        => 'text',
				'description' => __( 'A short description that appears below the payment method title on the checkout page. This helps customers understand what Apple Pay is and how it works.', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => false,
				'default'     => 'Pay securely with Apple Pay using your iPhone, iPad, or Mac.',
			],

			// ==================== STEP 1: PAYMENT PROCESSING CERTIFICATE SETUP (for decryption) ====================
			'apple_pay_certificate_setup' => [
				'title'       => __( 'Step 1: Payment Processing Certificate Setup (for decryption)', 'checkout-com-unified-payments-api' ),
				'type'        => 'title',
				'description' => __( 'Set up your Apple Pay Payment Processing Certificate. This certificate is required for Checkout.com to decrypt Apple Pay payment tokens. Follow the steps below to generate and upload your certificate.', 'checkout-com-unified-payments-api' ),
			],
			'apple_pay_csr_generate'         => [
				'title'       => __( 'Step 1a: Generate Certificate Signing Request (CSR)', 'checkout-com-unified-payments-api' ),
				'type'        => 'apple_pay_csr_button',
				/* translators: 1: HTML anchor opening tag, 2: HTML anchor closing tag. */
				'description' => sprintf( __( 'First, create a Merchant ID in your %1$sApple Developer account%2$s if you haven\'t already. Then generate a Certificate Signing Request (CSR) file that you will upload to Apple Developer. This CSR is valid for 24 hours, so complete the certificate creation process promptly.', 'checkout-com-unified-payments-api' ), '<a target="_blank" href="https://developer.apple.com/account/resources/identifiers/add/merchant">', '</a>' ),
				'desc_tip'    => false,
			],
			'apple_pay_certificate_upload'    => [
				'title'       => __( 'Step 1b: Upload Signed Certificate', 'checkout-com-unified-payments-api' ),
				'type'        => 'apple_pay_certificate_upload',
				'description' => __( 'After generating the CSR above, upload it to Apple Developer and download the signed certificate (apple_pay.cer). Then upload that certificate here to complete the setup.', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => false,
			],

			// ==================== STEP 2: DOMAIN ASSOCIATION SETUP ====================
			'apple_pay_domain_association_setup' => [
				'title'       => __( 'Step 2: Domain Association Setup', 'checkout-com-unified-payments-api' ),
				'type'        => 'title',
				'description' => __( 'Register your merchant domain with Apple. This is required to display the Apple Pay button on your website. You must have a valid TLS certificate on your domain.', 'checkout-com-unified-payments-api' ),
			],
			'apple_pay_domain_association_upload' => [
				'title'       => __( 'Upload Domain Association File', 'checkout-com-unified-payments-api' ),
				'type'        => 'apple_pay_domain_association_upload',
				/* translators: 1: HTML anchor opening tag, 2: HTML anchor closing tag. */
				'description' => sprintf( __( 'After adding your domain in Apple Developer, download the domain association file (.txt) and upload it here. The file will be automatically saved to your server. Go to your %1$sApple Developer Merchant IDs%2$s to manage your domains.', 'checkout-com-unified-payments-api' ), '<a target="_blank" href="https://developer.apple.com/account/resources/identifiers/list/merchant">', '</a>' ),
				'desc_tip'    => false,
			],

			// ==================== STEP 3: MERCHANT IDENTITY CERTIFICATE SETUP (for session signing) ====================
			'apple_pay_merchant_identity_certificate_setup' => [
				'title'       => __( 'Step 3: Merchant Identity Certificate Setup (for session signing)', 'checkout-com-unified-payments-api' ),
				'type'        => 'title',
				'description' => __( 'Create and set up your Apple Pay Merchant Identity Certificate. This certificate is used to sign merchant session responses during Apple Pay payment authorization. Generate a CSR and private key locally using OpenSSL, upload the CSR to Apple Developer, download the signed certificate, and convert it to PEM format.', 'checkout-com-unified-payments-api' ),
			],
			'apple_pay_merchant_identity_csr_generate' => [
				'title'       => __( 'Step 3a: Generate Merchant Identity CSR and Key', 'checkout-com-unified-payments-api' ),
				'type'        => 'apple_pay_merchant_identity_csr_button',
				'description' => __( 'Generate a Certificate Signing Request (CSR) and private key file using OpenSSL. Click the button below to generate these files on your server. The files will be downloaded automatically: uploadMe.csr and certificate_sandbox.key. Save the private key file securely on your server, then upload the CSR file to Apple Developer.', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => false,
			],
			'apple_pay_merchant_identity_certificate_upload' => [
				'title'       => __( 'Step 3b: Upload and Convert Merchant Identity Certificate', 'checkout-com-unified-payments-api' ),
				'type'        => 'apple_pay_merchant_identity_certificate_upload',
				'description' => __( 'After uploading the CSR to Apple Developer and downloading the signed certificate (merchant_id.cer), upload it here. The certificate will be automatically converted from DER (.cer) format to PEM format and saved to your server. Make sure you have the certificate_sandbox.key file saved on your server from Step 3a.', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => false,
			],
			'apple_pay_test_certificate' => [
				'title'       => __( 'Step 4: Test Certificate and Key (Final Verification)', 'checkout-com-unified-payments-api' ),
				'type'        => 'apple_pay_test_certificate_button',
				'description' => __( 'Test your Apple Pay certificate and private key by validating them with Apple\'s payment session endpoint. This will verify that your certificate and key are correctly configured and working. Make sure you have configured the Merchant Identifier, Domain Name, Display Name, and certificate/key paths above.', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => false,
			],

			// ==================== EXPRESS CHECKOUT SETTINGS ====================
			'apple_pay_express_section' => [
				'title'       => __( 'Express Checkout Settings', 'checkout-com-unified-payments-api' ),
				'type'        => 'title',
				'description' => __( 'Decide how buttons for digital wallets Apple Pay and Google Pay are displayed in your store. Depending on their web browser and their wallet configurations, your customers will see either Apple Pay or Google Pay, but not both.', 'checkout-com-unified-payments-api' ),
			],
			'apple_pay_express'            => [
				'title'       => __( 'Enable Apple Pay / Google Pay', 'checkout-com-unified-payments-api' ),
				'label'       => __( 'Enable Apple Pay / Google Pay', 'checkout-com-unified-payments-api' ),
				'type'        => 'checkbox',
				'description' => __( 'When enabled, customers who have configured Apple Pay or Google Pay enabled devices will be able to pay with their respective choice of Wallet.', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => false,
				'default'     => 'no',
			],
			'apple_pay_express_location'    => [
				'title'       => __( 'Show express checkouts on', 'checkout-com-unified-payments-api' ),
				'type'        => 'title',
				'description' => __( 'Configure the display of Apple Pay and Google Pay buttons on your store.', 'checkout-com-unified-payments-api' ),
			],
			'apple_pay_express_product_page' => [
				'title'       => __( 'Product page', 'checkout-com-unified-payments-api' ),
				'label'       => __( 'Product page', 'checkout-com-unified-payments-api' ),
				'type'        => 'checkbox',
				'description' => __( 'Display the Apple Pay button on individual product pages.', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => false,
				'default'     => 'yes',
			],
			'apple_pay_express_shop_page'    => [
				'title'       => __( 'Shop & Category Pages', 'checkout-com-unified-payments-api' ),
				'label'       => __( 'Shop & Category Pages', 'checkout-com-unified-payments-api' ),
				'type'        => 'checkbox',
				'description' => __( 'Display the Apple Pay button on shop pages, category pages, tag archives, and other product listing pages.', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => false,
				'default'     => 'yes',
			],
			'apple_pay_express_cart_page'    => [
				'title'       => __( 'Cart', 'checkout-com-unified-payments-api' ),
				'label'       => __( 'Cart', 'checkout-com-unified-payments-api' ),
				'type'        => 'checkbox',
				'description' => __( 'Display the Apple Pay button on the cart page.', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => false,
				'default'     => 'yes',
			],
			'apple_pay_express_checkout_page' => [
				'title'       => __( 'Checkout', 'checkout-com-unified-payments-api' ),
				'label'       => __( 'Checkout', 'checkout-com-unified-payments-api' ),
				'type'        => 'checkbox',
				'description' => __( 'Display the Apple Pay button on the checkout page.', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => false,
				'default'     => 'yes',
			],
			'apple_pay_express_appearance' => [
				'title'       => __( 'Appearance', 'checkout-com-unified-payments-api' ),
				'type'        => 'title',
				'description' => __( 'Configure the appearance of Apple Pay and Google Pay buttons. Note: Some appearance settings may be overridden by the express payment section of the Cart & Checkout blocks.', 'checkout-com-unified-payments-api' ),
			],
			// Size settings temporarily disabled - buttons use native SDK sizes
			/*
			'apple_pay_express_button_size_preset' => [
				'title'       => __( 'Size', 'checkout-com-unified-payments-api' ),
				'type'        => 'select',
				'description' => __( 'Select the button size. Note that larger buttons are more suitable for mobile use.', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => false,
				'default'     => 'default',
				'options'     => [
					'native'  => __( 'Native (Recommended)', 'checkout-com-unified-payments-api' ),
					'small'   => __( 'Small (40 px)', 'checkout-com-unified-payments-api' ),
					'default' => __( 'Default (48 px)', 'checkout-com-unified-payments-api' ),
					'large'   => __( 'Large (56 px)', 'checkout-com-unified-payments-api' ),
					'custom'  => __( 'Custom Height', 'checkout-com-unified-payments-api' ),
				],
			],
			'apple_pay_express_button_custom_height' => [
				'title'       => __( 'Custom Button Height', 'checkout-com-unified-payments-api' ),
				'type'        => 'number',
				'description' => __( 'Enter a custom height in pixels (minimum 36px, maximum 60px). This setting only applies when "Custom Height" is selected above.', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => false,
				'default'     => '48',
				'custom_attributes' => [
					'min' => '36',
					'max' => '60',
					'step' => '1',
				],
			],
			*/

			// ==================== APPLE PAY CONFIGURATION ====================
			'apple_pay_configuration' => [
				'title'       => __( 'Apple Pay Configuration', 'checkout-com-unified-payments-api' ),
				'type'        => 'title',
				'description' => __( 'Configure your Apple Pay merchant identifier and certificate settings. These are required for Apple Pay to function properly.', 'checkout-com-unified-payments-api' ),
			],
			'ckocom_apple_mercahnt_id' => [
				'title'       => __( 'Merchant Identifier', 'checkout-com-unified-payments-api' ),
				'type'        => 'text',
				/* translators: 1: HTML anchor opening tag, 2: HTML anchor closing tag. */
				'description' => sprintf( __( 'Your Apple Pay Merchant Identifier is a unique identifier registered with Apple. You can find this in your Apple Developer account under Certificates, Identifiers & Profiles > Identifiers. To create a new Merchant ID, follow this %1$s guide %2$s', 'checkout-com-unified-payments-api' ), '<a target="_blank" href="' . esc_url( $docs_link ) . '">', '</a>' ),
				'desc_tip'    => false,
				'default'     => '',
				'placeholder' => 'merchant.com.yourdomain.production',
			],
			'ckocom_apple_certificate' => [
				'title'       => __( 'Merchant Identity Certificate Path', 'checkout-com-unified-payments-api' ),
				'type'        => 'text',
				'description' => __( 'Enter the absolute server path to your Apple Pay Merchant Identity Certificate file (.pem). This certificate is used to sign merchant session responses during Apple Pay payment authorization. This is the certificate from Step 3 (Merchant Identity Certificate Setup).', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => false,
				'default'     => '',
				'placeholder' => '/path/to/your/certificate_sandbox.pem',
			],
			'ckocom_apple_key'         => [
				'title'       => __( 'Merchant Identity Certificate Key Path', 'checkout-com-unified-payments-api' ),
				'type'        => 'text',
				'description' => __( 'Enter the absolute server path to your Apple Pay Merchant Identity Certificate private key file (.key). This key is used alongside the certificate to sign merchant session responses. This is the private key from Step 3a (Generate Merchant Identity CSR and Key).', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => false,
				'default'     => '',
				'placeholder' => '/path/to/your/certificate_sandbox.key',
				'custom_attributes' => [
					'data-field-type' => 'private-key',
				],
			],
			'apple_pay_domain_name' => [
				'title'       => __( 'Domain Name', 'checkout-com-unified-payments-api' ),
				'type'        => 'text',
				'description' => __( 'Enter the domain name where you will display the Apple Pay button (e.g., example.com or sandbox.example.com). This domain must match the domain where your Apple Pay button is displayed.', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => false,
				'default'     => '',
				'placeholder' => 'example.com',
			],
			'apple_pay_display_name' => [
				'title'       => __( 'Display Name', 'checkout-com-unified-payments-api' ),
				'type'        => 'text',
				'description' => __( 'Enter a display name for your merchant account (e.g., "merchant id for test environment"). This name will be used during Apple Pay payment session validation.', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => false,
				'default'     => '',
				'placeholder' => 'merchant id for test environment',
			],

			// ==================== BUTTON CUSTOMIZATION ====================
			'apple_pay_button_customization' => [
				'title'       => __( 'Button Appearance', 'checkout-com-unified-payments-api' ),
				'type'        => 'title',
				'description' => __( 'Customize the appearance of Apple Pay buttons on your store. These settings control the style, type, and language of the Apple Pay button.', 'checkout-com-unified-payments-api' ),
			],
			'ckocom_apple_type'        => array(
				'title'   => __( 'Call to action', 'checkout-com-unified-payments-api' ),
				'type'    => 'select',
				'description' => __( 'Select a button label that fits best with the flow of purchase or payment experience on your store.', 'checkout-com-unified-payments-api' ),
				'desc_tip' => false,
				'options' => array(
					'plain'     => __( 'Only icon', 'checkout-com-unified-payments-api' ),
					'buy'       => __( 'Buy', 'checkout-com-unified-payments-api' ),
					'donate'    => __( 'Donate', 'checkout-com-unified-payments-api' ),
					'book'      => __( 'Book', 'checkout-com-unified-payments-api' ),
					'check-out' => __( 'Checkout', 'checkout-com-unified-payments-api' ),
				),
				'default' => 'buy',
			),
			'ckocom_apple_theme'       => array(
				'title'   => __( 'Theme', 'checkout-com-unified-payments-api' ),
				'type'    => 'select',
				'description' => __( 'Choose the color theme for the Apple Pay button. Select a theme that contrasts well with your website design for optimal visibility.', 'checkout-com-unified-payments-api' ),
				'desc_tip' => false,
				'options' => array(
					'black'         => __( 'Dark', 'checkout-com-unified-payments-api' ) . ' - ' . __( 'Recommended for white or light-colored backgrounds with high contrast.', 'checkout-com-unified-payments-api' ),
					'white'         => __( 'Light', 'checkout-com-unified-payments-api' ) . ' - ' . __( 'Recommended for dark or colored backgrounds with high contrast.', 'checkout-com-unified-payments-api' ),
					'white-outline' => __( 'Outline', 'checkout-com-unified-payments-api' ) . ' - ' . __( 'Recommended for white or light-colored backgrounds with insufficient contrast.', 'checkout-com-unified-payments-api' ),
				),
				'default' => 'black',
			),
			'ckocom_apple_language'    => [
				'title'       => __( 'Button Language', 'checkout-com-unified-payments-api' ),
				'type'        => 'text',
				/* translators: 1: HTML anchor opening tag, 2: HTML anchor closing tag. */
				'description' => sprintf( __( 'Enter the ISO 639-1 language code for the Apple Pay button (e.g., "en" for English, "es" for Spanish). If left empty, the button will use the browser default language. See supported languages %1$s here %2$s', 'checkout-com-unified-payments-api' ), '<a href="https://applepaydemo.apple.com/" target="_blank">', '</a>' ),
				'desc_tip'    => false,
				'default'     => '',
				'placeholder' => 'en',
			],

			// ==================== MERCHANT CERTIFICATE SETUP ====================
			'apple_pay_merchant_certificate_setup' => [
				'title'       => __( 'Merchant Certificate & Private Key Setup', 'checkout-com-unified-payments-api' ),
				'type'        => 'title',
				'description' => __( 'Generate a self-signed merchant certificate and private key pair for Apple Pay merchant validation. These certificates are used to sign merchant session responses during Apple Pay payment authorization. After generating, download both files and configure their paths in the "Apple Pay Configuration" section above.', 'checkout-com-unified-payments-api' ),
			],
			'apple_pay_merchant_certificate_generate' => [
				'title'       => __( 'Generate Merchant Certificate and Key', 'checkout-com-unified-payments-api' ),
				'type'        => 'apple_pay_merchant_certificate_button',
				'description' => __( 'Click the button below to generate a self-signed merchant certificate (.pem) and private key (.key) file pair. After generation, both files will be automatically downloaded. Save these files securely on your server and configure their paths in the "Merchant Certificate Path" and "Merchant Certificate Key Path" fields above.', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => false,
			],

			// ==================== ADVANCED SETTINGS ====================
			'apple_pay_advanced_settings' => [
				'title'       => __( 'Advanced Settings', 'checkout-com-unified-payments-api' ),
				'type'        => 'title',
				'description' => __( 'Additional configuration options for specialized use cases.', 'checkout-com-unified-payments-api' ),
			],
			'enable_mada'              => [
				'id'          => 'enable_mada_apple_pay',
				'title'       => __( 'Enable MADA Support', 'checkout-com-unified-payments-api' ),
				'label'       => __( 'Enable MADA card support for Apple Pay', 'checkout-com-unified-payments-api' ),
				'type'        => 'checkbox',
				'description' => __( 'Enable this option if your business is located in Saudi Arabia and you want to accept MADA (Saudi Payments Network) cards through Apple Pay. MADA is the national payment network of Saudi Arabia.', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => false,
				'default'     => 'no',
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
		$core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings', array() );

		$checkout_mode           = isset( $core_settings['ckocom_checkout_mode'] ) ? $core_settings['ckocom_checkout_mode'] : 'classic';
		$should_disable_checkbox = false;
				
		if ( 'flow' === $checkout_mode ) {
			$should_disable_checkbox = true;
		}

		$settings = [
			'google_setting'            => [
				'title'       => __( 'Google Pay Settings', 'checkout-com-unified-payments-api' ),
				'type'        => 'title',
				'description' => '',
			],
			'enabled'                   => [
				'id'          		=> 'enable',
				'title'       		=> __( 'Enable/Disable', 'checkout-com-unified-payments-api' ),
				'type'        		=> 'checkbox',
				'label'       		=> __( 'Enable Checkout.com', 'checkout-com-unified-payments-api' ),
				'description' 		=> __( 'This enables google pay as a payment method', 'checkout-com-unified-payments-api' ),
				'desc_tip'    		=> true,
				'default'     		=> 'no',
				'custom_attributes' => $should_disable_checkbox ? [ 'disabled' => 'disabled' ] : [],
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
			'google_pay_express'            => [
				'title'       => __( 'Google Pay Express', 'checkout-com-unified-payments-api' ),
				'label'       => __( 'Enable Google Pay Express', 'checkout-com-unified-payments-api' ),
				'type'        => 'checkbox',
				'description' => __( 'Master toggle to activate Google Pay Express checkout. When enabled, use the options below to control where buttons appear.', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => true,
				'default'     => 'no',
			],
			'google_pay_express_location'    => [
				'title'       => __( 'Button Locations', 'checkout-com-unified-payments-api' ),
				'type'        => 'title',
				'description' => __( 'Control where Google Pay Express buttons appear on your store.', 'checkout-com-unified-payments-api' ),
			],
			'google_pay_express_product_page' => [
				'title'       => __( 'Show on Product Page', 'checkout-com-unified-payments-api' ),
				'label'       => __( 'Display Google Pay Express button on product detail pages', 'checkout-com-unified-payments-api' ),
				'type'        => 'checkbox',
				'description' => __( 'Show Google Pay Express button on individual product pages.', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => true,
				'default'     => 'yes',
			],
			'google_pay_express_shop_page'    => [
				'title'       => __( 'Show on Shop/Category Pages', 'checkout-com-unified-payments-api' ),
				'label'       => __( 'Display Google Pay Express button on shop and category listing pages', 'checkout-com-unified-payments-api' ),
				'type'        => 'checkbox',
				'description' => __( 'Show Google Pay Express button on shop, category, tag, and archive pages.', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => true,
				'default'     => 'yes',
			],
			'google_pay_express_cart_page'    => [
				'title'       => __( 'Show on Cart Page', 'checkout-com-unified-payments-api' ),
				'label'       => __( 'Display Google Pay Express button on cart page', 'checkout-com-unified-payments-api' ),
				'type'        => 'checkbox',
				'description' => __( 'Show Google Pay Express button on the cart page.', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => true,
				'default'     => 'yes',
			],
			// Size settings temporarily disabled - buttons use native SDK sizes
			/*
			'google_pay_express_button_size' => [
				'title'       => __( 'Button Size Customization', 'checkout-com-unified-payments-api' ),
				'type'        => 'title',
				'description' => __( 'Customize the size of Google Pay Express buttons. By default, buttons use their native styles for optimal appearance. You can choose to set a consistent size across all express buttons if needed.', 'checkout-com-unified-payments-api' ),
			],
			'google_pay_express_button_size_preset' => [
				'title'       => __( 'Button Size', 'checkout-com-unified-payments-api' ),
				'type'        => 'select',
				'description' => __( 'Choose the button size preset. "Native" uses the default Google Pay button styling (recommended). Other options set a consistent height across all express buttons.', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => false,
				'default'     => 'native',
				'options'     => [
					'native' => __( 'Native (Recommended)', 'checkout-com-unified-payments-api' ),
					'small'  => __( 'Small (36px)', 'checkout-com-unified-payments-api' ),
					'medium' => __( 'Medium (40px)', 'checkout-com-unified-payments-api' ),
					'large'  => __( 'Large (48px)', 'checkout-com-unified-payments-api' ),
					'custom' => __( 'Custom Height', 'checkout-com-unified-payments-api' ),
				],
			],
			'google_pay_express_button_custom_height' => [
				'title'       => __( 'Custom Button Height', 'checkout-com-unified-payments-api' ),
				'type'        => 'number',
				'description' => __( 'Enter a custom height in pixels (minimum 36px, maximum 60px). This setting only applies when "Custom Height" is selected above.', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => false,
				'default'     => '40',
				'custom_attributes' => [
					'min' => '36',
					'max' => '60',
					'step' => '1',
				],
			],
			*/
		];

		return apply_filters( 'wc_checkout_com_google_pay', $settings );
	}

	/**
	 * CKO admin paypal setting fields
	 *
	 * @return mixed|void
	 */
	public static function paypal_settings() {
		$core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings' );

		$checkout_mode           = $core_settings['ckocom_checkout_mode'];
		$should_disable_checkbox = false;
				
		if ( 'flow' === $checkout_mode ) {
			$should_disable_checkbox = true;
		}

		$settings = [
			'google_setting'            => [
				'title'       => __( 'PayPal Settings', 'checkout-com-unified-payments-api' ),
				'type'        => 'title',
				'description' => '',
			],
			'enabled'                   => [
				'id'          		=> 'enable',
				'title'       		=> __( 'Enable/Disable', 'checkout-com-unified-payments-api' ),
				'type'        		=> 'checkbox',
				'label'       		=> __( 'Enable Checkout.com', 'checkout-com-unified-payments-api' ),
				'description' 		=> __( 'This enables PayPal as a payment method', 'checkout-com-unified-payments-api' ),
				'desc_tip'    		=> true,
				'default'     		=> 'no',
				'custom_attributes' => $should_disable_checkbox ? [ 'disabled' => 'disabled' ] : [],
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
			'paypal_express'            => [
				'title'       => __( 'PayPal Express', 'checkout-com-unified-payments-api' ),
				'label'       => __( 'Enable PayPal Express', 'checkout-com-unified-payments-api' ),
				'type'        => 'checkbox',
				'description' => __( 'Master toggle to activate PayPal Express checkout. When enabled, use the options below to control where buttons appear.', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => true,
				'default'     => 'no',
			],
			'paypal_express_location'    => [
				'title'       => __( 'Button Locations', 'checkout-com-unified-payments-api' ),
				'type'        => 'title',
				'description' => __( 'Control where PayPal Express buttons appear on your store.', 'checkout-com-unified-payments-api' ),
			],
			'paypal_express_product_page' => [
				'title'       => __( 'Show on Product Page', 'checkout-com-unified-payments-api' ),
				'label'       => __( 'Display PayPal Express button on product detail pages', 'checkout-com-unified-payments-api' ),
				'type'        => 'checkbox',
				'description' => __( 'Show PayPal Express button on individual product pages.', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => true,
				'default'     => 'yes',
			],
			'paypal_express_shop_page'    => [
				'title'       => __( 'Show on Shop/Category Pages', 'checkout-com-unified-payments-api' ),
				'label'       => __( 'Display PayPal Express button on shop and category listing pages', 'checkout-com-unified-payments-api' ),
				'type'        => 'checkbox',
				'description' => __( 'Show PayPal Express button on shop, category, tag, and archive pages.', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => true,
				'default'     => 'yes',
			],
			'paypal_express_cart_page'    => [
				'title'       => __( 'Show on Cart Page', 'checkout-com-unified-payments-api' ),
				'label'       => __( 'Display PayPal Express button on cart page', 'checkout-com-unified-payments-api' ),
				'type'        => 'checkbox',
				'description' => __( 'Show PayPal Express button on the cart page.', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => true,
				'default'     => 'yes',
			],
			// Size settings temporarily disabled - buttons use native SDK sizes
			/*
			'paypal_express_button_size' => [
				'title'       => __( 'Button Size Customization', 'checkout-com-unified-payments-api' ),
				'type'        => 'title',
				'description' => __( 'Customize the size of PayPal Express buttons. By default, buttons use their native styles for optimal appearance. You can choose to set a consistent size across all express buttons if needed.', 'checkout-com-unified-payments-api' ),
			],
			'paypal_express_button_size_preset' => [
				'title'       => __( 'Button Size', 'checkout-com-unified-payments-api' ),
				'type'        => 'select',
				'description' => __( 'Choose the button size preset. "Native" uses the default PayPal button styling (recommended). Other options set a consistent height across all express buttons.', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => false,
				'default'     => 'native',
				'options'     => [
					'native' => __( 'Native (Recommended)', 'checkout-com-unified-payments-api' ),
					'small'  => __( 'Small (36px)', 'checkout-com-unified-payments-api' ),
					'medium' => __( 'Medium (40px)', 'checkout-com-unified-payments-api' ),
					'large'  => __( 'Large (48px)', 'checkout-com-unified-payments-api' ),
					'custom' => __( 'Custom Height', 'checkout-com-unified-payments-api' ),
				],
			],
			'paypal_express_button_custom_height' => [
				'title'       => __( 'Custom Button Height', 'checkout-com-unified-payments-api' ),
				'type'        => 'number',
				'description' => __( 'Enter a custom height in pixels (minimum 36px, maximum 60px). This setting only applies when "Custom Height" is selected above.', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => false,
				'default'     => '40',
				'custom_attributes' => [
					'min' => '36',
					'max' => '60',
					'step' => '1',
				],
			],
			*/
		];

		return apply_filters( 'wc_checkout_com_paypal', $settings );
	}

	/**
	 * Alternative payment methods settings fields.
	 *
	 * @return mixed
	 */
	public static function apm_settings() {
		$core_settings = get_option( 'woocommerce_wc_checkout_com_cards_settings' );

		$checkout_mode           = $core_settings['ckocom_checkout_mode'];
		$should_disable_checkbox = false;
				
		if ( 'flow' === $checkout_mode ) {
			$should_disable_checkbox = true;
		}

		$settings = [
			'apm_setting'          => [
				'title'       => __( 'Alternative Payment Settings', 'checkout-com-unified-payments-api' ),
				'type'        => 'title',
				'description' => '',
			],
			'enabled'              => [
				'id'          		=> 'enable',
				'title'       		=> __( 'Enable/Disable', 'checkout-com-unified-payments-api' ),
				'type'        		=> 'checkbox',
				'label'       		=> __( 'Enable Checkout.com', 'checkout-com-unified-payments-api' ),
				'description' 		=> __( 'This enables alternative payment methods', 'checkout-com-unified-payments-api' ),
				'desc_tip'    		=> true,
				'default'     		=> 'no',
				'custom_attributes' => $should_disable_checkbox ? [ 'disabled' => 'disabled' ] : [],
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

	/**
	 * CKO FLOW settings fields.
	 *
	 * @return array
	 */
	public static function flow_settings() {

		$locale_link      = 'https://www.checkout.com/docs/payments/accept-payments/accept-a-payment-on-your-website/add-localization-to-your-flow-integration#Supported_languages';
		$translation_link = 'https://www.checkout.com/docs/payments/accept-payments/accept-a-payment-on-your-website/add-localization-to-your-flow-integration#Add_custom_translations';
		$flow_com_link    = 'https://www.checkout.com/docs/payments/accept-payments/accept-a-payment-on-your-website/flow-library-reference/checkoutwebcomponents#Methods';

		// Flow Appearance settings.
		$settings = array(
			'flow_appearance_settings'              => array(
				'title'       => __( 'Appearance', 'checkout-com-unified-payments-api' ),
				'type'        => 'title',
				'description' => '',
			),
			'flow_appearance_color_action'          => array(
				'id'          => 'flow_appearance_color_action',
				'title'       => __( 'Color Action', 'checkout-com-unified-payments-api' ),
				'type'        => 'color',
				'description' => __( 'Choose a color for the Pay Button', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => true,
				'default'     => '#186AFF',
			),
			'flow_appearance_color_background'      => array(
				'id'          => 'flow_appearance_color_background',
				'title'       => __( 'Color Background', 'checkout-com-unified-payments-api' ),
				'type'        => 'color',
				'description' => __( 'Choose a color for the Background', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => true,
				'default'     => '#f8f9fa',
			),
			'flow_appearance_color_border'          => array(
				'id'          => 'flow_appearance_color_border',
				'title'       => __( 'Color Border', 'checkout-com-unified-payments-api' ),
				'type'        => 'color',
				'description' => __( 'Choose a color for the Border', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => true,
				'default'     => '#68686C',
			),
			'flow_appearance_color_disabled'        => array(
				'id'          => 'flow_appearance_color_disabled',
				'title'       => __( 'Color Disabled', 'checkout-com-unified-payments-api' ),
				'type'        => 'color',
				'description' => __( 'Choose a color for the Disabled button', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => true,
				'default'     => '#64646E',
			),
			'flow_appearance_color_error'           => array(
				'id'          => 'flow_appearance_color_error',
				'title'       => __( 'Color Error', 'checkout-com-unified-payments-api' ),
				'type'        => 'color',
				'description' => __( 'Choose a color for the Error Notification', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => true,
				'default'     => '#FF3300',
			),
			'flow_appearance_color_form_background' => array(
				'id'          => 'flow_appearance_color_form_background',
				'title'       => __( 'Color Form Background', 'checkout-com-unified-payments-api' ),
				'type'        => 'color',
				'description' => __( "Choose a color for the Form's Background", 'checkout-com-unified-payments-api' ),
				'desc_tip'    => true,
				'default'     => '#e9ebeb',
			),
			'flow_appearance_color_form_border'     => array(
				'id'          => 'flow_appearance_color_form_border',
				'title'       => __( 'Color Form Border', 'checkout-com-unified-payments-api' ),
				'type'        => 'color',
				'description' => __( "Choose a color for the Form's Border", 'checkout-com-unified-payments-api' ),
				'desc_tip'    => true,
				'default'     => '#1F1F1F',
			),
			'flow_appearance_color_inverse'         => array(
				'id'          => 'flow_appearance_color_inverse',
				'title'       => __( 'Color Inverse', 'checkout-com-unified-payments-api' ),
				'type'        => 'color',
				'description' => __( 'Choose a color for the text on Pay Button', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => true,
				'default'     => '#F9F9FB',
			),
			'flow_appearance_color_outline'         => array(
				'id'          => 'flow_appearance_color_outline',
				'title'       => __( 'Color Outline', 'checkout-com-unified-payments-api' ),
				'type'        => 'color',
				'description' => __( 'Choose a color for the outline', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => true,
				'default'     => '#ADA4EC',
			),
			'flow_appearance_color_primary'         => array(
				'id'          => 'flow_appearance_color_primary',
				'title'       => __( 'Color Primary', 'checkout-com-unified-payments-api' ),
				'type'        => 'color',
				'description' => __( 'Choose a primary color for the flow container', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => true,
				'default'     => '#454553',
			),
			'flow_appearance_color_secondary'       => array(
				'id'          => 'flow_appearance_color_secondary',
				'title'       => __( 'Color Secondary', 'checkout-com-unified-payments-api' ),
				'type'        => 'color',
				'description' => __( 'Choose a secondary color for the flow container', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => true,
				'default'     => '#828388',
			),
			'flow_appearance_color_success'         => array(
				'id'          => 'flow_appearance_color_success',
				'title'       => __( 'Color Success', 'checkout-com-unified-payments-api' ),
				'type'        => 'color',
				'description' => __( 'Choose a color for success notifications', 'checkout-com-unified-payments-api' ),
				'desc_tip'    => true,
				'default'     => '#2ECC71',
			),
		);      

		// Flow Button settings.
		$settings = array_merge(
			$settings,
			array(
				'flow_button_settings'       => array(
					'title'       => __( 'Button Settings', 'checkout-com-unified-payments-api' ),
					'type'        => 'title',
					'description' => '',
				),
				'flow_button_font_family'    => array(
					'id'          => 'flow_button_font_family',
					'title'       => __( 'Font Family', 'checkout-com-unified-payments-api' ),
					'type'        => 'text',
					'description' => __( 'Set the font family', 'checkout-com-unified-payments-api' ),
					'desc_tip'    => true,
					'default'     => '"Roboto Mono", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Fira Sans", "Droid Sans", "Helvetica Neue", "Noto Sans", "Liberation Sans", Arial, sans-serif',
				),
				'flow_button_font_size'      => array(
					'id'          => 'flow_button_font_size',
					'title'       => __( 'Font Size', 'checkout-com-unified-payments-api' ),
					'type'        => 'text',
					'description' => __( 'Set the font size', 'checkout-com-unified-payments-api' ),
					'desc_tip'    => true,
					'default'     => '16px',
				),
				'flow_button_font_weight'    => array(
					'id'          => 'flow_button_font_weight',
					'title'       => __( 'Font Weight', 'checkout-com-unified-payments-api' ),
					'type'        => 'number',
					'description' => __( 'Set the font weight (e.g., 400, 700)', 'checkout-com-unified-payments-api' ),
					'desc_tip'    => true,
					'default'     => '700',
				),
				'flow_button_letter_spacing' => array(
					'id'          => 'flow_button_letter_spacing',
					'title'       => __( 'Letter Spacing', 'checkout-com-unified-payments-api' ),
					'type'        => 'number',
					'description' => __( 'Set letter spacing (px)', 'checkout-com-unified-payments-api' ),
					'desc_tip'    => true,
					'default'     => '0',
				),
				'flow_button_line_height'    => array(
					'id'          => 'flow_button_line_height',
					'title'       => __( 'Line Height', 'checkout-com-unified-payments-api' ),
					'type'        => 'text',
					'description' => __( 'Set line height', 'checkout-com-unified-payments-api' ),
					'desc_tip'    => true,
					'default'     => '24px',
				),
			)
		);

		// Flow Footnote settings.
		$settings = array_merge(
			$settings,
			array(
				'flow_footnote_settings'       => array(
					'title'       => __( 'Footnote Settings', 'checkout-com-unified-payments-api' ),
					'type'        => 'title',
					'description' => '',
				),
				'flow_footnote_font_family'    => array(
					'id'          => 'flow_footnote_font_family',
					'title'       => __( 'Font Family', 'checkout-com-unified-payments-api' ),
					'type'        => 'text',
					'description' => __( 'Set the font family', 'checkout-com-unified-payments-api' ),
					'desc_tip'    => true,
					'default'     => '"PT Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Fira Sans", "Droid Sans", "Helvetica Neue", "Noto Sans", "Liberation Sans", Arial, sans-serif',
				),
				'flow_footnote_font_size'      => array(
					'id'          => 'flow_footnote_font_size',
					'title'       => __( 'Font Size', 'checkout-com-unified-payments-api' ),
					'type'        => 'text',
					'description' => __( 'Set the font size', 'checkout-com-unified-payments-api' ),
					'desc_tip'    => true,
					'default'     => '14px',
				),
				'flow_footnote_font_weight'    => array(
					'id'          => 'flow_footnote_font_weight',
					'title'       => __( 'Font Weight', 'checkout-com-unified-payments-api' ),
					'type'        => 'number',
					'description' => __( 'Set the font weight (e.g., 400, 700)', 'checkout-com-unified-payments-api' ),
					'desc_tip'    => true,
					'default'     => '400',
				),
				'flow_footnote_letter_spacing' => array(
					'id'          => 'flow_footnote_letter_spacing',
					'title'       => __( 'Letter Spacing', 'checkout-com-unified-payments-api' ),
					'type'        => 'number',
					'description' => __( 'Set letter spacing (px)', 'checkout-com-unified-payments-api' ),
					'desc_tip'    => true,
					'default'     => '0',
				),
				'flow_footnote_line_height'    => array(
					'id'          => 'flow_footnote_line_height',
					'title'       => __( 'Line Height', 'checkout-com-unified-payments-api' ),
					'type'        => 'text',
					'description' => __( 'Set line height', 'checkout-com-unified-payments-api' ),
					'desc_tip'    => true,
					'default'     => '20px',
				),
			)
		);

		// Flow Label settings.
		$settings = array_merge(
			$settings,
			array(
				'flow_label_settings'       => array(
					'title'       => __( 'Label Settings', 'checkout-com-unified-payments-api' ),
					'type'        => 'title',
					'description' => '',
				),
				'flow_label_font_family'    => array(
					'id'          => 'flow_label_font_family',
					'title'       => __( 'Font Family', 'checkout-com-unified-payments-api' ),
					'type'        => 'text',
					'description' => __( 'Set the font family', 'checkout-com-unified-payments-api' ),
					'desc_tip'    => true,
					'default'     => '"Roboto Mono", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Fira Sans", "Droid Sans", "Helvetica Neue", "Noto Sans", "Liberation Sans", Arial, sans-serif',
				),
				'flow_label_font_size'      => array(
					'id'          => 'flow_label_font_size',
					'title'       => __( 'Font Size', 'checkout-com-unified-payments-api' ),
					'type'        => 'text',
					'description' => __( 'Set the font size', 'checkout-com-unified-payments-api' ),
					'desc_tip'    => true,
					'default'     => '14px',
				),
				'flow_label_font_weight'    => array(
					'id'          => 'flow_label_font_weight',
					'title'       => __( 'Font Weight', 'checkout-com-unified-payments-api' ),
					'type'        => 'number',
					'description' => __( 'Set the font weight (e.g., 400, 700)', 'checkout-com-unified-payments-api' ),
					'desc_tip'    => true,
					'default'     => '400',
				),
				'flow_label_letter_spacing' => array(
					'id'          => 'flow_label_letter_spacing',
					'title'       => __( 'Letter Spacing', 'checkout-com-unified-payments-api' ),
					'type'        => 'number',
					'description' => __( 'Set letter spacing (px)', 'checkout-com-unified-payments-api' ),
					'desc_tip'    => true,
					'default'     => '0',
				),
				'flow_label_line_height'    => array(
					'id'          => 'flow_label_line_height',
					'title'       => __( 'Line Height', 'checkout-com-unified-payments-api' ),
					'type'        => 'text',
					'description' => __( 'Set line height', 'checkout-com-unified-payments-api' ),
					'desc_tip'    => true,
					'default'     => '20px',
				),
			)
		);

		// Flow Subheading settings.
		$settings = array_merge(
			$settings,
			array(
				'flow_subheading_settings'       => array(
					'title'       => __( 'Subheading Settings', 'checkout-com-unified-payments-api' ),
					'type'        => 'title',
					'description' => '',
				),
				'flow_subheading_font_family'    => array(
					'id'          => 'flow_subheading_font_family',
					'title'       => __( 'Font Family', 'checkout-com-unified-payments-api' ),
					'type'        => 'text',
					'description' => __( 'Set the font family', 'checkout-com-unified-payments-api' ),
					'desc_tip'    => true,
					'default'     => '"Roboto Mono", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Fira Sans", "Droid Sans", "Helvetica Neue", "Noto Sans", "Liberation Sans", Arial, sans-serif',
				),
				'flow_subheading_font_size'      => array(
					'id'          => 'flow_subheading_font_size',
					'title'       => __( 'Font Size', 'checkout-com-unified-payments-api' ),
					'type'        => 'text',
					'description' => __( 'Set the font size', 'checkout-com-unified-payments-api' ),
					'desc_tip'    => true,
					'default'     => '16px',
				),
				'flow_subheading_font_weight'    => array(
					'id'          => 'flow_subheading_font_weight',
					'title'       => __( 'Font Weight', 'checkout-com-unified-payments-api' ),
					'type'        => 'number',
					'description' => __( 'Set the font weight (e.g., 400, 700)', 'checkout-com-unified-payments-api' ),
					'desc_tip'    => true,
					'default'     => '700',
				),
				'flow_subheading_letter_spacing' => array(
					'id'          => 'flow_subheading_letter_spacing',
					'title'       => __( 'Letter Spacing', 'checkout-com-unified-payments-api' ),
					'type'        => 'number',
					'description' => __( 'Set letter spacing (px)', 'checkout-com-unified-payments-api' ),
					'desc_tip'    => true,
					'default'     => '0',
				),
				'flow_subheading_line_height'    => array(
					'id'          => 'flow_subheading_line_height',
					'title'       => __( 'Line Height', 'checkout-com-unified-payments-api' ),
					'type'        => 'text',
					'description' => __( 'Set line height', 'checkout-com-unified-payments-api' ),
					'desc_tip'    => true,
					'default'     => '24px',
				),
			)
		);

		// Flow Border settings.
		$settings = array_merge(
			$settings,
			array(
				'flow_border_settings'      => array(
					'title'       => __( 'Border Settings', 'checkout-com-unified-payments-api' ),
					'type'        => 'title',
					'description' => '',
				),
				'flow_form_border_radius'   => array(
					'id'          => 'flow_form_border_radius',
					'title'       => __( 'Form Border Radius', 'checkout-com-unified-payments-api' ),
					'type'        => 'text',
					'description' => __( 'Enter Form border-radius value', 'checkout-com-unified-payments-api' ),
					'desc_tip'    => true,
					'default'     => '8px',
				),
				'flow_button_border_radius' => array(
					'id'          => 'flow_button_border_radius',
					'title'       => __( 'Button Border Radius', 'checkout-com-unified-payments-api' ),
					'type'        => 'text',
					'description' => __( 'Enter Button border-radius value', 'checkout-com-unified-payments-api' ),
					'desc_tip'    => true,
					'default'     => '8px',
				),
			)
		);

		// Flow Component options.
		$settings = array_merge(
			$settings,
			array(
				'flow_component_options'                     => array(
					'title'       => __( 'Component Options', 'checkout-com-unified-payments-api' ),
					'type'        => 'title',
					'description' => '',
				),
				'flow_component_expand_first_payment_method' => array(
					'id'          => 'flow_component_expand_first_payment_method',
					'title'       => __( 'Auto Expand', 'checkout-com-unified-payments-api' ),
					'type'        => 'checkbox',
					'label'       => __( 'Expand/Collapse First Payment Method', 'checkout-com-unified-payments-api' ),
					'description' => __( 'Automatically expand the first available payment method if multiple methods exist.', 'checkout-com-unified-payments-api' ),
					'desc_tip'    => true,
					'default'     => 'yes',
				),
				'flow_show_card_holder_name'                 => array(
					'id'          => 'flow_show_card_holder_name',
					'title'       => __( 'Card Holder Name', 'checkout-com-unified-payments-api' ),
					'type'        => 'checkbox',
					'label'       => __( 'Show/Hide Card Holder Name', 'checkout-com-unified-payments-api' ),
					'description' => __( 'Show Card holder name from their account', 'checkout-com-unified-payments-api' ),
					'desc_tip'    => true,
					'default'     => 'yes',
				),
				'flow_saved_payment'                 		 => array(
					'id'          => 'flow_saved_payment',
					'title'       => __( 'Saved Payment Display Order', 'checkout-com-unified-payments-api' ),
					'type'        => 'select',
					'description' => __( "Choose how to display payment options at checkout: 
						- **Saved Cards First**: Show saved payment methods first and expand them by default when available. 
						- **New Payment Methods First**: Show new payment options first and expand them by default.", 'checkout-com-unified-payments-api' ),
					'desc_tip'    => true,
					'default'     => 'saved_cards_first',
					'options'     => array(
						'saved_cards_first' => __( 'Saved Cards First (default expanded)', 'checkout-com-unified-payments-api' ),
						'new_payment_first' => __( 'New Payment Methods First (default expanded)', 'checkout-com-unified-payments-api' ),
					),
				),
				'flow_component_cardholder_name_position'    => array(
					'id'          => 'flow_component_cardholder_name_position',
					'title'       => __( 'Cardholder Name Position', 'checkout-com-unified-payments-api' ),
					'type'        => 'select',
					'options'     => array(
						'top'    => __( 'Top', 'checkout-com-unified-payments-api' ),
						'bottom' => __( 'Bottom', 'checkout-com-unified-payments-api' ),
						'hidden' => __( 'Hidden', 'checkout-com-unified-payments-api' ),
					),
					'description' => __( 'Choose the position of the cardholder name field.', 'checkout-com-unified-payments-api' ),
					'desc_tip'    => true,
					'default'     => 'top',
				),
				'flow_component_locale'                      => array(
					'id'          => 'flow_component_locale',
					'title'       => __( 'Locale', 'checkout-com-unified-payments-api' ),
					'type'        => 'text',
					/* translators: 1: HTML anchor opening tag, 2: HTML anchor closing tag. */
					'description' => sprintf( __( 'You can %1$s check out locales here %2$s in the Checkout.com Hub', 'checkout-com-unified-payments-api' ), '<a class="checkoutcom-key-docs" target="_blank" href="' . esc_url( $locale_link ) . '">', '</a>' ),
					'default'     => 'en',
				),
				'flow_component_translation'                 => array(
					'id'          => 'flow_component_translation',
					'title'       => __( 'Custom Translation', 'checkout-com-unified-payments-api' ),
					'type'        => 'textarea',
					/* translators: 1: HTML anchor opening tag, 2: HTML anchor closing tag. */
					'description' => sprintf( __( 'Add data in JSON format. You can %1$s check out about translations here %2$s in the Checkout.com Hub', 'checkout-com-unified-payments-api' ), '<a class="checkoutcom-key-docs" target="_blank" href="' . esc_url( $translation_link ) . '">', '</a>' ),
					'placeholder' => <<<JSON
					{
						"en": {
							"form.required": "Please provide this field",
							"pay_button.pay": "Pay now",
							"pay_button.payment_failed": "Payment failed, please try again"
						},
						"fr": {.........}
					}
					JSON,
				),
				'flow_component_name'                        => array(
					'id'          => 'flow_component_name',
					'title'       => __( 'Flow Payment method', 'checkout-com-unified-payments-api' ),
					'type'        => 'text',
					/* translators: 1: HTML anchor opening tag, 2: HTML anchor closing tag. */
					'description' => sprintf( __( 'You can %1$s read more about flow component name here %2$s in the Checkout.com Hub. "flow" option will render all the available payment methods.', 'checkout-com-unified-payments-api' ), '<a class="checkoutcom-key-docs" target="_blank" href="' . esc_url( $flow_com_link ) . '">', '</a>' ),
					'default'     => 'flow',
				),
				'flow_enabled_payment_methods'               => array(
					'id'          => 'flow_enabled_payment_methods',
					'title'       => __( 'Enabled Payment Methods', 'checkout-com-unified-payments-api' ),
					'type'        => 'multiselect',
					'class'       => 'wc-enhanced-select',
					'css'         => 'width: 400px;',
					'description' => __( 'Select which payment methods to enable at checkout. Leave empty to enable all available methods. Select "Card" if you want only specific methods to show. Note: MOTO orders (admin-created orders) will always allow all payment methods regardless of this setting.', 'checkout-com-unified-payments-api' ),
					'desc_tip'    => true,
					'options'     => array(
						// Card payments
						'card'        => __( '💳 Card (Credit/Debit) - Global', 'checkout-com-unified-payments-api' ),
						'stored_card' => __( '🔐 Stored Card (Checkout.com Network) - Global', 'checkout-com-unified-payments-api' ),
						
						// Global / Multi-Region
						'googlepay'   => __( '🟢 Google Pay - Global', 'checkout-com-unified-payments-api' ),
						'applepay'    => __( '🍎 Apple Pay - Global', 'checkout-com-unified-payments-api' ),
						'paypal'      => __( '🅿️ PayPal - Global', 'checkout-com-unified-payments-api' ),
						
						// Europe
						'ideal'       => __( '🇳🇱 iDEAL - Netherlands', 'checkout-com-unified-payments-api' ),
						'eps'         => __( '🇦🇹 EPS - Austria', 'checkout-com-unified-payments-api' ),
						'bancontact'  => __( '🇧🇪 Bancontact - Belgium', 'checkout-com-unified-payments-api' ),
						'p24'         => __( '🇵🇱 Przelewy24 - Poland', 'checkout-com-unified-payments-api' ),
						'multibanco'  => __( '🇵🇹 Multibanco - Portugal', 'checkout-com-unified-payments-api' ),
						'mbway'       => __( '🇵🇹 MB WAY - Portugal', 'checkout-com-unified-payments-api' ),
						'klarna'      => __( '🇪🇺 Klarna - EU/Nordics/UK', 'checkout-com-unified-payments-api' ),
						'sepa'        => __( '🇪🇺 SEPA Direct Debit - EU', 'checkout-com-unified-payments-api' ),
						'alma'        => __( '🇫🇷 Alma (Installments) - France', 'checkout-com-unified-payments-api' ),
						
						// Middle East
						'knet'        => __( '🇰🇼 KNET - Kuwait', 'checkout-com-unified-payments-api' ),
						'benefit'     => __( '🇧🇭 BenefitPay - Bahrain', 'checkout-com-unified-payments-api' ),
						'stcpay'      => __( '🇸🇦 STC Pay - Saudi Arabia', 'checkout-com-unified-payments-api' ),
						'tabby'       => __( '🇦🇪 Tabby - UAE/Saudi Arabia', 'checkout-com-unified-payments-api' ),
						'tamara'      => __( '🇦🇪 Tamara - UAE/Saudi Arabia', 'checkout-com-unified-payments-api' ),
						'qpay'        => __( '🇶🇦 QPay - Qatar', 'checkout-com-unified-payments-api' ),
						
						// Asia
						'tng'         => __( '🇲🇾 Touch n Go - Malaysia', 'checkout-com-unified-payments-api' ),
						'truemoney'   => __( '🇹🇭 TrueMoney - Thailand', 'checkout-com-unified-payments-api' ),
						'dana'        => __( '🇮🇩 DANA - Indonesia', 'checkout-com-unified-payments-api' ),
						'gcash'       => __( '🇵🇭 GCash - Philippines', 'checkout-com-unified-payments-api' ),
						'kakaopay'    => __( '🇰🇷 KakaoPay - South Korea', 'checkout-com-unified-payments-api' ),
						
						// China / Hong Kong
						'alipay_cn'   => __( '🇨🇳 Alipay CN - China', 'checkout-com-unified-payments-api' ),
						'alipay_hk'   => __( '🇭🇰 Alipay HK - Hong Kong', 'checkout-com-unified-payments-api' ),
					),
					'default'     => array(), // Empty by default - shows all available methods including cards
				),
			'flow_debug_logging'                   => array(
				'id'          => 'flow_debug_logging',
				'title'       => __( 'Debug Logging', 'checkout-com-unified-payments-api' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable detailed console logging for debugging', 'checkout-com-unified-payments-api' ),
				'description' => __( 'When enabled, logs detailed debugging information to browser console including initialization, form submissions, and card saving. Shows performance metrics, 3DS flows, and payment processing steps. <strong>Disable in production</strong> to reduce console output. Critical errors, warnings, webhooks, and 3DS authentication will always be visible.', 'checkout-com-unified-payments-api' ),
					'desc_tip'    => true,
					'default'     => 'yes',
				),
			)
		);


		return apply_filters( 'wc_checkout_com_flow_settings', $settings );
	}

}
