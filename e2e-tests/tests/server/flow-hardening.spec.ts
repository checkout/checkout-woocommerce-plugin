import { test, expect } from '../../src/fixtures';
import { createCkoPayment, waitForCkoStatus } from '../../src/support/checkout-com';
import { createPaidOrder, createPendingOrder, hitReturnUrl } from '../../src/support/orders';
import { customerIdByLogin, getOrder, latestOrderId } from '../../src/support/store';
import { PRODUCTS } from '../../src/support/test-data';
import { wpEvalFileJson, wpEvalJson } from '../../src/support/wp-cli';
import { env } from '../../src/env';

/*
 * Flow payment session security hardening: the session body is built on the server (only the
 * shopper's name, email, addresses and locale come from the browser), 3D Secure follows the
 * store rule at create and submit, a session needs a cart, an order or an add-card page, submit
 * refuses orders that are already paid, order creation runs the checkout validation, a
 * failed-attempt order carries the whole cart, and a 3DS return creates no order and leaves a
 * paid order unchanged.
 *
 * The AJAX handlers are called inside WordPress with outgoing calls to /payment-sessions
 * answered locally, so these tests never reach Checkout.com for sessions.
 */

type AjaxResult = { success: boolean; data: Record<string, any> | null };
type HandlerRun = { response: AjaxResult | null; sent: Array<{ url: string; body: Record<string, any> | null }> };

/**
 * PHP prelude: answers /payment-sessions calls locally (recording their bodies) and turns
 * wp_send_json() into a return value. Defines $run( $method ) which calls a Flow gateway AJAX
 * handler and returns { response, sent }.
 */
const HANDLER_PRELUDE = `
	$sent = array();
	add_filter( 'pre_http_request', function ( $pre, $args, $url ) use ( &$sent ) {
		if ( false === strpos( (string) $url, '/payment-sessions' ) ) {
			return $pre;
		}
		$sent[] = array( 'url' => (string) $url, 'body' => isset( $args['body'] ) && is_string( $args['body'] ) ? json_decode( $args['body'], true ) : null );
		$body = array( 'id' => 'ps_' . str_repeat( 'e', 26 ), 'payment_session_token' => 'e2e', 'payment_session_secret' => 'e2e', 'status' => 'Pending' );
		return array( 'headers' => array(), 'body' => wp_json_encode( $body ), 'response' => array( 'code' => 201, 'message' => 'Created' ), 'cookies' => array(), 'filename' => null );
	}, 10, 3 );
	add_filter( 'wp_doing_ajax', '__return_true' );
	// wp_send_json() ends the request in WordPress. Keep the first response it sends, as a browser
	// would see it, and stop the handler there.
	$GLOBALS['cko_e2e_first_json'] = null;
	add_filter( 'wp_die_ajax_handler', function () {
		return function () {
			if ( null === $GLOBALS['cko_e2e_first_json'] ) {
				$GLOBALS['cko_e2e_first_json'] = (string) ob_get_contents();
			}
			throw new RuntimeException( 'cko-e2e-json-sent' );
		};
	} );
	$run = function ( $method ) use ( &$sent ) {
		$sent    = array();
		$GLOBALS['cko_e2e_first_json'] = null;
		$gateway = new WC_Gateway_Checkout_Com_Flow();
		ob_start();
		try {
			$gateway->{$method}();
		} catch ( RuntimeException $e ) {
			if ( 'cko-e2e-json-sent' !== $e->getMessage() ) {
				ob_end_clean();
				throw $e;
			}
		}
		$out = ob_get_clean();
		if ( null !== $GLOBALS['cko_e2e_first_json'] ) {
			$out = $GLOBALS['cko_e2e_first_json'];
		}
		return array( 'response' => json_decode( $out, true ), 'sent' => $sent );
	};
	$fill_cart = function () {
		wc_load_cart();
		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( wc_get_product_id_by_sku( ${JSON.stringify(PRODUCTS.virtual.sku)} ), 1 );
		WC()->cart->calculate_totals();
		return (int) round( (float) WC_Checkoutcom_Utility::value_to_decimal( (float) WC()->cart->get_total( 'edit' ), get_woocommerce_currency() ) );
	};
	$empty_cart = function () {
		wc_load_cart();
		WC()->cart->empty_cart();
	};
`;

