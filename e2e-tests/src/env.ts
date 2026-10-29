import 'dotenv/config';

function required(name: string): string {
	const value = process.env[name];
	if (!value) {
		throw new Error(`Missing ${name}. Copy e2e-tests/.env.example to e2e-tests/.env and fill it in.`);
	}
	return value;
}

/** Checkout.com merchant subdomain: first 8 chars after "cli_" (mirrors the plugin's helper). */
function apiHost(clientId: string): string {
	const raw = clientId.trim().toLowerCase().replace(/^cli_/, '').slice(0, 8);
	return /^(?:pl-)?[a-z0-9]+$/.test(raw) ? `https://${raw}.api.sandbox.checkout.com` : 'https://api.sandbox.checkout.com';
}

export const env = {
	baseUrl: process.env.BASE_URL || 'http://localhost:8892',
	customer: { username: 'e2e_customer', password: process.env.E2E_CUSTOMER_PASSWORD || 'password' },
	get ckoSecretKey(): string {
		return required('CKO_SECRET_KEY');
	},
	get ckoApiBase(): string {
		return apiHost(process.env.CKO_CLIENT_ID || '');
	},
};
