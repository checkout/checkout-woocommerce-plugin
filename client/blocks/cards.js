/**
 * Checkout.com Cards — WooCommerce Blocks integration.
 *
 * Mounts Checkout.com Frames (v2) inside the Blocks checkout, tokenises the card
 * on payment setup, and hands the resulting token to the server as `cko-card-token`
 * — the exact field the existing classic process_payment() already consumes.
 */
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { getSetting } from '@woocommerce/settings';
import { useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { loadScript, getCardholderName } from './shared';

const PAYMENT_METHOD_NAME = 'wc_checkout_com_cards';
const settings = getSetting( `${ PAYMENT_METHOD_NAME }_data`, {} );
const FRAMES_SRC = 'https://cdn.checkout.com/js/framesv2.min.js';

/**
 * The card form + Frames lifecycle, wired into the Blocks payment event API.
 */
const CheckoutComCardsContent = ( props ) => {
	const { eventRegistration, emitResponse, billing } = props;
	const { onPaymentSetup } = eventRegistration;
	const tokenRef = useRef( '' );
	const readyRef = useRef( false );

	// Latest emitResponse/billing in a ref so onPaymentSetup subscribes once with stable
	// deps — depending on emitResponse/billing (fresh each render) re-subscribes every
	// render and loops ("Maximum update depth exceeded").
	const latestRef = useRef( { emitResponse, billing } );
	latestRef.current = { emitResponse, billing };

	// Load Frames and initialise the card form once.
	useEffect( () => {
		let cancelled = false;

		const init = async () => {
			try {
				await loadScript( FRAMES_SRC, 'cko-frames-script-blocks' );
			} catch ( e ) {
				return;
			}
			if ( cancelled || ! window.Frames || ! settings.public_key ) {
				return;
			}

			window.Frames.init( {
				publicKey: settings.public_key,
				schemeChoice: true,
				modes: window.Frames.modes ? [ window.Frames.modes.FEATURE_FLAG_SCHEME_CHOICE ] : undefined,
				style: { base: { borderRadius: '3px' } },
			} );

			// Capture the token as soon as Frames tokenises the card.
			window.Frames.addEventHandler(
				window.Frames.Events.CARD_TOKENIZED,
				( event ) => {
					tokenRef.current = event && event.token ? event.token : '';
				}
			);

			// Keep the cardholder name in sync with the Blocks billing fields.
			window.Frames.addEventHandler(
				window.Frames.Events.CARD_VALIDATION_CHANGED,
				() => {
					const name = getCardholderName( billing );
					if ( name && window.Frames.isCardValid && window.Frames.isCardValid() ) {
						window.Frames.cardholder = { name };
					}
				}
			);

			readyRef.current = true;
		};

		init();

		return () => {
			cancelled = true;
			if ( window.Frames && window.Frames.removeAllEventHandlers ) {
				window.Frames.removeAllEventHandlers();
			}
		};
	}, [] );

	// On payment setup, tokenise and return the token as cko-card-token.
	useEffect( () => {
		const unsubscribe = onPaymentSetup( async () => {
			const { emitResponse: emit, billing: bill } = latestRef.current;
			const { responseTypes } = emit;
			try {
				if ( ! window.Frames ) {
					return {
						type: responseTypes.ERROR,
						message: __( 'Card form is not ready. Please try again.', 'checkout-com-unified-payments-api' ),
					};
				}

				// Attach cardholder name before submitting.
				const name = getCardholderName( bill );
				if ( name ) {
					window.Frames.cardholder = { name };
				}

				// submitCard() resolves with { token } once tokenisation succeeds.
				const result = await window.Frames.submitCard();
				const token = ( result && result.token ) || tokenRef.current;

				if ( ! token ) {
					return {
						type: responseTypes.ERROR,
						message: __( 'Please enter valid card details.', 'checkout-com-unified-payments-api' ),
					};
				}

				return {
					type: responseTypes.SUCCESS,
					meta: {
						paymentMethodData: {
							'cko-card-token': token,
						},
					},
				};
			} catch ( err ) {
				if ( window.Frames && window.Frames.enableSubmitForm ) {
					window.Frames.enableSubmitForm();
				}
				return {
					type: responseTypes.ERROR,
					message:
						( err && err.message ) ||
						__( 'Card tokenization failed. Please check your details and try again.', 'checkout-com-unified-payments-api' ),
				};
			}
		} );

		return () => unsubscribe();
		// Subscribe exactly once on mount (see note in flow.js) to avoid a re-render loop.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	return <div className="cko-blocks-card-form"><div className="card-frame"></div></div>;
};

registerPaymentMethod( {
	name: PAYMENT_METHOD_NAME,
	label: settings.title || __( 'Credit / Debit Card', 'checkout-com-unified-payments-api' ),
	ariaLabel: settings.description || 'Checkout.com Cards',
	content: <CheckoutComCardsContent />,
	edit: <div>{ settings.title || __( 'Credit / Debit Card', 'checkout-com-unified-payments-api' ) }</div>,
	canMakePayment: () => true,
	supports: {
		features: settings.supports || [ 'products' ],
	},
} );
