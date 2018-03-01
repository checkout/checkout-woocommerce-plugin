<?php

include_once ('lib/autoload.php');

/**
 * Class WC_Checkout_Pci
 *
 * @version 20160304
 */
class WC_Checkout_Pci extends WC_Payment_Gateway {

    const PAYMENT_METHOD_CODE       = 'woocommerce_checkout_pci';
    const PAYMENT_ACTION_AUTHORIZE  = 'authorize';
    const PAYMENT_ACTION_CAPTURE    = 'authorize_capture';
    const PAYMENT_CARD_NEW_CARD     = 'new_card';
    const AUTO_CAPTURE_TIME         = 0;
    const VERSION                   = '2.5.5';

    const CREDIT_CARD_CHARGE_MODE_NOT_3D    = 1;
    const TRANSACTION_INDICATOR_REGULAR     = 1;

    public static $log = false;

    /**
     * Constructor
     *
     * WC_Checkout_Pci constructor.
     *
     * @version 20160304
     */
    public function __construct() {
        $this->id                   = self::PAYMENT_METHOD_CODE;
        $this->method_title         = __("Checkout.com Credit Card (PCI Version)", 'woocommerce-checkout-pci');
        $this->method_description   = __("Checkout.com Credit Card (PCI Version) Plug-in for WooCommerce", 'woocommerce-checkout-pci');
        $this->title                = __("Checkout.com Credit Card (PCI Version)", 'woocommerce-checkout-pci');

        $this->icon         = null;
        $this->supports     = array(
            'products',
            'refunds',
            'subscriptions',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_cancellation'
        );
        $this->has_fields   = true;

        // This basically defines your settings which are then loaded with init_settings()
        $this->init_form_fields();
        $this->init_settings();

        // Turn these settings into variables we can use
        foreach ( $this->settings as $setting_key => $value ) {
            $this->$setting_key = $value;
        }

        // Check if saved cards is enabled from backend
        $this->saved_cards = $this->get_option( 'saved_cards' ) === "yes" ? true : false;

        // Save settings
        if (is_admin()) {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }

        add_action('woocommerce_subscription_status_on-hold',array($this, 'updatePlan'));
        add_action('woocommerce_subscription_status_active',array($this, 'updatePlan'));
        add_action('woocommerce_subscription_status_cancelled',array($this, 'updatePlan'));
    }

