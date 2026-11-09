import { test, expect } from '../../src/fixtures';
import { latestOrderId } from '../../src/support/store';
import { PRODUCTS } from '../../src/support/test-data';
import { wpEvalFileJson, wpEvalJson } from '../../src/support/wp-cli';

/*
 * Express checkout (PayPal, Apple Pay, Google Pay) security hardening: express orders are rated
 * for the wallet address, the Google Pay and PayPal express order steps require their nonces and
 * POST, the PayPal endpoints answer only while PayPal is offered, PayPal only charges the amount
 * approved in PayPal, and new Apple Pay keys are only written outside the web root.
 */

type WalletOrder = {
	created: boolean;
	error: string;
	cartTotal: string;
	orderTotal: string;
	shippingTotal: string;
	shippingCountry: string;
};

const GB_ADDRESS = {
	paypal: `WC_Checkoutcom_Utility::cko_set_session( 'cko_pc_details', array( 'payment_request' => array( 'shipping' => array( 'first_name' => 'E2E Shopper', 'address' => array( 'address_line1' => '1 High St', 'city' => 'London', 'zip' => 'SW1A 1AA', 'country' => 'GB' ) ) ) ) );`,
	apple: "array( 'givenName' => 'E2E', 'familyName' => 'Shopper', 'addressLines' => array( '1 High St' ), 'locality' => 'London', 'postalCode' => 'SW1A 1AA', 'countryCode' => 'gb' )",
	google: "array( 'name' => 'E2E Shopper', 'address1' => '1 High St', 'locality' => 'London', 'postalCode' => 'SW1A 1AA', 'countryCode' => 'GB' )",
};

const WALLET_GATEWAYS = [
	{ name: 'PayPal', className: 'WC_Gateway_Checkout_Com_PayPal', setup: GB_ADDRESS.paypal, args: '', walletSheet: false },
	{ name: 'Apple Pay', className: 'WC_Gateway_Checkout_Com_Apple_Pay', setup: '', args: `'', ${GB_ADDRESS.apple}, null`, walletSheet: true },
	{ name: 'Google Pay', className: 'WC_Gateway_Checkout_Com_Google_Pay', setup: '', args: `'', ${GB_ADDRESS.google}`, walletSheet: true },
] as const;

/**
 * Total passed as approved in the wallet sheet: the total for the wallet address, the total for
 * the session address, none, or (PayPal, which checks the approved amount when charging) no argument.
 */
type Approved = 'wallet' | 'session' | 'missing' | 'none';

const APPROVED_ARG: Record<Approved, string> = {
	wallet: "array( 'total' => $wallet_total, 'currency' => get_woocommerce_currency() )",
	session: "array( 'total' => $session_total, 'currency' => get_woocommerce_currency() )",
	missing: 'null',
	none: '',
};

/**
 * With the session address in the US, create an express order whose wallet address is in GB.
 * Shipping costs 5 to the US and 20 to GB, or has no rate to GB when gbRate is false.
 */
