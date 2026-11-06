import { test, expect } from '../../src/fixtures';
import { createCkoPayment, webhookEvent } from '../../src/support/checkout-com';
import { createPaidOrder, createPendingOrder } from '../../src/support/orders';
import { getOrder, orderRefunds, patchOrder } from '../../src/support/store';
import { sendWebhook } from '../../src/support/webhooks';
import { wpEvalJson } from '../../src/support/wp-cli';

/*
 * Webhook handlers only change an order that the payment belongs to, and only in line with
 * what the payment actually covers. Signed like Checkout.com signs them; no tunnel needed.
 */

function isMarkedAuthorised(orderId: number): boolean {
	return wpEvalJson<boolean>(`return (bool) wc_get_order( ${Number(orderId)} )->get_meta( 'cko_payment_authorized' );`);
}

test.describe('Webhooks: payment and order consistency', () => {
	test('payment_approved for less than the order total holds the order on-hold', async ({ request }) => {
		const order = createPendingOrder();
		const payment = await createCkoPayment({ amount: order.totalMinor, currency: order.currency, reference: String(order.id) });
		patchOrder(order.id, { meta: { _cko_payment_id: payment.id } });

		await sendWebhook(request, webhookEvent('payment_approved', { ...payment, amount: payment.amount - 100 }));

		const after = getOrder(order.id);
		expect(after.status).toBe('on-hold');
		expect(after.notes.some((n) => /Authorised amount or currency does not match the order total/i.test(n))).toBe(true);
		expect(isMarkedAuthorised(order.id), 'order not recorded as authorised').toBe(false);
	});

	test('payment_approved for the full order total still authorises the order', async ({ request }) => {
		const order = createPendingOrder();
		const payment = await createCkoPayment({ amount: order.totalMinor, currency: order.currency, reference: String(order.id) });
		patchOrder(order.id, { meta: { _cko_payment_id: payment.id } });

		expect(await sendWebhook(request, webhookEvent('payment_approved', payment))).toBe(200);

		expect(isMarkedAuthorised(order.id)).toBe(true);
		expect(getOrder(order.id).status).toBe('on-hold');
	});

	test('card_verified does not mark an order with a non-zero total as paid', async ({ request }) => {
		const { order, payment } = await createPaidOrder(request);
		expect(Number(order.total)).toBeGreaterThan(0);

		expect(await sendWebhook(request, webhookEvent('card_verified', payment))).toBe(200);

		const after = getOrder(order.id);
		expect(after.status, 'status unchanged').toBe('on-hold');
		expect(after.notes.some((n) => /Order status not changed/i.test(n))).toBe(true);
	});

	test('payment_canceled for another order\'s payment leaves this order unchanged', async ({ request }) => {
		// Order A holds payment P; P's metadata names order B, which has no payment of its own.
		const target = createPendingOrder();
		const owner = createPendingOrder();
		const payment = await createCkoPayment({
			amount: owner.totalMinor,
			currency: owner.currency,
			reference: `e2e-${Date.now()}`,
			metadata: { order_id: String(target.id) },
		});
		patchOrder(owner.id, { meta: { _cko_payment_id: payment.id } });

		await sendWebhook(request, webhookEvent('payment_canceled', { ...payment, metadata: {} }));

		expect(getOrder(target.id).status, 'order B untouched').toBe('pending');
	});

	test('payment_refunded books no refund on an order without that payment id', async ({ request }) => {
		const order = createPendingOrder();
		patchOrder(order.id, { status: 'processing' });
		const payment = await createCkoPayment({ amount: order.totalMinor, currency: order.currency, reference: String(order.id), capture: true });

		await sendWebhook(request, webhookEvent('payment_refunded', payment));

		expect(orderRefunds(order.id), 'no WooCommerce refund recorded').toEqual([]);
		expect(getOrder(order.id).status).toBe('processing');
	});

	test('payment_refunded whose metadata and reference name different orders books no refund', async ({ request }) => {
		const owner = await createPaidOrder(request, { capture: true });
		const other = createPendingOrder();
		patchOrder(other.id, { status: 'processing' });

		await sendWebhook(
			request,
			webhookEvent('payment_refunded', { ...owner.payment, reference: String(owner.order.id), metadata: { order_id: String(other.id) } }),
		);

		expect(orderRefunds(other.id), 'other order not refunded').toEqual([]);
		expect(orderRefunds(owner.order.id), 'owner order not refunded by this event').toEqual([]);
	});
});
