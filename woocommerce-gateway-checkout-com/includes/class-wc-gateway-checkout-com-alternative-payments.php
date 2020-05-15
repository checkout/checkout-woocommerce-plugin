<?php
include_once __DIR__."/../templates/class-wc-checkoutcom-apm-templates.php";

class WC_Gateway_Checkout_Com_Alternative_Payments extends WC_Payment_Gateway
{
    /**
     * WC_Gateway_Checkout_Com_Google_Pay constructor.
     */
    public function __construct()
    {
        $this->id = 'wc_checkout_com_alternative_payments';
        $this->method_title = __("Checkout.com", 'wc_checkout_com');
        $this->method_description = __("The Checkout.com extension allows shop owners to process online payments through the <a href=\"https://www.checkout.com\">Checkout.com Payment Gateway.</a>", 'wc_checkout_com');
        $this->title = __("Alternative Payment", 'wc_checkout_com');

        $this->has_fields = true;
        $this->supports = array('products', 'refunds');

        $this->init_form_fields();
        $this->init_settings();

        // Turn these settings into variables we can use
        foreach ($this->settings as $setting_key => $value) {
            $this->$setting_key = $value;
        }

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        // Generate token
        add_action('woocommerce_api_wc_checkoutcom_googlepay_token', array($this, 'generate_token'));
    }

    /**
     * Show module configuration in backend
     *
     * @return string|void
     */
    public function init_form_fields()
    {
        $this->form_fields = WC_Checkoutcom_Cards_Settings::apm_settings();
        $this->form_fields = array_merge($this->form_fields, array(
            'screen_button' => array(
                'id' => 'screen_button',
                'type' => 'screen_button',
                'title' => __('Other Settings', 'wc_checkout_com'),
            )
        ));
    }

    /**
     * @param $key
     * @param $value
     */
    public function generate_screen_button_html($key, $value)
    {
        WC_Checkoutcom_Admin::generate_links($key, $value);
    }

