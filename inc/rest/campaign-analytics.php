<?php
/**
 * REST resource: campaign analytics.
 *
 * @package Newspack_Listmonk_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

if ( ! function_exists( 'newspack_listmonk_connector_campaign_analytics_load_rest_resource_schema' ) ) {
	/**
	 * Load a generated REST schema.
	 *
	 * @param string $schema_name Schema name.
	 * @return array|null
	 */
	function newspack_listmonk_connector_campaign_analytics_load_rest_resource_schema( $schema_name ) {
		$project_root = dirname( __DIR__, 2 );
		$schema_paths = array(
			$project_root . '/src/rest/campaign-analytics/api-schemas/' . $schema_name . '.schema.json',
			$project_root . '/inc/rest-schemas/campaign-analytics/' . $schema_name . '.schema.json',
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

if ( ! function_exists( 'newspack_listmonk_connector_campaign_analytics_sanitize_rest_resource_schema' ) ) {
	/**
	 * Make generated JSON schema usable by WordPress REST validation.
	 *
	 * @param mixed $schema Schema.
	 * @return mixed
	 */
	function newspack_listmonk_connector_campaign_analytics_sanitize_rest_resource_schema( $schema ) {
		if ( ! is_array( $schema ) ) {
			return $schema;
		}

		unset( $schema['$schema'], $schema['title'] );

		if ( isset( $schema['properties'] ) && is_array( $schema['properties'] ) ) {
			foreach ( $schema['properties'] as $key => $property_schema ) {
				$schema['properties'][ $key ] = newspack_listmonk_connector_campaign_analytics_sanitize_rest_resource_schema( $property_schema );
			}
		}

		if ( isset( $schema['items'] ) && is_array( $schema['items'] ) ) {
			$schema['items'] = newspack_listmonk_connector_campaign_analytics_sanitize_rest_resource_schema( $schema['items'] );
		}

		return $schema;
	}
}

if ( ! function_exists( 'newspack_listmonk_connector_campaign_analytics_validate_rest_resource_payload' ) ) {
	/**
	 * Validate a payload against a generated schema.
	 *
	 * @param mixed  $value Payload.
	 * @param string $schema_name Schema name.
	 * @param string $param_name Param name.
	 * @return mixed|WP_Error
	 */
	function newspack_listmonk_connector_campaign_analytics_validate_rest_resource_payload( $value, $schema_name, $param_name ) {
		$schema = newspack_listmonk_connector_campaign_analytics_load_rest_resource_schema( $schema_name );
		if ( ! is_array( $schema ) ) {
			return new WP_Error( 'missing_schema', __( 'Missing REST schema.', 'newspack-listmonk-connector' ), array( 'status' => 500 ) );
		}

		$rest_schema = newspack_listmonk_connector_campaign_analytics_sanitize_rest_resource_schema( $schema );
		$validation  = rest_validate_value_from_schema( $value, $rest_schema, $param_name );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		return rest_sanitize_value_from_schema( $value, $rest_schema, $param_name );
	}
}

if ( ! function_exists( 'newspack_listmonk_connector_campaign_analytics_can_read_rest_resource' ) ) {
	/**
	 * Permission callback.
	 *
	 * @return bool
	 */
	function newspack_listmonk_connector_campaign_analytics_can_read_rest_resource() {
		return current_user_can( 'edit_posts' );
	}
}

if ( ! function_exists( 'newspack_listmonk_connector_campaign_analytics_normalize_total' ) ) {
	/**
	 * Resolve a numeric total from campaign and running stats data.
	 *
	 * @param array  $campaign Campaign data.
	 * @param array  $stats Running stats data.
	 * @param string $campaign_key Campaign data key.
	 * @param string $stats_key Stats data key.
	 * @return int
	 */
	function newspack_listmonk_connector_campaign_analytics_normalize_total( array $campaign, array $stats, $campaign_key, $stats_key = '' ) {
		$stats_key = $stats_key ? $stats_key : $campaign_key;
		if ( array_key_exists( $campaign_key, $campaign ) ) {
			return absint( $campaign[ $campaign_key ] );
		}
		if ( array_key_exists( $stats_key, $stats ) ) {
			return absint( $stats[ $stats_key ] );
		}
		return 0;
	}
}

if ( ! function_exists( 'newspack_listmonk_connector_campaign_analytics_find_stats_row' ) ) {
	/**
	 * Find stats for one campaign.
	 *
	 * @param array $stats Running stats rows.
	 * @param int   $campaign_id Campaign ID.
	 * @return array
	 */
	function newspack_listmonk_connector_campaign_analytics_find_stats_row( array $stats, $campaign_id ) {
		foreach ( $stats as $row ) {
			if ( is_array( $row ) && absint( $row['campaign_id'] ?? $row['id'] ?? 0 ) === absint( $campaign_id ) ) {
				return $row;
			}
		}

		return isset( $stats[0] ) && is_array( $stats[0] ) ? $stats[0] : array();
	}
}

if ( ! function_exists( 'newspack_listmonk_connector_campaign_analytics_normalize_series' ) ) {
	/**
	 * Normalize a Listmonk analytics response as time-series rows.
	 *
	 * @param string $type Analytics type.
	 * @param array  $rows Analytics rows.
	 * @return array
	 */
	function newspack_listmonk_connector_campaign_analytics_normalize_series( $type, array $rows ) {
		$series = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$series[] = array(
				'type'       => sanitize_key( $type ),
				'campaignId' => absint( $row['campaign_id'] ?? $row['id'] ?? 0 ),
				'count'      => absint( $row['count'] ?? 0 ),
				'timestamp'  => sanitize_text_field( (string) ( $row['timestamp'] ?? '' ) ),
			);
		}

		return $series;
	}
}

if ( ! function_exists( 'newspack_listmonk_connector_campaign_analytics_normalize_links' ) ) {
	/**
	 * Normalize Listmonk link analytics rows.
	 *
	 * @param array $rows Link rows.
	 * @return array
	 */
	function newspack_listmonk_connector_campaign_analytics_normalize_links( array $rows ) {
		$links = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$links[] = array(
				'url'   => esc_url_raw( (string) ( $row['url'] ?? '' ) ),
				'count' => absint( $row['count'] ?? 0 ),
			);
		}

		return $links;
	}
}

