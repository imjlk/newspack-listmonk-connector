<?php
/**
 * Thin Listmonk HTTP client.
 *
 * @package Newspack_Listmonk_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Listmonk API client.
 */
class Newspack_Listmonk_Connector_Listmonk_Client {
	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array|null $settings Optional settings override.
	 */
	public function __construct( $settings = null ) {
		$this->settings = is_array( $settings ) ? $settings : newspack_listmonk_connector_get_settings( true );
	}

	/**
	 * Whether all required credentials exist.
	 *
	 * @return bool
	 */
	public function has_credentials() {
		return ! empty( $this->settings['base_url'] ) && ! empty( $this->settings['api_user'] ) && ! empty( $this->settings['api_token'] );
	}

	/**
	 * Run a request against Listmonk.
	 *
	 * @param string     $method HTTP method.
	 * @param string     $path API path.
	 * @param array|null $body Request body.
	 * @param array      $query Query args.
	 * @return array|WP_Error
	 */
	public function request( $method, $path, $body = null, array $query = array() ) {
		if ( ! $this->has_credentials() ) {
			return new WP_Error(
				'newspack_listmonk_connector_missing_credentials',
				__( 'Listmonk API URL, user, and token are required.', 'newspack-listmonk-connector' )
			);
		}

		$url = untrailingslashit( $this->settings['base_url'] ) . '/' . ltrim( $path, '/' );
		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}

		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return new WP_Error(
				'newspack_listmonk_connector_invalid_url',
				__( 'Listmonk API URL must use http or https.', 'newspack-listmonk-connector' )
			);
		}

		$args = array(
			'method'  => strtoupper( $method ),
			'timeout' => 45,
			'headers' => array(
				'Accept'        => 'application/json',
				'Authorization' => 'Basic ' . base64_encode( $this->settings['api_user'] . ':' . $this->settings['api_token'] ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			),
		);

		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json; charset=utf-8';
			$args['body']                    = wp_json_encode( $body );
		}

		$host_filter = $this->get_http_host_allow_filter();
		$port_filter = $this->get_http_port_allow_filter( $url );
		add_filter( 'http_request_host_is_external', $host_filter, 10, 3 );
		add_filter( 'http_allowed_safe_ports', $port_filter, 10, 3 );
		$response = wp_safe_remote_request( $url, $args );
		remove_filter( 'http_request_host_is_external', $host_filter, 10 );
		remove_filter( 'http_allowed_safe_ports', $port_filter, 10 );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$raw_body    = wp_remote_retrieve_body( $response );
		$decoded     = '' !== $raw_body ? json_decode( $raw_body, true ) : array();

		if ( null === $decoded && '' !== $raw_body ) {
			return new WP_Error(
				'newspack_listmonk_connector_invalid_json',
				__( 'Listmonk returned an invalid JSON response.', 'newspack-listmonk-connector' ),
				array(
					'status' => $status_code,
					'body'   => $raw_body,
				)
			);
		}

		if ( 200 > $status_code || 300 <= $status_code ) {
			$message = wp_remote_retrieve_response_message( $response );
			if ( is_array( $decoded ) ) {
				$message = $decoded['message'] ?? $decoded['error'] ?? $decoded['data'] ?? $message;
				if ( is_array( $message ) ) {
					$message = wp_json_encode( $message );
				}
			}

			return new WP_Error(
				'newspack_listmonk_connector_api_error',
				$message ? (string) $message : __( 'Listmonk API request failed.', 'newspack-listmonk-connector' ),
				array(
					'status' => $status_code,
					'body'   => $decoded,
				)
			);
		}

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Permit the explicitly configured Listmonk host through WordPress safe HTTP validation.
	 *
	 * @return Closure
	 */
	private function get_http_host_allow_filter() {
		$configured_host = wp_parse_url( $this->settings['base_url'], PHP_URL_HOST );

		return static function ( $is_external, $host ) use ( $configured_host ) {
			if ( $configured_host && 0 === strcasecmp( (string) $host, (string) $configured_host ) ) {
				return true;
			}

			return $is_external;
		};
	}

	/**
	 * Permit the explicitly configured Listmonk port through WordPress safe HTTP validation.
	 *
	 * @param string $url Request URL.
	 * @return Closure
	 */
	private function get_http_port_allow_filter( $url ) {
		$configured_port = absint( wp_parse_url( $url, PHP_URL_PORT ) );

		return static function ( $ports ) use ( $configured_port ) {
			if ( $configured_port && ! in_array( $configured_port, $ports, true ) ) {
				$ports[] = $configured_port;
			}

			return $ports;
		};
	}

	/**
	 * Test the configured Listmonk connection.
	 *
	 * @return true|WP_Error
	 */
	public function test_connection() {
		$result = $this->request( 'GET', '/api/lists', null, array( 'per_page' => 1 ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return true;
	}

	/**
	 * Get active lists.
	 *
	 * @return array|WP_Error
	 */
	public function get_lists() {
		$result = $this->request(
			'GET',
			'/api/lists',
			null,
			array(
				'status'   => 'active',
				'per_page' => 'all',
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->extract_results( $result );
	}

	/**
	 * Get subscribers.
	 *
	 * @param array $query Query args.
	 * @return array|WP_Error
	 */
	public function get_subscribers( array $query = array() ) {
		$result = $this->request( 'GET', '/api/subscribers', null, $query );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->extract_results( $result );
	}

	/**
	 * Find a subscriber by email.
	 *
	 * @param string $email Email address.
	 * @return array|WP_Error
	 */
	public function find_subscriber_by_email( $email ) {
		$email = strtolower( sanitize_email( $email ) );
		if ( '' === $email || ! is_email( $email ) ) {
			return new WP_Error(
				'newspack_listmonk_connector_invalid_subscriber_email',
				__( 'A valid subscriber email is required.', 'newspack-listmonk-connector' )
			);
		}

		$subscribers = $this->get_subscribers(
			array(
				'per_page' => 'all',
			)
		);
		if ( is_wp_error( $subscribers ) ) {
			return $subscribers;
		}

		foreach ( $subscribers as $subscriber ) {
			if ( 0 === strcasecmp( (string) ( $subscriber['email'] ?? '' ), $email ) ) {
				return $subscriber;
			}
		}

		return new WP_Error(
			'newspack_listmonk_connector_subscriber_not_found',
			__( 'Listmonk subscriber was not found.', 'newspack-listmonk-connector' ),
			array( 'email' => $email )
		);
	}

	/**
	 * Create a subscriber.
	 *
	 * @param array $payload Subscriber payload.
	 * @return array|WP_Error
	 */
	public function create_subscriber( array $payload ) {
		return $this->request( 'POST', '/api/subscribers', $payload );
	}

	/**
	 * Partially update a subscriber.
	 *
	 * @param int   $subscriber_id Subscriber ID.
	 * @param array $payload Subscriber payload.
	 * @return array|WP_Error
	 */
	public function update_subscriber( $subscriber_id, array $payload ) {
		return $this->request( 'PATCH', sprintf( '/api/subscribers/%d', absint( $subscriber_id ) ), $payload );
	}

	/**
	 * Update subscriber list memberships.
	 *
	 * @param int[]  $subscriber_ids Subscriber IDs.
	 * @param int[]  $list_ids List IDs.
	 * @param string $action Action: add, remove, or unsubscribe.
	 * @param string $status Subscription status for add actions.
	 * @return array|WP_Error
	 */
	public function update_subscriber_lists( array $subscriber_ids, array $list_ids, $action = 'add', $status = 'unconfirmed' ) {
		$action = sanitize_key( $action );
		if ( ! in_array( $action, array( 'add', 'remove', 'unsubscribe' ), true ) ) {
			return new WP_Error(
				'newspack_listmonk_connector_invalid_subscriber_list_action',
				__( 'Invalid Listmonk subscriber list action.', 'newspack-listmonk-connector' )
			);
		}

		$subscriber_ids = $this->normalize_id_list( $subscriber_ids );
		$list_ids       = $this->normalize_id_list( $list_ids );
		if ( empty( $subscriber_ids ) || empty( $list_ids ) ) {
			return new WP_Error(
				'newspack_listmonk_connector_invalid_subscriber_list_payload',
				__( 'Subscriber IDs and list IDs are required.', 'newspack-listmonk-connector' )
			);
		}

		$payload = array(
			'ids'             => $subscriber_ids,
			'action'          => $action,
			'target_list_ids' => $list_ids,
		);

		if ( 'add' === $action ) {
			$status = sanitize_key( $status );
			if ( ! in_array( $status, array( 'confirmed', 'unconfirmed', 'unsubscribed' ), true ) ) {
				return new WP_Error(
					'newspack_listmonk_connector_invalid_subscription_status',
					__( 'Invalid Listmonk subscription status.', 'newspack-listmonk-connector' )
				);
			}
			$payload['status'] = $status;
		}

		return $this->request( 'PUT', '/api/subscribers/lists', $payload );
	}

	/**
	 * Create a campaign.
	 *
	 * @param array $payload Campaign payload.
	 * @return array|WP_Error
	 */
	public function create_campaign( array $payload ) {
		return $this->request( 'POST', '/api/campaigns', $payload );
	}

	/**
	 * Update a campaign.
	 *
	 * @param int   $campaign_id Campaign ID.
	 * @param array $payload Campaign payload.
	 * @return array|WP_Error
	 */
	public function update_campaign( $campaign_id, array $payload ) {
		return $this->request( 'PUT', sprintf( '/api/campaigns/%d', absint( $campaign_id ) ), $payload );
	}

	/**
	 * Retrieve a campaign.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return array|WP_Error
	 */
	public function get_campaign( $campaign_id ) {
		return $this->request( 'GET', sprintf( '/api/campaigns/%d', absint( $campaign_id ) ) );
	}

	/**
	 * Archive a campaign without hard-deleting it from Listmonk.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return array|WP_Error
	 */
	public function archive_campaign( $campaign_id ) {
		$campaign_id = absint( $campaign_id );
		if ( ! $campaign_id ) {
			return new WP_Error(
				'newspack_listmonk_connector_invalid_campaign_id',
				__( 'Invalid Listmonk campaign ID.', 'newspack-listmonk-connector' )
			);
		}

		$campaign = $this->get_campaign( $campaign_id );
		if ( is_wp_error( $campaign ) ) {
			return $campaign;
		}

		$campaign_data   = $campaign['data'] ?? $campaign;
		$previous_status = sanitize_text_field( $campaign_data['status'] ?? '' );
		if ( 'scheduled' === $previous_status ) {
			$result = $this->request( 'PUT', sprintf( '/api/campaigns/%d/status', $campaign_id ), array( 'status' => 'draft' ) );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return array(
				'campaign_id'     => $campaign_id,
				'previous_status' => $previous_status,
				'archived_status' => 'draft',
				'changed'         => true,
				'policy'          => 'reverted_scheduled_campaign_to_draft',
				'result'          => $result,
			);
		}

		$cancellable = array( 'paused' );

		if ( in_array( $previous_status, $cancellable, true ) ) {
			$result = $this->set_status( $campaign_id, 'cancelled' );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return array(
				'campaign_id'     => $campaign_id,
				'previous_status' => $previous_status,
				'archived_status' => 'cancelled',
				'changed'         => true,
				'policy'          => 'cancelled_cancellable_campaign',
				'result'          => $result,
			);
		}

		$policy = 'preserved_unknown_status';
		if ( 'draft' === $previous_status ) {
			$policy = 'preserved_draft_campaign';
		} elseif ( 'running' === $previous_status ) {
			$policy = 'preserved_running_campaign';
		} elseif ( 'cancelled' === $previous_status ) {
			$policy = 'preserved_cancelled_campaign';
		}

		return array(
			'campaign_id'     => $campaign_id,
			'previous_status' => $previous_status,
			'archived_status' => $previous_status,
			'changed'         => false,
			'policy'          => $policy,
			'result'          => $campaign,
		);
	}

	/**
	 * Send a test campaign.
	 *
	 * @param int   $campaign_id Campaign ID.
	 * @param array $emails Test emails.
	 * @param array $payload Campaign payload.
	 * @return array|WP_Error
	 */
	public function send_test( $campaign_id, array $emails, array $payload ) {
		$payload['subscribers'] = array_values( $emails );
		return $this->request( 'POST', sprintf( '/api/campaigns/%d/test', absint( $campaign_id ) ), $payload );
	}

	/**
	 * Change a campaign status.
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $status New status.
	 * @return array|WP_Error
	 */
	public function set_status( $campaign_id, $status ) {
		$allowed = array( 'scheduled', 'running', 'paused', 'cancelled' );
		if ( ! in_array( $status, $allowed, true ) ) {
			return new WP_Error(
				'newspack_listmonk_connector_invalid_status',
				__( 'Invalid Listmonk campaign status.', 'newspack-listmonk-connector' )
			);
		}
		return $this->request( 'PUT', sprintf( '/api/campaigns/%d/status', absint( $campaign_id ) ), array( 'status' => $status ) );
	}

	/**
	 * Normalize integer ID arrays.
	 *
	 * @param array $ids Raw IDs.
	 * @return int[]
	 */
	private function normalize_id_list( array $ids ) {
		$ids = array_map( 'absint', $ids );
		$ids = array_filter(
			$ids,
			static function ( $id ) {
				return 0 < $id;
			}
		);

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Extract Listmonk response data arrays.
	 *
	 * @param array $response Response body.
	 * @return array
	 */
	private function extract_results( array $response ) {
		$data = $response['data'] ?? $response;
		if ( isset( $data['results'] ) && is_array( $data['results'] ) ) {
			return $data['results'];
		}
		if ( isset( $data['lists'] ) && is_array( $data['lists'] ) ) {
			return $data['lists'];
		}
		if ( is_array( $data ) && $this->is_list_array( $data ) ) {
			return $data;
		}
		return array();
	}

	/**
	 * PHP 8.0-compatible array_is_list().
	 *
	 * @param array $array Array.
	 * @return bool
	 */
	private function is_list_array( array $array ) {
		$index = 0;
		foreach ( array_keys( $array ) as $key ) {
			if ( $key !== $index++ ) {
				return false;
			}
		}
		return true;
	}
}
