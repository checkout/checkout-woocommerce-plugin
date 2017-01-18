<?php
/**
 * Script for verify charge by payment token
 *
 * @version 20160317
 */
include_once('../../../../../wp-load.php');
include_once('../../includes/class-wc-gateway-checkout-non-pci-request.php');
include_once('../../includes/class-wc-gateway-checkout-non-pci-validator.php');
include_once('../../includes/class-wc-gateway-checkout-non-pci-customer-card.php');

$paymentToken       = !empty($_REQUEST['cko-payment-token']) ? $_REQUEST['cko-payment-token'] : '';
$localPaymentToken  = !empty($_SESSION['checkout_local_payment_token']) ? $_SESSION['checkout_local_payment_token'] : '';
$savedCardData  = array();

if($_REQUEST['cko-card-token']){
    global $woocommerce;

    $orderId    = $woocommerce->session->order_awaiting_payment;

    if (empty($orderId)) {
        WC_Checkout_Non_Pci::log('Empty OrderId');
        WC_Checkout_Non_Pci_Validator::wc_add_notice_self('An error has occured while processing your transaction.', 'error');
        wp_redirect(WC_Cart::get_checkout_url());
        exit();
    }

    $order      = new WC_Order( $orderId );
    $checkout   = new WC_Checkout_Non_Pci();
    $request    = new WC_Checkout_Non_Pci_Request($checkout);
    $cardRequest = new WC_Checkout_Non_Pci_Customer_Card();
    $order_status = $order->get_status(); 

    if($order_status == 'pending'){   

        $result     = $request->createCharge($order,$_REQUEST['cko-card-token'],$savedCardData);

        if (!empty($result['error'])) {
            WC_Checkout_Non_Pci::log($result);
            WC_Checkout_Non_Pci_Validator::wc_add_notice_self($result['error'], 'error');
            wp_redirect(WC_Cart::get_checkout_url());
            exit();
        }

        $entityId       = $result->getId();
        $redirectUrl    = $result->getRedirectUrl();

        if ($redirectUrl) {
            $_SESSION['checkout_payment_token'] =  $entityId;
            $url = $redirectUrl;
            wp_redirect($url);
            exit();
        }

        update_post_meta($orderId, '_transaction_id', $entityId);

        $order->update_status($request->getOrderStatus(), __("Checkout.com Charge Approved (Transaction ID - {$entityId}", 'woocommerce-checkout-non-pci'));
        $order->reduce_order_stock();
        $woocommerce->cart->empty_cart();

        if (is_user_logged_in() && $checkout->saved_cards) {
            $cardRequest->saveCard($result, $order->user_id, $_SESSION['checkout_save_card_checked']);
        }

        $url = $checkout->get_return_url($order);
        wp_redirect($url);
    }

}else {

    if (!empty($paymentToken) && $paymentToken == $localPaymentToken) {
        unset($_SESSION['checkout_local_payment_token']);

        WC_Checkout_Non_Pci_Validator::wc_add_notice_self('Thank you for your purchase! Thanks you for completing the payment. Once we confirm the we have successfully received the payment, you will be notified by email.', 'notice');
        wp_redirect(WC_Cart::get_checkout_url());
        exit();
    }

    if (empty($paymentToken) || empty($_SESSION['checkout_payment_token']) || $_SESSION['checkout_payment_token'] !== $paymentToken) {
        WC_Checkout_Non_Pci_Validator::wc_add_notice_self('Payment error: Please check your card data.', 'error');
        wp_redirect(WC_Cart::get_checkout_url());
        exit();
    }

    $checkout   = new WC_Checkout_Non_Pci();
    $request    = new WC_Checkout_Non_Pci_Request($checkout);
    $result     = $request->verifyCharge($paymentToken);


    if ($result['status'] === 'error') {
        WC_Checkout_Non_Pci_Validator::wc_add_notice_self($result['message'], 'error');
        wp_redirect(WC_Cart::get_checkout_url());
        exit();
    }

    unset($_SESSION['checkout_payment_token']);

    $url = $checkout->get_return_url($order);
    wp_redirect($url);
}

die();