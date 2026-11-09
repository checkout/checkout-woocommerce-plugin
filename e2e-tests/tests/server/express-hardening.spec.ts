import { test, expect } from '../../src/fixtures';
import { createPendingOrder } from '../../src/support/orders';
import { customerIdByLogin, latestOrderId } from '../../src/support/store';
import { PRODUCTS } from '../../src/support/test-data';
import { wpEvalFileJson, wpEvalJson } from '../../src/support/wp-cli';
import { env } from '../../src/env';

/*
 * Express checkout (PayPal, Apple Pay, Google Pay) order handling: the order built from the cart
 * carries the same lines and total as the cart; the Apple Pay Express endpoints answer only while
 * Apple Pay Express is offered, and the order step requires its nonce and a token; the Apple Pay
 * domain association upload and certificate directory checks; the PayPal order-pay order is only
 * used for its owner.
 */

type ExpressOrderLines = {
	created: boolean;
	cartTotal: string;
	orderTotal: string;
	shipping: string[];
	fees: string[];
	coupons: string[];
	products: number;
};

// Each gateway is given a wallet address in the same place as the cart's session address, so the
// order is rated for the address it ships to (PayPal takes it from the payment context details).
const EXPRESS_GATEWAYS = [
	{
		name: 'PayPal',
		className: 'WC_Gateway_Checkout_Com_PayPal',
		args: '',
		setup: `WC_Checkoutcom_Utility::cko_set_session( 'cko_pc_details', array( 'payment_request' => array( 'shipping' => array( 'first_name' => 'E2E Shopper', 'address' => array( 'address_line1' => '1 Market St', 'city' => 'San Francisco', 'state' => 'CA', 'zip' => '94103', 'country' => 'US' ) ) ) ) );`,
	},
	{
		name: 'Apple Pay',
		className: 'WC_Gateway_Checkout_Com_Apple_Pay',
		args: "'', array( 'givenName' => 'E2E', 'familyName' => 'Shopper', 'addressLines' => array( '1 Market St' ), 'locality' => 'San Francisco', 'administrativeArea' => 'CA', 'postalCode' => '94103', 'countryCode' => 'us' ), null, array( 'total' => $cart_total, 'currency' => get_woocommerce_currency() )",
		setup: '',
	},
	{
		name: 'Google Pay',
		className: 'WC_Gateway_Checkout_Com_Google_Pay',
		args: "'', array( 'name' => 'E2E Shopper', 'address1' => '1 Market St', 'locality' => 'San Francisco', 'administrativeArea' => 'CA', 'postalCode' => '94103', 'countryCode' => 'US' ), array( 'total' => $cart_total, 'currency' => get_woocommerce_currency() )",
		setup: '',
	},
] as const;

/**
 * Build a cart with a physical product, a fee, a fixed coupon and a chosen shipping rate, then
 * create the express order for it with the given gateway class. The order is deleted afterwards.
 */
function buildExpressOrder(className: string, args: string, setup = ''): ExpressOrderLines {
	return wpEvalFileJson<ExpressOrderLines>(`
		wc_load_cart();
		WC()->cart->empty_cart();
		WC()->customer->set_shipping_country( 'US' );
		WC()->customer->set_shipping_state( 'CA' );
		WC()->customer->set_shipping_postcode( '94103' );
		WC()->customer->set_billing_country( 'US' );

		add_action( 'woocommerce_cart_calculate_fees', function ( $cart ) {
			$cart->add_fee( 'E2E handling fee', 2.5 );
		} );
		add_filter( 'woocommerce_package_rates', function () {
			return array( 'e2e_rate:1' => new WC_Shipping_Rate( 'e2e_rate:1', 'E2E rate', 5, array(), 'flat_rate', 1 ) );
		} );

		$code = 'e2e-express-' . wp_generate_password( 6, false );
		$coupon = new WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( 'fixed_cart' );
		$coupon->set_amount( 1 );
		$coupon->save();

		WC()->cart->add_to_cart( wc_get_product_id_by_sku( ${JSON.stringify(PRODUCTS.physical.sku)} ), 1 );
		WC()->cart->apply_coupon( $code );
		WC()->session->set( 'chosen_shipping_methods', array( 'e2e_rate:1' ) );
		WC()->cart->calculate_totals();
		$cart_total = wc_format_decimal( WC()->cart->get_total( 'edit' ), wc_get_price_decimals() );

		${setup}
		$gateway = new ${className}();
		$create  = new ReflectionMethod( $gateway, 'create_express_order_from_cart' );
		$create->setAccessible( true );
		$order = $create->invoke( $gateway${args ? `, ${args}` : ''} );

		$result = array( 'created' => $order instanceof WC_Order, 'cartTotal' => $cart_total, 'orderTotal' => '', 'shipping' => array(), 'fees' => array(), 'coupons' => array(), 'products' => 0 );
		if ( $order instanceof WC_Order ) {
			$order = wc_get_order( $order->get_id() );
			$result['orderTotal'] = wc_format_decimal( $order->get_total(), wc_get_price_decimals() );
			$result['products']   = count( $order->get_items( 'line_item' ) );
			foreach ( $order->get_items( 'shipping' ) as $item ) {
				$result['shipping'][] = $item->get_method_id() . ':' . $item->get_instance_id();
			}
			foreach ( $order->get_items( 'fee' ) as $item ) {
				$result['fees'][] = $item->get_name();
			}
			foreach ( $order->get_items( 'coupon' ) as $item ) {
				$result['coupons'][] = $item->get_code();
			}
			$order->delete( true );
		}

		WC()->cart->empty_cart();
		WC_Checkoutcom_Utility::cko_set_session( 'cko_pc_details', '' );
		wp_delete_post( $coupon->get_id(), true );
		$result['coupons'] = array_map( function ( $c ) use ( $code ) { return $c === $code ? 'applied' : $c; }, $result['coupons'] );
		return $result;
	`);
}

