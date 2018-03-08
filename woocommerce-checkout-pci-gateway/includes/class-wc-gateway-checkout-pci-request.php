<?php

/**
 * Class WC_Checkout_Pci_Request
 *
 * @version 20160304
 */
class WC_Checkout_Pci_Request
{
    /**
     * Constructor
     *
     * WC_Checkout_Pci_Request constructor.
     * @param WC_Checkout_Pci $gateway
     *
     * @version 20160304
     */
    public function __construct(WC_Checkout_Pci $gateway) {
        $this->gateway = $gateway;
    }

    /**
     * Get stored 3d mode
     *
     * @return mixed
     *
     * @version 20171127
     */
    public function getChargeMode() {
        return $this->gateway->get_option('is_3d');
    }

     /**
     * Get stored autoCapTime
     *
     * @return mixed
     *
     * @version 20171127
     */
    public function getAutoCapTime() {
        $autoCapTime = $this->gateway->get_option('auto_cap_time');
        
        if(empty($autoCapTime)){
            $autoCapTime = 0.02;
        }

        if (strpos($autoCapTime, ',') !== false) {
             $autoCapTime = str_replace(',', '.', $autoCapTime);
        }

        return $autoCapTime;
    }

    /**
     * Get stored reactivate_cancel
     *
     * @return mixed
     *
     * @version 20180221
     */
    public function getRecurringSetting() {
        return $this->gateway->get_option('reactivate_cancel');
    }

    /**
     * Create Charge
     *
     * @param WC_Order $order
     * @param array $ccParams
     * @param $savedCardData
     * @return array
     *
     * @version 20160312
     */
    public function createCharge(WC_Order $order, array $ccParams, $savedCardData) {
        $amount     = $order->get_total();
        $Api        = CheckoutApi_Api::getApi(array('mode' => $this->_getEndpointMode()));

        $amount     = $Api->valueToDecimal($amount, $this->getOrderCurrency($order));
        $chargeData = $this->_getChargeData($order, $ccParams, $amount, $savedCardData);

        $result     = $Api->createCharge($chargeData);

        if ($Api->getExceptionState()->hasError()) {
            $errorMessage = 'Your payment was not completed. Try again or contact customer support.';

            WC_Checkout_Pci::log($errorMessage);
            WC_Checkout_Pci::log($Api->getExceptionState());
            return array('error' => $errorMessage);
        }

        if (!$result->isValid() || !WC_Checkout_Pci_Validator::responseValidation($result)) {
            $errorMessage = 'Please check you card details and try again. Thank you.';

            WC_Checkout_Pci::log($errorMessage);
            WC_Checkout_Pci::log($Api->getExceptionState());
            return array('error' => $errorMessage);
        }

        return $result;
    }

