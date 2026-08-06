#!/usr/bin/env node

import fs from 'node:fs';
import path from 'node:path';
import { execFileSync, spawnSync } from 'node:child_process';

const rootDir = process.cwd();
const packageJsonPath = path.join(rootDir, 'package.json');
const pluginFile = 'connector-for-newspack-newsletters-and-listmonk.php';
const packageJson = JSON.parse(fs.readFileSync(packageJsonPath, 'utf8'));
const pluginSlug = packageJson.name;
const version = packageJson.version;
const artifactsDir = path.join(rootDir, 'artifacts');
const releaseWorkDir = path.join(artifactsDir, 'release');
const distDir = path.join(releaseWorkDir, pluginSlug);
const zipPath = path.join(artifactsDir, `${pluginSlug}-${version}.zip`);

const restSchemaResources = [
	'listmonk-settings',
	'newsletter-preview',
	'newsletter-sync',
	'campaign-analytics',
];

const runtimePaths = [
	pluginFile,
	'uninstall.php',
	'inc',
	'build',
	'README.md',
	'CHANGELOG.md',
	'readme.txt',
	'LICENSE',
	'docs/SETUP.md',
	'docs/PRIVACY.md',
	'docs/STAGING-CHECKLIST.md',
	'docs/WEBHOOK-POLICY.md',
	'docs/COMPATIBILITY.md',
	'docs/METHOD-MAPPING.md',
];

const requiredFiles = [
	pluginFile,
	'uninstall.php',
	'inc/bootstrap.php',
	'inc/compat.php',
	'inc/options.php',
	'inc/uninstall.php',
	'inc/admin/settings-page.php',
	'inc/listmonk/class-listmonk-client.php',
	'inc/provider/class-listmonk-controller.php',
	'inc/provider/class-listmonk-provider.php',
	'inc/render/class-plain-text-builder.php',
	'inc/render/class-raw-html-builder.php',
	'inc/rest/listmonk-settings.php',
	'inc/rest/newsletter-preview.php',
	'inc/rest/newsletter-sync.php',
	'inc/rest/campaign-analytics.php',
	'build/blocks-manifest.php',
	'build/admin-views/index.js',
	'build/admin-views/index.asset.php',
	'build/admin-views/style-index.css',
	'build/editor-plugins/index.js',
	'build/editor-plugins/index.asset.php',
	'build/editor-plugins/style-index.css',
	'README.md',
	'CHANGELOG.md',
	'readme.txt',
	'LICENSE',
	'docs/SETUP.md',
	'docs/PRIVACY.md',
	'docs/STAGING-CHECKLIST.md',
	'docs/WEBHOOK-POLICY.md',
];

const requiredRestSchemaFiles = restSchemaResources.flatMap((resource) => {
	const schemaDir = path.join(rootDir, 'src/rest', resource, 'api-schemas');
	if (!fs.existsSync(schemaDir)) {
		return [];
	}

	return fs
		.readdirSync(schemaDir)
		.filter((fileName) => fileName.endsWith('.schema.json'))
		.map((fileName) => `src/rest/${resource}/api-schemas/${fileName}`);
});

const forbiddenZipPatterns = [
	/^connector-for-newspack-newsletters-and-listmonk\/node_modules\//,
	/^connector-for-newspack-newsletters-and-listmonk\/vendor\//,
	/^connector-for-newspack-newsletters-and-listmonk\/src\//,
	/^connector-for-newspack-newsletters-and-listmonk\/tests\//,
	/^connector-for-newspack-newsletters-and-listmonk\/scripts\//,
	/^connector-for-newspack-newsletters-and-listmonk\/artifacts\//,
	/^connector-for-newspack-newsletters-and-listmonk\/\.git(?:\/|$)/,
	/^connector-for-newspack-newsletters-and-listmonk\/\.env(?:\.|$)/,
	/^connector-for-newspack-newsletters-and-listmonk\/\.listmonk\.env$/,
	/^connector-for-newspack-newsletters-and-listmonk\/\.wp-env(?:\.|\/|$)/,
	/^connector-for-newspack-newsletters-and-listmonk\/.*\/\.gitkeep$/,
	/^connector-for-newspack-newsletters-and-listmonk\/docker-compose\.listmonk\.yml$/,
	/^connector-for-newspack-newsletters-and-listmonk\/playwright\.config\.js$/,
	/^connector-for-newspack-newsletters-and-listmonk\/phpunit\.xml\.dist$/,
	/^connector-for-newspack-newsletters-and-listmonk\/composer\.(?:json|lock)$/,
	/^connector-for-newspack-newsletters-and-listmonk\/package\.json$/,
	/^connector-for-newspack-newsletters-and-listmonk\/pnpm-lock\.yaml$/,
	/^connector-for-newspack-newsletters-and-listmonk\/tsconfig\.json$/,
	/^connector-for-newspack-newsletters-and-listmonk\/webpack\.config\.js$/,
];

function logStep(message) {
	console.log(`\n> ${message}`);
}

function run(command, args, options = {}) {
	const result = spawnSync(command, args, {
		cwd: options.cwd ?? rootDir,
		encoding: 'utf8',
		stdio: options.stdio ?? 'inherit',
	});

	if (result.error) {
		throw result.error;
	}

	if (result.status !== 0) {
		throw new Error(`${command} ${args.join(' ')} failed with exit code ${result.status}`);
	}

	return result;
}

