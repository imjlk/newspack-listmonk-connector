import {
	useEndpointMutation,
	useEndpointQuery,
	type UseEndpointMutationOptions,
	type UseEndpointQueryOptions,
} from '@wp-typia/rest/react';

import type {
	ListmonkSettingsCreateRequest,
	ListmonkSettingsCreateResponse,
	ListmonkSettingsReadQuery,
	ListmonkSettingsReadResponse,
} from './api-types';
import { restResourceCreateEndpoint, restResourceReadEndpoint } from './api';

export type UseListmonkSettingsReadQueryOptions<
	Selected = ListmonkSettingsReadResponse,
> = UseEndpointQueryOptions<
	ListmonkSettingsReadQuery,
	ListmonkSettingsReadResponse,
	Selected
>;

export function useListmonkSettingsReadQuery<
	Selected = ListmonkSettingsReadResponse,
>(
	request: ListmonkSettingsReadQuery,
	options: UseListmonkSettingsReadQueryOptions< Selected > = {}
) {
	return useEndpointQuery( restResourceReadEndpoint, request, options );
}

export type UseCreateListmonkSettingsResourceMutationOptions =
	UseEndpointMutationOptions<
		ListmonkSettingsCreateRequest,
		ListmonkSettingsCreateResponse,
		unknown
	>;

export function useCreateListmonkSettingsResourceMutation(
	options: UseCreateListmonkSettingsResourceMutationOptions = {}
) {
	return useEndpointMutation( restResourceCreateEndpoint, options );
}
