<?php

use Checkout\CheckoutApi;
use Checkout\Models\Address;
use Checkout\Models\Phone;
use Checkout\Models\Payments\IdSource;
use Checkout\Models\Payments\IdealSource;
use Checkout\Models\Product;
use Checkout\Models\Sources\Klarna;
use Checkout\Models\Payments\KlarnaSource;
use Checkout\Models\Payments\GiropaySource;
use Checkout\Models\Payments\BoletoSource;
use Checkout\Models\Payments\AlipaySource;
use Checkout\Models\Payments\PoliSource;
use Checkout\Models\Payments\EpsSource;
use Checkout\Models\Payments\BancontactSource;
use Checkout\Models\Payments\KnetSource;
use Checkout\Models\Payments\FawrySource;
use Checkout\Models\Payments\SofortSource;
use Checkout\Models\Payments\QpaySource;
use Checkout\Models\Sources\SepaAddress;
use Checkout\Models\Sources\SepaData;
use Checkout\Models\Sources\Sepa;


class WC_Gateway_Checkout_Com_APM_Method {

    public static $post;
    public static $dataInfo;
    public static $orderInfo;

    function  __construct($data, $order) {

        self::$post = sanitize_post($_POST);
        self::$dataInfo = $data;
        self::$orderInfo = $order;
    }

    /**
     *  @return Sofortsource
     */
    public static function sofort() {

        $method = new SofortSource();

        return $method;
    }

    /**
     *  @return AlipaySource
     */
    public static function alipay() {

        $method = new AlipaySource();

        return $method;
    }

     /**
     *  @return PoliSource
     */
    public static function poli() {

        $method = new PoliSource();

        return $method;
    }

    /**
     *  @return QpaySource
     */
    public static function qpay() {

        $method = new QpaySource(get_bloginfo( 'name' ));

        return $method;
    }

    /**
     *  @return GiropaySource
     */
    public static function giropay() {

        $bic = self::$dataInfo['giropay-bank-details'];
        $purpose = self::$orderInfo->get_order_number(). '-' . $_SERVER['HTTP_HOST'];

        $method = new GiropaySource($purpose, $bic);

        return $method;
    }

    /**
     *  @return BoletoSource
     */
    public static function boleto() {

        $payer = [
            'name' => self::$dataInfo['name'],
            'email' => self::$post['billing_email'],
            'document' => self::$dataInfo['cpf']
        ];

        $method = new BoletoSource('redirect', self::$post['billing_country'], $payer);

        return $method;
    }

    /**
     *  @return KnetSource
     */
    public static function knet() {

        $language = get_locale();

        switch ($language) {
            case 'ar_SA':
                $language = 'ar';
                break;
            default:
                $language = 'en';
                break;
        }

        $method = new KnetSource($language);

        return $method;
    }

    /**
     *  @return EpsSource
     */
    public static function eps() {

        $purpose = get_bloginfo( 'name' );
        $method = new EpsSource($purpose);

        return $method;
    }

     /**
     *  @return BancontactSource
     */
    public static function bancontact() {

        $accountHolder = self::$post['billing_first_name'] . ' '. self::$post['billing_last_name'];
        $countryCode = self::$post['billing_country'];

        $method = new BancontactSource($accountHolder, $countryCode);

        return $method;
    }

    /**
     *  @return IdSource
     */
    public static function sepa() {

        $details = self::get_sepa_info();
        $method = new IdSource($details->getId());

        return $method;
    }

    /**
     *  @return IdealSource
     */
    public static function ideal() {

        $bic = self::$dataInfo['issuer-id'];
        $description = self::$orderInfo->get_order_number();

        $method = new IdealSource($bic, $description);

        return $method;
    }

    /**
     *  @return FawrySource
     */
    public static function fawry() {

        $fawryInfo = self::get_fawry_info();
        $method = new FawrySource($fawryInfo['email'], $fawryInfo['phone'], self::$orderInfo->get_order_number(), $fawryInfo['products']);

        return $method;
    }

    /**
     *  @return KlarnaSource
     */
    public static function klarna() {

        $klarnaInfo = self::get_klarna_info();
        $method = new KlarnaSource(self::$post['cko-klarna-token'], self::$post['billing_country'], strtolower($klarnaInfo['locale']), $klarnaInfo['billingAddress'], $klarnaInfo['tax'], $klarnaInfo['products']);

        return $method;
    }

    /**
     * GET APM INFO
     */

    /**
     *  Gather fawry info
     */
    public static function get_fawry_info() {

        $fawryInfo = array();

        $fawryInfo['email'] = self::$post['billing_email'];
        $fawryInfo['phone'] = self::$post['billing_phone'];

        $cartInfo = WC_Checkoutcom_Api_request::get_cart_info();

        $productInfo = $cartInfo['order_lines'];
        $orderAmount = $cartInfo['order_amount'];
        $products = array();
        $totalProductAmount = 0;

        foreach ($productInfo as $item) {
            $products[] = array(
                "product_id" => $item['name'],
                "quantity" => $item['quantity'],
                "price" => $item['unit_price'],
                "description" => $item['name'],
                );

            $totalProductAmount += $item['unit_price'] * $item['quantity'];
        }

        if ($totalProductAmount !== $orderAmount) {
            
            WC_Checkoutcom_Utility::logger("Total product amount {$totalProductAmount} does not match order amount {$orderAmount}", null);
            
            $product[] = WC_Checkoutcom_Api_request::format_fawry_product($products, $orderAmount);

            $products = $product;
        }

        $fawryInfo['products'] = $products;

        return $fawryInfo;
    }
    
