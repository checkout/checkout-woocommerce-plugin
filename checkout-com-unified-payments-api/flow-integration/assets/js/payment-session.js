/*
 * Centralized logging utility for Checkout.com Flow integration.
 * Controls what logs appear in production vs debug mode.
 */
var ckoLogger = {
	debugEnabled: (typeof cko_flow_vars !== 'undefined' && cko_flow_vars.debug_logging) || false,
	
	// ALWAYS VISIBLE (Production + Debug) - Critical for payment troubleshooting
	error: function(message, data) {
		console.error('[FLOW ERROR] ' + message, data !== undefined ? data : '');
	},
	
	warn: function(message, data) {
		console.warn('[FLOW WARNING] ' + message, data !== undefined ? data : '');
	},
	
	webhook: function(message, data) {
		console.log('[FLOW WEBHOOK] ' + message, data !== undefined ? data : '');
	},
	
	threeDS: function(message, data) {
		console.log('[FLOW 3DS] ' + message, data !== undefined ? data : '');
	},
	
	payment: function(message, data) {
		console.log('[FLOW PAYMENT] ' + message, data !== undefined ? data : '');
	},
	
	version: function(version) {
		console.log('🚀 Checkout.com Flow v' + version);
	},
	
	// DEBUG ONLY (Hidden in Production) - Enable via "Debug Logging" setting
	debug: function(message, data) {
		if (this.debugEnabled) {
			console.log('[FLOW DEBUG] ' + message, data !== undefined ? data : '');
		}
	},
	
	performance: function(message, data) {
		if (this.debugEnabled) {
			console.log('[FLOW PERFORMANCE] ' + message, data !== undefined ? data : '');
		}
	}
};

/*
 * CRITICAL: Early 3DS Detection - MUST run before any other code
 * This prevents Flow from initializing during 3DS returns
 */

/**
 * TERMS CHECKBOX FIX: Prevent page reload when terms checkbox is clicked
 * 
 * Strategy: Intercept checkbox change events in CAPTURE phase BEFORE WooCommerce handlers run
 * This prevents WooCommerce from triggering update_checkout for terms checkboxes
 */

/**
 * Helper function to detect if an element is a terms/agreement checkbox
 * Works generically for any terms checkbox regardless of ID/name/class
 * Defined globally so it's accessible in updated_checkout handler
 */
function isTermsCheckbox(element) {
	if (!element || element.type !== 'checkbox') {
		return false;
	}
	
	const $element = jQuery(element);
	const id = (element.id || '').toLowerCase();
	const name = (element.name || '').toLowerCase();
	const className = (element.className || '').toLowerCase();
	
	// Check ID/name patterns
	if (id.includes('terms') || id.includes('agree') || id.includes('policy') ||
	    name.includes('terms') || name.includes('agree') || name.includes('policy')) {
		return true;
	}
	
	// Check for WooCommerce terms wrapper classes
	if ($element.closest('.woocommerce-terms-and-conditions-wrapper').length > 0 ||
	    $element.closest('.woocommerce-terms-and-conditions-checkbox-text').length > 0 ||
	    $element.closest('.terms-wrapper').length > 0) {
		return true;
	}
	
	// Check label text for agreement phrases
	const label = $element.closest('label');
	if (label.length) {
		const labelText = label.text().toLowerCase();
		const agreementPhrases = [
			'read and agree', 'read and accept', 'agree to', 'agree with',
			'accept the', 'accept our', 'terms and conditions', 'terms & conditions',
			'i agree', 'i accept', 'agree me'
		];
		if (agreementPhrases.some(phrase => labelText.includes(phrase))) {
			return true;
		}
	}
	
	return false;
}

