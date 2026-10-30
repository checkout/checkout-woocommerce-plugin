import { test, expect } from '../../src/fixtures';
import { createCkoPayment, signWebhook, webhookEvent } from '../../src/support/checkout-com';
import { sendWebhook } from '../../src/support/webhooks';
import { createPaidOrder, createPendingOrder, hitReturnUrl } from '../../src/support/orders';
import { customerIdByLogin, getOrder, overrideCoreSetting, patchOrder } from '../../src/support/store';
import { wp, wpEvalJson } from '../../src/support/wp-cli';

const AUTHORISED = ['on-hold', 'processing', 'completed'];

/*
 * Regression tests for the payment-verification fixes (fix/payment-verification).
 * Each one checks that an order only reaches an authorised status when a real payment for
 * exactly that order (amount, currency, binding) is confirmed server-side.
 */
test.describe('Security: payment verification', () => {
	test('status=failed return URL without the order key leaves the order untouched', async ({ request }) => {
		const order = createPendingOrder();

		await hitReturnUrl(request, { orderId: order.id, status: 'failed', paymentId: 'pay_aaaaaaaaaaaaaaaaaaaaaaaaaa' });

		const after = getOrder(order.id);
		expect(after.status).toBe('pending');
		expect(after.ckoPaymentId).toBe('');
		expect(after.ckoFlowPaymentId).toBe('');
	});

	test('status=failed return URL with a wrong order key leaves the order untouched', async ({ request }) => {
		const order = createPendingOrder();

		await hitReturnUrl(request, { orderId: order.id, key: 'wc_order_wrongkey', status: 'failed', paymentId: 'pay_aaaaaaaaaaaaaaaaaaaaaaaaaa' });

		expect(getOrder(order.id).status).toBe('pending');
	});

	test('a payment id Checkout.com does not know does not authorise the order', async ({ request }) => {
		const order = createPendingOrder();

		await hitReturnUrl(request, { orderId: order.id, key: order.key, paymentId: 'pay_aaaaaaaaaaaaaaaaaaaaaaaaaa' });

		expect(AUTHORISED).not.toContain(getOrder(order.id).status);
	});

	test('a payment already used by another order is not accepted', async ({ request }) => {
		const first = await createPaidOrder(request);
		const second = createPendingOrder();

		await hitReturnUrl(request, { orderId: second.id, key: second.key, paymentId: first.payment.id });

		expect(AUTHORISED).not.toContain(getOrder(second.id).status);
	});

	test('a payment for less than the order total is not accepted', async ({ request }) => {
		const order = createPendingOrder();
		const payment = await createCkoPayment({ amount: 100, currency: order.currency, reference: String(order.id) });

		await hitReturnUrl(request, { orderId: order.id, key: order.key, paymentId: payment.id });

		expect(AUTHORISED).not.toContain(getOrder(order.id).status);
	});

	test('a payment in a different currency is not accepted', async ({ request }) => {
		const order = createPendingOrder();
		const payment = await createCkoPayment({ amount: order.totalMinor, currency: order.currency === 'USD' ? 'EUR' : 'USD', reference: String(order.id) });

		await hitReturnUrl(request, { orderId: order.id, key: order.key, paymentId: payment.id });

		expect(AUTHORISED).not.toContain(getOrder(order.id).status);
	});

	test('a payment whose reference names a different order is not accepted', async ({ request }) => {
		const order = createPendingOrder();
		const other = createPendingOrder();
		const payment = await createCkoPayment({ amount: order.totalMinor, currency: order.currency, reference: String(other.id) });

		await hitReturnUrl(request, { orderId: order.id, key: order.key, paymentId: payment.id });

		expect(AUTHORISED).not.toContain(getOrder(order.id).status);
	});

	test('a stored payment/transaction id on a failed order does not skip verification', async ({ request }) => {
		const order = createPendingOrder();
		const unknown = 'pay_aaaaaaaaaaaaaaaaaaaaaaaaaa';
		patchOrder(order.id, { status: 'failed', transactionId: 'act_unverified', meta: { _cko_payment_id: unknown, _cko_flow_payment_id: unknown } });

		await hitReturnUrl(request, { orderId: order.id, key: order.key, paymentId: unknown });

		expect(getOrder(order.id).status).toBe('failed');
	});

	test('fails closed when the payment cannot be verified (SDK not initialised)', async ({ request }) => {
		const order = createPendingOrder();
		const payment = await createCkoPayment({ amount: order.totalMinor, currency: order.currency, reference: String(order.id) });

		const restore = overrideCoreSetting('ckocom_sk', '');
		try {
			await hitReturnUrl(request, { orderId: order.id, key: order.key, paymentId: payment.id });
		} finally {
			restore();
		}

		expect(AUTHORISED, 'genuine payment, but unverifiable - must not be assumed approved').not.toContain(getOrder(order.id).status);
	});

	test('a genuine payment for the right order and amount is still accepted', async ({ request }) => {
		const { stored } = await createPaidOrder(request);
		expect(AUTHORISED).toContain(stored.status);
	});
});

