import fs from 'node:fs';
import { spawnSync } from 'node:child_process';

const pnpmBin = process.platform === 'win32' ? 'pnpm.cmd' : 'pnpm';

export function loadDotEnv(filePath = '.env') {
	if (!fs.existsSync(filePath)) {
		return;
	}

	const content = fs.readFileSync(filePath, 'utf8');
	for (const rawLine of content.split(/\r?\n/)) {
		const line = rawLine.trim();
		if (!line || line.startsWith('#')) {
			continue;
		}

		const equalsIndex = line.indexOf('=');
		if (equalsIndex < 1) {
			continue;
		}

		const key = line.slice(0, equalsIndex).trim();
		let value = line.slice(equalsIndex + 1).trim();
		if (
			(value.startsWith('"') && value.endsWith('"')) ||
			(value.startsWith("'") && value.endsWith("'"))
		) {
			value = value.slice(1, -1);
		}

		if (!(key in process.env)) {
			process.env[key] = value;
		}
	}
}

export function runWp(args, options = {}) {
	const result = spawnSync(
		pnpmBin,
		['exec', 'wp-env', 'run', 'cli', 'wp', ...args],
		{
			cwd: process.cwd(),
			encoding: 'utf8',
			input: options.input,
			stdio: ['pipe', 'pipe', 'pipe'],
		}
	);

	if (result.error) {
		throw result.error;
	}

	if (!options.allowFailure && result.status !== 0) {
		throw new Error(
			[
				`WP-CLI command failed: wp ${args.join(' ')}`,
				result.stdout.trim(),
				result.stderr.trim(),
			]
				.filter(Boolean)
				.join('\n')
		);
	}

	return result;
}

export function wpEval(code, options = {}) {
	return runWp(['eval', code], options);
}

export function logStep(message) {
	console.log(`\n> ${message}`);
}

export function printCommandOutput(result) {
	const output = [result.stdout.trim(), result.stderr.trim()]
		.filter(Boolean)
		.join('\n');
	if (output) {
		console.log(output);
	}
}

export function resolvePluginSlug(candidates) {
	const result = runWp(['plugin', 'list', '--field=name']);
	const installed = result.stdout
		.split(/\r?\n/)
		.map((line) => line.trim())
		.filter(Boolean);

	for (const candidate of candidates) {
		if (installed.includes(candidate)) {
			return candidate;
		}
	}

	throw new Error(
		`Unable to find any expected plugin slug. Expected one of: ${candidates.join(
			', '
		)}. Installed: ${installed.join(', ')}`
	);
}

export function requireEnv(name) {
	const value = process.env[name];
	if (!value || value.trim() === '') {
		throw new Error(`Missing required environment variable: ${name}`);
	}
	return value.trim();
}

export function parseListIds(rawValue) {
	const ids = String(rawValue)
		.split(/[,\s]+/)
		.map((value) => Number.parseInt(value, 10))
		.filter((value) => Number.isInteger(value) && value > 0);

	if (ids.length === 0) {
		throw new Error('LISTMONK_DEFAULT_LIST_IDS must contain at least one positive integer.');
	}

	return [...new Set(ids)];
}

export function phpString(value) {
	return `'${String(value).replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;
}
