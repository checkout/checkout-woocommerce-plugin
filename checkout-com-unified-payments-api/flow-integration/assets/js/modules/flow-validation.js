/**
 * Flow Validation Module
 * 
 * Provides validation functions for checkout fields and user data.
 * Used throughout Flow integration to validate form fields before initialization.
 * 
 * This module must be loaded BEFORE payment-session.js
 * Dependencies: jQuery, flow-logger.js
 * 
 * @module FlowValidation
 */

(function() {
	'use strict';
	
	/**
	 * Flow Validation namespace
	 * Exposes validation functions globally for use in payment-session.js
	 */
	window.FlowValidation = {
		/**
		 * Check if user is logged in
		 * @returns {boolean} True if user is logged in
		 */
		isUserLoggedIn: function() {
			if (typeof cko_flow_vars === 'undefined') {
				return false;
			}
			return cko_flow_vars.is_user_logged_in === true || 
				   cko_flow_vars.is_user_logged_in === "1" ||
				   cko_flow_vars.is_user_logged_in === 1 ||
				   document.querySelector('.woocommerce-form-login') === null;
		},
		
		/**
		 * Get checkout field value by ID
		 * @param {string} fieldId - Field ID
		 * @returns {string|null} Field value or null if not found
		 */
		getCheckoutFieldValue: function(fieldId) {
			const el = document.getElementById(fieldId);
			return el && el.value ? el.value.trim() : null;
		},
		
		/**
		 * Validate email format
		 * @param {string} email - Email address
		 * @returns {boolean} True if email is valid
		 */
		isValidEmail: function(email) {
			if (!email) return false;
			const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
			return emailRegex.test(email);
		},
		
		/**
		 * Check if postcode is required for a country
		 * @param {string} country - Country code
		 * @returns {boolean} True if postcode is required
		 */
		isPostcodeRequiredForCountry: function(country) {
			if (!country) return true; // Default to required if unknown
			// Countries that don't require postcode
			const noPostcodeCountries = ['AE', 'AO', 'AG', 'AW', 'BS', 'BZ', 'BJ', 'BW', 'BF', 'BI', 'CM', 'CF', 'KM', 'CG', 'CD', 'CK', 'CI', 'DJ', 'DM', 'GQ', 'ER', 'FJ', 'TF', 'GM', 'GH', 'GD', 'GN', 'GY', 'HK', 'IE', 'JM', 'KE', 'KI', 'LS', 'LR', 'MW', 'ML', 'MR', 'MU', 'MS', 'NR', 'NU', 'KP', 'PA', 'QA', 'RW', 'KN', 'LC', 'ST', 'SC', 'SL', 'SB', 'SO', 'SR', 'SZ', 'TJ', 'TZ', 'TL', 'TG', 'TO', 'TT', 'TV', 'UG', 'VU', 'YE', 'ZW'];
			return !noPostcodeCountries.includes(country.toUpperCase());
		},
		
		/**
		 * Check if billing address is present
		 * For order-pay pages, checks order data first (cartInfo), then falls back to form fields
		 * @returns {boolean} True if billing address exists
		 */
		hasBillingAddress: function() {
			// Check if we're on order-pay page and have order data
			const isOrderPayPage = window.location.pathname.includes('/order-pay/');
			let orderPayInfo = null;
			if (isOrderPayPage && typeof jQuery !== 'undefined') {
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
					if (typeof window.ckoLogger !== 'undefined') {
						window.ckoLogger.debug('hasBillingAddress: Using order data (order-pay page)', {
							email: email ? 'SET' : 'EMPTY',
							address1: address1 ? 'SET' : 'EMPTY',
							city: city ? 'SET' : 'EMPTY',
							country: country ? 'SET' : 'EMPTY'
						});
					}
					return true;
				}
			}
			
			// Fallback to form fields (regular checkout or if order data not available)
			const email = this.getCheckoutFieldValue("billing_email");
			const address1 = this.getCheckoutFieldValue("billing_address_1");
			const city = this.getCheckoutFieldValue("billing_city");
			const country = this.getCheckoutFieldValue("billing_country");
			
			return !!(email && address1 && city && country);
		},
		
		/**
		 * Check if billing address is complete
		 * For order-pay pages, checks order data first (cartInfo), then falls back to form fields
		 * @returns {boolean} True if billing address is complete
		 */
		hasCompleteBillingAddress: function() {
			// Check if we're on order-pay page and have order data
			const isOrderPayPage = window.location.pathname.includes('/order-pay/');
			let orderPayInfo = null;
			if (isOrderPayPage && typeof jQuery !== 'undefined') {
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
				const postcodeRequired = this.isPostcodeRequiredForCountry(country);
				if (postcodeRequired && !postcode) {
					return false;
				}
				
				return true;
			}
			
			// Fallback to form fields (regular checkout or if order data not available)
			const address1 = this.getCheckoutFieldValue("billing_address_1");
			const city = this.getCheckoutFieldValue("billing_city");
			const country = this.getCheckoutFieldValue("billing_country");
			const postcode = this.getCheckoutFieldValue("billing_postcode");
			
			if (!address1 || !city || !country) {
				return false;
			}
			
			// Check if postcode is required for country
			const postcodeRequired = this.isPostcodeRequiredForCountry(country);
			if (postcodeRequired && !postcode) {
				return false;
			}
			
			return true;
		},
		
		/**
		 * Enhanced required fields check with email validation
		 * For order-pay pages, checks order data first (cartInfo), then falls back to form fields
		 * @returns {boolean} True if all required fields are filled and valid
		 */
		requiredFieldsFilledAndValid: function() {
			// Check if we're on order-pay page and have order data
			const isOrderPayPage = window.location.pathname.includes('/order-pay/');
			let orderPayInfo = null;
			if (isOrderPayPage && typeof jQuery !== 'undefined') {
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
					if (typeof window.ckoLogger !== 'undefined') {
						window.ckoLogger.debug('requiredFieldsFilledAndValid: Order data missing required fields', {
							email: email ? 'SET' : 'EMPTY',
							address1: address1 ? 'SET' : 'EMPTY',
							city: city ? 'SET' : 'EMPTY',
							country: country ? 'SET' : 'EMPTY'
						});
					}
					return false;
				}
				
				// Validate email format
				const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
				if (!emailRegex.test(email.trim())) {
					if (typeof window.ckoLogger !== 'undefined') {
						window.ckoLogger.debug('requiredFieldsFilledAndValid: Order data email invalid', { email: email });
					}
					return false;
				}
				
				// Check postcode if required for country
				const postcodeRequired = this.isPostcodeRequiredForCountry(country);
				if (postcodeRequired && !postcode) {
					if (typeof window.ckoLogger !== 'undefined') {
						window.ckoLogger.debug('requiredFieldsFilledAndValid: Order data missing postcode for country', { country: country });
					}
					return false;
				}
				
				if (typeof window.ckoLogger !== 'undefined') {
					window.ckoLogger.debug('requiredFieldsFilledAndValid: Order data validation passed (order-pay page)', {
						email: email ? 'SET' : 'EMPTY',
						address1: address1 ? 'SET' : 'EMPTY',
						city: city ? 'SET' : 'EMPTY',
						country: country ? 'SET' : 'EMPTY',
						postcode: postcode ? 'SET' : 'EMPTY'
					});
				}
				return true;
			}
			
			// Fallback to form fields (regular checkout or if order data not available)
			// First check if all required fields are filled
			if (typeof window.ckoLogger !== 'undefined') {
				window.ckoLogger.debug('requiredFieldsFilledAndValid: Calling requiredFieldsFilled()...');
			}
			const fieldsFilled = this.requiredFieldsFilled();
			if (typeof window.ckoLogger !== 'undefined') {
				window.ckoLogger.debug('requiredFieldsFilledAndValid: requiredFieldsFilled() returned:', fieldsFilled);
			}
			if (!fieldsFilled) {
				if (typeof window.ckoLogger !== 'undefined') {
					window.ckoLogger.debug('requiredFieldsFilledAndValid: requiredFieldsFilled() returned false - fields not filled');
				}
				return false;
			}
			
			// Validate email format
			const email = this.getCheckoutFieldValue("billing_email");
			if (!email || !this.isValidEmail(email)) {
				return false;
			}
			
			// Check billing address is complete
			if (!this.hasCompleteBillingAddress()) {
				return false;
			}
			
			return true;
		},
		
		/**
		 * Check if all required fields are filled
		 * Filters out shipping fields, other payment gateway fields, and account fields (unless account creation enabled)
		 * @returns {boolean} True if all required fields are filled
		 */
		requiredFieldsFilled: function() {
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
				if (typeof window.ckoLogger !== 'undefined' && window.flowDebugLogging) {
					window.ckoLogger.debug('requiredFieldsFilled: Account creation not enabled - filtered out account_username and account_password');
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
					if (typeof window.ckoLogger !== 'undefined' && window.flowDebugLogging) {
						window.ckoLogger.debug('requiredFieldsFilled: Account creation enabled but fields are hidden - filtered out account fields');
					}
				} else {
					if (typeof window.ckoLogger !== 'undefined' && window.flowDebugLogging) {
						window.ckoLogger.debug('requiredFieldsFilled: Account creation enabled and fields visible - including account_username and account_password');
					}
				}
			}

			// FALLBACK: If no fields found via .required selector, check common required fields directly
			// This handles cases where:
			// 1. Site uses Blocks checkout (different structure)
			// 2. Theme doesn't use .required class
			// 3. Fields are required but not marked with .required
			if (filteredFieldIds.length === 0) {
				if (typeof window.ckoLogger !== 'undefined' && window.flowDebugLogging) {
					window.ckoLogger.debug('requiredFieldsFilled: No fields found via .required selector, using fallback');
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
				
				if (typeof window.ckoLogger !== 'undefined' && window.flowDebugLogging) {
					window.ckoLogger.debug('requiredFieldsFilled: Fallback found fields:', filteredFieldIds);
				}
			}

			// DEBUG: Log field validation details
			if (typeof window.ckoLogger !== 'undefined' && window.flowDebugLogging) {
				window.ckoLogger.debug('requiredFieldsFilled: Checking ' + filteredFieldIds.length + ' fields:', filteredFieldIds.join(', '));
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
				if (typeof window.ckoLogger !== 'undefined' && window.flowDebugLogging) {
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
			if (typeof window.ckoLogger !== 'undefined' && window.flowDebugLogging) {
				if (failedFields.length > 0) {
					window.ckoLogger.debug('requiredFieldsFilled: ❌ FAILED fields: ' + failedFields.join(', '));
				}
				// Log each field's status individually for better visibility
				Object.keys(fieldResults).forEach(id => {
					const result = fieldResults[id];
					if (!result.isValid) {
						window.ckoLogger.debug('requiredFieldsFilled: Field "' + id + '" - exists: ' + result.exists + ', value: "' + result.value + '", isEmpty: ' + result.isEmpty);
					}
				});
				window.ckoLogger.debug('requiredFieldsFilled: Final result: ' + (result ? '✅ PASSED' : '❌ FAILED') + ' (' + filteredFieldIds.length + ' fields checked, ' + failedFields.length + ' failed)');
			}

			return result;
		}
	};
	
	// Expose individual functions globally for backward compatibility
	// This allows existing code to call functions directly without FlowValidation prefix
	window.isUserLoggedIn = function() { return window.FlowValidation.isUserLoggedIn(); };
	window.getCheckoutFieldValue = function(fieldId) { return window.FlowValidation.getCheckoutFieldValue(fieldId); };
	window.isValidEmail = function(email) { return window.FlowValidation.isValidEmail(email); };
	window.isPostcodeRequiredForCountry = function(country) { return window.FlowValidation.isPostcodeRequiredForCountry(country); };
	window.hasBillingAddress = function() { return window.FlowValidation.hasBillingAddress(); };
	window.hasCompleteBillingAddress = function() { return window.FlowValidation.hasCompleteBillingAddress(); };
	window.requiredFieldsFilledAndValid = function() { return window.FlowValidation.requiredFieldsFilledAndValid(); };
	window.requiredFieldsFilled = function() { return window.FlowValidation.requiredFieldsFilled(); };
	
	// Log that module loaded (only in debug mode)
	if (typeof window.ckoLogger !== 'undefined' && window.ckoLogger.debugEnabled) {
		window.ckoLogger.debug('[FLOW VALIDATION] Validation module loaded');
	}
})();

