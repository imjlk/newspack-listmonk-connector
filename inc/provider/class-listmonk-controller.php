<?php
/**
 * Newspack Newsletters Listmonk ESP REST controller.
 *
 * @package Newspack_Listmonk_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API controller for the Listmonk service provider.
 */
final class Newspack_Listmonk_Connector_Controller extends Newspack_Newsletters_Service_Provider_Controller {
	/**
	 * Constructor.
	 *
	 * @param Newspack_Listmonk_Connector_Provider $provider Listmonk provider.
	 */
	public function __construct( $provider ) {
		parent::__construct( $provider );
	}

	/**
	 * Register API endpoints for the Listmonk provider.
	 *
	 * @return void
	 */
	public function register_routes() {
		$this->register_sync_error_route();
		$namespace = newspack_listmonk_connector_newspack_rest_namespace( $this->service_provider->service );

		register_rest_route(
			$namespace,
			'(?P<id>[\d]+)/retrieve',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'api_retrieve' ),
				'permission_callback' => 'newspack_listmonk_connector_newspack_authoring_permissions_check',
				'args'                => array(
					'id' => array(
						'sanitize_callback' => 'absint',
						'validate_callback' => 'newspack_listmonk_connector_newspack_validate_newsletter_id',
					),
				),
			)
		);

		register_rest_route(
			$namespace,
			'(?P<id>[\d]+)/test',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'api_test' ),
				'permission_callback' => 'newspack_listmonk_connector_newspack_authoring_permissions_check',
				'args'                => array(
					'id'         => array(
						'sanitize_callback' => 'absint',
						'validate_callback' => 'newspack_listmonk_connector_newspack_validate_newsletter_id',
					),
					'test_email' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Register the shared sync-error route when Newspack no longer provides it.
	 *
	 * @return void
	 */
	private function register_sync_error_route() {
		register_rest_route(
			newspack_listmonk_connector_newspack_rest_namespace(),
			'(?P<id>[\d]+)/sync-error',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'api_get_sync_error' ),
				'permission_callback' => 'newspack_listmonk_connector_newspack_authoring_permissions_check',
				'args'                => array(
					'id' => array(
						'sanitize_callback' => 'absint',
						'validate_callback' => 'newspack_listmonk_connector_newspack_validate_newsletter_id',
					),
				),
			)
		);
	}

	/**
	 * Retrieve the sync error.
	 *
	 * @param WP_REST_Request $request API request object.
	 * @return WP_REST_Response|mixed API response or error.
	 */
	public function api_get_sync_error( $request ) {
		$post_id        = absint( $request['id'] );
		$transient_name = $this->get_sync_error_transient_name( $post_id );
		$error_message  = get_transient( $transient_name );
		delete_transient( $transient_name );

		return newspack_listmonk_connector_newspack_rest_response( array( 'message' => $error_message ) );
	}

	/**
	 * Retrieve campaign data for the editor.
	 *
	 * @param WP_REST_Request $request API request object.
	 * @return WP_REST_Response|mixed API response or error.
	 */
	public function api_retrieve( $request ) {
		return newspack_listmonk_connector_newspack_rest_response( $this->service_provider->retrieve( $request['id'] ) );
	}

	/**
	 * Send a test email through Listmonk.
	 *
	 * @param WP_REST_Request $request API request object.
	 * @return WP_REST_Response|mixed API response or error.
	 */
	public function api_test( $request ) {
		$emails = explode( ',', (string) $request['test_email'] );
		foreach ( $emails as &$email ) {
			$email = sanitize_email( trim( $email ) );
		}
		unset( $email );

		if ( method_exists( $this, 'update_user_test_emails' ) ) {
			$this->update_user_test_emails( $emails );
		} else {
			newspack_listmonk_connector_update_user_test_emails( $emails );
		}

		return newspack_listmonk_connector_newspack_rest_response(
			$this->service_provider->test(
				$request['id'],
				$emails
			)
		);
	}

	/**
	 * Resolve the sync error transient name.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function get_sync_error_transient_name( $post_id ) {
		if ( is_callable( array( $this->service_provider, 'get_transient_name' ) ) ) {
			return (string) $this->service_provider->get_transient_name( $post_id );
		}

		return newspack_listmonk_connector_newspack_sync_error_transient_name( $post_id );
	}
}
