/**
 * Flow updated_checkout Guard Module
 *
 * Protects Flow component from WooCommerce updated_checkout DOM replacement.
 * Key mechanism: Detaches Flow container from DOM before WooCommerce replaces fragments,
 * then reattaches it after. This preserves card details during coupon operations.
 *
 * NOTE: The detach/reattach feature is controlled by admin toggle "Preserve Card Details".
 * When disabled (default), card details will be cleared on coupon apply for maximum compatibility.
 *
 * Dependencies: jQuery, flow-logger.js, flow-state.js
 *
 * @module FlowUpdatedCheckoutGuard
 */

(function () {
	'use strict';

	// Check if preserve card feature is enabled (admin toggle)
	const preserveCardEnabled = typeof cko_flow_vars !== 'undefined' && cko_flow_vars.preserve_card_on_update === true;

	// order_amount is in cents (minor units). Used to detect coupon/cart changes so Flow reloads with correct amount.
	let previousOrderAmount =
		typeof cko_flow_vars !== 'undefined' && cko_flow_vars.order_amount
			? parseInt(cko_flow_vars.order_amount, 10)
			: null;

	let initialized = false;
	
	// Storage for detached Flow container during WooCommerce DOM replacement
	let detachedFlowContainer = null;
	let detachedFlowParentSelector = null;
	
	/**
	 * Get the current order total in minor units (cents/pence).
	 * Tries multiple sources to ensure we get the most up-to-date value:
	 * 1. WooCommerce order total display (most reliable after fragment update)
	 * 2. Cart-info data attribute (may be stale if not in fragments)
	 * 
	 * @returns {number|null} Order total in minor units, or null if not found
	 */
	function getCurrentOrderTotalFromDOM() {
		let currentOrderAmount = null;
		
		// Debug: Log all potential price elements
		if (typeof ckoLogger !== 'undefined') {
			const allPriceElements = jQuery('.order-total, .cart-subtotal, .cart-discount, .woocommerce-Price-amount');
			ckoLogger.debug('[ORDER TOTAL] ========== DOM PRICE DEBUG ==========');
			ckoLogger.debug('[ORDER TOTAL] Found price-related elements:', allPriceElements.length);
			
			// Log order-total specifically
			const orderTotal = jQuery('.order-total');
			if (orderTotal.length > 0) {
				ckoLogger.debug('[ORDER TOTAL] .order-total HTML:', orderTotal.html());
				ckoLogger.debug('[ORDER TOTAL] .order-total text:', orderTotal.text().trim());
			}
			
			// Log if there's a discount row
			const discountRow = jQuery('.cart-discount');
			if (discountRow.length > 0) {
				ckoLogger.debug('[ORDER TOTAL] .cart-discount found:', discountRow.text().trim());
			}
		}
		
		// Method 1: Read from WooCommerce order total display (most reliable after fragment update)
		// Try specific order-total first, then fall back to other selectors
		let orderTotalEl = jQuery('.order-total .woocommerce-Price-amount bdi');
		
		if (orderTotalEl.length === 0) {
			orderTotalEl = jQuery('.order-total .woocommerce-Price-amount');
		}
		if (orderTotalEl.length === 0) {
			orderTotalEl = jQuery('.order-total .amount');
		}
		
		if (orderTotalEl.length > 0) {
			// Get the LAST price element (in case there are multiple, the final one is usually the actual total)
			let totalText = orderTotalEl.last().text().trim();
			// Remove currency symbols and non-numeric chars except decimal
			let numericValue = totalText.replace(/[^0-9.,]/g, '').replace(',', '.');
			// Parse and convert to minor units (cents/pence)
			let parsedValue = parseFloat(numericValue);
			if (!isNaN(parsedValue)) {
				currentOrderAmount = Math.round(parsedValue * 100);
				if (typeof ckoLogger !== 'undefined') {
					ckoLogger.debug('[ORDER TOTAL] Read from DOM (.order-total):', {
						displayedText: totalText,
						parsedValue: parsedValue,
						minorUnits: currentOrderAmount,
						elementCount: orderTotalEl.length
					});
				}
				ckoLogger.debug('[ORDER TOTAL] ========== END DOM PRICE DEBUG ==========');
				return currentOrderAmount;
			}
		}
		
		// Method 2: Fallback to cart-info data attribute
		const cartInfo = jQuery('#cart-info').data('cart');
		if (cartInfo && typeof cartInfo.order_amount !== 'undefined') {
			currentOrderAmount = parseInt(cartInfo.order_amount, 10);
			if (typeof ckoLogger !== 'undefined') {
				ckoLogger.debug('[ORDER TOTAL] Fallback to cart-info data:', {
					orderAmount: currentOrderAmount
				});
				ckoLogger.debug('[ORDER TOTAL] ========== END DOM PRICE DEBUG ==========');
			}
			return currentOrderAmount;
		}
		
		if (typeof ckoLogger !== 'undefined') {
			ckoLogger.debug('[ORDER TOTAL] Could not determine current order total');
			ckoLogger.debug('[ORDER TOTAL] ========== END DOM PRICE DEBUG ==========');
		}
		return null;
	}

	/**
	 * Detach Flow container from DOM to protect it from WooCommerce fragment replacement.
	 * Called BEFORE WooCommerce AJAX request to ensure container is safe.
	 * Only operates when preserve_card_on_update is enabled.
	 */
	function detachFlowContainer() {
		// Skip if feature is disabled
		if (!preserveCardEnabled) {
			if (typeof ckoLogger !== 'undefined') {
				ckoLogger.debug('[COUPON GUARD] Preserve card feature disabled - skipping detach');
			}
			return false;
		}

		const flowContainer = document.getElementById('flow-container');
		if (!flowContainer) {
			if (typeof ckoLogger !== 'undefined') {
				ckoLogger.debug('[COUPON GUARD] No flow-container to detach');
			}
			return false;
		}

		// Check if Flow is actually mounted (has SDK content)
		const hasFlowContent = flowContainer.querySelector('[data-testid="checkout-web-component-root"]') ||
		                       flowContainer.querySelector('.cko-flow-sdk-root') ||
		                       flowContainer.querySelector('iframe');
		
		if (!hasFlowContent) {
			if (typeof ckoLogger !== 'undefined') {
				ckoLogger.debug('[COUPON GUARD] Flow container exists but has no SDK content - skipping detach');
			}
			return false;
		}

		// Store reference to parent for reattachment
		const parent = flowContainer.parentNode;
		if (parent) {
			// Create a placeholder to mark where Flow should be reattached
			const placeholder = document.createElement('div');
			placeholder.id = 'flow-container-placeholder';
			placeholder.style.display = 'none';
			parent.insertBefore(placeholder, flowContainer);
			
			// Detach the Flow container (removes from DOM but keeps in memory)
			detachedFlowContainer = flowContainer;
			parent.removeChild(flowContainer);
			
			if (typeof ckoLogger !== 'undefined') {
				ckoLogger.debug('[COUPON GUARD] ✅ Flow container DETACHED from DOM (protected from WooCommerce replacement)');
			}
			return true;
		}
		return false;
	}

	/**
	 * Reattach Flow container after WooCommerce finishes DOM updates.
	 * Only operates when preserve_card_on_update is enabled.
	 */
	function reattachFlowContainer() {
		// Skip if feature is disabled
		if (!preserveCardEnabled) {
			return false;
		}

		if (!detachedFlowContainer) {
			if (typeof ckoLogger !== 'undefined') {
				ckoLogger.debug('[COUPON GUARD] No detached container to reattach');
			}
			return false;
		}

		// Find the placeholder or the new payment_box
		let placeholder = document.getElementById('flow-container-placeholder');
		let targetParent = null;

		if (placeholder && placeholder.parentNode) {
			targetParent = placeholder.parentNode;
			// Remove placeholder and insert Flow container in its place
			targetParent.insertBefore(detachedFlowContainer, placeholder);
			placeholder.remove();
			if (typeof ckoLogger !== 'undefined') {
				ckoLogger.debug('[COUPON GUARD] ✅ Flow container reattached at placeholder location');
			}
		} else {
			// Placeholder gone (WooCommerce replaced parent) - find new payment_box
			const paymentMethod = document.querySelector('.payment_method_wc_checkout_com_flow');
			if (paymentMethod) {
				const paymentBox = paymentMethod.querySelector('.payment_box');
				if (paymentBox) {
					// Clear any new content WooCommerce added
					paymentBox.innerHTML = '';
					paymentBox.id = 'flow-container';
					paymentBox.classList.add('cko-flow__container');
					paymentBox.style.padding = '0';
					
					// Move Flow content into the new payment_box
					while (detachedFlowContainer.firstChild) {
						paymentBox.appendChild(detachedFlowContainer.firstChild);
					}
					
					if (typeof ckoLogger !== 'undefined') {
						ckoLogger.debug('[COUPON GUARD] ✅ Flow content moved to new payment_box (WooCommerce replaced DOM)');
					}
				} else {
					if (typeof ckoLogger !== 'undefined') {
						ckoLogger.error('[COUPON GUARD] ❌ Cannot reattach: payment_box not found');
					}
				}
			} else {
				if (typeof ckoLogger !== 'undefined') {
					ckoLogger.error('[COUPON GUARD] ❌ Cannot reattach: payment method container not found');
				}
			}
		}

		// Clear reference
		detachedFlowContainer = null;
		return true;
	}

	/**
	 * Intercept coupon application to track amount changes.
	 * 
	 * NOTE: When preserve_card_on_update is enabled, WooCommerce no longer replaces 
	 * the payment methods section due to PHP filter (exclude_payment_method_from_fragments). 
	 * This interceptor tracks the coupon action so we can update the pending amount for handleSubmit.
	 * When disabled, Flow will reload normally after coupon apply.
	 */
	function attachCouponInterceptor() {
		if (typeof jQuery === 'undefined') {
			return;
		}

		// Helper function to handle coupon action
		function handleCouponAction(actionName) {
			// Only set flag if preserve card feature is enabled
			if (!preserveCardEnabled) {
				if (typeof ckoLogger !== 'undefined') {
					ckoLogger.debug('[COUPON] Preserve card feature disabled - Flow will reload after ' + actionName);
				}
				return;
			}

			// Check if Flow is initialized
			if (typeof ckoFlow !== 'undefined' && ckoFlow.flowComponent && 
			    typeof FlowState !== 'undefined' && FlowState.get('initialized')) {
				
				window.ckoFlowAmountUpdateInProgress = true;
				if (typeof ckoLogger !== 'undefined') {
					ckoLogger.debug('[COUPON] 🛡️ ' + actionName + ' - marking amount update in progress');
				}
			}
		}

		// Intercept coupon form submission
		jQuery(document).on('click', '.woocommerce-form-coupon .button, [name="apply_coupon"]', function() {
			handleCouponAction('Coupon apply clicked');
		});

		// Also intercept coupon removal
		jQuery(document).on('click', '.woocommerce-remove-coupon', function() {
			handleCouponAction('Coupon remove clicked');
		});

		// Intercept Enter key in coupon field
		jQuery(document).on('keypress', '#coupon_code', function(e) {
			if (e.which === 13) { // Enter key
				handleCouponAction('Enter pressed in coupon field');
			}
		});
		
		// Listen for WooCommerce AJAX completion to update pending amount
		// The payment methods section is preserved (not replaced) by PHP filter,
		// so we just need to update the amount for handleSubmit
		jQuery(document).ajaxComplete(function(event, xhr, settings) {
			// Only handle WooCommerce checkout AJAX requests
			if (!settings.url || settings.url.indexOf('wc-ajax') === -1) {
				return;
			}
			
			// Check if this is a checkout update or coupon action
			const isCouponAction = settings.url.indexOf('apply_coupon') !== -1 || 
			                       settings.url.indexOf('remove_coupon') !== -1 ||
			                       settings.url.indexOf('update_order_review') !== -1;
			
			if (!isCouponAction) {
				return;
			}
			
		// Update pending amount and items after coupon operations
		if (window.ckoFlowAmountUpdateInProgress) {
			// Wait for WooCommerce to finish updating the DOM with new totals
			setTimeout(function() {
				const currentOrderAmount = getCurrentOrderTotalFromDOM();
				
				if (currentOrderAmount && typeof ckoFlow !== 'undefined') {
					// Check if current payment type is an APM that doesn't support amount updates
					// Only Card, Apple Pay, and Google Pay support dynamic amount adjustment
					const paymentType = ckoFlow.selectedPaymentType?.toLowerCase() || '';
					const typesWithAmountSupport = ['card', 'applepay', 'googlepay'];
					const isAPMWithoutSupport = paymentType && !typesWithAmountSupport.includes(paymentType);
					const amountChanged = ckoFlow.initialSessionAmount !== null && 
						currentOrderAmount !== ckoFlow.initialSessionAmount;
					
					if (isAPMWithoutSupport && amountChanged) {
						// APM selected that doesn't support amount updates - trigger reload
						if (typeof ckoLogger !== 'undefined') {
							ckoLogger.debug('[COUPON GUARD] APM detected without amount support:', paymentType);
							ckoLogger.debug('[COUPON GUARD] Triggering Flow reload for amount change');
						}
						
						// Don't set pending amount - we're reloading
						ckoFlow.pendingAmountUpdate = null;
						
						// Trigger Flow reload
						setTimeout(function() {
							if (typeof reloadFlowComponent === 'function') {
								reloadFlowComponent();
							} else if (typeof window.reloadFlowComponent === 'function') {
								window.reloadFlowComponent();
							}
						}, 100);
					} else {
						// Card/Apple Pay/Google Pay or no payment selected - use pending amount
						ckoFlow.pendingAmountUpdate = currentOrderAmount;
						if (typeof ckoLogger !== 'undefined') {
							ckoLogger.debug('[COUPON GUARD] ✅ Updated pendingAmountUpdate after coupon action:', currentOrderAmount);
						}
						
						// CRITICAL: For Apple Pay and Google Pay, also call checkout.update() 
						// to update the payment sheet amount shown to the customer
						if ((paymentType === 'applepay' || paymentType === 'googlepay') && 
							ckoFlow.checkoutInstance && 
							typeof ckoFlow.checkoutInstance.update === 'function') {
							ckoFlow.checkoutInstance.update({ amount: currentOrderAmount })
								.then(function() {
									if (typeof ckoLogger !== 'undefined') {
										ckoLogger.debug('[COUPON GUARD] ✅ checkout.update() succeeded for', paymentType);
									}
								})
								.catch(function(error) {
									if (typeof ckoLogger !== 'undefined') {
										ckoLogger.debug('[COUPON GUARD] checkout.update() failed:', error?.message || 'unknown');
									}
								});
						}
					}
				}
				
				// Clear flag
				window.ckoFlowAmountUpdateInProgress = false;
				if (typeof ckoLogger !== 'undefined') {
					ckoLogger.debug('[COUPON GUARD] ✅ Amount update complete - flag cleared');
				}
			}, 500); // Increased delay to ensure WooCommerce DOM updates are complete
		}
		});
	}
	
	/**
	 * Handle reattachment after WooCommerce finishes DOM updates.
	 * Called from the main updated_checkout handler.
	 * Only operates when preserve_card_on_update is enabled.
	 */
	function handleReattachmentIfNeeded() {
		// Skip if feature is disabled
		if (!preserveCardEnabled) {
			return false;
		}

		if (typeof ckoLogger !== 'undefined') {
			ckoLogger.debug('[COUPON GUARD] handleReattachmentIfNeeded called', {
				amountUpdateInProgress: window.ckoFlowAmountUpdateInProgress,
				hasDetachedContainer: !!detachedFlowContainer
			});
		}
		
		if (!window.ckoFlowAmountUpdateInProgress) {
			return false;
		}
		
		if (!detachedFlowContainer) {
			if (typeof ckoLogger !== 'undefined') {
				ckoLogger.debug('[COUPON GUARD] Amount update in progress but no detached container - skipping reattach');
			}
			return false;
		}
		
		if (typeof ckoLogger !== 'undefined') {
			ckoLogger.debug('[COUPON GUARD] 🔄 Starting reattachment process...');
		}
		
		// Wait a tick for WooCommerce to finish DOM updates
		setTimeout(function() {
			const success = reattachFlowContainer();
			
			if (success) {
				// Update pending amount for handleSubmit
				const currentOrderAmount = getCurrentOrderTotalFromDOM();
				
				if (currentOrderAmount && typeof ckoFlow !== 'undefined') {
					// Check if current payment type is an APM that doesn't support amount updates
					const paymentType = ckoFlow.selectedPaymentType?.toLowerCase() || '';
					const typesWithAmountSupport = ['card', 'applepay', 'googlepay'];
					const isAPMWithoutSupport = paymentType && !typesWithAmountSupport.includes(paymentType);
					const amountChanged = ckoFlow.initialSessionAmount !== null && 
						currentOrderAmount !== ckoFlow.initialSessionAmount;
					
					if (isAPMWithoutSupport && amountChanged) {
						// APM selected - trigger reload instead of setting pending amount
						if (typeof ckoLogger !== 'undefined') {
							ckoLogger.debug('[COUPON GUARD] APM detected in reattachment - triggering reload');
						}
						ckoFlow.pendingAmountUpdate = null;
						setTimeout(function() {
							if (typeof reloadFlowComponent === 'function') {
								reloadFlowComponent();
							} else if (typeof window.reloadFlowComponent === 'function') {
								window.reloadFlowComponent();
							}
						}, 100);
					} else {
						ckoFlow.pendingAmountUpdate = currentOrderAmount;
						if (typeof ckoLogger !== 'undefined') {
							ckoLogger.debug('[COUPON GUARD] Updated pendingAmountUpdate:', currentOrderAmount);
						}
						
						// CRITICAL: For Apple Pay and Google Pay, also call checkout.update()
						if ((paymentType === 'applepay' || paymentType === 'googlepay') && 
							ckoFlow.checkoutInstance && 
							typeof ckoFlow.checkoutInstance.update === 'function') {
							ckoFlow.checkoutInstance.update({ amount: currentOrderAmount })
								.then(function() {
									if (typeof ckoLogger !== 'undefined') {
										ckoLogger.debug('[COUPON GUARD] ✅ checkout.update() succeeded for', paymentType);
									}
								})
								.catch(function(error) {
									if (typeof ckoLogger !== 'undefined') {
										ckoLogger.debug('[COUPON GUARD] checkout.update() failed:', error?.message || 'unknown');
									}
								});
						}
					}
				}
			}
			
			// Clear flag after reattachment attempt (success or fail)
			setTimeout(function() {
				window.ckoFlowAmountUpdateInProgress = false;
				if (typeof ckoLogger !== 'undefined') {
					ckoLogger.debug('[COUPON GUARD] ✅ Amount update complete - flag cleared');
				}
			}, 500);
		}, 150);
		
		return true;
	}

	function attachHandler() {
		if (typeof jQuery === 'undefined') {
			return;
		}

		// Attach coupon interceptor
		attachCouponInterceptor();

		// Remove any existing handler first
		jQuery(document).off('updated_checkout.cko-terms-prevention');
		jQuery(document.body).off('updated_checkout.cko-flow-guard');

		// Attach handler to BOTH body and document.body to ensure we catch the event
		// WooCommerce fires updated_checkout on $(document.body)
		jQuery(document.body).on('updated_checkout.cko-flow-guard', function (event) {
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
			
			// CRITICAL: Handle Flow container reattachment if a coupon operation detached it
			// This must happen FIRST, before any other updated_checkout logic
			if (handleReattachmentIfNeeded()) {
				// Flow was detached and is being reattached - skip all other processing
				// The reattachment handler will update the pending amount
				if (debugEnabled) {
					ckoLogger.debug('[UPDATED_CHECKOUT] Flow reattachment in progress - skipping other handlers');
				}
				return;
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
				ckoLogger.debug('🚫 updated_checkout triggered by terms checkbox - skipping Flow-specific handling only', {
					preventionFlag: window.ckoPreventUpdateCheckout,
					lastClickedId: lastClicked ? lastClicked.id || 'no-id' : 'none',
					activeElementId: activeElement ? activeElement.id || 'no-id' : 'none',
					timeSinceClick: timeSinceLastClick + 'ms'
				});

				// Clear tracking after a delay
				setTimeout(function () {
					window.ckoPreventUpdateCheckout = false;
					window.ckoTermsCheckboxLastClicked = null;
				}, 3000);

				// NOTE: We no longer block the event propagation - this was interfering with 
				// WooCommerce's normal checkout flow and preventing the loading overlay from being removed.
				// We just skip our Flow-specific handling and let WooCommerce continue normally.
				return;
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
				flowInitialized: FlowState.get('initialized')
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

			// Check if cart total changed (e.g. coupon applied/removed).
			// Use helper function to read the most up-to-date value from DOM.
			// order_amount is in cents (minor units).
			const currentOrderAmount = getCurrentOrderTotalFromDOM();

			if (currentOrderAmount !== null && previousOrderAmount !== null && currentOrderAmount !== previousOrderAmount) {
				if (typeof ckoLogger !== 'undefined') {
					ckoLogger.debug('[CART CHANGE] Cart total changed (e.g. coupon applied/removed)', {
						previous: previousOrderAmount,
						current: currentOrderAmount
					});
				}

				previousOrderAmount = currentOrderAmount;

				// CRITICAL: Clear existing order data when cart changes.
				// This ensures a NEW order is created with the correct amount.
				// Prevents reusing an order created with the old (wrong) amount.
				const orderIdField = jQuery('input[name="order_id"]');
				if (orderIdField.length && orderIdField.val()) {
					const oldOrderId = orderIdField.val();
					if (typeof ckoLogger !== 'undefined') {
						ckoLogger.debug('[CART CHANGE] Clearing stale order_id from form: ' + oldOrderId + ' (will create new order with correct amount)');
					}
					orderIdField.val('');
				}

				// Also clear from session storage
				if (typeof FlowSessionStorage !== 'undefined') {
					const sessionOrderId = FlowSessionStorage.getOrderId();
					if (sessionOrderId) {
						if (typeof ckoLogger !== 'undefined') {
							ckoLogger.debug('[CART CHANGE] Clearing stale order_id from session storage: ' + sessionOrderId);
						}
						FlowSessionStorage.clearOrderData();
					}
				}

				// DYNAMIC AMOUNT ADJUSTMENT: Instead of reloading Flow component,
				// store the new amount for handleSubmit to use when payment is submitted.
				// This preserves card details and avoids component reload.
				// Works for card, Apple Pay, and Google Pay payments.
				
				// CRITICAL FIX: Set flag to prevent flow-container-ready-handler from remounting
				// This flag tells other modules that we're handling a cart change with dynamic amount update
				// so they should NOT reinitialize Flow (which would destroy card details)
				if (typeof ckoFlow !== 'undefined' && ckoFlow.checkoutInstance) {
					window.ckoFlowAmountUpdateInProgress = true;
					if (typeof ckoLogger !== 'undefined') {
						ckoLogger.debug('[CART CHANGE] 🛡️ Setting ckoFlowAmountUpdateInProgress flag to prevent Flow reload');
					}
				}

				setTimeout(function () {
					if (typeof FlowState !== 'undefined' && FlowState.get('initialized') &&
						typeof ckoFlow !== 'undefined' && ckoFlow.flowComponent) {
						
						// Check if checkout instance supports dynamic updates
						if (ckoFlow.checkoutInstance && typeof ckoFlow.checkoutInstance.update === 'function') {
							if (typeof ckoLogger !== 'undefined') {
								ckoLogger.debug('[CART CHANGE] 🚀 Using dynamic amount update (no reload needed)', {
									previousAmount: ckoFlow.initialSessionAmount,
									newAmount: currentOrderAmount,
									hasCheckoutInstance: true
								});
							}

							// Store pending amount for handleSubmit callback
							ckoFlow.pendingAmountUpdate = currentOrderAmount;

							// Try to update payment sheet for Apple Pay/Google Pay (client-side update)
							// This updates the visual amount shown in wallet payment sheets
							try {
								ckoFlow.checkoutInstance.update({
									amount: currentOrderAmount
								}).then(function() {
									if (typeof ckoLogger !== 'undefined') {
										ckoLogger.debug('[CART CHANGE] ✅ checkout.update() succeeded - payment sheet updated');
									}
								}).catch(function(error) {
									// checkout.update() may not be supported for all payment types
									// That's OK - handleSubmit will still use the updated amount
									if (typeof ckoLogger !== 'undefined') {
										ckoLogger.debug('[CART CHANGE] checkout.update() not applicable (expected for card payments)', {
											error: error?.message || 'unknown'
										});
									}
								});
							} catch (error) {
								// Silently handle - checkout.update is primarily for wallet payment sheets
								if (typeof ckoLogger !== 'undefined') {
									ckoLogger.debug('[CART CHANGE] checkout.update() threw error (expected for some payment types)', {
										error: error?.message || 'unknown'
									});
								}
							}

							// Clear the flag after a delay to allow container-ready handler to skip
							setTimeout(function() {
								window.ckoFlowAmountUpdateInProgress = false;
								if (typeof ckoLogger !== 'undefined') {
									ckoLogger.debug('[CART CHANGE] 🛡️ Cleared ckoFlowAmountUpdateInProgress flag');
								}
							}, 2000);
							
						} else if (typeof canInitializeFlow === 'function' && canInitializeFlow()) {
							// Fallback: If checkout instance not available, use old reload behavior
							window.ckoFlowAmountUpdateInProgress = false;
							if (typeof reloadFlowComponent === 'function') {
								if (typeof ckoLogger !== 'undefined') {
									ckoLogger.debug('[CART CHANGE] Fallback: Reloading Flow component (checkoutInstance not available)');
								}
								reloadFlowComponent();
							}
						}
					} else {
						// Flow not initialized, clear the flag
						window.ckoFlowAmountUpdateInProgress = false;
					}
				}, 300);
			} else if (currentOrderAmount !== null) {
				previousOrderAmount = currentOrderAmount;
			}

			ckoLogger.debug('updated_checkout event fired');
			ckoLogger.debug('Flow state BEFORE updated_checkout:', {
				flowInitialized: FlowState.get('initialized'),
				flowComponentExists: !!ckoFlow.flowComponent,
				flowComponentRootExists: !!document.querySelector('[data-testid="checkout-web-component-root"]'),
				flowContainerExists: !!document.getElementById('flow-container'),
				flowPaymentChecked: document.getElementById('payment_method_wc_checkout_com_flow')?.checked || false
			});

			// EVENT-DRIVEN: Flow remounting is now handled by cko:flow-container-ready event listener
			// No need for setTimeout delays - flow-container.js will emit event when container is ready
			
			// CRITICAL: Remove WooCommerce loading overlay after updated_checkout completes
			// This ensures the checkout is interactive after coupon operations
			// Use a small delay to allow WooCommerce to finish its AJAX response handling
			setTimeout(function() {
				if (typeof jQuery !== 'undefined') {
					jQuery('.woocommerce').removeClass('processing').unblock();
					jQuery('.woocommerce-checkout').removeClass('processing').unblock();
					jQuery('form.checkout').removeClass('processing').unblock();
					jQuery('.blockOverlay').remove();
					ckoLogger.debug('[UPDATED_CHECKOUT] ✓ WooCommerce loading overlay removed after update');
				}
			}, 500);
		});
	}

	// Global overlay removal function - can be called from anywhere
	function removeWooCommerceOverlay() {
		if (typeof jQuery !== 'undefined') {
			jQuery('.woocommerce').removeClass('processing').unblock();
			jQuery('.woocommerce-checkout').removeClass('processing').unblock();
			jQuery('form.checkout').removeClass('processing').unblock();
			jQuery('.blockOverlay').remove();
		}
	}

	// Setup global AJAX complete handler to always remove overlay after checkout AJAX
	function setupAjaxOverlayHandler() {
		if (typeof jQuery === 'undefined') return;
		
		jQuery(document).ajaxComplete(function(event, xhr, settings) {
			// Only handle WooCommerce checkout related AJAX
			if (settings && settings.url && 
				(settings.url.indexOf('wc-ajax') !== -1 || 
				 settings.url.indexOf('update_order_review') !== -1 ||
				 settings.url.indexOf('apply_coupon') !== -1 ||
				 settings.url.indexOf('remove_coupon') !== -1)) {
				
				ckoLogger.debug('[AJAX COMPLETE] Checkout AJAX finished, removing overlay', {
					url: settings.url
				});
				
				// Remove overlay after a small delay to let WooCommerce finish processing
				setTimeout(removeWooCommerceOverlay, 100);
				// And again after a longer delay as backup
				setTimeout(removeWooCommerceOverlay, 500);
			}
		});
		
		ckoLogger.debug('[OVERLAY GUARD] AJAX complete handler attached');
	}

	window.FlowUpdatedCheckoutGuard = {
		init: function () {
			if (initialized) {
				return;
			}
			initialized = true;
			attachHandler();
			setupAjaxOverlayHandler();
		},
		removeOverlay: removeWooCommerceOverlay,
		getCurrentOrderTotalFromDOM: getCurrentOrderTotalFromDOM
	};
})();
