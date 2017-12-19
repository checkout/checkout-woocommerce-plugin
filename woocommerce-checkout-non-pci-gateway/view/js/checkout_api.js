jQuery(document).ready(function() {
    var _cko = window.CKOWoocommerce = window.CKOWoocommerce || {};
    setTimeout(function(){
        if (window.hasOwnProperty('CheckoutApiJsConfig') && typeof Checkout != 'undefined') {
            window.CheckoutApiJsConfig.styling.overlayOpacity = parseFloat(window.CheckoutApiJsConfig.styling.overlayOpacity);
            Checkout.render(window.CheckoutApiJsConfig);

            if (Checkout.isMobile()){
                document.getElementById('cko-mobile-redirectUrl').value = Checkout.getRedirectionUrl();
            }

            delete window.CheckoutApiJsConfig;
        }

        jQuery(document).ready(function() {

            createBindings();

             if(jQuery('#createaccount').length === 1){
                var checkbox = document.querySelector("input[name=createaccount]");
                checkbox.addEventListener( 'change', function() {
                    if(this.checked) {
                        // Checkbox is checked..
                        jQuery('#save-card-check').show();
                    } else {
                        // Checkbox is not checked..
                        jQuery('#save-card-check').hide();
                    }
                });

                if( jQuery('#createaccount:checked').length === 1){
                    jQuery('#save-card-check').show();
                }
            }

            function createBindings() { console.log('createBindings');
                document.getElementById('cko-is-mobile').value = false;
                var integrationType = document.getElementById('cko-hosted-url').value;

                if (!Checkout.isMobile() && integrationType =="checkoutjs"){

                    jQuery('form.checkout').bind('#place_order, checkout_place_order', function (e) {
                        if(jQuery('input[name=payment_method]:checked').val() != 'woocommerce_checkout_non_pci' || document.getElementById('cko-card-token').value.length > 0 ){
                            
                            return true;
                        }

                        return false;
                    });

                    jQuery('form#order_review').bind('#place_order, checkout_place_order', function (e) {
                        if(jQuery('input[name=payment_method]:checked').val() != 'woocommerce_checkout_non_pci' || document.getElementById('cko-card-token').value.length > 0){
                            return true;
                        }

                        return false;
                    });

                    jQuery('#place_order').on('click', function(e){

                       
                        if(jQuery('input[name=payment_method]:checked').val() != 'woocommerce_checkout_non_pci' || document.getElementById('cko-card-token').value.length > 0){
                            return true;
                        }

                         if(jQuery('#payment_method_woocommerce_checkout_non_pci').is(':checked')){
                            if(jQuery('.checkout-saved-card-radio').length >0){
                                if(jQuery('.checkout-new-card-radio').is(':checked') == false && jQuery('.checkout-saved-card-radio').is(':checked') == false ){
                                    e.preventDefault();
                                    var result = {error: false, messages: []};
                                    result.error = true;
                                    result.messages.push({target: false, message : 'Please select a payment method.'});

                                    jQuery('.woocommerce-error, .woocommerce-message').remove();

                                    jQuery.each(result.messages, function(index, value) {
                                        jQuery('form.checkout').prepend('<div class="woocommerce-error">' + value.message + '</div>');
                                    });

                                    jQuery('html, body').animate({
                                        scrollTop: (jQuery('form.checkout').offset().top - 100 )
                                    }, 1000 );

                                    jQuery(document.body).trigger('checkout_error');

                                    return false;
                                }
                            }
                        }


                        if (isValidFormField(window.checkoutFields)) {
                            Checkout.setCustomerEmail(document.getElementById('billing_email').value);
                            Checkout.setCustomerName(document.getElementById('billing_first_name').value + ' ' + document.getElementById('billing_last_name').value);

                            if (Checkout.isMobile()) {
                                jQuery('#checkout-api-js-hover').show();

                            }

                            Checkout.open();
                        }

                        return false;
                    });                   
                } else{
                    document.getElementById('cko-is-mobile').value = true;
                }
            }

            // Make createBindings function public so it can be called when user
            // decides to add a new card again after choosing to use saved card
            _cko.createBindings = createBindings;

            function isValidFormField(fieldList) {
                var result = {error: false, messages: []};
                var fields = JSON.parse(fieldList);

                if(jQuery('#terms').length === 1 && jQuery('#terms:checked').length === 0){ 
                    result.error = true;
                    result.messages.push({target: 'terms', message : 'You must accept our Terms & Conditions.'});
                }
                
                if (fields) {
                    jQuery.each(fields, function(group, groupValue) {
                        if (group === 'shipping' && jQuery('#ship-to-different-address-checkbox:checked').length === 0) {
                            return true;
                        }

                        jQuery.each(groupValue, function(name, value ) {
                            if (!value.hasOwnProperty('required')) {
                                return true;
                            }

                            if (name === 'account_password' && jQuery('#createaccount:checked').length === 0) {
                                return true;
                            }

                            var inputValue = jQuery('#' + name).length > 0 && jQuery('#' + name).val().length > 0 ? jQuery('#' + name).val() : '';

                            if (value.required && jQuery('#' + name).length > 0 && jQuery('#' + name).val().length === 0) {
                                result.error = true;
                                result.messages.push({target: name, message : value.label + ' is a required field.'});
                            }

                            if (value.hasOwnProperty('type')) {
                                switch (value.type) {
                                    case 'email':
                                        var reg     = /^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/;
                                        var correct = reg.test(inputValue);

                                        if (!correct) {
                                            result.error = true;
                                            result.messages.push({target: name, message : value.label + ' is not correct email.'});
                                        }

                                        break;
                                    case 'tel':
                                        var tel         = inputValue;
                                        var filtered    = tel.replace(/[\s\#0-9_\-\+\(\)]/g, '').trim();

                                        if (filtered.length > 0) {
                                            result.error = true;
                                            result.messages.push({target: name, message : value.label + ' is not correct phone number.'});
                                        }

                                        break;
                                }
                            }
                        });
                    });
                } else {
                    result.error = true;
                    result.messages.push({target: false, message : 'Empty form data.'});
                }

                if (!result.error) {
                    return true;
                }

                jQuery('.woocommerce-error, .woocommerce-message').remove();

                jQuery.each(result.messages, function(index, value) {
                    jQuery('form.checkout').prepend('<div class="woocommerce-error">' + value.message + '</div>');
                });

                jQuery('html, body').animate({
                    scrollTop: (jQuery('form.checkout').offset().top - 100 )
                }, 1000 );

                jQuery(document.body).trigger('checkout_error');

                return false;
            }
        });
    }, 1000);
});