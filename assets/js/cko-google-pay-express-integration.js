/**
 * Google Pay Express Integration
 * Simple direct checkout flow - bypasses standard checkout form
 */

(function($) {
    'use strict';

    // Form selector for product pages
    const formSelector = '.single-product form.cart';

    /**
     * Get product attributes for variable products
     */
    const getAttributes = function() {
        const attributes = {};
        
        $('.variations_form .variations select').each(function() {
            const $select = $(this);
            const attributeName = $select.attr('name').replace('attribute_', '').replace(/\[(.*?)\]/, '');
            const attributeValue = $select.val();
            
            if (attributeValue) {
                attributes[attributeName] = attributeValue;
            }
        });
        
        return { data: attributes };
    };

    /**
     * Handle form changes
     */
    const onFormChange = function() {
        // Update Google Pay button state based on form changes
        if (typeof googlePayButton !== 'undefined') {
            googlePayButton.updateButtonVisibility();
        }
    };

    /**
     * Google Pay Express button controller
     */
    const googlePayButton = {
        init: function() {
            // Initialize Google Pay express button
            this.initializeGooglePayButton();
            this.updateButtonVisibility();
            
            $(document).on('change', formSelector, onFormChange);
        },

        /**
         * Initialize Google Pay button
         */
        initializeGooglePayButton: function() {
            if (typeof google === 'undefined' || !google.payments) {
                console.warn('Google Pay API not loaded');
                return;
            }

            const paymentsClient = new google.payments.api.PaymentsClient({
                environment: cko_google_pay_vars.environment || 'TEST'
            });

            const button = paymentsClient.createButton({
                onClick: this.onGooglePayButtonClick.bind(this),
                buttonColor: 'default',
                buttonType: 'pay'
            });

            const buttonContainer = document.getElementById('cko-google-pay-button-wrapper');
            if (buttonContainer) {
                buttonContainer.innerHTML = '';
                buttonContainer.appendChild(button);
                buttonContainer.style.display = 'block';
            }
        },

        /**
         * Handle Google Pay button click
         */
        onGooglePayButtonClick: async function() {
            try {
                // Show loading state
                this.setButtonLoading(true);

                // Process Google Pay payment directly
                await this.processGooglePayPayment();
            } catch (error) {
                console.error('Google Pay Express error:', error);
                this.showError('Payment failed. Please try again.');
            } finally {
                this.setButtonLoading(false);
            }
        },

        /**
         * Process Google Pay payment - Direct checkout flow
         */
        processGooglePayPayment: async function() {
            const paymentsClient = new google.payments.api.PaymentsClient({
                environment: cko_google_pay_vars.environment || 'TEST'
            });

            // Get product information
            const productInfo = this.getProductInfo();

            const paymentDataRequest = {
                apiVersion: 2,
                apiVersionMinor: 0,
                allowedPaymentMethods: [{
                    type: 'CARD',
                    parameters: {
                        allowedAuthMethods: ['PAN_ONLY', 'CRYPTOGRAM_3DS'],
                        allowedCardNetworks: ['AMEX', 'DISCOVER', 'JCB', 'MASTERCARD', 'VISA']
                    },
                    tokenizationSpecification: {
                        type: 'PAYMENT_GATEWAY',
                        parameters: {
                            gateway: 'checkoutltd',
                            gatewayMerchantId: cko_google_pay_vars.merchant_id
                        }
                    }
                }],
                transactionInfo: {
                    totalPriceStatus: 'FINAL',
                    totalPrice: productInfo.total_price,
                    currencyCode: cko_google_pay_vars.currency_code
                },
                merchantInfo: {
                    merchantId: cko_google_pay_vars.merchant_id,
                    merchantName: cko_google_pay_vars.merchant_name || 'Store'
                }
            };

            try {
                const paymentData = await paymentsClient.loadPaymentData(paymentDataRequest);
                
                // Process the payment directly - bypass checkout form
                await this.handleDirectPayment(paymentData, productInfo);
            } catch (error) {
                if (error.statusCode === 'CANCELED') {
                    console.log('Google Pay payment canceled');
                } else {
                    console.error('Google Pay payment error:', error);
                    this.showError('Payment failed. Please try again.');
                }
            }
        },

        /**
         * Get product information for payment
         */
        getProductInfo: function() {
            let product_id = $('input[name="add-to-cart"]').val();
            let quantity = parseInt($('.quantity .qty').val()) || 1;
            let attributes = {};

            // Check if product is a variable product
            if ($('.single_variation_wrap').length) {
                product_id = $('.single_variation_wrap').find('input[name="product_id"]').val();
                attributes = $('.variations_form').length ? getAttributes().data : {};
            }

            // Get product price
            const priceElement = $('.price .amount, .price .woocommerce-Price-amount');
            let total_price = '0.00';
            
            if (priceElement.length) {
                const priceText = priceElement.first().text().replace(/[^\d.,]/g, '');
                total_price = priceText.replace(',', '.');
            }

            return {
                product_id: product_id,
                quantity: quantity,
                attributes: attributes,
                total_price: total_price
            };
        },

        /**
         * Handle direct payment - bypass checkout form
         */
        handleDirectPayment: async function(paymentData, productInfo) {
            try {
                const response = await $.ajax({
                    url: cko_google_pay_vars.direct_payment_url,
                    type: 'POST',
                    data: {
                        payment_data: JSON.stringify(paymentData),
                        product_id: productInfo.product_id,
                        quantity: productInfo.quantity,
                        attributes: JSON.stringify(productInfo.attributes),
                        nonce: cko_google_pay_vars.woocommerce_process_checkout
                    },
                    dataType: 'json'
                });

                if (response.success) {
                    // Redirect directly to Thank You page
                    window.location.href = response.redirect;
                } else {
                    throw new Error(response.message || 'Payment processing failed');
                }
            } catch (error) {
                console.error('Direct payment processing error:', error);
                this.showError('Payment processing failed. Please try again.');
            }
        },

        /**
         * Set button loading state
         */
        setButtonLoading: function(loading) {
            const buttonContainer = document.getElementById('cko-google-pay-button-wrapper');
            if (buttonContainer) {
                if (loading) {
                    buttonContainer.classList.add('cko-disabled');
                } else {
                    buttonContainer.classList.remove('cko-disabled');
                }
            }
        },

        /**
         * Show error message
         */
        showError: function(message) {
            // You can implement a toast notification or alert here
            alert(message);
        },

        /**
         * Update button visibility based on form state
         */
        updateButtonVisibility: function() {
            const buttonContainer = document.getElementById('cko-google-pay-button-wrapper');
            if (!buttonContainer) return;

            // Check if product is available and form is valid
            const isProductAvailable = $('.single-product .stock').length === 0 || 
                                     !$('.single-product .stock').hasClass('out-of-stock');
            
            const isFormValid = this.isFormValid();

            if (isProductAvailable && isFormValid) {
                buttonContainer.style.display = 'block';
            } else {
                buttonContainer.style.display = 'none';
            }
        },

        /**
         * Check if form is valid
         */
        isFormValid: function() {
            // Check if required fields are filled for variable products
            if ($('.variations_form').length) {
                let isValid = true;
                $('.variations_form .variations select').each(function() {
                    if ($(this).val() === '') {
                        isValid = false;
                        return false;
                    }
                });
                return isValid;
            }
            return true;
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        // Only initialize on product pages
        if ($('body').hasClass('single-product')) {
            googlePayButton.init();
        }
    });

})(jQuery);
