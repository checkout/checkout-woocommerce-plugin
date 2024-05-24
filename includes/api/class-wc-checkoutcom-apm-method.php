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
use Checkout\Payments\Previous\Source\Apm\RequestGiropaySource;
use Checkout\Payments\Previous\Source\Apm\RequestKlarnaSource;
use Checkout\Payments\Previous\Source\Apm\RequestPoliSource;
use Checkout\Payments\Previous\Source\Apm\RequestSepaSource;
use Checkout\Payments\Request\Source\Apm\FawryProduct;
use Checkout\Payments\Request\Source\Apm\RequestBancontactSource;
use Checkout\Payments\Request\Source\Apm\RequestFawrySource;
use Checkout\Payments\Request\Source\Apm\RequestIdealSource;
use Checkout\Payments\Request\Source\Apm\RequestKnetSource;
use Checkout\Payments\Request\Source\Apm\RequestMultiBancoSource;
use Checkout\Payments\Request\Source\Apm\RequestQPaySource;
use Checkout\Payments\Request\Source\Apm\RequestSofortSource;
use Checkout\Sources\Previous\SepaSourceRequest;
use Checkout\Sources\Previous\SourceData;

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
	function __construct( $data, $order ) {

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
	 * Returns the source data for the giropay APM.
	 *
	 * @return RequestGiropaySource
	 */
	public function giropay() {

		$method          = new RequestGiropaySource();
		$method->purpose = 'Giropay by Checkout.com';

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
	 * Returns the source data for the sepa APM.
	 *
	 * @return RequestSepaSource
	 */
	public function sepa() {

		$details = self::get_sepa_info();

		$method = new RequestSepaSource();

		$method->id = $details['id'];

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
	 * Gather info for sepa payment and create source ID.
	 *
	 * @return array
	 */
	public static function get_sepa_info() {

		$get_data        = sanitize_post( $_GET );
		$items           = WC()->cart->get_cart();
		$is_subscription = false;
		$is_pay_order    = ! empty( $get_data['pay_for_order'] ) && (bool) $get_data['pay_for_order'];

		foreach ( $items as $item => $values ) {
			$_product        = wc_get_product( $values['data']->get_id() );
			$is_subscription = WC_Subscriptions_Product::is_subscription( $_product );

			if ( $is_subscription ) {
				break;
			}
		}

		if ( $is_pay_order ) {
			self::$post['billing_address_1']  = self::$order_info->get_billing_address_1();
			self::$post['billing_address_2']  = self::$order_info->get_billing_address_2();
			self::$post['billing_city']       = self::$order_info->get_billing_city();
			self::$post['billing_postcode']   = self::$order_info->get_billing_postcode();
			self::$post['billing_country']    = self::$order_info->get_billing_country();
			self::$post['billing_first_name'] = self::$order_info->get_billing_first_name();
			self::$post['billing_last_name']  = self::$order_info->get_billing_last_name();
			self::$post['billing_email']      = self::$order_info->get_billing_email();
		}

		$address                = new Address();
		$address->address_line1 = self::$post['billing_address_1'];
		$address->address_line2 = self::$post['billing_address_2'];
		$address->city          = self::$post['billing_city'];
		$address->zip           = self::$post['billing_postcode'];
		$address->country       = self::$post['billing_country'];

		$address = apply_filters( 'checkout_apm_sepa_address', $address );

		$source_data                     = new SourceData();
		$source_data->first_name         = self::$post['billing_first_name'];
		$source_data->last_name          = self::$post['billing_last_name'];
		$source_data->account_iban       = self::$post['sepa-iban'];
		$source_data->bic                = ! empty( self::$post['sepa-bic'] ) ? self::$post['sepa-bic'] : '';
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

				WC()->session->set( 'mandate_reference', $response_data['mandate_reference'] );

				if ( isset( $response['_links']['sepa:mandate-cancel']['href'] ) ) {
					WC()->session->set( 'mandate_cancel', $response['_links']['sepa:mandate-cancel']['href'] );
				}
			}
		} catch ( CheckoutApiException $ex ) {
			// Unset any old value if source creation failed.
			WC()->session->__unset( 'mandate_reference' );

			$error_message = __( 'An error has occurred while getting sepa info.', 'checkout-com-unified-payments-api' );
			WC_Checkoutcom_Utility::logger( $error_message, $ex );
		}

		return $response;
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
