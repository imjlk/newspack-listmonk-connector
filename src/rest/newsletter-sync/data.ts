import {
	useEndpointMutation,
	type UseEndpointMutationOptions,
} from '@wp-typia/rest/react';

import type {
	NewsletterSyncCreateRequest,
	NewsletterSyncCreateResponse,
} from './api-types';
import { restResourceCreateEndpoint } from './api';

export type UseCreateNewsletterSyncResourceMutationOptions =
	UseEndpointMutationOptions<
		NewsletterSyncCreateRequest,
		NewsletterSyncCreateResponse,
		unknown
	>;

export function useCreateNewsletterSyncResourceMutation(
	options: UseCreateNewsletterSyncResourceMutationOptions = {}
) {
	return useEndpointMutation( restResourceCreateEndpoint, options );
}
