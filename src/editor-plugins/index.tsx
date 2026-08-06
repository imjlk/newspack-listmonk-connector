import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import {
	PluginDocumentSettingPanel,
	store as editorStore,
} from '@wordpress/editor';
import {
	createElement,
	useCallback,
	useEffect,
	useMemo,
	useState,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import { registerPlugin } from '@wordpress/plugins';
import type { EndpointValidationResult } from '@wp-typia/rest';

import './style.scss';

import type {
	CampaignAnalyticsResponse,
	CampaignAnalyticsTotals,
	NewspackEditorRetrieveResponse,
	NewspackEditorTestResponse,
	NewspackSendList,
	NewsletterPreviewResponse,
	NewsletterSyncResponse,
} from '../types';
import type { NewsletterPreviewCreateRequest } from '../rest/newsletter-preview/api-types';
import { createResource as createPreviewResource } from '../rest/newsletter-preview/api';
import type { NewsletterSyncCreateRequest } from '../rest/newsletter-sync/api-types';
import { createResource as createSyncResource } from '../rest/newsletter-sync/api';
import type { CampaignAnalyticsReadQuery } from '../rest/campaign-analytics/api-types';
import { readResource as readCampaignAnalyticsResource } from '../rest/campaign-analytics/api';

type EditorSelect = {
	getCurrentPostId?: () => number;
	getCurrentPostType?: () => string;
	getEditedPostAttribute?: ( attribute: string ) => unknown;
	isAutosavingPost?: () => boolean;
	isSavingPost?: () => boolean;
};

type EditorDispatch = {
	editPost?: ( edits: Record< string, unknown > ) => void;
	savePost?: () => Promise< unknown > | unknown;
};

type NoticeDispatch = {
	createErrorNotice?: (
		message: string,
		options?: Record< string, unknown >
	) => void;
	createSuccessNotice?: (
		message: string,
		options?: Record< string, unknown >
	) => void;
};

type EditorData = {
	meta: Record< string, unknown >;
	postId: number;
	postStatus: string;
	postType: string;
	isSaving: boolean;
};

type NewspackEmailEditorData = {
	current_post_type?: string;
	newsletter_post_type?: string;
};

type NewspackNewslettersData = {
	is_service_provider_configured?: boolean;
	service_provider?: string;
	user_test_emails?: string[] | string;
};

type NewspackListmonkConnectorEditorData = {
	isConfigured?: boolean;
	settingsUrl?: string;
};

type WindowWithNewspack = typeof window & {
	newspack_email_editor_data?: NewspackEmailEditorData;
	newspack_listmonk_connector_editor?: NewspackListmonkConnectorEditorData;
	newspack_newsletters_data?: NewspackNewslettersData;
};

const LISTMONK_MERGE_TAG_HELPERS = [
	{
		label: __( 'Unsubscribe URL', 'wp-typia-newsletter-connector' ),
		tag: '{{ UnsubscribeURL }}',
	},
	{
		label: __( 'Open tracking pixel', 'wp-typia-newsletter-connector' ),
		tag: '{{ TrackView }}',
	},
];

const ANALYTICS_DAY_IN_MS = 24 * 60 * 60 * 1000;

const EMPTY_ANALYTICS_TOTALS: CampaignAnalyticsTotals = {
	bounces: 0,
	clicks: 0,
	sent: 0,
	toSend: 0,
	views: 0,
};

function getNewspackEmailEditorData(): NewspackEmailEditorData {
	if ( typeof window === 'undefined' ) {
		return {};
	}

	return ( window as WindowWithNewspack ).newspack_email_editor_data ?? {};
}

function getNewspackNewslettersData(): NewspackNewslettersData {
	if ( typeof window === 'undefined' ) {
		return {};
	}

	return ( window as WindowWithNewspack ).newspack_newsletters_data ?? {};
}

function getConnectorEditorData(): NewspackListmonkConnectorEditorData {
	if ( typeof window === 'undefined' ) {
		return {};
	}

	return (
		( window as WindowWithNewspack ).newspack_listmonk_connector_editor ??
		{}
	);
}

function isListmonkServiceProviderConfigured(): boolean {
	const connectorData = getConnectorEditorData();
	if ( typeof connectorData.isConfigured === 'boolean' ) {
		return connectorData.isConfigured;
	}

	const newslettersData = getNewspackNewslettersData();

	return newslettersData.is_service_provider_configured !== false;
}

function getDefaultTestEmail(): string {
	const emails = getNewspackNewslettersData().user_test_emails;
	if ( Array.isArray( emails ) ) {
		return emails.join( ', ' );
	}

	return typeof emails === 'string' ? emails : '';
}

function isListmonkNewsletterEditor( postType: string ): boolean {
	const editorData = getNewspackEmailEditorData();
	const newslettersData = getNewspackNewslettersData();
	const newsletterPostType =
		editorData.newsletter_post_type ||
		editorData.current_post_type ||
		'newspack_nl_cpt';

	return (
		postType === newsletterPostType &&
		newslettersData.service_provider === 'listmonk'
	);
}

function getErrorMessage( error: unknown ): string {
	if ( error instanceof Error && error.message ) {
		return error.message;
	}

	if ( error && typeof error === 'object' ) {
		const maybeError = error as {
			data?: { message?: unknown };
			message?: unknown;
		};
		if ( typeof maybeError.message === 'string' ) {
			return maybeError.message;
		}
		if ( typeof maybeError.data?.message === 'string' ) {
			return maybeError.data.message;
		}
	}

	return __( 'Something went wrong.', 'wp-typia-newsletter-connector' );
}

function unwrapEndpointData< Req, Res >(
	result: EndpointValidationResult< Req, Res >
): Res {
	if ( result.isValid && typeof result.data !== 'undefined' ) {
		return result.data as Res;
	}

	const firstError = result.errors[ 0 ];
	throw new Error(
		firstError
			? `${ firstError.path }: ${ firstError.expected }`
			: __(
					'The REST response failed validation.',
					'wp-typia-newsletter-connector'
			  )
	);
}

function normalizePostMeta( meta: unknown ): Record< string, unknown > {
	return meta && typeof meta === 'object'
		? ( meta as Record< string, unknown > )
		: {};
}

function getMetaString( meta: Record< string, unknown >, key: string ): string {
	const value = meta[ key ];
	return typeof value === 'string' || typeof value === 'number'
		? String( value )
		: '';
}

function getSelectedListId(
	meta: Record< string, unknown >,
	retrieve: NewspackEditorRetrieveResponse | undefined,
	fallback: string
): string {
	return (
		getMetaString( meta, 'send_list_id' ) ||
		retrieve?.send_list_id ||
		fallback
	);
}

function formatListLabel( list: NewspackSendList ): string {
	if ( list.label ) {
		return list.label;
	}

	if ( typeof list.count === 'number' ) {
		return `${ list.name } (${ list.count })`;
	}

	return list.name;
}

function formatUtcDate( date: Date ): string {
	return date.toISOString().slice( 0, 10 );
}

function getDefaultAnalyticsRange(): { from: string; to: string } {
	const toDate = new Date();
	const fromDate = new Date( toDate.getTime() - 29 * ANALYTICS_DAY_IN_MS );

	return {
		from: formatUtcDate( fromDate ),
		to: formatUtcDate( toDate ),
	};
}

function getCampaignIdFromRetrieve(
	retrieve: NewspackEditorRetrieveResponse | undefined
): string {
	return retrieve?.listmonk_campaign_id || retrieve?.campaign_id || '';
}

function formatNumber( value: number | undefined ): string {
	return new Intl.NumberFormat().format( value ?? 0 );
}

function renderAnalyticsMetric( label: string, value: number | undefined ) {
	return createElement(
		'div',
		{
			className: 'wp-typia-newsletter-connector-panel__analytics-metric',
			key: label,
		},
		createElement( 'span', null, label ),
		createElement( 'strong', null, formatNumber( value ) )
	);
}

async function fetchRetrieve(
	postId: number
): Promise< NewspackEditorRetrieveResponse > {
	return apiFetch< NewspackEditorRetrieveResponse >( {
		path: `/newspack-newsletters/v1/listmonk/${ postId }/retrieve`,
	} );
}

async function fetchSendLists(): Promise< NewspackSendList[] > {
	const query = new URLSearchParams( {
		limit: '100',
		provider: 'listmonk',
		type: 'list',
	} );

	return apiFetch< NewspackSendList[] >( {
		path: `/newspack-newsletters/v1/send-lists?${ query.toString() }`,
	} );
}

async function sendTestEmail(
	postId: number,
	testEmail: string
): Promise< NewspackEditorTestResponse > {
	return apiFetch< NewspackEditorTestResponse >( {
		data: {
			test_email: testEmail,
		},
		method: 'POST',
		path: `/newspack-newsletters/v1/listmonk/${ postId }/test`,
	} );
}

function renderMergeTagHelpers() {
	return createElement(
		'div',
		{
			className: 'wp-typia-newsletter-connector-panel__merge-tags',
			key: 'merge-tags',
		},
		createElement(
			'div',
			{
				className:
					'wp-typia-newsletter-connector-panel__merge-tags-title',
			},
			__( 'Listmonk merge tags', 'wp-typia-newsletter-connector' )
		),
		LISTMONK_MERGE_TAG_HELPERS.map( ( helper ) =>
			createElement(
				'div',
				{
					className: 'wp-typia-newsletter-connector-panel__merge-tag',
					key: helper.tag,
				},
				createElement( 'span', null, helper.label ),
				createElement( 'code', null, helper.tag )
			)
		)
	);
}

function ListmonkPanel() {
	const editorData = useSelect( ( select ) => {
		const editor = select( editorStore ) as EditorSelect;
		return {
			meta: normalizePostMeta(
				editor.getEditedPostAttribute?.( 'meta' )
			),
			postId: Number( editor.getCurrentPostId?.() ?? 0 ),
			postStatus: String(
				editor.getEditedPostAttribute?.( 'status' ) ?? ''
			),
			postType: String( editor.getCurrentPostType?.() ?? '' ),
			isSaving: Boolean(
				editor.isSavingPost?.() || editor.isAutosavingPost?.()
			),
		};
	}, [] ) as EditorData;

	const { editPost, savePost } = useDispatch( editorStore ) as EditorDispatch;
	const { createErrorNotice, createSuccessNotice } = useDispatch(
		noticesStore
	) as NoticeDispatch;

	const [ retrieveData, setRetrieveData ] =
		useState< NewspackEditorRetrieveResponse >();
	const [ lists, setLists ] = useState< NewspackSendList[] >( [] );
	const [ preview, setPreview ] = useState< NewsletterPreviewResponse >();
	const [ localListId, setLocalListId ] = useState( '' );
	const [ testEmail, setTestEmail ] = useState( getDefaultTestEmail );
	const [ errorMessage, setErrorMessage ] = useState( '' );
	const [ analytics, setAnalytics ] = useState< CampaignAnalyticsResponse >();
	const [ analyticsError, setAnalyticsError ] = useState( '' );
	const [ analyticsRangeFrom, setAnalyticsRangeFrom ] = useState(
		() => getDefaultAnalyticsRange().from
	);
	const [ analyticsRangeTo, setAnalyticsRangeTo ] = useState(
		() => getDefaultAnalyticsRange().to
	);
	const [ lastAutoAnalyticsCampaignId, setLastAutoAnalyticsCampaignId ] =
		useState( '' );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ isPreviewing, setIsPreviewing ] = useState( false );
	const [ isSyncing, setIsSyncing ] = useState( false );
	const [ isRetryingSend, setIsRetryingSend ] = useState( false );
	const [ isTesting, setIsTesting ] = useState( false );
	const [ isLoadingAnalytics, setIsLoadingAnalytics ] = useState( false );

	const isActiveEditor = isListmonkNewsletterEditor( editorData.postType );
	const isServiceProviderConfigured = isListmonkServiceProviderConfigured();
	const canUseListmonkApi = isActiveEditor && isServiceProviderConfigured;
	const selectedListId = getSelectedListId(
		editorData.meta,
		retrieveData,
		localListId
	);
	const campaignId = getCampaignIdFromRetrieve( retrieveData );
	const lastStatus = retrieveData?.listmonk_last_status || '';
	const canRetrySend =
		Boolean( retrieveData?.listmonk_last_error ) &&
		( editorData.postStatus === 'publish' ||
			editorData.postStatus === 'future' );

	const listOptions = useMemo(
		() => [
			{
				label: __(
					'Select a Listmonk list',
					'wp-typia-newsletter-connector'
				),
				value: '',
			},
			...lists.map( ( list ) => ( {
				label: formatListLabel( list ),
				value: list.value || list.id,
			} ) ),
		],
		[ lists ]
	);

	const saveCurrentPost = useCallback( async () => {
		if ( typeof savePost !== 'function' ) {
			return;
		}

		await Promise.resolve( savePost() );
	}, [ savePost ] );

	const refreshAnalytics = useCallback(
		async ( nextCampaignId = campaignId ) => {
			if ( ! editorData.postId || ! nextCampaignId ) {
				setAnalytics( undefined );
				setAnalyticsError( '' );
				return;
			}

			setIsLoadingAnalytics( true );
			setAnalyticsError( '' );

			try {
				const request: CampaignAnalyticsReadQuery = {
					from: analyticsRangeFrom,
					postId: editorData.postId,
					to: analyticsRangeTo,
				};
				const result = await readCampaignAnalyticsResource( request );
				setAnalytics(
					unwrapEndpointData<
						CampaignAnalyticsReadQuery,
						CampaignAnalyticsResponse
					>( result )
				);
			} catch ( error ) {
				const message = getErrorMessage( error );
				setAnalyticsError( message );
				createErrorNotice?.( message, { type: 'snackbar' } );
			} finally {
				setIsLoadingAnalytics( false );
			}
		},
		[
			analyticsRangeFrom,
			analyticsRangeTo,
			campaignId,
			createErrorNotice,
			editorData.postId,
		]
	);

	const refreshRetrieveAndLists = useCallback( async () => {
		if ( ! canUseListmonkApi || ! editorData.postId ) {
			return;
		}

		setIsLoading( true );
		setErrorMessage( '' );

		try {
			const [ nextRetrieve, nextLists ] = await Promise.all( [
				fetchRetrieve( editorData.postId ),
				fetchSendLists(),
			] );
			setRetrieveData( nextRetrieve );
			setLists( nextLists );
			setLocalListId(
				( currentValue ) =>
					currentValue || nextRetrieve.send_list_id || ''
			);
		} catch ( error ) {
			const message = getErrorMessage( error );
			setErrorMessage( message );
			createErrorNotice?.( message, { type: 'snackbar' } );
		} finally {
			setIsLoading( false );
		}
	}, [ canUseListmonkApi, createErrorNotice, editorData.postId ] );

	const refreshPreview = useCallback( async () => {
		if ( ! canUseListmonkApi || ! editorData.postId ) {
			return;
		}

		setIsPreviewing( true );
		setErrorMessage( '' );

		try {
			const listId = Number.parseInt( selectedListId, 10 );
			const request: NewsletterPreviewCreateRequest = {
				postId: editorData.postId,
				...( Number.isFinite( listId ) && listId > 0
					? { listIds: [ listId ] }
					: {} ),
			};
			const result = await createPreviewResource( request );
			setPreview(
				unwrapEndpointData<
					NewsletterPreviewCreateRequest,
					NewsletterPreviewResponse
				>( result )
			);
		} catch ( error ) {
			const message = getErrorMessage( error );
			setErrorMessage( message );
			createErrorNotice?.( message, { type: 'snackbar' } );
		} finally {
			setIsPreviewing( false );
		}
	}, [
		canUseListmonkApi,
		createErrorNotice,
		editorData.postId,
		selectedListId,
	] );

	useEffect( () => {
		if ( ! canUseListmonkApi ) {
			return;
		}

		void refreshRetrieveAndLists();
	}, [ canUseListmonkApi, refreshRetrieveAndLists ] );

	useEffect( () => {
		if ( ! canUseListmonkApi || ! selectedListId ) {
			return;
		}

		void refreshPreview();
	}, [ canUseListmonkApi, refreshPreview, selectedListId ] );

	useEffect( () => {
		if ( ! canUseListmonkApi ) {
			return;
		}

		if ( ! campaignId ) {
			setAnalytics( undefined );
			setAnalyticsError( '' );
			setLastAutoAnalyticsCampaignId( '' );
			return;
		}

		if ( campaignId === lastAutoAnalyticsCampaignId ) {
			return;
		}

		setLastAutoAnalyticsCampaignId( campaignId );
		void refreshAnalytics( campaignId );
	}, [
		campaignId,
		canUseListmonkApi,
		lastAutoAnalyticsCampaignId,
		refreshAnalytics,
	] );

	const handleListChange = useCallback(
		( nextListId: string ) => {
			setLocalListId( nextListId );
			editPost?.( {
				meta: {
					...editorData.meta,
					send_list_id: nextListId,
				},
			} );
		},
		[ editPost, editorData.meta ]
	);

	const handleSync = useCallback( async () => {
		if ( ! editorData.postId ) {
			return;
		}

		setIsSyncing( true );
		setErrorMessage( '' );

		try {
			await saveCurrentPost();
			const result = await createSyncResource( {
				postId: editorData.postId,
			} );
			const data = unwrapEndpointData<
				NewsletterSyncCreateRequest,
				NewsletterSyncResponse
			>( result );
			setRetrieveData( data.retrieve );
			await refreshPreview();
			await refreshAnalytics(
				getCampaignIdFromRetrieve( data.retrieve )
			);
			createSuccessNotice?.( data.message, { type: 'snackbar' } );
		} catch ( error ) {
			const message = getErrorMessage( error );
			setErrorMessage( message );
			createErrorNotice?.( message, { type: 'snackbar' } );
		} finally {
			setIsSyncing( false );
		}
	}, [
		createErrorNotice,
		createSuccessNotice,
		editorData.postId,
		refreshAnalytics,
		refreshPreview,
		saveCurrentPost,
	] );

	const handleRetrySend = useCallback( async () => {
		if ( ! editorData.postId ) {
			return;
		}

		setIsRetryingSend( true );
		setErrorMessage( '' );

		try {
			const result = await createSyncResource( {
				postId: editorData.postId,
				retrySend: true,
			} );
			const data = unwrapEndpointData<
				NewsletterSyncCreateRequest,
				NewsletterSyncResponse
			>( result );
			setRetrieveData( data.retrieve );
			await refreshPreview();
			await refreshAnalytics(
				getCampaignIdFromRetrieve( data.retrieve )
			);
			createSuccessNotice?.( data.message, { type: 'snackbar' } );
		} catch ( error ) {
			const message = getErrorMessage( error );
			setErrorMessage( message );
			createErrorNotice?.( message, { type: 'snackbar' } );
			try {
				setRetrieveData( await fetchRetrieve( editorData.postId ) );
			} catch {
				// Keep the direct REST error visible if retrieve also fails.
			}
		} finally {
			setIsRetryingSend( false );
		}
	}, [
		createErrorNotice,
		createSuccessNotice,
		editorData.postId,
		refreshAnalytics,
		refreshPreview,
	] );

	const handleSendTest = useCallback( async () => {
		if ( ! editorData.postId || ! testEmail.trim() ) {
			const message = __(
				'Enter at least one test email address.',
				'wp-typia-newsletter-connector'
			);
			setErrorMessage( message );
			createErrorNotice?.( message, { type: 'snackbar' } );
			return;
		}

		setIsTesting( true );
		setErrorMessage( '' );

		try {
			await saveCurrentPost();
			const result = await sendTestEmail( editorData.postId, testEmail );
			const nextRetrieve = await fetchRetrieve( editorData.postId );
			setRetrieveData( nextRetrieve );
			await refreshAnalytics( getCampaignIdFromRetrieve( nextRetrieve ) );
			createSuccessNotice?.( result.message, { type: 'snackbar' } );
		} catch ( error ) {
			const message = getErrorMessage( error );
			setErrorMessage( message );
			createErrorNotice?.( message, { type: 'snackbar' } );
		} finally {
			setIsTesting( false );
		}
	}, [
		createErrorNotice,
		createSuccessNotice,
		editorData.postId,
		refreshAnalytics,
		saveCurrentPost,
		testEmail,
	] );

	if ( ! isActiveEditor ) {
		return null;
	}

	if ( ! isServiceProviderConfigured ) {
		const settingsUrl = getConnectorEditorData().settingsUrl;

		return createElement( PluginDocumentSettingPanel, {
			children: createElement( Notice, {
				children: [
					createElement(
						'p',
						{ key: 'message' },
						__(
							'Configure Listmonk settings before syncing newsletters.',
							'wp-typia-newsletter-connector'
						)
					),
					settingsUrl
						? createElement(
								Button,
								{
									href: settingsUrl,
									key: 'settings-link',
									variant: 'secondary',
								},
								__(
									'Open Listmonk settings',
									'wp-typia-newsletter-connector'
								)
						  )
						: null,
				],
				isDismissible: false,
				status: 'warning',
			} ),
			className: 'wp-typia-newsletter-connector-panel',
			name: 'wp-typia-newsletter-connector',
			title: __( 'Listmonk', 'wp-typia-newsletter-connector' ),
		} );
	}

	const isBusy =
		editorData.isSaving ||
		isLoading ||
		isPreviewing ||
		isSyncing ||
		isRetryingSend ||
		isTesting;
	const payloadPreview = preview?.listmonkPayload
		? JSON.stringify( preview.listmonkPayload, null, 2 )
		: '';
	const analyticsTotals = analytics?.totals ?? EMPTY_ANALYTICS_TOTALS;
	const topAnalyticsLinks = [ ...( analytics?.links ?? [] ) ]
		.sort( ( firstLink, secondLink ) => secondLink.count - firstLink.count )
		.slice( 0, 5 );

	return createElement( PluginDocumentSettingPanel, {
		children: [
			errorMessage
				? createElement( Notice, {
						children: errorMessage,
						isDismissible: false,
						key: 'error',
						status: 'error',
				  } )
				: null,
			createElement(
				'div',
				{
					className: 'wp-typia-newsletter-connector-panel__status',
					key: 'status',
				},
				createElement(
					'div',
					null,
					createElement(
						'span',
						null,
						__( 'Campaign', 'wp-typia-newsletter-connector' )
					),
					createElement( 'strong', null, campaignId || '-' )
				),
				createElement(
					'div',
					null,
					createElement(
						'span',
						null,
						__( 'Status', 'wp-typia-newsletter-connector' )
					),
					createElement( 'strong', null, lastStatus || '-' )
				),
				createElement(
					'div',
					null,
					createElement(
						'span',
						null,
						__( 'Last sync', 'wp-typia-newsletter-connector' )
					),
					createElement(
						'strong',
						null,
						retrieveData?.listmonk_last_synced_at || '-'
					)
				)
			),
			retrieveData?.listmonk_last_error
				? createElement( Notice, {
						children: [
							createElement(
								'p',
								{ key: 'message' },
								retrieveData.listmonk_last_error
							),
							canRetrySend
								? createElement(
										Button,
										{
											disabled: isBusy,
											isBusy: isRetryingSend,
											key: 'retry-send',
											onClick: () =>
												void handleRetrySend(),
											variant: 'secondary',
										},
										__(
											'Retry send',
											'wp-typia-newsletter-connector'
										)
								  )
								: null,
						],
						isDismissible: false,
						key: 'last-error',
						status: 'warning',
				  } )
				: null,
			createElement(
				'div',
				{
					className: 'wp-typia-newsletter-connector-panel__analytics',
					key: 'analytics',
				},
				createElement(
					'div',
					{
						className:
							'wp-typia-newsletter-connector-panel__analytics-header',
					},
					createElement(
						'strong',
						null,
						__( 'Analytics', 'wp-typia-newsletter-connector' )
					),
					isLoadingAnalytics ? createElement( Spinner ) : null
				),
				! campaignId
					? createElement(
							'p',
							{
								className:
									'wp-typia-newsletter-connector-panel__muted',
							},
							__(
								'Sync to Listmonk to view analytics.',
								'wp-typia-newsletter-connector'
							)
					  )
					: [
							analyticsError
								? createElement( Notice, {
										children: analyticsError,
										isDismissible: false,
										key: 'analytics-error',
										status: 'warning',
								  } )
								: null,
							createElement(
								'div',
								{
									className:
										'wp-typia-newsletter-connector-panel__analytics-range',
									key: 'analytics-range',
								},
								createElement( TextControl, {
									disabled: isBusy || isLoadingAnalytics,
									label: __(
										'From',
										'wp-typia-newsletter-connector'
									),
									onChange: setAnalyticsRangeFrom,
									type: 'date',
									value: analyticsRangeFrom,
								} ),
								createElement( TextControl, {
									disabled: isBusy || isLoadingAnalytics,
									label: __(
										'To',
										'wp-typia-newsletter-connector'
									),
									onChange: setAnalyticsRangeTo,
									type: 'date',
									value: analyticsRangeTo,
								} )
							),
							createElement(
								Button,
								{
									className:
										'wp-typia-newsletter-connector-panel__full-button',
									disabled:
										isBusy ||
										isLoadingAnalytics ||
										! analyticsRangeFrom ||
										! analyticsRangeTo,
									isBusy: isLoadingAnalytics,
									key: 'refresh-analytics',
									onClick: () => void refreshAnalytics(),
									variant: 'secondary',
								},
								__(
									'Refresh analytics',
									'wp-typia-newsletter-connector'
								)
							),
							createElement(
								'div',
								{
									className:
										'wp-typia-newsletter-connector-panel__analytics-metrics',
									key: 'analytics-metrics',
								},
								[
									renderAnalyticsMetric(
										__(
											'Sent',
											'wp-typia-newsletter-connector'
										),
										analyticsTotals.sent
									),
									renderAnalyticsMetric(
										__(
											'To send',
											'wp-typia-newsletter-connector'
										),
										analyticsTotals.toSend
									),
									renderAnalyticsMetric(
										__(
											'Views',
											'wp-typia-newsletter-connector'
										),
										analyticsTotals.views
									),
									renderAnalyticsMetric(
										__(
											'Clicks',
											'wp-typia-newsletter-connector'
										),
										analyticsTotals.clicks
									),
									renderAnalyticsMetric(
										__(
											'Bounces',
											'wp-typia-newsletter-connector'
										),
										analyticsTotals.bounces
									),
								]
							),
							createElement(
								'div',
								{
									className:
										'wp-typia-newsletter-connector-panel__analytics-links',
									key: 'analytics-links',
								},
								createElement(
									'div',
									{
										className:
											'wp-typia-newsletter-connector-panel__analytics-subtitle',
									},
									__(
										'Top links',
										'wp-typia-newsletter-connector'
									)
								),
								topAnalyticsLinks.length > 0
									? topAnalyticsLinks.map( ( link, index ) =>
											createElement(
												'div',
												{
													className:
														'wp-typia-newsletter-connector-panel__analytics-link',
													key: `${ link.url }-${ index }`,
												},
												createElement(
													'span',
													null,
													link.url
												),
												createElement(
													'strong',
													null,
													formatNumber( link.count )
												)
											)
									  )
									: createElement(
											'p',
											{
												className:
													'wp-typia-newsletter-connector-panel__muted',
											},
											__(
												'No tracked links for this range.',
												'wp-typia-newsletter-connector'
											)
									  )
							),
							analytics?.checkedAt
								? createElement(
										'p',
										{
											className:
												'wp-typia-newsletter-connector-panel__muted',
											key: 'analytics-checked-at',
										},
										`${ __(
											'Checked',
											'wp-typia-newsletter-connector'
										) } ${ analytics.checkedAt }`
								  )
								: null,
					  ]
			),
			createElement( SelectControl, {
				disabled: isBusy || listOptions.length <= 1,
				key: 'list',
				label: __( 'List', 'wp-typia-newsletter-connector' ),
				onChange: handleListChange,
				options: listOptions,
				value: selectedListId,
			} ),
			createElement(
				'div',
				{
					className: 'wp-typia-newsletter-connector-panel__actions',
					key: 'actions',
				},
				createElement(
					Button,
					{
						disabled: isBusy,
						isBusy: isLoading || isPreviewing,
						onClick: () => {
							void refreshRetrieveAndLists();
							void refreshPreview();
						},
						variant: 'secondary',
					},
					__( 'Refresh', 'wp-typia-newsletter-connector' )
				),
				createElement(
					Button,
					{
						disabled: isBusy || ! selectedListId,
						isBusy: isSyncing,
						onClick: () => void handleSync(),
						variant: 'primary',
					},
					__( 'Sync', 'wp-typia-newsletter-connector' )
				)
			),
			createElement( TextControl, {
				disabled: isBusy,
				key: 'test-email',
				label: __( 'Test email', 'wp-typia-newsletter-connector' ),
				onChange: setTestEmail,
				value: testEmail,
			} ),
			createElement(
				Button,
				{
					className: 'wp-typia-newsletter-connector-panel__full-button',
					disabled: isBusy || ! testEmail.trim(),
					isBusy: isTesting,
					key: 'send-test',
					onClick: () => void handleSendTest(),
					variant: 'secondary',
				},
				__( 'Send test', 'wp-typia-newsletter-connector' )
			),
			renderMergeTagHelpers(),
			isLoading || isPreviewing
				? createElement(
						'div',
						{
							className:
								'wp-typia-newsletter-connector-panel__loading',
							key: 'loading',
						},
						createElement( Spinner )
				  )
				: null,
			createElement( TextareaControl, {
				disabled: true,
				key: 'raw-html',
				label: __( 'Raw HTML preview', 'wp-typia-newsletter-connector' ),
				onChange: () => {},
				rows: 8,
				value: preview?.rawHtml || '',
			} ),
			createElement( TextareaControl, {
				disabled: true,
				key: 'payload',
				label: __( 'Listmonk payload', 'wp-typia-newsletter-connector' ),
				onChange: () => {},
				rows: 8,
				value: payloadPreview,
			} ),
		],
		className: 'wp-typia-newsletter-connector-panel',
		name: 'wp-typia-newsletter-connector',
		title: __( 'Listmonk', 'wp-typia-newsletter-connector' ),
	} );
}

registerPlugin( 'wp-typia-newsletter-connector', {
	render: ListmonkPanel,
} );
