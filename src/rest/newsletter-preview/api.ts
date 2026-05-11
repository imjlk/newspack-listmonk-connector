import { callEndpoint, resolveRestRouteUrl } from '@wp-typia/rest';

import type {
	NewsletterPreviewCreateRequest,
	NewsletterPreviewReadQuery,
} from './api-types';
import {
	createNewsletterPreviewResourceEndpoint,
	readNewsletterPreviewResourceEndpoint,
} from './api-client';
function resolveRestNonce( fallback?: string ): string | undefined {
	if ( typeof fallback === 'string' && fallback.length > 0 ) {
		return fallback;
	}

	if ( typeof window === 'undefined' ) {
		return undefined;
	}

	const wpApiSettings = (
		window as typeof window & {
			wpApiSettings?: { nonce?: string };
		}
	 ).wpApiSettings;

	return typeof wpApiSettings?.nonce === 'string' &&
		wpApiSettings.nonce.length > 0
		? wpApiSettings.nonce
		: undefined;
}

export const restResourceReadEndpoint = {
	...readNewsletterPreviewResourceEndpoint,
	buildRequestOptions: () => {
		const nonce = resolveRestNonce();
		return {
			headers: nonce
				? {
						'X-WP-Nonce': nonce,
				  }
				: undefined,
			url: resolveRestRouteUrl(
				readNewsletterPreviewResourceEndpoint.path
			),
		};
	},
};

export function readResource( request: NewsletterPreviewReadQuery ) {
	return callEndpoint( restResourceReadEndpoint, request );
}

export const restResourceCreateEndpoint = {
	...createNewsletterPreviewResourceEndpoint,
	buildRequestOptions: () => {
		const nonce = resolveRestNonce();
		return {
			headers: nonce
				? {
						'X-WP-Nonce': nonce,
				  }
				: undefined,
			url: resolveRestRouteUrl(
				createNewsletterPreviewResourceEndpoint.path
			),
		};
	},
};

export function createResource( request: NewsletterPreviewCreateRequest ) {
	return callEndpoint( restResourceCreateEndpoint, request );
}
