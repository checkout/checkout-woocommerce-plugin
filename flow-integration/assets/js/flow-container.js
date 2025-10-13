document.addEventListener("DOMContentLoaded", function () {
	function addPaymentMethod() {
		const paymentContainer = document.querySelector(
			".payment_method_wc_checkout_com_flow"
		);

		if (paymentContainer) {
			// Add flow-container to the PAYMENT_BOX div, not the accordion!
			// Skip any accordion divs and find the actual payment_box
			const innerDiv = paymentContainer.querySelector("div.payment_box");
			if (innerDiv && !innerDiv.id) {
				innerDiv.id = "flow-container";
                innerDiv.style.padding = "0";
				if (typeof ckoLogger !== 'undefined') {
					ckoLogger.debug('Set flow-container id on payment_box div');
				}
				
				// IMPORTANT: Check for saved payment methods (not accordion, which is created later)
				// The .woocommerce-SavedPaymentMethods elements exist in DOM immediately (PHP rendered)
				const savedPaymentMethods = document.querySelectorAll('.woocommerce-SavedPaymentMethods.wc-saved-payment-methods');
				let hasSavedCards = false;
				
				savedPaymentMethods.forEach(function(el) {
					const count = parseInt(el.getAttribute('data-count') || '0', 10);
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
				
				/* COMMENTED OUT - Flow accordion wrapper not needed
				// Wrap Flow container in styled accordion if not already wrapped
				// (Only for cases without saved cards)
				if (!innerDiv.closest('.flow-accordion-container')) {
					const flowWrapper = document.createElement('div');
					flowWrapper.className = 'flow-accordion-container';
					flowWrapper.innerHTML = `
						<div class="flow-accordion">
							<div class="flow-accordion-header">
								<div class="flow-accordion-left">
									<div class="flow-icon">
										<svg width="40" height="24" viewBox="0 0 40 24" xmlns="http://www.w3.org/2000/svg" fill="none">
											<rect x="0.5" y="0.5" width="39" height="23" rx="3.5" stroke="#186aff"></rect>
											<path fill-rule="evenodd" clip-rule="evenodd" d="M26.8571 6.85714H13.1428V17.1429H26.8571V6.85714ZM12.2857 6V18H27.7143V6H12.2857Z" fill="#186aff"></path>
											<path fill-rule="evenodd" clip-rule="evenodd" d="M26.8571 9.42857H13.1428V7.71429H26.8571V9.42857Z" fill="#186aff"></path>
											<path fill-rule="evenodd" clip-rule="evenodd" d="M18.2857 15.4286H14.8571V14.5714H18.2857V15.4286Z" fill="#186aff"></path>
										</svg>
									</div>
									<div class="flow-label">
										<span class="flow-label-text">New payment method</span>
										<span class="flow-label-subtext">Pay with card or other methods</span>
									</div>
								</div>
							</div>
							<div class="flow-accordion-panel" id="flow-accordion-panel">
								<!-- Flow container will be moved here -->
							</div>
						</div>
					`;
					
					// Insert wrapper before innerDiv
					innerDiv.parentNode.insertBefore(flowWrapper, innerDiv);
					
					// Move innerDiv (flow-container) into the accordion panel
					document.getElementById('flow-accordion-panel').appendChild(innerDiv);
					
					if (typeof ckoLogger !== 'undefined') {
						ckoLogger.debug('Flow wrapped in accordion (no saved cards)');
					}
				}
				*/
			}
		}
	}

	addPaymentMethod();

	jQuery(document).on("updated_checkout", function () {
		if (typeof ckoLogger !== 'undefined') {
			ckoLogger.debug('updated_checkout event fired, re-checking...');
		}
		addPaymentMethod();
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
});
