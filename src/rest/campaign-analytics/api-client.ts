import {
  callEndpoint,
  createEndpoint,
  type EndpointCallOptions,
} from '@wp-typia/api-client';
import type {
  CampaignAnalyticsReadQuery,
  CampaignAnalyticsReadResponse,
} from './api-types';
import { apiValidators } from './api-validators';

export const readCampaignAnalyticsResourceEndpoint = createEndpoint<
  CampaignAnalyticsReadQuery,
  CampaignAnalyticsReadResponse
>({
  authIntent: 'authenticated',
  authMode: 'authenticated-rest-nonce',
  method: 'GET',
  operationId: 'readCampaignAnalyticsResource',
  path: '/connector-for-newspack-newsletters-and-listmonk/v1/campaign-analytics/item',
  requestLocation: 'query',
  validateRequest: apiValidators.readQuery,
  validateResponse: apiValidators.readResponse,
});

export function readCampaignAnalyticsResource(
  request: CampaignAnalyticsReadQuery,
  options: EndpointCallOptions,
) {
  return callEndpoint(readCampaignAnalyticsResourceEndpoint, request, options);
}
