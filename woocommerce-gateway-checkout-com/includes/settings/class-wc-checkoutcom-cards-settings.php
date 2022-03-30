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
            'core_setting'       => array(
                'title'       => __( 'Core settings', 'wc_checkout_com' ),
                'type'        => 'title',
                'description' => '',
            ),
            'enabled'            => array(
                'id'          => 'enable',
                'title'       => __( 'Enable/Disable', 'wc_checkout_com' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable Checkout.com cards payment', 'wc_checkout_com' ),
                'description' => __( 'This enables Checkout.com. cards payment', 'wc_checkout_com' ),
                'desc_tip'    => true,
                'default'     => 'yes',
            ),
            'ckocom_environment' => array(
                'title'       => __( 'Environment', 'wc_checkout_com' ),
                'type'        => 'select',
                'description' => __( 'When going to production, make sure to set this to Live', 'wc_checkout_com' ),
                'desc_tip'    => true,
                'options'     => array(
                    'sandbox' => __( 'SandBox', 'wc_checkout_com' ),
                    'live'    => __( 'Live', 'wc_checkout_com' ),
                ),
                'default'     => 'sandbox',
            ),
            'title'              => array(
                'title'       => __( 'Payment Option Title', 'wc_checkout_com' ),
                'type'        => 'text',
                'label'       => __( 'Pay by Card with Checkout.com', 'wc_checkout_com' ),
                'description' => __( 'Title that will be displayed on the checkout page', 'wc_checkout_com' ),
                'desc_tip'    => true,
                'default'     => 'Pay by Card with Checkout.com',
            ),
            'ckocom_sk'          => array(
                'title'       => __( 'Secret Key', 'wc_checkout_com' ),
                'type'        => 'text',
                'description' => __( 'You can ' . '<a href="https://www.checkout.com/docs/the-hub/update-your-hub-settings#Manage_the_API_keys">find your secret key </a>' . 'in the Checkout.com Hub', 'wc_checkout_com' ),
                'placeholder' => 'sk_xxx',
            ),
            'ckocom_pk'          => array(
                'title'       => __( 'Public Key', 'wc_checkout_com' ),
                'type'        => 'text',
                'description' => __( 'You can ' . '<a href="https://www.checkout.com/docs/the-hub/update-your-hub-settings#Manage_the_API_keys">find your public key </a>' . 'in the Checkout.com Hub', 'wc_checkout_com' ),
                'placeholder' => 'pk_xxx',
            ),
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
            'card_setting'                => array(
                'title'       => __( 'Card settings', 'wc_checkout_com' ),
                'type'        => 'title',
                'description' => '',
            ),
            'ckocom_card_autocap'         => array(
                'id'       => 'ckocom_card_autocap',
                'title'    => __( 'Payment Action', 'wc_checkout_com' ),
                'type'     => 'select',
                'desc_tip' => true,
                'options'  => array(
                    0 => __( 'Authorize only', 'wc_checkout_com' ),
                    1 => __( 'Authorize and Capture', 'wc_checkout_com' ),
                ),
                'default'  => 1,
                'desc'     => 'Set this to Authorise only if you want to manually capture the payment.',
            ),
            'ckocom_card_cap_delay'       => array(
                'id'       => 'ckocom_card_cap_delay',
                'title'    => __( 'Capture Delay', 'wc_checkout_com' ),
                'type'     => 'text',
                'desc'     => __( 'The delay in hours (0 means immediately, 1.2 means one hour and 30 min)', 'wc_checkout_com' ),
                'desc_tip' => true,
            ),
            'ckocom_card_threed'          => array(
                'id'       => 'ckocom_card_threed',
                'title'    => __( 'Use 3D Secure', 'wc_checkout_com' ),
                'type'     => 'select',
                'desc_tip' => true,
                'options'  => array(
                    0 => __( 'No', 'wc_checkout_com' ),
                    1 => __( 'Yes', 'wc_checkout_com' ),
                ),
                'default'  => 0,
                'desc'     => '3D secure payment',
            ),
            'ckocom_card_notheed'         => array(
                'id'       => 'ckocom_card_notheed',
                'title'    => __( 'Attempt non-3D Secure', 'wc_checkout_com' ),
                'type'     => 'select',
                'desc_tip' => true,
                'options'  => array(
                    0 => __( 'No', 'wc_checkout_com' ),
                    1 => __( 'Yes', 'wc_checkout_com' ),
                ),
                'default'  => 0,
                'desc'     => 'Attempt non-3D Secure payment',
            ),
            'ckocom_card_saved'           => array(
                'id'       => 'ckocom_card_saved',
                'title'    => __( 'Enable Save Cards', 'wc_checkout_com' ),
                'type'     => 'select',
                'desc_tip' => true,
                'options'  => array(
                    0 => __( 'No', 'wc_checkout_com' ),
                    1 => __( 'Yes', 'wc_checkout_com' ),
                ),
                'default'  => 0,
                'desc'     => 'Allow customers to save cards for future payments',
            ),
            'ckocom_card_require_cvv'     => array(
                'id'       => 'ckocom_card_require_cvv',
                'title'    => __( 'Require CVV For Saved Cards', 'wc_checkout_com' ),
                'type'     => 'select',
                'desc_tip' => true,
                'options'  => array(
                    0 => __( 'No', 'wc_checkout_com' ),
                    1 => __( 'Yes', 'wc_checkout_com' ),
                ),
                'default'  => 0,
                'desc'     => 'Allow customers to save cards for future payments',
            ),
            'ckocom_card_desctiptor'      => array(
                'id'       => 'ckocom_card_desctiptor',
                'title'    => __( 'Enable Dynamic Descriptor', 'wc_checkout_com' ),
                'type'     => 'select',
                'desc_tip' => true,
                'options'  => array(
                    0 => __( 'No', 'wc_checkout_com' ),
                    1 => __( 'Yes', 'wc_checkout_com' ),
                ),
                'default'  => 0,
                'desc'     => __( 'Dynamic Descriptor', 'wc_checkout_com' ),
            ),
            'ckocom_card_desctiptor_name' => array(
                'id'       => 'ckocom_card_desctiptor_name',
                'title'    => __( 'Descriptor Name', 'wc_checkout_com' ),
                'type'     => 'text',
                'desc'     => __( 'Maximum 25 characters)', 'wc_checkout_com' ),
                'desc_tip' => true,
            ),
            'ckocom_card_desctiptor_city' => array(
                'id'       => 'ckocom_card_desctiptor_city',
                'title'    => __( 'Descriptor City', 'wc_checkout_com' ),
                'type'     => 'text',
                'desc'     => __( 'Maximum 13 characters)', 'wc_checkout_com' ),
                'desc_tip' => true,
            ),
            'ckocom_card_mada'            => array(
                'id'       => 'ckocom_card_mada',
                'title'    => __( 'Enable MADA Bin Check', 'wc_checkout_com' ),
                'type'     => 'select',
                'desc_tip' => true,
                'options'  => array(
                    0 => __( 'No', 'wc_checkout_com' ),
                    1 => __( 'Yes', 'wc_checkout_com' ),
                ),
                'default'  => 0,
                'desc'     => __( 'For processing MADA transactions, this option needs to be set to Yes', 'wc_checkout_com' ),
            ),
            'ckocom_display_icon'         => array(
                'id'       => 'ckocom_display_icon',
                'title'    => __( 'Display Card Icons', 'wc_checkout_com' ),
                'type'     => 'select',
                'desc_tip' => true,
                'options'  => array(
                    0 => __( 'No', 'wc_checkout_com' ),
                    1 => __( 'Yes', 'wc_checkout_com' ),
                ),
                'default'  => 0,
                'desc'     => 'Enable/disable cards icon on checkout page',
            ),
            'ckocom_card_icons'           => array(
                'id'      => 'ckocom_card_icons',
                'title'   => __( 'Card Icons', 'wc_checkout_com' ),
                'type'    => 'multiselect',
                'options' => array(
                    'visa'       => __( 'Visa', 'wc_checkout_com' ),
                    'mastercard' => __( 'Mastercard', 'wc_checkout_com' ),
                    'amex'       => __( 'American Express', 'wc_checkout_com' ),
                    'dinersclub' => __( 'Diners Club International', 'wc_checkout_com' ),
                    'discover'   => __( 'Discover', 'wc_checkout_com' ),
                    'jcb'        => __( 'JCB', 'wc_checkout_com' ),
                ),
                'class'   => 'wc-enhanced-select',
                'css'     => 'width: 400px;',
            ),
            'ckocom_language_fallback'    => array(
                'id'       => 'ckocom_language_fallback',
                'title'    => __( 'Language Fallback', 'wc_checkout_com' ),
                'type'     => 'select',
                'desc_tip' => true,
                'options'  => array(
                    'EN-GB' => __( 'English', 'wc_checkout_com' ),
                    'NL-NL' => __( 'Dutch', 'wc_checkout_com' ),
                    'FR-FR' => __( 'French', 'wc_checkout_com' ),
                    'DE-DE' => __( 'German', 'wc_checkout_com' ),
                    'IT-IT' => __( 'Italian', 'wc_checkout_com' ),
                    'KR-KR' => __( 'Korean', 'wc_checkout_com' ),
                    'ES-ES' => __( 'Spanish', 'wc_checkout_com' ),
                ),
                'default'  => 'EN-GB',
                'desc'     => 'Select the language to use by default if the one used by the shopper is not supported by the integration.',
            ),
            'ckocom_iframe_style'         => array(
                'id'       => 'ckocom_iframe_style',
                'title'    => __( 'Iframe Style', 'wc_checkout_com' ),
                'type'     => 'select',
                'desc_tip' => true,
                'options'  => array(
                    0 => __( 'Single Iframe', 'wc_checkout_com' ),
                    1 => __( 'Multiple Iframe', 'wc_checkout_com' ),
                ),
                'default'  => 0,
                'desc'     => 'Select the styling for card iframe',
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
        wc_enqueue_js( "
            jQuery( function(){
                
                jQuery('#ckocom_order_authorised').on('click', function() {

                    jQuery('#ckocom_order_authorised option').prop('disabled', false);
                    
                    const captured_order_status = jQuery('#ckocom_order_captured').val();
                    jQuery('#ckocom_order_authorised option[value= \"' + captured_order_status + '\"]').prop('disabled', true);

                });

                jQuery('#ckocom_order_captured').on('click', function() {

                    jQuery('#ckocom_order_captured option').prop('disabled', false);

                    const authorized_order_status = jQuery('#ckocom_order_authorised').val();
                    jQuery('#ckocom_order_captured option[value= \"' + authorized_order_status + '\"]').prop('disabled', true);

                });
            });
        ");

        $settings = array(
            'order_setting'           => array(
                'title'       => __( 'Order Management settings', 'wc_checkout_com' ),
                'type'        => 'title',
                'description' => '',
            ),
            'ckocom_order_authorised' => array(
                'id'       => 'ckocom_order_authorised',
                'title'    => __( 'Authorised Order Status', 'wc_checkout_com' ),
                'type'     => 'select',
                'desc_tip' => true,
                'options'  => wc_get_order_statuses(),
                'default'  => 'wc-on-hold',
                'desc'     => __( 'Select the status that should be used for orders with successful payment authorisation', 'wc_checkout_com' ),
            ),
            'ckocom_order_captured'   => array(
                'id'       => 'ckocom_order_captured',
                'title'    => __( 'Captured Order Status', 'wc_checkout_com' ),
                'type'     => 'select',
                'desc_tip' => true,
                'options'  => wc_get_order_statuses(),
                'default'  => 'wc-processing',
                'desc'     => __( 'Select the status that should be used for orders with successful payment capture', 'wc_checkout_com' ),
            ),
            'ckocom_order_void'       => array(
                'id'       => 'ckocom_order_void',
                'title'    => __( 'Void Order Status', 'wc_checkout_com' ),
                'type'     => 'select',
                'desc_tip' => true,
                'options'  => wc_get_order_statuses(),
                'default'  => 'wc-cancelled',
                'desc'     => __( 'Select the status that should be used for orders that have been voided', 'wc_checkout_com' ),
            ),
            'ckocom_order_flagged'    => array(
                'id'       => 'ckocom_order_flagged',
                'title'    => __( 'Flagged Order Status', 'wc_checkout_com' ),
                'type'     => 'select',
                'desc_tip' => true,
                'options'  => wc_get_order_statuses(),
                'default'  => 'wc-flagged',
                'desc'     => __( 'Select the status that should be used for flagged orders', 'wc_checkout_com' ),
            ),
            'ckocom_order_refunded'   => array(
                'id'       => 'ckocom_order_refunded',
                'title'    => __( 'Refunded Order Status', 'wc_checkout_com' ),
                'type'     => 'select',
                'desc_tip' => true,
                'options'  => wc_get_order_statuses(),
                'default'  => 'wc-refunded',
                'desc'     => __( 'Select the status that should be used for new orders with successful payment refund', 'wc_checkout_com' ),
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
            'core_setting'             => array(
                'title'       => __( 'Apple Pay settings', 'wc_checkout_com' ),
                'type'        => 'title',
                'description' => '',
            ),
            'enabled'                  => array(
                'id'          => 'enable',
                'title'       => __( 'Enable/Disable', 'wc_checkout_com' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable Checkout.com', 'wc_checkout_com' ),
                'description' => __( 'This enables Checkout.com. cards payment', 'wc_checkout_com' ),
                'desc_tip'    => true,
                'default'     => 'yes',
            ),
            'title'                    => array(
                'title'       => __( 'Title', 'wc_checkout_com' ),
                'type'        => 'text',
                'label'       => __( 'Card payment title', 'wc_checkout_com' ),
                'description' => __( 'Title that will be displayed on the checkout page', 'wc_checkout_com' ),
                'desc_tip'    => true,
                'default'     => 'Core settings',
            ),
            'description'              => array(
                'title'       => __( 'Description', 'wc_checkout_com' ),
                'type'        => 'text',
                'description' => __( 'This controls the description which the user sees during checkout.', 'wc_checkout_com' ),
                'default'     => 'Pay with Apple Pay.',
                'desc_tip'    => true,
            ),
            'ckocom_apple_mercahnt_id' => array(
                'title'       => __( 'Merchant Identifier', 'wc_checkout_com' ),
                'type'        => 'text',
                'description' => __( 'You can find this in your developer portal, or to generate one follow this ' . '<a href="https://docs.checkout.com/docs/apple-pay">guide</a>', 'wc_checkout_com' ),
                'default'     => '',
            ),
            'ckocom_apple_certificate' => array(
                'title'       => __( 'Merchant Certificate', 'wc_checkout_com' ),
                'type'        => 'text',
                'description' => __( 'The absolute path to your .pem certificate.', 'wc_checkout_com' ),
                'desc_tip'    => true,
                'default'     => '',
            ),
            'ckocom_apple_key'         => array(
                'title'       => __( 'Merchant Certificate Key', 'wc_checkout_com' ),
                'type'        => 'text',
                'description' => __( 'The absolute path to your .key certificate key.', 'wc_checkout_com' ),
                'desc_tip'    => true,
                'default'     => '',
            ),
            'ckocom_apple_type'        => array(
                'title'   => __( 'Button Type', 'wc_checkout_com' ),
                'type'    => 'select',
                'options' => array(
                    'apple-pay-button-text-buy'       => __( 'Buy', 'wc_checkout_com' ),
                    'apple-pay-button-text-check-out' => __( 'Checkout out', 'wc_checkout_com' ),
                    'apple-pay-button-text-book'      => __( 'Book', 'wc_checkout_com' ),
                    'apple-pay-button-text-donate'    => __( 'Donate', 'wc_checkout_com' ),
                    'apple-pay-button'                => __( 'Plain', 'wc_checkout_com' ),
                ),
            ),
            'ckocom_apple_theme'       => array(
                'title'   => __( 'Button Theme', 'wc_checkout_com' ),
                'type'    => 'select',
                'options' => array(
                    'apple-pay-button-black-with-text'           => __( 'Black', 'wc_checkout_com' ),
                    'apple-pay-button-white-with-text'           => __( 'White', 'wc_checkout_com' ),
                    'apple-pay-button-white-with-line-with-text' => __( 'White with outline', 'wc_checkout_com' ),
                ),
            ),
            'ckocom_apple_language'    => array(
                'title'       => __( 'Button Language', 'wc_checkout_com' ),
                'type'        => 'text',
                'description' => __( 'ISO 639-1 value of the language. See suported languages ' . '<a href="https://applepaydemo.apple.com/" >here.</a>', 'wc_checkout_com' ),
                'default'     => '',
            ),
            'enable_mada'              => array(
                'id'          => 'enable_mada_apple_pay',
                'title'       => __( 'Enable MADA', 'wc_checkout_com' ),
                'type'        => 'checkbox',
                'desc_tip'    => true,
                'default'     => 'no',
                'description' => __( 'Please enable if entity is in Saudi Arabia', 'wc_checkout_com' ),
            ),
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
            'google_setting'            => array(
                'title'       => __( 'Google Pay Settings', 'wc_checkout_com' ),
                'type'        => 'title',
                'description' => '',
            ),
            'enabled'                   => array(
                'id'          => 'enable',
                'title'       => __( 'Enable/Disable', 'wc_checkout_com' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable Checkout.com', 'wc_checkout_com' ),
                'description' => __( 'This enables google pay as a payment method', 'wc_checkout_com' ),
                'desc_tip'    => true,
                'default'     => 'no',
            ),
            'title'                     => array(
                'title'       => __( 'Title', 'wc_checkout_com' ),
                'type'        => 'text',
                'label'       => __( 'Google Pay', 'wc_checkout_com' ),
                'description' => __( 'Title that will be displayed on the checkout page', 'wc_checkout_com' ),
                'desc_tip'    => true,
                'default'     => 'Google Pay',
            ),
            'description'               => array(
                'title'       => __( 'Description', 'wc_checkout_com' ),
                'type'        => 'text',
                'description' => __( 'This controls the description which the user sees during checkout.', 'wc_checkout_com' ),
                'default'     => 'Pay with Google Pay.',
                'desc_tip'    => true,
            ),
            'ckocom_google_merchant_id' => array(
                'title'       => __( 'Merchant Identifier', 'wc_checkout_com' ),
                'type'        => 'text',
                'description' => __( 'Your production merchant identifier.' . '<br>' . 'For testing use the following value: 01234567890123456789', 'wc_checkout_com' ),
                'desc_tip'    => false,
                'default'     => '01234567890123456789',
            ),
            'ckocom_google_threed'      => array(
                'id'          => 'ckocom_google_threed',
                'title'       => __( 'Use 3D Secure', 'wc_checkout_com' ),
                'type'        => 'select',
                'desc_tip'    => true,
                'options'     => array(
                    0 => __( 'No', 'wc_checkout_com' ),
                    1 => __( 'Yes', 'wc_checkout_com' ),
                ),
                'default'     => 0,
                'description' => '3D secure payment',
            ),
            'ckocom_google_style'       => array(
                'title'       => __( 'Button Style', 'wc_checkout_com' ),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'description' => __( 'Select button color.', 'wc_checkout_com' ),
                'default'     => 'authorize',
                'desc_tip'    => true,
                'options'     => array(
                    'google-pay-black' => __( 'Black', 'wc_checkout_com' ),
                    'google-pay-white' => __( 'White', 'wc_checkout_com' ),
                ),
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
            'apm_setting'          => array(
                'title'       => __( 'Alternative Payment Settings', 'wc_checkout_com' ),
                'type'        => 'title',
                'description' => '',
            ),
            'enabled'              => array(
                'id'          => 'enable',
                'title'       => __( 'Enable/Disable', 'wc_checkout_com' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable Checkout.com', 'wc_checkout_com' ),
                'description' => __( 'This enables alternative payment methods', 'wc_checkout_com' ),
                'desc_tip'    => true,
                'default'     => 'no',
            ),
            'title'                => array(
                'title'       => __( 'Title', 'wc_checkout_com' ),
                'type'        => 'text',
                'label'       => __( 'Alternative Payments', 'wc_checkout_com' ),
                'description' => __( 'Title that will be displayed on the checkout page', 'wc_checkout_com' ),
                'desc_tip'    => true,
                'default'     => 'Alternative Payment Methods',
            ),
            'ckocom_apms_selector' => array(
                'title'   => __( 'Alternative Payment Methods', 'wc_checkout_com' ),
                'type'    => 'multiselect',
                'options' => array(
                    'alipay'     => __( 'Alipay', 'wc_checkout_com' ),
                    'boleto'     => __( 'Boleto', 'wc_checkout_com' ),
                    'giropay'    => __( 'Giropay', 'wc_checkout_com' ),
                    'ideal'      => __( 'iDEAL', 'wc_checkout_com' ),
                    'klarna'     => __( 'Klarna', 'wc_checkout_com' ),
                    'poli'       => __( 'Poli', 'wc_checkout_com' ),
                    'sepa'       => __( 'Sepa Direct Debit', 'wc_checkout_com' ),
                    'sofort'     => __( 'Sofort', 'wc_checkout_com' ),
                    'eps'        => __( 'EPS', 'wc_checkout_com' ),
                    'bancontact' => __( 'Bancontact', 'wc_checkout_com' ),
                    'knet'       => __( 'KNET', 'wc_checkout_com' ),
                    'fawry'      => __( 'Fawry', 'wc_checkout_com' ),
                    'qpay'       => __( 'QPay', 'wc_checkout_com' ),
                    'multibanco' => __( 'Multibanco', 'wc_checkout_com' ),
                ),
                'class'   => 'wc-enhanced-select',
                'css'     => 'width: 400px;',
            ),

        );

        return apply_filters( 'wc_checkout_com_alternative_payments', $settings );
    }

    public static function debug_settings()
    {
        $settings = array(
            'debug_settings'        => array(
                'title'       => __( 'Debug Settings', 'wc_checkout_com' ),
                'type'        => 'title',
                'description' => '',
            ),
            'cko_file_logging'      => array(
                'id'       => 'cko_file_logging',
                'title'    => __( 'File Logging', 'wc_checkout_com' ),
                'type'     => 'checkbox',
                'desc_tip' => true,
                'default'  => 'no',
                'desc'     => __( 'Check to enable file logging', 'wc_checkout_com' ),
            ),
            'cko_console_logging'   => array(
                'id'       => 'cko_console_logging',
                'title'    => __( 'Console Logging', 'wc_checkout_com' ),
                'type'     => 'checkbox',
                'desc_tip' => true,
                'default'  => 'no',
                'desc'     => __( 'Check to enable console logging', 'wc_checkout_com' ),
            ),
            'cko_gateway_responses' => array(
                'id'       => 'cko_gateway_responses',
                'title'    => __( 'Gateway Responses', 'wc_checkout_com' ),
                'type'     => 'checkbox',
                'desc_tip' => true,
                'default'  => 'no',
                'desc'     => __( 'Check to show gateway response.', 'wc_checkout_com' ),
            ),
        );

        return apply_filters( 'wc_checkout_com_cards', $settings );
    }
}
