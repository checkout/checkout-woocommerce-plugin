<?php

/**
 * Class WC_Checkoutcom_Utility
 */
class WC_Checkoutcom_Utility
{
     /**
     * 
     * *verify cko signature for webhooks
     * @param event
     * @param secretKey
     */
    public static function verifySignature($event, $key, $cko_signature) {
        return hash_hmac('sha256', $event, $key) === $cko_signature ? true : false;
    }
    
    /**
     * Format amount in cents
     *
     * @param $value
     * @param $currencySymbol
     * @return float|int
     */
    public static function valueToDecimal($value, $currencySymbol)
    {
        $currency = strtoupper($currencySymbol);
        $threeDecimalCurrencyList = array('BHD', 'LYD', 'JOD', 'IQD', 'KWD', 'OMR', 'TND');
        $zeroDecimalCurencyList = array(
            'BYR',
            'XOF',
            'BIF',
            'XAF',
            'KMF',
            'XOF',
            'DJF',
            'XPF',
            'GNF',
            'JPY',
            'KRW',
            'PYG',
            'RWF',
            'VUV',
            'VND',
        );

        // check for decimal seperator
        $amount = str_replace(",",".", $value);

        if (in_array($currency, $threeDecimalCurrencyList)) {
            $value = (int) ($amount * 1000);
        } elseif (in_array($currency, $zeroDecimalCurencyList)) {
            $value = floor($amount);
        } else {
            $value = round($amount * 100);
        }

        return $value;
    }

    /**
     * Format amount in decimal
     *
     * @param $amount
     * @param $currencySymbol
     * @return float|int
     */
    public function decimalToValue($amount, $currencySymbol)
    {
        $currency = strtoupper($currencySymbol);
        $threeDecimalCurrencyList = array('BHD', 'LYD', 'JOD', 'IQD', 'KWD', 'OMR', 'TND');
        $zeroDecimalCurencyList = array(
            'BYR',
            'XOF',
            'BIF',
            'XAF',
            'KMF',
            'XOF',
            'DJF',
            'XPF',
            'GNF',
            'JPY',
            'KRW',
            'PYG',
            'RWF',
            'VUV',
            'VND',
        );

        if (in_array($currency, $threeDecimalCurrencyList)) {
            $value = $amount / 1000;
        } elseif (in_array($currency, $zeroDecimalCurencyList)) {
            $value = $amount;
        } else {
            $value = $amount / 100;
        }

        return $value;
    }

    /**
     * Add a delay to the current URC time
     *
     * @return string ISO 8601 timestamp of UTC current time plus delays
     */
    public static function getDelayedCaptureTimestamp()
    {
        // Specify a 10 seconds delay even if the autocapture time is set to 0 to avoid webhook issues
        $defaultSecondsDelay = 10;
        $delay = preg_replace('/\s/', '', WC_Admin_Settings::get_option('ckocom_card_cap_delay'));
        // If the input of the delay is numeric
        if (is_numeric($delay)) {
            // Get total seconds based on the hour input
            $totalSeconds = $delay * 3600;
            // If the delay is 0 manually add a 10 seconds delay
            if ($totalSeconds == 0) {
                $totalSeconds += $defaultSecondsDelay;
            }
            $hours = floor($totalSeconds / 3600);
            $minutes = floor($totalSeconds / 60 % 60);
            $seconds = floor($totalSeconds % 60);
            // Return date and time in UTC with the delays added
            return gmdate("Y-m-d\TH:i:s\Z", strtotime('+' . $hours . ' hours +' . $minutes . ' minutes +' . $seconds . 'seconds'));
        }
        // If the delay is in an invalid format (non-numeric) default to base delay (defaultSecondsDelay)
        return gmdate("Y-m-d\TH:i:s\Z", strtotime('+' . $defaultSecondsDelay . 'seconds'));
    }

    /**
     * @param $bin
     * @return bool
     */
    public static function isMadaCard($bin)
    {
        // Path to MADA_BIN.csv
        $csvPath = WP_PLUGIN_DIR. "\checkout-com-unified-payments-api\includes\Files\Mada\MADA_BINS.csv";

        $arrayFromCSV =  array_map('str_getcsv', file($csvPath));

        // Remove the first row of csv columns
        unset($arrayFromCSV[0]);

        // Build the MADA BIN array
        $binArray = [];
        foreach ($arrayFromCSV as $row) {
            $binArray[] = $row[0];
        }

        return in_array($bin, $binArray);
    }

    /**
     * @param $message
     * @param string $status
     */
    public static function wc_add_notice_self($message, $status = 'error') {
        if (function_exists('wc_add_notice')) {
            wc_add_notice(__($message), $status);
        } else {
            global $woocommerce;

            switch ($status) {
                case 'error':
                    $woocommerce->add_error(__($message));
                    break;
                case 'notice':
                    $woocommerce->add_message(__($message));
                    break;
                default:
                    $woocommerce->add_error(__($message));
                    break;
            }
        }
    }

