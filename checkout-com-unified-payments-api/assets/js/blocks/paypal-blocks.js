/**
 * External dependencies
 */
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { getSetting } from '@woocommerce/settings';

/**
 * Internal dependencies
 */
import { PayPalExpressCheckout } from './paypal-express-checkout';

const settings = getSetting( 'wc_checkout_com_paypal_data', {} );

// Register PayPal Express Checkout
registerPaymentMethod( {
    name: 'wc_checkout_com_paypal',
    label: settings.title || 'PayPal',
    content: <PayPalExpressCheckout />,
    edit: <PayPalExpressCheckout />,
    canMakePayment: () => true,
    ariaLabel: settings.description || 'PayPal Express Checkout',
    supports: {
        features: settings.supports || [],
    },
} );
