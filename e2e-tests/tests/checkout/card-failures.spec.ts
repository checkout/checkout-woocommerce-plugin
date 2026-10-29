import { test, expect } from '../../src/fixtures';
import { clickAnyPlaceOrder, waitForOrderReceived } from '../../src/pages/CheckoutPage';
import { ThreeDSChallenge } from '../../src/pages/ThreeDSChallenge';
import { authorisedOrdersSince, expectOrderPaidByCheckoutCom, expectPaymentRejected } from '../../src/support/assertions';
import { latestOrderId } from '../../src/support/store';
import {
	APPROVED_CARD,
	DECLINED_CARD,
	EXPIRED_CARD,
	INVALID_NUMBER_CARD,
	SHORT_CVV_CARD,
	billingAddress,
	type Card,
} from '../../src/support/test-data';

const DECLINE_MESSAGE = /payment failed|declined|not approved|insufficient/i;
const INVALID_FORM_MESSAGE = /not valid|incomplete|invalid|expired|check your payment details/i;

/*
 * Failure paths, once per checkout layout. Each test proves three things: the shopper is
 * told, never reaches the thank-you page, and no order is left authorised.
 */
test.describe('Flow card failures', () => {
	test('declined card shows an error and leaves no authorised order', async ({ page, checkout, addToCart }) => {
		const before = latestOrderId();
		await addToCart('virtual');
		await checkout.goto();
		await checkout.fillBilling(billingAddress('declined'));
		await checkout.selectFlow();
		await checkout.flow.fillCard(DECLINED_CARD);
		await checkout.clickPlaceOrder();
		await completeChallengeIfShown(page);

		await expectPaymentRejected(page, before, DECLINE_MESSAGE);
	});

	test('failed 3DS authentication shows an error and leaves no authorised order', async ({ page, checkout, addToCart }) => {
		const before = latestOrderId();
		await addToCart('virtual');
		await checkout.goto();
		await checkout.fillBilling(billingAddress('3ds-fail'));
		await checkout.selectFlow();
		await checkout.flow.fillCard(APPROVED_CARD);
		await checkout.clickPlaceOrder();

		const challenge = new ThreeDSChallenge(page);
		await expect.poll(async () => !!(await challenge.find()), { message: '3DS challenge shown', timeout: 60_000 }).toBe(true);
		await challenge.fail();

		await expectPaymentRejected(page, before, DECLINE_MESSAGE);
	});

	for (const [label, card] of [
		['invalid card number (Luhn)', INVALID_NUMBER_CARD],
		['expired card', EXPIRED_CARD],
		['short CVV', SHORT_CVV_CARD],
	] as Array<[string, Card]>) {
		test(`${label} is rejected before any payment is attempted`, async ({ page, checkout, addToCart }) => {
			const before = latestOrderId();
			await addToCart('virtual');
			await checkout.goto();
			await checkout.fillBilling(billingAddress('invalid'));
			await checkout.selectFlow();
			await checkout.flow.fillCardUnchecked(card);
			await checkout.clickPlaceOrder();

			await expect(page.getByText(INVALID_FORM_MESSAGE).first(), 'shopper told the card details are invalid').toBeVisible();
			await page.waitForTimeout(5000);
			expect(page.url()).not.toMatch(/order-received/);
			expect(authorisedOrdersSince(before), 'no order was authorised').toEqual([]);
		});
	}

	test('retry after a decline completes the order with a good card', async ({ page, checkout, addToCart }) => {
		const before = latestOrderId();
		await addToCart('virtual');
		await checkout.goto();
		await checkout.fillBilling(billingAddress('retry'));
		await checkout.selectFlow();
		await checkout.flow.fillCard(DECLINED_CARD);
		await checkout.clickPlaceOrder();
		await completeChallengeIfShown(page);
		await expectPaymentRejected(page, before, DECLINE_MESSAGE);

		// Retry on whatever page the shopper was left on (classic: order-pay; Blocks: checkout).
		await checkout.flow.fillCard(APPROVED_CARD);
		await clickAnyPlaceOrder(page);
		const orderId = await waitForOrderReceived(page);

		await expectOrderPaidByCheckoutCom(orderId);
		expect(authorisedOrdersSince(before).map((o) => o.id), 'exactly one paid order').toEqual([orderId]);
	});
});

/** A declined card may still be challenged first; get through the challenge so the decline happens. */
async function completeChallengeIfShown(page: import('@playwright/test').Page): Promise<void> {
	const challenge = new ThreeDSChallenge(page);
	const deadline = Date.now() + 20_000;
	while (Date.now() < deadline) {
		if (await challenge.find()) {
			await challenge.complete();
			return;
		}
		if (await page.getByText(DECLINE_MESSAGE).first().isVisible().catch(() => false)) {
			return;
		}
		await page.waitForTimeout(500);
	}
}
