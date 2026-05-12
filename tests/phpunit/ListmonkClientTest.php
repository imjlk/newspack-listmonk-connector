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
	 * List fetch requests and merges paginated Listmonk responses.
	 */
	public function test_get_lists_fetches_and_merges_paginated_results() {
		$page_one = $this->id_results( 1, 100 );
		$this->queue_response(
			200,
			array(
				'data' => array(
					'results'  => $page_one,
					'total'    => 101,
					'per_page' => 100,
					'page'     => 1,
				),
			)
		);
		$this->queue_response(
			200,
			array(
				'data' => array(
					'results'  => array( array( 'id' => 101 ) ),
					'total'    => 101,
					'per_page' => 100,
					'page'     => 2,
				),
			)
		);

		$lists = $this->client()->get_lists();

		$this->assertCount( 101, $lists );
		$this->assertSame( 1, $lists[0]['id'] );
		$this->assertSame( 101, $lists[100]['id'] );
		$this->assert_request_without_body( 0, 'GET', 'http://listmonk.test:9000/api/lists?status=active&page=1&per_page=100' );
		$this->assert_request_without_body( 1, 'GET', 'http://listmonk.test:9000/api/lists?status=active&page=2&per_page=100' );
	}

	/**
	 * List pagination returns later page failures without masking them.
	 */
	public function test_get_lists_returns_later_page_wp_error() {
		$this->queue_response(
			200,
			array(
				'data' => array(
					'results'  => $this->id_results( 1, 100 ),
					'total'    => 101,
					'per_page' => 100,
					'page'     => 1,
				),
			)
		);
		$this->queue_response(
			500,
			array(
				'message' => 'Listmonk page failed.',
			),
			'Server Error'
		);

		$result = $this->client()->get_lists();

		$this->assertWPError( $result );
		$this->assertSame( 'newspack_listmonk_connector_api_error', $result->get_error_code() );
		$this->assertSame( 'Listmonk page failed.', $result->get_error_message() );
		$this->assertCount( 2, $this->requests );
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
	 * Campaign analytics endpoint helpers issue expected GET requests.
	 */
	public function test_campaign_analytics_endpoint_helpers_send_expected_requests() {
		$client = $this->client();

		$this->queue_response( 200, array( 'data' => array( array( 'campaign_id' => 13 ) ) ) );
		$stats = $client->get_campaign_running_stats( 13 );
		$this->assertSame( array( array( 'campaign_id' => 13 ) ), $stats );
		$this->assert_request_without_body( 0, 'GET', 'http://listmonk.test:9000/api/campaigns/running/stats?campaign_id=13' );

		$this->queue_response( 200, array( 'data' => array( array( 'count' => 5 ) ) ) );
		$analytics = $client->get_campaign_analytics( 'views', 13, '2026-05-01', '2026-05-12' );
		$this->assertSame( array( array( 'count' => 5 ) ), $analytics );
		$this->assert_request_without_body( 1, 'GET', 'http://listmonk.test:9000/api/campaigns/analytics/views?id=13&from=2026-05-01&to=2026-05-12' );
	}

	/**
	 * Invalid analytics types fail before making HTTP requests.
	 */
	public function test_invalid_campaign_analytics_type_returns_wp_error_without_http_request() {
		$result = $this->client()->get_campaign_analytics( 'invalid', 13, '2026-05-01', '2026-05-12' );

		$this->assertWPError( $result );
		$this->assertSame( 'newspack_listmonk_connector_invalid_analytics_type', $result->get_error_code() );
		$this->assertCount( 0, $this->requests );
	}

	/**
	 * Subscriber endpoint helpers issue the expected method, path, and JSON body.
	 */
	public function test_subscriber_endpoint_helpers_send_expected_requests() {
		$client = $this->client();

		$this->queue_response( 200, array( 'data' => array( 'id' => 20 ) ) );
		$client->create_subscriber(
			array(
				'email'                    => 'reader@example.com',
				'name'                     => 'Reader',
				'status'                   => 'enabled',
				'lists'                    => array( 1 ),
				'attribs'                  => array( 'membership' => 'paid' ),
				'preconfirm_subscriptions' => false,
			)
		);
		$this->assert_request(
			0,
			'POST',
			'http://listmonk.test:9000/api/subscribers',
			array(
				'email'                    => 'reader@example.com',
				'name'                     => 'Reader',
				'status'                   => 'enabled',
				'lists'                    => array( 1 ),
				'attribs'                  => array( 'membership' => 'paid' ),
				'preconfirm_subscriptions' => false,
			)
		);

		$this->queue_response( 200, array( 'data' => array( 'id' => 20 ) ) );
		$client->update_subscriber( 20, array( 'name' => 'Updated Reader' ) );
		$this->assert_request( 1, 'PATCH', 'http://listmonk.test:9000/api/subscribers/20', array( 'name' => 'Updated Reader' ) );

		$this->queue_response( 200, array( 'data' => true ) );
		$client->update_subscriber_lists( array( 20 ), array( 2, 3 ), 'add', 'unconfirmed' );
		$this->assert_request(
			2,
			'PUT',
			'http://listmonk.test:9000/api/subscribers/lists',
			array(
				'ids'             => array( 20 ),
				'action'          => 'add',
				'target_list_ids' => array( 2, 3 ),
				'status'          => 'unconfirmed',
			)
		);

		$this->queue_response( 200, array( 'data' => array( 'id' => 20 ) ) );
		$client->get_subscriber( 20 );
		$this->assert_request_without_body( 3, 'GET', 'http://listmonk.test:9000/api/subscribers/20' );

		$this->queue_response(
			200,
			array(
				'data' => array(
					'results'  => $this->id_results( 1, 100 ),
					'total'    => 101,
					'per_page' => 100,
					'page'     => 1,
				),
			)
		);
		$this->queue_response(
			200,
			array(
				'data' => array(
					'results'  => array( array( 'id' => 101 ) ),
					'total'    => 101,
					'per_page' => 100,
					'page'     => 2,
				),
			)
		);
		$bounces = $client->get_subscriber_bounces( 20 );
		$this->assertCount( 101, $bounces );
		$this->assert_request_without_body( 4, 'GET', 'http://listmonk.test:9000/api/subscribers/20/bounces?page=1&per_page=100' );
		$this->assert_request_without_body( 5, 'GET', 'http://listmonk.test:9000/api/subscribers/20/bounces?page=2&per_page=100' );
	}

	/**
	 * Explicit subscriber pagination queries remain single-page fetches.
	 */
	public function test_get_subscribers_explicit_pagination_fetches_single_page() {
		$client = $this->client();

		$this->queue_response(
			200,
			array(
				'data' => array(
					'results' => array(
						array(
							'id' => 9,
						),
					),
				),
			)
		);

		$subscribers = $client->get_subscribers(
			array(
				'page'     => 3,
				'per_page' => 25,
			)
		);

		$this->assertSame( array( array( 'id' => 9 ) ), $subscribers );
		$this->assertCount( 1, $this->requests );
		$this->assert_request_without_body( 0, 'GET', 'http://listmonk.test:9000/api/subscribers?page=3&per_page=25' );
	}

	/**
	 * Subscriber lookup fetches subscribers and returns the local exact email match.
	 */
	public function test_find_subscriber_by_email_queries_and_returns_match() {
		$client = $this->client();

		$this->queue_response(
			200,
			array(
				'data' => array(
					'results' => array(
						array(
							'id'    => 21,
							'email' => 'reader@example.com',
							'name'  => 'Reader',
						),
					),
				),
			)
		);

		$subscriber = $client->find_subscriber_by_email( 'Reader@Example.com' );

		$this->assertSame( 21, $subscriber['id'] );
		$this->assertCount( 1, $this->requests );
		$this->assert_request_without_body( 0, 'GET', 'http://listmonk.test:9000/api/subscribers?page=1&per_page=100' );
	}

	/**
	 * Subscriber lookup scans later pages and keeps case-insensitive exact matching.
	 */
	public function test_find_subscriber_by_email_finds_second_page_match() {
		$client = $this->client();

		$this->queue_response(
			200,
			array(
				'data' => array(
					'results'  => $this->subscriber_results( 1, 100 ),
					'total'    => 101,
					'per_page' => 100,
					'page'     => 1,
				),
			)
		);
		$this->queue_response(
			200,
			array(
				'data' => array(
					'results'  => array(
						array(
							'id'    => 101,
							'email' => 'reader@example.com',
						),
					),
					'total'    => 101,
					'per_page' => 100,
					'page'     => 2,
				),
			)
		);

		$subscriber = $client->find_subscriber_by_email( 'Reader@Example.com' );

		$this->assertSame( 101, $subscriber['id'] );
		$this->assertCount( 2, $this->requests );
		$this->assert_request_without_body( 0, 'GET', 'http://listmonk.test:9000/api/subscribers?page=1&per_page=100' );
		$this->assert_request_without_body( 1, 'GET', 'http://listmonk.test:9000/api/subscribers?page=2&per_page=100' );
	}

	/**
	 * Subscriber lookup returns a clear WP_Error when no matching email is found.
	 */
	public function test_find_subscriber_by_email_returns_not_found_error() {
		$client = $this->client();

		$this->queue_response(
			200,
			array(
				'data' => array(
					'results'  => $this->subscriber_results( 1, 100 ),
					'total'    => 101,
					'per_page' => 100,
					'page'     => 1,
				),
			)
		);
		$this->queue_response(
			200,
			array(
				'data' => array(
					'results'  => array(
						array(
							'id'    => 101,
							'email' => 'not-reader-101@example.com',
						),
					),
					'total'    => 101,
					'per_page' => 100,
					'page'     => 2,
				),
			)
		);

		$result = $client->find_subscriber_by_email( 'missing@example.com' );

		$this->assertWPError( $result );
		$this->assertSame( 'newspack_listmonk_connector_subscriber_not_found', $result->get_error_code() );
		$this->assertCount( 2, $this->requests );
	}

	/**
	 * Cancellable campaigns are archived by moving them to cancelled.
	 */
	public function test_archive_campaign_cancels_cancellable_campaign() {
		$client = $this->client();

		$this->queue_response( 200, array( 'data' => array( 'id' => 14, 'status' => 'paused' ) ) );
		$this->queue_response( 200, array( 'data' => array( 'id' => 14, 'status' => 'cancelled' ) ) );

		$result = $client->archive_campaign( 14 );

		$this->assertIsArray( $result );
		$this->assertSame( 14, $result['campaign_id'] );
		$this->assertSame( 'paused', $result['previous_status'] );
		$this->assertSame( 'cancelled', $result['archived_status'] );
		$this->assertTrue( $result['changed'] );
		$this->assertSame( 'cancelled_cancellable_campaign', $result['policy'] );
		$this->assert_request_without_body( 0, 'GET', 'http://listmonk.test:9000/api/campaigns/14' );
		$this->assert_request( 1, 'PUT', 'http://listmonk.test:9000/api/campaigns/14/status', array( 'status' => 'cancelled' ) );
	}

	/**
	 * Scheduled campaigns are reverted to draft because Listmonk cannot cancel inactive scheduled campaigns.
	 */
	public function test_archive_campaign_reverts_scheduled_campaign_to_draft() {
		$client = $this->client();

		$this->queue_response( 200, array( 'data' => array( 'id' => 17, 'status' => 'scheduled' ) ) );
		$this->queue_response( 200, array( 'data' => array( 'id' => 17, 'status' => 'draft' ) ) );

		$result = $client->archive_campaign( 17 );

		$this->assertIsArray( $result );
		$this->assertSame( 17, $result['campaign_id'] );
		$this->assertSame( 'scheduled', $result['previous_status'] );
		$this->assertSame( 'draft', $result['archived_status'] );
		$this->assertTrue( $result['changed'] );
		$this->assertSame( 'reverted_scheduled_campaign_to_draft', $result['policy'] );
		$this->assert_request_without_body( 0, 'GET', 'http://listmonk.test:9000/api/campaigns/17' );
		$this->assert_request( 1, 'PUT', 'http://listmonk.test:9000/api/campaigns/17/status', array( 'status' => 'draft' ) );
	}

	/**
	 * Draft campaigns are preserved because Listmonk cannot cancel inactive drafts.
	 */
	public function test_archive_campaign_preserves_draft_campaign() {
		$client = $this->client();

		$this->queue_response( 200, array( 'data' => array( 'id' => 16, 'status' => 'draft' ) ) );

		$result = $client->archive_campaign( 16 );

		$this->assertIsArray( $result );
		$this->assertSame( 16, $result['campaign_id'] );
		$this->assertSame( 'draft', $result['previous_status'] );
		$this->assertSame( 'draft', $result['archived_status'] );
		$this->assertFalse( $result['changed'] );
		$this->assertSame( 'preserved_draft_campaign', $result['policy'] );
		$this->assertCount( 1, $this->requests );
		$this->assert_request_without_body( 0, 'GET', 'http://listmonk.test:9000/api/campaigns/16' );
	}

	/**
	 * Running campaigns are preserved for operator inspection.
	 */
	public function test_archive_campaign_preserves_running_campaign() {
		$client = $this->client();

		$this->queue_response( 200, array( 'data' => array( 'id' => 15, 'status' => 'running' ) ) );

		$result = $client->archive_campaign( 15 );

		$this->assertIsArray( $result );
		$this->assertSame( 15, $result['campaign_id'] );
		$this->assertSame( 'running', $result['previous_status'] );
		$this->assertSame( 'running', $result['archived_status'] );
		$this->assertFalse( $result['changed'] );
		$this->assertSame( 'preserved_running_campaign', $result['policy'] );
		$this->assertCount( 1, $this->requests );
		$this->assert_request_without_body( 0, 'GET', 'http://listmonk.test:9000/api/campaigns/15' );
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
	 * Build Listmonk fixture results with numeric IDs.
	 *
	 * @param int $start First ID.
	 * @param int $count Number of results.
	 * @return array
	 */
	private function id_results( $start, $count ) {
		$results = array();
		for ( $id = absint( $start ); $id < absint( $start ) + absint( $count ); $id++ ) {
			$results[] = array( 'id' => $id );
		}
		return $results;
	}

	/**
	 * Build subscriber fixtures with non-matching emails.
	 *
	 * @param int $start First ID.
	 * @param int $count Number of results.
	 * @return array
	 */
	private function subscriber_results( $start, $count ) {
		$results = array();
		foreach ( $this->id_results( $start, $count ) as $result ) {
			$result['email'] = sprintf( 'not-reader-%d@example.com', absint( $result['id'] ) );
			$results[]       = $result;
		}
		return $results;
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

	/**
	 * Assert a captured request with no JSON body.
	 *
	 * @param int    $index Request index.
	 * @param string $method HTTP method.
	 * @param string $url URL.
	 * @return void
	 */
	private function assert_request_without_body( $index, $method, $url ) {
		$this->assertArrayHasKey( $index, $this->requests );

		$request = $this->requests[ $index ];
		$this->assertSame( $method, $request['args']['method'] );
		$this->assertSame( $url, $request['url'] );
		$this->assertTrue( ! isset( $request['args']['body'] ) || '' === $request['args']['body'] );
	}
}
