import { defineConfig, devices } from '@playwright/test';
import type { Options } from './src/fixtures';
import { env } from './src/env';

/**
 * One project per checkout layout. The store has a single checkout page; a worker-scoped
 * fixture switches it to the project's layout when the worker starts (see src/fixtures.ts).
 * workers: 1 - payment tests share one store and change order state, so they run serially,
 * and a worker only ever serves one project, so the layout is always right for it.
 */
export default defineConfig<Options>({
	testDir: './tests',
	fullyParallel: false,
	workers: 1,
	forbidOnly: !!process.env.CI,
	retries: process.env.CI ? 1 : 0,
	timeout: 120_000,
	expect: { timeout: 15_000 },
	reporter: process.env.CI
		? [['list'], ['html', { open: 'never' }], ['github']]
		: [['list'], ['html', { open: 'never' }]],
	use: {
		baseURL: env.baseUrl,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		video: 'retain-on-failure',
		actionTimeout: 20_000,
		navigationTimeout: 45_000,
		locale: 'en-US',
	},
	projects: [
		{
			name: 'classic',
			testDir: './tests/checkout',
			use: { ...devices['Desktop Chrome'], checkoutLayout: 'classic' },
		},
		{
			name: 'blocks',
			testDir: './tests/checkout',
			use: { ...devices['Desktop Chrome'], checkoutLayout: 'blocks' },
		},
	],
});
