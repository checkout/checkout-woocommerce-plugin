import { test, expect } from '../../src/fixtures';
import { createCkoPayment } from '../../src/support/checkout-com';
import { createPendingOrder } from '../../src/support/orders';
import { getOrder } from '../../src/support/store';
import { wpEvalJson } from '../../src/support/wp-cli';

/*
 * Classic card gateway: return path (some tests create sandbox payments), logging and
 * admin/checkout output checks.
 */

test.describe('Classic gateway hardening', () => {
	test('callback with a malformed cko-session-id redirects to the store without a lookup', async ({ request, baseURL }) => {
		for (const id of ['bad', '../../payments', 'pay_short', 'sid_aaaaaaaaaaaaaaaaaaaaaaaaaa/../x']) {
			const res = await request.get(`/?wc-api=wc_checkoutcom_callback&cko-session-id=${encodeURIComponent(id)}`, { maxRedirects: 0 });
			expect(res.status(), `callback redirects for "${id}"`).toBe(302);
			const location = new URL(res.headers()['location'] ?? '', baseURL);
			expect(location.host, 'redirect stays on the store').toBe(new URL(baseURL ?? '').host);
		}
	});

	test('session id format check accepts Checkout.com ids only', async () => {
		const results = wpEvalJson<Record<string, boolean>>(`
			$ids = array( 'pay_aaaaaaaaaaaaaaaaaaaaaaaaaa', 'sid_aaaaaaaaaaaaaaaaaaaaaaaaaa', 'bad', 'pay_aaaa', 'pay_aaaaaaaaaaaaaaaaaaaaaaaaaa/x', 'tok_aaaaaaaaaaaaaaaaaaaaaaaaaa' );
			$out = array();
			foreach ( $ids as $id ) {
				$out[ $id ] = WC_Checkoutcom_Api_Request::is_valid_session_id( $id );
			}
			$out['verify_session_error'] = ! empty( WC_Checkoutcom_Api_Request::verify_session( '../bad' )['error'] );
			return $out;
		`);
		expect(results).toEqual({
			pay_aaaaaaaaaaaaaaaaaaaaaaaaaa: true,
			sid_aaaaaaaaaaaaaaaaaaaaaaaaaa: true,
			bad: false,
			pay_aaaa: false,
			'pay_aaaaaaaaaaaaaaaaaaaaaaaaaa/x': false,
			tok_aaaaaaaaaaaaaaaaaaaaaaaaaa: false,
			verify_session_error: true,
		});
	});

	test('callback for a payment whose amount differs from the order does not mark it paid', async ({ request }) => {
		const order = createPendingOrder();
		const payment = await createCkoPayment({
			amount: Math.max(1, order.totalMinor - 100),
			currency: order.currency,
			reference: String(order.id),
			metadata: { order_id: String(order.id) },
		});
		expect(payment.status).toBe('Authorized');

		const res = await request.get(`/?wc-api=wc_checkoutcom_callback&cko-session-id=${payment.id}`, { maxRedirects: 0 });
		expect(res.status(), 'callback redirects').toBe(302);

		const after = getOrder(order.id);
		expect(after.status, 'order is held for review').toBe('on-hold');
		expect(after.notes.join('\n')).toContain('Payment amount or currency does not match the order');
		expect(after.notes.join('\n')).not.toContain('Payment Authorised');
	});

	test('callback for a matching payment still authorises the order', async ({ request }) => {
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
		expect(res.headers()['location']).toContain('order-received');

		const after = getOrder(order.id);
		expect(after.ckoPaymentId).toBe(payment.id);
		expect(after.notes.join('\n')).toContain('Payment Authorised');
	});
});
