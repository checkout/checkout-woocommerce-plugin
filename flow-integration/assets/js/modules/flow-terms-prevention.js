/**
 * Flow Terms Checkbox Prevention Module
 * 
 * Prevents page reload when terms checkbox is clicked by intercepting
 * WooCommerce's update_checkout event.
 * 
 * This module must be loaded BEFORE payment-session.js
 * Dependencies: jQuery, flow-logger.js
 * 
 * @module FlowTermsPrevention
 */

(function() {
	'use strict';
	
	/**
	 * Helper function to detect if an element is a terms/agreement checkbox
	 * Works generically for any terms checkbox regardless of ID/name/class
	 * 
	 * Exposed globally so it can be used in updated_checkout handler
	 * 
	 * @param {HTMLElement} element - The checkbox element to check
	 * @returns {boolean} True if element is a terms checkbox
	 */
	window.isTermsCheckbox = function isTermsCheckbox(element) {
		if (!element || element.type !== 'checkbox') {
			return false;
		}
		
		const $element = jQuery(element);
		const id = (element.id || '').toLowerCase();
		const name = (element.name || '').toLowerCase();
		
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
	};
	
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
			if (typeof window.ckoLogger !== 'undefined') {
				window.ckoLogger.debug('Terms checkbox clicked - setting prevention flag', {
					elementId: e.target.id || 'no-id',
					elementName: e.target.name || 'no-name'
				});
			}
			
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
			if (typeof window.ckoLogger !== 'undefined') {
				window.ckoLogger.debug('Terms checkbox changed - setting prevention flag', {
					elementId: e.target.id || 'no-id',
					elementName: e.target.name || 'no-name'
				});
			}
			
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
				if (typeof window.ckoLogger !== 'undefined') {
					window.ckoLogger.debug('🚫 Terms checkbox change intercepted via jQuery delegation - preventing update_checkout', {
						elementId: this.id || 'no-id',
						elementName: this.name || 'no-name'
					});
				}
				
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
				if (typeof window.ckoLogger !== 'undefined') {
					window.ckoLogger.debug('🚫 Terms checkbox change intercepted via body delegation - preventing update_checkout');
				}
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
					if (typeof window.ckoLogger !== 'undefined') {
						window.ckoLogger.debug('✅ BLOCKED update_checkout trigger from jQuery.fn.trigger() - terms checkbox prevention active', {
							event: eventName,
							element: this[0] ? (this[0].id || this[0].tagName || this[0].className) : 'unknown',
							preventionFlag: window.ckoPreventUpdateCheckout
						});
					}
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
					if (typeof window.ckoLogger !== 'undefined') {
						window.ckoLogger.debug('✅ BLOCKED update_checkout trigger from jQuery.event.trigger() - terms checkbox prevention active', {
							event: eventName,
							element: elem ? (elem.id || elem.tagName || elem.className) : 'unknown',
							preventionFlag: window.ckoPreventUpdateCheckout
						});
					}
					return; // Exit without triggering event
				}
			}
			// Call original trigger for all other events (99.9% of calls take this path)
			return originalEventTrigger.apply(this, arguments);
		};
		
		if (typeof window.ckoLogger !== 'undefined') {
			window.ckoLogger.debug('jQuery trigger interception installed for terms checkbox prevention');
		}
		
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
					if (typeof window.ckoLogger !== 'undefined') {
						window.ckoLogger.debug('Blocked form submission triggered by terms checkbox', {
							preventionFlag: window.ckoPreventUpdateCheckout,
							lastClickedId: window.ckoTermsCheckboxLastClicked ? (window.ckoTermsCheckboxLastClicked.id || 'no-id') : 'none'
						});
					}
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
				if (typeof window.ckoLogger !== 'undefined') {
					window.ckoLogger.debug('Blocked jQuery form submission triggered by terms checkbox');
				}
				e.preventDefault();
				e.stopImmediatePropagation();
				window.ckoPreventUpdateCheckout = false;
				window.ckoTermsCheckboxLastClicked = null;
				return false;
			}
		});
	}
	
	// Log that module loaded (only in debug mode)
	if (typeof window.ckoLogger !== 'undefined' && window.ckoLogger.debugEnabled) {
		window.ckoLogger.debug('[FLOW TERMS PREVENTION] Terms checkbox prevention module loaded');
	}
})();

