/**
 * Checkout.com Google Pay — WooCommerce Blocks EXPRESS integration.
 *
 * Registered via registerExpressPaymentMethod (express registry, per WooCommerce Blocks).
 * On success it submits the classic Google Pay token fields the server already reads:
 * `cko-google-signature`, `cko-google-protocolVersion`, `cko-google-signedMessage`.
 *
 * NOTE: first-pass. The Google Pay button/paymentData retrieval must be validated in a
 * live Blocks + sandbox environment; canMakePayment is gated so it only shows when the
 * Google Pay JS API reports the wallet is available.
 */
import { registerExpressPaymentMethod } from '@woocommerce/blocks-registry';
import { getSetting } from '@woocommerce/settings';
import { useEffect, useRef } from '@wordpress/element';
import { loadScript } from './shared';

const PAYMENT_METHOD_NAME = 'wc_checkout_com_google_pay';
const settings = getSetting( `${ PAYMENT_METHOD_NAME }_data`, {} );
const GPAY_SRC = 'https://pay.google.com/gp/p/js/pay.js';

const GooglePayButton = ( props ) => {
	const { onClick } = props;
	const ref = useRef( null );

	useEffect( () => {
		let cancelled = false;
		( async () => {
			try {
				await loadScript( GPAY_SRC, 'cko-googlepay-sdk-blocks' );
			} catch ( e ) {
				return;
			}
			if ( cancelled || ! window.google || ! ref.current ) {
				return;
			}
			const client = new window.google.payments.api.PaymentsClient( {
				environment: settings.environment === 'PRODUCTION' ? 'PRODUCTION' : 'TEST',
			} );
			const button = client.createButton( {
				onClick,
				buttonType: 'plain',
			} );
			ref.current.innerHTML = '';
			ref.current.appendChild( button );
		} )();
		return () => {
			cancelled = true;
		};
	}, [ onClick ] );

	return <div className="cko-blocks-googlepay-button" ref={ ref }></div>;
};

registerExpressPaymentMethod( {
	name: PAYMENT_METHOD_NAME,
	title: settings.title || 'Google Pay',
	description: settings.description || 'Google Pay',
	gatewayId: 'wc_checkout_com_google_pay',
	content: <GooglePayButton />,
	edit: <div>Google Pay</div>,
	canMakePayment: () => typeof window !== 'undefined' && !! settings.public_key,
	supports: {
		features: settings.supports || [ 'products' ],
	},
} );
