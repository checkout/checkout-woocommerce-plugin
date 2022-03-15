<?php

use Checkout\CheckoutApi;
use Checkout\Library\Exceptions\CheckoutHttpException;
use Checkout\Library\Exceptions\CheckoutModelException;

class WC_Checkout_Com_Webhook
{
    /**
     * authorize_payment
     *
     * @param  mixed $data
     * @return void
     */
    public static function authorize_payment($data)
    {
        $webhook_data = $data->data;
        $order_id = $webhook_data->metadata->order_id;

        // return false if no order id
        if (empty($order_id)) {
            return false;
        }

        // Load order form order id
        $order = self::get_wc_order($order_id);
        $order_id = $order->get_id();

        $already_captured = get_post_meta($order_id, 'cko_payment_captured', true);

        if ($already_captured) {
            return true;
        }

        $already_authorized = get_post_meta($order_id, 'cko_payment_authorized', true);
        $auth_status = WC_Admin_Settings::get_option('ckocom_order_authorised');
        $message = 'Webhook received from checkout.com. Payment Authorized';

        // Add note to order if Authorized already
        if ($already_authorized && $order->get_status() === $auth_status ) {
            $order->add_order_note(__($message, 'wc_checkout_com'));
            return true;
        }

        // Get action id from webhook data
        $action_id = $webhook_data->action_id;

        // Set action id as woo transaction id
        update_post_meta($order_id, '_transaction_id', $action_id);
        update_post_meta($order_id, '_cko_payment_id', $webhook_data->id);
        update_post_meta($order_id, 'cko_payment_authorized', true);

        $order_message = __("Checkout.com Payment Authorised " ."</br>". " Action ID : {$action_id} ", 'wc_checkout_com');

        $order->add_order_note(__($message, 'wc_checkout_com'));
        $order->update_status($auth_status);


        return true;
    }

    /**
     * Process webhook for card verification
     *
     * @param $data
     * @return bool
     */
    public static function card_verified($data)
    {
        $webhook_data = $data->data;
        $order_id = $webhook_data->metadata->order_id;
        $action_id = $webhook_data->action_id;

        // return false if no order id
        if (empty($order_id)) {
            return false;
        }

        // Load order form order id
        $order = self::get_wc_order($order_id);
        $order_id = $order->get_id();

        $order->add_order_note(__("Checkout.com Card verified webhook received", 'wc_checkout_com'));
        // Set action id as woo transaction id
        update_post_meta($order_id, '_transaction_id', $action_id);

        // Get cko capture status configured in admin
        $status = WC_Admin_Settings::get_option('ckocom_order_captured');

        // update status of the order
        $order->update_status($status);

        return true;
    }

    /**
     * Process webhook for captured payment
     *
     * @param $data
     * @return bool
     */
    public static function capture_payment($data)
    {
        $webhook_data = $data->data;
        $order_id = $webhook_data->metadata->order_id;

        // return false if no order id
        if (empty($order_id)) {
            return false;
        }

        // Load order form order id
        // $order = wc_get_order( $order_id );
        $order = self::get_wc_order($order_id);
        $order_id = $order->get_id();

        // check if payment is already captured
        $already_captured = get_post_meta($order_id, 'cko_payment_captured', true );
        $message = '.Webhook received from checkout.com Payment captured';

        $already_authorized = get_post_meta($order_id, 'cko_payment_authorized', true);

        /**
        * We return false here as payment approved webhook is not yet delivered
        * Gateway will retry sending the captured webhook
        */
        if(!$already_authorized) {
            WC_Checkoutcom_Utility::logger('Payment approved webhook not received yet : ' . $order_id, null);
            return false;
        }

        // Add note to order if captured already
        if ($already_captured) {
            $order->add_order_note(__($message, 'wc_checkout_com'));
            return true;
        }

        $order->add_order_note(__("Checkout.com Payment Capture webhook received", 'wc_checkout_com'));

        // Get action id from webhook data
        $action_id = $webhook_data->action_id;
        $amount = $webhook_data->amount;
        $order_amount = $order->get_total();
        $order_amount_cents = WC_Checkoutcom_Utility::valueToDecimal($order_amount, $order->get_currency() );

        // Set action id as woo transaction id
        update_post_meta($order_id, '_transaction_id', $action_id);
        update_post_meta($order_id, 'cko_payment_captured', true);

        // Get cko capture status configured in admin
        $status = WC_Admin_Settings::get_option('ckocom_order_captured');
        $order_message = __("Checkout.com Payment Captured " ."</br>". " Action ID : {$action_id} ", 'wc_checkout_com');

        // Check if webhook amount is less than order amount
        if ($amount < $order_amount_cents) {
            $order_message = __("Checkout.com Payment partially captured " ."</br>". " Action ID : {$action_id} ", 'wc_checkout_com');
        }

       // add notes for the order and update status
        $order->add_order_note($order_message);
        $order->update_status($status);

        return true;
    }

