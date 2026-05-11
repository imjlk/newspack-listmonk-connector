import {
	callEndpoint,
	createEndpoint,
	type EndpointCallOptions,
} from '@wp-typia/api-client';
import type {
	NewsletterPreviewCreateRequest,
	NewsletterPreviewCreateResponse,
	NewsletterPreviewReadQuery,
	NewsletterPreviewReadResponse,
} from './api-types';
import { apiValidators } from './api-validators';

export const readNewsletterPreviewResourceEndpoint = createEndpoint<
	NewsletterPreviewReadQuery,
	NewsletterPreviewReadResponse
>( {
	authIntent: 'authenticated',
	authMode: 'authenticated-rest-nonce',
	method: 'GET',
	operationId: 'readNewsletterPreviewResource',
	path: '/newspack-listmonk-connector/v1/newsletter-preview/item',
	requestLocation: 'query',
	validateRequest: apiValidators.readQuery,
	validateResponse: apiValidators.readResponse,
} );

export function readNewsletterPreviewResource(
	request: NewsletterPreviewReadQuery,
	options: EndpointCallOptions
) {
	return callEndpoint(
		readNewsletterPreviewResourceEndpoint,
		request,
		options
	);
}

export const createNewsletterPreviewResourceEndpoint = createEndpoint<
	NewsletterPreviewCreateRequest,
	NewsletterPreviewCreateResponse
>( {
	authIntent: 'authenticated',
	authMode: 'authenticated-rest-nonce',
	method: 'POST',
	operationId: 'createNewsletterPreviewResource',
	path: '/newspack-listmonk-connector/v1/newsletter-preview',
	requestLocation: 'body',
	validateRequest: apiValidators.createRequest,
	validateResponse: apiValidators.createResponse,
} );

export function createNewsletterPreviewResource(
	request: NewsletterPreviewCreateRequest,
	options: EndpointCallOptions
) {
	return callEndpoint(
		createNewsletterPreviewResourceEndpoint,
		request,
		options
	);
}
