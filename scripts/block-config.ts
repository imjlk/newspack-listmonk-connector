import { defineEndpointManifest } from '@wp-typia/block-runtime/metadata-core';

export interface WorkspaceBlockConfig {
	slug: string;
	attributeTypeName: string;
	typesFile: string;
	apiTypesFile?: string;
	openApiFile?: string;
	restManifest?: ReturnType<
		typeof import('@wp-typia/block-runtime/metadata-core').defineEndpointManifest
	>;
}

export interface WorkspaceVariationConfig {
	block: string;
	file: string;
	slug: string;
}

export interface WorkspaceBlockStyleConfig {
	block: string;
	file: string;
	slug: string;
}

export interface WorkspaceBlockTransformConfig {
	block: string;
	file: string;
	from: string;
	slug: string;
	to: string;
}

export interface WorkspacePatternConfig {
	file: string;
	slug: string;
}

export interface WorkspaceBindingSourceConfig {
	attribute?: string;
	block?: string;
	editorFile: string;
	serverFile: string;
	slug: string;
}

export interface WorkspaceRestResourceConfig {
	apiFile: string;
	clientFile: string;
	dataFile: string;
	methods: Array< 'list' | 'read' | 'create' | 'update' | 'delete' >;
	namespace: string;
	openApiFile: string;
	phpFile: string;
	restManifest?: ReturnType<
		typeof import('@wp-typia/block-runtime/metadata-core').defineEndpointManifest
	>;
	slug: string;
	typesFile: string;
	validatorsFile: string;
}

export interface WorkspaceEditorPluginConfig {
	file: string;
	slug: string;
	slot: string;
}

export interface WorkspaceAdminViewConfig {
	file: string;
	phpFile: string;
	slug: string;
	source?: string;
}

export const BLOCKS: WorkspaceBlockConfig[] = [
	// wp-typia add block entries
];

export const VARIATIONS: WorkspaceVariationConfig[] = [
	// wp-typia add variation entries
];

export const BLOCK_STYLES: WorkspaceBlockStyleConfig[] = [
	// wp-typia add style entries
];

export const BLOCK_TRANSFORMS: WorkspaceBlockTransformConfig[] = [
	// wp-typia add transform entries
];

export const PATTERNS: WorkspacePatternConfig[] = [
	// wp-typia add pattern entries
];

export const BINDING_SOURCES: WorkspaceBindingSourceConfig[] = [
	// wp-typia add binding-source entries
];

