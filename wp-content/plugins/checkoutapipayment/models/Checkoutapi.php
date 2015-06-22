<?php

if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
    return;
};


abstract class models_Checkoutapi extends WC_Payment_Gateway implements models_InterfacePayment{
    protected $_code;
    protected $_methodType;
    protected $_methodInstance;

    public function __construct()
    {

        $this->_init();
        define("CHECKOUTAPI_SECRET_KEY", $this->checkoutapipayment_secretkey);
		define("CHECKOUTAPI_PUBLIC_KEY", $this->checkoutapipayment_publickey);
		define("CHECKOUTAPI_PAYMENTACTION", $this ->checkoutapipayment_paymentaction);

		define("CHECKOUTAPI_AUTOCAPTIME", $this->checkoutapipayment_autoCaptime );
		define("CHECKOUTAPI_TIMEOUT", $this->checkoutapipayment_timeout);
		define("CHECKOUTAPI_ENDPOINT", $this->checkoutapipayment_endpoint);
		define("CHECKOUTAPI_ISPCI", $this->checkoutapipayment_ispci);
		define("CHECKOUTAPI_PCINO", $this->checkoutapipayment_pciNo);
		define("CHECKOUTAPI_LOGOURL", $this->checkoutapipayment_logoUrl);
		define("CHECKOUTAPI_THEMECOLOR", $this->checkoutapipayment_themeColor);
		define("CHECKOUTAPI_BUTTONCOLOR", $this->checkoutapipayment_buttonColor);
		define("CHECKOUTAPI_ICONCOLOR", $this->checkoutapipayment_iconColor);
		define("CHECKOUTAPI_LP", $this->checkoutapipayment_lp);
		define("CHECKOUTAPI_CURRENCYCODE", $this->checkoutapipayment_currencyCode);

		$this->_setInstanceMethod();

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

    }

    abstract public function _initCode();

    public function admin_options()
    {
        ?>
		<h3><?php _e( 'Credit Card (Checkout.com)', 'woocommerce' ); ?></h3>
		<p><?php _e( 'Credit Card payment offered by Checkout.com.', 'woocommerce' ); ?></p>
		<table class="form-table">
			  <?php $this->generate_settings_html(); ?>
		</table>
		<?php
    }

