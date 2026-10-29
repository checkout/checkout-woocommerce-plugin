import { test, expect } from '../../src/fixtures';
import { expectOrderPaidByCheckoutCom } from '../../src/support/assertions';
import { customerIdByLogin } from '../../src/support/store';
import { APPROVED_CARD, billingAddress } from '../../src/support/test-data';

/*
 * Runs once per checkout layout (Playwright projects "classic" and "blocks").
 */
test.describe('Flow card payment', () => {
	test('guest pays with an approved card @smoke', async ({ page, checkout, addToCart }) => {
		await addToCart('virtual');
		await checkout.goto();
		await checkout.fillBilling(billingAddress('guest'));
		await checkout.selectFlow();
		await checkout.flow.fillCard(APPROVED_CARD);

		const orderId = await checkout.placeOrder();

		await expect(page.getByText(/order (has been )?received|thank you/i).first()).toBeVisible();
		const order = await expectOrderPaidByCheckoutCom(orderId);
		expect(order.customerId, 'guest order has no customer').toBe(0);
	});

	test('logged-in customer pays with an approved card @smoke', async ({ page, checkout, addToCart, loginAsCustomer }) => {
		await loginAsCustomer();
		await addToCart('virtual');
		await checkout.goto();
		await checkout.fillBilling(billingAddress('customer'), { includeEmail: false });
		await checkout.selectFlow();
		await checkout.flow.fillCard(APPROVED_CARD);

		const orderId = await checkout.placeOrder();

		await expect(page.getByText(/order (has been )?received|thank you/i).first()).toBeVisible();
		const order = await expectOrderPaidByCheckoutCom(orderId);
		expect(order.customerId, 'order belongs to the logged-in customer').toBe(customerIdByLogin('e2e_customer'));
	});
});