    /**
     * Return decorated data for create charge request
     *
     * @param WC_Order $order
     * @param array $ccParams
     * @param $amount
     * @param $savedCardData
     * @return mixed
     *
     * @version 20160312
     */
    private function _getChargeData(WC_Order $order, array $ccParams, $amount, $savedCardData) {
        global $woocommerce;
        $cart = WC()->cart;
        $secretKey = $this->getSecretKey();
        $Api        = CheckoutApi_Api::getApi(array('mode' => $this->_getEndpointMode()));
        $config         = array();
        $autoCapture    = $this->_isAutoCapture();

        /* START: Prepare data */
        $billingAddressConfig = array (
            'addressLine1'  => $order->billing_address_1,
            'addressLine2'  => $order->billing_address_2,
            'postcode'      => $order->billing_postcode,
            'country'       => $order->billing_country,
            'city'          => $order->billing_city,
            'state'         => $order->billing_state,
            'phone'         => array('number' => $order->billing_phone)
        );

        $shippingAddressConfig = array(
            'addressLine1'  => !empty($order->shipping_address_1) ? $order->shipping_address_1 : $order->billing_address_1,
            'addressLine2'  => !empty($order->shipping_address_2) ? $order->shipping_address_2 : $order->billing_address_2,
            'postcode'      => !empty($order->shipping_postcode) ? $order->shipping_postcode : $order->billing_postcode,
            'country'       => !empty($order->shipping_country) ? $order->shipping_country : $order->billing_country,
            'city'          => !empty($order->shipping_city) ? $order->shipping_city : $order->billing_city,
            'state'         => !empty($order->shipping_state) ? $order->shipping_state : $order->billing_state,
            'phone'         => array('number' => $order->billing_phone),
        );

        $products       = array();
        $productFactory = new WC_Product_Factory();

        foreach ($order->get_items() as $item) {
            $product        = $productFactory->get_product($item['product_id']);

            $productPrice = $product->get_price();
            if(is_null($productPrice)){
                $productPrice = 0;
            }

            $products[] = array(
                'name'          => $item['name'],
                'price'         => $productPrice,
                'quantity'      => $item['qty'],
                'sku'           => $product->get_sku()
            );
        }

        /* END: Prepare data */

        $config['autoCapture']  = $autoCapture ? CheckoutApi_Client_Constant::AUTOCAPUTURE_CAPTURE : CheckoutApi_Client_Constant::AUTOCAPUTURE_AUTH;
        $config['value']                = $amount;
        $config['currency']             = $this->getOrderCurrency($order);
        $config['trackId']              = $order->id;
        $config['transactionIndicator'] = WC_Checkout_Pci::TRANSACTION_INDICATOR_REGULAR;
        $config['customerIp']           = $this->get_ip_address();
        $config['chargeMode']           = $this->getChargeMode();
        $config['autoCapTime']          = $this->getAutoCapTime();

        if (!empty($savedCardData)) {
            $config['cardId'] = $savedCardData->card_id;

            global $wpdb;
            $tableName = $wpdb->prefix. 'checkout_customer_cards';
            $sql        = $wpdb->prepare("SELECT * FROM {$tableName} WHERE card_id = '%s';", $savedCardData->card_id);

            $result = $wpdb->get_results($sql);

            foreach ($result as $row) {
                $results = $row;
            }

            if($results->cko_customer_id){
                $config['customerId'] = $results->cko_customer_id;
            } else {
                $config['email'] = $order->billing_email;
            }

        } else {
            $config['email'] = $order->billing_email;
            $config['card'] = array(
                'name'              => $ccParams['ccName'],
                'number'            => $ccParams['ccNumber'],
                'expiryMonth'       => $ccParams['ccMonth'],
                'expiryYear'        => $ccParams['ccYear'],
                'cvv'               => $ccParams['ccCvc'],
                'billingDetails'    => $billingAddressConfig
            );
        }

        $config['shippingDetails']  = $shippingAddressConfig;
        $config['products']         = $products;

        /* Meta */
        $config['metadata'] = array(
            'server'            => get_site_url(),
            'quote_id'          => $order->id,
            'woo_version'       => property_exists($woocommerce, 'version') ? $woocommerce->version : '2.0',
            'plugin_version'    => WC_Checkout_Pci::VERSION,
            'lib_version'       => CheckoutApi_Client_Constant::LIB_VERSION,
            'integration_type'  => 'API',
            'time'              => date('Y-m-d H:i:s')
        );

        if (class_exists('WC_Subscriptions_Order')) {
            $isSubscription = WC_Subscriptions_Order::order_contains_subscription($order);

            if ($isSubscription) {
                $recurringAmt = number_format(WC_Subscriptions_Order::get_recurring_total($order, $product_id = ''), 2, '.', '');
                $recurringAmount = $Api->valueToDecimal($recurringAmt, $this->getOrderCurrency($order));
                $intervalType = $this->getRecurringData()->intervalType;
                $recurringStartDate = $this->getRecurringData()->recurringStartDate;
                $interval = $this->getRecurringData()->interval;
                $recurringCount = $this->getRecurringData()->recurringCount;
                // Additional param to process recurring charge
                $config['paymentPlans'] = array(array(
                    'name' => $order->billing_first_name . '_' . $order->id,
                    'planTrackId' => $order->id,
                    'value' => $recurringAmount,
                    'cycle' => $interval . '' . $intervalType,
                    'recurringCount' => $recurringCount,
                    'autoCapTime' => WC_Checkout_Pci::AUTO_CAPTURE_TIME,
                    'startDate' => $recurringStartDate //Next day of initial payment
                ));
            }
        }

        $result['authorization']    = $secretKey;
        $result['postedParam']      = $config;

        return $result;
    }

