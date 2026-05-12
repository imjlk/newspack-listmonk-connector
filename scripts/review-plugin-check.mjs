#!/usr/bin/env node

import { spawnSync } from 'node:child_process';

function run( command, args, options = {} ) {
	const result = spawnSync( command, args, {
		cwd: process.cwd(),
		encoding: 'utf8',
		stdio: options.stdio ?? 'inherit',
	} );

	if ( result.error ) {
		throw result.error;
	}

	if ( result.status !== 0 && ! options.allowFailure ) {
		throw new Error( `${ command } ${ args.join( ' ' ) } failed with exit code ${ result.status }` );
	}

	return result;
}

function wp( args, options = {} ) {
	return run( 'pnpm', [ 'exec', 'wp-env', 'run', 'cli', 'wp', ...args ], options );
}

function ensurePluginCheck() {
	const isInstalled = wp( [ 'plugin', 'is-installed', 'plugin-check' ], {
		allowFailure: true,
		stdio: 'pipe',
	} );

	if ( isInstalled.status === 0 ) {
		wp( [ 'plugin', 'activate', 'plugin-check' ] );
		return;
	}

	wp( [ 'plugin', 'install', 'plugin-check', '--activate' ] );
}

function main() {
	run( 'pnpm', [ 'run', 'env:start' ] );
	ensurePluginCheck();
	wp( [ 'plugin', 'activate', 'newspack-newsletters', 'newspack-listmonk-connector' ] );
	wp( [
		'plugin',
		'check',
		'newspack-listmonk-connector',
		'--require=./wp-content/plugins/plugin-check/cli.php',
		'--mode=new',
		'--exclude-directories=artifacts,tests,node_modules,vendor,.wp-env,playwright-report,test-results',
		'--exclude-files=.listmonk.env,.env.example,.wp-env.json,.phpunit.result.cache,.gitignore,phpunit.xml.dist,playwright.config.js,composer.json,composer.lock,docker-compose.listmonk.yml',
	] );
}

main();
