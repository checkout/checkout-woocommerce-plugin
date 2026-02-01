/**
 * Flow Initialization Module
 * 
 * Contains helper functions for Flow initialization, extracted from loadFlow()
 * to improve code organization and maintainability.
 * 
 * This module must be loaded BEFORE payment-session.js
 * Dependencies: jQuery, flow-logger.js, flow-validation.js, flow-state.js, flow-checkout-data.js
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
		 * Delegates to FlowCheckoutData module for normalization.
		 * @returns {Object} Checkout data object
		 */
		collectCheckoutData: function() {
			if (typeof window.FlowCheckoutData === 'undefined' || !window.FlowCheckoutData.getCheckoutData) {
				if (typeof window.ckoLogger !== 'undefined') {
					window.ckoLogger.error('collectCheckoutData: FlowCheckoutData module not loaded');
				}
				return null;
			}
			
			return window.FlowCheckoutData.getCheckoutData();
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

