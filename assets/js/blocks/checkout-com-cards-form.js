/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';

/**
 * Checkout.com Cards Form Component
 */
export const CheckoutComCardsForm = ({ 
    onSubmit, 
    isLoading, 
    error, 
    shouldSavePayment, 
    setShouldSavePayment 
}) => {
    const [cardData, setCardData] = useState({
        cardNumber: '',
        expiryDate: '',
        cvv: '',
        cardholderName: ''
    });

    const handleInputChange = (field, value) => {
        setCardData(prev => ({
            ...prev,
            [field]: value
        }));
    };

    const handleSavePaymentChange = (event) => {
        setShouldSavePayment(event.target.checked);
    };

    return (
        <form onSubmit={onSubmit} className="wc-checkout-com-cards-form">
            {error && (
                <div className="woocommerce-error" role="alert">
                    {error}
                </div>
            )}

            <div className="form-row form-row-wide">
                <label htmlFor="checkout-com-cardholder-name">
                    {__('Cardholder Name', 'checkout-com-unified-payments-api')}
                </label>
                <input
                    type="text"
                    id="checkout-com-cardholder-name"
                    name="checkout-com-cardholder-name"
                    value={cardData.cardholderName}
                    onChange={(e) => handleInputChange('cardholderName', e.target.value)}
                    required
                />
            </div>

            <div className="form-row form-row-wide">
                <label htmlFor="checkout-com-card-number">
                    {__('Card Number', 'checkout-com-unified-payments-api')}
                </label>
                <input
                    type="text"
                    id="checkout-com-card-number"
                    name="checkout-com-card-number"
                    value={cardData.cardNumber}
                    onChange={(e) => handleInputChange('cardNumber', e.target.value)}
                    placeholder="1234 5678 9012 3456"
                    required
                />
            </div>

            <div className="form-row form-row-first">
                <label htmlFor="checkout-com-expiry-date">
                    {__('Expiry Date', 'checkout-com-unified-payments-api')}
                </label>
                <input
                    type="text"
                    id="checkout-com-expiry-date"
                    name="checkout-com-expiry-date"
                    value={cardData.expiryDate}
                    onChange={(e) => handleInputChange('expiryDate', e.target.value)}
                    placeholder="MM/YY"
                    required
                />
            </div>

            <div className="form-row form-row-last">
                <label htmlFor="checkout-com-cvv">
                    {__('CVV', 'checkout-com-unified-payments-api')}
                </label>
                <input
                    type="text"
                    id="checkout-com-cvv"
                    name="checkout-com-cvv"
                    value={cardData.cvv}
                    onChange={(e) => handleInputChange('cvv', e.target.value)}
                    placeholder="123"
                    required
                />
            </div>

            <div className="form-row form-row-wide">
                <label className="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
                    <input
                        type="checkbox"
                        className="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox"
                        name="checkout-com-save-payment-method"
                        checked={shouldSavePayment}
                        onChange={handleSavePaymentChange}
                    />
                    <span>{__('Save payment method for future purchases', 'checkout-com-unified-payments-api')}</span>
                </label>
            </div>

            <div className="form-row form-row-wide">
                <button
                    type="submit"
                    className="button alt wc-forward"
                    disabled={isLoading}
                >
                    {isLoading ? __('Processing...', 'checkout-com-unified-payments-api') : __('Place Order', 'checkout-com-unified-payments-api')}
                </button>
            </div>
        </form>
    );
};
