<?php 

include_once('../../../../wp-load.php');

global $woocommerce;

$myPluginGateway = new WC_Checkout_Apple_Pay();
$settings = $myPluginGateway->settings;

$validationURL = $_POST['validationURL'];
$merchantIdentifier = $settings['applepay_merchant_id'];
$domainName = $_SERVER['SERVER_NAME'];
$displayName = $_SERVER['HTTP_HOST'];
$applePayCertPath = $settings['applepay_cert_path'];
$applePayCertKey =  $settings['applepay_cert_key'];

$data = '{"merchantIdentifier": "'.$merchantIdentifier.'", "domainName":"'.$domainName.'", "displayName":"'.$displayName.'"}';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $validationURL);
curl_setopt($ch, CURLOPT_SSLCERT, $applePayCertPath);
curl_setopt($ch,CURLOPT_SSLCERTTYPE,"PEM");
curl_setopt($ch, CURLOPT_SSLKEY, $applePayCertKey);
curl_setopt($ch, CURLOPT_SSLKEYPASSWD, '');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

$result = curl_exec($ch);

if($result === false)
{
    $message =  '{"curlError":"' . curl_error($ch) . '"}';
    $msg = "\n(Network error [errno $errno]: $message)";
    
}

curl_close($ch);