    public function getRecurringData(){

        // Get recurring data
        $intervalType = WC_Subscriptions_Cart::get_cart_subscription_period();
        $interval = WC_Subscriptions_Cart::get_cart_subscription_interval();
        $subscriptionLength = WC_Subscriptions_Cart::get_cart_subscription_length();

        if($subscriptionLength == 0){
            switch($intervalType)
            {
                case 'month';
                    $subscriptionLength = 83;
                    break;

                case 'day';
                    $subscriptionLength = 6993;
                    break;

                case 'week';
                     $subscriptionLength = 999;
                     break;

                case 'year';
                     $subscriptionLength = 19;
                     break;
            }
        }
        
        $recurringCount = $subscriptionLength - 1;

        // Get trial Info
        $trialLength = WC_Subscriptions_Cart::get_cart_subscription_trial_length();
        $trialPeriod = WC_Subscriptions_Cart::get_cart_subscription_trial_period();

        $cyclePeriod = 1;
        $transactionDate = time();

        switch($intervalType)
        {
            case 'month';
                $intervalType = 'm';
                $datetime = strtotime("+{$cyclePeriod} Month",$transactionDate);
                $recurringStartDate =  date('Y-m-d',$datetime);
                break;

            case 'day';
                $intervalType = 'd';
                $datetime = strtotime("+{$cyclePeriod} DAY",$transactionDate);
                $recurringStartDate =  date('Y-m-d',$datetime);
                break;

            case 'week';
                $intervalType = 'w';
                $datetime = strtotime("+{$cyclePeriod} week",$transactionDate);
                $recurringStartDate =  date('Y-m-d',$datetime);
                break;

            case 'year';
                $intervalType = 'y';
                $datetime = strtotime("+{$cyclePeriod} year",$transactionDate);
                $recurringStartDate =  date('Y-m-d',$datetime);
                break;
        }

        // get trial end period
        // d or m or y
        // Trial end day > $recurringStartDate, the $recurringStartDate = Trial end day

        $obj = (object) array(
            'intervalType' => $intervalType,
            'recurringStartDate' => $recurringStartDate,
            'interval' => $interval,
            'recurringCount' => $recurringCount
        );
        return $obj;
    }

    /**
     * Return Payment Action Type
     *
     * @return string
     *
     * @version 20160304
     */
    private function _isAutoCapture() {
        return $this->gateway->get_option('payment_action') === WC_Checkout_Pci::PAYMENT_ACTION_AUTHORIZE
            ? false : true;
    }

    /**
     * return Charge Mode
     *
     * @return int
     *
     * @version 20160215
     */
    private function _getChargeMode() {
        return WC_Checkout_Pci::CREDIT_CARD_CHARGE_MODE_NOT_3D;
    }

    /**
     * Convert Date form mm/yy format
     *
     * @param $ccExpiry
     * @return mixed
     *
     * @version 20160312
     */
    public function getCcExpiryDate($ccExpiry) {
        $ccDate     = explode('/', $ccExpiry);
        $result['ccMonth']  = !empty($ccDate[0]) ? trim($ccDate[0]) : 0;
        $result['ccYear']   = !empty($ccDate[1]) ? trim($ccDate[1]) : 0;

        $result['ccYear'] = strlen((string)$result['ccYear']) == 2 ? '20' . $result['ccYear'] : $result['ccYear'];

        return $result;
    }

    /**
     * Return Endpoint Mode from configuration
     *
     * @return mixed
     *
     * @version 20160313
     */
    protected function _getEndpointMode(){
        return $this->gateway->get_option('mode');
    }

    /**
     * Return order status for new order
     *
     * @return mixed
     *
     * @version 20160315
     */
    public function getOrderStatus() {
        return $this->gateway->get_option('order_status');
    }

    /**
     * Check if cancel order needed after void
     *
     * @return bool
     *
     * @version 20160316
     */
    public function getVoidOrderStatus() {
        return $this->gateway->get_option('void_status') == 'no' ? false : true;
    }

