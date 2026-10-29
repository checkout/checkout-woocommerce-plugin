import { test, expect } from '../../src/fixtures';
import { createCkoPayment, signWebhook, webhookEvent } from '../../src/support/checkout-com';
import { createPaidOrder, createPendingOrder } from '../../src/support/orders';
import { getOrder, latestOrderId, orderRefunds, patchOrder } from '../../src/support/store';
import { sendWebhook } from '../../src/support/webhooks';
import { wpEvalJson } from '../../src/support/wp-cli';

/*
 * Webhooks signed exactly like Checkout.com signs them (HMAC-SHA256, "Bearer <sk>"),
 * posted to /?wc-api=wc_checkoutcom_webhook. Deterministic - no tunnel needed.
 * Orders come from createPaidOrder(): a real sandbox payment authorised by the plugin.
 */
test.describe('Webhooks: lifecycle', () => {
	test('payment_captured moves an authorised order to processing', async ({ request }) => {
		const { order, payment } = await createPaidOrder(request);

		expect(await sendWebhook(request, webhookEvent('payment_captured', payment))).toBe(200);

		const after = getOrder(order.id);
		expect(after.status).toBe('processing');
		expect(after.notes.some((n) => /Payment Captured/i.test(n))).toBe(true);
	});

	test('a duplicate payment_captured delivery is applied once', async ({ request }) => {
		const { order, payment } = await createPaidOrder(request);
		const event = webhookEvent('payment_captured', payment);

		expect(await sendWebhook(request, event)).toBe(200);
		expect(await sendWebhook(request, event)).toBe(200);

		const captureNotes = getOrder(order.id).notes.filter((n) => /Payment Captured - Payment ID/i.test(n));
		expect(captureNotes, 'one capture note for one capture').toHaveLength(1);
	});

	test('a capture for less than the order total does not advance the order', async ({ request }) => {
		const { order, payment } = await createPaidOrder(request);

		await sendWebhook(request, webhookEvent('payment_captured', { ...payment, amount: payment.amount - 100 }));

		const after = getOrder(order.id);
		expect(after.status, 'underpaid capture keeps the order on-hold').toBe('on-hold');
		expect(after.notes.some((n) => /does not match the order total/i.test(n))).toBe(true);
	});

	test('a late payment_approved after capture does not downgrade the order', async ({ request }) => {
		const { order, payment } = await createPaidOrder(request);
		await sendWebhook(request, webhookEvent('payment_captured', payment));

		await sendWebhook(request, webhookEvent('payment_approved', payment));

		expect(getOrder(order.id).status).toBe('processing');
	});

	test('payment_refunded (full) records a full WooCommerce refund', async ({ request }) => {
		const { order, payment } = await createPaidOrder(request);
		await sendWebhook(request, webhookEvent('payment_captured', payment));

		expect(await sendWebhook(request, webhookEvent('payment_refunded', payment))).toBe(200);

		expect(orderRefunds(order.id).map(Number)).toEqual([Number(order.total)]);
		expect(getOrder(order.id).status).toBe('refunded');
	});

	test('payment_refunded (partial) records a partial refund and keeps the order open', async ({ request }) => {
		const { order, payment } = await createPaidOrder(request);
		await sendWebhook(request, webhookEvent('payment_captured', payment));

		await sendWebhook(request, webhookEvent('payment_refunded', { ...payment, amount: 500 }));

		expect(orderRefunds(order.id).map(Number)).toEqual([5]);
		expect(getOrder(order.id).status).toBe('processing');
	});

	test('payment_voided cancels an authorised order', async ({ request }) => {
		test.skip(process.env.E2E_LIVE_WEBHOOKS === '1', 'Live delivery on: the real payment_approved arrives after the signed void and reopens the order (known reopen bug)');
		const { order, payment } = await createPaidOrder(request);

		expect(await sendWebhook(request, webhookEvent('payment_voided', payment))).toBe(200);

		expect(getOrder(order.id).status).toBe('cancelled');
	});

	test('payment_declined fails a pending order', async ({ request }) => {
		const order = createPendingOrder();
		const payment = await createCkoPayment({ amount: order.totalMinor, currency: order.currency, reference: String(order.id), card: '4544249167673670' });
		expect(payment.status).toBe('Declined');
		// The plugin records a genuine decline's payment id on its order (process_payment).
		patchOrder(order.id, { meta: { _cko_payment_id: payment.id } });

		await sendWebhook(request, webhookEvent('payment_declined', payment, { response_code: '20051', response_summary: 'Insufficient Funds' }));

		expect(getOrder(order.id).status).toBe('failed');
	});
});

