<?php
	
abstract class models_methods_Abstract extends WC_Payment_Gateway implements models_InterfacePayment
{


	public function getCode()
	{
        return $this->_code;
    }

	public function admin_options(){}
	public function init_form_fields(){}
	public function payment_fields(){}
	public function process_payment($order_id){}


	protected function _createCharge($config)
    {
        $Api = CheckoutApi_Api::getApi(array('mode'=>CHECKOUTAPI_ENDPOINT));
        return $Api->createCharge($config);
    }

    protected function _validateChrage($order,$respondCharge)
    {	


	    if($respondCharge->isValid()){

		    $Api = CheckoutApi_Api::getApi(
		    	array('mode' 		=> CHECKOUTAPI_ENDPOINT,
		    		'authorization' => CHECKOUTAPI_SECRET_KEY)
		    	);
		    
		    $chargeUpdated = $Api->updateTrackId($respondCharge,$order->id);

			if (preg_match('/^1[0-9]+$/', $respondCharge->getResponseCode())) {

				$order->payment_complete ( $respondCharge->getId () );

				$order->add_order_note ( sprintf ( __ ( 'Checkout.com Credit Card Payment Approved - ChargeID: %s with Response Code: %s' , 'woocommerce' ) ,
					$respondCharge->getId () , $respondCharge->getResponseCode () ) );

				if (is_user_logged_in()) {

					$user_ID = get_current_user_id();
					Datalayer_Sql_installer::saveChargeDetails($respondCharge,$user_ID);
				}

				return array (
					'result' => 'success' ,
					'redirect' => $this->get_return_url ( $order )
				);
			}else {

				$order->add_order_note( sprintf(__('Checkout.com Credit Card Payment Declined - Error Code: %s, Decline Reason: %s', 'woocommerce'),
					$respondCharge->getId(), $respondCharge->getExceptionState()->getErrorMessage()));

				$error_message = 'The transaction was declined. Please check your Payment Details';
				wc_add_notice( __('Payment error: ', 'woothemes') . $error_message, 'error' );
				return;
			}

		} else {
			$order->add_order_note( sprintf(__('Checkout.com Credit Card Payment Declined - Error Code: %s, Decline Reason: %s', 'woocommerce'),
				$respondCharge->getErrorCode(), $respondCharge->getMessage()));

			$error_message = 'The transaction was declined. Please check your Payment Details';
			wc_add_notice( __('Payment error: ', 'woothemes') . $error_message, 'error' );
			return;

		}
    }

    protected function get_post( $name )
    {
		if (isset($_POST[ $name ])){
				return $_POST[ $name ];
		}
		return null;
	}

    protected function _captureConfig()
    {
        $to_return['postedParam'] = array (
            'autoCapture'    =>    CheckoutApi_Client_Constant::AUTOCAPUTURE_CAPTURE,
            'autoCapTime'    =>    CHECKOUTAPI_AUTOCAPTIME
        );
        return $to_return;
    }

    protected function _authorizeConfig()
    {
        $to_return['postedParam'] = array (
            'autoCapture'    =>    CheckoutApi_Client_Constant::AUTOCAPUTURE_AUTH,
            'autoCapTime'    =>    0
        );
        return $to_return;
    }
}