    public function payment_fields()
    {
        $currencyCode = get_woocommerce_currency();
        $apm = $this->get_option( 'ckocom_apms_selector' );
        // Get apm base on currency
        $apm_available = WC_Checkoutcom_Utility::get_alternative_payment_methods(
            $currencyCode,
            $apm,
            WC()->customer->get_billing_country()
        );
        $plugin_url = plugins_url('/assets/images/',__DIR__);
        $cartInfo = WC_Checkoutcom_Api_request::get_cart_info();
        $count_apm = count($apm_available);

        ?>
        <input type="hidden" id="cko-ideal-bic" name="cko-ideal-bic" value="" />
        <input type="hidden" id="cko-klarna-token" name="cko-klarna-token" value="" />
        <input type="hidden" id="cko-giropay-bank" name="cko-giropay-bank" value="" />
        <input type="hidden" id="cko-eps-bank" name="cko-eps-bank" value="" />
        <?php

        foreach ($apm_available as $index => $value){
            if($count_apm > 1) {
                ?>
                <div>
                    <input type="radio" id="cko-apms-<?php echo $value; ?>" value="<?php echo $value; ?>" name="cko-apm"/>
                    <img src="<?php echo $plugin_url . $value . '.svg'; ?>"/>
                    <label for="cko-apms-<?php echo $value; ?>">
                        <?php
                        switch ($value) {
                            case "ideal":
                                $value = "iDEAL";
                                break;
                            case "sepa":
                                $value = "SEPA Direct Debit";
                                break;
                            case "eps":
                                $value = "EPS";
                                break;
                            case "qpay":
                                $value = "QPay";
                                break;
                            default:
                                $value = ucfirst($value);
                        }
                        echo $value;
                        if($value == 'Klarna'){
                            $klarna_session = WC_Checkoutcom_Api_request::klarna_session();
                            $client_token = $klarna_session->client_token;
                            $payment_method_categories = $klarna_session->payment_method_categories;
                            $klarna = WC_Checkoutcom_Apm_Templates::get_klarna($client_token, $payment_method_categories);

                            ?> <div class="klarna-details"></div><div id="klarna_container"></div><?php

                        } elseif ($value == 'iDEAL') {
                            $ideal_bank = WC_Checkoutcom_Apm_Templates::get_ideal_bank();
                        } elseif ($value == 'Giropay') {
                            $giropay_bank = WC_Checkoutcom_Apm_Templates::get_giropay_bank();
                        }  elseif ($value == 'Boleto') {
                            $boleto = WC_Checkoutcom_Apm_Templates::get_boleto_details();
                        }  elseif ($value == 'SEPA Direct Debit') {
                            $sepa = WC_Checkoutcom_Apm_Templates::get_sepa_details(wp_get_current_user());
                        }
                        ?>
                    </label>
                </div>
                <?php
            } elseif (count($apm_available) == 1) {
                ?>
                <div class="cko-single-apm">
                    <input type="radio" id="cko-apms-<?php echo $value; ?>" value="<?php echo $value; ?>" name="cko-apm" checked />
                    <img src="<?php echo $plugin_url . $value . '.svg'; ?>"/>
                    <label for="cko-apms-<?php echo $value; ?>">
                    <?php
                    switch ($value) {
                        case "ideal":
                            $value = "iDEAL";
                            break;
                        case "sepa":
                            $value = "SEPA Direct Debit";
                            break;
                        case "eps":
                            $value = "EPS";
                            break;
                        case "qpay":
                            $value = "QPay";
                            break;
                        default:
                            $value = ucfirst($value);
                    }
                    echo $value;

                    if ($value == 'Klarna') {
                        $klarna_session = WC_Checkoutcom_Api_request::klarna_session();
                        $client_token = $klarna_session->client_token;
                        $payment_method_categories = $klarna_session->payment_method_categories;
                        $klarna = WC_Checkoutcom_Apm_Templates::get_klarna($client_token, $payment_method_categories);
                        ?> <div class="klarna-details"></div><div id="klarna_container"></div><?php
                    } elseif ($value == 'iDEAL') {
                        $ideal_bank = WC_Checkoutcom_Apm_Templates::get_ideal_bank();
                    } elseif ($value == 'Boleto') {
                        $boleto = WC_Checkoutcom_Apm_Templates::get_boleto_details();
                    } elseif ($value == 'SEPA Direct Debit') {
                        $sepa = WC_Checkoutcom_Apm_Templates::get_sepa_details(wp_get_current_user());
                    }
                ?></div><?php
            }
        }
        ?>
        <script>
            var count_apm = '<?= $count_apm ?>';

            // check if apm count >1 and hide/show apm payment method
            if(count_apm == 0){
                jQuery('.payment_method_wc_checkout_com_alternative_payments').hide();
            } else {
                jQuery('.payment_method_wc_checkout_com_alternative_payments').show();
            }

            if(jQuery("[name='cko-apm']").length == 0) {
                if(jQuery('.klarna_widgets').length > 0) {
                    jQuery('.klarna_widgets').show();
                }
            }

            // hide/show individual
            jQuery("[name='cko-apm']").change(function() {
                if (jQuery('#cko-apms-ideal').length > 0 && jQuery("[name='cko-apm']:checked").val() == 'ideal') {
                    // Show Ideal form
                    jQuery('.ideal-bank-info').show();
                    jQuery('.klarna_widgets').hide();
                    jQuery('#klarna_container').hide();
                    jQuery('.giropay-bank-info').hide();
                    jQuery('.boleto-content').hide();
                    jQuery('.sepa-content').hide();

                } else if (jQuery('#cko-apms-klarna').length > 0 && jQuery("[name='cko-apm']:checked").val() == 'klarna') {
                    jQuery('.ideal-bank-info').hide();
                    jQuery('.klarna_widgets').show();
                    jQuery('#klarna_container').show();
                    jQuery('.giropay-bank-info').hide();
                    jQuery('.boleto-content').hide();
                    jQuery('.sepa-content').hide();

                } else if (jQuery('#cko-apms-giropay').length > 0 && jQuery("[name='cko-apm']:checked").val() == 'giropay'){
                    jQuery('.giropay-bank-info').show();
                    jQuery('.ideal-bank-info').hide();
                    jQuery('.klarna_widgets').hide();
                    jQuery('#klarna_container').hide();
                    jQuery('.boleto-content').hide();
                    jQuery('.sepa-content').hide();

                } else if(jQuery('#cko-apms-boleto').length > 0 && jQuery("[name='cko-apm']:checked").val() == 'boleto') {
                    jQuery('.ideal-bank-info').hide();
                    jQuery('.klarna_widgets').hide();
                    jQuery('#klarna_container').hide()
                    jQuery('.giropay-bank-info').hide();
                    jQuery('.boleto-content').show();
                    jQuery('.sepa-content').hide();

                } else if(jQuery('#cko-apms-eps').length > 0 && jQuery("[name='cko-apm']:checked").val() == 'eps') {
                    jQuery('.ideal-bank-info').hide();
                    jQuery('.klarna_widgets').hide();
                    jQuery('#klarna_container').hide()
                    jQuery('.giropay-bank-info').hide();
                    jQuery('.boleto-content').hide();
                    jQuery('.sepa-content').hide();

                } else if(jQuery('#cko-apms-sepa').length > 0 && jQuery("[name='cko-apm']:checked").val() == 'sepa') {
                    jQuery('.ideal-bank-info').hide();
                    jQuery('.klarna_widgets').hide();
                    jQuery('#klarna_container').hide()
                    jQuery('.giropay-bank-info').hide();
                    jQuery('.boleto-content').hide();
                    jQuery('.sepa-content').show();

                } else {
                    jQuery('.ideal-bank-info').hide();
                    jQuery('.klarna_widgets').hide();
                    jQuery('#klarna_container').hide()
                    jQuery('.giropay-bank-info').hide();
                    jQuery('.boleto-content').hide();
                    jQuery('.sepa-content').hide();
                }
            });

            // Alter default place order button click
            jQuery('#place_order').click(function (e) {
                // check if apm is selected as payment method
                if (jQuery('#payment_method_wc_checkout_com_alternative_payments').is(':checked')) {

                    // Boleto
                    if (jQuery('#cko-apms-boleto').length > 0 && jQuery('#cko-apms-boleto').is(':checked')) {
                        if (!jQuery("[name='name']")[0].checkValidity()) {
                            alert('Please enter your name');
                            return false;
                        }

                        if (!jQuery("[name='cpf']")[0].checkValidity()) {
                            alert('Please enter your CPF');
                            return false;
                        }

                        if (!jQuery("[name='birthDate']")[0].checkValidity()) {
                            alert('Please enter your birthdate in the correct format.');
                            return false;
                        }
                    }

                    // Sepa
                    if (jQuery('#cko-apms-sepa').length > 0 && jQuery('#cko-apms-sepa').is(':checked')) {
                        if(jQuery('#sepa-iban').val().length == 0){
                            alert('Please enter your bank accounts iban');
                            return false;
                        }

                        if(jQuery('#sepa-bic').val().length == 0){
                            alert('Please enter your BIC code');
                            return false;
                        }

                        if (jQuery('input[name="sepa-checkbox-input"]:checked').length == 0) {
                            alert('Please accept the mandate to continue');
                            return false;
                        }
                    }

                    // Klarna
                    if (jQuery('.klarna_widgets').length > 0 && jQuery('.klarna_widgets').find('input[type="radio"]').is(':checked')) {

                        // check if token value not empty
                        if (document.getElementById('cko-klarna-token').value.length > 0) {
                            return true;
                        }

                        // prevent default click
                        e.preventDefault();

                        // create token and trigger place order button
                        try {
                            Klarna.Payments.authorize(
                                // options
                                {
                                    // Same as instance_id set in Klarna.Payments.load().
                                    instance_id: "klarna-payments-instance"
                                },
                                // callback
                                function (response) {
                                    if(response.approved){
                                        document.getElementById('cko-klarna-token').value = response.authorization_token;
                                        jQuery('#place_order').trigger('click');
                                    }
                                }
                            );
                        } catch (e) {
                            // Handle error. The authorize~callback will have been called
                            // with "{ show_form: false, approved: false }" at this point.
                            console.log(e);
                        }
                    }
                }
            });

            // load klarna widgets if selected
            if(jQuery('.klarna_widgets').length > 0) {

                setTimeout(function(){
                    if(jQuery('.klarna_widgets').find('input[type="radio"]').is(':checked')){ console.log('here now');
                        jQuery('.klarna_widgets').find('input[type="radio"]').prop('checked', false);
                    }
                },300)

                jQuery('.klarna_widgets').find('input[type="radio"]').on('click',function(event){
                    var cartInfo = <?php echo json_encode(WC_Checkoutcom_Api_request::get_cart_info()); ?>;

                    var email  = cartInfo['billing_address']['email'];
                    var family_name = cartInfo['billing_address']['family_name'];
                    var given_name = cartInfo['billing_address']['given_name'];
                    var phone = cartInfo['billing_address']['phone'];

                    if(!email){
                        email = document.getElementById('billing_email').value;
                    }

                    if(!family_name){
                        family_name = document.getElementById('billing_last_name').value;
                    }

                    if(!given_name){
                        given_name = document.getElementById('billing_first_name').value;
                    }

                    if(!phone){
                        phone = document.getElementById('billing_phone').value;
                    }

                    console.log(cartInfo);
                    try {
                        Klarna.Payments.init(
                            {
                                client_token: "<?php echo $client_token; ?>"
                            }
                        );

                        Klarna.Payments.load(
                            // options
                            {
                                container: "#klarna_container",
                                payment_method_categories: [event.target.id],
                                instance_id: "klarna-payments-instance"
                            },
                            {
                                purchase_country:   cartInfo['purchase_country'],
                                purchase_currency:  cartInfo['purchase_currency'],
                                locale:             cartInfo['locale'],
                                order_amount:       cartInfo['order_amount'],
                                // order_tax_amount:   parseInt(data.tax_amount) *100,
                                order_lines:        cartInfo['order_lines'],
                                billing_address:    {
                                    given_name:     given_name,
                                    family_name:    family_name,
                                    email:          email,
                                    street_address: cartInfo['billing_address']['street_address'],
                                    postal_code:    cartInfo['billing_address']['postal_code'],
                                    city:          cartInfo['billing_address']['city'],
                                    region:         cartInfo['billing_address']['city'],
                                    phone:          phone,
                                    country:        cartInfo['billing_address']['country'],
                                }
                            },
                            // callback
                            function (response) {
                                // ...
                                console.log(response);
                            }
                        );
                    } catch (e) {
                        // Handle error. The load~callback will have been called
                        // with "{ show_form: false }" at this point.
                        console.log(e);
                    }
                });
            }
        </script>
        <?php
    }

