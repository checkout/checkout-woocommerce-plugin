<?php
/**
 * Utility class.
 *
 * @package wc_checkout_com
 */

/**
 * Class WC_Checkoutcom_Utility
 */
class WC_Checkoutcom_Utility {

	/**
	 * Verify cko signature for webhooks.
	 *
	 * @param string $event Data.
	 * @param string $key Secret key.
	 * @param string $cko_signature Header signature.
	 *
	 * @return bool
	 */
	public static function verify_signature( $event, $key, $cko_signature ) {
		return hash_hmac( 'sha256', $event, $key ) === $cko_signature;
	}

	/**
	 * Format amount in cents.
	 *
	 * @param integer $amount Amount in cents.
	 * @param string  $currency_symbol Currency symbol.
	 *
	 * @return float|int
	 */
	public static function value_to_decimal( $amount, $currency_symbol ) {
		$currency                    = strtoupper( $currency_symbol );
		$three_decimal_currency_list = [ 'BHD', 'LYD', 'JOD', 'IQD', 'KWD', 'OMR', 'TND' ];
		$zero_decimal_currency_list  = [
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
		];

		if ( in_array( $currency, $three_decimal_currency_list, true ) ) {
			$value = (int) ( $amount * 1000 );
		} elseif ( in_array( $currency, $zero_decimal_currency_list, true ) ) {
			$value = floor( $amount );
		} else {
			$value = round( $amount * 100 );
		}

		return $value;
	}

	/**
	 * Format amount in decimal.
	 *
	 * @param integer $amount Amount in cents.
	 * @param string  $currency_symbol Currency symbol.
	 *
	 * @return float|int
	 */
	public static function decimal_to_value( $amount, $currency_symbol ) {
		$currency                    = strtoupper( $currency_symbol );
		$three_decimal_currency_list = [ 'BHD', 'LYD', 'JOD', 'IQD', 'KWD', 'OMR', 'TND' ];
		$zero_decimal_currency_list  = [
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
		];

		if ( in_array( $currency, $three_decimal_currency_list, true ) ) {
			$value = $amount / 1000;
		} elseif ( in_array( $currency, $zero_decimal_currency_list, true ) ) {
			$value = $amount;
		} else {
			$value = $amount / 100;
		}

		return $value;
	}

	/**
	 * Add a delay to the current URC time
	 *
	 * @return DateTime ISO 8601 timestamp of UTC current time plus delays
	 *
	 * @throws Exception If the date cannot be parsed.
	 */
	public static function get_delayed_capture_timestamp() {
		// Specify a 10 seconds delay even if the autocapture time is set to 0 to avoid webhook issues.
		$default_seconds_delay = 10;
		$delay                 = preg_replace( '/\s/', '', WC_Admin_Settings::get_option( 'ckocom_card_cap_delay' ) );
		// If the input of the delay is numeric.
		if ( is_numeric( $delay ) ) {
			// Get total seconds based on the hour input.
			$total_seconds = round( $delay * 3600 );
			// If the delay is 0 manually add a 10 seconds delay.
			if ( 0 === $total_seconds ) {
				$total_seconds += $default_seconds_delay;
			}
			$hours   = floor( $total_seconds / 3600 );
			$minutes = floor( floor( $total_seconds / 60 ) % 60 );
			$seconds = floor( $total_seconds % 60 );

			// Return date and time in UTC with the delays added.
			return new DateTime( '+' . $hours . ' hours +' . $minutes . ' minutes +' . $seconds . 'seconds' );
		}

		// If the delay is in an invalid format (non-numeric) default to base delay (default_seconds_delay).
		return new DateTime( '+' . $default_seconds_delay . 'seconds' );
	}

	/**
	 * Check is MADA card.
	 *
	 * @param string $bin The card bin.
	 *
	 * @return bool
	 */
	public static function is_mada_card( $bin ) {
		// Path to MADA_BIN.csv.
		$csv_path = WC_CHECKOUTCOM_PLUGIN_PATH . '/includes/Files/Mada/MADA_BINS.csv';

		$array_from_csv = array_map( 'str_getcsv', file( $csv_path ) );

		// Remove the first row of csv columns.
		unset( $array_from_csv[0] );

		// Build the MADA BIN array.
		$bin_array = [];
		foreach ( $array_from_csv as $row ) {
			$bin_array[] = $row[0];
		}

		return in_array( strval( $bin ), $bin_array, true );
	}

