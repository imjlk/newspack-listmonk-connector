<?php
/**
 * REST resource: newsletter preview.
 *
 * @package Newspack_Listmonk_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! function_exists( 'newspack_listmonk_connector_newsletter_preview_load_rest_resource_schema' ) ) {
	/**
	 * Load a generated REST schema.
	 *
	 * @param string $schema_name Schema name.
	 * @return array|null
	 */
	function newspack_listmonk_connector_newsletter_preview_load_rest_resource_schema( $schema_name ) {
		$project_root = dirname( __DIR__, 2 );
		$schema_paths = array(
			$project_root . '/src/rest/newsletter-preview/api-schemas/' . $schema_name . '.schema.json',
			$project_root . '/inc/rest-schemas/newsletter-preview/' . $schema_name . '.schema.json',
		);

		foreach ( $schema_paths as $schema_path ) {
			if ( ! file_exists( $schema_path ) ) {
				continue;
			}

			$decoded = newspack_listmonk_connector_read_json_file( $schema_path );
			return is_array( $decoded ) ? $decoded : null;
		}

		return null;
	}
}

if ( ! function_exists( 'newspack_listmonk_connector_newsletter_preview_sanitize_rest_resource_schema' ) ) {
	/**
	 * Make generated JSON schema usable by WordPress REST validation.
	 *
	 * @param mixed $schema Schema.
	 * @return mixed
	 */
	function newspack_listmonk_connector_newsletter_preview_sanitize_rest_resource_schema( $schema ) {
		if ( ! is_array( $schema ) ) {
			return $schema;
		}

		unset( $schema['$schema'], $schema['title'] );

		if ( isset( $schema['properties'] ) && is_array( $schema['properties'] ) ) {
			foreach ( $schema['properties'] as $key => $property_schema ) {
				$schema['properties'][ $key ] = newspack_listmonk_connector_newsletter_preview_sanitize_rest_resource_schema( $property_schema );
			}
		}

		if ( isset( $schema['items'] ) && is_array( $schema['items'] ) ) {
			$schema['items'] = newspack_listmonk_connector_newsletter_preview_sanitize_rest_resource_schema( $schema['items'] );
		}

		return $schema;
	}
}

if ( ! function_exists( 'newspack_listmonk_connector_newsletter_preview_validate_rest_resource_payload' ) ) {
	/**
	 * Validate a payload against a generated schema.
	 *
	 * @param mixed  $value Payload.
	 * @param string $schema_name Schema name.
	 * @param string $param_name Param name.
	 * @return mixed|WP_Error
	 */
	function newspack_listmonk_connector_newsletter_preview_validate_rest_resource_payload( $value, $schema_name, $param_name ) {
		$schema = newspack_listmonk_connector_newsletter_preview_load_rest_resource_schema( $schema_name );
		if ( ! is_array( $schema ) ) {
			return new WP_Error( 'missing_schema', __( 'Missing REST schema.', 'wp-typia-newsletter-connector' ), array( 'status' => 500 ) );
		}

		$rest_schema = newspack_listmonk_connector_newsletter_preview_sanitize_rest_resource_schema( $schema );
		$validation  = rest_validate_value_from_schema( $value, $rest_schema, $param_name );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		return rest_sanitize_value_from_schema( $value, $rest_schema, $param_name );
	}
}

if ( ! function_exists( 'newspack_listmonk_connector_newsletter_preview_can_manage_rest_resource' ) ) {
	/**
	 * Permission callback.
	 *
	 * @return bool
	 */
	function newspack_listmonk_connector_newsletter_preview_can_manage_rest_resource() {
		return current_user_can( 'edit_posts' );
	}
}

