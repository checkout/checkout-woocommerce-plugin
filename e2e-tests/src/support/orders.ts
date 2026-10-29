import type { APIRequestContext } from '@playwright/test';
import { createCkoPayment, type CkoPayment } from './checkout-com';
import { getOrder, type StoreOrder } from './store';
import { PRODUCTS } from './test-data';
import { wpEvalJson } from './wp-cli';

export interface TestOrder {
	id: number;
	key: string;
	total: string;
	currency: string;
	totalMinor: number;
}

/** Create a pending Flow order for one test product via WP-CLI (no browser). */
export function createPendingOrder(sku: string = PRODUCTS.virtual.sku, customerId = 0): TestOrder {
	return wpEvalJson<TestOrder>(`
		$product = wc_get_product( wc_get_product_id_by_sku( ${JSON.stringify(sku)} ) );
		$order   = wc_create_order( array( 'customer_id' => ${Number(customerId)} ) );
		$order->add_product( $product, 1 );
		$order->set_address( array(
			'first_name' => 'E2E', 'last_name' => 'Server', 'email' => 'e2e+server-' . wp_generate_password( 8, false ) . '@example.com',
			'address_1' => '1 Market Street', 'city' => 'San Francisco', 'state' => 'CA', 'postcode' => '94103', 'country' => 'US',
		), 'billing' );
		$order->set_payment_method( 'wc_checkout_com_flow' );
		$order->calculate_totals();
		$order->set_status( 'pending' );
		$order->save();
		$minor = (int) round( (float) WC_Checkoutcom_Utility::value_to_decimal( (float) $order->get_total(), $order->get_currency() ) );
		return array( 'id' => $order->get_id(), 'key' => $order->get_order_key(), 'total' => $order->get_total(), 'currency' => $order->get_currency(), 'totalMinor' => $minor );
	`);
}

/** The plugin's public 3DS/redirect return URL - what Checkout.com sends the shopper back to. */
export function flowReturnUrl(params: { orderId?: number; key?: string; paymentId?: string; status?: string }): string {
	const q = new URLSearchParams({ 'wc-api': 'wc_checkoutcom_flow_process' });
	if (params.orderId !== undefined) q.set('order_id', String(params.orderId));
	if (params.key !== undefined) q.set('key', params.key);
	if (params.paymentId !== undefined) q.set('cko-payment-id', params.paymentId);
	if (params.status !== undefined) q.set('status', params.status);
	return `/?${q.toString()}`;
}

/** Hit the return URL like a shopper's browser would (redirects not followed). */
export async function hitReturnUrl(request: APIRequestContext, params: Parameters<typeof flowReturnUrl>[0]): Promise<number> {
	const res = await request.get(flowReturnUrl(params), { maxRedirects: 0 });
	return res.status();
}

/**
 * A paid order without a browser: pending order + real sandbox payment for its exact
 * amount/reference, then the shopper's return to the store. The plugin's own server-side
 * verification is what authorises the order.
 */
export async function createPaidOrder(request: APIRequestContext, opts: { capture?: boolean } = {}): Promise<{ order: TestOrder; payment: CkoPayment; stored: StoreOrder }> {
	const order = createPendingOrder();
	const payment = await createCkoPayment({ amount: order.totalMinor, currency: order.currency, reference: String(order.id), capture: opts.capture });
	await hitReturnUrl(request, { orderId: order.id, key: order.key, paymentId: payment.id });
	const stored = getOrder(order.id);
	if (!['on-hold', 'processing'].includes(stored.status)) {
		throw new Error(`Factory: order ${order.id} not authorised after return (status ${stored.status}; notes: ${stored.notes.join(' | ')})`);
	}
	return { order, payment, stored };
}
