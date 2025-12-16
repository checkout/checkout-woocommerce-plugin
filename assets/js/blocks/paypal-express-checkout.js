/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { usePaymentMethodDataContext } from '@woocommerce/base-context';

/**
 * Internal dependencies
 */
import { PayPalButton } from './paypal-button';

/**
 * PayPal Express Checkout Component
 */
export const PayPalExpressCheckout = () => {
    const { 
        paymentMethodData, 
        setPaymentMethodData 
    } = usePaymentMethodDataContext();

    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState(null);
    const [paypalLoaded, setPaypalLoaded] = useState(false);

    // Load PayPal SDK
    useEffect(() => {
        if (window.paypal) {
            setPaypalLoaded(true);
            return;
        }

        const script = document.createElement('script');
        script.src = 'https://www.paypal.com/sdk/js?client-id=YOUR_CLIENT_ID&currency=USD';
        script.async = true;
        script.onload = () => setPaypalLoaded(true);
        document.head.appendChild(script);

        return () => {
            if (script.parentNode) {
                script.parentNode.removeChild(script);
            }
        };
    }, []);

    const handlePayPalApprove = async (data) => {
        setIsLoading(true);
        setError(null);

        try {
            // Process PayPal payment
            const result = await processPayPalPayment(data);
            
            if (result.success) {
                setPaymentMethodData({
                    ...paymentMethodData,
                    paymentMethod: 'wc_checkout_com_paypal',
                    paymentData: result.paymentData
                });
            } else {
                setError(result.error || 'PayPal payment failed');
            }
        } catch (err) {
            setError('An unexpected error occurred');
        } finally {
            setIsLoading(false);
        }
    };

    const handlePayPalError = (err) => {
        setError('PayPal payment failed');
        setIsLoading(false);
    };

    if (!paypalLoaded) {
        return (
            <div className="wc-paypal-express-loading">
                {__('Loading PayPal...', 'checkout-com-unified-payments-api')}
            </div>
        );
    }

    return (
        <div className="wc-paypal-express-checkout">
            {error && (
                <div className="woocommerce-error" role="alert">
                    {error}
                </div>
            )}

            <PayPalButton 
                onApprove={handlePayPalApprove}
                onError={handlePayPalError}
                isLoading={isLoading}
            />
        </div>
    );
};

/**
 * Process PayPal payment
 */
const processPayPalPayment = async (paypalData) => {
    // This would integrate with your existing PayPal API
    // For now, return a placeholder
    return new Promise((resolve) => {
        setTimeout(() => {
            resolve({
                success: true,
                paymentData: {
                    paymentMethod: 'wc_checkout_com_paypal',
                    paypalOrderId: paypalData.orderID,
                    timestamp: Date.now()
                }
            });
        }, 1000);
    });
};
