<?php

class WC_Gateway_Checkout_Com_Alternative_Payments_Boleto extends WC_Gateway_Checkout_Com_Alternative_Payments {

    const PAYMENT_METHOD = 'boleto';

    public function __construct()
    {
        $this->id = 'wc_checkout_com_alternative_payments_boleto';
        $this->method_title = __("Checkout.com", 'wc_checkout_com');
        $this->method_description = __("The Checkout.com extension allows shop owners to process online payments through the <a href=\"https://www.checkout.com\">Checkout.com Payment Gateway.</a>", 'wc_checkout_com');
        $this->title = __("Boleto", 'wc_checkout_com');
        $this->has_fields = true;
        $this->supports = array('products', 'refunds');

        $this->init_form_fields();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    public function payment_fields()
    {
        // get available apms depending on currency
        $apm_available = WC_Checkoutcom_Utility::get_alternative_payment_methods();

        if (! in_array(self::PAYMENT_METHOD, $apm_available) ) {
            ?>
                <script>
                    jQuery('.payment_method_wc_checkout_com_alternative_payments_boleto').hide();
                </script>
            <?php
        } else {
            WC_Checkoutcom_Apm_Templates::get_boleto_details();
            ?>
                <script>
                // Alter default place order button click
                jQuery('#place_order').click(function(e) {
                    // check if apm is selected as payment method
                    if (jQuery('#payment_method_wc_checkout_com_alternative_payments_boleto').is(':checked')) {

                        if (!jQuery("[name='name']")[0].checkValidity()) {
                            alert('Please enter your name');
                            return false;
                        }

                        if (!jQuery("[name='cpf']")[0].checkValidity()) {
                            alert('Please enter your CPF');
                            return false;
                        }
                    }
                });
                </script>
            <?php
        }
    }

    public function process_payment( $order_id )
    {
        if (!session_id()) session_start();

        global $woocommerce;

        $order = wc_get_order( $order_id );

        // create alternative payment
        $result =  (array) WC_Checkoutcom_Api_request::create_apm_payment($order, self::PAYMENT_METHOD);

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
        }
    }

	/**
	 * Process refund for the order.
	 *
	 * @param int    $order_id Order ID.
	 * @param int    $amount   Amount to refund.
	 * @param string $reason   Refund reason.
	 *
	 * @return bool
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {

		return parent::process_refund( $order_id, $amount, $reason );
	}
}
