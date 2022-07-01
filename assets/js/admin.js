jQuery( function ( $ ) {

	var admin_functions = {

		hidePaymentMethods: function () {
			const applePay = $( '[data-gateway_id="wc_checkout_com_apple_pay"]' );
			const googlePay = $( '[data-gateway_id="wc_checkout_com_google_pay"]' );
			const alternativePay = $( '[data-gateway_id*="wc_checkout_com_alternative_payments"]' );

			if ( applePay.length > 0 ) {
				applePay.hide();
			}
			if ( googlePay.length > 0 ) {
				googlePay.hide();
			}

			if ( alternativePay.length > 0 ) {
				alternativePay.hide();
			}
		},

		disableRefundForZero: function () {
			// Disable cko refund button to prevent refund of 0.00
			const refund_button = document.getElementsByClassName( 'button-primary do-api-refund' )[0];

			if ( refund_button ) {
				refund_button.disabled = true

				$( '#refund_amount' ).on( 'change input', function () {
					$( this ).val() <= 0 ? refund_button.disabled = true : refund_button.disabled = false;
				} );
			}
		},

		addCaptureVoidButtons: function () {

			if ( typeof ckoCustomButtonValues === 'undefined' ) {
				return;
			}

			const order_status = ckoCustomButtonValues.order_status;
			const auth_status = ckoCustomButtonValues.auth_status;
			const capture_status = ckoCustomButtonValues.capture_status;

			// check if order status is same as auth status in cko settings
			// hide refund button and show capture and void button
			if ( order_status === auth_status ) {
				$( '.refund-items' ).hide();
				$( '#cko-capture' ).show();
				$( '#cko-void' ).show();
			} else if ( order_status === capture_status || 'completed' === order_status ) {
				$( '.refund-items' ).show();
			} else {
				$( '.refund-items' ).hide();
			}

			if ( $( '#cko-void' ).length > 0 ) {
				$( '#cko-void' ).on( 'click', function () {
					document.getElementById( 'cko_payment_action' ).value = this.id;
				} )
			}

			if ( $( '#cko-capture' ).length > 0 ) {
				$( '#cko-capture' ).on( 'click', function () {
					document.getElementById( 'cko_payment_action' ).value = this.id;
				} )
			}
		},

		updateDocURL: function () {

			if ( typeof cko_admin_vars === 'undefined' ) {
				return;
			}

			let keyDocs = $( '.checkoutcom-key-docs' );
			let nasDocs = cko_admin_vars.nas_docs;
			let abcDocs = cko_admin_vars.abc_docs;

			// Handle account type change to update docs link.
			$( '#woocommerce_wc_checkout_com_cards_ckocom_account_type' ).on( 'change', function ( e ) {
				if ( 'NAS' === $( this ).val() ) {
					keyDocs.attr( 'href', nasDocs );
				} else {
					keyDocs.attr( 'href', abcDocs );
				}
			} );
		},

		orderStatusSettings: function () {
			$( '#ckocom_order_authorised' ).on( 'click', function () {

				$( '#ckocom_order_authorised option' ).prop( 'disabled', false );

				const captured_order_status = $( '#ckocom_order_captured' ).val();
				$( '#ckocom_order_authorised option[value="' + captured_order_status + '"]' ).prop( 'disabled', true );

			} );

			$( '#ckocom_order_captured' ).on( 'click', function () {

				$( '#ckocom_order_captured option' ).prop( 'disabled', false );

				const authorized_order_status = $( '#ckocom_order_authorised' ).val();
				$( '#ckocom_order_captured option[value= "' + authorized_order_status + '"]' ).prop( 'disabled', true );

			} );
		},

		cardSettings: function () {

			let ckocom_card_autocap = $( '#ckocom_card_autocap' );
			let ckocom_card_cap_delay = $( '#ckocom_card_cap_delay' );
			let ckocom_card_threed = $( '#ckocom_card_threed' );
			let ckocom_card_notheed = $( '#ckocom_card_notheed' );
			let ckocom_card_saved = $( '#ckocom_card_saved' );
			let ckocom_card_require_cvv = $( '#ckocom_card_require_cvv' );
			let ckocom_card_desctiptor = $( '#ckocom_card_desctiptor' );
			let ckocom_card_desctiptor_name = $( '#ckocom_card_desctiptor_name' );
			let ckocom_card_desctiptor_city = $( '#ckocom_card_desctiptor_city' );
			let ckocom_display_icon = $( '#ckocom_display_icon' );
			let ckocom_card_icons = $( '#ckocom_card_icons' );

			if ( ckocom_card_autocap.val() === '0' ) {
				ckocom_card_cap_delay.closest( 'tr' ).hide();
			}

			ckocom_card_autocap.on( 'change', function () {
				if ( this.value === '0' ) {
					ckocom_card_cap_delay.closest( 'tr' ).hide();
				} else {
					ckocom_card_cap_delay.closest( 'tr' ).show();
				}
			} )

			if ( ckocom_card_threed.val() === '0' ) {
				ckocom_card_notheed.closest( 'tr' ).hide();
			}

			ckocom_card_threed.on( 'change', function () {
				if ( this.value === '0' ) {
					ckocom_card_notheed.closest( 'tr' ).hide();
				} else {
					ckocom_card_notheed.closest( 'tr' ).show();
				}
			} )

			if ( ckocom_card_saved.val() === '0' ) {
				ckocom_card_require_cvv.closest( 'tr' ).hide();
			}

			ckocom_card_saved.on( 'change', function () {
				if ( this.value === '0' ) {
					ckocom_card_require_cvv.closest( 'tr' ).hide();
				} else {
					ckocom_card_require_cvv.closest( 'tr' ).show();
				}
			} )

			if ( ckocom_card_desctiptor.val() === '0' ) {
				ckocom_card_desctiptor_name.closest( 'tr' ).hide();
				ckocom_card_desctiptor_city.closest( 'tr' ).hide();
			}

			ckocom_card_desctiptor.on( 'change', function () {
				if ( this.value === '0' ) {
					ckocom_card_desctiptor_name.closest( 'tr' ).hide();
					ckocom_card_desctiptor_city.closest( 'tr' ).hide();
				} else {
					ckocom_card_desctiptor_name.closest( 'tr' ).show();
					ckocom_card_desctiptor_city.closest( 'tr' ).show();
				}
			} )

			if ( ckocom_display_icon.val() === '0' ) {
				ckocom_card_icons.closest( 'tr' ).hide();
			}

			ckocom_display_icon.on( 'change', function () {
				if ( this.value === '0' ) {
					ckocom_card_icons.closest( 'tr' ).hide();
				} else {
					ckocom_card_icons.closest( 'tr' ).show();
				}
			} )
		},

		webhookSettings: function () {

			if ( ! $( '.cko-admin-settings__links .current.cko-webhook' ).length ) {
				return;
			}

			$( '.submit .woocommerce-save-button' ).attr( 'disabled', 'disabled' ).hide();


			// Fetch the latest webhooks.
			$( '#checkoutcom-is-register-webhook' ).on( 'click', function () {
				$( this ).attr( 'disabled', 'disabled' );
				$( this ).siblings( '.spinner' ).addClass( 'is-active' );
				$( '.checkoutcom-is-register-webhook-text' ).html( '' );
				$( '#checkoutcom-is-register-webhook' ).siblings( '.dashicons-yes' ).addClass( 'hidden' );

				$.ajax( {
					url: ajaxurl,
					type: 'POST',
					data: {
						'action': 'wc_checkoutcom_check_webhook',
						'security': cko_admin_vars.checkoutcom_check_webhook_nonce
					}
				} ).done( function ( response ) {
					if ( response.data.message ) {
						$( '#checkoutcom-is-register-webhook' ).siblings( '.dashicons-yes.hidden' ).removeClass( 'hidden' );
						$( '.checkoutcom-is-register-webhook-text' ).html( response.data.message );
					}

				} ).fail( function ( response ) {
					alert( cko_admin_vars.webhook_check_error );

				} ).always( function () {
					$( '#checkoutcom-is-register-webhook' ).prop( 'disabled', false );
					$( '#checkoutcom-is-register-webhook' ).siblings( '.spinner' ).removeClass( 'is-active' );
				} );
			} );


			// Register a new webhook.
			$( '#checkoutcom-register-webhook' ).on( 'click', function () {
				$( this ).attr( 'disabled', 'disabled' );
				$( this ).siblings( '.spinner' ).addClass( 'is-active' );
				$( '#checkoutcom-register-webhook' ).siblings( '.dashicons-yes' ).addClass( 'hidden' );

				$.ajax( {
					url: ajaxurl,
					type: 'POST',
					data: {
						'action': 'wc_checkoutcom_register_webhook',
						'security': cko_admin_vars.checkoutcom_check_webhook_nonce
					}
				} ).done( function ( response ) {
					$( '#checkoutcom-register-webhook' ).siblings( '.dashicons-yes.hidden' ).removeClass( 'hidden' );

				} ).fail( function ( response ) {
					alert( cko_admin_vars.webhook_register_error );

				} ).always( function () {
					$( '#checkoutcom-register-webhook' ).prop( 'disabled', false );
					$( '#checkoutcom-register-webhook' ).siblings( '.spinner' ).removeClass( 'is-active' );

				} );
			} );

		}
	}

	// Hide Apple Pay, Google Pay from payment method tab.
	admin_functions.hidePaymentMethods();

	// Disable refund button for 0 value.
	admin_functions.disableRefundForZero();

	admin_functions.addCaptureVoidButtons();

	// Update docs URL bases on selection.
	admin_functions.updateDocURL();

	admin_functions.orderStatusSettings();

	// Script to hide and show fields.
	admin_functions.cardSettings();

	admin_functions.webhookSettings();
} );
