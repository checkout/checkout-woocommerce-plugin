<?php
/**
 * Web hook controller
 *
 * @version 20160321
 */
include_once('../../../../../wp-load.php');
include_once('../../includes/class-wc-gateway-checkout-pci-web-hook.php');
include_once('../../includes/class-wc-gateway-checkout-pci-validator.php');
include_once('../../woocommerce-checkout-pci.php');

if (!function_exists('getallheaders'))
{
    function getallheaders()
    {
        $headers = '';
        foreach ($_SERVER as $name => $value)
        {
            if (substr($name, 0, 5) == 'HTTP_')
            {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

$headers = getallheaders();

foreach ($headers as $header => $value) {
    $lowHeaders[strtolower($header)] = $value;
}

$secretKey  = !empty($lowHeaders['authorization']) ? $lowHeaders['authorization'] : '';
$checkout   = new WC_Checkout_Pci();
$webHook    = new WC_Checkout_Pci_Web_Hook($checkout);
$storedKey  = $webHook->getPublicKey();

if (empty($secretKey) || (string)$secretKey !== (string)$storedKey) {
    WC_Checkout_Pci::log("{$secretKey} and {$storedKey} is not match");
    http_response_code(401);
    return;
}

$data = json_decode(file_get_contents('php://input'));

WC_Checkout_Pci::log($data);

$eventType = $data->eventType;

if (empty($data) || !WC_Checkout_Pci_Validator::webHookValidation($data)) {
    $responseCode       = (int)$data->message->responseCode;
    $status             = (string)$data->message->status;
    $responseMessage    = (string)$data->message->responseMessage;
    $trackId            = (string)$data->message->trackId;

    WC_Checkout_Pci::log("Error Code - {$responseCode}. Message - {$responseMessage}. Status - {$status}. Order - {$trackId}");

    http_response_code(400);

    return;
}

switch ($eventType) {
    case WC_Checkout_Pci_Web_Hook::EVENT_TYPE_CHARGE_CAPTURED:
        $result = $webHook->captureOrder($data);
        break;
    case WC_Checkout_Pci_Web_Hook::EVENT_TYPE_CHARGE_REFUNDED:
        $result = $webHook->refundOrder($data);
        break;
    case WC_Checkout_Pci_Web_Hook::EVENT_TYPE_CHARGE_VOIDED:
        $result = $webHook->voidOrder($data);
        break;
    case WC_Checkout_Pci_Web_Hook::EVENT_TYPE_CHARGE_SUCCEEDED:
        $result = $webHook->authorisedOrder($data);
        break;
    case WC_Checkout_Non_Pci_Web_Hook::EVENT_TYPE_CHARGE_FAILED:
        $result = $webHook->failOrder($data);
        break;
    case WC_Checkout_Non_Pci_Web_Hook::EVENT_TYPE_INVOICE_CANCELLED:
        $result = $webHook->invoiceCancelOrder($data);
        break;
    default:
        http_response_code(500);
        return;
}

$httpCode = $result ? 200 : 400;

http_response_code($httpCode);

return;

