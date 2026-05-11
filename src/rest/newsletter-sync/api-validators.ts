import typia from 'typia';

import { toValidationResult } from '@wp-typia/rest';
import type {
	NewsletterSyncCreateRequest,
	NewsletterSyncCreateResponse,
} from './api-types';

const validateCreateRequest =
	typia.createValidate< NewsletterSyncCreateRequest >();
const validateCreateResponse =
	typia.createValidate< NewsletterSyncCreateResponse >();

export const apiValidators = {
	createRequest: ( input: unknown ) =>
		toValidationResult< NewsletterSyncCreateRequest >(
			validateCreateRequest( input )
		),
	createResponse: ( input: unknown ) =>
		toValidationResult< NewsletterSyncCreateResponse >(
			validateCreateResponse( input )
		),
};