    /**
     * Gather info for sepa payment and create source ID
     * 
     * @return IdSource
     */
    public static function get_sepa_info() {

        $customerAddress = self::$post['billing_address_1'] . ' ' . self::$post['billing_address_2'];
        $address = new SepaAddress(
            $customerAddress,
            self::$post['billing_city'],
            self::$post['billing_postcode'],
            self::$post['billing_country']
        );

        $data = new SepaData(
            self::$post['billing_first_name'],
            self::$post['billing_last_name'],
            self::$post['sepa-iban'],
            self::$post['sepa-bic'],
            "Thanks for shopping.",
            'single'
        );

        $sepa = new Sepa($address, $data);
        $sepa->customer = array(
          'email' => self::$post['billing_email'],
          'name' => self::$post['billing_first_name'] . ' ' . self::$post['billing_last_name']
        );

        $core_settings = get_option('woocommerce_wc_checkout_com_cards_settings');
        $environment =  $core_settings['ckocom_environment'] == 'sandbox' ? true : false;
        $checkout = new CheckoutApi($core_settings['ckocom_sk'], $environment);

        $details = $checkout->sources()->add($sepa);
        $responseData = $details->response_data;
        WC()->session->set('mandate_reference', $responseData['mandate_reference']);

        return $details;
    }

    /**
     * Gather info for klarna
     * @return array
     */
    public static function get_klarna_info() {

        $klarnaInfo = array();

        $woo_locale = str_replace("_", "-", get_locale());
        $locale = substr($woo_locale, 0, 5);

        $products = array();
        foreach (self::$orderInfo->get_items() as $item_id => $item_data) {
            // Get an instance of corresponding the WC_Product object
            $product = $item_data->get_product();
            $items = wc_get_product( $product->get_id() );
            $price_excl_tax = wc_get_price_excluding_tax($items);
            $unit_price_cents = WC_Checkoutcom_Utility::valueToDecimal($price_excl_tax, get_woocommerce_currency());

            if($items->is_taxable()) {
                $price_incl_tax = wc_get_price_including_tax($items);
                $unit_price_cents = WC_Checkoutcom_Utility::valueToDecimal($price_incl_tax, get_woocommerce_currency());
                $tax_amount = $price_incl_tax - $price_excl_tax;
                $total_tax_amount_cents = WC_Checkoutcom_Utility::valueToDecimal($tax_amount, get_woocommerce_currency());
                $tax = WC_Tax::get_rates();
                $reset_tax = reset($tax)['rate'];
                $tax_rate = round($reset_tax);

            } else {
                $tax_rate = 0;
                $total_tax_amount_cents = 0;
            }

            $products[] = array(
                "name" => $product->get_title(),
                "quantity" => $item_data['quantity'],
                "unit_price" => $unit_price_cents,
                "tax_rate" => $tax_rate * 100,
                "total_amount" => $unit_price_cents * $item_data['quantity'],
                "total_tax_amount" => $total_tax_amount_cents ,
                "type" => "physical",
                "reference" => $product->get_name(),
                "total_discount_amount" => 0

            );
        }

        $chosen_methods = wc_get_chosen_shipping_method_ids();
        $chosen_shipping = $chosen_methods[0];

        if($chosen_shipping != 'free_shipping') {

            $shipping_amount = WC()->cart->get_shipping_total() ;
            $shipping_amount_cents = WC_Checkoutcom_Utility::valueToDecimal($shipping_amount, get_woocommerce_currency());

            if(WC()->cart->get_shipping_tax() > 0){
                $shipping_amount = WC()->cart->get_shipping_total() + WC()->cart->get_shipping_tax();
                $shipping_amount_cents = WC_Checkoutcom_Utility::valueToDecimal($shipping_amount, get_woocommerce_currency());

                $total_tax_amount = WC()->cart->get_shipping_tax();
                $total_tax_amount_cents = WC_Checkoutcom_Utility::valueToDecimal($total_tax_amount, get_woocommerce_currency());

                $shipping_rates = WC_Tax::get_shipping_tax_rates();
                $vat            = array_shift( $shipping_rates );

                if ( isset( $vat['rate'] ) ) {
                    $shipping_tax_rate = round( $vat['rate'] * 100 );
                } else {
                    $shipping_tax_rate = 0;
                }

            } else {
                $shipping_tax_rate = 0;
                $total_tax_amount_cents = 0;
            }

            $products[] = array(
                "name" => $chosen_shipping,
                "quantity" => 1,
                "unit_price" => $shipping_amount_cents,
                "tax_rate" => $shipping_tax_rate,
                "total_amount" => $shipping_amount_cents,
                "total_tax_amount" => $total_tax_amount_cents,
                "type" => "shipping_fee",
                "reference" => $chosen_shipping,
                "total_discount_amount" => 0
            );
        }

        $total_tax_amount_cents = WC_Checkoutcom_Utility::valueToDecimal(WC()->cart->get_total_tax(), get_woocommerce_currency());

        // Set Billing address
        $billingAddressParam = new Address();
        $billingAddressParam->given_name = self::$post['billing_first_name'];
        $billingAddressParam->family_name = self::$post['billing_last_name'];
        $billingAddressParam->email = self::$post['billing_email'];
        $billingAddressParam->street_address = self::$post['billing_address_1'];
        $billingAddressParam->postal_code = self::$post['billing_postcode'];
        $billingAddressParam->city = self::$post['billing_city'];
        $billingAddressParam->region = self::$post['billing_city'];
        $billingAddressParam->phone = self::$post['billing_phone'];
        $billingAddressParam->country = self::$post['billing_country'];

        $klarnaInfo['locale'] = $locale;
        $klarnaInfo['billingAddress'] = $billingAddressParam;
        $klarnaInfo['tax'] = $total_tax_amount_cents;
        $klarnaInfo['products'] = $products;

        return $klarnaInfo;

    }
}