/* @jsxRuntime classic */
/* @jsx createElement */
import { Button, Notice, Spinner, TextControl } from '@wordpress/components';
import {
	createElement,
	createRoot,
	useCallback,
	useEffect,
	useMemo,
	useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import type { EndpointValidationResult } from '@wp-typia/rest';

import './style.scss';

import type { ListmonkSettingsResponse } from '../types';
import type { ListmonkSettingsCreateRequest } from '../rest/listmonk-settings/api-types';
import {
	createResource as createSettingsResource,
	readResource as readSettingsResource,
} from '../rest/listmonk-settings/api';

type SettingsForm = {
	apiToken: string;
	apiUser: string;
	baseUrl: string;
	defaultFromEmail: string;
	defaultListIds: string;
	defaultTemplateId: string;
	hasApiToken: boolean;
};

type NoticeState = {
	message: string;
	status: 'error' | 'success' | 'warning';
};

const EMPTY_FORM: SettingsForm = {
	apiToken: '',
	apiUser: '',
	baseUrl: '',
	defaultFromEmail: '',
	defaultListIds: '',
	defaultTemplateId: '0',
	hasApiToken: false,
};

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
					'connector-for-newspack-newsletters-and-listmonk'
			  )
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

	return __( 'Something went wrong.', 'connector-for-newspack-newsletters-and-listmonk' );
}

function formFromResponse( response: ListmonkSettingsResponse ): SettingsForm {
	return {
		apiToken: '',
		apiUser: response.apiUser,
		baseUrl: response.baseUrl,
		defaultFromEmail: response.defaultFromEmail ?? '',
		defaultListIds: response.defaultListIds.join( ', ' ),
		defaultTemplateId: String( response.defaultTemplateId ?? 0 ),
		hasApiToken: response.hasApiToken,
	};
}

function parseTemplateId( rawValue: string ): number {
	const value = Number.parseInt( rawValue, 10 );
	return Number.isInteger( value ) && value > 0 ? value : 0;
}

function parseListIds( rawValue: string ): number[] {
	const ids = rawValue
		.split( /[,\s]+/ )
		.map( ( value ) => Number.parseInt( value, 10 ) )
		.filter( ( value ) => Number.isInteger( value ) && value > 0 );

	return [ ...new Set( ids ) ];
}

