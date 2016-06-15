<?php
include_once ('class-wc-gateway-checkout-pci-request.php');

/**
 * Class WC_Checkout_Pci_Web_Hook
 */
class WC_Checkout_Pci_Web_Hook extends WC_Checkout_Pci_Request
{
    const EVENT_TYPE_CHARGE_SUCCEEDED   = 'charge.succeeded';
    const EVENT_TYPE_CHARGE_CAPTURED    = 'charge.captured';
    const EVENT_TYPE_CHARGE_REFUNDED    = 'charge.refunded';
    const EVENT_TYPE_CHARGE_VOIDED      = 'charge.voided';

    /**
     * Constructor
     *
     * WC_Checkout_Pci_Web_Hook constructor.
     * @param WC_Checkout_Pci $gateway
     *
     * @version 20160317
     */
    public function __construct(WC_Checkout_Pci $gateway) {
        parent::__construct($gateway);
    }

    /**
     * Capture order from web hook
     *
     * @param WC_Order $response
     * @return bool
     *
     * @version 20160321
     */
    public function captureOrder($response) {
        $orderId        = (string)$response->message->trackId;
        $order          = new WC_Order($orderId);
        $transactionId  = (string)$response->message->id;

        if (!is_object($order)) {
            WC_Checkout_Pci::log("Cannot capture an order. Order ID - {$orderId}");
            return false;
        }

        $trackIdList            = get_post_meta($orderId, '_transaction_id');
        $storedTransactionId    = end($trackIdList);

        if ($storedTransactionId === $transactionId) {
            return false;
        }

        update_post_meta($orderId, '_transaction_id', $transactionId);

        $order->add_order_note(__("Checkout.com Capture Charge Approved (Transaction ID - {$transactionId}, Parent ID - {$storedTransactionId})", 'woocommerce-checkout-pci'));

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

        return true;
    }

    /**
     * Refund order from web hook
     *
     * @param WC_Order $response
     * @return bool
     *
     * @version 20160321
     */
    public function refundOrder($response) {
        $orderId        = (string)$response->message->trackId;
        $order          = new WC_Order($orderId);
        $transactionId  = (string)$response->message->id;

        if (!is_object($order)) {
            WC_Checkout_Pci::log("Cannot capture an order. Order ID - {$orderId}");
            return false;
        }

        $trackIdList            = get_post_meta($orderId, '_transaction_id');
        $storedTransactionId    = end($trackIdList);

        if ($storedTransactionId === $transactionId) {
            return false;
        }

        $amountDecimal          = $response->message->value;
        $Api                    = CheckoutApi_Api::getApi(array('mode' => $this->_getEndpointMode()));
        $modelRequest           = new WC_Checkout_Pci_Request();
        $amount                 = $Api->decimalToValue($amountDecimal, $modelRequest->getOrderCurrency($order));

        wc_create_refund(array(
            'amount'    => $amount,
            'order_id'  => $orderId
        ));

        update_post_meta($orderId, '_transaction_id', $transactionId);
        $successMessage = __("Checkout.com Refund Charge Approved (Transaction ID - {$transactionId}, Parent ID - {$storedTransactionId})", 'woocommerce-checkout-pci');
        $order->update_status('refunded', $successMessage);

        return true;
    }

    /**
     * Void order from web hook
     *
     * @param WC_Order $response
     * @return bool
     *
     * @version 20160321
     */
    public function voidOrder($response) {
        $orderId        = (string)$response->message->trackId;
        $order          = new WC_Order($orderId);
        $transactionId  = (string)$response->message->id;

        if (!is_object($order)) {
            WC_Checkout_Pci::log("Cannot capture an order. Order ID - {$orderId}");
            return false;
        }

        $trackIdList            = get_post_meta($orderId, '_transaction_id');
        $storedTransactionId    = end($trackIdList);

        if ($storedTransactionId === $transactionId) {
            return false;
        }

        update_post_meta($orderId, '_transaction_id', $storedTransactionId);

        $successMessage = __("Checkout.com Void Charge Approved (Transaction ID - {$transactionId}, Parent ID - {$storedTransactionId})", 'woocommerce-checkout-pci');

        if (!$this->getVoidOrderStatus()) {
            $order->add_order_note($successMessage);
        } else {
            $order->update_status('cancelled', $successMessage);
        }

        return true;
    }
}