	/**
	 * Add notice.
	 *
	 * @param string $message Message to display.
	 * @param string $status Status.
	 */
	public static function wc_add_notice_self( $message, $status = 'error' ) {
		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( $message, $status );
		} else {
			global $woocommerce;

			switch ( $status ) {
				case 'error':
					$woocommerce->add_error( $message );
					break;
				case 'notice':
					$woocommerce->add_message( $message );
					break;
				default:
					$woocommerce->add_error( $message );
					break;
			}
		}
	}

	/**
	 * Log data to file using WC logger.
	 *
	 * @param string    $error_message Error message to log.
	 * @param Exception $exception Exception object.
	 */
	public static function logger( $error_message, $exception = null ) {
		$logger  = wc_get_logger();
		$context = [ 'source' => 'wc_checkoutcom_gateway_log' ];

		// Get file logging from module setting.
		$file_logging = 'yes' === WC_Admin_Settings::get_option( 'cko_file_logging', 'no' );

		// Check if file logging is enabled.
		if ( $file_logging ) {
			// Log error message with exception.
			$logger->error( $error_message, $context );
			$logger->error( wc_print_r( $exception, true ), $context );
		} else {
			// Log only error message.
			$logger->error( $error_message, $context );
		}
	}

	/**
	 * Returns available alternative payment methods APMs.
	 *
	 * @return array
	 */
	public static function get_alternative_payment_methods() {
		$currency_code = get_woocommerce_currency();
		$apm_setting   = get_option( 'woocommerce_wc_checkout_com_alternative_payments_settings' );
		$apm           = ! empty( $apm_setting['ckocom_apms_selector'] ) ? $apm_setting['ckocom_apms_selector'] : [];
		$country_code  = WC()->customer->get_billing_country();

		$abc_apms = [ 'alipay', 'bancontact', 'boleto', 'eps', 'fawry', 'giropay', 'ideal', 'knet', 'multibanco', 'poli', 'qpay', 'sepa', 'sofort' ];
		$nas_apms = [ 'ideal', 'bancontact', 'eps', 'fawry', 'giropay', 'klarna', 'knet', 'multibanco', 'qpay', 'sort' ];

		if ( cko_is_nas_account() ) {
			$apm = array_intersect( $apm, $nas_apms );
		} else {
			$apm = array_intersect( $apm, $abc_apms );
		}

		$apm_array = [];
		if ( 0 !== $apm ) {

			foreach ( $apm as $value ) {
				// PHPCS:disable WordPress.PHP.YodaConditions.NotYoda
				if ( $value === 'ideal' && $currency_code === 'EUR' && $country_code === 'NL' ) {
					array_push( $apm_array, $value );
				}

				if ( $value === 'sofort' && $currency_code === 'EUR' ) {

					if ( $country_code === 'BE'
						|| $country_code === 'DE'
						|| $country_code === 'IT'
						|| $country_code === 'NL'
						|| $country_code === 'AT'
						|| $country_code === 'ES'
					) {
						array_push( $apm_array, $value );
					}
				}

				if ( $value === 'boleto' && $country_code === 'BR' ) {
					if ( $currency_code === 'BRL' || $currency_code === 'USD' ) {
						array_push( $apm_array, $value );
					}
				}

				if ( $value === 'giropay' && $currency_code === 'EUR' && $country_code === 'DE' ) {
					array_push( $apm_array, $value );
				}

				if ( $value === 'poli' ) {
					if ( $currency_code === 'AUD' || $currency_code === 'NZD' ) {
						if ( $country_code === 'AU' || $country_code === 'NZ' ) {
							array_push( $apm_array, $value );
						}
					}
				}

				if ( $value === 'klarna' ) {
					if ( $currency_code === 'EUR'
						|| $currency_code === 'DKK'
						|| $currency_code === 'GBP'
						|| $currency_code === 'NOR'
						|| $currency_code === 'SEK'
					) {
						if ( $country_code === 'AU'
							|| $country_code === 'AT'
							|| $country_code === 'BE'
							|| $country_code === 'CA'
							|| $country_code === 'CZ'
							|| $country_code === 'DK'
							|| $country_code === 'FI'
							|| $country_code === 'FR'
							|| $country_code === 'DE'
							|| $country_code === 'GR'
							|| $country_code === 'IE'
							|| $country_code === 'IT'
							|| $country_code === 'MX'
							|| $country_code === 'NL'
							|| $country_code === 'NZ'
							|| $country_code === 'NO'
							|| $country_code === 'PL'
							|| $country_code === 'PT'
							|| $country_code === 'RO'
							|| $country_code === 'ES'
							|| $country_code === 'SE'
							|| $country_code === 'CH'
							|| $country_code === 'GB'
							|| $country_code === 'US'
						) {
							array_push( $apm_array, $value );
						}
					}
				}

				if ( $value === 'sepa' && $currency_code === 'EUR' ) {

					if ( $country_code === 'AD'
						|| $country_code === 'AT'
						|| $country_code === 'BE'
						|| $country_code === 'CY'
						|| $country_code === 'EE'
						|| $country_code === 'FI'
						|| $country_code === 'DE'
						|| $country_code === 'GR'
						|| $country_code === 'IE'
						|| $country_code === 'IT'
						|| $country_code === 'LV'
						|| $country_code === 'LT'
						|| $country_code === 'LU'
						|| $country_code === 'MT'
						|| $country_code === 'MC'
						|| $country_code === 'NL'
						|| $country_code === 'PT'
						|| $country_code === 'SM'
						|| $country_code === 'SK'
						|| $country_code === 'SI'
						|| $country_code === 'ES'
						|| $country_code === 'VA'
						|| $country_code === 'BG'
						|| $country_code === 'HR'
						|| $country_code === 'CZ'
						|| $country_code === 'DK'
						|| $country_code === 'HU'
						|| $country_code === 'IS'
						|| $country_code === 'LI'
						|| $country_code === 'NO'
						|| $country_code === 'PL'
						|| $country_code === 'RO'
						|| $country_code === 'SE'
						|| $country_code === 'CH'
						|| $country_code === 'GB'
					) {
						array_push( $apm_array, $value );
					}
				}

				if ( $value === 'eps' && $currency_code === 'EUR' && $country_code === 'AT' ) {
					array_push( $apm_array, $value );
				}

				if ( $value === 'bancontact' && $currency_code === 'EUR' && $country_code === 'BE' ) {
					array_push( $apm_array, $value );
				}

				if ( 'multibanco' === $value && 'EUR' === $currency_code && 'PT' === $country_code ) {
					array_push( $apm_array, $value );
				}

				if ( $value === 'knet' && $currency_code === 'KWD' && $country_code === 'KW' ) {
					array_push( $apm_array, $value );
				}

				if ( $value === 'fawry' && $currency_code === 'EGP' && $country_code === 'EG' ) {
					array_push( $apm_array, $value );
				}

				if ( $value === 'alipay' && $currency_code === 'USD' && $country_code === 'CN' ) {
					array_push( $apm_array, $value );
				}

				if ( $value === 'qpay' && $currency_code === 'QAR' && $country_code === 'QA' ) {
					array_push( $apm_array, $value );
				}
				// PHPCS:enable WordPress.PHP.YodaConditions.NotYoda
			}
		}

		return $apm_array;
	}

	/**
	 * Get redirect url for the payment.
	 *
	 * @param array $data Response data.
	 *
	 * @return false|mixed
	 */
	public static function get_redirect_url( $data ) {

		if ( empty( $data['_links'] ) ) {
			return false;
		}

		if ( empty( $data['_links']['redirect'] ) ) {
			return false;
		}

		if ( ! empty( $data['_links']['redirect']['href'] ) ) {
			return $data['_links']['redirect']['href'];
		}

		return false;
	}

	/**
	 * Check is successful.
	 *
	 * @param array $data Response data.
	 *
	 * @return bool
	 */
	public static function is_successful( $data ) {

		if ( ! empty( $data['http_metadata'] ) ) {
			return $data['http_metadata']->getStatusCode() < 400 && self::is_approved( $data );
		}

		return false;
	}

	/**
	 * Check is pending.
	 *
	 * @param array $data Response data.
	 *
	 * @return bool
	 */
	public static function is_pending( $data ) {
		return $data['http_metadata']->getStatusCode() === 202;
	}

	/**
	 * Check data contains approved key.
	 *
	 * @param array $data Response data.
	 *
	 * @return bool|mixed
	 */
	public static function is_approved( $data ) {
		$approved = true;
		if ( isset( $data['approved'] ) ) {
			$approved = $data['approved'];
		}

		return $approved;
	}


	/**
	 * Set WC session value by key.
	 *
	 * @param string $key Session key.
	 * @param string $value Session value.
	 *
	 * @return false|void
	 */
	public static function cko_set_session( $key, $value ) {
		if ( ! class_exists( 'WooCommerce' ) || null == WC()->session ) {
			return false;
		}

		$cko_session = WC()->session->get( 'cko_session' );

		if ( ! is_array( $cko_session ) ) {
			$cko_session = [];
		}

		$cko_session[ $key ] = $value;

		WC()->session->set( 'cko_session', $cko_session );
	}

	/**
	 *  Get WC session value by key.
	 *
	 * @param string $key Session key.
	 *
	 * @return false|mixed
	 */
	public static function cko_get_session( $key ) {
		if ( ! class_exists( 'WooCommerce' ) || null == WC()->session ) {
			return false;
		}

		$cko_session = WC()->session->get( 'cko_session' );

		if ( ! empty( $cko_session[ $key ] ) ) {
			return $cko_session[ $key ];
		}

		return false;
	}

	/**
	 * Check if cart has subscription item.
	 *
	 * @return bool
	 */
	public static function is_cart_contains_subscription() {
		$cart = WC()->cart;

		if ( $cart->is_empty() ) {
			return false;
		}

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			$product = $cart_item['data'];

			if ( $product->is_type( 'subscription' ) ) {
				return true;
			}
		}

		return false;
	}

	public static function is_paypal_express_available() {
		$paypal_settings = get_option( 'woocommerce_wc_checkout_com_paypal_settings' );

		$is_express_enable = ! empty( $paypal_settings['paypal_express'] ) && 'yes' === $paypal_settings['paypal_express'];

		$available_payment_methods = WC()->payment_gateways()->get_available_payment_gateways();

		if ( isset( $available_payment_methods['wc_checkout_com_paypal'] ) && $is_express_enable ) {
			return true;
		}

		return false;
	}
}
