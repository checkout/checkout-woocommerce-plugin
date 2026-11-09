import { test, expect } from '../../src/fixtures';
import type { Page } from '@playwright/test';
import { FlowComponent } from '../../src/pages/FlowComponent';
import { ThreeDSChallenge } from '../../src/pages/ThreeDSChallenge';
import { getCkoPayment } from '../../src/support/checkout-com';
import { changeSessionRequestInBrowser, sessionCapture } from '../../src/support/http-capture';
import { getOrder } from '../../src/support/store';
import { APPROVED_CARD, PRODUCTS, toMinorUnits } from '../../src/support/test-data';
import { wpEvalJson } from '../../src/support/wp-cli';

/*
 * MOTO: an order an admin creates for a guest customer and pays on its order-pay page
 * ("Customer payment page" link). It is sent as payment_type MOTO without 3DS; every other
 * Flow payment keeps 3DS. Order data on the page must survive apostrophes (O'Brien, Queen's Rd).
 */
type PayOrder = { id: number; payUrl: string };

function createOrder(opts: { createdVia: string; billing?: Record<string, string> }): PayOrder {
	const billing = {
		first_name: 'Sean', last_name: "O'Brien", email: `e2e+moto-${Date.now()}@example.com`,
		address_1: "1 Queen's Road", city: 'London', postcode: 'SW1A 1AA', country: 'GB',
		...(opts.billing || {}),
	};
	return wpEvalJson<PayOrder>(`
		$order = wc_create_order( array( 'customer_id' => 0, 'created_via' => ${JSON.stringify(opts.createdVia)} ) );
		$order->add_product( wc_get_product( wc_get_product_id_by_sku( ${JSON.stringify(PRODUCTS.virtual.sku)} ) ), 1 );
		$order->set_address( json_decode( ${JSON.stringify(JSON.stringify(billing))}, true ), 'billing' );
		$order->calculate_totals();
		$order->set_status( 'pending' );
		$order->save();
		return array( 'id' => $order->get_id(), 'payUrl' => $order->get_checkout_payment_url() );
	`);
}

/** Pay on the order-pay page; returns whether a 3DS challenge was shown. */
async function payOnOrderPayPage(page: Page, payUrl: string): Promise<boolean> {
	await page.goto(payUrl);
	await new FlowComponent(page).fillCard(APPROVED_CARD);
	if (await page.locator('#terms').count()) await page.locator('#terms').check();
	await page.locator('#place_order').click();

	const challenge = new ThreeDSChallenge(page);
	let challenged = false;
	await expect(async () => {
		if (!challenged && (await challenge.find())) {
			challenged = true;
			await challenge.complete();
		}
		expect(page.url()).toContain('/order-received/');
	}).toPass({ timeout: 90_000, intervals: [1_000] });
	return challenged;
}

