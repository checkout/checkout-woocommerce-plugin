// Production-ready: Use ckoLogger if available, otherwise silent
if (typeof ckoLogger !== 'undefined') {
	ckoLogger.debug('[FLOW CONTAINER] Script loaded');
}

// Define addPaymentMethod outside any event listener so it's always available
function addPaymentMethod() {
	if (typeof ckoLogger !== 'undefined') {
		ckoLogger.debug('[FLOW CONTAINER] addPaymentMethod() called');
	}
	
	const paymentContainer = document.querySelector(
		".payment_method_wc_checkout_com_flow"
	);

	if (typeof ckoLogger !== 'undefined') {
		ckoLogger.debug('[FLOW CONTAINER] addPaymentMethod() state:', {
			paymentContainerExists: !!paymentContainer,
			existingContainerId: document.getElementById('flow-container') ? 'EXISTS' : 'NOT FOUND'
		});
	}

	if (paymentContainer) {
		// Add flow-container to the PAYMENT_BOX div, not the accordion!
		// Skip any accordion divs and find the actual payment_box
		const innerDiv = paymentContainer.querySelector("div.payment_box");
		
		if (typeof ckoLogger !== 'undefined') {
			ckoLogger.debug('[FLOW CONTAINER] addPaymentMethod() - payment_box div:', {
				innerDivExists: !!innerDiv,
				innerDivHasId: innerDiv ? !!innerDiv.id : false,
				innerDivId: innerDiv ? innerDiv.id : 'N/A'
			});
		}
		
		if (innerDiv && !innerDiv.id) {
			innerDiv.id = "flow-container";
            innerDiv.style.padding = "0";
			if (typeof ckoLogger !== 'undefined') {
				ckoLogger.debug('[FLOW CONTAINER] ✅ Created flow-container id on payment_box div');
			}
			
			// EVENT-DRIVEN: Emit custom event when container is created/recreated
			// This allows payment-session.js to react immediately instead of polling
			const containerReadyEvent = new CustomEvent('cko:flow-container-ready', {
				detail: { container: innerDiv },
				bubbles: true
			});
			document.dispatchEvent(containerReadyEvent);
			if (typeof ckoLogger !== 'undefined') {
				ckoLogger.debug('[FLOW CONTAINER] ✅ Emitted cko:flow-container-ready event');
			}
			
		} else if (innerDiv && innerDiv.id === 'flow-container') {
			if (typeof ckoLogger !== 'undefined') {
				ckoLogger.debug('[FLOW CONTAINER] ✅ Container already exists with correct ID');
			}
			
			// Still emit event even if container exists - allows Flow to check if it needs remounting
			const containerReadyEvent = new CustomEvent('cko:flow-container-ready', {
				detail: { container: innerDiv },
				bubbles: true
			});
			document.dispatchEvent(containerReadyEvent);
		} else if (!innerDiv) {
			if (typeof ckoLogger !== 'undefined') {
				ckoLogger.error('[FLOW CONTAINER] ❌ ERROR: payment_box div not found inside payment method container');
				ckoLogger.debug('[FLOW CONTAINER] Payment container HTML:', paymentContainer.innerHTML.substring(0, 200));
			}
		}
		
		// IMPORTANT: Check for saved payment methods (not accordion, which is created later)
		// The .woocommerce-SavedPaymentMethods elements exist in DOM immediately (PHP rendered)
		const savedPaymentMethods = document.querySelectorAll('.woocommerce-SavedPaymentMethods.wc-saved-payment-methods');
		let hasSavedCards = false;
		
		savedPaymentMethods.forEach(function(el) {
			const count = parseInt(el.getAttribute("data-count") || '0', 10);
			if (count > 0) {
				hasSavedCards = true;
			}
		});
		
		if (hasSavedCards) {
			if (typeof ckoLogger !== 'undefined') {
				ckoLogger.debug('Skipping Flow accordion - saved cards exist (count > 0), using simple layout');
			}
			return; // Don't wrap in accordion when saved cards are present
		}
		
		if (typeof ckoLogger !== 'undefined') {
			ckoLogger.debug('No saved cards found, using default label (no accordion wrapper)');
		}
		
		// DISABLED: Don't wrap Flow in accordion when no saved cards
		// Use the default WooCommerce payment method label instead
		// This ensures "Payment FLOW Payment" label is always visible
	} else {
		if (typeof ckoLogger !== 'undefined') {
			ckoLogger.debug('[FLOW CONTAINER] Payment method container not found');
		}
	}
}

// Run addPaymentMethod immediately if DOM is ready, otherwise wait
if (document.readyState === 'loading') {
	document.addEventListener("DOMContentLoaded", function () {
		if (typeof ckoLogger !== 'undefined') {
			ckoLogger.debug('[FLOW CONTAINER] DOMContentLoaded - calling addPaymentMethod()');
		}
		addPaymentMethod();
	});
} else {
	if (typeof ckoLogger !== 'undefined') {
		ckoLogger.debug('[FLOW CONTAINER] DOM already loaded - calling addPaymentMethod() immediately');
	}
	addPaymentMethod();
}

// EVENT-DRIVEN DESIGN: Listen for updated_checkout and ensure container exists
// When container is ready, emit event for payment-session.js to handle Flow lifecycle
jQuery(document).on("updated_checkout", function () {
	if (typeof ckoLogger !== 'undefined') {
		ckoLogger.debug('[FLOW CONTAINER] updated_checkout event fired');
	}
	
	// Wait for WooCommerce to finish DOM updates
	setTimeout(function() {
		const paymentMethod = document.querySelector('.payment_method_wc_checkout_com_flow');
		const flowContainer = document.getElementById('flow-container');
		
		// If Flow payment method is selected but container is missing, create it
		if (paymentMethod && !flowContainer) {
			if (typeof ckoLogger !== 'undefined') {
				ckoLogger.debug('[FLOW CONTAINER] Container missing after updated_checkout - recreating');
			}
			addPaymentMethod(); // This will emit cko:flow-container-ready event
		} else if (flowContainer) {
			// Container exists - emit event so Flow can check if remounting is needed
			const containerReadyEvent = new CustomEvent('cko:flow-container-ready', {
				detail: { container: flowContainer },
				bubbles: true
			});
			document.dispatchEvent(containerReadyEvent);
			if (typeof ckoLogger !== 'undefined') {
				ckoLogger.debug('[FLOW CONTAINER] Container exists - emitted cko:flow-container-ready event');
			}
		}
	}, 100); // Wait for WooCommerce to finish DOM updates
});

// Additional check after a delay to catch late-rendered saved cards
setTimeout(function() {
	if (typeof ckoLogger !== 'undefined') {
		ckoLogger.debug('Delayed check for saved cards...');
	}
	
	// If saved cards accordion was created after initial load, remove Flow accordion if it exists
	const savedCardsAccordion = document.querySelector('.saved-cards-accordion-container');
	const flowAccordion = document.querySelector('.flow-accordion-container');
	
	if (savedCardsAccordion && flowAccordion) {
		if (typeof ckoLogger !== 'undefined') {
			ckoLogger.debug('REMOVING Flow accordion - saved cards were created after initial load');
		}
		
		// Move flow-container out of the accordion
		const flowContainer = document.getElementById('flow-container');
		if (flowContainer) {
			// Insert flow-container before the flow accordion
			flowAccordion.parentNode.insertBefore(flowContainer, flowAccordion);
			// Remove the flow accordion wrapper
			flowAccordion.remove();
			if (typeof ckoLogger !== 'undefined') {
				ckoLogger.debug('Flow accordion removed, using simple layout');
			}
		}
	}
}, 1000);
