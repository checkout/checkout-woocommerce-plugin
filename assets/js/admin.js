jQuery( function ( $ ) {

	var admin_functions = {

		hidePaymentMethods: function () {
			const observer = new MutationObserver(() => {
				const classicCheckout = $( '#wc_checkout_com_cards' );
				const applePay = $( '#wc_checkout_com_apple_pay' );
				const googlePay = $( '#wc_checkout_com_google_pay' );
				const payPal = $( '#wc_checkout_com_paypal' );
				const flowPay = $( '#wc_checkout_com_flow' );
				const alternativePay = $( '#wc_checkout_com_alternative_payments' );

				if (applePay.length && googlePay.length && flowPay.length && alternativePay.length) {
					observer.disconnect();
				}

				if ( applePay.length > 0 ) {
					applePay.hide();
				}
				if ( googlePay.length > 0 ) {
					googlePay.hide();
				}
				if ( payPal.length > 0 ) {
					payPal.hide()
				}
	
				if ( flowPay.length > 0 ) {
					flowPay.hide();
				}
	
				if ( alternativePay.length > 0 ) {
					alternativePay.hide();
				}
	
				if( cko_admin_vars.flow_enabled ) {
					classicCheckout.hide();
					payPal.hide();
					flowPay.show();
				}
			});

			observer.observe(document.body, { childList: true, subtree: true });
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
			// show capture and void button
			if ( order_status === auth_status ) {
				$( '#cko-capture' ).show();
				$( '#cko-void' ).show();
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

        coreSettings: function () {
            let enable_fallback_ac = $( '#woocommerce_wc_checkout_com_cards_enable_fallback_ac' );
            let fallback_ckocom_sk = $( '#woocommerce_wc_checkout_com_cards_fallback_ckocom_sk' );
            let fallback_ckocom_pk = $( '#woocommerce_wc_checkout_com_cards_fallback_ckocom_pk' );

            if ( enable_fallback_ac.length <= 0 ) {
                return;
            }

            enable_fallback_ac.on( 'change', function () {
                if ( this.checked ) {
                    fallback_ckocom_sk.closest( 'tr' ).show();
                    fallback_ckocom_pk.closest( 'tr' ).show();
                } else {
                    fallback_ckocom_sk.closest( 'tr' ).hide();
                    fallback_ckocom_pk.closest( 'tr' ).hide();
                }
            } )

            enable_fallback_ac.trigger( 'change' );
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
			let ckocom_language_type = $( '#ckocom_language_type' );
			let ckocom_language_fallback = $( '#ckocom_language_fallback' );
			let ckocom_card_number_placeholder = $( '#ckocom_card_number_placeholder' );
			let ckocom_card_expiry_month_placeholder = $( '#ckocom_card_expiry_month_placeholder' );
			let ckocom_card_expiry_year_placeholder = $( '#ckocom_card_expiry_year_placeholder' );
			let ckocom_card_cvv_placeholder = $( '#ckocom_card_cvv_placeholder' );
			let ckocom_card_scheme_link_placeholder = $( '#ckocom_card_scheme_link_placeholder' );
			let ckocom_card_scheme_header_placeholder = $( '#ckocom_card_scheme_header_placeholder' );

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

            if ( ckocom_language_type.val() === '0' ) {
                ckocom_language_fallback.closest( 'tr' ).show();
                ckocom_card_number_placeholder.closest( 'tr' ).hide();
                ckocom_card_expiry_month_placeholder.closest( 'tr' ).hide();
                ckocom_card_expiry_year_placeholder.closest( 'tr' ).hide();
                ckocom_card_cvv_placeholder.closest( 'tr' ).hide();
                ckocom_card_scheme_link_placeholder.closest( 'tr' ).hide();
                ckocom_card_scheme_header_placeholder.closest( 'tr' ).hide();
            } else {
                ckocom_language_fallback.closest( 'tr' ).hide();
            }

            ckocom_language_type.on( 'change', function () {
                if ( this.value === '0' ) {
                    ckocom_language_fallback.closest( 'tr' ).show();
                    ckocom_card_number_placeholder.closest( 'tr' ).hide();
                    ckocom_card_expiry_month_placeholder.closest( 'tr' ).hide();
                    ckocom_card_expiry_year_placeholder.closest( 'tr' ).hide();
                    ckocom_card_cvv_placeholder.closest( 'tr' ).hide();
                    ckocom_card_scheme_link_placeholder.closest( 'tr' ).hide();
                    ckocom_card_scheme_header_placeholder.closest( 'tr' ).hide();
                } else {
                    ckocom_language_fallback.closest( 'tr' ).hide();
                    ckocom_card_number_placeholder.closest( 'tr' ).show();
                    ckocom_card_expiry_month_placeholder.closest( 'tr' ).show();
                    ckocom_card_expiry_year_placeholder.closest( 'tr' ).show();
                    ckocom_card_cvv_placeholder.closest( 'tr' ).show();
                    ckocom_card_scheme_link_placeholder.closest( 'tr' ).show();
                    ckocom_card_scheme_header_placeholder.closest( 'tr' ).show();
                }
            } )
		},

		webhookSettings: function () {

			// Check if webhook check button exists (more reliable than checking for non-existent selector)
			if ( ! $( '#checkoutcom-is-register-webhook' ).length ) {
				return;
			}

			// Check if required variables are available
			if ( typeof cko_admin_vars === 'undefined' ) {
				console.error( 'Checkout.com: cko_admin_vars is not defined' );
				return;
			}

			// Use ajaxurl from WordPress global or fallback
			var ajaxUrl = ( typeof ajaxurl !== 'undefined' ) ? ajaxurl : ( cko_admin_vars.ajaxurl || '/wp-admin/admin-ajax.php' );

			$( '.submit .woocommerce-save-button' ).attr( 'disabled', 'disabled' ).hide();


			// Fetch the latest webhooks.
			$( '#checkoutcom-is-register-webhook' ).on( 'click', function () {
				console.log( 'Checkout.com: Webhook check button clicked' );
				var $button = $( this );
				$button.attr( 'disabled', 'disabled' );
				$button.siblings( '.spinner' ).addClass( 'is-active' );
				$( '.checkoutcom-is-register-webhook-text' ).html( '' );
				$( '#checkoutcom-is-register-webhook' ).siblings( '.dashicons-yes' ).addClass( 'hidden' );

				// Check if nonce is available
				if ( ! cko_admin_vars.checkoutcom_check_webhook_nonce ) {
					console.error( 'Checkout.com: Security nonce is missing' );
					$( '.checkoutcom-is-register-webhook-text' ).html( '<span style="color: #d63638;">⚠ Security nonce is missing. Please refresh the page.</span>' );
					$button.prop( 'disabled', false );
					$button.siblings( '.spinner' ).removeClass( 'is-active' );
					return;
				}

				$.ajax( {
					url: ajaxUrl,
					type: 'POST',
					data: {
						'action': 'wc_checkoutcom_check_webhook',
						'security': cko_admin_vars.checkoutcom_check_webhook_nonce
					}
				} ).done( function ( response ) {
					console.log( 'Checkout.com Webhook Check Response:', response );
					if ( response && response.success && response.data && response.data.message ) {
						var message = response.data.message;
						var isConfigured = message.indexOf( 'Webhook is configured' ) !== -1;
						
						if ( isConfigured ) {
							// Show checkmark icon
							$( '#checkoutcom-is-register-webhook' ).siblings( '.dashicons-yes.hidden' ).removeClass( 'hidden' );
							// Change button text to "Refresh Status"
							$button.text( cko_admin_vars.webhook_refresh || 'Refresh Status' );
							// Display message with checkmark prefix
							$( '.checkoutcom-is-register-webhook-text' ).html( '<span style="color: #008000;">✓ ' + message + '</span>' );
						} else {
							// Hide checkmark for errors
							$( '#checkoutcom-is-register-webhook' ).siblings( '.dashicons-yes' ).addClass( 'hidden' );
							// Keep button text as "Run Webhook check"
							$button.text( cko_admin_vars.webhook_check || 'Run Webhook check' );
							// Display error message
							$( '.checkoutcom-is-register-webhook-text' ).html( '<span style="color: #d63638;">⚠ ' + message + '</span>' );
						}
					} else {
						console.error( 'Checkout.com: Invalid response format', response );
						$( '.checkoutcom-is-register-webhook-text' ).html( '<span style="color: #d63638;">⚠ Invalid response from server. Please check console for details.</span>' );
					}

				} ).fail( function ( xhr, status, error ) {
					console.error( 'Checkout.com Webhook Check Error:', status, error, xhr );
					var errorMessage = cko_admin_vars.webhook_check_error || 'An error occurred while checking webhook status. Please try again.';
					if ( xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ) {
						errorMessage = xhr.responseJSON.data.message;
					}
					alert( errorMessage );

				} ).always( function () {
					$( '#checkoutcom-is-register-webhook' ).prop( 'disabled', false );
					$( '#checkoutcom-is-register-webhook' ).siblings( '.spinner' ).removeClass( 'is-active' );
				} );
			} );

			// Auto-check webhook status on page load
			var $webhookContainer = $( '.checkoutcom-is-register-webhook-text' );
			if ( $webhookContainer.length ) {
				// Trigger click after a short delay to ensure DOM is ready
				setTimeout( function() {
					$( '#checkoutcom-is-register-webhook' ).trigger( 'click' );
				}, 500 );
			}

			// Register a new webhook.
			$( '#checkoutcom-register-webhook' ).on( 'click', function () {
				var $button = $( this );
				$button.attr( 'disabled', 'disabled' );
				$button.siblings( '.spinner' ).addClass( 'is-active' );
				$( '#checkoutcom-register-webhook' ).siblings( '.dashicons-yes' ).addClass( 'hidden' );

				// Check if nonce is available
				if ( ! cko_admin_vars.checkoutcom_register_webhook_nonce ) {
					console.error( 'Checkout.com: Security nonce is missing' );
					alert( 'Security nonce is missing. Please refresh the page and try again.' );
					$button.prop( 'disabled', false );
					$button.siblings( '.spinner' ).removeClass( 'is-active' );
					return;
				}

				$.ajax( {
					url: ajaxUrl,
					type: 'POST',
					data: {
						'action': 'wc_checkoutcom_register_webhook',
						'security': cko_admin_vars.checkoutcom_register_webhook_nonce
					}
				} ).done( function ( response ) {
					console.log( 'Checkout.com Webhook Register Response:', response );
					if ( response && response.success ) {
						$( '#checkoutcom-register-webhook' ).siblings( '.dashicons-yes.hidden' ).removeClass( 'hidden' );
						// After successful registration, refresh webhook status
						setTimeout( function() {
							$( '#checkoutcom-is-register-webhook' ).trigger( 'click' );
						}, 500 );
					} else {
						var errorMessage = cko_admin_vars.webhook_register_error || 'An error occurred while registering the webhook. Please try again.';
						if ( response && response.data && response.data.message ) {
							errorMessage = response.data.message;
						}
						alert( errorMessage );
					}

				} ).fail( function ( xhr, status, error ) {
					console.error( 'Checkout.com Webhook Register Error:', status, error, xhr );
					var errorMessage = cko_admin_vars.webhook_register_error || 'An error occurred while registering the webhook. Please try again.';
					if ( xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ) {
						errorMessage = xhr.responseJSON.data.message;
					}
					alert( errorMessage );

				} ).always( function () {
					$( '#checkoutcom-register-webhook' ).prop( 'disabled', false );
					$( '#checkoutcom-register-webhook' ).siblings( '.spinner' ).removeClass( 'is-active' );

				} );
			} );

		},

		toggleSecretKeyVisibility: function () {
			// Find password field for Secret Key - try multiple selectors
			const fieldSelectors = [
				'#woocommerce_wc_checkout_com_cards_ckocom_sk',
				'input[name="woocommerce_wc_checkout_com_cards_settings[ckocom_sk]"]',
				'input[type="password"][id*="ckocom_sk"]',
				'input[type="password"][name*="ckocom_sk"]',
				'tr:has(label:contains("Secret Key")) input[type="password"]'
			];
			
			let field = null;
			for (let i = 0; i < fieldSelectors.length; i++) {
				field = $(fieldSelectors[i]);
				if (field.length && field.attr('type') === 'password') {
					break;
				}
			}
			
			if (!field || !field.length) return;

			// Check if button already exists in the same table cell
			const parentCell = field.closest('td');
			if (parentCell.find('.cko-toggle-password').length) return;

			// Create the toggle button
			const toggleBtn = $('<button type="button" class="button button-secondary cko-toggle-password" style="margin-left: 10px;">View</button>');
			toggleBtn.on('click', function (e) {
				e.preventDefault();
				const currentType = field.attr('type');
				if (currentType === 'password') {
					field.attr('type', 'text');
					toggleBtn.text('Hide');
				} else {
					field.attr('type', 'password');
					toggleBtn.text('View');
				}
			});

			// Append button after the input field
			field.after(toggleBtn);
		},

		expressButtonSizeSettings: function () {
			// Handle button size preset changes for all three payment methods
			const paymentMethods = ['apple_pay', 'google_pay', 'paypal'];
			
			paymentMethods.forEach(function(paymentMethod) {
				const presetField = $('#woocommerce_wc_checkout_com_' + paymentMethod + '_' + paymentMethod + '_express_button_size_preset');
				const customHeightField = $('#woocommerce_wc_checkout_com_' + paymentMethod + '_' + paymentMethod + '_express_button_custom_height');
				
				if (!presetField.length || !customHeightField.length) {
					return;
				}
				
				// Show/hide custom height field based on preset
				function toggleCustomHeight() {
					if (presetField.val() === 'custom') {
						customHeightField.closest('tr').show();
					} else {
						customHeightField.closest('tr').hide();
					}
				}
				
				// Initial state
				toggleCustomHeight();
				
				// On change
				presetField.on('change', toggleCustomHeight);
			});
		},

		checkoutModeToggle: function () {
			// This function is now handled by admin-checkout-mode-toggle.js
			// Keeping this stub for backward compatibility
			// The new script handles everything more robustly
			return;
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

    admin_functions.coreSettings();

	// Script to hide and show fields.
	admin_functions.cardSettings();

	// Initialize webhook settings
	admin_functions.webhookSettings();

	// Toggle secret key visibility - run immediately and after a delay for dynamic content
	admin_functions.toggleSecretKeyVisibility();
	setTimeout(function() {
		admin_functions.toggleSecretKeyVisibility();
	}, 500);

	// Handle express button size settings
	admin_functions.expressButtonSizeSettings();

	// Handle checkout mode toggle for Enabled Payment Methods
	admin_functions.checkoutModeToggle();
} );
