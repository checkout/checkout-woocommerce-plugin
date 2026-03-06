/**
 * Flow Checkout Data Normalizer
 *
 * Centralizes checkout/cart data collection without validation.
 * Reads DOM values first, then falls back to cartInfo/order-pay data.
 *
 * Dependencies: jQuery, flow-logger.js
 *
 * @module FlowCheckoutData
 */
(function() {
	'use strict';
	
	window.FlowCheckoutData = {
		/**
		 * Collect normalized checkout data (no validation).
		 * @returns {Object} Normalized checkout data for session creation
		 */
		getCheckoutData: function() {
			let cartInfo = jQuery("#cart-info").data("cart");
			
			if (!cartInfo || jQuery.isEmptyObject(cartInfo)) {
				cartInfo = jQuery("#order-pay-info").data("order-pay");
			}
			
			// Extract basic information
			// CRITICAL: Read amount from DOM first (most up-to-date after coupon changes)
			// The #cart-info data attribute may have stale amount if coupon was applied
			let amount = this.getAmountFromDOM() || cartInfo["order_amount"];
			let currency = cartInfo["purchase_currency"];
			let reference = "WOO" + (cko_flow_vars.ref_session || 'default');
			
			// Read from DOM fields first (fresh data), then fall back to cartInfo
			const billingAddress = cartInfo["billing_address"] || {};
			let email = (document.getElementById("billing_email") ? document.getElementById("billing_email").value : '') || 
				billingAddress["email"];
			let family_name = (document.getElementById("billing_last_name") ? document.getElementById("billing_last_name").value : '') || 
				billingAddress["family_name"];
			let given_name = (document.getElementById("billing_first_name") ? document.getElementById("billing_first_name").value : '') || 
				billingAddress["given_name"];
			let phone = (document.getElementById("billing_phone") ? document.getElementById("billing_phone").value : '') || 
				billingAddress["phone"];
			
			// Extract address fields - read from DOM first for fresh data
			let address1 = (document.getElementById("billing_address_1") ? document.getElementById("billing_address_1").value : '') || 
				billingAddress["street_address"] || '';
			let address2 = (document.getElementById("billing_address_2") ? document.getElementById("billing_address_2").value : '') || 
				billingAddress["street_address2"] || '';
			let city = (document.getElementById("billing_city") ? document.getElementById("billing_city").value : '') || 
				billingAddress["city"] || '';
			let state = (document.getElementById("billing_state") ? document.getElementById("billing_state").value : '') || 
				billingAddress["state"] || '';
			let zip = (document.getElementById("billing_postcode") ? document.getElementById("billing_postcode").value : '') || 
				billingAddress["postal_code"] || '';
			let country = (document.getElementById("billing_country") ? document.getElementById("billing_country").value : '') || 
				billingAddress["country"] || '';
			
			// Debug: Log data sources to verify fresh data
			if (typeof window.ckoLogger !== 'undefined' && window.ckoLogger.debugEnabled) {
				window.ckoLogger.debug('[FlowCheckoutData] Reading from DOM (fresh) vs cartInfo (cached):', {
					'email_dom': document.getElementById("billing_email") ? document.getElementById("billing_email").value : 'N/A',
					'email_cartInfo': billingAddress["email"] || 'N/A',
					'email_final': email,
					'given_name_dom': document.getElementById("billing_first_name") ? document.getElementById("billing_first_name").value : 'N/A',
					'given_name_cartInfo': billingAddress["given_name"] || 'N/A',
					'given_name_final': given_name,
					'family_name_dom': document.getElementById("billing_last_name") ? document.getElementById("billing_last_name").value : 'N/A',
					'family_name_cartInfo': billingAddress["family_name"] || 'N/A',
					'family_name_final': family_name,
					'city_dom': document.getElementById("billing_city") ? document.getElementById("billing_city").value : 'N/A',
					'city_cartInfo': billingAddress["city"] || 'N/A',
					'city_final': city,
					'country_dom': document.getElementById("billing_country") ? document.getElementById("billing_country").value : 'N/A',
					'country_cartInfo': billingAddress["country"] || 'N/A',
					'country_final': country
				});
			}
			
			// Initialize shipping address variables (default to billing address)
			let shippingAddress1 = address1;
			let shippingAddress2 = address2;
			let shippingCity = city;
			let shippingState = state;
			let shippingZip = zip;
			let shippingCountry = country;
			
			// Check for shipping address - read from DOM first for fresh data
			let shippingElement = document.getElementById("ship-to-different-address-checkbox");
			if (shippingElement && shippingElement.checked) {
				const shippingAddress = cartInfo["shipping_address"] || {};
				shippingAddress1 = (document.getElementById("shipping_address_1") ? document.getElementById("shipping_address_1").value : '') || 
					shippingAddress["street_address"] || address1;
				shippingAddress2 = (document.getElementById("shipping_address_2") ? document.getElementById("shipping_address_2").value : '') || 
					shippingAddress["street_address2"] || address2;
				shippingCity = (document.getElementById("shipping_city") ? document.getElementById("shipping_city").value : '') || 
					shippingAddress["city"] || city;
				shippingState = (document.getElementById("shipping_state") ? document.getElementById("shipping_state").value : '') || 
					shippingAddress["state"] || state;
				shippingZip = (document.getElementById("shipping_postcode") ? document.getElementById("shipping_postcode").value : '') || 
					shippingAddress["postal_code"] || zip;
				shippingCountry = (document.getElementById("shipping_country") ? document.getElementById("shipping_country").value : '') || 
					shippingAddress["country"] || country;
			}
			
			// Extract order lines
			let orders = cartInfo["order_lines"];
			
			// Trust backend-provided order lines/totals.
			// Taxes, discounts, shipping, and fees are computed server-side by WooCommerce.
			
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
			const isSubscription = !!(cartInfo["contains_subscription"] || cartInfo["contains_subscription_in_cart"]);
			
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
				state: state,
				zip: zip,
				country: country,
				shippingAddress1: shippingAddress1,
				shippingAddress2: shippingAddress2,
				shippingCity: shippingCity,
				shippingState: shippingState,
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
		 * Read current order amount from DOM (.order-total display)
		 * This is more reliable than cached #cart-info after coupon changes
		 * @returns {number|null} Amount in minor units (cents) or null if not found
		 */
		getAmountFromDOM: function() {
			// Try to read from WooCommerce order total display
			let orderTotalEl = jQuery('.order-total .woocommerce-Price-amount bdi');
			
			if (orderTotalEl.length === 0) {
				orderTotalEl = jQuery('.order-total .woocommerce-Price-amount');
			}
			if (orderTotalEl.length === 0) {
				orderTotalEl = jQuery('.order-total .amount');
			}
			
			if (orderTotalEl.length > 0) {
				let totalText = orderTotalEl.last().text().trim();
				// Remove currency symbols and non-numeric chars except decimal
				let numericValue = totalText.replace(/[^0-9.,]/g, '').replace(',', '.');
				let parsedValue = parseFloat(numericValue);
				if (!isNaN(parsedValue)) {
					const amountInCents = Math.round(parsedValue * 100);
					if (typeof window.ckoLogger !== 'undefined') {
						window.ckoLogger.debug('[FlowCheckoutData] Read amount from DOM:', {
							displayedText: totalText,
							parsedValue: parsedValue,
							minorUnits: amountInCents
						});
					}
					return amountInCents;
				}
			}
			
			return null;
		}
	};
	
	if (typeof window.ckoLogger !== 'undefined' && window.ckoLogger.debugEnabled) {
		window.ckoLogger.debug('[FlowCheckoutData] Module loaded');
	}
})();
