<?php

use Checkout\CheckoutApi;
use Checkout\Library\Exceptions\CheckoutHttpException;
use Checkout\Library\Exceptions\CheckoutModelException;

class WC_Checkout_Com_Webhook
{
    /**
     * Process webhook for captured payment
     *
     * @param $data
     * @return bool
     */
    public static function capture_payment($data)
    {
        $webhook_data = $data->data;
        $order_id = $webhook_data->reference;

        // return false if no order id
        if (empty($order_id)) {
            return false;
        }

        // Load order form order id
        $order = wc_get_order( $order_id );

        // check if payment is already captured
        $already_captured = get_post_meta($order_id, 'cko_payment_captured', true );
        $message = 'Webhook received from checkout.com. Payment captured';

        // Add note to order if captured already
        if ($already_captured) {
            $order->add_order_note(__($message, 'wc_checkout_com'));
            return true;
        }

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
        $order_message = __("Checkout.com Payment Captured (Transaction ID - {$action_id}) ", 'wc_checkout_com');

        // Check if webhook amount is less than order amount
        if ($amount < $order_amount_cents) {
            $order_message = __("Checkout.com Payment partially captured (Transaction ID - {$action_id}) ", 'wc_checkout_com');
        }

        // Update order status on woo backend
        $order->update_status($status, $order_message);
        $order->add_order_note(__($message, 'wc_checkout_com'));

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
        $order_id = $webhook_data->reference;

        // return false if no order id
        if (empty($order_id)) {
            return false;
        }

        // Load order form order id
        $order = wc_get_order( $order_id );
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
        $order_id = $webhook_data->reference;

        // return false if no order id
        if (empty($order_id)) {
            return false;
        }

        // Load order form order id
        $order = wc_get_order( $order_id );

        // check if payment is already captured
        $already_voided = get_post_meta($order_id, 'cko_payment_voided', true );
        $message = 'Webhook received from checkout.com. Payment voided';

        // Add note to order if captured already
        if ($already_voided) {
            $order->add_order_note(__($message, 'wc_checkout_com'));
            return true;
        }

        // Get action id from webhook data
        $action_id = $webhook_data->action_id;

        // Set action id as woo transaction id
        update_post_meta($order_id, '_transaction_id', $action_id);
        update_post_meta($order_id, 'cko_payment_voided', true);

        // Get cko capture status configured in admin
        $status = WC_Admin_Settings::get_option('ckocom_order_void');
        $order_message = __("Checkout.com Payment Voided (Transaction ID - {$action_id}) ", 'wc_checkout_com');

        // Update order status on woo backend
        $order->update_status($status, $order_message);
        $order->add_order_note(__($message, 'wc_checkout_com'));

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
        $order_id = $webhook_data->reference;

        // return false if no order id
        if (empty($order_id)) {
            return false;
        }

        // Load order form order id
        $order = wc_get_order( $order_id );

        // check if payment is already captured
        $already_refunded = get_post_meta($order_id, 'cko_payment_refunded', true );
        $message = 'Webhook received from checkout.com. Payment refunded';

        // Add note to order if captured already
        if ($already_refunded) {
            $order->add_order_note(__($message, 'wc_checkout_com'));
            return true;
        }

        // Get action id from webhook data
        $action_id = $webhook_data->action_id;
        $amount = $webhook_data->amount;
        $order_amount = $order->get_total();
        $order_amount_cents = WC_Checkoutcom_Utility::valueToDecimal($order_amount, $order->get_currency() );

        // Set action id as woo transaction id
        update_post_meta($order_id, '_transaction_id', $action_id);
        update_post_meta($order_id, 'cko_payment_refunded', true);

        // Get cko capture status configured in admin
        $status = WC_Admin_Settings::get_option('ckocom_order_refunded');
        $order_message = __("Checkout.com Payment Refunded (Transaction ID - {$action_id}) ", 'wc_checkout_com');

        // Check if webhook amount is less than order amount
        if ($amount < $order_amount_cents) {
            $order_message = __("Checkout.com Payment partially refunded (Transaction ID - {$action_id}) ", 'wc_checkout_com');
        }

        // Update order status on woo backend
        $order->update_status($status, $order_message);
        $order->add_order_note(__($message, 'wc_checkout_com'));

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

            $order_id = $details->reference;

            // return false if no order id
            if (empty($order_id)) {
                WC_Checkoutcom_Utility::logger('No order id' , null);
                return false;
            }

            // Load order form order id
            $order = wc_get_order( $order_id );

            $status = 'wc-cancelled';
            $message = 'Webhook received from checkout.com. Payment cancelled';

            $order->update_status($status, $message);

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
     * Status changed from "pending payment to Cancelled"
     */
    public static function decline_payment($data)
    {
        $webhook_data = $data->data;
        $order_id = $webhook_data->reference;
        $paymentID = $webhook_data->id;

        if (empty($order_id)) {
            WC_Checkoutcom_Utility::logger('No order id for payment '.$paymentID , null);
           
            return false;
        }

        $order = wc_get_order( $order_id );

        $status = "wc-cancelled";
        $message = "Webhook received from checkout.com. Payment declined";

        try{
           
            // Update order status on woo backend
            $order->update_status($status, $message);
            $order->add_order_note(__($message, 'wc_checkout_com'));

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


}