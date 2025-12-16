// ALWAYS VISIBLE - Critical for diagnosing Flow disappearing issue
console.log('[FLOW CONTAINER] Script loaded');

// Define addPaymentMethod outside any event listener so it's always available
function addPaymentMethod() {
	// ALWAYS VISIBLE - Critical for diagnosing Flow disappearing issue
	console.log('[FLOW CONTAINER] addPaymentMethod() called');
	
	const paymentContainer = document.querySelector(
		".payment_method_wc_checkout_com_flow"
	);

	console.log('[FLOW CONTAINER] addPaymentMethod() state:', {
		paymentContainerExists: !!paymentContainer,
		existingContainerId: document.getElementById('flow-container') ? 'EXISTS' : 'NOT FOUND'
	});

	if (paymentContainer) {
		// Add flow-container to the PAYMENT_BOX div, not the accordion!
		// Skip any accordion divs and find the actual payment_box
		const innerDiv = paymentContainer.querySelector("div.payment_box");
		
		console.log('[FLOW CONTAINER] addPaymentMethod() - payment_box div:', {
			innerDivExists: !!innerDiv,
			innerDivHasId: innerDiv ? !!innerDiv.id : false,
			innerDivId: innerDiv ? innerDiv.id : 'N/A'
		});
		
		if (innerDiv && !innerDiv.id) {
			innerDiv.id = "flow-container";
            innerDiv.style.padding = "0";
			console.log('[FLOW CONTAINER] ✅ Created flow-container id on payment_box div');
			if (typeof ckoLogger !== 'undefined') {
				ckoLogger.debug('Set flow-container id on payment_box div');
			}
		} else if (innerDiv && innerDiv.id === 'flow-container') {
			console.log('[FLOW CONTAINER] ✅ Container already exists with correct ID');
		} else if (!innerDiv) {
			console.log('[FLOW CONTAINER] ❌ ERROR: payment_box div not found inside payment method container');
			console.log('[FLOW CONTAINER] Payment container HTML:', paymentContainer.innerHTML.substring(0, 200));
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
		console.log('[FLOW CONTAINER] ❌ Payment method container not found');
	}
}

// Run addPaymentMethod immediately if DOM is ready, otherwise wait
if (document.readyState === 'loading') {
	document.addEventListener("DOMContentLoaded", function () {
		console.log('[FLOW CONTAINER] DOMContentLoaded - calling addPaymentMethod()');
		addPaymentMethod();
	});
} else {
	console.log('[FLOW CONTAINER] DOM already loaded - calling addPaymentMethod() immediately');
	addPaymentMethod();
}

// Attach updated_checkout handler immediately (doesn't need to wait for DOMContentLoaded)
jQuery(document).on("updated_checkout", function () {
	// ALWAYS VISIBLE - Critical for diagnosing Flow disappearing issue
	console.log('[FLOW CONTAINER] updated_checkout event fired, re-checking...');
	
	// CRITICAL: Wait for WooCommerce to finish updating DOM before checking
	// WooCommerce replaces HTML asynchronously, so we need to wait
	setTimeout(function() {
		const flowContainer = document.getElementById('flow-container');
		const flowComponentRoot = document.querySelector('[data-testid="checkout-web-component-root"]');
		const paymentMethod = document.querySelector('.payment_method_wc_checkout_com_flow');
		
		console.log('[FLOW CONTAINER] After updated_checkout (100ms):', {
			flowContainerExists: !!flowContainer,
			flowComponentRootExists: !!flowComponentRoot,
			paymentMethodExists: !!paymentMethod
		});
		
		// CRITICAL FIX: Always call addPaymentMethod() if payment method exists but container doesn't
		// This ensures the container is recreated after WooCommerce destroys it
		if (paymentMethod && !flowContainer) {
			console.log('[FLOW CONTAINER] Payment method exists but container missing - creating container');
			addPaymentMethod();
		} else if (flowContainer && (!flowComponentRoot || !flowContainer.querySelector('[data-testid="checkout-web-component-root"]'))) {
			console.log('[FLOW CONTAINER] Flow component not mounted after DOM update, calling addPaymentMethod');
			addPaymentMethod();
		} else if (flowContainer && flowComponentRoot) {
			console.log('[FLOW CONTAINER] Flow component is still mounted, skipping addPaymentMethod');
		} else if (!paymentMethod) {
			console.log('[FLOW CONTAINER] Payment method not found - Flow not selected');
		} else {
			console.log('[FLOW CONTAINER] Container not found yet, will retry...');
			// Container might not be ready yet, retry after a short delay
			setTimeout(function() {
				const flowContainerRetry = document.getElementById('flow-container');
				const paymentMethodRetry = document.querySelector('.payment_method_wc_checkout_com_flow');
				console.log('[FLOW CONTAINER] Retry check (300ms total):', {
					flowContainerExists: !!flowContainerRetry,
					paymentMethodExists: !!paymentMethodRetry
				});
				if (paymentMethodRetry && !flowContainerRetry) {
					console.log('[FLOW CONTAINER] Payment method found on retry but container missing - creating container');
					addPaymentMethod();
				} else if (flowContainerRetry && !document.querySelector('[data-testid="checkout-web-component-root"]')) {
					console.log('[FLOW CONTAINER] Flow container found on retry, calling addPaymentMethod');
					addPaymentMethod();
				}
			}, 200);
		}
	}, 100); // Wait 100ms for WooCommerce to finish updating DOM
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
