<?php
/**
 * Renderer tests.
 *
 * @package Newspack_Listmonk_Connector
 */

/**
 * Tests raw HTML and plain text builders.
 */
class Newspack_Listmonk_Connector_Renderers_Test extends WP_UnitTestCase {
	/**
	 * Raw HTML fallback wraps block content in a minimal document.
	 */
	public function test_raw_html_builder_wraps_fallback_content() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Renderer Test',
				'post_content' => '<!-- wp:paragraph --><p>Hello world.</p><!-- /wp:paragraph -->',
			)
		);

		$html = ( new Newspack_Listmonk_Connector_Raw_HTML_Builder() )->build( get_post( $post_id ) );

		$this->assertStringContainsString( '<!doctype html>', $html );
		$this->assertStringContainsString( '<title>Renderer Test</title>', $html );
		$this->assertStringContainsString( '<p>Hello world.</p>', $html );
	}

	/**
	 * Root-relative links and image URLs are made absolute.
	 */
	public function test_raw_html_builder_absolutizes_root_relative_urls() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'URL Test',
				'post_content' => '<a href="/newsletter">Read</a><img src="/image.jpg" alt="">',
			)
		);

		$html = ( new Newspack_Listmonk_Connector_Raw_HTML_Builder() )->build( get_post( $post_id ) );

		$this->assertStringContainsString( 'href="' . home_url( '/newsletter' ) . '"', $html );
		$this->assertStringContainsString( 'src="' . home_url( '/image.jpg' ) . '"', $html );
	}

	/**
	 * Plain text builder strips tags and decodes entities.
	 */
	public function test_plain_text_builder_strips_tags_and_decodes_entities() {
		$text = ( new Newspack_Listmonk_Connector_Plain_Text_Builder() )->build(
			'<p>Hello&nbsp;<strong>world</strong> &amp; friends.</p>'
		);

		$this->assertSame( 'Hello world & friends.', $text );
	}
}
