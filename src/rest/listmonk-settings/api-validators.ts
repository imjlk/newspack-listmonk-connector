import typia from 'typia';

import { toValidationResult } from '@wp-typia/rest';
import type {
	ListmonkSettingsCreateRequest,
	ListmonkSettingsCreateResponse,
	ListmonkSettingsReadQuery,
	ListmonkSettingsReadResponse,
} from './api-types';

const validateReadQuery = typia.createValidate< ListmonkSettingsReadQuery >();
const validateReadResponse =
	typia.createValidate< ListmonkSettingsReadResponse >();
const validateCreateRequest =
	typia.createValidate< ListmonkSettingsCreateRequest >();
const validateCreateResponse =
	typia.createValidate< ListmonkSettingsCreateResponse >();

export const apiValidators = {
	readQuery: ( input: unknown ) =>
		toValidationResult< ListmonkSettingsReadQuery >(
			validateReadQuery( input )
		),
	readResponse: ( input: unknown ) =>
		toValidationResult< ListmonkSettingsReadResponse >(
			validateReadResponse( input )
		),
	createRequest: ( input: unknown ) =>
		toValidationResult< ListmonkSettingsCreateRequest >(
			validateCreateRequest( input )
		),
	createResponse: ( input: unknown ) =>
		toValidationResult< ListmonkSettingsCreateResponse >(
			validateCreateResponse( input )
		),
};
