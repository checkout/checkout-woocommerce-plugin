import { expect, type Page } from '@playwright/test';
import { getCkoPayment } from './checkout-com';
import { getOrder, type StoreOrder } from './store';
import { wpEvalJson } from './wp-cli';
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

/** Orders created after `sinceOrderId` that ended up in an authorised status (must be none on failure paths). */
export function authorisedOrdersSince(sinceOrderId: number): Array<{ id: number; status: string }> {
	return wpEvalJson<Array<{ id: number; status: string }>>(`
		$out = array();
		foreach ( wc_get_orders( array( 'limit' => 20, 'orderby' => 'ID', 'order' => 'DESC' ) ) as $o ) {
			if ( $o->get_id() > ${Number(sinceOrderId)} && in_array( $o->get_status(), array( 'on-hold', 'processing', 'completed' ), true ) ) {
				$out[] = array( 'id' => $o->get_id(), 'status' => $o->get_status() );
			}
		}
		return $out;
	`);
}

/**
 * Failure path: the shopper is told the payment failed, never reaches the thank-you page,
 * and no order created since `sinceOrderId` is authorised.
 */
export async function expectPaymentRejected(page: Page, sinceOrderId: number, message: RegExp): Promise<void> {
	await expect(page.getByText(message).first(), 'shopper sees why the payment failed').toBeVisible({ timeout: 60_000 });
	expect(page.url(), 'never reaches the thank-you page').not.toMatch(/order-received/);
	expect(authorisedOrdersSince(sinceOrderId), 'no order was authorised').toEqual([]);
}