    /**
     * Capture Charge on Checkout.com
     *
     * @param WC_Order $order
     * @return array
     *
     * @version 20160315
     */
    public function capture(WC_Order $order) {
        include_once('class-wc-gateway-checkout-pci-validator.php');

        $Api        = CheckoutApi_Api::getApi(array('mode' => $this->_getEndpointMode()));
        $amount     = $Api->valueToDecimal($order->get_total(), $this->getOrderCurrency($order));
        $response   = array('status' => 'ok', 'message' => __('Checkout.com Capture Charge Approved.', 'woocommerce-checkout-pci'));

        $config         = array();
        $orderId        = $order->id;
        $secretKey      = $this->getSecretKey();

        $config['postedParam']['value']         = $amount;
        $config['postedParam']['trackId']       = $orderId;
        $config['postedParam']['description']   = 'capture description';
        $trackIdList                            = get_post_meta($orderId, '_transaction_id');

        $config['authorization']    = $secretKey;
        $config['chargeId']         = end($trackIdList);

        $result = $Api->captureCharge($config);

        if ($Api->getExceptionState()->hasError()) {
            $errorMessage = __('Transaction was not captured. '. $Api->getExceptionState()->getErrorMessage(). ' and try again or contact customer support.',
                'woocommerce-checkout-pci');

            WC_Checkout_Pci::log($errorMessage);
            WC_Checkout_Pci::log($Api->getExceptionState());

            $response['status']     = 'error';
            $response['message']    = $errorMessage;

            return $response;
        }

        if (!$result->isValid() || !WC_Checkout_Pci_Validator::responseValidation($result)) {
            $errorMessage = __('Transaction was not captured. Try again or contact customer support.', 'woocommerce-checkout-pci');

            WC_Checkout_Pci::log($errorMessage);
            WC_Checkout_Pci::log($Api->getExceptionState());

            $response['status']     = 'error';
            $response['message']    = $errorMessage;

            return $response;
        }

        $entityId = $result->getId();

        update_post_meta($orderId, '_transaction_id', $entityId);

        $order->add_order_note(__("Checkout.com Capture Charge Approved (Transaction ID - {$entityId}, Parent ID - {$config['chargeId']})", 'woocommerce-checkout-pci'));

        if (function_exists('WC')) {
            $order->payment_complete();
        } else {
            // Record the sales
            $order->record_product_sales();

            // Increase coupon usage counts
            $order->increase_coupon_usage_counts();

            wp_set_post_terms($order->id, 'processing', 'shop_order_status', false);
            $order->add_order_note(sprintf( __( 'Order status changed from %s to %s.', 'woocommerce' ), __( $order->status, 'woocommerce' ), __('processing', 'woocommerce')));

            do_action('woocommerce_payment_complete', $order->id);

        }

        return $response;
    }

    /**
     * Void Charge on Checkout.com
     *
     * @param WC_Order $order
     * @return array
     *
     * @version 20160316
     */
    public function void(WC_Order $order) {
        include_once('class-wc-gateway-checkout-pci-validator.php');

        $response       = array('status' => 'ok', 'message' => __('Checkout.com your transaction has been successfully voided', 'woocommerce-checkout-pci'));
        $config         = array();
        $orderId        = $order->id;

        $config['postedParam']['trackId']       = $orderId;
        $config['postedParam']['description']   = 'Void Description';
        $trackIdList                            = get_post_meta($orderId, '_transaction_id');

        $config['authorization']    = $this->getSecretKey();
        $config['chargeId']         = end($trackIdList);

        $Api    = CheckoutApi_Api::getApi(array('mode' => $this->_getEndpointMode()));
        $result = $Api->voidCharge($config);

        if ($Api->getExceptionState()->hasError()) {
            $errorMessage = __('Transaction was not voided. '. $Api->getExceptionState()->getErrorMessage(). ' and try again or contact customer support.',
                'woocommerce-checkout-pci');

            WC_Checkout_Pci::log($errorMessage);
            WC_Checkout_Pci::log($Api->getExceptionState());

            $response['status']     = 'error';
            $response['message']    = $errorMessage;

            return $response;
        }

        if (!$result->isValid() || !WC_Checkout_Pci_Validator::responseValidation($result)) {
            $errorMessage = __('Transaction was not voided. Try again or contact customer support.', 'woocommerce-checkout-pci');

            WC_Checkout_Pci::log($errorMessage);

            $response['status']     = 'error';
            $response['message']    = $errorMessage;

            return $response;
        }

        $entityId = $result->getId();

        update_post_meta($orderId, '_transaction_id', $entityId);

        $successMessage = __("Checkout.com Void Charge Approved (Transaction ID - {$entityId}, Parent ID - {$config['chargeId']})", 'woocommerce-checkout-pci');

        if (!$this->getVoidOrderStatus()) {
            $order->add_order_note($successMessage);
        } else {
            if (function_exists('WC')) {
                $order->update_status('cancelled', $successMessage);
            } else {
                $order->decrease_coupon_usage_counts();
                wp_set_post_terms($order->id, 'cancelled', 'shop_order_status', false);
                $order->add_order_note(sprintf( __( 'Order status changed from %s to %s.', 'woocommerce' ), __( $order->status, 'woocommerce' ), __('processing', 'woocommerce')));
            }
        }

        return $response;
    }

