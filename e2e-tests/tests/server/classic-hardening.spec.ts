import { test, expect } from '../../src/fixtures';
import { createCkoPayment } from '../../src/support/checkout-com';
import { createPendingOrder } from '../../src/support/orders';
import { getOrder } from '../../src/support/store';
import { wpEvalJson } from '../../src/support/wp-cli';

/*
 * Classic card gateway: return path (some tests create sandbox payments), logging and
 * admin/checkout output checks.
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

	test('callback for a payment whose amount differs from the order does not mark it paid', async ({ request }) => {
		const order = createPendingOrder();
		const payment = await createCkoPayment({
			amount: Math.max(1, order.totalMinor - 100),
			currency: order.currency,
			reference: String(order.id),
			metadata: { order_id: String(order.id) },
		});
		expect(payment.status).toBe('Authorized');

		const res = await request.get(`/?wc-api=wc_checkoutcom_callback&cko-session-id=${payment.id}`, { maxRedirects: 0 });
		expect(res.status(), 'callback redirects').toBe(302);

		const after = getOrder(order.id);
		expect(after.status, 'order is held for review').toBe('on-hold');
		expect(after.notes.join('\n')).toContain('Payment amount or currency does not match the order');
		expect(after.notes.join('\n')).not.toContain('Payment Authorised');
	});

	test('callback for a matching payment still authorises the order', async ({ request }) => {
		const order = createPendingOrder();
		const payment = await createCkoPayment({
			amount: order.totalMinor,
			currency: order.currency,
			reference: String(order.id),
			metadata: { order_id: String(order.id) },
		});
		expect(payment.status).toBe('Authorized');

		const res = await request.get(`/?wc-api=wc_checkoutcom_callback&cko-session-id=${payment.id}`, { maxRedirects: 0 });
		expect(res.status()).toBe(302);
		expect(res.headers()['location']).toContain('order-received');

		const after = getOrder(order.id);
		expect(after.ckoPaymentId).toBe(payment.id);
		expect(after.notes.join('\n')).toContain('Payment Authorised');
	});

	test('logger records an exception summary without its call arguments', async () => {
		const out = wpEvalJson<{ log: string }>(`
			$saved    = get_option( 'cko_file_logging', false );
			$captured = array();
			$capture  = function ( $message ) use ( &$captured ) {
				$captured[] = $message;
				return $message;
			};
			add_filter( 'woocommerce_logger_log_message', $capture, 10, 1 );
			update_option( 'cko_file_logging', 'yes' );
			try {
				$thrower = function ( $secret ) {
					throw new Exception( 'e2e logger message' );
				};
				try {
					$thrower( 'sk_sbox_e2e_hidden_value' );
				} catch ( Exception $e ) {
					WC_Checkoutcom_Utility::logger( 'e2e logger plain', $e );
				}
				$api                = new \\Checkout\\CheckoutApiException( 'e2e api message' );
				$api->error_details = array( 'error_codes' => array( 'e2e_error_code' ), 'token' => 'tok_e2e_hidden_value' );
				WC_Checkoutcom_Utility::logger( 'e2e logger api', $api );
			} finally {
				remove_filter( 'woocommerce_logger_log_message', $capture, 10 );
				false === $saved ? delete_option( 'cko_file_logging' ) : update_option( 'cko_file_logging', $saved );
			}
			return array( 'log' => implode( "\\n", $captured ) );
		`);
		expect(out.log).toContain('e2e logger message');
		expect(out.log).toContain('Exception');
		expect(out.log).not.toContain('sk_sbox_e2e_hidden_value');
		expect(out.log).toContain('e2e api message');
		expect(out.log).toContain('e2e_error_code');
		expect(out.log).not.toContain('tok_e2e_hidden_value');
	});

	test('card icon filter renders only known brands, escaped', async () => {
		const html = wpEvalJson<string>(`
			$keys  = array( 'ckocom_display_icon', 'ckocom_card_icons' );
			$saved = array_combine( $keys, array_map( 'get_option', $keys ) );
			try {
				update_option( 'ckocom_display_icon', '1' );
				update_option( 'ckocom_card_icons', array( 'visa', '"><b id="e2e-icon">', 'mastercard', '../../e2e' ) );
				return (string) apply_filters( 'woocommerce_gateway_icon', '', 'wc_checkout_com_cards' );
			} finally {
				foreach ( $saved as $key => $value ) {
					false === $value ? delete_option( $key ) : update_option( $key, $value );
				}
			}
		`);
		expect(html).toContain('visa.svg');
		expect(html).toContain('mastercard.svg');
		expect(html).not.toContain('e2e-icon');
		expect(html).not.toContain('../../e2e');
		expect(html.match(/<img /g) ?? []).toHaveLength(2);
	});

	test('order list payment id column uses a data attribute and no inline script', async () => {
		const out = wpEvalJson<{ valid: string; other: string }>(`
			if ( ! class_exists( 'WC_Checkoutcom_Order_Columns' ) ) {
				include_once dirname( ( new ReflectionClass( 'WC_Checkoutcom_Utility' ) )->getFileName(), 2 ) . '/admin/class-wc-checkoutcom-order-columns.php';
			}
			$render = function ( $payment_id ) {
				$order = wc_create_order();
				$order->update_meta_data( '_cko_payment_id', $payment_id );
				$order->save();
				ob_start();
				WC_Checkoutcom_Order_Columns::render_order_column_hpos( 'cko_payment_id', $order );
				$html = ob_get_clean();
				$order->delete( true );
				return $html;
			};
			return array(
				'valid' => $render( 'pay_aaaaaaaaaaaaaaaaaaaaaaaaaa' ),
				'other' => $render( "x'); alert(1); ('" ),
			);
		`);
		expect(out.valid).toContain('data-cko-payment-id="pay_aaaaaaaaaaaaaaaaaaaaaaaaaa"');
		expect(out.valid).not.toMatch(/onclick/i);
		expect(out.other).not.toMatch(/onclick/i);
		expect(out.other).not.toContain('data-cko-payment-id');
		expect(out.other).not.toContain("'");
	});
});