/** Run a session create in WordPress with the given browser request and extra POST fields. */
function createSession(opts: { cart: boolean; request: Record<string, unknown>; post?: Record<string, string>; asUser?: number; setup?: string }): HandlerRun & { cartMinor: number } {
	return wpEvalFileJson<HandlerRun & { cartMinor: number }>(`
		${HANDLER_PRELUDE}
		wp_set_current_user( ${Number(opts.asUser ?? 0)} );
		${opts.setup ?? ''}
		$cart_minor = 0;
		if ( ${opts.cart ? 'true' : 'false'} ) {
			$cart_minor = $fill_cart();
		} else {
			$empty_cart();
		}
		$_POST = array_merge(
			json_decode( ${JSON.stringify(JSON.stringify(opts.post ?? {}))}, true ),
			array(
				'nonce'                   => wp_create_nonce( 'cko_flow_payment_session' ),
				'payment_session_request' => wp_slash( ${JSON.stringify(JSON.stringify(opts.request))} ),
			)
		);
		$result = $run( 'ajax_create_payment_session' );
		$result['cartMinor'] = $cart_minor;
		return $result;
	`);
}

/** The browser request a checkout script would send, with merchant-only fields it must not set. */
function browserRequest(extra: Record<string, unknown> = {}): Record<string, unknown> {
	return {
		amount: 1,
		currency: 'JPY',
		reference: '424242',
		capture: false,
		payment_type: 'MOTO',
		processing_channel_id: 'pc_e2e_from_browser',
		risk: { enabled: false },
		'3ds': { enabled: false },
		metadata: { user_id: 1, card_verification: 'yes', order_id: 424242 },
		items: [{ name: 'Browser item', quantity: 1, unit_price: 1, total_amount: 1 }],
		success_url: 'https://example.invalid/?wc-api=wc_checkoutcom_flow_process',
		failure_url: 'https://example.invalid/',
		customer: { name: 'E2E Hardening', email: 'e2e+flow-hardening@example.com' },
		billing: { address: { address_line1: '1 Market Street', city: 'San Francisco', zip: '94103', country: 'US' } },
		shipping: { address: { address_line1: '1 Market Street', city: 'San Francisco', zip: '94103', country: 'US' } },
		...extra,
	};
}

/** Save the 3DS options, run fn, and restore them. */
function withThreeDsOptions<T>(values: { threed: string; confirmed: string | null }, fn: () => T): T {
	const saved = wpEvalJson<Record<string, string | false>>(`
		return array( 'ckocom_card_threed' => get_option( 'ckocom_card_threed' ), 'ckocom_card_3ds_off_confirmed' => get_option( 'ckocom_card_3ds_off_confirmed' ) );
	`);
	wpEvalJson(`
		update_option( 'ckocom_card_threed', ${JSON.stringify(values.threed)} );
		${values.confirmed === null ? "delete_option( 'ckocom_card_3ds_off_confirmed' );" : `update_option( 'ckocom_card_3ds_off_confirmed', ${JSON.stringify(values.confirmed)} );`}
		return true;
	`);
	try {
		return fn();
	} finally {
		wpEvalJson(`
			foreach ( json_decode( ${JSON.stringify(JSON.stringify(saved))}, true ) as $key => $value ) {
				false === $value ? delete_option( $key ) : update_option( $key, $value );
			}
			return true;
		`);
	}
}

