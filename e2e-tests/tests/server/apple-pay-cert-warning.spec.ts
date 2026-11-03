import { test, expect } from '../../src/fixtures';
import { wpEvalJson } from '../../src/support/wp-cli';

/*
 * Apple Pay merchant-identity key/certificate files inside the web root (outside the plugin's
 * protected uploads/checkout-com-apple-pay/ directory) must raise an admin warning on the Apple
 * Pay settings screen. Dummy files only - no real key is written.
 */
const SETTINGS_URL = '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=wc_checkout_com_apple_pay';
const WARNING = 'inside your website\'s public folder';

function writeUploadsFile(relative: string): string {
	return wpEvalJson<string>(`
		$path = wp_upload_dir()['basedir'] . '/' . ${JSON.stringify(relative)};
		wp_mkdir_p( dirname( $path ) );
		file_put_contents( $path, 'e2e dummy - not a real key' );
		return $path;
	`);
}

function removeFile(path: string): void {
	wpEvalJson(`@unlink( ${JSON.stringify(path)} ); return true;`);
}

/** Set the Apple Pay key path setting; returns a restore function. */
function setKeyPath(path: string): () => void {
	const previous = wpEvalJson<string>(`
		$s = get_option( 'woocommerce_wc_checkout_com_apple_pay_settings', array() );
		$prev = isset( $s['ckocom_apple_key'] ) ? (string) $s['ckocom_apple_key'] : '';
		$s['ckocom_apple_key'] = ${JSON.stringify(path)};
		update_option( 'woocommerce_wc_checkout_com_apple_pay_settings', $s );
		return $prev;
	`);
	return () => {
		wpEvalJson(`
			$s = get_option( 'woocommerce_wc_checkout_com_apple_pay_settings', array() );
			$s['ckocom_apple_key'] = ${JSON.stringify(previous)};
			update_option( 'woocommerce_wc_checkout_com_apple_pay_settings', $s );
			return true;
		`);
	};
}

test.describe('Apple Pay certificate files in the web root', () => {
	test('a key left in the uploads root is reported', async ({ page, loginAsAdmin }) => {
		const file = writeUploadsFile('certificate_sandbox.key');
		try {
			await loginAsAdmin();
			await page.goto(SETTINGS_URL);
			const notice = page.locator('.notice-error', { hasText: WARNING });
			await expect(notice).toBeVisible();
			await expect(notice).toContainText('certificate_sandbox.key');
		} finally {
			removeFile(file);
		}
	});

	test('a configured key path inside the web root is reported, one in the protected directory is not', async ({ page, loginAsAdmin }) => {
		const exposed = writeUploadsFile('2026/11/merchant-id.key');
		const protectedFile = writeUploadsFile('checkout-com-apple-pay/merchant-id.key');
		let restore = setKeyPath(exposed);
		try {
			await loginAsAdmin();
			await page.goto(SETTINGS_URL);
			await expect(page.locator('.notice-error', { hasText: WARNING })).toContainText('merchant-id.key');

			restore();
			restore = setKeyPath(protectedFile);
			await page.goto(SETTINGS_URL);
			await expect(page.locator('.notice-error', { hasText: WARNING })).toHaveCount(0);
		} finally {
			restore();
			removeFile(exposed);
			removeFile(protectedFile);
		}
	});
});
