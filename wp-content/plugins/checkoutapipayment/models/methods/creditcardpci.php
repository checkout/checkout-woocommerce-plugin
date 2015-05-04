<?php

 class models_methods_creditcardpci extends models_methods_Abstract
 {

 	protected $_code = 'creditcardpci';

 	public function __construct()
    {
 		$this ->id = 'checkoutapipayment';
 		$this->has_fields = true;
 		$this->checkoutapipayment_ispci = 'yes';
 		$this->supports[] = 'default_credit_card_form';
 		//parent::__construct();
 	}

 	public function _initCode(){}

 	public function payment_fields()
    {

 	     $this->credit_card_form();
 	
 	}

 	public function validate_fields()
    {
 		$this->credit_card_form();
 	}

 	public function process_payment($order_id)
    {

 		global $woocommerce;
 		$order = new WC_Order( $order_id );
		$grand_total = $order->order_total;
		$amount = $grand_total*100;
		$config['authorization'] = CHECKOUTAPI_SECRET_KEY;

		$config['timeout'] = CHECKOUTAPI_TIMEOUT;
		$config['postedParam'] = array('email' =>$order->billing_email,
			'value'       =>    $amount,
			'currency'    =>    $order->order_currency,
                        'trackId'     =>    $order_id,
			'description' =>    "Order number::$order_id",
		);

		$extraConfig = array();
		if(CHECKOUTAPI_PAYMENTACTION == 'Capture'){
			$extraConfig = parent::_captureConfig();
		}
		else {
			$extraConfig= parent::_authorizeConfig();
		}

		$config['postedParam'] = array_merge($config['postedParam'],$extraConfig);

		$cardnumber = preg_replace('/\D/', '', parent::get_post($this->id.'-card-number'));
		$cardexpiry = explode(" / ", parent::get_post($this->id.'-card-expiry')) ;


		if($errors = $this->validateCard($cardnumber, parent::get_post($this->id.'-card-expiry'),
			parent::get_post($this->id.'-card-cvc'))) {

			foreach($errors as $error) {
				wc_add_notice( __('Payment error: ', 'woothemes') . $error, 'error' );
			}

			return $errors;
		}
		$config['postedParam']['card'] = array(
			'name'          =>    $order->billing_first_name .' '.$order->billing_last_name,
			'number'        =>    $cardnumber,
			'expiryMonth'   =>    $cardexpiry[0],
            'expiryYear'    =>    $cardexpiry[1],
            'cvv'           =>    parent::get_post($this->id.'-card-cvc'),
		);
		
		$config['postedParam']['card']['billingdetails'] = array(
			'addressline1'  =>    $order->billing_address_1,
			'addressline2'  =>    $order->billing_address_2,
			'city'          =>    $order->billing_city,
			'country'       =>    $order->billing_country,
			'phone'         =>    array('number' => $order->billing_phone),
			'postcode'      =>    $order->billing_postcode,
			'state'         =>    $order->billing_state
		);
		
		$config['postedParam']['shippingdetails'] = array(
			'addressline1'    =>    $order->shipping_address_1,
			'addressline2'    =>    $order->shipping_address_2,
			'city'            =>    $order->shipping_city,
			'country'         =>    $order->shipping_country,
			'postcode'        =>    $order->shipping_postcode,
			'state'           =>    $order->shipping_state
		);

		$respondCharge = parent::_createCharge($config);
		return parent::_validateChrage($order, $respondCharge);

 	}
	 public function validateCcNumOther($ccNumber)
	 {
		 return preg_match('/^\\d+$/', $ccNumber);
	 }

	 /*
	 *	validateCard($cardnumber)
	 * 	Checks mod10 check digit of card, returns true if valid
	 */
	 function validateCard($cardnumber,$expiration,$cvv)
	 {
		 $cardnumber = preg_replace("/\D|\s/", "", $cardnumber);  # strip any non-digits
		 $cardlength = strlen($cardnumber);
		 $errors = array();
		 if ($cardlength != 0)
		 {
			 $parity = $cardlength % 2;
			 $sum = 0;
			 for ($i = 0; $i < $cardlength; $i++)
			 {
				 $digit = $cardnumber[$i];
				 if ($i % 2 == $parity) $digit = $digit * 2;
				 if ($digit > 9) $digit = $digit-9;
				 $sum = $sum + $digit;
			 }
			 $valid = ($sum % 10 == 0);
			if(!$valid) {
				$errors[] = __('Invalid Card number');
			}
		 }elseif(!$cardlength) {
			 $errors[] = __('Please provide a valid card number');
		 }

		 // Check that the date is valid
		 $dateSplit = explode("/",$expiration);
		 $myMonth =(int) trim($dateSplit[0]);
		 $myYear = (int)2000 + $dateSplit[1];
		 $myExpDate = strtotime(  "01-".$myMonth .'-'. $myYear);
		 $myToday = date("d/m/Y");
		 $myTodayNum = strtotime($myToday);


		 if ($myExpDate < $myTodayNum ) {
			 $errors[] = __('The expiration date you entered is invalid');
		 }

		 if (!$cvv ) {
			 $errors[] = __('Invalid Cvv number');
		 }
		 return empty($errors)?false:$errors;
	 }


	 public function validateCcNum($ccNumber)
	 {
		 $cardNumber = strrev($ccNumber);
		 $numSum = 0;

		 for ($i=0; $i<strlen($cardNumber); $i++) {
			 $currentNum = substr($cardNumber, $i, 1);

			 /**
			  * Double every second digit
			  */
			 if ($i % 2 == 1) {
				 $currentNum *= 2;
			 }

			 /**
			  * Add digits of 2-digit numbers together
			  */
			 if ($currentNum > 9) {
				 $firstNum = $currentNum % 10;
				 $secondNum = ($currentNum - $firstNum) / 10;
				 $currentNum = $firstNum + $secondNum;
			 }

			 $numSum += $currentNum;
		 }

		 /**
		  * If the total has no remainder it's OK
		  */
		 return ($numSum % 10 == 0);
	 }
 }
