import type { Page } from '@playwright/test';
import { test, expect } from '../../src/fixtures';
import { getCkoPayment, waitForCkoStatus } from '../../src/support/checkout-com';
import { createPaidOrder } from '../../src/support/orders';
import { getOrder, orderRefunds } from '../../src/support/store';
import { wpEvalJson } from '../../src/support/wp-cli';

/*
 * Refunds started from WooCommerce admin go to Checkout.com through the gateway's
 * process_refund(). Checked in WooCommerce (refund recorded, status) and at Checkout.com
 * (refund action for the right amount).
 */
test.describe('Refunds from WooCommerce admin', () => {
	test.beforeEach(async ({ loginAsAdmin }) => {
		await loginAsAdmin();
	});

	test('full refund is sent to Checkout.com and the order becomes refunded', async ({ page, request }) => {
		const { order, payment } = await createPaidOrder(request, { capture: true });
		await waitForCkoStatus(payment.id, ['Captured']);

		await refundFromAdmin(page, order.id, order.total);

		expect(orderRefunds(order.id).map(Number)).toEqual([Number(order.total)]);
		expect(getOrder(order.id).status).toBe('refunded');
		await waitForCkoStatus(payment.id, ['Refunded']);
	});

	test('partial refund is sent to Checkout.com and the order stays open', async ({ page, request }) => {
		const { order, payment } = await createPaidOrder(request, { capture: true });
		await waitForCkoStatus(payment.id, ['Captured']);

		await refundFromAdmin(page, order.id, '2.00');

		expect(orderRefunds(order.id).map(Number)).toEqual([2]);
		expect(getOrder(order.id).status).not.toBe('refunded');
		await waitForCkoStatus(payment.id, ['Partially Refunded']);
	});

	test('a refund created in code (REST/programmatic) refunds the requested amount', async ({ request }) => {
		// Regression: refund_payment() used to read only $_POST['refund_amount'] (admin screen).
		const { order, payment } = await createPaidOrder(request, { capture: true });
		await waitForCkoStatus(payment.id, ['Captured']);

		const result = wpEvalJson<{ ok: boolean; error?: string }>(`
			$r = wc_create_refund( array( 'order_id' => ${order.id}, 'amount' => '3.00', 'refund_payment' => true ) );
			return is_wp_error( $r ) ? array( 'ok' => false, 'error' => $r->get_error_message() ) : array( 'ok' => true );
		`);
		expect(result.ok, result.error).toBe(true);
		await waitForCkoStatus(payment.id, ['Partially Refunded'], 30_000);
		expect(orderRefunds(order.id).map(Number), 'WooCommerce recorded the requested amount').toEqual([3]);
	});
});

async function refundFromAdmin(page: Page, orderId: number, amount: string): Promise<void> {
	const editUrl = wpEvalJson<string>(`return \\Automattic\\WooCommerce\\Utilities\\OrderUtil::get_order_admin_edit_url( ${orderId} );`);
	await page.goto(editUrl);
	await dismissAdminModals(page);
	await page.locator('button.refund-items').click();
	await page.fill('#refund_amount', amount);
	page.once('dialog', (d) => d.accept());
	await page.locator('button.do-api-refund').click();
	// WooCommerce reloads the order screen once the gateway confirms the refund.
	await expect(page.locator('.refund .refund_by, tr.refund').first(), 'refund line shown on the order').toBeVisible({ timeout: 60_000 });
	expect((await getCkoPayment(getOrder(orderId).ckoPaymentId)).id).toBeTruthy();
}

/** WooCommerce admin can open full-screen modals (e.g. the order-milestone celebration). */
async function dismissAdminModals(page: Page): Promise<void> {
	const dismiss = page.getByRole('button', { name: /don.t show again/i }).or(page.getByRole('link', { name: /don.t show again/i }));
	if (await dismiss.first().isVisible({ timeout: 3_000 }).catch(() => false)) {
		await dismiss.first().click();
		await expect(dismiss.first()).toBeHidden();
	}
}