if ( ! function_exists( 'newspack_listmonk_connector_campaign_analytics_build_response' ) ) {
	/**
	 * Build analytics response for one newsletter post.
	 *
	 * @param array $payload Validated payload.
	 * @return array|WP_Error
	 */
	function newspack_listmonk_connector_campaign_analytics_build_response( array $payload ) {
		$post_id = absint( $payload['postId'] ?? 0 );
		$post    = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error(
				'newspack_listmonk_connector_invalid_post',
				__( 'Newsletter post not found.', 'newspack-listmonk-connector' ),
				array( 'status' => 404 )
			);
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error(
				'newspack_listmonk_connector_forbidden',
				__( 'You are not allowed to read analytics for this newsletter.', 'newspack-listmonk-connector' ),
				array( 'status' => 403 )
			);
		}

		$campaign_id = absint( get_post_meta( $post_id, '_wtnl_listmonk_campaign_id', true ) );
		if ( ! $campaign_id ) {
			return new WP_Error(
				'newspack_listmonk_connector_missing_campaign_id',
				__( 'This newsletter has not been synced to a Listmonk campaign yet.', 'newspack-listmonk-connector' ),
				array( 'status' => 409 )
			);
		}

		$client   = new Newspack_Listmonk_Connector_Listmonk_Client();
		$campaign = $client->get_campaign( $campaign_id );
		if ( is_wp_error( $campaign ) ) {
			return $campaign;
		}

		$running_stats = $client->get_campaign_running_stats( $campaign_id );
		if ( is_wp_error( $running_stats ) ) {
			return $running_stats;
		}

		$analytics = array();
		foreach ( array( 'views', 'clicks', 'bounces', 'links' ) as $type ) {
			$result = $client->get_campaign_analytics( $type, $campaign_id, $payload['from'], $payload['to'] );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$analytics[ $type ] = $result;
		}

		$campaign_data = $campaign['data'] ?? $campaign;
		$campaign_data = is_array( $campaign_data ) ? $campaign_data : array();
		$stats_row     = newspack_listmonk_connector_campaign_analytics_find_stats_row( $running_stats, $campaign_id );

		return array(
			'postId'     => $post_id,
			'campaignId' => $campaign_id,
			'status'     => sanitize_text_field( (string) ( $campaign_data['status'] ?? get_post_meta( $post_id, '_wtnl_listmonk_last_status', true ) ) ),
			'totals'     => array(
				'sent'    => newspack_listmonk_connector_campaign_analytics_normalize_total( $campaign_data, $stats_row, 'sent' ),
				'toSend'  => newspack_listmonk_connector_campaign_analytics_normalize_total( $campaign_data, $stats_row, 'to_send', 'to_send' ),
				'views'   => newspack_listmonk_connector_campaign_analytics_normalize_total( $campaign_data, $stats_row, 'views' ),
				'clicks'  => newspack_listmonk_connector_campaign_analytics_normalize_total( $campaign_data, $stats_row, 'clicks' ),
				'bounces' => newspack_listmonk_connector_campaign_analytics_normalize_total( $campaign_data, $stats_row, 'bounces' ),
			),
			'series'     => array_merge(
				newspack_listmonk_connector_campaign_analytics_normalize_series( 'views', $analytics['views'] ),
				newspack_listmonk_connector_campaign_analytics_normalize_series( 'clicks', $analytics['clicks'] ),
				newspack_listmonk_connector_campaign_analytics_normalize_series( 'bounces', $analytics['bounces'] )
			),
			'links'      => newspack_listmonk_connector_campaign_analytics_normalize_links( $analytics['links'] ),
			'checkedAt'  => gmdate( 'c' ),
		);
	}
}

if ( ! function_exists( 'newspack_listmonk_connector_campaign_analytics_handle_read_rest_resource' ) ) {
	/**
	 * Read campaign analytics.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	function newspack_listmonk_connector_campaign_analytics_handle_read_rest_resource( WP_REST_Request $request ) {
		$payload = newspack_listmonk_connector_campaign_analytics_validate_rest_resource_payload(
			$request->get_query_params(),
			'read-query',
			'query'
		);
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$response = newspack_listmonk_connector_campaign_analytics_build_response( $payload );
		return is_wp_error( $response ) ? $response : rest_ensure_response( $response );
	}
}

if ( ! function_exists( 'newspack_listmonk_connector_campaign_analytics_register_rest_routes' ) ) {
	/**
	 * Register routes.
	 */
	function newspack_listmonk_connector_campaign_analytics_register_rest_routes() {
		register_rest_route(
			'newspack-listmonk-connector/v1',
			'/campaign-analytics/item',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => 'newspack_listmonk_connector_campaign_analytics_handle_read_rest_resource',
					'permission_callback' => 'newspack_listmonk_connector_campaign_analytics_can_read_rest_resource',
				),
			)
		);
	}
}

add_action( 'rest_api_init', 'newspack_listmonk_connector_campaign_analytics_register_rest_routes' );
