import { expect, type Frame, type Page } from '@playwright/test';

/** Password accepted by the Checkout.com sandbox 3DS2 simulator (shown as its hint). */
export const SANDBOX_3DS_PASSWORD = 'Checkout1!';

/**
 * The Checkout.com sandbox 3DS2 challenge ("SIMULATOR"). It renders inside nested
 * cross-origin iframes whose nesting can change, so the frame is found by its password
 * field rather than by a fixed iframe path.
 */
export class ThreeDSChallenge {
	constructor(private readonly page: Page) {}

	/** The frame holding the challenge, or null if no challenge is on screen. */
	async find(): Promise<Frame | null> {
		for (const frame of this.page.frames()) {
			try {
				if (await frame.locator('input[type="password"]').first().isVisible()) {
					return frame;
				}
			} catch {
				// Frame detached while checking - ignore it.
			}
		}
		return null;
	}

	async complete(password = SANDBOX_3DS_PASSWORD): Promise<void> {
		const frame = await this.find();
		if (!frame) {
			throw new Error('3DS challenge expected but not found');
		}
		await frame.locator('input[type="password"]').first().fill(password);
		const submit = frame.getByRole('button', { name: /continue|submit/i }).first();
		await expect(submit).toBeEnabled();
		await submit.click();
	}
}
