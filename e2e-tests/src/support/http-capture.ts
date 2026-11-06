import type { Page } from '@playwright/test';
import { wpEvalJson } from './wp-cli';

/**
 * Records what the store sends to Checkout.com's /payment-sessions endpoints, using the
 * e2e-only mu-plugin in e2e-tests/mu-plugins. Call stop() in a finally block.
 */
export type CapturedRequest = { url: string; body: Record<string, any> | null };

export const sessionCapture = {
	start(): void {
		wpEvalJson(`delete_option( 'cko_e2e_captured_requests' ); update_option( 'cko_e2e_capture_http', 1, false ); return true;`);
	},
	stop(): void {
		wpEvalJson(`delete_option( 'cko_e2e_capture_http' ); delete_option( 'cko_e2e_captured_requests' ); return true;`);
	},
	/** Bodies of the payment-session create calls (POST /payment-sessions), oldest first. */
	creates(): Record<string, any>[] {
		const all = wpEvalJson<CapturedRequest[] | false>(`return get_option( 'cko_e2e_captured_requests', array() );`) || [];
		return all.filter((r) => /\/payment-sessions\/?$/.test(r.url) && r.body).map((r) => r.body as Record<string, any>);
	},
};

/**
 * Changes the payment session request the checkout script sends to the store, before it is
 * sent. `set` replaces top-level fields; `successUrlQuery` replaces query parameters of
 * success_url. Used to check the store builds these values itself.
 */
export async function changeSessionRequestInBrowser(
	page: Page,
	change: { set?: Record<string, unknown>; successUrlQuery?: Record<string, string> },
): Promise<void> {
	await page.addInitScript((change) => {
		const append = FormData.prototype.append;
		FormData.prototype.append = function (this: FormData, name: string, value: any, ...rest: any[]) {
			if (name === 'payment_session_request' && typeof value === 'string') {
				const request = JSON.parse(value);
				Object.assign(request, change.set || {});
				if (change.successUrlQuery && request.success_url) {
					const url = new URL(request.success_url, window.location.origin);
					for (const [k, v] of Object.entries(change.successUrlQuery)) url.searchParams.set(k, v);
					request.success_url = url.toString();
				}
				value = JSON.stringify(request);
			}
			return (append as any).call(this, name, value, ...rest);
		} as typeof FormData.prototype.append;
	}, change);
}
