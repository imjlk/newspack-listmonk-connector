<?php
/**
 * Listmonk client tests.
 *
 * @package Newspack_Listmonk_Connector
 */

/**
 * Tests Listmonk HTTP request and response handling.
 */
class Newspack_Listmonk_Connector_Listmonk_Client_Test extends WP_UnitTestCase {
	/**
	 * Captured requests.
	 *
	 * @var array
	 */
	private $requests = array();

	/**
	 * Queued mock responses.
	 *
	 * @var array
	 */
	private $responses = array();

	/**
	 * Reset filters and captured requests.
	 */
	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'mock_http_request' ), 10 );
		$this->requests  = array();
		$this->responses = array();
		parent::tear_down();
	}

	/**
	 * Missing credentials return a clear WP_Error.
	 */
	public function test_missing_credentials_return_wp_error() {
		$result = ( new Newspack_Listmonk_Connector_Listmonk_Client( array() ) )->request( 'GET', '/api/lists' );

		$this->assertWPError( $result );
		$this->assertSame( 'newspack_listmonk_connector_missing_credentials', $result->get_error_code() );
	}

	/**
	 * Basic auth headers and JSON response normalization work for a raw request.
	 */
	public function test_request_sends_basic_auth_header_and_decodes_json() {
		$this->queue_response(
			200,
			array(
				'data' => array(
					'ok' => true,
				),
			)
		);

		$result = $this->client()->request( 'GET', '/api/lists', null, array( 'per_page' => 1 ) );
		$request = $this->last_request();

		$this->assertSame( array( 'data' => array( 'ok' => true ) ), $result );
		$this->assertSame( 'GET', $request['args']['method'] );
		$this->assertSame( 'http://listmonk.test:9000/api/lists?per_page=1', $request['url'] );
		$this->assertSame( 'application/json', $request['args']['headers']['Accept'] );
		$this->assertSame( 'Basic ' . base64_encode( 'api-user:api-token' ), $request['args']['headers']['Authorization'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * List fetch extracts results from Listmonk response envelopes.
	 */
	public function test_get_lists_extracts_results_from_response() {
		$this->queue_response(
			200,
			array(
				'data' => array(
					'results' => array(
						array(
							'id'   => 1,
							'name' => 'Daily News',
						),
					),
				),
			)
		);

		$lists = $this->client()->get_lists();
		$request = $this->last_request();

		$this->assertSame( array( array( 'id' => 1, 'name' => 'Daily News' ) ), $lists );
		$this->assertSame( 'GET', $request['args']['method'] );
		$this->assertSame( 'http://listmonk.test:9000/api/lists?status=active&per_page=all', $request['url'] );
	}

	/**
	 * Non-2xx responses are normalized into WP_Error with Listmonk message and status.
	 */
	public function test_non_2xx_response_returns_wp_error() {
		$this->queue_response(
			422,
			array(
				'message' => 'Invalid campaign payload.',
			),
			'Unprocessable Entity'
		);

		$result = $this->client()->create_campaign( array( 'name' => '' ) );

		$this->assertWPError( $result );
		$this->assertSame( 'newspack_listmonk_connector_api_error', $result->get_error_code() );
		$this->assertSame( 'Invalid campaign payload.', $result->get_error_message() );
		$this->assertSame( 422, $result->get_error_data( 'newspack_listmonk_connector_api_error' )['status'] );
	}

	/**
	 * Invalid JSON responses are normalized into WP_Error.
	 */
	public function test_invalid_json_response_returns_wp_error() {
		$this->queue_raw_response( 200, '{not-json' );

		$result = $this->client()->request( 'GET', '/api/lists' );

		$this->assertWPError( $result );
		$this->assertSame( 'newspack_listmonk_connector_invalid_json', $result->get_error_code() );
		$this->assertSame( 200, $result->get_error_data( 'newspack_listmonk_connector_invalid_json' )['status'] );
		$this->assertSame( '{not-json', $result->get_error_data( 'newspack_listmonk_connector_invalid_json' )['body'] );
	}

	/**
	 * Campaign endpoint helpers issue the expected method, path, and JSON body.
	 */
	public function test_campaign_endpoint_helpers_send_expected_requests() {
		$client = $this->client();

		$this->queue_response( 200, array( 'data' => array( 'id' => 10 ) ) );
		$client->create_campaign( array( 'name' => 'Draft' ) );
		$this->assert_request( 0, 'POST', 'http://listmonk.test:9000/api/campaigns', array( 'name' => 'Draft' ) );

		$this->queue_response( 200, array( 'data' => array( 'id' => 11 ) ) );
		$client->update_campaign( 11, array( 'name' => 'Updated' ) );
		$this->assert_request( 1, 'PUT', 'http://listmonk.test:9000/api/campaigns/11', array( 'name' => 'Updated' ) );

		$this->queue_response( 200, array( 'data' => array( 'sent' => true ) ) );
		$client->send_test( 12, array( 'one@example.com', 'two@example.com' ), array( 'subject' => 'Test' ) );
		$this->assert_request(
			2,
			'POST',
			'http://listmonk.test:9000/api/campaigns/12/test',
			array(
				'subject'     => 'Test',
				'subscribers' => array( 'one@example.com', 'two@example.com' ),
			)
		);

		$this->queue_response( 200, array( 'data' => array( 'status' => 'running' ) ) );
		$client->set_status( 13, 'running' );
		$this->assert_request( 3, 'PUT', 'http://listmonk.test:9000/api/campaigns/13/status', array( 'status' => 'running' ) );
	}

	/**
	 * Invalid status changes fail before making an HTTP request.
	 */
	public function test_invalid_status_returns_wp_error_without_http_request() {
		$result = $this->client()->set_status( 13, 'draft' );

		$this->assertWPError( $result );
		$this->assertSame( 'newspack_listmonk_connector_invalid_status', $result->get_error_code() );
		$this->assertCount( 0, $this->requests );
	}

	/**
	 * Create a configured client.
	 *
	 * @return Newspack_Listmonk_Connector_Listmonk_Client
	 */
	private function client() {
		add_filter( 'pre_http_request', array( $this, 'mock_http_request' ), 10, 3 );

		return new Newspack_Listmonk_Connector_Listmonk_Client(
			array(
				'api_token' => 'api-token',
				'api_user'  => 'api-user',
				'base_url'  => 'http://listmonk.test:9000',
			)
		);
	}

	/**
	 * Queue a JSON mock response.
	 *
	 * @param int    $status_code Status code.
	 * @param array  $body Response body.
	 * @param string $message Response message.
	 * @return void
	 */
	private function queue_response( $status_code, array $body, $message = 'OK' ) {
		$this->queue_raw_response( $status_code, wp_json_encode( $body ), $message );
	}

	/**
	 * Queue a raw mock response.
	 *
	 * @param int    $status_code Status code.
	 * @param string $body Response body.
	 * @param string $message Response message.
	 * @return void
	 */
	private function queue_raw_response( $status_code, $body, $message = 'OK' ) {
		$this->responses[] = array(
			'body'     => $body,
			'headers'  => array(),
			'response' => array(
				'code'    => $status_code,
				'message' => $message,
			),
		);
	}

	/**
	 * Mock WordPress HTTP requests.
	 *
	 * @param false|array|WP_Error $preempt Preempt value.
	 * @param array                $args Request args.
	 * @param string               $url URL.
	 * @return array
	 */
	public function mock_http_request( $preempt, $args, $url ) {
		$this->requests[] = array(
			'args' => $args,
			'url'  => $url,
		);

		return array_shift( $this->responses );
	}

	/**
	 * Get the last captured request.
	 *
	 * @return array
	 */
	private function last_request() {
		$this->assertNotEmpty( $this->requests );
		return $this->requests[ count( $this->requests ) - 1 ];
	}

	/**
	 * Assert a captured request.
	 *
	 * @param int    $index Request index.
	 * @param string $method HTTP method.
	 * @param string $url URL.
	 * @param array  $body JSON body.
	 * @return void
	 */
	private function assert_request( $index, $method, $url, array $body ) {
		$this->assertArrayHasKey( $index, $this->requests );

		$request = $this->requests[ $index ];
		$this->assertSame( $method, $request['args']['method'] );
		$this->assertSame( $url, $request['url'] );
		$this->assertSame( 'application/json; charset=utf-8', $request['args']['headers']['Content-Type'] );
		$this->assertSame( $body, json_decode( $request['args']['body'], true ) );
	}
}
