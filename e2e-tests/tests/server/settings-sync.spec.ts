import { test, expect } from '../../src/fixtures';
import { wpEvalJson } from '../../src/support/wp-cli';

/*
 * WC_Gateway_Checkout_Com_Flow::flow_enabled() runs in the gateway constructor, i.e. on most
 * storefront requests. It must only write the gateway settings when the enabled flags actually
 * need to change - not on every load (performance, and it is how a past bug turned an
 * admin-only DB error into a per-request secret-key leak in the error log).
 */

/** Count writes to the Checkout.com gateway options while constructing the Flow gateway. */
function writesDuringGatewayLoad(setup = ''): { writes: number; cardsEnabled: string; flowEnabled: string } {
	return wpEvalJson(`
		${setup}
		$writes = 0;
		add_filter( 'query', function ( $q ) use ( &$writes ) {
			if ( preg_match( '/^\\s*(INSERT|UPDATE)\\b/i', $q ) && preg_match( '/woocommerce_wc_checkout_com_[a-z_]+_settings/', $q ) ) {
				$writes++;
			}
			return $q;
		} );
		new WC_Gateway_Checkout_Com_Flow();
		$cards = cko_get_raw_option( 'woocommerce_wc_checkout_com_cards_settings' );
		$flow  = cko_get_raw_option( 'woocommerce_wc_checkout_com_flow_settings' );
		return array( 'writes' => $writes, 'cardsEnabled' => (string) ( $cards['enabled'] ?? '' ), 'flowEnabled' => (string) ( $flow['enabled'] ?? '' ) );
	`);
}

test.describe('Settings sync (flow_enabled)', () => {
	test('loading the gateway writes nothing when the enabled flags are already right', () => {
		writesDuringGatewayLoad(); // settle once
		const result = writesDuringGatewayLoad();

		expect(result.writes, 'no settings writes on a normal gateway load').toBe(0);
		expect(result).toMatchObject({ cardsEnabled: 'no', flowEnabled: 'yes' });
	});

	test('loading the gateway corrects the enabled flags when they are wrong', () => {
		const result = writesDuringGatewayLoad(`
			$s = cko_get_raw_option( 'woocommerce_wc_checkout_com_cards_settings' );
			$s['enabled'] = 'yes'; // wrong while checkout mode is Flow
			cko_update_raw_option( 'woocommerce_wc_checkout_com_cards_settings', $s );
		`);

		expect(result.writes, 'the wrong flag is written back').toBeGreaterThan(0);
		expect(result).toMatchObject({ cardsEnabled: 'no', flowEnabled: 'yes' });
	});
});