test.describe('Express checkout orders', () => {
	for (const gateway of EXPRESS_GATEWAYS) {
		test(`${gateway.name} Express order carries the cart's shipping, fee and coupon lines`, async () => {
			const lines = buildExpressOrder(gateway.className, gateway.args, gateway.setup);

			expect(lines.created, 'order created').toBe(true);
			expect(lines.products).toBe(1);
			expect(lines.shipping).toEqual(['flat_rate:1']);
			expect(lines.fees).toEqual(['E2E handling fee']);
			expect(lines.coupons).toHaveLength(1);
			expect(lines.coupons[0], 'the cart coupon is on the order').toMatch(/^e2e-express-/);
			expect(lines.orderTotal, 'order total matches the cart total').toBe(lines.cartTotal);
		});
	}
});

/** Set Apple Pay settings keys; returns a restore function. */
function setApplePaySettings(values: Record<string, string>): () => void {
	const previous = wpEvalJson<Record<string, string | null>>(`
		$s = (array) get_option( 'woocommerce_wc_checkout_com_apple_pay_settings', array() );
		$prev = array();
		foreach ( json_decode( ${JSON.stringify(JSON.stringify(values))}, true ) as $key => $value ) {
			$prev[ $key ] = isset( $s[ $key ] ) ? $s[ $key ] : null;
			$s[ $key ] = $value;
		}
		update_option( 'woocommerce_wc_checkout_com_apple_pay_settings', $s );
		return $prev;
	`);
	return () => {
		wpEvalJson(`
			$s = (array) get_option( 'woocommerce_wc_checkout_com_apple_pay_settings', array() );
			foreach ( json_decode( ${JSON.stringify(JSON.stringify(previous))}, true ) as $key => $value ) {
				if ( null === $value ) {
					unset( $s[ $key ] );
				} else {
					$s[ $key ] = $value;
				}
			}
			update_option( 'woocommerce_wc_checkout_com_apple_pay_settings', $s );
			return true;
		`);
	};
}

const APPLE_PAY_API = '/?wc-api=CKO_Apple_Pay_Woocommerce&cko_apple_pay_action=';

