import {
	useEndpointMutation,
	useEndpointQuery,
	type UseEndpointMutationOptions,
	type UseEndpointQueryOptions,
} from '@wp-typia/rest/react';

import type {
	NewsletterPreviewCreateRequest,
	NewsletterPreviewCreateResponse,
	NewsletterPreviewReadQuery,
	NewsletterPreviewReadResponse,
} from './api-types';
import { restResourceCreateEndpoint, restResourceReadEndpoint } from './api';

export type UseNewsletterPreviewReadQueryOptions<
	Selected = NewsletterPreviewReadResponse,
> = UseEndpointQueryOptions<
	NewsletterPreviewReadQuery,
	NewsletterPreviewReadResponse,
	Selected
>;

export function useNewsletterPreviewReadQuery<
	Selected = NewsletterPreviewReadResponse,
>(
	request: NewsletterPreviewReadQuery,
	options: UseNewsletterPreviewReadQueryOptions< Selected > = {}
) {
	return useEndpointQuery( restResourceReadEndpoint, request, options );
}

export type UseCreateNewsletterPreviewResourceMutationOptions =
	UseEndpointMutationOptions<
		NewsletterPreviewCreateRequest,
		NewsletterPreviewCreateResponse,
		unknown
	>;

export function useCreateNewsletterPreviewResourceMutation(
	options: UseCreateNewsletterPreviewResourceMutationOptions = {}
) {
	return useEndpointMutation( restResourceCreateEndpoint, options );
}
