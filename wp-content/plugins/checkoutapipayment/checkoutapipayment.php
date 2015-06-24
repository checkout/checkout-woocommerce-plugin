<?php
/*
Plugin Name: Checkout.com Payment Gateway (GW 3.0) 
Description: Add Checkout.com Payment Gateway (GW 3.0) for WooCommerce. 
Version: 1.0.3
Author: Checkout Integration Team
Author URI: http://www.checkout.com
*/

require_once 'autoload.php';

//include ("models/Checkoutapi.php");



add_action( 'plugins_loaded', 'checkoutapipayment_init', 0);
add_action( 'plugins_loaded', array( 'WC_Gateway_checkoutapipayment', 'get_instance' ) );

DEFINE ('PLUGIN_DIR', plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) .
	'/' );

define('PLUGIN_DIR_PATH', WP_PLUGIN_DIR . '/checkoutapipayment/checkoutapipayment.php');
register_activation_hook( PLUGIN_DIR_PATH,array('Datalayer_Sql_installer','install'));
//install();
function checkoutapipayment_init()
{

	function add_checkoutapipayment_gateway ( $methods )
	{
		$methods[ ] = 'WC_Gateway_checkoutapipayment';
		return $methods;
	}

	add_filter ( 'woocommerce_payment_gateways' , 'add_checkoutapipayment_gateway' );


	class WC_Gateway_checkoutapipayment extends models_Checkoutapi
	{

		protected $_methodType;
		protected $_methodInstance;
		protected static $_instance;

		public function __construct ()
		{
			add_action ( 'valid-checkoutapipayment-webhook' , array ( $this , 'valid_webhook' ) );
            add_action ( 'valid-success-page' , array ( $this , 'success_page' ) );
			parent::__construct ();
		}

		public static function get_instance() {
			// If the single instance hasn't been set, set it now.

			if ( null == self::$_instance ) {
				self::$_instance = new self;
			}
			return self::$_instance;
		}

		public function _initCode ()
		{
			$this->_code = $this->_methodInstance->getCode ();
		}

		public function admin_options ()
		{
			parent::admin_options ();
		}

		public function init_form_fields ()
		{
			parent::init_form_fields ();
		}

		public function payment_fields ()
		{
			return parent::payment_fields ();
		}

		public function process_payment ( $order_id )
		{
			return parent::process_payment ( $order_id );
		}

        public function success_page()
        {

                if ( !session_id() )
                    session_start();
                global $woocommerce;
                $order_id = $_SESSION['trackId'];
                $order = new WC_Order($order_id);
                $paymentToken =  $_SESSION['cko_cc_paymenToken'];
                $config['authorization'] = CHECKOUTAPI_SECRET_KEY;

                $config['paymentToken'] = $paymentToken;
                $Api = CheckoutApi_Api::getApi(array('mode'=>CHECKOUTAPI_ENDPOINT));

                $objectCharge = $Api->verifyChargePaymentToken($config);

                if (preg_match('/^1[0-9]+$/', $objectCharge->getResponseCode())) {


                    $modelOrder = wc_get_order ( $order_id );

                    if ( $objectCharge->getStatus () =='Captured' ) {
                        if($modelOrder->get_status() !='completed' && $modelOrder->get_status() !='cancel') {

                            $modelOrder->update_status ( 'wc-completed' , __ ( 'Order status changed by webhook' , 'woocommerce'
                            ) );

                        }
                        $returnURL = $this->get_return_url( $order );
                        header('Location: '.$returnURL);

                    } elseif ($objectCharge->getStatus() !='Authorised' ) {

                        if( $modelOrder->get_status() !='cancel') {
                            $modelOrder->update_status ( 'pending' , __ ( 'Order status changed by webhook:' , 'woocommerce' ) );
                            header('Location: '.$woocommerce->cart->get_checkout_url());

                        }

                    }else {
                        $returnURL = $this->get_return_url( $order );
                        header('Location: '.$returnURL);
                    }
                }

                $customerConfig['authorization'] = CHECKOUTAPI_SECRET_KEY;
                $customerConfig['customerId'] = $objectCharge->getCard()->getCustomerId() ;
                $customerConfig['postedParam'] = array ('phone'=>array('number'=>$order -> billing_phone));
                $customerCharge = $Api->updateCustomer($customerConfig);

        }

		public function valid_webhook ()
		{
			if(isset($_GET['chargeId'])) {
				$stringCharge = $this->_process();
			}else {
				$stringCharge = file_get_contents ( "php://input" );
			}
			$Api = CheckoutApi_Api::getApi ( array ( 'mode' => $this->checkoutapipayment_endpoint ) );

			$objectCharge = $Api->chargeToObj ( $stringCharge );
			

			if (preg_match('/^1[0-9]+$/', $objectCharge->getResponseCode())) {
				//  $this->load->model('sale/order');
				/*
				* Need to get track id
				*/
				$order_id = $objectCharge->getTrackId();

				$modelOrder = wc_get_order ( $order_id );

				if ( $objectCharge->getCaptured ()  ) {
					if($modelOrder->get_status() !='completed' && $modelOrder->get_status() !='cancel') {

					$modelOrder->update_status ( 'wc-completed' , __ ( 'Order status changed by webhook' , 'woocommerce'
					) );
						echo "Order has been captured";
					}else {
						echo "Order has already been captured";
					}

				} elseif (  $objectCharge->getRefunded () ) {
					if( $modelOrder->get_status() !='cancel') {
						$modelOrder->update_status ( 'wc-refunded' , __ ( 'Order status changed by webhook' , 'woocommerce' ) );
						echo "Order has been refunded";


					}else {
						echo "Order has already been refunded";
					}

				} elseif ( !$objectCharge->getAuthorised() ) {

					if( $modelOrder->get_status() !='cancel') {
						$modelOrder->update_status ( 'wc-cancelled' , __ ( 'Order status changed by webhook:' , 'woocommerce' ) );
						$modelOrder->cancel_order();
						echo "Order has been cancel";
					}

				}else {
					echo "Payment status is still ".$objectCharge->getStatus();
				}
			}
			exit();
		}

		private function _process ()
		{
			$config[ 'chargeId' ] = $_GET[ 'chargeId' ];
			$config[ 'authorization' ] = $this->checkoutapipayment_secretkey;
			$Api = CheckoutApi_Api::getApi ( array ( 'mode' => $this->checkoutapipayment_endpoint ) );
			$respondBody = $Api->getCharge ( $config );

			$json = $respondBody->getRawOutput ();
			return $json;
		}

		public static function  install()
		{

		}

	}

	function woocommerce_checkoutapipayment_webhook ()
	{
		if ( !empty( $_GET[ 'checkoutapipaymentListener' ] ) && $_GET[ 'checkoutapipaymentListener' ] ==
			'checkoutapi_payment_Listener'
		) {

			WC ()->payment_gateways ();

			do_action ( 'valid-checkoutapipayment-webhook' );
		}
	}

    function woocommerce_checkoutapipayment_success_valid ()
    {
        $urlvars = explode('/', $_SERVER['REQUEST_URI']);
         $nextUrl = explode('?',$urlvars[1]);

        if ( !empty( $nextUrl[0]) && $nextUrl[0] ==
            'checkoutapipaymentSuccessValidate'
        ) {

            do_action ( 'valid-success-page' );
            die();
        }



    }

	add_action ( 'init' , 'woocommerce_checkoutapipayment_webhook' );
    add_action ( 'init' , 'woocommerce_checkoutapipayment_success_valid' );

}
