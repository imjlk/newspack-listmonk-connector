import {
	callEndpoint,
	createEndpoint,
	type EndpointCallOptions,
} from '@wp-typia/api-client';
import type {
	NewsletterSyncCreateRequest,
	NewsletterSyncCreateResponse,
} from './api-types';
import { apiValidators } from './api-validators';

export const createNewsletterSyncResourceEndpoint = createEndpoint<
	NewsletterSyncCreateRequest,
	NewsletterSyncCreateResponse
>( {
	authIntent: 'authenticated',
	authMode: 'authenticated-rest-nonce',
	method: 'POST',
	operationId: 'createNewsletterSyncResource',
	path: '/newspack-listmonk-connector/v1/newsletter-sync',
	requestLocation: 'body',
	validateRequest: apiValidators.createRequest,
	validateResponse: apiValidators.createResponse,
} );

export function createNewsletterSyncResource(
	request: NewsletterSyncCreateRequest,
	options: EndpointCallOptions
) {
	return callEndpoint(
		createNewsletterSyncResourceEndpoint,
		request,
		options
	);
}
