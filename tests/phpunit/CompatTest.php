<?php
/**
 * Newspack compatibility helper tests.
 *
 * @package Newspack_Listmonk_Connector
 */

/**
 * Tests defensive compatibility helpers.
 */
class Newspack_Listmonk_Connector_Compat_Test extends WP_UnitTestCase {
	/**
	 * REST namespace helper uses Newspack constants when available and defaults otherwise.
	 */
	public function test_rest_namespace_helper_resolves_provider_namespace() {
		if ( class_exists( 'Newspack_Newsletters_Service_Provider' ) && defined( 'Newspack_Newsletters_Service_Provider::BASE_NAMESPACE' ) ) {
			$expected = trim( (string) constant( 'Newspack_Newsletters_Service_Provider::BASE_NAMESPACE' ), '/' );
		} elseif ( class_exists( 'Newspack_Newsletters' ) && defined( 'Newspack_Newsletters::API_NAMESPACE' ) ) {
			$expected = trim( (string) constant( 'Newspack_Newsletters::API_NAMESPACE' ), '/' );
		} else {
			$expected = 'newspack-newsletters/v1';
		}

		$this->assertSame( $expected, newspack_listmonk_connector_newspack_rest_namespace() );
		$this->assertSame( $expected . '/listmonk', newspack_listmonk_connector_newspack_rest_namespace( 'listmonk' ) );
	}

	/**
	 * CPT and meta helpers use Newspack constants when available and defaults otherwise.
	 */
	public function test_post_type_and_meta_key_helpers_resolve_constants_or_defaults() {
		$expected_post_type = class_exists( 'Newspack_Newsletters' ) && defined( 'Newspack_Newsletters::NEWSPACK_NEWSLETTERS_CPT' )
			? (string) constant( 'Newspack_Newsletters::NEWSPACK_NEWSLETTERS_CPT' )
			: 'newspack_nl_cpt';
		$expected_meta_key  = class_exists( 'Newspack_Newsletters' ) && defined( 'Newspack_Newsletters::EMAIL_HTML_META' )
			? (string) constant( 'Newspack_Newsletters::EMAIL_HTML_META' )
			: 'newspack_email_html';

		$this->assertSame( $expected_post_type, newspack_listmonk_connector_newspack_newsletter_post_type() );
		$this->assertSame( $expected_meta_key, newspack_listmonk_connector_newspack_email_html_meta_key() );
	}

	/**
	 * Response helper keeps errors and wraps successful arrays.
	 */
	public function test_rest_response_helper_preserves_errors_and_wraps_arrays() {
		$error = new WP_Error( 'example_error', 'Example error.' );

		$this->assertWPError( newspack_listmonk_connector_newspack_rest_response( $error ) );

		$response = newspack_listmonk_connector_newspack_rest_response( array( 'ok' => true ) );
		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( array( 'ok' => true ), $response->get_data() );
	}

	/**
	 * Provider registration is guarded by available Newspack base classes.
	 */
	public function test_provider_registration_is_guarded_by_base_classes() {
		$providers  = array(
			'manual' => array(
				'name' => 'Manual',
			),
		);
		$registered = newspack_listmonk_connector_register_provider( $providers );

		if ( newspack_listmonk_connector_can_register_newspack_provider() ) {
			$this->assertArrayHasKey( 'listmonk', $registered );
		} else {
			$this->assertSame( $providers, $registered );
		}
	}
}
