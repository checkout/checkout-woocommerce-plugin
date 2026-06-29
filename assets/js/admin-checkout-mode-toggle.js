/**
 * Checkout Mode Toggle Handler
 * 
 * Handles visibility of payment method fields based on checkout mode selection.
 * Ensures values are preserved when switching modes.
 * 
 * @package wc_checkout_com
 */

(function($) {
	'use strict';

	/**
	 * Checkout Mode Toggle Manager
	 */
	const CheckoutModeToggle = {
		
		/**
		 * Initialize the toggle functionality
		 */
		init: function() {
			const checkoutModeField = $('select[name="woocommerce_wc_checkout_com_cards_settings[ckocom_checkout_mode]"]');
			
			if (!checkoutModeField.length) {
				// Try alternative selectors
				const alt1 = $('select[id*="ckocom_checkout_mode"]');
				
				if (alt1.length) {
					this.initWithField(alt1);
					return;
				}
				
				return; // Field not found, exit early
			}

			this.initWithField(checkoutModeField);
		},
		
		/**
		 * Initialize with a specific field element
		 */
		initWithField: function(checkoutModeField) {
			const currentValue = checkoutModeField.val() || 'flow';
			
			// CRITICAL: Ensure field has correct name attribute for WooCommerce
			// Read directly from DOM to avoid jQuery timing issues
			const fieldElement = checkoutModeField[0];
			const currentName = fieldElement ? fieldElement.getAttribute('name') : checkoutModeField.attr('name');
			const expectedName = 'woocommerce_wc_checkout_com_cards_settings[ckocom_checkout_mode]';
			
			// Only fix if name is actually incorrect (not just missing prefix)
			if (currentName && currentName !== expectedName) {
				if (fieldElement) {
					fieldElement.setAttribute('name', expectedName);
				} else {
					checkoutModeField.attr('name', expectedName);
				}
			} else if (!currentName) {
				// Field name is missing entirely - add it
				if (fieldElement) {
					fieldElement.setAttribute('name', expectedName);
				} else {
					checkoutModeField.attr('name', expectedName);
				}
			}
			
			// Set initial state based on current value
			this.setVisibility(currentValue);
			
			// Handle mode changes
			checkoutModeField.on('change', function() {
				const mode = $(this).val() || 'flow';
				
				// Ensure field name is still correct after change
				const fieldName = $(this).attr('name');
				if (fieldName !== expectedName) {
					$(this).attr('name', expectedName);
				}
				
				CheckoutModeToggle.setVisibility(mode);
			});

			// Also handle when WooCommerce re-renders fields
			$(document).on('woocommerce_settings_saved', function() {
				setTimeout(function() {
					const currentMode = checkoutModeField.val() || 'flow';
					CheckoutModeToggle.setVisibility(currentMode);
				}, 100);
			});
		},

		/**
		 * Set visibility of payment method fields based on checkout mode
		 * 
		 * @param {string} mode - 'flow' or 'classic'
		 */
		setVisibility: function(mode) {
			// Find fields using data attributes (most reliable)
			const flowField1 = $('select[data-field-type="flow-enabled-methods"]');
			const flowField2 = $('select.cko-flow-payment-methods');
			const flowField3 = $('select[id*="flow_enabled_payment_methods"]');
			const flowField = flowField1.length ? flowField1 : (flowField2.length ? flowField2 : flowField3);
			
			const apmField1 = $('select[data-field-type="alternative-payment-methods"]');
			const apmField2 = $('select.cko-classic-payment-methods');
			const apmField3 = $('select[id*="ckocom_apms_selector"]');
			const apmField = apmField1.length ? apmField1 : (apmField2.length ? apmField2 : apmField3);
			
			// Find parent table rows
			const flowRow = flowField.closest('tr');
			const apmRow = apmField.closest('tr');
			
			// Ensure save button is always visible
			this.ensureSaveButtonVisible();

			// Standalone Flow component fields (tickboxes + order selects) + their section title.
			const standaloneEls = $('[data-checkout-mode="flow"][data-cko-component], [data-checkout-mode="flow"][data-cko-order]');
			const standaloneRows = standaloneEls.closest('tr');
			const componentsTitle = $('#flow_components_title');

			if (mode === 'flow') {
				// Flow mode: Show Enabled Payment Methods, Hide Alternative Payment Methods
				if (flowRow.length) {
					flowRow.css('display', 'table-row').show();
				}
				if (apmRow.length) {
					apmRow.css('display', 'none').hide();
				}
				// Show the standalone component section, then apply the component-specific rules.
				standaloneRows.css('display', 'table-row').show();
				componentsTitle.show().nextUntil('table, h3').show();
				this.refreshComponentRows();
			} else if (mode === 'classic') {
				// Classic mode: Hide Enabled Payment Methods, Show Alternative Payment Methods
				if (flowRow.length) {
					flowRow.css('display', 'none').hide();
				}
				if (apmRow.length) {
					apmRow.css('display', 'table-row').show();
				}
				// Hide the entire standalone component section.
				standaloneRows.css('display', 'none').hide();
				componentsTitle.hide().nextUntil('table, h3').hide();
			}

			// Double-check save button visibility
			this.ensureSaveButtonVisible();
		},

		/**
		 * Apply component-selection rules within Flow mode:
		 * - When "Flow" (all-in-one) is ticked, hide the individual method tickboxes and order selects
		 *   (Checkout.com owns the ordering) and show an explanatory note.
		 * - Otherwise show the individual tickboxes; each method's order select is visible only when ticked.
		 */
		refreshComponentRows: function() {
			const self = this;
			const components = ['card', 'googlepay', 'applepay'];
			const flowCheckbox = $('input[data-cko-component="flow"]');
			const flowChecked = flowCheckbox.is(':checked');

			components.forEach(function(name) {
				const cb = $('input[data-cko-component="' + name + '"]');
				const cbRow = cb.closest('tr');
				const orderRow = $('select[data-cko-order="' + name + '"]').closest('tr');

				if (flowChecked) {
					cbRow.hide();
					orderRow.hide();
				} else {
					cbRow.css('display', 'table-row').show();
					if (cb.is(':checked')) {
						orderRow.css('display', 'table-row').show();
					} else {
						orderRow.hide();
					}
				}
			});

			self.toggleFlowNote(flowChecked);
			self.enforceOrderUniqueness();
		},

		/**
		 * Enforce unique First/Second/Third positions across the ticked standalone components.
		 * Disables order options already taken by another ticked component and surfaces a warning
		 * if a clash or empty selection state is detected.
		 */
		enforceOrderUniqueness: function() {
			const components = ['card', 'googlepay', 'applepay'];
			const flowChecked = $('input[data-cko-component="flow"]').is(':checked');

			// Count chosen positions among ticked components to detect duplicates.
			const counts = {};
			let checkedCount = 0;
			components.forEach(function(name) {
				if ($('input[data-cko-component="' + name + '"]').is(':checked')) {
					checkedCount++;
					const v = $('select[data-cko-order="' + name + '"]').val();
					counts[v] = (counts[v] || 0) + 1;
				}
			});
			let duplicate = false;
			Object.keys(counts).forEach(function(v) {
				if (counts[v] > 1) {
					duplicate = true;
				}
			});

			// Disable, on each select, the positions already taken by a different ticked component.
			components.forEach(function(name) {
				const cb = $('input[data-cko-component="' + name + '"]');
				const sel = $('select[data-cko-order="' + name + '"]');
				const myVal = sel.val();
				sel.find('option').each(function() {
					const optVal = $(this).val();
					let takenByOther = false;
					components.forEach(function(other) {
						if (other === name) {
							return;
						}
						const ocb = $('input[data-cko-component="' + other + '"]');
						if (ocb.is(':checked') && $('select[data-cko-order="' + other + '"]').val() === optVal) {
							takenByOther = true;
						}
					});
					$(this).prop('disabled', takenByOther && optVal !== myVal);
				});
			});

			// Warn on duplicate positions, or when no method is selected at all in Flow mode.
			let message = '';
			if (!flowChecked && checkedCount === 0) {
				message = 'Select at least one payment method (or tick "Flow" to show all).';
			} else if (duplicate) {
				message = 'Each payment method must have a unique display order (First, Second, Third).';
			}
			this.toggleOrderWarning(message);
		},

		/**
		 * Show/hide the note explaining that Checkout.com controls ordering when "Flow" is selected.
		 */
		toggleFlowNote: function(show) {
			let note = $('#cko-flow-all-note');
			if (!note.length) {
				const flowRow = $('input[data-cko-component="flow"]').closest('tr');
				if (!flowRow.length) {
					return;
				}
				flowRow.after('<tr id="cko-flow-all-note"><td colspan="2"><em>' +
					'When "Flow" is selected, all payment methods are shown and Checkout.com controls the display order.' +
					'</em></td></tr>');
				note = $('#cko-flow-all-note');
			}
			if (show) {
				note.show();
			} else {
				note.hide();
			}
		},

		/**
		 * Show/hide an inline warning message for the ordering controls.
		 */
		toggleOrderWarning: function(message) {
			let warn = $('#cko-flow-order-warning');
			if (!warn.length) {
				const anchorRow = $('select[data-cko-order="applepay"]').closest('tr');
				if (!anchorRow.length) {
					return;
				}
				anchorRow.after('<tr id="cko-flow-order-warning"><td colspan="2">' +
					'<span style="color:#b32d2e;"></span></td></tr>');
				warn = $('#cko-flow-order-warning');
			}
			if (message) {
				warn.find('span').text(message);
				warn.show();
			} else {
				warn.hide();
			}
		},

		/**
		 * Ensure save button is always visible
		 */
		ensureSaveButtonVisible: function() {
			const saveButton = $('.submit .woocommerce-save-button, .woocommerce-save-button, button[name="save"], p.submit button[type="submit"]');
			if (saveButton.length) {
				saveButton.show();
				saveButton.css('display', '');
				saveButton.closest('tr').show();
				saveButton.closest('p.submit').show();
				saveButton.closest('.submit').show();
			}
		}
	};

	// Initialize when DOM is ready
	$(document).ready(function() {
		// Wait a bit for WooCommerce to render fields
		setTimeout(function() {
			CheckoutModeToggle.init();
		}, 300);
	});

	// Also initialize immediately if DOM is already ready
	if (document.readyState === 'complete' || document.readyState === 'interactive') {
		setTimeout(function() {
			CheckoutModeToggle.init();
		}, 100);
	}

	// Also initialize on tab changes
	$(document).on('click', '.woocommerce-nav-tab', function() {
		setTimeout(function() {
			CheckoutModeToggle.init();
		}, 500);
	});
	
	// Listen for any select changes (in case WooCommerce re-renders)
	$(document).on('change', 'select[name*="ckocom_checkout_mode"], select[id*="ckocom_checkout_mode"]', function() {
		const mode = $(this).val() || 'flow';
		CheckoutModeToggle.setVisibility(mode);
	});

	// Standalone Flow component tickboxes: re-evaluate visibility/order rules on change.
	$(document).on('change', 'input[data-cko-component]', function() {
		CheckoutModeToggle.refreshComponentRows();
	});

	// Per-component order selects: re-enforce unique First/Second/Third positions on change.
	$(document).on('change', 'select[data-cko-order]', function() {
		CheckoutModeToggle.enforceOrderUniqueness();
	});
	
	// CRITICAL: Fix field name on form submit to ensure it's saved
	$(document).on('submit', 'form#mainform', function(e) {
		console.log('[CKO JS DEBUG] ===== FORM SUBMIT DETECTED =====');
		console.log('[CKO JS DEBUG] Form action:', $(this).attr('action'));
		console.log('[CKO JS DEBUG] Form method:', $(this).attr('method'));
		console.log('[CKO JS DEBUG] Current URL:', window.location.href);
		console.log('[CKO JS DEBUG] URL params:', new URLSearchParams(window.location.search).toString());
		
		// Check if keys are in form and fix their names
		const secretKeyField = $('input[name*="ckocom_sk"], input[id*="ckocom_sk"]').first();
		const publicKeyField = $('input[name*="ckocom_pk"], input[id*="ckocom_pk"]').first();
		console.log('[CKO JS DEBUG] Secret Key field found:', secretKeyField.length > 0);
		console.log('[CKO JS DEBUG] Secret Key field name:', secretKeyField.length > 0 ? secretKeyField.attr('name') : 'NOT FOUND');
		console.log('[CKO JS DEBUG] Secret Key value length:', secretKeyField.length > 0 ? secretKeyField.val().length : 0);
		console.log('[CKO JS DEBUG] Public Key field found:', publicKeyField.length > 0);
		console.log('[CKO JS DEBUG] Public Key field name:', publicKeyField.length > 0 ? publicKeyField.attr('name') : 'NOT FOUND');
		console.log('[CKO JS DEBUG] Public Key value:', publicKeyField.length > 0 ? publicKeyField.val().substring(0, 10) + '...' : 'NOT FOUND');
		
		// CRITICAL FIX: Ensure Secret Key field has correct name attribute
		if (secretKeyField.length > 0) {
			const expectedSecretKeyName = 'woocommerce_wc_checkout_com_cards_settings[ckocom_sk]';
			const currentSecretKeyName = secretKeyField.attr('name');
			if (currentSecretKeyName !== expectedSecretKeyName) {
				console.log('[CKO JS DEBUG] Fixing Secret Key field name from "' + currentSecretKeyName + '" to "' + expectedSecretKeyName + '"');
				secretKeyField.attr('name', expectedSecretKeyName);
			}
		}
		
		// CRITICAL FIX: Ensure Public Key field has correct name attribute
		if (publicKeyField.length > 0) {
			const expectedPublicKeyName = 'woocommerce_wc_checkout_com_cards_settings[ckocom_pk]';
			const currentPublicKeyName = publicKeyField.attr('name');
			if (currentPublicKeyName !== expectedPublicKeyName) {
				console.log('[CKO JS DEBUG] Fixing Public Key field name from "' + currentPublicKeyName + '" to "' + expectedPublicKeyName + '"');
				publicKeyField.attr('name', expectedPublicKeyName);
			}
		}
		
		// Also check Environment and Title fields
		const environmentField = $('select[name*="ckocom_environment"], select[id*="ckocom_environment"]').first();
		const titleField = $('input[name*="title"], input[id*="title"]').filter(function() {
			return $(this).closest('tr').find('th').text().indexOf('Payment Method Title') !== -1;
		}).first();
		
		if (environmentField.length > 0) {
			const expectedEnvName = 'woocommerce_wc_checkout_com_cards_settings[ckocom_environment]';
			const currentEnvName = environmentField.attr('name');
			if (currentEnvName !== expectedEnvName) {
				console.log('[CKO JS DEBUG] Fixing Environment field name from "' + currentEnvName + '" to "' + expectedEnvName + '"');
				environmentField.attr('name', expectedEnvName);
			}
		}
		
		if (titleField.length > 0) {
			const expectedTitleName = 'woocommerce_wc_checkout_com_cards_settings[title]';
			const currentTitleName = titleField.attr('name');
			if (currentTitleName !== expectedTitleName) {
				console.log('[CKO JS DEBUG] Fixing Title field name from "' + currentTitleName + '" to "' + expectedTitleName + '"');
				titleField.attr('name', expectedTitleName);
			}
		}
		
		// Find the checkout mode field with any selector
		const modeField = $('select[name*="ckocom_checkout_mode"], select[id*="ckocom_checkout_mode"]').first();
		
		if (!modeField.length) {
			return; // Can't fix if field doesn't exist
		}
		
		const modeValue = modeField.val() || 'flow';
		const currentName = modeField.attr('name');
		const expectedName = 'woocommerce_wc_checkout_com_cards_settings[ckocom_checkout_mode]';
		
		// Ensure field is visible for submission
		modeField.closest('tr').show();
		modeField.show();
		
		// Fix field name if incorrect
		if (currentName !== expectedName) {
			modeField.attr('name', expectedName);
		}
		
		// Verify the value will be submitted
		const formData = new FormData(this);
		const submittedValue = formData.get(expectedName);
		
		// If value is not in FormData, add hidden input as backup
		if (!submittedValue && modeValue) {
			const form = $(this);
			// Remove any existing hidden input
			form.find('input[type="hidden"][name="' + expectedName + '"]').remove();
			// Add new hidden input
			$('<input>').attr({
				type: 'hidden',
				name: expectedName,
				value: modeValue
			}).appendTo(form);
		}
	});

})(jQuery);
