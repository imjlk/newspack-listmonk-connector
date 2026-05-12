<?php
/**
 * Campaign analytics REST tests.
 *
 * @package Newspack_Listmonk_Connector
 */

/**
 * Tests campaign analytics response mapping.
 */
class Newspack_Listmonk_Connector_Campaign_Analytics_Test extends WP_UnitTestCase {
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
	 * User ID.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * Set up an editor user and Listmonk settings.
	 */
	public function set_up() {
		parent::set_up();

		$this->user_id = self::factory()->user->create(
			array(
				'role' => 'editor',
			)
		);
		wp_set_current_user( $this->user_id );
		newspack_listmonk_connector_save_settings(
			array(
				'api_token' => 'api-token',
				'api_user'  => 'api-user',
				'base_url'  => 'http://listmonk.test:9000',
			)
		);
		add_filter( 'pre_http_request', array( $this, 'mock_http_request' ), 10, 3 );
	}

	/**
	 * Reset filters and settings.
	 */
	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'mock_http_request' ), 10 );
		delete_option( newspack_listmonk_connector_get_option_name() );
		wp_set_current_user( 0 );
		$this->requests  = array();
		$this->responses = array();
		parent::tear_down();
	}

	/**
	 * Analytics response normalizes totals, time-series rows, and links.
	 */
	public function test_campaign_analytics_response_normalizes_listmonk_data() {
		$post_id = $this->create_post_with_campaign( 77 );

		$this->queue_response(
			200,
			array(
				'data' => array(
					'id'       => 77,
					'status'   => 'running',
					'sent'     => 12,
					'to_send'  => 20,
					'views'    => 3,
					'clicks'   => 2,
					'bounces'  => 1,
				),
			)
		);
		$this->queue_response( 200, array( 'data' => array( array( 'campaign_id' => 77, 'sent' => 11 ) ) ) );
		$this->queue_response( 200, array( 'data' => array( array( 'campaign_id' => 77, 'count' => 3, 'timestamp' => '2026-05-01T00:00:00Z' ) ) ) );
		$this->queue_response( 200, array( 'data' => array( array( 'campaign_id' => 77, 'count' => 2, 'timestamp' => '2026-05-02T00:00:00Z' ) ) ) );
		$this->queue_response( 200, array( 'data' => array( array( 'campaign_id' => 77, 'count' => 1, 'timestamp' => '2026-05-03T00:00:00Z' ) ) ) );
		$this->queue_response( 200, array( 'data' => array( array( 'url' => 'https://example.com/story', 'count' => 5 ) ) ) );

		$response = newspack_listmonk_connector_campaign_analytics_build_response(
			array(
				'postId' => $post_id,
				'from'   => '2026-05-01',
				'to'     => '2026-05-12',
			)
		);

		$this->assertIsArray( $response );
		$this->assertSame( $post_id, $response['postId'] );
		$this->assertSame( 77, $response['campaignId'] );
		$this->assertSame( 'running', $response['status'] );
		$this->assertSame( array( 'sent' => 12, 'toSend' => 20, 'views' => 3, 'clicks' => 2, 'bounces' => 1 ), $response['totals'] );
		$this->assertCount( 3, $response['series'] );
		$this->assertSame( 'views', $response['series'][0]['type'] );
		$this->assertSame( 3, $response['series'][0]['count'] );
		$this->assertSame( array( array( 'url' => 'https://example.com/story', 'count' => 5 ) ), $response['links'] );
		$this->assertNotEmpty( $response['checkedAt'] );
		$this->assert_request_without_body( 0, 'GET', 'http://listmonk.test:9000/api/campaigns/77' );
		$this->assert_request_without_body( 1, 'GET', 'http://listmonk.test:9000/api/campaigns/running/stats?campaign_id=77' );
		$this->assert_request_without_body( 2, 'GET', 'http://listmonk.test:9000/api/campaigns/analytics/views?id=77&from=2026-05-01&to=2026-05-12' );
		$this->assert_request_without_body( 3, 'GET', 'http://listmonk.test:9000/api/campaigns/analytics/clicks?id=77&from=2026-05-01&to=2026-05-12' );
		$this->assert_request_without_body( 4, 'GET', 'http://listmonk.test:9000/api/campaigns/analytics/bounces?id=77&from=2026-05-01&to=2026-05-12' );
		$this->assert_request_without_body( 5, 'GET', 'http://listmonk.test:9000/api/campaigns/analytics/links?id=77&from=2026-05-01&to=2026-05-12' );
	}

	/**
	 * Posts without a campaign ID return a clear error without remote calls.
	 */
	public function test_campaign_analytics_missing_campaign_id_returns_wp_error() {
		$post_id = self::factory()->post->create(
			array(
				'post_author' => $this->user_id,
			)
		);

		$response = newspack_listmonk_connector_campaign_analytics_build_response(
			array(
				'postId' => $post_id,
				'from'   => '2026-05-01',
				'to'     => '2026-05-12',
			)
		);

		$this->assertWPError( $response );
		$this->assertSame( 'newspack_listmonk_connector_missing_campaign_id', $response->get_error_code() );
		$this->assertCount( 0, $this->requests );
	}

	/**
	 * REST route rejects unauthenticated readers.
	 */
	public function test_campaign_analytics_rest_permission_rejects_logged_out_user() {
		$post_id = $this->create_post_with_campaign( 77 );
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'GET', '/newspack-listmonk-connector/v1/campaign-analytics/item' );
		$request->set_query_params(
			array(
				'postId' => $post_id,
				'from'   => '2026-05-01',
				'to'     => '2026-05-12',
			)
		);

		$response = rest_do_request( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertContains( $response->get_status(), array( 401, 403 ), 'Logged-out analytics request should be rejected.' );
		$this->assertCount( 0, $this->requests );
	}

	/**
	 * Listmonk API failures are returned as WP_Error values.
	 */
	public function test_campaign_analytics_api_failure_returns_wp_error() {
		$post_id = $this->create_post_with_campaign( 77 );

		$this->queue_response(
			200,
			array(
				'data' => array(
					'id'     => 77,
					'status' => 'running',
				),
			)
		);
		$this->queue_response( 200, array( 'data' => array() ) );
		$this->queue_response( 500, array( 'message' => 'Analytics lookup failed.' ), 'Server Error' );

		$response = newspack_listmonk_connector_campaign_analytics_build_response(
			array(
				'postId' => $post_id,
				'from'   => '2026-05-01',
				'to'     => '2026-05-12',
			)
		);

		$this->assertWPError( $response );
		$this->assertSame( 'newspack_listmonk_connector_api_error', $response->get_error_code() );
		$this->assertSame( 'Analytics lookup failed.', $response->get_error_message() );
		$this->assertSame( 500, $response->get_error_data( 'newspack_listmonk_connector_api_error' )['status'] );
		$this->assertCount( 3, $this->requests );
	}

	/**
	 * Create a post with active Listmonk campaign metadata.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return int
	 */
	private function create_post_with_campaign( $campaign_id ) {
		$post_id = self::factory()->post->create(
			array(
				'post_author' => $this->user_id,
			)
		);
		update_post_meta( $post_id, '_wtnl_listmonk_campaign_id', absint( $campaign_id ) );
		update_post_meta( $post_id, '_wtnl_listmonk_last_status', 'running' );

		return $post_id;
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
		$this->responses[] = array(
			'body'     => wp_json_encode( $body ),
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
