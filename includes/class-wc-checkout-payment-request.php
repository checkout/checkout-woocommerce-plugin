<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Checkout_Payment_Request class.
 */
class  WC_Checkout_Payment_Request {

	/**
	 * Current instance of this class.
	 *
	 * @var $instance Current instance of this class.
	 */
	private static $instance = null;

	/**
	 * Constructor.
	 */
	public function __construct() {

		// AJAX handlers.
		add_action( 'wp_ajax_get_shipping_options', [ $this, 'ajax_get_shipping_options' ] );
		add_action( 'wp_ajax_nopriv_get_shipping_options', [ $this, 'ajax_get_shipping_options' ] );

		add_action( 'wp_ajax_update_shipping_method', [ $this, 'ajax_update_shipping_method' ] );
		add_action( 'wp_ajax_nopriv_update_shipping_method', [ $this, 'ajax_update_shipping_method' ] );
	}

	/**
	 * Get singleton instance.
	 *
	 * @return WC_Checkout_Payment_Request
	 */
	public static function get_instance(): WC_Checkout_Payment_Request {
		if ( null === self::$instance ) {
			self::$instance = new WC_Checkout_Payment_Request();
		}

		return self::$instance;
	}

	/**
	 * Get shipping options.
	 */
	public function ajax_get_shipping_options() {
//		check_ajax_referer( 'wc-stripe-payment-request-shipping', 'security' );

		$shipping_address          = filter_input_array(
			INPUT_POST,
			[
				'country'   => FILTER_SANITIZE_STRING,
				'state'     => FILTER_SANITIZE_STRING,
				'postcode'  => FILTER_SANITIZE_STRING,
				'city'      => FILTER_SANITIZE_STRING,
				'address'   => FILTER_SANITIZE_STRING,
				'address_2' => FILTER_SANITIZE_STRING,
			]
		);

		$data = $this->get_shipping_options( $shipping_address );
		wp_send_json( $data );
	}

