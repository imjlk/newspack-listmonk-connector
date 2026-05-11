<?php
/**
 * REST resource: Listmonk settings.
 *
 * @package Newspack_Listmonk_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! function_exists( 'newspack_listmonk_connector_listmonk_settings_load_rest_resource_schema' ) ) {
	/**
	 * Load a generated REST schema.
	 *
	 * @param string $schema_name Schema name.
	 * @return array|null
	 */
	function newspack_listmonk_connector_listmonk_settings_load_rest_resource_schema( $schema_name ) {
		$project_root = dirname( __DIR__, 2 );
		$schema_path  = $project_root . '/src/rest/listmonk-settings/api-schemas/' . $schema_name . '.schema.json';
		if ( ! file_exists( $schema_path ) ) {
			return null;
		}

		$decoded = json_decode( file_get_contents( $schema_path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return is_array( $decoded ) ? $decoded : null;
	}
}

if ( ! function_exists( 'newspack_listmonk_connector_listmonk_settings_sanitize_rest_resource_schema' ) ) {
	/**
	 * Make generated JSON schema usable by WordPress REST validation.
	 *
	 * @param mixed $schema Schema.
	 * @return mixed
	 */
	function newspack_listmonk_connector_listmonk_settings_sanitize_rest_resource_schema( $schema ) {
		if ( ! is_array( $schema ) ) {
			return $schema;
		}

		unset( $schema['$schema'], $schema['title'] );

		if ( isset( $schema['properties'] ) && is_array( $schema['properties'] ) ) {
			foreach ( $schema['properties'] as $key => $property_schema ) {
				$schema['properties'][ $key ] = newspack_listmonk_connector_listmonk_settings_sanitize_rest_resource_schema( $property_schema );
			}
		}

		if ( isset( $schema['items'] ) && is_array( $schema['items'] ) ) {
			$schema['items'] = newspack_listmonk_connector_listmonk_settings_sanitize_rest_resource_schema( $schema['items'] );
		}

		return $schema;
	}
}

if ( ! function_exists( 'newspack_listmonk_connector_listmonk_settings_validate_rest_resource_payload' ) ) {
	/**
	 * Validate a payload against a generated schema.
	 *
	 * @param mixed  $value Payload.
	 * @param string $schema_name Schema name.
	 * @param string $param_name Param name.
	 * @return mixed|WP_Error
	 */
	function newspack_listmonk_connector_listmonk_settings_validate_rest_resource_payload( $value, $schema_name, $param_name ) {
		$schema = newspack_listmonk_connector_listmonk_settings_load_rest_resource_schema( $schema_name );
		if ( ! is_array( $schema ) ) {
			return new WP_Error( 'missing_schema', __( 'Missing REST schema.', 'newspack-listmonk-connector' ), array( 'status' => 500 ) );
		}

		$rest_schema = newspack_listmonk_connector_listmonk_settings_sanitize_rest_resource_schema( $schema );
		$validation  = rest_validate_value_from_schema( $value, $rest_schema, $param_name );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		return rest_sanitize_value_from_schema( $value, $rest_schema, $param_name );
	}
}

if ( ! function_exists( 'newspack_listmonk_connector_listmonk_settings_can_manage_rest_resource' ) ) {
	/**
	 * Permission callback.
	 *
	 * @return bool
	 */
	function newspack_listmonk_connector_listmonk_settings_can_manage_rest_resource() {
		return current_user_can( 'manage_options' );
	}
}

if ( ! function_exists( 'newspack_listmonk_connector_listmonk_settings_handle_read_rest_resource' ) ) {
	/**
	 * Read current settings without exposing the token.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	function newspack_listmonk_connector_listmonk_settings_handle_read_rest_resource( WP_REST_Request $request ) {
		$payload = newspack_listmonk_connector_listmonk_settings_validate_rest_resource_payload(
			$request->get_query_params(),
			'read-query',
			'query'
		);

		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		return rest_ensure_response( newspack_listmonk_connector_get_public_settings_response() );
	}
}

if ( ! function_exists( 'newspack_listmonk_connector_listmonk_settings_handle_create_rest_resource' ) ) {
	/**
	 * Save settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	function newspack_listmonk_connector_listmonk_settings_handle_create_rest_resource( WP_REST_Request $request ) {
		$payload = newspack_listmonk_connector_listmonk_settings_validate_rest_resource_payload( $request->get_json_params(), 'create-request', 'body' );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$settings = newspack_listmonk_connector_save_settings(
			array(
				'base_url'            => $payload['baseUrl'] ?? '',
				'api_user'            => $payload['apiUser'] ?? '',
				'api_token'           => $payload['apiToken'] ?? '',
				'default_from_email'  => $payload['defaultFromEmail'] ?? '',
				'default_template_id' => $payload['defaultTemplateId'] ?? 0,
				'default_list_ids'    => $payload['defaultListIds'] ?? array(),
				'send_mode'           => 'campaign',
			)
		);

		$response = newspack_listmonk_connector_get_public_settings_response( $settings );

		if ( ! empty( $payload['testConnection'] ) ) {
			$result = ( new Newspack_Listmonk_Connector_Listmonk_Client() )->test_connection();
			$response['connection'] = array(
				'ok'        => ! is_wp_error( $result ),
				'message'   => is_wp_error( $result ) ? $result->get_error_message() : __( 'Listmonk connection succeeded.', 'newspack-listmonk-connector' ),
				'checkedAt' => gmdate( 'c' ),
			);
		}

		return rest_ensure_response( $response );
	}
}

if ( ! function_exists( 'newspack_listmonk_connector_listmonk_settings_register_rest_routes' ) ) {
	/**
	 * Register routes.
	 */
	function newspack_listmonk_connector_listmonk_settings_register_rest_routes() {
		$namespace = 'newspack-listmonk-connector/v1';

		register_rest_route(
			$namespace,
			'/listmonk-settings',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => 'newspack_listmonk_connector_listmonk_settings_handle_create_rest_resource',
					'permission_callback' => 'newspack_listmonk_connector_listmonk_settings_can_manage_rest_resource',
				),
			)
		);

		register_rest_route(
			$namespace,
			'/listmonk-settings/item',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => 'newspack_listmonk_connector_listmonk_settings_handle_read_rest_resource',
					'permission_callback' => 'newspack_listmonk_connector_listmonk_settings_can_manage_rest_resource',
				),
			)
		);
	}
}

add_action( 'rest_api_init', 'newspack_listmonk_connector_listmonk_settings_register_rest_routes' );
