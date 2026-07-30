import { registerBlockCollection } from '@wordpress/blocks';

const globalScope = globalThis as typeof globalThis & {
	__wpTypiaCollections?: Record< string, true >;
};

globalScope.__wpTypiaCollections ??= {};

if ( ! globalScope.__wpTypiaCollections[ 'connector-for-newspack-newsletters-and-listmonk' ] ) {
	registerBlockCollection( 'connector-for-newspack-newsletters-and-listmonk', {
		title: 'Connector for Newspack Newsletters and Listmonk',
	} );
	globalScope.__wpTypiaCollections[ 'connector-for-newspack-newsletters-and-listmonk' ] = true;
}