/** Submit a session for an order (with its key) and return what was sent. */
function submitSession(orderId: number, orderKey: string): HandlerRun {
	return wpEvalFileJson<HandlerRun>(`
		${HANDLER_PRELUDE}
		$empty_cart();
		$_POST = array(
			'nonce'              => wp_create_nonce( 'cko_flow_payment_session' ),
			'payment_session_id' => 'ps_' . str_repeat( 'e', 26 ),
			'session_data'       => 'e2e-session-data',
			'order_id'           => '${Number(orderId)}',
			'order_key'          => ${JSON.stringify(orderKey)},
		);
		return $run( 'ajax_submit_payment_session' );
	`);
}

function setOrderStatus(orderId: number, status: string, createdVia = ''): void {
	wpEvalJson(`
		$o = wc_get_order( ${Number(orderId)} );
		${createdVia ? `$o->set_created_via( ${JSON.stringify(createdVia)} );` : ''}
		$o->set_status( ${JSON.stringify(status)} );
		$o->save();
		return true;
	`);
}

test.describe('Flow payment session security hardening', () => {
	test('the session body is built on the server; only shopper fields come from the browser', () => {
		const { response, sent, cartMinor } = createSession({ cart: true, request: browserRequest({ locale: 'en-GB' }) });
		expect(response?.success, JSON.stringify(response)).toBe(true);
		expect(sent).toHaveLength(1);
		const body = sent[0].body as Record<string, any>;
		const storeCurrency = wpEvalJson<string>(`return get_woocommerce_currency();`);

		expect(body.amount).toBe(cartMinor);
		expect(body.currency).toBe(storeCurrency);
		expect(body.reference).toMatch(/^WOO[a-zA-Z0-9]+$/);
		expect(body.payment_type).toBe('Regular');
		expect(body.risk).toBeUndefined();
		expect(body.processing_channel_id).toBeUndefined();
		expect(body.metadata).not.toHaveProperty('user_id');
		expect(body.metadata).not.toHaveProperty('card_verification');
		expect(body.metadata).not.toHaveProperty('order_id');
		expect((body.items as Array<{ name: string }>).map((i) => i.name)).not.toContain('Browser item');
		expect(String(body.success_url)).not.toContain('example.invalid');
		expect(String(body.success_url)).toContain('wc-api=wc_checkoutcom_flow_process');
		expect(body['3ds'].enabled).toBe(true);
		// Shopper fields are kept.
		expect(body.customer).toEqual({ name: 'E2E Hardening', email: 'e2e+flow-hardening@example.com' });
		expect(body.billing.address).toMatchObject({ country: 'US', city: 'San Francisco' });
		expect(body.locale).toBe('en-GB');
	});

	test('a session needs a cart, an order or the add-payment-method page', () => {
		const { response, sent } = createSession({ cart: false, request: browserRequest() });
		expect(response?.success).toBe(false);
		expect(sent).toHaveLength(0);
	});

	test('an order-pay session takes amount and reference from the order, only with its key', () => {
		const order = createPendingOrder();
		const withKey = createSession({
			cart: false,
			post: { is_order_pay: '1' },
			request: browserRequest({ success_url: `https://example.invalid/?wc-api=wc_checkoutcom_flow_process&order_id=${order.id}&key=${order.key}` }),
		});
		expect(withKey.response?.success, JSON.stringify(withKey.response)).toBe(true);
		const body = withKey.sent[0].body as Record<string, any>;
		expect(body.amount).toBe(order.totalMinor);
		expect(body.currency).toBe(order.currency);
		expect(body.reference).toBe(String(order.id));
		expect(body.metadata.order_id).toBe(order.id);
		expect(String(body.success_url)).toContain(`order_id=${order.id}`);

		const wrongKey = createSession({
			cart: false,
			post: { is_order_pay: '1' },
			request: browserRequest({ success_url: `https://example.invalid/?wc-api=wc_checkoutcom_flow_process&order_id=${order.id}&key=wc_order_wrong` }),
		});
		expect(wrongKey.response?.success).toBe(false);
		expect(wrongKey.sent).toHaveLength(0);
	});

	test('add-payment-method metadata names only the logged-in customer', () => {
		const customerId = customerIdByLogin(env.customer.username);
		const loggedIn = createSession({ cart: false, asUser: customerId, post: { is_add_payment_method: '1' }, request: browserRequest() });
		expect(loggedIn.response?.success, JSON.stringify(loggedIn.response)).toBe(true);
		const body = loggedIn.sent[0].body as Record<string, any>;
		expect(body.amount).toBe(0);
		expect(body.capture).toBe(false);
		expect(body.enabled_payment_methods).toEqual(['card']);
		expect(body.metadata.user_id).toBe(customerId);
		expect(body.metadata).not.toHaveProperty('card_verification');
		expect(body.reference).toBe(`cko-add-payment-method-${customerId}`);

		// A visitor who is not logged in gets no add-card session (and no user_id in metadata).
		const anonymous = createSession({ cart: false, post: { is_add_payment_method: '1' }, request: browserRequest() });
		expect(anonymous.response?.success).toBe(false);
		expect(anonymous.sent).toHaveLength(0);
	});

	test('3D Secure is on unless turned off and confirmed, at create and at submit', () => {
		const createThreeDs = () => (createSession({ cart: true, request: browserRequest() }).sent[0]?.body as Record<string, any>)?.['3ds'];
		const submitThreeDs = () => {
			const order = createPendingOrder();
			return (submitSession(order.id, order.key).sent[0]?.body as Record<string, any>)?.['3ds'];
		};

		withThreeDsOptions({ threed: '0', confirmed: null }, () => {
			expect(createThreeDs()?.enabled, 'default: off setting without confirmation keeps 3DS on').toBe(true);
			expect(submitThreeDs()?.enabled).toBe(true);
		});
		withThreeDsOptions({ threed: '0', confirmed: 'yes' }, () => {
			expect(createThreeDs()).toEqual({ enabled: false });
			expect(submitThreeDs()).toEqual({ enabled: false });
		});
		withThreeDsOptions({ threed: '1', confirmed: 'yes' }, () => {
			expect(createThreeDs()?.enabled).toBe(true);
			expect(submitThreeDs()?.enabled).toBe(true);
		});
	});

	test('submit refuses an order that is already paid, MOTO orders included', () => {
		const paid = createPendingOrder();
		setOrderStatus(paid.id, 'processing');
		const paidRun = submitSession(paid.id, paid.key);
		expect(paidRun.response?.success).toBe(false);
		expect(paidRun.sent).toHaveLength(0);

		const moto = createPendingOrder();
		setOrderStatus(moto.id, 'completed', 'admin');
		const motoRun = submitSession(moto.id, moto.key);
		expect(motoRun.response?.success).toBe(false);
		expect(motoRun.sent).toHaveLength(0);

		// An unpaid MOTO order is still submitted without 3DS.
		const unpaidMoto = createPendingOrder();
		setOrderStatus(unpaidMoto.id, 'pending', 'admin');
		const unpaidRun = submitSession(unpaidMoto.id, unpaidMoto.key);
		expect(unpaidRun.response?.success, JSON.stringify(unpaidRun.response)).toBe(true);
		expect((unpaidRun.sent[0].body as Record<string, any>)['3ds']).toEqual({ enabled: false });
	});

	test('order creation runs the checkout validation first', () => {
		const result = wpEvalFileJson<{ run: HandlerRun; before: number; after: number }>(`
			${HANDLER_PRELUDE}
			$fill_cart();
			$latest = function () {
				$ids = wc_get_orders( array( 'limit' => 1, 'orderby' => 'ID', 'order' => 'DESC', 'return' => 'ids' ) );
				return $ids ? (int) $ids[0] : 0;
			};
			$before = $latest();
			$_POST = array(
				'woocommerce-process-checkout-nonce' => wp_create_nonce( 'woocommerce-process_checkout' ),
				'payment_method'                     => 'wc_checkout_com_flow',
				'billing_email'                      => 'not-an-email',
			);
			$_REQUEST = $_POST;
			$run_result = $run( 'ajax_create_order' );
			return array( 'run' => $run_result, 'before' => $before, 'after' => $latest() );
		`);
		expect(result.run.response?.success).toBe(false);
		expect(String(result.run.response?.data?.message ?? '')).not.toBe('');
		expect(result.after, 'no order is created').toBe(result.before);
	});

	test('a failed-attempt order carries the shipping, fees and coupons of the cart', () => {
		const result = wpEvalFileJson<{ success: boolean; cartTotal: string; orderTotal: string; shipping: number; fees: number; coupons: string[] }>(`
			${HANDLER_PRELUDE}
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
			$code   = 'e2e-failed-' . wp_generate_password( 6, false );
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

			$_POST = array(
				'woocommerce-process-checkout-nonce' => wp_create_nonce( 'woocommerce-process_checkout' ),
				'billing_email'                      => 'e2e+failed-order@example.com',
				'billing_country'                    => 'US',
			);
			$_REQUEST = $_POST;
			$run_result = $run( 'ajax_create_failed_order' );
			$order_id   = isset( $run_result['response']['data']['order_id'] ) ? (int) $run_result['response']['data']['order_id'] : 0;
			$order      = $order_id ? wc_get_order( $order_id ) : false;
			$out = array(
				'success'    => ! empty( $run_result['response']['success'] ),
				'debug'      => $run_result,
				'cartTotal'  => $cart_total,
				'orderTotal' => $order ? wc_format_decimal( $order->get_total(), wc_get_price_decimals() ) : '',
				'shipping'   => $order ? count( $order->get_items( 'shipping' ) ) : 0,
				'fees'       => $order ? count( $order->get_items( 'fee' ) ) : 0,
				'coupons'    => $order ? $order->get_coupon_codes() : array(),
			);
			if ( $order ) {
				$order->delete( true );
			}
			$coupon->delete( true );
			WC()->cart->empty_cart();
			return $out;
		`);
		expect(result.success, JSON.stringify((result as any).debug)).toBe(true);
		expect(result.shipping).toBe(1);
		expect(result.fees).toBe(1);
		expect(result.coupons).toHaveLength(1);
		expect(result.orderTotal).toBe(result.cartTotal);
	});

	test('a 3DS return that matches no order creates no order and releases the payment', async ({ request }) => {
		const currency = wpEvalJson<string>(`return get_woocommerce_currency();`);
		const payment = await createCkoPayment({ amount: 1234, currency, reference: `e2e-unmatched-${Date.now()}` });
		const before = latestOrderId();

		const res = await request.get(`/?wc-api=wc_checkoutcom_flow_process&cko-payment-id=${payment.id}`, { maxRedirects: 0 });
		expect(res.status()).toBe(302);
		expect(res.headers()['location'] ?? '').not.toContain('order-received');
		expect(latestOrderId(), 'no order is created').toBe(before);
		await waitForCkoStatus(payment.id, ['Voided']);
	});

	test('a return on a paid order leaves its addresses, customer and status unchanged', async ({ request }) => {
		const { order, payment, stored } = await createPaidOrder(request);
		const snapshot = () =>
			wpEvalJson<Record<string, string | number>>(`
				$o = wc_get_order( ${order.id} );
				return array( 'status' => $o->get_status(), 'customer' => $o->get_customer_id(), 'billing' => $o->get_billing_address_1(), 'shipping' => $o->get_shipping_address_1(), 'payment' => (string) $o->get_meta( '_cko_payment_id' ) );
			`);
		const beforeReturn = snapshot();

		// Another approved payment for the same amount, returned with the order key.
		const other = await createCkoPayment({ amount: order.totalMinor, currency: order.currency, reference: `e2e-other-${Date.now()}` });
		await hitReturnUrl(request, { orderId: order.id, key: order.key, paymentId: other.id });

		expect(snapshot()).toEqual(beforeReturn);
		expect(getOrder(order.id).status).toBe(stored.status);
		expect(beforeReturn.payment).toBe(payment.id);
	});
});