export const REST_RESOURCES: WorkspaceRestResourceConfig[] = [
	{
		apiFile: 'src/rest/listmonk-settings/api.ts',
		clientFile: 'src/rest/listmonk-settings/api-client.ts',
		dataFile: 'src/rest/listmonk-settings/data.ts',
		methods: [ 'read', 'create' ],
		namespace: 'wp-typia-newsletter-connector/v1',
		openApiFile: 'src/rest/listmonk-settings/api.openapi.json',
		phpFile: 'inc/rest/listmonk-settings.php',
		restManifest: defineEndpointManifest( {
			contracts: {
				'read-query': {
					sourceTypeName: 'ListmonkSettingsReadQuery',
				},
				'read-response': {
					sourceTypeName: 'ListmonkSettingsReadResponse',
				},
				'create-request': {
					sourceTypeName: 'ListmonkSettingsCreateRequest',
				},
				'create-response': {
					sourceTypeName: 'ListmonkSettingsCreateResponse',
				},
			},
			endpoints: [
				{
					auth: 'authenticated',
					method: 'GET',
					operationId: 'readListmonkSettingsResource',
					path: '/wp-typia-newsletter-connector/v1/listmonk-settings/item',
					queryContract: 'read-query',
					responseContract: 'read-response',
					summary: 'Read one Listmonk Settings resource.',
					tags: [ 'Listmonk Settings' ],
					wordpressAuth: {
						mechanism: 'rest-nonce',
					},
				},
				{
					auth: 'authenticated',
					bodyContract: 'create-request',
					method: 'POST',
					operationId: 'createListmonkSettingsResource',
					path: '/wp-typia-newsletter-connector/v1/listmonk-settings',
					responseContract: 'create-response',
					summary: 'Create one Listmonk Settings resource.',
					tags: [ 'Listmonk Settings' ],
					wordpressAuth: {
						mechanism: 'rest-nonce',
					},
				},
			],
			info: {
				title: 'Listmonk Settings REST API',
				version: '1.0.0',
			},
		} ),
		slug: 'listmonk-settings',
		typesFile: 'src/rest/listmonk-settings/api-types.ts',
		validatorsFile: 'src/rest/listmonk-settings/api-validators.ts',
	},
	{
		apiFile: 'src/rest/newsletter-preview/api.ts',
		clientFile: 'src/rest/newsletter-preview/api-client.ts',
		dataFile: 'src/rest/newsletter-preview/data.ts',
		methods: [ 'read', 'create' ],
		namespace: 'wp-typia-newsletter-connector/v1',
		openApiFile: 'src/rest/newsletter-preview/api.openapi.json',
		phpFile: 'inc/rest/newsletter-preview.php',
		restManifest: defineEndpointManifest( {
			contracts: {
				'read-query': {
					sourceTypeName: 'NewsletterPreviewReadQuery',
				},
				'read-response': {
					sourceTypeName: 'NewsletterPreviewReadResponse',
				},
				'create-request': {
					sourceTypeName: 'NewsletterPreviewCreateRequest',
				},
				'create-response': {
					sourceTypeName: 'NewsletterPreviewCreateResponse',
				},
			},
			endpoints: [
				{
					auth: 'authenticated',
					method: 'GET',
					operationId: 'readNewsletterPreviewResource',
					path: '/wp-typia-newsletter-connector/v1/newsletter-preview/item',
					queryContract: 'read-query',
					responseContract: 'read-response',
					summary: 'Read one Newsletter Preview resource.',
					tags: [ 'Newsletter Preview' ],
					wordpressAuth: {
						mechanism: 'rest-nonce',
					},
				},
				{
					auth: 'authenticated',
					bodyContract: 'create-request',
					method: 'POST',
					operationId: 'createNewsletterPreviewResource',
					path: '/wp-typia-newsletter-connector/v1/newsletter-preview',
					responseContract: 'create-response',
					summary: 'Create one Newsletter Preview resource.',
					tags: [ 'Newsletter Preview' ],
					wordpressAuth: {
						mechanism: 'rest-nonce',
					},
				},
			],
			info: {
				title: 'Newsletter Preview REST API',
				version: '1.0.0',
			},
		} ),
		slug: 'newsletter-preview',
		typesFile: 'src/rest/newsletter-preview/api-types.ts',
		validatorsFile: 'src/rest/newsletter-preview/api-validators.ts',
	},
	{
		apiFile: 'src/rest/newsletter-sync/api.ts',
		clientFile: 'src/rest/newsletter-sync/api-client.ts',
		dataFile: 'src/rest/newsletter-sync/data.ts',
		methods: [ 'create' ],
		namespace: 'wp-typia-newsletter-connector/v1',
		openApiFile: 'src/rest/newsletter-sync/api.openapi.json',
		phpFile: 'inc/rest/newsletter-sync.php',
		restManifest: defineEndpointManifest( {
			contracts: {
				'create-request': {
					sourceTypeName: 'NewsletterSyncCreateRequest',
				},
				'create-response': {
					sourceTypeName: 'NewsletterSyncCreateResponse',
				},
			},
			endpoints: [
				{
					auth: 'authenticated',
					bodyContract: 'create-request',
					method: 'POST',
					operationId: 'createNewsletterSyncResource',
					path: '/wp-typia-newsletter-connector/v1/newsletter-sync',
					responseContract: 'create-response',
					summary: 'Sync one newsletter to Listmonk.',
					tags: [ 'Newsletter Sync' ],
					wordpressAuth: {
						mechanism: 'rest-nonce',
					},
				},
			],
			info: {
				title: 'Newsletter Sync REST API',
				version: '1.0.0',
			},
		} ),
		slug: 'newsletter-sync',
		typesFile: 'src/rest/newsletter-sync/api-types.ts',
		validatorsFile: 'src/rest/newsletter-sync/api-validators.ts',
	},
	{
		apiFile: 'src/rest/campaign-analytics/api.ts',
		clientFile: 'src/rest/campaign-analytics/api-client.ts',
		dataFile: 'src/rest/campaign-analytics/data.ts',
		methods: [ 'read' ],
		namespace: 'wp-typia-newsletter-connector/v1',
		openApiFile: 'src/rest/campaign-analytics/api.openapi.json',
		phpFile: 'inc/rest/campaign-analytics.php',
		restManifest: defineEndpointManifest( {
			contracts: {
				'read-query': {
					sourceTypeName: 'CampaignAnalyticsReadQuery',
				},
				'read-response': {
					sourceTypeName: 'CampaignAnalyticsReadResponse',
				},
			},
			endpoints: [
				{
					auth: 'authenticated',
					method: 'GET',
					operationId: 'readCampaignAnalyticsResource',
					path: '/wp-typia-newsletter-connector/v1/campaign-analytics/item',
					queryContract: 'read-query',
					responseContract: 'read-response',
					summary:
						'Read Listmonk campaign analytics for one newsletter.',
					tags: [ 'Campaign Analytics' ],
					wordpressAuth: {
						mechanism: 'rest-nonce',
					},
				},
			],
			info: {
				title: 'Campaign Analytics REST API',
				version: '1.0.0',
			},
		} ),
		slug: 'campaign-analytics',
		typesFile: 'src/rest/campaign-analytics/api-types.ts',
		validatorsFile: 'src/rest/campaign-analytics/api-validators.ts',
	},
	// wp-typia add rest-resource entries
];

