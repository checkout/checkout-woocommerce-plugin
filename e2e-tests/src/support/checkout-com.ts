import crypto from 'node:crypto';
import { env } from '../env';

export interface CkoPayment {
	id: string;
	status: string;
	approved?: boolean;
	amount: number;
	currency: string;
	reference?: string;
	metadata?: Record<string, unknown>;
	actions?: Array<{ id: string; type: string }>;
	response_code?: string;
	response_summary?: string;
}

/** Checkout.com sandbox test cards (verified against this sandbox, see README). */
export const SANDBOX_CARDS = {
	approved: '4242424242424242',
	declined: '4544249167673670', // 20051 Insufficient Funds
} as const;

async function cko(path: string, init: RequestInit & { key?: string } = {}): Promise<Response> {
	const { key = env.ckoSecretKey, ...rest } = init;
	return fetch(`${env.ckoApiBase}${path}`, {
		...rest,
		headers: { Authorization: `Bearer ${key}`, 'Content-Type': 'application/json', ...(rest.headers || {}) },
	});
}

/** Fetch a payment from the Checkout.com sandbox API - the source of truth for what was charged. */
export async function getCkoPayment(paymentId: string): Promise<CkoPayment> {
	const res = await cko(`/payments/${encodeURIComponent(paymentId)}`);
	if (!res.ok) {
		throw new Error(`Checkout.com GET /payments/${paymentId} returned HTTP ${res.status}`);
	}
	return (await res.json()) as CkoPayment;
}

/**
 * Create a real sandbox card payment server-side (no browser, no 3DS).
 * Used to build paid orders quickly and to craft mismatching payments for security tests.
 */
export async function createCkoPayment(opts: {
	amount: number;
	currency: string;
	reference: string;
	card?: string;
	capture?: boolean;
	metadata?: Record<string, string>;
}): Promise<CkoPayment> {
	const tokenRes = await cko('/tokens', {
		method: 'POST',
		key: process.env.CKO_PUBLIC_KEY || '',
		body: JSON.stringify({ type: 'card', number: opts.card ?? SANDBOX_CARDS.approved, expiry_month: 12, expiry_year: 2030, cvv: '100' }),
	});
	const token = ((await tokenRes.json()) as { token?: string }).token;
	if (!token) {
		throw new Error(`Checkout.com tokenisation failed (HTTP ${tokenRes.status})`);
	}
	const body: Record<string, unknown> = {
		source: { type: 'token', token },
		amount: opts.amount,
		currency: opts.currency,
		reference: opts.reference,
		capture: opts.capture ?? false,
	};
	if (opts.metadata) {
		body.metadata = opts.metadata;
	}
	if (process.env.CKO_PROCESSING_CHANNEL_ID) {
		body.processing_channel_id = process.env.CKO_PROCESSING_CHANNEL_ID;
	}
	const res = await cko('/payments', { method: 'POST', body: JSON.stringify(body) });
	const payment = (await res.json()) as CkoPayment;
	if (!payment.id) {
		throw new Error(`Checkout.com payment creation failed (HTTP ${res.status}): ${JSON.stringify(payment).slice(0, 200)}`);
	}
	return payment;
}

/** Capture an authorised sandbox payment. */
export async function captureCkoPayment(paymentId: string): Promise<void> {
	const res = await cko(`/payments/${paymentId}/captures`, { method: 'POST', body: '{}' });
	if (res.status !== 202) {
		throw new Error(`Capture of ${paymentId} returned HTTP ${res.status}`);
	}
}

/** Poll Checkout.com until the payment reaches one of the given statuses. */
export async function waitForCkoStatus(paymentId: string, statuses: string[], timeoutMs = 90_000): Promise<CkoPayment> {
	const deadline = Date.now() + timeoutMs;
	let last: CkoPayment | undefined;
	while (Date.now() < deadline) {
		last = await getCkoPayment(paymentId);
		if (statuses.includes(last.status)) {
			return last;
		}
		await new Promise((r) => setTimeout(r, 3000));
	}
	throw new Error(`Payment ${paymentId} did not reach ${statuses.join('/')} (last: ${last?.status})`);
}

/** Sign a webhook body exactly as Checkout.com does for NAS accounts: HMAC-SHA256 keyed with "Bearer <sk>". */
export function signWebhook(rawBody: string, secretKey = env.ckoSecretKey): string {
	return crypto.createHmac('sha256', `Bearer ${secretKey}`).update(rawBody).digest('hex');
}

/** Build a Checkout.com-shaped webhook event for a payment. */
export function webhookEvent(
	type: string,
	payment: { id: string; amount: number; currency: string; reference?: string; metadata?: Record<string, unknown> },
	overrides: Record<string, unknown> = {},
): Record<string, unknown> {
	return {
		id: `evt_${crypto.randomBytes(13).toString('hex')}`,
		type,
		created_on: new Date().toISOString(),
		data: {
			id: payment.id,
			action_id: `act_${crypto.randomBytes(13).toString('hex')}`,
			amount: payment.amount,
			currency: payment.currency,
			reference: payment.reference,
			metadata: payment.metadata ?? {},
			response_code: '10000',
			response_summary: 'Approved',
			...overrides,
		},
	};
}