function buildWalletOrder(className: string, setup: string, args: string, gbRate: boolean, approved: Approved): WalletOrder {
	const allArgs = [args, APPROVED_ARG[approved]].filter(Boolean).join(', ');
	return wpEvalFileJson<WalletOrder>(`
		wc_load_cart();
		WC()->cart->empty_cart();
		WC()->customer->set_shipping_country( 'US' );
		WC()->customer->set_shipping_state( 'CA' );
		WC()->customer->set_shipping_postcode( '94103' );
		WC()->customer->set_billing_country( 'US' );

		add_filter( 'woocommerce_package_rates', function ( $rates, $package ) {
			$country = isset( $package['destination']['country'] ) ? $package['destination']['country'] : '';
			if ( 'GB' === $country ) {
				return ${gbRate ? "array( 'e2e_rate:1' => new WC_Shipping_Rate( 'e2e_rate:1', 'E2E rate', 20, array(), 'flat_rate', 1 ) )" : 'array()'};
			}
			return array( 'e2e_rate:1' => new WC_Shipping_Rate( 'e2e_rate:1', 'E2E rate', 5, array(), 'flat_rate', 1 ) );
		}, 10, 2 );

		WC()->cart->add_to_cart( wc_get_product_id_by_sku( ${JSON.stringify(PRODUCTS.physical.sku)} ), 1 );
		WC()->session->set( 'chosen_shipping_methods', array( 'e2e_rate:1' ) );
		WC()->cart->calculate_totals();
		$session_total = wc_format_decimal( WC()->cart->get_total( 'edit' ), wc_get_price_decimals() );

		// What the wallet sheet shows once it is rated for the GB address.
		WC()->customer->set_shipping_country( 'GB' );
		WC()->customer->set_shipping_state( '' );
		WC()->customer->set_shipping_postcode( 'SW1A 1AA' );
		WC()->customer->set_billing_country( 'GB' );
		WC()->shipping()->reset_shipping();
		WC()->cart->calculate_totals();
		$wallet_total = wc_format_decimal( WC()->cart->get_total( 'edit' ), wc_get_price_decimals() );

		WC()->customer->set_shipping_country( 'US' );
		WC()->customer->set_shipping_state( 'CA' );
		WC()->customer->set_shipping_postcode( '94103' );
		WC()->customer->set_billing_country( 'US' );
		WC()->session->set( 'chosen_shipping_methods', array( 'e2e_rate:1' ) );
		WC()->shipping()->reset_shipping();
		WC()->cart->calculate_totals();

		${setup}
		$gateway = new ${className}();
		$create  = new ReflectionMethod( $gateway, 'create_express_order_from_cart' );
		$create->setAccessible( true );
		$order = $create->invoke( $gateway${allArgs ? `, ${allArgs}` : ''} );

		$error = new ReflectionProperty( $gateway, 'express_order_error' );
		$error->setAccessible( true );

		$result = array(
			'created'         => $order instanceof WC_Order,
			'error'           => (string) $error->getValue( $gateway ),
			'cartTotal'       => wc_format_decimal( WC()->cart->get_total( 'edit' ), wc_get_price_decimals() ),
			'orderTotal'      => '',
			'shippingTotal'   => '',
			'shippingCountry' => '',
		);
		if ( $order instanceof WC_Order ) {
			$order = wc_get_order( $order->get_id() );
			$result['orderTotal']      = wc_format_decimal( $order->get_total(), wc_get_price_decimals() );
			$result['shippingTotal']   = wc_format_decimal( $order->get_shipping_total(), wc_get_price_decimals() );
			$result['shippingCountry'] = $order->get_shipping_country();
			$order->delete( true );
		}

		WC()->cart->empty_cart();
		WC_Checkoutcom_Utility::cko_set_session( 'cko_pc_details', '' );
		WC()->customer->set_shipping_country( 'US' );
		WC()->customer->set_billing_country( 'US' );
		return $result;
	`);
}

test.describe('Express orders use the wallet address', () => {
	for (const gateway of WALLET_GATEWAYS) {
		test(`${gateway.name} Express order is rated for the wallet address`, () => {
			const order = buildWalletOrder(gateway.className, gateway.setup, gateway.args, true, gateway.walletSheet ? 'wallet' : 'none');

			expect(order.created, 'order created').toBe(true);
			expect(order.shippingCountry).toBe('GB');
			expect(Number(order.shippingTotal), 'shipping rated for the wallet address').toBe(20);
			expect(order.orderTotal, 'order total matches the recalculated cart total').toBe(order.cartTotal);
		});

		test(`${gateway.name} Express order is refused when the wallet address has no shipping rate`, () => {
			const before = latestOrderId();
			const order = buildWalletOrder(gateway.className, gateway.setup, gateway.args, false, gateway.walletSheet ? 'wallet' : 'none');

			expect(order.created, 'no order created').toBe(false);
			expect(order.error).toContain('cannot ship');
			expect(latestOrderId(), 'no order left behind').toBe(before);
		});

		if (gateway.walletSheet) {
			for (const approved of ['session', 'missing'] as const) {
				test(`${gateway.name} Express order is refused when the approved wallet total is ${approved === 'session' ? 'different' : 'missing'}`, () => {
					const before = latestOrderId();
					const order = buildWalletOrder(gateway.className, gateway.setup, gateway.args, true, approved);

					expect(order.created, 'no order created').toBe(false);
					expect(order.error).toContain('You have not been charged');
					expect(latestOrderId(), 'no order left behind').toBe(before);
				});
			}
		}
	}
});

/** Set keys in a gateway settings option; returns a restore function. */
function setSettings(option: string, values: Record<string, string>): () => void {
	const previous = wpEvalJson<Record<string, string | null>>(`
		$s = (array) get_option( ${JSON.stringify(option)}, array() );
		$prev = array();
		foreach ( json_decode( ${JSON.stringify(JSON.stringify(values))}, true ) as $key => $value ) {
			$prev[ $key ] = isset( $s[ $key ] ) ? $s[ $key ] : null;
			$s[ $key ] = $value;
		}
		update_option( ${JSON.stringify(option)}, $s );
		return $prev;
	`);
	return () => {
		wpEvalJson(`
			$s = (array) get_option( ${JSON.stringify(option)}, array() );
			foreach ( json_decode( ${JSON.stringify(JSON.stringify(previous))}, true ) as $key => $value ) {
				if ( null === $value ) {
					unset( $s[ $key ] );
				} else {
					$s[ $key ] = $value;
				}
			}
			update_option( ${JSON.stringify(option)}, $s );
			return true;
		`);
	};
}

