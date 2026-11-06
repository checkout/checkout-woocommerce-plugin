import { test, expect } from '../../src/fixtures';
import { wpEvalJson } from '../../src/support/wp-cli';

/*
 * Classic card gateway: return-path, logging and admin/checkout output checks that run on the
 * store itself (no card entry needed).
 */

test.describe('Classic gateway hardening', () => {
	test('callback with a malformed cko-session-id redirects to the store without a lookup', async ({ request, baseURL }) => {
		for (const id of ['bad', '../../payments', 'pay_short', 'sid_aaaaaaaaaaaaaaaaaaaaaaaaaa/../x']) {
			const res = await request.get(`/?wc-api=wc_checkoutcom_callback&cko-session-id=${encodeURIComponent(id)}`, { maxRedirects: 0 });
			expect(res.status(), `callback redirects for "${id}"`).toBe(302);
			const location = new URL(res.headers()['location'] ?? '', baseURL);
			expect(location.host, 'redirect stays on the store').toBe(new URL(baseURL ?? '').host);
		}
	});

	test('session id format check accepts Checkout.com ids only', async () => {
		const results = wpEvalJson<Record<string, boolean>>(`
			$ids = array( 'pay_aaaaaaaaaaaaaaaaaaaaaaaaaa', 'sid_aaaaaaaaaaaaaaaaaaaaaaaaaa', 'bad', 'pay_aaaa', 'pay_aaaaaaaaaaaaaaaaaaaaaaaaaa/x', 'tok_aaaaaaaaaaaaaaaaaaaaaaaaaa' );
			$out = array();
			foreach ( $ids as $id ) {
				$out[ $id ] = WC_Checkoutcom_Api_Request::is_valid_session_id( $id );
			}
			$out['verify_session_error'] = ! empty( WC_Checkoutcom_Api_Request::verify_session( '../bad' )['error'] );
			return $out;
		`);
		expect(results).toEqual({
			pay_aaaaaaaaaaaaaaaaaaaaaaaaaa: true,
			sid_aaaaaaaaaaaaaaaaaaaaaaaaaa: true,
			bad: false,
			pay_aaaa: false,
			'pay_aaaaaaaaaaaaaaaaaaaaaaaaaa/x': false,
			tok_aaaaaaaaaaaaaaaaaaaaaaaaaa: false,
			verify_session_error: true,
		});
	});
});
