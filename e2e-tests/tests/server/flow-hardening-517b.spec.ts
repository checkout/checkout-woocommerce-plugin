import { test, expect } from '../../src/fixtures';
import { PRODUCTS } from '../../src/support/test-data';
import { wpEvalFileJson } from '../../src/support/wp-cli';

test.describe('Flow 3DS return: block checkout draft order', () => {
	test('a draft order is used only for a payment this session submitted', () => {
		const result = wpEvalFileJson<{ foreign: boolean; own: boolean; ownStatus: string; reused: boolean }>(`
			wc_load_cart();
			WC()->cart->empty_cart();
			WC()->cart->add_to_cart( wc_get_product_id_by_sku( ${JSON.stringify(PRODUCTS.virtual.sku)} ), 1 );
			WC()->cart->calculate_totals();

			$draft = wc_create_order();
			$draft->set_status( 'checkout-draft' );
			$draft->save();
			WC()->session->set( 'store_api_draft_order', $draft->get_id() );

			$gateway = new WC_Gateway_Checkout_Com_Flow();
			$adopt   = new ReflectionMethod( $gateway, 'adopt_store_api_draft_order' );
			$adopt->setAccessible( true );
			$details = array( 'id' => 'pay_' . str_repeat( 'b', 26 ), 'amount' => 100, 'approved' => true );

			// A payment this session never submitted.
			WC()->session->set( 'cko_flow_session_payment_ids', array() );
			$foreign = $adopt->invoke( $gateway, 'pay_' . str_repeat( 'a', 26 ), $details, '' );

			// This session's own payment.
			$own_id = 'pay_' . str_repeat( 'b', 26 );
			WC()->session->set( 'cko_flow_session_payment_ids', array( $own_id ) );
			$own = $adopt->invoke( $gateway, $own_id, $details, '' );
			$own_status = $own instanceof WC_Order ? $own->get_status() : '';

			// The same payment cannot be applied again.
			$reused = $adopt->invoke( $gateway, $own_id, $details, '' );

			$draft->delete( true );
			WC()->cart->empty_cart();
			return array(
				'foreign'   => $foreign instanceof WC_Order,
				'own'       => $own instanceof WC_Order,
				'ownStatus' => $own_status,
				'reused'    => $reused instanceof WC_Order,
			);
		`);
		expect(result.foreign, 'a payment from another session is not applied').toBe(false);
		expect(result.own, "this session's payment is applied").toBe(true);
		expect(result.ownStatus).toBe('pending');
		expect(result.reused, 'a payment is applied at most once').toBe(false);
	});
});

test.describe('Flow 3DS return: unmatched payment', () => {
	test('a payment whose reference names an existing order is not voided', () => {
		const result = wpEvalFileJson<{ voidCalls: number }>(`
			$order = wc_create_order();
			$order->save();
			$voids = 0;
			add_filter( 'pre_http_request', function ( $pre, $args, $url ) use ( &$voids ) {
				if ( false !== strpos( (string) $url, '/voids' ) ) {
					$voids++;
					return array( 'headers' => array(), 'body' => '{}', 'response' => array( 'code' => 202, 'message' => 'Accepted' ), 'cookies' => array(), 'filename' => null );
				}
				return $pre;
			}, 10, 3 );
			add_filter( 'wp_redirect', function () {
				throw new RuntimeException( 'cko-e2e-redirect' );
			} );
			$gateway = new WC_Gateway_Checkout_Com_Flow();
			$reject  = new ReflectionMethod( $gateway, 'reject_unmatched_3ds_return' );
			$reject->setAccessible( true );
			try {
				$reject->invoke( $gateway, 'pay_' . str_repeat( 'c', 26 ), array( 'approved' => true, 'reference' => (string) $order->get_id() ) );
			} catch ( RuntimeException $e ) {
				// Redirect reached.
			}
			$order->delete( true );
			return array( 'voidCalls' => $voids );
		`);
		expect(result.voidCalls).toBe(0);
	});
});
