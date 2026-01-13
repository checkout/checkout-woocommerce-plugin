/**
 * Flow State Management Module
 * 
 * Centralizes all state variables for the Flow integration.
 * Provides a single source of truth for component state, preventing
 * scattered state variables and making debugging easier.
 * 
 * This module must be loaded BEFORE payment-session.js
 * Dependencies: flow-logger.js
 * 
 * @module FlowState
 */

(function() {
	'use strict';
	
	/**
	 * Centralized state object for Flow integration
	 * All state variables are managed here instead of being scattered throughout the codebase
	 */
	window.FlowState = {
		// Component state
		initialized: false,
		initializing: false,
		component: null,
		
		// Container state
		container: null,
		containerReady: false,
		
		// Payment state
		paymentSession: null,
		paymentSessionId: null,
		orderCreationInProgress: false,
		
		// UI state
		userInteracted: false,
		savedCardSelected: false,
		fieldsWereFilled: false,
		
		// Prevention flags (from terms prevention module)
		preventUpdateCheckout: false,
		termsCheckboxLastClicked: null,
		termsCheckboxLastClickTime: 0,
		
		// 3DS state
		is3DSReturn: false,
		
		// Error state
		lastError: null,
		
		// Timeouts
		reloadFlowTimeout: null,
		
		// Performance metrics
		performanceMetrics: {
			pageLoadTime: null,
			flowInitStartTime: null,
			flowReadyTime: null,
			enableLogging: false
		},
		
		/**
		 * Set a state value and optionally notify listeners
		 * @param {string} key - State key to set
		 * @param {*} value - Value to set
		 * @param {boolean} silent - If true, don't emit change event
		 */
		set: function(key, value, silent) {
			const oldValue = this[key];
			this[key] = value;
			
			if (!silent && typeof window.ckoLogger !== 'undefined' && window.ckoLogger.debugEnabled) {
				window.ckoLogger.debug('[FLOW STATE] ' + key + ' changed:', {
					old: oldValue,
					new: value
				});
			}
			
			// Emit custom event for state changes
			if (!silent) {
				const event = new CustomEvent('flow:state-changed', {
					detail: { key: key, value: value, oldValue: oldValue }
				});
				document.dispatchEvent(event);
			}
		},
		
		/**
		 * Get a state value
		 * @param {string} key - State key to get
		 * @returns {*} State value
		 */
		get: function(key) {
			return this[key];
		},
		
		/**
		 * Reset all state to initial values
		 * Useful for cleanup or testing
		 */
		reset: function() {
			this.initialized = false;
			this.initializing = false;
			this.component = null;
			this.container = null;
			this.containerReady = false;
			this.paymentSession = null;
			this.paymentSessionId = null;
			this.orderCreationInProgress = false;
			this.userInteracted = false;
			this.savedCardSelected = false;
			this.fieldsWereFilled = false;
			this.preventUpdateCheckout = false;
			this.termsCheckboxLastClicked = null;
			this.termsCheckboxLastClickTime = 0;
			this.is3DSReturn = false;
			this.lastError = null;
			
			if (this.reloadFlowTimeout) {
				clearTimeout(this.reloadFlowTimeout);
				this.reloadFlowTimeout = null;
			}
			
			if (typeof window.ckoLogger !== 'undefined') {
				window.ckoLogger.debug('[FLOW STATE] State reset to initial values');
			}
		},
		
		/**
		 * Get current state snapshot (for debugging)
		 * @returns {Object} Current state object
		 */
		getSnapshot: function() {
			return {
				initialized: this.initialized,
				initializing: this.initializing,
				hasComponent: !!this.component,
				hasContainer: !!this.container,
				containerReady: this.containerReady,
				hasPaymentSession: !!this.paymentSession,
				hasPaymentSessionId: !!this.paymentSessionId,
				orderCreationInProgress: this.orderCreationInProgress,
				userInteracted: this.userInteracted,
				savedCardSelected: this.savedCardSelected,
				fieldsWereFilled: this.fieldsWereFilled,
				preventUpdateCheckout: this.preventUpdateCheckout,
				is3DSReturn: this.is3DSReturn,
				hasError: !!this.lastError
			};
		}
	};
	
	// Expose legacy global variables for backward compatibility
	// These will be gradually replaced with FlowState throughout the codebase
	Object.defineProperty(window, 'ckoFlowInitialized', {
		get: function() { return window.FlowState.initialized; },
		set: function(value) { window.FlowState.set('initialized', value); },
		configurable: true
	});
	
	Object.defineProperty(window, 'ckoFlowInitializing', {
		get: function() { return window.FlowState.initializing; },
		set: function(value) { window.FlowState.set('initializing', value); },
		configurable: true
	});
	
	// Log that module loaded (only in debug mode)
	if (typeof window.ckoLogger !== 'undefined' && window.ckoLogger.debugEnabled) {
		window.ckoLogger.debug('[FLOW STATE] State management module loaded');
	}
})();

