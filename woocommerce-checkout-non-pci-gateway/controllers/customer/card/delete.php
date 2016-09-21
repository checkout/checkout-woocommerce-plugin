<?php

include_once ('../../../includes/class-wc-gateway-checkout-non-pci-customer-card.php');
include_once ('../../../../../../wp-load.php');

$cardId     = !empty($_GET['card']) ? (int)$_GET['card'] : 0;
$result     = array('status' => 'error', 'message' => 'Failed to delete card');
$customerId = get_current_user_id();


if (empty($cardId)) {
    echo json_encode($result);
}

$deleted = WC_Checkout_Non_Pci_Customer_Card::removeCustomerCard($customerId, $cardId);

if ($deleted) {
    $result['status']   = 'ok';
    $result['message']  = WC_Checkout_Non_Pci_Customer_Card::getCustomerCardListHtml($customerId);
}

echo json_encode($result);