    /**
     * init admin settings form
     *
     * @version 20160304
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'		=> __( 'Enable / Disable', 'woocommerce-checkout-pci' ),
                'label'		=> __( 'Enable Payment Method', 'woocommerce-checkout-pci' ),
                'type'		=> 'checkbox',
                'default'	=> 'no',
            ),
            'title' => array(
                'title'		=> __('Title', 'woocommerce-checkout-pci'),
                'type'		=> 'text',
                'desc_tip'	=> __('Payment title the customer will see during the checkout process.', 'woocommerce-checkout-pci'),
                'default'	=> __( 'Credit Card (Checkout.com)', 'woocommerce-checkout-pci' ),
            ),
            'secret_key' => array(
                'title'		=> __('Secret Key', 'woocommerce-checkout-pci'),
                'type'		=> 'password',
                'desc_tip'	=> __( 'Only used for requests from the merchant server to the Checkout API', 'woocommerce-checkout-pci' ),
            ),
            'public_key' => array(
                'title'		=> __('Private Shared Key', 'woocommerce-checkout-pci'),
                'type'		=> 'password',
                'desc_tip'	=> __( 'Used for webhooks from Checkout API', 'woocommerce-checkout-pci' ),
            ),
            'void_status' => array(
                'title'		=> __( 'Enable / Disable', 'woocommerce-checkout-pci' ),
                'label'		=> __( 'When voided change order status to Cancelled', 'woocommerce-checkout-pci' ),
                'type'		=> 'checkbox',
                'default'	=> 'no',
            ),
            'payment_action' => array(
                'title'       => __('Payment Action', 'woocommerce-checkout-pci'),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'description' => __('Choose whether you wish to capture funds immediately or authorize payment only.', 'woocommerce-checkout-pci'),
                'default'     => 'authorize',
                'desc_tip'    => true,
                'options'     => array(
                    self::PAYMENT_ACTION_CAPTURE    => __('Authorize and Capture', 'woocommerce-checkout-pci'),
                    self::PAYMENT_ACTION_AUTHORIZE  => __('Authorize Only', 'woocommerce-checkout-pci')
                )
            ),
            'auto_cap_time' => array(
                'title'     => __('Auto Capture Time', 'woocommerce-checkout-pci'),
                'type'      => 'text',
                'desc_tip'  => __('Time to automatically capture charge. It is recommended to set it to a minimun of 0.02', 'woocommerce-checkout-pci'),
                'default'   => __( '0.02', 'woocommerce-checkout-pci' ),
            ),
            'order_status' => array(
                'title'       => __('New Order Status', 'woocommerce-checkout-pci'),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'default'     => 'on-hold',
                'desc_tip'    => true,
                'options'     => array(
                    'on-hold'    => __('On Hold', 'woocommerce-checkout-pci'),
                    'processing' => __('Processing', 'woocommerce-checkout-pci')
                )
            ),
            'mode' => array(
                'title'       => __('Endpoint URL mode', 'woocommerce-checkout-pci'),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'description' => __('When going on live production, Endpoint url mode should be set to live.', 'woocommerce-checkout-pci'),
                'default'     => 'sandbox',
                'desc_tip'    => true,
                'options'     => array(
                    'sandbox'   => __('SandBox', 'woocommerce-checkout-pci'),
                    'live'      => __('Live', 'woocommerce-checkout-pci')
                )
            ),
            'is_3d' => array(
                'title'       => __('Is 3D', 'woocommerce-checkout-pci'),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'description' => __('3D Secure Card Validation.', 'woocommerce-checkout-pci'),
                'default'     => '1',
                'desc_tip'    => true,
                'options'     => array(
                    '1' => __('No', 'woocommerce-checkout-pci'),
                    '2' => __('Yes', 'woocommerce-checkout-pci')
                )
            ),
            'saved_cards' => array(
                'title'       => __( 'Saved Cards', 'woocommerce-checkout-pci' ),
                'label'       => __( 'Enable Payment via Saved Cards', 'woocommerce-checkout-pci' ),
                'type'        => 'checkbox',
                'description' => __( 'If enabled, users will be able to pay with a saved card during checkout.', 'woocommerce-checkout-pci' ),
                'default'     => 'no'
            ),
            'adv_setting_subscription' => array(
                'title'       => __( 'Advance option for Subscription', 'woocommerce' ),
                'type'        => 'title',
            ),
            'reactivate_cancel' => array(
                'title'     => __( 'Enable / Disable', 'woocommerce-checkout-pci' ),
                'label'     => __( 'Allow customer to reactivate or cancel subscription from his/her Account', 'woocommerce-checkout-pci' ),
                'type'      => 'checkbox',
                'default'   => 'no',
                'desc_tip'  => __('Allow customer to reactivate or cancel subscription from his/her Account', 'woocommerce-checkout-pci'),
            ),
        );
    }

    /**
     * Create Charge on Checkout.com
     *
     * @param int $order_id
     * @return array|void
     *
     * @version 20160316
     */
    public function process_payment($order_id) {
        include_once( 'includes/class-wc-gateway-checkout-pci-request.php');
        include_once( 'includes/class-wc-gateway-checkout-pci-validator.php');
        include_once( 'includes/class-wc-gateway-checkout-pci-customer-card.php');

        global $woocommerce;

        $order          = new WC_Order( $order_id );
        $request        = new WC_Checkout_Pci_Request($this);
        $savedCardData  = array();
        $ccParams       = array();
        $savedCard      = !empty($_POST["{$request->gateway->id}-saved-card"]) ? $_POST["{$request->gateway->id}-saved-card"] : '';


        if (empty($savedCard)) {
            WC_Checkout_Pci_Validator::wc_add_notice_self('Payment error: Please check your card data.', 'error');
            return;
        }

        if ($savedCard !== self::PAYMENT_CARD_NEW_CARD) {
            $savedCardData = WC_Checkout_Pci_Customer_Card::getCustomerCardData($savedCard, $order->user_id);

            if (!$savedCardData) {
                WC_Checkout_Pci_Validator::wc_add_notice_self('Payment error: Please check your card data.', 'error' );
                return;
            }
        } else {

            $_SESSION['checkout_save_card_checked'] = isset($_POST['save-card-checkbox']);

            $ccDate = $request->getCcExpiryDate($_POST["{$request->gateway->id}-card-expiry"]);

            $ccParams['ccNumber']   = preg_replace('/\D/', '', $_POST["{$request->gateway->id}-card-number"]);
            $ccParams['ccCvc']      = $_POST["{$request->gateway->id}-card-cvc"];
            $ccParams['ccName']     = $_POST["{$request->gateway->id}-card-holder-name"];
            $ccParams['ccMonth']    = $ccDate['ccMonth'];
            $ccParams['ccYear']     = $ccDate['ccYear'];

            $ccIsValid = WC_Checkout_Pci_Validator::validateCcData($ccParams);

            if (!$ccIsValid) {
                WC_Checkout_Pci_Validator::wc_add_notice_self('Payment error: Please check your card data.', 'error');;
                return;
            }
        }

        $result = $request->createCharge($order, $ccParams, $savedCardData);

        if (!empty($result['error'])) {
            WC_Checkout_Pci_Validator::wc_add_notice_self($result['error'], 'error');
            return;
        }

        $entityId       = $result->getId();
        $redirectUrl    = $result->getRedirectUrl();

        if ($redirectUrl) {
            $_SESSION['checkout_payment_token'] = strtolower($entityId);

            return array(
                'result'    => 'success',
                'redirect'  => $redirectUrl
            );
        }

        $order->update_status($request->getOrderStatus(), __("Checkout.com Charge Approved (Transaction ID - {$entityId}", 'woocommerce-checkout-pci'));
        $order->reduce_order_stock();
        $woocommerce->cart->empty_cart();

        update_post_meta($order_id, '_transaction_id', $entityId);

        if (is_user_logged_in() && $this->saved_cards) {
            WC_Checkout_Pci_Customer_Card::saveCard($result, $order->user_id, isset($_POST['save-card-checkbox']));
        }

        return array(
            'result'        => 'success',
            'redirect'      => $this->get_return_url($order)
        );
    }

