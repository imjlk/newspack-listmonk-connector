#!/usr/bin/env node

import fs from 'node:fs';
import path from 'node:path';
import { execFileSync, spawnSync } from 'node:child_process';

const rootDir = process.cwd();
const packageJson = JSON.parse(
	fs.readFileSync( path.join( rootDir, 'package.json' ), 'utf8' )
);
const pluginSlug = packageJson.name;
const version = packageJson.version;
const pluginFile = 'connector-for-newspack-newsletters-and-listmonk.php';
const artifactsDir = path.join( rootDir, 'artifacts' );
const releaseWorkDir = path.join( artifactsDir, 'wporg-release' );
const distDir = path.join( releaseWorkDir, pluginSlug );
const zipPath = path.join( artifactsDir, `${ pluginSlug }-${ version }-wporg.zip` );

function getTrackedPaths( relativePath ) {
	return execFileSync( 'git', [ 'ls-files', '-z', '--', relativePath ], {
		cwd: rootDir,
		encoding: 'utf8',
	} ).split( '\0' ).filter( Boolean );
}

const sourcePaths = [
	pluginFile,
	'uninstall.php',
	'inc',
	'build',
	'src',
	'scripts/block-config.ts',
	'scripts/build-workspace.mjs',
	'scripts/prepare-sampo-release.mjs',
	'scripts/release-zip.mjs',
	'scripts/release-wporg.mjs',
	'scripts/review-plugin-check.mjs',
	'scripts/sync-release-version.mjs',
	'scripts/sync-project.ts',
	'scripts/sync-rest-contracts.ts',
	'scripts/sync-types-to-block-json.ts',
	'README.md',
	'CHANGELOG.md',
	'readme.txt',
	'LICENSE',
	'package.json',
	'pnpm-lock.yaml',
	'tsconfig.json',
	'webpack.config.js',
	'docs/RELEASING.md',
	...getTrackedPaths( 'docs' ).filter(
		( relativePath ) => relativePath !== 'docs/RELEASING.md'
	),
];

const requiredEntries = [
	pluginFile,
	'uninstall.php',
	'inc/bootstrap.php',
	'inc/uninstall.php',
	'build/admin-views/index.js',
	'build/editor-plugins/index.js',
	'src/types.ts',
	'src/editor-plugins/index.tsx',
	'src/admin-views/index.tsx',
	'scripts/build-workspace.mjs',
	'scripts/prepare-sampo-release.mjs',
	'scripts/sync-project.ts',
	'scripts/sync-release-version.mjs',
	'package.json',
	'pnpm-lock.yaml',
	'webpack.config.js',
	'tsconfig.json',
	'README.md',
	'CHANGELOG.md',
	'readme.txt',
	'LICENSE',
	'docs/PRIVACY.md',
	'docs/PLUGIN-REVIEW-CHECKLIST.md',
	'docs/RELEASING.md',
];

const restSchemaResources = [
	'listmonk-settings',
	'newsletter-preview',
	'newsletter-sync',
	'campaign-analytics',
];

const forbiddenZipPatterns = [
	/^connector-for-newspack-newsletters-and-listmonk\/node_modules\//,
	/^connector-for-newspack-newsletters-and-listmonk\/vendor\//,
	/^connector-for-newspack-newsletters-and-listmonk\/tests\//,
	/^connector-for-newspack-newsletters-and-listmonk\/artifacts\//,
	/^connector-for-newspack-newsletters-and-listmonk\/\.git(?:\/|$)/,
	/^connector-for-newspack-newsletters-and-listmonk\/\.env(?:\.|$)/,
	/^connector-for-newspack-newsletters-and-listmonk\/\.listmonk\.env$/,
	/^connector-for-newspack-newsletters-and-listmonk\/\.staging\.env$/,
	/^connector-for-newspack-newsletters-and-listmonk\/\.wp-env(?:\.|\/|$)/,
	/^connector-for-newspack-newsletters-and-listmonk\/.*\/\.gitkeep$/,
	/^connector-for-newspack-newsletters-and-listmonk\/docker-compose\.listmonk\.yml$/,
	/^connector-for-newspack-newsletters-and-listmonk\/playwright-report\//,
	/^connector-for-newspack-newsletters-and-listmonk\/test-results\//,
];

function logStep( message ) {
	console.log( `\n> ${ message }` );
}

function run( command, args, options = {} ) {
	const result = spawnSync( command, args, {
		cwd: options.cwd ?? rootDir,
		encoding: 'utf8',
		stdio: options.stdio ?? 'inherit',
	} );

	if ( result.error ) {
		throw result.error;
	}

	if ( result.status !== 0 ) {
		throw new Error( `${ command } ${ args.join( ' ' ) } failed with exit code ${ result.status }` );
	}

	return result;
}

