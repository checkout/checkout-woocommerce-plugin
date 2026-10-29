import { expect } from '@playwright/test';
import { getCkoPayment } from './checkout-com';
import { getOrder, type StoreOrder } from './store';
import { toMinorUnits } from './test-data';

const AUTHORISED_STATUSES = ['on-hold', 'processing', 'completed'];

/**
 * A payment test passes only if all three places agree:
 *  1. the shopper saw the thank-you page (checked by the caller),
 *  2. WooCommerce has the order in an authorised status with a Checkout.com payment id,
 *  3. Checkout.com has an approved payment for exactly this order's amount and currency.
 * A thank-you page alone proves nothing - an unpaid order once reached "on-hold" with one.
 */
export async function expectOrderPaidByCheckoutCom(orderId: number): Promise<StoreOrder> {
	const order = getOrder(orderId);

	expect(order.paymentMethod, 'order payment method').toBe('wc_checkout_com_flow');
	expect(AUTHORISED_STATUSES, `order status (notes: ${order.notes.join(' | ')})`).toContain(order.status);

	const paymentId = order.ckoPaymentId || order.ckoFlowPaymentId;
	expect(paymentId, 'Checkout.com payment id on the order').toMatch(/^pay_[a-z0-9]{20,40}$/i);

	const payment = await getCkoPayment(paymentId);
	expect(payment.approved, 'Checkout.com approved flag').toBe(true);
	expect(['Authorized', 'Captured'], 'Checkout.com payment status').toContain(payment.status);
	expect(payment.currency, 'charged currency').toBe(order.currency);
	expect(payment.amount, 'charged amount (minor units)').toBe(toMinorUnits(order.total));
	if (payment.reference && /^\d+$/.test(payment.reference)) {
		expect(payment.reference, 'payment reference names this order').toBe(String(orderId));
	}

	return order;
}
