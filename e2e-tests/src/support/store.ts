import { wpEvalJson } from './wp-cli';

export interface StoreOrder {
	id: number;
	status: string;
	total: string;
	currency: string;
	paymentMethod: string;
	transactionId: string;
	ckoPaymentId: string;
	ckoFlowPaymentId: string;
	customerId: number;
	notes: string[];
}

export function productIdBySku(sku: string): number {
	const id = wpEvalJson<number>(`return wc_get_product_id_by_sku( ${JSON.stringify(sku)} );`);
	if (!id) {
		throw new Error(`Product with SKU "${sku}" not found - run "npm run env:seed".`);
	}
	return id;
}

export function getOrder(orderId: number): StoreOrder {
	const order = wpEvalJson<StoreOrder | null>(`
		$o = wc_get_order( ${Number(orderId)} );
		if ( ! $o ) { return null; }
		return array(
			'id'               => $o->get_id(),
			'status'           => $o->get_status(),
			'total'            => $o->get_total(),
			'currency'         => $o->get_currency(),
			'paymentMethod'    => $o->get_payment_method(),
			'transactionId'    => (string) $o->get_transaction_id(),
			'ckoPaymentId'     => (string) $o->get_meta( '_cko_payment_id' ),
			'ckoFlowPaymentId' => (string) $o->get_meta( '_cko_flow_payment_id' ),
			'customerId'       => $o->get_customer_id(),
			'notes'            => array_map( function ( $n ) { return wp_strip_all_tags( $n->content ); }, wc_get_order_notes( array( 'order_id' => $o->get_id() ) ) ),
		);
	`);
	if (!order) {
		throw new Error(`Order ${orderId} not found in the store.`);
	}
	return order;
}

export function customerIdByLogin(login: string): number {
	return wpEvalJson<number>(`$u = get_user_by( 'login', ${JSON.stringify(login)} ); return $u ? $u->ID : 0;`);
}
