/**
 * Flow Initialization Module
 * 
 * Contains helper functions for Flow initialization, extracted from loadFlow()
 * to improve code organization and maintainability.
 * 
 * This module must be loaded BEFORE payment-session.js
 * Dependencies: jQuery, flow-logger.js, flow-validation.js, flow-state.js
 * 
 * @module FlowInitialization
 */

(function() {
	'use strict';
	
	/**
	 * Flow Initialization helpers namespace
	 * Exposes initialization helper functions for use in payment-session.js
	 */
	window.FlowInitialization = {
		/**
		 * Validates prerequisites before initializing Flow
		 * @returns {Object} Validation result with isValid flag and reason if invalid
		 */
		validatePrerequisites: function() {
			// Check for 3DS return
			if (window.FlowState && window.FlowState.get('is3DSReturn')) {
				if (typeof window.ckoLogger !== 'undefined') {
					window.ckoLogger.threeDS('validatePrerequisites: 3DS return in progress, aborting Flow initialization');
				}
				return { isValid: false, reason: '3DS_RETURN' };
			}
			
			// Check if cko_flow_vars is available
			if (typeof cko_flow_vars === 'undefined') {
				if (typeof window.ckoLogger !== 'undefined') {
					window.ckoLogger.error('validatePrerequisites: cko_flow_vars is not defined. Flow cannot be initialized.');
				}
				return { isValid: false, reason: 'MISSING_VARS' };
			}
			
			// Validate required fields
			if (typeof window.requiredFieldsFilledAndValid === 'function') {
				const fieldsValid = window.requiredFieldsFilledAndValid();
				if (!fieldsValid) {
					if (typeof window.ckoLogger !== 'undefined') {
						window.ckoLogger.debug('validatePrerequisites: Required fields not filled');
					}
					return { isValid: false, reason: 'FIELDS_NOT_FILLED' };
				}
			}
			
			return { isValid: true };
		},
		
		/**
		 * Collects checkout data from cart info and form fields
		 * @returns {Object} Checkout data object
		 */
		collectCheckoutData: function() {
			let cartInfo = jQuery("#cart-info").data("cart");
			
			if (!cartInfo || jQuery.isEmptyObject(cartInfo)) {
				cartInfo = jQuery("#order-pay-info").data("order-pay");
			}
			
			// Extract basic information
			let amount = cartInfo["order_amount"];
			let currency = cartInfo["purchase_currency"];
			let reference = "WOO" + (cko_flow_vars.ref_session || 'default');
			
			// Extract billing address
			const billingAddress = cartInfo["billing_address"] || {};
			let email = billingAddress["email"] || 
				(document.getElementById("billing_email") ? document.getElementById("billing_email").value : '');
			let family_name = billingAddress["family_name"] || 
				(document.getElementById("billing_last_name") ? document.getElementById("billing_last_name").value : '');
			let given_name = billingAddress["given_name"] || 
				(document.getElementById("billing_first_name") ? document.getElementById("billing_first_name").value : '');
			
			// Validate email
			const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
			if (!email || typeof email !== 'string' || !email.trim() || !emailRegex.test(email.trim())) {
				if (typeof window.ckoLogger !== 'undefined') {
					window.ckoLogger.error('collectCheckoutData: Invalid email', { 
						email: email,
						emailType: typeof email
					});
				}
				return { error: 'INVALID_EMAIL', email: email };
			}
			
			email = email.trim();
			let phone = billingAddress["phone"] || 
				(document.getElementById("billing_phone") ? document.getElementById("billing_phone").value : '');
			
			// Extract address fields
			let address1 = billingAddress["street_address"] || '';
			let address2 = billingAddress["street_address2"] || '';
			let city = billingAddress["city"] || '';
			let zip = billingAddress["postal_code"] || '';
			let country = billingAddress["country"] || '';
			
			// Initialize shipping address variables (default to billing address)
			let shippingAddress1 = address1;
			let shippingAddress2 = address2;
			let shippingCity = city;
			let shippingZip = zip;
			let shippingCountry = country;
			
			// Check for shipping address
			let shippingElement = document.getElementById("ship-to-different-address-checkbox");
			if (shippingElement && shippingElement.checked) {
				const shippingAddress = cartInfo["shipping_address"] || {};
				shippingAddress1 = shippingAddress["street_address"] || address1;
				shippingAddress2 = shippingAddress["street_address2"] || address2;
				shippingCity = shippingAddress["city"] || city;
				shippingZip = shippingAddress["postal_code"] || zip;
				shippingCountry = shippingAddress["country"] || country;
			}
			
			// Extract order lines
			let orders = cartInfo["order_lines"];
			
			// Add missing shipping to order_lines if needed
			if (orders && Array.isArray(orders) && cartInfo["order_amount"]) {
				const productsTotal = orders.reduce((sum, item) => {
					return sum + (parseInt(item.total_amount) || 0);
				}, 0);
				const orderAmountCents = parseInt(cartInfo["order_amount"]) || 0;
				const shippingDifference = orderAmountCents - productsTotal;
				
				if (shippingDifference > 0) {
					const hasShipping = orders.some(item => 
						item.type === 'shipping_fee' || 
						item.reference === 'shipping' || 
						(item.name && item.name.toLowerCase().includes('shipping'))
					);
					
					if (!hasShipping) {
						let shippingMethodName = 'Shipping';
						const shippingMethodElement = document.querySelector('.woocommerce-shipping-methods .shipping-method input:checked + label, .woocommerce-shipping-methods label');
						if (shippingMethodElement) {
							shippingMethodName = shippingMethodElement.textContent.trim() || 'Shipping';
						}
						
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
						
						if (typeof window.ckoLogger !== 'undefined') {
							window.ckoLogger.debug('[PAYMENT SESSION] [SHIPPING DEBUG] Added missing shipping to order_lines', {
								amount: shippingDifference,
								name: shippingMethodName
							});
						}
					}
				}
			}
			
			// Build description
			let products = orders ? orders.map(line => line.name).join(', ') : '';
			let description = 'Payment from ' + cko_flow_vars.site_url + ' for [ ' + products + ' ]';
			if (description.length > 100) {
				description = description.substring(0, 97) + '...';
			}
			
			// Extract order ID
			let orderId = cartInfo["order_id"];
			if (!orderId && window.location.pathname.includes('/order-pay/')) {
				const pathMatch = window.location.pathname.match(/\/order-pay\/(\d+)\//);
				orderId = pathMatch ? pathMatch[1] : null;
			}
			
			// Determine payment type
			let payment_type = cko_flow_vars.regular_payment_type;
			let metadata = { udf5: cko_flow_vars.udf5 };
			
			// Check for subscription
			let containsSubscriptionProduct = cartInfo["contains_subscription_product"];
			let cartInfoPaymentType = cartInfo["payment_type"];
			let isSubscription = false;
			
			if (cartInfoPaymentType === cko_flow_vars.recurring_payment_type) {
				payment_type = cko_flow_vars.recurring_payment_type;
				isSubscription = true;
			} else if (containsSubscriptionProduct) {
				isSubscription = orders ? orders.some(order => order.is_subscription === true) : false;
				if (isSubscription) {
					payment_type = cko_flow_vars.recurring_payment_type;
				}
			}
			
			if (orderId) {
				metadata = {
					udf5: cko_flow_vars.udf5,
					order_id: orderId
				};
				
				if (!isSubscription) {
					payment_type = cartInfo["payment_type"];
				}
			}
			
			return {
				amount: amount,
				currency: currency,
				reference: reference,
				email: email,
				family_name: family_name,
				given_name: given_name,
				phone: phone,
				address1: address1,
				address2: address2,
				city: city,
				zip: zip,
				country: country,
				shippingAddress1: shippingAddress1,
				shippingAddress2: shippingAddress2,
				shippingCity: shippingCity,
				shippingZip: shippingZip,
				shippingCountry: shippingCountry,
				orders: orders,
				description: description,
				orderId: orderId,
				payment_type: payment_type,
				metadata: metadata,
				isSubscription: isSubscription
			};
		},
		
		/**
		 * Checks if Flow can be initialized (guards and prerequisites)
		 * @returns {Object} Result with canInitialize flag and reason if not
		 */
		canInitialize: function() {
			// Check if already initializing
			if (window.FlowState && window.FlowState.get('initializing')) {
				return { canInitialize: false, reason: 'ALREADY_INITIALIZING' };
			}
			
			// Check for 3DS return
			if (window.FlowState && window.FlowState.get('is3DSReturn')) {
				return { canInitialize: false, reason: '3DS_RETURN' };
			}
			
			// Check URL parameters for 3DS return
			const urlParams = new URLSearchParams(window.location.search);
			const paymentId = urlParams.get("cko-payment-id");
			const sessionId = urlParams.get("cko-session-id");
			const paymentSessionId = urlParams.get("cko-payment-session-id");
			
			if (paymentId || sessionId || paymentSessionId) {
				if (window.FlowState) {
					window.FlowState.set('is3DSReturn', true);
				}
				return { canInitialize: false, reason: '3DS_RETURN_URL' };
			}
			
			// Check if payment method is selected
			const flowPayment = document.getElementById("payment_method_wc_checkout_com_flow");
			if (!flowPayment || !flowPayment.checked) {
				return { canInitialize: false, reason: 'PAYMENT_NOT_SELECTED' };
			}
			
			// Check if container exists
			const flowContainer = document.getElementById("flow-container");
			if (!flowContainer) {
				return { canInitialize: false, reason: 'CONTAINER_NOT_FOUND' };
			}
			
			// Check if already initialized
			if (window.FlowState && window.FlowState.get('initialized') && window.ckoFlow && window.ckoFlow.flowComponent) {
				const flowComponentRoot = document.querySelector('[data-testid="checkout-web-component-root"]');
				if (flowComponentRoot) {
					return { canInitialize: false, reason: 'ALREADY_INITIALIZED' };
				}
			}
			
			return { canInitialize: true };
		},
		
		/**
		 * Gets Flow DOM elements
		 * @returns {Object} Object with flowPayment, flowContainer, flowComponentRoot
		 */
		getFlowElements: function() {
			return {
				flowPayment: document.getElementById("payment_method_wc_checkout_com_flow"),
				flowContainer: document.getElementById("flow-container"),
				flowComponentRoot: document.querySelector('[data-testid="checkout-web-component-root"]')
			};
		}
	};
	
	// Log that module loaded (only in debug mode)
	if (typeof window.ckoLogger !== 'undefined' && window.ckoLogger.debugEnabled) {
		window.ckoLogger.debug('[FLOW INITIALIZATION] Initialization helper module loaded');
	}
})();

