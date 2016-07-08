<?php
/**
 * Script for verify charge by payment token
 *
 * @version 20160317
 */
include_once('../../../../../wp-load.php');
include_once('../../includes/class-wc-gateway-checkout-non-pci-request.php');
include_once('../../includes/class-wc-gateway-checkout-non-pci-validator.php');

$paymentToken       = !empty($_GET['cko-payment-token']) ? $_GET['cko-payment-token'] : '';
$localPaymentToken  = !empty($_SESSION['checkout_local_payment_token']) ? $_SESSION['checkout_local_payment_token'] : '';

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

die();