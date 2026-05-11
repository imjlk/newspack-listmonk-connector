import { registerBlockCollection } from '@wordpress/blocks';

const globalScope = globalThis as typeof globalThis & {
	__wpTypiaCollections?: Record< string, true >;
};

globalScope.__wpTypiaCollections ??= {};

if ( ! globalScope.__wpTypiaCollections[ 'newspack-listmonk-connector' ] ) {
	registerBlockCollection( 'newspack-listmonk-connector', {
		title: 'Newspack Listmonk Connector',
	} );
	globalScope.__wpTypiaCollections[ 'newspack-listmonk-connector' ] = true;
}