    public function init_form_fields()
    {
		$this->form_fields = array (
			'enabled'   =>    array(
			    'title'     =>    __( 'Enable/Disable', 'woocommerce' ),
				'type'      =>    'checkbox',
				'label'     =>    __( 'Enable Credit Card (Checkout.com) payment method', 'woocommerce' ),
				'default'   =>   'yes'
			 ),

			'title'    =>    array(
				  'title'       =>    __( 'Title', 'woocommerce' ),
				  'type'        =>   'text',
				  'description' =>   __( 'This controls the title which the user sees during checkout.',
					  'woocommerce' ),
				  'default'     =>   __( 'Credit Card (Checkout.com)', 'woocommerce' ),
				  'desc_tip'    => true,
			  ),

			'checkoutapipayment_secretkey' => array(
			  'title'       => __( 'Secret Key', 'woocommerce' ),
			  'type'        => 'text',
			  'description' => __( 'This is the Secret Key where you could find on Checkout Hub Settings.', 'woocommerce' ),
			  'default'     => '',
			  'desc_tip'    => true,
			  'placeholder' => 'Your secret Key'
			  ),

			'checkoutapipayment_publickey' => array(
			  'title' => __( 'Public Key', 'woocommerce' ),
			  'type' => 'text',
			  'description' => __( 'This is the Secret Key where you could find on Checkout Hub Settings.', 'woocommerce' ),
			  'default' => '',
			  'desc_tip'      => true,
			  'placeholder' => 'Your public Key'
			  ),

			'checkoutapipayment_paymentaction' => array(
			  'title' => __( 'Payment Action', 'woocommerce' ),
			  'type' => 'select',
			  'description' => __( 'Select which payment action to use. Authorize Only will authorize the customers card for the purchase amount only.  Authorize &amp; Capture will authorize the customer\'s card and collect funds.', 'woothemes' ),
			  'options'     => array(
				  'Capture' => 'Authorize &amp; Capture',
				  'Auth' => 'Authorize Only',
				  ),
			  'default'     => 'Authorize &amp; Capture'
			  ),

			'checkoutapipayment_cardtype' => array(
			  'title' => __( 'Credit Card Types', 'woocommerce' ),
			  'type' => 'multiselect',
			  'description' => __( 'Select the Credit Card Types','woocommerce' ),
			  'desc_tip'      => true,
			  'options'     => array(
				  'Visa' => 'VISA',
				  'MasterCard' => 'MasterCard',
				  'American Express' => 'Amercian Express',
				  'Discover' => 'Discover',
				  'Diners Club' => 'Diners Club',
				  'JCB' => 'JCB',
				  'Maestro' => 'Maestro/Switch',
				  'Other' => 'Other'
				  )
			  ),

			'checkoutapipayment_autoCaptime' => array(
			  'title'       =>    __( 'Auto Capture Time (Seconds)', 'woocommerce' ),
			  'type'        =>    'text',
			  'description' =>    __( 'This is the setting for when auto capture would occur after authorization',
				  'woocommerce' ),
			  'default'     =>    '0',
			  'desc_tip'    =>    true
			  ),

			'checkoutapipayment_timeout' => array(
			  'title'       =>    __( 'Timeout (Seconds)', 'woocommerce' ),
			  'type'        =>   'text',
			  'description' =>   __( 'This is the setting for time out value for a request to the gateway',
				  'woocommerce' ),
			  'default'     =>  '60',
			  'desc_tip'    =>  true
			  ),

			'checkoutapipayment_endpoint' => array(
			  'title'       =>    __( 'Endpoint Mode URL', 'woocommerce' ),
			  'type'        =>    'select',
			  'description' =>    __( 'This is the setting for identifying the API URL used (dev/preprod/live)',
				  'woocommerce' ),
			  'desc_tip'    =>    true,
			  'default'     =>  'sandbox',
			  'options'     =>    array(
								
								  'sandbox' =>    'Sandbox',
								  'live'    =>    'Live'
				  )
			 ),

            'checkoutapipayment_lp' => array(
                'title'         =>    __( 'Enable Local Payment?', 'woocommerce' ),
                'type'          =>    'select',
                'description'   =>    __( 'Please select whether to enable local payment', 'woocommerce' ),
                'desc_tip'      =>    true,
                'options'       =>    array(
                    'yes'    =>    'YES',
                    'no'     =>    'NO',
                ),
                'default'     =>     'no'
            ),

            'checkoutapipayment_ispci' => array(
                'title'         =>    __( 'Is PCI Compliance?', 'woocommerce' ),
                'type'          =>    'select',
                'description'   =>    __( 'Please select whether you are PCI Compliance', 'woocommerce' ),
                'desc_tip'      =>    true,
                'options'       =>    array(
                    'yes'    =>    'YES',
                    'no'     =>    'NO',
                ),
                'default'     =>     'no'
             ),

            'advanced' => array(
                'title'       => __( 'Advanced options for Checkout.js', 'woocommerce' ),
                'type'        => 'title',
                'description' => '',
            ),

            'checkoutapipayment_logoUrl' => array(
             'title'       =>    __( 'Logo URL', 'woocommerce' ),
             'type'        =>   'text',
             'description' =>   __( 'This is the setting to display the logo on checkout.js',
                'woocommerce' ),
             'default'     =>  '',
             'desc_tip'    =>  true
            ),

            'checkoutapipayment_themeColor' => array(
                'title'       =>    __( 'Theme color', 'woocommerce' ),
                'type'        =>   'text',
                'description' =>   __( 'Set theme color for checkout.js',
                    'woocommerce' ),
                'default'     =>  '',
                'desc_tip'    =>  true
            ),

            'checkoutapipayment_buttonColor' => array(
                'title'       =>    __( 'Button color', 'woocommerce' ),
                'type'        =>   'text',
                'description' =>   __( 'Set color for Pay now button',
                    'woocommerce' ),
                'default'     =>  '',
                'desc_tip'    =>  true
            ),

            'checkoutapipayment_iconColor' => array(
                'title'       =>    __( 'Icon color', 'woocommerce' ),
                'type'        =>   'text',
                'description' =>   __( 'Set icon color for checkout.js',
                    'woocommerce' ),
                'default'     =>  '',
                'desc_tip'    =>  true
            ),


            'checkoutapipayment_currencyCode' => array(
                'title'         =>    __( 'Widget currency format', 'woocommerce' ),
                'type'          =>    'select',
                'description'   =>    __( 'Display currency code or currency symbol on the js', 'woocommerce' ),
                'desc_tip'      =>    true,
                'options'       =>    array(
                    'true'    =>    'Code',
                    'false'     =>    'Symbol',
                ),
                'default'     =>     'false'
             )

		);
	}

