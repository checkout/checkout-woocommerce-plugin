import { execFileSync } from 'node:child_process';
import path from 'node:path';

const REPO_ROOT = path.resolve(__dirname, '../../..');
const WP_ENV = path.join(REPO_ROOT, 'e2e-tests/node_modules/.bin/wp-env');

/** Run a WP-CLI command inside the wp-env "cli" container and return its stdout. */
export function wp(...args: string[]): string {
	try {
		return execFileSync(WP_ENV, ['run', 'cli', 'wp', ...args], {
			cwd: REPO_ROOT,
			encoding: 'utf8',
			stdio: ['ignore', 'pipe', 'pipe'],
		}).trim();
	} catch (e) {
		const err = e as { stderr?: string; stdout?: string };
		// Surface WP-CLI's own error (PHP fatal, unknown command, ...) instead of just "Command failed".
		const detail = `${err.stdout ?? ''}\n${err.stderr ?? ''}`
			.split('\n')
			.filter((l) => /error|fatal|warning|exception/i.test(l) && !/^\s*ℹ/.test(l))
			.slice(0, 5)
			.join('\n');
		throw new Error(`wp ${args[0]} failed:\n${detail || (e as Error).message}`);
	}
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
