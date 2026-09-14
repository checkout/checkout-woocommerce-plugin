/**
 * Checkout.com Apple Pay — WooCommerce Blocks EXPRESS integration.
 *
 * Registered via registerExpressPaymentMethod. On success it submits the classic
 * Apple Pay token field the server already reads: `cko-apple-card-token`.
 *
 * NOTE: first-pass. The Apple Pay session/merchant validation must be validated in a
 * live Blocks + sandbox environment on an Apple device/Safari; canMakePayment is gated
 * on ApplePaySession availability so it only shows when the wallet is usable.
 */
import { registerExpressPaymentMethod } from '@woocommerce/blocks-registry';
import { getSetting } from '@woocommerce/settings';
import { useEffect, useRef } from '@wordpress/element';

const PAYMENT_METHOD_NAME = 'wc_checkout_com_apple_pay';
const settings = getSetting( `${ PAYMENT_METHOD_NAME }_data`, {} );

const canUseApplePay = () =>
	typeof window !== 'undefined' &&
	window.ApplePaySession &&
	typeof window.ApplePaySession.canMakePayments === 'function' &&
	window.ApplePaySession.canMakePayments();

const ApplePayButton = ( props ) => {
	const { onClick } = props;
	const ref = useRef( null );

	useEffect( () => {
		if ( ! ref.current ) {
			return;
		}
		// Native Apple Pay button styling.
		ref.current.style.cssText =
			'-apple-pay-button-style: black; -webkit-appearance: -apple-pay-button; apple-pay-button-type: plain; display: block; width: 100%; height: 40px; cursor: pointer;';
	}, [] );

	return (
		<div
			className="cko-blocks-applepay-button apple-pay-button apple-pay-button-black"
			ref={ ref }
			onClick={ onClick }
			role="button"
			tabIndex={ 0 }
		></div>
	);
};

registerExpressPaymentMethod( {
	name: PAYMENT_METHOD_NAME,
	title: settings.title || 'Apple Pay',
	description: settings.description || 'Apple Pay',
	gatewayId: 'wc_checkout_com_apple_pay',
	content: <ApplePayButton />,
	edit: <div>Apple Pay</div>,
	canMakePayment: () => {
		try {
			return canUseApplePay();
		} catch ( e ) {
			return false;
		}
	},
	supports: {
		features: settings.supports || [ 'products' ],
	},
} );
