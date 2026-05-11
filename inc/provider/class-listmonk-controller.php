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
		parent::register_routes();

		register_rest_route(
			$this->service_provider::BASE_NAMESPACE . $this->service_provider->service,
			'(?P<id>[\d]+)/retrieve',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'api_retrieve' ),
				'permission_callback' => array( 'Newspack_Newsletters', 'api_authoring_permissions_check' ),
				'args'                => array(
					'id' => array(
						'sanitize_callback' => 'absint',
						'validate_callback' => array( 'Newspack_Newsletters', 'validate_newsletter_id' ),
					),
				),
			)
		);

		register_rest_route(
			$this->service_provider::BASE_NAMESPACE . $this->service_provider->service,
			'(?P<id>[\d]+)/test',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'api_test' ),
				'permission_callback' => array( 'Newspack_Newsletters', 'api_authoring_permissions_check' ),
				'args'                => array(
					'id'         => array(
						'sanitize_callback' => 'absint',
						'validate_callback' => array( 'Newspack_Newsletters', 'validate_newsletter_id' ),
					),
					'test_email' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Retrieve campaign data for the editor.
	 *
	 * @param WP_REST_Request $request API request object.
	 * @return WP_REST_Response|mixed API response or error.
	 */
	public function api_retrieve( $request ) {
		return self::get_api_response( $this->service_provider->retrieve( $request['id'] ) );
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

		$this->update_user_test_emails( $emails );

		return self::get_api_response(
			$this->service_provider->test(
				$request['id'],
				$emails
			)
		);
	}
}