    /**
     * Refund Charge on Checkout.com
     *
     * @param WC_Order $order
     * @param $amount
     * @param $message
     * @return array
     *
     * @version 20160316
     */
    public function refund(WC_Order $order, $amount, $message) {
        include_once('class-wc-gateway-checkout-pci-validator.php');

        $response = array('status' => 'ok', 'message' => __('Checkout.com your transaction has been successfully refunded', 'woocommerce-checkout-pci'));

        $Api    = CheckoutApi_Api::getApi(array('mode' => $this->_getEndpointMode()));
        $totalAmount = $order->get_total();
        $totalAmount = $Api->valueToDecimal($totalAmount, $this->getOrderCurrency($order));

        $amount = empty($amount) ? $order->get_total() : $amount;
        $amount = $Api->valueToDecimal($amount, $this->getOrderCurrency($order));

        $config         = array();
        $orderId        = $order->id;

        $config['postedParam']['trackId']       = $orderId;
        $config['postedParam']['description']   = (string)$message;
        $config['postedParam']['value']         = $amount;
        $trackIdList                            = get_post_meta($orderId, '_transaction_id');

        $config['authorization']    = $this->getSecretKey();
        $config['chargeId']         = end($trackIdList);

        $result = $Api->refundCharge($config);

        if ($Api->getExceptionState()->hasError()) {
            $errorMessage = __('Transaction was not refunded. '. $Api->getExceptionState()->getErrorMessage(). ' and try again or contact customer support.',
                'woocommerce-checkout-pci');

            WC_Checkout_Pci::log($errorMessage);
            WC_Checkout_Pci::log($Api->getExceptionState());

            $response['status']     = 'error';
            $response['message']    = $errorMessage;

            return $response;
        }

        if (!$result->isValid() || !WC_Checkout_Pci_Validator::responseValidation($result)) {
            $errorMessage = __('Transaction was not refunded. Try again or contact customer support.', 'woocommerce-checkout-pci');

            WC_Checkout_Pci::log($errorMessage);

            $response['status']     = 'error';
            $response['message']    = $errorMessage;

            return $response;
        }

        $entityId = $result->getId();

        update_post_meta($orderId, '_transaction_id', $entityId);

        $successMessage = __("Checkout.com Refund Charge Approved (Transaction ID - {$entityId}, Parent ID - {$config['chargeId']})", 'woocommerce-checkout-pci');

        if($totalAmount == $amount){
            $order->update_status('refunded', $successMessage);
        } else {
            $order->add_order_note( sprintf($successMessage) );
        }
        
        return $response;
    }

    /**
     * Check if order can be capture
     *
     * @param WC_Order $order
     * @return bool
     *
     * @version 20160314
     */
    public function canCapture(WC_Order $order) {
        $paymentMethod  = (string)get_post_meta($order->id, '_payment_method', true);

        if ($paymentMethod !== WC_Checkout_Pci::PAYMENT_METHOD_CODE) {
            return false;
        }

        return true;
    }

