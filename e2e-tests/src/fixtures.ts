import { test as base, expect } from '@playwright/test';
import { checkoutPageFor, type CheckoutLayout, type CheckoutPage } from './pages/CheckoutPage';
import { productIdBySku } from './support/store';
import { PRODUCTS } from './support/test-data';
import { runScript } from './support/wp-cli';
import { env } from './env';

export type Options = { checkoutLayout: CheckoutLayout };

type WorkerFixtures = {
	checkoutLayout: CheckoutLayout;
	/** Switches the store's checkout page to this project's layout once per worker. */
	store: { productIds: Record<keyof typeof PRODUCTS, number> };
};

type TestFixtures = {
	checkout: CheckoutPage;
	addToCart: (product: keyof typeof PRODUCTS) => Promise<void>;
	loginAsCustomer: () => Promise<void>;
};

export const test = base.extend<TestFixtures, WorkerFixtures>({
	checkoutLayout: ['classic', { scope: 'worker', option: true }],

	store: [
		async ({ checkoutLayout }, use) => {
			runScript('checkout-layout.sh', checkoutLayout);
			await use({
				productIds: {
					virtual: productIdBySku(PRODUCTS.virtual.sku),
					physical: productIdBySku(PRODUCTS.physical.sku),
				},
			});
		},
		{ scope: 'worker', auto: true },
	],

	checkout: async ({ page, checkoutLayout }, use) => {
		await use(checkoutPageFor(checkoutLayout, page));
	},

	addToCart: async ({ page, store }, use) => {
		await use(async (product) => {
			await page.goto(`/?add-to-cart=${store.productIds[product]}`);
			await page.goto('/cart/');
			await expect(page.getByText(/E2E (Virtual|Physical) Product/).first()).toBeVisible();
		});
	},

	loginAsCustomer: async ({ page }, use) => {
		await use(async () => {
			await page.goto('/my-account/');
			await page.fill('#username', env.customer.username);
			await page.fill('#password', env.customer.password);
			await page.locator('button[name="login"]').click();
			await expect(page.locator('.woocommerce-MyAccount-navigation')).toBeVisible();
		});
	},
});

export { expect };
