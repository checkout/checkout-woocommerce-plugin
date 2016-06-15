<?php

/**
 * Class WC_Checkout_Non_Pci_Validator
 *
 * @version 20160312
 */
class WC_Checkout_Non_Pci_Validator
{
    const CHECKOUT_API_RESPONSE_CODE_APPROVED       = 10000;
    const CHECKOUT_API_RESPONSE_CODE_APPROVED_RISK  = 10100;

    /**
     * Validate response by code
     *
     * @param $response
     * @return bool
     *
     * @version 20160312
     */
     public static function responseValidation($response) {
        $responseCode       = (int)$response->getResponseCode();

        if ($responseCode !== self::CHECKOUT_API_RESPONSE_CODE_APPROVED && $responseCode !== self::CHECKOUT_API_RESPONSE_CODE_APPROVED_RISK) {
            return false;
        }

        return true;
    }

    /**
     * Validate response from web hook
     *
     * @param $response
     * @return bool
     *
     * @version 20160321
     */
    public static function webHookValidation($response) {
        if (empty($response)) {
            return false;
        }

        $responseCode       = (int)$response->message->responseCode;

        if ($responseCode !== self::CHECKOUT_API_RESPONSE_CODE_APPROVED &&
            $responseCode !== self::CHECKOUT_API_RESPONSE_CODE_APPROVED_RISK) {

            return false;
        }

        return true;
    }

    /**
     * For old versions
     *
     * @param $message
     * @param $status
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
}