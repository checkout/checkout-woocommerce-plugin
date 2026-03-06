/**
 * Flow Field Change Handler Module
 *
 * Attaches debounced event listeners to checkout inputs to update flow state
 * and cart info dynamically.
 *
 * Dependencies: jQuery, flow-logger.js, flow-state.js
 *
 * @module FlowFieldChangeHandler
 */

(function () {
	'use strict';

	let initialized = false;
	let virtual = false;
	
	// Global flag to prevent update_checkout during initial page load
	// This prevents infinite loops caused by field change events during initialization
	let pageInitializationComplete = false;

	function initHandlers() {
		if (typeof jQuery === 'undefined') {
			return;
		}

		if (typeof debounce === 'undefined') {
			ckoLogger.warn('FlowFieldChangeHandler: debounce not available');
			return;
		}
		
		// Mark page initialization as complete after a delay
		// This allows all initial field population and WooCommerce setup to complete
		setTimeout(function() {
			pageInitializationComplete = true;
			if (typeof ckoLogger !== 'undefined') {
				ckoLogger.debug('[FIELD HANDLER] Page initialization complete - update_checkout now enabled');
			}
		}, 3000);

		const storeFieldValue = function(fieldId, value) {
			if (!window.ckoFlowFieldValues) {
				window.ckoFlowFieldValues = {};
			}
			window.ckoFlowFieldValues[fieldId] = value;
		};
		
		const triggerUpdateCheckoutDebounced = function(context) {
			// Don't trigger update_checkout during page initialization
			if (!pageInitializationComplete) {
				if (typeof ckoLogger !== 'undefined') {
					ckoLogger.debug('[FIELD HANDLER] SKIPPING update_checkout - page still initializing', context || {});
				}
				return;
			}
			
			if (!window.ckoUpdateCheckoutDebounce) {
				window.ckoUpdateCheckoutDebounce = null;
			}
			
			if (window.ckoUpdateCheckoutDebounce) {
				clearTimeout(window.ckoUpdateCheckoutDebounce);
			}
			
			window.ckoUpdateCheckoutDebounce = setTimeout(function () {
				if (typeof ckoLogger !== 'undefined') {
					ckoLogger.debug('Triggering update_checkout after debounce', context || {});
				}
				jQuery('body').trigger('update_checkout');
				window.ckoUpdateCheckoutDebounce = null;
			}, 100);
		};
		
		// Set up handlers for critical fields to immediately update ckoFlowFieldValues
		// NOTE: billing_email is NOT included here - it has its own handler with debouncing
		// that needs to compare old vs new values before the value is stored
		const criticalFieldSelectors = [
			'#billing_country',
			'#billing_address_1',
			'#billing_city',
			'#billing_postcode'
		];
		criticalFieldSelectors.forEach((selector) => {
			jQuery(document).on('change', selector, function () {
				const fieldId = this.id;
				const value = jQuery(this).val();
				storeFieldValue(fieldId, value);
				ckoLogger.debug(`Critical field ${fieldId} changed - stored value: ${value}`);
			});
		});

	// Immediate check for postcode changes (some checkout UIs don't fire all events consistently)
	const immediateSelectors = [
		'#billing_postcode',
		'[name="billing_postcode"]',
		'[data-key="billing_postcode"] input',
		'[data-key="billing_postcode"] select',
		'[data-key="billing_postcode"] textarea',
		'#shipping_postcode',
		'[name="shipping_postcode"]',
		'[data-key="shipping_postcode"] input',
		'[data-key="shipping_postcode"] select',
		'[data-key="shipping_postcode"] textarea'
	];
	jQuery(document).on('input change', immediateSelectors.join(', '), function () {
		if (typeof checkRequiredFieldsStatus === 'function') {
			checkRequiredFieldsStatus();
		}
	});

		// Add a direct handler for billing country changes to update cko_flow_vars
		// This ensures the flow component is updated with the correct country for APMs
		jQuery('#billing_country').on('change', function () {
			// Store the country in global state for Flow initialization
			if (typeof cko_flow_vars !== 'undefined') {
				cko_flow_vars.billing_country = jQuery(this).val();
			}
			// Also update stored value
			if (!window.ckoFlowFieldValues) {
				window.ckoFlowFieldValues = {};
			}
			window.ckoFlowFieldValues['billing_country'] = jQuery(this).val();

			ckoLogger.debug('Billing country updated in global state:', jQuery(this).val());
		});

		jQuery(function ($) {
			/**
			 * Main handler function triggered on input/change events.
			 * It performs field checks and updates the checkout flow and cart info accordingly.
			 *
			 * @param {Event} event - The input or change event.
			 */
		const handleTyping = (event) => {
				// Early return for hidden fields and cko-flow internal fields to prevent infinite loops
				if (event && event.target) {
					const targetType = event.target.type || '';
					const targetId = event.target.id || '';
					const targetName = event.target.name || '';
					
					// Skip hidden fields entirely
					if (targetType === 'hidden') {
						return;
					}
					
					// Skip cko-flow internal fields (e.g., cko-flow-save-card-persist)
					if (targetId.startsWith('cko-flow') || targetName.startsWith('cko-flow') ||
					    targetId.startsWith('cko_flow') || targetName.startsWith('cko_flow')) {
						return;
					}
					
					// Skip coupon fields - WooCommerce handles coupon application separately
					// and our field handler shouldn't trigger additional update_checkout calls
					if (targetId === 'coupon_code' || targetName === 'coupon_code') {
						return;
					}
				}
				
				let isShippingField = false;

				// Check if the changed field belongs to shipping info.
				if (event) {
					const $target = jQuery(event.target);
					isShippingField = $target.is('[id^="shipping"]');
				}

				// If the field is a shipping-related field, trigger WooCommerce checkout update and exit early.
				if (isShippingField) {
					ckoLogger.debug('Triggered by a shipping field, skipping...');
					triggerUpdateCheckoutDebounced({
						fieldName: event.target.name || 'unknown',
						fieldId: event.target.id || 'unknown',
						reason: 'shipping-field'
					});
					return;
				}

				// Fields that REQUIRE Flow reload (cardholder name is set at component creation)
				const reloadRequiredFields = [
					'billing_first_name',  // Cardholder name - requires Flow reload
					'billing_last_name',   // Cardholder name - requires Flow reload
					'billing_email'        // Email is in payment session, requires reload
				];
				
				// Address fields that do NOT require Flow reload (sent via handleSubmit)
				// These are handled dynamically via Submit Payment Session API
				const addressFields = [
					'billing_country',
					'billing_address_1',
					'billing_city',
					'billing_postcode',
					'billing_state',
					'billing_phone'
				];

				const fieldName = event.target.name || event.target.id || '';
				const fieldId = event.target.id || '';
				
				const requiresReload = reloadRequiredFields.some(
					(field) =>
						fieldName.includes(field.replace('billing_', '')) ||
						fieldId.includes(field.replace('billing_', ''))
				);
				
				const isAddressField = addressFields.some(
					(field) =>
						fieldName.includes(field.replace('billing_', '')) ||
						fieldId.includes(field.replace('billing_', ''))
				);
				
				const isCriticalField = requiresReload || isAddressField;

				// Store previous values to detect changes
				// Flag to track if we should skip update_checkout (for name fields when position is hidden)
				let shouldSkipUpdateCheckout = false;
				
				if (isCriticalField && FlowState.get('initialized')) {
					const fieldKey = fieldId || fieldName;
					
					// Special handling for email - use captured context from event time
					// This is needed because the debounce delay means stored values might change
					let currentValue, previousValue;
					
					if (event.ckoEmailContext && fieldKey === 'billing_email') {
						// Use the context captured at the moment of the input event
						currentValue = event.ckoEmailContext.currentValue;
						previousValue = event.ckoEmailContext.previousValue;
						ckoLogger.debug('[EMAIL HANDLER] Using captured context in handleTyping', {
							currentValue: currentValue,
							previousValue: previousValue,
							capturedAt: event.ckoEmailContext.capturedAt,
							processedAt: Date.now(),
							debounceDelay: Date.now() - event.ckoEmailContext.capturedAt
						});
					} else {
						// Standard behavior for other fields
						currentValue = event.target.value || '';
						previousValue = (window.ckoFlowFieldValues || {})[fieldKey] || '';
					}

					// If value actually changed (not just typing)
					if (currentValue !== previousValue) {
						// Update stored value
						storeFieldValue(fieldKey, currentValue);

						// CRITICAL FIX: Don't trigger reload if Flow was just initialized (within 3 seconds)
						// This prevents double payment session creation when filling the last required field
						const lastInitTime = FlowState.get('lastInitTime');
						const flowJustInitialized = lastInitTime && (Date.now() - lastInitTime) < 3000;
						
						if (flowJustInitialized) {
							if (typeof ckoLogger !== 'undefined') {
								ckoLogger.debug(`[FIELD CHANGE] Skipping reload for "${fieldKey}" - Flow just initialized`, {
									timeSinceInit: Date.now() - lastInitTime,
									threshold: 3000
								});
							}
							return;
						}

						// Address fields do NOT require Flow reload - they are sent via handleSubmit
						// to the Submit Payment Session API dynamically
						if (isAddressField) {
							if (typeof ckoLogger !== 'undefined') {
								ckoLogger.debug(`[FIELD CHANGE] Address field "${fieldKey}" changed - will be sent via handleSubmit, no reload needed`, {
									newValue: currentValue,
									previousValue: previousValue
								});
							}
							// Store the change but don't reload Flow
							// The address will be sent to Checkout.com via handleSubmit when payment is submitted
							return;
						}

						// Only reload Flow for fields that require it (name, email)
						if (requiresReload) {
							if (typeof ckoLogger !== 'undefined') {
								ckoLogger.debug(`[FIELD CHANGE] Field "${fieldKey}" requires Flow reload`, {
									newValue: currentValue,
									previousValue: previousValue
								});
							}
							debouncedCheckFlowReload(fieldKey, currentValue);
						}
					} else {
						// Log when no change detected (helps debug)
						if (fieldKey === 'billing_email') {
							ckoLogger.debug('[EMAIL HANDLER] No change detected - skipping reload', {
								currentValue: currentValue,
								previousValue: previousValue,
								hadContext: !!event.ckoEmailContext
							});
						}
					}
				}

				// Only proceed if all required fields are filled.
				if (requiredFieldsFilled()) {
					// Skip update_checkout if this is a name field (prevents Flow reload)
					if (!shouldSkipUpdateCheckout) {
						// CRITICAL: Don't trigger update_checkout immediately after Flow initialization
						// This prevents the duplicate init cycle: init → update_checkout → container destroyed → remount
						const flowJustInitialized = FlowState.get('initialized') && 
							FlowState.get('lastInitTime') && 
							(Date.now() - FlowState.get('lastInitTime')) < 3000; // 3 seconds
						
						if (flowJustInitialized) {
							if (typeof ckoLogger !== 'undefined') {
								ckoLogger.debug('[FIELD CHANGE] Skipping update_checkout - Flow just initialized', {
									timeSinceInit: Date.now() - FlowState.get('lastInitTime'),
									fieldName: event.target.name,
									fieldId: event.target.id
								});
							}
							return;
						}
						
						// Debounce update_checkout triggers to prevent cascading reloads
						triggerUpdateCheckoutDebounced({
							fieldName: event.target.name || 'unknown',
							fieldId: event.target.id || 'unknown',
							reason: 'critical-field'
						});
					} else {
						if (typeof ckoLogger !== 'undefined') {
							ckoLogger.debug('[CARDHOLDER NAME] Skipping update_checkout to prevent Flow reload');
						}
					}

					// If the event is from checking 'ship to different address' or 'create account', return early.
					if (
						jQuery(event.target).is('#ship-to-different-address-checkbox, #createaccount') &&
						jQuery(event.target).is(':checked')
					) {
						ckoLogger.debug('User just checked the checkbox. Returning early...');
						return;
					}

					const targetName = event.target.name || '';

					// If the event is not from billing fields or key checkboxes, exit early.
					// Note: coupon_code is now excluded at the top of handleTyping - WooCommerce handles it separately
					if (
						!targetName.startsWith('billing') &&
						!jQuery(event.target).is(
							'#ship-to-different-address-checkbox, #terms, #createaccount'
						)
					) {
						const cartData = $('#cart-info').data('cart');
						if (!cartData || !cartData['contains_virtual_product']) {
							ckoLogger.debug('Neither billing nor the shipping checkbox. Returning early...');
							return;
						}
						ckoLogger.debug('Virtual Product found. Triggering FLOW...');
					}

					// Check required fields status (for initialization/reload)
					checkRequiredFieldsStatus();
				} else {
					// Required fields not filled - check status anyway
					// This ensures Flow initializes when fields become filled
					checkRequiredFieldsStatus();
				}

				// Always check field status on any field change (for initialization)
				// This ensures Flow initializes as soon as all fields are filled
				if (!FlowState.get('initialized')) {
					checkRequiredFieldsStatus();

					// Also directly check if we can initialize now
					if (canInitializeFlow()) {
						ckoLogger.debug('Field change detected - all fields now valid, initializing Flow');
						initializeFlowIfNeeded();
					}
				}
			};

			// Debounce the handler to limit how often it's triggered during typing.
			const debouncedTyping = debounce(handleTyping, 2000);

			// Special handler for name fields - use 'blur' instead of 'input' to avoid reloading during typing
			// This prevents clearing card details while user is still editing the name
			$('#billing_first_name, #billing_last_name').on(
				'blur',
				function (e) {
					// Only trigger if value actually changed
					const fieldId = this.id;
					const currentValue = this.value || '';
					const previousValue = window.ckoFlowFieldValues?.[fieldId] || '';
					
					if (currentValue !== previousValue && currentValue.trim() !== '') {
						debouncedTyping(e);
					}
				}
			);

			// Special handler for billing_email - use 'blur' instead of 'input' (like name fields)
			// This prevents clearing card details while user is still typing their email
			// and ensures reliable detection of value changes
			$('#billing_email').on(
				'blur',
				function (e) {
					const fieldId = this.id;
					const currentValue = this.value || '';
					const previousValue = window.ckoFlowFieldValues?.[fieldId] || '';
					
					ckoLogger.debug('[EMAIL HANDLER] Blur event - checking for changes', {
						fieldId: fieldId,
						previousValue: previousValue,
						currentValue: currentValue,
						changed: currentValue !== previousValue
					});
					
					// Only trigger if value actually changed and is not empty
					if (currentValue !== previousValue && currentValue.trim() !== '') {
						// Store context with the event for later use in handleTyping
						e.ckoEmailContext = {
							fieldId: fieldId,
							currentValue: currentValue,
							previousValue: previousValue,
							capturedAt: Date.now()
						};
						
						ckoLogger.debug('[EMAIL HANDLER] Email changed - triggering Flow reload', {
							fieldId: fieldId,
							previousValue: previousValue,
							currentValue: currentValue
						});
						
						debouncedTyping(e);
					}
				}
			);
			
			// Attach debounced handler to billing_phone - still use 'input'
			$('#billing_phone').on(
				'input',
				function (e) {
					debouncedTyping(e);
				}
			);

			// Attach to all other inputs/selects, excluding the key billing fields above.
			// EXCLUDE CHECKBOXES - they don't need to trigger Flow updates and cause form reload issues
			// EXCLUDE hidden fields and cko-flow-* fields to prevent infinite loops
			$(
				"input:not(#billing_first_name, #billing_last_name, #billing_email, #billing_phone):not([type='checkbox']):not([type='hidden']):not([id^='cko-flow']):not([name^='cko-flow']), select"
			).on('input change', function (e) {
				debouncedTyping(e);
			});

			// Attach handler to all input/selects, but ignore payment method fields.
			// EXCLUDE CHECKBOXES - they don't need to trigger Flow updates and cause form reload issues
			// EXCLUDE hidden fields and cko-flow-* fields to prevent infinite loops
			// EXCLUDE billing_email - it has its own dedicated handler that captures context for debouncing
			$(document).on('input change', "input:not([type='checkbox']):not([type='hidden']):not([id^='cko-flow']):not([name^='cko-flow']):not(#billing_email), select", function (e) {
				if ($(this).closest('.wc_payment_method').length === 0) {
					debouncedTyping(e);
				}

				const cartData = $('#cart-info').data('cart');
				if (
					!virtual &&
					cartData &&
					cartData['contains_virtual_product'] &&
					$('#ship-to-different-address-checkbox').length === 0
				) {
					debouncedTyping(e);
					virtual = true;
				}
			});

			// Handle checkboxes that legitimately need to trigger update_checkout
			// These checkboxes change the checkout form structure (shipping fields, account creation)
			// Note: Terms checkboxes are handled separately above and do NOT trigger update_checkout
			// Use a flag to prevent triggering during page initialization
			let checkboxHandlersReady = false;
			setTimeout(function() {
				checkboxHandlersReady = true;
			}, 3000); // Wait 3 seconds after page load before enabling checkbox handlers
			
			jQuery(document).on(
				'change',
				'#ship-to-different-address-checkbox, #createaccount',
				function (e) {
					// Skip if this is during page initialization (not user-triggered)
					if (!checkboxHandlersReady) {
						ckoLogger.debug('Checkbox change detected during initialization - SKIPPING update_checkout', {
							checkboxId: this.id,
							checked: this.checked
						});
						return;
					}
					
					// Also skip if this wasn't triggered by user interaction (isTrusted check)
					if (e.originalEvent && !e.originalEvent.isTrusted) {
						ckoLogger.debug('Checkbox change detected (programmatic) - SKIPPING update_checkout', {
							checkboxId: this.id,
							checked: this.checked
						});
						return;
					}
					
					ckoLogger.debug('Checkbox change detected - triggering update_checkout', {
						checkboxId: this.id,
						checked: this.checked
					});
					// These checkboxes legitimately need to trigger update_checkout
					// They show/hide form fields that require checkout refresh
					jQuery('body').trigger('update_checkout');
				}
			);

			// Watch country field specifically for Flow reload
			jQuery('#billing_country').on('change.flow-reload', function () {
				ckoLogger.debug('Billing country changed - will reload Flow after checkout update');

				// Wait for WooCommerce to update fields
				jQuery(document).one('updated_checkout', function () {
					// Small delay to ensure fields are updated
					setTimeout(function () {
						if (FlowState.get('initialized') && ckoFlow.flowComponent) {
							// Store country value
							const countryValue = jQuery('#billing_country').val();
							if (!window.ckoFlowFieldValues) {
								window.ckoFlowFieldValues = {};
							}
							window.ckoFlowFieldValues['billing_country'] = countryValue;

							// Reload Flow
							reloadFlowComponent();
						} else if (canInitializeFlow()) {
							initializeFlowIfNeeded();
						}
					}, 500);
				});
			});

		});
	}

	window.FlowFieldChangeHandler = {
		init: function () {
			if (initialized) {
				return;
			}
			initialized = true;
			initHandlers();
		}
	};
})();
