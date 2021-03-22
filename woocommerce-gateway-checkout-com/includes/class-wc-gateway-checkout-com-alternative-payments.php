<?php
include_once __DIR__."/../templates/class-wc-checkoutcom-apm-templates.php";
include_once __DIR__."/apms/class-wc-checkoutcom-fawry.php";

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
        ?>
            <script>
                jQuery('.payment_method_wc_checkout_com_alternative_payments').hide();
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
                update_post_meta($order_id, 'cko_payment_authorized', true);
                
                // Get cko auth status configured in admin
                $message = __("Checkout.com - Fawry payment " ."</br>". " Action ID : {$result['id']} - Fawry reference number : {$result['source']['reference_number']} ", 'wc_checkout_com');

                if ($result['status'] == 'Captured') {
                    $status = WC_Admin_Settings::get_option('ckocom_order_captured');
                    $message = __("Checkout.com Payment Captured " ."</br>". " Action ID - {$result['id']} ", 'wc_checkout_com');
                }
            }

            if ($result['source']['type'] == 'sepa') {

                $mandate = WC()->session->get( 'mandate_reference');

                update_post_meta($order_id, 'cko_sepa_mandate_reference', $mandate);

                $message = __("Checkout.com - Sepa payment " ."</br>". " Action ID : {$result['id']} - Sepa mandate reference : {$mandate} ", 'wc_checkout_com');

                WC()->session->__unset( 'mandate_reference' );

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
    }
}