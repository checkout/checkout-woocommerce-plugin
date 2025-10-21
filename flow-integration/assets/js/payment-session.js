/*
 * Centralized logging utility for Checkout.com Flow integration.
 * Controls what logs appear in production vs debug mode.
 */
var ckoLogger = {
	debugEnabled: (typeof cko_flow_vars !== 'undefined' && cko_flow_vars.debug_logging) || false,
	
	// ALWAYS VISIBLE (Production + Debug) - Critical for payment troubleshooting
	error: function(message, data) {
		console.error('[FLOW ERROR] ' + message, data !== undefined ? data : '');
	},
	
	warn: function(message, data) {
		console.warn('[FLOW WARNING] ' + message, data !== undefined ? data : '');
	},
	
	webhook: function(message, data) {
		console.log('[FLOW WEBHOOK] ' + message, data !== undefined ? data : '');
	},
	
	threeDS: function(message, data) {
		console.log('[FLOW 3DS] ' + message, data !== undefined ? data : '');
	},
	
	payment: function(message, data) {
		console.log('[FLOW PAYMENT] ' + message, data !== undefined ? data : '');
	},
	
	version: function(version) {
		console.log('🚀 Checkout.com Flow v' + version);
	},
	
	// DEBUG ONLY (Hidden in Production) - Enable via "Debug Logging" setting
	debug: function(message, data) {
		if (this.debugEnabled) {
			console.log('[FLOW DEBUG] ' + message, data !== undefined ? data : '');
		}
	},
	
	performance: function(message, data) {
		if (this.debugEnabled) {
			console.log('[FLOW PERFORMANCE] ' + message, data !== undefined ? data : '');
		}
	}
};

/*
 * The main object managing the Checkout.com flow payment integration.
 */
