/**
 * Flow Early 3DS Detection Module
 *
 * Detects 3DS return parameters as early as possible to prevent
 * Flow initialization on redirect return pages.
 *
 * Dependencies: flow-logger.js, flow-state.js (optional)
 *
 * @module Flow3DSDetection
 */

(function () {
	'use strict';

	// Check URL parameters immediately (before any other code runs)
	const urlParams = new URLSearchParams(window.location.search);
	const paymentId = urlParams.get('cko-payment-id');
	const sessionId = urlParams.get('cko-session-id');
	const paymentSessionId = urlParams.get('cko-payment-session-id');

	if (paymentId || sessionId || paymentSessionId) {
		// Set flag immediately to prevent Flow initialization
		if (typeof window.FlowState !== 'undefined') {
			window.FlowState.set('is3DSReturn', true);
		} else {
			window.ckoFlow3DSReturn = true;
		}

		if (typeof window.ckoLogger !== 'undefined' && window.ckoLogger.threeDS) {
			window.ckoLogger.threeDS('⚠️⚠️⚠️ EARLY DETECTION: 3DS return detected, preventing ALL Flow initialization', {
				paymentId: paymentId,
				sessionId: sessionId,
				paymentSessionId: paymentSessionId,
				timestamp: new Date().toISOString()
			});
		}
	}
})();
