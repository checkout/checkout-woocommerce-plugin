/**
 * Object for handling Klarna.
 *
 *
 * @type {{init: ckoKlarna.init, onPlaceOrder: ckoKlarna.onPlaceOrder, loadKlarna: ckoKlarna.loadKlarna}}
 */
var ckoKlarna = {
    init: () => {
        ckoKlarna.loadKlarna();
        ckoKlarna.onPlaceOrder();
    },

    /**
     * executes when checkout method is loading.
     */
    loadKlarna: () => {
        if (document.getElementById("klarna-client-token")?.value?.length > 0) {
            var cartInfo = jQuery("#cart-info").data("cart");

            var email = cartInfo["billing_address"]["email"];
            var family_name = cartInfo["billing_address"]["family_name"];
            var given_name = cartInfo["billing_address"]["given_name"];
            var phone = cartInfo["billing_address"]["phone"];

            if (!email) {
                email = document.getElementById("billing_email").value;
            }

            if (!family_name) {
                family_name = document.getElementById("billing_last_name").value;
            }

            if (!given_name) {
                given_name = document.getElementById("billing_first_name").value;
            }

            if (!phone) {
                phone = document.getElementById("billing_phone").value;
            }

            try {
                Klarna.Payments.init({
                    client_token: jQuery("#klarna-client-token").val(),
                });

                Klarna.Payments.load(
                    // options
                    {
                        container: "#klarna_container",
                        instance_id: "klarna-payments-instance",
                    },
                    {
                        purchase_country: cartInfo["purchase_country"],
                        purchase_currency: cartInfo["purchase_currency"],
                        locale: cartInfo["locale"],
                        order_amount: cartInfo["order_amount"],
                        // order_tax_amount:   parseInt(data.tax_amount) *100,
                        order_lines: cartInfo["order_lines"],
                        billing_address: {
                            given_name: given_name,
                            family_name: family_name,
                            email: email,
                            street_address: cartInfo["billing_address"]["street_address"],
                            postal_code: cartInfo["billing_address"]["postal_code"],
                            city: cartInfo["billing_address"]["city"],
                            region: cartInfo["billing_address"]["city"],
                            phone: phone,
                            country: cartInfo["billing_address"]["country"],
                        },
                    },
                    // callback
                    function (response) {
                        console.log(response);
                    }
                );
            } catch (e) {
                // Handle error. The load~callback will have been called
                // with "{ show_form: false }" at this point.
                console.log(e);
            }
        }
    },

    /**
     * executes when order now button is clicked
     * sets klarna authorizatio token
     */
    onPlaceOrder: () => {
        /**
         * executes when order now button is clicked
         * sets klarna authorizatio token
         */
        jQuery("#place_order").click(function (e) {

            // check if apm is selected as payment method
            if ( jQuery("#payment_method_wc_checkout_com_alternative_payments_klarna").is( ":checked" ) ) {
                // Klarna
                // check if token value not empty
                if (document.getElementById("cko-klarna-token").value.length > 0) {
                    return true;
                }

                // prevent default click
                e.preventDefault();

                // create token and trigger place order button
                try {
                    Klarna.Payments.authorize(
                        {
                            // Same as instance_id set in Klarna.Payments.load().
                            instance_id: "klarna-payments-instance",
                        },
                        // callback
                        function (response) {
                            if (response.approved) {
                                document.getElementById("cko-klarna-token").value =
                                    response.authorization_token;
                                jQuery("#place_order").trigger("click");
                            }
                        }
                    );
                } catch (e) {
                    // Handle error. The authorize~callback will have been called
                    // with "{ show_form: false, approved: false }" at this point.
                    console.log(e);
                }
            }
        });
    },
};

ckoKlarna.init()
