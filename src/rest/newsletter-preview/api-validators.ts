import typia from 'typia';

import { toValidationResult } from '@wp-typia/rest';
import type {
	NewsletterPreviewCreateRequest,
	NewsletterPreviewCreateResponse,
	NewsletterPreviewReadQuery,
	NewsletterPreviewReadResponse,
} from './api-types';

const validateReadQuery = typia.createValidate< NewsletterPreviewReadQuery >();
const validateReadResponse =
	typia.createValidate< NewsletterPreviewReadResponse >();
const validateCreateRequest =
	typia.createValidate< NewsletterPreviewCreateRequest >();
const validateCreateResponse =
	typia.createValidate< NewsletterPreviewCreateResponse >();

export const apiValidators = {
	readQuery: ( input: unknown ) =>
		toValidationResult< NewsletterPreviewReadQuery >(
			validateReadQuery( input )
		),
	readResponse: ( input: unknown ) =>
		toValidationResult< NewsletterPreviewReadResponse >(
			validateReadResponse( input )
		),
	createRequest: ( input: unknown ) =>
		toValidationResult< NewsletterPreviewCreateRequest >(
			validateCreateRequest( input )
		),
	createResponse: ( input: unknown ) =>
		toValidationResult< NewsletterPreviewCreateResponse >(
			validateCreateResponse( input )
		),
};
