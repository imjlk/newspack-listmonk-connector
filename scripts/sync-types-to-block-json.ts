/* eslint-disable no-console */
import path from 'node:path';

import { syncBlockMetadata } from '@wp-typia/block-runtime/metadata-core';

import { BLOCKS } from './block-config';

function parseCliOptions( argv: string[] ) {
	const options = {
		check: false,
	};

	for ( const argument of argv ) {
		if ( argument === '--check' ) {
			options.check = true;
			continue;
		}

		throw new Error( `Unknown sync-types flag: ${ argument }` );
	}

	return options;
}

async function main() {
	const options = parseCliOptions( process.argv.slice( 2 ) );

	if ( BLOCKS.length === 0 ) {
		console.log(
			options.check
				? 'ℹ️ No workspace blocks are registered yet. `sync-types --check` is already clean.'
				: 'ℹ️ No workspace blocks are registered yet. Add one with `wp-typia add block <name> --template basic`.'
		);
		return;
	}

	for ( const block of BLOCKS ) {
		const baseDir = path.join( 'src', 'blocks', block.slug );
		const result = await syncBlockMetadata(
			{
				blockJsonFile: path.join( baseDir, 'block.json' ),
				jsonSchemaFile: path.join( baseDir, 'typia.schema.json' ),
				manifestFile: path.join( baseDir, 'typia.manifest.json' ),
				openApiFile: path.join( baseDir, 'typia.openapi.json' ),
				sourceTypeName: block.attributeTypeName,
				typesFile: block.typesFile,
			},
			{
				check: options.check,
			}
		);

		console.log(
			options.check
				? `✅ ${ block.slug }: block.json, typia.manifest.json, typia-validator.php, typia.schema.json, and typia.openapi.json are already up to date with the TypeScript types!`
				: `✅ ${ block.slug }: block.json, typia.manifest.json, typia-validator.php, typia.schema.json, and typia.openapi.json were generated from TypeScript types!`
		);
		console.log( '📝 Generated attributes:', result.attributeNames );
	}
}

main().catch( ( error ) => {
	console.error( '❌ Type sync failed:', error );
	process.exit( 1 );
} );
