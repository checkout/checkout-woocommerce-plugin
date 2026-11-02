<?php
/**
 * APMs source generate class.
 *
 * @package wc_checkout_com
 */

use Checkout\CheckoutApiException;
use Checkout\Common\Address;
use Checkout\Common\CustomerRequest;
use Checkout\Payments\Payer;
use Checkout\Payments\Previous\Source\Apm\IntegrationType;
use Checkout\Payments\Previous\Source\Apm\RequestAlipaySource;
use Checkout\Payments\Previous\Source\Apm\RequestBoletoSource;
use Checkout\Payments\Previous\Source\Apm\RequestEpsSource;
use Checkout\Payments\Previous\Source\Apm\RequestKlarnaSource;
use Checkout\Payments\Previous\Source\Apm\RequestPoliSource;
use Checkout\Payments\Request\Source\Apm\FawryProduct;
use Checkout\Payments\Request\Source\Apm\RequestBancontactSource;
use Checkout\Payments\Request\Source\Apm\RequestFawrySource;
use Checkout\Payments\Request\Source\Apm\RequestIdealSource;
use Checkout\Payments\Request\Source\Apm\RequestKnetSource;
use Checkout\Payments\Request\Source\Apm\RequestMultiBancoSource;
use Checkout\Payments\Request\Source\Apm\RequestQPaySource;
use Checkout\Payments\Request\Source\Apm\RequestSofortSource;

/**
 * Class WC_Checkoutcom_APM_Method gives source class for different APMs.
 */
class WC_Checkoutcom_APM_Method {

	/**
	 * Post data.
	 *
	 * @var array
	 */
	public static $post;

	/**
	 * Post data.
	 *
	 * @var array
	 */
	public static $data_info;

	/**
	 * Order object.
	 *
	 * @var WC_Order
	 */
	public static $order_info;

	/**
	 * WC_Checkoutcom_APM_Method constructor.
	 *
	 * @param array    $data Post data.
	 * @param WC_Order $order Order object.
	 */
	public function __construct( $data, $order ) {

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::$post       = sanitize_post( $_POST );
		self::$data_info  = $data;
		self::$order_info = $order;
	}

	/**
	 * Returns the source data for the sofort APM.
	 *
	 * @return RequestSofortSource
	 */
	public function sofort() {

		return new RequestSofortSource();
	}

	/**
	 * Returns the source data for the alipay APM.
	 *
	 * @return RequestAlipaySource
	 */
	public function alipay() {

		return new RequestAlipaySource();
	}

	/**
	 * Returns the source data for the poli APM.
	 *
	 * @return RequestPoliSource
	 */
	public function poli() {

		return new RequestPoliSource();
	}

	/**
	 * Returns the source data for the qpay APM.
	 *
	 * @return RequestQPaySource
	 */
	public function qpay() {

		$method              = new RequestQPaySource();
		$method->description = get_bloginfo( 'name' );

		return $method;
	}

	/**
	 * Returns the source data for the boleto APM.
	 *
	 * @return RequestBoletoSource
	 */
	public function boleto() {

		$payer           = new Payer();
		$payer->name     = self::$data_info['name'];
		$payer->email    = self::$data_info['billing_email'];
		$payer->document = self::$data_info['cpf'];

		$method                   = new RequestBoletoSource();
		$method->integration_type = IntegrationType::$redirect;
		$method->country          = self::$post['billing_country'];
		$method->payer            = $payer;

		return $method;
	}

	/**
	 * Returns the source data for the knet APM.
	 *
	 * @return RequestKnetSource
	 */
	public function knet() {

		$language = get_locale();

		switch ( $language ) {
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
	 * Returns the source data for the eps APM.
	 *
	 * @return RequestEpsSource
	 */
	public function eps() {

		$method          = new RequestEpsSource();
		$method->purpose = get_bloginfo( 'name' );

		return $method;
	}

	/**
	 * Returns the source data for the bancontact APM.
	 *
	 * @return RequestBancontactSource
	 */
	public function bancontact() {

		$method                      = new RequestBancontactSource();
		$method->account_holder_name = self::$post['billing_first_name'] . ' ' . self::$post['billing_last_name'];
		$method->payment_country     = self::$post['billing_country'];

		return $method;
	}


	/**
	 * Returns the source data for the ideal APM.
	 *
	 * @return RequestIdealSource
	 */
	public function ideal() {

		$method              = new RequestIdealSource();
		$method->description = self::$order_info->get_order_number();

		return $method;
	}

	/**
	 * Returns the source data for the fawry APM.
	 *
	 * @return RequestFawrySource
	 */
	public function fawry() {

		$fawry_info = self::get_fawry_info();

		$method                  = new RequestFawrySource();
		$method->customer_email  = $fawry_info['email'];
		$method->customer_mobile = $fawry_info['phone'];
		$method->description     = self::$order_info->get_order_number();
		$method->products        = $fawry_info['products'];

		return $method;
	}

	/**
	 * Returns the source data for the multibanco APM.
	 *
	 * @return RequestMultiBancoSource
	 */
	public function multibanco() {

		$method                      = new RequestMultiBancoSource();
		$method->account_holder_name = self::$post['billing_first_name'] . ' ' . self::$post['billing_last_name'];
		$method->payment_country     = self::$post['billing_country'];

		return $method;
	}

	/**
	 * Returns the source data for the klarna APM.
	 *
	 * @return RequestKlarnaSource
	 */
	public function klarna() {

		$klarna_info = self::get_klarna_info();
		$cart_info   = WC_Checkoutcom_Api_Request::get_cart_info();

		$method                      = new RequestKlarnaSource();
		$method->authorization_token = self::$post['cko-klarna-token'];
		$method->purchase_country    = self::$post['billing_country'];
		$method->locale              = strtolower( $cart_info['locale'] );
		$method->tax_amount          = $cart_info['order_tax_amount'];
		$method->products            = $cart_info['order_lines'];
		$method->billing_address     = $klarna_info['billingAddress'];

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

		$fawry_info = [];

		$fawry_info['email'] = self::$post['billing_email'];
		$fawry_info['phone'] = self::$post['billing_phone'];

		$cart_info = WC_Checkoutcom_Api_Request::get_cart_info();

		$product_info         = $cart_info['order_lines'];
		$order_amount         = $cart_info['order_amount'];
		$products             = [];
		$total_product_amount = 0;

		foreach ( $product_info as $item ) {

			$fawry_product              = new FawryProduct();
			$fawry_product->product_id  = $item['name'];
			$fawry_product->quantity    = $item['quantity'];
			$fawry_product->price       = $item['unit_price'];
			$fawry_product->description = $item['name'];

			$products[] = $fawry_product;

			$total_product_amount += $item['unit_price'] * $item['quantity'];
		}

		if ( $total_product_amount !== $order_amount ) {

			WC_Checkoutcom_Utility::logger( "Total product amount {$total_product_amount} does not match order amount {$order_amount}", null );

			$product[] = WC_Checkoutcom_Api_Request::format_fawry_product( $products, $order_amount );

			$products = $product;
		}

		$fawry_info['products'] = $products;

		return $fawry_info;
	}

	/**
	 * Gather billing info from Address instance
	 *
	 * @return array
	 */
	public static function get_klarna_info() {

		$klarna_info = [];

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
