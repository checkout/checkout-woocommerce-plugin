/**
 * Checkout.com PayPal — WooCommerce Blocks integration.
 *
 * Registered as a standard payment method. Checkout.com PayPal uses a payment-context
 * redirect flow handled server-side, so the Blocks component simply selects the method
 * and lets process_payment() drive the PayPal context/redirect (result 'redirect').
 *
 * NOTE: first-pass; validate the redirect/return flow in a live Blocks + sandbox environment.
 */
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { getSetting } from '@woocommerce/settings';
import { __ } from '@wordpress/i18n';

const PAYMENT_METHOD_NAME = 'wc_checkout_com_paypal';
const settings = getSetting( `${ PAYMENT_METHOD_NAME }_data`, {} );

const Content = () => {
	return (
		<div className="cko-blocks-paypal">
			{ settings.description ||
				__( 'You will be redirected to PayPal to complete your payment.', 'checkout-com-unified-payments-api' ) }
		</div>
	);
};

registerPaymentMethod( {
	name: PAYMENT_METHOD_NAME,
	label: settings.title || 'PayPal',
	ariaLabel: settings.description || 'PayPal',
	content: <Content />,
	edit: <Content />,
	canMakePayment: () => true,
	supports: {
		features: settings.supports || [ 'products' ],
	},
} );
