<?php
/**
 * Provider send retry tests.
 *
 * @package Newspack_Listmonk_Connector
 */

if ( ! class_exists( 'Newspack_Newsletters_Service_Provider' ) ) {
	/**
	 * Minimal Newspack service provider test double.
	 */
	class Newspack_Newsletters_Service_Provider {
		const BASE_NAMESPACE = '/newspack-newsletters/v1/';

		/**
		 * Service slug.
		 *
		 * @var string
		 */
		public $service = '';

		/**
		 * REST controller.
		 *
		 * @var object|null
		 */
		protected $controller;

		/**
		 * Constructor.
		 */
		public function __construct() {}

		/**
		 * Base provider labels.
		 *
		 * @param mixed $context Label context.
		 * @return array
		 */
		public static function get_labels( $context = '' ) {
			return array();
		}

		/**
		 * Match the transient name behavior needed by the provider under test.
		 *
		 * @param int $post_id Post ID.
		 * @return string
		 */
		protected function get_transient_name( $post_id ) {
			return 'newspack_listmonk_connector_sync_error_' . absint( $post_id );
		}

		/**
		 * Build a campaign name.
		 *
		 * @param WP_Post $post Post.
		 * @return string
		 */
		protected function get_campaign_name( $post ) {
			return get_the_title( $post );
		}
	}
}

if ( ! class_exists( 'Newspack_Newsletters_Service_Provider_Controller' ) ) {
	/**
	 * Minimal Newspack service provider controller test double.
	 */
	class Newspack_Newsletters_Service_Provider_Controller {
		/**
		 * Provider.
		 *
		 * @var object
		 */
		protected $service_provider;

		/**
		 * Constructor.
		 *
		 * @param object $provider Provider.
		 */
		public function __construct( $provider ) {
			$this->service_provider = $provider;
		}

		/**
		 * Stub route registration.
		 *
		 * @return void
		 */
		public function register_routes() {}

		/**
		 * Pass through API responses.
		 *
		 * @param mixed $response Response.
		 * @return mixed
		 */
		public static function get_api_response( $response ) {
			if ( is_wp_error( $response ) ) {
				$response->add_data( array( 'status' => 400 ) );
			}
			return rest_ensure_response( $response );
		}

		/**
		 * Stub user email persistence.
		 *
		 * @param array $emails Emails.
		 * @return void
		 */
		public function update_user_test_emails( $emails ) {}
	}
}

if ( ! class_exists( 'Newspack_Newsletters' ) ) {
	/**
	 * Minimal Newspack facade test double.
	 */
	class Newspack_Newsletters {
		const NEWSPACK_NEWSLETTERS_CPT = 'post';
		const EMAIL_HTML_META          = '_newspack_email_html';

		/**
		 * Provider instance.
		 *
		 * @var object|null
		 */
		public static $provider;

		/**
		 * Active provider slug.
		 *
		 * @return string
		 */
		public static function service_provider() {
			return 'listmonk';
		}

		/**
		 * Store active provider slug.
		 *
		 * @param string $provider Provider slug.
		 * @return void
		 */
		public static function set_service_provider( $provider ) {}

		/**
		 * Get a provider instance.
		 *
		 * @param string $provider Provider slug.
		 * @return object|null
		 */
		public static function get_service_provider_instance( $provider ) {
			return self::$provider;
		}

		/**
		 * Permissions callback.
		 *
		 * @return bool
		 */
		public static function api_authoring_permissions_check() {
			return true;
		}

		/**
		 * Newsletter ID validator.
		 *
		 * @return bool
		 */
		public static function validate_newsletter_id() {
			return true;
		}
	}
}

require_once dirname( __DIR__, 2 ) . '/inc/provider/class-listmonk-provider.php';

/**
 * Tests provider send retry behavior.
 */
class Newspack_Listmonk_Connector_Provider_Send_Retry_Test extends WP_UnitTestCase {
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
	 * Provider under test.
	 *
	 * @var Newspack_Listmonk_Connector_Provider|null
	 */
	private $provider;

