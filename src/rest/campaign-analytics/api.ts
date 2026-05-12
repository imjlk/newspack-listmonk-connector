import { callEndpoint, resolveRestRouteUrl } from '@wp-typia/rest';

import type { CampaignAnalyticsReadQuery } from './api-types';
import { readCampaignAnalyticsResourceEndpoint } from './api-client';

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
	...readCampaignAnalyticsResourceEndpoint,
	buildRequestOptions: () => {
		const nonce = resolveRestNonce();
		return {
			headers: nonce
				? {
						'X-WP-Nonce': nonce,
				  }
				: undefined,
			url: resolveRestRouteUrl(
				readCampaignAnalyticsResourceEndpoint.path
			),
		};
	},
};

export function readResource( request: CampaignAnalyticsReadQuery ) {
	return callEndpoint( restResourceReadEndpoint, request );
}