    /**
     * Payment form on checkout page.
     *
     * @version 20160304
     */
    public function payment_fields() {
        $this->credit_card_form();
    }

    /**
     * Custom credit card form
     *
     * @param array $args
     * @param array $fields
     * @return bool
     */
    public function credit_card_form($args = array(), $fields = array()) {
        include_once ('includes/class-wc-gateway-checkout-pci-customer-card.php');
        wp_enqueue_script( 'wc-credit-card-form' );

        // Pay Order Page
        $isPayOrder = !empty($_GET['pay_for_order']) ? (boolean)$_GET['pay_for_order'] : false;

        if ($isPayOrder) {
            if(!empty($_GET['order_id'])) {
                $orderId    = $_GET['order_id'];
            } else if (!empty($_GET['key'])){
                $orderKey   = $_GET['key'];
                $orderId    = wc_get_order_id_by_order_key($orderKey);
            } else {
                return false;
            }

            if (empty($orderId)) return false;

            $order = new WC_Order($orderId);

            if (!is_object($order)) return false;

            $billingEmail       = $order->billing_email;
            $customerName       = $order->billing_first_name;
            $customerLastName   = $order->billing_last_name;

            if (empty($billingEmail) || empty($customerName) || empty($customerLastName)) {
                echo '<p>' . __('Some required fields are empty.', 'woocommerce-checkout-non-pci') . '</p>';
                return false;
            }
        }

        $checkoutFields = !$isPayOrder ? json_encode($woocommerce->checkout->checkout_fields,JSON_HEX_APOS) : json_encode(array());
        $cardList = (is_user_logged_in() && $this->saved_cards)? WC_Checkout_Pci_Customer_Card::getCustomerCardList(get_current_user_id()) : array();

        ?>
        <fieldset id="<?php echo $this->id; ?>-cc-form">
			<?php do_action( 'woocommerce_credit_card_form_start', $this->id ); ?>
            <?php if(!empty($cardList)): ?>
                <ul class="wc_payment_methods payment_methods">
                    <?php foreach($cardList as $index => $card):?>
                        <li>
                            <p>
                                <input id="checkout-saved-card-<?php echo $index?>" class="checkout-saved-card-radio" type="radio" name="<?php echo $this->id . '-saved-card'?>" value="<?php echo md5($card->entity_id . '_' . $card->card_number . '_' . $card->card_type)?>"/>
                                <label for="checkout-saved-card-<?php echo $index?>"><?php echo sprintf('xxxx-%s', $card->card_number) . ' ' . $card->card_type?></label>
                            </p>
                        </li>
                    <?php endforeach?>
                    <li>
                        <p>
                            <input id="checkout-new-card" class="checkout-new-card-radio" type="radio" name="<?php echo $this->id . '-saved-card'?>" value="<?php echo self::PAYMENT_CARD_NEW_CARD?>"/>
                            <label for="checkout-new-card"><?php echo __('Use New Card', 'woocommerce') ?></label>
                        </p>
                    </li>
                </ul>
            <?php else:?>
                <input id="checkout-new-card" class="checkout-new-card-input" type="hidden" name="<?php echo $this->id . '-saved-card'?>" value="<?php echo self::PAYMENT_CARD_NEW_CARD?>"/>
            <?php endif?>
            <p class="form-row form-row-wide checkout-pci-new-card-row">
                <?php echo '<label for="' . esc_attr( $this->id ) . '-card-holder-name">' . __('Name on Card', 'woocommerce-checkout-pci') . ' <span class="required">*</span></label>'?>
                <?php echo '<input id="' . esc_attr( $this->id ) . '-card-holder-name" class="input-text" type="text" maxlength="100" autocomplete="off" placeholder="" name="' . $this->id . '-card-holder-name' . '" />'?>
            </p>
            <p class="form-row form-row-wide checkout-pci-new-card-row">
                <?php echo '<label for="' . esc_attr( $this->id ) . '-card-number">' . __('Card Number', 'woocommerce') . ' <span class="required">*</span></label>'?>
                <?php echo '<input id="' . esc_attr( $this->id ) . '-card-number" class="input-text wc-credit-card-form-card-number" type="text" maxlength="20" autocomplete="off" placeholder="•••• •••• •••• ••••" name="' . $this->id . '-card-number' . '" />'?>
            </p>
            <p class="form-row form-row-wide checkout-pci-new-card-row">
                <?php echo '<label for="' . esc_attr( $this->id ) . '-card-expiry">' . __( 'Expiry (MM/YY)', 'woocommerce' ) . ' <span class="required">*</span></label>'?>
                <?php echo '<input id="' . esc_attr( $this->id ) . '-card-expiry" class="input-text wc-credit-card-form-card-expiry" type="text" autocomplete="off" placeholder="' . esc_attr__( 'MM / YY', 'woocommerce' ) . '" name="' . $this->id . '-card-expiry' . '" />'?>
            </p>
            <p class="form-row form-row-wide checkout-pci-new-card-row">
                <?php echo '<label for="' . esc_attr( $this->id ) . '-card-cvc">' . __( 'Card Code', 'woocommerce' ) . ' <span class="required">*</span></label>'?>
                <?php echo '<input id="' . esc_attr( $this->id ) . '-card-cvc" class="input-text wc-credit-card-form-card-cvc" type="text" autocomplete="off" placeholder="' . esc_attr__( 'CVC', 'woocommerce' ) . '" name="' . $this->id . '-card-cvc' . '" />'?>
            </p>
            <p class="form-row form-row-wide checkout-pci-new-card-row">
                <?php if($this->saved_cards): ?>
                        
                            <div class="card-save-checkbox" style="display: none">
                                <input type="checkbox" name="save-card-checkbox" id="save-card-checkbox" value="1">
                                <label for="save-card-checkbox" style="position:relative; display:inline-block; margin-bottom: 10px; margin-top: 10px">Save card for future payments</label>
                            </div>
                                
                        
                <?php endif?>
            </p>
			<?php do_action( 'woocommerce_credit_card_form_end', $this->id ); ?>
            <div class="clear"></div>
        </fieldset>
        <?php if(!empty($cardList)): ?>
            <script type="application/javascript">
                checkoutHideNewCard();

                function checkoutHideNewCard() {
                    jQuery('.checkout-pci-new-card-row').hide();
                    jQuery('.card-save-checkbox').hide();
                }

                function checkoutShowNewCard() {
                    jQuery('.checkout-pci-new-card-row').show();
                    jQuery('.card-save-checkbox').show();
                }

                jQuery('.checkout-saved-card-radio').on("change", function() {
                    checkoutHideNewCard();
                });

                jQuery('.checkout-new-card-radio').on("change", function() {
                    checkoutShowNewCard();
                });

            </script>

            <script type="application/javascript">
                jQuery('#place_order').click(function(e){
                    if(jQuery('#payment_method_woocommerce_checkout_pci').is(':checked')){
                        if(jQuery('.checkout-new-card-radio').is(':checked') == false && jQuery('.checkout-saved-card-radio').is(':checked') == false ){
                            e.preventDefault();

                            window.checkoutFields = '<?php echo $checkoutFields?>';
                            var result = {error: false, messages: []};
                            var fields = JSON.parse(window.checkoutFields);
                            result.error = true;
                            result.messages.push({target: 'payment_box payment_method_woocommerce_checkout_pci', message : 'Please select a payment method.'});

                            jQuery('.woocommerce-error, .woocommerce-message').remove();

                            jQuery.each(result.messages, function(index, value) {
                                jQuery('form.checkout').prepend('<div class="woocommerce-error">' + value.message + '</div>');
                            });

                            jQuery('html, body').animate({
                                scrollTop: (jQuery('form.checkout').offset().top - 100 )
                            }, 1000 );

                            jQuery(document.body).trigger('checkout_error');
                        }
                    }
                })
            </script>
            
        <?php endif?>

        <script type="text/javascript">
            if(jQuery('#createaccount').length === 1){
                var checkbox = document.querySelector("input[name=createaccount]");
                checkbox.addEventListener( 'change', function() {
                    if(this.checked) {
                        jQuery('.card-save-checkbox').show();
                    } else {s
                        jQuery('.card-save-checkbox').hide();
                    }
                });

                if( jQuery('#createaccount:checked').length === 1){
                    jQuery('.card-save-checkbox').show();
                }
            }
        </script>
        <?php
    }

