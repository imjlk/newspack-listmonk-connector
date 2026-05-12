import typia from 'typia';

import { toValidationResult } from '@wp-typia/rest';
import type {
	CampaignAnalyticsReadQuery,
	CampaignAnalyticsReadResponse,
} from './api-types';

const validateReadQuery = typia.createValidate< CampaignAnalyticsReadQuery >();
const validateReadResponse =
	typia.createValidate< CampaignAnalyticsReadResponse >();

export const apiValidators = {
	readQuery: ( input: unknown ) =>
		toValidationResult< CampaignAnalyticsReadQuery >(
			validateReadQuery( input )
		),
	readResponse: ( input: unknown ) =>
		toValidationResult< CampaignAnalyticsReadResponse >(
			validateReadResponse( input )
		),
};
