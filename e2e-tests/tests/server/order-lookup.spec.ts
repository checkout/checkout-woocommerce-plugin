import { test, expect } from '../../src/fixtures';
import { createCkoPayment } from '../../src/support/checkout-com';
import { createPendingOrder, hitReturnUrl } from '../../src/support/orders';
import { getOrder, patchOrder } from '../../src/support/store';

/*
 * When the shopper returns from 3DS without an order id in the URL (the Blocks checkout path),
 * the plugin finds the order by payment-session id. That lookup must match exactly on BOTH
 * WooCommerce order storages - on the legacy posts storage, `meta_query` is silently ignored
 * and used to return unrelated recent orders.
 */
test.describe('Order lookup on return without an order id', () => {
	test('the payment is applied to the shopper\'s order, not another pending order with the same total', async ({ request }) => {
		const shopper = createPendingOrder();
		const sessionId = `ps_e2e${Date.now()}lookup`;
		patchOrder(shopper.id, { meta: { _cko_payment_session_id: sessionId } });
		const otherCustomer = createPendingOrder(); // newer, same total, no relation to this payment

		const payment = await createCkoPayment({ amount: shopper.totalMinor, currency: shopper.currency, reference: `wc-blocks-${Date.now()}` });
		await hitReturnUrl(request, { paymentId: payment.id, sessionId });

		expect(getOrder(otherCustomer.id).status, 'another customer\'s order must not be touched').toBe('pending');
		expect(getOrder(otherCustomer.id).ckoPaymentId, 'and must not receive the payment id').toBe('');
		const own = getOrder(shopper.id);
		expect(['on-hold', 'processing'], 'the shopper\'s own order is paid').toContain(own.status);
		expect(own.ckoPaymentId).toBe(payment.id);
	});
});
