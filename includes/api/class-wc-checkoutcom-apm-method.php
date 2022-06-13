<?php

use Checkout\CheckoutApiException;
use Checkout\Common\Address;
use Checkout\Common\CustomerRequest;
use Checkout\Payments\Payer;
use Checkout\Payments\Source\Apm\FawryProduct;
use Checkout\Payments\Source\Apm\IntegrationType;
use Checkout\Payments\Source\Apm\RequestAlipaySource;
use Checkout\Payments\Source\Apm\RequestBancontactSource;
use Checkout\Payments\Source\Apm\RequestBoletoSource;
use Checkout\Payments\Source\Apm\RequestEpsSource;
use Checkout\Payments\Source\Apm\RequestFawrySource;
use Checkout\Payments\Source\Apm\RequestGiropaySource;
use Checkout\Payments\Source\Apm\RequestIdealSource;
use Checkout\Payments\Source\Apm\RequestKlarnaSource;
use Checkout\Payments\Source\Apm\RequestKnetSource;
use Checkout\Payments\Source\Apm\RequestMultiBancoSource;
use Checkout\Payments\Source\Apm\RequestPoliSource;
use Checkout\Payments\Source\Apm\RequestQPaySource;
use Checkout\Payments\Source\Apm\RequestSepaSource;
use Checkout\Payments\Source\Apm\RequestSofortSource;
use Checkout\Sources\SepaSourceRequest;
use Checkout\Sources\SourceData;

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
     *  @return RequestSofortSource
     */
    public function sofort() {

	    return new RequestSofortSource();
    }

    /**
     *  @return RequestAlipaySource
     */
    public function alipay() {

        $method = new RequestAlipaySource();

        return $method;
    }

     /**
     *  @return RequestPoliSource
     */
    public function poli() {

	    return new RequestPoliSource();
    }

    /**
     *  @return RequestQPaySource
     */
    public function qpay() {

	    $method              = new RequestQPaySource();
	    $method->description = get_bloginfo( 'name' );

        return $method;
    }

    /**
     *  @return RequestGiropaySource
     */
    public function giropay() {

	    $method          = new RequestGiropaySource();
	    $method->purpose = self::$orderInfo->get_order_number(). '-' . $_SERVER['HTTP_HOST'];

        return $method;
    }

    /**
     *  @return RequestBoletoSource
     */
    public function boleto() {

	    $payer           = new Payer();
	    $payer->name     = self::$dataInfo['name'];
	    $payer->email    = self::$dataInfo['billing_email'];
	    $payer->document = self::$dataInfo['cpf'];

	    $method                   = new RequestBoletoSource();
	    $method->integration_type = IntegrationType::$redirect;
	    $method->country          = self::$post['billing_country'];
	    $method->payer            = $payer;

	    return $method;
    }

    /**
     *  @return RequestKnetSource
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

	    $method           = new RequestKnetSource();
	    $method->language = $language;

        return $method;
    }

    /**
     *  @return RequestEpsSource
     */
    public function eps() {

	    $method          = new RequestEpsSource();
	    $method->purpose = get_bloginfo( 'name' );

        return $method;
    }

     /**
     *  @return RequestBancontactSource
     */
    public function bancontact() {

	    $method                      = new RequestBancontactSource();
	    $method->account_holder_name = self::$post['billing_first_name'] . ' '. self::$post['billing_last_name'];
	    $method->payment_country     = self::$post['billing_country'];

        return $method;
    }

    /**
     *  @return RequestSepaSource
     */
    public function sepa() {

        $details = self::get_sepa_info();

	    $method = new RequestSepaSource();

		$method->id = $details['id'];

        return $method;
    }

    /**
     *  @return RequestIdealSource
     */
    public function ideal() {

		$method              = new RequestIdealSource();
        $method->bic         = self::$dataInfo['issuer-id'];
        $method->description = self::$orderInfo->get_order_number();

        return $method;
    }

    /**
     *  @return RequestFawrySource
     */
	public function fawry() {

		$fawryInfo = self::get_fawry_info();

		$method                  = new RequestFawrySource();
		$method->customer_email  = $fawryInfo['email'];
		$method->customer_mobile = $fawryInfo['phone'];
		$method->description     = self::$orderInfo->get_order_number();
		$method->products        = $fawryInfo['products'];

		return $method;
	}

    /**
     *  @return RequestMultiBancoSource
     */
    public function multibanco() {

	    $method                      = new RequestMultiBancoSource();
	    $method->account_holder_name = self::$post['billing_first_name'] . ' ' . self::$post['billing_last_name'];
	    $method->payment_country     = self::$post['billing_country'];

        return $method;
    }

    /**
     *  @return RequestKlarnaSource
     */
    public function klarna() {

        $klarnaInfo = self::get_klarna_info();
        $cartInfo = WC_Checkoutcom_Api_request::get_cart_info();

	    $method                      = new RequestKlarnaSource();
	    $method->authorization_token = self::$post['cko-klarna-token'];
	    $method->purchase_country    = self::$post['billing_country'];
	    $method->locale              = strtolower( $cartInfo['locale'] );
	    $method->tax_amount          = $cartInfo['order_tax_amount'];
	    $method->products            = $cartInfo['order_lines'];
		$method->billing_address     = $klarnaInfo['billingAddress'];

        return $method;
    }

    /**
     * GET APM INFO
     */

    /**
     * Gather fawry info.
     *
     * @return array
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

        foreach ( $productInfo as $item ) {

	        $fawry_product              = new FawryProduct();
	        $fawry_product->product_id  = $item['name'];
	        $fawry_product->quantity    = $item['quantity'];
	        $fawry_product->price       = $item['unit_price'];
	        $fawry_product->description = $item['name'];

			$products[] = $fawry_product;

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
     * @return array
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

	    $address                = new Address();
	    $address->address_line1 = self::$post['billing_address_1'];
	    $address->address_line2 = self::$post['billing_address_2'];
	    $address->city          = self::$post['billing_city'];
	    $address->zip           = self::$post['billing_postcode'];
	    $address->country       = self::$post['billing_country'];

	    $source_data                     = new SourceData();
	    $source_data->first_name         = self::$post['billing_first_name'];
	    $source_data->last_name          = self::$post['billing_last_name'];
	    $source_data->account_iban       = self::$post['sepa-iban'];
	    $source_data->bic                = self::$post['sepa-bic'];
	    $source_data->billing_descriptor = 'Thanks for shopping.';
	    $source_data->mandate_type       = $is_subscription ? 'recurring' : 'single';

	    $customer_request        = new CustomerRequest();
	    $customer_request->email = self::$post['billing_email'];
	    $customer_request->name  = self::$post['billing_first_name'] . ' ' . self::$post['billing_last_name'];

	    $sepa_source_request                  = new SepaSourceRequest();
	    $sepa_source_request->billing_address = $address;
	    $sepa_source_request->source_data     = $source_data;
	    $sepa_source_request->customer        = $customer_request;

	    $checkout = new Checkout_SDK();

	    $response = [];

	    try {

		    $builder = $checkout->get_builder();

			// SEPA support for ABC AC type only.
			if ( method_exists( $builder, 'getSourcesClient' ) ) {

				$response = $builder->getSourcesClient()->createSepaSource( $sepa_source_request );

				$response_data = $response['response_data'];

			    WC()->session->set('mandate_reference', $response_data['mandate_reference']);

			    if ( isset( $response['_links']['sepa:mandate-cancel']['href'] ) ) {
				    WC()->session->set( 'mandate_cancel', $response['_links']['sepa:mandate-cancel']['href'] );
			    }
			}
	    } catch ( CheckoutApiException $ex ) {
		    $error_message = __( 'An error has occurred while getting sepa info.', 'wc_checkout_com' );
		    WC_Checkoutcom_Utility::logger( $error_message, $ex );
	    }

	    return $response;
    }

    /**
     * Gather billing info from Address instance
     * @return array
     */
    public static function get_klarna_info() {

	    $klarna_info = array();

	    // Set Billing address.
	    $billing_address_param                 = new Address();
	    $billing_address_param->given_name     = self::$post['billing_first_name'];
	    $billing_address_param->family_name    = self::$post['billing_last_name'];
	    $billing_address_param->email          = self::$post['billing_email'];
	    $billing_address_param->street_address = self::$post['billing_address_1'];
	    $billing_address_param->postal_code    = self::$post['billing_postcode'];
	    $billing_address_param->city           = self::$post['billing_city'];
	    $billing_address_param->region         = self::$post['billing_city'];
	    $billing_address_param->phone          = self::$post['billing_phone'];
	    $billing_address_param->country        = self::$post['billing_country'];

	    $klarna_info['billingAddress'] = $billing_address_param;

	    return $klarna_info;
    }
}