	/**
	 * Update shipping method.
	 */
	public function ajax_update_shipping_method() {
//		check_ajax_referer( 'wc-stripe-update-shipping-method', 'security' );

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		$shipping_methods = filter_input( INPUT_POST, 'shipping_method', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
		$this->update_shipping_method( $shipping_methods );

		WC()->cart->calculate_totals();

		$data           = [];
		$data          += $this->build_display_items();
		$data['result'] = 'success';

		wp_send_json( $data );
	}

	/**
	 * Gets shipping options available for specified shipping address
	 *
	 * @param array   $shipping_address       Shipping address.
	 * @param boolean $itemized_display_items Indicates whether to show subtotals or itemized views.
	 *
	 * @return array Shipping options data.
	 * phpcs:ignore Squiz.Commenting.FunctionCommentThrowTag
	 */
	public function get_shipping_options( $shipping_address, $itemized_display_items = false ) {
		try {
			// Set the shipping options.
			$data = [];

			// Remember current shipping method before resetting.
			$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
			$this->calculate_shipping( apply_filters( 'wc_stripe_payment_request_shipping_posted_values', $shipping_address ) );

			$packages          = WC()->shipping->get_packages();
			$shipping_rate_ids = [];

			if ( ! empty( $packages ) && WC()->customer->has_calculated_shipping() ) {
				foreach ( $packages as $package_key => $package ) {
					if ( empty( $package['rates'] ) ) {
						throw new Exception( __( 'Unable to find shipping method for address.', 'woocommerce-gateway-stripe' ) );
					}

					foreach ( $package['rates'] as $key => $rate ) {
						if ( in_array( $rate->id, $shipping_rate_ids, true ) ) {
							// The Payment Requests will try to load indefinitely if there are duplicate shipping
							// option IDs.
							throw new Exception( __( 'Unable to provide shipping options for Payment Requests.', 'woocommerce-gateway-stripe' ) );
						}
						$shipping_rate_ids[]        = $rate->id;
						$data['shipping_options'][] = [
							'identifier' => $rate->id,
							'label'  => $rate->label,
							'detail' => '',
							'amount' => $rate->cost,
						];
					}
				}
			} else {
				throw new Exception( __( 'Unable to find shipping method for address.', 'woocommerce-gateway-stripe' ) );
			}

			// The first shipping option is automatically applied on the client.
			// Keep chosen shipping method by sorting shipping options if the method still available for new address.
			// Fallback to the first available shipping method.
			if ( isset( $data['shipping_options'][0] ) ) {
				if ( isset( $chosen_shipping_methods[0] ) ) {
					$chosen_method_id         = $chosen_shipping_methods[0];
					$compare_shipping_options = function ( $a, $b ) use ( $chosen_method_id ) {
						if ( $a['identifier'] === $chosen_method_id ) {
							return -1;
						}

						if ( $b['identifier'] === $chosen_method_id ) {
							return 1;
						}

						return 0;
					};
					usort( $data['shipping_options'], $compare_shipping_options );
				}

				$first_shipping_method_id = $data['shipping_options'][0]['id'];
				$this->update_shipping_method( [ $first_shipping_method_id ] );
			}

			WC()->cart->calculate_totals();

			$data          += $this->build_display_items( $itemized_display_items );
			$data['result'] = 'success';
		} catch ( Exception $e ) {
			error_log( var_export( $e, true ) );
			$data          += $this->build_display_items( $itemized_display_items );
			$data['result'] = 'invalid_shipping_address';
		}

		return $data;
	}

	/**
	 * Calculate and set shipping method.
	 *
	 * @param array $address Shipping address.
	 *
	 * @since   3.1.0
	 * @version 5.0.0
	 */
	protected function calculate_shipping( $address = [] ) {

		$country   = $address['country'];
		$state     = $address['state'];
		$postcode  = $address['postcode'];
		$city      = $address['city'];
		$address_1 = $address['address'];
		$address_2 = $address['address_2'];

		// Normalizes state to calculate shipping zones.
		$state = $this->get_normalized_state( $state, $country );

		// Normalizes postal code in case of redacted data from Apple Pay.
		$postcode = $this->get_normalized_postal_code( $postcode, $country );

		WC()->shipping->reset_shipping();

		if ( $postcode && WC_Validation::is_postcode( $postcode, $country ) ) {
			$postcode = wc_format_postcode( $postcode, $country );
		}

		if ( $country ) {
			WC()->customer->set_location( $country, $state, $postcode, $city );
			WC()->customer->set_shipping_location( $country, $state, $postcode, $city );
		} else {
			WC()->customer->set_billing_address_to_base();
			WC()->customer->set_shipping_address_to_base();
		}

		WC()->customer->set_calculated_shipping( true );
		WC()->customer->save();

		$packages = [];

		$packages[0]['contents']                 = WC()->cart->get_cart();
		$packages[0]['contents_cost']            = 0;
		$packages[0]['applied_coupons']          = WC()->cart->applied_coupons;
		$packages[0]['user']['ID']               = get_current_user_id();
		$packages[0]['destination']['country']   = $country;
		$packages[0]['destination']['state']     = $state;
		$packages[0]['destination']['postcode']  = $postcode;
		$packages[0]['destination']['city']      = $city;
		$packages[0]['destination']['address']   = $address_1;
		$packages[0]['destination']['address_2'] = $address_2;

		foreach ( WC()->cart->get_cart() as $item ) {
			if ( $item['data']->needs_shipping() ) {
				if ( isset( $item['line_total'] ) ) {
					$packages[0]['contents_cost'] += $item['line_total'];
				}
			}
		}

		$packages = apply_filters( 'woocommerce_cart_shipping_packages', $packages );

		WC()->shipping->calculate_shipping( $packages );
	}

	/**
	 * Updates shipping method in WC session
	 *
	 * @param array $shipping_methods Array of selected shipping methods ids.
	 */
	public function update_shipping_method( $shipping_methods ) {
		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );

		if ( is_array( $shipping_methods ) ) {
			foreach ( $shipping_methods as $i => $value ) {
				$chosen_shipping_methods[ $i ] = wc_clean( $value );
			}
		}

		WC()->session->set( 'chosen_shipping_methods', $chosen_shipping_methods );
	}

	/**
	 * Builds the line items to pass to Payment Request
	 *
	 * @since   3.1.0
	 * @version 4.0.0
	 */
	protected function build_display_items( $itemized_display_items = false ) {
		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		$items         = [];
		$lines         = [];
		$subtotal      = 0;
		$discounts     = 0;
		$display_items = ! apply_filters( 'wc_stripe_payment_request_hide_itemization', true ) || $itemized_display_items;

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$subtotal      += $cart_item['line_subtotal'];
			$amount         = $cart_item['line_subtotal'];
			$quantity_label = 1 < $cart_item['quantity'] ? ' (x' . $cart_item['quantity'] . ')' : '';
			$product_name   = $cart_item['data']->get_name();

			$lines[] = [
				'label'  => $product_name . $quantity_label,
				'amount' => $amount,
			];
		}

		if ( $display_items ) {
			$items = array_merge( $items, $lines );
		} else {
			// Default show only subtotal instead of itemization.

			$items[] = [
				'label'  => 'Subtotal',
				'amount' => $subtotal,
			];
		}

		if ( version_compare( WC_VERSION, '3.2', '<' ) ) {
			$discounts = wc_format_decimal( WC()->cart->get_cart_discount_total(), WC()->cart->dp );
		} else {
			$applied_coupons = array_values( WC()->cart->get_coupon_discount_totals() );

			foreach ( $applied_coupons as $amount ) {
				$discounts += (float) $amount;
			}
		}

		$discounts   = wc_format_decimal( $discounts, WC()->cart->dp );
		$tax         = wc_format_decimal( WC()->cart->tax_total + WC()->cart->shipping_tax_total, WC()->cart->dp );
		$shipping    = wc_format_decimal( WC()->cart->shipping_total, WC()->cart->dp );
		$items_total = wc_format_decimal( WC()->cart->cart_contents_total, WC()->cart->dp ) + $discounts;
		$order_total = version_compare( WC_VERSION, '3.2', '<' ) ? wc_format_decimal( $items_total + $tax + $shipping - $discounts, WC()->cart->dp ) : WC()->cart->get_total( false );

