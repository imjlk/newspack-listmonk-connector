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
	postType: string;
	isSaving: boolean;
};

type NewspackEmailEditorData = {
	current_post_type?: string;
	newsletter_post_type?: string;
};

type NewspackNewslettersData = {
	service_provider?: string;
	user_test_emails?: string[] | string;
};

type WindowWithNewspack = typeof window & {
	newspack_email_editor_data?: NewspackEmailEditorData;
	newspack_newsletters_data?: NewspackNewslettersData;
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

	return __( 'Something went wrong.', 'newspack-listmonk-connector' );
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
					'newspack-listmonk-connector'
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

function ListmonkPanel() {
	const editorData = useSelect( ( select ) => {
		const editor = select( editorStore ) as EditorSelect;
		return {
			meta: normalizePostMeta(
				editor.getEditedPostAttribute?.( 'meta' )
			),
			postId: Number( editor.getCurrentPostId?.() ?? 0 ),
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
	const [ isLoading, setIsLoading ] = useState( false );
	const [ isPreviewing, setIsPreviewing ] = useState( false );
	const [ isSyncing, setIsSyncing ] = useState( false );
	const [ isTesting, setIsTesting ] = useState( false );

	const isActiveEditor = isListmonkNewsletterEditor( editorData.postType );
	const selectedListId = getSelectedListId(
		editorData.meta,
		retrieveData,
		localListId
	);
	const campaignId =
		retrieveData?.listmonk_campaign_id || retrieveData?.campaign_id || '';
	const lastStatus = retrieveData?.listmonk_last_status || '';

	const listOptions = useMemo(
		() => [
			{
				label: __(
					'Select a Listmonk list',
					'newspack-listmonk-connector'
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

	const refreshRetrieveAndLists = useCallback( async () => {
		if ( ! editorData.postId ) {
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
	}, [ createErrorNotice, editorData.postId ] );

	const refreshPreview = useCallback( async () => {
		if ( ! editorData.postId ) {
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
	}, [ createErrorNotice, editorData.postId, selectedListId ] );

	useEffect( () => {
		if ( ! isActiveEditor ) {
			return;
		}

		void refreshRetrieveAndLists();
	}, [ isActiveEditor, refreshRetrieveAndLists ] );

	useEffect( () => {
		if ( ! isActiveEditor || ! selectedListId ) {
			return;
		}

		void refreshPreview();
	}, [ isActiveEditor, refreshPreview, selectedListId ] );

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
		refreshPreview,
		saveCurrentPost,
	] );

	const handleSendTest = useCallback( async () => {
		if ( ! editorData.postId || ! testEmail.trim() ) {
			const message = __(
				'Enter at least one test email address.',
				'newspack-listmonk-connector'
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
		saveCurrentPost,
		testEmail,
	] );

	if ( ! isActiveEditor ) {
		return null;
	}

	const isBusy =
		editorData.isSaving ||
		isLoading ||
		isPreviewing ||
		isSyncing ||
		isTesting;
	const payloadPreview = preview?.listmonkPayload
		? JSON.stringify( preview.listmonkPayload, null, 2 )
		: '';

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
					className: 'newspack-listmonk-connector-panel__status',
					key: 'status',
				},
				createElement(
					'div',
					null,
					createElement(
						'span',
						null,
						__( 'Campaign', 'newspack-listmonk-connector' )
					),
					createElement( 'strong', null, campaignId || '-' )
				),
				createElement(
					'div',
					null,
					createElement(
						'span',
						null,
						__( 'Status', 'newspack-listmonk-connector' )
					),
					createElement( 'strong', null, lastStatus || '-' )
				),
				createElement(
					'div',
					null,
					createElement(
						'span',
						null,
						__( 'Last sync', 'newspack-listmonk-connector' )
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
						children: retrieveData.listmonk_last_error,
						isDismissible: false,
						key: 'last-error',
						status: 'warning',
				  } )
				: null,
			createElement( SelectControl, {
				disabled: isBusy || listOptions.length <= 1,
				key: 'list',
				label: __( 'List', 'newspack-listmonk-connector' ),
				onChange: handleListChange,
				options: listOptions,
				value: selectedListId,
			} ),
			createElement(
				'div',
				{
					className: 'newspack-listmonk-connector-panel__actions',
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
					__( 'Refresh', 'newspack-listmonk-connector' )
				),
				createElement(
					Button,
					{
						disabled: isBusy || ! selectedListId,
						isBusy: isSyncing,
						onClick: () => void handleSync(),
						variant: 'primary',
					},
					__( 'Sync', 'newspack-listmonk-connector' )
				)
			),
			createElement( TextControl, {
				disabled: isBusy,
				key: 'test-email',
				label: __( 'Test email', 'newspack-listmonk-connector' ),
				onChange: setTestEmail,
				value: testEmail,
			} ),
			createElement(
				Button,
				{
					className: 'newspack-listmonk-connector-panel__full-button',
					disabled: isBusy || ! testEmail.trim(),
					isBusy: isTesting,
					key: 'send-test',
					onClick: () => void handleSendTest(),
					variant: 'secondary',
				},
				__( 'Send test', 'newspack-listmonk-connector' )
			),
			isLoading || isPreviewing
				? createElement(
						'div',
						{
							className:
								'newspack-listmonk-connector-panel__loading',
							key: 'loading',
						},
						createElement( Spinner )
				  )
				: null,
			createElement( TextareaControl, {
				disabled: true,
				key: 'raw-html',
				label: __( 'Raw HTML preview', 'newspack-listmonk-connector' ),
				onChange: () => {},
				rows: 8,
				value: preview?.rawHtml || '',
			} ),
			createElement( TextareaControl, {
				disabled: true,
				key: 'payload',
				label: __( 'Listmonk payload', 'newspack-listmonk-connector' ),
				onChange: () => {},
				rows: 8,
				value: payloadPreview,
			} ),
		],
		className: 'newspack-listmonk-connector-panel',
		name: 'newspack-listmonk-connector',
		title: __( 'Listmonk', 'newspack-listmonk-connector' ),
	} );
}

registerPlugin( 'newspack-listmonk-connector', {
	render: ListmonkPanel,
} );
