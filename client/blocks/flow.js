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
	const { eventRegistration, emitResponse, billing } = props;
	const { onPaymentSetup } = eventRegistration;
	const containerRef = useRef( null );
	const paymentRef = useRef( { id: '', type: 'card' } );
	const flowComponentRef = useRef( null );

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
				// request only needs the fields the server doesn't fill. Success/failure URLs cover the
				// 3DS redirect fallback; Flow itself completes via the onPaymentCompleted callback.
				const paymentSessionRequest = {
					currency: settings.currency,
					reference: 'wc-blocks-' + Date.now(),
					success_url: window.location.origin + '/?wc-api=wc_checkoutcom_flow_process',
					failure_url: window.location.href,
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

				const checkout = await window.CheckoutWebComponents( {
					publicKey: settings.public_key,
					environment: settings.environment === 'PRODUCTION' ? 'production' : 'sandbox',
					paymentSession: session.data,
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

registerPaymentMethod( {
	name: PAYMENT_METHOD_NAME,
	label: settings.title || __( 'Checkout.com', 'checkout-com-unified-payments-api' ),
	ariaLabel: settings.description || 'Checkout.com Flow',
	content: <CheckoutComFlowContent />,
	edit: <div>{ settings.title || __( 'Checkout.com', 'checkout-com-unified-payments-api' ) }</div>,
	canMakePayment: () => true,
	supports: {
		features: settings.supports || [ 'products' ],
	},
} );
