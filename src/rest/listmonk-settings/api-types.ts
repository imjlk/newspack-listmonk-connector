import type {
	ListmonkSettingsResponse,
	SaveListmonkSettingsRequest,
} from '../../types';

export interface ListmonkSettingsReadQuery {}

export type ListmonkSettingsReadResponse = ListmonkSettingsResponse;

export type ListmonkSettingsCreateRequest = SaveListmonkSettingsRequest;

export type ListmonkSettingsCreateResponse = ListmonkSettingsResponse;