    public function process_payment( $order_id )
    {
        if (!session_id()) session_start();

        global $woocommerce;

        $order = wc_get_order( $order_id );

        // check if no apm is selected
        if(! sanitize_text_field($_POST['cko-apm'])){
            WC_Checkoutcom_Utility::wc_add_notice_self(__('Please select an alternative payment method.', 'wc_checkout_com'), 'error');
            return;
        }

        // create alternative payment
        $result =  (array) WC_Checkoutcom_Api_request::create_apm_payment($order, $arg = null);

        // check if result has error and return error message
        if (isset($result['error']) && !empty($result['error'])) {
            WC_Checkoutcom_Utility::wc_add_notice_self(__($result['error']), 'error');
            return;
        }

        // redirect to apm if redirection url is available
        if (isset($result['apm_redirection']) &&!empty($result['apm_redirection'])) {

            return array(
                'result'        => 'success',
                'redirect'      => $result['apm_redirection'],
            );
        } else {
            $status = WC_Admin_Settings::get_option('ckocom_order_authorised');
            $message = "";

            if ($result['source']['type'] == 'fawry') {
                update_post_meta($order_id, 'cko_fawry_reference_number', $result['source']['reference_number']);

                // Get cko auth status configured in admin
                $message = __("Checkout.com - Fawry payment (Transaction ID : {$result['id']} - Fawry reference number : {$result['source']['reference_number']}) ", 'wc_checkout_com');

                if ($result['status'] == 'Captured') {
                    $status = WC_Admin_Settings::get_option('ckocom_order_captured');
                    $message = __("Checkout.com Payment Captured (Transaction ID - {$result['id']}) ", 'wc_checkout_com');
                }
            }

            if ($result['source']['type'] == 'sepa') {

                $mandate = WC()->session->get( 'mandate_reference');

                update_post_meta($order_id, 'cko_sepa_mandate_reference', $mandate);

                $message = __("Checkout.com - Sepa payment (Transaction ID : {$result['id']} - Sepa mandate reference : {$mandate}) ", 'wc_checkout_com');

                WC()->session->__unset( 'mandate_reference' );

            }

            update_post_meta($order_id, '_transaction_id', $result['id']);
            update_post_meta($order_id, '_cko_payment_id', $result['id']);

            // Update order status on woo backend
            $order->update_status($status,$message);

            // Reduce stock levels
            wc_reduce_stock_levels( $order_id );

            // Remove cart
            $woocommerce->cart->empty_cart();

            // Return thank you page
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url( $order )
            );
        }
    }
}