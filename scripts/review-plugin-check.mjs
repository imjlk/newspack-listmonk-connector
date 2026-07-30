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
	wp( [ 'plugin', 'install', 'plugin-check', '--force', '--activate' ] );
}

function assertNoPluginCheckFindings( result ) {
	const output = result.stdout || '';
	const combinedOutput = `${ output }\n${ result.stderr || '' }`;
	const jsonStart = output.indexOf( '[' );
	const jsonEnd = output.lastIndexOf( ']' );

	if ( jsonStart === -1 || jsonEnd < jsonStart ) {
		if ( combinedOutput.includes( 'Checks complete. No errors found.' ) ) {
			console.log( 'Plugin Check passed with no findings.' );
			return;
		}

		process.stdout.write( output );
		process.stderr.write( result.stderr || '' );
		throw new Error( 'Plugin Check did not return strict JSON output.' );
	}

	const findings = JSON.parse( output.slice( jsonStart, jsonEnd + 1 ) );
	if ( findings.length > 0 ) {
		process.stdout.write( output );
		throw new Error( `Plugin Check reported ${ findings.length } finding(s).` );
	}

	console.log( 'Plugin Check passed with no findings.' );
}

function main() {
	run( 'pnpm', [ 'run', 'env:start' ] );
	ensurePluginCheck();
	wp( [ 'plugin', 'activate', 'newspack-newsletters', 'connector-for-newspack-newsletters-and-listmonk' ] );
	const result = wp( [
		'plugin',
		'check',
		'connector-for-newspack-newsletters-and-listmonk',
		'--require=./wp-content/plugins/plugin-check/cli.php',
		'--mode=new',
		'--format=strict-json',
		'--exclude-directories=artifacts,tests,node_modules,vendor,.wp-env,playwright-report,test-results',
		'--exclude-files=.DS_Store,.listmonk.env,.staging.env,.env.example,.wp-env.json,.phpunit.result.cache,.gitignore,AGENTS.md,CLAUDE.md,phpunit.xml.dist,playwright.config.js,composer.json,composer.lock,docker-compose.listmonk.yml',
	], { stdio: 'pipe' } );
	assertNoPluginCheckFindings( result );
}

main();
