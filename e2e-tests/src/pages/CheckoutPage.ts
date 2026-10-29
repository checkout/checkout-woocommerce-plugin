import { expect, type Page } from '@playwright/test';
import type { Address } from '../support/test-data';
import { FlowComponent } from './FlowComponent';
import { ThreeDSChallenge } from './ThreeDSChallenge';

export type CheckoutLayout = 'classic' | 'blocks';

/** What every test needs from a checkout page, whichever layout the store uses. */
export interface CheckoutPage {
	readonly flow: FlowComponent;
	goto(): Promise<void>;
	fillBilling(address: Address, options?: { includeEmail?: boolean }): Promise<void>;
	selectFlow(): Promise<void>;
	placeOrder(): Promise<number>;
}

export function checkoutPageFor(layout: CheckoutLayout, page: Page): CheckoutPage {
	return layout === 'blocks' ? new BlocksCheckoutPage(page) : new ClassicCheckoutPage(page);
}

/**
 * Shared: after Place Order, wait for the order-received page. If the Checkout.com
 * sandbox raises a 3DS challenge first, complete it (the plugin forces 3DS on, so
 * challenges are part of the normal flow).
 */
async function waitForOrderReceived(page: Page): Promise<number> {
	const challenge = new ThreeDSChallenge(page);
	const deadline = Date.now() + 90_000;
	let challengeCompleted = false;

	while (!/\/order-received\/\d+/.test(page.url())) {
		if (Date.now() > deadline) {
			throw new Error(`Order was not received within 90s: ${await describeStuckCheckout(page)}`);
		}
		if (!challengeCompleted && (await challenge.find())) {
			await challenge.complete();
			challengeCompleted = true;
		}
		await page.waitForTimeout(500);
	}

	await page.waitForLoadState('load');
	return Number(page.url().match(/\/order-received\/(\d+)/)![1]);
}

/** Classic checkout is settled when no AJAX and no block overlay have been seen for 1.5s. */
async function waitForClassicCheckoutToSettle(page: Page): Promise<void> {
	const deadline = Date.now() + 30_000;
	let quietSince = 0;
	while (Date.now() < deadline) {
		const busy = await page.evaluate(
			() => ((window as any).jQuery?.active ?? 0) > 0 || document.querySelector('.blockUI') !== null,
		);
		if (busy) {
			quietSince = 0;
		} else if (!quietSince) {
			quietSince = Date.now();
		} else if (Date.now() - quietSince >= 1500) {
			return;
		}
		await page.waitForTimeout(200);
	}
	throw new Error('Classic checkout did not settle within 30s (update_checkout still running)');
}

/** What was on screen when an order failed to complete - makes timeouts self-explanatory. */
async function describeStuckCheckout(page: Page): Promise<string> {
	const notices = await page
		.locator('.woocommerce-error, .woocommerce-NoticeGroup, .wc-block-components-notice-banner')
		.allInnerTexts()
		.catch(() => []);
	const ready = await page.locator('#flow-container').getAttribute('data-cko-flow-ready').catch(() => null);
	return `url=${page.url()} flowReady=${ready} notices=${JSON.stringify(notices.map((n) => n.trim()).filter(Boolean))}`;
}

/** Classic [woocommerce_checkout] shortcode checkout. */
export class ClassicCheckoutPage implements CheckoutPage {
	readonly flow: FlowComponent;

	constructor(private readonly page: Page) {
		this.flow = new FlowComponent(page);
	}

	async goto(): Promise<void> {
		await this.page.goto('/checkout/');
		await expect(this.page.locator('form.woocommerce-checkout')).toBeVisible();
	}

	async fillBilling(a: Address, { includeEmail = true } = {}): Promise<void> {
		// Country/state default to the store base (US / CA), set by the seed.
		await this.page.fill('#billing_first_name', a.firstName);
		await this.page.fill('#billing_last_name', a.lastName);
		await this.page.fill('#billing_address_1', a.address1);
		await this.page.fill('#billing_city', a.city);
		await this.page.fill('#billing_postcode', a.postcode);
		await this.page.fill('#billing_phone', a.phone);
		if (includeEmail) {
			await this.page.fill('#billing_email', a.email);
		}
		// Address changes trigger update_checkout ~1s later, and the plugin rebuilds Flow with a
		// new payment session when that happens. Entering the card before it settles races the
		// rebuild (order created on one session, payment submitted on another), so wait it out.
		await this.page.locator('#billing_email').press('Tab');
		await waitForClassicCheckoutToSettle(this.page);
		await this.flow.waitUntilReady();
	}

	async selectFlow(): Promise<void> {
		const radio = this.page.locator('#payment_method_wc_checkout_com_flow');
		await expect(radio, 'Checkout.com Flow gateway not offered').toHaveCount(1);
		if (await radio.isVisible()) {
			await radio.check();
		}
	}

	async placeOrder(): Promise<number> {
		await this.flow.expectCardStillEntered();
		await this.page.locator('#place_order').click();
		return waitForOrderReceived(this.page);
	}
}

/** WooCommerce Checkout block. */
export class BlocksCheckoutPage implements CheckoutPage {
	readonly flow: FlowComponent;

	constructor(private readonly page: Page) {
		this.flow = new FlowComponent(page);
	}

	async goto(): Promise<void> {
		await this.page.goto('/checkout/');
		await expect(this.page.locator('.wc-block-checkout__form')).toBeVisible();
	}

	async fillBilling(a: Address, { includeEmail = true } = {}): Promise<void> {
		if (includeEmail) {
			await this.page.fill('#email', a.email);
		}
		const form = this.page.locator('#billing, #shipping').first();
		// A returning customer's saved address is shown as a collapsed card - open it first.
		const edit = this.page.getByRole('button', { name: /edit (billing|shipping) address/i }).first();
		if (await edit.isVisible()) {
			await edit.click();
		}
		await form.locator('[id$="-first_name"]').fill(a.firstName);
		await form.locator('[id$="-last_name"]').fill(a.lastName);
		await form.locator('[id$="-address_1"]').fill(a.address1);
		await form.locator('[id$="-city"]').fill(a.city);
		await form.locator('[id$="-postcode"]').fill(a.postcode);
		const phone = form.locator('[id$="-phone"]');
		if (await phone.count()) {
			await phone.fill(a.phone);
		}
	}

	async selectFlow(): Promise<void> {
		// With a single gateway the block renders no radio; with several, pick Flow.
		const radio = this.page.locator('input[name="radio-control-wc-payment-method-options"][value="wc_checkout_com_flow"]');
		if (await radio.count()) {
			await radio.check();
		}
		await expect(this.page.locator('#flow-container'), 'Checkout.com Flow not rendered').toBeVisible();
	}

	async placeOrder(): Promise<number> {
		await this.flow.expectCardStillEntered();
		await this.page.locator('.wc-block-components-checkout-place-order-button').click();
		return waitForOrderReceived(this.page);
	}
}
