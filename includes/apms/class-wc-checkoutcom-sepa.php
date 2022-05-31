<?php

class WC_Gateway_Checkout_Com_Alternative_Payments_Sepa extends WC_Gateway_Checkout_Com_Alternative_Payments {

    const PAYMENT_METHOD = 'sepa';

    public function __construct()
    {
        $this->id                 = 'wc_checkout_com_alternative_payments_sepa';
        $this->method_title       = __( 'Checkout.com', 'wc_checkout_com' );
        $this->method_description = __( "The Checkout.com extension allows shop owners to process online payments through the <a href=\"https://www.checkout.com\">Checkout.com Payment Gateway.</a>", 'wc_checkout_com' );
        $this->title              = __( 'SEPA Direct Debit', 'wc_checkout_com' );
        $this->has_fields         = true;
        $this->supports           = array(
            'products',
            'refunds',
            'subscriptions',
            'subscription_cancellation',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_date_changes',
        );

        $this->init_form_fields();

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options', ) );
    }

    public function payment_fields()
    {
        // get available apms depending on currency
        $apm_available = WC_Checkoutcom_Utility::get_alternative_payment_methods();

        if (! in_array(self::PAYMENT_METHOD, $apm_available) ) {
            ?>
                <script>
                    jQuery('.payment_method_wc_checkout_com_alternative_payments_sepa').hide();
                </script>
            <?php
        } else {
             WC_Checkoutcom_Apm_Templates::get_sepa_details(wp_get_current_user());
            ?>
                <script>
                // Alter default place order button click
                jQuery('#place_order').click(function(e) {
                    // check if apm is selected as payment method
                    if (jQuery('#payment_method_wc_checkout_com_alternative_payments_sepa').is(':checked')) {

                        if (jQuery('#sepa-iban').val().length == 0) {
                            alert('Please enter your bank accounts iban');
                            return false;
                        }

                        if (jQuery('input[name="sepa-checkbox-input"]:checked').length == 0) {
                            alert('Please accept the mandate to continue');
                            return false;
                        }
                    }
                });
                </script>
            <?php
        }
    }

    public function process_payment( $order_id ) {
        if ( ! session_id() ) {
            session_start();
        }

        global $woocommerce;

        $order = wc_get_order( $order_id );

        // create alternative payment.
        $result = (array) WC_Checkoutcom_Api_request::create_apm_payment( $order, self::PAYMENT_METHOD );

        // check if result has error and return error message.
        if ( isset( $result['error'] ) && ! empty( $result['error'] ) ) {
            WC_Checkoutcom_Utility::wc_add_notice_self( __( $result['error'] ) );

            return;
        }

        $status  = WC_Admin_Settings::get_option( 'ckocom_order_authorised', 'on-hold' );
        $message = '';

        if ( ! empty( $result['source'] ) && self::PAYMENT_METHOD === $result['source']['type'] ) {

            $mandate = WC()->session->get( 'mandate_reference' );

            update_post_meta( $order_id, 'cko_sepa_mandate_reference', $mandate );
            update_post_meta( $order_id, 'cko_payment_authorized', true );

            $message = sprintf(
                esc_html__( 'Checkout.com - Sepa payment Action ID : %s - Sepa mandate reference : %s', 'wc_checkout_com' ),
                $result['id'],
                $mandate
            );

            WC()->session->__unset( 'mandate_reference' );

        }

        // save source id for subscription.
        if ( class_exists( 'WC_Subscriptions_Order' ) ) {

            if ( ! empty( $result['source'] ) ) {
                WC_Checkoutcom_Subscription::save_source_id( $order_id, $order, $result['source']['id'] );
            }

            $mandate_cancel = WC()->session->get( 'mandate_cancel' );
            if ( ! empty( $mandate_cancel ) ) {
                WC_Checkoutcom_Subscription::save_mandate_cancel( $order_id, $order, $mandate_cancel );
                WC()->session->__unset( 'mandate_cancel' );
            }
        }

        update_post_meta( $order_id, '_transaction_id', $result['id'] );
        update_post_meta( $order_id, '_cko_payment_id', $result['id'] );

        // add notes for the order and update status.
        $order->add_order_note( $message );
        $order->update_status( $status );

        // Reduce stock levels.
        wc_reduce_stock_levels( $order_id );

        // Remove cart.
        $woocommerce->cart->empty_cart();

        // Return thank you page.
        return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url( $order ),
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
