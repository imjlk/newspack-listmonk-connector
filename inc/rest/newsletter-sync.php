<?php
/**
 * REST resource: newsletter sync.
 *
 * @package Newspack_Listmonk_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! function_exists( 'newspack_listmonk_connector_newsletter_sync_load_rest_resource_schema' ) ) {
	/**
	 * Load a generated REST schema.
	 *
	 * @param string $schema_name Schema name.
	 * @return array|null
	 */
	function newspack_listmonk_connector_newsletter_sync_load_rest_resource_schema( $schema_name ) {
		$project_root = dirname( __DIR__, 2 );
		$schema_paths = array(
			$project_root . '/src/rest/newsletter-sync/api-schemas/' . $schema_name . '.schema.json',
			$project_root . '/inc/rest-schemas/newsletter-sync/' . $schema_name . '.schema.json',
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

if ( ! function_exists( 'newspack_listmonk_connector_newsletter_sync_sanitize_rest_resource_schema' ) ) {
	/**
	 * Make generated JSON schema usable by WordPress REST validation.
	 *
	 * @param mixed $schema Schema.
	 * @return mixed
	 */
	function newspack_listmonk_connector_newsletter_sync_sanitize_rest_resource_schema( $schema ) {
		if ( ! is_array( $schema ) ) {
			return $schema;
		}

		unset( $schema['$schema'], $schema['title'] );

		if ( isset( $schema['properties'] ) && is_array( $schema['properties'] ) ) {
			foreach ( $schema['properties'] as $key => $property_schema ) {
				$schema['properties'][ $key ] = newspack_listmonk_connector_newsletter_sync_sanitize_rest_resource_schema( $property_schema );
			}
		}

		if ( isset( $schema['items'] ) && is_array( $schema['items'] ) ) {
			$schema['items'] = newspack_listmonk_connector_newsletter_sync_sanitize_rest_resource_schema( $schema['items'] );
		}

		return $schema;
	}
}

if ( ! function_exists( 'newspack_listmonk_connector_newsletter_sync_validate_rest_resource_payload' ) ) {
	/**
	 * Validate a payload against a generated schema.
	 *
	 * @param mixed  $value Payload.
	 * @param string $schema_name Schema name.
	 * @param string $param_name Param name.
	 * @return mixed|WP_Error
	 */
	function newspack_listmonk_connector_newsletter_sync_validate_rest_resource_payload( $value, $schema_name, $param_name ) {
		$schema = newspack_listmonk_connector_newsletter_sync_load_rest_resource_schema( $schema_name );
		if ( ! is_array( $schema ) ) {
			return new WP_Error( 'missing_schema', __( 'Missing REST schema.', 'wp-typia-newsletter-connector' ), array( 'status' => 500 ) );
		}

		$rest_schema = newspack_listmonk_connector_newsletter_sync_sanitize_rest_resource_schema( $schema );
		$validation  = rest_validate_value_from_schema( $value, $rest_schema, $param_name );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		return rest_sanitize_value_from_schema( $value, $rest_schema, $param_name );
	}
}

if ( ! function_exists( 'newspack_listmonk_connector_newsletter_sync_can_manage_rest_resource' ) ) {
	/**
	 * Permission callback.
	 *
	 * @return bool
	 */
	function newspack_listmonk_connector_newsletter_sync_can_manage_rest_resource() {
		return current_user_can( 'edit_posts' );
	}
}

if ( ! function_exists( 'newspack_listmonk_connector_newsletter_sync_get_provider' ) ) {
	/**
	 * Get the active Listmonk provider.
	 *
	 * @return Newspack_Listmonk_Connector_Provider|WP_Error
	 */
	function newspack_listmonk_connector_newsletter_sync_get_provider() {
		if ( ! class_exists( 'Newspack_Newsletters' ) ) {
			return new WP_Error(
				'newspack_listmonk_connector_newspack_missing',
				__( 'Newspack Newsletters is not available.', 'wp-typia-newsletter-connector' ),
				array( 'status' => 400 )
			);
		}

		if ( 'listmonk' !== newspack_listmonk_connector_newspack_service_provider() ) {
			return new WP_Error(
				'newspack_listmonk_connector_inactive_provider',
				__( 'Listmonk is not the active Newspack Newsletters provider.', 'wp-typia-newsletter-connector' ),
				array( 'status' => 409 )
			);
		}

		$provider = newspack_listmonk_connector_get_newspack_provider_instance( 'listmonk' );
		if ( ! $provider instanceof Newspack_Listmonk_Connector_Provider ) {
			return new WP_Error(
				'newspack_listmonk_connector_invalid_provider',
				__( 'The Listmonk provider is not available.', 'wp-typia-newsletter-connector' ),
				array( 'status' => 500 )
			);
		}

		return $provider;
	}
}

if ( ! function_exists( 'newspack_listmonk_connector_newsletter_sync_build_response' ) ) {
	/**
	 * Sync a newsletter post and build the editor response.
	 *
	 * @param array $payload Validated payload.
	 * @return array|WP_Error
	 */
	function newspack_listmonk_connector_newsletter_sync_build_response( array $payload ) {
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
				__( 'You are not allowed to sync this newsletter.', 'wp-typia-newsletter-connector' ),
				array( 'status' => 403 )
			);
		}

		$provider = newspack_listmonk_connector_newsletter_sync_get_provider();
		if ( is_wp_error( $provider ) ) {
			return $provider;
		}

		$retry_send = ! empty( $payload['retrySend'] );
		if ( $retry_send && ! in_array( $post->post_status, array( 'publish', 'future' ), true ) ) {
			return new WP_Error(
				'newspack_listmonk_connector_invalid_retry_status',
				__( 'Only published or scheduled newsletters can retry a Listmonk send.', 'wp-typia-newsletter-connector' ),
				array( 'status' => 409 )
			);
		}

		if ( $retry_send ) {
			$result = $provider->send( $post );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$sync = array();
		} else {
			$sync = $provider->sync( $post );
			if ( is_wp_error( $sync ) ) {
				return $sync;
			}
		}

		$retrieve = $provider->retrieve( $post_id );
		if ( is_wp_error( $retrieve ) ) {
			return $retrieve;
		}

		$campaign_id = absint( $sync['campaign_id'] ?? $retrieve['listmonk_campaign_id'] ?? 0 );
		$status      = (string) ( $retrieve['listmonk_last_status'] ?? $sync['listmonk_status'] ?? '' );

		return array(
			'postId'               => $post_id,
			'message'              => $retry_send ? __( 'Listmonk send retried.', 'wp-typia-newsletter-connector' ) : __( 'Newsletter synced to Listmonk.', 'wp-typia-newsletter-connector' ),
			'campaignId'           => $campaign_id,
			'listmonkCampaignId'   => $campaign_id,
			'listmonkCampaignUuid' => (string) ( $retrieve['listmonk_campaign_uuid'] ?? '' ),
			'status'               => $status,
			'sendListId'           => (string) ( $retrieve['send_list_id'] ?? '' ),
			'lastSyncedAt'         => (string) ( $retrieve['listmonk_last_synced_at'] ?? get_post_meta( $post_id, '_wtnl_listmonk_last_synced_at', true ) ),
			'retrieve'             => $retrieve,
		);
	}
}

if ( ! function_exists( 'newspack_listmonk_connector_newsletter_sync_handle_create_rest_resource' ) ) {
	/**
	 * Create a sync request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	function newspack_listmonk_connector_newsletter_sync_handle_create_rest_resource( WP_REST_Request $request ) {
		$payload = newspack_listmonk_connector_newsletter_sync_validate_rest_resource_payload( $request->get_json_params(), 'create-request', 'body' );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$response = newspack_listmonk_connector_newsletter_sync_build_response( $payload );
		return is_wp_error( $response ) ? $response : rest_ensure_response( $response );
	}
}

if ( ! function_exists( 'newspack_listmonk_connector_newsletter_sync_register_rest_routes' ) ) {
	/**
	 * Register routes.
	 */
	function newspack_listmonk_connector_newsletter_sync_register_rest_routes() {
		register_rest_route(
			'wp-typia-newsletter-connector/v1',
			'/newsletter-sync',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => 'newspack_listmonk_connector_newsletter_sync_handle_create_rest_resource',
					'permission_callback' => 'newspack_listmonk_connector_newsletter_sync_can_manage_rest_resource',
				),
			)
		);
	}
}

add_action( 'rest_api_init', 'newspack_listmonk_connector_newsletter_sync_register_rest_routes' );
