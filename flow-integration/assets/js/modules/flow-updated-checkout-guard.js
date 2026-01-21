/**
 * Flow updated_checkout Guard Module
 *
 * Protects Flow component from WooCommerce updated_checkout DOM replacement.
 * Attaches the handler early and re-initializes Flow only when needed.
 *
 * Dependencies: jQuery, flow-logger.js, flow-state.js
 *
 * @module FlowUpdatedCheckoutGuard
 */

(function () {
	'use strict';

	let previousCartTotal =
		typeof cko_flow_vars !== 'undefined' && cko_flow_vars.cart_total
			? parseFloat(cko_flow_vars.cart_total)
			: 0;

	let initialized = false;

	function attachHandler() {
		if (typeof jQuery === 'undefined') {
			return;
		}

		// Remove any existing handler first
		jQuery(document).off('updated_checkout.cko-terms-prevention');

		// Attach handler immediately (before DOM ready) with capture-like behavior
		// Use body instead of document to catch events earlier
		jQuery('body').on('updated_checkout.cko-terms-prevention', function (event) {
			// Track updated_checkout events to detect multiple reloads
			if (!window.ckoUpdatedCheckoutCount) {
				window.ckoUpdatedCheckoutCount = 0;
				window.ckoUpdatedCheckoutTimestamps = [];
			}
			window.ckoUpdatedCheckoutCount++;
			const now = Date.now();
			window.ckoUpdatedCheckoutTimestamps.push(now);

			// Keep only last 10 timestamps
			if (window.ckoUpdatedCheckoutTimestamps.length > 10) {
				window.ckoUpdatedCheckoutTimestamps.shift();
			}

			const debugEnabled = typeof ckoLogger !== 'undefined' && ckoLogger.debugEnabled;

			// Check for rapid-fire events (multiple within 500ms)
			const recentEvents = window.ckoUpdatedCheckoutTimestamps.filter((ts) => now - ts < 500);
			if (recentEvents.length > 1 && debugEnabled) {
				ckoLogger.warn(
					`⚠️ MULTIPLE updated_checkout events detected: ${recentEvents.length} events in last 500ms (Total: ${window.ckoUpdatedCheckoutCount})`
				);
			}

			// Log in debug mode only to avoid noisy logs on hot path
			if (debugEnabled) {
				ckoLogger.debug(
					`updated_checkout EVENT #${window.ckoUpdatedCheckoutCount} fired at ${new Date().toLocaleTimeString()}`
				);
				ckoLogger.debug(`===== updated_checkout EVENT FIRED (#${window.ckoUpdatedCheckoutCount}) =====`);
			}

			// CRITICAL: Prevent update_checkout if it was triggered by a terms checkbox
			// Check prevention flag first (set by terms-prevention module)
			// Also check if the last clicked/changed element was a terms checkbox (backup check)
			const activeElement = document.activeElement;
			const lastClicked = window.ckoTermsCheckboxLastClicked;
			const timeSinceLastClick = Date.now() - window.ckoTermsCheckboxLastClickTime;
			const hasTermsHelper = typeof window.isTermsCheckbox === 'function';
			const isTermsTriggered =
				window.ckoPreventUpdateCheckout ||
				(hasTermsHelper && lastClicked && window.isTermsCheckbox(lastClicked) && timeSinceLastClick < 2000) ||
				(hasTermsHelper && activeElement && window.isTermsCheckbox(activeElement));

			if (isTermsTriggered) {
				ckoLogger.debug('🚫🚫🚫 updated_checkout triggered by terms checkbox - BLOCKING page reload', {
					preventionFlag: window.ckoPreventUpdateCheckout,
					lastClickedId: lastClicked ? lastClicked.id || 'no-id' : 'none',
					activeElementId: activeElement ? activeElement.id || 'no-id' : 'none',
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
				setTimeout(function () {
					window.ckoPreventUpdateCheckout = false;
					window.ckoTermsCheckboxLastClicked = null;
				}, 3000);

				// Exit early - don't process updated_checkout
				return false;
			}

			// Log field values BEFORE DOM update
			const emailBefore = getCheckoutFieldValue('billing_email');
			const addressBefore = getCheckoutFieldValue('billing_address_1');
			const cityBefore = getCheckoutFieldValue('billing_city');
			const countryBefore = getCheckoutFieldValue('billing_country');
			const flowPaymentBefore = document.getElementById('payment_method_wc_checkout_com_flow');

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
			const paymentId = urlParams.get('cko-payment-id');
			const sessionId = urlParams.get('cko-session-id');
			const paymentSessionId = urlParams.get('cko-payment-session-id');

			if (paymentId || sessionId || paymentSessionId) {
				ckoLogger.threeDS('Skipping updated_checkout handler - 3DS return detected in URL');
				FlowState.set('is3DSReturn', true);
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
					setTimeout(function () {
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
				flowContainerExists: !!document.getElementById('flow-container'),
				flowPaymentChecked: document.getElementById('payment_method_wc_checkout_com_flow')?.checked || false
			});

			// EVENT-DRIVEN: Flow remounting is now handled by cko:flow-container-ready event listener
			// No need for setTimeout delays - flow-container.js will emit event when container is ready
		});
	}

	window.FlowUpdatedCheckoutGuard = {
		init: function () {
			if (initialized) {
				return;
			}
			initialized = true;
			attachHandler();
		}
	};
})();
