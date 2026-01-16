/*
 * REFACTORED: Logger module extracted to modules/flow-logger.js
 * 
 * The logger is now loaded as a separate module before this file.
 * This reduces complexity and improves maintainability.
 * 
 * Module location: flow-integration/assets/js/modules/flow-logger.js
 * 
 * Fallback: If logger module didn't load (shouldn't happen), use basic console methods
 */
if (typeof window.ckoLogger === 'undefined') {
	console.warn('[FLOW] Logger module not loaded - using fallback logger');
	window.ckoLogger = {
		debugEnabled: (typeof cko_flow_vars !== 'undefined' && cko_flow_vars.debug_logging) || false,
		error: function(m, d) { console.error('[FLOW ERROR] ' + m, d !== undefined ? d : ''); },
		warn: function(m, d) { console.warn('[FLOW WARNING] ' + m, d !== undefined ? d : ''); },
		webhook: function(m, d) { console.log('[FLOW WEBHOOK] ' + m, d !== undefined ? d : ''); },
		threeDS: function(m, d) { console.log('[FLOW 3DS] ' + m, d !== undefined ? d : ''); },
		payment: function(m, d) { console.log('[FLOW PAYMENT] ' + m, d !== undefined ? d : ''); },
		version: function(v) { console.log('🚀 Checkout.com Flow v' + v); },
		debug: function(m, d) { 
			if (this.debugEnabled) {
				console.log('[FLOW DEBUG] ' + m, d !== undefined ? d : '');
			}
		},
		performance: function(m, d) { 
			if (this.debugEnabled) {
				console.log('[FLOW PERFORMANCE] ' + m, d !== undefined ? d : '');
			}
		}
	};
}

/*
 * REFACTORED: Early 3DS detection extracted to modules/flow-3ds-detection.js
 *
 * This module runs before payment-session.js to flag 3DS returns.
 */

/*
 * REFACTORED: Terms checkbox prevention extracted to modules/flow-terms-prevention.js
 * 
 * The terms checkbox prevention logic is now loaded as a separate module before this file.
 * This reduces complexity and improves maintainability.
 * 
 * Module location: flow-integration/assets/js/modules/flow-terms-prevention.js
 * 
 * The module exposes:
 * - window.isTermsCheckbox() - Helper function (used in updated_checkout handler)
 * - window.ckoPreventUpdateCheckout - Prevention flag
 * - window.ckoTermsCheckboxLastClicked - Last clicked checkbox reference
 * - window.ckoTermsCheckboxLastClickTime - Timestamp of last click
 * 
 * Fallback: If module didn't load, isTermsCheckbox will be undefined and updated_checkout
 * handler will skip terms checkbox checks (graceful degradation)
 */
// Ensure isTermsCheckbox is available (should be set by module, but check for safety)
if (typeof window.isTermsCheckbox === 'undefined') {
	console.warn('[FLOW] Terms prevention module not loaded - isTermsCheckbox unavailable');
	// Fallback function (minimal implementation)
	window.isTermsCheckbox = function(element) {
		if (!element || element.type !== 'checkbox') return false;
		const id = (element.id || '').toLowerCase();
		const name = (element.name || '').toLowerCase();
		return id.includes('terms') || id.includes('agree') || 
		       name.includes('terms') || name.includes('agree');
	};
}

/*
 * The main object managing the Checkout.com flow payment integration.
 */