/** A guest (user 0) nonce, as the product/cart page would localise it. */
function guestNonce(action: string): string {
	return wpEvalJson<string>(`wp_set_current_user( 0 ); return wp_create_nonce( ${JSON.stringify(action)} );`);
}

const GOOGLE_PAY_SETTINGS = 'woocommerce_wc_checkout_com_google_pay_settings';
const PAYPAL_SETTINGS = 'woocommerce_wc_checkout_com_paypal_settings';
const GOOGLE_PAY_API = '/?wc-api=CKO_Google_Pay_Woocommerce&cko_google_pay_action=';
const PAYPAL_API = '/?wc-api=CKO_Paypal_Woocommerce&cko_paypal_action=';

test.describe('Google Pay Express order step', () => {
	test('requires its nonce and POST before creating an order', async ({ request }) => {
		const restore = setSettings(GOOGLE_PAY_SETTINGS, { google_pay_express: 'yes', enabled: 'yes' });
		try {
			const before = latestOrderId();
			const url = GOOGLE_PAY_API + 'express_google_pay_order_session';
			const form = { 'cko-google-signature': 'x', 'cko-google-protocolVersion': 'ECv2', 'cko-google-signedMessage': 'x', payment_data: '{}' };

			const noNonce = await request.post(url, { form });
			expect(noNonce.status(), 'refused without the nonce').toBe(403);

			const badNonce = await request.post(url, { form: { ...form, nonce: 'not-a-nonce' } });
			expect(badNonce.status(), 'refused with an invalid nonce').toBe(403);

			const nonce = guestNonce('checkoutcom_google_pay_express_order');
			const get = await request.get(`${url}&nonce=${encodeURIComponent(nonce)}`);
			expect(get.status(), 'refused over GET').toBe(405);

			expect(latestOrderId(), 'no order created').toBe(before);
		} finally {
			restore();
		}
	});
});

test.describe('PayPal endpoints', () => {
	test('answer only while PayPal (and, for express actions, PayPal Express) is offered', async ({ request }) => {
		const restore = setSettings(PAYPAL_SETTINGS, { enabled: 'no', paypal_express: 'no' });
		try {
			for (const action of ['create_order', 'cc_capture', 'order_pay', 'express_add_to_cart', 'express_create_order', 'express_paypal_order_session']) {
				const res = await request.post(PAYPAL_API + action, { form: { nonce: 'x' } });
				expect(res.status(), `${action} refused while PayPal is off`).toBe(403);
			}

			setSettings(PAYPAL_SETTINGS, { enabled: 'yes', paypal_express: 'no' });
			for (const action of ['express_add_to_cart', 'express_create_order', 'express_paypal_order_session']) {
				const res = await request.post(PAYPAL_API + action, { form: { nonce: 'x' } });
				expect(res.status(), `${action} refused while PayPal Express is off`).toBe(403);
			}
			const expressViaCreateOrder = await request.post(PAYPAL_API + 'create_order', { form: { express_checkout: '1' } });
			expect(expressViaCreateOrder.status(), 'express create order refused while PayPal Express is off').toBe(403);
		} finally {
			restore();
		}
	});

	test('express order steps require their nonces and POST', async ({ request }) => {
		const restore = setSettings(PAYPAL_SETTINGS, { enabled: 'yes', paypal_express: 'yes' });
		try {
			const before = latestOrderId();

			for (const action of ['express_create_order', 'express_paypal_order_session']) {
				const res = await request.post(PAYPAL_API + action, { form: { express_checkout: '1', use_existing_cart: '1' } });
				expect(res.status(), `${action} refused without the nonce`).toBe(403);
				const body = await res.json();
				expect(body.success).toBe(false);
			}

			const nonce = guestNonce('checkoutcom_paypal_express_order');
			const get = await request.get(`${PAYPAL_API}express_paypal_order_session&nonce=${encodeURIComponent(nonce)}`);
			expect(get.status(), 'order step refused over GET').toBe(405);

			expect(latestOrderId(), 'no order created').toBe(before);
		} finally {
			restore();
		}
	});
});

