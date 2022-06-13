<?php
include_once __DIR__."/../templates/class-wc-checkoutcom-apm-templates.php";
include_once __DIR__."/apms/class-wc-checkoutcom-ideal.php";
include_once __DIR__."/apms/class-wc-checkoutcom-alipay.php";
include_once __DIR__."/apms/class-wc-checkoutcom-qpay.php";
include_once __DIR__."/apms/class-wc-checkoutcom-boleto.php";
include_once __DIR__."/apms/class-wc-checkoutcom-sepa.php";
include_once __DIR__."/apms/class-wc-checkoutcom-knet.php";
include_once __DIR__."/apms/class-wc-checkoutcom-bancontact.php";
include_once __DIR__."/apms/class-wc-checkoutcom-eps.php";
include_once __DIR__."/apms/class-wc-checkoutcom-poli.php";
include_once __DIR__."/apms/class-wc-checkoutcom-klarna.php";
include_once __DIR__."/apms/class-wc-checkoutcom-sofort.php";
include_once __DIR__."/apms/class-wc-checkoutcom-fawry.php";
include_once __DIR__."/apms/class-wc-checkoutcom-giropay.php";
include_once __DIR__."/apms/class-wc-checkoutcom-multibanco.php";

class WC_Gateway_Checkout_Com_Alternative_Payments extends WC_Payment_Gateway
{

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

	    $order  = wc_get_order( $order_id );
	    $result = (array) WC_Checkoutcom_Api_request::refund_payment( $order_id, $order );

	    // check if result has error and return error message.
	    if ( isset( $result['error'] ) && ! empty( $result['error'] ) ) {
		    WC_Checkoutcom_Utility::wc_add_notice_self( $result['error'] );

		    return false;
	    }

	    // Set action id as woo transaction id.
	    update_post_meta( $order_id, '_transaction_id', $result['action_id'] );
	    update_post_meta( $order_id, 'cko_payment_refunded', true );

	    if ( isset( $_SESSION['cko-refund-is-less'] ) ) {
		    if ( $_SESSION['cko-refund-is-less'] ) {
			    $order->add_order_note( sprintf( __( 'Checkout.com Payment Partially refunded from Admin - Action ID : %s', 'wc_checkout_com' ), $result['action_id'] ) );

			    unset( $_SESSION['cko-refund-is-less'] );

			    return true;
		    }
	    }

	    $order->add_order_note( sprintf( __( 'Checkout.com Payment refunded from Admin - Action ID : %s', 'wc_checkout_com' ), $result['action_id'] ) );

	    // when true is returned, status is changed to refunded automatically.
	    return true;
    }
}
