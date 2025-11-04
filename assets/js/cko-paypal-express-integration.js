/* global cko_paypal_vars */

// Ensure cko_paypal_vars is available before proceeding
if ( typeof cko_paypal_vars === 'undefined' ) {
	console.error( '[PayPal Express] cko_paypal_vars is not defined. Make sure the script is properly enqueued and localized.' );
}

jQuery( function ( $ ) {
	
	// Double-check cko_paypal_vars is available
	if ( typeof cko_paypal_vars === 'undefined' ) {
		console.error( '[PayPal Express] cko_paypal_vars is still undefined after jQuery ready. Script may not be properly loaded.' );
		return;
	}
    
    const formSelector = 'form.cart';

    const onFormChange = function ( e ) {
        const form = document.querySelector( formSelector );

        const addToCartButton = form ? form.querySelector('.single_add_to_cart_button') : null;

        const isEnabled = ( null === addToCartButton ) || ! addToCartButton.classList.contains( 'disabled' );

        const element = jQuery( cko_paypal_vars.paypal_button_selector );

        if ( isEnabled ) {
            jQuery(element)
                .removeClass('cko-disabled')
                .off('mouseup')
                .find('> *')
                .css('pointer-events', '');
        } else {
            jQuery(element)
                .addClass('cko-disabled')
                .on('mouseup', function(event) {
                    event.stopImmediatePropagation();
                })
                .find('> *')
                .css('pointer-events', 'none');
        }

    };

    const getAttributes = function() {
        var select = $( '.variations_form' ).find( '.variations select' ),
            data   = {},
            count  = 0,
            chosen = 0;

        select.each( function() {
            var attribute_name = $( this ).data( 'attribute_name' ) || $( this ).attr( 'name' );
            var value          = $( this ).val() || '';

            if ( value.length > 0 ) {
                chosen ++;
            }

            count ++;
            data[ attribute_name ] = value;
        });

        return {
            'count'      : count,
            'chosenCount': chosen,
            'data'       : data
        };
    };

    let showError = function ( error_message ) {

        if ( 'string' === typeof error_message ) {
            error_message = [ error_message ];
        }

        let ulWrapper = jQuery( '<ul/>' )
            .prop( 'role', 'alert' ).addClass( 'woocommerce-error' );

        if ( Array.isArray( error_message ) ) {
            jQuery.each( error_message, function( index, value ) {
                jQuery( ulWrapper ).append( jQuery( '<li>' ).html( value ) );
            });
        }

        let wcNoticeDiv = jQuery( '<div>' )
            .addClass( 'woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout' )
            .append( ulWrapper );

        let scrollTarget;

        if ( jQuery('form.checkout').length ) {
            jQuery('form.checkout .woocommerce-NoticeGroup').remove();
            jQuery('form.checkout').prepend(wcNoticeDiv);
            jQuery('.woocommerce, .form.checkout').removeClass('processing').unblock();
            scrollTarget = jQuery('form.checkout');
        } else if ( jQuery('.woocommerce-order-pay').length ) {
            jQuery('.woocommerce-order-pay .woocommerce-NoticeGroup').remove();
            jQuery('.woocommerce-order-pay').prepend(wcNoticeDiv);
            jQuery('.woocommerce, .woocommerce-order-pay').removeClass('processing').unblock();
            scrollTarget = jQuery('.woocommerce-order-pay');
        }
    
        if ( scrollTarget ) {
            jQuery('html, body').animate({
                scrollTop: (scrollTarget.offset().top - 100)
            }, 1000);
        }
    };

    const cko_express_add_to_cart = async function () {
        var product_id = $( '.single_add_to_cart_button' ).val();

        // Check if product is a variable product.
        if ( $( '.single_variation_wrap' ).length ) {
            product_id = $( '.single_variation_wrap' ).find( 'input[name="product_id"]' ).val();
        }

        var data = {
            product_id: product_id,
            qty: $( '.quantity .qty' ).val(),
            attributes: $( '.variations_form' ).length ? getAttributes().data : [],
            nonce: cko_paypal_vars.paypal_express_add_to_cart_nonce
        };

        return await $.ajax( {
            url: cko_paypal_vars.add_to_cart_url,
            type: 'POST',
            async: false,
            data: data
        } ).done( function ( response ) {
            cko_paypal_vars.debug && console.log( response );
        } ).fail( function ( xhr, status, error ) {
            console.error('[PayPal Express] Add to cart failed:', error);
            cko_paypal_vars.debug && console.error('[PayPal Express] Response text:', xhr.responseText);
            // Return a default response to prevent the chain from breaking
            return { result: 'error', message: 'Failed to add product to cart' };
        } )
    }

    const cko_express_create_order_id_for_product = async function (productId) {
    // Add product to cart first
    let addToCartSuccess = await cko_express_add_to_cart_for_product(productId);

    if (!addToCartSuccess || addToCartSuccess.result === 'error') {
        console.error('[PayPal Express] Add to cart failed for product:', productId);
        showError('Failed to add product to cart. Please try again.');
        throw new Error('Failed to add product to cart');
    }

    let data = {
        express_checkout: true,
        add_to_cart: addToCartSuccess.result
    }
    
    return fetch( cko_paypal_vars.create_order_url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: jQuery.param( data )
    }).then(function (res) {
        if (!res.ok) {
            throw new Error('Network response was not ok: ' + res.status);
        }
        return res.text().then(function(text) {
            try {
                return JSON.parse(text);
            } catch (e) {
            console.error('[PayPal Express] Invalid JSON response:', e.message);
                throw new Error('Invalid JSON response from server: ' + text.substring(0, 200));
            }
        });
    }).then(function (data) {
        if (typeof data.success !== 'undefined' && data.success === false) {
            let messages = data.data && data.data.messages ? data.data.messages : data.data;
            if ( 'string' === typeof messages || Array.isArray( messages ) ) {
                showError( messages );
            }
            throw new Error(typeof messages === 'string' ? messages : 'Failed to create PayPal order');
        } else if (data.order_id) {
            return data.order_id;
        } else {
            cko_paypal_vars.debug && console.error('[PayPal Express] Unexpected response format:', data);
            showError('Unexpected response format from server');
            throw new Error('Unexpected response format from server. Missing order_id.');
        }
    }).catch(function(error) {
        console.error('[PayPal Express] Error in create_order_id_for_product:', error.message);
        showError('Failed to create order: ' + error.message);
        throw error;
    });
};

const cko_express_create_order_id_for_cart = async function () {
    cko_paypal_vars.debug && console.log('[PayPal Express] Creating order for cart page');
    
    // Trigger cart fragments refresh to ensure cart is synced with server
    // This works for both classic and Blocks cart pages
    try {
        await new Promise(function(resolve) {
            jQuery(document.body).trigger('wc_fragment_refresh');
            // Wait a bit for the cart to sync
            setTimeout(resolve, 300);
        });
    } catch (e) {
        // Continue even if refresh fails
        cko_paypal_vars.debug && console.log('[PayPal Express] Cart refresh error (continuing):', e);
    }
    
    let data = {
        express_checkout: true,
        use_existing_cart: true
    }
    
    return fetch( cko_paypal_vars.create_order_url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: jQuery.param( data )
    }).then(function (res) {
        if (!res.ok) {
            throw new Error('Network response was not ok: ' + res.status);
        }
        return res.text().then(function(text) {
            try {
                const parsed = JSON.parse(text);
                cko_paypal_vars.debug && console.log('[PayPal Express] Response from server:', parsed);
                return parsed;
            } catch (e) {
            console.error('[PayPal Express] Invalid JSON response:', e.message);
                throw new Error('Invalid JSON response from server: ' + text.substring(0, 200));
            }
        });
    }).then(function (data) {
        cko_paypal_vars.debug && console.log('[PayPal Express] Order creation response:', data);
        
        if (typeof data.success !== 'undefined' && data.success === false) {
            let messages = data.data && data.data.messages ? data.data.messages : data.data;
            console.error('[PayPal Express] Server returned error:', messages);
            
            if ( 'string' === typeof messages || Array.isArray( messages ) ) {
                const errorMsg = typeof messages === 'string' ? messages : messages.join('; ');
                showError( messages );
                throw new Error(errorMsg);
            } else {
                const errorMsg = 'Failed to create PayPal order';
                throw new Error(errorMsg);
            }
        } else if (data.order_id) {
            cko_paypal_vars.debug && console.log('[PayPal Express] Order ID received:', data.order_id);
            return data.order_id;
        } else {
            const errorMsg = 'Unexpected response format from server. Missing order_id';
            console.error('[PayPal Express]', errorMsg);
            showError(errorMsg);
            throw new Error(errorMsg);
        }
    }).catch(function(error) {
        console.error('[PayPal Express] Error in create_order_id_for_cart:', error.message);
        showError('Failed to create order: ' + error.message);
        // Re-throw error - PayPal SDK will handle the rejection
        throw error;
    });
};

