import { test, expect } from '../../src/fixtures';
import { createPaidOrder } from '../../src/support/orders';
import { getOrder } from '../../src/support/store';

/*
 * Real webhooks from Checkout.com (no signing by us). Needs the store reachable from the
 * internet (e.g. a cloudflared tunnel) and a Checkout.com workflow pointing at
 * <public-url>/?wc-api=wc_checkoutcom_webhook. Opt in with E2E_LIVE_WEBHOOKS=1.
 */
test.describe('Live webhooks @webhook-live', () => {
	test.skip(process.env.E2E_LIVE_WEBHOOKS !== '1', 'Set E2E_LIVE_WEBHOOKS=1 with a public URL registered as the webhook endpoint');

	test('real payment_approved and payment_captured webhooks complete the order', async ({ request }) => {
		const { order, payment } = await createPaidOrder(request, { capture: true });

		await expect
			.poll(() => getOrder(order.id).status, { message: 'real capture webhook moved the order to processing', timeout: 120_000, intervals: [3000] })
			.toBe('processing');

		const notes = getOrder(order.id).notes;
		expect(notes.some((n) => n.includes('Webhook received from checkout.com') && n.includes(payment.id)), 'payment_approved webhook note').toBe(true);
		expect(notes.some((n) => /Payment Captured/.test(n) && n.includes(payment.id)), 'payment_captured webhook note').toBe(true);
	});
});
