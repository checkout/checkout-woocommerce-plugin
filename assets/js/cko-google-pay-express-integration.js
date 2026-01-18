/* global cko_google_pay_vars */

jQuery( function ( $ ) {
    
    const formSelector = 'form.cart';

    const onFormChange = function ( e ) {
        const form = document.querySelector( formSelector );

        const addToCartButton = form ? form.querySelector('.single_add_to_cart_button') : null;

        const isEnabled = ( null === addToCartButton ) || ! addToCartButton.classList.contains( 'disabled' );

        const element = jQuery( cko_google_pay_vars.google_pay_button_selector );

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
            nonce: cko_google_pay_vars.google_pay_express_add_to_cart_nonce
        };

        return await $.ajax( {
            url: cko_google_pay_vars.add_to_cart_url,
            type: 'POST',
            async: false,
            data: data
        } ).done( function ( response ) {
            cko_google_pay_vars.debug && console.log( response );
        } ).fail( function ( xhr, status, error ) {
            console.error('[Google Pay Express] Add to cart failed:', error);
            cko_google_pay_vars.debug && console.error('[Google Pay Express] Response text:', xhr.responseText);
            return { result: 'error', message: 'Failed to add product to cart' };
        } )
    }

    const cko_express_add_to_cart_for_product = async function (productId) {
        let data = {
            product_id: productId,
            quantity: 1,
            nonce: cko_google_pay_vars.google_pay_express_add_to_cart_nonce
        };

        return await $.ajax( {
            url: cko_google_pay_vars.add_to_cart_url,
            type: 'POST',
            async: false,
            data: data
        } ).done( function ( response ) {
            cko_google_pay_vars.debug && console.log( 'Add to cart response for product ' + productId + ':', response );
        } ).fail( function ( xhr, status, error ) {
            console.error('[Google Pay Express] Add to cart failed for product ' + productId + ':', error);
            cko_google_pay_vars.debug && console.error('[Google Pay Express] Response text:', xhr.responseText);
            return { result: 'error', message: 'Failed to add product to cart' };
        } )
    };

    const cko_express_create_payment_context_for_product = async function (productId) {
        // Add product to cart first
        let addToCartSuccess = await cko_express_add_to_cart_for_product(productId);

        if (!addToCartSuccess || addToCartSuccess.result === 'error') {
            console.error('[Google Pay Express] Add to cart failed for product:', productId);
            showError('Failed to add product to cart. Please try again.');
            return null;
        }

        let data = {
            express_checkout: true,
            add_to_cart: addToCartSuccess.result
        }
        
        return fetch( cko_google_pay_vars.create_payment_context_url, {
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
            console.error('[Google Pay Express] Invalid JSON response:', e.message);
                    throw new Error('Invalid JSON response from server: ' + text.substring(0, 200));
                }
            });
        }).then(function (data) {
            if (typeof data.success !== 'undefined' && data.success === false) {
                let messages = data.data && data.data.messages ? data.data.messages : data.data;
                if ( 'string' === typeof messages || Array.isArray( messages ) ) {
                    showError( messages );
                }
                return null;
            } else if (data.payment_context_id) {
                return data.payment_context_id;
            } else {
                cko_google_pay_vars.debug && console.error('[Google Pay Express] Unexpected response format:', data);
                showError('Unexpected response format from server');
                return null;
            }
        }).catch(function(error) {
            console.error('[Google Pay Express] Error in create_payment_context_for_product:', error.message);
            showError('Failed to create payment context: ' + error.message);
            return null;
        });
    };

    const cko_express_create_payment_context_for_cart = async function () {
        let data = {
            express_checkout: true,
            use_existing_cart: true
        }
        
        return fetch( cko_google_pay_vars.create_payment_context_url, {
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
            console.error('[Google Pay Express] Invalid JSON response:', e.message);
                    throw new Error('Invalid JSON response from server: ' + text.substring(0, 200));
                }
            });
        }).then(function (data) {
            if (typeof data.success !== 'undefined' && data.success === false) {
                let messages = data.data && data.data.messages ? data.data.messages : data.data;
                if ( 'string' === typeof messages || Array.isArray( messages ) ) {
                    showError( messages );
                }
                return null;
            } else if (data.payment_context_id) {
                return data.payment_context_id;
            } else {
                cko_google_pay_vars.debug && console.error('[Google Pay Express] Unexpected response format:', data);
                showError('Unexpected response format from server');
                return null;
            }
        }).catch(function(error) {
            console.error('[Google Pay Express] Error in create_payment_context_for_cart:', error.message);
            showError('Failed to create payment context: ' + error.message);
            return null;
        });
    };

    const cko_express_create_payment_context = async function () {
        let addToCartSuccess = await cko_express_add_to_cart()

        // Check if add to cart was successful
        if (!addToCartSuccess || addToCartSuccess.result === 'error') {
            console.error('[Google Pay Express] Add to cart failed');
            showError('Failed to add product to cart. Please try again.');
            return null;
        }

        // Prepare add-to-cart for express checkout.
        let data = {
            express_checkout: true,
            add_to_cart: addToCartSuccess.result
        }

        cko_google_pay_vars.debug && console.log( data );

        // Get Payment Context ID from below endpoint.
        return fetch( cko_google_pay_vars.create_payment_context_url, {
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
            console.error('[Google Pay Express] Invalid JSON response:', e.message);
                    throw new Error('Invalid JSON response from server: ' + text.substring(0, 200));
                }
            });
        }).then(function (data) {
            if (typeof data.success !== 'undefined' && data.success === false) {
                let messages = data.data && data.data.messages ? data.data.messages : data.data;

                if ( 'string' === typeof messages || Array.isArray( messages ) ) {
                    showError( messages );
                }
                return null;
            } else if (data.payment_context_id) {
                return data.payment_context_id;
            } else {
                cko_google_pay_vars.debug && console.error('[Google Pay Express] Unexpected response format:', data);
                showError('Unexpected response format from server');
                return null;
            }
        }).catch(function(error) {
            console.error('[Google Pay Express] Error in create_payment_context:', error.message);
            showError('Failed to create payment context: ' + error.message);
            return null;
        });
    };

    const googlePayButton = {
        init: function () {
            // Wait for Google Pay API to load
            this.waitForGooglePayAPI();
        },

        waitForGooglePayAPI: function() {
            const self = this;
            const checkInterval = setInterval(function() {
                if (typeof google !== 'undefined' && google.payments && google.payments.api) {
                    clearInterval(checkInterval);
                    // Wait a bit for DOM to be ready
                    setTimeout(function() {
                        self.initializeButtons();
                    }, 100);
                }
            }, 100);

            // Timeout after 10 seconds
            setTimeout(function() {
                clearInterval(checkInterval);
                if (typeof google === 'undefined' || !google.payments || !google.payments.api) {
                    cko_google_pay_vars.debug && console.warn('[Google Pay Express] Google Pay API not loaded after 10 seconds');
                } else {
                    // If API is loaded but buttons weren't initialized, try now
                    setTimeout(function() {
                        self.initializeButtons();
                    }, 100);
                }
            }, 10000);
        },

        initializeButtons: function() {
            // Initialize Google Pay express buttons for different contexts
            // Only initialize buttons relevant to the current page
            const isProductPage = jQuery('#cko-google-pay-button-wrapper').length > 0;
            const isCartPageByWrapper = jQuery('#cko-google-pay-button-wrapper-cart').length > 0;
            const isCartPageByClass = jQuery('.wc-block-cart, .woocommerce-cart').length > 0;
            const isCartPage = isCartPageByWrapper || isCartPageByClass;
            const isShopPage = jQuery('[id^="cko-google-pay-button-wrapper-"]').filter(function() {
                return !jQuery(this).is('#cko-google-pay-button-wrapper-cart') && !jQuery(this).is('#cko-google-pay-button-wrapper');
            }).length > 0;
            
            if (cko_google_pay_vars.debug) {
                console.log('[Google Pay Express] Button initialization check:', {
                    isProductPage,
                    isCartPageByWrapper,
                    isCartPageByClass,
                    isCartPage,
                    isShopPage,
                    productWrapper: jQuery('#cko-google-pay-button-wrapper').length,
                    cartWrapper: jQuery('#cko-google-pay-button-wrapper-cart').length,
                    shopWrappers: jQuery('[id^="cko-google-pay-button-wrapper-"]').length,
                    cartPageClass: jQuery('.wc-block-cart, .woocommerce-cart').length
                });
            }
            
            if (isProductPage) {
                this.initProductPageButton();
            }
            
            if (isShopPage) {
                this.initShopPageButtons();
            }
            
            if (isCartPage) {
                cko_google_pay_vars.debug && console.log('[Google Pay Express] Initializing cart page button');
                this.initCartPageButton();
            }

            this.updateButtonVisibility();

            jQuery(document).on('change', formSelector, onFormChange );
        },

        initProductPageButton: function(retryCount) {
            retryCount = retryCount || 0;
            const maxRetries = 5;
            
            // Initialize button for single product page
            const wrapper = jQuery('#cko-google-pay-button-wrapper');
            if (wrapper.length && typeof google !== 'undefined' && google.payments && google.payments.api) {
                try {
                    const paymentsClient = new google.payments.api.PaymentsClient({
                        environment: cko_google_pay_vars.environment || 'TEST'
                    });

                    const button = paymentsClient.createButton({
                        onClick: this.onGooglePayButtonClick.bind(this, 'product'),
                        buttonColor: cko_google_pay_vars.button_style === 'google-pay-white' ? 'white' : 'black',
                        buttonType: 'plain'
                    });

                    wrapper.html('');
                    wrapper.append(button);
                    wrapper.show();
                } catch (error) {
                    console.error('[Google Pay Express] Error initializing product page button:', error.message);
                }
            } else {
                // If wrapper doesn't exist or API not loaded, try again after a delay (with limit)
                if (!wrapper.length && retryCount < maxRetries) {
                    setTimeout(() => this.initProductPageButton(retryCount + 1), 500);
                } else if (!wrapper.length && retryCount >= maxRetries) {
                    console.warn('[Google Pay Express] Product page button wrapper not found after ' + maxRetries + ' retries. Make sure the product page setting is enabled.');
                }
            }
        },

        initShopPageButtons: function() {
            // Initialize buttons for shop/listing pages
            const self = this;
            
            jQuery('[id^="cko-google-pay-button-wrapper-"]').each(function() {
                const $wrapper = jQuery(this);
                const productId = $wrapper.data('product-id');
                
                if (productId && $wrapper.attr('id') !== 'cko-google-pay-button-wrapper' && typeof google !== 'undefined' && google.payments && google.payments.api) {
                    try {
                        const paymentsClient = new google.payments.api.PaymentsClient({
                            environment: cko_google_pay_vars.environment || 'TEST'
                        });

                        const button = paymentsClient.createButton({
                            onClick: self.onGooglePayButtonClick.bind(self, 'shop', productId),
                            buttonColor: cko_google_pay_vars.button_style === 'google-pay-white' ? 'white' : 'black',
                            buttonType: 'plain'
                        });

                        $wrapper.html('');
                        $wrapper.append(button);
                        $wrapper.show();
                    } catch (error) {
                        console.error('[Google Pay Express] Error initializing shop page button for product ' + productId + ':', error.message);
                    }
                }
            });
        },

        initCartPageButton: function(retryCount) {
            retryCount = retryCount || 0;
            const maxRetries = 10;
            
            // Check if this is a Blocks cart page or classic cart
            const isBlocksCart = jQuery('.wc-block-cart').length > 0;
            const isClassicCart = jQuery('.woocommerce-cart').length > 0 && !isBlocksCart;
            const wrapperExists = jQuery('#cko-google-pay-button-wrapper-cart').length > 0;
            
            if (cko_google_pay_vars.debug) {
                console.log('[Google Pay Express] Cart page button initialization:', {
                    retryCount,
                    isBlocksCart,
                    isClassicCart,
                    wrapperExists,
                    googleApiLoaded: typeof google !== 'undefined' && google.payments && google.payments.api
                });
            }
            
            const getOrCreateCartButtonsContainer = function() {
                const existingButtons = jQuery('.cko-express-checkout-container .cko-express-checkout-buttons').first();
                if (existingButtons.length) {
                    return existingButtons;
                }

                const container = jQuery('<div id="cko-express-checkout-cart-container" class="cko-express-checkout-container"><div class="cko-express-checkout-buttons"></div></div>');
                const buttons = container.find('.cko-express-checkout-buttons');

                if (isBlocksCart) {
                    const paymentOptions = jQuery('.wc-block-cart__payment-options');
                    if (paymentOptions.length) {
                        paymentOptions.append(container);
                        return buttons;
                    }

                    const proceedButton = jQuery('.wc-block-cart__submit, .wc-block-components-button--contained');
                    if (proceedButton.length) {
                        proceedButton.before(container);
                        return buttons;
                    }

                    const cartTotals = jQuery('.wc-block-cart__totals');
                    if (cartTotals.length) {
                        cartTotals.before(container);
                        return buttons;
                    }

                    const cartBlock = jQuery('.wc-block-cart');
                    if (cartBlock.length) {
                        cartBlock.append(container);
                        return buttons;
                    }
                }

                if (isClassicCart) {
                    const proceedToCheckout = jQuery('.wc-proceed-to-checkout');
                    if (proceedToCheckout.length) {
                        proceedToCheckout.before(container);
                        return buttons;
                    }

                    const cartTotals = jQuery('.cart_totals');
                    if (cartTotals.length) {
                        cartTotals.before(container);
                        return buttons;
                    }
                }

                return buttons;
            };

            if ((isBlocksCart || isClassicCart) && !wrapperExists) {
                cko_google_pay_vars.debug && console.log('[Google Pay Express] Injecting button wrapper for cart');
                const buttonsContainer = getOrCreateCartButtonsContainer();
                if (buttonsContainer && buttonsContainer.length && !buttonsContainer.find('#cko-google-pay-button-wrapper-cart').length) {
                    buttonsContainer.append('<div class="cko-google-pay-cart-button"><div id="cko-google-pay-button-wrapper-cart"></div></div>');
                }
            }
            
            // Initialize button for cart page
            const wrapper = jQuery('#cko-google-pay-button-wrapper-cart');
            if (wrapper.length && typeof google !== 'undefined' && google.payments && google.payments.api) {
                try {
                    const paymentsClient = new google.payments.api.PaymentsClient({
                        environment: cko_google_pay_vars.environment || 'TEST'
                    });

                    const button = paymentsClient.createButton({
                        onClick: this.onGooglePayButtonClick.bind(this, 'cart'),
                        buttonColor: cko_google_pay_vars.button_style === 'google-pay-white' ? 'white' : 'black',
                        buttonType: 'plain'
                    });

                    wrapper.html('');
                    wrapper.append(button);
                    wrapper.show();
                    
                    cko_google_pay_vars.debug && console.log('[Google Pay Express] Cart page button initialized successfully');
                } catch (error) {
                    console.error('[Google Pay Express] Error initializing cart page button:', error.message);
                }
            } else {
                // If wrapper doesn't exist or API not loaded, try again after a delay (with limit)
                if (!wrapper.length && retryCount < maxRetries) {
                    cko_google_pay_vars.debug && console.log('[Google Pay Express] Wrapper not found, retrying...', retryCount + 1);
                    setTimeout(() => this.initCartPageButton(retryCount + 1), 500);
                } else if (!wrapper.length && retryCount >= maxRetries) {
                    console.warn('[Google Pay Express] Cart page button wrapper not found after ' + maxRetries + ' retries. Make sure the cart page setting is enabled.');
                } else if (typeof google === 'undefined' || !google.payments || !google.payments.api) {
                    if (retryCount < maxRetries) {
                        cko_google_pay_vars.debug && console.log('[Google Pay Express] Google Pay API not loaded, retrying...', retryCount + 1);
                        setTimeout(() => this.initCartPageButton(retryCount + 1), 500);
                    } else {
                        console.warn('[Google Pay Express] Google Pay API not loaded after ' + maxRetries + ' retries.');
                    }
                }
            }
        },

        onGooglePayButtonClick: async function(context, productId) {
            cko_google_pay_vars.debug && console.log('[Google Pay Express] Button clicked:', context, productId);

            // Show loading state
            jQuery('body').addClass('google-pay-processing');
            
            let loadingContainer = '#cko-google-pay-button-wrapper';
            if (context === 'shop' && productId) {
                loadingContainer = '#cko-google-pay-button-wrapper-' + productId;
            } else if (context === 'cart') {
                loadingContainer = '#cko-google-pay-button-wrapper-cart';
            }
            
            jQuery(loadingContainer).html('<div style="text-align: center; padding: 20px;"><div style="display: inline-block; width: 20px; height: 20px; border: 2px solid #f3f3f3; border-top: 2px solid #4285f4; border-radius: 50%; animation: spin 1s linear infinite;"></div><br><span style="color: #4285f4; font-size: 14px; margin-top: 10px; display: inline-block;">Processing your payment...</span></div>');
            
            // Add CSS for spinner animation
            if (!jQuery('#google-pay-spinner-css').length) {
                jQuery('head').append('<style id="google-pay-spinner-css">@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }</style>');
            }

            // Set a timeout fallback in case the API call takes too long (30 seconds)
            var timeoutFallback = setTimeout(function() {
                jQuery('body').removeClass('google-pay-processing');
                jQuery(loadingContainer).html('<div id="cko-google-pay-button"></div>');
                showError('Payment processing is taking longer than expected. Please try again.');
            }, 30000);

            try {
                // For Google Pay Express, we don't need payment contexts - process payment directly
                // But we still need to add product to cart if on product/shop page
                let cartTotal = 0;
                if (context === 'shop' && productId) {
                    let addToCartSuccess = await cko_express_add_to_cart_for_product(productId);
                    if (!addToCartSuccess || addToCartSuccess.result === 'error') {
                        clearTimeout(timeoutFallback);
                        jQuery('body').removeClass('google-pay-processing');
                        // Re-initialize the button after error
                        if (context === 'shop' && productId) {
                            this.initShopPageButtons();
                        } else {
                            jQuery(loadingContainer).html('<div id="cko-google-pay-button"></div>');
                        }
                        showError('Failed to add product to cart. Please try again.');
                        return;
                    }
                    // Get cart total from add to cart response
                    cartTotal = parseFloat(addToCartSuccess.total || 0);
                } else if (context === 'product') {
                    // For product page, use the form-based add to cart function
                    let addToCartSuccess = await cko_express_add_to_cart();
                    if (!addToCartSuccess || addToCartSuccess.result === 'error') {
                        clearTimeout(timeoutFallback);
                        jQuery('body').removeClass('google-pay-processing');
                        // Re-initialize the button after error
                        this.initProductPageButton();
                        showError('Failed to add product to cart. Please try again.');
                        return;
                    }
                    // Get cart total from add to cart response
                    cartTotal = parseFloat(addToCartSuccess.total || 0);
                } else {
                    // For cart page, try multiple methods to get cart total
                    // First try the dedicated API endpoint (most reliable)
                    cartTotal = await this.getCartTotalFromAPI();
                    
                    // If API doesn't work, try server fragments
                    if (cartTotal === 0 || isNaN(cartTotal)) {
                        cartTotal = await this.getCartTotalFromServer();
                    }
                    
                    // If still 0, try DOM
                    if (cartTotal === 0 || isNaN(cartTotal)) {
                        cartTotal = this.getCartTotal(context);
                    }
                    
                    // Final fallback: try API again
                    if (cartTotal === 0 || isNaN(cartTotal)) {
                        cartTotal = await this.getCartTotalFromAPI();
                    }
                }

                // If cart total is still 0, try one more time from DOM as final fallback
                if (cartTotal === 0) {
                    cartTotal = this.getCartTotal(context);
                }
                
                // If cart total is still 0, try to get it from server one more time
                if (cartTotal === 0) {
                    cartTotal = await this.getCartTotalFromServer();
                }
                
                // Final validation - ensure we have a valid cart total
                if (!cartTotal || cartTotal <= 0) {
                    clearTimeout(timeoutFallback);
                    jQuery('body').removeClass('google-pay-processing');
                    // Re-initialize the button after error
                    if (context === 'product') {
                        this.initProductPageButton();
                    } else if (context === 'cart') {
                        this.initCartPageButton();
                    } else if (context === 'shop' && productId) {
                        this.initShopPageButtons();
                    }
                    console.error('[Google Pay Express] Cart total is 0 or invalid:', cartTotal);
                    showError('Unable to calculate cart total. Please refresh the page and try again.');
                    return;
                }

                // Now create payment data request
                const paymentsClient = new google.payments.api.PaymentsClient({
                    environment: cko_google_pay_vars.environment || 'TEST'
                });

                // Get cart items for display
                let displayItems = await this.getCartItemsForDisplay();
                
                // Ensure displayItems is an array (fallback if empty)
                if (!Array.isArray(displayItems) || displayItems.length === 0) {
                    // Fallback: create a single item with cart total
                    displayItems = [{
                        label: 'Order Total',
                        type: 'LINE_ITEM',
                        price: cartTotal.toFixed(2),
                        quantity: '1'
                    }];
                }
                
                if (cko_google_pay_vars.debug) {
                    console.log('[Google Pay Express] Payment data request:', {
                        cartTotal,
                        displayItemsCount: displayItems.length,
                        totalPrice: cartTotal.toFixed(2)
                    });
                }

                // Validate required configuration
                if (!cko_google_pay_vars.public_key || !cko_google_pay_vars.public_key.trim()) {
                    clearTimeout(timeoutFallback);
                    jQuery('body').removeClass('google-pay-processing');
                    // Re-initialize the button after error
                    if (context === 'product') {
                        this.initProductPageButton();
                    } else {
                        jQuery(loadingContainer).html('<div id="cko-google-pay-button"></div>');
                    }
                    console.error('[Google Pay Express] Public key is missing or empty');
                    showError('Payment configuration error: Public key is missing. Please contact support.');
                    return;
                }

                if (!cko_google_pay_vars.merchant_id || !cko_google_pay_vars.merchant_id.trim()) {
                    clearTimeout(timeoutFallback);
                    jQuery('body').removeClass('google-pay-processing');
                    // Re-initialize the button after error
                    if (context === 'product') {
                        this.initProductPageButton();
                    } else {
                        jQuery(loadingContainer).html('<div id="cko-google-pay-button"></div>');
                    }
                    console.error('[Google Pay Express] Merchant ID is missing or empty');
                    showError('Payment configuration error: Merchant ID is missing. Please contact support.');
                    return;
                }

                // Use API v1 structure (same as classic Google Pay) to match working configuration
                // This ensures compatibility with Google Pay merchant configuration
                
                // Format amount based on currency decimal places
                // Zero-decimal currencies (JPY, KRW, etc.) should be rounded to integers
                // Two-decimal currencies (USD, EUR, etc.) use 2 decimal places
                const currencyCode = cko_google_pay_vars.currency_code || 'USD';
                const zeroDecimalCurrencies = ['JPY', 'KRW', 'VND', 'CLP', 'ISK', 'UGX', 'XAF', 'XOF', 'XPF', 'BIF', 'DJF', 'GNF', 'KMF', 'PYG', 'RWF', 'VUV'];
                const isZeroDecimal = zeroDecimalCurrencies.includes(currencyCode.toUpperCase());
                const formattedAmount = isZeroDecimal ? Math.round(cartTotal).toString() : cartTotal.toFixed(2);
                
                const paymentDataRequest = {
                    merchantId: cko_google_pay_vars.merchant_id.trim(),
                    paymentMethodTokenizationParameters: {
                        tokenizationType: 'PAYMENT_GATEWAY',
                        parameters: {
                            'gateway': 'checkoutltd',
                            'gatewayMerchantId': cko_google_pay_vars.public_key.trim()
                        }
                    },
                    allowedPaymentMethods: ['CARD', 'TOKENIZED_CARD'],
                    cardRequirements: {
                        allowedCardNetworks: ['AMEX', 'DISCOVER', 'JCB', 'MASTERCARD', 'VISA'],
                        // Request ECv2 by specifying allowedCardAuthMethods (CRYPTOGRAM_3DS is ECv2, PAN_ONLY may be ECv1)
                        allowedCardAuthMethods: ['CRYPTOGRAM_3DS']
                    },
                    transactionInfo: {
                        currencyCode: currencyCode,
                        totalPriceStatus: 'FINAL',
                        totalPrice: formattedAmount,
                        totalPriceLabel: 'Total'
                    },
                    // Add display items for breakdown in popup (API v1 supports this)
                    displayItems: displayItems,
                    // Request shipping address and email (API v1 supports these)
                    shippingAddressRequired: true,
                    emailRequired: true,
                    shippingAddressParameters: {
                        phoneNumberRequired: false
                    }
                };

                // Load payment data
                try {
                    let paymentData = await paymentsClient.loadPaymentData(paymentDataRequest);
                    
                    // Clear the timeout since we got payment data
                    clearTimeout(timeoutFallback);

                    // IMPORTANT: Extract token data directly from paymentData, matching classic Google Pay
                    // Classic Google Pay does: JSON.parse(paymentData.paymentMethodToken.token).signedMessage
                    // We need to extract the token data and ensure it's in the same format
                    let tokenData = null;
                    if (paymentData.paymentMethodToken && paymentData.paymentMethodToken.token) {
                        // Parse the token JSON string to extract the token data (same as classic)
                        tokenData = JSON.parse(paymentData.paymentMethodToken.token);
                        cko_google_pay_vars.debug && console.log('[Google Pay Express] Token data extracted - protocolVersion:', tokenData.protocolVersion);
                        
                        // Create a modified payment data object with the token data properly formatted
                        // This ensures the signedMessage is in the exact same format as classic Google Pay
                        // Note: We use a new variable to avoid reassigning const
                        paymentData = {
                            cardInfo: paymentData.cardInfo,
                            email: paymentData.email,
                            shippingAddress: paymentData.shippingAddress,
                            // Keep the token as a JSON string (matching classic format)
                            paymentMethodToken: {
                                token: paymentData.paymentMethodToken.token, // Keep original token string
                                tokenizationType: paymentData.paymentMethodToken.tokenizationType
                            }
                        };
                    }

                    // Process the payment (no payment context needed for Google Pay)
                    await this.processPayment(paymentData, null, loadingContainer);
                    } catch (loadError) {
                        clearTimeout(timeoutFallback);
                        jQuery('body').removeClass('google-pay-processing');
                        
                        // Re-initialize the button after error/cancel
                        if (context === 'product') {
                            this.initProductPageButton();
                        } else if (context === 'cart') {
                            this.initCartPageButton();
                        } else if (context === 'shop' && productId) {
                            this.initShopPageButtons();
                        }
                        
                        if (loadError.statusCode === 'CANCELED') {
                            return; // User canceled, don't show error
                        } else {
                            showError('Payment failed: ' + (loadError.message || 'Unknown error. Please try again.'));
                        }
                        return;
                    }

            } catch (error) {
                clearTimeout(timeoutFallback);
                jQuery('body').removeClass('google-pay-processing');
                
                // Re-initialize the button after error
                if (context === 'product') {
                    this.initProductPageButton();
                } else if (context === 'cart') {
                    this.initCartPageButton();
                } else if (context === 'shop' && productId) {
                    // For shop page, button will be re-initialized on next page load
                    jQuery(loadingContainer).html('<div id="cko-google-pay-button"></div>');
                }
                
                if (error.statusCode !== 'CANCELED') {
                    showError('Payment failed: ' + (error.message || 'Unknown error. Please try again.'));
                }
            }
        },

        getCartTotalFromAPI: async function() {
            // Get cart total directly from WooCommerce API endpoint
            // This works for both classic and Blocks cart pages
            try {
                if (!cko_google_pay_vars.get_cart_total_url) {
                    if (cko_google_pay_vars.debug) {
                        console.log('[Google Pay Express] get_cart_total_url not available');
                    }
                    return 0;
                }

                const response = await jQuery.ajax({
                    url: cko_google_pay_vars.get_cart_total_url,
                    type: 'GET',
                    dataType: 'json'
                });

                if (response && response.success && response.data && response.data.total) {
                    const total = parseFloat(response.data.total) || 0;
                    if (cko_google_pay_vars.debug) {
                        console.log('[Google Pay Express] Cart total from API (Blocks/Classic):', total, response.data);
                    }
                    if (total > 0) {
                        return total;
                    }
                } else if (response && response.success === false) {
                    if (cko_google_pay_vars.debug) {
                        console.log('[Google Pay Express] API returned error:', response.data);
                    }
                }

                return 0;
            } catch (error) {
                if (cko_google_pay_vars.debug) {
                    console.log('[Google Pay Express] API call failed:', error);
                }
                return 0;
            }
        },

        getCartTotalFromServer: async function() {
            // Get cart total from WooCommerce cart fragment
            try {
                // Build AJAX URL - try multiple methods
                let ajaxUrl = window.location.href;
                
                // Try to get WooCommerce AJAX URL from various sources
                if (typeof wc_add_to_cart_params !== 'undefined' && wc_add_to_cart_params.wc_ajax_url) {
                    ajaxUrl = wc_add_to_cart_params.wc_ajax_url.toString().replace('%%endpoint%%', 'get_refreshed_fragments');
                } else if (typeof wc_cart_fragments_params !== 'undefined' && wc_cart_fragments_params.wc_ajax_url) {
                    ajaxUrl = wc_cart_fragments_params.wc_ajax_url.toString().replace('%%endpoint%%', 'get_refreshed_fragments');
                } else {
                    // Fallback: construct URL manually
                    ajaxUrl = window.location.origin + '/?wc-ajax=get_refreshed_fragments';
                }

                const response = await jQuery.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        'wc-ajax': 'get_refreshed_fragments'
                    }
                });

                if (response && response.fragments) {
                    // Try to extract total from cart fragments (classic cart)
                    let cartTotalMatch = response.fragments['div.cart_totals']?.match(/total[^>]*>[\s\S]*?(\d+[.,]\d+)/i);
                    if (cartTotalMatch) {
                        const total = parseFloat(cartTotalMatch[1].replace(',', '.')) || 0;
                        if (cko_google_pay_vars.debug) {
                            console.log('[Google Pay Express] Cart total from fragments (classic):', total);
                        }
                        if (total > 0) return total;
                    }
                    
                    // Try Blocks cart fragments
                    const fragmentKeys = Object.keys(response.fragments);
                    for (let key of fragmentKeys) {
                        const fragment = response.fragments[key];
                        if (fragment && typeof fragment === 'string') {
                            // Look for total in Blocks cart format
                            const blocksTotalMatch = fragment.match(/wc-block-components-totals__total-value[^>]*>[\s\S]*?(\d+[.,]\d+)/i) ||
                                                      fragment.match(/wc-block-components-totals__item-value[^>]*>[\s\S]*?(\d+[.,]\d+)/i);
                            if (blocksTotalMatch) {
                                const total = parseFloat(blocksTotalMatch[1].replace(',', '.')) || 0;
                                if (cko_google_pay_vars.debug) {
                                    console.log('[Google Pay Express] Cart total from fragments (Blocks):', total);
                                }
                                if (total > 0) return total;
                            }
                        }
                    }
                }

                // Fallback: try to get from cart total element in DOM (both classic and Blocks)
                const cartTotalElement = jQuery('.cart_totals .order-total .woocommerce-Price-amount, .wc-block-cart__totals .wc-block-components-totals__total-value, .wc-block-cart__totals .wc-block-components-totals__item-value');
                if (cartTotalElement.length) {
                    const totalText = cartTotalElement.last().text().replace(/[^\d.,]/g, '');
                    const total = parseFloat(totalText.replace(',', '.')) || 0;
                    if (cko_google_pay_vars.debug) {
                        console.log('[Google Pay Express] Cart total from DOM fallback:', total);
                    }
                    return total;
                }

                return 0;
            } catch (error) {
                // If AJAX fails, try DOM fallback
                cko_google_pay_vars.debug && console.log('[Google Pay Express] AJAX failed, using DOM fallback:', error);
                const cartTotalElement = jQuery('.cart_totals .order-total .woocommerce-Price-amount, .wc-block-cart__totals .wc-block-components-totals__total-value, .wc-block-cart__totals .wc-block-components-totals__item-value');
                if (cartTotalElement.length) {
                    const totalText = cartTotalElement.last().text().replace(/[^\d.,]/g, '');
                    const total = parseFloat(totalText.replace(',', '.')) || 0;
                    if (cko_google_pay_vars.debug) {
                        console.log('[Google Pay Express] Cart total from DOM error fallback:', total);
                    }
                    return total;
                }
                return 0;
            }
        },

        getCartTotal: function(context) {
            // Get cart total from page or cart (fallback method)
            let total = '0.00';
            
            if (context === 'cart') {
                // Try multiple selectors for different cart page types
                // Blocks cart page
                let cartTotalElement = jQuery('.wc-block-cart__totals .wc-block-components-totals__item-value, .wc-block-cart__totals .wc-block-components-totals__total-value');
                if (cartTotalElement.length) {
                    const totalText = cartTotalElement.last().text().replace(/[^\d.,]/g, '');
                    total = totalText.replace(',', '.');
                } else {
                    // Classic cart page
                    cartTotalElement = jQuery('.cart_totals .order-total .woocommerce-Price-amount, .cart_totals .woocommerce-Price-amount, .cart_totals .order-total td .amount');
                    if (cartTotalElement.length) {
                        const totalText = cartTotalElement.last().text().replace(/[^\d.,]/g, '');
                        total = totalText.replace(',', '.');
                    } else {
                        // Another fallback for Blocks cart
                        cartTotalElement = jQuery('.wc-block-cart__totals .amount, .wc-block-cart__totals .woocommerce-Price-amount');
                        if (cartTotalElement.length) {
                            const totalText = cartTotalElement.last().text().replace(/[^\d.,]/g, '');
                            total = totalText.replace(',', '.');
                        }
                    }
                }
                
                if (cko_google_pay_vars.debug) {
                    console.log('[Google Pay Express] getCartTotal for cart:', {
                        total,
                        foundElements: cartTotalElement.length,
                        elementText: cartTotalElement.length ? cartTotalElement.last().text() : 'none'
                    });
                }
            } else {
                // Get product price
                const priceElement = jQuery('.price .amount, .price .woocommerce-Price-amount, .woocommerce-Price-amount');
                if (priceElement.length) {
                    const priceText = priceElement.first().text().replace(/[^\d.,]/g, '');
                    total = priceText.replace(',', '.');
                }
            }

            return parseFloat(total) || 0.00;
        },

        getCartItemsForDisplay: async function() {
            // Get cart items for Google Pay display
            const items = [];
            
            try {
                // Try to get cart items from DOM first (most reliable)
                let hasItems = false;
                jQuery('.cart .cart_item, .woocommerce-cart .cart_item').each(function() {
                    const $item = jQuery(this);
                    const productName = $item.find('.product-name a, .product-name').text().trim() || 'Product';
                    const quantity = parseInt($item.find('.product-quantity input, .quantity input').val() || $item.find('.product-quantity').text().match(/\d+/)?.[0] || '1');
                    const priceText = $item.find('.product-price .amount, .product-price .woocommerce-Price-amount').text().replace(/[^\d.,]/g, '');
                    const price = parseFloat(priceText.replace(',', '.')) || 0;
                    
                    if (productName && price > 0 && productName !== 'Product') {
                        items.push({
                            label: productName,
                            type: 'LINE_ITEM',
                            price: price.toFixed(2),
                            quantity: quantity.toString()
                        });
                        hasItems = true;
                    }
                });

                // If no items found in DOM, create a default item with cart total
                if (!hasItems || items.length === 0) {
                    // Try DOM first for cart total
                    let cartTotal = this.getCartTotal('cart');
                    if (cartTotal === 0) {
                        // Only try server if DOM fails
                        try {
                            cartTotal = await this.getCartTotalFromServer();
                        } catch (e) {
                            // Ignore error, use 0
                        }
                    }
                    
                    if (cartTotal > 0) {
                        items.push({
                            label: 'Order Total',
                            type: 'LINE_ITEM',
                            price: cartTotal.toFixed(2),
                            quantity: '1'
                        });
                    }
                }
            } catch (error) {
                cko_google_pay_vars.debug && console.error('[Google Pay Express] Error getting cart items:', error);
                // Fallback: create a single item with cart total from DOM
                const cartTotal = this.getCartTotal('cart');
                if (cartTotal > 0) {
                    items.push({
                        label: 'Order Total',
                        type: 'LINE_ITEM',
                        price: cartTotal.toFixed(2),
                        quantity: '1'
                    });
                }
            }

            return items;
        },

        processPayment: async function(paymentData, paymentContextId, loadingContainer) {
            try {
                // IMPORTANT: Use the exact same flow as classic Google Pay
                // Classic Google Pay does:
                // 1. JSON.parse(paymentData.paymentMethodToken.token).signature
                // 2. JSON.parse(paymentData.paymentMethodToken.token).protocolVersion
                // 3. JSON.parse(paymentData.paymentMethodToken.token).signedMessage
                // 4. Sets form fields and submits form (which calls process_payment)
                //
                // For express, we do the same extraction but call process_payment directly via AJAX
                
                // Extract token data exactly like classic Google Pay
                let signature = '';
                let protocolVersion = '';
                let signedMessage = '';
                
                if (paymentData.paymentMethodToken && paymentData.paymentMethodToken.token) {
                    // Parse the token JSON string exactly like classic does
                    const tokenData = JSON.parse(paymentData.paymentMethodToken.token);
                    signature = tokenData.signature;
                    protocolVersion = tokenData.protocolVersion;
                    signedMessage = tokenData.signedMessage;
                }
                
                // Extract email and address from payment data
                const email = paymentData.email || '';
                const shippingAddress = paymentData.shippingAddress || null;
                
                // Create order first (like express does)
                // Then call process_payment with the same data format as classic
                const response = await jQuery.post(
                    cko_google_pay_vars.google_pay_order_session_url + 
                    "&woocommerce-process-checkout-nonce=" + cko_google_pay_vars.woocommerce_process_checkout,
                    {
                        // Send token fields exactly like classic Google Pay form fields
                        'cko-google-signature': signature,
                        'cko-google-protocolVersion': protocolVersion,
                        'cko-google-signedMessage': signedMessage,
                        // Also send payment data for email/address extraction
                        payment_data: JSON.stringify({
                            email: email,
                            shippingAddress: shippingAddress
                        })
                    }
                );

                // Check if response indicates error (WordPress wp_send_json_error format)
                // wp_send_json_error returns: { success: false, data: { messages: '...' } }
                if (response.success === false || (response.success !== true && response.data)) {
                    // Hide loading state on error
                    jQuery('body').removeClass('google-pay-processing');
                    
                    // Re-initialize the button after error
                    // We need to determine context from loadingContainer
                    if (loadingContainer === '#cko-google-pay-button-wrapper') {
                        this.initProductPageButton();
                    } else if (loadingContainer === '#cko-google-pay-button-wrapper-cart') {
                        this.initCartPageButton();
                    } else {
                        jQuery(loadingContainer).html('<div id="cko-google-pay-button"></div>');
                    }
                    
                    // Extract error message from response
                    var messages = '';
                    if (response.data && response.data.messages) {
                        messages = response.data.messages;
                    } else if (response.data && typeof response.data === 'string') {
                        messages = response.data;
                    } else if (response.message) {
                        messages = response.message;
                    } else if (typeof response === 'string') {
                        messages = response;
                    } else {
                        messages = 'Google Pay returned ECv1 protocol which is not supported. Please try again or use a different payment method.';
                    }

                    // Always show error message
                    if (typeof messages === 'string') {
                        showError(messages);
                    } else if (Array.isArray(messages)) {
                        showError(messages);
                    } else {
                        showError('Google Pay returned ECv1 protocol which is not supported. Please try again or use a different payment method.');
                    }
                    return;
                } 
                
                // Check if response indicates success (WordPress wp_send_json_success format)
                // wp_send_json_success returns: { success: true, data: { redirect_url: '...' } }
                if (response.success === true && response.data && response.data.redirect_url) {
                    // Redirect to success page
                    window.location.href = response.data.redirect_url;
                } else {
                    // Unexpected response - re-initialize button
                    jQuery('body').removeClass('google-pay-processing');
                    if (loadingContainer === '#cko-google-pay-button-wrapper') {
                        this.initProductPageButton();
                    } else if (loadingContainer === '#cko-google-pay-button-wrapper-cart') {
                        this.initCartPageButton();
                    } else {
                        jQuery(loadingContainer).html('<div id="cko-google-pay-button"></div>');
                    }
                    showError('Payment processing failed. Please try again.');
                }
            } catch (error) {
                // Hide loading state on error
                jQuery('body').removeClass('google-pay-processing');
                
                // Re-initialize the button after error
                if (loadingContainer === '#cko-google-pay-button-wrapper') {
                    this.initProductPageButton();
                } else if (loadingContainer === '#cko-google-pay-button-wrapper-cart') {
                    this.initCartPageButton();
                } else {
                    jQuery(loadingContainer).html('<div id="cko-google-pay-button"></div>');
                }
                
                showError('Payment processing failed. Please try again.');
            }
        },

        updateButtonVisibility: function () {
            // Show product page button
            if ( jQuery( cko_google_pay_vars.google_pay_button_selector ) ) {
                jQuery( cko_google_pay_vars.google_pay_button_selector ).show();
            }
            
            // Show shop page buttons
            jQuery('[id^="cko-google-pay-button-wrapper-"]').each(function() {
                if (jQuery(this).attr('id') !== 'cko-google-pay-button-wrapper') {
                    jQuery(this).show();
                }
            });
            
            // Show cart page button
            if ( jQuery('#cko-google-pay-button-wrapper-cart') ) {
                jQuery('#cko-google-pay-button-wrapper-cart').show();
            }
        }
    }

    // Initialize on document ready
    googlePayButton.init();
    
    // Also try to initialize after a short delay to catch cases where DOM isn't ready yet
    setTimeout(function() {
        if (typeof google !== 'undefined' && google.payments && google.payments.api) {
            googlePayButton.initializeButtons();
        }
    }, 1000);
    
    // Re-initialize buttons when cart is updated (for cart page)
    jQuery(document.body).on('updated_cart_totals', function() {
        if (typeof google !== 'undefined' && google.payments && google.payments.api) {
            googlePayButton.initializeButtons();
        }
    });
});

