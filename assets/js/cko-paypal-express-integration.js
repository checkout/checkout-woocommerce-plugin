/* global cko_paypal_vars */

jQuery( function ( $ ) {

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
            // security: wc_stripe_payment_request_params.nonce.add_to_cart,
            product_id: product_id,
            qty: $( '.quantity .qty' ).val(),
            attributes: $( '.variations_form' ).length ? getAttributes().data : []
        };

        console.log(data);

        return await $.ajax( {
            url: cko_paypal_vars.add_to_cart_url,
            type: 'POST',
            async:false,
            data: data
        } ).done( function ( response ) {
            console.log( response );
        } )
    }

    const cko_express_create_order_id = async function () {
        let addToCartSuccess = await cko_express_add_to_cart()
        console.log(addToCartSuccess );

        // Prepare add-to-cart for express checkout.

        // Get Order ID from below endpoint.


        let data = {
            express_checkout: true,
            add_to_cart: addToCartSuccess.result
        }

        console.log( data );

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
                var messages = data.data.messages ? data.data.messages : data.data;

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
        },

        paypalButtonProps: function () {
            let paypalButtonProps = {
                onApprove: async function (data) {

                    console.log(data);

                    // if ( data.orderID ) {
                    //     window.location = cko_paypal_vars.redirect;
                    // }

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
                    console.log(data);
                    jQuery('.woocommerce').unblock();
                },
                onError: function (err) {
                    console.log(err);
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