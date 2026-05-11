/* eslint-disable no-console */
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

interface SyncCliOptions {
	check: boolean;
}

function parseCliOptions( argv: string[] ): SyncCliOptions {
	const options: SyncCliOptions = {
		check: false,
	};

	for ( const argument of argv ) {
		if ( argument === '--check' ) {
			options.check = true;
			continue;
		}

		throw new Error( `Unknown sync flag: ${ argument }` );
	}

	return options;
}

function getSyncScriptEnv() {
	const binaryDirectory = path.join( process.cwd(), 'node_modules', '.bin' );
	const inheritedPath =
		process.env.PATH ??
		process.env.Path ??
		Object.entries( process.env ).find(
			( [ key ] ) => key.toLowerCase() === 'path'
		)?.[ 1 ] ??
		'';
	const nextPath = fs.existsSync( binaryDirectory )
		? `${ binaryDirectory }${ path.delimiter }${ inheritedPath }`
		: inheritedPath;
	const env: NodeJS.ProcessEnv = {
		...process.env,
	};

	for ( const key of Object.keys( env ) ) {
		if ( key.toLowerCase() === 'path' ) {
			delete env[ key ];
		}
	}

	env.PATH = nextPath;

	return env;
}

function runSyncScript( scriptPath: string, options: SyncCliOptions ) {
	const args = [ scriptPath ];
	if ( options.check ) {
		args.push( '--check' );
	}

	const result = spawnSync( 'tsx', args, {
		cwd: process.cwd(),
		env: getSyncScriptEnv(),
		shell: process.platform === 'win32',
		stdio: 'inherit',
	} );

	if ( result.error ) {
		if ( ( result.error as NodeJS.ErrnoException ).code === 'ENOENT' ) {
			throw new Error(
				'Unable to resolve `tsx` for project sync. Install project dependencies or rerun the command through your package manager.'
			);
		}

		throw result.error;
	}

	if ( result.status !== 0 ) {
		throw new Error( `Sync script failed: ${ scriptPath }` );
	}
}

async function main() {
	const options = parseCliOptions( process.argv.slice( 2 ) );
	const syncTypesScriptPath = path.join(
		'scripts',
		'sync-types-to-block-json.ts'
	);
	const syncRestScriptPath = path.join( 'scripts', 'sync-rest-contracts.ts' );

	runSyncScript( syncTypesScriptPath, options );

	if ( fs.existsSync( path.resolve( process.cwd(), syncRestScriptPath ) ) ) {
		runSyncScript( syncRestScriptPath, options );
	}

	console.log(
		options.check
			? '✅ Generated project metadata and REST artifacts are already synchronized.'
			: '✅ Generated project metadata and REST artifacts were synchronized.'
	);
}

main().catch( ( error ) => {
	console.error( '❌ Project sync failed:', error );
	process.exit( 1 );
} );
