/**
 * Flow Saved Card Handler Module
 *
 * Handles saved card selection and Flow container visibility.
 *
 * Dependencies: jQuery, flow-logger.js
 *
 * @module FlowSavedCardHandler
 */

(function () {
	'use strict';

	let initialized = false;

	function attachHandler() {
		if (typeof jQuery === 'undefined') {
			return;
		}

		jQuery(document).on(
			'change',
			'input[name="wc-wc_checkout_com_flow-payment-token"]',
			function () {
				const selectedToken = jQuery(
					'input[name="wc-wc_checkout_com_flow-payment-token"]:checked'
				);
				const flowContainer = document.getElementById('flow-container');
				const selectedId = selectedToken.attr('id');

				// If "Use new payment method" is selected
				if (selectedId === 'wc-wc_checkout_com_flow-payment-token-new') {
					ckoLogger.debug('New payment method selected - showing Flow container');

					// Show Flow container
					if (flowContainer) {
						flowContainer.style.display = 'block';
					}

					// Mark as ready when Flow component is valid
					if (ckoFlow.flowComponent) {
						document.body.classList.add('flow-ready');
					} else {
						// Flow not initialized yet, wait for it
						document.body.classList.remove('flow-ready');
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
					document.body.classList.add('flow-ready');

					// Keep Flow container visible
					if (flowContainer) {
						flowContainer.style.display = 'block';
					}
				}
			}
		);
	}

	window.FlowSavedCardHandler = {
		init: function () {
			if (initialized) {
				return;
			}
			initialized = true;
			attachHandler();
		}
	};
})();
