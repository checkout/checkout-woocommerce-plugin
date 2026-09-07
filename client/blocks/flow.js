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

const wcAjax = ( endpoint ) => {
	const vars = window.cko_flow_vars || {};
	if ( vars.wc_ajax_url ) {
		return vars.wc_ajax_url.replace( '%%endpoint%%', endpoint );
	}
	return vars.ajax_url || window.ajaxurl;
};

const CheckoutComFlowContent = ( props ) => {
	const { eventRegistration, emitResponse } = props;
	const { onPaymentSetup } = eventRegistration;
	const containerRef = useRef( null );
	const paymentRef = useRef( { id: '', type: 'card' } );
	const flowComponentRef = useRef( null );

	// Create a payment session and mount the Flow component.
	useEffect( () => {
		let cancelled = false;

		const init = async () => {
			try {
				await loadScript( FLOW_SDK_SRC, 'cko-flow-sdk-blocks' );

				const response = await fetch( wcAjax( 'cko_flow_create_payment_session' ), {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					credentials: 'same-origin',
					body: new URLSearchParams( {
						action: 'cko_flow_create_payment_session',
						context: 'blocks',
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

				flowComponentRef.current = checkout.create( 'flow' );
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
			try {
				// Ask the mounted Flow component to submit / tokenise.
				if ( flowComponentRef.current && flowComponentRef.current.submit ) {
					await flowComponentRef.current.submit();
				}

				const { id, type } = paymentRef.current;
				if ( ! id ) {
					return {
						type: emitResponse.responseTypes.ERROR,
						message: __( 'Payment could not be completed. Please try again.', 'checkout-com-unified-payments-api' ),
					};
				}

				return {
					type: emitResponse.responseTypes.SUCCESS,
					meta: {
						paymentMethodData: {
							'cko-flow-payment-id': id,
							'cko-flow-payment-type': type,
						},
					},
				};
			} catch ( err ) {
				return {
					type: emitResponse.responseTypes.ERROR,
					message: ( err && err.message ) || __( 'Payment failed. Please try again.', 'checkout-com-unified-payments-api' ),
				};
			}
		} );

		return () => unsubscribe();
	}, [ onPaymentSetup, emitResponse ] );

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
