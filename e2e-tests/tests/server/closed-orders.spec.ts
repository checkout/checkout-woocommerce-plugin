import { test, expect } from '../../src/fixtures';
import { createCkoPayment, webhookEvent } from '../../src/support/checkout-com';
import { createPaidOrder, createPendingOrder, hitReturnUrl } from '../../src/support/orders';
import { getOrder, patchOrder } from '../../src/support/store';
import { sendWebhook } from '../../src/support/webhooks';

/*
 * Order-status rules (Flow):
 *  - A cancelled or refunded order is closed: none of its own payment events, and no returning
 *    payment, may change its status.
 *  - A failed order may still be completed by ITS OWN genuine payment (policy "a": a late bank
 *    approval must not leave money taken against a failed order).
 */
test.describe('Closed orders stay closed', () => {
	for (const event of ['payment_captured', 'payment_approved', 'payment_declined', 'card_verified'] as const) {
		test(`a cancelled order is not changed by its own ${event} webhook`, async ({ request }) => {
			const { order, payment } = await createPaidOrder(request);
			patchOrder(order.id, { status: 'cancelled' }); // merchant cancels the order in admin

			expect(await sendWebhook(request, webhookEvent(event, payment))).toBe(200);

			const after = getOrder(order.id);
			expect(after.status, `${event} must not reopen a cancelled order`).toBe('cancelled');
		});
	}

	test('a fully refunded order is not reopened by a late capture webhook', async ({ request }) => {
		const { order, payment } = await createPaidOrder(request);
		await sendWebhook(request, webhookEvent('payment_captured', payment));
		await sendWebhook(request, webhookEvent('payment_refunded', payment));
		expect(getOrder(order.id).status).toBe('refunded');

		await sendWebhook(request, webhookEvent('payment_captured', payment));

		expect(getOrder(order.id).status).toBe('refunded');
	});

	test('a returning payment is not applied to a cancelled order', async ({ request }) => {
		const order = createPendingOrder();
		const payment = await createCkoPayment({ amount: order.totalMinor, currency: order.currency, reference: String(order.id) });
		patchOrder(order.id, { status: 'cancelled' });

		await hitReturnUrl(request, { orderId: order.id, key: order.key, paymentId: payment.id });

		const after = getOrder(order.id);
		expect(after.status).toBe('cancelled');
		expect(after.notes.some((n) => n.includes(payment.id) && /closed order/i.test(n)), 'merchant told the payment was not applied').toBe(true);
	});

	test('a partially refunded order stays open and accepts a further partial refund', async ({ request }) => {
		const { order, payment } = await createPaidOrder(request);
		await sendWebhook(request, webhookEvent('payment_captured', payment));
		await sendWebhook(request, webhookEvent('payment_refunded', { ...payment, amount: 200 }));

		await sendWebhook(request, webhookEvent('payment_refunded', { ...payment, amount: 300 }));

		expect(getOrder(order.id).status).toBe('processing');
	});
});

test.describe('Failed orders (policy: own genuine payment may complete them)', () => {
	test('a failed order is completed by a late approval of its own payment', async ({ request }) => {
		const order = createPendingOrder();
		const payment = await createCkoPayment({ amount: order.totalMinor, currency: order.currency, reference: String(order.id) });
		// The order failed earlier, but this payment is bound to it.
		patchOrder(order.id, { status: 'failed', meta: { _cko_payment_id: payment.id } });

		await sendWebhook(request, webhookEvent('payment_approved', payment));

		expect(getOrder(order.id).status).toBe('on-hold');
	});
});