function SettingsApp() {
	const [ form, setForm ] = useState< SettingsForm >( EMPTY_FORM );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ notice, setNotice ] = useState< NoticeState | null >( null );

	useEffect( () => {
		let isMounted = true;

		async function loadSettings() {
			setIsLoading( true );
			try {
				const response = unwrapEndpointData(
					await readSettingsResource( {} )
				);
				if ( isMounted ) {
					setForm( formFromResponse( response ) );
					setNotice( null );
				}
			} catch ( error ) {
				if ( isMounted ) {
					setNotice( {
						message: getErrorMessage( error ),
						status: 'error',
					} );
				}
			} finally {
				if ( isMounted ) {
					setIsLoading( false );
				}
			}
		}

		loadSettings();

		return () => {
			isMounted = false;
		};
	}, [] );

	const updateField = useCallback(
		( key: keyof SettingsForm ) => ( value: string ) => {
			setForm( ( current ) => ( {
				...current,
				[ key ]: value,
			} ) );
		},
		[]
	);

	const tokenHelp = useMemo( () => {
		if ( form.apiToken.trim().length > 0 ) {
			return __(
				'This token will replace the saved token.',
				'connector-for-newspack-newsletters-and-listmonk'
			);
		}

		return form.hasApiToken
			? __(
					'A token is saved. Leave this blank to keep it unchanged.',
					'connector-for-newspack-newsletters-and-listmonk'
			  )
			: __(
					'Paste a Listmonk API token.',
					'connector-for-newspack-newsletters-and-listmonk'
			  );
	}, [ form.apiToken, form.hasApiToken ] );

	const saveSettings = useCallback(
		async ( testConnection: boolean ) => {
			setIsSaving( true );
			setNotice( null );

			const request: ListmonkSettingsCreateRequest = {
				apiUser: form.apiUser.trim(),
				baseUrl: form.baseUrl.trim(),
				defaultFromEmail: form.defaultFromEmail.trim(),
				defaultListIds: parseListIds( form.defaultListIds ),
				defaultTemplateId: parseTemplateId( form.defaultTemplateId ),
				testConnection,
			};
			const apiToken = form.apiToken.trim();
			if ( apiToken.length > 0 ) {
				request.apiToken = apiToken;
			}

			try {
				const response = unwrapEndpointData(
					await createSettingsResource( request )
				);
				setForm( formFromResponse( response ) );

				if ( response.connection ) {
					setNotice( {
						message: response.connection.message,
						status: response.connection.ok ? 'success' : 'error',
					} );
				} else {
					setNotice( {
						message: __(
							'Listmonk settings saved.',
							'connector-for-newspack-newsletters-and-listmonk'
						),
						status: 'success',
					} );
				}
			} catch ( error ) {
				setNotice( {
					message: getErrorMessage( error ),
					status: 'error',
				} );
			} finally {
				setIsSaving( false );
			}
		},
		[ form ]
	);

	return (
		<div className="connector-for-newspack-newsletters-and-listmonk-settings">
			{ notice && (
				<Notice status={ notice.status } isDismissible={ false }>
					{ notice.message }
				</Notice>
			) }
			{ isLoading ? (
				<div className="connector-for-newspack-newsletters-and-listmonk-settings__loading">
					<Spinner />
					<span>
						{ __(
							'Loading Listmonk settings…',
							'connector-for-newspack-newsletters-and-listmonk'
						) }
					</span>
				</div>
			) : (
				<div className="connector-for-newspack-newsletters-and-listmonk-settings__form">
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __(
							'Listmonk API URL',
							'connector-for-newspack-newsletters-and-listmonk'
						) }
						onChange={ updateField( 'baseUrl' ) }
						placeholder="https://listmonk.example.com"
						type="url"
						value={ form.baseUrl }
					/>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						autoComplete="off"
						label={ __(
							'API user',
							'connector-for-newspack-newsletters-and-listmonk'
						) }
						onChange={ updateField( 'apiUser' ) }
						value={ form.apiUser }
					/>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						autoComplete="new-password"
						help={ tokenHelp }
						label={ __(
							'API token',
							'connector-for-newspack-newsletters-and-listmonk'
						) }
						onChange={ updateField( 'apiToken' ) }
						type="password"
						value={ form.apiToken }
					/>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __(
							'Default From email',
							'connector-for-newspack-newsletters-and-listmonk'
						) }
						onChange={ updateField( 'defaultFromEmail' ) }
						placeholder="Newsroom <news@example.com>"
						value={ form.defaultFromEmail }
					/>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __(
							'Default template ID',
							'connector-for-newspack-newsletters-and-listmonk'
						) }
						min={ 0 }
						onChange={ updateField( 'defaultTemplateId' ) }
						type="number"
						value={ form.defaultTemplateId }
					/>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						help={ __(
							'Separate multiple Listmonk list IDs with commas.',
							'connector-for-newspack-newsletters-and-listmonk'
						) }
						label={ __(
							'Default list IDs',
							'connector-for-newspack-newsletters-and-listmonk'
						) }
						onChange={ updateField( 'defaultListIds' ) }
						placeholder="1, 2"
						value={ form.defaultListIds }
					/>
					<div className="connector-for-newspack-newsletters-and-listmonk-settings__actions">
						<Button
							disabled={ isSaving }
							isBusy={ isSaving }
							onClick={ () => saveSettings( false ) }
							variant="primary"
						>
							{ __(
								'Save settings',
								'connector-for-newspack-newsletters-and-listmonk'
							) }
						</Button>
						<Button
							disabled={ isSaving }
							onClick={ () => saveSettings( true ) }
							variant="secondary"
						>
							{ __(
								'Save and test connection',
								'connector-for-newspack-newsletters-and-listmonk'
							) }
						</Button>
					</div>
					<p className="connector-for-newspack-newsletters-and-listmonk-settings__token-status">
						{ sprintf(
							/* translators: %s is whether an API token is saved. */
							__(
								'API token saved: %s',
								'connector-for-newspack-newsletters-and-listmonk'
							),
							form.hasApiToken
								? __( 'yes', 'connector-for-newspack-newsletters-and-listmonk' )
								: __( 'no', 'connector-for-newspack-newsletters-and-listmonk' )
						) }
					</p>
				</div>
			) }
		</div>
	);
}

const rootElement = document.getElementById(
	'connector-for-newspack-newsletters-and-listmonk-settings-root'
);

if ( rootElement ) {
	createRoot( rootElement ).render( <SettingsApp /> );
}
