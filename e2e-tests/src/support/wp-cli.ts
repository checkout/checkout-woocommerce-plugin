import { execFileSync } from 'node:child_process';
import path from 'node:path';

const REPO_ROOT = path.resolve(__dirname, '../../..');
const WP_ENV = path.join(REPO_ROOT, 'e2e-tests/node_modules/.bin/wp-env');

/** Run a WP-CLI command inside the wp-env "cli" container and return its stdout. */
export function wp(...args: string[]): string {
	return execFileSync(WP_ENV, ['run', 'cli', 'wp', ...args], {
		cwd: REPO_ROOT,
		encoding: 'utf8',
		stdio: ['ignore', 'pipe', 'pipe'],
	}).trim();
}

/** Run PHP inside WordPress and parse the JSON it echoes. */
export function wpEvalJson<T>(php: string): T {
	const out = wp('eval', `echo wp_json_encode( (function () { ${php} })() );`);
	// wp-env can print notices before the payload; the JSON is the last line.
	const last = out.split('\n').filter(Boolean).pop() ?? 'null';
	return JSON.parse(last) as T;
}

/** Run one of the bin/e2e helper scripts from the repo root. */
export function runScript(script: string, ...args: string[]): void {
	execFileSync(path.join(REPO_ROOT, 'bin/e2e', script), args, { cwd: REPO_ROOT, stdio: 'inherit' });
}
