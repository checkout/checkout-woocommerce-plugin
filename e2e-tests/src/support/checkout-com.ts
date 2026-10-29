import { env } from '../env';

export interface CkoPayment {
	id: string;
	status: string;
	approved?: boolean;
	amount: number;
	currency: string;
	reference?: string;
	metadata?: Record<string, unknown>;
}

/** Fetch a payment from the Checkout.com sandbox API - the source of truth for what was charged. */
export async function getCkoPayment(paymentId: string): Promise<CkoPayment> {
	const res = await fetch(`${env.ckoApiBase}/payments/${encodeURIComponent(paymentId)}`, {
		headers: { Authorization: `Bearer ${env.ckoSecretKey}` },
	});
	if (!res.ok) {
		throw new Error(`Checkout.com GET /payments/${paymentId} returned HTTP ${res.status}`);
	}
	return (await res.json()) as CkoPayment;
}