test.describe('Apple Pay Express endpoints', () => {
	test('answer only while Apple Pay Express is offered', async ({ request }) => {
		const restore = setApplePaySettings({ apple_pay_express: 'no', enabled: 'yes' });
		try {
			for (const action of ['express_get_cart_total', 'express_add_to_cart', 'express_apple_pay_order_session']) {
				const res = await request.post(APPLE_PAY_API + action, { form: { nonce: 'x' } });
				expect(res.status(), `${action} refused while Express is off`).toBe(403);
			}

			setApplePaySettings({ apple_pay_express: 'yes', enabled: 'yes' });
			const res = await request.get(APPLE_PAY_API + 'express_get_cart_total');
			expect(res.status(), 'handled while Express is on').not.toBe(403);
		} finally {
			restore();
		}
	});

	test('the order step requires its nonce and a token before creating an order', async ({ request }) => {
		const restore = setApplePaySettings({ apple_pay_express: 'yes', enabled: 'yes' });
		try {
			const before = latestOrderId();
			const url = APPLE_PAY_API + 'express_apple_pay_order_session';

			const noNonce = await request.post(url, { form: { 'cko-apple-card-token': 'tok_e2eexpressplaceholder', payment_data: '{}' } });
			expect(noNonce.status(), 'refused without the nonce').toBe(403);

			// Guest nonce (user 0), as the product/cart page would localise it.
			const nonce = wpEvalJson<string>(`wp_set_current_user( 0 ); return wp_create_nonce( 'checkoutcom_apple_pay_express_order' );`);
			for (const token of ['', 'not-a-token', '<b>tok_</b>']) {
				const res = await request.post(url, { form: { nonce, 'cko-apple-card-token': token, payment_data: '{}' } });
				expect(res.status(), `refused for token "${token}"`).toBe(400);
			}

			expect(latestOrderId(), 'no order created').toBe(before);
		} finally {
			restore();
		}
	});
});

/** Whether the Apple Pay settings accept this as domain association file content. */
function acceptsDomainAssociation(content: string): boolean {
	return wpEvalJson<boolean>(`
		$check = new ReflectionMethod( 'WC_Gateway_Checkout_Com_Apple_Pay', 'is_valid_domain_association_content' );
		$check->setAccessible( true );
		return $check->invoke( null, base64_decode( ${JSON.stringify(Buffer.from(content).toString('base64'))} ) );
	`);
}

test.describe('Apple Pay domain association upload', () => {
	test('accepts a file shaped like an Apple domain association file', () => {
		const hex = '7B227073704964223A2246324244453130433742414337303943443033443939353732363132433830413238383344313742424133443246444531373844463543324633413239413342222C2276657273696F6E223A312C22637265617465644F6E223A313539373838343131373730377D';
		expect(acceptsDomainAssociation(hex)).toBe(true);
		expect(acceptsDomainAssociation(`${hex.slice(0, 64)}\n${hex.slice(64)}\n`), 'line breaks allowed').toBe(true);
		expect(acceptsDomainAssociation('MIIQdgYJKoZIhvcNAQcCoIIQZzCCEGMCAQExCzAJBgUrDgMCGgUAMIIG+Q==\r\n'), 'base64 allowed').toBe(true);
	});

	test('rejects markup, empty and oversized content', () => {
		expect(acceptsDomainAssociation('<html><body>hello</body></html>')).toBe(false);
		expect(acceptsDomainAssociation('7B2270<script>7370</script>')).toBe(false);
		expect(acceptsDomainAssociation('<?php echo 1; ?>')).toBe(false);
		expect(acceptsDomainAssociation('')).toBe(false);
		expect(acceptsDomainAssociation(' \n\t ')).toBe(false);
		// Built in PHP: a 64 KB value is too long to pass on the WP-CLI command line.
		const oversized = wpEvalJson<boolean>(`
			$check = new ReflectionMethod( 'WC_Gateway_Checkout_Com_Apple_Pay', 'is_valid_domain_association_content' );
			$check->setAccessible( true );
			return $check->invoke( null, str_repeat( 'A', 64 * 1024 + 1 ) );
		`);
		expect(oversized, 'over 64 KB').toBe(false);
	});
});

