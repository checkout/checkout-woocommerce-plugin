import { test, expect } from '../../src/fixtures';
import { createCkoPayment, SANDBOX_CARDS } from '../../src/support/checkout-com';

/*
 * Classic (Frames) My Account -> Add payment method: the shopper returns to the plugin's callback
 * after a declined card verification. That verification has no order, so the callback must show
 * "Unable to add payment method" on Payment methods - not crash.
 */
test('a declined card on Add payment method returns to Payment methods without an error', async ({ request }) => {
	const payment = await createCkoPayment({
		amount: 0,
		currency: 'USD',
		reference: 'e2e-add-card-declined',
		card: SANDBOX_CARDS.declined,
		metadata: { card_verification: 'true', redirection_url: 'http://localhost:8890/my-account/payment-methods/' },
	});
	expect(payment.status).toBe('Declined');

	const res = await request.get(`/?wc-api=wc_checkoutcom_callback&cko-session-id=${payment.id}`, { maxRedirects: 0 });
	expect(res.status(), 'callback must redirect, not fail').toBe(302);
	expect(res.headers()['location']).toContain('/my-account/payment-methods/');
});
