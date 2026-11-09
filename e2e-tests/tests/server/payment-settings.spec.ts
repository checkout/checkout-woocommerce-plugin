import { test, expect } from '../../src/fixtures';
import { FlowComponent } from '../../src/pages/FlowComponent';
import { changeSessionRequestInBrowser, sessionCapture } from '../../src/support/http-capture';
import { createPendingOrder } from '../../src/support/orders';
import { wpEvalJson } from '../../src/support/wp-cli';

/*
 * Payment settings the store decides itself: the 3DS options of a Flow payment session, the
 * Google Pay Express endpoints (only while Express is offered), and the Classic card MOTO
 * decision (made on the order being paid).
 */

const CARD_3DS_OPTIONS = ['ckocom_card_threed', 'ckocom_card_notheed', 'ckocom_card_3ds_challenge_indicator'];

test.describe('Payment settings decided by the store', () => {
	test('Flow session 3DS options come from the store settings', async ({ page }) => {
		const saved = wpEvalJson<Record<string, string | false>>(`
			$keys = ${JSON.stringify(CARD_3DS_OPTIONS)};
			return array_combine( $keys, array_map( 'get_option', $keys ) );
		`);
		wpEvalJson(`
			update_option( 'ckocom_card_threed', '1' );
			update_option( 'ckocom_card_notheed', '0' );
			update_option( 'ckocom_card_3ds_challenge_indicator', 'challenge_requested' );
			return true;
		`);
		const order = createPendingOrder();
		const payUrl = wpEvalJson<string>(`return wc_get_order( ${order.id} )->get_checkout_payment_url();`);
		// The checkout script sends different 3DS options; the store's settings must win.
		await changeSessionRequestInBrowser(page, {
			set: { '3ds': { enabled: false, attempt_n3d: true, challenge_indicator: 'no_challenge_requested' } },
		});

		sessionCapture.start();
		try {
			await page.goto(payUrl);
			await new FlowComponent(page).waitUntilReady();
			await expect.poll(() => sessionCapture.creates().length, { timeout: 30_000 }).toBeGreaterThan(0);
			const sent = sessionCapture.creates().pop();
			expect(sent?.['3ds']).toMatchObject({ enabled: true, attempt_n3d: false, challenge_indicator: 'challenge_requested' });
		} finally {
			sessionCapture.stop();
			wpEvalJson(`
				foreach ( json_decode( ${JSON.stringify(JSON.stringify(saved))}, true ) as $key => $value ) {
					false === $value ? delete_option( $key ) : update_option( $key, $value );
				}
				return true;
			`);
		}
	});

	test('Google Pay Express endpoints answer only while Express is offered', async ({ request }) => {
		const original = wpEvalJson<string>(`
			$s = get_option( 'woocommerce_wc_checkout_com_google_pay_settings', array() );
			return isset( $s['google_pay_express'] ) ? $s['google_pay_express'] : '';
		`);
		const setExpress = (value: string) =>
			wpEvalJson(`
				$s = (array) get_option( 'woocommerce_wc_checkout_com_google_pay_settings', array() );
				$s['google_pay_express'] = ${JSON.stringify(value)};
				update_option( 'woocommerce_wc_checkout_com_google_pay_settings', $s );
				return true;
			`);
		const endpoint = '/?wc-api=CKO_Google_Pay_Woocommerce&cko_google_pay_action=express_get_cart_total';

		try {
			setExpress('no');
			expect((await request.get(endpoint)).status(), 'refused while Express is off').toBe(403);

			setExpress('yes');
			expect((await request.get(endpoint)).status(), 'handled while Express is on').not.toBe(403);
		} finally {
			setExpress(original);
		}
	});

	test('Classic card MOTO is decided on the order being paid', async () => {
		// payment_type the Classic card request gets for `paid`, with the order-pay query string
		// naming `named` (as the order-pay page URL does).
		const paymentType = (paid: number, named: number) =>
			wpEvalJson<string>(`
				$paid = wc_get_order( ${paid} );
				$_GET  = array( 'pay_for_order' => 'true', 'key' => $paid->get_order_key(), 'order_id' => ${named} );
				$_POST = array( 'payment_method' => 'wc_checkout_com_cards' );
				$build = new ReflectionMethod( 'WC_Checkoutcom_Api_Request', 'get_request_param' );
				$build->setAccessible( true );
				$payment = $build->invoke( null, $paid, 'tok_e2e_classic_moto' );
				return is_object( $payment ) ? (string) $payment->payment_type : 'error';
			`);
		const adminGuestOrder = wpEvalJson<number>(`
			$o = wc_get_order( ${createPendingOrder().id} );
			$o->set_created_via( 'admin' );
			$o->save();
			return $o->get_id();
		`);
		const checkoutOrder = wpEvalJson<number>(`
			$o = wc_get_order( ${createPendingOrder().id} );
			$o->set_created_via( 'checkout' );
			$o->save();
			return $o->get_id();
		`);

		expect(paymentType(adminGuestOrder, adminGuestOrder), 'an admin order for a guest is MOTO').toBe('MOTO');
		expect(paymentType(checkoutOrder, adminGuestOrder), 'another order named in the URL does not make it MOTO').toBe('Regular');
	});
});