const cko_express_add_to_cart_for_product = async function (productId) {
    let data = {
        product_id: productId,
        quantity: 1,
        nonce: cko_paypal_vars.paypal_express_add_to_cart_nonce
    };

    return await $.ajax( {
        url: cko_paypal_vars.add_to_cart_url,
        type: 'POST',
        async: false,
        data: data
    } ).done( function ( response ) {
        cko_paypal_vars.debug && console.log( 'Add to cart response for product ' + productId + ':', response );
    } ).fail( function ( xhr, status, error ) {
        console.error('[PayPal Express] Add to cart failed for product ' + productId + ':', error);
        cko_paypal_vars.debug && console.error('[PayPal Express] Response text:', xhr.responseText);
        return { result: 'error', message: 'Failed to add product to cart' };
    } )
};

const cko_express_create_order_id = async function () {
        let addToCartSuccess = await cko_express_add_to_cart()

        // Check if add to cart was successful
        if (!addToCartSuccess || addToCartSuccess.result === 'error') {
            console.error('[PayPal Express] Add to cart failed');
            showError('Failed to add product to cart. Please try again.');
            throw new Error('Failed to add product to cart');
        }

        // Prepare add-to-cart for express checkout.
        let data = {
            express_checkout: true,
            add_to_cart: addToCartSuccess.result
        }

        cko_paypal_vars.debug && console.log( data );

        // Get Order ID from below endpoint.
        return fetch( cko_paypal_vars.create_order_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: jQuery.param( data )
        }).then(function (res) {
            // Check if response is ok
            if (!res.ok) {
                throw new Error('Network response was not ok: ' + res.status);
            }
            
            // Get response text first to handle malformed JSON
            return res.text().then(function(text) {
                try {
                    // Try to parse as JSON
                    return JSON.parse(text);
                } catch (e) {
                    // If JSON parsing fails, log the raw response and throw error
            console.error('[PayPal Express] Invalid JSON response:', e.message);
                    throw new Error('Invalid JSON response from server: ' + text.substring(0, 200));
                }
            });
        }).then(function (data) {
            if (typeof data.success !== 'undefined' && data.success === false) {
                let messages = data.data && data.data.messages ? data.data.messages : data.data;

                if ( 'string' === typeof messages || Array.isArray( messages ) ) {
                    showError( messages );
                }
                throw new Error(typeof messages === 'string' ? messages : 'Failed to create PayPal order');
            } else if (data.order_id) {
                return data.order_id;
            } else {
                cko_paypal_vars.debug && console.error('[PayPal Express] Unexpected response format:', data);
                showError('Unexpected response format from server');
                throw new Error('Unexpected response format from server. Missing order_id.');
            }
        }).catch(function(error) {
            console.error('[PayPal Express] Error in create_order_id:', error.message);
            showError('Failed to create order: ' + error.message);
            throw error;
        });
    };

    const paypalButton = {
        init: function () {
            // Initialize PayPal express buttons for different contexts
            this.initProductPageButton();
            this.initShopPageButtons();
            this.initCartPageButton();

            this.updateButtonVisibility();

            jQuery(document).on('change', formSelector, onFormChange );
        },

        initProductPageButton: function() {
            // Initialize button for single product page
            if (jQuery('#cko-paypal-button-wrapper').length && typeof paypal !== 'undefined') {
                try {
                    paypal.Buttons({ ...this.paypalButtonProps('product') }).render('#cko-paypal-button-wrapper');
                } catch (error) {
                    // Silently fail - error already handled by PayPal SDK
                }
            }
        },

        initShopPageButtons: function() {
            // Initialize buttons for shop/listing pages
            const wrappers = jQuery('[id^="cko-paypal-button-wrapper-"]');
            
            // Wait for PayPal SDK to be available
            const checkPayPal = setInterval(function() {
                if (typeof paypal !== 'undefined') {
                    clearInterval(checkPayPal);
                    jQuery('[id^="cko-paypal-button-wrapper-"]').each(function() {
                        const $wrapper = jQuery(this);
                        const productId = $wrapper.data('product-id');
                        
                        if (productId && $wrapper.attr('id') !== 'cko-paypal-button-wrapper') {
                            try {
                                paypal.Buttons({ ...paypalButton.paypalButtonProps('shop', productId) }).render('#' + $wrapper.attr('id'));
                            } catch (error) {
                                // Silently fail - error already handled by PayPal SDK
                            }
                        }
                    });
                }
            }, 100);
            
            // Timeout after 10 seconds
            setTimeout(function() {
                clearInterval(checkPayPal);
            }, 10000);
        },

        initCartPageButton: function() {
            // Initialize button for cart page
            // Check if this is a Blocks cart page or classic cart
            const isBlocksCart = jQuery('.wc-block-cart').length > 0;
            const isClassicCart = jQuery('.woocommerce-cart').length > 0 && !isBlocksCart;
            const wrapperExists = jQuery('#cko-paypal-button-wrapper-cart').length > 0;
            
            // For Blocks cart, inject the button wrapper if it doesn't exist
            if (isBlocksCart && !wrapperExists) {
                const paymentOptions = jQuery('.wc-block-cart__payment-options');
                if (paymentOptions.length) {
                    // Check if button wrapper already exists in payment options
                    if (!paymentOptions.find('#cko-paypal-button-wrapper-cart').length) {
                        // Append instead of replacing to preserve Google Pay button
                        paymentOptions.append('<div class="cko-paypal-cart-button"><h3>Express Checkout</h3><div id="cko-paypal-button-wrapper-cart"></div></div>');
                    }
                } else {
                    // Fallback: inject after proceed to checkout button
                    const proceedButton = jQuery('.wc-block-cart__submit, .wc-block-components-button--contained');
                    if (proceedButton.length) {
                        proceedButton.after('<div class="cko-paypal-cart-button"><h3>Express Checkout</h3><div id="cko-paypal-button-wrapper-cart"></div></div>');
                    } else {
                        // Another fallback: inject before cart totals
                        const cartTotals = jQuery('.wc-block-cart__totals');
                        if (cartTotals.length) {
                            cartTotals.before('<div class="cko-paypal-cart-button"><h3>Express Checkout</h3><div id="cko-paypal-button-wrapper-cart"></div></div>');
                        } else {
                            // Last fallback: inject at the end of cart block
                            const cartBlock = jQuery('.wc-block-cart');
                            if (cartBlock.length) {
                                cartBlock.append('<div class="cko-paypal-cart-button"><h3>Express Checkout</h3><div id="cko-paypal-button-wrapper-cart"></div></div>');
                            }
                        }
                    }
                }
            } else if (isClassicCart && !wrapperExists) {
                // For classic cart, try to inject if wrapper doesn't exist
                const proceedToCheckout = jQuery('.wc-proceed-to-checkout');
                if (proceedToCheckout.length) {
                    proceedToCheckout.before('<div class="cko-paypal-cart-button"><h3>Express Checkout</h3><div id="cko-paypal-button-wrapper-cart"></div></div>');
                } else {
                    // Fallback: inject before cart totals
                    const cartTotals = jQuery('.cart_totals');
                    if (cartTotals.length) {
                        cartTotals.before('<div class="cko-paypal-cart-button"><h3>Express Checkout</h3><div id="cko-paypal-button-wrapper-cart"></div></div>');
                    }
                }
            }
            
            // Wait for PayPal SDK to be available
            const checkPayPal = setInterval(function() {
                if (jQuery('#cko-paypal-button-wrapper-cart').length && typeof paypal !== 'undefined') {
                    clearInterval(checkPayPal);
                    try {
                        paypal.Buttons({ ...paypalButton.paypalButtonProps('cart') }).render('#cko-paypal-button-wrapper-cart');
                    } catch (error) {
                        // Silently fail - error already handled by PayPal SDK
                    }
                }
            }, 100);
            
            // Timeout after 10 seconds
            setTimeout(function() {
                clearInterval(checkPayPal);
            }, 10000);
        },

        paypalButtonProps: function (context = 'product', productId = null) {
            let paypalButtonProps = {
        onApprove: async function (data) {
            cko_paypal_vars.debug && console.log('PayPal onApprove data:', data);

            // Show loading state
            jQuery('body').addClass('paypal-processing');
            
            // Show loading state in the appropriate container
            let loadingContainer = '#cko-paypal-button-wrapper';
            if (context === 'shop' && productId) {
                loadingContainer = '#cko-paypal-button-wrapper-' + productId;
            } else if (context === 'cart') {
                loadingContainer = '#cko-paypal-button-wrapper-cart';
            }
            
            jQuery(loadingContainer).html('<div style="text-align: center; padding: 20px;"><div style="display: inline-block; width: 20px; height: 20px; border: 2px solid #f3f3f3; border-top: 2px solid #0070ba; border-radius: 50%; animation: spin 1s linear infinite;"></div><br><span style="color: #0070ba; font-size: 14px; margin-top: 10px; display: inline-block;">Processing your payment...</span></div>');
            
            // Add CSS for spinner animation
            if (!jQuery('#paypal-spinner-css').length) {
                jQuery('head').append('<style id="paypal-spinner-css">@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }</style>');
            }

            // Set a timeout fallback in case the API call takes too long (30 seconds)
            var timeoutFallback = setTimeout(function() {
                jQuery('body').removeClass('paypal-processing');
                jQuery(loadingContainer).html('<div id="cko-paypal-button"></div>');
                showError('Payment processing is taking longer than expected. Please try again.');
            }, 30000);

                            // Validate URL before making request
                            if (!cko_paypal_vars.paypal_order_session_url || 
                                typeof cko_paypal_vars.paypal_order_session_url !== 'string' ||
                                cko_paypal_vars.paypal_order_session_url === 'undefined' ||
                                cko_paypal_vars.paypal_order_session_url.trim() === '') {
                                clearTimeout(timeoutFallback);
                                jQuery('body').removeClass('paypal-processing');
                                jQuery(loadingContainer).html('<div id="cko-paypal-button"></div>');
                                console.error('[PayPal Express] paypal_order_session_url is invalid:', cko_paypal_vars.paypal_order_session_url);
                                showError('Payment processing failed: Invalid configuration. Please contact support.');
                                return;
                            }
                            
                            // Construct URL with proper query parameters
                            var requestUrl = cko_paypal_vars.paypal_order_session_url.trim();
                            
                            // Ensure requestUrl is a valid URL
                            try {
                                new URL(requestUrl);
                            } catch (e) {
                                clearTimeout(timeoutFallback);
                                jQuery('body').removeClass('paypal-processing');
                                jQuery(loadingContainer).html('<div id="cko-paypal-button"></div>');
                                console.error('[PayPal Express] paypal_order_session_url is not a valid URL:', requestUrl);
                                showError('Payment processing failed: Invalid URL configuration. Please contact support.');
                                return;
                            }
                            
                            var separator = requestUrl.indexOf('?') !== -1 ? '&' : '?';
                            requestUrl += separator + 'paypal_order_id=' + encodeURIComponent(data.orderID) + '&woocommerce-process-checkout-nonce=' + encodeURIComponent(cko_paypal_vars.woocommerce_process_checkout);
                            
                            jQuery.post(requestUrl, function (response) {
                
                // Clear the timeout since we got a response
                clearTimeout(timeoutFallback);
                
                cko_paypal_vars.debug && console.log('PayPal API response:', response);
                cko_paypal_vars.debug && console.log('Response type:', typeof response);
                cko_paypal_vars.debug && console.log('Response success:', response.success);
                cko_paypal_vars.debug && console.log('Response data:', response.data);
                        
                        if (typeof response.success !== 'undefined' && response.success !== true ) {
                            // Hide loading state on error
                            jQuery('body').removeClass('paypal-processing');
                            jQuery('#cko-paypal-button-wrapper').html('<div id="cko-paypal-button"></div>');
                            
                            var messages = response.data.messages ? response.data.messages : response.data;

                            if ( 'string' === typeof messages || Array.isArray( messages ) ) {
                                showError( messages );
                            }
                        } else {
                            // Check if we have a redirect URL for express checkout
                            if (response.data && response.data.redirect_url) {
                                cko_paypal_vars.debug && console.log('Redirecting to success page:', response.data.redirect_url);
                                // Express checkout - redirect directly to success page
                                window.location.href = response.data.redirect_url;
                            } else {
                                cko_paypal_vars.debug && console.log('No redirect URL found, using fallback to checkout page');
                                cko_paypal_vars.debug && console.log('Available redirect URL:', cko_paypal_vars.redirect);
                                // Fallback to checkout page (old behavior)
                                window.location.href = cko_paypal_vars.redirect;
                            }
                        }
                            }).fail(function(xhr, status, error) {
                        // Clear the timeout since we got a response (even if failed)
                        clearTimeout(timeoutFallback);
                        
                        // Hide loading state on error
                        jQuery('body').removeClass('paypal-processing');
                        jQuery(loadingContainer).html('<div id="cko-paypal-button"></div>');
                        
                        cko_paypal_vars.debug && console.log('PayPal API request failed:', xhr, status, error);
                        cko_paypal_vars.debug && console.log('Response text:', xhr.responseText);
                        showError('Payment processing failed. Please try again.');
                    });
                },
                onCancel: function (data, actions) {
                    // Hide loading state if user cancels
                    jQuery('body').removeClass('paypal-processing');
                    jQuery(loadingContainer).html('<div id="cko-paypal-button"></div>');
                    
                    cko_paypal_vars.debug && console.log(data);
                    jQuery('.woocommerce').unblock();
                },
                onError: function (err) {
                    // Hide loading state on PayPal error
                    jQuery('body').removeClass('paypal-processing');
                    jQuery(loadingContainer).html('<div id="cko-paypal-button"></div>');
                    
                    cko_paypal_vars.debug && console.log(err);
                    jQuery('.woocommerce').unblock();
                },
            };

            // Always use createOrder for express checkout (no billing agreements)
            paypalButtonProps.createOrder = function( data, actions ) {
                if (context === 'shop' && productId) {
                    return cko_express_create_order_id_for_product(productId);
                } else if (context === 'cart') {
                    return cko_express_create_order_id_for_cart();
                } else {
                    return cko_express_create_order_id();
                }
            };

            return paypalButtonProps;
        },

        updateButtonVisibility: function () {
            // Show product page button
            if ( jQuery( cko_paypal_vars.paypal_button_selector ) ) {
                jQuery( cko_paypal_vars.paypal_button_selector ).show();
            }
            
            // Show shop page buttons
            jQuery('[id^="cko-paypal-button-wrapper-"]').each(function() {
                if (jQuery(this).attr('id') !== 'cko-paypal-button-wrapper') {
                    jQuery(this).show();
                }
            });
            
            // Show cart page button
            if ( jQuery('#cko-paypal-button-wrapper-cart') ) {
                jQuery('#cko-paypal-button-wrapper-cart').show();
            }
        }
    }

    paypalButton.init();
});
