/**
 * Flow Logger Module
 * 
 * Centralized logging utility for Checkout.com Flow integration.
 * Controls what logs appear in production vs debug mode.
 * 
 * This module must be loaded BEFORE payment-session.js
 * 
 * @module FlowLogger
 */

(function() {
	'use strict';
	
	/**
	 * Centralized logging utility for Checkout.com Flow integration.
	 * Controls what logs appear in production vs debug mode.
	 * 
	 * @namespace ckoLogger
	 */
	window.ckoLogger = {
		/**
		 * Whether debug logging is enabled
		 * @type {boolean}
		 */
		debugEnabled: (typeof cko_flow_vars !== 'undefined' && cko_flow_vars.debug_logging) || false,
		
		/**
		 * Log error message (always visible in production)
		 * @param {string} message - Error message
		 * @param {*} [data] - Optional data object
		 */
		error: function(message, data) {
			console.error('[FLOW ERROR] ' + message, data !== undefined ? data : '');
		},
		
		/**
		 * Log warning message (always visible in production)
		 * @param {string} message - Warning message
		 * @param {*} [data] - Optional data object
		 */
		warn: function(message, data) {
			console.warn('[FLOW WARNING] ' + message, data !== undefined ? data : '');
		},
		
		/**
		 * Log webhook message (always visible in production)
		 * @param {string} message - Webhook message
		 * @param {*} [data] - Optional data object
		 */
		webhook: function(message, data) {
			console.log('[FLOW WEBHOOK] ' + message, data !== undefined ? data : '');
		},
		
		/**
		 * Log 3DS message (always visible in production)
		 * @param {string} message - 3DS message
		 * @param {*} [data] - Optional data object
		 */
		threeDS: function(message, data) {
			console.log('[FLOW 3DS] ' + message, data !== undefined ? data : '');
		},
		
		/**
		 * Log payment message (always visible in production)
		 * @param {string} message - Payment message
		 * @param {*} [data] - Optional data object
		 */
		payment: function(message, data) {
			console.log('[FLOW PAYMENT] ' + message, data !== undefined ? data : '');
		},
		
		/**
		 * Log version message (always visible in production)
		 * @param {string} version - Version string
		 */
		version: function(version) {
			console.log('🚀 Checkout.com Flow v' + version);
		},
		
		/**
		 * Log debug message (only visible when debug logging is enabled)
		 * Enable via "Debug Logging" setting in admin
		 * @param {string} message - Debug message
		 * @param {*} [data] - Optional data object
		 */
		debug: function(message, data) {
			if (this.debugEnabled) {
				console.log('[FLOW DEBUG] ' + message, data !== undefined ? data : '');
			}
		},
		
		/**
		 * Log performance message (only visible when debug logging is enabled)
		 * @param {string} message - Performance message
		 * @param {*} [data] - Optional data object
		 */
		performance: function(message, data) {
			if (this.debugEnabled) {
				console.log('[FLOW PERFORMANCE] ' + message, data !== undefined ? data : '');
			}
		}
	};
	
	// Log that logger module loaded (only in debug mode)
	if (window.ckoLogger.debugEnabled) {
		window.ckoLogger.debug('[FLOW LOGGER] Logger module loaded');
	}
})();

