<?php

/**
 * Class WC_Gateway_Checkout_Com_Google_Pay for Google Pay payment method.
 */
class WC_Gateway_Checkout_Com_Google_Pay extends WC_Payment_Gateway {

	/**
	 * WC_Gateway_Checkout_Com_Google_Pay constructor.
	 */
	public function __construct() {
		$this->id                 = 'wc_checkout_com_google_pay';
		$this->method_title       = __( 'Checkout.com', 'wc_checkout_com' );
		$this->method_description = __( 'The Checkout.com extension allows shop owners to process online payments through the <a href="https://www.checkout.com">Checkout.com Payment Gateway.</a>', 'wc_checkout_com' );
		$this->title              = __( 'Google Pay', 'wc_checkout_com' );

		$this->has_fields = true;
		$this->supports   = [ 'products', 'refunds' ];

		$this->init_form_fields();
		$this->init_settings();

		// Turn these settings into variables we can use.
		foreach ( $this->settings as $setting_key => $value ) {
			$this->$setting_key = $value;
		}

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );

		// Generate token.
		add_action( 'woocommerce_api_wc_checkoutcom_googlepay_token', [ $this, 'generate_token' ] );
	}

	/**
	 * Show module configuration in backend.
	 *
	 * @return string|void
	 */
	public function init_form_fields() {
		$this->form_fields = WC_Checkoutcom_Cards_Settings::google_settings();
		$this->form_fields = array_merge(
			$this->form_fields,
			[
				'screen_button' => [
					'id'    => 'screen_button',
					'type'  => 'screen_button',
					'title' => __( 'Other Settings', 'wc_checkout_com' ),
				],
			]
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
	 * Show frames js on checkout page.
	 */
	public function payment_fields() {
		global $woocommerce;

		$core_settings      = get_option( 'woocommerce_wc_checkout_com_cards_settings' );
		$environment        = 'sandbox' === $core_settings['ckocom_environment'];
		$currency_code      = get_woocommerce_currency();
		$total_price        = $woocommerce->cart->total;
		$generate_token_url = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'wc_checkoutcom_googlepay_token', home_url( '/' ) ) );

		if ( ! empty( $this->get_option( 'description' ) ) ) {
			echo  $this->get_option( 'description' );
		}

		?>
		<input type="hidden" id="cko-google-signature" name="cko-google-signature" value="" />
		<input type="hidden" id="cko-google-protocolVersion" name="cko-google-protocolVersion" value="" />
		<input type="hidden" id="cko-google-signedMessage" name="cko-google-signedMessage" value="" />
		<script>
			googlePayUiController = (function () {
				var DOMStrings = {
					buttonId: 'ckocom_googlePay',
					buttonClass: 'google-pay-button',
					googleButtonArea: 'method_wc_checkout_com_google_pay',
					buttonArea: '.form-row.place-order',
					placeOrder: '#place_order',
					paymentOptionLabel: '#dt_method_checkoutcomgooglepay > label:nth-child(2)',
					iconSpacer: 'cko-wallet-icon-spacer',
					token: 'google-cko-card-token',
					paymentMethodName: 'wc_checkout_com_google_pay'
				}

				return {
					hideDefaultPlaceOrder: function () {
						jQuery("input[name='payment_method']").change(function(e){
							jQuery(this).val() == DOMStrings.paymentMethodName ? jQuery(DOMStrings.placeOrder).hide() : jQuery(DOMStrings.placeOrder).show();
						})
					},
					addGooglePayButton: function (type) {
						// Create the Google Pay Button.
						var button = document.createElement('button');
						button.id = DOMStrings.buttonId;
						// Add button class based on the user configuration.
						button.className = DOMStrings.buttonClass + " " + type
						// Append the Google Pay button to the GooglePay area.
						jQuery('#payment').append(button);
						// Hide Google Pay button
						jQuery('#ckocom_googlePay').hide();

						// On page load if Google Pay is selected, show the button.
						if(jQuery('#payment_method_wc_checkout_com_google_pay').is(':checked')){
							// Disable place order button.
							jQuery('#place_order').hide();
							// Show Google Pay button.
							jQuery('#ckocom_googlePay').show();
						}
					},
					addIconSpacer: function () {
						jQuery(DOMStrings.paymentOptionLabel).append("<div class='" + iconSpacer + "'></div>")
					},
					getElements: function () {
						return {
							googlePayButtonId: jQuery(DOMStrings.buttonId),
							googlePayButtonClass: jQuery(DOMStrings.buttonClass),
							placeOrder: jQuery(DOMStrings.defaultPlaceOrder),
							buttonArea: jQuery(DOMStrings.buttonArea),
						};
					},
					getSelectors: function () {
						return {
							googlePayButtonId: DOMStrings.buttonId,
							googlePayButtonClass: DOMStrings.buttonClass,
							placeOrder: DOMStrings.defaultPlaceOrder,
							buttonArea: DOMStrings.buttonArea,
							token: DOMStrings.token,
						};
					}
				}
			})();

			googlePayTransactionController = (function (googlePayUiController) {
				var environment = '<?php echo $environment; ?>' == false ? "PRODUCTION" : "TEST";
				var publicKey = '<?php echo $core_settings['ckocom_pk']; ?>';
				var merchantId = '<?php echo $this->get_option( 'ckocom_google_merchant_id' ); ?>';
				var currencyCode = '<?php echo $currency_code; ?>';
				var totalPrice = '<?php echo $total_price; ?>';
				var buttonType = '<?php echo $this->get_option( 'ckocom_google_style' ); ?>';

				var generateTokenPath = '<?php echo $generate_token_url; ?>';
				var allowedPaymentMethods = ['CARD', 'TOKENIZED_CARD'];
				var allowedCardNetworks = ["AMEX", "DISCOVER", "JCB", "MASTERCARD", "VISA"];

				var _setupClickListeners = function () {
					jQuery(document).on('click', '#' + googlePayUiController.getSelectors().googlePayButtonId, function (e) {
						e.preventDefault();
						_startPaymentDataRequest();
					});
				}

				var _getGooglePaymentDataConfiguration = function () {
					return {
						merchantId: merchantId,
						paymentMethodTokenizationParameters: {
							tokenizationType: 'PAYMENT_GATEWAY',
							parameters: {
								'gateway': 'checkoutltd',
								'gatewayMerchantId': publicKey
							}
						},
						allowedPaymentMethods: allowedPaymentMethods,
						cardRequirements: {
							allowedCardNetworks: allowedCardNetworks
						}
					};
				}

				var _getGoogleTransactionInfo = function () {
					return {
						currencyCode: currencyCode,
						totalPriceStatus: 'FINAL',
						totalPrice: totalPrice
					};
				}

				var _getGooglePaymentsClient = function () {
					return (new google.payments.api.PaymentsClient({ environment: environment }));
				}

				var _generateCheckoutToken = function (token, callback) {
					var data = JSON.parse(token.paymentMethodToken.token);
					jQuery.ajax({
						type: 'POST',
						url : generateTokenPath,
						data: {
							token: {
								protocolVersion: data.protocolVersion,
								signature: data.signature,
								signedMessage: data.signedMessage
							}
						},
						success: function (outcome) {
							callback(outcome);
						},
						error: function (err) {
							console.log(err);
						}
					});
				}

				var _startPaymentDataRequest = function () {
					var paymentDataRequest = _getGooglePaymentDataConfiguration();
					paymentDataRequest.transactionInfo = _getGoogleTransactionInfo();

					var paymentsClient = _getGooglePaymentsClient();
					paymentsClient.loadPaymentData(paymentDataRequest)
						.then(function (paymentData) {
							document.getElementById('cko-google-signature').value = JSON.parse(paymentData.paymentMethodToken.token).signature;
							document.getElementById('cko-google-protocolVersion').value = JSON.parse(paymentData.paymentMethodToken.token).protocolVersion;
							document.getElementById('cko-google-signedMessage').value = JSON.parse(paymentData.paymentMethodToken.token).signedMessage;

							jQuery('#place_order').prop("disabled",false);
							jQuery('#place_order').trigger('click');
						})
						.catch(function (err) {
							console.error(err);
						});
				}

				return {
					init: function () {
						_setupClickListeners();
						googlePayUiController.hideDefaultPlaceOrder();
						googlePayUiController.addGooglePayButton(buttonType);
					}
				}

			})(googlePayUiController);

			// Initialise Google Pay.
			jQuery( document ).ready(function() {
				googlePayTransactionController.init();
			});

			// Check if Google Pay method is checked.
			if ( jQuery( '#payment_method_wc_checkout_com_google_pay' ).is( ':checked' ) ) {
				// Disable place order button.
				jQuery( '#place_order' ).prop( "disabled", true );
				jQuery( '#place_order' ).hide();
				jQuery( '#ckocom_googlePay' ).show();
			} else {
				// Enable place order button if not Google Pay.
				jQuery( '#place_order' ).prop( "disabled", false );
				jQuery( '#place_order' ).show();
				jQuery( '#ckocom_googlePay' ).hide();
			}

			// On payment radio button click.
			jQuery( "input[name='payment_method']" ).click( function () {
				// Check if payment method is Google Pay.
				if ( 'wc_checkout_com_google_pay' === this.value ) {
					// Show Google Pay button.
					jQuery( '#ckocom_googlePay' ).show();
					// Disable place order button.
					jQuery( '#place_order' ).prop( "disabled", true );
				} else if ( 'wc_checkout_com_apple_pay' === this.value ) {
					jQuery( '#ckocom_googlePay' ).hide();
					jQuery( document ).ready( function () {
						jQuery( "#place_order" ).hide();
					} );
				} else {
					// Hide Google Pay button.
					jQuery( '#ckocom_googlePay' ).hide();
					// Enable place order button.
					jQuery( '#place_order' ).prop( "disabled", false );
				}
			} )

		</script>
		<?php
	}

	/**
	 * Process payment with Google Pay.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {
		if ( ! session_id() ) {
			session_start();
		}

		global $woocommerce;
		$order = new WC_Order( $order_id );

		// create google token from Google payment data.
		$google_token = WC_Checkoutcom_Api_Request::generate_google_token();

		// Check if google token is not empty.
		if ( empty( $google_token['token'] ) ) {
			WC_Checkoutcom_Utility::wc_add_notice_self( __( 'There was an issue completing the payment.', 'wc_checkout_com' ), 'error' );
			return;
		}

		// Create payment with Google token.
		$result = (array) ( new WC_Checkoutcom_Api_Request )->create_payment( $order, $google_token );

		// Redirect to apm if redirection url is available.
		if ( isset( $result['3d'] ) && ! empty( $result['3d'] ) ) {

			return [
				'result'   => 'success',
				'redirect' => $result['3d'],
			];
		}

		// check if result has error and return error message.
		if ( isset( $result['error'] ) && ! empty( $result['error'] ) ) {
			WC_Checkoutcom_Utility::wc_add_notice_self( $result['error'] );
			return;
		}

		// Set action id as woo transaction id.
		update_post_meta( $order_id, '_transaction_id', $result['action_id'] );
		update_post_meta( $order_id, '_cko_payment_id', $result['id'] );

		// Get cko auth status configured in admin.
		$status = WC_Admin_Settings::get_option( 'ckocom_order_authorised', 'on-hold' );

		/* translators: %s: Action ID. */
		$message = sprintf( esc_html__( 'Checkout.com Payment Authorised - Action ID : %s', 'wc_checkout_com' ), $result['action_id'] );

		// check if payment was flagged.
		if ( $result['risk']['flagged'] ) {
			// Get cko auth status configured in admin.
			$status = WC_Admin_Settings::get_option( 'ckocom_order_flagged', 'flagged' );

			/* translators: %s: Action ID. */
			$message = sprintf( esc_html__( 'Checkout.com Payment Flagged - Action ID : %s', 'wc_checkout_com' ), $result['action_id'] );
		}

		// add notes for the order and update status.
		$order->add_order_note( $message );
		$order->update_status( $status );

		// Reduce stock levels.
		wc_reduce_stock_levels( $order_id );

		// Remove cart.
		$woocommerce->cart->empty_cart();

		// Return thank you page.
		return [
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		];
	}

	/**
	 * Handle Google Pay refund.
	 *
	 * @param int    $order_id Order ID.
	 * @param null   $amount  Amount to refund.
	 * @param string $reason Reason for refund.
	 *
	 * @return bool|WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order  = wc_get_order( $order_id );
		$result = (array) WC_Checkoutcom_Api_Request::refund_payment( $order_id, $order );

		// check if result has error and return error message.
		if ( isset( $result['error'] ) && ! empty( $result['error'] ) ) {
			WC_Checkoutcom_Utility::wc_add_notice_self( $result['error'] );
			return false;
		}

		// Set action id as woo transaction id.
		update_post_meta( $order_id, '_transaction_id', $result['action_id'] );

		// Get cko auth status configured in admin.
		$status = WC_Admin_Settings::get_option( 'ckocom_order_refunded', 'refunded' );

		/* translators: %s: Action ID. */
		$message = sprintf( esc_html__( 'Checkout.com Payment refunded - Action ID : %s', 'wc_checkout_com' ), $result['action_id'] );

		if ( isset( $_SESSION['cko-refund-is-less'] ) ) {
			if ( $_SESSION['cko-refund-is-less'] ) {
				/* translators: %s: Action ID. */
				$order->add_order_note( sprintf( esc_html__( 'Checkout.com Payment Partially refunded - Action ID : %s', 'wc_checkout_com' ), $result['action_id'] ) );

				unset( $_SESSION['cko-refund-is-less'] );

				return true;
			}
		}

		// Add notes for the order and update status.
		$order->add_order_note( $message );
		$order->update_status( $status );

		return true;
	}

}
