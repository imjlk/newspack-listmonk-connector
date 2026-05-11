import type {
	NewsletterPreviewRequest,
	NewsletterPreviewResponse,
} from '../../types';

export interface NewsletterPreviewReadQuery {
	postId: NewsletterPreviewRequest[ 'postId' ];
}

export type NewsletterPreviewReadResponse = NewsletterPreviewResponse;

export type NewsletterPreviewCreateRequest = NewsletterPreviewRequest;

export type NewsletterPreviewCreateResponse = NewsletterPreviewResponse;