function readPluginFile() {
	return fs.readFileSync(path.join(rootDir, pluginFile), 'utf8');
}

function assertVersionSync() {
	const pluginSource = readPluginFile();
	const headerMatch = pluginSource.match(/^\s*\*\s*Version:\s*([^\s]+)/m);
	const constantMatch = pluginSource.match(
		/define\(\s*'NEWSPACK_LISTMONK_CONNECTOR_VERSION'\s*,\s*'([^']+)'\s*\)/
	);

	if (!headerMatch) {
		throw new Error('Unable to find plugin header Version.');
	}
	if (!constantMatch) {
		throw new Error('Unable to find NEWSPACK_LISTMONK_CONNECTOR_VERSION.');
	}

	const pluginHeaderVersion = headerMatch[1];
	const pluginConstantVersion = constantMatch[1];
	const versions = [version, pluginHeaderVersion, pluginConstantVersion];
	if (new Set(versions).size !== 1) {
		throw new Error(
			`Version mismatch: package=${version}, header=${pluginHeaderVersion}, constant=${pluginConstantVersion}`
		);
	}
}

function assertSourceFilesExist() {
	for (const relativePath of [...requiredFiles, ...requiredRestSchemaFiles]) {
		const fullPath = path.join(rootDir, relativePath);
		if (!fs.existsSync(fullPath)) {
			throw new Error(`Missing required release file: ${relativePath}`);
		}
	}
}

function copyRuntimeFiles() {
	fs.rmSync(releaseWorkDir, { force: true, recursive: true });
	fs.mkdirSync(distDir, { recursive: true });

	for (const relativePath of runtimePaths) {
		const sourcePath = path.join(rootDir, relativePath);
		const targetPath = path.join(distDir, relativePath);
		if (!fs.existsSync(sourcePath)) {
			throw new Error(`Release source path does not exist: ${relativePath}`);
		}

		fs.mkdirSync(path.dirname(targetPath), { recursive: true });
		fs.cpSync(sourcePath, targetPath, { recursive: true });
	}
}

function copyRestSchemas() {
	for (const relativePath of requiredRestSchemaFiles) {
		const sourcePath = path.join(rootDir, relativePath);
		const [, , resource, , fileName] = relativePath.split('/');
		const targetPath = path.join(distDir, 'inc/rest-schemas', resource, fileName);

		fs.mkdirSync(path.dirname(targetPath), { recursive: true });
		fs.copyFileSync(sourcePath, targetPath);
	}
}

function collectFiles(dir) {
	const entries = fs.readdirSync(dir, { withFileTypes: true });
	const files = [];
	for (const entry of entries) {
		const fullPath = path.join(dir, entry.name);
		if (entry.isDirectory()) {
			files.push(...collectFiles(fullPath));
		} else if (entry.isFile()) {
			files.push(fullPath);
		}
	}
	return files;
}

function lintPhpFiles() {
	const phpFiles = collectFiles(distDir).filter((file) => file.endsWith('.php'));
	for (const phpFile of phpFiles) {
		execFileSync('php', ['-l', phpFile], {
			cwd: rootDir,
			stdio: 'pipe',
		});
	}
}

function createZip() {
	fs.mkdirSync(artifactsDir, { recursive: true });
	fs.rmSync(zipPath, { force: true });
	run('zip', ['-qr', zipPath, pluginSlug], {
		cwd: releaseWorkDir,
		stdio: 'inherit',
	});
}

function listZipEntries() {
	const output = execFileSync('unzip', ['-Z1', zipPath], {
		cwd: rootDir,
		encoding: 'utf8',
	});

	return output
		.split(/\r?\n/)
		.map((line) => line.trim())
		.filter(Boolean);
}

function assertZipContents() {
	const entries = listZipEntries();
	const entrySet = new Set(entries);
	const requiredZipEntries = requiredFiles
		.map((relativePath) => `${pluginSlug}/${relativePath}`)
		.concat(
			requiredRestSchemaFiles.map((relativePath) => {
				const [, , resource, , fileName] = relativePath.split('/');
				return `${pluginSlug}/inc/rest-schemas/${resource}/${fileName}`;
			})
		);

	for (const requiredEntry of requiredZipEntries) {
		if (!entrySet.has(requiredEntry)) {
			throw new Error(`Release zip is missing required entry: ${requiredEntry}`);
		}
	}

	for (const entry of entries) {
		if (forbiddenZipPatterns.some((pattern) => pattern.test(entry))) {
			throw new Error(`Release zip contains development-only entry: ${entry}`);
		}
	}
}

function main() {
	logStep('Validating synchronized release metadata');
	run('node', ['scripts/sync-release-version.mjs', '--check']);

	logStep('Building production assets');
	run('pnpm', ['run', 'build']);

	logStep('Validating release inputs');
	assertVersionSync();
	assertSourceFilesExist();

	logStep('Preparing release directory');
	copyRuntimeFiles();
	copyRestSchemas();
	lintPhpFiles();

	logStep('Creating plugin zip');
	createZip();
	assertZipContents();

	console.log(`\nCreated ${path.relative(rootDir, zipPath)}`);
}

main();
