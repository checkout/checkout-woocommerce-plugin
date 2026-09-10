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
import { __, sprintf } from '@wordpress/i18n';
import { dispatch } from '@wordpress/data';
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
	let country = a.country;
	if ( ! country || ! /^[A-Za-z]{2}$/.test( country ) ) {
		// "Collect only name & email" checkouts don't capture a country. Fall back to the store
		// base country so Checkout.com can still route card payments (mirrors classic). Only in
		// this mode — normal checkouts keep the original behaviour (no billing until entered).
		if ( settings.address_not_required && settings.base_country && /^[A-Za-z]{2}$/.test( settings.base_country ) ) {
			country = settings.base_country;
		} else {
			return null;
		}
	}
	return {
		address: {
			address_line1: a.address_1 || '',
			address_line2: a.address_2 || '',
			city: a.city || '',
			state: a.state || '',
			zip: a.postcode || '',
			country,
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

/**
 * Map a Flow SDK error / decline into a human message, mirroring the classic
 * payment-session.js onError handling. A declined payment surfaces here (not as an
 * HTTP error) because Checkout.com returns 201 for a decline and the SDK raises it
 * via onError as `payment_request_declined`.
 */
const mapFlowError = ( error ) => {
	let raw = '';
	if ( typeof error === 'string' ) {
		raw = error;
	} else if ( error && error.message ) {
		raw = String( error.message );
	} else if ( error ) {
		try {
			raw = JSON.stringify( error );
		} catch ( e ) {
			raw = String( error );
		}
	}
	const lower = raw.toLowerCase();
	if ( lower.indexOf( 'payment_request_declined' ) !== -1 || lower.indexOf( 'declined' ) !== -1 ) {
		return __( 'Your payment was declined. Please check your card details and try again, or use a different payment method.', 'checkout-com-unified-payments-api' );
	}
	if ( lower.indexOf( 'component_invalid' ) !== -1 ) {
		return __( 'Please complete your payment details before placing the order.', 'checkout-com-unified-payments-api' );
	}
	if ( lower.indexOf( 'payment_request_failed' ) !== -1 || lower.indexOf( 'network' ) !== -1 ) {
		return __( 'Payment request failed. Please try again.', 'checkout-com-unified-payments-api' );
	}
	return __( 'Something went wrong with your payment. Please try again.', 'checkout-com-unified-payments-api' );
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

	// Holds the latest real CKO error/decline message (set by the SDK onError or a session
	// create failure) so onPaymentSetup can surface it in the Blocks notice area instead of
	// a generic fallback.
	const errorRef = useRef( '' );

	// Email baked into the active payment session; used to recreate the session when it changes
	// (customer email is immutable in an existing session and can't be updated at submit).
	const emailRef = useRef( '' );
	// Generation token so an in-flight init aborts if a newer create/teardown supersedes it.
	const initGenRef = useRef( 0 );

	const teardownFlow = () => {
		if ( flowComponentRef.current && flowComponentRef.current.unmount ) {
			try {
				flowComponentRef.current.unmount();
			} catch ( e ) {
				// ignore unmount errors
			}
		}
		flowComponentRef.current = null;
		sessionIdRef.current = '';
		paymentRef.current = { id: '', type: 'card' };
		if ( containerRef.current ) {
			containerRef.current.innerHTML = '';
		}
	};

	// Create a payment session and mount the Flow component.
	const initFlow = async () => {
		const gen = ++initGenRef.current;
		const isStale = () => gen !== initGenRef.current;
		try {
			await loadScript( FLOW_SDK_SRC, 'cko-flow-sdk-blocks' );

			if ( isStale() || ! settings.create_session_url ) {
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
					// On a 3DS decline, Checkout.com redirects (full page) to failure_url. Bring the
					// shopper back to the checkout page with a marker we detect on load to show a
					// Blocks error notice — otherwise the decline is invisible on Blocks (no classic
					// WC notice is rendered). See surfaceFailedReturn().
					failure_url: window.location.origin + window.location.pathname + '?cko_flow_status=failed',
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

				// 3DS config — mirrors classic payment-session.js. The detail params are only applied
				// at session create (the shared submit handler just forces enabled:true), so send them
				// here from the admin settings: attempt non-3DS, challenge indicator, exemption, upgrade.
				const threeDs = settings.three_ds || {};
				const threeDsRequest = {
					enabled: !! threeDs.enabled,
					attempt_n3d: !! threeDs.attempt_n3d,
					challenge_indicator: threeDs.challenge_indicator || 'no_preference',
					allow_upgrade: !! threeDs.allow_upgrade,
				};
				if ( threeDs.exemption ) {
					threeDsRequest.exemption = threeDs.exemption;
				}
				paymentSessionRequest[ '3ds' ] = threeDsRequest;

				const billingObj = buildBilling( billingRef.current );
				if ( billingObj ) {
					paymentSessionRequest.billing = billingObj;
				}
				const customerObj = buildCustomer( billingRef.current );
				if ( customerObj ) {
					paymentSessionRequest.customer = customerObj;
					// Remember the email baked into this session so we can detect a later change.
					emailRef.current = customerObj.email || '';
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

				if ( isStale() || ! window.CheckoutWebComponents || ! session || ! session.success ) {
					// Session creation failed — remember a message so onPaymentSetup can surface it.
					if ( ! isStale() && session && ! session.success ) {
						errorRef.current = ( session.data && session.data.message )
							|| __( 'Unable to start the payment. Please refresh the page and try again.', 'checkout-com-unified-payments-api' );
					}
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
						const data = json.data || {};
						// A declined payment comes back as HTTP 201 "success" with status "Declined"
						// (approved:false) and NO further action. The SDK doesn't reliably raise this,
						// so detect it here and throw — that rejects submit() and onPaymentSetup surfaces
						// the message. A 3DS challenge (data.action.url) is NOT a decline: let it through.
						const hasAction = !! ( data.action && data.action.url );
						const isDeclined = data.approved === false
							|| ( typeof data.status === 'string' && /declin|fail|expired|cancel/i.test( data.status ) );
						if ( ! hasAction && isDeclined ) {
							errorRef.current = data.response_summary || mapFlowError( 'payment_request_declined' );
							throw new Error( errorRef.current );
						}
						// Return the Checkout.com submit result to the SDK so it can complete / run 3DS.
						return data;
					},
					onPaymentCompleted: ( _component, paymentResponse ) => {
						// Guard against a declined response arriving here: don't hand a declined
						// payment id back to Blocks as success.
						const declined = paymentResponse
							&& ( paymentResponse.approved === false
								|| ( typeof paymentResponse.status === 'string' && /declin|fail/i.test( paymentResponse.status ) ) );
						if ( declined ) {
							errorRef.current = paymentResponse.response_summary || mapFlowError( 'payment_request_declined' );
							paymentRef.current = { id: '', type: 'card' };
							return;
						}
						paymentRef.current = {
							id: paymentResponse && paymentResponse.id ? paymentResponse.id : '',
							type: ( paymentResponse && paymentResponse.payment_type ) || 'card',
						};
					},
					// Declines and SDK failures come through here (a decline is HTTP 201, so it is
					// not thrown by handleSubmit). Store the mapped message for onPaymentSetup.
					onError: ( _component, error ) => {
						errorRef.current = mapFlowError( error );
					},
				} );

				if ( isStale() ) {
					return;
				}

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

		// Create the session + mount the component once on mount.
		useEffect( () => {
			initFlow();
			return () => {
				// Invalidate any in-flight init and unmount.
				initGenRef.current++;
				teardownFlow();
			};
			// eslint-disable-next-line react-hooks/exhaustive-deps
		}, [] );

		// Recreate the session when the customer email changes. The email is baked into the payment
		// session at creation and cannot be changed at submit, so a mid-checkout email change would
		// otherwise leave a stale session (mirrors classic, which reloads Flow on email change).
		// Debounced; only once a session exists and the new email is a valid, different address.
		const currentEmail = ( billing && billing.billingAddress && billing.billingAddress.email ) || '';
		useEffect( () => {
			if ( ! sessionIdRef.current ) {
				return undefined;
			}
			if ( ! currentEmail || currentEmail === emailRef.current ) {
				return undefined;
			}
			if ( ! /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test( currentEmail ) ) {
				return undefined;
			}
			const timer = setTimeout( () => {
				teardownFlow();
				initFlow();
			}, 800 );
			return () => clearTimeout( timer );
			// eslint-disable-next-line react-hooks/exhaustive-deps
		}, [ currentEmail ] );

	useEffect( () => {
		const unsubscribe = onPaymentSetup( async () => {
			const { responseTypes } = emitRef.current;
			try {
				// Note: the save-card choice is persisted server-side by the submit-session call
				// (save_card param -> WC session), which is the step guaranteed to run before any
				// 3DS redirect. No separate preference AJAX is needed here.

				// Ask the mounted Flow component to submit / tokenise.
				if ( flowComponentRef.current && flowComponentRef.current.submit ) {
					// Reset any stale decline/error from a previous attempt so onError reflects
					// this submit. A session-create failure error is kept (no component to submit).
					errorRef.current = '';
					await flowComponentRef.current.submit();
				}

				const { id, type } = paymentRef.current;
				if ( ! id ) {
					return {
						type: responseTypes.ERROR,
						message: errorRef.current
							|| __( 'Payment could not be completed. Please try again.', 'checkout-com-unified-payments-api' ),
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
					// Prefer the mapped SDK/decline message; fall back to the thrown server
					// message (submit HTTP >=400 carries CKO error_codes) then a generic one.
					message: errorRef.current
						|| ( err && err.message )
						|| __( 'Payment failed. Please try again.', 'checkout-com-unified-payments-api' ),
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

/**
 * Detect a return from a failed 3DS challenge and surface it as a Blocks error notice.
 *
 * On a 3DS decline Checkout.com does a full-page redirect to our failure_url (the checkout
 * page + `?cko_flow_status=failed`). Blocks does not render the classic WooCommerce notice
 * that the server sets, so without this the decline is invisible. We push an error notice
 * into the checkout context and strip the marker (and CKO's appended params) from the URL so
 * a refresh does not re-show it.
 */
const surfaceFailedReturn = async () => {
	try {
		const params = new URLSearchParams( window.location.search );
		if ( params.get( 'cko_flow_status' ) !== 'failed' ) {
			return;
		}

		const genericMessage = __( 'Your payment was not completed. Please try again or use a different payment method.', 'checkout-com-unified-payments-api' );
		let message = genericMessage;

		// Try to show Checkout.com's actual decline reason. The failure return carries the
		// payment id; the nonce-protected payment-status route returns response_summary/status.
		const paymentId = params.get( 'cko-payment-id' );
		if ( paymentId && settings.payment_status_url && settings.create_session_nonce ) {
			try {
				const sep = settings.payment_status_url.indexOf( '?' ) !== -1 ? '&' : '?';
				const url = settings.payment_status_url + sep
					+ 'paymentId=' + encodeURIComponent( paymentId )
					+ '&nonce=' + encodeURIComponent( settings.create_session_nonce );
				const res = await fetch( url, { credentials: 'same-origin' } );
				const data = await res.json();
				if ( data && data.response_summary ) {
					message = data.response_summary;
				} else if ( data && data.status ) {
					/* translators: %s: Checkout.com payment status. */
					message = sprintf( __( 'Payment failed with status: %s', 'checkout-com-unified-payments-api' ), data.status );
				}
			} catch ( fetchErr ) {
				// Keep the generic message.
			}
		}

		const notices = dispatch( 'core/notices' );
		if ( notices && notices.createErrorNotice ) {
			notices.createErrorNotice( message, { context: 'wc/checkout', id: 'cko-flow-failed' } );
		}

		// Remove our marker and Checkout.com's appended params so the notice is one-shot.
		[ 'cko_flow_status', 'cko-payment-id', 'cko-payment-session-id', 'cko-session-id' ].forEach(
			( key ) => params.delete( key )
		);
		const qs = params.toString();
		window.history.replaceState( {}, '', window.location.pathname + ( qs ? '?' + qs : '' ) );
	} catch ( e ) {
		// no-op
	}
};

if ( window.wp && typeof window.wp.domReady === 'function' ) {
	window.wp.domReady( surfaceFailedReturn );
} else if ( document.readyState !== 'loading' ) {
	surfaceFailedReturn();
} else {
	document.addEventListener( 'DOMContentLoaded', surfaceFailedReturn );
}

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
		// Render the saved-card radio list and the save-card checkbox only when the admin
		// "Enable Save Cards" setting is on — matches classic, which hides the saved-card list
		// (and disables saving) when ckocom_card_saved is off.
		showSavedCards: !! settings.save_card_enabled,
		showSaveOption: !! settings.save_card_enabled,
	},
} );
