/* global cko_paypal_vars */

jQuery( function ( $ ) {

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
        } )
    }

    const cko_express_create_order_id = async function () {
        let addToCartSuccess = await cko_express_add_to_cart()

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
            return res.json();
        }).then(function (data) {
            if (typeof data.success !== 'undefined') {
                let messages = data.data.messages ? data.data.messages : data.data;

                if ( 'string' === typeof messages || Array.isArray( messages ) ) {
                    showError( messages );
                }
                return null;
            } else {
                return data.order_id;
            }
        });
    };

    const paypalButton = {
        init: function () {
            // Initialize PayPal express button.
            paypal.Buttons({ ...this.paypalButtonProps() }).render( cko_paypal_vars.paypal_button_selector );

            this.updateButtonVisibility();

            jQuery(document).on('change', formSelector, onFormChange );
        },

        paypalButtonProps: function () {
            let paypalButtonProps = {
                onApprove: async function (data) {
                    cko_paypal_vars.debug && console.log(data);

                    jQuery.post(cko_paypal_vars.paypal_order_session_url + "&paypal_order_id=" + data.orderID + "&woocommerce-process-checkout-nonce=" + cko_paypal_vars.woocommerce_process_checkout, function (data) {
                        if (typeof data.success !== 'undefined' && data.success !== true ) {
                            var messages = data.data.messages ? data.data.messages : data.data;

                            if ( 'string' === typeof messages || Array.isArray( messages ) ) {
                                showError( messages );
                            }
                        } else {
                            window.location.href = cko_paypal_vars.redirect;
                        }
                    });
                },
                onCancel: function (data, actions) {
                    cko_paypal_vars.debug && console.log(data);
                    jQuery('.woocommerce').unblock();
                },
                onError: function (err) {
                    cko_paypal_vars.debug && console.log(err);
                    jQuery('.woocommerce').unblock();
                },
            };

            if ( cko_paypal_vars.is_cart_contains_subscription ) {
                paypalButtonProps.createBillingAgreement = function( data, actions ) {
                    return cko_express_create_order_id();
                };
            } else {
                paypalButtonProps.createOrder = function( data, actions ) {
                    return cko_express_create_order_id();
                };
            }

            return paypalButtonProps;
        },

        updateButtonVisibility: function () {
            if ( jQuery( cko_paypal_vars.paypal_button_selector ) ) {
                jQuery( cko_paypal_vars.paypal_button_selector ).show();
            }
        }
    }

    paypalButton.init();
});