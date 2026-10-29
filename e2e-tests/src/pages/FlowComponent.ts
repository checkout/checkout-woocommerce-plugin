import { expect, type FrameLocator, type Page } from '@playwright/test';
import type { Card } from '../support/test-data';

/**
 * The Checkout.com Flow web component. Each card field lives in its own cross-origin
 * iframe (name prefix + data-testid). Every step fails loudly - a missing field is a
 * test failure, never a warning.
 */
export class FlowComponent {
	constructor(private readonly page: Page) {}

	private get container() {
		return this.page.locator('#flow-container');
	}

	private field(prefix: string): FrameLocator {
		return this.page.frameLocator(`iframe[name^="${prefix}__"]`);
	}

	/** Wait for the plugin's readiness signal (set from the SDK's onReady callback). */
	async waitUntilReady(): Promise<void> {
		await expect(this.container, 'Flow component did not report ready').toHaveAttribute('data-cko-flow-ready', '1', {
			timeout: 60_000,
		});
	}

	async fillCard(card: Card): Promise<void> {
		await this.waitUntilReady();

		// Cardholder name is optional (plugin setting), so only fill it when rendered.
		if ((await this.page.locator('iframe[name^="cardholder-name__"]').count()) > 0) {
			await this.type('cardholder-name', card.name);
		}
		await this.type('card-number', card.number);
		await this.type('card-expiry-date', card.expiry.replace('/', ''));
		await this.type('card-cvv', card.cvv);
	}

	/**
	 * Fails if the component was rebuilt after the card was typed (a rebuilt component has
	 * empty fields and a new payment session) - catches the update_checkout race.
	 */
	async expectCardStillEntered(): Promise<void> {
		await expect(this.container, 'Flow component is not ready').toHaveAttribute('data-cko-flow-ready', '1');
		await expect(this.field('card-number').getByTestId('card-number'), 'card number was cleared by a Flow rebuild').not.toHaveValue('');
	}

	private async type(testId: string, value: string): Promise<void> {
		const input = this.field(testId).getByTestId(testId);
		await expect(input, `Flow field "${testId}" not rendered`).toBeVisible();
		await input.click();
		await input.pressSequentially(value, { delay: 25 });
		await expect(input, `Flow field "${testId}" is empty after typing`).not.toHaveValue('');
	}
}