    /**
     * @param $errorMessage
     * @param $exception
     */
    public static function logger($errorMessage , $exception)
    {
        $logger = wc_get_logger();
        $context = array( 'source' => 'wc_checkoutcom_gateway_log' );

        // Get file logging from module setting
        $file_logging = WC_Admin_Settings::get_option('cko_file_logging') == 'yes' ? true : false;

        // Check if file logging is enable
        if($file_logging){
            // Log error message with exception
            $logger->error($errorMessage, $context );
            $logger->error(wc_print_r($exception, true), $context );
        } else {
            // Log only error message
            $logger->error($errorMessage, $context );
        }
    }

    /**
     * @param $currencyCode
     * @param $apm
     * @return array
     */
    public static function get_alternative_payment_methods()
    {
        $currencyCode = get_woocommerce_currency();
        $apm_setting = get_option('woocommerce_wc_checkout_com_alternative_payments_settings');
        $apm = $apm_setting['ckocom_apms_selector'];
        $countryCode = WC()->customer->get_billing_country();

        $apmArray = array();
        if ($apm !== 0) {

            foreach ($apm as $value) {
                if ($value == 'ideal' && $currencyCode == 'EUR' && $countryCode == 'NL') {
                    array_push($apmArray, $value);
                }

                if ($value == 'sofort' && $currencyCode == 'EUR') {

                    if ($countryCode == 'BE'
                        || $countryCode == 'DE'
                        || $countryCode == 'IT'
                        || $countryCode == 'NL'
                        || $countryCode == 'AT'
                        || $countryCode == 'ES'
                    ) {
                        array_push($apmArray, $value);
                    }
                }

                if ($value == 'boleto' && $countryCode == 'BR') {
                    if ($currencyCode == 'BRL' || $currencyCode == 'USD' ) {
                        array_push($apmArray, $value);
                    }
                }

                if ($value == 'giropay' && $currencyCode == 'EUR' && $countryCode == 'DE') {
                    array_push($apmArray, $value);
                }

                if ($value == 'poli') {
                    if ($currencyCode == 'AUD' || $currencyCode == 'NZD') {
                        if ($countryCode == 'AU' || $countryCode == 'NZ') {
                            array_push($apmArray, $value);
                        }
                    }
                }

                if ($value == 'klarna') {
                    if ($currencyCode == 'EUR'
                        || $currencyCode == 'DKK'
                        || $currencyCode == 'GBP'
                        || $currencyCode == 'NOR'
                        || $currencyCode == 'SEK'
                    ) {
                        if ($countryCode == 'AT'
                            || $countryCode == 'DK'
                            || $countryCode == 'FI'
                            || $countryCode == 'DE'
                            || $countryCode == 'NL'
                            || $countryCode == 'NO'
                            || $countryCode == 'SE'
                            || $countryCode == 'GB'
                        ) {
                            array_push($apmArray, $value);
                        }
                    }
                }

                if ($value == 'sepa' && $currencyCode == 'EUR') {

                    if ($countryCode == 'AD'
                        || $countryCode == 'AT'
                        || $countryCode == 'BE'
                        || $countryCode == 'CY'
                        || $countryCode == 'EE'
                        || $countryCode == 'FI'
                        || $countryCode == 'DE'
                        || $countryCode == 'GR'
                        || $countryCode == 'IE'
                        || $countryCode == 'IT'
                        || $countryCode == 'LV'
                        || $countryCode == 'LT'
                        || $countryCode == 'LU'
                        || $countryCode == 'MT'
                        || $countryCode == 'MC'
                        || $countryCode == 'NL'
                        || $countryCode == 'PT'
                        || $countryCode == 'SM'
                        || $countryCode == 'SK'
                        || $countryCode == 'SI'
                        || $countryCode == 'ES'
                        || $countryCode == 'VA'
                        || $countryCode == 'BG'
                        || $countryCode == 'HR'
                        || $countryCode == 'CZ'
                        || $countryCode == 'DK'
                        || $countryCode == 'HU'
                        || $countryCode == 'IS'
                        || $countryCode == 'LI'
                        || $countryCode == 'NO'
                        || $countryCode == 'PL'
                        || $countryCode == 'RO'
                        || $countryCode == 'SE'
                        || $countryCode == 'CH'
                        || $countryCode == 'GB'  
                    ) {
                        array_push($apmArray, $value);
                    }
                }

                if ($value == 'eps' && $currencyCode == 'EUR' && $countryCode == 'AT') {
                    array_push($apmArray, $value);
                }

                if ($value == 'bancontact' && $currencyCode == 'EUR' && $countryCode == 'BE') {
                    array_push($apmArray, $value);
                }

                if ($value == 'knet' && $currencyCode == 'KWD' && $countryCode == 'KW') {
                    array_push($apmArray, $value);
                }

                if ($value == 'fawry' && $currencyCode == 'EGP' && $countryCode == 'EG') {
                    array_push($apmArray, $value);
                }

                if ($value == 'alipay' && $currencyCode == 'USD' && $countryCode == 'CN') {
                    array_push($apmArray, $value);
                }

                if ($value == 'qpay' && $currencyCode == 'QAR' && $countryCode == 'QA') {
                    array_push($apmArray, $value);
                }
            }
        }

        return $apmArray;
    }

}