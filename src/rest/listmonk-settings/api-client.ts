import {
  callEndpoint,
  createEndpoint,
  type EndpointCallOptions,
} from '@wp-typia/api-client';
import type {
  ListmonkSettingsCreateRequest,
  ListmonkSettingsCreateResponse,
  ListmonkSettingsReadQuery,
  ListmonkSettingsReadResponse,
} from './api-types';
import { apiValidators } from './api-validators';

export const readListmonkSettingsResourceEndpoint = createEndpoint<
  ListmonkSettingsReadQuery,
  ListmonkSettingsReadResponse
>({
  authIntent: 'authenticated',
  authMode: 'authenticated-rest-nonce',
  method: 'GET',
  operationId: 'readListmonkSettingsResource',
  path: '/connector-for-newspack-newsletters-and-listmonk/v1/listmonk-settings/item',
  requestLocation: 'query',
  validateRequest: apiValidators.readQuery,
  validateResponse: apiValidators.readResponse,
});

export function readListmonkSettingsResource(
  request: ListmonkSettingsReadQuery,
  options: EndpointCallOptions,
) {
  return callEndpoint(readListmonkSettingsResourceEndpoint, request, options);
}

export const createListmonkSettingsResourceEndpoint = createEndpoint<
  ListmonkSettingsCreateRequest,
  ListmonkSettingsCreateResponse
>({
  authIntent: 'authenticated',
  authMode: 'authenticated-rest-nonce',
  method: 'POST',
  operationId: 'createListmonkSettingsResource',
  path: '/connector-for-newspack-newsletters-and-listmonk/v1/listmonk-settings',
  requestLocation: 'body',
  validateRequest: apiValidators.createRequest,
  validateResponse: apiValidators.createResponse,
});

export function createListmonkSettingsResource(
  request: ListmonkSettingsCreateRequest,
  options: EndpointCallOptions,
) {
  return callEndpoint(createListmonkSettingsResourceEndpoint, request, options);
}
