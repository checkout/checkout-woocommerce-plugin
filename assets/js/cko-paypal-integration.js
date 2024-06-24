/* global cko_paypal_vars */

jQuery( function ( $ ) {

    jQuery( cko_paypal_vars.paypal_button_selector ).hide();

    if ( jQuery( '#payment_method_wc_checkout_com_paypal' ).is( ':checked' ) ) {
        // Disable place order button.
        jQuery( '#place_order' ).hide();
        // Show Google Pay button.
        jQuery( cko_paypal_vars.paypal_button_selector ).show();
    }

    jQuery( document ).on( 'change', "input[name='payment_method']", function ( e ) {

        if ( jQuery( this ).val() === 'wc_checkout_com_paypal' ) {
            // PayPay selected.
            jQuery( cko_paypal_vars.paypal_button_selector ).show();

            jQuery( "#place_order" ).hide();
            jQuery( '#place_order' ).prop( "disabled", true );

        } else if ( 'wc_checkout_com_apple_pay' === this.value ) {
            jQuery( cko_paypal_vars.paypal_button_selector ).hide();
            jQuery( '#ckocom_googlePay' ).hide();
            jQuery( "#place_order" ).hide();
        } else if ( 'wc_checkout_com_google_pay' === this.value ) {
            jQuery( cko_paypal_vars.paypal_button_selector ).hide();
            jQuery( '#ckocom_applePay' ).hide();
            jQuery( "#place_order" ).hide();
        } else {
            jQuery( cko_paypal_vars.paypal_button_selector ).hide();
            jQuery( '#ckocom_googlePay' ).hide();

            jQuery( '#place_order' ).prop( "disabled", false );
            jQuery( '#place_order' ).show()
        }
    } )

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

        jQuery('form.checkout .woocommerce-NoticeGroup').remove();
        jQuery('form.checkout').prepend(wcNoticeDiv);
        jQuery('.woocommerce, .form.checkout').removeClass('processing').unblock();

        jQuery('html, body').animate({
            scrollTop: (jQuery('form.checkout').offset().top - 100 )
        }, 1000 );
    };

    let cko_create_order_id = function () {
        let data = jQuery( cko_paypal_vars.paypal_button_selector ).closest('form').serialize();

        return fetch( cko_paypal_vars.create_order_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: data
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

            jQuery( '#payment #paypal-button-container' ).remove();

            if ( ! jQuery( '#payment' ).find( '#paypal-button-container' ).length ) {
                jQuery( '#payment .place-order' ).append( '<div id="paypal-button-container" style="margin-top:15px; display:none;"></div>' );
            }

            // Initialize paypal button.
            paypal.Buttons({ ...this.paypalButtonProps() }).render( cko_paypal_vars.paypal_button_selector );

            this.updateButtonVisibility();
        },

        paypalButtonProps: function () {
            let paypalButtonProps = {
                onApprove: async function (data) {

                    jQuery('.woocommerce').block({message: null, overlayCSS: {background: '#fff', opacity: 0.6}});

                    jQuery.post(cko_paypal_vars.cc_capture + "&paypal_order_id=" + data.orderID + "&woocommerce-process-checkout-nonce=" + cko_paypal_vars.woocommerce_process_checkout, function (data) {
                        if (typeof data.success !== 'undefined' && data.success !== true ) {
                            var messages = data.data.messages ? data.data.messages : data.data;

                            if ( 'string' === typeof messages || Array.isArray( messages ) ) {
                                showError( messages );
                            }
                        } else {
                            window.location.href = data.data.redirect;
                        }
                    });
                },
                onCancel: function (data, actions) {
                    jQuery('.woocommerce').unblock();
                },
                onError: function (err) {
                    console.log(err);
                    jQuery('.woocommerce').unblock();
                },
            };

            if ( cko_paypal_vars.is_cart_contains_subscription ) {
                paypalButtonProps.createBillingAgreement = function( data, actions ) {
                    return cko_create_order_id();
                };
            } else {
                paypalButtonProps.createOrder = function( data, actions ) {
                    return cko_create_order_id();
                };
            }

            return paypalButtonProps;
        },

        updateButtonVisibility: function () {
            if ( jQuery( '#payment_method_wc_checkout_com_paypal' ).is( ':checked' ) ) {
                // Disable place order button.
                jQuery( '#place_order' ).hide();
                // Show Google Pay button.
                jQuery( cko_paypal_vars.paypal_button_selector ).show();
            }
        }
    }

    paypalButton.init();

    jQuery( document.body ).on( 'updated_checkout', function () {
        paypalButton.init();
    } );

    return;

    // Initialise PayPal when page is ready.
    jQuery( document ).ready(function() {

        let paypalButtonProps = {
            onApprove: async function (data) {

                jQuery('.woocommerce').block({message: null, overlayCSS: {background: '#fff', opacity: 0.6}});

                jQuery.post(cko_paypal_vars.cc_capture + "&paypal_order_id=" + data.orderID + "&woocommerce-process-checkout-nonce=" + cko_paypal_vars.woocommerce_process_checkout, function (data) {
                    if (typeof data.success !== 'undefined' && data.success !== true ) {
                        var messages = data.data.messages ? data.data.messages : data.data;

                        if ( 'string' === typeof messages || Array.isArray( messages ) ) {
                            showError( messages );
                        }
                    } else {
                        window.location.href = data.data.redirect;
                    }
                });
            },
            onCancel: function (data, actions) {
                jQuery('.woocommerce').unblock();
            },
            onError: function (err) {
                console.log(err);
                jQuery('.woocommerce').unblock();
            },
        };

        if ( cko_paypal_vars.is_cart_contains_subscription ) {
            paypalButtonProps.createBillingAgreement = function( data, actions ) {
                return cko_create_order_id();
            };
        } else {
            paypalButtonProps.createOrder = function( data, actions ) {
                return cko_create_order_id();
            };
        }

        paypal.Buttons({ paypalButtonProps }).render( cko_paypal_vars.paypal_button_selector );
    });



});