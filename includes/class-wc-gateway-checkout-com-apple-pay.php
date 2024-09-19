<?php
/**
 * Apple Pay method class.
 *
 * @package wc_checkout_com
 */

defined( 'ABSPATH' ) || exit;

include_once( 'settings/class-wc-checkoutcom-cards-settings.php' );

/**
 * Class WC_Gateway_Checkout_Com_Apple_Pay for Apple Pay method.
 */
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

	}

	/**
	 * Show module configuration in backend.
	 *
	 * @return string|void
	 */
	public function init_form_fields() {
		$this->form_fields = WC_Checkoutcom_Cards_Settings::apple_settings();
		$this->form_fields = array_merge(
			$this->form_fields,
			[
				'screen_button' => [
					'id'    => 'screen_button',
					'type'  => 'screen_button',
					'title' => __( 'Other Settings', 'checkout-com-unified-payments-api' ),
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

		$chosen_methods     = wc_get_chosen_shipping_method_ids();
		$chosen_shipping    = $chosen_methods[0] ?? '';
		$shipping_amount    = WC()->cart->get_shipping_total();
		$checkout_fields    = json_encode( $woocommerce->checkout->checkout_fields, JSON_HEX_APOS );
		$session_url        = str_replace( 'https:', 'https:', add_query_arg( 'wc-api', 'wc_checkoutcom_session', home_url( '/' ) ) );
		$generate_token_url = str_replace( 'https:', 'https:', add_query_arg( 'wc-api', 'wc_checkoutcom_generate_token', home_url( '/' ) ) );
		$apple_settings     = get_option( 'woocommerce_wc_checkout_com_apple_pay_settings' );
		$mada_enabled       = isset( $apple_settings['enable_mada'] ) && ( 'yes' === $apple_settings['enable_mada'] );

		if ( ! empty( $this->get_option( 'description' ) ) ) {
			echo  $this->get_option( 'description' );
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
				var canMakePayments = ApplePaySession.canMakePayments("<?php echo $this->get_option( 'ckocom_apple_mercahnt_id' ); ?>");

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
				jQuery('#payment').append('<div id="' + applePayButtonId + '" class="apple-pay-button '
				+ "<?php echo $this->get_option( 'ckocom_apple_type' ); ?>" + " "
				+ "<?php echo $this->get_option( 'ckocom_apple_theme' ); ?>"  + '" lang="'
				+ "<?php echo $this->get_option( 'ckocom_apple_language' ); ?>" + '"></div>');

				jQuery('#ckocom_applePay').hide();
			};

			// Listen for when the Apple Pay button is pressed.
			jQuery( document ).off( 'click', '#' + applePayButtonId );

			jQuery( document ).on( 'click', '#' + applePayButtonId, function () {
				var checkoutFields = '<?php echo $checkout_fields; ?>';
				var result = isValidFormField(checkoutFields);

				if(result){
					var applePaySession = new ApplePaySession(3, getApplePayConfig());
					handleApplePayEvents(applePaySession);
					applePaySession.begin();
				}
			});

			/**
			 * Get the configuration needed to initialise the Apple Pay session.
			 *
			 * @param {function} callback
			 */
			function getApplePayConfig() {

				var networksSupported = <?php echo json_encode( $supported_networks ); ?>;
				var merchantCapabilities = <?php echo json_encode( $merchant_capabilities ); ?>;

				return {
					currencyCode: "<?php echo get_woocommerce_currency(); ?>",
					countryCode: "<?php echo $country_code; ?>",
					merchantCapabilities: merchantCapabilities,
					supportedNetworks: networksSupported,
					total: {
						label: window.location.host,
						amount: "<?php echo $woocommerce->cart->total; ?>",
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
						amount: "<?php echo $woocommerce->cart->total; ?>",
					};

					var newLineItems = [
						{
							type: 'final',
							label: 'Subtotal',
							amount: "<?php echo $woocommerce->cart->subtotal; ?>"
						},
						{
							type: 'final',
							label: 'Shipping - ' + "<?php echo $chosen_shipping; ?>",
							amount: "<?php echo $shipping_amount; ?>"
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
					url: "<?php echo $session_url; ?>",
					data: {
						url: valURL,
						merchantId: "<?php echo $this->get_option( 'ckocom_apple_mercahnt_id' ); ?>",
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
					url: "<?php echo $generate_token_url; ?>",
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
	 * Apple pay session.
	 *
	 * @return void
	 */
	public function applepay_sesion() {
		$url          = $_POST['url'];
		$domain       = $_POST['domain'];
		$display_name = $_POST['displayName'];

		$merchant_id     = $this->get_option( 'ckocom_apple_mercahnt_id' );
		$certificate     = $this->get_option( 'ckocom_apple_certificate' );
		$certificate_key = $this->get_option( 'ckocom_apple_key' );

		if (
			'https' === parse_url( $url, PHP_URL_SCHEME ) &&
			substr( parse_url( $url, PHP_URL_HOST ), - 10 ) === '.apple.com'
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
	 * @return array
	 */
	public function process_payment( $order_id ) {
		if ( ! session_id() ) {
			session_start();
		}

		global $woocommerce;
		$order = new WC_Order( $order_id );

		// create apple token from apple payment data.
		$apple_token = $_POST['cko-apple-card-token'];

		// Check if apple token is not empty.
		if ( empty( $apple_token ) ) {
			WC_Checkoutcom_Utility::wc_add_notice_self( __( 'There was an issue completing the payment.', 'checkout-com-unified-payments-api' ), 'error' );

			return;
		}

		// Create payment with apple token.
		$result = (array) ( new WC_Checkoutcom_Api_Request )->create_payment( $order, $apple_token );

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
}
