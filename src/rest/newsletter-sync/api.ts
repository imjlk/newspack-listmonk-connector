import { callEndpoint, resolveRestRouteUrl } from '@wp-typia/rest';

import type { NewsletterSyncCreateRequest } from './api-types';
import { createNewsletterSyncResourceEndpoint } from './api-client';

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

export const restResourceCreateEndpoint = {
	...createNewsletterSyncResourceEndpoint,
	buildRequestOptions: () => {
		const nonce = resolveRestNonce();
		return {
			headers: nonce
				? {
						'X-WP-Nonce': nonce,
				  }
				: undefined,
			url: resolveRestRouteUrl(
				createNewsletterSyncResourceEndpoint.path
			),
		};
	},
};

export function createResource( request: NewsletterSyncCreateRequest ) {
	return callEndpoint( restResourceCreateEndpoint, request );
}