function assertVersionSync() {
	const pluginSource = fs.readFileSync( path.join( rootDir, pluginFile ), 'utf8' );
	const headerMatch = pluginSource.match( /^\s*\*\s*Version:\s*([^\s]+)/m );
	const constantMatch = pluginSource.match(
		/define\(\s*'NEWSPACK_LISTMONK_CONNECTOR_VERSION'\s*,\s*'([^']+)'\s*\)/
	);

	if ( ! headerMatch || ! constantMatch ) {
		throw new Error( 'Unable to resolve plugin version metadata.' );
	}

	if ( new Set( [ version, headerMatch[ 1 ], constantMatch[ 1 ] ] ).size !== 1 ) {
		throw new Error(
			`Version mismatch: package=${ version }, header=${ headerMatch[ 1 ] }, constant=${ constantMatch[ 1 ] }`
		);
	}
}

function getRestSchemaFiles() {
	return restSchemaResources.flatMap( ( resource ) => {
		const schemaDir = path.join( rootDir, 'src/rest', resource, 'api-schemas' );
		if ( ! fs.existsSync( schemaDir ) ) {
			return [];
		}

		return fs
			.readdirSync( schemaDir )
			.filter( ( fileName ) => fileName.endsWith( '.schema.json' ) )
			.map( ( fileName ) => `src/rest/${ resource }/api-schemas/${ fileName }` );
	} );
}

function copyPath( relativePath ) {
	const sourcePath = path.join( rootDir, relativePath );
	const targetPath = path.join( distDir, relativePath );
	if ( ! fs.existsSync( sourcePath ) ) {
		throw new Error( `WP.org release source path does not exist: ${ relativePath }` );
	}

	fs.mkdirSync( path.dirname( targetPath ), { recursive: true } );
	fs.cpSync( sourcePath, targetPath, { recursive: true } );
}

function copyRestSchemasForRuntime() {
	for ( const relativePath of getRestSchemaFiles() ) {
		const sourcePath = path.join( rootDir, relativePath );
		const [ , , resource, , fileName ] = relativePath.split( '/' );
		const targetPath = path.join( distDir, 'inc/rest-schemas', resource, fileName );

		fs.mkdirSync( path.dirname( targetPath ), { recursive: true } );
		fs.copyFileSync( sourcePath, targetPath );
	}
}

function collectFiles( dir ) {
	return fs.readdirSync( dir, { withFileTypes: true } ).flatMap( ( entry ) => {
		const fullPath = path.join( dir, entry.name );
		if ( entry.isDirectory() ) {
			return collectFiles( fullPath );
		}
		return entry.isFile() ? [ fullPath ] : [];
	} );
}

function lintPhpFiles() {
	for ( const phpFile of collectFiles( distDir ).filter( ( file ) => file.endsWith( '.php' ) ) ) {
		execFileSync( 'php', [ '-l', phpFile ], {
			cwd: rootDir,
			stdio: 'pipe',
		} );
	}
}

function createZip() {
	fs.mkdirSync( artifactsDir, { recursive: true } );
	fs.rmSync( zipPath, { force: true } );
	run( 'zip', [ '-qr', zipPath, pluginSlug ], {
		cwd: releaseWorkDir,
		stdio: 'inherit',
	} );
}

function listZipEntries() {
	const output = execFileSync( 'unzip', [ '-Z1', zipPath ], {
		cwd: rootDir,
		encoding: 'utf8',
	} );

	return output.split( /\r?\n/ ).map( ( line ) => line.trim() ).filter( Boolean );
}

function assertZipContents() {
	const entries = listZipEntries();
	const entrySet = new Set( entries );
	const requiredZipEntries = requiredEntries
		.concat(
			getRestSchemaFiles().map( ( relativePath ) => {
				const [ , , resource, , fileName ] = relativePath.split( '/' );
				return `inc/rest-schemas/${ resource }/${ fileName }`;
			} )
		)
		.map( ( relativePath ) => `${ pluginSlug }/${ relativePath }` );

	for ( const requiredEntry of requiredZipEntries ) {
		if ( ! entrySet.has( requiredEntry ) ) {
			throw new Error( `WP.org zip is missing required entry: ${ requiredEntry }` );
		}
	}

	for ( const entry of entries ) {
		if ( forbiddenZipPatterns.some( ( pattern ) => pattern.test( entry ) ) ) {
			throw new Error( `WP.org zip contains forbidden entry: ${ entry }` );
		}
	}
}

function main() {
	logStep( 'Validating synchronized release metadata' );
	run( 'node', [ 'scripts/sync-release-version.mjs', '--check' ] );

	logStep( 'Building production assets' );
	run( 'pnpm', [ 'run', 'build' ] );

	logStep( 'Validating release inputs' );
	assertVersionSync();

	logStep( 'Preparing WordPress.org release directory' );
	fs.rmSync( releaseWorkDir, { force: true, recursive: true } );
	fs.mkdirSync( distDir, { recursive: true } );
	for ( const relativePath of sourcePaths ) {
		copyPath( relativePath );
	}
	copyRestSchemasForRuntime();
	lintPhpFiles();

	logStep( 'Creating WordPress.org source zip' );
	createZip();
	assertZipContents();

	console.log( `\nCreated ${ path.relative( rootDir, zipPath ) }` );
}

main();
