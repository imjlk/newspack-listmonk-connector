<?php
/**
 * Settings tests.
 *
 * @package Newspack_Listmonk_Connector
 */

/**
 * Tests settings sanitization and public response behavior.
 */
class Newspack_Listmonk_Connector_Settings_Test extends WP_UnitTestCase {
	/**
	 * Clean settings option after each test.
	 */
	public function tear_down() {
		delete_option( newspack_listmonk_connector_get_option_name() );
		parent::tear_down();
	}

	/**
	 * A blank API token keeps the existing stored token.
	 */
	public function test_blank_api_token_preserves_existing_token() {
		$settings = newspack_listmonk_connector_sanitize_settings(
			array(
				'api_token' => '',
			),
			array(
				'api_token' => 'existing-token',
			)
		);

		$this->assertSame( 'existing-token', $settings['api_token'] );
	}

	/**
	 * Public settings expose token presence without exposing the token value.
	 */
	public function test_public_settings_mask_api_token() {
		$response = newspack_listmonk_connector_get_public_settings_response(
			array(
				'api_token'           => 'secret-token',
				'api_user'            => 'api-user',
				'base_url'            => 'https://listmonk.example.com',
				'default_from_email'  => 'news@example.com',
				'default_template_id' => 3,
				'default_list_ids'    => array( 1, 2 ),
			)
		);

		$this->assertTrue( $response['hasApiToken'] );
		$this->assertArrayNotHasKey( 'apiToken', $response );
		$this->assertArrayNotHasKey( 'api_token', $response );
	}

	/**
	 * Display-name From email syntax is preserved while unsafe control chars are removed.
	 */
	public function test_display_name_from_email_is_preserved() {
		$settings = newspack_listmonk_connector_sanitize_settings(
			array(
				'default_from_email' => "Newsroom\r\n <news@example.com>",
			)
		);

		$this->assertSame( 'Newsroom <news@example.com>', $settings['default_from_email'] );
	}

	/**
	 * Invalid angle-bracket emails fall back to text sanitization.
	 */
	public function test_invalid_display_name_email_is_sanitized_as_text() {
		$settings = newspack_listmonk_connector_sanitize_settings(
			array(
				'default_from_email' => 'Newsroom <not-an-email>',
			)
		);

		$this->assertSame( 'Newsroom', $settings['default_from_email'] );
	}
}
