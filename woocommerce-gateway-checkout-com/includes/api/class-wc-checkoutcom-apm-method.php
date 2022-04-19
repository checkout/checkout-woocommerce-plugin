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
use Checkout\Models\Payments\MultibancoSource;
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
    public function sofort() {

        $method = new SofortSource();

        return $method;
    }

    /**
     *  @return AlipaySource
     */
    public function alipay() {

        $method = new AlipaySource();

        return $method;
    }

     /**
     *  @return PoliSource
     */
    public function poli() {

        $method = new PoliSource();

        return $method;
    }

    /**
     *  @return QpaySource
     */
    public function qpay() {

        $method = new QpaySource(get_bloginfo( 'name' ));

        return $method;
    }

    /**
     *  @return GiropaySource
     */
    public function giropay() {

        $bic = self::$dataInfo['giropay-bank-details'];
        $purpose = self::$orderInfo->get_order_number(). '-' . $_SERVER['HTTP_HOST'];

        $method = new GiropaySource($purpose, $bic);

        return $method;
    }

    /**
     *  @return BoletoSource
     */
    public function boleto() {

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
    public function knet() {

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
    public function eps() {

        $purpose = get_bloginfo( 'name' );
        $method = new EpsSource($purpose);

        return $method;
    }

     /**
     *  @return BancontactSource
     */
    public function bancontact() {

        $accountHolder = self::$post['billing_first_name'] . ' '. self::$post['billing_last_name'];
        $countryCode = self::$post['billing_country'];

        $method = new BancontactSource($accountHolder, $countryCode);

        return $method;
    }

    /**
     *  @return IdSource
     */
    public function sepa() {

        $details = self::get_sepa_info();
        $method = new IdSource($details->getId());

        return $method;
    }

    /**
     *  @return IdealSource
     */
    public function ideal() {

        $bic = self::$dataInfo['issuer-id'];
        $description = self::$orderInfo->get_order_number();

        $method = new IdealSource($bic, $description);

        return $method;
    }

    /**
     *  @return FawrySource
     */
    public function fawry() {

        $fawryInfo = self::get_fawry_info();
        $method = new FawrySource($fawryInfo['email'], $fawryInfo['phone'], self::$orderInfo->get_order_number(), $fawryInfo['products']);

        return $method;
    }

    /**
     *  @return MultibancoSource
     */
    public function multibanco() {

        $accountHolder = self::$post['billing_first_name'] . ' ' . self::$post['billing_last_name'];
        $countryCode   = self::$post['billing_country'];

        $method = new MultibancoSource( $countryCode, $accountHolder );

        return $method;
    }

    /**
     *  @return KlarnaSource
     */
    public function klarna() {

        $klarnaInfo = self::get_klarna_info();
        $cartInfo = WC_Checkoutcom_Api_request::get_cart_info();

        $method = new KlarnaSource(self::$post['cko-klarna-token'], self::$post['billing_country'], strtolower($cartInfo['locale']), $klarnaInfo['billingAddress'], $cartInfo['order_tax_amount'], $cartInfo['order_lines']);

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

        global $woocommerce;

        $items           = $woocommerce->cart->get_cart();
        $is_subscription = false;

        foreach ( $items as $item => $values ) {
            $_product        = wc_get_product( $values['data']->get_id() );
            $is_subscription = WC_Subscriptions_Product::is_subscription( $_product );

            if ( $is_subscription ) {
                break;
            }
        }

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
            $is_subscription ? 'recurring' : 'single'
        );

        $sepa = new Sepa($address, $data);
        $sepa->customer = array(
          'email' => self::$post['billing_email'],
          'name' => self::$post['billing_first_name'] . ' ' . self::$post['billing_last_name']
        );

        $core_settings = get_option('woocommerce_wc_checkout_com_cards_settings');
        $environment =  $core_settings['ckocom_environment'] == 'sandbox' ? true : false;

        $core_settings['ckocom_sk'] = cko_is_nas_account() ? 'Bearer ' . $core_settings['ckocom_sk'] : $core_settings['ckocom_sk'];

        $checkout = new CheckoutApi($core_settings['ckocom_sk'], $environment);

        $details = $checkout->sources()->add($sepa);
        $responseData = $details->response_data;
        WC()->session->set('mandate_reference', $responseData['mandate_reference']);

	    if ( isset( $details->_links['sepa:mandate-cancel']['href'] ) ) {
		    WC()->session->set( 'mandate_cancel', $details->_links['sepa:mandate-cancel']['href'] );
	    }

        return $details;
    }

    /**
     * Gather billing info from Address instance
     * @return array
     */
    public static function get_klarna_info() {

        $klarnaInfo = array();

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

        $klarnaInfo['billingAddress'] = $billingAddressParam;

        return $klarnaInfo;
    }
}
