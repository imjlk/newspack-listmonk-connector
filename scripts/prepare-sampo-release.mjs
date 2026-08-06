#!/usr/bin/env node

import { spawnSync } from 'node:child_process';

const rootDir = process.cwd();

function run( command, args ) {
	const result = spawnSync( command, args, {
		cwd: rootDir,
		encoding: 'utf8',
		stdio: 'inherit',
	} );

	if ( result.error ) {
		throw result.error;
	}

	if ( result.status !== 0 ) {
		throw new Error(
			`${ command } ${ args.join( ' ' ) } failed with exit code ${ result.status }`
		);
	}
}

run( 'sampo', [ 'release' ] );
run( 'node', [ 'scripts/sync-release-version.mjs' ] );
