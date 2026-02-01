/**
 * Flow Container-Ready Handler Module
 *
 * Listens for cko:flow-container-ready events and remounts Flow when needed.
 *
 * Dependencies: flow-logger.js, flow-state.js
 *
 * @module FlowContainerReadyHandler
 */

(function () {
	'use strict';

	let initialized = false;

	function attachHandler() {
		document.addEventListener('cko:flow-container-ready', function (event) {
			// CRITICAL: Check for 3DS return
			if (FlowState.get('is3DSReturn')) {
				ckoLogger.threeDS('Skipping container-ready handler - 3DS return in progress');
				return;
			}

			// Check URL parameters as fallback
			const urlParams = new URLSearchParams(window.location.search);
			if (
				urlParams.get('cko-payment-id') ||
				urlParams.get('cko-session-id') ||
				urlParams.get('cko-payment-session-id')
			) {
				ckoLogger.threeDS('Skipping container-ready handler - 3DS return detected in URL');
				FlowState.set('is3DSReturn', true);
				return;
			}

			const flowContainer = event.detail?.container || document.getElementById('flow-container');
			const flowPayment = document.getElementById('payment_method_wc_checkout_com_flow');
			const flowComponentRoot = document.querySelector('[data-testid="checkout-web-component-root"]');

			// Check if Flow component is actually mounted
			const flowComponentActuallyMounted = flowComponentRoot && flowComponentRoot.isConnected;
			const flowWasInitializedBefore =
				FlowState.get('initialized') && ckoFlow.flowComponent && !flowComponentActuallyMounted;

			ckoLogger.debug('🔔 Container-ready event received - checking if Flow needs remounting', {
				flowPaymentChecked: flowPayment?.checked || false,
				flowContainerExists: !!flowContainer,
				flowComponentRootExists: !!flowComponentRoot,
				flowComponentMounted: flowComponentActuallyMounted,
				flowInitialized: FlowState.get('initialized'),
				flowComponentExists: !!ckoFlow.flowComponent,
				flowWasInitializedBefore: flowWasInitializedBefore
			});

		// Only remount if:
		// 1. Flow payment method is selected
		// 2. Container exists
		// 3. Flow component WAS initialized but is now missing (was destroyed by updated_checkout)
		// 4. Flow is NOT currently initializing
		if (flowPayment && flowPayment.checked && flowContainer) {
			// CRITICAL FIX: Only remount if component was initialized before but now missing
			// Don't remount if component simply hasn't rendered yet (SDK takes 2-5 seconds)
			if (flowWasInitializedBefore) {
				// Component was destroyed by updated_checkout
				if (FlowState.get('initializing')) {
					ckoLogger.debug('Flow component not mounted but initialization already in progress, skipping');
					return;
				}

				ckoLogger.debug(
					'🔄 Flow component needs remounting - container is ready, re-initializing...'
				);

				// Reset flag so Flow can be re-initialized
				FlowState.set('initialized', false);
				if (ckoFlow.flowComponent) {
					// Component exists but was unmounted - unmount it so we can create a new one
					try {
						// CRITICAL: Flow SDK uses unmount(), not destroy()
						if (typeof ckoFlow.flowComponent.unmount === 'function') {
							ckoFlow.flowComponent.unmount();
						} else if (typeof ckoFlow.flowComponent.destroy === 'function') {
							// Fallback for older SDK versions
							ckoFlow.flowComponent.destroy();
						}
					} catch (e) {
						ckoLogger.debug('Error unmounting Flow component:', e);
					}
					ckoFlow.flowComponent = null;
				}
				// Re-initialize
				initializeFlowIfNeeded();
			} else {
				ckoLogger.debug('✅ Flow component still mounted or never initialized, no remounting needed');
			}
		}
		});
	}

	window.FlowContainerReadyHandler = {
		init: function () {
			if (initialized) {
				return;
			}
			initialized = true;
			attachHandler();
		}
	};
})();