var ckoFlow = {
	flowComponent: null, // Holds the reference to the Checkout Web Component.
	performanceMetrics: {
		pageLoadTime: null,
		flowInitStartTime: null,
		flowReadyTime: null,
	},

	/*
	 * Initializes the payment flow by loading the component.
	 */
	init: () => {
		// Don't initialize if 3DS return is in progress
		if (FlowState.get('is3DSReturn')) {
			ckoLogger.threeDS('Skipping ckoFlow.init() - 3DS return in progress');
			return;
		}
		
		// Mark when Flow initialization starts
		ckoFlow.performanceMetrics.flowInitStartTime = performance.now();
		ckoLogger.performance('Flow initialization started');
		ckoFlow.loadFlow();
	},

	/*
	 * Loads the Checkout.com payment flow by collecting cart and user info,
	 * creating a payment session, and mounting the Checkout component.
	 */
	loadFlow: async () => {
		// REFACTORED: Use initialization helper to validate prerequisites
		if (typeof window.FlowInitialization !== 'undefined' && window.FlowInitialization.validatePrerequisites) {
			const validation = window.FlowInitialization.validatePrerequisites();
			if (!validation.isValid) {
				if (validation.reason === 'FIELDS_NOT_FILLED') {
					ckoLogger.debug('loadFlow: Required fields not filled - showing waiting message and aborting');
					showFlowWaitingMessage();
					// Reset initialization state so Flow can retry when fields are filled
					FlowState.set('initialized', false);
					FlowState.set('initializing', false);
				}
				return; // Exit - don't proceed with payment session creation
			}
		} else {
			// Fallback to original validation if helper not available
			// CRITICAL: Check for 3DS return FIRST - before any other checks
			if (FlowState.get('is3DSReturn')) {
				ckoLogger.threeDS('loadFlow: 3DS return in progress, aborting Flow initialization');
				return; // Exit immediately
			}
			
			// Check if cko_flow_vars is available
			if (typeof cko_flow_vars === 'undefined') {
				ckoLogger.error('cko_flow_vars is not defined. Flow cannot be initialized.');
				return;
			}
			
			// CRITICAL: Validate required fields BEFORE creating payment session
			const fieldsValid = requiredFieldsFilledAndValid();
			if (!fieldsValid) {
				ckoLogger.debug('loadFlow: Required fields not filled - showing waiting message and aborting');
				showFlowWaitingMessage();
				FlowState.set('initialized', false);
				FlowState.set('initializing', false);
				return;
			}
		}
		
		ckoLogger.debug('loadFlow: Validation passed - proceeding with payment session creation');
		
		ckoLogger.version('2025-10-13-FINAL-E2E');
		
		// Check if we're on a redirect page with payment parameters - if so, don't initialize Flow
		const urlParams = new URLSearchParams(window.location.search);
		const paymentId = urlParams.get("cko-payment-id");
		const sessionId = urlParams.get("cko-session-id");
		const status = urlParams.get("status");
		
	// Detect 3DS redirect: if we have payment ID or session ID in URL, it's a 3DS return
	// Don't require status parameter (some environments don't include it)
	const is3DSReturn = (paymentId || sessionId);
	
	if (is3DSReturn) {
		ckoLogger.threeDS('3DS redirect detected - Server-side processing should handle this', {
			paymentId: paymentId,
			sessionId: sessionId,
			status: status || 'detected_via_ids'
		});
		
		// Prevent Flow initialization and updated_checkout events during 3DS return
		FlowState.set('is3DSReturn', true);
		
		// Server-side should handle this, but if page loads, JavaScript can help as fallback
		// Wait longer for slow environments - server-side processing might take time
		setTimeout(() => {
			// If we're still on checkout page after delay, server-side didn't redirect
			// Fallback: submit form via JavaScript
			if (window.location.pathname.includes('/checkout/') && !window.location.pathname.includes('/order-received/')) {
				ckoLogger.threeDS('Server-side redirect did not occur, using JavaScript fallback');
				
				// Set the payment ID in the hidden input
				const paymentIdInput = document.getElementById('cko-flow-payment-id');
				if (paymentIdInput && paymentId) {
					paymentIdInput.value = paymentId;
					ckoLogger.debug('Set payment ID in hidden input:', paymentId);
				}
				
				// Set payment type to card (most common)
				const paymentTypeInput = document.getElementById('cko-flow-payment-type');
				if (paymentTypeInput) {
					paymentTypeInput.value = 'card';
					ckoLogger.debug('Set payment type to: card');
				}
				
				// Submit the checkout form to complete the order
				const checkoutForm = document.querySelector('form.checkout');
				const orderPayForm = document.querySelector('form#order_review');
				
				if (checkoutForm) {
					ckoLogger.threeDS('Submitting checkout form to complete order (fallback)');
					jQuery(checkoutForm).off('submit').submit();
				} else if (orderPayForm) {
					ckoLogger.threeDS('Submitting order-pay form to complete order (fallback)');
					jQuery(orderPayForm).off('submit').submit();
				} else {
					ckoLogger.error('No checkout or order-pay form found after 3DS redirect');
				}
			} else {
				ckoLogger.threeDS('Server-side redirect successful, no JavaScript action needed');
			}
		}, 5000); // Wait 5 seconds for server-side redirect (increased for slow environments)
		
		return; // Don't initialize Flow component
	}
		
		// Show skeleton loader and disable place order button
		const skeleton = document.getElementById("flow-skeleton");
		const placeOrderBtn = document.getElementById("place_order");
		
		if (skeleton) {
			skeleton.classList.add("show");
		}
		if (placeOrderBtn) {
			placeOrderBtn.classList.add("flow-loading");
		}
		
		// REFACTORED: Use initialization helper to collect checkout data
		let checkoutData;
		if (typeof window.FlowInitialization !== 'undefined' && window.FlowInitialization.collectCheckoutData) {
			checkoutData = window.FlowInitialization.collectCheckoutData();
			
			// Check for errors in data collection
			if (checkoutData.error === 'INVALID_EMAIL') {
				ckoLogger.error('❌ BLOCKED: Invalid email during data collection', { email: checkoutData.email });
				hideLoadingOverlay();
				showError('Please enter a valid email address to continue with payment.');
				return; // Exit early - don't call API with invalid email
			}
		} else {
			// Fallback to original data collection if helper not available
			// This maintains backward compatibility
			let cartInfo = jQuery("#cart-info").data("cart");
			if (!cartInfo || jQuery.isEmptyObject(cartInfo)) {
				cartInfo = jQuery("#order-pay-info").data("order-pay");
			}
			checkoutData = {
				amount: cartInfo["order_amount"],
				currency: cartInfo["purchase_currency"],
				reference: "WOO" + (cko_flow_vars.ref_session || 'default'),
				email: (cartInfo["billing_address"] || {})["email"] || (document.getElementById("billing_email") ? document.getElementById("billing_email").value : ''),
				family_name: (cartInfo["billing_address"] || {})["family_name"] || (document.getElementById("billing_last_name") ? document.getElementById("billing_last_name").value : ''),
				given_name: (cartInfo["billing_address"] || {})["given_name"] || (document.getElementById("billing_first_name") ? document.getElementById("billing_first_name").value : ''),
				phone: (cartInfo["billing_address"] || {})["phone"] || (document.getElementById("billing_phone") ? document.getElementById("billing_phone").value : ''),
				orders: cartInfo["order_lines"],
				orderId: cartInfo["order_id"],
				payment_type: cko_flow_vars.regular_payment_type,
				metadata: { udf5: cko_flow_vars.udf5 },
				isSubscription: false
			};
		}
		
		// Extract variables from checkoutData for use in rest of function
		let amount = checkoutData.amount;
		let currency = checkoutData.currency;
		let reference = checkoutData.reference;
		let email = checkoutData.email;
		let family_name = checkoutData.family_name;
		let given_name = checkoutData.given_name;
		let phone = checkoutData.phone;
		let address1 = checkoutData.address1;
		let address2 = checkoutData.address2;
		let city = checkoutData.city;
		let zip = checkoutData.zip;
		let country = checkoutData.country;
		let shippingAddress1 = checkoutData.shippingAddress1;
		let shippingAddress2 = checkoutData.shippingAddress2;
		let shippingCity = checkoutData.shippingCity;
		let shippingZip = checkoutData.shippingZip;
		let shippingCountry = checkoutData.shippingCountry;
		let orders = checkoutData.orders;
		let description = checkoutData.description;
		let orderId = checkoutData.orderId;
		let payment_type = checkoutData.payment_type;
		let metadata = checkoutData.metadata;
		let isSubscription = checkoutData.isSubscription;
		
		// Get cartInfo for remaining logic that needs it
		let cartInfo = jQuery("#cart-info").data("cart");
		if (!cartInfo || jQuery.isEmptyObject(cartInfo)) {
			cartInfo = jQuery("#order-pay-info").data("order-pay");
		}
		
		ckoLogger.debug('🔍 Payment Type - Final value before paymentSessionRequest:', payment_type);

		ckoLogger.debug('🔍 Payment Type - Final value before paymentSessionRequest:', payment_type);

		// Remove is_subscription from all orders.
		orders.forEach(order => {
			delete order.is_subscription;
		});

		/*
		 * Helper to get a field value by ID from the DOM.
		 */
		function getCheckoutField(fieldId) {
			const el = document.getElementById(fieldId);
			return el && el.value ? el.value : null;
		}

		if (!email) {
			email = getCheckoutField("billing_email");
		}
		
		// CRITICAL: Validate email before proceeding - prevent API call with invalid email
		// This is a safety check even though canInitializeFlow() should prevent this
		// Use inline validation to ensure it works even if isValidEmail() isn't available
		const emailRegex2 = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
		if (!email || typeof email !== 'string' || !email.trim() || !emailRegex2.test(email.trim())) {
			ckoLogger.error('❌ BLOCKED: Invalid email at second check', { 
				email: email,
				emailType: typeof email,
				emailLength: email?.length,
				isEmpty: !email || !email.trim()
			});
			hideLoadingOverlay();
			showError('Please enter a valid email address to continue with payment.');
			return; // Exit early - don't call API with invalid email
		}
		
		// Trim email to remove whitespace
		email = email.trim();

		if (!family_name) {
			family_name = getCheckoutField("billing_first_name");
		}

		if (!given_name) {
			given_name = getCheckoutField("billing_last_name");
		}

		if (!phone) {
			phone = getCheckoutField("billing_phone");
		}

		/*
		 * Displays the loading overlay.
		 */
		function showLoadingOverlay(arg) {
			let overlay = document.getElementById("loading-overlay");
			if (arg === 2) {
				overlay = document.getElementById("loading-overlay2");
			}
			if (overlay) {
				overlay.style.display = "flex";
			}
		}

		/*
		 * Hides the loading overlay.
		 */
		function hideLoadingOverlay(arg) {
			let overlay = document.getElementById("loading-overlay");
			if (arg === 2) {
				overlay = document.getElementById("loading-overlay2");
			}
			if (overlay) {
				overlay.style.display = "none";
			}
		}

		try {
			showLoadingOverlay();

			// Performance: Track API request start
			const apiRequestStart = performance.now();
			if (ckoFlow.performanceMetrics.enableLogging) {
				ckoLogger.performance('Starting payment session API request...');
			}

			/*
			 * Send request to Checkout.com to create a payment session.
			 */
			// Debug: Log 3DS configuration
			// Ensure proper boolean values - convert any truthy/falsy values to strict booleans
			const three_ds_enabled = Boolean(cko_flow_vars.three_d_enabled === true || cko_flow_vars.three_d_enabled === "true" || cko_flow_vars.three_d_enabled === 1 || cko_flow_vars.three_d_enabled === "1");
			const attempt_n3d = Boolean(cko_flow_vars.attempt_no_three_d === true || cko_flow_vars.attempt_no_three_d === "true" || cko_flow_vars.attempt_no_three_d === 1 || cko_flow_vars.attempt_no_three_d === "1");
			
			// Valid exemption values according to Checkout.com API
			const valid_exemptions = [
				"low_value", "trusted_listing", "trusted_listing_prompt", 
				"transaction_risk_assessment", "3ds_outage", "sca_delegation", 
				"out_of_sca_scope", "low_risk_program", "recurring_operation", 
				"data_share", "other"
			];
			
			// Use valid exemption or default to null (no exemption)
			const exemption = (cko_flow_vars.exemption && valid_exemptions.includes(cko_flow_vars.exemption)) ? cko_flow_vars.exemption : null;
			
			ckoLogger.debug('Exemption Processing:', {
				'Raw exemption from backend': cko_flow_vars.exemption,
				'Type of raw exemption': typeof cko_flow_vars.exemption,
				'Is exemption truthy': !!cko_flow_vars.exemption,
				'Is exemption valid': cko_flow_vars.exemption && valid_exemptions.includes(cko_flow_vars.exemption),
				'Processed exemption': exemption,
				'Will exemption be included': !!exemption,
				'Valid exemptions list': valid_exemptions
			});
			
			ckoLogger.threeDS('3DS Configuration (field name: "3ds"):', {
				'Raw three_d_enabled': cko_flow_vars.three_d_enabled,
				'Type of three_d_enabled': typeof cko_flow_vars.three_d_enabled,
				'Processed enabled': three_ds_enabled,
				'Type of processed enabled': typeof three_ds_enabled,
				'Raw attempt_no_three_d': cko_flow_vars.attempt_no_three_d,
				'Type of attempt_no_three_d': typeof cko_flow_vars.attempt_no_three_d,
				'Processed attempt_n3d': attempt_n3d,
				'Type of processed attempt_n3d': typeof attempt_n3d,
				challenge_indicator: cko_flow_vars.challenge_indicator || "no_preference",
				'Raw exemption': cko_flow_vars.exemption,
				'Processed exemption': exemption,
				'Raw allow_upgrade': cko_flow_vars.allow_upgrade,
				'Type of allow_upgrade': typeof cko_flow_vars.allow_upgrade,
				allow_upgrade: cko_flow_vars.allow_upgrade === true || cko_flow_vars.allow_upgrade === "true" || cko_flow_vars.allow_upgrade === "yes",
			});

			// CRITICAL: Log payment_type right before creating paymentSessionRequest
			ckoLogger.debug('🔍 Payment Type Check - Right before paymentSessionRequest creation:', {
				payment_type: payment_type,
				regular_payment_type: cko_flow_vars.regular_payment_type,
				recurring_payment_type: cko_flow_vars.recurring_payment_type,
				isRecurring: payment_type === cko_flow_vars.recurring_payment_type,
				isRegular: payment_type === cko_flow_vars.regular_payment_type
			});

			// Debug: Log complete payment session request
			const paymentSessionRequest = {
				amount: amount,
				currency: currency,
				reference: reference,
				payment_type: payment_type,
				description: description,
				customer: {
					name: `${given_name} ${family_name}`,
					email: email,
				},
				billing: {
					address: {
						address_line1: address1,
						address_line2: address2,
						city: city,
						zip: zip,
						country: country,
					},
				},
				shipping: {
					address: {
						address_line1: shippingAddress1,
						address_line2: shippingAddress2,
						city: shippingCity,
						zip: shippingZip,
						country: shippingCountry,
					},
				},
			// Determine success and failure URLs based on current page
			// NOTE: success_url and failure_url are overridden below based on order-pay vs regular checkout
			// These initial values are placeholders and will be replaced
			// Use query string format (?wc-api=...) instead of path format (/wc-api/...) for WooCommerce API endpoints
			success_url: window.location.origin + "/?wc-api=wc_checkoutcom_flow_process",
			failure_url: window.location.origin + "/?wc-api=wc_checkoutcom_flow_process",
				metadata: metadata,
				payment_method_configuration: {
					card: {
						store_payment_details: "enabled",
					},
				},
				capture: true,
				items: orders,
				integration: {
					external_platform: {
						name: "Woocomerce",
						version: cko_flow_vars.woo_version,
					},
				},
				"3ds": {
					enabled: three_ds_enabled,
					attempt_n3d: attempt_n3d,
					challenge_indicator: cko_flow_vars.challenge_indicator || "no_preference",
					...(exemption && { exemption: exemption }),
					allow_upgrade: cko_flow_vars.allow_upgrade === true || cko_flow_vars.allow_upgrade === "true" || cko_flow_vars.allow_upgrade === "yes",
				},
			};
			
			// Check if this is a MOTO order (admin-created order + order-pay page + guest customer)
			const orderPayInfo = jQuery("#order-pay-info")?.data("order-pay");
			const isMotoOrder = orderPayInfo?.payment_type === 'MOTO';
			
			ckoLogger.debug('MOTO Detection - Payment Type:', orderPayInfo?.payment_type, 'Is MOTO:', isMotoOrder);
			
			// Override payment type for MOTO orders
			if (isMotoOrder) {
				payment_type = 'MOTO';
				ckoLogger.debug('MOTO order detected - setting payment_type to MOTO');
				ckoLogger.debug('MOTO conditions: Admin order + Order-pay page + Guest customer');
			} else {
				ckoLogger.debug('Not a MOTO order - current payment_type:', payment_type);
			}
			
			// CRITICAL: Log payment_type right before final assignment to paymentSessionRequest
			ckoLogger.debug('🔍 Final Payment Type Assignment:', {
				payment_type: payment_type,
				regular_payment_type: cko_flow_vars.regular_payment_type,
				recurring_payment_type: cko_flow_vars.recurring_payment_type,
				isRecurring: payment_type === cko_flow_vars.recurring_payment_type,
				isRegular: payment_type === cko_flow_vars.regular_payment_type,
				isMOTO: payment_type === 'MOTO',
				'Will be assigned to paymentSessionRequest.payment_type': payment_type
			});
			
			// Update payment type in the request
			paymentSessionRequest.payment_type = payment_type;
			
			// Verify the assignment
			ckoLogger.debug('✅ Verified paymentSessionRequest.payment_type after assignment:', paymentSessionRequest.payment_type);
			
			// SIMPLIFIED: All flows go directly to PHP endpoint which processes payment and redirects to success/failure page
			// This provides a smooth transition: 3DS → PHP Endpoint → Success/Failure Page (no checkout page in between)
			const isOrderPayPage = window.location.pathname.includes('/order-pay/');
			
			// Get save card preference to include in redirect URL (for 3DS flow)
			const saveCardCheckbox = document.getElementById('wc-wc_checkout_com_flow-new-payment-method');
			const saveCardValue = (saveCardCheckbox && saveCardCheckbox.checked) ? 'yes' : 'no';
			
			ckoLogger.debug('[SAVE CARD DEBUG] Early checkbox read:', {
				checkboxId: 'wc-wc_checkout_com_flow-new-payment-method',
				checkboxFound: !!saveCardCheckbox,
				checkboxChecked: saveCardCheckbox ? saveCardCheckbox.checked : false,
				saveCardValue: saveCardValue
			});
			
			if (isOrderPayPage) {
				// For order-pay pages, include order_id and key in URL so PHP endpoint can find the order
				const orderId = window.location.pathname.match(/\/order-pay\/(\d+)\//)?.[1];
				const orderKey = new URLSearchParams(window.location.search).get('key');
				
				if (orderId && orderKey) {
					// Use query string format (?wc-api=...) for WooCommerce API endpoints
					// Include save card preference in URL for 3DS flow
					paymentSessionRequest.success_url = window.location.origin + "/?wc-api=wc_checkoutcom_flow_process&order_id=" + orderId + "&key=" + orderKey + "&cko-save-card=" + saveCardValue;
					paymentSessionRequest.failure_url = window.location.origin + "/?wc-api=wc_checkoutcom_flow_process&order_id=" + orderId + "&key=" + orderKey + "&cko-save-card=" + saveCardValue;
					ckoLogger.debug('Order-pay - using PHP endpoint with order_id, key, and save card preference: ' + saveCardValue);
				} else {
					ckoLogger.error('❌ ERROR: Could not extract order ID or key from order-pay URL');
					// Fallback to PHP endpoint without order_id (will try to find order by payment session ID)
					paymentSessionRequest.success_url = window.location.origin + "/?wc-api=wc_checkoutcom_flow_process&cko-save-card=" + saveCardValue;
					paymentSessionRequest.failure_url = window.location.origin + "/?wc-api=wc_checkoutcom_flow_process&cko-save-card=" + saveCardValue;
					ckoLogger.debug('Order-pay fallback - using PHP endpoint without order_id, save card preference: ' + saveCardValue);
				}
			} else {
				// For regular checkout, redirect directly to PHP endpoint which processes payment and redirects to success page
				// Use query string format (?wc-api=...) for WooCommerce API endpoints
				// Include save card preference in URL for 3DS flow
				// CRITICAL: Include order_id and order_key if order already exists (created via AJAX)
				// This ensures guest orders can access order received page after 3DS
				// CRITICAL FIX: Validate order_id before using - only use if it matches current checkout session
				const formOrderId = jQuery('input[name="order_id"]').val();
				const sessionOrderId = sessionStorage.getItem('cko_flow_order_id');
				const sessionOrderKey = sessionStorage.getItem('cko_flow_order_key');
				
				// CRITICAL: Only use order_id from sessionStorage if it matches form order_id
				// This prevents reusing old order IDs from previous checkouts
				let orderIdToInclude = null;
				let orderKeyToInclude = '';
				
				if (formOrderId) {
					// Form order_id takes priority (most reliable - from current checkout)
					orderIdToInclude = formOrderId;
					ckoLogger.debug('[PAYMENT SESSION] Using order_id from form: ' + orderIdToInclude);
					
					// Try to get matching order_key from sessionStorage
					if (sessionOrderId === formOrderId && sessionOrderKey) {
						orderKeyToInclude = sessionOrderKey;
						ckoLogger.debug('[PAYMENT SESSION] Order key found in sessionStorage for matching order_id');
					}
				} else if (sessionOrderId) {
					// Only use sessionStorage order_id if form doesn't have one
					// This means order was created via AJAX but form hasn't been updated yet
					orderIdToInclude = sessionOrderId;
					orderKeyToInclude = sessionOrderKey || '';
					ckoLogger.debug('[PAYMENT SESSION] Using order_id from sessionStorage (form order_id not available): ' + orderIdToInclude);
				} else {
					ckoLogger.debug('[PAYMENT SESSION] Order ID not found yet - will be created before payment');
				}
				
				let successUrl = window.location.origin + "/?wc-api=wc_checkoutcom_flow_process&cko-save-card=" + saveCardValue;
				let failureUrl = window.location.origin + "/?wc-api=wc_checkoutcom_flow_process&cko-save-card=" + saveCardValue;
				
				if (orderIdToInclude) {
					successUrl += '&order_id=' + orderIdToInclude;
					failureUrl += '&order_id=' + orderIdToInclude;
					ckoLogger.debug('[PAYMENT SESSION] Order ID found, including in success_url: ' + orderIdToInclude);
					
					if (orderKeyToInclude) {
						successUrl += '&key=' + encodeURIComponent(orderKeyToInclude);
						failureUrl += '&key=' + encodeURIComponent(orderKeyToInclude);
						ckoLogger.debug('[PAYMENT SESSION] Order key found, including in success_url');
					} else {
						ckoLogger.warn('[PAYMENT SESSION] ⚠️ Order ID found but order key NOT found - guest orders may not display correctly');
					}
				}
				
				paymentSessionRequest.success_url = successUrl;
				paymentSessionRequest.failure_url = failureUrl;
				ckoLogger.debug('Regular checkout - using PHP endpoint for direct redirect to success page, save card preference: ' + saveCardValue);
				ckoLogger.debug('[PAYMENT SESSION] Final success_url: ' + successUrl);
			}
			
			// Add enabled_payment_methods if specified by merchant
			// Only send if array has items (empty array means show all methods)
			// For MOTO orders, hardcode to only show card payment method
			if (isMotoOrder) {
				ckoLogger.debug('MOTO order detected - hardcoding to show only card payment method');
				paymentSessionRequest.enabled_payment_methods = ['card'];
				ckoLogger.debug('MOTO: Set enabled_payment_methods to:', paymentSessionRequest.enabled_payment_methods);
			} else if (cko_flow_vars.enabled_payment_methods && 
			    Array.isArray(cko_flow_vars.enabled_payment_methods) && 
			    cko_flow_vars.enabled_payment_methods.length > 0) {
				paymentSessionRequest.enabled_payment_methods = cko_flow_vars.enabled_payment_methods;
				ckoLogger.debug('Regular: Enabled payment methods (sent to API):', cko_flow_vars.enabled_payment_methods);
			} else {
				ckoLogger.debug('No payment methods filter - all available methods will be shown');
				ckoLogger.debug('cko_flow_vars.enabled_payment_methods:', cko_flow_vars.enabled_payment_methods);
			}
			
			// Update save card value right before sending request to ensure latest checkbox state
			// Check multiple sources: checkbox state, sessionStorage, hidden field
			const currentSaveCardCheckbox = document.getElementById('wc-wc_checkout_com_flow-new-payment-method');
			const checkboxFound = !!currentSaveCardCheckbox;
			const checkboxChecked = currentSaveCardCheckbox ? currentSaveCardCheckbox.checked : false;
			
			// Also check sessionStorage and hidden field as fallbacks
			const sessionStorageValue = sessionStorage.getItem('cko_flow_save_card');
			const hiddenField = document.getElementById('cko-flow-save-card-persist');
			const hiddenFieldValue = hiddenField ? hiddenField.value : '';
			
			// Determine final value: checkbox > sessionStorage > hidden field > 'no'
			let currentSaveCardValue = 'no';
			if (checkboxChecked) {
				currentSaveCardValue = 'yes';
			} else if (sessionStorageValue === 'yes') {
				currentSaveCardValue = 'yes';
			} else if (hiddenFieldValue === 'yes') {
				currentSaveCardValue = 'yes';
			}
			
			ckoLogger.debug('[SAVE CARD DEBUG] Reading checkbox before API call:', {
				checkboxId: 'wc-wc_checkout_com_flow-new-payment-method',
				checkboxFound: checkboxFound,
				checkboxChecked: checkboxChecked,
				sessionStorageValue: sessionStorageValue,
				hiddenFieldValue: hiddenFieldValue,
				finalSaveCardValue: currentSaveCardValue,
				checkboxElement: currentSaveCardCheckbox ? 'found' : 'NOT FOUND'
			});
			
			// Update success_url and failure_url with current checkbox value
			const currentIsOrderPayPage = window.location.pathname.includes('/order-pay/');
			if (currentIsOrderPayPage) {
				const orderId = window.location.pathname.match(/\/order-pay\/(\d+)\//)?.[1];
				const orderKey = new URLSearchParams(window.location.search).get('key');
				if (orderId && orderKey) {
					paymentSessionRequest.success_url = window.location.origin + "/?wc-api=wc_checkoutcom_flow_process&order_id=" + orderId + "&key=" + orderKey + "&cko-save-card=" + currentSaveCardValue;
					paymentSessionRequest.failure_url = window.location.origin + "/?wc-api=wc_checkoutcom_flow_process&order_id=" + orderId + "&key=" + orderKey + "&cko-save-card=" + currentSaveCardValue;
				} else {
					paymentSessionRequest.success_url = window.location.origin + "/?wc-api=wc_checkoutcom_flow_process&cko-save-card=" + currentSaveCardValue;
					paymentSessionRequest.failure_url = window.location.origin + "/?wc-api=wc_checkoutcom_flow_process&cko-save-card=" + currentSaveCardValue;
				}
			} else {
				// For regular checkout, include order_id and order_key if order already exists (created via AJAX)
				// CRITICAL: This ensures guest orders can access order received page after 3DS
				// CRITICAL FIX: Validate order_id before using - only use if it matches current checkout session
				const formOrderId = jQuery('input[name="order_id"]').val();
				const sessionOrderId = sessionStorage.getItem('cko_flow_order_id');
				const sessionOrderKey = sessionStorage.getItem('cko_flow_order_key');
				
				// CRITICAL: Only use order_id from sessionStorage if it matches form order_id
				// This prevents reusing old order IDs from previous checkouts
				let orderIdToInclude = null;
				let orderKeyToInclude = '';
				
				if (formOrderId) {
					// Form order_id takes priority (most reliable - from current checkout)
					orderIdToInclude = formOrderId;
					// Try to get matching order_key from sessionStorage
					if (sessionOrderId === formOrderId && sessionOrderKey) {
						orderKeyToInclude = sessionOrderKey;
					}
				} else if (sessionOrderId) {
					// Only use sessionStorage order_id if form doesn't have one
					orderIdToInclude = sessionOrderId;
					orderKeyToInclude = sessionOrderKey || '';
				}
				
				let successUrl = window.location.origin + "/?wc-api=wc_checkoutcom_flow_process&cko-save-card=" + currentSaveCardValue;
				let failureUrl = window.location.origin + "/?wc-api=wc_checkoutcom_flow_process&cko-save-card=" + currentSaveCardValue;
				
				if (orderIdToInclude && orderKeyToInclude) {
					successUrl += "&order_id=" + orderIdToInclude + "&key=" + encodeURIComponent(orderKeyToInclude);
					failureUrl += "&order_id=" + orderIdToInclude + "&key=" + encodeURIComponent(orderKeyToInclude);
					ckoLogger.debug('[PAYMENT SESSION UPDATE] Order ID and key found, including in success_url: ' + orderIdToInclude);
				} else if (orderIdToInclude) {
					successUrl += "&order_id=" + orderIdToInclude;
					failureUrl += "&order_id=" + orderIdToInclude;
					ckoLogger.debug('[PAYMENT SESSION UPDATE] Order ID found (no key), including in success_url: ' + orderIdToInclude);
				} else {
					ckoLogger.debug('[PAYMENT SESSION UPDATE] Order ID not found yet - will be created before payment');
				}
				
				paymentSessionRequest.success_url = successUrl;
				paymentSessionRequest.failure_url = failureUrl;
				ckoLogger.debug('[PAYMENT SESSION UPDATE] Final success_url: ' + successUrl);
			}
			
			ckoLogger.debug('[SAVE CARD DEBUG] Final URLs set:', {
				success_url: paymentSessionRequest.success_url,
				failure_url: paymentSessionRequest.failure_url,
				containsSaveCardYes: paymentSessionRequest.success_url.includes('cko-save-card=yes')
			});
			
			ckoLogger.debug('Complete Payment Session Request:', paymentSessionRequest);

			// SECURITY: Use secure backend AJAX endpoint instead of exposing secret key to frontend
			const formData = new FormData();
			formData.append('action', 'cko_flow_create_payment_session');
			formData.append('nonce', cko_flow_vars.payment_session_nonce);
			// CRITICAL: Final validation before API call - prevent invalid email from being sent
			// Validate email format inline (don't rely on function that might not be available)
			const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
			const emailValue = paymentSessionRequest.customer?.email || '';
			const emailTrimmed = emailValue.trim();
			
			// Check if email is missing or invalid
			if (!emailValue || !emailTrimmed || !emailRegex.test(emailTrimmed)) {
				ckoLogger.error('❌ BLOCKED: Invalid or missing email before API call', { 
					email: emailValue,
					emailType: typeof emailValue,
					emailLength: emailValue?.length,
					emailTrimmed: emailTrimmed,
					emailRegexTest: emailRegex.test(emailTrimmed)
				});
				hideLoadingOverlay();
				
				// Check if required fields are filled - if not, show waiting message instead of error
				const fieldsFilledCheck = requiredFieldsFilled();
				const fieldsValidCheck = requiredFieldsFilledAndValid();
				
				ckoLogger.debug('Email validation failed - checking field status:', {
					fieldsFilled: fieldsFilledCheck,
					fieldsValid: fieldsValidCheck,
					willShowWaitingMessage: !fieldsValidCheck
				});
				
				if (!fieldsValidCheck) {
					ckoLogger.debug('Required fields not filled - showing waiting message instead of error');
					showFlowWaitingMessage();
					// Reset initialization state so Flow can retry when fields are filled
					ckoFlowInitialized = false;
					ckoFlowInitializing = false;
					return; // Exit - don't call API
				} else {
					// Fields are filled but email is invalid - show error
					ckoLogger.debug('Fields are filled but email is invalid - showing error message');
					showError('Please enter a valid email address to continue with payment.');
					return; // Exit - don't call API
				}
			}
			
			// Trim email to remove any whitespace
			paymentSessionRequest.customer.email = paymentSessionRequest.customer.email.trim();
			
			ckoLogger.debug('✅ Email validated before API call', { email: paymentSessionRequest.customer.email });

			formData.append('payment_session_request', JSON.stringify(paymentSessionRequest));

			let response = await fetch(cko_flow_vars.ajax_url, {
				method: "POST",
				body: formData,
			});

			let responseData = await response.json();
			
			// Declare paymentSession variable
			let paymentSession;
			
			// Check if AJAX response indicates success
			if (!responseData.success) {
				// Handle error response - format as API error response
				paymentSession = {
					error_type: responseData.data?.error_type || 'ajax_error',
					error_codes: responseData.data?.error_codes || [responseData.data?.message || 'Unknown error'],
					request_id: responseData.data?.request_id || null
				};
				
				// Create a mock response object with ok: false to trigger error handling below
				response = {
					ok: false,
					status: response.status || 500
				};
			} else {
				// Success - extract payment session from response
				paymentSession = responseData.data;
				// Ensure response.ok is true for success case
				response = {
					ok: true,
					status: 200
				};
			}

			// Validate payment session response
			if (!paymentSession.payment_session_token) {
				ckoLogger.warn('Payment session token not found in response');
			}

			// Check for API errors (422, 400, etc.)
			if (!response.ok || paymentSession.error_type || paymentSession.error_codes) {
				ckoLogger.error('Payment Session API Error:', {
					status: response.status,
					statusText: response.statusText,
					error_type: paymentSession.error_type,
					error_codes: paymentSession.error_codes,
					request_id: paymentSession.request_id,
					response: paymentSession
				});
				
				// Hide loading overlay and skeleton
				hideLoadingOverlay();
				const skeleton = document.getElementById("flow-skeleton");
				const placeOrderBtn = document.getElementById("place_order");
				
				if (skeleton) {
					skeleton.classList.remove("show");
				}
				if (placeOrderBtn) {
					placeOrderBtn.classList.remove("flow-loading");
				}
				
				// Show user-friendly error message
				let errorMessage = 'Error creating payment session. Please try again.';
				if (paymentSession.error_codes && paymentSession.error_codes.length > 0) {
					errorMessage = 'Payment session error: ' + paymentSession.error_codes.join(', ');
				}
				
				showError(errorMessage);
				return;
			}

			// Performance: Track API request completion
			const apiRequestEnd = performance.now();
			const apiDuration = apiRequestEnd - apiRequestStart;
			if (ckoFlow.performanceMetrics.enableLogging) {
				ckoLogger.performance(`Payment session API completed in ${apiDuration.toFixed(2)}ms (${(apiDuration / 1000).toFixed(2)}s)`);
			}

			// Debug: Log payment session response
			ckoLogger.payment('Payment Session Response:', paymentSession);
			
			// Store payment session ID globally for access in callbacks
			window.currentPaymentSessionId = paymentSession.id;
			
			// CRITICAL: Add payment session ID as hidden field to form for process_payment()
			// This ensures payment session ID is available in POST data when form is submitted
			// Function to add hidden field (can be called multiple times as fallback)
			const addPaymentSessionIdField = () => {
				if (!window.currentPaymentSessionId) {
					return false;
				}
				
				// Remove existing hidden field if it exists
				const existingField = document.getElementById('cko-flow-payment-session-id');
				if (existingField) {
					existingField.remove();
				}
				
				// Create hidden input field for payment session ID
				const hiddenField = document.createElement('input');
				hiddenField.type = 'hidden';
				hiddenField.id = 'cko-flow-payment-session-id';
				hiddenField.name = 'cko-flow-payment-session-id';
				hiddenField.value = window.currentPaymentSessionId;
				
				// Add to checkout form (or order-pay form)
				const checkoutForm = document.querySelector('form.checkout');
				const orderPayForm = document.querySelector('form#order_review');
				const targetForm = checkoutForm || orderPayForm;
				
				if (targetForm) {
					targetForm.appendChild(hiddenField);
					ckoLogger.debug('[SAVE PAYMENT SESSION ID] Added hidden field to form:', {
						form: checkoutForm ? 'checkout' : 'order-pay',
						payment_session_id: window.currentPaymentSessionId
					});
					return true;
				} else {
					ckoLogger.debug('[SAVE PAYMENT SESSION ID] Form not found yet - will retry on form submission');
					return false;
				}
			};
			
			// Try to add immediately
			if (paymentSession.id) {
				addPaymentSessionIdField();
				
				// Also expose function globally so it can be called as fallback
				window.ckoAddPaymentSessionIdField = addPaymentSessionIdField;
			}
			
			// PERFORMANCE: Save payment session ID to order immediately (asynchronously, non-blocking)
			// This ensures order is linked to payment session even if user cancels after 3DS
			// Get order ID from cartInfo (available earlier in function) or from URL (for order-pay pages)
			let orderIdToSave = cartInfo["order_id"];
			if (!orderIdToSave && window.location.pathname.includes('/order-pay/')) {
				const pathMatch = window.location.pathname.match(/\/order-pay\/(\d+)\//);
				orderIdToSave = pathMatch ? pathMatch[1] : null;
			}
			
			// Save payment session ID asynchronously (fire and forget - don't wait for response)
			// NOTE: This only works for order-pay pages where order already exists
			// For regular checkout, payment session ID is saved via hidden field in process_payment()
			if (paymentSession.id && orderIdToSave && cko_flow_vars && cko_flow_vars.ajax_url && cko_flow_vars.payment_session_nonce) {
				const saveFormData = new FormData();
				saveFormData.append('action', 'cko_flow_save_payment_session_id');
				saveFormData.append('nonce', cko_flow_vars.payment_session_nonce);
				saveFormData.append('order_id', orderIdToSave);
				saveFormData.append('payment_session_id', paymentSession.id);
				
				// Fire and forget - don't wait for response (non-blocking)
				fetch(cko_flow_vars.ajax_url, {
					method: 'POST',
					body: saveFormData
				}).then(response => {
					if (response.ok) {
						ckoLogger.debug('[SAVE PAYMENT SESSION ID] Successfully saved payment session ID to order:', {
							order_id: orderIdToSave,
							payment_session_id: paymentSession.id
						});
					} else {
						ckoLogger.warn('[SAVE PAYMENT SESSION ID] Failed to save payment session ID (non-critical):', {
							order_id: orderIdToSave,
							payment_session_id: paymentSession.id,
							status: response.status
						});
					}
				}).catch(err => {
					// Log error but don't block flow (fallback exists in handle_3ds_return)
					ckoLogger.warn('[SAVE PAYMENT SESSION ID] Error saving payment session ID (non-critical, fallback exists):', err);
				});
				
				// Continue immediately - don't wait for AJAX response
			} else {
				if (ckoLogger.debug) {
					ckoLogger.debug('[SAVE PAYMENT SESSION ID] Skipping AJAX save - missing order ID (normal for regular checkout):', {
						has_payment_session_id: !!paymentSession.id,
						has_order_id: !!orderIdToSave,
						has_ajax_url: !!(cko_flow_vars && cko_flow_vars.ajax_url),
						has_nonce: !!(cko_flow_vars && cko_flow_vars.payment_session_nonce),
						note: 'Payment session ID will be saved via hidden field in process_payment()'
					});
				}
			}

			/*
			 * Handle API Session errors returned from Checkout.com.
			 */
			if (paymentSession.error_type) {
				// Hide loading overlay.
				hideLoadingOverlay();

				// Check for 3DS specific errors
				if (paymentSession.error_codes && paymentSession.error_codes.includes('three_ds_invalid')) {
					ckoLogger.error('3DS Configuration Error:', paymentSession);
					showError('3DS configuration error. Please check your 3DS settings in the admin panel.');
					return;
				}

				const readableErrors = {
					customer_email_invalid: wp.i18n.__(
						"Please enter the email to proceed.",
						"checkout-com-unified-payments-api"
					),
					billing_phone_number_invalid: wp.i18n.__(
						"Please enter the phone number to proceed.",
						"checkout-com-unified-payments-api"
					),
				};

				let messages = (paymentSession.error_codes || []).map(
					(code) => readableErrors[code] || code
				);
				showError(messages[0]);
				return;
			}

			/*
			 * Successfully received a payment session.
			 * Load Checkout.com Web Component.
			 */
			if (paymentSession.id) {
				// Performance: Track component initialization start
				const componentInitStart = performance.now();
				if (ckoFlow.performanceMetrics.enableLogging) {
					ckoLogger.performance('Initializing Checkout Web Component...');
				}
				
		// Debug: Log Flow component configuration
		ckoLogger.debug('Flow Component Configuration:', {
			publicKey: cko_flow_vars.PKey ? 'SET' : 'NOT SET',
			publicKeyPreview: cko_flow_vars.PKey ? cko_flow_vars.PKey.substring(0, 10) + '...' : 'NOT SET',
			environment: cko_flow_vars.env,
			locale: window.locale,
			paymentSessionId: paymentSession.id,
			paymentSessionSecret: paymentSession.payment_session_secret ? 'SET' : 'NOT SET',
			appearance: window.appearance ? 'SET' : 'NOT SET',
			componentOptions: window.componentOptions ? 'SET' : 'NOT SET',
			translations: window.translations ? 'SET' : 'NOT SET'
		});
		
		// Additional validation
		if (!cko_flow_vars.PKey) {
			ckoLogger.error('CRITICAL: Public key is missing!');
			showError('Payment gateway configuration error. Please contact support.');
			return;
		}
		
		if (!paymentSession.id) {
			ckoLogger.error('CRITICAL: Payment session ID is missing!');
			showError('Payment session error. Please try again.');
			return;
		}
		
		// Define callback functions BEFORE passing them to CheckoutWebComponents
		const onReady = () => {
				try {
					ckoLogger.debug('onReady callback fired! 🔥🔥🔥');
					hideLoadingOverlay();
					
					// Performance tracking: Mark when Flow is ready
					ckoFlow.performanceMetrics.flowReadyTime = performance.now();
					
					const pageLoadToReady = ckoFlow.performanceMetrics.flowReadyTime - ckoFlow.performanceMetrics.pageLoadTime;
					const initToReady = ckoFlow.performanceMetrics.flowReadyTime - ckoFlow.performanceMetrics.flowInitStartTime;
					
					if (ckoFlow.performanceMetrics.enableLogging) {
						ckoLogger.performance('===== Flow Load Complete =====');
						ckoLogger.performance(`Page Load → Flow Ready: ${pageLoadToReady.toFixed(2)}ms (${(pageLoadToReady / 1000).toFixed(2)}s)`);
						ckoLogger.performance(`Flow Init → Flow Ready: ${initToReady.toFixed(2)}ms (${(initToReady / 1000).toFixed(2)}s)`);
						ckoLogger.performance('================================');
					}
					
					// ============================================================
					// SMOOTH CHECKOUT EXPERIENCE - onReady Handler
					// ============================================================
					
					const displayOrder = window.saved_payment || 'new_payment_first';
					const skeleton = document.getElementById("flow-skeleton");
					const placeOrderBtn = document.getElementById("place_order");
					const saveCardCheckbox = document.querySelector('.cko-save-card-checkbox');
					
					ckoLogger.debug('onReady - Display order:', displayOrder);
					ckoLogger.debug('onReady - Flow is fully loaded and ready');
					
					// Step 1: Hide skeleton loader with smooth transition
					ckoLogger.debug('onReady - Skeleton element found:', !!skeleton);
					if (skeleton) {
						ckoLogger.debug('onReady - Skeleton classes BEFORE:', skeleton.className);
						skeleton.classList.remove("show");
						ckoLogger.debug('onReady - Skeleton classes AFTER:', skeleton.className);
						ckoLogger.debug('onReady - ✓ Skeleton loader hidden');
					} else {
						ckoLogger.debug('onReady - ❌ Skeleton element NOT FOUND');
					}
					
					// Step 2: Enable Place Order button (remove loading state)
					ckoLogger.debug('onReady - Place Order button found:', !!placeOrderBtn);
					if (placeOrderBtn) {
						ckoLogger.debug('onReady - Button classes BEFORE:', placeOrderBtn.className);
						placeOrderBtn.classList.remove("flow-loading");
						ckoLogger.debug('onReady - Button classes AFTER:', placeOrderBtn.className);
						placeOrderBtn.style.removeProperty('opacity');
						placeOrderBtn.style.removeProperty('visibility');
						ckoLogger.debug('onReady - ✓ Place Order button enabled');
					} else {
						ckoLogger.debug('onReady - ❌ Place Order button NOT FOUND');
					}
					
					// Step 3: Hide save card checkbox initially (onChange will show it for card payment type)
					if (saveCardCheckbox) {
						saveCardCheckbox.style.display = 'none';
						ckoLogger.debug('onReady - ✓ Save card checkbox hidden (will show on card selection)');
					} else {
						ckoLogger.debug('onReady - Save card checkbox not available (feature disabled)');
					}
					
					// Step 4: Mark Flow as ready - triggers CSS animations and visibility rules
					document.body.classList.add("flow-ready");
					ckoLogger.debug('onReady - ✓ Flow marked as ready - Checkout is now fully interactive');
					
					
					// Step 5: Ensure no saved card is selected by default - user must explicitly select
					// Remove any default selections to prevent issues with auto-detection
					const anyDefaultCard = jQuery('input[name="wc-wc_checkout_com_flow-payment-token"][checked="checked"]:not(#wc-wc_checkout_com_flow-payment-token-new)');
					if (anyDefaultCard.length > 0) {
						ckoLogger.debug('onReady - Removing default saved card selection - user must explicitly select');
						anyDefaultCard.prop('checked', false).removeAttr('checked');
						// Ensure "new" card option is selected by default
						const newCardRadio = jQuery('#wc-wc_checkout_com_flow-payment-token-new');
						if (newCardRadio.length) {
							newCardRadio.prop('checked', true);
							ckoLogger.debug('onReady - ✓ "New card" option selected by default');
						}
						window.flowSavedCardSelected = false;
						FlowState.set('userInteracted', false);
					}
					
					// Note: Save card checkbox visibility is controlled by the onChange event
					// It will only show when payment type is "card" and hide for other payment methods
				} catch (error) {
					ckoLogger.error('onReady ERROR:', error);
					ckoLogger.error('Error stack:', error.stack);
				}
			};

		/*
		 * Called when the payment is completed successfully.
		 */
		const onPaymentCompleted = (_component, paymentResponse) => {
		
		// Check if this is a 3DS authentication response
		if (paymentResponse.threeDs && paymentResponse.threeDs.challenged) {
			// 3DS challenge detected - user will be redirected to 3DS page
			return;
		}
			
			if (paymentResponse.id) {
				hideLoadingOverlay(2);

				// Set the hidden input values.
				jQuery("#cko-flow-payment-id").val(paymentResponse.id);
				jQuery("#cko-flow-payment-type").val(paymentResponse?.type || "");

				// Check if this is an APM payment (Alternative Payment Method)
				// APM payments should submit form normally instead of redirecting
				// This is because handle_3ds_return() tries to fetch payment details which may fail for APM
				const paymentType = paymentResponse?.type || "";
				const isAPMPayment = paymentType && !['card', ''].includes(paymentType.toLowerCase());
				const apmTypes = ['googlepay', 'applepay', 'paypal', 'octopus', 'twint', 'klarna', 'sofort', 'ideal', 'giropay', 'bancontact', 'eps', 'p24', 'knet', 'fawry', 'qpay', 'multibanco', 'stcpay', 'alipay', 'wechatpay'];
				const isKnownAPM = apmTypes.includes(paymentType.toLowerCase());
				
				ckoLogger.debug('[PAYMENT COMPLETED] Payment type check:', {
					paymentType: paymentType,
					isAPMPayment: isAPMPayment,
					isKnownAPM: isKnownAPM
				});

				// CRITICAL: Check form field for order ID (set by createOrderBeforePayment())
				// Don't use orderId from loadFlow() scope - it's only set for order-pay pages
				const formOrderId = jQuery('input[name="order_id"]').val();
				const sessionOrderId = sessionStorage.getItem('cko_flow_order_id');
				const hasOrderId = formOrderId || sessionOrderId || orderId; // orderId is fallback for order-pay pages
				
				ckoLogger.debug('[PAYMENT COMPLETED] Order ID check:', {
					formOrderId: formOrderId || 'NOT SET',
					sessionOrderId: sessionOrderId || 'NOT SET',
					loadFlowOrderId: orderId || 'NOT SET',
					hasOrderId: !!hasOrderId
				});

				if ( ! hasOrderId ) {
					// No order exists - trigger WooCommerce order placement on checkout page.
					ckoLogger.debug('[PAYMENT COMPLETED] No order ID found - submitting form to create order');
					
					// CRITICAL: Ensure payment session ID is in form before submitting
					// This ensures payment_session_id is saved to order metadata
					const existingSessionIdField = document.getElementById('cko-flow-payment-session-id');
					const existingSessionIdValue = existingSessionIdField ? existingSessionIdField.value : '';
					
					if (!existingSessionIdField || !existingSessionIdValue) {
						if (window.ckoAddPaymentSessionIdField) {
							const added = window.ckoAddPaymentSessionIdField();
							if (added) {
								ckoLogger.debug('[PAYMENT COMPLETED] Payment session ID added to form before submission (no order ID)');
							} else if (window.currentPaymentSessionId) {
								// Fallback: Try to manually add
								const checkoutForm = document.querySelector('form.checkout');
								if (checkoutForm) {
									const manualField = document.createElement('input');
									manualField.type = 'hidden';
									manualField.id = 'cko-flow-payment-session-id';
									manualField.name = 'cko-flow-payment-session-id';
									manualField.value = window.currentPaymentSessionId;
									checkoutForm.appendChild(manualField);
									ckoLogger.debug('[PAYMENT COMPLETED] Payment session ID manually added (fallback)');
								}
							}
						} else if (window.currentPaymentSessionId) {
							// Fallback: Try to manually add if function not available
							const checkoutForm = document.querySelector('form.checkout');
							if (checkoutForm) {
								const manualField = document.createElement('input');
								manualField.type = 'hidden';
								manualField.id = 'cko-flow-payment-session-id';
								manualField.name = 'cko-flow-payment-session-id';
								manualField.value = window.currentPaymentSessionId;
								checkoutForm.appendChild(manualField);
								ckoLogger.debug('[PAYMENT COMPLETED] Payment session ID manually added (no function available)');
							}
						}
					}
					
					jQuery("form.checkout").submit();
				} else {
					// Order already exists (created via AJAX)
					const orderIdToUse = formOrderId || sessionOrderId || orderId;
					
					// For APM payments, always submit form instead of redirecting
					// This prevents payment details fetch errors in handle_3ds_return()
					if (isKnownAPM || isAPMPayment) {
						ckoLogger.debug('[PAYMENT COMPLETED] APM payment detected (' + paymentType + ') - submitting form instead of redirecting');
						
						// CRITICAL: Ensure payment session ID is in form before submitting
						// This ensures payment_session_id is saved to order metadata
						const existingSessionIdField = document.getElementById('cko-flow-payment-session-id');
						const existingSessionIdValue = existingSessionIdField ? existingSessionIdField.value : '';
						
						ckoLogger.debug('[PAYMENT COMPLETED] Payment session ID check:', {
							hasExistingField: !!existingSessionIdField,
							existingValue: existingSessionIdValue || 'EMPTY',
							hasWindowFunction: !!window.ckoAddPaymentSessionIdField,
							hasWindowSessionId: !!window.currentPaymentSessionId,
							windowSessionId: window.currentPaymentSessionId || 'NOT SET'
						});
						
						// If field doesn't exist or is empty, try to add it
						if (!existingSessionIdField || !existingSessionIdValue) {
							if (window.ckoAddPaymentSessionIdField) {
								const added = window.ckoAddPaymentSessionIdField();
								if (added) {
									ckoLogger.debug('[PAYMENT COMPLETED] Payment session ID added to form before APM submission');
								} else {
									ckoLogger.warn('[PAYMENT COMPLETED] Failed to add payment session ID to form - window.currentPaymentSessionId: ' + (window.currentPaymentSessionId || 'NOT SET'));
									
									// Fallback: Try to manually add if we have the session ID
									if (window.currentPaymentSessionId) {
										const checkoutForm = document.querySelector('form.checkout');
										if (checkoutForm) {
											const manualField = document.createElement('input');
											manualField.type = 'hidden';
											manualField.id = 'cko-flow-payment-session-id';
											manualField.name = 'cko-flow-payment-session-id';
											manualField.value = window.currentPaymentSessionId;
											checkoutForm.appendChild(manualField);
											ckoLogger.debug('[PAYMENT COMPLETED] Payment session ID manually added to form (fallback)');
										}
									}
								}
							} else {
								ckoLogger.warn('[PAYMENT COMPLETED] window.ckoAddPaymentSessionIdField not available');
								
								// Fallback: Try to manually add if we have the session ID
								if (window.currentPaymentSessionId) {
									const checkoutForm = document.querySelector('form.checkout');
									if (checkoutForm) {
										const manualField = document.createElement('input');
										manualField.type = 'hidden';
										manualField.id = 'cko-flow-payment-session-id';
										manualField.name = 'cko-flow-payment-session-id';
										manualField.value = window.currentPaymentSessionId;
										checkoutForm.appendChild(manualField);
										ckoLogger.debug('[PAYMENT COMPLETED] Payment session ID manually added to form (fallback - no function)');
									}
								} else {
									ckoLogger.error('[PAYMENT COMPLETED] CRITICAL: Payment session ID cannot be added - window.currentPaymentSessionId is not set!');
								}
							}
						} else {
							ckoLogger.debug('[PAYMENT COMPLETED] Payment session ID already in form: ' + existingSessionIdValue.substring(0, 20) + '...');
						}
						
						jQuery("form.checkout").submit();
						return;
					}
					
					ckoLogger.debug('[PAYMENT COMPLETED] Order already exists (ID: ' + orderIdToUse + ') - redirecting to process payment endpoint');
					
					// For order-pay pages, use native DOM submit to bypass event handlers
					if (orderId && window.location.pathname.includes('/order-pay/')) {
						ckoLogger.threeDS('Submitting order-pay form using native submit after payment completion');
						const orderPayForm = document.querySelector('form#order_review');
						if (orderPayForm) {
							orderPayForm.submit();
						} else {
							ckoLogger.error('ERROR: form#order_review not found!');
						}
					} else {
						// For regular checkout with card payments, redirect to process payment endpoint with order ID, order key, and payment ID
						// This ensures process_payment() is called with the existing order
						// CRITICAL: handle_3ds_return() requires cko-payment-id in GET params
						// CRITICAL: Order key is required for guest orders to access order received page
						ckoLogger.debug('[PAYMENT COMPLETED] ========== ORDER KEY RETRIEVAL DEBUG ==========');
						ckoLogger.debug('[PAYMENT COMPLETED] Order ID to use:', orderIdToUse);
						ckoLogger.debug('[PAYMENT COMPLETED] Checking sessionStorage for order key...');
						const orderKey = sessionStorage.getItem('cko_flow_order_key') || '';
						ckoLogger.debug('[PAYMENT COMPLETED] Order key from sessionStorage:', orderKey || 'NOT FOUND');
						ckoLogger.debug('[PAYMENT COMPLETED] Order key type:', typeof orderKey);
						ckoLogger.debug('[PAYMENT COMPLETED] Order key length:', orderKey ? orderKey.length : 0);
						ckoLogger.debug('[PAYMENT COMPLETED] All sessionStorage keys:', Object.keys(sessionStorage));
						ckoLogger.debug('[PAYMENT COMPLETED] SessionStorage cko_flow_order_id:', sessionStorage.getItem('cko_flow_order_id'));
						ckoLogger.debug('[PAYMENT COMPLETED] SessionStorage cko_flow_order_key:', sessionStorage.getItem('cko_flow_order_key'));
						
						let redirectUrl = window.location.origin + '/?wc-api=wc_checkoutcom_flow_process&order_id=' + orderIdToUse + '&cko-payment-id=' + paymentResponse.id;
						if (orderKey) {
							redirectUrl += '&key=' + encodeURIComponent(orderKey);
							ckoLogger.debug('[PAYMENT COMPLETED] ✅ Order key found in session storage, including in redirect URL');
							ckoLogger.debug('[PAYMENT COMPLETED] Order key (encoded):', encodeURIComponent(orderKey));
						} else {
							ckoLogger.error('[PAYMENT COMPLETED] ❌ Order key not found in session storage - guest order may not display correctly');
							ckoLogger.error('[PAYMENT COMPLETED] This will cause "Please log in" message on order received page');
						}
						ckoLogger.debug('[PAYMENT COMPLETED] Final redirect URL:', redirectUrl);
						ckoLogger.debug('[PAYMENT COMPLETED] ========== END ORDER KEY DEBUG ==========');
						window.location.href = redirectUrl;
					}
				}
			}
		};

				/*
				 * Triggered when user submits the payment using Place Order Button of Woocommerce.
				 */
				const onSubmit = async (component) => {
					showLoadingOverlay(2);
					return { continue: true };
				};

				/*
				 * Triggered on component state change.
				 */
				const onChange = (component) => {
					hideLoadingOverlay();
					
					// Initialize tracking variables if not exists
					if (!window.onChangeTracking) {
						window.onChangeTracking = {
							lastState: {},
							lastProcessedTime: 0,
							processingCount: 0
						};
					}
					
					const now = Date.now();
					const currentState = {
						selectedType: component.selectedType,
						componentType: component.type,
						isValid: component.isValid ? component.isValid() : false
					};
					
					// Check if state has actually changed
					const stateChanged = (
						window.onChangeTracking.lastState.selectedType !== currentState.selectedType ||
						window.onChangeTracking.lastState.componentType !== currentState.componentType ||
						window.onChangeTracking.lastState.isValid !== currentState.isValid
					);
					
					// Rate limiting: don't process more than once every 50ms
					const timeSinceLastProcess = now - window.onChangeTracking.lastProcessedTime;
					const shouldRateLimit = timeSinceLastProcess < 50;
					
					// Only process if state actually changed AND enough time has passed
					if (!stateChanged || shouldRateLimit) {
						window.onChangeTracking.processingCount++;
						if (window.onChangeTracking.processingCount % 10 === 0) {
							ckoLogger.debug(`onChange - Skipped ${window.onChangeTracking.processingCount} duplicate events`);
						}
						return;
					}
					
					// Update tracking
					window.onChangeTracking.lastState = { ...currentState };
					window.onChangeTracking.lastProcessedTime = now;
					window.onChangeTracking.processingCount = 0;
					
			ckoLogger.debug('===== onChange START =====');
			ckoLogger.debug('onChange - Selected Type:', component.selectedType);
			ckoLogger.debug('onChange - Component Type:', component.type);
			ckoLogger.debug('onChange - Component valid:', component.isValid ? component.isValid() : 'unknown');
			ckoLogger.debug('onChange - User interacted flag:', FlowState.get('userInteracted'));
			ckoLogger.debug('onChange - Body has flow-ready class:', document.body.classList.contains('flow-ready'));
			
			// CRITICAL: Auto-deselect saved card when user interacts with Flow
			// Check if a saved card is currently selected and Flow is de-emphasized
			const flowContainer = document.getElementById("flow-container");
			const anySavedCardSelected = jQuery('input[name="wc-wc_checkout_com_flow-payment-token"]:checked').filter(function() {
				return jQuery(this).attr('id') !== 'wc-wc_checkout_com_flow-payment-token-new';
			}).length > 0;
			
		if (anySavedCardSelected && flowContainer) {
			ckoLogger.debug('onChange - User typing in Flow while saved card selected - auto-activating Flow');
			// Call the activation function
			if (typeof window.activateFlowPayment === 'function') {
				window.activateFlowPayment();
			} else {
				// Fallback if function not available yet
				jQuery('input[name="wc-wc_checkout_com_flow-payment-token"]:checked').prop('checked', false);
				window.flowSavedCardSelected = false;
				ckoLogger.debug('onChange - Deselected saved card and activated Flow (fallback)');
			}
		}
			
			// Note: We don't set flowUserInteracted here anymore because onChange
			// fires on initial load. Instead, we use click/focus/input listeners
			// to detect actual user interaction with Flow fields.

					const hiddenTypes = [
						"applepay",
						"googlepay",
						"octopus",
						"paypal",
						"twint",
						"venmo",
						"wechatpay"
					];

					const placeOrderButton = document.querySelector("#place_order");

					// Hide place order button on digital wallets.
					if (hiddenTypes.includes(component.selectedType)) {
						if (placeOrderButton) placeOrderButton.style.display = "none";
						ckoLogger.debug('onChange - Hiding Place Order button');
					} else {
						if (placeOrderButton) placeOrderButton.style.display = "block";
						ckoLogger.debug('onChange - Showing Place Order button');
					}
					
					ckoLogger.debug('===== onChange END =====');
						
					// Pre-validate for apple pay.
					if ( component.selectedType === "applepay" ) {
						const applePayButton = document.querySelector('button[aria-label="Apple Pay"]');
						applePayButton.disabled = true;

						const form = jQuery("form.checkout");

						if ( ! orderId ) {
							validateCheckout(form, function (response) {
								applePayButton.disabled = false;
							});
						}
					}

					// Control Save to Account checkbox visibility based on payment type
					const saveCardCheckbox = jQuery('.cko-save-card-checkbox');
					if ( saveCardCheckbox.length > 0 ) {
						if ( component.selectedType === "card" ) {
							saveCardCheckbox.addClass('wc-cko-flow-card-on');
							saveCardCheckbox.css('display', 'block'); // Use .css() to set inline style
							saveCardCheckbox.show();
							// Also show the inner <p> element that contains the actual checkbox
							const saveNewElement = saveCardCheckbox.find('.woocommerce-SavedPaymentMethods-saveNew');
							if ( saveNewElement.length > 0 ) {
								saveNewElement.css('display', 'block');
								saveNewElement.show();
							}
							ckoLogger.debug('onChange - Showing Save to Account checkbox for CARD');
						} else {
							saveCardCheckbox.removeClass('wc-cko-flow-card-on');
							saveCardCheckbox.css('display', 'none'); // Use .css() to set inline style
							saveCardCheckbox.hide();
							ckoLogger.debug('onChange - Hiding Save to Account checkbox for', component.selectedType);
						}
					} else {
						ckoLogger.debug('onChange - Save card checkbox not available (feature disabled)');
					}

					ckoLogger.debug(
						`onChange() -> isValid: "${component.isValid()}" for "${
							component.type
						}"`
					);
					
				// Additional debugging for Flow component validation issues
				if (component.type === 'flow' && !component.isValid()) {
					ckoLogger.debug('Flow component validation failed - checking component state:', {
						componentType: component.type,
						isValid: component.isValid(),
						componentState: component.state || 'no state available',
						componentErrors: component.errors || 'no errors available'
					});
				}
				};

					/*
					 * Triggered on component click.
					 */
					const handleClick = (component) => {

						if(component.type==="applepay") {
							return {continue: true};
						}

						if ( orderId ) {
							return {continue: true};
						}

						return new Promise((resolve) => {
							const form = jQuery("form.checkout");
					
							validateCheckout(form, function (response) {
								resolve({ continue: true });
							});
						});
					};

				/*
				 * Triggered on any error in the component.
				 */
				const onError = (component, error) => {
					ckoLogger.error("onError", error, "Component", component.type);

					// Hide loading overlay and skeleton.
					hideLoadingOverlay();
					hideLoadingOverlay(2);
					
					const skeleton = document.getElementById("flow-skeleton");
					const placeOrderBtn = document.getElementById("place_order");
					
					if (skeleton) {
						skeleton.classList.remove("show");
					}
					if (placeOrderBtn) {
						placeOrderBtn.classList.remove("flow-loading");
					}

					// Extract error message from various error object formats
					// CheckoutError objects might have message in different places
					let errorMessage = '';
					if (error.message) {
						errorMessage = error.message;
					} else if (error.toString && typeof error.toString === 'function') {
						errorMessage = error.toString();
					} else if (typeof error === 'string') {
						errorMessage = error;
					} else {
						errorMessage = JSON.stringify(error);
					}
					
					// Handle specific error types with user-friendly messages
					let userFriendlyMessage = null;
					let isPaymentDeclined = false;
					
					if (errorMessage.includes("[Request]: Network request failed [payment_request_failed]")) {
						ckoLogger.error("Payment request failed - checking configuration:");
						ckoLogger.error("Public Key:", cko_flow_vars.PKey ? 'SET' : 'NOT SET');
						ckoLogger.error("Environment:", cko_flow_vars.env);
						ckoLogger.error("Payment Session ID:", paymentSession ? paymentSession.id : 'N/A');
						userFriendlyMessage = "Payment request failed. Please check your payment gateway configuration or try again.";
					} else if (errorMessage.includes("[Request]: Payment request declined [payment_request_declined]") || 
					           errorMessage.includes("payment_request_declined")) {
						ckoLogger.error("Payment request declined by Checkout.com");
						ckoLogger.error("Error details:", error);
						// User-friendly message for payment decline
						userFriendlyMessage = "Your payment was declined. Please check your card details and try again, or use a different payment method.";
						isPaymentDeclined = true;
					} else if (errorMessage === "[Submit]: Component is invalid [component_invalid]") {
						userFriendlyMessage = "Please complete your payment before placing the order.";
					} else if (errorMessage.includes("declined")) {
						// Catch any other decline-related errors
						ckoLogger.error("Payment declined (generic decline error)");
						ckoLogger.error("Error details:", error);
						userFriendlyMessage = "Your payment was declined. Please check your card details and try again, or use a different payment method.";
						isPaymentDeclined = true;
					}
					
					// Use user-friendly message if available, otherwise use original or fallback
					const finalErrorMessage = userFriendlyMessage || errorMessage || wp.i18n.__(
						"Something went wrong. Please try again.",
						"checkout-com-unified-payments-api"
					);
					
					// Always show error message to user
					ckoLogger.error("Displaying error to user:", finalErrorMessage);
					showError(finalErrorMessage);
					
					// CRITICAL: Create order for declined payments if Place Order was clicked
					// This ensures failed payment attempts are tracked even when payment fails before form submission
					ckoLogger.error("[ORDER CREATION DEBUG] Checking conditions:");
					ckoLogger.error("[ORDER CREATION DEBUG] isPaymentDeclined:", isPaymentDeclined);
					ckoLogger.error("[ORDER CREATION DEBUG] window.ckoPlaceOrderClicked:", window.ckoPlaceOrderClicked);
					ckoLogger.error("[ORDER CREATION DEBUG] cko_flow_vars:", typeof cko_flow_vars !== 'undefined' ? 'defined' : 'undefined');
					ckoLogger.error("[ORDER CREATION DEBUG] ajax_url:", typeof cko_flow_vars !== 'undefined' ? cko_flow_vars.ajax_url : 'N/A');
					
					if (isPaymentDeclined && window.ckoPlaceOrderClicked) {
						ckoLogger.error("[ORDER CREATION] Payment declined AFTER Place Order clicked - creating failed order for tracking");
						
						const form = jQuery("form.checkout");
						ckoLogger.error("[ORDER CREATION DEBUG] Form found:", form.length > 0);
						
						if (form.length > 0) {
							const formData = form.serialize();
							ckoLogger.error("[ORDER CREATION DEBUG] Form data serialized, length:", formData.length);
							
							// Check if nonce exists in form
							const nonceField = jQuery('input[name="woocommerce-process-checkout-nonce"]');
							const nonceValue = nonceField.length > 0 ? nonceField.val() : '';
							ckoLogger.error("[ORDER CREATION DEBUG] Nonce field found:", nonceField.length > 0);
							ckoLogger.error("[ORDER CREATION DEBUG] Nonce value:", nonceValue ? nonceValue.substring(0, 10) + '...' : 'EMPTY');
							
							// Parse form data into object
							const formDataObj = Object.fromEntries(new URLSearchParams(formData));
							
							// Ensure nonce is explicitly included
							if (!formDataObj['woocommerce-process-checkout-nonce'] && nonceValue) {
								formDataObj['woocommerce-process-checkout-nonce'] = nonceValue;
								ckoLogger.error("[ORDER CREATION DEBUG] Added nonce explicitly to data");
							}
							
							// Create order via AJAX to track failed payment attempt
							jQuery.ajax({
								url: cko_flow_vars.ajax_url,
								type: "POST",
								data: {
									action: "cko_flow_create_failed_order",
									error_reason: "payment_request_declined",
									error_message: finalErrorMessage,
									...formDataObj
								},
								success: function(response) {
									ckoLogger.error("[ORDER CREATION] AJAX Success Response:", response);
									if (response.success && response.data && response.data.order_id) {
										ckoLogger.error("[ORDER CREATION] ✅ Failed order created successfully - Order ID: " + response.data.order_id);
									} else {
										ckoLogger.error("[ORDER CREATION] ❌ Failed to create order for declined payment:", response);
									}
								},
								error: function(xhr, status, error) {
									ckoLogger.error("[ORDER CREATION] ❌ AJAX Error:", error);
									ckoLogger.error("[ORDER CREATION] ❌ Status:", status);
									ckoLogger.error("[ORDER CREATION] ❌ XHR Response:", xhr.responseText);
									ckoLogger.error("[ORDER CREATION] ❌ XHR Status Code:", xhr.status);
									ckoLogger.error("[ORDER CREATION] ❌ XHR Response Headers:", xhr.getAllResponseHeaders());
									
									// Try to parse response if available
									if (xhr.responseText) {
										try {
											const responseData = JSON.parse(xhr.responseText);
											ckoLogger.error("[ORDER CREATION] ❌ Parsed Response:", responseData);
										} catch (e) {
											ckoLogger.error("[ORDER CREATION] ❌ Response is not JSON:", xhr.responseText.substring(0, 500));
										}
									}
								}
							});
						} else {
							ckoLogger.error("[ORDER CREATION] ❌ Form not found - cannot create order");
						}
						
						// Clear the flag
						window.ckoPlaceOrderClicked = false;
					} else {
						ckoLogger.error("[ORDER CREATION] ⚠️ Conditions not met - order NOT created");
						if (!isPaymentDeclined) {
							ckoLogger.error("[ORDER CREATION] ⚠️ Reason: Payment not declined");
						}
						if (!window.ckoPlaceOrderClicked) {
							ckoLogger.error("[ORDER CREATION] ⚠️ Reason: Place Order not clicked");
						}
					}
					
					// Log the error for debugging
					ckoLogger.error("Flow component error:", {
						component: component.type,
						originalMessage: errorMessage,
						userFriendlyMessage: userFriendlyMessage,
						finalErrorMessage: finalErrorMessage,
						isPaymentDeclined: isPaymentDeclined,
						placeOrderClicked: window.ckoPlaceOrderClicked || false,
						fullError: error
					});
					};

		// Debug: Log callback functions
		ckoLogger.debug('Callback Functions:', {
			onReady: typeof onReady,
			onPaymentCompleted: typeof onPaymentCompleted,
			onSubmit: typeof onSubmit,
			onChange: typeof onChange,
			handleClick: typeof handleClick,
			onError: typeof onError
		});
		
		// Note: onPaymentCompleted is internal - not exposed globally for security
		
		// WORKAROUND: Add URL fields to paymentSession object based on environment
		// This helps SDK construct correct URLs even if token is missing URL fields
		// SDK might check these fields before/after decoding the token
		const baseUrl = cko_flow_vars.env === 'sandbox' 
			? 'https://api.sandbox.checkout.com'
			: 'https://api.checkout.com';
		const cdnUrl = cko_flow_vars.env === 'sandbox'
			? 'https://cdn.sandbox.checkout.com'
			: 'https://cdn.checkout.com';
		const devicesUrl = cko_flow_vars.env === 'sandbox'
			? 'https://devices.api.sandbox.checkout.com'
			: 'https://devices.api.checkout.com';
		
		// Add URL fields to paymentSession object (SDK might use these)
		// Note: SDK decodes token internally, but might fall back to these if token URLs are missing
		if (!paymentSession._urls) {
			paymentSession._urls = {
				api_url: baseUrl,
				cdn_url: cdnUrl,
				devices_url: devicesUrl,
				base_url: baseUrl
			};
			ckoLogger.debug('Added URL fields to paymentSession:', paymentSession._urls);
		}
		
		// NOTE: We cannot pre-load risk.js manually because:
		// 1. The SDK constructs the risk.js URL internally from the payment session token
		// 2. The SDK decodes the token and extracts URLs (cdn_url, etc.)
		// 3. If token is missing URL fields, SDK constructs incorrect URLs
		// 4. We cannot modify the token without decoding it (which we're avoiding)
		// 5. The SDK must load risk.js itself - we can't intercept or pre-load it
		// 
		// SOLUTION: The payment session token MUST include URL fields (cdn_url, api_url, etc.)
		// This is an API issue that needs to be fixed by Checkout.com
		// 
		// We've added URL fields to paymentSession._urls as a fallback, but the SDK
		// primarily uses URLs from the decoded token, so this may not help.
		
		const checkout = await CheckoutWebComponents({
			publicKey: cko_flow_vars.PKey,
			environment: cko_flow_vars.env,
			locale: window.locale,
			paymentSession,
			appearance: window.appearance,
			componentOptions: window.componentOptions,
			translations: window.translations,
			onReady,
			onPaymentCompleted,
			onSubmit,
			onChange,
			handleClick,
			onError
		});

		// Log SDK initialization (debug mode only)
		ckoLogger.debug('SDK initialized:', {
			hasCreate: typeof checkout.create === 'function',
			paymentSessionId: paymentSession.id,
			environment: cko_flow_vars.env
		});


				// Ensure component name is defined
				const componentName = window.componentName || 'flow';
				ckoLogger.debug('Creating Flow component with name:', componentName);
				
				let flowComponent;
				try {
					flowComponent = checkout.create(componentName, {
						showPayButton: false,
					});
					
					ckoLogger.debug('Flow component created successfully:', {
						componentName: componentName,
						componentType: flowComponent.type
					});
				} catch (error) {
					ckoLogger.error('Error creating Flow component:', error);
					showError('Failed to initialize payment component. Please try again.');
					return;
				}

				// Performance: Track component creation
				const componentInitEnd = performance.now();
				const componentInitDuration = componentInitEnd - componentInitStart;
				if (ckoFlow.performanceMetrics.enableLogging) {
					ckoLogger.performance(`Component initialized in ${componentInitDuration.toFixed(2)}ms (${(componentInitDuration / 1000).toFixed(2)}s)`);
				}

				ckoFlow.flowComponent = flowComponent;

				/*
				 * Check if the component is available. Mount component only if available.
				 */
				flowComponent.isAvailable().then((available) => {
					// Log component availability (debug mode only)
					ckoLogger.debug('Component availability:', {
						available: available,
						componentType: flowComponent.type,
						environment: cko_flow_vars.env
					});
					
					if (available) {
						// CRITICAL FIX: Ensure container exists before mounting with retry logic
						// mountWithRetry will handle container creation, mounting, and UI enabling
						ckoFlow.mountWithRetry(flowComponent);
					} else {
						// Hide loading overlay.
						hideLoadingOverlay();
						ckoLogger.error("Component is not available.");
						console.error('[FLOW UI ERROR] Component is not available - showing error message');
						console.error('[FLOW UI ERROR] This is a CLIENT-SIDE (JavaScript) error');

						showError(
							wp.i18n.__(
								"The selected payment method is not available at this time.",
								"checkout-com-unified-payments-api"
							)
						);
					}
				});
			}
		} catch (error) {
			// Clear initialization guard flag on error
			ckoFlowInitializing = false;
			ckoFlowInitialized = false;
			
			// Hide loading overlay and skeleton.
			hideLoadingOverlay();
			
			const skeleton = document.getElementById("flow-skeleton");
			const placeOrderBtn = document.getElementById("place_order");
			
			if (skeleton) {
				skeleton.classList.remove("show");
			}
			if (placeOrderBtn) {
				placeOrderBtn.classList.remove("flow-loading");
			}
			
			ckoLogger.error("Error creating payment session:", error);

			showError(
				error.message ||
					wp.i18n.__(
						"Error creating payment session.",
						"checkout-com-unified-payments-api"
					)
			);
		}
	},

	/**
	 * CRITICAL FIX: Mount Flow component with retry logic to handle container race conditions
	 * This ensures the container exists before mounting and handles cases where
	 * WooCommerce removes the container during initialization
	 */
	mountWithRetry: function(flowComponent, attempt = 1, maxAttempts = 5) {
		const mountStart = performance.now();
		if (ckoFlow.performanceMetrics.enableLogging) {
			ckoLogger.performance('Attempting to mount component (attempt ' + attempt + ')...');
		}

		// Get container - may be null if WooCommerce removed it
		let flowContainer = document.getElementById("flow-container");
		
		// If container doesn't exist, try to create it efficiently
		if (!flowContainer) {
			ckoLogger.debug('Container not found before mount (attempt ' + attempt + ') - ensuring container exists', {
				attempt: attempt,
				maxAttempts: maxAttempts,
				flowInitializing: typeof ckoFlowInitializing !== 'undefined' ? ckoFlowInitializing : false
			});

			// OPTIMIZATION: Try direct container creation first (faster than calling addPaymentMethod)
			// This avoids unnecessary DOM queries in addPaymentMethod if container can be created directly
			const paymentMethod = document.querySelector('.payment_method_wc_checkout_com_flow');
			if (paymentMethod) {
				const paymentBox = paymentMethod.querySelector("div.payment_box");
				if (paymentBox && !paymentBox.id) {
					paymentBox.id = "flow-container";
					paymentBox.style.padding = "0";
					flowContainer = paymentBox; // Use the newly created container directly
					ckoLogger.debug('Created flow-container id directly on payment_box div');
				} else if (paymentBox && paymentBox.id === 'flow-container') {
					flowContainer = paymentBox; // Container exists but getElementById didn't find it (timing issue)
				}
			}

			// If direct creation failed, try addPaymentMethod (more comprehensive but slower)
			if (!flowContainer && typeof addPaymentMethod === 'function') {
				addPaymentMethod();
				// Re-check container after addPaymentMethod call
				flowContainer = document.getElementById("flow-container");
			}
		}

		// If container still doesn't exist, retry with exponential backoff
		if (!flowContainer) {
			if (attempt < maxAttempts) {
				const delay = Math.min(200 * Math.pow(2, attempt - 1), 1000); // Exponential backoff: 200ms, 400ms, 600ms, 800ms, 1000ms
				ckoLogger.debug('Container still not found, retrying in ' + delay + 'ms (attempt ' + attempt + '/' + maxAttempts + ')');
				
				setTimeout(() => {
					ckoFlow.mountWithRetry(flowComponent, attempt + 1, maxAttempts);
				}, delay);
				return;
			} else {
				// Max attempts reached - show error
				ckoLogger.error('Failed to mount Flow component: Container not found after ' + maxAttempts + ' attempts');
				hideLoadingOverlay();
				showError(
					wp.i18n.__(
						"Unable to initialize payment form. Please refresh the page and try again.",
						"checkout-com-unified-payments-api"
					)
				);
				// Clear initialization flags
				ckoFlowInitializing = false;
				ckoFlowInitialized = false;
				return;
			}
		}

		// Container exists - proceed with mount
		try {
			ckoLogger.debug('Container found, mounting Flow component', {
				containerId: flowContainer.id,
				attempt: attempt
			});

			flowComponent.mount(flowContainer);
			
			const mountEnd = performance.now();
			if (ckoFlow.performanceMetrics.enableLogging) {
				ckoLogger.performance(`Component mounted successfully in ${(mountEnd - mountStart).toFixed(2)}ms (attempt ${attempt})`);
			}

			// Mark Flow as initialized and clear guard flag now that component is mounted
			ckoFlowInitialized = true;
			ckoFlowInitializing = false;

			// Enable UI after successful mount
			ckoFlow.enableUIAfterMount();
			
		} catch (error) {
			ckoLogger.error('Error mounting Flow component:', error);
			
			// If mount fails, retry if we haven't exceeded max attempts
			if (attempt < maxAttempts) {
				const delay = Math.min(200 * Math.pow(2, attempt - 1), 1000);
				ckoLogger.debug('Mount failed, retrying in ' + delay + 'ms (attempt ' + attempt + '/' + maxAttempts + ')');
				
				setTimeout(() => {
					ckoFlow.mountWithRetry(flowComponent, attempt + 1, maxAttempts);
				}, delay);
			} else {
				// Max attempts reached - show error
				ckoLogger.error('Failed to mount Flow component after ' + maxAttempts + ' attempts:', error);
				hideLoadingOverlay();
				showError(
					wp.i18n.__(
						"Unable to initialize payment form. Please refresh the page and try again.",
						"checkout-com-unified-payments-api"
					)
				);
				// Clear initialization flags
				ckoFlowInitializing = false;
				ckoFlowInitialized = false;
			}
		}
	},

	/**
	 * Enable UI elements after successful Flow component mount
	 */
	enableUIAfterMount: function() {
		ckoLogger.debug('Component mounted - enabling UI! 🔥🔥🔥');
		
		const skeleton = document.getElementById("flow-skeleton");
		const placeOrderBtn = document.getElementById("place_order");
		const saveCardCheckbox = document.querySelector('.cko-save-card-checkbox');
		
		// Step 1: Hide skeleton loader
		if (skeleton) {
			skeleton.classList.remove("show");
			ckoLogger.debug('✓ Skeleton loader hidden');
		}
		
		// Step 2: Enable Place Order button
		if (placeOrderBtn) {
			placeOrderBtn.classList.remove("flow-loading");
			placeOrderBtn.style.removeProperty('opacity');
			placeOrderBtn.style.removeProperty('visibility');
			ckoLogger.debug('Place Order button enabled');
		}
		
		// Step 3: Hide save card checkbox initially
		if (saveCardCheckbox) {
			saveCardCheckbox.style.display = 'none';
		} else {
			ckoLogger.debug('Mount - Save card checkbox not available (feature disabled)');
		}
		
		// Step 4: Mark Flow as ready
		document.body.classList.add("flow-ready");
		ckoLogger.debug('Checkout is now fully interactive');
		
		// CRITICAL: Listen for user interaction with Flow fields (click, focus, input)
		// This detects when user actually starts using Flow (not just onChange firing on load)
		setTimeout(() => {
			const flowContainer = document.getElementById("flow-container");
			if (flowContainer) {
				// Listen for any interaction with Flow component
				flowContainer.addEventListener('click', function() {
					if (!FlowState.get('userInteracted')) {
						ckoLogger.debug('User clicked on Flow component - marking as interacted');
						FlowState.set('userInteracted', true);
						window.flowSavedCardSelected = false; // Reset saved card flag when user interacts with Flow
					}
				}, { once: false });
				
				flowContainer.addEventListener('focus', function(e) {
					if (!FlowState.get('userInteracted')) {
						ckoLogger.debug('User focused on Flow field - marking as interacted');
						FlowState.set('userInteracted', true);
						window.flowSavedCardSelected = false; // Reset saved card flag when user interacts with Flow
					}
				}, { capture: true });
				
				flowContainer.addEventListener('input', function(e) {
					if (!FlowState.get('userInteracted')) {
						ckoLogger.debug('User typing in Flow field - marking as interacted');
						FlowState.set('userInteracted', true);
						window.flowSavedCardSelected = false; // Reset saved card flag when user interacts with Flow
					}
				}, { capture: true });
				
				ckoLogger.debug('Flow interaction listeners attached');
			}
		}, 500);
	},
};

/*
 * Displays error messages at the top of the WooCommerce form.
 */
let showError = function (error_message) {
	ckoLogger.error("showError() called with message:", error_message);
	
	if (!error_message) {
		ckoLogger.error("showError() called with empty/null message");
		return;
	}
	
	if ("string" === typeof error_message) {
		error_message = [error_message];
	}

	let ulWrapper = jQuery("<ul/>")
		.prop("role", "alert")
		.addClass("woocommerce-error");

	if (Array.isArray(error_message)) {
		jQuery.each(error_message, function (index, value) {
			jQuery(ulWrapper).append(jQuery("<li>").html(value));
		});
	}

	let wcNoticeDiv = jQuery("<div>")
		.addClass("woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout")
		.append(ulWrapper);

	let scrollTarget;
	let errorDisplayed = false;

	// Try form.checkout first
	if (jQuery("form.checkout").length) {
		ckoLogger.error("showError() - Found form.checkout, displaying error");
		jQuery("form.checkout .woocommerce-NoticeGroup").remove();
		jQuery("form.checkout").prepend(wcNoticeDiv);
		jQuery(".woocommerce, .form.checkout").removeClass("processing").unblock();
		scrollTarget = jQuery("form.checkout");
		errorDisplayed = true;
	} 
	// Try .woocommerce-order-pay
	else if (jQuery(".woocommerce-order-pay").length) {
		ckoLogger.error("showError() - Found .woocommerce-order-pay, displaying error");
		jQuery(".woocommerce-order-pay .woocommerce-NoticeGroup").remove();
		jQuery(".woocommerce-order-pay").prepend(wcNoticeDiv);
		jQuery(".woocommerce, .woocommerce-order-pay")
			.removeClass("processing")
			.unblock();
		scrollTarget = jQuery(".woocommerce-order-pay");
		errorDisplayed = true;
	}
	// Try .woocommerce-checkout (block theme)
	else if (jQuery(".woocommerce-checkout").length) {
		ckoLogger.error("showError() - Found .woocommerce-checkout, displaying error");
		jQuery(".woocommerce-checkout .woocommerce-NoticeGroup").remove();
		jQuery(".woocommerce-checkout").prepend(wcNoticeDiv);
		jQuery(".woocommerce-checkout").removeClass("processing").unblock();
		scrollTarget = jQuery(".woocommerce-checkout");
		errorDisplayed = true;
	}
	// Fallback: Try to use WooCommerce's notice system
	else if (typeof wc_add_to_notices === 'function') {
		ckoLogger.error("showError() - Using WooCommerce notice system");
		wc_add_to_notices(error_message, 'error');
		errorDisplayed = true;
	}
	// Last resort: Display at top of body
	else {
		ckoLogger.error("showError() - No form found, displaying error at top of body");
		jQuery("body").prepend(wcNoticeDiv);
		errorDisplayed = true;
	}

	if (errorDisplayed) {
		ckoLogger.error("showError() - Error displayed successfully");
		
		// Store error message to persist through updated_checkout events
		// WooCommerce's updated_checkout event replaces the form HTML, clearing our error
		if (error_message && error_message.length > 0) {
			FlowState.set('lastError', Array.isArray(error_message) ? error_message[0] : error_message);
			ckoLogger.error("showError() - Stored error message for persistence:", FlowState.get('lastError'));
		}
	} else {
		ckoLogger.error("showError() - ERROR: Failed to display error message!");
	}

	// Scroll to top of checkout form or error message
	if (scrollTarget && scrollTarget.length) {
		jQuery("html, body").animate(
			{
				scrollTop: scrollTarget.offset().top - 100,
			},
			1000
		);
	} else if (wcNoticeDiv.length) {
		// Scroll to the error message itself
		jQuery("html, body").animate(
			{
				scrollTop: wcNoticeDiv.offset().top - 100,
			},
			1000
		);
	}
};

// Re-display error messages after WooCommerce's updated_checkout event
// This ensures payment decline errors persist even when WooCommerce refreshes the checkout form
jQuery(document.body).on('updated_checkout', function() {
	if (FlowState.get('lastError')) {
		ckoLogger.error("updated_checkout fired - Re-displaying stored error:", FlowState.get('lastError'));
		// Use a small delay to ensure form is fully updated
		setTimeout(function() {
			showError(FlowState.get('lastError'));
			// Verify error is visible in DOM
			setTimeout(function() {
				const errorElement = jQuery("form.checkout .woocommerce-NoticeGroup, .woocommerce-order-pay .woocommerce-NoticeGroup, .woocommerce-checkout .woocommerce-NoticeGroup");
				if (errorElement.length > 0) {
					ckoLogger.error("Error element found in DOM:", errorElement.length, "elements");
					// Make sure it's visible
					errorElement.css('display', 'block').css('visibility', 'visible');
					// Scroll to it
					jQuery("html, body").animate({
						scrollTop: errorElement.offset().top - 100
					}, 500);
				} else {
					ckoLogger.error("ERROR: Error element NOT found in DOM after re-display!");
				}
			}, 200);
			// Clear the stored error after displaying (user can dismiss it manually)
			// Don't clear immediately - let it persist for a few seconds
			setTimeout(function() {
				FlowState.set('lastError', null);
				ckoLogger.error("Cleared stored error message");
			}, 5000);
		}, 100);
	}
});

/**
 * REFACTORED: State variables migrated to FlowState module
 * 
 * State is now managed centrally via FlowState module (modules/flow-state.js).
 * The following variables are now accessed via FlowState:
 * - ckoFlowInitialized → FlowState.initialized
 * - ckoFlowInitializing → FlowState.initializing
 * - ckoOrderCreationInProgress → FlowState.orderCreationInProgress
 * - window.flowUserInteracted → FlowState.userInteracted
 * - window.ckoFlow3DSReturn → FlowState.is3DSReturn
 * - window.ckoFlowFieldsWereFilled → FlowState.fieldsWereFilled
 * - window.ckoLastError → FlowState.lastError
 * - reloadFlowTimeout → FlowState.reloadFlowTimeout
 * 
 * Backward compatibility is maintained via property descriptors in FlowState module,
 * so existing code using ckoFlowInitialized and ckoFlowInitializing will continue to work.
 * 
 * Module location: flow-integration/assets/js/modules/flow-state.js
 */

// Ensure FlowState is available (should be loaded before this file)
if (typeof window.FlowState === 'undefined') {
	console.error('[FLOW] FlowState module not loaded - state management unavailable');
	// Fallback: Create minimal FlowState object
	window.FlowState = {
		initialized: false,
		initializing: false,
		orderCreationInProgress: false,
		userInteracted: false,
		is3DSReturn: false,
		fieldsWereFilled: false,
		lastError: null,
		reloadFlowTimeout: null,
		set: function(key, value) { this[key] = value; },
		get: function(key) { return this[key]; }
	};
}

/**
 * Initializes the observer to monitor the presence of the Flow checkout component in the DOM.
 *
 * - Sets the `FlowState.initialized` flag to `false` on page load.
 * - Observes the DOM for any changes using `MutationObserver`.
 * - If the Flow checkout component (identified by `data-testid="checkout-web-component-root"`) 
 *   is removed from the DOM, the flag `FlowState.initialized` is reset to `false`.
 *
 * This helps ensure that the Flow component can be re-initialized when needed.
 */

// Note: Early 3DS detection is now at the top of the file (right after ckoLogger definition)
// This ensures it runs before any other code that might initialize Flow

document.addEventListener("DOMContentLoaded", function () {
	// CRITICAL: Check for 3DS return FIRST - before any other checks
	// Check flag first (set by early detection)
	if (FlowState.get('is3DSReturn')) {
		ckoLogger.threeDS('⚠️ DOMContentLoaded: Blocked by 3DS return flag');
		if (typeof ckoLogger !== 'undefined') {
			ckoLogger.threeDS('DOMContentLoaded: 3DS return in progress, skipping all Flow initialization');
		}
		return;
	}
	
	// Also check URL parameters directly as fallback (in case early detection didn't run)
	const urlParams = new URLSearchParams(window.location.search);
	const paymentId = urlParams.get("cko-payment-id");
	const sessionId = urlParams.get("cko-session-id");
	const paymentSessionId = urlParams.get("cko-payment-session-id");
	
	if (paymentId || sessionId || paymentSessionId) {
		ckoLogger.threeDS('⚠️ DOMContentLoaded: Blocked by 3DS return URL parameters', {
			paymentId: paymentId,
			sessionId: sessionId,
			paymentSessionId: paymentSessionId
		});
		FlowState.set('is3DSReturn', true);
		if (typeof ckoLogger !== 'undefined') {
			ckoLogger.threeDS('DOMContentLoaded: 3DS return detected in URL, skipping all Flow initialization');
		}
		return;
	}
	
	// Track page load time for performance metrics
	ckoFlow.performanceMetrics.pageLoadTime = performance.now();
	if (ckoFlow.performanceMetrics.enableLogging) {
		ckoLogger.performance('Page DOMContentLoaded at:', ckoFlow.performanceMetrics.pageLoadTime.toFixed(2) + 'ms');
	}
	
	const element = document.querySelector(
		'[data-testid="checkout-web-component-root"]'
	);
	FlowState.set('initialized', false);

	const observer = new MutationObserver(() => {
		const element = document.querySelector(
			'[data-testid="checkout-web-component-root"]'
		);

		// If the element is not present, update ckoFlowInitialized.
		if (!element) {
			ckoFlowInitialized = false;
		}
	});

	// Observe the entire document for any changes in the DOM.
	observer.observe(document.body, {
		childList: true,
		subtree: true,
	});
});

/*
 * Listens to changes in the payment method radio buttons
 * and initializes the Flow payment method if selected.
 * 
 * This function handles the logic for showing or hiding the Flow payment container
 * and initializing the Flow checkout component when the Flow payment method is selected.
 * It also manages the visibility of saved payment methods and ensures the Flow component is
 * initialized when needed.
 */
/**
 * Validation Helper Functions
 * These functions check if Flow can be initialized based on required fields
 */

/**
 * Check if user is logged in
 * @returns {boolean}
 */
/*
 * REFACTORED: Validation functions extracted to modules/flow-validation.js
 * 
 * The validation functions are now loaded as a separate module before this file.
 * This reduces complexity and improves maintainability.
 * 
 * Module location: flow-integration/assets/js/modules/flow-validation.js
 * 
 * The module exposes functions both via FlowValidation namespace and globally:
 * - FlowValidation.isUserLoggedIn()
 * - FlowValidation.getCheckoutFieldValue()
 * - FlowValidation.isValidEmail()
 * - FlowValidation.isPostcodeRequiredForCountry()
 * - FlowValidation.hasBillingAddress()
 * - FlowValidation.hasCompleteBillingAddress()
 * - FlowValidation.requiredFieldsFilledAndValid()
 * - FlowValidation.requiredFieldsFilled()
 * 
 * Global functions (for backward compatibility):
 * - isUserLoggedIn()
 * - getCheckoutFieldValue()
 * - isValidEmail()
 * - isPostcodeRequiredForCountry()
 * - hasBillingAddress()
 * - hasCompleteBillingAddress()
 * - requiredFieldsFilledAndValid()
 * - requiredFieldsFilled()
 * 
 * Fallback: If module didn't load, functions will be undefined and code will fail gracefully
 */
// Validation functions are now provided by flow-validation.js module
// Functions are exposed globally for backward compatibility

/**
 * Check if Flow can be initialized
 * Validates all requirements before allowing Flow initialization
 * @returns {boolean}
 */
function canInitializeFlow() {
	// Check if Flow payment method is selected
	const flowPayment = document.getElementById("payment_method_wc_checkout_com_flow");
	if (!flowPayment || !flowPayment.checked) {
		ckoLogger.debug('canInitializeFlow: Flow payment method not selected');
		return false;
	}
	
	// Check if container exists
	const flowContainer = document.getElementById("flow-container");
	if (!flowContainer) {
		ckoLogger.debug('canInitializeFlow: Flow container not found');
		return false;
	}
	
	// Check if already initialized
	if (ckoFlowInitialized && ckoFlow.flowComponent) {
		ckoLogger.debug('canInitializeFlow: Already initialized');
		return true; // Already initialized
	}
	
	// Check cart has items
	// Note: cart_total might not be available in cko_flow_vars
	// If we're on checkout page, WooCommerce ensures cart has items (redirects empty carts)
	// So we can skip strict cart validation and let the API handle it if cart is truly empty
	const cartTotal = cko_flow_vars?.cart_total ? parseFloat(cko_flow_vars.cart_total) : null;
	const isOnCheckoutPage = window.location.pathname.includes('/checkout') || window.location.pathname.includes('/order-pay/');
	
	if (cartTotal !== null && cartTotal <= 0 && !isOnCheckoutPage) {
		ckoLogger.debug('canInitializeFlow: Cart is empty', { 
			cartTotal: cartTotal,
			isOnCheckoutPage: isOnCheckoutPage
		});
		return false;
	}
	
	// If cart_total not available but we're on checkout page, assume cart has items
	if (cartTotal === null && isOnCheckoutPage) {
		ckoLogger.debug('canInitializeFlow: cart_total not available, but on checkout page - assuming cart has items');
	}
	
	// Check if user is logged in
	const userLoggedIn = isUserLoggedIn();
	
	// Check if we're on order-pay page (special handling)
	const isOrderPayPage = window.location.pathname.includes('/order-pay/');
	let orderPayInfo = null;
	if (isOrderPayPage) {
		orderPayInfo = jQuery("#order-pay-info")?.data("order-pay");
	}
	
	ckoLogger.debug('canInitializeFlow: User status', { 
		isLoggedIn: userLoggedIn,
		isOrderPayPage: isOrderPayPage,
		hasOrderData: !!orderPayInfo,
		hasBillingAddress: !!(orderPayInfo && orderPayInfo.billing_address)
	});
	
	if (userLoggedIn) {
		// Logged-in users can initialize if billing address exists
		// But still check if address is filled (user might have cleared it)
		// On order-pay pages, hasBillingAddress() now checks order data first
		const hasAddress = hasBillingAddress();
		ckoLogger.debug('canInitializeFlow: Logged-in user address check', { 
			hasAddress: hasAddress,
			isOrderPayPage: isOrderPayPage,
			usingOrderData: !!(isOrderPayPage && orderPayInfo && orderPayInfo.billing_address)
		});
		if (hasAddress) {
			return true;
		}
		// Address was cleared - wait for user to fill it
		ckoLogger.debug('Cannot initialize Flow - logged-in user but billing address not filled');
		return false;
	}
	
	// Guest users: Check ALL required fields are filled and valid
	// On order-pay pages, requiredFieldsFilledAndValid() now checks order data first
	const requiredFilled = requiredFieldsFilled();
	const requiredValid = requiredFieldsFilledAndValid();
	
	// Get field values for logging (prefer order data on order-pay pages)
	let email, address1, city, country;
	if (isOrderPayPage && orderPayInfo && orderPayInfo.billing_address) {
		const billing = orderPayInfo.billing_address;
		email = billing.email || billing.Email || '';
		address1 = billing.street_address || billing.address_line1 || '';
		city = billing.city || '';
		country = billing.country || '';
	} else {
		email = getCheckoutFieldValue("billing_email");
		address1 = getCheckoutFieldValue("billing_address_1");
		city = getCheckoutFieldValue("billing_city");
		country = getCheckoutFieldValue("billing_country");
	}
	
	// DETAILED LOGGING: Log each field individually
	const emailField = document.getElementById("billing_email");
	const addressField = document.getElementById("billing_address_1");
	const cityField = document.getElementById("billing_city");
	const countryField = document.getElementById("billing_country");
	const postcodeField = document.getElementById("billing_postcode");
	
	ckoLogger.debug('===== canInitializeFlow: Guest user validation DETAILED =====');
	ckoLogger.debug('Field-by-field analysis:', {
		email: {
			fieldExists: !!emailField,
			value: email || 'EMPTY',
			rawValue: emailField?.value || 'EMPTY',
			disabled: emailField?.disabled || false,
			display: emailField?.style.display || 'default',
			offsetParent: !!emailField?.offsetParent,
			valid: email && isValidEmail(email)
		},
		address1: {
			fieldExists: !!addressField,
			value: address1 || 'EMPTY',
			rawValue: addressField?.value || 'EMPTY',
			disabled: addressField?.disabled || false,
			display: addressField?.style.display || 'default',
			offsetParent: !!addressField?.offsetParent
		},
		city: {
			fieldExists: !!cityField,
			value: city || 'EMPTY',
			rawValue: cityField?.value || 'EMPTY',
			disabled: cityField?.disabled || false,
			display: cityField?.style.display || 'default',
			offsetParent: !!cityField?.offsetParent
		},
		country: {
			fieldExists: !!countryField,
			value: country || 'EMPTY',
			rawValue: countryField?.value || 'EMPTY',
			disabled: countryField?.disabled || false,
			display: countryField?.style.display || 'default',
			offsetParent: !!countryField?.offsetParent
		},
		postcode: {
			fieldExists: !!postcodeField,
			value: getCheckoutFieldValue("billing_postcode") || 'EMPTY',
			rawValue: postcodeField?.value || 'EMPTY',
			disabled: postcodeField?.disabled || false,
			display: postcodeField?.style.display || 'default',
			offsetParent: !!postcodeField?.offsetParent
		}
	});
	
	ckoLogger.debug('Validation results:', {
		requiredFilled: requiredFilled,
		requiredValid: requiredValid,
		isOrderPayPage: isOrderPayPage,
		usingOrderData: !!(isOrderPayPage && orderPayInfo && orderPayInfo.billing_address),
		email: email ? 'SET' : 'EMPTY',
		address1: address1 ? 'SET' : 'EMPTY',
		city: city ? 'SET' : 'EMPTY',
		country: country ? 'SET' : 'EMPTY',
		hasBillingAddress: hasBillingAddress(),
		hasCompleteBillingAddress: hasCompleteBillingAddress()
	});
	
	// Additional validation: Check email format and billing address
	// This function now checks order data first on order-pay pages
	if (!requiredFieldsFilledAndValid()) {
		ckoLogger.debug('Cannot initialize Flow - required fields not filled or invalid');
		return false;
	}
	
	ckoLogger.debug('canInitializeFlow: All checks passed - Flow can initialize');
	return true;
}

/**
 * Show waiting message in Flow container
 */
function showFlowWaitingMessage() {
	let flowContainer = document.getElementById("flow-container");
	
	// If container doesn't exist, try to create it or find the payment method container
	if (!flowContainer) {
		// Try to find the payment method container
		const paymentContainer = document.querySelector(".payment_method_wc_checkout_com_flow");
		if (paymentContainer) {
			// Find or create the payment_box div
			let paymentBox = paymentContainer.querySelector("div.payment_box");
			if (!paymentBox) {
				// Create payment_box if it doesn't exist
				paymentBox = document.createElement('div');
				paymentBox.className = 'payment_box';
				paymentContainer.appendChild(paymentBox);
			}
			
			// Set the id if not already set
			if (!paymentBox.id) {
				paymentBox.id = "flow-container";
				paymentBox.style.padding = "0";
			}
			
			flowContainer = paymentBox;
		} else {
			// Payment method container not found - can't show message
			ckoLogger.debug('Cannot show Flow waiting message - payment method container not found');
			return;
		}
	}
	
	// Show container but with waiting message
	flowContainer.style.display = "block";
	
	// Check if waiting message already exists
	let waitingMessage = flowContainer.querySelector('.flow-waiting-message');
	if (!waitingMessage) {
		waitingMessage = document.createElement('div');
		waitingMessage.className = 'flow-waiting-message';
		waitingMessage.innerHTML = `
			<div style="padding: 20px; text-align: center; background: #f5f5f5; border-radius: 4px; margin: 10px 0;">
				<p style="margin: 0 0 10px 0; font-weight: 600; color: #333;">Please fill in all required fields to continue with payment.</p>
				<p style="margin: 0; font-size: 14px; color: #666;">Required fields: Email, Billing Address</p>
			</div>
		`;
		flowContainer.appendChild(waitingMessage);
	}
	
	ckoLogger.debug('Showing Flow waiting message');
}

/**
 * Hide waiting message in Flow container
 */
function hideFlowWaitingMessage() {
	const flowContainer = document.getElementById("flow-container");
	if (!flowContainer) {
		return;
	}
	
	const waitingMessage = flowContainer.querySelector('.flow-waiting-message');
	if (waitingMessage) {
		waitingMessage.remove();
		ckoLogger.debug('Hiding Flow waiting message');
	}
}

/**
 * Destroy Flow component
 */
function destroyFlowComponent() {
	if (ckoFlow.flowComponent) {
		try {
			// Unmount component if method exists
			if (typeof ckoFlow.flowComponent.unmount === 'function') {
				ckoFlow.flowComponent.unmount();
			}
		} catch (error) {
			ckoLogger.error('Error destroying Flow component:', error);
		}
		ckoFlow.flowComponent = null;
	}
	
	// Clear component root
	const flowComponentRoot = document.querySelector('[data-testid="checkout-web-component-root"]');
	if (flowComponentRoot) {
		flowComponentRoot.innerHTML = '';
	}
	
	ckoLogger.debug('Flow component destroyed');
}

/**
 * Reload Flow component
 */
function reloadFlowComponent() {
	if (!ckoFlow.flowComponent) {
		try {
			initializeFlowIfNeeded();
		} catch (error) {
			ckoLogger.error('Error reloading Flow component:', error);
		}
		return;
	}
	
	if (!window.ckoReloadCount) {
		window.ckoReloadCount = 0;
	}
	window.ckoReloadCount++;
	ckoLogger.debug(`🔄 Reloading Flow component (#${window.ckoReloadCount})`, {
		timestamp: new Date().toLocaleTimeString(),
		reason: 'field change'
	});
	ckoLogger.debug('Reloading Flow component due to field change');
	
	// Destroy existing component
	destroyFlowComponent();
	
	// Reset initialization flag
	ckoFlowInitialized = false;
	
	// Re-initialize Flow
	try {
		initializeFlowIfNeeded();
	} catch (error) {
		ckoLogger.error('Error re-initializing Flow:', error);
	}
}

/**
 * Check required fields status and handle Flow accordingly
 */
function checkRequiredFieldsStatus() {
	const wereFilled = FlowState.get('fieldsWereFilled') || false;
	const areFilled = requiredFieldsFilledAndValid();
	
	ckoLogger.debug('checkRequiredFieldsStatus:', {
		wereFilled: wereFilled,
		areFilled: areFilled,
		flowInitialized: FlowState.get('initialized'),
		flowComponentExists: !!ckoFlow.flowComponent
	});
	
	if (wereFilled && !areFilled) {
		// Fields became unfilled
		ckoLogger.debug('Required fields became unfilled - destroying Flow');
		if (FlowState.get('initialized') && ckoFlow.flowComponent) {
			destroyFlowComponent();
			showFlowWaitingMessage();
			FlowState.set('initialized', false);
		}
	} else if (!wereFilled && areFilled) {
		// Fields became filled - initialize Flow
		ckoLogger.debug('Required fields became filled - initializing Flow');
		if (!FlowState.get('initialized') && !FlowState.get('initializing')) {
			initializeFlowIfNeeded();
		} else if (FlowState.get('initializing')) {
			ckoLogger.debug('Flow initialization already in progress, skipping');
		}
	} else if (areFilled && !FlowState.get('initialized') && !FlowState.get('initializing')) {
		// Fields are filled but Flow not initialized - try to initialize
		ckoLogger.debug('Fields are filled but Flow not initialized - attempting initialization');
		initializeFlowIfNeeded();
	} else if (areFilled && FlowState.get('initializing')) {
		ckoLogger.debug('Flow initialization already in progress, skipping duplicate call');
	}
	
	FlowState.set('fieldsWereFilled', areFilled);
}

/**
 * Debounced check for Flow reload when critical fields change
 */
function debouncedCheckFlowReload(fieldName, newValue) {
	// Clear existing timeout
	if (FlowState.get('reloadFlowTimeout')) {
		clearTimeout(FlowState.get('reloadFlowTimeout'));
	}
	
	// Set new timeout
	FlowState.set('reloadFlowTimeout', setTimeout(() => {
		// Only reload if Flow is initialized and field is actually filled
		if (!FlowState.get('initialized') || !ckoFlow.flowComponent) {
			return;
		}
		
		// Check if all required fields are still filled
		if (!requiredFieldsFilledAndValid()) {
			// Fields became invalid - destroy Flow
			destroyFlowComponent();
			showFlowWaitingMessage();
			FlowState.set('initialized', false);
			return;
		}
		
		// Critical field changed - reload Flow
		ckoLogger.debug(`🔄 Critical field "${fieldName}" changed - reloading Flow`, {
			newValue: newValue,
			timestamp: new Date().toLocaleTimeString()
		});
		ckoLogger.debug(`Critical field ${fieldName} changed - reloading Flow`);
		reloadFlowComponent();
	}, 1000)); // 1 second debounce for reload check - closes both setTimeout and FlowState.set
}

/**
 * SIMPLIFIED: Single function to initialize Flow when needed
 * Only initializes if:
 * 1. Flow payment method is selected
 * 2. Flow is not already initialized
 * 3. Container exists
 * 4. All required fields are filled (NEW)
 */
function initializeFlowIfNeeded() {
	// CRITICAL FIX: Clear old order data from sessionStorage when starting a new checkout
	// This prevents reusing old order IDs from previous checkouts
	// BUT: Only clear if we're NOT on a checkout page with an active order (to prevent clearing during payment processing)
	const currentOrderId = sessionStorage.getItem('cko_flow_order_id');
	const isCheckoutPage = window.location.pathname.includes('/checkout/') || window.location.pathname.includes('/order-pay/');
	const hasPaymentIdInUrl = new URLSearchParams(window.location.search).has('cko-payment-id');
	const hasOrderIdInUrl = new URLSearchParams(window.location.search).has('order_id');
	
	// Only clear if:
	// 1. We have an old order ID in sessionStorage
	// 2. We're NOT on a checkout/order-pay page with payment processing in progress
	// 3. We're NOT on a 3DS return (has payment ID or order_id in URL)
	if (currentOrderId && !(isCheckoutPage && (hasPaymentIdInUrl || hasOrderIdInUrl))) {
		ckoLogger.debug('[SESSION CLEANUP] Clearing old order data from sessionStorage - Order ID: ' + currentOrderId);
		sessionStorage.removeItem('cko_flow_order_id');
		sessionStorage.removeItem('cko_flow_order_key');
		ckoLogger.debug('[SESSION CLEANUP] ✅ Old order data cleared - new checkout will create fresh order');
	} else if (currentOrderId) {
		ckoLogger.debug('[SESSION CLEANUP] ⏭️ Skipping cleanup - Payment processing in progress (Order ID: ' + currentOrderId + ')');
	}
	
	// Track initialization attempts
	if (!window.ckoInitAttemptCount) {
		window.ckoInitAttemptCount = 0;
	}
	window.ckoInitAttemptCount++;
	const attemptNumber = window.ckoInitAttemptCount;
	
	// REFACTORED: Use initialization helper for guard checks
	let guardCheck;
	if (typeof window.FlowInitialization !== 'undefined' && window.FlowInitialization.canInitialize) {
		guardCheck = window.FlowInitialization.canInitialize();
		if (!guardCheck.canInitialize) {
			ckoLogger.debug(`⏭️ ATTEMPT #${attemptNumber} BLOCKED - Reason: ${guardCheck.reason}`, {
				timestamp: new Date().toLocaleTimeString()
			});
			if (guardCheck.reason === 'ALREADY_INITIALIZING') {
				ckoLogger.debug('Flow initialization already in progress, skipping duplicate call');
			} else if (guardCheck.reason === '3DS_RETURN' || guardCheck.reason === '3DS_RETURN_URL') {
				ckoLogger.threeDS('⚠️ initializeFlowIfNeeded: Blocked by 3DS return');
				ckoLogger.threeDS('Skipping Flow initialization - 3DS return in progress');
			} else if (guardCheck.reason === 'PAYMENT_NOT_SELECTED') {
				ckoLogger.debug('Flow payment method not selected, skipping initialization');
			} else if (guardCheck.reason === 'CONTAINER_NOT_FOUND') {
				ckoLogger.debug('Flow container not found, skipping initialization');
			} else if (guardCheck.reason === 'ALREADY_INITIALIZED') {
				// Already initialized - just ensure UI is correct
				const elements = window.FlowInitialization.getFlowElements();
				if (elements.flowContainer) {
					elements.flowContainer.style.display = "block";
					document.body.classList.add("flow-method-selected");
					hideFlowWaitingMessage();
				}
				ckoLogger.debug(`✅ ATTEMPT #${attemptNumber} - Already initialized and mounted, skipping`);
				ckoLogger.debug('Flow already initialized and mounted, skipping');
			}
			return;
		}
	} else {
		// Fallback to original checks if helper not available
		if (FlowState.get('initializing')) {
			ckoLogger.debug('Flow initialization already in progress, skipping duplicate call');
			return;
		}
		if (FlowState.get('is3DSReturn')) {
			ckoLogger.threeDS('Skipping Flow initialization - 3DS return in progress');
			return;
		}
		const flowPayment = document.getElementById("payment_method_wc_checkout_com_flow");
		if (!flowPayment || !flowPayment.checked) {
			ckoLogger.debug('Flow payment method not selected, skipping initialization');
			return;
		}
		const flowContainer = document.getElementById("flow-container");
		if (!flowContainer) {
			ckoLogger.debug('Flow container not found, skipping initialization');
			return;
		}
	}
	
	// Get Flow elements
	const elements = typeof window.FlowInitialization !== 'undefined' && window.FlowInitialization.getFlowElements ?
		window.FlowInitialization.getFlowElements() :
		{
			flowPayment: document.getElementById("payment_method_wc_checkout_com_flow"),
			flowContainer: document.getElementById("flow-container"),
			flowComponentRoot: document.querySelector('[data-testid="checkout-web-component-root"]')
		};
	
	ckoLogger.debug('initializeFlowIfNeeded() state check:', {
		flowPaymentExists: !!elements.flowPayment,
		flowPaymentChecked: elements.flowPayment?.checked || false,
		flowContainerExists: !!elements.flowContainer,
		flowComponentRootExists: !!elements.flowComponentRoot,
		flowInitialized: FlowState.get('initialized'),
		flowInitializing: FlowState.get('initializing'),
		flowComponentExists: !!ckoFlow.flowComponent
	});
	
	// Check if Flow can be initialized (validation check)
	const canInit = canInitializeFlow();
	ckoLogger.debug('canInitializeFlow() check result:', {
		canInit: canInit,
		flowPaymentSelected: !!elements.flowPayment && elements.flowPayment.checked,
		containerExists: !!elements.flowContainer,
		cartTotal: cko_flow_vars?.cart_total,
		isLoggedIn: isUserLoggedIn(),
		hasBillingAddress: hasBillingAddress(),
		requiredFieldsFilled: requiredFieldsFilled(),
		requiredFieldsValid: requiredFieldsFilledAndValid()
	});
	
	if (!canInit) {
		ckoLogger.debug('❌ BLOCKED - Cannot initialize Flow - validation failed', {
			requiredFieldsFilled: requiredFieldsFilled(),
			requiredFieldsValid: requiredFieldsFilledAndValid(),
			isLoggedIn: isUserLoggedIn(),
			hasBillingAddress: hasBillingAddress()
		});
		ckoLogger.debug('Cannot initialize Flow - validation failed');
		document.body.classList.add("flow-method-selected");
		showFlowWaitingMessage();
		setupFieldWatchersForInitialization();
		return;
	}
	
	// Hide waiting message if it was shown
	hideFlowWaitingMessage();
	
	// Initialize Flow
	ckoLogger.debug('✅ PROCEEDING - Initializing Flow - payment selected, container exists, validation passed');
	ckoLogger.debug('Initializing Flow - payment selected, container exists, validation passed');
	document.body.classList.add("flow-method-selected");
	if (elements.flowContainer) {
		elements.flowContainer.style.display = "block";
	}
	
	// Mark fields as filled
	FlowState.set('fieldsWereFilled', true);
	
	// Only initialize if not already initialized
	if (!FlowState.get('initialized') || !ckoFlow.flowComponent) {
		ckoLogger.debug('🚀 STARTING - Calling ckoFlow.init()...', {
			alreadyInitialized: FlowState.get('initialized'),
			componentExists: !!ckoFlow.flowComponent,
			timestamp: new Date().toLocaleTimeString()
		});
		FlowState.set('initializing', true);
		
		try {
			ckoLogger.debug('Calling ckoFlow.init()...');
			ckoFlow.init();
		} catch (error) {
			console.error('[FLOW INIT] ❌ ERROR - Error during Flow initialization:', error);
			ckoLogger.debug('Error during Flow initialization:', error);
			FlowState.set('initialized', false);
			FlowState.set('initializing', false);
			throw error;
		}
	} else {
		ckoLogger.debug('⏭️ SKIPPED - Already initialized, skipping ckoFlow.init()');
		ckoLogger.debug('Skipping ckoFlow.init() - already initialized');
	}
}

/**
 * Setup field watchers for initialization
 * Watches required fields and initializes Flow when they become filled
 */
function setupFieldWatchersForInitialization() {
	// Only setup if Flow is not initialized
	if (ckoFlowInitialized) {
		return;
	}
	
	// Get all required field selectors
	const requiredLabels = document.querySelectorAll(".woocommerce-checkout label .required");
	const fieldSelectors = [];
	
	requiredLabels.forEach((label) => {
		const fieldId = label.closest("label").getAttribute("for");
		if (fieldId && !fieldId.startsWith("shipping")) {
			fieldSelectors.push('#' + fieldId);
		}
	});
	
	// FALLBACK: If no fields found via .required selector, use common required fields
	// This matches the fallback logic in requiredFieldsFilled()
	if (fieldSelectors.length === 0) {
		if (window.flowDebugLogging) {
			ckoLogger.debug('setupFieldWatchersForInitialization: No fields found via .required, using fallback fields');
		}
		
		const commonRequiredFields = [
			'#billing_email',
			'#billing_first_name',
			'#billing_last_name',
			'#billing_address_1',
			'#billing_city',
			'#billing_country'
		];
		
		// Check which fields exist and are required
		commonRequiredFields.forEach(selector => {
			const field = document.querySelector(selector);
			if (field) {
				const isRequired = field.hasAttribute('required') || 
				                  field.hasAttribute('aria-required') ||
				                  field.closest('label')?.querySelector('.required') !== null ||
				                  field.closest('.form-row')?.classList.contains('validate-required');
				
				const isVisible = field.offsetParent !== null && 
				                 field.style.display !== 'none' &&
				                 !field.hasAttribute('disabled');
				
				if (isRequired && isVisible) {
					fieldSelectors.push(selector);
				}
			}
		});
		
		if (window.flowDebugLogging) {
			ckoLogger.debug('setupFieldWatchersForInitialization: Fallback fields to watch:', fieldSelectors);
		}
	}
	
	// Add critical fields if not already included
	const criticalFields = ['#billing_email', '#billing_country', '#billing_address_1', '#billing_city', '#billing_postcode'];
	criticalFields.forEach(selector => {
		if (!fieldSelectors.includes(selector)) {
			fieldSelectors.push(selector);
		}
	});
	
	// Setup watchers with debouncing
	const debouncedCheck = debounce(() => {
		ckoLogger.debug('Field watcher triggered - checking if Flow can initialize');
		checkRequiredFieldsStatus();
		
		// Also directly check if we can initialize now
		if (!ckoFlowInitialized && canInitializeFlow()) {
			ckoLogger.debug('Field watcher - all fields valid, initializing Flow');
			initializeFlowIfNeeded();
		}
	}, 300); // Reduced debounce to 300ms for faster response
	
	// Attach watchers to all required fields
	fieldSelectors.forEach(selector => {
		const field = document.querySelector(selector);
		if (field) {
			// Remove existing listener to avoid duplicates
			jQuery(field).off('input change.flow-init');
			// Add new listener
			jQuery(field).on('input change.flow-init', debouncedCheck);
			ckoLogger.debug('Field watcher attached:', selector);
		}
	});
	
	// CRITICAL: Watch the account creation checkbox
	// When this changes, account_username/account_password become required/not required
	const createAccountCheckbox = document.querySelector('#createaccount');
	if (createAccountCheckbox) {
		jQuery(createAccountCheckbox).off('change.flow-init');
		jQuery(createAccountCheckbox).on('change.flow-init', debouncedCheck);
		ckoLogger.debug('Field watcher attached: #createaccount');
	}
	
	ckoLogger.debug('Field watchers setup for initialization', { fieldCount: fieldSelectors.length });
	
	// Also check immediately if fields are already filled
	setTimeout(() => {
		if (!ckoFlowInitialized && canInitializeFlow()) {
			ckoLogger.debug('Immediate check after watcher setup - fields already filled, initializing Flow');
			initializeFlowIfNeeded();
		}
	}, 100);
}

/**
 * Legacy function name for compatibility
 * Now just calls the simplified initializeFlowIfNeeded()
 */
function handleFlowPaymentSelection() {
	initializeFlowIfNeeded();
}


/**
 * REFACTORED: updated_checkout guard extracted to modules/flow-updated-checkout-guard.js
 */
if (window.FlowUpdatedCheckoutGuard && window.FlowUpdatedCheckoutGuard.init) {
	window.FlowUpdatedCheckoutGuard.init();
} else if (typeof window.ckoLogger !== 'undefined') {
	window.ckoLogger.warn('FlowUpdatedCheckoutGuard module not loaded - updated_checkout protection missing');
}

// EVENT-DRIVEN DESIGN: Listen for container-ready events from flow-container.js
// This eliminates timing race conditions - Flow remounts immediately when container is ready
if (window.FlowContainerReadyHandler && window.FlowContainerReadyHandler.init) {
	window.FlowContainerReadyHandler.init();
} else if (typeof window.ckoLogger !== 'undefined') {
	window.ckoLogger.warn('FlowContainerReadyHandler module not loaded - container-ready handler missing');
}

/**
 * REFACTORED: Field change handlers extracted to modules/flow-field-change-handler.js
 */
if (window.FlowFieldChangeHandler && window.FlowFieldChangeHandler.init) {
	window.FlowFieldChangeHandler.init();
} else if (typeof window.ckoLogger !== 'undefined') {
	window.ckoLogger.warn('FlowFieldChangeHandler module not loaded - field change handling missing');
}

/**
 * REFACTORED: Saved card selection handler extracted to modules/flow-saved-card-handler.js
 */
if (window.FlowSavedCardHandler && window.FlowSavedCardHandler.init) {
	window.FlowSavedCardHandler.init();
} else if (typeof window.ckoLogger !== 'undefined') {
	window.ckoLogger.warn('FlowSavedCardHandler module not loaded - saved card handling missing');
}

/**
 * Listen for changes in the payment method selection and handle the Flow payment method.
 *
 * This event listener listens for changes to the payment method selection form (typically a radio button).
 * When a change is detected in the selection of the payment method, it checks if the selected input is 
 * the payment method field and triggers the Flow payment selection handler.
 */
document.addEventListener("change", function (event) {
	if (event.target && event.target.name === "payment_method") {
		ckoLogger.debug('===== PAYMENT METHOD CHANGE EVENT =====');
		ckoLogger.debug('Changed payment method:', {
			targetId: event.target.id,
			targetValue: event.target.value,
			targetChecked: event.target.checked
		});
		
		// Log all payment methods
		const allPaymentMethods = document.querySelectorAll('input[name="payment_method"]');
		const paymentMethodsState = [];
		allPaymentMethods.forEach(pm => {
			paymentMethodsState.push({
				id: pm.id,
				value: pm.value,
				checked: pm.checked
			});
		});
		ckoLogger.debug('All payment methods state:', paymentMethodsState);
		
		// Immediately add flow-method-selected class if Flow is selected
		const flowPayment = document.getElementById("payment_method_wc_checkout_com_flow");
		if (flowPayment && flowPayment.checked) {
			ckoLogger.debug('Checkout.com payment method SELECTED');
			document.body.classList.add("flow-method-selected");
			// Remove flow-ready until component is actually ready
			document.body.classList.remove("flow-ready");
		} else {
			ckoLogger.debug('Checkout.com payment method NOT selected - other method selected');
			// Remove Flow classes for other payment methods
			document.body.classList.remove("flow-method-selected", "flow-ready");
			// Reset Flow interaction flags when switching to other payment methods
			FlowState.set('userInteracted', false);
			window.flowSavedCardSelected = false;
		}
		
		handleFlowPaymentSelection();
	}
});

/**
 * SIMPLIFIED: Single DOMContentLoaded handler
 * Handles initial Flow setup when page loads
 */
document.addEventListener("DOMContentLoaded", function () {
	// Track page loads to detect multiple reloads
	if (!window.ckoPageLoadCount) {
		window.ckoPageLoadCount = 0;
		window.ckoPageLoadTimestamps = [];
	}
	window.ckoPageLoadCount++;
	const loadTime = Date.now();
	window.ckoPageLoadTimestamps.push(loadTime);
	
	// Keep only last 10 timestamps
	if (window.ckoPageLoadTimestamps.length > 10) {
		window.ckoPageLoadTimestamps.shift();
	}
	
	const debugEnabled = typeof ckoLogger !== 'undefined' && ckoLogger.debugEnabled;
	
	// Check for rapid page loads (multiple within 2 seconds)
	const recentLoads = window.ckoPageLoadTimestamps.filter(ts => (loadTime - ts) < 2000);
	if (recentLoads.length > 1 && debugEnabled) {
		ckoLogger.warn(`⚠️ [FLOW RELOAD] MULTIPLE page loads detected: ${recentLoads.length} loads in last 2 seconds (Total: ${window.ckoPageLoadCount})`);
	}
	
	if (debugEnabled) {
		ckoLogger.debug(`Page load #${window.ckoPageLoadCount} at ${new Date().toLocaleTimeString()}`);
	}
	
	// CRITICAL: Check for 3DS return FIRST - before any other checks
	// This must be the very first thing we check
	if (window.ckoFlow3DSReturn) {
		ckoLogger.threeDS('DOMContentLoaded: 3DS return in progress, skipping ALL Flow initialization');
		return; // Exit immediately - don't do anything else
	}
	
	// Also check URL parameters as a fallback (in case early detection didn't run)
	const urlParams = new URLSearchParams(window.location.search);
	const paymentId = urlParams.get("cko-payment-id");
	const sessionId = urlParams.get("cko-session-id");
	const paymentSessionId = urlParams.get("cko-payment-session-id");
	
	if (paymentId || sessionId || paymentSessionId) {
		ckoLogger.threeDS('DOMContentLoaded: 3DS return detected in URL, skipping Flow initialization');
		FlowState.set('is3DSReturn', true);
		return; // Don't initialize Flow during 3DS return
	}
	
	// Check if Flow payment method is selected on page load
	const flowPayment = document.getElementById("payment_method_wc_checkout_com_flow");
	
	// If Flow is selected, check if we can initialize it
	if (flowPayment && flowPayment.checked) {
		ckoLogger.debug('DOMContentLoaded: Flow payment method is selected');
		// Try to initialize - validation will happen inside initializeFlowIfNeeded()
		initializeFlowIfNeeded();
		
		// Also setup field watchers in case fields aren't filled yet
		if (!ckoFlowInitialized) {
			setupFieldWatchersForInitialization();
			
			// Set up periodic check as fallback (every 2 seconds)
			// This ensures Flow initializes even if field watchers don't trigger
			const periodicCheck = setInterval(() => {
				if (ckoFlowInitialized) {
					clearInterval(periodicCheck);
					return;
				}
				
				if (canInitializeFlow()) {
					ckoLogger.debug('Periodic check - Flow can initialize, initializing now');
					initializeFlowIfNeeded();
					clearInterval(periodicCheck);
				}
			}, 2000);
			
			// Clear interval after 30 seconds to avoid infinite checking
			setTimeout(() => {
				clearInterval(periodicCheck);
			}, 30000);
		}
	}
	
	// Handle order-pay page
	const orderPaySlug = cko_flow_vars.orderPaySlug;
	if (window.location.pathname.includes('/' + orderPaySlug + '/')) {
		// Check if Flow payment method is selected before initializing
		const orderPayFlowPayment = document.getElementById("payment_method_wc_checkout_com_flow");
		if (orderPayFlowPayment && orderPayFlowPayment.checked) {
			ckoLogger.debug('DOMContentLoaded: Order-pay page detected, Flow payment method selected, initializing Flow...');
			initializeFlowIfNeeded();
		} else {
			ckoLogger.debug('DOMContentLoaded: Order-pay page detected, but Flow payment method not selected');
		}
	}
});

/**
 * Handle Place Order Button with Flow Checkout when ShowPayButton is False.
 * 
 * This function listens for a click event on the "Place Order" button. If the checkout method 
 * is "Flow" and the user is using the checkout component, it validates the form and proceeds 
 * with the appropriate order placement, either using the Flow component or submitting the form directly.
 * 
 */
document.addEventListener("DOMContentLoaded", function () {
	
	// Listen for saved card clicks to de-emphasize Flow
	jQuery(document).on('click', 'input[name="wc-wc_checkout_com_flow-payment-token"]', function() {
		const selectedId = jQuery(this).attr('id');
		const flowContainer = document.getElementById("flow-container");
		
		if (selectedId && selectedId !== 'wc-wc_checkout_com_flow-payment-token-new') {
			ckoLogger.debug('Saved card selected');
			FlowState.set('userInteracted', false);
			window.flowSavedCardSelected = true;
			
			// Remove the hidden override input if it exists (user switched back to saved card)
			jQuery('#flow-new-payment-override').remove();
	} else if (selectedId === 'wc-wc_checkout_com_flow-payment-token-new') {
		ckoLogger.debug('New payment method selected - activating Flow');
		window.activateFlowPayment();
	}
	});
	
	// Function to activate Flow and deselect saved cards
	// Make it globally accessible so onChange callback can use it
	window.activateFlowPayment = function() {
		ckoLogger.debug('Activating Flow payment method');
		
		const flowContainer = document.getElementById("flow-container");
		if (!flowContainer) return;
		
		// Uncheck all saved card radio buttons (including any that might be hidden)
		jQuery('input[name="wc-wc_checkout_com_flow-payment-token"]').each(function() {
			const radioId = jQuery(this).attr('id');
			// Don't uncheck the "new payment method" radio
			if (radioId !== 'wc-wc_checkout_com_flow-payment-token-new') {
				jQuery(this).prop('checked', false);
				jQuery(this).removeAttr('checked');
			}
		});
		
		// Try to select "Use new payment method" radio button if it exists
		const newPaymentRadio = document.getElementById('wc-wc_checkout_com_flow-payment-token-new');
		if (newPaymentRadio) {
			newPaymentRadio.checked = true;
			jQuery(newPaymentRadio).attr('checked', 'checked');
			jQuery(newPaymentRadio).trigger('change');
		} else {
			// If the "new payment method" radio doesn't exist, add a hidden input
			jQuery('#flow-new-payment-override').remove();
			
			const overrideInput = jQuery('<input>', {
				type: 'hidden',
				id: 'flow-new-payment-override',
				name: 'wc-wc_checkout_com_flow-payment-token',
				value: 'new'
			});
			jQuery('form.checkout, form#order_review').append(overrideInput);
		}
		
		// Flow container is now active for new payment
		
		// Clear saved card flag and mark for new Flow payment
		window.flowSavedCardSelected = false;
		FlowState.set('userInteracted', false); // Reset to allow user to interact with Flow
		document.body.classList.add("flow-ready");
		
		// Ensure Place Order button is visible
		const placeOrderButton = document.getElementById('place_order');
		if (placeOrderButton) {
			placeOrderButton.style.display = 'block';
			placeOrderButton.style.opacity = '1';
			placeOrderButton.style.visibility = 'visible';
		}
	}
	
	// Listen for clicks/focus on Flow component fields to auto-activate Flow
	jQuery(document).on('click focus', '#flow-container input, #flow-container iframe, #flow-container', function(e) {
		const flowContainer = document.getElementById("flow-container");
		
		// Only activate if Flow is currently de-emphasized (saved card selected)
		if (flowContainer && flowContainer.classList.contains('flow-de-emphasized')) {
			ckoLogger.debug('User clicked on Flow field - auto-activating Flow');
			window.activateFlowPayment();
		}
	});
	
	/**
	 * Helper function to persist the save card checkbox value to sessionStorage
	 * This ensures the value survives 3DS redirects
	 */
	function persistSaveCardCheckbox() {
		const checkbox = document.getElementById('wc-wc_checkout_com_flow-new-payment-method');
		
		if (checkbox) {
			// Store in sessionStorage to survive 3DS redirects
			const value = checkbox.checked ? 'yes' : 'no';
			sessionStorage.setItem('cko_flow_save_card', value);
			ckoLogger.debug('SAVE CARD: Persisted checkbox value to sessionStorage:', value);
			ckoLogger.debug('SAVE CARD: Checkbox found:', checkbox);
			ckoLogger.debug('SAVE CARD: Checkbox checked:', checkbox.checked);
			ckoLogger.debug('SAVE CARD: Checkbox visible:', checkbox.offsetParent !== null);
			
			// Also set hidden field as backup
			const hiddenField = document.getElementById('cko-flow-save-card-persist');
			if (hiddenField) {
				hiddenField.value = value;
				ckoLogger.debug('SAVE CARD: Set hidden field value:', value);
			} else {
				ckoLogger.debug('SAVE CARD: WARNING: Hidden field not found!');
			}
			
			// Store in cookie as fallback (survives 3DS redirects)
			// Set cookie with 1 hour expiry
			const cookieName = 'cko_flow_save_card_preference';
			document.cookie = cookieName + '=' + value + '; path=/; max-age=3600; SameSite=Lax';
			ckoLogger.debug('SAVE CARD: Stored in cookie:', value);
			
			// Also try to store in WooCommerce session via AJAX (survives 3DS redirects)
			if (cko_flow_vars && cko_flow_vars.ajax_url && cko_flow_vars.payment_session_nonce) {
				jQuery.ajax({
					url: cko_flow_vars.ajax_url,
					type: 'POST',
					data: {
						action: 'cko_flow_store_save_card_preference',
						nonce: cko_flow_vars.payment_session_nonce,
						save_card_value: value
					},
					success: function(response) {
						if (response.success) {
							ckoLogger.debug('SAVE CARD: Stored in WooCommerce session:', value);
						} else {
							ckoLogger.debug('SAVE CARD: Failed to store in WooCommerce session:', response.data?.message);
						}
					},
					error: function(xhr, status, error) {
						ckoLogger.debug('SAVE CARD: AJAX error storing in WooCommerce session - Status:', status, 'Error:', error);
						// Cookie fallback already set above
					}
				});
			}
		} else {
			ckoLogger.debug('SAVE CARD: WARNING: Checkbox element not found in DOM!');
		}
	}
	
	/**
	 * Restore save card checkbox value from sessionStorage after 3DS redirect
	 * This runs on every page load
	 */
	function restoreSaveCardCheckbox() {
		const savedValue = sessionStorage.getItem('cko_flow_save_card');
		
		if (savedValue) {
			ckoLogger.debug('SAVE CARD: Restoring checkbox value from sessionStorage:', savedValue);
			
			// Set the hidden field value
			const hiddenField = document.getElementById('cko-flow-save-card-persist');
			if (hiddenField) {
				hiddenField.value = savedValue;
				ckoLogger.debug('SAVE CARD: Set hidden field to:', savedValue);
			}
			
			// Also check the checkbox if it exists
			const checkbox = document.getElementById('wc-wc_checkout_com_flow-new-payment-method');
			if (checkbox && savedValue === 'yes') {
				checkbox.checked = true;
				ckoLogger.debug('SAVE CARD: Checked the save card checkbox');
			}
			
			// Clear sessionStorage after restoring (optional - only clear after successful order)
			// sessionStorage.removeItem('cko_flow_save_card');
		}
	}
	
	// Restore checkbox value on page load (for 3DS returns)
	document.addEventListener('DOMContentLoaded', function() {
		// Check if we're on the order received page
		const isOrderReceivedPage = window.location.pathname.includes('/order-received/') || 
		                             window.location.pathname.includes('/checkout/thank-you/');
		
		if (isOrderReceivedPage) {
			// Clear the saved checkbox value after successful order
			ckoLogger.debug('SAVE CARD: Order completed - clearing sessionStorage');
			sessionStorage.removeItem('cko_flow_save_card');
		} else {
			// Restore checkbox value (for 3DS return to checkout page)
			restoreSaveCardCheckbox();
		}
	});
	
	// Listen for checkbox changes to update payment session URLs dynamically
	jQuery(document).on('change', '#wc-wc_checkout_com_flow-new-payment-method', function() {
		const checkbox = document.getElementById('wc-wc_checkout_com_flow-new-payment-method');
		if (checkbox) {
			const value = checkbox.checked ? 'yes' : 'no';
			ckoLogger.debug('[SAVE CARD DEBUG] Checkbox changed - updating sessionStorage, hidden field, and WooCommerce session:', {
				checked: checkbox.checked,
				value: value
			});
			
			// Update sessionStorage
			sessionStorage.setItem('cko_flow_save_card', value);
			
			// Update hidden field
			const hiddenField = document.getElementById('cko-flow-save-card-persist');
			if (hiddenField) {
				hiddenField.value = value;
				ckoLogger.debug('[SAVE CARD DEBUG] Hidden field updated:', value);
			}
			
			// Store in cookie as fallback (survives 3DS redirects)
			// Set cookie with 1 hour expiry
			const cookieName = 'cko_flow_save_card_preference';
			document.cookie = cookieName + '=' + value + '; path=/; max-age=3600; SameSite=Lax';
			ckoLogger.debug('[SAVE CARD DEBUG] Stored in cookie:', value);
			
			// Also try to store in WooCommerce session via AJAX (survives 3DS redirects)
			if (cko_flow_vars && cko_flow_vars.ajax_url && cko_flow_vars.payment_session_nonce) {
				jQuery.ajax({
					url: cko_flow_vars.ajax_url,
					type: 'POST',
					data: {
						action: 'cko_flow_store_save_card_preference',
						nonce: cko_flow_vars.payment_session_nonce,
						save_card_value: value
					},
					success: function(response) {
						if (response.success) {
							ckoLogger.debug('[SAVE CARD DEBUG] Stored in WooCommerce session:', value);
						} else {
							ckoLogger.debug('[SAVE CARD DEBUG] Failed to store in WooCommerce session:', response.data?.message);
						}
					},
					error: function(xhr, status, error) {
						ckoLogger.debug('[SAVE CARD DEBUG] AJAX error storing in WooCommerce session - Status:', status, 'Error:', error, 'Response:', xhr.responseText);
						// Cookie fallback already set above
					}
				});
			}
			
			// Note: Payment session URLs cannot be updated after creation,
			// but backend will read from WooCommerce session as fallback
			ckoLogger.debug('[SAVE CARD DEBUG] Checkbox value updated - will be read from WooCommerce session after 3DS redirect');
		}
	});
	
	// Also try to restore immediately in case DOM is already loaded
	if (document.readyState === 'loading') {
		// DOM still loading, listener above will handle it
	} else {
		// DOM already loaded, check now
		const isOrderReceivedPage = window.location.pathname.includes('/order-received/') || 
		                             window.location.pathname.includes('/checkout/thank-you/');
		if (isOrderReceivedPage) {
			ckoLogger.debug('SAVE CARD: Order completed - clearing sessionStorage');
			sessionStorage.removeItem('cko_flow_save_card');
		} else {
			restoreSaveCardCheckbox();
		}
	}
	
	/**
	 * Create order before payment processing via WooCommerce checkout AJAX.
	 * This runs full checkout processing (required for subscriptions) but skips charging.
	 * 
	 * @returns {Promise<number|null>} Order ID if successful, null if failed
	 */
	function parseJsonLenient(responseText) {
		if (typeof responseText !== 'string') {
			return responseText;
		}
		const trimmed = responseText.trim();
		if (!trimmed) {
			return null;
		}
		// If response contains leading junk (e.g., "200"), extract the JSON payload.
		const firstBraceIndex = trimmed.indexOf('{');
		const firstBracketIndex = trimmed.indexOf('[');
		let startIndex = -1;
		if (firstBraceIndex !== -1 && firstBracketIndex !== -1) {
			startIndex = Math.min(firstBraceIndex, firstBracketIndex);
		} else if (firstBraceIndex !== -1) {
			startIndex = firstBraceIndex;
		} else if (firstBracketIndex !== -1) {
			startIndex = firstBracketIndex;
		}
		if (startIndex === -1) {
			return null;
		}
		const jsonText = trimmed.substring(startIndex);
		try {
			return JSON.parse(jsonText);
		} catch (error) {
			ckoLogger.error('[CREATE ORDER] JSON parse failed:', error);
			return null;
		}
	}

	async function createOrderBeforePayment() {
		// CRITICAL: Prevent multiple simultaneous order creation calls (race condition protection)
		if (FlowState.get('orderCreationInProgress')) {
			ckoLogger.warn('[CREATE ORDER] ⚠️ Order creation already in progress - preventing duplicate call');
			// Wait a bit and check if order was created
			await new Promise(resolve => setTimeout(resolve, 500));
			const orderIdField = jQuery('input[name="order_id"]');
			if (orderIdField.length && orderIdField.val()) {
				const existingOrderId = orderIdField.val();
				ckoLogger.debug('[CREATE ORDER] Order was created by previous call - Order ID: ' + existingOrderId);
				return parseInt(existingOrderId);
			}
			// If still in progress after wait, return null to prevent duplicate
			if (FlowState.get('orderCreationInProgress')) {
				ckoLogger.error('[CREATE ORDER] ❌ Order creation still in progress after wait - aborting duplicate call');
				return null;
			}
		}
		
		// Set lock flag to prevent multiple simultaneous calls
		FlowState.set('orderCreationInProgress', true);
		
		// Disable place order button to prevent multiple clicks
		const placeOrderButton = jQuery('#place_order');
		const originalButtonText = placeOrderButton.length ? placeOrderButton.text() : '';
		if (placeOrderButton.length) {
			placeOrderButton.prop('disabled', true);
			placeOrderButton.addClass('processing');
			if (placeOrderButton.text().trim() !== '') {
				placeOrderButton.data('original-text', placeOrderButton.text());
				placeOrderButton.text('Processing...');
			}
			ckoLogger.debug('[CREATE ORDER] Place Order button disabled to prevent multiple clicks');
		}
		
		try {
			// Check if order already exists (for order-pay page) - check BEFORE form lookup
			const orderIdField = jQuery('input[name="order_id"]');
			if (orderIdField.length && orderIdField.val()) {
				const existingOrderId = orderIdField.val();
				ckoLogger.debug('[CREATE ORDER] Order already exists (order-pay page) - Order ID: ' + existingOrderId);
				// Clear lock flag and re-enable button before returning
				FlowState.set('orderCreationInProgress', false);
				if (placeOrderButton.length) {
					placeOrderButton.prop('disabled', false);
					placeOrderButton.removeClass('processing');
					const originalText = placeOrderButton.data('original-text');
					if (originalText) {
						placeOrderButton.text(originalText);
					}
				}
				return parseInt(existingOrderId);
			}
		
			// Try to find form - checkout form or order-pay form
			let form = jQuery("form.checkout");
			if (!form.length) {
				// Try order-pay form (form#order_review)
				form = jQuery("form#order_review");
				if (form.length) {
					ckoLogger.debug('[CREATE ORDER] Found order-pay form (form#order_review)');
				}
			}
			
			if (!form.length) {
				ckoLogger.debug('[CREATE ORDER] No checkout form or order-pay form found - skipping order creation');
				// For order-pay pages, order already exists, so return order ID from URL
				if (window.location.pathname.includes('/order-pay/')) {
					const pathMatch = window.location.pathname.match(/\/order-pay\/(\d+)\//);
					if (pathMatch && pathMatch[1]) {
						const orderId = parseInt(pathMatch[1]);
						ckoLogger.debug('[CREATE ORDER] Order-pay page detected - using order ID from URL: ' + orderId);
						// Clear lock flag and re-enable button before returning
						FlowState.set('orderCreationInProgress', false);
						if (placeOrderButton.length) {
							placeOrderButton.prop('disabled', false);
							placeOrderButton.removeClass('processing');
							const originalText = placeOrderButton.data('original-text');
							if (originalText) {
								placeOrderButton.text(originalText);
							}
						}
						return orderId;
					}
				}
				// Clear lock flag and re-enable button before returning
				FlowState.set('orderCreationInProgress', false);
				if (placeOrderButton.length) {
					placeOrderButton.prop('disabled', false);
					placeOrderButton.removeClass('processing');
					const originalText = placeOrderButton.data('original-text');
					if (originalText) {
						placeOrderButton.text(originalText);
					}
				}
				return null;
			}
			
			ckoLogger.debug('[CREATE ORDER] Creating order before payment processing...');
			
			// Get form data
			const formData = form.serialize();
			const formDataObj = Object.fromEntries(new URLSearchParams(formData));
			
			// Get nonce from form or page
			let nonceValue = '';
			const nonceField = jQuery('input[name="woocommerce-process-checkout-nonce"]');
			if (nonceField.length > 0) {
				nonceValue = nonceField.val();
			}
			
			// Fallback: Try to get nonce from form data
			if (!nonceValue && formDataObj['woocommerce-process-checkout-nonce']) {
				nonceValue = formDataObj['woocommerce-process-checkout-nonce'];
			}
			
			// Fallback: Try to get from meta tag or other sources
			if (!nonceValue) {
				const metaNonce = jQuery('meta[name="woocommerce-process-checkout-nonce"]');
				if (metaNonce.length > 0) {
					nonceValue = metaNonce.attr('content');
				}
			}
			
			// For order-pay pages, order already exists, so we don't need to create it
			// Just return the order ID from URL if nonce is missing
			if (!nonceValue) {
				if (window.location.pathname.includes('/order-pay/')) {
					const pathMatch = window.location.pathname.match(/\/order-pay\/(\d+)\//);
					if (pathMatch && pathMatch[1]) {
						const orderId = parseInt(pathMatch[1]);
						ckoLogger.debug('[CREATE ORDER] Order-pay page - nonce missing but order exists, using order ID from URL: ' + orderId);
						// Clear lock flag and re-enable button before returning
						FlowState.set('orderCreationInProgress', false);
						if (placeOrderButton.length) {
							placeOrderButton.prop('disabled', false);
							placeOrderButton.removeClass('processing');
							const originalText = placeOrderButton.data('original-text');
							if (originalText) {
								placeOrderButton.text(originalText);
							}
						}
						return orderId;
					}
				}
				ckoLogger.error('[CREATE ORDER] ERROR: Nonce not found in form or page');
				ckoLogger.error('[CREATE ORDER] Form data keys:', Object.keys(formDataObj));
				// Clear lock flag and re-enable button before returning
				FlowState.set('orderCreationInProgress', false);
				if (placeOrderButton.length) {
					placeOrderButton.prop('disabled', false);
					placeOrderButton.removeClass('processing');
					const originalText = placeOrderButton.data('original-text');
					if (originalText) {
						placeOrderButton.text(originalText);
					}
				}
				return null;
			}
			
			ckoLogger.debug('[CREATE ORDER] Nonce found:', nonceValue.substring(0, 10) + '...');
			
			// Ensure nonce is included in form data
			formDataObj['woocommerce-process-checkout-nonce'] = nonceValue;
			
			// Get payment session ID if available
			const paymentSessionIdField = jQuery('input[name="cko-flow-payment-session-id"]');
			const paymentSessionId = paymentSessionIdField.length > 0 ? paymentSessionIdField.val() : '';
			
			// Get save card preference
			const saveCardField = jQuery('input[name="cko-flow-save-card-persist"]');
			const saveCardValue = saveCardField.length > 0 ? saveCardField.val() : '';
			
			// Build data object ensuring nonce is included
			const ajaxData = {
				cko_flow_precreate_order: '1',
				'cko-flow-payment-session-id': paymentSessionId,
				'cko-flow-save-card-persist': saveCardValue
			};
			
			// Add all form data (including nonce)
			Object.assign(ajaxData, formDataObj);
			
			// Ensure nonce is explicitly included
			if (nonceValue && !ajaxData['woocommerce-process-checkout-nonce']) {
				ajaxData['woocommerce-process-checkout-nonce'] = nonceValue;
				ckoLogger.debug('[CREATE ORDER] Explicitly added nonce to AJAX data');
			}
			
			ckoLogger.debug('[CREATE ORDER] AJAX data keys:', Object.keys(ajaxData));
			ckoLogger.debug('[CREATE ORDER] Nonce in data:', !!ajaxData['woocommerce-process-checkout-nonce']);
			
			// Use WooCommerce checkout AJAX endpoint (full checkout processing).
			let wcAjaxUrl = '';
			if (typeof wc_checkout_params !== 'undefined' && wc_checkout_params.wc_ajax_url) {
				wcAjaxUrl = wc_checkout_params.wc_ajax_url.toString().replace('%%endpoint%%', 'checkout');
			} else if (typeof wc_add_to_cart_params !== 'undefined' && wc_add_to_cart_params.wc_ajax_url) {
				wcAjaxUrl = wc_add_to_cart_params.wc_ajax_url.toString().replace('%%endpoint%%', 'checkout');
			} else if (typeof wc_cart_fragments_params !== 'undefined' && wc_cart_fragments_params.wc_ajax_url) {
				wcAjaxUrl = wc_cart_fragments_params.wc_ajax_url.toString().replace('%%endpoint%%', 'checkout');
			} else {
				wcAjaxUrl = window.location.origin + '/?wc-ajax=checkout';
			}

			const responseText = await jQuery.ajax({
				url: wcAjaxUrl,
				type: "POST",
				data: ajaxData,
				dataType: "text"
			}).fail(function(xhr, status, error) {
				ckoLogger.error('[CREATE ORDER] ❌ AJAX Request Failed');
				ckoLogger.error('[CREATE ORDER] Status:', status);
				ckoLogger.error('[CREATE ORDER] Error:', error);
				ckoLogger.error('[CREATE ORDER] Response Text:', xhr.responseText);
				ckoLogger.error('[CREATE ORDER] Status Code:', xhr.status);
				ckoLogger.error('[CREATE ORDER] Request Data:', ajaxData);
			});
			
			const response = parseJsonLenient(responseText);
			if (!response) {
				ckoLogger.error('[CREATE ORDER] ❌ Failed to parse AJAX response as JSON');
				ckoLogger.error('[CREATE ORDER] Response Text:', responseText);
				return null;
			}
			
			ckoLogger.debug('[CREATE ORDER] ========== PROCESSING AJAX RESPONSE ==========');
			ckoLogger.debug('[CREATE ORDER] Response received:', response);
			ckoLogger.debug('[CREATE ORDER] Response.success:', response?.success);
			ckoLogger.debug('[CREATE ORDER] Response.data:', response?.data);
			ckoLogger.debug('[CREATE ORDER] Response.data.order_id:', response?.data?.order_id);

			// Normalize WooCommerce public checkout AJAX response when enabled (result/redirect format).
			let normalizedResponse = response;
			if (response && typeof response === 'object' && response.result) {
				if (response.result === 'success') {
					const redirectUrl = response.redirect || response?.data?.redirect || '';
					let orderIdFromRedirect = null;
					let orderKeyFromRedirect = '';

					if (redirectUrl) {
						try {
							const parsedUrl = new URL(redirectUrl, window.location.origin);
							const orderReceivedMatch = parsedUrl.pathname.match(/order-received\/(\d+)/);
							const orderPayMatch = parsedUrl.pathname.match(/order-pay\/(\d+)/);
							if (orderReceivedMatch && orderReceivedMatch[1]) {
								orderIdFromRedirect = parseInt(orderReceivedMatch[1], 10);
							} else if (orderPayMatch && orderPayMatch[1]) {
								orderIdFromRedirect = parseInt(orderPayMatch[1], 10);
							}
							orderKeyFromRedirect = parsedUrl.searchParams.get('key') || '';
						} catch (error) {
							ckoLogger.error('[CREATE ORDER] Failed to parse redirect URL:', error);
						}
					}

					if (!orderIdFromRedirect && response.order_id) {
						orderIdFromRedirect = parseInt(response.order_id, 10);
					}

					if (orderIdFromRedirect) {
						normalizedResponse = {
							success: true,
							data: {
								order_id: orderIdFromRedirect,
								order_key: orderKeyFromRedirect || '',
								redirect: redirectUrl || ''
							},
							_wc_public_checkout: true
						};
					} else {
						normalizedResponse = {
							success: false,
							data: {
								message: response.messages || 'Checkout completed but order ID was not found.'
							},
							_wc_public_checkout: true
						};
					}
				} else if (response.result === 'failure') {
					normalizedResponse = {
						success: false,
						data: {
							message: response.messages || 'Checkout validation failed.'
						},
						_wc_public_checkout: true
					};
				}
			}
			
			if (normalizedResponse && normalizedResponse.success && normalizedResponse.data && normalizedResponse.data.order_id) {
				if (normalizedResponse._wc_public_checkout && normalizedResponse.data.redirect) {
					ckoLogger.debug('[CREATE ORDER] Public checkout redirect detected - skipping redirect (precreate mode)');
				}
				const orderId = normalizedResponse.data.order_id;
				const orderKey = normalizedResponse.data.order_key || '';
				ckoLogger.debug('[CREATE ORDER] ========== ORDER CREATED SUCCESSFULLY ==========');
				ckoLogger.debug('[CREATE ORDER] ✅✅✅ Order created successfully - Order ID: ' + orderId + ' ✅✅✅');
				ckoLogger.debug('[CREATE ORDER] Order ID type:', typeof orderId);
				ckoLogger.debug('[CREATE ORDER] Order ID value:', orderId);
				ckoLogger.debug('[CREATE ORDER] ========== ORDER KEY DEBUG ==========');
				ckoLogger.debug('[CREATE ORDER] Response.data.order_key:', normalizedResponse.data.order_key);
				ckoLogger.debug('[CREATE ORDER] Order key (extracted):', orderKey);
				ckoLogger.debug('[CREATE ORDER] Order key type:', typeof orderKey);
				ckoLogger.debug('[CREATE ORDER] Order key length:', orderKey ? orderKey.length : 0);
				ckoLogger.debug('[CREATE ORDER] Order key empty?:', !orderKey);
				
				// Store order ID in form for process_payment()
				if (!orderIdField.length) {
					form.append('<input type="hidden" name="order_id" value="' + orderId + '">');
				} else {
					orderIdField.val(orderId);
				}
				
				// Store in session for fallback
				sessionStorage.setItem('cko_flow_order_id', orderId);
				ckoLogger.debug('[CREATE ORDER] Stored order ID in sessionStorage:', orderId);
				if (orderKey) {
					sessionStorage.setItem('cko_flow_order_key', orderKey);
					ckoLogger.debug('[CREATE ORDER] ✅ Stored order key in sessionStorage:', orderKey);
					// Verify it was stored
					const storedKey = sessionStorage.getItem('cko_flow_order_key');
					ckoLogger.debug('[CREATE ORDER] Verification - Retrieved order key from sessionStorage:', storedKey);
					ckoLogger.debug('[CREATE ORDER] Verification - Keys match?:', storedKey === orderKey);
				} else {
					ckoLogger.error('[CREATE ORDER] ❌ Order key is empty - NOT storing in sessionStorage');
					ckoLogger.error('[CREATE ORDER] Full response.data:', JSON.stringify(normalizedResponse.data, null, 2));
				}
				
				// Clear lock flag on success
				FlowState.set('orderCreationInProgress', false);
				
				// Re-enable place order button
				if (placeOrderButton.length) {
					placeOrderButton.prop('disabled', false);
					placeOrderButton.removeClass('processing');
					const originalText = placeOrderButton.data('original-text');
					if (originalText) {
						placeOrderButton.text(originalText);
					}
				}
				
				return parseInt(orderId);
			} else {
				// Check if this is a validation error
				ckoLogger.error('[CREATE ORDER] ========== ORDER CREATION FAILED ==========');
				ckoLogger.error('[CREATE ORDER] Response received:', normalizedResponse);
				ckoLogger.error('[CREATE ORDER] Response type:', typeof normalizedResponse);
				ckoLogger.error('[CREATE ORDER] Response.success:', normalizedResponse?.success);
				ckoLogger.error('[CREATE ORDER] Response.data:', normalizedResponse?.data);
				
				if (normalizedResponse && normalizedResponse.data && normalizedResponse.data.message) {
					ckoLogger.error('[CREATE ORDER] ❌❌❌ VALIDATION FAILED - ORDER NOT CREATED ❌❌❌');
					ckoLogger.error('[CREATE ORDER] Error message:', normalizedResponse.data.message);
					ckoLogger.error('[CREATE ORDER] This is a validation error - order was NOT created');
					showError(normalizedResponse.data.message);
				} else {
					ckoLogger.error('[CREATE ORDER] ❌❌❌ FAILED TO CREATE ORDER ❌❌❌');
					ckoLogger.error('[CREATE ORDER] Full response:', JSON.stringify(normalizedResponse, null, 2));
					ckoLogger.error('[CREATE ORDER] Order creation failed for unknown reason');
					showError('Failed to create order. Please check your form and try again.');
				}
				
				// Clear lock flag on failure
				FlowState.set('orderCreationInProgress', false);
				
				// Re-enable place order button on failure
				if (placeOrderButton.length) {
					placeOrderButton.prop('disabled', false);
					placeOrderButton.removeClass('processing');
					const originalText = placeOrderButton.data('original-text');
					if (originalText) {
						placeOrderButton.text(originalText);
					}
				}
				
				return null;
			}
		} catch (error) {
			ckoLogger.error('[CREATE ORDER] ❌ AJAX Error:', error);
			
			// Clear lock flag on error
			ckoOrderCreationInProgress = false;
			
			// Re-enable place order button on error
			if (placeOrderButton.length) {
				placeOrderButton.prop('disabled', false);
				placeOrderButton.removeClass('processing');
				const originalText = placeOrderButton.data('original-text');
				if (originalText) {
					placeOrderButton.text(originalText);
				}
			}
			
			return null;
		} finally {
			// Ensure lock flag is cleared even if something unexpected happens
			// This is a safety net - the flag should already be cleared in success/error handlers
			if (FlowState.get('orderCreationInProgress')) {
				ckoLogger.warn('[CREATE ORDER] ⚠️ Lock flag still set in finally block - clearing it');
				FlowState.set('orderCreationInProgress', false);
				
				// Re-enable button as safety measure
				if (placeOrderButton.length) {
					placeOrderButton.prop('disabled', false);
					placeOrderButton.removeClass('processing');
					const originalText = placeOrderButton.data('original-text');
					if (originalText) {
						placeOrderButton.text(originalText);
					}
				}
			}
		}
	}
	
	document.addEventListener("click", function (event) {
		const flowPayment = document.getElementById(
			"payment_method_wc_checkout_com_flow"
		);

		// If the Place Order button is clicked, proceed.
		if (event.target && event.target.id === "place_order") {
			// CRITICAL: Prevent multiple clicks if order creation is already in progress
			if (FlowState.get('orderCreationInProgress')) {
				ckoLogger.warn('[PLACE ORDER] ⚠️ Order creation already in progress - ignoring duplicate click');
				event.preventDefault();
				return;
			}
			
			// Track that Place Order was clicked (for order creation on payment decline)
			window.ckoPlaceOrderClicked = true;
			ckoLogger.debug("Place Order button clicked - tracking for order creation on payment decline");

			// If the Flow payment method is selected, proceed with validation and order placement.
			if (flowPayment && flowPayment.checked) {
			event.preventDefault();
			
			// Check if saved card is selected BEFORE creating order
			// For saved card payments, WooCommerce will create order on form submission
			// So we should NOT create order via AJAX to prevent duplicates
			// Check if saved card is selected (only by property - no defaults anymore)
			let selectedSavedCard = jQuery('input[name="wc-wc_checkout_com_flow-payment-token"]:checked:not(#wc-wc_checkout_com_flow-payment-token-new)');
			let savedCardSelected = selectedSavedCard.length > 0;
			let savedCardEnabled = document.querySelector('[data-testid="checkout-web-component-root"]')?.classList.contains('saved-card-is-enabled') || false;
				
				if (savedCardSelected || savedCardEnabled) {
					ckoLogger.debug('[CREATE ORDER] Saved card selected - bypassing Flow component (direct API call via WooCommerce)');
					// For saved cards, let WooCommerce handle order creation and payment via direct API call
					// Flow component is NOT used for saved cards - payment is processed server-side via API
					const form = jQuery("form.checkout");
					
					// Handle payment for order-pay page.
					if (form.length === 0) {
						const orderPaySlug = cko_flow_vars.orderPaySlug;
						const orderPayForm = jQuery('form#order_review');
						
						if (window.location.pathname.includes('/' + orderPaySlug + '/')) {
							// CRITICAL: Ensure payment session ID is in form before submission
							if (window.ckoAddPaymentSessionIdField) {
								window.ckoAddPaymentSessionIdField();
							}
							// Submit directly - no Flow component needed for saved cards
							ckoLogger.debug('[SAVED CARD] Submitting order-pay form directly (bypassing Flow component)');
							orderPayForm.submit();
						}
					} else {
						// For saved cards, submit form directly to WooCommerce
						// WooCommerce will call process_payment() which makes direct API call
						// No Flow component validation needed
						ckoLogger.debug('[SAVED CARD] Submitting checkout form directly (bypassing Flow component)');
						
						// CRITICAL: Ensure payment session ID is in form before submission
						if (window.ckoAddPaymentSessionIdField) {
							window.ckoAddPaymentSessionIdField();
						}
						// Submit directly - no validateCheckout needed for saved cards
						form.submit();
					}
					return; // Exit early - don't create order via AJAX, don't use Flow component
				}
				
				// CRITICAL: Validate checkout form FIRST before creating order
				// This ensures orders are only created when form is valid
				const form = jQuery("form.checkout");

				// Handle payment for order-pay page (order already exists, skip validation)
				if ( form.length === 0 ) {
					const orderPaySlug = cko_flow_vars.orderPaySlug;
					const orderPayForm = jQuery('form#order_review');

					if (window.location.pathname.includes('/' + orderPaySlug + '/')) {
						// Order-pay page: Order already exists, proceed with payment processing
						document.getElementById("flow-container").style.display = "block";

						// Place order for FLOW.
						if (ckoFlow.flowComponent) {
							// Check if a saved card is actually selected (only by property - no defaults anymore)
							// Re-check saved card status (may have changed)
							selectedSavedCard = jQuery('input[name="wc-wc_checkout_com_flow-payment-token"]:checked:not(#wc-wc_checkout_com_flow-payment-token-new)');
							savedCardSelected = selectedSavedCard.length > 0;
							savedCardEnabled = document.querySelector('[data-testid="checkout-web-component-root"]')?.classList.contains('saved-card-is-enabled') || false;
							
							// ALWAYS persist save card checkbox on order-pay page (for new card payments)
							if (!savedCardSelected) {
								persistSaveCardCheckbox();
								ckoLogger.debug('Order-pay: Persisted save card checkbox for new card payment');
							}
							
							if( savedCardSelected || savedCardEnabled ) {
								ckoLogger.debug('[SAVED CARD] Saved card selected - submitting order-pay form directly (bypassing Flow component)');
								// CRITICAL: Ensure payment session ID is in form before submission
								if (window.ckoAddPaymentSessionIdField) {
									window.ckoAddPaymentSessionIdField();
								}
								// Submit directly - Flow component NOT used for saved cards
								orderPayForm.submit();
							} else {
								// CRITICAL: Ensure payment session ID is in form before Flow component submission
								if (window.ckoAddPaymentSessionIdField) {
									window.ckoAddPaymentSessionIdField();
								}
								
								// Check if component is valid before submitting
								// Note: isValid() returns a boolean, not a Promise
								if (ckoFlow.flowComponent && typeof ckoFlow.flowComponent.isValid === 'function') {
									const isValid = ckoFlow.flowComponent.isValid();
									if (isValid) {
										ckoLogger.debug('Flow component is valid, submitting...');
										try {
											ckoFlow.flowComponent.submit();
										} catch (error) {
											ckoLogger.error('Flow component submit failed:', error);
											showError('Payment processing failed. Please try again.');
										}
									} else {
										ckoLogger.error('Flow component is invalid, cannot submit');
										showError('Payment form is not valid. Please check your payment details and try again.');
									}
								} else {
									// Fallback: submit without validation check
									ckoLogger.debug('Flow component validity check not available, submitting directly...');
									try {
										if (ckoFlow.flowComponent && typeof ckoFlow.flowComponent.submit === 'function') {
											ckoFlow.flowComponent.submit();
										} else {
											ckoLogger.error('Flow component submit method not available');
											showError('Payment component not ready. Please refresh the page and try again.');
										}
									} catch (error) {
										ckoLogger.error('Flow component submit failed (fallback):', error);
										showError('Payment processing failed. Please try again.');
									}
								}
							}
						} else {
							// No flow component - submit order-pay form for saved card
							orderPayForm.submit();
						}
					}
					return; // Exit early for order-pay page
				}
				
				// Regular checkout: Check Flow component validity BEFORE creating order
				// This prevents creating orders when payment form is invalid
				ckoLogger.debug('[VALIDATION] Checking Flow component validity before order creation...');
				
				// Check if saved card is selected (saved cards don't need Flow component validation)
				// Re-check saved card status (may have changed)
				selectedSavedCard = jQuery('input[name="wc-wc_checkout_com_flow-payment-token"]:checked:not(#wc-wc_checkout_com_flow-payment-token-new)');
				savedCardSelected = selectedSavedCard.length > 0;
				savedCardEnabled = document.querySelector('[data-testid="checkout-web-component-root"]')?.classList.contains('saved-card-is-enabled') || false;
				
				// For new card payments, validate Flow component BEFORE creating order
				if (!savedCardSelected && !savedCardEnabled && ckoFlow.flowComponent) {
					if (typeof ckoFlow.flowComponent.isValid === 'function') {
						const isValid = ckoFlow.flowComponent.isValid();
						if (!isValid) {
							ckoLogger.error('[VALIDATION] ❌ Flow component is invalid - blocking order creation');
							showError('Payment form is not valid. Please check your payment details and try again.');
							return; // Exit early - don't create order
						}
						ckoLogger.debug('[VALIDATION] ✅ Flow component is valid - proceeding with order creation');
					} else {
						ckoLogger.warn('[VALIDATION] ⚠️ Flow component isValid() method not available - proceeding with order creation');
					}
				}
				
				// Use WooCommerce's full checkout processing (subscriptions rely on it).
				// For Flow (new card), pre-create the order via WC checkout before submitting payment.
				ckoLogger.debug('[CHECKOUT] Using WooCommerce full checkout flow (pre-create order for Flow).');

				// CRITICAL: Ensure payment session ID is added to form before submission
				// This is a fallback in case form wasn't available when payment session was created
				if (window.ckoAddPaymentSessionIdField) {
					const added = window.ckoAddPaymentSessionIdField();
					if (added) {
						ckoLogger.debug('[SAVE PAYMENT SESSION ID] Hidden field added on Place Order click (fallback)');
					}
				}

				// Persist save card checkbox before form submission
				persistSaveCardCheckbox();

				// Show flow container
				document.getElementById("flow-container").style.display = "block";

				// Place order for FLOW.
				if (ckoFlow.flowComponent) {
					if( savedCardSelected || savedCardEnabled ) {
						ckoLogger.debug('[SAVED CARD] Saved card selected - submitting checkout form directly (bypassing Flow component)');
						// CRITICAL: Ensure payment session ID is in form before submission
						if (window.ckoAddPaymentSessionIdField) {
							window.ckoAddPaymentSessionIdField();
						}
						// Submit directly - Flow component NOT used for saved cards
						// WooCommerce will call process_payment() which makes direct API call
						form.submit();
					} else {
						// Pre-create order via WooCommerce checkout (required for subscriptions)
						ckoLogger.debug('[CREATE ORDER] Creating order via WooCommerce checkout before Flow payment...');
						(async function() {
							const orderId = await createOrderBeforePayment();
							if (!orderId) {
								ckoLogger.error('[CREATE ORDER] Failed to create order - cannot proceed with payment');
								return;
							}

							ckoLogger.debug('[CREATE ORDER] ✅ Order created successfully - Order ID: ' + orderId);

							// CRITICAL: Ensure payment session ID is in form before Flow component submission
							if (window.ckoAddPaymentSessionIdField) {
								window.ckoAddPaymentSessionIdField();
							}

							// Double-check component validity before submitting (defense-in-depth)
							if (ckoFlow.flowComponent && typeof ckoFlow.flowComponent.isValid === 'function') {
								const isValid = ckoFlow.flowComponent.isValid();
								if (isValid) {
									ckoLogger.debug('Flow component is valid, submitting...');
									try {
										ckoFlow.flowComponent.submit();
									} catch (error) {
										ckoLogger.error('Flow component submit failed:', error);
										showError('Payment processing failed. Please try again.');
									}
								} else {
									ckoLogger.error('Flow component is invalid - cannot submit');
									showError('Payment form is not valid. Please check your payment details and try again.');
								}
							} else {
								// Fallback: submit without validation check
								ckoLogger.debug('Flow component validity check not available, submitting directly...');
								try {
									if (ckoFlow.flowComponent && typeof ckoFlow.flowComponent.submit === 'function') {
										ckoFlow.flowComponent.submit();
									} else {
										ckoLogger.error('Flow component submit method not available');
										showError('Payment component not ready. Please refresh the page and try again.');
									}
								} catch (error) {
									ckoLogger.error('Flow component submit failed (fallback):', error);
									showError('Payment processing failed. Please try again.');
								}
							}
						})();
					}
				} else {
					ckoLogger.error('[CHECKOUT] Flow component not found - cannot process payment');
					showError('Payment component not loaded. Please refresh the page and try again.');
				}

				// Place order for saved card when Flow component isn't available.
				if (!ckoFlow.flowComponent) {
					form.submit();
				}
			} else {
				// console.log('[CURRENT VERSION] Flow payment method not selected or not found');
			}
		}
	});
});

// Removed complex 3DS redirect handling - keeping it simple like the working version

/**
 * REFACTORED: Field change handlers extracted to modules/flow-field-change-handler.js
 */

/**
 * 
 * Debounce utility function.
 * Delays the execution of a function until after a specified delay
 * has passed since the last time it was invoked.
 * 
 * @param {Function} func - The function to debounce.
 * @param {number} delay - Delay in milliseconds.
 * 
 * @returns {Function} - A debounced version of the original function. 
 */
function debounce(func, delay) {
	let timer;

	return function (...args) {

		// Clear the existing timer, if any.
		clearTimeout(timer);

		// Set a new timer to call the function after the delay.
		timer = setTimeout(() => {

			// Call the original function with the correct context and arguments.
			func.apply(this, args);
		}, delay);
	};
}

/**
 * Checks if all non-shipping required fields in the WooCommerce checkout form are filled.
 *
 * This function looks for elements marked with a `.required` span inside labels
 * within the `.woocommerce-checkout` form, extracts the associated input field IDs,
 * filters out those related to shipping, and then verifies that the corresponding
 * fields are not empty.
 *
 * @returns {boolean} - Returns true if all non-shipping required fields are filled; otherwise, false.
 */
// requiredFieldsFilled() function moved to modules/flow-validation.js
// Function is available globally via FlowValidation.requiredFieldsFilled() or requiredFieldsFilled()

/**
 * Validates the checkout form by sending serialized form data to the server
 * using an AJAX POST request. Executes callback functions based on the response.
 *
 * @param {jQuery} form - The jQuery-wrapped form element to be validated.
 * @param {Function} onSuccess - Callback function executed when validation is successful.
 * @param {Function} onError - Optional callback function executed when validation fails or an error occurs.
 */
function validateCheckout(form, onSuccess, onError) {

	const formData = form.serialize(); // Serialize the form.

	// Perform AJAX POST request for server-side validation.
	jQuery.ajax({
		url: cko_flow_vars.ajax_url,
		type: "POST",
		data: {
			action: "cko_validate_checkout",
			...Object.fromEntries(new URLSearchParams(formData)),
		},
		success: function (response) {
			// Normalize WooCommerce public checkout AJAX response (result/redirect format) if used.
			let normalizedResponse = response;
			if (response && typeof response === 'object' && response.result) {
				if (response.result === 'success') {
					normalizedResponse = { success: true, data: {} };
				} else if (response.result === 'failure') {
					normalizedResponse = {
						success: false,
						data: {
							message: response.messages || 'Checkout validation failed.'
						}
					};
				}
			}

			// If the response indicates success, trigger the onSuccess callback.
			if (normalizedResponse.success) {
				onSuccess(normalizedResponse);
			} else {
				
				// Show an error message and trigger the onError callback if provided.
				showError(normalizedResponse.data.message);
				if (onError) onError(normalizedResponse);
			}
		},
		error: function () {

			// If the request fails, display the flow container and show a generic error message.
			document.getElementById("flow-container").style.display = "block";
			showError(
				wp.i18n.__(
					"An error occurred. Please try again.",
					"checkout-com-unified-payments-api"
				)
			);

			// Trigger onError callback if provided.
			if (onError) onError();
		},
	});
}

/**
 * Handles radio button selection across multiple saved payment method lists.
 *
 * This script ensures that only one radio button across multiple 
 * `.woocommerce-SavedPaymentMethods` lists can be selected at a time.
 *
 * Behavior:
 * - If a radio button inside one list is selected, all radios in the other lists are deselected.
 * - Supports dynamically added elements by using event delegation on `document`.
 *
 * Implementation details:
 * - Uses jQuery's `on('change')` with delegation to handle dynamically loaded content.
 * - `currentUl` stores the UL containing the selected radio.
 * - `otherUls` targets all other ULs to clear their selected radios.
 *
 * Example:
 * If the user selects a radio in the first UL and then selects a radio in the second UL,
 * the first UL's selected radio is automatically deselected.
 */
jQuery(function($) {
    $(document).on('change', '.woocommerce-SavedPaymentMethods input[type="radio"]', function() {
        const currentUl = $(this).closest('.woocommerce-SavedPaymentMethods'); // Current UL.
        const otherUls = $('.woocommerce-SavedPaymentMethods').not(currentUl);  // All other ULs.

        // Uncheck all radios in the other ULs.
        otherUls.find('input[type="radio"]').prop('checked', false);
    });
});

/**
 * Toggles the visibility of the Checkout Payment FLOW label based on the presence 
 * of the 'saved-card-is-enabled' class on the web component root.
 *
 * Behavior:
 * 1. Initially hides the label if the body has class 'flow-method-single' and the
 *    root element exists without 'saved-card-is-enabled'.
 * 2. Shows the label if 'saved-card-is-enabled' class is present on the root element.
 * 3. Dynamically observes the root element:
 *    - Detects when the root element appears in the DOM.
 *    - Watches for class changes on the root to toggle the label visibility in real-time.
 *
 * Uses MutationObserver to handle dynamically loaded elements and class changes.
 */
document.addEventListener('DOMContentLoaded', function () {
    const labelSelector = 'label[for="payment_method_wc_checkout_com_flow"]';
    const rootSelector = '[data-testid="checkout-web-component-root"]';

    const checkAndToggle = () => {
        const label = document.querySelector(labelSelector);
        const rootElement = document.querySelector(rootSelector);
        
        // Check if we have saved cards (new behavior)
        const hasSavedCards = document.querySelector('.saved-cards-accordion-container');

        if (label) {
            // ALWAYS show the label (either with saved cards or without)
            label.style.removeProperty('display');
            
            if (hasSavedCards) {
                ckoLogger.debug('Label shown - saved cards present');
            } else {
                ckoLogger.debug('Label shown - no saved cards (default label)');
            }
            
            /* DISABLED - Old behavior that was hiding the label
            // Old behavior: only for flow-method-single (no saved cards)
            if (document.body.classList.contains('flow-method-single') && rootElement) {
                if (rootElement.classList.contains('saved-card-is-enabled')) {
                    label.style.removeProperty('display'); // Show again.
                } else {
                    label.style.setProperty('display', 'none', 'important'); // Hide.
                }
            }
            */
        }
    };

    // Watch for root element and its class changes.
    const observer = new MutationObserver(() => {
        const rootElement = document.querySelector(rootSelector);

        if (rootElement) {
            // Run once when root appears.
            checkAndToggle();

            // Now watch the root for class changes.
            const classObserver = new MutationObserver(checkAndToggle);
            classObserver.observe(rootElement, { attributes: true, attributeFilter: ['class'] });

            observer.disconnect(); // Stop watching for root element.
        }
    });

    observer.observe(document.body, { childList: true, subtree: true });
    
    // Also check immediately and after a delay for saved cards
    checkAndToggle();
    setTimeout(checkAndToggle, 500);
    setTimeout(checkAndToggle, 1000);
});

/**
 * On DOMContentLoaded, checks if the current order payment type is "MOTO".
 * If so, it ensures that no saved payment tokens are pre-selected.
 * 
 * - Looks for the #order-pay-info element and reads its "order-pay" data attribute.
 * - If payment_type is "MOTO" (Admin order + Order-pay page + Guest customer):
 *   - Finds all saved payment token radio inputs from Checkout.com (Flow & Cards).
 *   - Iterates through them and unchecks each one, forcing the user to actively select a payment method.
 */
document.addEventListener('DOMContentLoaded', function () {
	const orderPayInfo = jQuery("#order-pay-info")?.data("order-pay");
	if ( orderPayInfo?.payment_type === 'MOTO' ) {
		ckoLogger.debug('MOTO payment type detected');
		const radios = document.querySelectorAll(
			'input[name="wc-wc_checkout_com_flow-payment-token"], input[name="wc-wc_checkout_com_cards-payment-token"]'
		);
		radios.forEach(radio => {
			radio.checked = false;
		});
	}
});

/**
 * REMOVED: JavaScript AJAX handler for 3DS redirects
 * 
 * The 3DS redirect now goes directly to the PHP endpoint (handle_3ds_return)
 * which processes the payment and redirects to the success page.
 * This provides a smoother transition without showing the checkout page.
 * 
 * The success_url in the payment session request points to:
 * /wc-api/wc_checkoutcom_flow_process?cko-payment-id=XXX
 * 
 * This endpoint processes the payment and redirects directly to order-received page.
 */
