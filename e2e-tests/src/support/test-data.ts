export interface Card {
	number: string;
	expiry: string; // MM/YY
	cvv: string;
	name: string;
}

export interface Address {
	firstName: string;
	lastName: string;
	address1: string;
	city: string;
	postcode: string;
	phone: string;
	email: string;
}

/** Checkout.com sandbox Visa that approves. The plugin forces 3DS on, so the sandbox may
 * challenge it; the checkout page objects complete the challenge automatically. */
export const APPROVED_CARD: Card = { number: '4242424242424242', expiry: '12/30', cvv: '100', name: 'E2E Tester' };

export const PRODUCTS = {
	virtual: { sku: 'e2e-virtual', price: '12.34' },
	physical: { sku: 'e2e-physical', price: '20.00' },
} as const;

/** A US/CA billing address (the store's base location) with an email unique to this test. */
export function billingAddress(tag: string): Address {
	const unique = `${Date.now()}${Math.floor(Math.random() * 1000)}`;
	return {
		firstName: 'E2E',
		lastName: 'Tester',
		address1: '1 Market Street',
		city: 'San Francisco',
		postcode: '94103',
		phone: '4155550100',
		email: `e2e+${tag}-${unique}@example.com`,
	};
}

/** Order total in minor units, as Checkout.com stores it (USD = 2 decimals). */
export function toMinorUnits(total: string): number {
	return Math.round(parseFloat(total) * 100);
}
