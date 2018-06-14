jQuery(document).ready(function() {
    var _ckoFRAMES = window.CKOConfig = window.CKOConfig || {};
    setTimeout(function(){
        
        if (window.hasOwnProperty('CheckoutApiEmbConfig') && typeof Frames != 'undefined') {
            Frames.init(window.CheckoutApiEmbConfig);
        }

        // jQuery(document).ready(function() {
        //     Frames.init(window.CheckoutApiEmbConfig);
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
            
            function createBindings() { 

                var integrationType = document.getElementById('cko-hosted-url').value;

                    jQuery('form.checkout').bind('#place_order, checkout_place_order', function (e) {
                        if(jQuery('input[name=payment_method]:checked').val() != 'woocommerce_checkout_non_pci' || document.getElementById('cko-card-token').value.length > 0 ){
                            return true;
                        }

                        return false;
                    });

                    jQuery('form#order_review').bind('#place_order, checkout_place_order', function (e) {
                        if(jQuery('input[name=payment_method]:checked').val() != 'woocommerce_checkout_non_pci' || document.getElementById('cko-card-token').value.length > 0 ){
                            return true;
                        }

                        return false;
                    });

                    jQuery('#place_order').on('click', function(e){

                        if(jQuery('input[name=payment_method]:checked').val() != 'woocommerce_checkout_non_pci' || document.getElementById('cko-card-token').value.length > 0 ){
                            return true;
                        }

                        if(jQuery('#payment_method_woocommerce_checkout_non_pci').is(':checked')){
                            if(jQuery('.checkout-alternative-payment-radio').length >0){
                                if(jQuery('input[name=woocommerce_checkout_non_pci-saved-card]:checked').val() == "ideal" 
                                    || jQuery('input[name=woocommerce_checkout_non_pci-saved-card]:checked').val() == "boleto"
                                    || jQuery('input[name=woocommerce_checkout_non_pci-saved-card]:checked').val() == "qiwi"){
                                        
                                    e.preventDefault();
                                    //Get selected LP name
                                    var selectedLpName = jQuery('input[name=woocommerce_checkout_non_pci-saved-card]:checked').val();
                                    jQuery("#lpName").text("Pay with "+selectedLpName.toUpperCase());

                                    // Open modal
                                    var modal = document.getElementById('myModal');
                                    modal.style.display = "block";

                                    if(selectedLpName == 'ideal'){
                                        jQuery('#selectIssuer').show();
                                        jQuery('#boletoInfo').hide();
                                        jQuery('#qiwiInfo').hide();
                                    } else if(selectedLpName == 'boleto'){
                                        jQuery('#selectIssuer').hide();
                                        jQuery('#boletoInfo').show();
                                        jQuery('#qiwiInfo').hide();
                                    } else if(selectedLpName == 'qiwi'){
                                        jQuery('#qiwiInfo').show();
                                        jQuery('#boletoInfo').hide();
                                        jQuery('#selectIssuer').hide();
                                    }

                                    // continue button
                                    jQuery('#mybtn').on('click', function(e) {
                                        modal.style.display = "none";

                                        if(selectedLpName == 'ideal'){
                                            var e = document.getElementById("issuer");
                                            var value = e.options[e.selectedIndex].value;
                                            var text = e.options[e.selectedIndex].text;

                                            document.getElementById('cko-lp-issuerId').value = value;
                                        } else if(selectedLpName == 'boleto'){
                                            if(document.getElementById('boletoDate').value == ""){
                                                alert('Please enter correct date');
                                                return false;
                                            } else {
                                                document.getElementById('cko-lp-boletoDate').value = document.getElementById('boletoDate').value;
                                            }

                                            if(document.getElementById('cpf').value == ""){
                                                alert('Please enter your CPF');
                                                return false;
                                            } else {
                                                document.getElementById('cko-lp-cpf').value = document.getElementById('cpf').value;
                                            }

                                            if(document.getElementById('custName').value == ""){
                                                alert('Please enter your customer name');
                                                return false;
                                            } else {
                                                document.getElementById('cko-lp-custName').value = document.getElementById('custName').value;
                                            }

                                        } else if(selectedLpName == 'qiwi'){
                                            if(document.getElementById('walletId').value == ""){
                                                alert('Please enter your Wallet Id');
                                                return false;
                                            } else {
                                                document.getElementById('cko-lp-walletId').value = document.getElementById('walletId').value;
                                            }
                                        }

                                        document.getElementById('cko-lp-lpName').value = selectedLpName;

                                        jQuery('form.checkout').unbind('#place_order, checkout_place_order');
                                        jQuery('form#order_review').unbind();
                                        jQuery('#place_order').unbind();
                                        jQuery('#place_order').trigger('click');
                                    });
                                } 
                            }

                            if(jQuery('.checkout-saved-card-radio').length >0){
                                if(jQuery('.checkout-new-card-radio').is(':checked') == false && jQuery('.checkout-saved-card-radio').is(':checked') == false &&
                                    jQuery('.checkout-alternative-payment-radio').is(':checked') == false){
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
                            if (Frames.isCardValid()){
                                Frames.submitCard();
                            } 
                            
                        }

                        return true;
                    });                   
                
            }

            // Make createBindings function public so it can be called when user
            // decides to add a new card again after choosing to use saved card
            _ckoFRAMES.createBindings = createBindings;

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
        // });
    }, 3000);
});