	/**
	 * Set up a configured provider.
	 */
	public function set_up() {
		parent::set_up();

		$user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);
		wp_set_current_user( $user_id );

		newspack_listmonk_connector_save_settings(
			array(
				'api_token'          => 'api-token',
				'api_user'           => 'api-user',
				'base_url'           => 'http://listmonk.test:9000',
				'default_from_email' => 'Newsroom <news@example.com>',
				'default_list_ids'   => array( 1 ),
			)
		);

		add_filter( 'pre_http_request', array( $this, 'mock_http_request' ), 10, 3 );

		$this->provider                 = new Newspack_Listmonk_Connector_Provider();
		Newspack_Newsletters::$provider = $this->provider;
	}

	/**
	 * Clean up filters, hooks, and options.
	 */
	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'mock_http_request' ), 10 );
		if ( $this->provider ) {
			remove_action( 'updated_post_meta', array( $this->provider, 'save' ), 10 );
			remove_action( 'wp_trash_post', array( $this->provider, 'trash' ), 10 );
			remove_action( 'before_delete_post', array( $this->provider, 'delete' ), 10 );
		}
		delete_option( newspack_listmonk_connector_get_option_name() );
		wp_set_current_user( 0 );
		$this->requests  = array();
		$this->responses = array();
		$this->provider  = null;
		parent::tear_down();
	}

	/**
	 * Failed scheduled sends persist retryable error metadata.
	 */
	public function test_failed_scheduled_send_stores_retryable_error_details() {
		$post_id = $this->create_scheduled_post();
		$post    = get_post( $post_id );

		$this->queue_response( 200, array( 'data' => array( 'id' => 99, 'status' => 'draft' ) ) );
		$this->queue_lists_response();
		$this->queue_response( 500, array( 'message' => 'Listmonk scheduler offline.' ), 'Server Error' );

		$result = $this->provider->send( $post );

		$this->assertWPError( $result );
		$this->assertSame( 'newspack_listmonk_connector_api_error', $result->get_error_code() );
		$this->assertSame( 'Listmonk scheduler offline.', get_post_meta( $post_id, '_wtnl_listmonk_last_error', true ) );
		$this->assertSame( 'newspack_listmonk_connector_api_error', get_post_meta( $post_id, '_wtnl_listmonk_last_error_code', true ) );
		$this->assertNotEmpty( get_post_meta( $post_id, '_wtnl_listmonk_last_error_at', true ) );
		$this->assertSame( 'draft', get_post_meta( $post_id, '_wtnl_listmonk_last_status', true ) );
		$this->assertStringContainsString( 'Listmonk send error: Listmonk scheduler offline.', get_transient( 'newspack_listmonk_connector_sync_error_' . $post_id ) );
		$this->assert_request( 2, 'PUT', 'http://listmonk.test:9000/api/campaigns/99/status', array( 'status' => 'scheduled' ) );
	}

	/**
	 * Retrying a failed scheduled send clears stale errors after Listmonk accepts the status transition.
	 */
	public function test_retry_send_endpoint_clears_stale_error_after_success() {
		$post_id = $this->create_scheduled_post();
		$post    = get_post( $post_id );

		$this->queue_response( 200, array( 'data' => array( 'id' => 99, 'status' => 'draft' ) ) );
		$this->queue_lists_response();
		$this->queue_response( 500, array( 'message' => 'Listmonk scheduler offline.' ), 'Server Error' );
		$this->provider->send( $post );

		$this->queue_lists_response();
		$this->queue_response( 200, array( 'data' => array( 'status' => 'scheduled' ) ) );
		$this->queue_lists_response();

		$response = newspack_listmonk_connector_newsletter_sync_build_response(
			array(
				'postId'    => $post_id,
				'retrySend' => true,
			)
		);

		$this->assertIsArray( $response );
		$this->assertSame( 'Listmonk send retried.', $response['message'] );
		$this->assertSame( 'scheduled', $response['status'] );
		$this->assertSame( 99, $response['campaignId'] );
		$this->assertSame( '', get_post_meta( $post_id, '_wtnl_listmonk_last_error', true ) );
		$this->assertSame( '', get_post_meta( $post_id, '_wtnl_listmonk_last_error_code', true ) );
		$this->assertSame( '', get_post_meta( $post_id, '_wtnl_listmonk_last_error_at', true ) );
		$this->assertFalse( get_transient( 'newspack_listmonk_connector_sync_error_' . $post_id ) );
		$this->assert_request( 4, 'PUT', 'http://listmonk.test:9000/api/campaigns/99/status', array( 'status' => 'scheduled' ) );
	}

	/**
	 * Newsletter trash without an active campaign clears transient errors only.
	 */
	public function test_trash_without_campaign_id_clears_transient_without_http_request() {
		$post_id = $this->create_draft_post();
		set_transient( 'newspack_listmonk_connector_sync_error_' . $post_id, 'Stale error.', 45 );

		$this->provider->trash( $post_id );

		$this->assertFalse( get_transient( 'newspack_listmonk_connector_sync_error_' . $post_id ) );
		$this->assertCount( 0, $this->requests );
	}

	/**
	 * Trash cancels cancellable Listmonk campaign statuses and clears active campaign meta.
	 *
	 * @dataProvider cancellable_campaign_status_provider
	 *
	 * @param string $status Campaign status.
	 */
	public function test_trash_cancels_cancellable_campaign_statuses( $status ) {
		$post_id = $this->create_draft_post_with_campaign( 88, $status );
		$this->queue_response( 200, array( 'data' => array( 'id' => 88, 'status' => $status ) ) );
		$this->queue_response( 200, array( 'data' => array( 'id' => 88, 'status' => 'cancelled' ) ) );

		$this->provider->trash( $post_id );

		$this->assert_request_without_body( 0, 'GET', 'http://listmonk.test:9000/api/campaigns/88' );
		$this->assert_request( 1, 'PUT', 'http://listmonk.test:9000/api/campaigns/88/status', array( 'status' => 'cancelled' ) );
		$this->assertSame( '', get_post_meta( $post_id, '_wtnl_listmonk_campaign_id', true ) );
		$this->assertSame( '', get_post_meta( $post_id, '_wtnl_listmonk_campaign_uuid', true ) );
		$this->assertSame( '', get_post_meta( $post_id, '_wtnl_listmonk_payload_hash', true ) );
		$this->assertSame( '', get_post_meta( $post_id, '_wtnl_listmonk_last_synced_at', true ) );
		$this->assertSame( '', get_post_meta( $post_id, '_wtnl_listmonk_last_status', true ) );
		$this->assertSame( 88, absint( get_post_meta( $post_id, '_wtnl_listmonk_archived_campaign_id', true ) ) );
		$this->assertSame( 'uuid-88', get_post_meta( $post_id, '_wtnl_listmonk_archived_campaign_uuid', true ) );
		$this->assertSame( 'cancelled', get_post_meta( $post_id, '_wtnl_listmonk_archived_status', true ) );
		$this->assertSame( 'cancelled_cancellable_campaign_trash', get_post_meta( $post_id, '_wtnl_listmonk_archive_policy', true ) );
		$this->assertNotEmpty( get_post_meta( $post_id, '_wtnl_listmonk_archived_at', true ) );
		$this->assertSame( '', get_post_meta( $post_id, '_wtnl_listmonk_last_error', true ) );
	}

	/**
	 * Running campaigns are preserved remotely and keep active meta for inspection.
	 */
	public function test_trash_preserves_running_campaign() {
		$post_id = $this->create_draft_post_with_campaign( 89, 'running' );
		$this->queue_response( 200, array( 'data' => array( 'id' => 89, 'status' => 'running' ) ) );

		$this->provider->trash( $post_id );

		$this->assertCount( 1, $this->requests );
		$this->assert_request_without_body( 0, 'GET', 'http://listmonk.test:9000/api/campaigns/89' );
		$this->assertSame( 89, absint( get_post_meta( $post_id, '_wtnl_listmonk_campaign_id', true ) ) );
		$this->assertSame( 'running', get_post_meta( $post_id, '_wtnl_listmonk_last_status', true ) );
		$this->assertSame( 89, absint( get_post_meta( $post_id, '_wtnl_listmonk_archived_campaign_id', true ) ) );
		$this->assertSame( 'running', get_post_meta( $post_id, '_wtnl_listmonk_archived_status', true ) );
		$this->assertSame( 'preserved_running_campaign_trash', get_post_meta( $post_id, '_wtnl_listmonk_archive_policy', true ) );
	}

	/**
	 * Archive failures are stored without clearing active campaign meta.
	 */
	public function test_trash_archive_failure_stores_error_and_preserves_active_meta() {
		$post_id = $this->create_draft_post_with_campaign( 90, 'paused' );
		$this->queue_response( 200, array( 'data' => array( 'id' => 90, 'status' => 'paused' ) ) );
		$this->queue_response( 500, array( 'message' => 'Unable to cancel campaign.' ), 'Server Error' );

		$this->provider->trash( $post_id );

		$this->assertSame( 90, absint( get_post_meta( $post_id, '_wtnl_listmonk_campaign_id', true ) ) );
		$this->assertSame( 'Unable to cancel campaign.', get_post_meta( $post_id, '_wtnl_listmonk_last_error', true ) );
		$this->assertSame( 'newspack_listmonk_connector_api_error', get_post_meta( $post_id, '_wtnl_listmonk_last_error_code', true ) );
		$this->assertStringContainsString( 'Listmonk archive error: Unable to cancel campaign.', get_transient( 'newspack_listmonk_connector_sync_error_' . $post_id ) );
		$this->assertSame( '', get_post_meta( $post_id, '_wtnl_listmonk_archived_campaign_id', true ) );
	}

	/**
	 * Cancellable campaign statuses.
	 *
	 * @return array
	 */
	public function cancellable_campaign_status_provider() {
		return array(
			'paused' => array( 'paused' ),
		);
	}

	/**
	 * Scheduled campaigns are reverted to draft and detached locally.
	 */
	public function test_trash_reverts_scheduled_campaign_to_draft_and_clears_active_meta() {
		$post_id = $this->create_draft_post_with_campaign( 92, 'scheduled' );
		$this->queue_response( 200, array( 'data' => array( 'id' => 92, 'status' => 'scheduled' ) ) );
		$this->queue_response( 200, array( 'data' => array( 'id' => 92, 'status' => 'draft' ) ) );

		$this->provider->trash( $post_id );

		$this->assert_request_without_body( 0, 'GET', 'http://listmonk.test:9000/api/campaigns/92' );
		$this->assert_request( 1, 'PUT', 'http://listmonk.test:9000/api/campaigns/92/status', array( 'status' => 'draft' ) );
		$this->assertSame( '', get_post_meta( $post_id, '_wtnl_listmonk_campaign_id', true ) );
		$this->assertSame( '', get_post_meta( $post_id, '_wtnl_listmonk_payload_hash', true ) );
		$this->assertSame( 92, absint( get_post_meta( $post_id, '_wtnl_listmonk_archived_campaign_id', true ) ) );
		$this->assertSame( 'draft', get_post_meta( $post_id, '_wtnl_listmonk_archived_status', true ) );
		$this->assertSame( 'reverted_scheduled_campaign_to_draft_trash', get_post_meta( $post_id, '_wtnl_listmonk_archive_policy', true ) );
	}

	/**
	 * Draft campaigns are preserved remotely but detached locally.
	 */
	public function test_trash_preserves_draft_campaign_and_clears_active_meta() {
		$post_id = $this->create_draft_post_with_campaign( 91, 'draft' );
		$this->queue_response( 200, array( 'data' => array( 'id' => 91, 'status' => 'draft' ) ) );

		$this->provider->trash( $post_id );

		$this->assertCount( 1, $this->requests );
		$this->assert_request_without_body( 0, 'GET', 'http://listmonk.test:9000/api/campaigns/91' );
		$this->assertSame( '', get_post_meta( $post_id, '_wtnl_listmonk_campaign_id', true ) );
		$this->assertSame( '', get_post_meta( $post_id, '_wtnl_listmonk_payload_hash', true ) );
		$this->assertSame( 91, absint( get_post_meta( $post_id, '_wtnl_listmonk_archived_campaign_id', true ) ) );
		$this->assertSame( 'draft', get_post_meta( $post_id, '_wtnl_listmonk_archived_status', true ) );
		$this->assertSame( 'preserved_draft_campaign_trash', get_post_meta( $post_id, '_wtnl_listmonk_archive_policy', true ) );
	}

	/**
	 * add_contact creates a missing Listmonk subscriber with sanitized metadata.
	 */
	public function test_add_contact_creates_missing_subscriber() {
		$this->queue_response( 200, array( 'data' => array( 'results' => array() ) ) );
		$this->queue_response( 200, array( 'data' => array( 'id' => 120, 'email' => 'reader@example.com' ) ) );

		$result = $this->provider->add_contact(
			array(
				'email'    => 'Reader@Example.com',
				'name'     => 'Reader <b>Name</b>',
				'metadata' => array(
					'Membership Level' => 'paid',
					'nested'           => array(
						'City' => 'Seoul',
					),
				),
			),
			2
		);

		$this->assertIsArray( $result );
		$this->assertSame( 120, $result['data']['id'] );
		$this->assert_request_without_body(
			0,
			'GET',
			add_query_arg(
				array(
					'per_page' => 'all',
				),
				'http://listmonk.test:9000/api/subscribers'
			)
		);
		$this->assert_request(
			1,
			'POST',
			'http://listmonk.test:9000/api/subscribers',
			array(
				'email'                    => 'reader@example.com',
				'name'                     => 'Reader Name',
				'status'                   => 'enabled',
				'lists'                    => array( 2 ),
				'attribs'                  => array(
					'membershiplevel' => 'paid',
					'nested'          => array(
						'city' => 'Seoul',
					),
				),
				'preconfirm_subscriptions' => false,
			)
		);
	}

	/**
	 * add_contact patches an existing subscriber and adds the requested list membership.
	 */
	public function test_add_contact_updates_existing_subscriber_and_adds_list() {
		$this->queue_response(
			200,
			array(
				'data' => array(
					'results' => array(
						array(
							'id'    => 121,
							'email' => 'reader@example.com',
							'lists' => array(),
						),
					),
				),
			)
		);
		$this->queue_response( 200, array( 'data' => array( 'id' => 121, 'email' => 'reader@example.com' ) ) );
		$this->queue_response( 200, array( 'data' => true ) );

		$result = $this->provider->add_contact(
			array(
				'email'    => 'reader@example.com',
				'name'     => 'Reader',
				'metadata' => array( 'membership' => 'paid' ),
			),
			3
		);

		$this->assertIsArray( $result );
		$this->assert_request(
			1,
			'PATCH',
			'http://listmonk.test:9000/api/subscribers/121',
			array(
				'email'                    => 'reader@example.com',
				'name'                     => 'Reader',
				'status'                   => 'enabled',
				'attribs'                  => array( 'membership' => 'paid' ),
				'preconfirm_subscriptions' => false,
			)
		);
		$this->assert_request(
			2,
			'PUT',
			'http://listmonk.test:9000/api/subscribers/lists',
			array(
				'ids'             => array( 121 ),
				'action'          => 'add',
				'target_list_ids' => array( 3 ),
				'status'          => 'unconfirmed',
			)
		);
	}

	/**
	 * get_contact_lists returns numeric Listmonk list IDs.
	 */
	public function test_get_contact_lists_returns_list_ids() {
		$this->queue_response(
			200,
			array(
				'data' => array(
					'results' => array(
						array(
							'id'    => 122,
							'email' => 'reader@example.com',
							'lists' => array(
								array( 'id' => 3 ),
								array( 'id' => 4 ),
							),
						),
					),
				),
			)
		);

		$this->assertSame( array( 3, 4 ), $this->provider->get_contact_lists( 'reader@example.com' ) );
	}

	/**
	 * update_contact_lists delegates add and remove membership changes to Listmonk.
	 */
	public function test_update_contact_lists_adds_and_removes_memberships() {
		$this->queue_response(
			200,
			array(
				'data' => array(
					'results' => array(
						array(
							'id'    => 123,
							'email' => 'reader@example.com',
							'lists' => array(),
						),
					),
				),
			)
		);
		$this->queue_response( 200, array( 'data' => true ) );
		$this->queue_response( 200, array( 'data' => true ) );

		$result = $this->provider->update_contact_lists( 'reader@example.com', array( '5', '5' ), array( 6 ) );

		$this->assertTrue( $result );
		$this->assert_request(
			1,
			'PUT',
			'http://listmonk.test:9000/api/subscribers/lists',
			array(
				'ids'             => array( 123 ),
				'action'          => 'add',
				'target_list_ids' => array( 5 ),
				'status'          => 'unconfirmed',
			)
		);
		$this->assert_request(
			2,
			'PUT',
			'http://listmonk.test:9000/api/subscribers/lists',
			array(
				'ids'             => array( 123 ),
				'action'          => 'remove',
				'target_list_ids' => array( 6 ),
			)
		);
	}

	/**
	 * Create a future newsletter post.
	 *
	 * @return int
	 */
	private function create_scheduled_post() {
		$future_gmt = gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS );
		$post_id    = self::factory()->post->create(
			array(
				'post_content'  => '<!-- wp:paragraph --><p>Scheduled body.</p><!-- /wp:paragraph -->',
				'post_date'     => get_date_from_gmt( $future_gmt ),
				'post_date_gmt' => $future_gmt,
				'post_status'   => 'future',
				'post_title'    => 'Scheduled Send',
				'post_type'     => Newspack_Newsletters::NEWSPACK_NEWSLETTERS_CPT,
			)
		);

		update_post_meta( $post_id, 'send_list_id', 1 );
		update_post_meta( $post_id, 'senderEmail', 'news@example.com' );
		update_post_meta( $post_id, 'senderName', 'Newsroom' );

		return $post_id;
	}

	/**
	 * Create a draft newsletter post.
	 *
	 * @return int
	 */
	private function create_draft_post() {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => '<!-- wp:paragraph --><p>Draft body.</p><!-- /wp:paragraph -->',
				'post_status'  => 'draft',
				'post_title'   => 'Draft Newsletter',
				'post_type'    => Newspack_Newsletters::NEWSPACK_NEWSLETTERS_CPT,
			)
		);

		update_post_meta( $post_id, 'send_list_id', 1 );
		update_post_meta( $post_id, 'senderEmail', 'news@example.com' );
		update_post_meta( $post_id, 'senderName', 'Newsroom' );

		return $post_id;
	}

	/**
	 * Create a draft newsletter post with active Listmonk campaign meta.
	 *
	 * @param int    $campaign_id Campaign ID.
	 * @param string $status Campaign status.
	 * @return int
	 */
	private function create_draft_post_with_campaign( $campaign_id, $status ) {
		$post_id = $this->create_draft_post();

		update_post_meta( $post_id, '_wtnl_listmonk_campaign_id', absint( $campaign_id ) );
		update_post_meta( $post_id, '_wtnl_listmonk_campaign_uuid', 'uuid-' . absint( $campaign_id ) );
		update_post_meta( $post_id, '_wtnl_listmonk_payload_hash', 'hash-' . absint( $campaign_id ) );
		update_post_meta( $post_id, '_wtnl_listmonk_last_synced_at', gmdate( 'c' ) );
		update_post_meta( $post_id, '_wtnl_listmonk_last_status', sanitize_text_field( $status ) );

		return $post_id;
	}

	/**
	 * Queue a Listmonk lists response.
	 *
	 * @return void
	 */
	private function queue_lists_response() {
		$this->queue_response(
			200,
			array(
				'data' => array(
					'results' => array(
						array(
							'id'               => 1,
							'name'             => 'Daily News',
							'subscriber_count' => 10,
						),
					),
				),
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