    /**
     * Process webhook for capture declined payment
     *
     * @param $data
     * @return bool
     */
    public static function capture_declined($data)
    {
        $webhook_data = $data->data;
        $order_id = $webhook_data->metadata->order_id;

        // return false if no order id
        if (empty($order_id)) {
            return false;
        }

        // Load order form order id
        // $order = wc_get_order( $order_id );
        $order = self::get_wc_order($order_id);
        $order_id = $order->get_id();

        $message = 'Webhook received from checkout.com. Payment capture declined. Reason : '.$webhook_data->response_summary;

        // Add note to order if capture declined
        $order->add_order_note(__($message, 'wc_checkout_com'));

        return true;
    }

    /**
     * Process webhook for void payment
     *
     * @param $data
     * @return bool
     */
    public static function void_payment($data)
    {
        $webhook_data = $data->data;
        $order_id = $webhook_data->metadata->order_id;

        // return false if no order id
        if (empty($order_id)) {
            return false;
        }

        // Load order form order id
        // $order = wc_get_order( $order_id );
        $order = self::get_wc_order($order_id);
        $order_id = $order->get_id();

        // check if payment is already captured
        $already_voided = get_post_meta($order_id, 'cko_payment_voided', true );
        $message = 'Webhook received from checkout.com. Payment voided';

        // Add note to order if captured already
        if ($already_voided) {
            $order->add_order_note(__($message, 'wc_checkout_com'));
            return true;
        }

        $order->add_order_note(__("Checkout.com Payment Void webhook received", 'wc_checkout_com'));

        // Get action id from webhook data
        $action_id = $webhook_data->action_id;

        // Set action id as woo transaction id
        update_post_meta($order_id, '_transaction_id', $action_id);
        update_post_meta($order_id, 'cko_payment_voided', true);

        // Get cko capture status configured in admin
        $status = WC_Admin_Settings::get_option('ckocom_order_void');
        $order_message = __("Checkout.com Payment Voided " ."</br>". " Action ID : {$action_id} ", 'wc_checkout_com');

        // add notes for the order and update status
        $order->add_order_note($order_message);
        $order->update_status($status);

        return true;
    }

    /**
     * Process webhook for refund payment
     *
     * @param $data
     * @return bool
     */
    public static function refund_payment($data)
    {
        $webhook_data = $data->data;
        $order_id = $webhook_data->metadata->order_id;

        // return false if no order id
        if (empty($order_id)) {
            return false;
        }

        // Load order form order id
        $order = self::get_wc_order($order_id);
        $order_id = $order->get_id();

        // check if payment is already refunded
        $already_refunded = get_post_meta($order_id, 'cko_payment_refunded', true );
        $message = 'Webhook received from checkout.com. Payment refunded';

        // Get action id from webhook data
        $action_id = $webhook_data->action_id;
        $amount = $webhook_data->amount;
        $order_amount = $order->get_total();
        $order_amount_cents = WC_Checkoutcom_Utility::valueToDecimal($order_amount, $order->get_currency() );
        $get_transaction_id = get_post_meta( $order_id, '_transaction_id', true );

        if ($get_transaction_id == $action_id) {
            return true;
        }

        // Add note to order if refunded already
        if ($order->get_total_refunded() == $order_amount) {
            $order->add_order_note(__($message, 'wc_checkout_com'));
            return true;
        }

        $order->add_order_note(__("Checkout.com Payment Refund webhook received", 'wc_checkout_com'));

        // Set action id as woo transaction id
        update_post_meta($order_id, '_transaction_id', $action_id);
        update_post_meta($order_id, 'cko_payment_refunded', true);

        $refund_amount = WC_Checkoutcom_Utility::decimalToValue($amount, $order->get_currency() );

        // Get cko refund status configured in admin - full refund
        $status = WC_Admin_Settings::get_option('ckocom_order_refunded');
        $order_message = __("Checkout.com Payment Refunded " ."</br>". " Action ID : {$action_id} ", 'wc_checkout_com');

        // Check if webhook amount is less than order amount - partial refund
        if ($amount < $order_amount_cents) {
            $order_message = __("Checkout.com Payment partially refunded " ."</br>". " Action ID : {$action_id} ", 'wc_checkout_com');
            $status = WC_Admin_Settings::get_option('ckocom_order_captured');

            // handle partial refund
            $refund = wc_create_refund(array('amount' => $refund_amount, 'reason' => "", 'order_id' => $order_id, 'line_items' => array()));

        }

        // add notes for the order and update status
        $order->add_order_note($order_message);
        $order->update_status($status);

        return true;
    }