	public function payment_fields()
	{
		return $this->_methodInstance->payment_fields();
	}

	public function process_payment($order_id)
	{
		return $this->_methodInstance->process_payment($order_id);
	}

    protected function _setInstanceMethod()
    {
        $configType = CHECKOUTAPI_ISPCI;
        if($configType) {
            switch ($configType) {
                case 'yes':
                    $this->_methodType = 'models_methods_creditcardpci';
                    break;
                case 'no':
                    $this->_methodType = 'models_methods_creditcard';
                    break;
                default:
                    $this->_methodType = 'models_methods_creditcard';
                    break;
            }
        } else {
            throw new Exception('Invalid method type');
            exit;
        }

        if(!$this->_methodInstance) {
            $this->_methodInstance =  models_FactoryInstance::getInstance( $this->_methodType );
        }

        return  $this->_methodInstance;

    }

    private function _init()
    {
		$this->id               = 'checkoutapipayment';
		$this->has_fields       = true;
		$this->method_title     = __( 'Credit Card (Checkout.com)', 'woocommerce' );
		$this->init_form_fields();
		$this->title            = $this->get_option( 'title' );
		$this->checkoutapipayment_secretkey     = $this -> get_option ('checkoutapipayment_secretkey');
		$this->checkoutapipayment_publickey     = $this -> get_option ('checkoutapipayment_publickey');
		$this->checkoutapipayment_paymentaction = $this -> get_option ('checkoutapipayment_paymentaction');
		$this->checkoutapipayment_cardtype      = $this -> get_option ('checkoutapipayment_cardtype');
		$this->checkoutapipayment_autoCaptime   = $this -> get_option ('checkoutapipayment_autoCaptime');
		$this->checkoutapipayment_timeout       = $this -> get_option ('checkoutapipayment_timeout');
		$this->checkoutapipayment_endpoint      = $this -> get_option ('checkoutapipayment_endpoint');
        $this->checkoutapipayment_ispci         = $this -> get_option ('checkoutapipayment_ispci');
        $this->checkoutapipayment_lp            = $this -> get_option ('checkoutapipayment_lp');
        $this->checkoutapipayment_logoUrl       = $this -> get_option ('checkoutapipayment_logoUrl');
        $this->checkoutapipayment_themeColor    = $this -> get_option ('checkoutapipayment_themeColor');
        $this->checkoutapipayment_buttonColor   = $this -> get_option ('checkoutapipayment_buttonColor');
        $this->checkoutapipayment_iconColor     = $this -> get_option ('checkoutapipayment_iconColor');
        $this->checkoutapipayment_currencyCode  = $this -> get_option ('checkoutapipayment_currencyCode');

    }
}