if ( ! function_exists( 'newspack_listmonk_connector_newsletter_preview_build_response' ) ) {
	/**
	 * Build preview response.
	 *
	 * @param array $payload Validated payload.
	 * @return array|WP_Error
	 */
	function newspack_listmonk_connector_newsletter_preview_build_response( array $payload ) {
		$post_id = absint( $payload['postId'] ?? 0 );
		$post    = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error(
				'newspack_listmonk_connector_invalid_post',
				__( 'Newsletter post not found.', 'wp-typia-newsletter-connector' ),
				array( 'status' => 404 )
			);
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error(
				'newspack_listmonk_connector_forbidden',
				__( 'You are not allowed to preview this newsletter.', 'wp-typia-newsletter-connector' ),
				array( 'status' => 403 )
			);
		}

		$settings     = newspack_listmonk_connector_get_settings( true );
		$html_builder = new Newspack_Listmonk_Connector_Raw_HTML_Builder();
		$text_builder = new Newspack_Listmonk_Connector_Plain_Text_Builder();
		$list_ids     = ! empty( $payload['listIds'] ) ? newspack_listmonk_connector_normalize_list_ids( $payload['listIds'] ) : newspack_listmonk_connector_normalize_list_ids( get_post_meta( $post_id, 'send_list_id', true ) );
		$list_ids     = ! empty( $list_ids ) ? $list_ids : newspack_listmonk_connector_normalize_list_ids( $settings['default_list_ids'] );
		$template_id  = isset( $payload['templateId'] ) && null !== $payload['templateId'] ? absint( $payload['templateId'] ) : absint( $settings['default_template_id'] );
		$raw_html     = $html_builder->build( $post, array( 'template_id' => $template_id ) );
		$plain_text   = $text_builder->build( $raw_html );
		$from_email   = ! empty( $payload['fromEmail'] ) ? newspack_listmonk_connector_sanitize_from_email( $payload['fromEmail'] ) : (string) $settings['default_from_email'];
		$tags         = array( 'newspack', 'wp-post:' . $post_id );
		$subject      = html_entity_decode( wp_strip_all_tags( $post->post_title ), ENT_QUOTES, get_bloginfo( 'charset' ) );
		$campaign     = array(
			'postId'       => $post_id,
			'campaignName' => get_post_meta( $post_id, 'campaign_name', true ) ?: sprintf( 'Newspack Newsletter (%d)', $post_id ),
			'subject'      => $subject,
			'rawHtml'      => $raw_html,
			'plainText'    => $plain_text,
			'listIds'      => $list_ids,
			'fromEmail'    => $from_email,
			'templateId'   => $template_id,
			'tags'         => $tags,
		);

		$listmonk_payload = array(
			'postId'       => $post_id,
			'campaignName' => $campaign['campaignName'],
			'subject'      => $subject,
			'rawHtml'      => $raw_html,
			'plainText'    => $plain_text,
			'listIds'      => $list_ids,
			'fromEmail'    => $from_email,
			'templateId'   => $campaign['templateId'],
			'tags'         => $tags,
			'sendMode'     => 'campaign',
		);

		$campaign['payloadHash']     = md5( wp_json_encode( $listmonk_payload ) );
		$campaign['listmonkPayload'] = $listmonk_payload;

		return $campaign;
	}
}

if ( ! function_exists( 'newspack_listmonk_connector_newsletter_preview_handle_read_rest_resource' ) ) {
	/**
	 * Read preview.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	function newspack_listmonk_connector_newsletter_preview_handle_read_rest_resource( WP_REST_Request $request ) {
		$payload = newspack_listmonk_connector_newsletter_preview_validate_rest_resource_payload(
			$request->get_query_params(),
			'read-query',
			'query'
		);
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$response = newspack_listmonk_connector_newsletter_preview_build_response( $payload );
		return is_wp_error( $response ) ? $response : rest_ensure_response( $response );
	}
}

if ( ! function_exists( 'newspack_listmonk_connector_newsletter_preview_handle_create_rest_resource' ) ) {
	/**
	 * Create preview from POST body.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	function newspack_listmonk_connector_newsletter_preview_handle_create_rest_resource( WP_REST_Request $request ) {
		$payload = newspack_listmonk_connector_newsletter_preview_validate_rest_resource_payload( $request->get_json_params(), 'create-request', 'body' );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$response = newspack_listmonk_connector_newsletter_preview_build_response( $payload );
		return is_wp_error( $response ) ? $response : rest_ensure_response( $response );
	}
}

if ( ! function_exists( 'newspack_listmonk_connector_newsletter_preview_register_rest_routes' ) ) {
	/**
	 * Register routes.
	 */
	function newspack_listmonk_connector_newsletter_preview_register_rest_routes() {
		$namespace = 'wp-typia-newsletter-connector/v1';

		register_rest_route(
			$namespace,
			'/newsletter-preview',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => 'newspack_listmonk_connector_newsletter_preview_handle_create_rest_resource',
					'permission_callback' => 'newspack_listmonk_connector_newsletter_preview_can_manage_rest_resource',
				),
			)
		);

		register_rest_route(
			$namespace,
			'/newsletter-preview/item',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => 'newspack_listmonk_connector_newsletter_preview_handle_read_rest_resource',
					'permission_callback' => 'newspack_listmonk_connector_newsletter_preview_can_manage_rest_resource',
				),
			)
		);
	}
}

add_action( 'rest_api_init', 'newspack_listmonk_connector_newsletter_preview_register_rest_routes' );
