import { tags } from 'typia';

export type ListmonkSendMode = 'campaign';

export type ListmonkCampaignStatus =
	| 'draft'
	| 'scheduled'
	| 'running'
	| 'paused'
	| 'cancelled';

export interface ListmonkSettings {
	baseUrl: string & tags.MaxLength< 300 >;
	apiUser: string & tags.MaxLength< 120 >;
	apiToken?: string & tags.MaxLength< 300 >;
	defaultFromEmail?: string & tags.MaxLength< 200 >;
	defaultTemplateId?: number & tags.Type< 'uint32' >;
	defaultListIds: Array< number & tags.Type< 'uint32' > >;
	sendMode: ListmonkSendMode;
}

export interface PublicListmonkSettings {
	baseUrl: string & tags.MaxLength< 300 >;
	apiUser: string & tags.MaxLength< 120 >;
	defaultFromEmail?: string & tags.MaxLength< 200 >;
	defaultTemplateId?: number & tags.Type< 'uint32' >;
	defaultListIds: Array< number & tags.Type< 'uint32' > >;
	sendMode: ListmonkSendMode;
	hasApiToken: boolean;
}

export interface ListmonkConnectionStatus {
	ok: boolean;
	message: string & tags.MaxLength< 500 >;
	checkedAt: string;
}

export interface ListmonkSettingsResponse extends PublicListmonkSettings {
	connection?: ListmonkConnectionStatus;
}

export interface SaveListmonkSettingsRequest {
	baseUrl: string & tags.MaxLength< 300 >;
	apiUser: string & tags.MaxLength< 120 >;
	apiToken?: string & tags.MaxLength< 300 >;
	defaultFromEmail?: string & tags.MaxLength< 200 >;
	defaultTemplateId?: number & tags.Type< 'uint32' >;
	defaultListIds: Array< number & tags.Type< 'uint32' > >;
	testConnection?: boolean;
}

export interface NewsletterPayload {
	postId: number & tags.Type< 'uint32' >;
	campaignName: string & tags.MinLength< 1 > & tags.MaxLength< 200 >;
	subject: string & tags.MaxLength< 300 >;
	rawHtml: string & tags.MinLength< 1 >;
	plainText?: string;
	listIds: Array< number & tags.Type< 'uint32' > >;
	fromEmail?: string & tags.MaxLength< 200 >;
	templateId?: number & tags.Type< 'uint32' >;
	tags?: string[];
	sendAt?: string;
	sendMode: ListmonkSendMode;
}

export interface NewsletterPreviewRequest {
	postId: number & tags.Type< 'uint32' >;
	listIds?: Array< number & tags.Type< 'uint32' > >;
	fromEmail?: string & tags.MaxLength< 200 >;
	templateId?: number & tags.Type< 'uint32' >;
}

export interface NewsletterPreviewResponse {
	postId: number & tags.Type< 'uint32' >;
	campaignName: string & tags.MinLength< 1 > & tags.MaxLength< 200 >;
	subject: string & tags.MaxLength< 300 >;
	rawHtml: string & tags.MinLength< 1 >;
	plainText?: string;
	listIds: Array< number & tags.Type< 'uint32' > >;
	fromEmail?: string & tags.MaxLength< 200 >;
	templateId?: number & tags.Type< 'uint32' >;
	tags?: string[];
	payloadHash: string & tags.MinLength< 32 > & tags.MaxLength< 32 >;
	listmonkPayload: NewsletterPayload;
}

export interface ListmonkCampaignRef {
	campaignId: number & tags.Type< 'uint32' >;
	uuid?: string;
	status: ListmonkCampaignStatus;
}
