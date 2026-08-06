import { registerBlockCollection } from '@wordpress/blocks';

const globalScope = globalThis as typeof globalThis & {
	__wpTypiaCollections?: Record< string, true >;
};

globalScope.__wpTypiaCollections ??= {};

if ( ! globalScope.__wpTypiaCollections[ 'wp-typia-newsletter-connector' ] ) {
	registerBlockCollection( 'wp-typia-newsletter-connector', {
		title: 'Newspack Listmonk Connector',
	} );
	globalScope.__wpTypiaCollections[ 'wp-typia-newsletter-connector' ] = true;
}
