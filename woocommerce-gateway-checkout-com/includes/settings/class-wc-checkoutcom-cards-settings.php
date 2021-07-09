<?php

/**
 * Class WC_Checkoutcom_Cards_Settings
 */
class WC_Checkoutcom_Cards_Settings
{
    /**
     * CKO admin core settings fields
     * @return mixed
     */
    public static function core_settings()
    {
        $settings = array(
            'core_setting' => array(
                'title'       => __( 'Core settings', 'checkoutcom-cards-settings' ),
                'type'        => 'title',
                'description' => '',
            ),
            'enabled' => array(
                'id'      => 'enable',
                'title'   => __( 'Enable/Disable', 'checkoutcom-cards-settings' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable Checkout.com cards payment', 'checkoutcom-cards-settings' ),
                'description' => __( 'This enables Checkout.com. cards payment', 'checkoutcom-cards-settings' ),
                'desc_tip'    => true,
                'default'     => 'yes',
            ),
            'ckocom_environment' => array(
                'title' => __('Environment', 'checkoutcom-cards-settings'),
                'type' => 'select',
                'description' => __('When going to production, make sure to set this to Live', 'checkoutcom-cards-settings'),
                'desc_tip' => true,
                'options'     => array(
                    'sandbox'   => __('SandBox', 'checkoutcom-cards-settings'),
                    'live'      => __('Live', 'checkoutcom-cards-settings')
                ),
                'default' => 'sandbox'
            ),
            'title' => array(
                'title' => __('Payment Option Title', 'checkoutcom-cards-settings'),
                'type' => 'text',
                'label' => __('Pay by Card with Checkout.com', 'checkoutcom-cards-settings'),
                'description' => __('Title that will be displayed on the checkout page', 'checkoutcom-cards-settings'),
                'desc_tip' => true,
                'default'     => 'Pay by Card with Checkout.com',
            ),
            'ckocom_sk' => array(
                'title' => __('Secret Key', 'checkoutcom-cards-settings'),
                'type' => 'text',
                'description' => __('You can '.'<a href="https://docs.checkout.com/docs/update-your-hub-settings#section-manage-the-api-keys">find your secret key </a>'. 'in the Checkout.com Hub', 'checkoutcom-cards-settings'),
                'placeholder' => 'sk_xxx'
            ),
            'ckocom_pk' => array(
                'title' => __('Public Key', 'checkoutcom-cards-settings'),
                'type' => 'text',
                'description' => __('You can '.'<a href="https://docs.checkout.com/docs/update-your-hub-settings#section-manage-the-api-keys">find your public key </a>'. 'in the Checkout.com Hub', 'checkoutcom-cards-settings'),
                'placeholder' => 'pk_xxx'
            )
        );

        return apply_filters( 'wc_checkout_com_cards', $settings );
    }

    /**
     * CKO admin card setting fields
     * @return mixed|void
     */
    public static function cards_settings()
    {
        /**
         * script to hide and show fields
         */
        wc_enqueue_js( "
            jQuery( function(){
                if(jQuery('#ckocom_card_autocap').val() == 0){
                    $( ckocom_card_cap_delay ).closest( 'tr' ).hide();
                }
                
                jQuery('#ckocom_card_autocap').on('change', function() {
                    if(this.value == 0){
                        $( ckocom_card_cap_delay ).closest( 'tr' ).hide();
                    } else {
                        $( ckocom_card_cap_delay ).closest( 'tr' ).show();
                    }
                })
                
                if(jQuery('#ckocom_card_threed').val() == 0){
                    $( ckocom_card_notheed ).closest( 'tr' ).hide();
                }
                
                jQuery('#ckocom_card_threed').on('change', function() {
                    if(this.value == 0){
                        $( ckocom_card_notheed ).closest( 'tr' ).hide();
                    } else {
                        $( ckocom_card_notheed ).closest( 'tr' ).show();
                    }
                })
                
                if(jQuery('#ckocom_card_saved').val() == 0){
                    $( ckocom_card_require_cvv ).closest( 'tr' ).hide();
                }
                
                jQuery('#ckocom_card_saved').on('change', function() {
                    if(this.value == 0){
                        $( ckocom_card_require_cvv ).closest( 'tr' ).hide();
                    } else {
                        $( ckocom_card_require_cvv ).closest( 'tr' ).show();
                    }
                })
                
                if(jQuery('#ckocom_card_desctiptor').val() == 0){
                    $( ckocom_card_desctiptor_name ).closest( 'tr' ).hide();
                    $( ckocom_card_desctiptor_city ).closest( 'tr' ).hide();
                }
                
                jQuery('#ckocom_card_desctiptor').on('change', function() {
                    if(this.value == 0){
                        $( ckocom_card_desctiptor_name ).closest( 'tr' ).hide();
                        $( ckocom_card_desctiptor_city ).closest( 'tr' ).hide();
                    } else {
                        $( ckocom_card_desctiptor_name ).closest( 'tr' ).show();
                        $( ckocom_card_desctiptor_city ).closest( 'tr' ).show();
                    }
                })

                if(jQuery('#ckocom_display_icon').val() == 0){
                    $( ckocom_card_icons ).closest( 'tr' ).hide();
                }

                jQuery('#ckocom_display_icon').on('change', function() {
                    if(this.value == 0){
                        $( ckocom_card_icons ).closest( 'tr' ).hide();
                    } else {
                        $( ckocom_card_icons ).closest( 'tr' ).show();
                    }
                })
            });
        ");

        $settings = array(
            'card_setting' => array(
                'title'       => __( 'Card settings', 'checkoutcom-cards-settings' ),
                'type'        => 'title',
                'description' => '',
            ),
            'ckocom_card_autocap' => array(
                'id' => 'ckocom_card_autocap',
                'title' => __('Payment Action', 'checkoutcom-cards-settings'),
                'type' => 'select',
                'desc_tip' => true,
                'options' => array(
                    0   => __('Authorize only', 'checkoutcom-cards-settings'),
                    1   => __('Authorize and Capture', 'checkoutcom-cards-settings')
                ),
                'default' => 1,
                'desc' => 'Set this to Authorise only if you want to manually capture the payment.',
            ),
            'ckocom_card_cap_delay' => array(
                'id' => 'ckocom_card_cap_delay',
                'title' => __('Capture Delay', 'checkoutcom-cards-settings'),
                'type' => 'text',
                'desc' => __('The delay in hours (0 means immediately, 1.2 means one hour and 30 min)', 'checkoutcom-cards-settings'),
                'desc_tip' => true,
            ),
            'ckocom_card_threed' => array(
                'id' => 'ckocom_card_threed',
                'title' => __('Use 3D Secure', 'checkoutcom-cards-settings'),
                'type' => 'select',
                'desc_tip' => true,
                'options' => array(
                    0   => __('No', 'checkoutcom-cards-settings'),
                    1   => __('Yes', 'checkoutcom-cards-settings')
                ),
                'default' => 0,
                'desc' => '3D secure payment',
            ),
            'ckocom_card_notheed' => array(
                'id' => 'ckocom_card_notheed',
                'title' => __('Attempt non-3D Secure', 'checkoutcom-cards-settings'),
                'type' => 'select',
                'desc_tip' => true,
                'options' => array(
                    0   => __('No', 'checkoutcom-cards-settings'),
                    1   => __('Yes', 'checkoutcom-cards-settings')
                ),
                'default' => 0,
                'desc' => 'Attempt non-3D Secure payment',
            ),
            'ckocom_card_saved' => array(
                'id' => 'ckocom_card_saved',
                'title' => __('Enable Save Cards', 'checkoutcom-cards-settings'),
                'type' => 'select',
                'desc_tip' => true,
                'options' => array(
                    0   => __('No', 'checkoutcom-cards-settings'),
                    1   => __('Yes', 'checkoutcom-cards-settings')
                ),
                'default' => 0,
                'desc' => 'Allow customers to save cards for future payments',
            ),
            'ckocom_card_require_cvv' => array(
                'id' => 'ckocom_card_require_cvv',
                'title' => __('Require CVV For Saved Cards', 'checkoutcom-cards-settings'),
                'type' => 'select',
                'desc_tip' => true,
                'options' => array(
                    0   => __('No', 'checkoutcom-cards-settings'),
                    1   => __('Yes', 'checkoutcom-cards-settings')
                ),
                'default' => 0,
                'desc' => 'Allow customers to save cards for future payments',
            ),
            'ckocom_card_desctiptor' => array(
                'id' => 'ckocom_card_desctiptor',
                'title' => __('Enable Dynamic Descriptor', 'checkoutcom-cards-settings'),
                'type' => 'select',
                'desc_tip' => true,
                'options' => array(
                    0   => __('No', 'checkoutcom-cards-settings'),
                    1   => __('Yes', 'checkoutcom-cards-settings')
                ),
                'default' => 0,
                'desc' => __('Dynamic Descriptor', 'checkoutcom-cards-settings'),
            ),
            'ckocom_card_desctiptor_name' => array(
                'id' => 'ckocom_card_desctiptor_name',
                'title' => __('Descriptor Name', 'checkoutcom-cards-settings'),
                'type' => 'text',
                'desc' => __('Maximum 25 characters)', 'checkoutcom-cards-settings'),
                'desc_tip' => true,
            ),
            'ckocom_card_desctiptor_city' => array(
                'id' => 'ckocom_card_desctiptor_city',
                'title' => __('Descriptor City', 'checkoutcom-cards-settings'),
                'type' => 'text',
                'desc' => __('Maximum 13 characters)', 'checkoutcom-cards-settings'),
                'desc_tip' => true,
            ),
            'ckocom_card_mada' => array(
                'id' => 'ckocom_card_mada',
                'title' => __('Enable MADA Bin Check', 'checkoutcom-cards-settings'),
                'type' => 'select',
                'desc_tip' => true,
                'options' => array(
                    0   => __('No', 'checkoutcom-cards-settings'),
                    1   => __('Yes', 'checkoutcom-cards-settings')
                ),
                'default' => 0,
                'desc' => __('For processing MADA transactions, this option needs to be set to Yes', 'checkoutcom-cards-settings'),
            ),
            'ckocom_display_icon' => array(
                'id' => 'ckocom_display_icon',
                'title' => __('Display Card Icons', 'checkoutcom-cards-settings'),
                'type' => 'select',
                'desc_tip' => true,
                'options' => array(
                    0   => __('No', 'checkoutcom-cards-settings'),
                    1   => __('Yes', 'checkoutcom-cards-settings')
                ),
                'default' => 0,
                'desc' => 'Enable/disable cards icon on checkout page',
            ),
            'ckocom_card_icons' => array(
                'id' => 'ckocom_card_icons',
                'title' => __('Card Icons', 'checkoutcom-cards-settings'),
                'type' => 'multiselect',
                'options' => array(
                    'visa' => __('Visa', 'checkoutcom-cards-settings'),
                    'mastercard' => __('Mastercard', 'checkoutcom-cards-settings'),
                    'amex' => __('American Express', 'checkoutcom-cards-settings'),
                    'dinersclub' => __('Diners Club International', 'checkoutcom-cards-settings'),
                    'discover' => __('Discover', 'checkoutcom-cards-settings'),
                    'jcb' => __('JCB', 'checkoutcom-cards-settings')
                ),
                'class' => 'wc-enhanced-select',
                'css' => 'width: 400px;',
            ),
            'ckocom_language_fallback' => array(
                'id' => 'ckocom_language_fallback',
                'title' => __('Language Fallback', 'checkoutcom-cards-settings'),
                'type' => 'select',
                'desc_tip' => true,
                'options' => array(
                    'EN-GB'   => __('English', 'checkoutcom-cards-settings'),
                    'NL-NL'   => __('Dutch', 'checkoutcom-cards-settings'),
                    'FR-FR'   => __('French', 'checkoutcom-cards-settings'),
                    'DE-DE'   => __('German', 'checkoutcom-cards-settings'),
                    'IT-IT'   => __('Italian', 'checkoutcom-cards-settings'),
                    'KR-KR'   => __('Korean', 'checkoutcom-cards-settings'),
                    'ES-ES'   => __('Spanish', 'checkoutcom-cards-settings')
                ),
                'default' => 'EN-GB',
                'desc' => 'Select the language to use by default if the one used by the shopper is not supported by the integration.',
            ),
            'ckocom_iframe_style' => array(
                'id' => 'ckocom_iframe_style',
                'title' => __('Iframe Style', 'checkoutcom-cards-settings'),
                'type' => 'select',
                'desc_tip' => true,
                'options' => array(
                    0   => __('Single Iframe', 'checkoutcom-cards-settings'),
                    1   => __('Multiple Iframe', 'checkoutcom-cards-settings')
                ),
                'default' => 0,
                'desc' => 'Select the styling for card iframe',
            ),
        );

        return apply_filters( 'wc_checkout_com_cards', $settings );
    }

    /**
     * CKO admin order management settings fields
     * @return mixed
     */
    public static function order_settings()
    {
        $settings = array(
            'order_setting'              => array(
                'title'       => __( 'Order Management settings', 'checkoutcom-cards-settings' ),
                'type'        => 'title',
                'description' => '',
            ),
            'ckocom_order_authorised' => array(
                'id' => 'ckocom_order_authorised',
                'title' => __('Authorised Order Status', 'checkoutcom-cards-settings'),
                'type' => 'select',
                'desc_tip' => true,
                'options' => wc_get_order_statuses(),
                'default' => 'wc-on-hold',
                'desc' => __('Select the status that should be used for orders with successful payment authorisation', 'checkoutcom-cards-settings'),
            ),
            'ckocom_order_captured' => array(
                'id' => 'ckocom_order_captured',
                'title' => __('Captured Order Status', 'checkoutcom-cards-settings'),
                'type' => 'select',
                'desc_tip' => true,
                'options' => wc_get_order_statuses(),
                'default' => 'wc-processing',
                'desc' => __('Select the status that should be used for orders with successful payment capture', 'checkoutcom-cards-settings'),
            ),
            'ckocom_order_void' => array(
                'id' => 'ckocom_order_void',
                'title' => __('Void Order Status', 'checkoutcom-cards-settings'),
                'type' => 'select',
                'desc_tip' => true,
                'options' => wc_get_order_statuses(),
                'default' => 'wc-cancelled',
                'desc' => __('Select the status that should be used for orders that have been voided', 'checkoutcom-cards-settings'),
            ),
            'ckocom_order_flagged' => array(
                'id' => 'ckocom_order_flagged',
                'title' => __('Flagged Order Status', 'checkoutcom-cards-settings'),
                'type' => 'select',
                'desc_tip' => true,
                'options' => wc_get_order_statuses(),
                'default' => 'wc-flagged',
                'desc' => __('Select the status that should be used for flagged orders', 'checkoutcom-cards-settings'),
            ),
            'ckocom_order_refunded' => array(
                'id' => 'ckocom_order_refunded',
                'title' => __('Refunded Order Status', 'checkoutcom-cards-settings'),
                'type' => 'select',
                'desc_tip' => true,
                'options' => wc_get_order_statuses(),
                'default' => 'wc-refunded',
                'desc' => __('Select the status that should be used for new orders with successful payment refund', 'checkoutcom-cards-settings'),
            ),
        );

        return apply_filters( 'wc_checkout_com_cards', $settings );
    }

    /**
     * CKO admin apple pay settting fields
     * @return mixed|void
     */
    public static function apple_settings()
    {
        $settings = array(
            'core_setting' => array(
                'title'       => __( 'Apple Pay settings', 'checkoutcom-cards-settings' ),
                'type'        => 'title',
                'description' => '',
            ),
            'enabled' => array(
                'id' => 'enable',
                'title'   => __( 'Enable/Disable', 'checkoutcom-cards-settings' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable Checkout.com', 'checkoutcom-cards-settings' ),
                'description' => __( 'This enables Checkout.com. cards payment', 'checkoutcom-cards-settings' ),
                'desc_tip'    => true,
                'default'     => 'yes',
            ),
            'title' => array(
                'title' => __('Title', 'checkoutcom-cards-settings'),
                'type' => 'text',
                'label' => __('Card payment title', 'checkoutcom-cards-settings'),
                'description' => __('Title that will be displayed on the checkout page', 'checkoutcom-cards-settings'),
                'desc_tip' => true,
                'default'     => 'Core settings',
            ),
            'description' => array(
                'title'       => __( 'Description', 'checkoutcom-cards-settings' ),
                'type'        => 'text',
                'description' => __( 'This controls the description which the user sees during checkout.', 'checkoutcom-cards-settings' ),
                'default'     => 'Pay with Apple Pay.',
                'desc_tip'    => true
            ),
            'ckocom_apple_mercahnt_id' => array(
                'title' => __('Merchant Identifier', 'checkoutcom-cards-settings'),
                'type' => 'text',
                'description' => __('You can find this in your developer portal, or to generate one follow this '.'<a href="https://docs.checkout.com/docs/apple-pay">guide</a>', 'checkoutcom-cards-settings'),
                'default'     => '',
            ),
            'ckocom_apple_certificate' => array(
                'title' => __('Merchant Certificate', 'checkoutcom-cards-settings'),
                'type' => 'text',
                'description' => __('The absolute path to your .pem certificate.', 'checkoutcom-cards-settings'),
                'desc_tip' => true,
                'default'     => '',
            ),
            'ckocom_apple_key' => array(
                'title' => __('Merchant Certificate Key', 'checkoutcom-cards-settings'),
                'type' => 'text',
                'description' => __('The absolute path to your .key certificate key.', 'checkoutcom-cards-settings'),
                'desc_tip' => true,
                'default'     => '',
            ),
            'ckocom_apple_type' => array(
                'title'       => __('Button Type', 'checkoutcom-cards-settings'),
                'type'        => 'select',
                'options'     => array(
                    'apple-pay-button-text-buy' => __('Buy', 'checkoutcom-cards-settings'),
                    'apple-pay-button-text-check-out' => __('Checkout out', 'checkoutcom-cards-settings'),
                    'apple-pay-button-text-book' => __('Book', 'checkoutcom-cards-settings'),
                    'apple-pay-button-text-donate' => __('Donate', 'checkoutcom-cards-settings'),
                    'apple-pay-button' => __('Plain', 'checkoutcom-cards-settings')
                )
            ),
            'ckocom_apple_theme' => array(
                'title'       => __('Button Theme', 'checkoutcom-cards-settings'),
                'type'        => 'select',
                'options'     => array(
                    'apple-pay-button-black-with-text' => __('Black', 'checkoutcom-cards-settings'),
                    'apple-pay-button-white-with-text' => __('White', 'checkoutcom-cards-settings'),
                    'apple-pay-button-white-with-line-with-text' => __('White with outline', 'checkoutcom-cards-settings')
                )
            ),
            'ckocom_apple_language' => array(
                'title' => __('Button Language', 'checkoutcom-cards-settings'),
                'type' => 'text',
                'description' => __('ISO 639-1 value of the language. See suported languages '.'<a href="https://applepaydemo.apple.com/" >here.</a>', 'checkoutcom-cards-settings'),
                'default'     => '',
            ),
            'enable_mada' => array(
                'id' => 'enable_mada_apple_pay',
                'title'   => __( 'Enable MADA', 'checkoutcom-cards-settings' ),
                'type'    => 'checkbox',
                'desc_tip'    => true,
                'default'     => 'no',
                'description' => __('Please enable if entity is in Saudi Arabia', 'checkoutcom-cards-settings'),
            )
        );

        return apply_filters( 'wc_checkout_com_apple_pay', $settings );
    }

    /**
     * CKO admin google pay setting fields
     * @return mixed|void
     */
    public static function google_settings()
    {
        $settings = array(
            'google_setting'              => array(
                'title'       => __( 'Google Pay Settings', 'checkoutcom-cards-settings' ),
                'type'        => 'title',
                'description' => '',
            ),
            'enabled' => array(
                'id' => 'enable',
                'title'   => __( 'Enable/Disable', 'checkoutcom-cards-settings' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable Checkout.com', 'checkoutcom-cards-settings' ),
                'description' => __( 'This enables google pay as a payment method', 'checkoutcom-cards-settings' ),
                'desc_tip'    => true,
                'default'     => 'no',
            ),
            'title' => array(
                'title' => __('Title', 'checkoutcom-cards-settings'),
                'type' => 'text',
                'label' => __('Google Pay', 'checkoutcom-cards-settings'),
                'description' => __('Title that will be displayed on the checkout page', 'checkoutcom-cards-settings'),
                'desc_tip' => true,
                'default'     => 'Google Pay',
            ),
            'description' => array(
                'title'       => __( 'Description', 'checkoutcom-cards-settings' ),
                'type'        => 'text',
                'description' => __( 'This controls the description which the user sees during checkout.', 'checkoutcom-cards-settings' ),
                'default'     => 'Pay with Google Pay.',
                'desc_tip'    => true
            ),
            'ckocom_google_merchant_id' => array(
                'title' => __('Merchant Identifier', 'checkoutcom-cards-settings'),
                'type' => 'text',
                'description' => __('Your production merchant identifier.'.'<br>'.'For testing use the following value: 01234567890123456789', 'checkoutcom-cards-settings'),
                'desc_tip' => false,
                'default'     => '01234567890123456789',
            ),
            'ckocom_google_style' => array(
                'title'       => __('Button Style', 'checkoutcom-cards-settings'),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'description' => __('Select button color.', 'checkoutcom-cards-settings'),
                'default'     => 'authorize',
                'desc_tip'    => true,
                'options'     => array(
                    'google-pay-black' => __('Black', 'checkoutcom-cards-settings'),
                    'google-pay-white' => __('White', 'checkoutcom-cards-settings')
                )
            ),
        );

        return apply_filters( 'wc_checkout_com_google_pay', $settings );
    }

    /**
     *
     * @return mixed
     */
    public static function apm_settings()
    {
        $settings = array(
            'apm_setting' => array(
                'title'       => __( 'Alternative Payment Settings', 'checkoutcom-cards-settings' ),
                'type'        => 'title',
                'description' => '',
            ),
            'enabled' => array(
                'id' => 'enable',
                'title'   => __( 'Enable/Disable', 'checkoutcom-cards-settings' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable Checkout.com', 'checkoutcom-cards-settings' ),
                'description' => __( 'This enables alternative payment methods', 'checkoutcom-cards-settings' ),
                'desc_tip'    => true,
                'default'     => 'no',
            ),
            'title' => array(
                'title' => __('Title', 'checkoutcom-cards-settings'),
                'type' => 'text',
                'label' => __('Alternative Payments', 'checkoutcom-cards-settings'),
                'description' => __('Title that will be displayed on the checkout page', 'checkoutcom-cards-settings'),
                'desc_tip' => true,
                'default'     => 'Alternative Payment Methods',
            ),
            'ckocom_apms_selector' => array(
                'title' => __('Alternative Payment Methods', 'checkoutcom-cards-settings'),
                'type' => 'multiselect',
                'options' => array(
                    'alipay' => __('Alipay', 'checkoutcom-cards-settings' ),
                    'boleto' => __('Boleto', 'checkoutcom-cards-settings' ),
                    'giropay' => __('Giropay', 'checkoutcom-cards-settings' ),
                    'ideal' => __('iDEAL', 'checkoutcom-cards-settings' ),
                    'klarna' => __('Klarna', 'checkoutcom-cards-settings' ),
                    'poli' => __('Poli', 'checkoutcom-cards-settings' ),
                    'sepa' => __('Sepa Direct Debit', 'checkoutcom-cards-settings' ),
                    'sofort' => __('Sofort', 'checkoutcom-cards-settings' ),
                    'eps' => __('EPS', 'checkoutcom-cards-settings' ),
                    'bancontact' => __('Bancontact', 'checkoutcom-cards-settings' ),
                    'knet' => __('KNET', 'checkoutcom-cards-settings' ),
                    'fawry' => __('Fawry', 'checkoutcom-cards-settings' ),
                    'qpay' => __('QPay', 'checkoutcom-cards-settings' ),
                ),
                'class' => 'wc-enhanced-select',
                'css' => 'width: 400px;',
            ),

        );

        return apply_filters( 'wc_checkout_com_alternative_payments', $settings );
    }

    public static function debug_settings()
    {
        $settings = array(
            'debug_settings'              => array(
                'title'       => __( 'Debug Settings', 'checkoutcom-cards-settings' ),
                'type'        => 'title',
                'description' => '',
            ),
            'cko_file_logging' => array(
                'id' => 'cko_file_logging',
                'title'   => __( 'File Logging', 'checkoutcom-cards-settings' ),
                'type'    => 'checkbox',
                'desc_tip'    => true,
                'default'     => 'no',
                'desc' => __('Check to enable file logging', 'checkoutcom-cards-settings'),
            ),
            'cko_console_logging' => array(
                'id' => 'cko_console_logging',
                'title'   => __( 'Console Logging', 'checkoutcom-cards-settings' ),
                'type'    => 'checkbox',
                'desc_tip'    => true,
                'default'     => 'no',
                'desc' => __('Check to enable console logging', 'checkoutcom-cards-settings'),
            ),
            'cko_gateway_responses' => array(
                'id' => 'cko_gateway_responses',
                'title'   => __( 'Gateway Responses', 'checkoutcom-cards-settings' ),
                'type'    => 'checkbox',
                'desc_tip'    => true,
                'default'     => 'no',
                'desc' => __('Check to show gateway response.', 'checkoutcom-cards-settings'),
            ),
        );

        return apply_filters( 'wc_checkout_com_cards', $settings );
    }
}