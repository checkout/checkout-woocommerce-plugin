import { test, expect } from '../../src/fixtures';
import type { Page } from '@playwright/test';
import { wp, wpEvalJson } from '../../src/support/wp-cli';

/*
 * Local copy of the WooCommerce QIT "Activation" managed test, which runs on every release:
 * the plugin activates and deactivates cleanly, the store and the plugin's admin pages load
 * without PHP errors, and they keep loading when outgoing HTTP, REST and e-mail fail
 * ("hook resilience", see e2e-tests/mu-plugins/cko-e2e-hook-resilience.php).
 *
 * QIT fails the release on a fatal error or a broken page, and warns on any other PHP error
 * (warning, notice, deprecation). Both fail here, so they are caught before a release.
 */

interface LogState {
	pluginDir: string;
	logFile: string;
	size: number;
}

/** Where PHP errors are logged, the plugin's folder name and the log's current size. */
function logState(): LogState {
	return wpEvalJson<LogState>(`
		$file = is_string( WP_DEBUG_LOG ) ? WP_DEBUG_LOG : WP_CONTENT_DIR . '/debug.log';
		clearstatcache();
		return array(
			'pluginDir' => basename( WC_CHECKOUTCOM_PLUGIN_PATH ),
			'logFile'   => $file,
			'size'      => file_exists( $file ) ? filesize( $file ) : 0,
		);
	`);
}

/** PHP errors logged since `since` that come from this plugin's files. */
function pluginErrorsSince(since: LogState): string[] {
	const lines = wpEvalJson<string[]>(`
		$file = ${JSON.stringify(since.logFile)};
		clearstatcache();
		if ( ! file_exists( $file ) || filesize( $file ) <= ${since.size} ) {
			return array();
		}
		$fh = fopen( $file, 'rb' );
		fseek( $fh, ${since.size} );
		$new = stream_get_contents( $fh );
		fclose( $fh );
		return array_values( array_filter( explode( "\\n", $new ) ) );
	`);
	const ourFiles = `/plugins/${since.pluginDir}/`;
	return lines.filter((l) => /PHP (Fatal error|Parse error|Warning|Notice|Deprecated)/.test(l) && l.includes(ourFiles));
}

/** Store pages, the plugin's settings screens and its admin pages. */
function pagesToVisit(): { front: string[]; admin: string[] } {
	const front = wpEvalJson<string[]>(`
		return array_values( array_unique( array_filter( array(
			home_url( '/' ),
			wc_get_page_permalink( 'shop' ),
			wc_get_page_permalink( 'cart' ),
			wc_get_page_permalink( 'checkout' ),
			wc_get_page_permalink( 'myaccount' ),
			rest_url(),
			rest_url( 'wc/store/v1/cart' ),
		) ) ) );
	`);
	const settings = 'admin.php?page=wc-settings&tab=checkout';
	const admin = [
		'index.php',
		'plugins.php',
		settings,
		...['quick_settings', 'card_settings', 'express_payments', 'orders_settings', 'advanced', 'webhook_queue'].map(
			(screen) => `${settings}&section=wc_checkout_com_cards&screen=${screen}`,
		),
		...['wc_checkout_com_flow', 'wc_checkout_com_apple_pay', 'wc_checkout_com_google_pay', 'wc_checkout_com_paypal', 'wc_checkout_com_alternative_payments'].map(
			(section) => `${settings}&section=${section}`,
		),
		'admin.php?page=checkout-com-logging',
		'admin.php?page=checkout-com-webhook-queue',
		'admin.php?page=checkoutcom-diagnostics',
		'admin.php?page=wc-orders',
		'admin.php?page=wc-status',
	].map((p) => `/wp-admin/${p}`);
	return { front, admin };
}

/** Load each page; return the ones that answered 5xx or showed WordPress's fatal-error screen. */
async function brokenPages(page: Page, urls: string[]): Promise<string[]> {
	const broken: string[] = [];
	for (const url of urls) {
		const response = await page.goto(url, { waitUntil: 'domcontentloaded' });
		const status = response?.status() ?? 0;
		const body = await page.content();
		if (status >= 500 || /There has been a critical error on this website/i.test(body)) {
			broken.push(`${status} ${url}`);
		}
	}
	return broken;
}

test.describe('QIT activation (local) @qit', () => {
	test.describe.configure({ mode: 'serial' });

	test.afterEach(() => {
		wp('option', 'delete', 'cko_e2e_hook_resilience');
	});

	test('the plugin deactivates and activates again without PHP errors', () => {
		const before = logState();
		try {
			wp('plugin', 'deactivate', before.pluginDir);
		} finally {
			wp('plugin', 'activate', before.pluginDir); // always leave the store with the plugin on
		}
		expect(wpEvalJson<boolean>(`return is_plugin_active( ${JSON.stringify(before.pluginDir + '/woocommerce-gateway-checkout-com.php')} );`)).toBe(true);
		expect(pluginErrorsSince(before), 'PHP errors from the plugin while activating').toEqual([]);
	});

	test('store and admin pages load without PHP errors', async ({ page, loginAsAdmin }) => {
		const pages = pagesToVisit();
		const before = logState();

		expect(await brokenPages(page, pages.front), 'store pages').toEqual([]);
		await loginAsAdmin();
		expect(await brokenPages(page, pages.admin), 'admin pages').toEqual([]);

		expect(pluginErrorsSince(before), 'PHP errors from the plugin').toEqual([]);
	});

	test('pages keep working when HTTP, REST and e-mail fail (hook resilience)', async ({ page, loginAsAdmin }) => {
		const pages = pagesToVisit();
		await loginAsAdmin(); // log in before REST authentication is made to fail
		wp('option', 'update', 'cko_e2e_hook_resilience', '1');
		const before = logState();

		const adminBroken = await brokenPages(page, pages.admin);
		await page.context().clearCookies();
		const frontBroken = await brokenPages(page, pages.front);
		wp('eval', "wp_mail( 'qit@example.com', 'QIT resilience', 'test' );");

		expect(adminBroken, 'admin pages with HTTP/REST/e-mail failing').toEqual([]);
		expect(frontBroken, 'store pages with HTTP/REST/e-mail failing').toEqual([]);
		expect(pluginErrorsSince(before), 'PHP errors from the plugin with HTTP/REST/e-mail failing').toEqual([]);
	});
});
