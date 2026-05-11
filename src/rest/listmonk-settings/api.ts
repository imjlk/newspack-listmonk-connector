import { callEndpoint, resolveRestRouteUrl } from '@wp-typia/rest';

import type {
	ListmonkSettingsCreateRequest,
	ListmonkSettingsReadQuery,
} from './api-types';
import {
	createListmonkSettingsResourceEndpoint,
	readListmonkSettingsResourceEndpoint,
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
	...readListmonkSettingsResourceEndpoint,
	buildRequestOptions: () => {
		const nonce = resolveRestNonce();
		return {
			headers: nonce
				? {
						'X-WP-Nonce': nonce,
				  }
				: undefined,
			url: resolveRestRouteUrl(
				readListmonkSettingsResourceEndpoint.path
			),
		};
	},
};

export function readResource( request: ListmonkSettingsReadQuery ) {
	return callEndpoint( restResourceReadEndpoint, request );
}

export const restResourceCreateEndpoint = {
	...createListmonkSettingsResourceEndpoint,
	buildRequestOptions: () => {
		const nonce = resolveRestNonce();
		return {
			headers: nonce
				? {
						'X-WP-Nonce': nonce,
				  }
				: undefined,
			url: resolveRestRouteUrl(
				createListmonkSettingsResourceEndpoint.path
			),
		};
	},
};

export function createResource( request: ListmonkSettingsCreateRequest ) {
	return callEndpoint( restResourceCreateEndpoint, request );
}
