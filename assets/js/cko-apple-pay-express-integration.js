/* global cko_apple_pay_vars */

jQuery( function ( $ ) {
    
    const formSelector = 'form.cart';

    const onFormChange = function ( e ) {
        const form = document.querySelector( formSelector );

        const addToCartButton = form ? form.querySelector('.single_add_to_cart_button') : null;

        const isEnabled = ( null === addToCartButton ) || ! addToCartButton.classList.contains( 'disabled' );

        const element = jQuery( cko_apple_pay_vars.apple_pay_button_selector );

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
            nonce: cko_apple_pay_vars.apple_pay_express_add_to_cart_nonce
        };

        return await $.ajax( {
            url: cko_apple_pay_vars.add_to_cart_url,
            type: 'POST',
            async: false,
            data: data
        } ).done( function ( response ) {
            cko_apple_pay_vars.debug && console.log( response );
        } ).fail( function ( xhr, status, error ) {
            console.error('[Apple Pay Express] Add to cart failed:', error);
            cko_apple_pay_vars.debug && console.error('[Apple Pay Express] Response text:', xhr.responseText);
            return { result: 'error', message: 'Failed to add product to cart' };
        } )
    }

    const cko_express_add_to_cart_for_product = async function (productId) {
        let data = {
            product_id: productId,
            quantity: 1,
            nonce: cko_apple_pay_vars.apple_pay_express_add_to_cart_nonce
        };

        return await $.ajax( {
            url: cko_apple_pay_vars.add_to_cart_url,
            type: 'POST',
            async: false,
            data: data
        } ).done( function ( response ) {
            cko_apple_pay_vars.debug && console.log( 'Add to cart response for product ' + productId + ':', response );
        } ).fail( function ( xhr, status, error ) {
            console.error('[Apple Pay Express] Add to cart failed for product ' + productId + ':', error);
            cko_apple_pay_vars.debug && console.error('[Apple Pay Express] Response text:', xhr.responseText);
            return { result: 'error', message: 'Failed to add product to cart' };
        } )
    };

    const getCartTotalFromAPI = async function() {
        // Get cart total directly from WooCommerce API endpoint
        try {
            if (!cko_apple_pay_vars.get_cart_total_url) {
                if (cko_apple_pay_vars.debug) {
                    console.log('[Apple Pay Express] get_cart_total_url not available');
                }
                return 0;
            }

            const response = await jQuery.ajax({
                url: cko_apple_pay_vars.get_cart_total_url,
                type: 'GET',
                dataType: 'json'
            });

            if (response && response.success && response.data && response.data.total) {
                const total = parseFloat(response.data.total) || 0;
                if (cko_apple_pay_vars.debug) {
                    console.log('[Apple Pay Express] Cart total from API:', total, response.data);
                }
                if (total > 0) {
                    return total;
                }
            }

            return 0;
        } catch (error) {
            if (cko_apple_pay_vars.debug) {
                console.log('[Apple Pay Express] API call failed:', error);
            }
            return 0;
        }
    };

    const getCartTotalFromServer = async function() {
        // Get cart total from WooCommerce cart fragment
        try {
            let ajaxUrl = window.location.href;
            
            if (typeof wc_add_to_cart_params !== 'undefined' && wc_add_to_cart_params.wc_ajax_url) {
                ajaxUrl = wc_add_to_cart_params.wc_ajax_url.toString().replace('%%endpoint%%', 'get_refreshed_fragments');
            } else if (typeof wc_cart_fragments_params !== 'undefined' && wc_cart_fragments_params.wc_ajax_url) {
                ajaxUrl = wc_cart_fragments_params.wc_ajax_url.toString().replace('%%endpoint%%', 'get_refreshed_fragments');
            } else {
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
                let cartTotalMatch = response.fragments['div.cart_totals']?.match(/total[^>]*>[\s\S]*?(\d+[.,]\d+)/i);
                if (cartTotalMatch) {
                    const total = parseFloat(cartTotalMatch[1].replace(',', '.')) || 0;
                    if (total > 0) return total;
                }
                
                const fragmentKeys = Object.keys(response.fragments);
                for (let key of fragmentKeys) {
                    const fragment = response.fragments[key];
                    if (fragment && typeof fragment === 'string') {
                        const blocksTotalMatch = fragment.match(/wc-block-components-totals__total-value[^>]*>[\s\S]*?(\d+[.,]\d+)/i) ||
                                                  fragment.match(/wc-block-components-totals__item-value[^>]*>[\s\S]*?(\d+[.,]\d+)/i);
                        if (blocksTotalMatch) {
                            const total = parseFloat(blocksTotalMatch[1].replace(',', '.')) || 0;
                            if (total > 0) return total;
                        }
                    }
                }
            }

            const cartTotalElement = jQuery('.cart_totals .order-total .woocommerce-Price-amount, .wc-block-cart__totals .wc-block-components-totals__total-value, .wc-block-cart__totals .wc-block-components-totals__item-value');
            if (cartTotalElement.length) {
                const totalText = cartTotalElement.last().text().replace(/[^\d.,]/g, '');
                const total = parseFloat(totalText.replace(',', '.')) || 0;
                return total;
            }

            return 0;
        } catch (error) {
            const cartTotalElement = jQuery('.cart_totals .order-total .woocommerce-Price-amount, .wc-block-cart__totals .wc-block-components-totals__total-value, .wc-block-cart__totals .wc-block-components-totals__item-value');
            if (cartTotalElement.length) {
                const totalText = cartTotalElement.last().text().replace(/[^\d.,]/g, '');
                const total = parseFloat(totalText.replace(',', '.')) || 0;
                return total;
            }
            return 0;
        }
    };

    const getCartTotal = function(context) {
        let total = '0.00';
        
        if (context === 'cart') {
            let cartTotalElement = jQuery('.wc-block-cart__totals .wc-block-components-totals__item-value, .wc-block-cart__totals .wc-block-components-totals__total-value');
            if (cartTotalElement.length) {
                const totalText = cartTotalElement.last().text().replace(/[^\d.,]/g, '');
                total = totalText.replace(',', '.');
            } else {
                cartTotalElement = jQuery('.cart_totals .order-total .woocommerce-Price-amount, .cart_totals .woocommerce-Price-amount, .cart_totals .order-total td .amount');
                if (cartTotalElement.length) {
                    const totalText = cartTotalElement.last().text().replace(/[^\d.,]/g, '');
                    total = totalText.replace(',', '.');
                } else {
                    cartTotalElement = jQuery('.wc-block-cart__totals .amount, .wc-block-cart__totals .woocommerce-Price-amount');
                    if (cartTotalElement.length) {
                        const totalText = cartTotalElement.last().text().replace(/[^\d.,]/g, '');
                        total = totalText.replace(',', '.');
                    }
                }
            }
        } else {
            const priceElement = jQuery('.price .amount, .price .woocommerce-Price-amount, .woocommerce-Price-amount');
            if (priceElement.length) {
                const priceText = priceElement.first().text().replace(/[^\d.,]/g, '');
                total = priceText.replace(',', '.');
            }
        }

        return parseFloat(total) || 0.00;
    };

    const getCartItemsForDisplay = async function() {
        const items = [];
        
        try {
            let hasItems = false;
            
            // Try to get cart items from DOM
            jQuery('.cart_item, .wc-block-cart-item').each(function() {
                const $item = jQuery(this);
                const productName = $item.find('.product-name a, .wc-block-cart-item__product-name a').text().trim();
                const quantity = $item.find('.product-quantity .quantity input, .wc-block-cart-item__quantity input').val() || 
                                $item.find('.product-quantity, .wc-block-cart-item__quantity').text().match(/\d+/)?.[0] || '1';
                const priceText = $item.find('.product-price .amount, .wc-block-cart-item__total .amount').text().replace(/[^\d.,]/g, '');
                const price = parseFloat(priceText.replace(',', '.')) || 0;
                
                if (productName && price > 0) {
                    items.push({
                        label: productName,
                        type: 'LINE_ITEM',
                        price: price.toFixed(2),
                        quantity: quantity
                    });
                    hasItems = true;
                }
            });
            
            if (hasItems) {
                return items;
            }
        } catch (error) {
            cko_apple_pay_vars.debug && console.log('[Apple Pay Express] Error getting cart items:', error);
        }
        
        return items;
    };

    const performAppleUrlValidation = function(valURL, callback) {
        jQuery.ajax({
            type: 'POST',
            url: cko_apple_pay_vars.session_url || window.location.origin + '/?wc-api=wc_checkoutcom_session',
            data: {
                url: valURL,
                merchantId: cko_apple_pay_vars.merchant_id,
                domain: window.location.host,
                displayName: window.location.host,
            },
            success: function (outcome) {
                var data = JSON.parse(outcome);
                callback(data);
            },
            error: function() {
                callback(null);
            }
        });
    };

    const generateCheckoutToken = function(token, callback) {
        jQuery.ajax({
            type: 'POST',
            url: cko_apple_pay_vars.generate_token_url || window.location.origin + '/?wc-api=wc_checkoutcom_generate_token',
            data: {
                token: token
            },
            success: function (outcome) {
                callback(outcome);
            },
            error: function () {
                callback('');
            }
        });
    };

    const applePayButton = {
        init: function () {
            // Wait for Apple Pay API to load
            this.waitForApplePayAPI();
        },

        waitForApplePayAPI: function() {
            const self = this;
            const checkInterval = setInterval(function() {
                if (typeof ApplePaySession !== 'undefined') {
                    clearInterval(checkInterval);
                    setTimeout(function() {
                        self.initializeButtons();
                    }, 100);
                }
            }, 100);

            setTimeout(function() {
                clearInterval(checkInterval);
                if (typeof ApplePaySession === 'undefined') {
                    cko_apple_pay_vars.debug && console.warn('[Apple Pay Express] Apple Pay API not loaded after 10 seconds');
                } else {
                    setTimeout(function() {
                        self.initializeButtons();
                    }, 100);
                }
            }, 10000);
        },

        initializeButtons: function() {
            const isProductPage = jQuery('#cko-apple-pay-button-wrapper').length > 0;
            const isCartPageByWrapper = jQuery('#cko-apple-pay-button-wrapper-cart').length > 0;
            const isCartPageByClass = jQuery('.wc-block-cart, .woocommerce-cart').length > 0;
            const isCartPage = isCartPageByWrapper || isCartPageByClass;
            const isShopPage = jQuery('[id^="cko-apple-pay-button-wrapper-"]').filter(function() {
                return !jQuery(this).is('#cko-apple-pay-button-wrapper-cart') && !jQuery(this).is('#cko-apple-pay-button-wrapper');
            }).length > 0;
            
            if (isProductPage) {
                this.initProductPageButton();
            }
            
            if (isShopPage) {
                this.initShopPageButtons();
            }
            
            if (isCartPage) {
                this.initCartPageButton();
            }

            this.updateButtonVisibility();

            jQuery(document).on('change', formSelector, onFormChange );
        },

        initProductPageButton: function(retryCount) {
            retryCount = retryCount || 0;
            const maxRetries = 5;
            
            const wrapper = jQuery('#cko-apple-pay-button-wrapper');
            if (wrapper.length && typeof ApplePaySession !== 'undefined') {
                if (!ApplePaySession.canMakePayments(cko_apple_pay_vars.merchant_id)) {
                    wrapper.hide();
                    return;
                }

                try {
                    const button = document.createElement('apple-pay-button');
                    button.setAttribute('id', 'cko-apple-pay-button');
                    button.setAttribute('type', 'plain');
                    button.setAttribute('buttonstyle', cko_apple_pay_vars.button_theme || 'black');
                    if (cko_apple_pay_vars.button_language) {
                        button.setAttribute('locale', cko_apple_pay_vars.button_language);
                    }
                    button.onclick = () => this.onApplePayButtonClick('product');
                    
                    wrapper.html('');
                    wrapper.append(button);
                    wrapper.show();
                } catch (error) {
                    console.error('[Apple Pay Express] Error initializing product page button:', error.message);
                }
            } else {
                if (!wrapper.length && retryCount < maxRetries) {
                    setTimeout(() => this.initProductPageButton(retryCount + 1), 500);
                } else if (!wrapper.length && retryCount >= maxRetries) {
                    console.warn('[Apple Pay Express] Product page button wrapper not found after ' + maxRetries + ' retries.');
                }
            }
        },

        initShopPageButtons: function() {
            const self = this;
            
            jQuery('[id^="cko-apple-pay-button-wrapper-"]').each(function() {
                const $wrapper = jQuery(this);
                const productId = $wrapper.data('product-id');
                
                if (productId && $wrapper.attr('id') !== 'cko-apple-pay-button-wrapper' && typeof ApplePaySession !== 'undefined') {
                    if (!ApplePaySession.canMakePayments(cko_apple_pay_vars.merchant_id)) {
                        $wrapper.hide();
                        return;
                    }

                    try {
                        const button = document.createElement('apple-pay-button');
                        button.setAttribute('id', 'cko-apple-pay-button-' + productId);
                        button.setAttribute('type', 'plain');
                        button.setAttribute('buttonstyle', cko_apple_pay_vars.button_theme || 'black');
                        if (cko_apple_pay_vars.button_language) {
                            button.setAttribute('locale', cko_apple_pay_vars.button_language);
                        }
                        button.onclick = () => self.onApplePayButtonClick('shop', productId);
                        
                        $wrapper.html('');
                        $wrapper.append(button);
                        $wrapper.show();
                    } catch (error) {
                        console.error('[Apple Pay Express] Error initializing shop page button for product ' + productId + ':', error.message);
                    }
                }
            });
        },

        initCartPageButton: function(retryCount) {
            retryCount = retryCount || 0;
            const maxRetries = 10;
            
            const isBlocksCart = jQuery('.wc-block-cart').length > 0;
            const isClassicCart = jQuery('.woocommerce-cart').length > 0 && !isBlocksCart;
            const wrapperExists = jQuery('#cko-apple-pay-button-wrapper-cart').length > 0;

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
                const buttonsContainer = getOrCreateCartButtonsContainer();
                if (buttonsContainer && buttonsContainer.length && !buttonsContainer.find('#cko-apple-pay-button-wrapper-cart').length) {
                    buttonsContainer.append('<div id="cko-apple-pay-button-wrapper-cart"></div>');
                }
            }
            
            const wrapper = jQuery('#cko-apple-pay-button-wrapper-cart');
            if (wrapper.length && typeof ApplePaySession !== 'undefined') {
                if (!ApplePaySession.canMakePayments(cko_apple_pay_vars.merchant_id)) {
                    wrapper.hide();
                    return;
                }

                try {
                    const button = document.createElement('apple-pay-button');
                    button.setAttribute('id', 'cko-apple-pay-button-cart');
                    button.setAttribute('type', 'plain');
                    button.setAttribute('buttonstyle', cko_apple_pay_vars.button_theme || 'black');
                    if (cko_apple_pay_vars.button_language) {
                        button.setAttribute('locale', cko_apple_pay_vars.button_language);
                    }
                    button.onclick = () => this.onApplePayButtonClick('cart');
                    
                    wrapper.html('');
                    wrapper.append(button);
                    wrapper.show();
                } catch (error) {
                    console.error('[Apple Pay Express] Error initializing cart page button:', error.message);
                }
            } else {
                if (!wrapper.length && retryCount < maxRetries) {
                    setTimeout(() => this.initCartPageButton(retryCount + 1), 500);
                } else if (typeof ApplePaySession === 'undefined' && retryCount < maxRetries) {
                    setTimeout(() => this.initCartPageButton(retryCount + 1), 500);
                }
            }
        },

        onApplePayButtonClick: async function(context, productId) {
            cko_apple_pay_vars.debug && console.log('[Apple Pay Express] Button clicked:', context, productId);

            jQuery('body').addClass('apple-pay-processing');
            
            let loadingContainer = '#cko-apple-pay-button-wrapper';
            if (context === 'shop' && productId) {
                loadingContainer = '#cko-apple-pay-button-wrapper-' + productId;
            } else if (context === 'cart') {
                loadingContainer = '#cko-apple-pay-button-wrapper-cart';
            }
            
            jQuery(loadingContainer).html('<div style="text-align: center; padding: 20px;"><div style="display: inline-block; width: 20px; height: 20px; border: 2px solid #f3f3f3; border-top: 2px solid #000; border-radius: 50%; animation: spin 1s linear infinite;"></div><br><span style="color: #000; font-size: 14px; margin-top: 10px; display: inline-block;">Processing your payment...</span></div>');
            
            if (!jQuery('#apple-pay-spinner-css').length) {
                jQuery('head').append('<style id="apple-pay-spinner-css">@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }</style>');
            }

            var timeoutFallback = setTimeout(function() {
                jQuery('body').removeClass('apple-pay-processing');
                jQuery(loadingContainer).html('<div id="cko-apple-pay-button"></div>');
                showError('Payment processing is taking longer than expected. Please try again.');
            }, 30000);

            try {
                let cartTotal = 0;
                if (context === 'shop' && productId) {
                    let addToCartSuccess = await cko_express_add_to_cart_for_product(productId);
                    if (!addToCartSuccess || addToCartSuccess.result === 'error') {
                        clearTimeout(timeoutFallback);
                        jQuery('body').removeClass('apple-pay-processing');
                        this.initShopPageButtons();
                        showError('Failed to add product to cart. Please try again.');
                        return;
                    }
                    cartTotal = parseFloat(addToCartSuccess.total || 0);
                } else if (context === 'product') {
                    let addToCartSuccess = await cko_express_add_to_cart();
                    if (!addToCartSuccess || addToCartSuccess.result === 'error') {
                        clearTimeout(timeoutFallback);
                        jQuery('body').removeClass('apple-pay-processing');
                        this.initProductPageButton();
                        showError('Failed to add product to cart. Please try again.');
                        return;
                    }
                    cartTotal = parseFloat(addToCartSuccess.total || 0);
                } else {
                    cartTotal = await getCartTotalFromAPI();
                    if (cartTotal === 0 || isNaN(cartTotal)) {
                        cartTotal = await getCartTotalFromServer();
                    }
                    if (cartTotal === 0 || isNaN(cartTotal)) {
                        cartTotal = getCartTotal(context);
                    }
                }

                if (!cartTotal || cartTotal <= 0) {
                    clearTimeout(timeoutFallback);
                    jQuery('body').removeClass('apple-pay-processing');
                    if (context === 'product') {
                        this.initProductPageButton();
                    } else if (context === 'cart') {
                        this.initCartPageButton();
                    } else if (context === 'shop' && productId) {
                        this.initShopPageButtons();
                    }
                    console.error('[Apple Pay Express] Cart total is 0 or invalid:', cartTotal);
                    showError('Unable to calculate cart total. Please refresh the page and try again.');
                    return;
                }

                // Get cart items for display
                let displayItems = await getCartItemsForDisplay();
                
                // Get country code from customer data or default to store country
                let countryCode = 'US';
                if (typeof wc_customer_data !== 'undefined' && wc_customer_data.billing_country) {
                    countryCode = wc_customer_data.billing_country;
                } else if (typeof wc_add_to_cart_params !== 'undefined' && wc_add_to_cart_params.base_location) {
                    countryCode = wc_add_to_cart_params.base_location;
                }

                // Get supported networks
                let supportedNetworks = ['amex', 'masterCard', 'visa'];
                if (cko_apple_pay_vars.enable_mada) {
                    supportedNetworks.push('mada');
                    countryCode = 'SA';
                }

                // Get merchant capabilities
                let merchantCapabilities = ['supports3DS', 'supportsEMV', 'supportsCredit', 'supportsDebit'];
                if (cko_apple_pay_vars.enable_mada) {
                    merchantCapabilities = merchantCapabilities.filter(cap => cap !== 'supportsEMV');
                }

                // Format amount based on currency decimal places
                // Zero-decimal currencies (JPY, KRW, etc.) should be rounded to integers
                // Two-decimal currencies (USD, EUR, etc.) use 2 decimal places
                const currencyCode = cko_apple_pay_vars.currency_code || 'USD';
                const zeroDecimalCurrencies = ['JPY', 'KRW', 'VND', 'CLP', 'ISK', 'UGX', 'XAF', 'XOF', 'XPF', 'BIF', 'DJF', 'GNF', 'KMF', 'PYG', 'RWF', 'VUV'];
                const isZeroDecimal = zeroDecimalCurrencies.includes(currencyCode.toUpperCase());
                const formattedAmount = isZeroDecimal ? Math.round(cartTotal).toString() : cartTotal.toFixed(2);
                
                // Create Apple Pay payment request
                const paymentRequest = {
                    countryCode: countryCode,
                    currencyCode: currencyCode,
                    supportedNetworks: supportedNetworks,
                    merchantCapabilities: merchantCapabilities,
                    total: {
                        label: window.location.host,
                        amount: formattedAmount,
                        type: 'final'
                    },
                    // REQUIRED: Request shipping contact fields (MANDATORY for express checkout)
                    requiredShippingContactFields: [
                        'postalAddress',
                        'name',
                        'phone',
                        'email'
                    ],
                    // REQUIRED: Request billing contact fields (for email and address)
                    requiredBillingContactFields: [
                        'postalAddress',
                        'name',
                        'email'
                    ]
                };

                // Add line items if available
                if (displayItems && displayItems.length > 0) {
                    paymentRequest.lineItems = displayItems.map(item => {
                        // Format line item amount based on currency
                        const itemAmount = parseFloat(item.price) || 0;
                        const formattedItemAmount = isZeroDecimal ? Math.round(itemAmount).toString() : itemAmount.toFixed(2);
                        return {
                            label: item.label,
                            amount: formattedItemAmount,
                            type: 'final'
                        };
                    });
                }

                // Create Apple Pay session
                const session = new ApplePaySession(3, paymentRequest);

                // Handle merchant validation
                session.onvalidatemerchant = (event) => {
                    performAppleUrlValidation(event.validationURL, (merchantSession) => {
                        if (merchantSession) {
                            session.completeMerchantValidation(merchantSession);
                        } else {
                            clearTimeout(timeoutFallback);
                            jQuery('body').removeClass('apple-pay-processing');
                            if (context === 'product') {
                                this.initProductPageButton();
                            } else if (context === 'cart') {
                                this.initCartPageButton();
                            } else if (context === 'shop' && productId) {
                                this.initShopPageButtons();
                            }
                            showError('Failed to validate merchant. Please try again.');
                            session.abort();
                        }
                    });
                };

                // Handle payment authorization
                session.onpaymentauthorized = (event) => {
                    clearTimeout(timeoutFallback);
                    
                    // Detect browser for debugging
                    const userAgent = navigator.userAgent || navigator.vendor || window.opera;
                    const isChrome = /Chrome/.test(userAgent) && /Google Inc/.test(navigator.vendor);
                    const isSafari = /^((?!chrome|android).)*safari/i.test(userAgent);
                    const browserName = isChrome ? 'Chrome' : (isSafari ? 'Safari' : 'Other');
                    
                    // DEBUG: Log the full payment event to see what's available
                    if (cko_apple_pay_vars.debug) {
                        console.log('[Apple Pay Express] Browser:', browserName, userAgent);
                        console.log('[Apple Pay Express] Full payment event:', event);
                        console.log('[Apple Pay Express] event.payment:', event.payment);
                        console.log('[Apple Pay Express] event.payment.shippingContact:', event.payment.shippingContact);
                        console.log('[Apple Pay Express] event.payment.billingContact:', event.payment.billingContact);
                        console.log('[Apple Pay Express] event.payment.billingContact?.emailAddress:', event.payment.billingContact?.emailAddress);
                        console.log('[Apple Pay Express] event.payment.shippingContact?.emailAddress:', event.payment.shippingContact?.emailAddress);
                    }
                    
                    // Generate Checkout.com token from Apple Pay token
                    generateCheckoutToken(event.payment.token.paymentData, (outcome) => {
                        if (outcome) {
                            // Store payment data for address extraction
                            // IMPORTANT: For guest users, email and address are MANDATORY from Apple Pay
                            // For logged-in users, email comes from account, but address from Apple Pay
                            // Collect email from billingContact first, fallback to shippingContact
                            const email = event.payment.billingContact?.emailAddress || 
                                        event.payment.shippingContact?.emailAddress || 
                                        '';
                            
                            // Collect shipping contact (primary address source - MANDATORY)
                            // IMPORTANT: Apple Pay requires shipping contact for express checkout
                            // If shippingContact is not available, use billingContact as fallback
                            const shippingContact = event.payment.shippingContact || event.payment.billingContact;
                            
                            // Collect billing contact if different from shipping
                            // If billingContact is not available, use shippingContact as fallback
                            const billingContact = event.payment.billingContact || event.payment.shippingContact;
                            
                            // DEBUG: Log what we're collecting with browser info
                            if (cko_apple_pay_vars.debug) {
                                console.log('[Apple Pay Express] Browser:', browserName);
                                console.log('[Apple Pay Express] Collected email:', email);
                                console.log('[Apple Pay Express] Email source:', email ? 
                                    (event.payment.billingContact?.emailAddress ? 'billingContact' : 'shippingContact') : 'NONE');
                                console.log('[Apple Pay Express] Collected shippingContact:', shippingContact);
                                console.log('[Apple Pay Express] Collected billingContact:', billingContact);
                            }
                            
                            // Validate that we have address data
                            if (!shippingContact && !billingContact) {
                                console.error('[Apple Pay Express] ERROR: No shipping or billing contact available!');
                                console.error('[Apple Pay Express] event.payment:', event.payment);
                                session.completePayment(ApplePaySession.STATUS_FAILURE);
                                showError('Address information is required but not provided by Apple Pay. Please try again.');
                                return;
                            }
                            
                            // For guest users, email is mandatory - warn if missing (especially for Chrome)
                            // Note: We can't check if user is logged in from JavaScript, so we'll validate on server side
                            if (!email && browserName === 'Chrome') {
                                console.warn('[Apple Pay Express] WARNING: No email found in Apple Pay data for Chrome browser!');
                                console.warn('[Apple Pay Express] event.payment.billingContact:', event.payment.billingContact);
                                console.warn('[Apple Pay Express] event.payment.shippingContact:', event.payment.shippingContact);
                                console.warn('[Apple Pay Express] This may cause issues for guest users.');
                            }
                            
                            const paymentData = {
                                email: email,
                                shippingContact: shippingContact,
                                billingContact: billingContact
                            };
                            
                            if (cko_apple_pay_vars.debug) {
                                console.log('[Apple Pay Express] Browser:', browserName);
                                console.log('[Apple Pay Express] Payment data to send:', paymentData);
                                console.log('[Apple Pay Express] Email in paymentData:', paymentData.email);
                                console.log('[Apple Pay Express] Email source check:', {
                                    'billingContact.emailAddress': event.payment.billingContact?.emailAddress,
                                    'shippingContact.emailAddress': event.payment.shippingContact?.emailAddress,
                                    'final_email': email
                                });
                            }
                            
                            // Process payment with token
                            this.processPayment(outcome, paymentData, loadingContainer, context, productId);
                            
                            session.completePayment(ApplePaySession.STATUS_SUCCESS);
                        } else {
                            session.completePayment(ApplePaySession.STATUS_FAILURE);
                            jQuery('body').removeClass('apple-pay-processing');
                            if (context === 'product') {
                                this.initProductPageButton();
                            } else if (context === 'cart') {
                                this.initCartPageButton();
                            } else if (context === 'shop' && productId) {
                                this.initShopPageButtons();
                            }
                            showError('Failed to generate payment token. Please try again.');
                        }
                    });
                };

                // Handle cancel
                session.oncancel = () => {
                    clearTimeout(timeoutFallback);
                    jQuery('body').removeClass('apple-pay-processing');
                    if (context === 'product') {
                        this.initProductPageButton();
                    } else if (context === 'cart') {
                        this.initCartPageButton();
                    } else if (context === 'shop' && productId) {
                        this.initShopPageButtons();
                    }
                };

                // Begin Apple Pay session
                session.begin();
            } catch (error) {
                clearTimeout(timeoutFallback);
                jQuery('body').removeClass('apple-pay-processing');
                
                if (context === 'product') {
                    this.initProductPageButton();
                } else if (context === 'cart') {
                    this.initCartPageButton();
                } else if (context === 'shop' && productId) {
                    this.initShopPageButtons();
                }
                
                showError('Payment failed: ' + (error.message || 'Unknown error. Please try again.'));
            }
        },

        processPayment: async function(token, paymentData, loadingContainer, context, productId) {
            try {
                // Prepare payment data for backend
                const postData = {
                    action: 'express_apple_pay_order_session',
                    'cko-apple-card-token': token,
                    payment_data: JSON.stringify(paymentData),
                    woocommerce_process_checkout: cko_apple_pay_vars.woocommerce_process_checkout
                };

                const response = await jQuery.ajax({
                    url: cko_apple_pay_vars.apple_pay_order_session_url,
                    type: 'POST',
                    data: postData,
                    dataType: 'json'
                });

                if (response && response.success && response.data && response.data.redirect_url) {
                    window.location.href = response.data.redirect_url;
                } else {
                    const errorMessage = response && response.data && response.data.messages 
                        ? (Array.isArray(response.data.messages) ? response.data.messages.join(', ') : response.data.messages)
                        : 'Payment processing failed. Please try again.';
                    
                    showError(errorMessage);
                    jQuery('body').removeClass('apple-pay-processing');
                    if (context === 'product') {
                        this.initProductPageButton();
                    } else if (context === 'cart') {
                        this.initCartPageButton();
                    } else if (context === 'shop' && productId) {
                        this.initShopPageButtons();
                    }
                }
            } catch (error) {
                console.error('[Apple Pay Express] Error processing payment:', error);
                showError('Payment processing failed: ' + (error.message || 'Unknown error. Please try again.'));
                jQuery('body').removeClass('apple-pay-processing');
                if (context === 'product') {
                    this.initProductPageButton();
                } else if (context === 'cart') {
                    this.initCartPageButton();
                } else if (context === 'shop' && productId) {
                    this.initShopPageButtons();
                }
            }
        },

        updateButtonVisibility: function() {
            // Update button visibility based on form state
            jQuery(document).on('change', formSelector, onFormChange);
        },

    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            applePayButton.init();
        });
    } else {
        applePayButton.init();
    }

    // Re-initialize on cart updates
    jQuery(document.body).on('updated_cart_totals', function() {
        applePayButton.initCartPageButton();
    });

});





