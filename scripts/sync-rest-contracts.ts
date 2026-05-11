/* eslint-disable no-console */
import path from 'node:path';

import {
	runSyncBlockMetadata,
	syncEndpointClient,
	syncRestOpenApi,
	syncTypeSchemas,
} from '@wp-typia/block-runtime/metadata-core';

import {
	BLOCKS,
	REST_RESOURCES,
	type WorkspaceBlockConfig,
	type WorkspaceRestResourceConfig,
} from './block-config';

function parseCliOptions( argv: string[] ) {
	const options = {
		check: false,
	};

	for ( const argument of argv ) {
		if ( argument === '--check' ) {
			options.check = true;
			continue;
		}

		throw new Error( `Unknown sync-rest flag: ${ argument }` );
	}

	return options;
}

function isRestEnabledBlock(
	block: WorkspaceBlockConfig
): block is WorkspaceBlockConfig & {
	apiTypesFile: string;
	openApiFile: string;
	restManifest: NonNullable< WorkspaceBlockConfig[ 'restManifest' ] >;
} {
	return (
		typeof block.apiTypesFile === 'string' &&
		typeof block.openApiFile === 'string' &&
		typeof block.restManifest === 'object' &&
		block.restManifest !== null
	);
}

function isWorkspaceRestResource(
	resource: WorkspaceRestResourceConfig
): resource is WorkspaceRestResourceConfig & {
	clientFile: string;
	openApiFile: string;
	restManifest: NonNullable< WorkspaceRestResourceConfig[ 'restManifest' ] >;
	typesFile: string;
	validatorsFile: string;
} {
	return (
		typeof resource.clientFile === 'string' &&
		typeof resource.openApiFile === 'string' &&
		typeof resource.typesFile === 'string' &&
		typeof resource.validatorsFile === 'string' &&
		typeof resource.restManifest === 'object' &&
		resource.restManifest !== null
	);
}

async function assertTypeArtifactsCurrent( block: WorkspaceBlockConfig ) {
	const report = await runSyncBlockMetadata(
		{
			blockJsonFile: path.join(
				'src',
				'blocks',
				block.slug,
				'block.json'
			),
			jsonSchemaFile: path.join(
				'src',
				'blocks',
				block.slug,
				'typia.schema.json'
			),
			manifestFile: path.join(
				'src',
				'blocks',
				block.slug,
				'typia.manifest.json'
			),
			openApiFile: path.join(
				'src',
				'blocks',
				block.slug,
				'typia.openapi.json'
			),
			sourceTypeName: block.attributeTypeName,
			typesFile: block.typesFile,
		},
		{
			check: true,
		}
	);

	if ( report.failure?.code === 'stale-generated-artifact' ) {
		throw new Error(
			`${ block.slug }: ${ report.failure.message }\nRun \`sync\` or \`sync-types\` first to refresh type-derived artifacts before rerunning \`sync-rest\`.`
		);
	}

	if ( report.failure ) {
		throw new Error(
			`${ block.slug }: type-derived artifact preflight failed before sync-rest.\n${ report.failure.message }`
		);
	}
}

async function main() {
	const options = parseCliOptions( process.argv.slice( 2 ) );
	const restBlocks = BLOCKS.filter( isRestEnabledBlock );
	const restResources = REST_RESOURCES.filter( isWorkspaceRestResource );

	if ( restBlocks.length === 0 && restResources.length === 0 ) {
		console.log(
			options.check
				? 'ℹ️ No REST-enabled workspace blocks or plugin-level REST resources are registered yet. `sync-rest --check` is already clean.'
				: 'ℹ️ No REST-enabled workspace blocks or plugin-level REST resources are registered yet.'
		);
		return;
	}

	for ( const block of restBlocks ) {
		await assertTypeArtifactsCurrent( block );

		const contracts = block.restManifest.contracts;

		for ( const [ baseName, contract ] of Object.entries( contracts ) ) {
			await syncTypeSchemas(
				{
					jsonSchemaFile: path.join(
						'src',
						'blocks',
						block.slug,
						'api-schemas',
						`${ baseName }.schema.json`
					),
					openApiFile: path.join(
						'src',
						'blocks',
						block.slug,
						'api-schemas',
						`${ baseName }.openapi.json`
					),
					sourceTypeName: contract.sourceTypeName,
					typesFile: block.apiTypesFile,
				},
				{
					check: options.check,
				}
			);
		}

		await syncRestOpenApi(
			{
				manifest: block.restManifest,
				openApiFile: block.openApiFile,
				typesFile: block.apiTypesFile,
			},
			{
				check: options.check,
			}
		);

		await syncEndpointClient(
			{
				clientFile: path.join(
					'src',
					'blocks',
					block.slug,
					'api-client.ts'
				),
				manifest: block.restManifest,
				typesFile: block.apiTypesFile,
			},
			{
				check: options.check,
			}
		);
	}

	for ( const resource of restResources ) {
		const contracts = resource.restManifest.contracts;

		for ( const [ baseName, contract ] of Object.entries( contracts ) ) {
			await syncTypeSchemas(
				{
					jsonSchemaFile: path.join(
						path.dirname( resource.typesFile ),
						'api-schemas',
						`${ baseName }.schema.json`
					),
					openApiFile: path.join(
						path.dirname( resource.typesFile ),
						'api-schemas',
						`${ baseName }.openapi.json`
					),
					sourceTypeName: contract.sourceTypeName,
					typesFile: resource.typesFile,
				},
				{
					check: options.check,
				}
			);
		}

		await syncRestOpenApi(
			{
				manifest: resource.restManifest,
				openApiFile: resource.openApiFile,
				typesFile: resource.typesFile,
			},
			{
				check: options.check,
			}
		);

		await syncEndpointClient(
			{
				clientFile: resource.clientFile,
				manifest: resource.restManifest,
				typesFile: resource.typesFile,
				validatorsFile: resource.validatorsFile,
			},
			{
				check: options.check,
			}
		);
	}

	console.log(
		options.check
			? '✅ REST contract schemas, portable API clients, and endpoint-aware OpenAPI documents are already up to date for workspace blocks and plugin-level resources!'
			: '✅ REST contract schemas, portable API clients, and endpoint-aware OpenAPI documents generated for workspace blocks and plugin-level resources!'
	);
}

main().catch( ( error ) => {
	console.error( '❌ REST contract sync failed:', error );
	process.exit( 1 );
} );