test.describe('Apple Pay certificate directory check', () => {
	test('is not cached before the directory exists and is cleared on demand', () => {
		const result = wpEvalJson<{ exposed: boolean; cachedWithoutDir: boolean; clearedAfterSave: boolean }>(`
			$gateway = new WC_Gateway_Checkout_Com_Apple_Pay();
			$dir     = wp_upload_dir()['basedir'] . '/checkout-com-apple-pay';
			$moved   = $dir . '-e2e-' . wp_generate_password( 6, false );
			$had_dir = is_dir( $dir );
			if ( $had_dir ) {
				rename( $dir, $moved );
			}
			try {
				delete_transient( 'cko_applepay_dir_web_exposed' );
				$exposed = $gateway->is_cert_dir_web_exposed();
				$cached  = false !== get_transient( 'cko_applepay_dir_web_exposed' );
			} finally {
				if ( $had_dir ) {
					rename( $moved, $dir );
				}
			}

			set_transient( 'cko_applepay_dir_web_exposed', 'no', HOUR_IN_SECONDS );
			$gateway->clear_cert_dir_exposure_check();
			$cleared = false === get_transient( 'cko_applepay_dir_web_exposed' );

			return array( 'exposed' => $exposed, 'cachedWithoutDir' => $cached, 'clearedAfterSave' => $cleared );
		`);

		expect(result.exposed).toBe(false);
		expect(result.cachedWithoutDir, 'no result cached while the directory is missing').toBe(false);
		expect(result.clearedAfterSave).toBe(true);
	});

	test('new keys only go to a location outside the web root', () => {
		const outside = wpEvalJson<Record<string, boolean>>(`
			$gateway = new WC_Gateway_Checkout_Com_Apple_Pay();
			$check   = new ReflectionMethod( $gateway, 'is_outside_web_root' );
			$check->setAccessible( true );
			$saved = isset( $_SERVER['DOCUMENT_ROOT'] ) ? $_SERVER['DOCUMENT_ROOT'] : null;

			$_SERVER['DOCUMENT_ROOT'] = '';
			$unknown_root = $check->invoke( $gateway, dirname( untrailingslashit( ABSPATH ) ) );

			$_SERVER['DOCUMENT_ROOT'] = ABSPATH;
			$result = array(
				'unknownRoot' => $unknown_root,
				'abspath'     => $check->invoke( $gateway, untrailingslashit( ABSPATH ) ),
				'uploads'     => $check->invoke( $gateway, wp_upload_dir()['basedir'] ),
				'parent'      => $check->invoke( $gateway, dirname( untrailingslashit( ABSPATH ) ) ),
			);

			if ( null === $saved ) {
				unset( $_SERVER['DOCUMENT_ROOT'] );
			} else {
				$_SERVER['DOCUMENT_ROOT'] = $saved;
			}
			return $result;
		`);

		expect(outside.unknownRoot, 'refused when the document root is unknown').toBe(false);
		expect(outside.abspath).toBe(false);
		expect(outside.uploads).toBe(false);
		expect(outside.parent, 'the directory above WordPress is outside the web root').toBe(true);
	});
});

/**
 * Which order the PayPal order-pay request would charge for these posted fields, acting as the
 * given user (0 = guest): the order id, 'refused', or 'none' (not an order-pay request).
 */
function paypalPayOrder(post: Record<string, string | number>, userId = 0, validNonce = true): string {
	return wpEvalJson<string>(`
		wp_set_current_user( ${Number(userId)} );
		$_POST = json_decode( ${JSON.stringify(JSON.stringify(post))}, true );
		$_POST['woocommerce-pay-nonce'] = ${validNonce ? "wp_create_nonce( 'woocommerce-pay' )" : "'not-a-nonce'"};
		$gateway = new WC_Gateway_Checkout_Com_PayPal();
		$resolve = new ReflectionMethod( $gateway, 'get_order_pay_order_from_request' );
		$resolve->setAccessible( true );
		$order = $resolve->invoke( $gateway );
		if ( null === $order ) {
			return 'none';
		}
		return $order instanceof WC_Order ? (string) $order->get_id() : 'refused';
	`);
}

test.describe('PayPal order-pay order', () => {
	test('is used only with its order key or for its own customer', () => {
		const customerId = customerIdByLogin(env.customer.username);
		const guestOrder = createPendingOrder(PRODUCTS.virtual.sku, 0);
		const customerOrder = createPendingOrder(PRODUCTS.virtual.sku, customerId);

		expect(paypalPayOrder({}), 'no order named').toBe('none');
		expect(paypalPayOrder({ cko_pay_order_id: guestOrder.id, cko_pay_order_key: guestOrder.key })).toBe(String(guestOrder.id));
		expect(paypalPayOrder({ cko_pay_order_id: guestOrder.id, cko_pay_order_key: 'wc_order_wrong' }), 'wrong key').toBe('refused');
		expect(paypalPayOrder({ cko_pay_order_id: guestOrder.id }), 'no key').toBe('refused');
		expect(paypalPayOrder({ cko_pay_order_id: guestOrder.id, cko_pay_order_key: guestOrder.key }, 0, false), 'invalid nonce').toBe('refused');

		expect(paypalPayOrder({ cko_pay_order_id: customerOrder.id }, customerId), 'own order when logged in').toBe(String(customerOrder.id));
		expect(paypalPayOrder({ cko_pay_order_id: customerOrder.id }, 0), 'customer order as guest').toBe('refused');
		expect(paypalPayOrder({ cko_pay_order_id: guestOrder.id }, customerId), 'another order when logged in').toBe('refused');
	});
});