test.describe('PayPal payment request', () => {
	test('is refused when the order differs from the amount approved in PayPal', () => {
		const result = wpEvalFileJson<{ refused: boolean; output: string; note: boolean }>(`
			$order = wc_create_order();
			$order->add_product( wc_get_product( wc_get_product_id_by_sku( ${JSON.stringify(PRODUCTS.virtual.sku)} ) ), 1 );
			$order->calculate_totals();
			$order->save();
			$approved = WC_Checkoutcom_Utility::value_to_decimal( $order->get_total(), $order->get_currency() ) - 100;

			add_filter( 'wp_doing_ajax', '__return_true' );
			add_filter( 'wp_die_ajax_handler', function () {
				return function () {
					throw new RuntimeException( 'e2e-stop' );
				};
			} );

			$refused = false;
			ob_start();
			try {
				( new WC_Gateway_Checkout_Com_PayPal() )->request_payment(
					$order,
					'pct_e2eplaceholder',
					'pc_e2eplaceholder',
					array( 'payment_request' => array( 'amount' => $approved, 'currency' => $order->get_currency() ) )
				);
			} catch ( RuntimeException $e ) {
				$refused = 'e2e-stop' === $e->getMessage();
			}
			$output = ob_get_clean();

			$order = wc_get_order( $order->get_id() );
			$note  = false;
			foreach ( wc_get_order_notes( array( 'order_id' => $order->get_id() ) ) as $n ) {
				$note = $note || false !== strpos( $n->content, 'not requested' );
			}
			$paid = (bool) $order->get_meta( '_cko_payment_id' );
			$order->delete( true );

			return array( 'refused' => $refused && ! $paid, 'output' => (string) $output, 'note' => $note );
		`);

		expect(result.refused, 'no payment requested').toBe(true);
		expect(result.output).toContain('"success":false');
		expect(result.note, 'order note added').toBe(true);
	});
});

test.describe('Apple Pay key location', () => {
	test('new keys are never written inside the web root', () => {
		const result = wpEvalFileJson<{ uploadsRefused: boolean; abspathRefused: boolean; outsideAccepted: boolean; outsideDir: string; writeDirInWebRoot: boolean }>(`
			$gateway = new WC_Gateway_Checkout_Com_Apple_Pay();
			$dir_fn  = new ReflectionMethod( $gateway, 'get_private_apple_pay_key_dir' );
			$dir_fn->setAccessible( true );
			$write_fn = new ReflectionMethod( $gateway, 'get_apple_pay_key_write_dir' );
			$write_fn->setAccessible( true );

			$saved = isset( $_SERVER['DOCUMENT_ROOT'] ) ? $_SERVER['DOCUMENT_ROOT'] : null;
			$_SERVER['DOCUMENT_ROOT'] = ABSPATH;

			$custom = '';
			add_filter( 'cko_apple_pay_key_dir', function () use ( &$custom ) {
				return $custom;
			} );

			$uploads = wp_upload_dir()['basedir'] . '/e2e-key-dir-' . wp_generate_password( 6, false );
			$custom  = $uploads;
			$uploads_refused = '' === $dir_fn->invoke( $gateway );
			@rmdir( $uploads );

			$custom = untrailingslashit( ABSPATH );
			$abspath_refused = '' === $dir_fn->invoke( $gateway );

			$outside = trailingslashit( sys_get_temp_dir() ) . 'e2e-cko-key-' . wp_generate_password( 6, false );
			$custom  = $outside;
			$got     = $dir_fn->invoke( $gateway );
			$outside_accepted = '' !== $got && is_dir( $got );
			if ( $outside_accepted ) {
				foreach ( array( '.htaccess', 'web.config', 'index.php' ) as $f ) {
					@unlink( $got . '/' . $f );
				}
				@rmdir( $got );
			}

			$custom = '';
			$write  = $write_fn->invoke( $gateway );
			$write_in_web_root = '' !== $write && 0 === strpos( wp_normalize_path( $write ), wp_normalize_path( untrailingslashit( ABSPATH ) ) );

			if ( null === $saved ) {
				unset( $_SERVER['DOCUMENT_ROOT'] );
			} else {
				$_SERVER['DOCUMENT_ROOT'] = $saved;
			}

			return array(
				'uploadsRefused'    => $uploads_refused,
				'abspathRefused'    => $abspath_refused,
				'outsideAccepted'   => $outside_accepted,
				'outsideDir'        => (string) $got,
				'writeDirInWebRoot' => $write_in_web_root,
			);
		`);

		expect(result.uploadsRefused, 'a custom directory under uploads is refused').toBe(true);
		expect(result.abspathRefused, 'the WordPress directory is refused').toBe(true);
		expect(result.outsideAccepted, `a writable directory outside the web root is used (${result.outsideDir})`).toBe(true);
		expect(result.writeDirInWebRoot, 'the default key location is never inside the web root').toBe(false);
	});
});