test.describe('MOTO (admin order for a guest)', () => {
	test('is paid as MOTO without 3DS, with apostrophes in the order', async ({ page, loginAsAdmin }) => {
		const order = createOrder({ createdVia: 'admin' });
		await loginAsAdmin();

		await page.goto(order.payUrl);
		const pageData = await page.evaluate(() => (window as any).jQuery('#order-pay-info').data('order-pay'));
		expect(typeof pageData, 'order data on the page must parse (apostrophes escaped)').toBe('object');
		expect(pageData.payment_type).toBe('MOTO');
		await expect(page.locator('.cko-flow__waiting-message')).toHaveCount(0);

		expect(await payOnOrderPayPage(page, order.payUrl), 'MOTO must not show a 3DS challenge').toBe(false);

		const stored = getOrder(order.id);
		const payment = await getCkoPayment(stored.ckoPaymentId);
		expect(payment.approved).toBe(true);
		expect(payment.payment_type).toBe('MOTO');
		expect(payment['3ds'], 'MOTO payment must not carry 3DS').toBeUndefined();
	});

	test('an order-pay payment for a non-admin order keeps 3DS and is Regular', async ({ page }) => {
		const order = createOrder({ createdVia: 'checkout' });

		expect(await payOnOrderPayPage(page, order.payUrl), 'regular payments must be 3DS-challenged').toBe(true);

		const payment = await getCkoPayment(getOrder(order.id).ckoPaymentId);
		expect(payment.approved).toBe(true);
		expect(payment.payment_type).toBe('Regular');
		expect(payment['3ds']?.challenged).toBe(true);
	});

	test('a MOTO claim from the browser is not trusted for a normal order', async ({ page }) => {
		const order = createOrder({ createdVia: 'checkout' });
		// Make the page report MOTO, as a tampered browser would; the server must decide from the order.
		await page.route(/\/order-pay\//, async (route) => {
			const response = await route.fetch();
			const html = (await response.text()).replace(/(&quot;payment_type&quot;:&quot;)\w+(&quot;)/, '$1MOTO$2');
			await route.fulfill({ response, body: html });
		});
		await page.goto(order.payUrl);
		expect(await page.evaluate(() => (window as any).jQuery('#order-pay-info').data('order-pay')?.payment_type)).toBe('MOTO');

		expect(await payOnOrderPayPage(page, order.payUrl), 'a normal order must still be 3DS-challenged').toBe(true);

		const payment = await getCkoPayment(getOrder(order.id).ckoPaymentId);
		expect(payment.payment_type).toBe('Regular');
		expect(payment['3ds']?.challenged).toBe(true);
	});

	test('a MOTO session takes its amount, currency and reference from the order', async ({ page, loginAsAdmin }) => {
		const order = createOrder({ createdVia: 'admin' });
		const stored = getOrder(order.id);
		const totalMinor = toMinorUnits(stored.total);
		await loginAsAdmin();
		await changeSessionRequestInBrowser(page, { set: { amount: 1, reference: 'e2e-not-this-order' } });

		sessionCapture.start();
		try {
			expect(await payOnOrderPayPage(page, order.payUrl), 'MOTO must not show a 3DS challenge').toBe(false);

			const sent = sessionCapture.creates().pop();
			expect(sent?.payment_type).toBe('MOTO');
			expect(sent?.['3ds']).toEqual({ enabled: false });
			expect(sent?.amount, 'session amount comes from the order').toBe(totalMinor);
			expect(sent?.currency).toBe(stored.currency);
			expect(sent?.reference).toBe(String(order.id));
			expect(Number(sent?.metadata?.order_id)).toBe(order.id);
		} finally {
			sessionCapture.stop();
		}

		const payment = await getCkoPayment(getOrder(order.id).ckoPaymentId);
		expect(payment.payment_type).toBe('MOTO');
		expect(payment.amount).toBe(totalMinor);
	});

	test('a MOTO order that is already paid does not make a session MOTO', async ({ page }) => {
		const paid = createOrder({ createdVia: 'admin' });
		wpEvalJson(`$o = wc_get_order( ${paid.id} ); $o->set_status( 'processing' ); $o->save(); return true;`);
		const paidKey = new URL(paid.payUrl).searchParams.get('key') as string;
		const regular = createOrder({ createdVia: 'checkout' });
		// The session request names the paid MOTO order; the store must not grant MOTO for it.
		await changeSessionRequestInBrowser(page, { successUrlQuery: { order_id: String(paid.id), key: paidKey } });

		sessionCapture.start();
		try {
			await page.goto(regular.payUrl);
			await new FlowComponent(page).waitUntilReady();
			await expect.poll(() => sessionCapture.creates().length, { timeout: 30_000 }).toBeGreaterThan(0);
			const sent = sessionCapture.creates().pop();
			expect(sent?.payment_type).not.toBe('MOTO');
		} finally {
			sessionCapture.stop();
		}
	});

	test('saved cards and "save card" are hidden on a MOTO order', async ({ page, loginAsAdmin }) => {
		// The admin paying has a saved card of their own; it must not be offered for the guest's order.
		const tokenId = wpEvalJson<number>(`
			$t = new WC_Payment_Token_CC();
			$t->set_token( 'src_e2emotohidden' . time() );
			$t->set_gateway_id( 'wc_checkout_com_flow' );
			$t->set_card_type( 'visa' );
			$t->set_last4( '4242' );
			$t->set_expiry_month( '12' );
			$t->set_expiry_year( '2030' );
			$t->set_user_id( get_user_by( 'login', 'admin' )->ID );
			$t->save();
			return $t->get_id();
		`);
		try {
			const moto = createOrder({ createdVia: 'admin' });
			const regular = createOrder({ createdVia: 'checkout' });
			await loginAsAdmin();
			const savedCard = page.locator('input[name="wc-wc_checkout_com_flow-payment-token"][value="' + tokenId + '"]');

			// Control: on a normal order the admin's saved card is offered.
			await page.goto(regular.payUrl);
			await new FlowComponent(page).waitUntilReady();
			await expect(savedCard).toBeVisible();

			await page.goto(moto.payUrl);
			await new FlowComponent(page).waitUntilReady();
			await expect(savedCard).toBeHidden();
			await expect(page.locator('#wc-wc_checkout_com_flow-new-payment-method')).toBeHidden();
		} finally {
			wpEvalJson(`WC_Payment_Tokens::delete( ${tokenId} ); return true;`);
		}
	});

	test('admin sees a warning on a manual order missing billing details', async ({ page, loginAsAdmin }) => {
		const incomplete = createOrder({ createdVia: 'admin', billing: { last_name: '', address_1: '', city: '' } });
		const complete = createOrder({ createdVia: 'admin' });
		await loginAsAdmin();

		const editUrl = (id: number) => wpEvalJson<string>(`return wc_get_order( ${id} )->get_edit_order_url();`);
		await page.goto(editUrl(incomplete.id));
		await expect(page.locator('.notice-error', { hasText: 'Checkout.com Flow Warning' })).toContainText('billing first and last name');
		await expect(page.locator('.notice-error', { hasText: 'Checkout.com Flow Warning' })).toContainText('complete billing address');

		await page.goto(editUrl(complete.id));
		await expect(page.locator('.notice', { hasText: 'Checkout.com Flow' })).toHaveCount(0);
	});
});
