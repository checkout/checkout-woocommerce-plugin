<?php
/**
 * Script for verify charge by payment token
 *
 * @version 20160317
 */
include_once('../../../../../wp-load.php');
include_once('../../includes/class-wc-gateway-checkout-pci-request.php');
include_once('../../includes/class-wc-gateway-checkout-pci-validator.php');

$paymentToken = !empty($_GET['cko-payment-token']) ? $_GET['cko-payment-token'] : '';

if (empty($paymentToken) || empty($_SESSION['checkout_payment_token']) || $_SESSION['checkout_payment_token'] !== $paymentToken) {
    WC_Checkout_Pci_Validator::wc_add_notice_self('Payment error: Please check your card data.', 'error');
    wp_redirect(WC_Cart::get_checkout_url());
    exit();
}

$checkout   = new WC_Checkout_Pci();
$request    = new WC_Checkout_Pci_Request($checkout);
$result     = $request->verifyCharge($paymentToken);

if ($result['status'] === 'error') {
    WC_Checkout_Pci_Validator::wc_add_notice_self($result['message'], 'error');
    wp_redirect(WC_Cart::get_checkout_url());
    exit();
}

unset($_SESSION['checkout_payment_token']);

$url = $checkout->get_return_url($order);
wp_redirect($url);
die();