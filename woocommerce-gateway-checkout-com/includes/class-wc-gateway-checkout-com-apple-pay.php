<?php

include_once('/settings/class-wc-checkoutcom-cards-settings.php');

class WC_Gateway_Checkout_Com_Apple_Pay extends WC_Payment_Gateway
{
    /**
     * WC_Gateway_Checkout_Com_Apple_Pay constructor.
     */
    public function __construct()
    {
        $this->id                   = 'wc_checkout_com_apple_pay';
        $this->method_title         = __("Checkout.com", 'checkout-com-apple-pay');
        $this->method_description   = __("The Checkout.com extension allows shop owners to process online payments through the <a href=\"https://www.checkout.com\">Checkout.com Payment Gateway.</a>", 'checkout-com-apple-pay');
        $this->title                = __("Apple Pay", 'checkout-com-apple-pay');
        $this->has_fields = true;
        $this->supports = array( 'products' );

        $this->init_form_fields();
        $this->init_settings();

        // Turn these settings into variables we can use
        foreach ( $this->settings as $setting_key => $value ) {
            $this->$setting_key = $value;
        }

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    /**
     * Show module configuration in backend
     *
     * @return string|void
     */
    public function init_form_fields()
    {
        $this->form_fields = WC_Checkoutcom_Cards_Settings::apple_settings();
        $this->form_fields = array_merge( $this->form_fields, array(
            'screen_button' => array(
                'id'    => 'screen_button',
                'type'  => 'screen_button',
                'title' => __( 'Other Settings', 'configuration_setting' ),
            )
        ));
    }

    /**
     * @param $key
     * @param $value
     */
    public function generate_screen_button_html( $key, $value )
    {
        WC_Checkoutcom_Admin::generate_links($key, $value);
    }

}