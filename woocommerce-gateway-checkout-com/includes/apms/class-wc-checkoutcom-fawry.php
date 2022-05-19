<?php

class WC_Gateway_Checkout_Com_Alternative_Payments_Fawry extends WC_Gateway_Checkout_Com_Alternative_Payments {

    const PAYMENT_METHOD = 'fawry';

    public function __construct()
    {
        $this->id = 'wc_checkout_com_alternative_payments_fawry';
        $this->method_title = __("Checkout.com", 'wc_checkout_com');
        $this->method_description = __("The Checkout.com extension allows shop owners to process online payments through the <a href=\"https://www.checkout.com\">Checkout.com Payment Gateway.</a>", 'wc_checkout_com');
        $this->title = __("Fawry", 'wc_checkout_com');
        $this->supports = array('products', 'refunds');
        $this->has_fields = true;

        $this->init_form_fields();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    public function payment_fields()
    {
        // get available apms depending on currency
        $apm_available = WC_Checkoutcom_Utility::get_alternative_payment_methods();
        $message = __("Pay with Fawry", 'wc_checkout_com');

        ?>
            <p style="margin-bottom: 0;"> <?php echo $message ?> </p>
        <?php

        if (! in_array(self::PAYMENT_METHOD, $apm_available) ) {
            ?>
                <script>
                    jQuery('.payment_method_wc_checkout_com_alternative_payments_fawry').hide();
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


        $status = WC_Admin_Settings::get_option('ckocom_order_authorised');
        $message = "";

        if ($result['source']['type'] == self::PAYMENT_METHOD) {
            update_post_meta($order_id, 'cko_fawry_reference_number', $result['source']['reference_number']);
            update_post_meta($order_id, 'cko_payment_authorized', true);

            // Get cko auth status configured in admin
            $message = __("Checkout.com - Fawry payment " ."</br>". " Action ID : {$result['id']} - Fawry reference number : {$result['source']['reference_number']} ", 'wc_checkout_com');

            if ($result['status'] == 'Captured') {
                $status = WC_Admin_Settings::get_option('ckocom_order_captured');
                $message = __("Checkout.com Payment Captured " ."</br>". " Action ID - {$result['id']} ", 'wc_checkout_com');
            }
        }

        update_post_meta($order_id, '_transaction_id', $result['id']);
        update_post_meta($order_id, '_cko_payment_id', $result['id']);

        // add notes for the order and update status
        $order->add_order_note($message);
        $order->update_status($status);

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