(function() {
	/**
	 * CRITICAL FIX: Prevent page reload when terms checkbox is clicked
	 * 
	 * Strategy: Intercept jQuery's trigger() method BEFORE it fires update_checkout
	 * This prevents WooCommerce from triggering the event that causes page reload
	 */
	
	// Global flag to prevent update_checkout when terms checkbox is clicked
	window.ckoPreventUpdateCheckout = false;
	window.ckoTermsCheckboxLastClicked = null;
	window.ckoTermsCheckboxLastClickTime = 0;
	
	// Track clicks on checkboxes and set prevention flag
	document.addEventListener('click', function(e) {
		if (e.target.type === 'checkbox' && isTermsCheckbox(e.target)) {
			window.ckoPreventUpdateCheckout = true;
			window.ckoTermsCheckboxLastClicked = e.target;
			window.ckoTermsCheckboxLastClickTime = Date.now();
			ckoLogger.debug('Terms checkbox clicked - setting prevention flag', {
				elementId: e.target.id || 'no-id',
				elementName: e.target.name || 'no-name'
			});
			
			// Clear flag after longer delay to catch async triggers
			setTimeout(function() {
				window.ckoPreventUpdateCheckout = false;
			}, 3000); // Increased to 3 seconds to catch async triggers
		}
	}, true); // Capture phase to set flag early
	
	// CRITICAL: Intercept change events on terms checkboxes BEFORE they reach WooCommerce
	// This prevents WooCommerce from triggering update_checkout
	document.addEventListener('change', function(e) {
		if (e.target.type === 'checkbox' && isTermsCheckbox(e.target)) {
			window.ckoPreventUpdateCheckout = true;
			window.ckoTermsCheckboxLastClicked = e.target;
			window.ckoTermsCheckboxLastClickTime = Date.now();
			ckoLogger.debug('Terms checkbox changed - setting prevention flag', {
				elementId: e.target.id || 'no-id',
				elementName: e.target.name || 'no-name'
			});
			
			// Clear flag after longer delay (for change events which trigger async updates)
			setTimeout(function() {
				window.ckoPreventUpdateCheckout = false;
			}, 3000); // Increased to 3 seconds to catch async triggers
		}
	}, true); // Capture phase - runs BEFORE WooCommerce handlers
	
	// CRITICAL: Intercept checkbox change events via jQuery BEFORE WooCommerce handlers
	// This must run immediately, not wait for DOM ready
	if (typeof jQuery !== 'undefined') {
		// Use event delegation on document to catch all checkbox changes early
		jQuery(document).on('change.cko-terms-prevention', 'input[type="checkbox"]', function(e) {
			if (isTermsCheckbox(this)) {
				window.ckoPreventUpdateCheckout = true;
				window.ckoTermsCheckboxLastClicked = this;
				window.ckoTermsCheckboxLastClickTime = Date.now();
				ckoLogger.debug('🚫 Terms checkbox change intercepted via jQuery delegation - preventing update_checkout', {
					elementId: this.id || 'no-id',
					elementName: this.name || 'no-name'
				});
				
				// CRITICAL: Stop this event from reaching WooCommerce handlers
				e.stopImmediatePropagation();
				
				// Clear flag after delay
				setTimeout(function() {
					window.ckoPreventUpdateCheckout = false;
				}, 3000);
			}
		});
		
		// Also intercept on body (WooCommerce often uses body for event delegation)
		jQuery('body').on('change.cko-terms-prevention', 'input[type="checkbox"]', function(e) {
			if (isTermsCheckbox(this)) {
				window.ckoPreventUpdateCheckout = true;
				window.ckoTermsCheckboxLastClicked = this;
				window.ckoTermsCheckboxLastClickTime = Date.now();
				ckoLogger.debug('🚫 Terms checkbox change intercepted via body delegation - preventing update_checkout');
				e.stopImmediatePropagation();
				setTimeout(function() {
					window.ckoPreventUpdateCheckout = false;
				}, 3000);
			}
		});
	}
	
	// CRITICAL: Intercept jQuery's trigger() method to block update_checkout events
	// This must happen BEFORE WooCommerce's handlers run
	if (typeof jQuery !== 'undefined') {
		// Store original trigger methods
		const originalTrigger = jQuery.fn.trigger;
		const originalEventTrigger = jQuery.event.trigger;
		
		// Override jQuery.fn.trigger()
		// PERFORMANCE: Check flag first (fastest check) before string/object comparisons
		jQuery.fn.trigger = function(event, data) {
			// Fast path: Only check if prevention flag is set (most trigger calls skip this)
			if (window.ckoPreventUpdateCheckout) {
				// Only do expensive checks if flag is set (rare case)
				const eventName = typeof event === 'string' ? event : (event && event.type ? event.type : 'unknown');
				const isUpdateCheckout = eventName === 'update_checkout' || 
				                        (typeof event === 'object' && event && event.type === 'update_checkout');
				if (isUpdateCheckout) {
					ckoLogger.debug('✅ BLOCKED update_checkout trigger from jQuery.fn.trigger() - terms checkbox prevention active', {
						event: eventName,
						element: this[0] ? (this[0].id || this[0].tagName || this[0].className) : 'unknown',
						preventionFlag: window.ckoPreventUpdateCheckout
					});
					return this; // Return jQuery object without triggering event
				}
			}
			// Call original trigger for all other events (99.9% of calls take this path)
			return originalTrigger.apply(this, arguments);
		};
		
		// Override jQuery.event.trigger() (used by jQuery internally)
		// PERFORMANCE: Check flag first (fastest check) before string/object comparisons
		jQuery.event.trigger = function(event, data, elem, onlyHandlers) {
			// Fast path: Only check if prevention flag is set (most trigger calls skip this)
			if (window.ckoPreventUpdateCheckout) {
				// Only do expensive checks if flag is set (rare case)
				const eventName = typeof event === 'string' ? event : (event && event.type ? event.type : 'unknown');
				const isUpdateCheckout = eventName === 'update_checkout' || 
				                        (typeof event === 'object' && event && event.type === 'update_checkout');
				if (isUpdateCheckout) {
					ckoLogger.debug('✅ BLOCKED update_checkout trigger from jQuery.event.trigger() - terms checkbox prevention active', {
						event: eventName,
						element: elem ? (elem.id || elem.tagName || elem.className) : 'unknown',
						preventionFlag: window.ckoPreventUpdateCheckout
					});
					return; // Exit without triggering event
				}
			}
			// Call original trigger for all other events (99.9% of calls take this path)
			return originalEventTrigger.apply(this, arguments);
		};
		
		ckoLogger.debug('jQuery trigger interception installed for terms checkbox prevention');
		
		// CRITICAL: Also intercept form submissions triggered by terms checkbox
		// WooCommerce might submit the form after update_checkout event
		const checkoutForm = document.querySelector('form.checkout');
		if (checkoutForm) {
			// Intercept form submit events
			checkoutForm.addEventListener('submit', function(e) {
				// Check if prevention flag is set (terms checkbox was clicked recently)
				if (window.ckoPreventUpdateCheckout || 
				    (window.ckoTermsCheckboxLastClicked && 
				     isTermsCheckbox(window.ckoTermsCheckboxLastClicked) && 
				     (Date.now() - window.ckoTermsCheckboxLastClickTime) < 2000)) {
					ckoLogger.debug('Blocked form submission triggered by terms checkbox', {
						preventionFlag: window.ckoPreventUpdateCheckout,
						lastClickedId: window.ckoTermsCheckboxLastClicked ? (window.ckoTermsCheckboxLastClicked.id || 'no-id') : 'none'
					});
					e.preventDefault();
					e.stopImmediatePropagation();
					// Clear flags
					window.ckoPreventUpdateCheckout = false;
					window.ckoTermsCheckboxLastClicked = null;
					return false;
				}
			}, true); // Capture phase to intercept early
		}
		
		// Also intercept via jQuery (backup)
		jQuery(document).on('submit', 'form.checkout', function(e) {
			if (window.ckoPreventUpdateCheckout || 
			    (window.ckoTermsCheckboxLastClicked && 
			     isTermsCheckbox(window.ckoTermsCheckboxLastClicked) && 
			     (Date.now() - window.ckoTermsCheckboxLastClickTime) < 2000)) {
				ckoLogger.debug('Blocked jQuery form submission triggered by terms checkbox');
				e.preventDefault();
				e.stopImmediatePropagation();
				window.ckoPreventUpdateCheckout = false;
				window.ckoTermsCheckboxLastClicked = null;
				return false;
			}
		});
		
	}
})();
(function() {
	// Check URL parameters immediately (before any other code runs)
	const urlParams = new URLSearchParams(window.location.search);
	const paymentId = urlParams.get("cko-payment-id");
	const sessionId = urlParams.get("cko-session-id");
	const paymentSessionId = urlParams.get("cko-payment-session-id");
	
	if (paymentId || sessionId || paymentSessionId) {
		// Set flag IMMEDIATELY - this prevents ALL Flow initialization
		window.ckoFlow3DSReturn = true;
		
		// Log using console.log (always available, even before ckoLogger is defined)
		console.log('[FLOW 3DS] ⚠️⚠️⚠️ EARLY DETECTION: 3DS return detected, preventing ALL Flow initialization', {
			paymentId: paymentId,
			sessionId: sessionId,
			paymentSessionId: paymentSessionId,
			timestamp: new Date().toISOString()
		});
	}
})();

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
		if (window.ckoFlow3DSReturn) {
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
		// CRITICAL: Check for 3DS return FIRST - before any other checks
		if (window.ckoFlow3DSReturn) {
			ckoLogger.threeDS('loadFlow: 3DS return in progress, aborting Flow initialization');
			return; // Exit immediately
		}
		
		// Check if cko_flow_vars is available
		if (typeof cko_flow_vars === 'undefined') {
			ckoLogger.error('cko_flow_vars is not defined. Flow cannot be initialized.');
			return;
		}
		
		// CRITICAL: Validate required fields BEFORE creating payment session
		// This prevents API errors when fields aren't filled (defense-in-depth)
		const fieldsFilled = requiredFieldsFilled();
		const fieldsValid = requiredFieldsFilledAndValid();
		const emailValue = getCheckoutFieldValue("billing_email");
		
		ckoLogger.debug('loadFlow: Field validation check:', {
			fieldsFilled: fieldsFilled,
			fieldsValid: fieldsValid,
			email: emailValue || '(empty)',
			emailLength: emailValue?.length || 0
		});
		
		if (!fieldsValid) {
			ckoLogger.debug('loadFlow: Required fields not filled - showing waiting message and aborting');
			showFlowWaitingMessage();
			// Reset initialization state so Flow can retry when fields are filled
			ckoFlowInitialized = false;
			ckoFlowInitializing = false;
			return; // Exit - don't proceed with payment session creation
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
		window.ckoFlow3DSReturn = true;
		
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
		
		let cartInfo = jQuery("#cart-info").data("cart");

		if ( ! cartInfo || jQuery.isEmptyObject( cartInfo ) ) {
			cartInfo = jQuery("#order-pay-info").data("order-pay");
		}

		/*
		 * Extract information from cartInfo or fallback to DOM form inputs.
		 */
		let amount = cartInfo["order_amount"];
		let currency = cartInfo["purchase_currency"];

		let reference = "WOO" + (cko_flow_vars.ref_session || 'default');

		// CRITICAL: Check if billing_address exists before accessing properties
		const billingAddress = cartInfo["billing_address"] || {};
		let email =
			billingAddress["email"] ||
			(document.getElementById("billing_email") ? document.getElementById("billing_email").value : '');
		let family_name =
			billingAddress["family_name"] ||
			(document.getElementById("billing_last_name") ? document.getElementById("billing_last_name").value : '');
		let given_name =
			billingAddress["given_name"] ||
			(document.getElementById("billing_first_name") ? document.getElementById("billing_first_name").value : '');
		
		// CRITICAL: Validate email before proceeding - prevent API call with invalid email
		// Use inline validation to ensure it works even if isValidEmail() isn't available
		const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
		if (!email || typeof email !== 'string' || !email.trim() || !emailRegex.test(email.trim())) {
			ckoLogger.error('❌ BLOCKED: Invalid email at first check', { 
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
		let phone =
			billingAddress["phone"] ||
			(document.getElementById("billing_phone") ? document.getElementById("billing_phone").value : '');

		let address1 = shippingAddress1 = billingAddress["street_address"] || '';
		let address2 = shippingAddress2 = billingAddress["street_address2"] || '';
		let city = shippingCity = billingAddress["city"] || '';
		let zip = shippingZip = billingAddress["postal_code"] || '';
		let country = shippingCountry = billingAddress["country"] || '';
			

		let shippingElement = document.getElementById("ship-to-different-address-checkbox");
		if ( shippingElement?.checked ) {
			// CRITICAL: Check if shipping_address exists before accessing properties
			const shippingAddress = cartInfo["shipping_address"] || {};
			shippingAddress1 = shippingAddress["street_address"] || address1;
			shippingAddress2 = shippingAddress["street_address2"] || address2;
			shippingCity = shippingAddress["city"] || city;
			shippingZip = shippingAddress["postal_code"] || zip;
			shippingCountry = shippingAddress["country"] || country;
		}

		let orders = cartInfo["order_lines"];

		// CLIENT-SIDE FIX: Check if shipping is missing from order_lines
		// This handles cases where cart-info was populated before shipping was calculated
		if (orders && Array.isArray(orders) && cartInfo["order_amount"]) {
			const productsTotal = orders.reduce((sum, item) => {
				return sum + (parseInt(item.total_amount) || 0);
			}, 0);
			const orderAmountCents = parseInt(cartInfo["order_amount"]) || 0;
			const shippingDifference = orderAmountCents - productsTotal;
			
			// If there's a positive difference (shipping amount) and no shipping item exists
			if (shippingDifference > 0) {
				const hasShipping = orders.some(item => 
					item.type === 'shipping_fee' || 
					item.reference === 'shipping' || 
					(item.name && item.name.toLowerCase().includes('shipping'))
				);
				
				if (!hasShipping) {
					// Try to get shipping method name from DOM or use default
					let shippingMethodName = 'Shipping';
					const shippingMethodElement = document.querySelector('.woocommerce-shipping-methods .shipping-method input:checked + label, .woocommerce-shipping-methods label');
					if (shippingMethodElement) {
						shippingMethodName = shippingMethodElement.textContent.trim() || 'Shipping';
					}
					
					// Add shipping to order_lines
					orders.push({
						name: shippingMethodName,
						quantity: 1,
						unit_price: shippingDifference,
						total_amount: shippingDifference,
						tax_amount: 0,
						type: 'shipping_fee',
						reference: 'shipping',
						discount_amount: 0
					});
					
					ckoLogger.debug('[PAYMENT SESSION] [SHIPPING DEBUG] Added missing shipping to order_lines - Amount: ' + shippingDifference + ', Name: ' + shippingMethodName);
				}
			}
		}

		let products = orders
			.map(line => line.name)
			.join(', ');

		let description = 'Payment from ' + cko_flow_vars.site_url + ' for [ ' +  products + ' ]';
		
		// Truncate description to 100 characters (Checkout.com API limit)
		if (description.length > 100) {
			description = description.substring(0, 97) + '...';
		}

		let orderId = cartInfo["order_id"];
		ckoLogger.debug('Initial orderId from cartInfo:', orderId);
		ckoLogger.debug('Current URL pathname:', window.location.pathname);
		ckoLogger.debug('Current URL search:', window.location.search);
		
		// For MOTO orders (order-pay page), get order ID from URL path if not in cartInfo
		if ( ! orderId && window.location.pathname.includes('/order-pay/') ) {
			// Extract order ID from URL path like /order-pay/4127/
			const pathMatch = window.location.pathname.match(/\/order-pay\/(\d+)\//);
			orderId = pathMatch ? pathMatch[1] : null;
			ckoLogger.debug('MOTO order detected - Order ID from URL path:', orderId);
		}
		ckoLogger.debug('Final orderId:', orderId);

		let payment_type = cko_flow_vars.regular_payment_type;
		let metadata = {
			udf5: cko_flow_vars.udf5,
		}

		// Check for subscription product FIRST (before orderId check)
		let containsSubscriptionProduct = cartInfo["contains_subscription_product"];
		let cartInfoPaymentType = cartInfo["payment_type"];
		let isSubscription = false;
		
		// PRIORITY 1: Check cartInfo["payment_type"] if already set to Recurring by backend
		if ( cartInfoPaymentType === cko_flow_vars.recurring_payment_type ) {
			payment_type = cko_flow_vars.recurring_payment_type;
			isSubscription = true;
			ckoLogger.debug('Subscription detected via cartInfo["payment_type"] = Recurring');
		}
		// PRIORITY 2: Check containsSubscriptionProduct flag
		else if ( containsSubscriptionProduct ) {
			isSubscription = orders.some(order => order.is_subscription === true);
			if ( isSubscription ) {
				payment_type = cko_flow_vars.recurring_payment_type;
				ckoLogger.debug('Subscription detected via containsSubscriptionProduct flag');
			}
		}

		if ( orderId ) {
			metadata = {
				udf5: cko_flow_vars.udf5,
				order_id: orderId,
			}

			ckoLogger.debug('🔍 OrderId exists - Checking cartInfo payment_type:', {
				orderId: orderId,
				cartInfo_payment_type: cartInfo["payment_type"],
				isSubscription: isSubscription,
				current_payment_type: payment_type
			});

			// Only use cartInfo payment_type if it's not already set to recurring by subscription check
			if ( !isSubscription ) {
				payment_type = cartInfo["payment_type"];
				ckoLogger.debug('✅ Using cartInfo payment_type:', payment_type);
			} else {
				ckoLogger.debug('⏭️ Skipping cartInfo payment_type (subscription takes priority). Current payment_type:', payment_type);
			}
		} else {
			ckoLogger.debug('🔍 No orderId - payment_type remains:', payment_type);
		}

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
				paymentSessionRequest.success_url = window.location.origin + "/?wc-api=wc_checkoutcom_flow_process&cko-save-card=" + saveCardValue;
				paymentSessionRequest.failure_url = window.location.origin + "/?wc-api=wc_checkoutcom_flow_process&cko-save-card=" + saveCardValue;
				ckoLogger.debug('Regular checkout - using PHP endpoint for direct redirect to success page, save card preference: ' + saveCardValue);
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
				paymentSessionRequest.success_url = window.location.origin + "/?wc-api=wc_checkoutcom_flow_process&cko-save-card=" + currentSaveCardValue;
				paymentSessionRequest.failure_url = window.location.origin + "/?wc-api=wc_checkoutcom_flow_process&cko-save-card=" + currentSaveCardValue;
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
						window.flowUserInteracted = false;
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
						// For regular checkout with card payments, redirect to process payment endpoint with order ID and payment ID
						// This ensures process_payment() is called with the existing order
						// CRITICAL: handle_3ds_return() requires cko-payment-id in GET params
						const redirectUrl = window.location.origin + '/?wc-api=wc_checkoutcom_flow_process&order_id=' + orderIdToUse + '&cko-payment-id=' + paymentResponse.id;
						ckoLogger.debug('[PAYMENT COMPLETED] Redirecting to process payment endpoint: ' + redirectUrl);
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
			ckoLogger.debug('onChange - User interacted flag:', window.flowUserInteracted);
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

					console.log(
						`[FLOW] onChange() -> isValid: "${component.isValid()}" for "${
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
			console.log('[FLOW] ✓ Place Order button enabled');
		}
		
		// Step 3: Hide save card checkbox initially
		if (saveCardCheckbox) {
			saveCardCheckbox.style.display = 'none';
		} else {
			console.log('[FLOW] Mount - Save card checkbox not available (feature disabled)');
		}
		
		// Step 4: Mark Flow as ready
		document.body.classList.add("flow-ready");
		console.log('[FLOW] ✓ Checkout is now fully interactive');
		
		// CRITICAL: Listen for user interaction with Flow fields (click, focus, input)
		// This detects when user actually starts using Flow (not just onChange firing on load)
		setTimeout(() => {
			const flowContainer = document.getElementById("flow-container");
			if (flowContainer) {
				// Listen for any interaction with Flow component
				flowContainer.addEventListener('click', function() {
					if (!window.flowUserInteracted) {
						console.log('[FLOW] User clicked on Flow component - marking as interacted');
						window.flowUserInteracted = true;
						window.flowSavedCardSelected = false; // Reset saved card flag when user interacts with Flow
					}
				}, { once: false });
				
				flowContainer.addEventListener('focus', function(e) {
					if (!window.flowUserInteracted) {
						console.log('[FLOW] User focused on Flow field - marking as interacted');
						window.flowUserInteracted = true;
						window.flowSavedCardSelected = false; // Reset saved card flag when user interacts with Flow
					}
				}, { capture: true });
				
				flowContainer.addEventListener('input', function(e) {
					if (!window.flowUserInteracted) {
						console.log('[FLOW] User typing in Flow field - marking as interacted');
						window.flowUserInteracted = true;
						window.flowSavedCardSelected = false; // Reset saved card flag when user interacts with Flow
					}
				}, { capture: true });
				
				console.log('[FLOW] Flow interaction listeners attached');
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
			window.ckoLastError = Array.isArray(error_message) ? error_message[0] : error_message;
			ckoLogger.error("showError() - Stored error message for persistence:", window.ckoLastError);
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
	if (window.ckoLastError) {
		ckoLogger.error("updated_checkout fired - Re-displaying stored error:", window.ckoLastError);
		// Use a small delay to ensure form is fully updated
		setTimeout(function() {
			showError(window.ckoLastError);
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
				window.ckoLastError = null;
				ckoLogger.error("Cleared stored error message");
			}, 5000);
		}, 100);
	}
});

/**
 * Initialize Flow interaction tracking flag
 * This flag tracks whether the user has actively interacted with the Flow component
 * to differentiate between saved card payments and new Flow payments
 */
window.flowUserInteracted = false;

/**
 * Initializes the observer to monitor the presence of the Flow checkout component in the DOM.
 *
 * - Sets the `ckoFlowInitialized` flag to `false` on page load.
 * - Observes the DOM for any changes using `MutationObserver`.
 * - If the Flow checkout component (identified by `data-testid="checkout-web-component-root"`) 
 *   is removed from the DOM, the flag `ckoFlowInitialized` is reset to `false`.
 *
 * This helps ensure that the Flow component can be re-initialized when needed.
 */

let ckoFlowInitialized = false;
let ckoFlowInitializing = false; // Guard flag to prevent multiple simultaneous initializations
let ckoOrderCreationInProgress = false; // Guard flag to prevent multiple simultaneous order creation calls

// Note: Early 3DS detection is now at the top of the file (right after ckoLogger definition)
// This ensures it runs before any other code that might initialize Flow

document.addEventListener("DOMContentLoaded", function () {
	// CRITICAL: Check for 3DS return FIRST - before any other checks
	// Check flag first (set by early detection)
	if (window.ckoFlow3DSReturn) {
		console.log('[FLOW 3DS] ⚠️ DOMContentLoaded: Blocked by 3DS return flag');
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
		console.log('[FLOW 3DS] ⚠️ DOMContentLoaded: Blocked by 3DS return URL parameters', {
			paymentId: paymentId,
			sessionId: sessionId,
			paymentSessionId: paymentSessionId
		});
		window.ckoFlow3DSReturn = true;
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
	ckoFlowInitialized = false;

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
function isUserLoggedIn() {
	return cko_flow_vars.is_user_logged_in === true || 
		   cko_flow_vars.is_user_logged_in === "1" ||
		   cko_flow_vars.is_user_logged_in === 1 ||
		   document.querySelector('.woocommerce-form-login') === null;
}

/**
 * Get checkout field value by ID
 * @param {string} fieldId - Field ID
 * @returns {string|null}
 */
function getCheckoutFieldValue(fieldId) {
	const el = document.getElementById(fieldId);
	return el && el.value ? el.value.trim() : null;
}

/**
 * Validate email format
 * @param {string} email - Email address
 * @returns {boolean}
 */
function isValidEmail(email) {
	if (!email) return false;
	const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
	return emailRegex.test(email);
}

/**
 * Check if postcode is required for a country
 * @param {string} country - Country code
 * @returns {boolean}
 */
function isPostcodeRequiredForCountry(country) {
	if (!country) return true; // Default to required if unknown
	// Countries that don't require postcode
	const noPostcodeCountries = ['AE', 'AO', 'AG', 'AW', 'BS', 'BZ', 'BJ', 'BW', 'BF', 'BI', 'CM', 'CF', 'KM', 'CG', 'CD', 'CK', 'CI', 'DJ', 'DM', 'GQ', 'ER', 'FJ', 'TF', 'GM', 'GH', 'GD', 'GN', 'GY', 'HK', 'IE', 'JM', 'KE', 'KI', 'LS', 'LR', 'MW', 'ML', 'MR', 'MU', 'MS', 'NR', 'NU', 'KP', 'PA', 'QA', 'RW', 'KN', 'LC', 'ST', 'SC', 'SL', 'SB', 'SO', 'SR', 'SZ', 'TJ', 'TZ', 'TL', 'TG', 'TO', 'TT', 'TV', 'UG', 'VU', 'YE', 'ZW'];
	return !noPostcodeCountries.includes(country.toUpperCase());
}

/**
 * Check if billing address is present
 * For order-pay pages, checks order data first (cartInfo), then falls back to form fields
 * @returns {boolean}
 */
function hasBillingAddress() {
	// Check if we're on order-pay page and have order data
	const isOrderPayPage = window.location.pathname.includes('/order-pay/');
	let orderPayInfo = null;
	if (isOrderPayPage) {
		orderPayInfo = jQuery("#order-pay-info")?.data("order-pay");
	}
	
	// If order-pay page and order data exists, use it for validation
	if (isOrderPayPage && orderPayInfo && orderPayInfo.billing_address) {
		const billing = orderPayInfo.billing_address;
		const email = billing.email || billing.Email || '';
		const address1 = billing.street_address || billing.address_line1 || '';
		const city = billing.city || '';
		const country = billing.country || '';
		
		if (email && address1 && city && country) {
			ckoLogger.debug('hasBillingAddress: Using order data (order-pay page)', {
				email: email ? 'SET' : 'EMPTY',
				address1: address1 ? 'SET' : 'EMPTY',
				city: city ? 'SET' : 'EMPTY',
				country: country ? 'SET' : 'EMPTY'
			});
			return true;
		}
	}
	
	// Fallback to form fields (regular checkout or if order data not available)
	const email = getCheckoutFieldValue("billing_email");
	const address1 = getCheckoutFieldValue("billing_address_1");
	const city = getCheckoutFieldValue("billing_city");
	const country = getCheckoutFieldValue("billing_country");
	
	return !!(email && address1 && city && country);
}

/**
 * Check if billing address is complete
 * For order-pay pages, checks order data first (cartInfo), then falls back to form fields
 * @returns {boolean}
 */
function hasCompleteBillingAddress() {
	// Check if we're on order-pay page and have order data
	const isOrderPayPage = window.location.pathname.includes('/order-pay/');
	let orderPayInfo = null;
	if (isOrderPayPage) {
		orderPayInfo = jQuery("#order-pay-info")?.data("order-pay");
	}
	
	// If order-pay page and order data exists, use it for validation
	if (isOrderPayPage && orderPayInfo && orderPayInfo.billing_address) {
		const billing = orderPayInfo.billing_address;
		const address1 = billing.street_address || billing.address_line1 || '';
		const city = billing.city || '';
		const country = billing.country || '';
		const postcode = billing.postal_code || billing.postcode || '';
		
		if (!address1 || !city || !country) {
			return false;
		}
		
		// Check if postcode is required for country
		const postcodeRequired = isPostcodeRequiredForCountry(country);
		if (postcodeRequired && !postcode) {
			return false;
		}
		
		return true;
	}
	
	// Fallback to form fields (regular checkout or if order data not available)
	const address1 = getCheckoutFieldValue("billing_address_1");
	const city = getCheckoutFieldValue("billing_city");
	const country = getCheckoutFieldValue("billing_country");
	const postcode = getCheckoutFieldValue("billing_postcode");
	
	if (!address1 || !city || !country) {
		return false;
	}
	
	// Check if postcode is required for country
	const postcodeRequired = isPostcodeRequiredForCountry(country);
	if (postcodeRequired && !postcode) {
		return false;
	}
	
	return true;
}

/**
 * Enhanced required fields check with email validation
 * For order-pay pages, checks order data first (cartInfo), then falls back to form fields
 * @returns {boolean}
 */
function requiredFieldsFilledAndValid() {
	// Check if we're on order-pay page and have order data
	const isOrderPayPage = window.location.pathname.includes('/order-pay/');
	let orderPayInfo = null;
	if (isOrderPayPage) {
		orderPayInfo = jQuery("#order-pay-info")?.data("order-pay");
	}
	
	// If order-pay page and order data exists, use it for validation
	if (isOrderPayPage && orderPayInfo && orderPayInfo.billing_address) {
		const billing = orderPayInfo.billing_address;
		const email = billing.email || billing.Email || '';
		const address1 = billing.street_address || billing.address_line1 || '';
		const city = billing.city || '';
		const country = billing.country || '';
		const postcode = billing.postal_code || billing.postcode || '';
		
		// Check if all required fields are filled
		if (!email || !address1 || !city || !country) {
			ckoLogger.debug('requiredFieldsFilledAndValid: Order data missing required fields', {
				email: email ? 'SET' : 'EMPTY',
				address1: address1 ? 'SET' : 'EMPTY',
				city: city ? 'SET' : 'EMPTY',
				country: country ? 'SET' : 'EMPTY'
			});
			return false;
		}
		
		// Validate email format
		const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
		if (!emailRegex.test(email.trim())) {
			ckoLogger.debug('requiredFieldsFilledAndValid: Order data email invalid', { email: email });
			return false;
		}
		
		// Check postcode if required for country
		const postcodeRequired = isPostcodeRequiredForCountry(country);
		if (postcodeRequired && !postcode) {
			ckoLogger.debug('requiredFieldsFilledAndValid: Order data missing postcode for country', { country: country });
			return false;
		}
		
		ckoLogger.debug('requiredFieldsFilledAndValid: Order data validation passed (order-pay page)', {
			email: email ? 'SET' : 'EMPTY',
			address1: address1 ? 'SET' : 'EMPTY',
			city: city ? 'SET' : 'EMPTY',
			country: country ? 'SET' : 'EMPTY',
			postcode: postcode ? 'SET' : 'EMPTY'
		});
		return true;
	}
	
	// Fallback to form fields (regular checkout or if order data not available)
	// First check if all required fields are filled
	ckoLogger.debug('requiredFieldsFilledAndValid: Calling requiredFieldsFilled()...');
	const fieldsFilled = requiredFieldsFilled();
	ckoLogger.debug('requiredFieldsFilledAndValid: requiredFieldsFilled() returned:', fieldsFilled);
	if (!fieldsFilled) {
		ckoLogger.debug('requiredFieldsFilledAndValid: requiredFieldsFilled() returned false - fields not filled');
		return false;
	}
	
	// Validate email format
	const email = getCheckoutFieldValue("billing_email");
	if (!email || !isValidEmail(email)) {
		return false;
	}
	
	// Check billing address is complete
	if (!hasCompleteBillingAddress()) {
		return false;
	}
	
	return true;
}

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
	const wereFilled = window.ckoFlowFieldsWereFilled || false;
	const areFilled = requiredFieldsFilledAndValid();
	
	ckoLogger.debug('checkRequiredFieldsStatus:', {
		wereFilled: wereFilled,
		areFilled: areFilled,
		flowInitialized: ckoFlowInitialized,
		flowComponentExists: !!ckoFlow.flowComponent
	});
	
	if (wereFilled && !areFilled) {
		// Fields became unfilled
		ckoLogger.debug('Required fields became unfilled - destroying Flow');
		if (ckoFlowInitialized && ckoFlow.flowComponent) {
			destroyFlowComponent();
			showFlowWaitingMessage();
			ckoFlowInitialized = false;
		}
	} else if (!wereFilled && areFilled) {
		// Fields became filled - initialize Flow
		ckoLogger.debug('Required fields became filled - initializing Flow');
		if (!ckoFlowInitialized && !ckoFlowInitializing) {
			initializeFlowIfNeeded();
		} else if (ckoFlowInitializing) {
			ckoLogger.debug('Flow initialization already in progress, skipping');
		}
	} else if (areFilled && !ckoFlowInitialized && !ckoFlowInitializing) {
		// Fields are filled but Flow not initialized - try to initialize
		ckoLogger.debug('Fields are filled but Flow not initialized - attempting initialization');
		initializeFlowIfNeeded();
	} else if (areFilled && ckoFlowInitializing) {
		ckoLogger.debug('Flow initialization already in progress, skipping duplicate call');
	}
	
	window.ckoFlowFieldsWereFilled = areFilled;
}

/**
 * Debounced check for Flow reload when critical fields change
 */
let reloadFlowTimeout = null;
function debouncedCheckFlowReload(fieldName, newValue) {
	// Clear existing timeout
	if (reloadFlowTimeout) {
		clearTimeout(reloadFlowTimeout);
	}
	
	// Set new timeout
	reloadFlowTimeout = setTimeout(() => {
		// Only reload if Flow is initialized and field is actually filled
		if (!ckoFlowInitialized || !ckoFlow.flowComponent) {
			return;
		}
		
		// Check if all required fields are still filled
		if (!requiredFieldsFilledAndValid()) {
			// Fields became invalid - destroy Flow
			destroyFlowComponent();
			showFlowWaitingMessage();
			ckoFlowInitialized = false;
			return;
		}
		
		// Critical field changed - reload Flow
		ckoLogger.debug(`Critical field ${fieldName} changed - reloading Flow`);
		reloadFlowComponent();
	}, 1000); // 1 second debounce for reload check
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
	// CRITICAL: Prevent multiple simultaneous initializations
	if (ckoFlowInitializing) {
		ckoLogger.debug('Flow initialization already in progress, skipping duplicate call');
		return;
	}
	
	// CRITICAL: Don't initialize if we're handling a 3DS return
	// Check flag first (set by early detection)
	if (window.ckoFlow3DSReturn) {
		console.log('[FLOW 3DS] ⚠️ initializeFlowIfNeeded: Blocked by 3DS return flag');
		if (typeof ckoLogger !== 'undefined') {
			ckoLogger.threeDS('Skipping Flow initialization - 3DS return in progress');
		}
		return;
	}
	
	// Also check URL parameters as fallback
	const urlParams = new URLSearchParams(window.location.search);
	const paymentId = urlParams.get("cko-payment-id");
	const sessionId = urlParams.get("cko-session-id");
	const paymentSessionId = urlParams.get("cko-payment-session-id");
	
	if (paymentId || sessionId || paymentSessionId) {
		console.log('[FLOW 3DS] ⚠️ initializeFlowIfNeeded: Blocked by 3DS return URL parameters');
		window.ckoFlow3DSReturn = true;
		if (typeof ckoLogger !== 'undefined') {
			ckoLogger.threeDS('Skipping Flow initialization - 3DS return detected in URL');
		}
		return;
	}
	
	const flowPayment = document.getElementById("payment_method_wc_checkout_com_flow");
	const flowContainer = document.getElementById("flow-container");
	const flowComponentRoot = document.querySelector('[data-testid="checkout-web-component-root"]');
	
	ckoLogger.debug('initializeFlowIfNeeded() state check:', {
		flowPaymentExists: !!flowPayment,
		flowPaymentChecked: flowPayment?.checked || false,
		flowContainerExists: !!flowContainer,
		flowComponentRootExists: !!flowComponentRoot,
		flowInitialized: ckoFlowInitialized,
		flowInitializing: ckoFlowInitializing,
		flowComponentExists: !!ckoFlow.flowComponent
	});
	
	// Check if Flow payment method is selected
	if (!flowPayment || !flowPayment.checked) {
		ckoLogger.debug('Flow payment method not selected, skipping initialization');
		return;
	}
	
	// Check if container exists
	if (!flowContainer) {
		ckoLogger.debug('Flow container not found, skipping initialization');
		return;
	}
	
	// Check if already initialized and component is mounted
	if (ckoFlowInitialized && ckoFlow.flowComponent && flowComponentRoot) {
		ckoLogger.debug('Flow already initialized and mounted, skipping');
		// Just ensure container is visible
		flowContainer.style.display = "block";
		document.body.classList.add("flow-method-selected");
		hideFlowWaitingMessage(); // Hide waiting message if shown
		return;
	}
	
	// NEW: Check if Flow can be initialized (validation check)
	const canInit = canInitializeFlow();
	ckoLogger.debug('canInitializeFlow() check result:', {
		canInit: canInit,
		flowPaymentSelected: !!flowPayment && flowPayment.checked,
		containerExists: !!flowContainer,
		cartTotal: cko_flow_vars?.cart_total,
		isLoggedIn: isUserLoggedIn(),
		hasBillingAddress: hasBillingAddress(),
		requiredFieldsFilled: requiredFieldsFilled(),
		requiredFieldsValid: requiredFieldsFilledAndValid()
	});
	
	if (!canInit) {
		ckoLogger.debug('Cannot initialize Flow - validation failed');
		// Show waiting message
		document.body.classList.add("flow-method-selected");
		showFlowWaitingMessage();
		// Setup field watchers to check again when fields are filled
		setupFieldWatchersForInitialization();
		return;
	}
	
	// Hide waiting message if it was shown
	hideFlowWaitingMessage();
	
	// Initialize Flow
	ckoLogger.debug('Initializing Flow - payment selected, container exists, validation passed');
	document.body.classList.add("flow-method-selected");
	flowContainer.style.display = "block";
	
	// Mark fields as filled
	window.ckoFlowFieldsWereFilled = true;
	
	// Only initialize if not already initialized
	if (!ckoFlowInitialized || !ckoFlow.flowComponent) {
		// Set guard flag to prevent multiple simultaneous initializations
		// NOTE: This flag will be cleared when component is mounted (in loadFlow callback)
		// or on error (in catch block of loadFlow)
		ckoFlowInitializing = true;
		
		try {
			ckoLogger.debug('Calling ckoFlow.init()...');
			ckoFlow.init();
			// Don't set ckoFlowInitialized = true here - it will be set when component mounts
			// Don't clear ckoFlowInitializing here - it will be cleared when component mounts
		} catch (error) {
			ckoLogger.debug('Error during Flow initialization:', error);
			ckoFlowInitialized = false;
			ckoFlowInitializing = false; // Clear guard flag on error
			throw error; // Re-throw to allow caller to handle
		}
	} else {
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
 * SIMPLIFIED: Protect Flow from updated_checkout destruction
 * When WooCommerce updates checkout, it replaces the HTML which can destroy Flow component
 * This handler checks if Flow was destroyed AFTER DOM update and re-initializes only if needed
 */
// Track previous cart total for change detection
let previousCartTotal = typeof cko_flow_vars !== 'undefined' && cko_flow_vars.cart_total ? parseFloat(cko_flow_vars.cart_total) : 0;

// CRITICAL: Attach updated_checkout handler IMMEDIATELY with HIGHEST PRIORITY
// This must run BEFORE WooCommerce attaches its handlers
// Use immediate execution (IIFE) to attach handler as early as possible
(function() {
	if (typeof jQuery !== 'undefined') {
		// Remove any existing handler first
		jQuery(document).off("updated_checkout.cko-terms-prevention");
		
		// Attach handler immediately (before DOM ready) with capture-like behavior
		// Use body instead of document to catch events earlier
		jQuery('body').on("updated_checkout.cko-terms-prevention", function (event) {
	ckoLogger.debug('===== updated_checkout EVENT FIRED =====');
	
	// CRITICAL: Prevent update_checkout if it was triggered by a terms checkbox
	// Check prevention flag first (set by jQuery trigger interception)
	// Also check if the last clicked/changed element was a terms checkbox (backup check)
	const activeElement = document.activeElement;
	const lastClicked = window.ckoTermsCheckboxLastClicked;
	const timeSinceLastClick = Date.now() - window.ckoTermsCheckboxLastClickTime;
	const isTermsTriggered = 
		window.ckoPreventUpdateCheckout ||
		((lastClicked && isTermsCheckbox(lastClicked)) && timeSinceLastClick < 2000) ||
		(activeElement && isTermsCheckbox(activeElement));
	
	if (isTermsTriggered) {
		ckoLogger.debug('🚫🚫🚫 updated_checkout triggered by terms checkbox - BLOCKING page reload', {
			preventionFlag: window.ckoPreventUpdateCheckout,
			lastClickedId: lastClicked ? (lastClicked.id || 'no-id') : 'none',
			activeElementId: activeElement ? (activeElement.id || 'no-id') : 'none',
			timeSinceClick: timeSinceLastClick + 'ms'
		});
		
		// CRITICAL: Stop ALL event propagation immediately
		event.stopImmediatePropagation();
		event.stopPropagation();
		event.preventDefault();
		
		// Also prevent default behavior on the document
		if (event.originalEvent) {
			event.originalEvent.stopImmediatePropagation();
			event.originalEvent.stopPropagation();
			event.originalEvent.preventDefault();
		}
		
		// Clear tracking after a delay (keep flag active longer to catch async triggers)
		setTimeout(function() {
			window.ckoPreventUpdateCheckout = false;
			window.ckoTermsCheckboxLastClicked = null;
		}, 3000);
		
		// Exit early - don't process updated_checkout
		return false;
	}
	
	// Log field values BEFORE DOM update
	const emailBefore = getCheckoutFieldValue("billing_email");
	const addressBefore = getCheckoutFieldValue("billing_address_1");
	const cityBefore = getCheckoutFieldValue("billing_city");
	const countryBefore = getCheckoutFieldValue("billing_country");
	const flowPaymentBefore = document.getElementById("payment_method_wc_checkout_com_flow");
	
	ckoLogger.debug('State BEFORE updated_checkout:', {
		email: emailBefore || 'EMPTY',
		address1: addressBefore || 'EMPTY',
		city: cityBefore || 'EMPTY',
		country: countryBefore || 'EMPTY',
		flowPaymentChecked: flowPaymentBefore?.checked || false,
		flowInitialized: ckoFlowInitialized
	});
	
	// CRITICAL: Skip if we're handling a 3DS return (don't re-initialize Flow)
	// Check both the flag and URL parameters
	if (window.ckoFlow3DSReturn) {
		ckoLogger.threeDS('Skipping updated_checkout handler - 3DS return flag is set');
		return;
	}
	
	// Also check URL parameters as fallback
	const urlParams = new URLSearchParams(window.location.search);
	const paymentId = urlParams.get("cko-payment-id");
	const sessionId = urlParams.get("cko-session-id");
	const paymentSessionId = urlParams.get("cko-payment-session-id");
	
	if (paymentId || sessionId || paymentSessionId) {
		ckoLogger.threeDS('Skipping updated_checkout handler - 3DS return detected in URL');
		window.ckoFlow3DSReturn = true;
		return;
	}
	
	// Check if cart total changed
	if (typeof cko_flow_vars !== 'undefined' && cko_flow_vars.cart_total) {
		const currentCartTotal = parseFloat(cko_flow_vars.cart_total) || 0;
		
		// Check if cart total changed significantly (> 0.01 to avoid floating point issues)
		if (Math.abs(currentCartTotal - previousCartTotal) > 0.01) {
			ckoLogger.debug('Cart total changed - will reload Flow', {
				previous: previousCartTotal,
				current: currentCartTotal
			});
			
			// Update stored total
			previousCartTotal = currentCartTotal;
			
			// Reload Flow if initialized (after a small delay to let checkout update complete)
			setTimeout(() => {
				if (ckoFlowInitialized && ckoFlow.flowComponent && canInitializeFlow()) {
					reloadFlowComponent();
				}
			}, 300);
		}
	}
	
	ckoLogger.debug('updated_checkout event fired');
	ckoLogger.debug('Flow state BEFORE updated_checkout:', {
		flowInitialized: ckoFlowInitialized,
		flowComponentExists: !!ckoFlow.flowComponent,
		flowComponentRootExists: !!document.querySelector('[data-testid="checkout-web-component-root"]'),
		flowContainerExists: !!document.getElementById("flow-container"),
		flowPaymentChecked: document.getElementById("payment_method_wc_checkout_com_flow")?.checked || false
	});
	
	// EVENT-DRIVEN: Flow remounting is now handled by cko:flow-container-ready event listener
	// No need for setTimeout delays - flow-container.js will emit event when container is ready
	});
	}
})(); // Close IIFE - handler attached immediately

// EVENT-DRIVEN DESIGN: Listen for container-ready events from flow-container.js
// This eliminates timing race conditions - Flow remounts immediately when container is ready
document.addEventListener('cko:flow-container-ready', function(event) {
	// CRITICAL: Check for 3DS return
	if (window.ckoFlow3DSReturn) {
		ckoLogger.threeDS('Skipping container-ready handler - 3DS return in progress');
		return;
	}
	
	// Check URL parameters as fallback
	const urlParams = new URLSearchParams(window.location.search);
	if (urlParams.get("cko-payment-id") || urlParams.get("cko-session-id") || urlParams.get("cko-payment-session-id")) {
		ckoLogger.threeDS('Skipping container-ready handler - 3DS return detected in URL');
		window.ckoFlow3DSReturn = true;
		return;
	}
	
	const flowContainer = event.detail?.container || document.getElementById("flow-container");
	const flowPayment = document.getElementById("payment_method_wc_checkout_com_flow");
	const flowComponentRoot = document.querySelector('[data-testid="checkout-web-component-root"]');
	
	// Check if Flow component is actually mounted
	const flowComponentActuallyMounted = flowComponentRoot && flowComponentRoot.isConnected;
	const flowWasInitializedBefore = ckoFlowInitialized && ckoFlow.flowComponent && !flowComponentActuallyMounted;
	
	ckoLogger.debug('🔔 Container-ready event received - checking if Flow needs remounting', {
		flowPaymentChecked: flowPayment?.checked || false,
		flowContainerExists: !!flowContainer,
		flowComponentRootExists: !!flowComponentRoot,
		flowComponentMounted: flowComponentActuallyMounted,
		flowInitialized: ckoFlowInitialized,
		flowComponentExists: !!ckoFlow.flowComponent,
		flowWasInitializedBefore: flowWasInitializedBefore
	});
	
	// Only remount if:
	// 1. Flow payment method is selected
	// 2. Container exists
	// 3. Flow component is NOT mounted (was destroyed by updated_checkout)
	// 4. Flow is NOT currently initializing
	if (flowPayment && flowPayment.checked && flowContainer) {
		if (!flowComponentActuallyMounted || flowWasInitializedBefore) {
			// Component was destroyed by updated_checkout
			if (ckoFlowInitializing) {
				ckoLogger.debug('Flow component not mounted but initialization already in progress, skipping');
				return;
			}
			
			ckoLogger.debug('🔄 Flow component needs remounting - container is ready, re-initializing...');
			
			// Reset flag so Flow can be re-initialized
			ckoFlowInitialized = false;
			if (ckoFlow.flowComponent) {
				// Component exists but was unmounted - destroy it so we can create a new one
				try {
					ckoFlow.flowComponent.destroy();
				} catch (e) {
					ckoLogger.debug('Error destroying Flow component:', e);
				}
				ckoFlow.flowComponent = null;
			}
			// Re-initialize
			initializeFlowIfNeeded();
		} else {
			ckoLogger.debug('✅ Flow component still mounted, no remounting needed');
		}
	}
});

/**
 * Global handler for saved card selection (works in both new_payment_first and saved_cards_first modes)
 * When a saved card is selected, show the Place Order button
 * When new payment method is selected, ensure Flow container is visible and interactive
 */
jQuery(document).on(
	"change",
	'input[name="wc-wc_checkout_com_flow-payment-token"]',
	function () {
		const selectedToken = jQuery('input[name="wc-wc_checkout_com_flow-payment-token"]:checked');
		const flowContainer = document.getElementById("flow-container");
		const selectedId = selectedToken.attr('id');
		
		// If "Use new payment method" is selected
		if (selectedId === 'wc-wc_checkout_com_flow-payment-token-new') {
			ckoLogger.debug('New payment method selected - showing Flow container');
			
			// Show Flow container
			if (flowContainer) {
				flowContainer.style.display = "block";
			}
			
			// Mark as ready when Flow component is valid
			if (ckoFlow.flowComponent) {
				document.body.classList.add("flow-ready");
			} else {
				// Flow not initialized yet, wait for it
				document.body.classList.remove("flow-ready");
			}
			
			// Scroll to Flow container
			setTimeout(() => {
				if (flowContainer) {
					flowContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
				}
			}, 100);
		}
		// If a saved card is selected
		else if (selectedToken.length > 0) {
			ckoLogger.debug('Saved card selected - making Place Order button visible');
			document.body.classList.add("flow-ready");
			
			// Keep Flow container visible
			if (flowContainer) {
				flowContainer.style.display = "block";
			}
		}
	}
);

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
			window.flowUserInteracted = false;
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
		window.ckoFlow3DSReturn = true;
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
			window.flowUserInteracted = false;
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
		console.log('[FLOW] Activating Flow payment method');
		
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
		window.flowUserInteracted = false; // Reset to allow user to interact with Flow
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
			console.log('[FLOW] User clicked on Flow field - auto-activating Flow');
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
	 * Create order before payment processing via AJAX.
	 * This ensures order exists before webhook arrives, preventing race conditions.
	 * 
	 * @returns {Promise<number|null>} Order ID if successful, null if failed
	 */
	async function createOrderBeforePayment() {
		// CRITICAL: Prevent multiple simultaneous order creation calls (race condition protection)
		if (ckoOrderCreationInProgress) {
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
			if (ckoOrderCreationInProgress) {
				ckoLogger.error('[CREATE ORDER] ❌ Order creation still in progress after wait - aborting duplicate call');
				return null;
			}
		}
		
		// Set lock flag to prevent multiple simultaneous calls
		ckoOrderCreationInProgress = true;
		
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
				ckoOrderCreationInProgress = false;
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
						ckoOrderCreationInProgress = false;
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
				ckoOrderCreationInProgress = false;
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
						ckoOrderCreationInProgress = false;
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
				ckoOrderCreationInProgress = false;
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
				action: "cko_flow_create_order",
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
			
			const response = await jQuery.ajax({
				url: cko_flow_vars.ajax_url,
				type: "POST",
				data: ajaxData
			}).fail(function(xhr, status, error) {
				ckoLogger.error('[CREATE ORDER] ❌ AJAX Request Failed');
				ckoLogger.error('[CREATE ORDER] Status:', status);
				ckoLogger.error('[CREATE ORDER] Error:', error);
				ckoLogger.error('[CREATE ORDER] Response Text:', xhr.responseText);
				ckoLogger.error('[CREATE ORDER] Status Code:', xhr.status);
				ckoLogger.error('[CREATE ORDER] Request Data:', ajaxData);
			});
			
			ckoLogger.debug('[CREATE ORDER] ========== PROCESSING AJAX RESPONSE ==========');
			ckoLogger.debug('[CREATE ORDER] Response received:', response);
			ckoLogger.debug('[CREATE ORDER] Response.success:', response?.success);
			ckoLogger.debug('[CREATE ORDER] Response.data:', response?.data);
			ckoLogger.debug('[CREATE ORDER] Response.data.order_id:', response?.data?.order_id);
			
			if (response && response.success && response.data && response.data.order_id) {
				const orderId = response.data.order_id;
				ckoLogger.debug('[CREATE ORDER] ========== ORDER CREATED SUCCESSFULLY ==========');
				ckoLogger.debug('[CREATE ORDER] ✅✅✅ Order created successfully - Order ID: ' + orderId + ' ✅✅✅');
				ckoLogger.debug('[CREATE ORDER] Order ID type:', typeof orderId);
				ckoLogger.debug('[CREATE ORDER] Order ID value:', orderId);
				
				// Store order ID in form for process_payment()
				if (!orderIdField.length) {
					form.append('<input type="hidden" name="order_id" value="' + orderId + '">');
				} else {
					orderIdField.val(orderId);
				}
				
				// Store in session for fallback
				sessionStorage.setItem('cko_flow_order_id', orderId);
				
				// Clear lock flag on success
				ckoOrderCreationInProgress = false;
				
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
				ckoLogger.error('[CREATE ORDER] Response received:', response);
				ckoLogger.error('[CREATE ORDER] Response type:', typeof response);
				ckoLogger.error('[CREATE ORDER] Response.success:', response?.success);
				ckoLogger.error('[CREATE ORDER] Response.data:', response?.data);
				
				if (response && response.data && response.data.message) {
					ckoLogger.error('[CREATE ORDER] ❌❌❌ VALIDATION FAILED - ORDER NOT CREATED ❌❌❌');
					ckoLogger.error('[CREATE ORDER] Error message:', response.data.message);
					ckoLogger.error('[CREATE ORDER] This is a validation error - order was NOT created');
					showError(response.data.message);
				} else {
					ckoLogger.error('[CREATE ORDER] ❌❌❌ FAILED TO CREATE ORDER ❌❌❌');
					ckoLogger.error('[CREATE ORDER] Full response:', JSON.stringify(response, null, 2));
					ckoLogger.error('[CREATE ORDER] Order creation failed for unknown reason');
					showError('Failed to create order. Please check your form and try again.');
				}
				
				// Clear lock flag on failure
				ckoOrderCreationInProgress = false;
				
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
			if (ckoOrderCreationInProgress) {
				ckoLogger.warn('[CREATE ORDER] ⚠️ Lock flag still set in finally block - clearing it');
				ckoOrderCreationInProgress = false;
				
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
			if (ckoOrderCreationInProgress) {
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
								console.log('[FLOW] Order-pay: Persisted save card checkbox for new card payment');
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
				
				// Create order (which includes server-side validation)
				// OPTIMIZATION: Combined validation + order creation into single AJAX call
				// This reduces latency by ~100-200ms compared to separate validation call
				ckoLogger.debug('[CREATE ORDER] Creating order with built-in validation...');
				(async function() {
					const orderId = await createOrderBeforePayment();
					if (!orderId) {
						// Order creation failed - error already shown by createOrderBeforePayment()
						// This could be due to validation errors or other issues
						ckoLogger.error('[CREATE ORDER] Failed to create order - cannot proceed with payment');
						return;
					}
					
					ckoLogger.debug('[CREATE ORDER] ✅ Order created successfully - Order ID: ' + orderId);
				
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
									ckoLogger.error('Flow component became invalid after order creation - cannot submit');
									showError('Payment form is not valid. Please check your payment details and try again.');
									// Note: Order was created but payment cannot proceed
									// The order will remain in "pending" status
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
						ckoLogger.error('[CREATE ORDER] Flow component not found - cannot process payment');
						showError('Payment component not loaded. Please refresh the page and try again.');
					}

					// Place order for saved card.
					if (!ckoFlow.flowComponent) {
						form.submit();
					}
				})(); // Close async IIFE for order creation
			} else {
				// console.log('[CURRENT VERSION] Flow payment method not selected or not found');
			}
		}
	});
});

// Removed complex 3DS redirect handling - keeping it simple like the working version

let virtual = false;
/**
 * Handle checkout flow-container rendering on various field changes.
 * Attaches debounced event listeners to checkout inputs to update flow state and cart info dynamically.
 */
jQuery(function ($) {

	/**
	 * Main handler function triggered on input/change events.
	 * It performs field checks and updates the checkout flow and cart info accordingly.
	 *
	 * @param {Event} event - The input or change event.
	 */
	const handleTyping = (event) => {

		let isShippingField = false;

		// Check if the changed field belongs to shipping info.
		if (event) {
			const $target = jQuery(event.target);
			isShippingField = $target.is('[id^="shipping"]');
		}

		// If the field is a shipping-related field, trigger WooCommerce checkout update and exit early.
		if (isShippingField) {
			console.log("Triggered by a shipping field, skipping...");
			$("body").trigger("update_checkout");
			return;
		}

		// Check if this is a critical field that requires Flow reload
		const criticalFields = [
			'billing_email',
			'billing_country',
			'billing_address_1',
			'billing_city',
			'billing_postcode',
			'billing_state'
		];
		
		const fieldName = event.target.name || event.target.id || '';
		const fieldId = event.target.id || '';
		const isCriticalField = criticalFields.some(field => 
			fieldName.includes(field.replace('billing_', '')) || 
			fieldId.includes(field.replace('billing_', ''))
		);
		
		// Store previous values to detect changes
		if (isCriticalField && ckoFlowInitialized) {
			const currentValue = event.target.value || '';
			const fieldKey = fieldId || fieldName;
			const previousValue = window.ckoFlowFieldValues?.[fieldKey] || '';
			
			// If value actually changed (not just typing)
			if (currentValue !== previousValue) {
				// Update stored value
				if (!window.ckoFlowFieldValues) {
					window.ckoFlowFieldValues = {};
				}
				window.ckoFlowFieldValues[fieldKey] = currentValue;
				
				// Debounce the reload check
				debouncedCheckFlowReload(fieldKey, currentValue);
			}
		}
		
		// Only proceed if all required fields are filled.
		if (requiredFieldsFilled()) {
			$("body").trigger("update_checkout");

			// If the event is from checking 'ship to different address' or 'create account', return early.
			if (
				jQuery(event.target).is(
					"#ship-to-different-address-checkbox, #createaccount"
				) &&
				jQuery(event.target).is(":checked")
			) {
				console.log("User just checked the checkbox. Returning early...");
				return;
			}

			var targetName = event.target.name || "";

			// If the event is not from billing fields or key checkboxes, exit early.
			if (
				!targetName.startsWith("billing") &&
				!jQuery(event.target).is(
					"#ship-to-different-address-checkbox, #terms, #createaccount, #coupon_code"
				)
			) {
				let cartData = $("#cart-info").data("cart");
				if ( !cartData || !cartData["contains_virtual_product"] ) {
					console.log(
						"Neither billing nor the shipping checkbox. Returning early..."
					);
					return;
				}
				console.log(
					"Virtual Product found. Triggering FLOW..."
				);
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
		if (!ckoFlowInitialized) {
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

	// Attach debounced handler to key billing fields.
	$(
		"#billing_first_name, #billing_last_name, #billing_email, #billing_phone"
	).on("input", function (e) {
		debouncedTyping(e);
	});

	// Attach to all other inputs/selects, excluding the key billing fields above.
	// EXCLUDE CHECKBOXES - they don't need to trigger Flow updates and cause form reload issues
	$(
		"input:not(#billing_first_name, #billing_last_name, #billing_email, #billing_phone):not([type='checkbox']), select"
	).on("input change", function (e) {
		debouncedTyping(e);
	});

	// Attach handler to all input/selects, but ignore payment method fields.
	// EXCLUDE CHECKBOXES - they don't need to trigger Flow updates and cause form reload issues
	$(document).on("input change", "input:not([type='checkbox']), select", function (e) {
		if ($(this).closest(".wc_payment_method").length === 0) {
			
			debouncedTyping(e);

		}

		let cartData = $("#cart-info").data("cart");
		if ( !virtual && cartData && cartData["contains_virtual_product"] && $('#ship-to-different-address-checkbox').length === 0 ) {
			debouncedTyping(e);
			virtual = true;
		}
	});
	
	// Handle checkboxes that legitimately need to trigger update_checkout
	// These checkboxes change the checkout form structure (shipping fields, account creation)
	// Note: Terms checkboxes are handled separately above and do NOT trigger update_checkout
	jQuery(document).on('change', '#ship-to-different-address-checkbox, #createaccount', function() {
		ckoLogger.debug('Checkbox change detected - triggering update_checkout', {
			checkboxId: this.id,
			checked: this.checked
		});
		// These checkboxes legitimately need to trigger update_checkout
		// They show/hide form fields that require checkout refresh
		jQuery('body').trigger('update_checkout');
	});
	
	// Watch country field specifically for Flow reload
	jQuery('#billing_country').on('change.flow-reload', function() {
		ckoLogger.debug('Billing country changed - will reload Flow after checkout update');
		
		// Wait for WooCommerce to update fields
		jQuery(document).one('updated_checkout', function() {
			// Small delay to ensure fields are updated
			setTimeout(() => {
				if (ckoFlowInitialized && ckoFlow.flowComponent) {
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
function requiredFieldsFilled() {
	// Select all required field indicators within WooCommerce checkout labels.
	const requiredLabels = document.querySelectorAll(
		".woocommerce-checkout label .required"
	);

	const fieldIds = [];

	requiredLabels.forEach((label) => {
		const fieldId = label.closest("label").getAttribute("for");
		if (fieldId) {
			fieldIds.push(fieldId);
		}
	});

	// Filter out fieldIds that start with "shipping".
	let filteredFieldIds = fieldIds.filter((id) => !id.startsWith("shipping"));

	// CRITICAL: Filter out payment gateway fields from OTHER payment methods
	// When Checkout.com is selected, PayPal/Stripe fields are still in DOM but empty
	// We should only check fields relevant to Checkout.com or general checkout fields
	// Strategy: Check if field is inside another payment gateway's container OR has gateway prefix
	filteredFieldIds = filteredFieldIds.filter((id) => {
		const field = document.getElementById(id);
		if (!field) {
			return false; // Field doesn't exist
		}
		
		// First check: If field ID starts with known payment gateway prefixes, exclude it
		// This catches fields like ppcp-credit-card-gateway-card-number that might not be in containers
		const paymentGatewayPrefixes = ['ppcp-', 'stripe-', 'wc-stripe-', 'square-', 'authorize-'];
		const isOtherGatewayField = paymentGatewayPrefixes.some(prefix => id.startsWith(prefix));
		if (isOtherGatewayField) {
			return false;
		}
		
		// Second check: Check if field is inside a payment gateway container
		// WooCommerce payment gateways are wrapped in: .payment_method_{gateway_id}
		const paymentMethodContainer = field.closest('.payment_method');
		
		if (paymentMethodContainer) {
			// Field is inside a payment gateway container
			const containerClass = paymentMethodContainer.className;
			
			// Check if it's Checkout.com Flow container
			if (containerClass.includes('payment_method_wc_checkout_com_flow')) {
				// This is a Checkout.com field - include it
				return true;
			} else {
				// Field is inside another payment gateway container (PayPal, Stripe, etc.)
				// Exclude it - we only want Checkout.com fields or general checkout fields
				return false;
			}
		}
		
		// Field is NOT inside any payment gateway container and doesn't have gateway prefix
		// This means it's a general checkout field (billing_email, billing_address, etc.)
		// Include it - these are always relevant
		return true;
	});

	// Check if login form is hidden.
	const loginForm = document.querySelector(".woocommerce-form-login");
	const loginFormHidden = loginForm && loginForm.style.display === "none";

	if (loginFormHidden) {
		// Remove username and password fields if form is hidden.
		filteredFieldIds = filteredFieldIds.filter(
			(id) => id !== "username" && id !== "password"
		);
	}

	// CRITICAL: Filter out account creation fields unless account creation is enabled
	// Account fields (account_username, account_password) should only be required if:
	// 1. The "Create an account?" checkbox exists and is checked
	// 2. The account fields are visible
	const createAccountCheckbox = document.querySelector('#createaccount');
	const isCreatingAccount = createAccountCheckbox && createAccountCheckbox.checked;
	
	if (!isCreatingAccount) {
		// Remove account creation fields if account creation is not enabled
		filteredFieldIds = filteredFieldIds.filter(
			(id) => id !== "account_username" && id !== "account_password"
		);
		if (window.flowDebugLogging) {
			ckoLogger.debug('requiredFieldsFilled: Account creation not enabled - filtered out account_username and account_password');
		}
	} else {
		// Account creation is enabled - check if fields are visible
		const accountUsernameField = document.getElementById('account_username');
		const accountPasswordField = document.getElementById('account_password');
		const accountFieldsVisible = accountUsernameField && accountPasswordField && 
			accountUsernameField.offsetParent !== null && 
			accountPasswordField.offsetParent !== null;
		
		if (!accountFieldsVisible) {
			// Account fields exist but are hidden - remove them
			filteredFieldIds = filteredFieldIds.filter(
				(id) => id !== "account_username" && id !== "account_password"
			);
			if (window.flowDebugLogging) {
				ckoLogger.debug('requiredFieldsFilled: Account creation enabled but fields are hidden - filtered out account fields');
			}
		} else {
			if (window.flowDebugLogging) {
				ckoLogger.debug('requiredFieldsFilled: Account creation enabled and fields visible - including account_username and account_password');
			}
		}
	}

	// FALLBACK: If no fields found via .required selector, check common required fields directly
	// This handles cases where:
	// 1. Site uses Blocks checkout (different structure)
	// 2. Theme doesn't use .required class
	// 3. Fields are required but not marked with .required
	if (filteredFieldIds.length === 0) {
		if (window.flowDebugLogging) {
			ckoLogger.debug('requiredFieldsFilled: No fields found via .required selector, using fallback');
		}
		
		// Common required billing fields
		const commonRequiredFields = [
			'billing_email',
			'billing_first_name',
			'billing_last_name',
			'billing_address_1',
			'billing_city',
			'billing_country'
		];
		
		// Check which of these fields exist and are required
		filteredFieldIds = commonRequiredFields.filter((id) => {
			const field = document.getElementById(id);
			if (!field) {
				return false;
			}
			
			// Check if field has required attribute or is inside a required label
			const isRequired = field.hasAttribute('required') || 
			                  field.hasAttribute('aria-required') ||
			                  field.closest('label')?.querySelector('.required') !== null ||
			                  field.closest('.form-row')?.classList.contains('validate-required');
			
			// Also check if field is visible (not hidden)
			const isVisible = field.offsetParent !== null && 
			                 field.style.display !== 'none' &&
			                 !field.hasAttribute('disabled');
			
			return isRequired && isVisible;
		});
		
		if (window.flowDebugLogging) {
			ckoLogger.debug('requiredFieldsFilled: Fallback found fields:', filteredFieldIds);
		}
	}

	// DEBUG: Log field validation details
	if (window.flowDebugLogging) {
		ckoLogger.debug('requiredFieldsFilled: Checking ' + filteredFieldIds.length + ' fields:', filteredFieldIds.join(', '));
	}

	// Check that each field is present and not empty.
	const fieldResults = {};
	const failedFields = [];
	const result = filteredFieldIds.every((id) => {
		const field = document.getElementById(id);
		const fieldExists = !!field;
		const fieldValue = field?.value || '';
		const fieldValueTrimmed = fieldValue.trim();
		const isEmpty = fieldValueTrimmed === "";
		const isValid = fieldExists && !isEmpty;
		
		// Store result for debugging
		if (window.flowDebugLogging) {
			fieldResults[id] = {
				exists: fieldExists,
				value: fieldValueTrimmed || '(empty)',
				isEmpty: isEmpty,
				isValid: isValid
			};
			
			if (!isValid) {
				failedFields.push(id + ' (' + (fieldExists ? (isEmpty ? 'empty' : 'invalid') : 'not found') + ')');
			}
		}
		
		return isValid;
	});

	// DEBUG: Log field validation results with expanded details
	if (window.flowDebugLogging) {
		if (failedFields.length > 0) {
			ckoLogger.debug('requiredFieldsFilled: ❌ FAILED fields: ' + failedFields.join(', '));
		}
		// Log each field's status individually for better visibility
		Object.keys(fieldResults).forEach(id => {
			const result = fieldResults[id];
			if (!result.isValid) {
				ckoLogger.debug('requiredFieldsFilled: Field "' + id + '" - exists: ' + result.exists + ', value: "' + result.value + '", isEmpty: ' + result.isEmpty);
			}
		});
		ckoLogger.debug('requiredFieldsFilled: Final result: ' + (result ? '✅ PASSED' : '❌ FAILED') + ' (' + filteredFieldIds.length + ' fields checked, ' + failedFields.length + ' failed)');
	}

	return result;
}

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

			// If the response indicates success, trigger the onSuccess callback.
			if (response.success) {
				onSuccess(response);
			} else {
				
				// Show an error message and trigger the onError callback if provided.
				showError(response.data.message);
				if (onError) onError(response);
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
                console.log('[FLOW LABEL] Label shown - saved cards present');
            } else {
                console.log('[FLOW LABEL] Label shown - no saved cards (default label)');
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
		console.log('here');
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
