import type { APIRequestContext } from '@playwright/test';
import { signWebhook } from './checkout-com';

export const WEBHOOK_PATH = '/?wc-api=wc_checkoutcom_webhook';

/**
 * POST a webhook to the store the way Checkout.com does: raw JSON body + cko-signature.
 * signature: 'valid' (default), 'missing', or an explicit (e.g. forged) value.
 */
export async function sendWebhook(
	request: APIRequestContext,
	event: Record<string, unknown>,
	signature: 'valid' | 'missing' | string = 'valid',
): Promise<number> {
	const body = JSON.stringify(event);
	const headers: Record<string, string> = { 'Content-Type': 'application/json' };
	if (signature === 'valid') {
		headers['cko-signature'] = signWebhook(body);
	} else if (signature !== 'missing') {
		headers['cko-signature'] = signature;
	}
	const res = await request.post(WEBHOOK_PATH, { data: body, headers, maxRedirects: 0 });
	return res.status();
}