    /**
     * Check payment method code
     *
     * @param WC_Order $order
     * @return bool
     *
     * @version 20160316
     */
    public function canVoid(WC_Order $order) {
        $paymentMethod  = (string)get_post_meta($order->id, '_payment_method', true);

        if ($paymentMethod !== WC_Checkout_Pci::PAYMENT_METHOD_CODE) {
            return false;
        }

        return true;
    }

    /**
     * Verify Charge on Checkout.com
     *
     * @param $paymentToken
     * @return array
     *
     * @version 20160317
     */
    public function verifyCharge($paymentToken) {
        include_once('class-wc-gateway-checkout-pci-validator.php');
        include_once('class-wc-gateway-checkout-pci-customer-card.php');
        global $woocommerce;

        $Api            = CheckoutApi_Api::getApi(array('mode' => $this->_getEndpointMode()));
        $verifyParams   = array('paymentToken' => $paymentToken, 'authorization' => $this->getSecretKey());
        $result         = $Api->verifyChargePaymentToken($verifyParams);
        $response       = array('status' => 'ok', 'message' => '', 'object' => array());

        if ($Api->getExceptionState()->hasError()) {
            $errorMessage = __('Your payment was not completed.'. $Api->getExceptionState()->getErrorMessage(). ' and try again or contact customer support.', 'woocommerce-checkout-pci');

            WC_Checkout_Pci::log($errorMessage);
            WC_Checkout_Pci::log($Api->getExceptionState());

            $response['status']     = 'error';
            $response['message']    = $errorMessage;

            return $response;
        }

        if (!$result->isValid() || !WC_Checkout_Pci_Validator::responseValidation($result)) {
            $errorMessage = __('Please check you card details and try again. Thank you.', 'woocommerce-checkout-pci');

            WC_Checkout_Pci::log($errorMessage);

            $response['status']     = 'error';
            $response['message']    = $errorMessage;

            return $response;
        }

        $entityId   = $result->getId();
        $orderId    = $result->getTrackId();
        $order      = new WC_Order($orderId);

        if (!is_object($order) || !$order) {
            $errorMessage = 'Empty order data.';

            WC_Checkout_Pci::log($errorMessage);

            $response['status']     = 'error';
            $response['message']    = $errorMessage;

            return $response;
        }

        $order->update_status($this->getOrderStatus(), __("Checkout.com Charge Approved (Transaction ID - {$entityId}", 'woocommerce-checkout-pci'));
        $order->reduce_order_stock();
        $woocommerce->cart->empty_cart();

        update_post_meta($orderId, '_transaction_id', $entityId, true);

        if (is_user_logged_in()) {
            WC_Checkout_Pci_Customer_Card::saveCard($result, $order->user_id);
        }

        $response['object'] = $order;

        return $response;
    }

    /**
     * Return stored secret key
     *
     * @return mixed
     *
     * @version 20160321
     */
    public function getSecretKey() {
        return $this->gateway->get_option('secret_key');
    }

    /**
     * Get stored public key
     * @return mixed
     *
     * @version 20160321
     */
    public function getPublicKey(){
        return $this->gateway->get_option('public_key');
    }

    /**
     * Get current user IP Address.
     * @return string
     */
    public function get_ip_address() {
        if ( isset( $_SERVER['X-Real-IP'] ) ) {
            return $_SERVER['X-Real-IP'];
        } elseif ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            // Proxy servers can send through this header like this: X-Forwarded-For: client1, proxy1, proxy2
            // Make sure we always only send through the first IP in the list which should always be the client IP.
            return trim( current( explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
        } elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
            return $_SERVER['REMOTE_ADDR'];
        }
        return '';
    }

    /**
     * For old version
     *
     * @param WC_Order $order
     * @return string
     */
    public function getOrderCurrency(WC_Order $order) {
        if (method_exists($order, 'get_order_currency')) {
            return $order->get_order_currency();
        }

        if (property_exists($order, 'order_custom_fields') && !empty($order->order_custom_fields['_order_currency'])) {
            return $order->order_custom_fields['_order_currency'][0];
        }

        return get_woocommerce_currency();
    }
}