    /**
     * Logging method.
     *
     * @param string $message
     *
     * @version 20160403
     */
    public static function log($message) {
        error_log(print_r($message, true) . "\r\n", 3, plugin_dir_path (__FILE__) . DIRECTORY_SEPARATOR . self::PAYMENT_METHOD_CODE . '.log');
    }

    /**
     * Refund order
     *
     * Process a refund if supported.
     * @param  int    $order_id
     * @param  float  $amount
     * @param  string $reason
     * @return bool True or false based on success, or a WP_Error object
     *
     * @version 20160316
     */
    public function process_refund($order_id, $amount = null, $reason = '') {
        include_once( 'includes/class-wc-gateway-checkout-pci-request.php');
        include_once( 'includes/class-wc-gateway-checkout-pci-validator.php');

        $order      = new WC_Order($order_id);
        $request    = new WC_Checkout_Pci_Request($this);

        $result = $request->refund($order, $amount, $reason);

        if ($result['status'] === 'error') {
            return new WP_Error('error', __($result['message'], 'woocommerce-checkout-pci'));
        }

        return true;
    }

    //Activate, suspend or cancel recurring payment plan
    public function updatePlan($subscription){ 
        $subscriptionParentId = $subscription->parent_id;

        if(!$subscriptionParentId){
            return false;
        }

        $order          = new WC_Order($subscriptionParentId);
        $transactionId = $order->transaction_id;

        $checkout   = new WC_Checkout_Pci();
        $mode       =  $checkout->settings['mode'];

        //Api call getChargeHistory in order to get the auth chargeId
        $Api            = CheckoutApi_Api::getApi(array('mode' => $checkout->settings['mode']));
        $verifyParamsHistory   = array('chargeId' => $transactionId, 'authorization' => $checkout->settings['secret_key']);
        $resultHistory         = $Api->getChargeHistory($verifyParamsHistory);
        $charges = $resultHistory->getCharges();

        if(!empty($charges)) {
            $chargesArray = $charges->toArray();

            foreach ($chargesArray as $key=> $charge) {
                if (in_array('Authorised', $charge)) {
                    $authChargeId = $charge['id'];
                    break;
                }
            }

            //Api Call getCharge in order to get the recurring planId
            $verifyParamsCharge   = array('chargeId' => $authChargeId, 'authorization' => $checkout->settings['secret_key']);
            $resultCharge         = $Api->getCharge($verifyParamsCharge);

            if(!empty($resultCharge)){
                if($subscription->status == 'cancelled'){
                    $customerPlanId = $resultCharge['customerPaymentPlans'][0]['customerPlanId'];
                    $param   = array('customerPlanId' => $customerPlanId, 'authorization' => $checkout->settings['secret_key']);

                    //Api call to delete customer plan
                    $resultCancel = $Api->cancelCustomerPaymentPlan($param);

                    if($resultCancel['message'] != 'ok'){
                        WC_Checkout_Pci::log('Failed to cancel Customer PlanId :'.$customerPlanId. ' for orderId:'.$subscriptionParentId);
                    } else {
                         WC_Checkout_Pci::log('Customer plan cancelled successfully. Customer PlanId:'.$customerPlanId. ' for orderId:'.$subscriptionParentId);
                    }

                }else{

                    $recPlanId = $resultCharge['customerPaymentPlans'][0]['planId'];

                    if($subscription->status == 'active'){
                        $postedParam['status'] = 1;
                        $failMessage = 'Failed to activate Recuring PlanId ';
                        $successMessage = 'Account successfully activated. Recuring PlanId ';
                    } elseif ($subscription->status == 'on-hold') {
                        $postedParam['status'] = 4;
                        $failMessage = 'Failed to suspend Recurring PlanId ';
                        $successMessage = 'Account successfully suspended. Recurring PlanId ';
                    }
                    
                    $param   = array('planId' => $recPlanId, 'postedParam' =>$postedParam, 'authorization' => $checkout->settings['secret_key']);

                    //Api call to update payment plan and set status to 4(Suspended) or 1(Activate)
                    $resultRec = $Api->updatePaymentPlan($param);

                    if($resultRec['message'] != 'ok'){
                        WC_Checkout_Pci::log($failMessage.':'.$recPlanId. ' for orderId:'.$subscriptionParentId);
                    } else {
                        WC_Checkout_Pci::log($successMessage.':'.$recPlanId. ' for orderId:'.$subscriptionParentId);
                    }
                }
            }
        }
    }
}
