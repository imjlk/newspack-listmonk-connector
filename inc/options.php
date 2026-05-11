<?php
/**
 * Settings helpers.
 *
 * @package Newspack_Listmonk_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the option name used for Listmonk settings.
 *
 * @return string
 */
function newspack_listmonk_connector_get_option_name() {
	return 'newspack_listmonk_connector_settings';
}

/**
 * Default settings.
 *
 * @return array
 */
function newspack_listmonk_connector_get_default_settings() {
	return array(
		'base_url'            => '',
		'api_user'            => '',
		'api_token'           => '',
		'default_from_email'  => '',
		'default_template_id' => 0,
		'default_list_ids'    => array(),
		'send_mode'           => 'campaign',
	);
}

/**
 * Normalize comma-separated or array list IDs.
 *
 * @param mixed $value Raw list IDs.
 * @return int[]
 */
function newspack_listmonk_connector_normalize_list_ids( $value ) {
	if ( is_string( $value ) ) {
		$value = preg_split( '/[,\s]+/', $value );
	}
	if ( ! is_array( $value ) ) {
		return array();
	}
	$ids = array_map( 'absint', $value );
	$ids = array_filter(
		$ids,
		static function ( $id ) {
			return 0 < $id;
		}
	);

	return array_values( array_unique( $ids ) );
}

/**
 * Sanitize settings while preserving the existing API token when the payload
 * intentionally leaves it blank.
 *
 * @param array $settings Raw settings.
 * @param array $existing Existing settings.
 * @return array
 */
function newspack_listmonk_connector_sanitize_settings( array $settings, array $existing = array() ) {
	$defaults = newspack_listmonk_connector_get_default_settings();
	$existing = wp_parse_args( $existing, $defaults );

	$sanitized = array(
		'base_url'            => isset( $settings['base_url'] ) ? esc_url_raw( trim( (string) $settings['base_url'] ) ) : $existing['base_url'],
		'api_user'            => isset( $settings['api_user'] ) ? sanitize_text_field( (string) $settings['api_user'] ) : $existing['api_user'],
		'api_token'           => array_key_exists( 'api_token', $settings ) && '' !== (string) $settings['api_token'] ? sanitize_text_field( (string) $settings['api_token'] ) : $existing['api_token'],
		'default_from_email'  => isset( $settings['default_from_email'] ) ? sanitize_text_field( (string) $settings['default_from_email'] ) : $existing['default_from_email'],
		'default_template_id' => isset( $settings['default_template_id'] ) ? absint( $settings['default_template_id'] ) : absint( $existing['default_template_id'] ),
		'default_list_ids'    => array_key_exists( 'default_list_ids', $settings ) ? newspack_listmonk_connector_normalize_list_ids( $settings['default_list_ids'] ) : newspack_listmonk_connector_normalize_list_ids( $existing['default_list_ids'] ),
		'send_mode'           => 'campaign',
	);

	if ( '' !== $sanitized['base_url'] ) {
		$sanitized['base_url'] = untrailingslashit( $sanitized['base_url'] );
	}

	return wp_parse_args( $sanitized, $defaults );
}

/**
 * Get stored settings with optional secret masking and constant overrides.
 *
 * @param bool $include_secret Whether to include the API token.
 * @return array
 */
function newspack_listmonk_connector_get_settings( $include_secret = true ) {
	$settings = get_option( newspack_listmonk_connector_get_option_name(), array() );
	$settings = is_array( $settings ) ? $settings : array();
	$settings = wp_parse_args( $settings, newspack_listmonk_connector_get_default_settings() );

	if ( defined( 'NEWSPACK_LISTMONK_CONNECTOR_BASE_URL' ) && NEWSPACK_LISTMONK_CONNECTOR_BASE_URL ) {
		$settings['base_url'] = untrailingslashit( esc_url_raw( NEWSPACK_LISTMONK_CONNECTOR_BASE_URL ) );
	}
	if ( defined( 'NEWSPACK_LISTMONK_CONNECTOR_API_USER' ) && NEWSPACK_LISTMONK_CONNECTOR_API_USER ) {
		$settings['api_user'] = sanitize_text_field( NEWSPACK_LISTMONK_CONNECTOR_API_USER );
	}
	if ( defined( 'NEWSPACK_LISTMONK_CONNECTOR_API_TOKEN' ) && NEWSPACK_LISTMONK_CONNECTOR_API_TOKEN ) {
		$settings['api_token'] = sanitize_text_field( NEWSPACK_LISTMONK_CONNECTOR_API_TOKEN );
	}

	$settings['default_template_id'] = absint( $settings['default_template_id'] );
	$settings['default_list_ids']    = newspack_listmonk_connector_normalize_list_ids( $settings['default_list_ids'] );
	$settings['send_mode']           = 'campaign';

	if ( ! $include_secret ) {
		$settings['has_api_token'] = ! empty( $settings['api_token'] );
		unset( $settings['api_token'] );
	}

	return $settings;
}

/**
 * Save settings.
 *
 * @param array $settings Raw settings.
 * @return array Saved settings.
 */
function newspack_listmonk_connector_save_settings( array $settings ) {
	$existing = newspack_listmonk_connector_get_settings( true );
	$settings = newspack_listmonk_connector_sanitize_settings( $settings, $existing );
	update_option( newspack_listmonk_connector_get_option_name(), $settings, false );

	return $settings;
}

/**
 * Build the public settings shape used by REST and admin screens.
 *
 * @param array|null $settings Optional settings.
 * @return array
 */
function newspack_listmonk_connector_get_public_settings_response( $settings = null ) {
	$settings = is_array( $settings ) ? $settings : newspack_listmonk_connector_get_settings( true );

	return array(
		'baseUrl'           => (string) ( $settings['base_url'] ?? '' ),
		'apiUser'           => (string) ( $settings['api_user'] ?? '' ),
		'hasApiToken'       => ! empty( $settings['api_token'] ),
		'defaultFromEmail'  => (string) ( $settings['default_from_email'] ?? '' ),
		'defaultTemplateId' => absint( $settings['default_template_id'] ?? 0 ),
		'defaultListIds'    => newspack_listmonk_connector_normalize_list_ids( $settings['default_list_ids'] ?? array() ),
		'sendMode'          => 'campaign',
	);
}