    /**
     * Process webhook for cancelled payment
     *
     * @param $data
     * @return bool
     */
    public static function cancel_payment($data)
    {
        $webhook_data = $data->data;
        $payment_id = $webhook_data->id;
        $core_settings = get_option('woocommerce_wc_checkout_com_cards_settings');
        $environment =  $core_settings['ckocom_environment'] == 'sandbox' ? true : false;
        $gateway_debug = WC_Admin_Settings::get_option('cko_gateway_responses') == 'yes' ? true : false;

        // Initialize the Checkout Api
        $checkout = new CheckoutApi($core_settings['ckocom_sk'], $environment);

        try {
            // Check if payment is already voided or captured on checkout.com hub
            $details = $checkout->payments()->details($payment_id);

            $order_id = $details->metadata->order_id;

            // return false if no order id
            if (empty($order_id)) {
                WC_Checkoutcom_Utility::logger('No order id' , null);
                return false;
            }

            // Load order form order id
            // $order = wc_get_order( $order_id );
            $order = self::get_wc_order($order_id);
            $order_id = $order->get_id();

            $status = 'wc-cancelled';
            $message = 'Webhook received from checkout.com. Payment cancelled';

            // add notes for the order and update status
            $order->add_order_note($message);
            $order->update_status($status);

            return true;

        } catch (CheckoutHttpException $ex) {
            $error_message = "An error has occurred while processing your cancel request.";

            // check if gateway response is enable from module settings
            if ($gateway_debug) {
                $error_message .= __($ex->getMessage() , 'wc_checkout_com');
            }

            // Log message
            WC_Checkoutcom_Utility::logger($error_message, $ex);

            return false;
        }
    }

    /**
     * Desc : This function is used to change the status of an order which are created following
     * Status changed from "pending payment to Failed"
     */
    public static function decline_payment($data)
    {
        $webhook_data = $data->data;
        $order_id = $webhook_data->metadata->order_id;
        $payment_id = $webhook_data->id;
        $response_summary = $webhook_data->response_summary;

        if (empty($order_id)) {
            WC_Checkoutcom_Utility::logger('No order id for payment '.$paymentID , null);

            return false;
        }

        // $order = wc_get_order( $order_id );
        $order = self::get_wc_order($order_id);
        $order_id = $order->get_id();

        $status = "wc-failed";
        $message = "Webhook received from checkout.com. Payment declined Reason : ".$response_summary;

        try{

            // add notes for the order and update status
            $order->add_order_note($message);
            $order->update_status($status);

            return true;

        }catch (CheckoutHttpException $ex) {
            $error_message = "An error has occurred while processing your cancel request.";

            // check if gateway response is enable from module settings
            if ($gateway_debug) {
                $error_message .= __($ex->getMessage() , 'wc_checkout_com');
            }

            // Log message
            WC_Checkoutcom_Utility::logger($error_message, $ex);

            return false;
        }

    }

    /**
     * Load order from order id or
     * Query order by order number
     */
    private static function get_wc_order($order_id)
    {
        $order = wc_get_order( $order_id );

        // Query order by order number to check if order exist
        if (!$order) {
            $orders = wc_get_orders( array(
                    'order_number' =>  $order_id
                )
            );

            $order = $orders[0];
        }

        return $order;
    }

}
