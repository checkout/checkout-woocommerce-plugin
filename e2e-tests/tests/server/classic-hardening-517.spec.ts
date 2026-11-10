import { test, expect } from '../../src/fixtures';
import { createCkoPayment, webhookEvent } from '../../src/support/checkout-com';
import { createPendingOrder } from '../../src/support/orders';
import { getOrder, patchOrder } from '../../src/support/store';
import { sendWebhook } from '../../src/support/webhooks';
import { wpEvalFileJson, wpEvalJson } from '../../src/support/wp-cli';

/*
 * Classic gateway, APM and webhook security hardening: request data handling, return path,
 * decline webhooks and Klarna confirmation.
 */

/** PHP prelude: a WC session for notices, and redirects turned into exceptions so exit() is not reached. */
const REDIRECT_PRELUDE = `
	if ( ! WC()->session ) {
		WC()->session = new WC_Session_Handler();
		WC()->session->init();
	}
	$redirect_to_exception = function ( $location ) {
		throw new RuntimeException( 'redirect:' . $location );
	};
	add_filter( 'wp_redirect', $redirect_to_exception, PHP_INT_MAX );
`;

test.describe('Classic security hardening: request data', () => {
	test('request field helper unslashes and sanitizes only the named keys, keeping arrays', async () => {
		const out = wpEvalJson<Record<string, unknown>>(`
			$source = array(
				'billing_last_name' => wp_slash( "O'Brien <b>x</b>" ),
				'nested'            => array( 'a' => wp_slash( "<i>v</i>" ), 'b' => array( '1' ) ),
				'not_asked'         => 'ignored',
			);
			return WC_Checkoutcom_Utility::get_request_fields( $source, array( 'billing_last_name', 'nested', 'missing' ) );
		`);
		expect(out).toEqual({ billing_last_name: "O'Brien x", nested: { a: 'v', b: ['1'] } });
	});

	test('request field helper does not run per-field post filters', async () => {
		const out = wpEvalJson<{ calls: number }>(`
			$calls = 0;
			$count = function ( $v ) use ( &$calls ) { $calls++; return $v; };
			add_filter( 'post_e2e_field', $count );
			add_filter( 'e2e_field', $count );
			WC_Checkoutcom_Utility::get_request_fields( array( 'e2e_field' => 'x' ), array( 'e2e_field' ) );
			remove_filter( 'post_e2e_field', $count );
			remove_filter( 'e2e_field', $count );
			return array( 'calls' => $calls );
		`);
		expect(out.calls).toBe(0);
	});

	test('pay-for-order address comes from the order being paid, not the query string', async () => {
		const paid = createPendingOrder();
		const other = createPendingOrder();
		const out = wpEvalJson<{ address: string; city: string }>(`
			$other = wc_get_order( ${other.id} );
			$other->set_billing_address_1( '99 Other Road' );
			$other->set_billing_city( 'Elsewhere' );
			$other->save();
			$_GET['pay_for_order'] = '1';
			$_GET['order_id']      = (string) ${other.id};
			$_GET['key']           = $other->get_order_key();
			$m = new ReflectionMethod( 'WC_Checkoutcom_Api_Request', 'customer_address' );
			$m->setAccessible( true );
			$address = $m->invoke( null, array(), wc_get_order( ${paid.id} ) );
			unset( $_GET['pay_for_order'], $_GET['order_id'], $_GET['key'] );
			return array( 'address' => $address['billing_address_1'], 'city' => $address['billing_city'] );
		`);
		expect(out).toEqual({ address: '1 Market Street', city: 'San Francisco' });
	});
});

test.describe('Classic security hardening: order access helper', () => {
	test('request_can_access_order accepts the order key and rejects other requests', async () => {
		const order = createPendingOrder();
		const out = wpEvalJson<Record<string, boolean>>(`
			wp_set_current_user( 0 );
			$order = wc_get_order( ${order.id} );
			$res   = array();
			$res['none'] = WC_Checkoutcom_Utility::request_can_access_order( $order );
			$_GET['key'] = 'wc_order_wrongkey';
			$res['wrong_key'] = WC_Checkoutcom_Utility::request_can_access_order( $order );
			$_GET['key'] = $order->get_order_key();
			$res['right_key'] = WC_Checkoutcom_Utility::request_can_access_order( $order );
			unset( $_GET['key'] );
			$res['no_order'] = WC_Checkoutcom_Utility::request_can_access_order( false );
			return $res;
		`);
		expect(out).toEqual({ none: false, wrong_key: false, right_key: true, no_order: false });
	});

	test('callback without proof of access leaves the order unchanged and does not reveal it', async ({ request, baseURL }) => {
		const order = createPendingOrder();
		const payment = await createCkoPayment({
			amount: order.totalMinor,
			currency: order.currency,
			reference: String(order.id),
			metadata: { order_id: String(order.id) },
		});
		expect(payment.status).toBe('Authorized');

		const res = await request.get(`/?wc-api=wc_checkoutcom_callback&cko-session-id=${payment.id}`, { maxRedirects: 0 });
		expect(res.status()).toBe(302);
		const location = res.headers()['location'] ?? '';
		expect(new URL(location, baseURL).host).toBe(new URL(baseURL ?? '').host);
		expect(location).not.toContain('order-received');
		expect(location).not.toContain(order.key);

		const after = getOrder(order.id);
		expect(after.status).toBe('pending');
		expect(after.ckoPaymentId).toBe('');
		expect(after.notes.join('\n')).not.toContain('Payment Authorised');
	});
});

