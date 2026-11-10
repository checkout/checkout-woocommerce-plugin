<?php
/**
 * Shipping and total preview for the Apple Pay / Google Pay Express wallet sheets.
 *
 * @package wc_checkout_com
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WC_Checkoutcom_Express_Shipping_Preview' ) ) {
	return;
}

/**
 * Rates the cart for the (partial) shipping address selected in a wallet sheet, so the sheet can
 * show the total the order will be created for. The express order step still recalculates the
 * cart and compares it with the approved total before charging.
 */
class WC_Checkoutcom_Express_Shipping_Preview {

	/**
	 * Partial shipping address posted by the express script (the wallets only share the country,
	 * state, postcode and city before the payment is authorised).
	 *
	 * @return array{country: string, state: string, postcode: string, city: string}
	 */
	public static function get_posted_address() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- the caller verifies the nonce first.
		$read = function ( $key, $max_length ) {
			if ( ! isset( $_POST[ $key ] ) || ! is_string( $_POST[ $key ] ) ) {
				return '';
			}
			$value = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );

			return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max_length ) : substr( $value, 0, $max_length );
		};

		$country = strtoupper( $read( 'country', 2 ) );
		$address = array(
			'country'  => 1 === preg_match( '/^[A-Z]{2}$/', $country ) ? $country : '',
			'state'    => $read( 'state', 100 ),
			'postcode' => $read( 'postcode', 20 ),
			'city'     => $read( 'city', 100 ),
		);
		// phpcs:enable

		return $address;
	}

	/**
	 * Shipping rate id posted by the express script ('' when none was chosen in the sheet).
	 *
	 * @return string
	 */
	public static function get_posted_shipping_method() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- the caller verifies the nonce first.
		if ( ! isset( $_POST['shipping_method'] ) || ! is_string( $_POST['shipping_method'] ) ) {
			return '';
		}
		$method = wc_clean( wp_unslash( $_POST['shipping_method'] ) );
		// phpcs:enable

		return is_string( $method ) ? substr( $method, 0, 200 ) : '';
	}

	/**
	 * Point the cart at the wallet address, optionally choose a shipping rate, recalculate shipping
	 * and totals, and describe the result for the wallet sheet.
	 *
	 * @param array  $address         Partial address ('country', 'state', 'postcode', 'city').
	 * @param string $shipping_method Shipping rate id chosen in the sheet, or '' to keep the current one.
	 * @param bool   $copy_to_billing Whether the express order uses this address for billing too
	 *                                (otherwise the billing location is only filled in when empty).
	 * @return array|null Preview data, or null when there is no cart to rate.
	 */
	public static function preview( array $address, $shipping_method = '', $copy_to_billing = false ) {
		$cart     = WC()->cart;
		$customer = WC()->customer;

		if ( ! $cart || $cart->is_empty() || ! $customer instanceof WC_Customer ) {
			return null;
		}

		$country  = isset( $address['country'] ) ? (string) $address['country'] : '';
		$state    = isset( $address['state'] ) ? (string) $address['state'] : '';
		$postcode = isset( $address['postcode'] ) ? (string) $address['postcode'] : '';
		$city     = isset( $address['city'] ) ? (string) $address['city'] : '';

		if ( '' !== $country ) {
			$customer->set_shipping_country( $country );
			$customer->set_shipping_state( $state );
			$customer->set_shipping_postcode( $postcode );
			$customer->set_shipping_city( $city );

			if ( $copy_to_billing || '' === (string) $customer->get_billing_country() ) {
				$customer->set_billing_country( $country );
				$customer->set_billing_state( $state );
				$customer->set_billing_postcode( $postcode );
				$customer->set_billing_city( $city );
			}

			$customer->set_calculated_shipping( true );
			$customer->save();
		}

		// Rate the cart for the address first: WooCommerce resets the chosen rate when the
		// address changes the available rates, so the shopper's choice is applied afterwards.
		WC()->shipping()->reset_shipping();
		$cart->calculate_totals();

		if ( '' !== $shipping_method && WC()->session ) {
			$rated    = WC()->shipping()->get_packages();
			$is_rated = ! empty( $rated[0]['rates'] ) && isset( $rated[0]['rates'][ $shipping_method ] );
			if ( $is_rated ) {
				$chosen    = (array) WC()->session->get( 'chosen_shipping_methods', array() );
				$chosen[0] = $shipping_method;
				WC()->session->set( 'chosen_shipping_methods', $chosen );
				WC()->shipping()->reset_shipping();
				$cart->calculate_totals();
			}
		}

		$needs_shipping = $cart->needs_shipping();
		$packages       = $needs_shipping ? WC()->shipping()->get_packages() : array();
		$chosen         = WC()->session ? (array) WC()->session->get( 'chosen_shipping_methods', array() ) : array();

		// Same rule as the express order step: every package needs a rate for the address.
		$shipping_available = true;
		if ( $needs_shipping ) {
			if ( '' === $country || empty( $packages ) ) {
				$shipping_available = false;
			}
			foreach ( $packages as $package_key => $package ) {
				if ( empty( $package['rates'] ) || ! isset( $chosen[ $package_key ] ) || ! isset( $package['rates'][ $chosen[ $package_key ] ] ) ) {
					$shipping_available = false;
				}
			}
		}

		// The sheet offers the rates of the first package (the usual single-package cart).
		$rates       = array();
		$chosen_rate = '';
		$first_key   = ! empty( $packages ) ? array_key_first( $packages ) : null;
		$first       = null !== $first_key ? $packages[ $first_key ] : null;
		if ( $shipping_available && is_array( $first ) && ! empty( $first['rates'] ) ) {
			$with_tax = $cart->display_prices_including_tax();
			foreach ( $first['rates'] as $rate_id => $rate ) {
				if ( ! $rate instanceof WC_Shipping_Rate ) {
					continue;
				}
				$amount = (float) $rate->get_cost();
				if ( $with_tax ) {
					$amount += array_sum( array_map( 'floatval', (array) $rate->get_taxes() ) );
				}
				$rates[] = array(
					'id'        => (string) $rate_id,
					'label'     => wp_strip_all_tags( $rate->get_label() ),
					'amount'    => self::amount( $amount ),
					'formatted' => self::formatted( $amount ),
				);
			}
			$chosen_rate = isset( $chosen[ $first_key ] ) ? (string) $chosen[ $first_key ] : '';
		}

		$line_items = array(
			array(
				'type'   => 'subtotal',
				'label'  => __( 'Subtotal', 'checkout-com-unified-payments-api' ),
				'amount' => self::amount( $cart->get_subtotal() ),
			),
		);
		$discount = (float) $cart->get_discount_total();
		if ( $discount > 0 ) {
			$line_items[] = array(
				'type'   => 'discount',
				'label'  => __( 'Discount', 'checkout-com-unified-payments-api' ),
				'amount' => self::amount( -1 * $discount ),
			);
		}
		$fees = (float) $cart->get_fee_total();
		if ( 0.0 !== $fees ) {
			$line_items[] = array(
				'type'   => 'fee',
				'label'  => __( 'Fees', 'checkout-com-unified-payments-api' ),
				'amount' => self::amount( $fees ),
			);
		}
		if ( $needs_shipping && $shipping_available ) {
			$line_items[] = array(
				'type'   => 'shipping',
				'label'  => __( 'Shipping', 'checkout-com-unified-payments-api' ),
				'amount' => self::amount( $cart->get_shipping_total() ),
			);
		}
		$tax = (float) $cart->get_total_tax();
		if ( wc_tax_enabled() && $tax > 0 ) {
			$line_items[] = array(
				'type'   => 'tax',
				'label'  => __( 'Tax', 'checkout-com-unified-payments-api' ),
				'amount' => self::amount( $tax ),
			);
		}

		return array(
			'total'                => self::amount( $cart->get_total( 'edit' ) ),
			'currency'             => get_woocommerce_currency(),
			'needs_shipping'       => $needs_shipping,
			'shipping_available'   => $shipping_available,
			'error'                => $shipping_available ? '' : 'no_shipping_rate',
			'message'              => $shipping_available ? '' : __( 'Sorry, we cannot ship to the address selected in your wallet. Please choose another address or use a different payment method.', 'checkout-com-unified-payments-api' ),
			'line_items'           => $line_items,
			'shipping_rates'       => $rates,
			'chosen_shipping_rate' => $chosen_rate,
		);
	}

	/**
	 * Amount rounded to the store price decimals.
	 *
	 * @param mixed $value Amount.
	 * @return float
	 */
	private static function amount( $value ) {
		return (float) wc_format_decimal( (float) $value, wc_get_price_decimals() );
	}

	/**
	 * Amount as plain text in the store currency format (for the wallet rate description).
	 *
	 * @param float $value Amount.
	 * @return string
	 */
	private static function formatted( $value ) {
		return html_entity_decode( wp_strip_all_tags( wc_price( $value ) ), ENT_QUOTES, 'UTF-8' );
	}
}
