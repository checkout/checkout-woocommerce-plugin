import { test, expect } from '../../src/fixtures';
import { expectOrderPaidByCheckoutCom } from '../../src/support/assertions';
import { customerTokens, deleteCustomerTokens, getOrder } from '../../src/support/store';
import { APPROVED_CARD, DECLINED_CARD, billingAddress } from '../../src/support/test-data';

const CUSTOMER = 'e2e_customer';

/*
 * Saved cards (logged-in customer), once per checkout layout. The My Account tests do not
 * depend on the checkout layout, so they only run in the classic project.
 */
test.describe('Saved cards', () => {
	test.beforeEach(async ({ loginAsCustomer }) => {
		deleteCustomerTokens(CUSTOMER);
		await loginAsCustomer();
	});

	test('card is NOT saved when "save card" is left unticked', async ({ checkout, addToCart }) => {
		await addToCart('virtual');
		await checkout.goto();
		await checkout.fillBilling(billingAddress('nosave'), { includeEmail: false });
		await checkout.selectFlow();
		await checkout.flow.fillCard(APPROVED_CARD);
		const orderId = await checkout.placeOrder();

		await expectOrderPaidByCheckoutCom(orderId);
		expect(customerTokens(CUSTOMER), 'no card saved without consent').toEqual([]);
	});

	test('card is saved when "save card" is ticked', async ({ checkout, addToCart }) => {
		await saveCardViaCheckout(checkout, addToCart);

		const tokens = customerTokens(CUSTOMER);
		expect(tokens, 'exactly one saved card').toHaveLength(1);
		expect(tokens[0]).toMatchObject({ gateway: 'wc_checkout_com_flow', last4: '4242' });
	});

	test('customer pays a later order with the saved card', async ({ checkout, addToCart }) => {
		await saveCardViaCheckout(checkout, addToCart);
		const [token] = customerTokens(CUSTOMER);

		await addToCart('virtual');
		await checkout.goto();
		await checkout.selectSavedCard(token.id);
		const orderId = await checkout.placeOrder();
		const order = await expectOrderPaidByCheckoutCom(orderId);
		expect(order.transactionId, 'transaction id recorded').toMatch(/^act_/);

		if (process.env.E2E_LIVE_WEBHOOKS === '1') {
			// The real capture webhook must be able to match the order now.
			await expect
				.poll(() => getOrder(orderId).status, { message: 'capture webhook moved the saved-card order to processing', timeout: 120_000, intervals: [3000] })
				.toBe('processing');
		}
	});

	test('My Account: adding an approved card saves it', async ({ page, checkoutLayout }) => {
		test.skip(checkoutLayout !== 'classic', 'My Account does not depend on the checkout layout');
		await page.goto('/my-account/add-payment-method/');
		await addCardOnMyAccount(page, APPROVED_CARD);

		await page.waitForURL(/\/payment-methods\//, { timeout: 60_000 });
		await expect(page.getByText(/payment method successfully added/i).first()).toBeVisible();
		expect(customerTokens(CUSTOMER)).toEqual([expect.objectContaining({ gateway: 'wc_checkout_com_flow', last4: '4242' })]);
	});

	test('My Account: a card that fails verification shows an error and is not saved', async ({ page, checkoutLayout }) => {
		test.skip(checkoutLayout !== 'classic', 'My Account does not depend on the checkout layout');
		await page.goto('/my-account/add-payment-method/');
		await addCardOnMyAccount(page, DECLINED_CARD);

		await expect(page.locator('.woocommerce-error, .woocommerce-NoticeGroup, [role="alert"]').getByText(/declined|failed|not approved|unable|could not|insufficient/i).first(), 'shopper told the card was not added').toBeVisible({ timeout: 60_000 });
		expect(customerTokens(CUSTOMER), 'declined card not saved').toEqual([]);
	});
});

async function saveCardViaCheckout(
	checkout: import('../../src/pages/CheckoutPage').CheckoutPage,
	addToCart: (p: 'virtual' | 'physical') => Promise<void>,
): Promise<void> {
	await addToCart('virtual');
	await checkout.goto();
	await checkout.fillBilling(billingAddress('save'), { includeEmail: false });
	await checkout.selectFlow();
	await checkout.flow.fillCard(APPROVED_CARD);
	await checkout.tickSaveCard();
	await expectOrderPaidByCheckoutCom(await checkout.placeOrder());
}

async function addCardOnMyAccount(page: import('@playwright/test').Page, card: typeof APPROVED_CARD): Promise<void> {
	const { FlowComponent } = await import('../../src/pages/FlowComponent');
	const { ThreeDSChallenge } = await import('../../src/pages/ThreeDSChallenge');
	const flow = new FlowComponent(page);
	await flow.fillCard(card);
	await page.locator('#place_order').click();
	const challenge = new ThreeDSChallenge(page);
	const deadline = Date.now() + 30_000;
	while (Date.now() < deadline) {
		if (await challenge.find()) {
			await challenge.complete();
			return;
		}
		if (!page.url().includes('add-payment-method')) {
			return;
		}
		await page.waitForTimeout(500);
	}
}
