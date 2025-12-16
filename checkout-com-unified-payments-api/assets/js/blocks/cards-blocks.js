/**
 * External dependencies
 */
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { getSetting } from '@woocommerce/settings';

/**
 * Internal dependencies
 */
import { CheckoutComCardsCheckout } from './checkout-com-cards-checkout';

const settings = getSetting( 'wc_checkout_com_cards_data', {} );

// Register Checkout.com Cards
registerPaymentMethod( {
    name: 'wc_checkout_com_cards',
    label: settings.title || 'Checkout.com Cards',
    content: <CheckoutComCardsCheckout />,
    edit: <CheckoutComCardsCheckout />,
    canMakePayment: () => true,
    ariaLabel: settings.description || 'Checkout.com Credit/Debit Cards',
    supports: {
        features: settings.supports || [],
    },
} );