		if ( wc_tax_enabled() ) {
			$items[] = [
				'label'  => esc_html( __( 'Tax', 'woocommerce-gateway-stripe' ) ),
				'amount' => $tax,
			];
		}

		if ( WC()->cart->needs_shipping() ) {
			$items[] = [
				'label'  => esc_html( __( 'Shipping', 'woocommerce-gateway-stripe' ) ),
				'amount' => $shipping,
			];
		}

		if ( WC()->cart->has_discount() ) {
			$items[] = [
				'label'  => esc_html( __( 'Discount', 'woocommerce-gateway-stripe' ) ),
				'amount' => $discounts,
			];
		}

		if ( version_compare( WC_VERSION, '3.2', '<' ) ) {
			$cart_fees = WC()->cart->fees;
		} else {
			$cart_fees = WC()->cart->get_fees();
		}

		// Include fees and taxes as display items.
		foreach ( $cart_fees as $key => $fee ) {
			$items[] = [
				'label'  => $fee->name,
				'amount' => $fee->amount,
			];
		}

		return [
			'displayItems' => $items,
			'total'        => [
				'label'   => '', //$this->total_label,
				'amount'  => max( 0, $order_total ),
				'type' => 'final',
			],
		];
	}

	/**
	 * Gets the normalized state/county field because in some
	 * cases, the state/county field is formatted differently from
	 * what WC is expecting and throws an error. An example
	 * for Ireland, the county dropdown in Chrome shows "Co. Clare" format.
	 *
	 * @since 5.0.0
	 * @version 5.1.0
	 *
	 * @param string $state   Full state name or an already normalized abbreviation.
	 * @param string $country Two-letter country code.
	 *
	 * @return string Normalized state abbreviation.
	 */
	public function get_normalized_state( $state, $country ) {
		// If it's empty or already normalized, skip.
		if ( ! $state || $this->is_normalized_state( $state, $country ) ) {
			return $state;
		}

		// Try to match state from the Payment Request API list of states.
		$state = $this->get_normalized_state_from_pr_states( $state, $country );

		// If it's normalized, return.
		if ( $this->is_normalized_state( $state, $country ) ) {
			return $state;
		}

		// If the above doesn't work, fallback to matching against the list of translated
		// states from WooCommerce.
		return $this->get_normalized_state_from_wc_states( $state, $country );
	}

	/**
	 * Normalizes postal code in case of redacted data from Apple Pay.
	 *
	 * @since 5.2.0
	 *
	 * @param string $postcode Postal code.
	 * @param string $country Country.
	 */
	public function get_normalized_postal_code( $postcode, $country ) {
		/**
		 * Currently, Apple Pay truncates the UK and Canadian postal codes to the first 4 and 3 characters respectively
		 * when passing it back from the shippingcontactselected object. This causes WC to invalidate
		 * the postal code and not calculate shipping zones correctly.
		 */
		if ( 'GB' === $country ) {
			// Replaces a redacted string with something like LN10***.
			return str_pad( preg_replace( '/\s+/', '', $postcode ), 7, '*' );
		}
		if ( 'CA' === $country ) {
			// Replaces a redacted string with something like L4Y***.
			return str_pad( preg_replace( '/\s+/', '', $postcode ), 6, '*' );
		}

		return $postcode;
	}

	/**
	 * Get Stripe amount to pay
	 *
	 * @param float  $total Amount due.
	 * @param string $currency Accepted currency.
	 *
	 * @return float|int
	 */
	public static function get_stripe_amount( $total, $currency = '' ) {
		if ( ! $currency ) {
			$currency = get_woocommerce_currency();
		}

		if ( in_array( strtolower( $currency ), self::no_decimal_currencies() ) ) {
			return absint( $total );
		} else {
			return absint( wc_format_decimal( ( (float) $total * 100 ), wc_get_price_decimals() ) ); // In cents.
		}
	}

	/**
	 * List of currencies supported by Stripe that has no decimals
	 * https://stripe.com/docs/currencies#zero-decimal from https://stripe.com/docs/currencies#presentment-currencies
	 *
	 * @return array $currencies
	 */
	public static function no_decimal_currencies() {
		return [
			'bif', // Burundian Franc
			'clp', // Chilean Peso
			'djf', // Djiboutian Franc
			'gnf', // Guinean Franc
			'jpy', // Japanese Yen
			'kmf', // Comorian Franc
			'krw', // South Korean Won
			'mga', // Malagasy Ariary
			'pyg', // Paraguayan Guaraní
			'rwf', // Rwandan Franc
			'ugx', // Ugandan Shilling
			'vnd', // Vietnamese Đồng
			'vuv', // Vanuatu Vatu
			'xaf', // Central African Cfa Franc
			'xof', // West African Cfa Franc
			'xpf', // Cfp Franc
		];
	}

}

WC_Checkout_Payment_Request::get_instance();