test.describe('Webhooks: order lookup', () => {
	test('get_wc_order resolves only exact ids or stored order numbers', () => {
		const order = createPendingOrder();
		const number = String(800000 + (order.id % 100000));
		patchOrder(order.id, { meta: { _cko_order_reference: number } });

		const found = wpEvalJson<{ byId: number; byNumber: number; unknown: boolean }>(`
			$m = new ReflectionMethod( 'WC_Checkout_Com_Webhook', 'get_wc_order' );
			$m->setAccessible( true );
			$by_id = $m->invoke( null, ${order.id} );
			$by_number = $m->invoke( null, '${number}' );
			return array( 'byId' => $by_id ? $by_id->get_id() : 0, 'byNumber' => $by_number ? $by_number->get_id() : 0, 'unknown' => false === $m->invoke( null, '99999998' ) );
		`);
		expect(found).toEqual({ byId: order.id, byNumber: order.id, unknown: true });
	});

	test('a decline addressed by order number (number differs from id) fails that order without crashing', async ({ request }) => {
		const order = createPendingOrder();
		const number = String(700000 + (order.id % 100000));
		const payment = await createCkoPayment({ amount: order.totalMinor, currency: order.currency, reference: number, card: '4544249167673670' });
		patchOrder(order.id, { meta: { _cko_order_reference: number, _cko_payment_id: payment.id } });
		const newer = createPendingOrder(); // the old fallback resolved unknown ids to the newest order

		const code = await sendWebhook(request, webhookEvent('payment_declined', { ...payment, reference: number, metadata: {} }, { response_code: '20051', response_summary: 'Insufficient Funds' }));

		expect(code, 'handled, not a PHP fatal').toBe(200);
		expect(getOrder(order.id).status, 'the addressed order is failed').toBe('failed');
		const newerOrder = getOrder(newer.id);
		expect(newerOrder.status, 'the newest order is untouched').toBe('pending');
		expect(newerOrder.notes.some((n) => n.includes(payment.id)), 'no note for a payment that is not its own').toBe(false);
	});
});

test.describe('Webhooks: security', () => {
	test('an unsigned webhook is rejected and changes nothing', async ({ request }) => {
		const { order, payment } = await createPaidOrder(request);

		expect(await sendWebhook(request, webhookEvent('payment_captured', payment), 'missing')).toBe(401);
		expect(getOrder(order.id).status).toBe('on-hold');
	});

	test('a webhook with a forged signature is rejected and changes nothing', async ({ request }) => {
		const { order, payment } = await createPaidOrder(request);
		const event = webhookEvent('payment_captured', payment);
		const forged = signWebhook(JSON.stringify(event), 'sk_sbox_attacker_guess');

		expect(await sendWebhook(request, event, forged)).toBe(401);
		expect(getOrder(order.id).status).toBe('on-hold');
	});

	test('a capture for another order\'s payment does not move this order', async ({ request }) => {
		const a = await createPaidOrder(request);
		const b = await createPaidOrder(request);

		// Payment of A, but pointing at order B.
		await sendWebhook(request, webhookEvent('payment_captured', { ...a.payment, reference: String(b.order.id), metadata: { order_id: String(b.order.id) } }));

		expect(getOrder(b.order.id).status, 'order B untouched').toBe('on-hold');
	});

	test('an event for a non-existent order number does not touch the newest (paid) order', async ({ request }) => {
		const newest = await createPaidOrder(request);
		const stranger = await createCkoPayment({ amount: newest.order.totalMinor, currency: newest.order.currency, reference: '99999999' });

		await sendWebhook(request, webhookEvent('payment_voided', { ...stranger, reference: '99999999', metadata: { order_id: '99999999' } }));

		expect(latestOrderId()).toBe(newest.order.id);
		expect(getOrder(newest.order.id).status, 'unrelated event must not touch the newest order').toBe('on-hold');
	});

	test('card_verified for an unrelated payment does not move a pending order', async ({ request }) => {
		const victim = createPendingOrder();
		const unrelated = await createCkoPayment({ amount: 0, currency: victim.currency, reference: `cko-add-payment-method-${Date.now()}` });

		await sendWebhook(request, webhookEvent('card_verified', { ...unrelated, reference: String(victim.id), metadata: { order_id: String(victim.id) } }));

		expect(getOrder(victim.id).status, 'unpaid order must stay pending').toBe('pending');
	});

	test('late payment_approved does not reopen a cancelled (voided) order', async ({ request }) => {
		const { order, payment } = await createPaidOrder(request);
		await sendWebhook(request, webhookEvent('payment_voided', payment));
		expect(getOrder(order.id).status).toBe('cancelled');

		await sendWebhook(request, webhookEvent('payment_approved', payment));

		expect(getOrder(order.id).status, 'cancelled order stays cancelled').toBe('cancelled');
	});
});
