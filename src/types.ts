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

export interface NewsletterSyncRequest {
	postId: number & tags.Type< 'uint32' >;
	retrySend?: boolean;
}

export interface NewsletterSyncResponse {
	postId: number & tags.Type< 'uint32' >;
	message: string & tags.MaxLength< 500 >;
	campaignId?: number & tags.Type< 'uint32' >;
	listmonkCampaignId?: number & tags.Type< 'uint32' >;
	listmonkCampaignUuid?: string & tags.MaxLength< 80 >;
	status?: string & tags.MaxLength< 40 >;
	sendListId?: string & tags.MaxLength< 80 >;
	lastSyncedAt?: string;
	retrieve: NewspackEditorRetrieveResponse;
}

export type CampaignAnalyticsType = 'views' | 'links' | 'clicks' | 'bounces';

export interface CampaignAnalyticsRequest {
	postId: number & tags.Type< 'uint32' >;
	from: string & tags.MinLength< 1 > & tags.MaxLength< 80 >;
	to: string & tags.MinLength< 1 > & tags.MaxLength< 80 >;
}

export interface CampaignAnalyticsTotals {
	sent: number & tags.Type< 'uint32' >;
	toSend: number & tags.Type< 'uint32' >;
	views: number & tags.Type< 'uint32' >;
	clicks: number & tags.Type< 'uint32' >;
	bounces: number & tags.Type< 'uint32' >;
}

export interface CampaignAnalyticsSeriesPoint {
	type: string & tags.MaxLength< 40 >;
	campaignId?: number & tags.Type< 'uint32' >;
	count: number & tags.Type< 'uint32' >;
	timestamp?: string & tags.MaxLength< 80 >;
}

export interface CampaignAnalyticsLink {
	url: string & tags.MaxLength< 1000 >;
	count: number & tags.Type< 'uint32' >;
}

export interface CampaignAnalyticsResponse {
	postId: number & tags.Type< 'uint32' >;
	campaignId: number & tags.Type< 'uint32' >;
	status: string & tags.MaxLength< 40 >;
	totals: CampaignAnalyticsTotals;
	series: CampaignAnalyticsSeriesPoint[];
	links: CampaignAnalyticsLink[];
	checkedAt: string;
}

export interface ListmonkCampaignRef {
	campaignId: number & tags.Type< 'uint32' >;
	uuid?: string;
	status: ListmonkCampaignStatus;
}

export interface NewspackSendList {
	provider: 'listmonk';
	type: 'list' | 'sublist';
	entity_type: string & tags.MaxLength< 80 >;
	id: string & tags.MaxLength< 80 >;
	name: string & tags.MaxLength< 200 >;
	count?: number & tags.Type< 'uint32' >;
	label?: string & tags.MaxLength< 300 >;
	value?: string & tags.MaxLength< 80 >;
}

export interface NewspackEditorRetrieveResponse {
	campaign: boolean;
	campaign_id?: string & tags.MaxLength< 80 >;
	listmonk_campaign_id?: string & tags.MaxLength< 80 >;
	listmonk_campaign_uuid?: string & tags.MaxLength< 80 >;
	listmonk_last_status?: string & tags.MaxLength< 40 >;
	listmonk_last_synced_at?: string;
	listmonk_last_error?: string & tags.MaxLength< 1000 >;
	listmonk_last_error_code?: string & tags.MaxLength< 120 >;
	listmonk_last_error_at?: string;
	send_list_id: string & tags.MaxLength< 80 >;
	lists: NewspackSendList[];
	senderName?: string & tags.MaxLength< 200 >;
	senderEmail?: string & tags.MaxLength< 200 >;
	supports_multiple_test_recipients: boolean;
	link?: string & tags.MaxLength< 300 >;
}

export interface NewspackEditorTestRequest {
	test_email: string & tags.MaxLength< 1000 >;
}

export interface NewspackEditorTestResponse {
	message: string & tags.MaxLength< 500 >;
	result?: unknown;
}

export interface NewspackEditorSyncErrorResponse {
	message?: string & tags.MaxLength< 1000 >;
}