test.describe('Classic security hardening: unconfirmed returns', () => {
	function unverifiedReturn(orderId: number, paymentMethod: string, sourceType: string) {
		return wpEvalFileJson<{ redirect: string; status: string; paymentId: string }>(`
			${REDIRECT_PRELUDE}
			$order = wc_get_order( ${Number(orderId)} );
			$order->set_payment_method( ${JSON.stringify(paymentMethod)} );
			$order->save();
			$gateway = new WC_Gateway_Checkout_Com_Cards();
			$m = new ReflectionMethod( $gateway, 'handle_unverified_return' );
			$m->setAccessible( true );
			$redirect = '';
			try {
				$m->invoke(
					$gateway,
					$order,
					array( 'id' => 'pay_e2eaaaaaaaaaaaaaaaaaaaaaaaaa', 'status' => 'Pending', 'source' => array( 'type' => ${JSON.stringify(sourceType)} ) ),
					new WP_Error( 'cko_payment_not_approved', 'e2e pending' )
				);
			} catch ( RuntimeException $e ) {
				$redirect = substr( $e->getMessage(), strlen( 'redirect:' ) );
			}
			remove_filter( 'wp_redirect', $redirect_to_exception, PHP_INT_MAX );
			$fresh = wc_get_order( ${Number(orderId)} );
			return array( 'redirect' => $redirect, 'status' => $fresh->get_status(), 'paymentId' => (string) $fresh->get_meta( '_cko_payment_id' ) );
		`);
	}

	test('a pending card payment return leaves the order unchanged', async () => {
		const order = createPendingOrder();
		const out = unverifiedReturn(order.id, 'wc_checkout_com_cards', 'card');
		expect(out.status).toBe('pending');
		expect(out.paymentId).toBe('');
		expect(out.redirect).not.toContain('order-received');
	});

	test('a pending alternative payment return still waits on-hold for the webhook', async () => {
		const order = createPendingOrder();
		const out = unverifiedReturn(order.id, 'wc_checkout_com_alternative_payments_ideal', 'ideal');
		expect(out.status).toBe('on-hold');
		expect(out.redirect).toContain('order-received');
	});

	test('an unconfirmed Klarna payment fails the order without completing the checkout', async () => {
		const order = createPendingOrder();
		const out = wpEvalFileJson<{ error: string; status: string; paymentId: string; authorised: boolean }>(`
			$order = wc_get_order( ${order.id} );
			$order->set_payment_method( 'wc_checkout_com_alternative_payments_klarna' );
			$order->save();
			$gateway = new WC_Gateway_Checkout_Com_Alternative_Payments_Klarna();
			$m = new ReflectionMethod( $gateway, 'reject_unconfirmed_payment' );
			$m->setAccessible( true );
			$result = $m->invoke( $gateway, $order, array( 'id' => 'not-a-payment-id' ), new WP_Error( 'cko_amount_mismatch', 'e2e mismatch' ) );
			$fresh  = wc_get_order( ${order.id} );
			return array(
				'error'      => isset( $result['error'] ) ? (string) $result['error'] : '',
				'status'     => $fresh->get_status(),
				'paymentId'  => (string) $fresh->get_meta( '_cko_payment_id' ),
				'authorised' => (bool) $fresh->get_meta( 'cko_payment_authorized' ),
			);
		`);
		expect(out.error).not.toBe('');
		expect(out.status).toBe('failed');
		expect(out.paymentId).toBe('');
		expect(out.authorised).toBe(false);
	});
});

test.describe('Webhook security hardening: declines', () => {
	test('payment_declined for another order\'s payment leaves this order unchanged', async ({ request }) => {
		const target = createPendingOrder();
		const owner = createPendingOrder();
		const payment = await createCkoPayment({
			amount: owner.totalMinor,
			currency: owner.currency,
			reference: `e2e-${Date.now()}`,
			metadata: { order_id: String(target.id) },
		});
		patchOrder(owner.id, { meta: { _cko_payment_id: payment.id } });

		await sendWebhook(request, webhookEvent('payment_declined', { ...payment, metadata: { order_id: String(target.id) } }));

		expect(getOrder(target.id).status, 'unbound order untouched').toBe('pending');
	});

	test('payment_declined whose metadata and reference name different orders changes neither', async ({ request }) => {
		const owner = createPendingOrder();
		const other = createPendingOrder();
		const payment = await createCkoPayment({ amount: owner.totalMinor, currency: owner.currency, reference: String(owner.id) });
		patchOrder(owner.id, { meta: { _cko_payment_id: payment.id } });
		patchOrder(other.id, { meta: { _cko_payment_id: payment.id } });

		await sendWebhook(
			request,
			webhookEvent('payment_declined', { ...payment, reference: String(owner.id), metadata: { order_id: String(other.id) } }),
		);

		expect(getOrder(other.id).status).toBe('pending');
		expect(getOrder(owner.id).status).toBe('pending');
	});

	test('payment_declined for the order\'s own payment still fails it', async ({ request }) => {
		const order = createPendingOrder();
		const payment = await createCkoPayment({ amount: order.totalMinor, currency: order.currency, reference: String(order.id) });
		patchOrder(order.id, { meta: { _cko_payment_id: payment.id } });

		expect(await sendWebhook(request, webhookEvent('payment_declined', { ...payment, metadata: { order_id: String(order.id) } }))).toBe(200);

		expect(getOrder(order.id).status).toBe('failed');
	});
});