var ckoFlow = {
	flowComponent: null, // Holds the reference to the Checkout Web Component.
	performanceMetrics: {
		pageLoadTime: null,
		flowInitStartTime: null,
		flowReadyTime: null,
	},

	/*
	 * Initializes the payment flow by loading the component.
	 */
	init: () => {
		// Mark when Flow initialization starts
		ckoFlow.performanceMetrics.flowInitStartTime = performance.now();
		ckoLogger.performance('Flow initialization started');
		ckoFlow.loadFlow();
	},

	/*
	 * Loads the Checkout.com payment flow by collecting cart and user info,
	 * creating a payment session, and mounting the Checkout component.
	 */
	loadFlow: async () => {
		// Check if cko_flow_vars is available
		if (typeof cko_flow_vars === 'undefined') {
			ckoLogger.error('cko_flow_vars is not defined. Flow cannot be initialized.');
			return;
		}
		
		ckoLogger.version('2025-10-13-FINAL-E2E');
		
		// Check if we're on a redirect page with payment parameters - if so, don't initialize Flow
		const urlParams = new URLSearchParams(window.location.search);
		const paymentId = urlParams.get("cko-payment-id");
		const sessionId = urlParams.get("cko-session-id");
		const status = urlParams.get("status");
		
	if ((paymentId || sessionId) && status === 'succeeded') {
		ckoLogger.threeDS('3DS redirect completed - Processing payment', {
			paymentId: paymentId,
			sessionId: sessionId,
			status: status
		});
		
		// Set the payment ID in the hidden input
		const paymentIdInput = document.getElementById('cko-flow-payment-id');
		if (paymentIdInput && paymentId) {
			paymentIdInput.value = paymentId;
			ckoLogger.debug('Set payment ID in hidden input:', paymentId);
		}
		
		// Set payment type to card (most common)
		const paymentTypeInput = document.getElementById('cko-flow-payment-type');
		if (paymentTypeInput) {
			paymentTypeInput.value = 'card';
			ckoLogger.debug('Set payment type to: card');
		}
		
		// Submit the checkout form to complete the order
		setTimeout(() => {
			const checkoutForm = document.querySelector('form.checkout');
			const orderPayForm = document.querySelector('form#order_review');
			
			if (checkoutForm) {
				ckoLogger.threeDS('Submitting checkout form to complete order');
				jQuery(checkoutForm).submit();
			} else if (orderPayForm) {
				ckoLogger.threeDS('Submitting order-pay form to complete order');
				jQuery(orderPayForm).submit();
			} else {
				ckoLogger.error('No checkout or order-pay form found after 3DS redirect');
			}
		}, 500); // Small delay to ensure DOM is ready
		
		return; // Don't initialize Flow component
	}
		
		// Show skeleton loader and disable place order button
		const skeleton = document.getElementById("flow-skeleton");
		const placeOrderBtn = document.getElementById("place_order");
		
		if (skeleton) {
			skeleton.classList.add("show");
		}
		if (placeOrderBtn) {
			placeOrderBtn.classList.add("flow-loading");
		}
		
		let cartInfo = jQuery("#cart-info").data("cart");

		if ( ! cartInfo || jQuery.isEmptyObject( cartInfo ) ) {
			cartInfo = jQuery("#order-pay-info").data("order-pay");
		}

		/*
		 * Extract information from cartInfo or fallback to DOM form inputs.
		 */
		let amount = cartInfo["order_amount"];
		let currency = cartInfo["purchase_currency"];

		let reference = "WOO" + (cko_flow_vars.ref_session || 'default');

		let email =
			cartInfo["billing_address"]["email"] ||
			(document.getElementById("billing_email") ? document.getElementById("billing_email").value : '');
		let family_name =
			cartInfo["billing_address"]["family_name"] ||
			(document.getElementById("billing_last_name") ? document.getElementById("billing_last_name").value : '');
		let given_name =
			cartInfo["billing_address"]["given_name"] ||
			(document.getElementById("billing_first_name") ? document.getElementById("billing_first_name").value : '');
		let phone =
			cartInfo["billing_address"]["phone"] ||
			(document.getElementById("billing_phone") ? document.getElementById("billing_phone").value : '');

		let address1 = shippingAddress1 = cartInfo["billing_address"]["street_address"];
		let address2 = shippingAddress2 = cartInfo["billing_address"]["street_address2"];
		let city = shippingCity = cartInfo["billing_address"]["city"];
		let zip = shippingZip = cartInfo["billing_address"]["postal_code"];
		let country = shippingCountry = cartInfo["billing_address"]["country"];
			

		let shippingElement = document.getElementById("ship-to-different-address-checkbox");
		if ( shippingElement?.checked ) {
			shippingAddress1 = cartInfo["shipping_address"]["street_address"];
			shippingAddress2 = cartInfo["shipping_address"]["street_address2"];
			shippingCity = cartInfo["shipping_address"]["city"];
			shippingZip = cartInfo["shipping_address"]["postal_code"];
			shippingCountry = cartInfo["shipping_address"]["country"];
		}

		let orders = cartInfo["order_lines"];

		let products = cartInfo["order_lines"]
			.map(line => line.name)
			.join(', ');

		let description = 'Payment from ' + cko_flow_vars.site_url + ' for [ ' +  products + ' ]';

		let orderId = cartInfo["order_id"];
		ckoLogger.debug('Initial orderId from cartInfo:', orderId);
		ckoLogger.debug('Current URL pathname:', window.location.pathname);
		ckoLogger.debug('Current URL search:', window.location.search);
		
		// For MOTO orders (order-pay page), get order ID from URL path if not in cartInfo
		if ( ! orderId && window.location.pathname.includes('/order-pay/') ) {
			// Extract order ID from URL path like /order-pay/4127/
			const pathMatch = window.location.pathname.match(/\/order-pay\/(\d+)\//);
			orderId = pathMatch ? pathMatch[1] : null;
			ckoLogger.debug('MOTO order detected - Order ID from URL path:', orderId);
		}
		
		ckoLogger.debug('Final orderId:', orderId);

		let payment_type = cko_flow_vars.regular_payment_type;

		let metadata = {
			udf5: cko_flow_vars.udf5,
		}

		if ( orderId ) {
			metadata = {
				udf5: cko_flow_vars.udf5,
				order_id: orderId,
			}

			payment_type = cartInfo["payment_type"];
		}

		// Check for subscription product.
		let containsSubscriptionProduct = cartInfo["contains_subscription_product"];

		if ( containsSubscriptionProduct ) {
			const isSubscription = orders.some(order => order.is_subscription === true);
			if ( isSubscription ) {
				payment_type = cko_flow_vars.recurring_payment_type;
			}
		}

		// Remove is_subscription from all orders.
		orders.forEach(order => {
			delete order.is_subscription;
		});

		/*
		 * Helper to get a field value by ID from the DOM.
		 */
		function getCheckoutField(fieldId) {
			const el = document.getElementById(fieldId);
			return el && el.value ? el.value : null;
		}

		if (!email) {
			email = getCheckoutField("billing_email");
		}

		if (!family_name) {
			family_name = getCheckoutField("billing_first_name");
		}

		if (!given_name) {
			given_name = getCheckoutField("billing_last_name");
		}

		if (!phone) {
			phone = getCheckoutField("billing_phone");
		}

		/*
		 * Displays the loading overlay.
		 */
		function showLoadingOverlay(arg) {
			let overlay = document.getElementById("loading-overlay");
			if (arg === 2) {
				overlay = document.getElementById("loading-overlay2");
			}
			if (overlay) {
				overlay.style.display = "flex";
			}
		}

		/*
		 * Hides the loading overlay.
		 */
		function hideLoadingOverlay(arg) {
			let overlay = document.getElementById("loading-overlay");
			if (arg === 2) {
				overlay = document.getElementById("loading-overlay2");
			}
			if (overlay) {
				overlay.style.display = "none";
			}
		}

		try {
			showLoadingOverlay();

			// Performance: Track API request start
			const apiRequestStart = performance.now();
			if (ckoFlow.performanceMetrics.enableLogging) {
				ckoLogger.performance('Starting payment session API request...');
			}

			/*
			 * Send request to Checkout.com to create a payment session.
			 */
			// Debug: Log 3DS configuration
			// Ensure proper boolean values - convert any truthy/falsy values to strict booleans
			const three_ds_enabled = Boolean(cko_flow_vars.three_d_enabled === true || cko_flow_vars.three_d_enabled === "true" || cko_flow_vars.three_d_enabled === 1 || cko_flow_vars.three_d_enabled === "1");
			const attempt_n3d = Boolean(cko_flow_vars.attempt_no_three_d === true || cko_flow_vars.attempt_no_three_d === "true" || cko_flow_vars.attempt_no_three_d === 1 || cko_flow_vars.attempt_no_three_d === "1");
			
			// Valid exemption values according to Checkout.com API
			const valid_exemptions = [
				"low_value", "trusted_listing", "trusted_listing_prompt", 
				"transaction_risk_assessment", "3ds_outage", "sca_delegation", 
				"out_of_sca_scope", "low_risk_program", "recurring_operation", 
				"data_share", "other"
			];
			
			// Use valid exemption or default to null (no exemption)
			const exemption = (cko_flow_vars.exemption && valid_exemptions.includes(cko_flow_vars.exemption)) ? cko_flow_vars.exemption : null;
			
			ckoLogger.debug('Exemption Processing:', {
				'Raw exemption from backend': cko_flow_vars.exemption,
				'Type of raw exemption': typeof cko_flow_vars.exemption,
				'Is exemption truthy': !!cko_flow_vars.exemption,
				'Is exemption valid': cko_flow_vars.exemption && valid_exemptions.includes(cko_flow_vars.exemption),
				'Processed exemption': exemption,
				'Will exemption be included': !!exemption,
				'Valid exemptions list': valid_exemptions
			});
			
			ckoLogger.threeDS('3DS Configuration (field name: "3ds"):', {
				'Raw three_d_enabled': cko_flow_vars.three_d_enabled,
				'Type of three_d_enabled': typeof cko_flow_vars.three_d_enabled,
				'Processed enabled': three_ds_enabled,
				'Type of processed enabled': typeof three_ds_enabled,
				'Raw attempt_no_three_d': cko_flow_vars.attempt_no_three_d,
				'Type of attempt_no_three_d': typeof cko_flow_vars.attempt_no_three_d,
				'Processed attempt_n3d': attempt_n3d,
				'Type of processed attempt_n3d': typeof attempt_n3d,
				challenge_indicator: cko_flow_vars.challenge_indicator || "no_preference",
				'Raw exemption': cko_flow_vars.exemption,
				'Processed exemption': exemption,
				'Raw allow_upgrade': cko_flow_vars.allow_upgrade,
				'Type of allow_upgrade': typeof cko_flow_vars.allow_upgrade,
				allow_upgrade: cko_flow_vars.allow_upgrade === true || cko_flow_vars.allow_upgrade === "true" || cko_flow_vars.allow_upgrade === "yes",
			});

			// Debug: Log complete payment session request
			const paymentSessionRequest = {
				amount: amount,
				currency: currency,
				reference: reference,
				payment_type: payment_type,
				description: description,
				customer: {
					name: `${given_name} ${family_name}`,
					email: email,
				},
				billing: {
					address: {
						address_line1: address1,
						address_line2: address2,
						city: city,
						zip: zip,
						country: country,
					},
				},
				shipping: {
					address: {
						address_line1: shippingAddress1,
						address_line2: shippingAddress2,
						city: shippingCity,
						zip: shippingZip,
						country: shippingCountry,
					},
				},
				// Determine success and failure URLs based on current page
				success_url: (() => {
					const isOrderPayPage = window.location.pathname.includes('/order-pay/');
					if (isOrderPayPage) {
						// For order-pay pages, redirect to order-received page with order key
						const orderId = window.location.pathname.match(/\/order-pay\/(\d+)\//)?.[1];
						const orderKey = new URLSearchParams(window.location.search).get('key');
						if (orderId && orderKey) {
							return window.location.origin + "/checkout/order-received/" + orderId + "/?key=" + orderKey;
						} else {
							// Fallback to checkout page if order details not found
							return window.location.origin + "/" + cko_flow_vars.checkoutSlug + "/?status=succeeded&from=order-pay";
						}
					} else {
						// For regular checkout pages
						return window.location.origin + "/" + cko_flow_vars.checkoutSlug + "/?status=succeeded";
					}
				})(),
				failure_url: (() => {
					const isOrderPayPage = window.location.pathname.includes('/order-pay/');
					if (isOrderPayPage) {
						// For order-pay pages, redirect to order-received page with order key
						const orderId = window.location.pathname.match(/\/order-pay\/(\d+)\//)?.[1];
						const orderKey = new URLSearchParams(window.location.search).get('key');
						if (orderId && orderKey) {
							return window.location.origin + "/checkout/order-received/" + orderId + "/?key=" + orderKey;
						} else {
							// Fallback to checkout page if order details not found
							return window.location.origin + "/" + cko_flow_vars.checkoutSlug + "/?status=failed&from=order-pay";
						}
					} else {
						// For regular checkout pages
						return window.location.origin + "/" + cko_flow_vars.checkoutSlug + "/?status=failed";
					}
				})(),
				metadata: metadata,
				payment_method_configuration: {
					card: {
						store_payment_details: "enabled",
					},
				},
				capture: true,
				items: orders,
				integration: {
					external_platform: {
						name: "Woocomerce",
						version: cko_flow_vars.woo_version,
					},
				},
				"3ds": {
					enabled: three_ds_enabled,
					attempt_n3d: attempt_n3d,
					challenge_indicator: cko_flow_vars.challenge_indicator || "no_preference",
					...(exemption && { exemption: exemption }),
					allow_upgrade: cko_flow_vars.allow_upgrade === true || cko_flow_vars.allow_upgrade === "true" || cko_flow_vars.allow_upgrade === "yes",
				},
			};
			
			// Check if this is a MOTO order (admin-created order + order-pay page + guest customer)
			const orderPayInfo = jQuery("#order-pay-info")?.data("order-pay");
			const isMotoOrder = orderPayInfo?.payment_type === 'MOTO';
			
			ckoLogger.debug('MOTO Detection - Payment Type:', orderPayInfo?.payment_type, 'Is MOTO:', isMotoOrder);
			
			// Override payment type for MOTO orders
			if (isMotoOrder) {
				payment_type = 'MOTO';
				ckoLogger.debug('MOTO order detected - setting payment_type to MOTO');
				ckoLogger.debug('MOTO conditions: Admin order + Order-pay page + Guest customer');
			} else {
				ckoLogger.debug('Not a MOTO order - using regular payment type:', payment_type);
			}
			
			// Update payment type in the request
			paymentSessionRequest.payment_type = payment_type;
			
			// Simple implementation: For order-pay pages, always redirect to order-received page after 3DS
			const isOrderPayPage = window.location.pathname.includes('/order-pay/');
			if (isOrderPayPage) {
				// Extract order ID and key from current order-pay URL
				const orderId = window.location.pathname.match(/\/order-pay\/(\d+)\//)?.[1];
				const orderKey = new URLSearchParams(window.location.search).get('key');
				
			if (orderId && orderKey) {
				// FIXED: Redirect back to order-pay page after 3DS so form can be submitted to process_payment()
				// This is critical for card saving to work - the form submission triggers process_payment() where card saving logic lives
				const orderPayReturnUrl = window.location.origin + "/checkout/order-pay/" + orderId + "/?pay_for_order=true&key=" + orderKey;
				paymentSessionRequest.success_url = orderPayReturnUrl;
				paymentSessionRequest.failure_url = orderPayReturnUrl;
				ckoLogger.threeDS('🎯 Order-pay 3DS will redirect BACK to order-pay page for form submission');
				ckoLogger.debug('Order ID:', orderId);
				ckoLogger.debug('Order Key:', orderKey);
				ckoLogger.debug('Success URL:', paymentSessionRequest.success_url);
				ckoLogger.debug('Failure URL:', paymentSessionRequest.failure_url);
				} else {
					ckoLogger.error('❌ ERROR: Could not extract order ID or key from order-pay URL');
					ckoLogger.error('Current URL:', window.location.href);
					ckoLogger.error('Order ID found:', orderId);
					ckoLogger.error('Order Key found:', orderKey);
					// Fallback to checkout page
					paymentSessionRequest.success_url = window.location.origin + "/" + cko_flow_vars.checkoutSlug + "/?status=succeeded&from=order-pay";
					paymentSessionRequest.failure_url = window.location.origin + "/" + cko_flow_vars.checkoutSlug + "/?status=failed&from=order-pay";
				}
			} else {
				// For regular checkout, use the standard URLs
				paymentSessionRequest.success_url = window.location.origin + "/" + cko_flow_vars.checkoutSlug + "/?status=succeeded";
				paymentSessionRequest.failure_url = window.location.origin + "/" + cko_flow_vars.checkoutSlug + "/?status=failed";
				ckoLogger.debug('Regular checkout - using standard success/failure URLs');
			}
			
			// Add enabled_payment_methods if specified by merchant
			// Only send if array has items (empty array means show all methods)
			// For MOTO orders, hardcode to only show card payment method
			if (isMotoOrder) {
				ckoLogger.debug('MOTO order detected - hardcoding to show only card payment method');
				paymentSessionRequest.enabled_payment_methods = ['card'];
				ckoLogger.debug('MOTO: Set enabled_payment_methods to:', paymentSessionRequest.enabled_payment_methods);
			} else if (cko_flow_vars.enabled_payment_methods && 
			    Array.isArray(cko_flow_vars.enabled_payment_methods) && 
			    cko_flow_vars.enabled_payment_methods.length > 0) {
				paymentSessionRequest.enabled_payment_methods = cko_flow_vars.enabled_payment_methods;
				ckoLogger.debug('Regular: Enabled payment methods (sent to API):', cko_flow_vars.enabled_payment_methods);
			} else {
				ckoLogger.debug('No payment methods filter - all available methods will be shown');
				ckoLogger.debug('cko_flow_vars.enabled_payment_methods:', cko_flow_vars.enabled_payment_methods);
			}
			
			ckoLogger.debug('Complete Payment Session Request:', paymentSessionRequest);

			let response = await fetch(cko_flow_vars.apiURL, {
				method: "POST",
				headers: {
					Authorization: `Bearer ${cko_flow_vars.SKey}`,
					"Content-Type": "application/json",
				},
				body: JSON.stringify(paymentSessionRequest),
			});

			let paymentSession = await response.json();

			// Performance: Track API request completion
			const apiRequestEnd = performance.now();
			const apiDuration = apiRequestEnd - apiRequestStart;
			if (ckoFlow.performanceMetrics.enableLogging) {
				ckoLogger.performance(`Payment session API completed in ${apiDuration.toFixed(2)}ms (${(apiDuration / 1000).toFixed(2)}s)`);
			}

			// Debug: Log payment session response
			ckoLogger.payment('Payment Session Response:', paymentSession);
			
			// Store payment session ID globally for access in callbacks
			window.currentPaymentSessionId = paymentSession.id;

			/*
			 * Handle API Session errors returned from Checkout.com.
			 */
			if (paymentSession.error_type) {
				// Hide loading overlay.
				hideLoadingOverlay();

				// Check for 3DS specific errors
				if (paymentSession.error_codes && paymentSession.error_codes.includes('three_ds_invalid')) {
					ckoLogger.error('3DS Configuration Error:', paymentSession);
					showError('3DS configuration error. Please check your 3DS settings in the admin panel.');
					return;
				}

				const readableErrors = {
					customer_email_invalid: wp.i18n.__(
						"Please enter the email to proceed.",
						"checkout-com-unified-payments-api"
					),
					billing_phone_number_invalid: wp.i18n.__(
						"Please enter the phone number to proceed.",
						"checkout-com-unified-payments-api"
					),
				};

				let messages = (paymentSession.error_codes || []).map(
					(code) => readableErrors[code] || code
				);
				showError(messages[0]);
				return;
			}

			/*
			 * Successfully received a payment session.
			 * Load Checkout.com Web Component.
			 */
			if (paymentSession.id) {
				// Performance: Track component initialization start
				const componentInitStart = performance.now();
				if (ckoFlow.performanceMetrics.enableLogging) {
					ckoLogger.performance('Initializing Checkout Web Component...');
				}
				
		// Debug: Log Flow component configuration
		ckoLogger.debug('Flow Component Configuration:', {
			publicKey: cko_flow_vars.PKey ? 'SET' : 'NOT SET',
			publicKeyPreview: cko_flow_vars.PKey ? cko_flow_vars.PKey.substring(0, 10) + '...' : 'NOT SET',
			environment: cko_flow_vars.env,
			locale: window.locale,
			paymentSessionId: paymentSession.id,
			paymentSessionSecret: paymentSession.payment_session_secret ? 'SET' : 'NOT SET',
			appearance: window.appearance ? 'SET' : 'NOT SET',
			componentOptions: window.componentOptions ? 'SET' : 'NOT SET',
			translations: window.translations ? 'SET' : 'NOT SET'
		});
		
		// Additional validation
		if (!cko_flow_vars.PKey) {
			ckoLogger.error('CRITICAL: Public key is missing!');
			showError('Payment gateway configuration error. Please contact support.');
			return;
		}
		
		if (!paymentSession.id) {
			ckoLogger.error('CRITICAL: Payment session ID is missing!');
			showError('Payment session error. Please try again.');
			return;
		}
		
		// Define callback functions BEFORE passing them to CheckoutWebComponents
		const onReady = () => {
				try {
					ckoLogger.debug('onReady callback fired! 🔥🔥🔥');
					hideLoadingOverlay();
					
					// Performance tracking: Mark when Flow is ready
					ckoFlow.performanceMetrics.flowReadyTime = performance.now();
					
					const pageLoadToReady = ckoFlow.performanceMetrics.flowReadyTime - ckoFlow.performanceMetrics.pageLoadTime;
					const initToReady = ckoFlow.performanceMetrics.flowReadyTime - ckoFlow.performanceMetrics.flowInitStartTime;
					
					if (ckoFlow.performanceMetrics.enableLogging) {
						ckoLogger.performance('===== Flow Load Complete =====');
						ckoLogger.performance(`Page Load → Flow Ready: ${pageLoadToReady.toFixed(2)}ms (${(pageLoadToReady / 1000).toFixed(2)}s)`);
						ckoLogger.performance(`Flow Init → Flow Ready: ${initToReady.toFixed(2)}ms (${(initToReady / 1000).toFixed(2)}s)`);
						ckoLogger.performance('================================');
					}
					
					// ============================================================
					// SMOOTH CHECKOUT EXPERIENCE - onReady Handler
					// ============================================================
					
					const displayOrder = window.saved_payment || 'new_payment_first';
					const skeleton = document.getElementById("flow-skeleton");
					const placeOrderBtn = document.getElementById("place_order");
					const saveCardCheckbox = document.querySelector('.cko-save-card-checkbox');
					
					ckoLogger.debug('onReady - Display order:', displayOrder);
					ckoLogger.debug('onReady - Flow is fully loaded and ready');
					
					// Step 1: Hide skeleton loader with smooth transition
					ckoLogger.debug('onReady - Skeleton element found:', !!skeleton);
					if (skeleton) {
						ckoLogger.debug('onReady - Skeleton classes BEFORE:', skeleton.className);
						skeleton.classList.remove("show");
						ckoLogger.debug('onReady - Skeleton classes AFTER:', skeleton.className);
						ckoLogger.debug('onReady - ✓ Skeleton loader hidden');
					} else {
						ckoLogger.debug('onReady - ❌ Skeleton element NOT FOUND');
					}
					
					// Step 2: Enable Place Order button (remove loading state)
					ckoLogger.debug('onReady - Place Order button found:', !!placeOrderBtn);
					if (placeOrderBtn) {
						ckoLogger.debug('onReady - Button classes BEFORE:', placeOrderBtn.className);
						placeOrderBtn.classList.remove("flow-loading");
						ckoLogger.debug('onReady - Button classes AFTER:', placeOrderBtn.className);
						placeOrderBtn.style.removeProperty('opacity');
						placeOrderBtn.style.removeProperty('visibility');
						ckoLogger.debug('onReady - ✓ Place Order button enabled');
					} else {
						ckoLogger.debug('onReady - ❌ Place Order button NOT FOUND');
					}
					
					// Step 3: Hide save card checkbox initially (onChange will show it for card payment type)
					if (saveCardCheckbox) {
						saveCardCheckbox.style.display = 'none';
						ckoLogger.debug('onReady - ✓ Save card checkbox hidden (will show on card selection)');
					} else {
						ckoLogger.debug('onReady - Save card checkbox not available (feature disabled)');
					}
					
					// Step 4: Mark Flow as ready - triggers CSS animations and visibility rules
					document.body.classList.add("flow-ready");
					ckoLogger.debug('onReady - ✓ Flow marked as ready - Checkout is now fully interactive');
					
					// Step 5: Restore saved card selection if in saved_cards_first mode
					if (displayOrder === 'saved_cards_first') {
						ckoLogger.debug('onReady - saved_cards_first mode: checking for default saved card');
						
						// Find default card (has checked="checked" attribute or is marked as default)
						const defaultCardRadio = jQuery('.saved-cards-accordion-panel input[name="wc-wc_checkout_com_flow-payment-token"][checked="checked"]:not(#wc-wc_checkout_com_flow-payment-token-new)').first();
						
						if (defaultCardRadio.length) {
							// Re-select the default card (in case Flow init deselected it)
							defaultCardRadio.prop('checked', true);
							window.flowSavedCardSelected = true;
							window.flowUserInteracted = false;
							ckoLogger.debug('onReady - ✓ Default saved card re-selected after Flow load');
							ckoLogger.debug('onReady - Selected card ID:', defaultCardRadio.attr('id'));
						} else {
							ckoLogger.debug('onReady - No default saved card found');
						}
					}
					
					// Note: Save card checkbox visibility is controlled by the onChange event
					// It will only show when payment type is "card" and hide for other payment methods
				} catch (error) {
					ckoLogger.error('onReady ERROR:', error);
					ckoLogger.error('Error stack:', error.stack);
				}
			};

		/*
		 * Called when the payment is completed successfully.
		 */
		const onPaymentCompleted = (_component, paymentResponse) => {
		
		// Check if this is a 3DS authentication response
		if (paymentResponse.threeDs && paymentResponse.threeDs.challenged) {
			// 3DS challenge detected - user will be redirected to 3DS page
			return;
		}
			
			if (paymentResponse.id) {
				hideLoadingOverlay(2);

				// Set the hidden input values.
				jQuery("#cko-flow-payment-id").val(paymentResponse.id);
				jQuery("#cko-flow-payment-type").val(paymentResponse?.type || "");

				if ( ! orderId ) {
					// Trigger WooCommerce order placement on checkout page.
					jQuery("form.checkout").submit();
				} else {
					// For order-pay pages, use native DOM submit to bypass event handlers
					ckoLogger.threeDS('Submitting order-pay form using native submit after payment completion');
					const orderPayForm = document.querySelector('form#order_review');
					if (orderPayForm) {
						orderPayForm.submit();
					} else {
						ckoLogger.error('ERROR: form#order_review not found!');
					}
				}
			}
		};

				/*
				 * Triggered when user submits the payment using Place Order Button of Woocommerce.
				 */
				const onSubmit = async (component) => {
					showLoadingOverlay(2);
					return { continue: true };
				};

				/*
				 * Triggered on component state change.
				 */
				const onChange = (component) => {
					hideLoadingOverlay();
					
					// Initialize tracking variables if not exists
					if (!window.onChangeTracking) {
						window.onChangeTracking = {
							lastState: {},
							lastProcessedTime: 0,
							processingCount: 0
						};
					}
					
					const now = Date.now();
					const currentState = {
						selectedType: component.selectedType,
						componentType: component.type,
						isValid: component.isValid ? component.isValid() : false
					};
					
					// Check if state has actually changed
					const stateChanged = (
						window.onChangeTracking.lastState.selectedType !== currentState.selectedType ||
						window.onChangeTracking.lastState.componentType !== currentState.componentType ||
						window.onChangeTracking.lastState.isValid !== currentState.isValid
					);
					
					// Rate limiting: don't process more than once every 50ms
					const timeSinceLastProcess = now - window.onChangeTracking.lastProcessedTime;
					const shouldRateLimit = timeSinceLastProcess < 50;
					
					// Only process if state actually changed AND enough time has passed
					if (!stateChanged || shouldRateLimit) {
						window.onChangeTracking.processingCount++;
						if (window.onChangeTracking.processingCount % 10 === 0) {
							ckoLogger.debug(`onChange - Skipped ${window.onChangeTracking.processingCount} duplicate events`);
						}
						return;
					}
					
					// Update tracking
					window.onChangeTracking.lastState = { ...currentState };
					window.onChangeTracking.lastProcessedTime = now;
					window.onChangeTracking.processingCount = 0;
					
			ckoLogger.debug('===== onChange START =====');
			ckoLogger.debug('onChange - Selected Type:', component.selectedType);
			ckoLogger.debug('onChange - Component Type:', component.type);
			ckoLogger.debug('onChange - Component valid:', component.isValid ? component.isValid() : 'unknown');
			ckoLogger.debug('onChange - User interacted flag:', window.flowUserInteracted);
			ckoLogger.debug('onChange - Body has flow-ready class:', document.body.classList.contains('flow-ready'));
			
			// CRITICAL: Auto-deselect saved card when user interacts with Flow
			// Check if a saved card is currently selected and Flow is de-emphasized
			const flowContainer = document.getElementById("flow-container");
			const anySavedCardSelected = jQuery('input[name="wc-wc_checkout_com_flow-payment-token"]:checked').filter(function() {
				return jQuery(this).attr('id') !== 'wc-wc_checkout_com_flow-payment-token-new';
			}).length > 0;
			
		if (anySavedCardSelected && flowContainer) {
			ckoLogger.debug('onChange - User typing in Flow while saved card selected - auto-activating Flow');
			// Call the activation function
			if (typeof window.activateFlowPayment === 'function') {
				window.activateFlowPayment();
			} else {
				// Fallback if function not available yet
				jQuery('input[name="wc-wc_checkout_com_flow-payment-token"]:checked').prop('checked', false);
				window.flowSavedCardSelected = false;
				ckoLogger.debug('onChange - Deselected saved card and activated Flow (fallback)');
			}
		}
			
			// Note: We don't set flowUserInteracted here anymore because onChange
			// fires on initial load. Instead, we use click/focus/input listeners
			// to detect actual user interaction with Flow fields.

					const hiddenTypes = [
						"applepay",
						"googlepay",
						"octopus",
						"paypal",
						"twint",
						"venmo",
						"wechatpay"
					];

					const placeOrderButton = document.querySelector("#place_order");

					// Hide place order button on digital wallets.
					if (hiddenTypes.includes(component.selectedType)) {
						if (placeOrderButton) placeOrderButton.style.display = "none";
						ckoLogger.debug('onChange - Hiding Place Order button');
					} else {
						if (placeOrderButton) placeOrderButton.style.display = "block";
						ckoLogger.debug('onChange - Showing Place Order button');
					}
					
					ckoLogger.debug('===== onChange END =====');
						
					// Pre-validate for apple pay.
					if ( component.selectedType === "applepay" ) {
						const applePayButton = document.querySelector('button[aria-label="Apple Pay"]');
						applePayButton.disabled = true;

						const form = jQuery("form.checkout");

						if ( ! orderId ) {
							validateCheckout(form, function (response) {
								applePayButton.disabled = false;
							});
						}
					}

					// Control Save to Account checkbox visibility based on payment type
					const saveCardCheckbox = jQuery('.cko-save-card-checkbox');
					if ( saveCardCheckbox.length > 0 ) {
						if ( component.selectedType === "card" ) {
							saveCardCheckbox.addClass('wc-cko-flow-card-on');
							saveCardCheckbox.css('display', 'block'); // Use .css() to set inline style
							saveCardCheckbox.show();
							ckoLogger.debug('onChange - Showing Save to Account checkbox for CARD');
						} else {
							saveCardCheckbox.removeClass('wc-cko-flow-card-on');
							saveCardCheckbox.css('display', 'none'); // Use .css() to set inline style
							saveCardCheckbox.hide();
							ckoLogger.debug('onChange - Hiding Save to Account checkbox for', component.selectedType);
						}
					} else {
						ckoLogger.debug('onChange - Save card checkbox not available (feature disabled)');
					}

					console.log(
						`[FLOW] onChange() -> isValid: "${component.isValid()}" for "${
							component.type
						}"`
					);
					
				// Additional debugging for Flow component validation issues
				if (component.type === 'flow' && !component.isValid()) {
					console.log('[FLOW DEBUG] Flow component validation failed - checking component state:', {
						componentType: component.type,
						isValid: component.isValid(),
						componentState: component.state || 'no state available',
						componentErrors: component.errors || 'no errors available',
						componentValue: component.value || 'no value available',
						componentData: component.data || 'no data available',
						componentMethods: Object.getOwnPropertyNames(component),
						componentPrototype: Object.getOwnPropertyNames(Object.getPrototypeOf(component)),
						componentMounted: component.mount ? 'mount method exists' : 'no mount method',
						componentAvailable: component.isAvailable ? 'isAvailable method exists' : 'no isAvailable method'
					});
					
				// Check if component is properly mounted
				if (component.mount) {
					console.log('[FLOW DEBUG] Component mount method exists, checking if mounted...');
					
					// Check if component is mounted to DOM
					const flowContainer = document.getElementById('flow-container');
					if (flowContainer) {
						console.log('[FLOW DEBUG] Flow container found:', {
							containerExists: true,
							containerChildren: flowContainer.children.length,
							containerInnerHTML: flowContainer.innerHTML.length > 0 ? 'has content' : 'empty',
							containerVisible: flowContainer.offsetWidth > 0 && flowContainer.offsetHeight > 0
						});
					} else {
						console.log('[FLOW DEBUG] Flow container NOT found - this is the problem!');
					}
				}
				}
				};

					/*
					 * Triggered on component click.
					 */
					const handleClick = (component) => {

						if(component.type==="applepay") {
							return {continue: true};
						}

						if ( orderId ) {
							return {continue: true};
						}

						return new Promise((resolve) => {
							const form = jQuery("form.checkout");
					
							validateCheckout(form, function (response) {
								resolve({ continue: true });
							});
						});
					};

				/*
				 * Triggered on any error in the component.
				 */
				const onError = (component, error) => {
					ckoLogger.error("onError", error, "Component", component.type);

					// Hide loading overlay and skeleton.
					hideLoadingOverlay();
					hideLoadingOverlay(2);
					
					const skeleton = document.getElementById("flow-skeleton");
					const placeOrderBtn = document.getElementById("place_order");
					
					if (skeleton) {
						skeleton.classList.remove("show");
					}
					if (placeOrderBtn) {
						placeOrderBtn.classList.remove("flow-loading");
					}

					// Handle specific error types
					if (error.message && error.message.includes("[Request]: Network request failed [payment_request_failed]")) {
						ckoLogger.error("Payment request failed - checking configuration:");
						ckoLogger.error("Public Key:", cko_flow_vars.PKey ? 'SET' : 'NOT SET');
						ckoLogger.error("Environment:", cko_flow_vars.env);
						ckoLogger.error("Payment Session ID:", paymentSession.id);
						error.message = "Payment request failed. Please check your payment gateway configuration or try again.";
					} else if (error.message === "[Submit]: Component is invalid [component_invalid]") {
						error.message = "Please complete your payment before placing the order.";
					}

						showError(
							error.message ||
								wp.i18n.__(
									"Something went wrong. Please try again.",
									"checkout-com-unified-payments-api"
								)
						);
					};

		// Debug: Log callback functions
		ckoLogger.debug('Callback Functions:', {
			onReady: typeof onReady,
			onPaymentCompleted: typeof onPaymentCompleted,
			onSubmit: typeof onSubmit,
			onChange: typeof onChange,
			handleClick: typeof handleClick,
			onError: typeof onError
		});
		
		// Debug: Make onPaymentCompleted globally accessible for testing
		window.testOnPaymentCompleted = onPaymentCompleted;
		ckoLogger.debug('onPaymentCompleted callback is now globally accessible as window.testOnPaymentCompleted');
		
		const checkout = await CheckoutWebComponents({
			publicKey: cko_flow_vars.PKey,
			environment: cko_flow_vars.env,
			locale: window.locale,
			paymentSession,
			appearance: window.appearance,
			componentOptions: window.componentOptions,
			translations: window.translations,
			onReady,
			onPaymentCompleted,
			onSubmit,
			onChange,
			handleClick,
			onError
		});

				// Ensure component name is defined
				const componentName = window.componentName || 'flow';
				ckoLogger.debug('Creating Flow component with name:', componentName);
				
				let flowComponent;
				try {
					console.log('[FLOW DEBUG] About to create Flow component with:', {
						componentName: componentName,
						checkoutObject: typeof checkout,
						checkoutCreate: typeof checkout.create,
						componentOptions: { showPayButton: false }
					});
					
					ckoLogger.debug('About to create Flow component with:', {
						componentName: componentName,
						checkoutObject: typeof checkout,
						checkoutCreate: typeof checkout.create,
						componentOptions: { showPayButton: false }
					});
					
					flowComponent = checkout.create(componentName, {
						showPayButton: false,
					});
					
					console.log('[FLOW DEBUG] Flow component created successfully:', {
						componentName: componentName,
						componentType: flowComponent.type,
						componentAvailable: flowComponent.isAvailable ? 'method exists' : 'method missing',
						componentMethods: Object.getOwnPropertyNames(flowComponent),
						componentPrototype: Object.getOwnPropertyNames(Object.getPrototypeOf(flowComponent))
					});
					
					ckoLogger.debug('Flow component created successfully:', {
						componentName: componentName,
						componentType: flowComponent.type,
						componentAvailable: flowComponent.isAvailable ? 'method exists' : 'method missing',
						componentMethods: Object.getOwnPropertyNames(flowComponent),
						componentPrototype: Object.getOwnPropertyNames(Object.getPrototypeOf(flowComponent))
					});
				} catch (error) {
					console.log('[FLOW ERROR] Error creating Flow component:', error);
					ckoLogger.error('Error creating Flow component:', error);
					showError('Failed to initialize payment component. Please try again.');
					return;
				}

				// Performance: Track component creation
				const componentInitEnd = performance.now();
				const componentInitDuration = componentInitEnd - componentInitStart;
				if (ckoFlow.performanceMetrics.enableLogging) {
					ckoLogger.performance(`Component initialized in ${componentInitDuration.toFixed(2)}ms (${(componentInitDuration / 1000).toFixed(2)}s)`);
				}

				ckoFlow.flowComponent = flowComponent;

				/*
				 * Check if the component is available. Mount component only if available.
				 */
				flowComponent.isAvailable().then((available) => {
					if (available) {
						// Performance: Track component mount
						const mountStart = performance.now();
						if (ckoFlow.performanceMetrics.enableLogging) {
							ckoLogger.performance('Mounting component to DOM...');
						}
				flowComponent.mount(document.getElementById("flow-container"));
				const mountEnd = performance.now();
				if (ckoFlow.performanceMetrics.enableLogging) {
					ckoLogger.performance(`Component mounted in ${(mountEnd - mountStart).toFixed(2)}ms`);
				}
				
				// ============================================================
				// SMOOTH CHECKOUT EXPERIENCE - Enable UI after mount
				// ============================================================
				ckoLogger.debug('Component mounted - enabling UI! 🔥🔥🔥');
				
				const skeleton = document.getElementById("flow-skeleton");
				const placeOrderBtn = document.getElementById("place_order");
				const saveCardCheckbox = document.querySelector('.cko-save-card-checkbox');
				
				// Step 1: Hide skeleton loader
				if (skeleton) {
					skeleton.classList.remove("show");
					ckoLogger.debug('✓ Skeleton loader hidden');
				}
				
				// Step 2: Enable Place Order button
				if (placeOrderBtn) {
					placeOrderBtn.classList.remove("flow-loading");
					placeOrderBtn.style.removeProperty('opacity');
					placeOrderBtn.style.removeProperty('visibility');
					console.log('[FLOW] ✓ Place Order button enabled');
				}
				
				// Step 3: Hide save card checkbox initially
				if (saveCardCheckbox) {
					saveCardCheckbox.style.display = 'none';
				} else {
					console.log('[FLOW] Mount - Save card checkbox not available (feature disabled)');
				}
				
				// Step 4: Mark Flow as ready
				document.body.classList.add("flow-ready");
				console.log('[FLOW] ✓ Checkout is now fully interactive');
				
				// CRITICAL: Listen for user interaction with Flow fields (click, focus, input)
					// This detects when user actually starts using Flow (not just onChange firing on load)
					setTimeout(() => {
						const flowContainer = document.getElementById("flow-container");
						if (flowContainer) {
							// Listen for any interaction with Flow component
							flowContainer.addEventListener('click', function() {
								if (!window.flowUserInteracted) {
									console.log('[FLOW] User clicked on Flow component - marking as interacted');
									window.flowUserInteracted = true;
									window.flowSavedCardSelected = false; // Reset saved card flag when user interacts with Flow
								}
							}, { once: false });
							
							flowContainer.addEventListener('focus', function(e) {
								if (!window.flowUserInteracted) {
									console.log('[FLOW] User focused on Flow field - marking as interacted');
									window.flowUserInteracted = true;
									window.flowSavedCardSelected = false; // Reset saved card flag when user interacts with Flow
								}
							}, { capture: true });
							
							flowContainer.addEventListener('input', function(e) {
								if (!window.flowUserInteracted) {
									console.log('[FLOW] User typing in Flow field - marking as interacted');
									window.flowUserInteracted = true;
									window.flowSavedCardSelected = false; // Reset saved card flag when user interacts with Flow
								}
							}, { capture: true });
							
							console.log('[FLOW] Flow interaction listeners attached');
						}
					}, 500);
					
				} else {
						// Hide loading overlay.
						hideLoadingOverlay();
						ckoLogger.error("Component is not available.");

						showError(
							wp.i18n.__(
								"The selected payment method is not available at this time.",
								"checkout-com-unified-payments-api"
							)
						);
					}
				});
			}
		} catch (error) {
			// Hide loading overlay and skeleton.
			hideLoadingOverlay();
			
			const skeleton = document.getElementById("flow-skeleton");
			const placeOrderBtn = document.getElementById("place_order");
			
			if (skeleton) {
				skeleton.classList.remove("show");
			}
			if (placeOrderBtn) {
				placeOrderBtn.classList.remove("flow-loading");
			}
			
			ckoLogger.error("Error creating payment session:", error);

			showError(
				error.message ||
					wp.i18n.__(
						"Error creating payment session.",
						"checkout-com-unified-payments-api"
					)
			);
		}
	},
};

/*
 * Displays error messages at the top of the WooCommerce form.
 */
let showError = function (error_message) {
	if ("string" === typeof error_message) {
		error_message = [error_message];
	}

	let ulWrapper = jQuery("<ul/>")
		.prop("role", "alert")
		.addClass("woocommerce-error");

	if (Array.isArray(error_message)) {
		jQuery.each(error_message, function (index, value) {
			jQuery(ulWrapper).append(jQuery("<li>").html(value));
		});
	}

	let wcNoticeDiv = jQuery("<div>")
		.addClass("woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout")
		.append(ulWrapper);

	let scrollTarget;

	if (jQuery("form.checkout").length) {
		jQuery("form.checkout .woocommerce-NoticeGroup").remove();
		jQuery("form.checkout").prepend(wcNoticeDiv);
		jQuery(".woocommerce, .form.checkout").removeClass("processing").unblock();
		scrollTarget = jQuery("form.checkout");
	} else if (jQuery(".woocommerce-order-pay").length) {
		jQuery(".woocommerce-order-pay .woocommerce-NoticeGroup").remove();
		jQuery(".woocommerce-order-pay").prepend(wcNoticeDiv);
		jQuery(".woocommerce, .woocommerce-order-pay")
			.removeClass("processing")
			.unblock();
		scrollTarget = jQuery(".woocommerce-order-pay");
	}

	// Scroll to top of checkout form.
	if (scrollTarget) {
		jQuery("html, body").animate(
			{
				scrollTop: scrollTarget.offset().top - 100,
			},
			1000
		);
	}
};

/**
 * Initialize Flow interaction tracking flag
 * This flag tracks whether the user has actively interacted with the Flow component
 * to differentiate between saved card payments and new Flow payments
 */
window.flowUserInteracted = false;

/**
 * Initializes the observer to monitor the presence of the Flow checkout component in the DOM.
 *
 * - Sets the `ckoFlowInitialized` flag to `false` on page load.
 * - Observes the DOM for any changes using `MutationObserver`.
 * - If the Flow checkout component (identified by `data-testid="checkout-web-component-root"`) 
 *   is removed from the DOM, the flag `ckoFlowInitialized` is reset to `false`.
 *
 * This helps ensure that the Flow component can be re-initialized when needed.
 */

let ckoFlowInitialized = false;

document.addEventListener("DOMContentLoaded", function () {
	// Track page load time for performance metrics
	ckoFlow.performanceMetrics.pageLoadTime = performance.now();
	if (ckoFlow.performanceMetrics.enableLogging) {
		ckoLogger.performance('Page DOMContentLoaded at:', ckoFlow.performanceMetrics.pageLoadTime.toFixed(2) + 'ms');
	}
	
	const element = document.querySelector(
		'[data-testid="checkout-web-component-root"]'
	);
	ckoFlowInitialized = false;

	const observer = new MutationObserver(() => {
		const element = document.querySelector(
			'[data-testid="checkout-web-component-root"]'
		);

		// If the element is not present, update ckoFlowInitialized.
		if (!element) {
			ckoFlowInitialized = false;
		}
	});

	// Observe the entire document for any changes in the DOM.
	observer.observe(document.body, {
		childList: true,
		subtree: true,
	});
});

/*
 * Listens to changes in the payment method radio buttons
 * and initializes the Flow payment method if selected.
 * 
 * This function handles the logic for showing or hiding the Flow payment container
 * and initializing the Flow checkout component when the Flow payment method is selected.
 * It also manages the visibility of saved payment methods and ensures the Flow component is
 * initialized when needed.
 */
function handleFlowPaymentSelection() {

	// Fetching elemnets required.
	let flowContainer = document.getElementById("flow-container");
	let flowPayment = document.getElementById(
		"payment_method_wc_checkout_com_flow"
	);
	let placeOrderElement = document.getElementById("place_order");

	// Logic for saved cards.
	const ulElements = document.querySelectorAll(".woocommerce-SavedPaymentMethods");

	let totalDataCount = 0;
	ulElements.forEach((ul) => {
		const count = parseInt(ul.getAttribute("data-count"), 10);
		if (!isNaN(count)) {
			totalDataCount += count;
		}
	});

	const dataCount = totalDataCount;

	if (flowPayment && flowPayment.checked) {
		
		ckoLogger.debug('Payment selected - Flow is checked');

		// Always show both Flow and saved cards together
		document.body.classList.add("flow-method-selected");

		// Initialize Flow
	if (!ckoFlowInitialized) {
		ckoFlow.init();
		ckoFlowInitialized = true;
	}

	// Show Flow container and make it interactive
	if (flowContainer) {
		flowContainer.style.display = "block";
		
		// Flow container is shown but saved cards take priority
		ckoLogger.debug('Flow container visible - saved cards can be selected');
	}
		
		// CRITICAL FIX: Ensure saved cards accordion respects CSS rules
		// Remove any inline display:none that might have been set by other code
		const accordionContainer = document.querySelector('.saved-cards-accordion-container');
		if (accordionContainer && accordionContainer.style.display === 'none') {
			// Let CSS handle visibility via data-saved-payment-order attribute
			accordionContainer.style.removeProperty('display');
			ckoLogger.debug('Removed inline display:none from accordion, CSS will control visibility');
		}
		
		ckoLogger.debug('CSS controls saved cards via data-saved-payment-order:', document.body.getAttribute('data-saved-payment-order'));
	} else {
		if (flowContainer) {
			flowContainer.style.display = "none";
		}
		if (placeOrderElement) {
			placeOrderElement.style.display = "block";
		}
		// Remove Flow-specific classes when switching to another payment method
		document.body.classList.remove("flow-method-selected", "flow-ready");
	}
}

/**
 * Global handler for saved card selection (works in both new_payment_first and saved_cards_first modes)
 * When a saved card is selected, show the Place Order button
 * When new payment method is selected, ensure Flow container is visible and interactive
 */
jQuery(document).on(
	"change",
	'input[name="wc-wc_checkout_com_flow-payment-token"]',
	function () {
		const selectedToken = jQuery('input[name="wc-wc_checkout_com_flow-payment-token"]:checked');
		const flowContainer = document.getElementById("flow-container");
		const selectedId = selectedToken.attr('id');
		
		// If "Use new payment method" is selected
		if (selectedId === 'wc-wc_checkout_com_flow-payment-token-new') {
			ckoLogger.debug('New payment method selected - showing Flow container');
			
			// Show Flow container
			if (flowContainer) {
				flowContainer.style.display = "block";
			}
			
			// Mark as ready when Flow component is valid
			if (ckoFlow.flowComponent) {
				document.body.classList.add("flow-ready");
			} else {
				// Flow not initialized yet, wait for it
				document.body.classList.remove("flow-ready");
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
			document.body.classList.add("flow-ready");
			
			// Keep Flow container visible
			if (flowContainer) {
				flowContainer.style.display = "block";
			}
		}
	}
);

/**
 * Listen for changes in the payment method selection and handle the Flow payment method.
 *
 * This event listener listens for changes to the payment method selection form (typically a radio button).
 * When a change is detected in the selection of the payment method, it checks if the selected input is 
 * the payment method field and triggers the Flow payment selection handler.
 */
document.addEventListener("change", function (event) {
	if (event.target && event.target.name === "payment_method") {
		// Immediately add flow-method-selected class if Flow is selected
		const flowPayment = document.getElementById("payment_method_wc_checkout_com_flow");
		if (flowPayment && flowPayment.checked) {
			document.body.classList.add("flow-method-selected");
			// Remove flow-ready until component is actually ready
			document.body.classList.remove("flow-ready");
		} else {
			// Remove Flow classes for other payment methods
			document.body.classList.remove("flow-method-selected", "flow-ready");
			// Reset Flow interaction flags when switching to other payment methods
			window.flowUserInteracted = false;
			window.flowSavedCardSelected = false;
		}
		
		handleFlowPaymentSelection();
	}
});

/**
 * Handle case -  when Flow is on the order-pay page.
 * 
 * This event listener ensures Flow-specific logic only runs on the order-pay page,
 * where the customer is directed to complete payment for an existing order.
 */
document.addEventListener("DOMContentLoaded", function () {
	const orderPaySlug = cko_flow_vars.orderPaySlug;

	if (window.location.pathname.includes('/' + orderPaySlug + '/')) {
		handleFlowPaymentSelection();
	}
});

/**
 * Handle case -  when Flow is the only payment method or the starting payment method.
 * 
 * This function listens for the DOMContentLoaded event, then checks if Flow is the only 
 * payment method listed in the available payment methods. If so, it triggers actions 
 * to handle this scenario, such as adding specific classes or handling payment selection.
 * It also checks if required fields are filled and adjusts the Flow payment selection accordingly.
 * 
 */
document.addEventListener("DOMContentLoaded", function () {
	const paymentMethodsList = document.querySelector("ul.wc_payment_methods");

	if (paymentMethodsList) {
		const listItems = paymentMethodsList.children;

		// Check if the first list item is for the Flow payment method.
		if (
			listItems[0].classList.contains("payment_method_wc_checkout_com_flow")
		) {

			// If Flow is the only payment method, add a custom class to the body 
			// and trigger the Flow payment selection handler.
			if (listItems.length === 1) {
				document.body.classList.add("flow-method-single");
				
				// Check if Flow is selected/checked
				const flowPayment = document.getElementById("payment_method_wc_checkout_com_flow");
				if (flowPayment && flowPayment.checked) {
					// Check if we're in saved_cards_first mode with saved cards
					const ulElements = document.querySelectorAll(".woocommerce-SavedPaymentMethods");
					let totalDataCount = 0;
					ulElements.forEach((ul) => {
						const count = parseInt(ul.getAttribute("data-count"), 10);
						if (!isNaN(count)) {
							totalDataCount += count;
						}
					});
					
					// If saved cards exist and we're in saved_cards_first mode, don't hide button
					if (window.saved_payment === "saved_cards_first" && totalDataCount > 0) {
						ckoLogger.debug('DOMContentLoaded: saved_cards_first with saved cards, adding flow-ready');
						document.body.classList.add("flow-ready");
					} else {
						// Otherwise, hide the button until Flow is ready
						document.body.classList.add("flow-method-selected");
						document.body.classList.remove("flow-ready");
					}
				}

				// Order-pay.
				const orderPaySlug = cko_flow_vars.orderPaySlug;

				// Check if current URL contains the slug.
				if (window.location.pathname.includes('/' + orderPaySlug + '/')) {
					handleFlowPaymentSelection();
				}
			}

			// If required fields are not filled, trigger Flow payment selection handler.
			if (!requiredFieldsFilled()) {
				// Check if Flow is selected
				const flowPayment = document.getElementById("payment_method_wc_checkout_com_flow");
				if (flowPayment && flowPayment.checked) {
					// Check if we're in saved_cards_first mode with saved cards
					const ulElements = document.querySelectorAll(".woocommerce-SavedPaymentMethods");
					let totalDataCount = 0;
					ulElements.forEach((ul) => {
						const count = parseInt(ul.getAttribute("data-count"), 10);
						if (!isNaN(count)) {
							totalDataCount += count;
						}
					});
					
					// If saved cards exist and we're in saved_cards_first mode, don't hide button
					if (window.saved_payment === "saved_cards_first" && totalDataCount > 0) {
						ckoLogger.debug('DOMContentLoaded (required fields): saved_cards_first with saved cards, adding flow-ready');
						document.body.classList.add("flow-ready");
					} else {
						// Otherwise, hide the button until Flow is ready
						document.body.classList.add("flow-method-selected");
						document.body.classList.remove("flow-ready");
					}
				}
				handleFlowPaymentSelection();
			}
		}
	}
});

/**
 * Handle Place Order Button with Flow Checkout when ShowPayButton is False.
 * 
 * This function listens for a click event on the "Place Order" button. If the checkout method 
 * is "Flow" and the user is using the checkout component, it validates the form and proceeds 
 * with the appropriate order placement, either using the Flow component or submitting the form directly.
 * 
 */
document.addEventListener("DOMContentLoaded", function () {
	
	// Listen for saved card clicks to de-emphasize Flow
	jQuery(document).on('click', 'input[name="wc-wc_checkout_com_flow-payment-token"]', function() {
		const selectedId = jQuery(this).attr('id');
		const flowContainer = document.getElementById("flow-container");
		
		if (selectedId && selectedId !== 'wc-wc_checkout_com_flow-payment-token-new') {
			ckoLogger.debug('Saved card selected');
			window.flowUserInteracted = false;
			window.flowSavedCardSelected = true;
			
			// Remove the hidden override input if it exists (user switched back to saved card)
			jQuery('#flow-new-payment-override').remove();
	} else if (selectedId === 'wc-wc_checkout_com_flow-payment-token-new') {
		ckoLogger.debug('New payment method selected - activating Flow');
		window.activateFlowPayment();
	}
	});
	
	// Function to activate Flow and deselect saved cards
	// Make it globally accessible so onChange callback can use it
	window.activateFlowPayment = function() {
		console.log('[FLOW] Activating Flow payment method');
		
		const flowContainer = document.getElementById("flow-container");
		if (!flowContainer) return;
		
		// Uncheck all saved card radio buttons (including any that might be hidden)
		jQuery('input[name="wc-wc_checkout_com_flow-payment-token"]').each(function() {
			const radioId = jQuery(this).attr('id');
			// Don't uncheck the "new payment method" radio
			if (radioId !== 'wc-wc_checkout_com_flow-payment-token-new') {
				jQuery(this).prop('checked', false);
				jQuery(this).removeAttr('checked');
			}
		});
		
		// Try to select "Use new payment method" radio button if it exists
		const newPaymentRadio = document.getElementById('wc-wc_checkout_com_flow-payment-token-new');
		if (newPaymentRadio) {
			newPaymentRadio.checked = true;
			jQuery(newPaymentRadio).attr('checked', 'checked');
			jQuery(newPaymentRadio).trigger('change');
		} else {
			// If the "new payment method" radio doesn't exist, add a hidden input
			jQuery('#flow-new-payment-override').remove();
			
			const overrideInput = jQuery('<input>', {
				type: 'hidden',
				id: 'flow-new-payment-override',
				name: 'wc-wc_checkout_com_flow-payment-token',
				value: 'new'
			});
			jQuery('form.checkout, form#order_review').append(overrideInput);
		}
		
		// Flow container is now active for new payment
		
		// Clear saved card flag and mark for new Flow payment
		window.flowSavedCardSelected = false;
		window.flowUserInteracted = false; // Reset to allow user to interact with Flow
		document.body.classList.add("flow-ready");
		
		// Ensure Place Order button is visible
		const placeOrderButton = document.getElementById('place_order');
		if (placeOrderButton) {
			placeOrderButton.style.display = 'block';
			placeOrderButton.style.opacity = '1';
			placeOrderButton.style.visibility = 'visible';
		}
	}
	
	// Listen for clicks/focus on Flow component fields to auto-activate Flow
	jQuery(document).on('click focus', '#flow-container input, #flow-container iframe, #flow-container', function(e) {
		const flowContainer = document.getElementById("flow-container");
		
		// Only activate if Flow is currently de-emphasized (saved card selected)
		if (flowContainer && flowContainer.classList.contains('flow-de-emphasized')) {
			console.log('[FLOW] User clicked on Flow field - auto-activating Flow');
			window.activateFlowPayment();
		}
	});
	
	/**
	 * Helper function to persist the save card checkbox value to sessionStorage
	 * This ensures the value survives 3DS redirects
	 */
	function persistSaveCardCheckbox() {
		const checkbox = document.getElementById('wc-wc_checkout_com_flow-new-payment-method');
		
		if (checkbox) {
			// Store in sessionStorage to survive 3DS redirects
			const value = checkbox.checked ? 'yes' : 'no';
			sessionStorage.setItem('cko_flow_save_card', value);
			ckoLogger.debug('SAVE CARD: Persisted checkbox value to sessionStorage:', value);
			ckoLogger.debug('SAVE CARD: Checkbox found:', checkbox);
			ckoLogger.debug('SAVE CARD: Checkbox checked:', checkbox.checked);
			ckoLogger.debug('SAVE CARD: Checkbox visible:', checkbox.offsetParent !== null);
			
			// Also set hidden field as backup
			const hiddenField = document.getElementById('cko-flow-save-card-persist');
			if (hiddenField) {
				hiddenField.value = value;
				ckoLogger.debug('SAVE CARD: Set hidden field value:', value);
			} else {
				ckoLogger.debug('SAVE CARD: WARNING: Hidden field not found!');
			}
		} else {
			ckoLogger.debug('SAVE CARD: WARNING: Checkbox element not found in DOM!');
		}
	}
	
	/**
	 * Restore save card checkbox value from sessionStorage after 3DS redirect
	 * This runs on every page load
	 */
	function restoreSaveCardCheckbox() {
		const savedValue = sessionStorage.getItem('cko_flow_save_card');
		
		if (savedValue) {
			ckoLogger.debug('SAVE CARD: Restoring checkbox value from sessionStorage:', savedValue);
			
			// Set the hidden field value
			const hiddenField = document.getElementById('cko-flow-save-card-persist');
			if (hiddenField) {
				hiddenField.value = savedValue;
				ckoLogger.debug('SAVE CARD: Set hidden field to:', savedValue);
			}
			
			// Also check the checkbox if it exists
			const checkbox = document.getElementById('wc-wc_checkout_com_flow-new-payment-method');
			if (checkbox && savedValue === 'yes') {
				checkbox.checked = true;
				ckoLogger.debug('SAVE CARD: Checked the save card checkbox');
			}
			
			// Clear sessionStorage after restoring (optional - only clear after successful order)
			// sessionStorage.removeItem('cko_flow_save_card');
		}
	}
	
	// Restore checkbox value on page load (for 3DS returns)
	document.addEventListener('DOMContentLoaded', function() {
		// Check if we're on the order received page
		const isOrderReceivedPage = window.location.pathname.includes('/order-received/') || 
		                             window.location.pathname.includes('/checkout/thank-you/');
		
		if (isOrderReceivedPage) {
			// Clear the saved checkbox value after successful order
			ckoLogger.debug('SAVE CARD: Order completed - clearing sessionStorage');
			sessionStorage.removeItem('cko_flow_save_card');
		} else {
			// Restore checkbox value (for 3DS return to checkout page)
			restoreSaveCardCheckbox();
		}
	});
	
	// Also try to restore immediately in case DOM is already loaded
	if (document.readyState === 'loading') {
		// DOM still loading, listener above will handle it
	} else {
		// DOM already loaded, check now
		const isOrderReceivedPage = window.location.pathname.includes('/order-received/') || 
		                             window.location.pathname.includes('/checkout/thank-you/');
		if (isOrderReceivedPage) {
			ckoLogger.debug('SAVE CARD: Order completed - clearing sessionStorage');
			sessionStorage.removeItem('cko_flow_save_card');
		} else {
			restoreSaveCardCheckbox();
		}
	}
	
	document.addEventListener("click", function (event) {
		const flowPayment = document.getElementById(
			"payment_method_wc_checkout_com_flow"
		);

		// If the Place Order button is clicked, proceed.
		if (event.target && event.target.id === "place_order") {

			// If the Flow payment method is selected, proceed with validation and order placement.
			if (flowPayment && flowPayment.checked) {
				event.preventDefault();
				
				// Persist save card checkbox before form submission
				persistSaveCardCheckbox();

				const form = jQuery("form.checkout");

				// Handle payment for order-pay page.
				if ( form.length === 0 ) {
					const orderPaySlug = cko_flow_vars.orderPaySlug;
					const orderPayForm = jQuery('form#order_review');

					if (window.location.pathname.includes('/' + orderPaySlug + '/')) {
						// console.log('[CURRENT VERSION] Order-pay page confirmed, showing flow container');

						document.getElementById("flow-container").style.display = "block";

						// Place order for FLOW.
						if (ckoFlow.flowComponent) {
							// console.log('[CURRENT VERSION] Flow component exists');
							
							// Check if a saved card is actually selected (not just if saved cards are enabled)
							const selectedSavedCard = jQuery('input[name="wc-wc_checkout_com_flow-payment-token"]:checked:not(#wc-wc_checkout_com_flow-payment-token-new)');
							const savedCardSelected = selectedSavedCard.length > 0;
							const savedCardEnabled = document.querySelector('[data-testid="checkout-web-component-root"]').classList.contains('saved-card-is-enabled');
							
							// console.log('[CURRENT VERSION] Saved card selected:', savedCardSelected);
							// console.log('[CURRENT VERSION] Saved card enabled:', savedCardEnabled);
							
							// ALWAYS persist save card checkbox on order-pay page (for new card payments)
							if (!savedCardSelected) {
								persistSaveCardCheckbox();
								console.log('[FLOW] Order-pay: Persisted save card checkbox for new card payment');
							}
							
							if( savedCardSelected || savedCardEnabled ) {
								// console.log('[CURRENT VERSION] Saved card selected/enabled - submitting order-pay form directly');
								orderPayForm.submit();
							} else {
								// console.log('[CURRENT VERSION] No saved card - calling flow component submit');
								ckoFlow.flowComponent.submit();
							}
						} else {
							// console.log('[CURRENT VERSION] No flow component found');
						}

						// Place order for saved card.
						if (!ckoFlow.flowComponent) {
							// console.log('[CURRENT VERSION] No flow component - submitting order-pay form for saved card');
							orderPayForm.submit();
						}
						
					} else {
						// console.log('[CURRENT VERSION] Not an order-pay page, skipping');
					}
				} else {
					// console.log('[CURRENT VERSION] Checkout form found - handling regular checkout');

					// Validate checkout before proceeding.
					validateCheckout(form, function (response) {
						// console.log('[CURRENT VERSION] Checkout validation response:', response);
						document.getElementById("flow-container").style.display = "block";

						// Place order for FLOW.
						if (ckoFlow.flowComponent) {
							// console.log('[CURRENT VERSION] Flow component exists for checkout');
							
							// Check if a saved card is actually selected (not just if saved cards are enabled)
							const selectedSavedCard = jQuery('input[name="wc-wc_checkout_com_flow-payment-token"]:checked:not(#wc-wc_checkout_com_flow-payment-token-new)');
							const savedCardSelected = selectedSavedCard.length > 0;
							const savedCardEnabled = document.querySelector('[data-testid="checkout-web-component-root"]').classList.contains('saved-card-is-enabled');
							
							// console.log('[CURRENT VERSION] Saved card selected for checkout:', savedCardSelected);
							// console.log('[CURRENT VERSION] Saved card enabled for checkout:', savedCardEnabled);
							
							if( savedCardSelected || savedCardEnabled ) {
								// console.log('[CURRENT VERSION] Saved card selected/enabled - submitting checkout form directly');
								form.submit();
							} else {
								// console.log('[CURRENT VERSION] No saved card - calling flow component submit for checkout');
								ckoFlow.flowComponent.submit();
							}
						} else {
							// console.log('[CURRENT VERSION] No flow component found for checkout');
						}

						// Place order for saved card.
						if (!ckoFlow.flowComponent) {
							// console.log('[CURRENT VERSION] No flow component - submitting checkout form for saved card');
							form.submit();
						}
					});

				}
			} else {
				// console.log('[CURRENT VERSION] Flow payment method not selected or not found');
			}
		}
	});
});

// Removed complex 3DS redirect handling - keeping it simple like the working version

let virtual = false;
/**
 * Handle checkout flow-container rendering on various field changes.
 * Attaches debounced event listeners to checkout inputs to update flow state and cart info dynamically.
 */
jQuery(function ($) {

	/**
	 * Main handler function triggered on input/change events.
	 * It performs field checks and updates the checkout flow and cart info accordingly.
	 *
	 * @param {Event} event - The input or change event.
	 */
	const handleTyping = (event) => {

		let isShippingField = false;

		// Check if the changed field belongs to shipping info.
		if (event) {
			const $target = jQuery(event.target);
			isShippingField = $target.is('[id^="shipping"]');
		}

		// If the field is a shipping-related field, trigger WooCommerce checkout update and exit early.
		if (isShippingField) {
			console.log("Triggered by a shipping field, skipping...");
			$("body").trigger("update_checkout");
			return;
		}

		// Only proceed if all required fields are filled.
		if (requiredFieldsFilled()) {
			$("body").trigger("update_checkout");

			// If the event is from checking 'ship to different address' or 'create account', return early.
			if (
				jQuery(event.target).is(
					"#ship-to-different-address-checkbox, #createaccount"
				) &&
				jQuery(event.target).is(":checked")
			) {
				console.log("User just checked the checkbox. Returning early...");
				return;
			}

			var targetName = event.target.name || "";

			// If the event is not from billing fields or key checkboxes, exit early.
			if (
				!targetName.startsWith("billing") &&
				!jQuery(event.target).is(
					"#ship-to-different-address-checkbox, #terms, #createaccount, #coupon_code"
				)
			) {
				let cartData = $("#cart-info").data("cart");
				if ( !cartData || !cartData["contains_virtual_product"] ) {
					console.log(
						"Neither billing nor the shipping checkbox. Returning early..."
					);
					return;
				}
				console.log(
					"Virtual Product found. Triggering FLOW..."
				);
			}

			// Use existing cart data instead of fetching via AJAX
			// The cart info is already available in the HTML data-cart attribute
			const $cartDiv = jQuery("#cart-info");
			const existingCartData = $cartDiv.data("cart");
			
			if (existingCartData) {
				ckoLogger.debug('Using existing cart data:', existingCartData);
				
				// Update global state and re-trigger payment flow setup.
				cartInfo = existingCartData;
				ckoFlowInitialized = false;
				handleFlowPaymentSelection();
			} else {
				ckoLogger.debug('No existing cart data found, skipping Flow re-initialization');
			}
		}
	};

	// Debounce the handler to limit how often it's triggered during typing.
	const debouncedTyping = debounce(handleTyping, 2000);

	// Attach debounced handler to key billing fields.
	$(
		"#billing_first_name, #billing_last_name, #billing_email, #billing_phone"
	).on("input", function (e) {
		debouncedTyping(e);
	});

	// Attach to all other inputs/selects, excluding the key billing fields above.
	$(
		"input:not(#billing_first_name, #billing_last_name, #billing_email, #billing_phone), select"
	).on("input change", function (e) {
		debouncedTyping(e);
	});

	// Attach handler to all input/selects, but ignore payment method fields.
	$(document).on("input change", "input, select", function (e) {
		if ($(this).closest(".wc_payment_method").length === 0) {
			
			debouncedTyping(e);

		}

		let cartData = $("#cart-info").data("cart");
		if ( !virtual && cartData && cartData["contains_virtual_product"] && $('#ship-to-different-address-checkbox').length === 0 ) {
			debouncedTyping(e);
			virtual = true;
		}
	});
});

/**
 * 
 * Debounce utility function.
 * Delays the execution of a function until after a specified delay
 * has passed since the last time it was invoked.
 * 
 * @param {Function} func - The function to debounce.
 * @param {number} delay - Delay in milliseconds.
 * 
 * @returns {Function} - A debounced version of the original function. 
 */
function debounce(func, delay) {
	let timer;

	return function (...args) {

		// Clear the existing timer, if any.
		clearTimeout(timer);

		// Set a new timer to call the function after the delay.
		timer = setTimeout(() => {

			// Call the original function with the correct context and arguments.
			func.apply(this, args);
		}, delay);
	};
}

/**
 * Checks if all non-shipping required fields in the WooCommerce checkout form are filled.
 *
 * This function looks for elements marked with a `.required` span inside labels
 * within the `.woocommerce-checkout` form, extracts the associated input field IDs,
 * filters out those related to shipping, and then verifies that the corresponding
 * fields are not empty.
 *
 * @returns {boolean} - Returns true if all non-shipping required fields are filled; otherwise, false.
 */
function requiredFieldsFilled() {

	// Select all required field indicators within WooCommerce checkout labels.
	const requiredLabels = document.querySelectorAll(
		".woocommerce-checkout label .required"
	);

	const fieldIds = [];

	requiredLabels.forEach((label) => {
		const fieldId = label.closest("label").getAttribute("for");
		if (fieldId) {
			fieldIds.push(fieldId);
		}
	});

	// Filter out fieldIds that start with "shipping".
	let filteredFieldIds = fieldIds.filter((id) => !id.startsWith("shipping"));

	// Check if login form is hidden.
	const loginForm = document.querySelector(".woocommerce-form-login");
	if (loginForm && loginForm.style.display === "none") {
		// Remove username and password fields if form is hidden.
		filteredFieldIds = filteredFieldIds.filter(
			(id) => id !== "username" && id !== "password"
		);
	}

	// Check that each field is present and not empty.
	const result = filteredFieldIds.every((id) => {
		const field = document.getElementById(id);
		return field && field.value.trim() !== "";
	});

	return result;
}

/**
 * Validates the checkout form by sending serialized form data to the server
 * using an AJAX POST request. Executes callback functions based on the response.
 *
 * @param {jQuery} form - The jQuery-wrapped form element to be validated.
 * @param {Function} onSuccess - Callback function executed when validation is successful.
 * @param {Function} onError - Optional callback function executed when validation fails or an error occurs.
 */
function validateCheckout(form, onSuccess, onError) {

	const formData = form.serialize(); // Serialize the form.

	// Perform AJAX POST request for server-side validation.
	jQuery.ajax({
		url: cko_flow_vars.ajax_url,
		type: "POST",
		data: {
			action: "cko_validate_checkout",
			...Object.fromEntries(new URLSearchParams(formData)),
		},
		success: function (response) {

			// If the response indicates success, trigger the onSuccess callback.
			if (response.success) {
				onSuccess(response);
			} else {
				
				// Show an error message and trigger the onError callback if provided.
				showError(response.data.message);
				if (onError) onError(response);
			}
		},
		error: function () {

			// If the request fails, display the flow container and show a generic error message.
			document.getElementById("flow-container").style.display = "block";
			showError(
				wp.i18n.__(
					"An error occurred. Please try again.",
					"checkout-com-unified-payments-api"
				)
			);

			// Trigger onError callback if provided.
			if (onError) onError();
		},
	});
}

/**
 * Handles radio button selection across multiple saved payment method lists.
 *
 * This script ensures that only one radio button across multiple 
 * `.woocommerce-SavedPaymentMethods` lists can be selected at a time.
 *
 * Behavior:
 * - If a radio button inside one list is selected, all radios in the other lists are deselected.
 * - Supports dynamically added elements by using event delegation on `document`.
 *
 * Implementation details:
 * - Uses jQuery's `on('change')` with delegation to handle dynamically loaded content.
 * - `currentUl` stores the UL containing the selected radio.
 * - `otherUls` targets all other ULs to clear their selected radios.
 *
 * Example:
 * If the user selects a radio in the first UL and then selects a radio in the second UL,
 * the first UL's selected radio is automatically deselected.
 */
jQuery(function($) {
    $(document).on('change', '.woocommerce-SavedPaymentMethods input[type="radio"]', function() {
        const currentUl = $(this).closest('.woocommerce-SavedPaymentMethods'); // Current UL.
        const otherUls = $('.woocommerce-SavedPaymentMethods').not(currentUl);  // All other ULs.

        // Uncheck all radios in the other ULs.
        otherUls.find('input[type="radio"]').prop('checked', false);
    });
});

/**
 * Toggles the visibility of the Checkout Payment FLOW label based on the presence 
 * of the 'saved-card-is-enabled' class on the web component root.
 *
 * Behavior:
 * 1. Initially hides the label if the body has class 'flow-method-single' and the
 *    root element exists without 'saved-card-is-enabled'.
 * 2. Shows the label if 'saved-card-is-enabled' class is present on the root element.
 * 3. Dynamically observes the root element:
 *    - Detects when the root element appears in the DOM.
 *    - Watches for class changes on the root to toggle the label visibility in real-time.
 *
 * Uses MutationObserver to handle dynamically loaded elements and class changes.
 */
document.addEventListener('DOMContentLoaded', function () {
    const labelSelector = 'label[for="payment_method_wc_checkout_com_flow"]';
    const rootSelector = '[data-testid="checkout-web-component-root"]';

    const checkAndToggle = () => {
        const label = document.querySelector(labelSelector);
        const rootElement = document.querySelector(rootSelector);
        
        // Check if we have saved cards (new behavior)
        const hasSavedCards = document.querySelector('.saved-cards-accordion-container');

        if (label) {
            // ALWAYS show the label (either with saved cards or without)
            label.style.removeProperty('display');
            
            if (hasSavedCards) {
                console.log('[FLOW LABEL] Label shown - saved cards present');
            } else {
                console.log('[FLOW LABEL] Label shown - no saved cards (default label)');
            }
            
            /* DISABLED - Old behavior that was hiding the label
            // Old behavior: only for flow-method-single (no saved cards)
            if (document.body.classList.contains('flow-method-single') && rootElement) {
                if (rootElement.classList.contains('saved-card-is-enabled')) {
                    label.style.removeProperty('display'); // Show again.
                } else {
                    label.style.setProperty('display', 'none', 'important'); // Hide.
                }
            }
            */
        }
    };

    // Watch for root element and its class changes.
    const observer = new MutationObserver(() => {
        const rootElement = document.querySelector(rootSelector);

        if (rootElement) {
            // Run once when root appears.
            checkAndToggle();

            // Now watch the root for class changes.
            const classObserver = new MutationObserver(checkAndToggle);
            classObserver.observe(rootElement, { attributes: true, attributeFilter: ['class'] });

            observer.disconnect(); // Stop watching for root element.
        }
    });

    observer.observe(document.body, { childList: true, subtree: true });
    
    // Also check immediately and after a delay for saved cards
    checkAndToggle();
    setTimeout(checkAndToggle, 500);
    setTimeout(checkAndToggle, 1000);
});

/**
 * On DOMContentLoaded, checks if the current order payment type is "MOTO".
 * If so, it ensures that no saved payment tokens are pre-selected.
 * 
 * - Looks for the #order-pay-info element and reads its "order-pay" data attribute.
 * - If payment_type is "MOTO" (Admin order + Order-pay page + Guest customer):
 *   - Finds all saved payment token radio inputs from Checkout.com (Flow & Cards).
 *   - Iterates through them and unchecks each one, forcing the user to actively select a payment method.
 */
document.addEventListener('DOMContentLoaded', function () {
	const orderPayInfo = jQuery("#order-pay-info")?.data("order-pay");
	if ( orderPayInfo?.payment_type === 'MOTO' ) {
		console.log('here');
		const radios = document.querySelectorAll(
			'input[name="wc-wc_checkout_com_flow-payment-token"], input[name="wc-wc_checkout_com_cards-payment-token"]'
		);
		radios.forEach(radio => {
			radio.checked = false;
		});
	}
});

/**
 * Handle asynchronous payment return flow.
 * This checks for a payment ID in the URL, verifies its status via an API call,
 * and either submits the checkout form or displays an error.
 */

// Extract the 'cko-payment-id' parameter from the URL query string.
const paymentId = new URLSearchParams(window.location.search).get(
	"cko-payment-id"
);
// Async payment handler - handles 3DS redirects


// Proceed only if a payment ID is found.
if (paymentId) {

	// Fetch payment status from the server using the async endpoint.
	fetch(`${cko_flow_vars.async_url}?paymentId=${paymentId}`)
		.then((res) => res.json())
		.then((data) => {

			// If payment is approved, set hidden fields with the payment data and submit checkout form.
			if (data.approved) {
				jQuery("#cko-flow-payment-id").val(data.id);
				jQuery("#cko-flow-payment-type").val(data.source?.type || "");
				
				// Check if we're on order-pay page
				if (window.location.pathname.includes('/order-pay/')) {
					console.log('[FLOW] Order-pay page - submitting form#order_review after 3DS using native submit');
					// Use native DOM submit() to bypass jQuery event handlers and force form POST to server
					const orderPayForm = document.querySelector('form#order_review');
					if (orderPayForm) {
						orderPayForm.submit();
					} else {
						ckoLogger.error('ERROR: form#order_review not found!');
					}
				} else {
					jQuery("form.checkout").submit();
				}
			} else {

				// If payment is not approved, show an error message to the user.
				showError(
					wp.i18n.__(
						"Payment Failed. Please try some another payment method.",
						"checkout-com-unified-payments-api"
					)
				);

				// Clean up the URL by removing the query parameters.
				const urlWithoutQuery =
					window.location.origin + window.location.pathname;
				window.history.replaceState({}, document.title, urlWithoutQuery);
			}
		})
		.catch((err) => {
			// console.log('[CURRENT VERSION] Error fetching payment status:', err);

			// Log any network or parsing errors in the console.
			console.error("Error fetching payment status:", err);
		});
} else {
	// console.log('[CURRENT VERSION] No payment ID found in URL, skipping async handler');
}
