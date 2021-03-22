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
}