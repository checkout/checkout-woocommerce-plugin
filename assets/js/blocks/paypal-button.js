/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { useEffect, useRef } from '@wordpress/element';

/**
 * PayPal Button Component
 */
export const PayPalButton = ({ onApprove, onError, isLoading }) => {
    const buttonRef = useRef(null);

    useEffect(() => {
        if (!window.paypal || !buttonRef.current) {
            return;
        }

        // Clear previous button
        buttonRef.current.innerHTML = '';

        // Create PayPal button
        window.paypal.Buttons({
            createOrder: (data, actions) => {
                return actions.order.create({
                    purchase_units: [{
                        amount: {
                            currency_code: 'USD',
                            value: '10.00' // This should come from cart total
                        }
                    }]
                });
            },
            onApprove: (data, actions) => {
                return actions.order.capture().then((details) => {
                    onApprove(details);
                });
            },
            onError: (err) => {
                onError(err);
            },
            style: {
                layout: 'vertical',
                color: 'blue',
                shape: 'rect',
                label: 'paypal'
            }
        }).render(buttonRef.current);

    }, [onApprove, onError]);

    return (
        <div className="wc-paypal-button-container">
            <div 
                ref={buttonRef}
                className="paypal-button-wrapper"
            />
            {isLoading && (
                <div className="wc-paypal-loading">
                    {__('Processing PayPal payment...', 'checkout-com-unified-payments-api')}
                </div>
            )}
        </div>
    );
};