test.describe('Security: endpoints', () => {
	test('payment-status returns only the status fields to a checkout visitor', async ({ page, request, addToCart }) => {
		const { payment } = await createPaidOrder(request);
		await addToCart('virtual');
		await page.goto('/checkout/');
		const nonce = await page.evaluate(() => (window as unknown as { cko_flow_vars?: { payment_session_nonce?: string } }).cko_flow_vars?.payment_session_nonce ?? '');
		expect(nonce, 'checkout page exposes the visitor nonce').not.toBe('');

		const res = await page.request.get(`/wp-json/ckoplugin/v1/payment-status?paymentId=${payment.id}&nonce=${nonce}`);
		expect(res.status()).toBe(200);
		const body = (await res.json()) as Record<string, unknown>;

		expect(Object.keys(body).sort(), 'only status fields').toEqual(['approved', 'response_code', 'response_summary', 'status']);
		expect(body.status).toBe('Authorized');
		expect(JSON.stringify(body), 'no customer or card data').not.toMatch(/@example\.com|4242|source|billing|customer/i);
	});

	test('webhooks are rejected while no secret key is configured', async ({ request }) => {
		const { order, payment } = await createPaidOrder(request);
		const event = webhookEvent('payment_captured', payment);
		const restore = overrideCoreSetting('ckocom_sk', '');
		let code = 0;
		try {
			code = await sendWebhook(request, event, signWebhook(JSON.stringify(event), ''));
		} finally {
			restore();
		}

		expect(code).toBe(401);
		expect(getOrder(order.id).status).toBe('on-hold');
	});
});

test.describe('Security: saved cards and wallets (server checks)', () => {
	test('a saved card can only be used by the customer who owns it', () => {
		// This WP-CLI call is blocked in the Claude Code session that wrote the suite, so it was never
		// verified here. Run it from a normal terminal or CI, then remove fixme.
		test.fixme(true, 'Not yet verified by a run - see comment');
		// Owner = the seeded e2e_customer; a second customer is created once with the standard CLI.
		if (!customerIdByLogin('e2e_other')) {
			wp('user', 'create', 'e2e_other', 'e2e_other@example.com', '--role=customer');
		}
		const result = wpEvalJson<Record<string, boolean>>(`
			$owner = get_user_by( 'login', 'e2e_customer' )->ID;
			$other = get_user_by( 'login', 'e2e_other' )->ID;
			$t = new WC_Payment_Token_CC();
			$t->set_token( 'src_e2e_ownership_check_0000' ); $t->set_gateway_id( 'wc_checkout_com_flow' ); $t->set_card_type( 'visa' );
			$t->set_last4( '4242' ); $t->set_expiry_month( '12' ); $t->set_expiry_year( '2030' ); $t->set_user_id( $owner ); $t->save();
			$own_order   = wc_create_order( array( 'customer_id' => $owner ) );
			$other_order = wc_create_order( array( 'customer_id' => $other ) );
			$check = function ( $user, $order ) use ( $t ) { wp_set_current_user( $user ); return null !== WC_Checkoutcom_Utility::get_owned_payment_token( $t->get_id(), $order ); };
			$out = array(
				'owner_on_own_order'   => $check( $owner, $own_order ),
				'other_on_other_order' => $check( $other, $other_order ),
				'guest'                => $check( 0, $own_order ),
			);
			WC_Payment_Tokens::delete( $t->get_id() );
			return $out;
		`);
		expect(result).toEqual({ owner_on_own_order: true, other_on_other_order: false, guest: false });
	});

	test('wallet payments are only authorised on a final approved result', () => {
		const result = wpEvalJson<Record<string, boolean>>(`
			$f = array( 'WC_Checkoutcom_Utility', 'is_authorised_payment_response' );
			return array(
				'pending_3ds'        => $f( array( '3d' => 'https://3ds.example' ) ),
				'3ds_redirect_error' => $f( array( '3d_redirection_error' => true ) ),
				'status_pending'     => $f( array( 'id' => 'pay_x', 'approved' => true, 'status' => 'Pending' ) ),
				'missing_approved'   => $f( array( 'id' => 'pay_x', 'status' => 'Authorized' ) ),
				'approved'           => $f( array( 'id' => 'pay_x', 'approved' => true, 'status' => 'Authorized' ) ),
			);
		`);
		expect(result).toEqual({ pending_3ds: false, '3ds_redirect_error': false, status_pending: false, missing_approved: false, approved: true });
	});
});
