<?php
/**
 * Newsletter preview tests.
 *
 * @package Newspack_Listmonk_Connector
 */

/**
 * Tests preview payload mapping.
 */
class Newspack_Listmonk_Connector_Newsletter_Preview_Test extends WP_UnitTestCase {
	/**
	 * User ID.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * Set up an editor user.
	 */
	public function set_up() {
		parent::set_up();

		$this->user_id = self::factory()->user->create(
			array(
				'role' => 'editor',
			)
		);
		wp_set_current_user( $this->user_id );
	}

	/**
	 * Clean settings option after each test.
	 */
	public function tear_down() {
		delete_option( newspack_listmonk_connector_get_option_name() );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Preview response uses defaults and builds stable Listmonk payload.
	 */
	public function test_preview_uses_defaults_and_builds_campaign_payload() {
		newspack_listmonk_connector_save_settings(
			array(
				'default_from_email'  => 'Newsroom <news@example.com>',
				'default_template_id' => 12,
				'default_list_ids'    => '4,5',
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_author'  => $this->user_id,
				'post_title'   => 'Weekly &amp; Digest',
				'post_content' => '<!-- wp:paragraph --><p>Preview body.</p><!-- /wp:paragraph -->',
			)
		);

		$response = newspack_listmonk_connector_newsletter_preview_build_response(
			array(
				'postId' => $post_id,
			)
		);

		$this->assertIsArray( $response );
		$this->assertSame( $post_id, $response['postId'] );
		$this->assertSame( 'Weekly & Digest', $response['subject'] );
		$this->assertSame( array( 4, 5 ), $response['listIds'] );
		$this->assertSame( 'Newsroom <news@example.com>', $response['fromEmail'] );
		$this->assertSame( 12, $response['templateId'] );
		$this->assertSame( 'campaign', $response['listmonkPayload']['sendMode'] );
		$this->assertSame( $response['fromEmail'], $response['listmonkPayload']['fromEmail'] );
		$this->assertSame( md5( wp_json_encode( $response['listmonkPayload'] ) ), $response['payloadHash'] );
	}

	/**
	 * Preview request From email override uses shared sanitizer.
	 */
	public function test_preview_from_email_override_preserves_display_name() {
		$post_id = self::factory()->post->create(
			array(
				'post_author'  => $this->user_id,
				'post_title'   => 'Override Test',
				'post_content' => '<p>Preview body.</p>',
			)
		);

		$response = newspack_listmonk_connector_newsletter_preview_build_response(
			array(
				'fromEmail' => "Editor\r\n <editor@example.com>",
				'postId'    => $post_id,
			)
		);

		$this->assertIsArray( $response );
		$this->assertSame( 'Editor <editor@example.com>', $response['fromEmail'] );
		$this->assertSame( 'Editor <editor@example.com>', $response['listmonkPayload']['fromEmail'] );
	}
}
