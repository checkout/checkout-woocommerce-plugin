/**
 * Checkout.com Flow — WooCommerce Blocks integration.
 *
 * Loads the Checkout Web Components (Flow) SDK, creates a payment session via the
 * plugin's existing wc-ajax endpoint, mounts the Flow component, and on payment
 * completion hands `cko-flow-payment-id` / `cko-flow-payment-type` to the server —
 * the same fields the classic Flow process_payment() consumes.
 *
 * NOTE: first-pass Blocks port of the classic flow-integration/assets/js/payment-session.js.
 * Requires validation in a live Blocks + Checkout.com sandbox (session amount/capture,
 * 3DS, saved cards).
 */
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { getSetting } from '@woocommerce/settings';
import { useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { loadScript } from './shared';

const PAYMENT_METHOD_NAME = 'wc_checkout_com_flow';
const settings = getSetting( `${ PAYMENT_METHOD_NAME }_data`, {} );
const FLOW_SDK_SRC = 'https://checkout-web-components.checkout.com/index.js';

/**
 * Map the WooCommerce Blocks billing address to a Checkout.com payment-session billing block.
 * Returns null when there's no usable 2-letter country yet (Checkout.com rejects an empty country).
 */
const buildBilling = ( billing ) => {
	const a = ( billing && billing.billingAddress ) || {};
	if ( ! a.country || ! /^[A-Za-z]{2}$/.test( a.country ) ) {
		return null;
	}
	return {
		address: {
			address_line1: a.address_1 || '',
			address_line2: a.address_2 || '',
			city: a.city || '',
			state: a.state || '',
			zip: a.postcode || '',
			country: a.country,
		},
	};
};

const buildCustomer = ( billing ) => {
	const a = ( billing && billing.billingAddress ) || {};
	if ( ! a.email ) {
		return null;
	}
	return {
		email: a.email,
		name: [ a.first_name, a.last_name ].filter( Boolean ).join( ' ' ).trim(),
	};
};

const CheckoutComFlowContent = ( props ) => {
	const { eventRegistration, emitResponse, billing, shouldSavePayment } = props;
	const { onPaymentSetup } = eventRegistration;
	const containerRef = useRef( null );
	const paymentRef = useRef( { id: '', type: 'card' } );
	const flowComponentRef = useRef( null );
	const sessionIdRef = useRef( '' );
	const billingRef = useRef( billing );
	billingRef.current = billing;

	// Blocks toggles this via the "save payment information" checkbox (shown because the
	// method supports tokenization). Kept in a ref so the once-subscribed onPaymentSetup
	// reads the latest value. Sent as cko-flow-save-card-persist so the server stamps
	// _cko_save_card_preference and flow_save_cards() stores the token after payment.
	const shouldSaveRef = useRef( shouldSavePayment );
	shouldSaveRef.current = shouldSavePayment;

	// Keep the latest emitResponse in a ref so the onPaymentSetup effect can subscribe
	// exactly once (stable deps) without re-subscribing every render — re-subscribing on
	// each render triggers a store update -> re-render loop ("Maximum update depth exceeded").
	const emitRef = useRef( emitResponse );
	emitRef.current = emitResponse;

	// Create a payment session and mount the Flow component.
	useEffect( () => {
		let cancelled = false;

		const init = async () => {
			try {
				await loadScript( FLOW_SDK_SRC, 'cko-flow-sdk-blocks' );

				if ( ! settings.create_session_url ) {
					return;
				}

				// The server re-derives amount/items/currency from the live WooCommerce cart, so this
				// request only needs the fields the server doesn't fill. success_url/failure_url are
				// REQUIRED by Checkout.com's Create Payment Session API — a redirect 3DS challenge
				// returns to wc_checkoutcom_flow_process, which finalises the order server-side.
				const paymentSessionRequest = {
					currency: settings.currency,
					reference: 'wc-blocks-' + Date.now(),
					success_url: window.location.origin + '/?wc-api=wc_checkoutcom_flow_process',
					failure_url: window.location.href,
					// Vault the card so Checkout.com returns source.id (needed to store a WC token).
					// Mirrors the classic payment-session.js: store_payment_details is "enabled"
					// whenever the admin "Enable Save Cards" setting is on — not the per-order
					// checkbox. The checkbox only gates whether the WC saved-card token is persisted
					// afterwards (handled server-side via the save-card preference).
					payment_method_configuration: {
						card: {
							store_payment_details: settings.save_card_enabled ? 'enabled' : 'disabled',
						},
					},
				};
				const billingObj = buildBilling( billing );
				if ( billingObj ) {
					paymentSessionRequest.billing = billingObj;
				}
				const customerObj = buildCustomer( billing );
				if ( customerObj ) {
					paymentSessionRequest.customer = customerObj;
				}

				const response = await fetch( settings.create_session_url, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					credentials: 'same-origin',
					body: new URLSearchParams( {
						action: 'cko_flow_create_payment_session',
						nonce: settings.create_session_nonce || '',
						context: 'blocks',
						payment_session_request: JSON.stringify( paymentSessionRequest ),
					} ),
				} );
				const session = await response.json();

				if ( cancelled || ! window.CheckoutWebComponents || ! session || ! session.success ) {
					return;
				}

				// Remember the session id — needed by the submit bridge below.
				sessionIdRef.current = ( session.data && session.data.id ) ? session.data.id : '';

				const checkout = await window.CheckoutWebComponents( {
					publicKey: settings.public_key,
					environment: settings.environment === 'PRODUCTION' ? 'production' : 'sandbox',
					paymentSession: session.data,
					// The SDK calls handleSubmit when the payment is submitted (we drive submit() from the
					// Place Order button). This bridges to the server's cko_flow_submit_payment_session,
					// which re-derives the amount from the cart/order, sets capture_on and enables 3DS,
					// then calls Checkout.com's /payment-sessions/{id}/submit. Mirrors the classic flow —
					// the SDK must NOT use its default submit.
					handleSubmit: async ( _self, submitData ) => {
						const bill = billingRef.current;
						const body = new URLSearchParams( {
							action: 'cko_flow_submit_payment_session',
							nonce: settings.create_session_nonce || '',
							payment_session_id: sessionIdRef.current,
							session_data: ( submitData && submitData.session_data ) ? submitData.session_data : '',
							// Persist the save-card choice server-side (into the WC session) at submit time,
							// so it survives a 3DS full-page redirect where paymentMethodData is lost.
							save_card: shouldSaveRef.current ? 'yes' : 'no',
						} );
						const billingObj2 = buildBilling( bill );
						if ( billingObj2 ) {
							body.append( 'billing', JSON.stringify( billingObj2 ) );
						}
						const res = await fetch( settings.submit_session_url, {
							method: 'POST',
							headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
							credentials: 'same-origin',
							body,
						} );
						const json = await res.json();
						if ( ! json || ! json.success ) {
							throw new Error( ( json && json.data && json.data.message ) || 'Payment submission failed.' );
						}
						// Return the Checkout.com submit result to the SDK so it can complete / run 3DS.
						return json.data;
					},
					onPaymentCompleted: ( _component, paymentResponse ) => {
						paymentRef.current = {
							id: paymentResponse && paymentResponse.id ? paymentResponse.id : '',
							type: ( paymentResponse && paymentResponse.payment_type ) || 'card',
						};
					},
				} );

				// showPayButton: false — hide Flow's own embedded pay button; the WooCommerce Blocks
				// "Place Order" button drives submission via flowComponent.submit() in onPaymentSetup.
				flowComponentRef.current = checkout.create( 'flow', { showPayButton: false } );
				if ( containerRef.current ) {
					flowComponentRef.current.mount( containerRef.current );
				}
			} catch ( e ) {
				// Leave the container empty; onPaymentSetup will surface an error.
			}
		};

		init();

		return () => {
			cancelled = true;
			if ( flowComponentRef.current && flowComponentRef.current.unmount ) {
				flowComponentRef.current.unmount();
			}
		};
	}, [] );

	useEffect( () => {
		const unsubscribe = onPaymentSetup( async () => {
			const { responseTypes } = emitRef.current;
			try {
				// Persist the save-card choice into the WC session BEFORE submit. On the 3DS
				// path the payment completes via a full-page redirect (handle_3ds_return) with
				// no POST body, so the paymentMethodData below never reaches the server; the
				// session value does, and the save-card check reads it. Mirrors the classic
				// cko_flow_store_save_card_preference AJAX. Harmless for guests (they can't save).
				if ( settings.store_save_card_url ) {
					try {
						await fetch( settings.store_save_card_url, {
							method: 'POST',
							headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
							credentials: 'same-origin',
							body: new URLSearchParams( {
								action: 'cko_flow_store_save_card_preference',
								nonce: settings.create_session_nonce || '',
								save_card_value: shouldSaveRef.current ? 'yes' : 'no',
							} ),
						} );
					} catch ( persistErr ) {
						// Non-fatal: the inline (non-3DS) path still carries the flag in paymentMethodData.
					}
				}

				// Ask the mounted Flow component to submit / tokenise.
				if ( flowComponentRef.current && flowComponentRef.current.submit ) {
					await flowComponentRef.current.submit();
				}

				const { id, type } = paymentRef.current;
				if ( ! id ) {
					return {
						type: responseTypes.ERROR,
						message: __( 'Payment could not be completed. Please try again.', 'checkout-com-unified-payments-api' ),
					};
				}

				return {
					type: responseTypes.SUCCESS,
					meta: {
						paymentMethodData: {
							'cko-flow-payment-id': id,
							'cko-flow-payment-type': type,
							'cko-flow-payment-session-id': sessionIdRef.current,
							'cko-flow-save-card-persist': shouldSaveRef.current ? 'yes' : 'no',
						},
					},
				};
			} catch ( err ) {
				return {
					type: responseTypes.ERROR,
					message: ( err && err.message ) || __( 'Payment failed. Please try again.', 'checkout-com-unified-payments-api' ),
				};
			}
		} );

		return () => unsubscribe();
		// Subscribe exactly once on mount. onPaymentSetup is stable; re-running this effect
		// tears down/re-adds the observer each render and loops via WooCommerce's store.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	return (
		<div className="cko-blocks-flow">
			<div id="flow-container" ref={ containerRef }></div>
		</div>
	);
};

/**
 * Rendered when a saved card is selected (instead of the Flow SDK). Replicates the classic
 * saved-card path: rather than creating/submitting a Flow payment session, it hands the WC
 * token id to the server under `wc-wc_checkout_com_flow-payment-token`. The existing
 * process_payment() -> is_using_saved_payment_method() -> create_payment() RequestIdSource
 * branch then charges the stored source (src_...) directly via the Checkout.com API.
 *
 * Blocks passes the selected token id in as the `token` prop.
 */
const CheckoutComFlowSavedToken = ( props ) => {
	const { eventRegistration, emitResponse, token } = props;
	const { onPaymentSetup } = eventRegistration;
	const cvvRef = useRef( '' );

	// Latest emitResponse/token in a ref so onPaymentSetup subscribes once (stable deps).
	const latestRef = useRef( { emitResponse, token } );
	latestRef.current = { emitResponse, token };

	useEffect( () => {
		const unsubscribe = onPaymentSetup( async () => {
			const { emitResponse: emit, token: tok } = latestRef.current;
			const { responseTypes } = emit;

			if ( ! tok ) {
				return {
					type: responseTypes.ERROR,
					message: __( 'Please select a saved card.', 'checkout-com-unified-payments-api' ),
				};
			}

			const paymentMethodData = {
				// Field the classic is_using_saved_payment_method() reads (value = WC token id).
				'wc-wc_checkout_com_flow-payment-token': String( tok ),
				// Ensures the server resolves the Flow token field (not the default cards one).
				payment_method: PAYMENT_METHOD_NAME,
			};

			if ( settings.require_cvv ) {
				if ( ! cvvRef.current ) {
					return {
						type: responseTypes.ERROR,
						message: __( 'Please enter your card security code.', 'checkout-com-unified-payments-api' ),
					};
				}
				// Same field create_payment() reads when CVV is required for saved cards.
				paymentMethodData[ 'wc_checkout_com_cards-card-cvv' ] = cvvRef.current;
			}

			return {
				type: responseTypes.SUCCESS,
				meta: { paymentMethodData },
			};
		} );

		return () => unsubscribe();
		// Subscribe exactly once on mount (see note in the content component).
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	if ( ! settings.require_cvv ) {
		return null;
	}

	return (
		<div className="cko-blocks-saved-card-cvv">
			<label htmlFor="cko-flow-saved-cvv">
				{ __( 'Card security code', 'checkout-com-unified-payments-api' ) }
			</label>
			<input
				id="cko-flow-saved-cvv"
				type="text"
				inputMode="numeric"
				autoComplete="cc-csc"
				maxLength="4"
				onChange={ ( e ) => {
					cvvRef.current = ( e.target.value || '' ).replace( /\D/g, '' );
				} }
			/>
		</div>
	);
};

registerPaymentMethod( {
	name: PAYMENT_METHOD_NAME,
	label: settings.title || __( 'Checkout.com', 'checkout-com-unified-payments-api' ),
	ariaLabel: settings.description || 'Checkout.com Flow',
	content: <CheckoutComFlowContent />,
	savedTokenComponent: <CheckoutComFlowSavedToken />,
	edit: <div>{ settings.title || __( 'Checkout.com', 'checkout-com-unified-payments-api' ) }</div>,
	canMakePayment: () => true,
	supports: {
		features: settings.supports || [ 'products' ],
		// Render the saved-card radio list and the save-card checkbox (logged-in users only).
		showSavedCards: true,
		showSaveOption: true,
	},
} );
