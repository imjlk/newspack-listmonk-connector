import {
	useEndpointQuery,
	type UseEndpointQueryOptions,
} from '@wp-typia/rest/react';

import type {
	CampaignAnalyticsReadQuery,
	CampaignAnalyticsReadResponse,
} from './api-types';
import { restResourceReadEndpoint } from './api';

export type UseCampaignAnalyticsReadQueryOptions<
	Selected = CampaignAnalyticsReadResponse,
> = UseEndpointQueryOptions<
	CampaignAnalyticsReadQuery,
	CampaignAnalyticsReadResponse,
	Selected
>;

export function useCampaignAnalyticsReadQuery<
	Selected = CampaignAnalyticsReadResponse,
>(
	request: CampaignAnalyticsReadQuery,
	options: UseCampaignAnalyticsReadQueryOptions< Selected > = {}
) {
	return useEndpointQuery( restResourceReadEndpoint, request, options );
}
