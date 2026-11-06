import { test, expect } from '../../src/fixtures';
import { PRODUCTS } from '../../src/support/test-data';
import { wpEvalJson } from '../../src/support/wp-cli';

/*
 * Express checkout (PayPal, Apple Pay, Google Pay) order handling: the order built from the cart
 * carries the same lines and total as the cart.
 */

type ExpressOrderLines = {
	created: boolean;
	cartTotal: string;
	orderTotal: string;
	shipping: string[];
	fees: string[];
	coupons: string[];
	products: number;
};

const EXPRESS_GATEWAYS = [
	{ name: 'PayPal', className: 'WC_Gateway_Checkout_Com_PayPal', args: '' },
	{ name: 'Apple Pay', className: 'WC_Gateway_Checkout_Com_Apple_Pay', args: "'', null, null" },
	{ name: 'Google Pay', className: 'WC_Gateway_Checkout_Com_Google_Pay', args: "'', null" },
] as const;

/**
 * Build a cart with a physical product, a fee, a fixed coupon and a chosen shipping rate, then
 * create the express order for it with the given gateway class. The order is deleted afterwards.
 */
function buildExpressOrder(className: string, args: string): ExpressOrderLines {
	return wpEvalJson<ExpressOrderLines>(`
		wc_load_cart();
		WC()->cart->empty_cart();
		WC()->customer->set_shipping_country( 'US' );
		WC()->customer->set_shipping_state( 'CA' );
		WC()->customer->set_shipping_postcode( '94103' );
		WC()->customer->set_billing_country( 'US' );

		add_action( 'woocommerce_cart_calculate_fees', function ( $cart ) {
			$cart->add_fee( 'E2E handling fee', 2.5 );
		} );
		add_filter( 'woocommerce_package_rates', function () {
			return array( 'e2e_rate:1' => new WC_Shipping_Rate( 'e2e_rate:1', 'E2E rate', 5, array(), 'flat_rate', 1 ) );
		} );

		$code = 'e2e-express-' . wp_generate_password( 6, false );
		$coupon = new WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( 'fixed_cart' );
		$coupon->set_amount( 1 );
		$coupon->save();

		WC()->cart->add_to_cart( wc_get_product_id_by_sku( ${JSON.stringify(PRODUCTS.physical.sku)} ), 1 );
		WC()->cart->apply_coupon( $code );
		WC()->session->set( 'chosen_shipping_methods', array( 'e2e_rate:1' ) );
		WC()->cart->calculate_totals();
		$cart_total = wc_format_decimal( WC()->cart->get_total( 'edit' ), wc_get_price_decimals() );

		$gateway = new ${className}();
		$create  = new ReflectionMethod( $gateway, 'create_express_order_from_cart' );
		$create->setAccessible( true );
		$order = $create->invoke( $gateway${args ? `, ${args}` : ''} );

		$result = array( 'created' => $order instanceof WC_Order, 'cartTotal' => $cart_total, 'orderTotal' => '', 'shipping' => array(), 'fees' => array(), 'coupons' => array(), 'products' => 0 );
		if ( $order instanceof WC_Order ) {
			$order = wc_get_order( $order->get_id() );
			$result['orderTotal'] = wc_format_decimal( $order->get_total(), wc_get_price_decimals() );
			$result['products']   = count( $order->get_items( 'line_item' ) );
			foreach ( $order->get_items( 'shipping' ) as $item ) {
				$result['shipping'][] = $item->get_method_id() . ':' . $item->get_instance_id();
			}
			foreach ( $order->get_items( 'fee' ) as $item ) {
				$result['fees'][] = $item->get_name();
			}
			foreach ( $order->get_items( 'coupon' ) as $item ) {
				$result['coupons'][] = $item->get_code();
			}
			$order->delete( true );
		}

		WC()->cart->empty_cart();
		wp_delete_post( $coupon->get_id(), true );
		$result['coupons'] = array_map( function ( $c ) use ( $code ) { return $c === $code ? 'applied' : $c; }, $result['coupons'] );
		return $result;
	`);
}

test.describe('Express checkout orders', () => {
	for (const gateway of EXPRESS_GATEWAYS) {
		test(`${gateway.name} Express order carries the cart's shipping, fee and coupon lines`, async () => {
			const lines = buildExpressOrder(gateway.className, gateway.args);

			expect(lines.created, 'order created').toBe(true);
			expect(lines.products).toBe(1);
			expect(lines.shipping).toEqual(['flat_rate:1']);
			expect(lines.fees).toEqual(['E2E handling fee']);
			expect(lines.coupons).toEqual(['applied']);
			expect(lines.orderTotal, 'order total matches the cart total').toBe(lines.cartTotal);
		});
	}
});