export const EDITOR_PLUGINS: WorkspaceEditorPluginConfig[] = [
	// wp-typia add editor-plugin entries
];

export const ADMIN_VIEWS: WorkspaceAdminViewConfig[] = [
	// wp-typia add admin-view entries
];

export interface WorkspaceAbilityConfig {
	clientFile: string;
	compatibility?: {
		hardMinimums: {
			php?: string;
			wordpress?: string;
		};
		mode: 'baseline' | 'optional' | 'required';
		optionalFeatureIds: string[];
		optionalFeatures: string[];
		requiredFeatureIds: string[];
		requiredFeatures: string[];
		runtimeGates: string[];
	};
	configFile: string;
	dataFile: string;
	inputSchemaFile: string;
	inputTypeName: string;
	outputSchemaFile: string;
	outputTypeName: string;
	phpFile: string;
	slug: string;
	typesFile: string;
}

export const ABILITIES: WorkspaceAbilityConfig[] = [
	// wp-typia add ability entries
];

export interface WorkspaceAiFeatureConfig {
	aiSchemaFile: string;
	apiFile: string;
	clientFile: string;
	compatibility?: {
		hardMinimums: {
			php?: string;
			wordpress?: string;
		};
		mode: 'baseline' | 'optional' | 'required';
		optionalFeatureIds: string[];
		optionalFeatures: string[];
		requiredFeatureIds: string[];
		requiredFeatures: string[];
		runtimeGates: string[];
	};
	dataFile: string;
	namespace: string;
	openApiFile: string;
	phpFile: string;
	restManifest?: ReturnType<
		typeof import('@wp-typia/block-runtime/metadata-core').defineEndpointManifest
	>;
	slug: string;
	typesFile: string;
	validatorsFile: string;
}

export const AI_FEATURES: WorkspaceAiFeatureConfig[] = [
	// wp-typia add ai-feature entries
];
