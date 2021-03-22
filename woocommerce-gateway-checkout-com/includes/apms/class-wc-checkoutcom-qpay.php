<?php

class WC_Gateway_Checkout_Com_Alternative_Payments_Qpay extends WC_Gateway_Checkout_Com_Alternative_Payments {

    public function __construct()
    {
        $this->id = 'wc_checkout_com_alternative_payments_qpay';
        $this->title = __("QPay", 'wc_checkout_com');
        $this->has_fields = true;
        $this->supports = array('products', 'refunds');

        $this->init_form_fields();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    public function payment_fields()
    {   
        // get available apms depending on currency
        $apm_available = WC_Checkoutcom_Utility::get_alternative_payment_methods();
        $message = __("Pay with QPay. You will be redirected upon place order", 'wc_checkout_com')

        ?>
            <p style="margin-bottom: 0;"> <?php echo $message ?> </p>
            <input type="hidden" id="cko-apm" name="cko-apm" value="qpay">
        <?php

        if (! in_array("qpay", $apm_available) ) {
            ?>
                <script>
                    jQuery('.payment_method_wc_checkout_com_alternative_payments_qpay').hide();
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
        }
    }
}