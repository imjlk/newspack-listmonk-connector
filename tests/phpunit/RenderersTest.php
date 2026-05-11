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

	/**
	 * Supported simple selectors are inlined into matching elements.
	 */
	public function test_email_html_processor_inlines_simple_selectors() {
		$html = '<!doctype html><html><head><style>p { color: red; } .lead, #hero { font-weight: bold; } span.note { font-size: 14px; }</style></head><body><p class="lead">Hello</p><div id="hero">Hero</div><span class="note">Note</span></body></html>';

		$output = ( new Newspack_Listmonk_Connector_Email_HTML_Processor() )->process( $html );

		$this->assertStringContainsString( '<!doctype html>', $output );
		$this->assertStringContainsString( '<p class="lead" style="color: red;font-weight: bold">Hello</p>', $output );
		$this->assertStringContainsString( '<div id="hero" style="font-weight: bold">Hero</div>', $output );
		$this->assertStringContainsString( '<span class="note" style="font-size: 14px">Note</span>', $output );
	}

	/**
	 * Existing inline properties win over stylesheet declarations.
	 */
	public function test_email_html_processor_preserves_existing_inline_properties() {
		$html = '<html><head><style>.lead { color: red; font-weight: bold; } .lead { color: blue; background-color: #fff; }</style></head><body><p class="lead" style="color: green;">Hello</p></body></html>';

		$output = ( new Newspack_Listmonk_Connector_Email_HTML_Processor() )->process( $html );

		preg_match( '/<p[^>]+style="([^"]+)"/', $output, $matches );
		$this->assertNotEmpty( $matches );
		$this->assertSame( 'color: green;font-weight: bold;background-color: #fff', $matches[1] );
	}

	/**
	 * Unsupported nested CSS remains in style tags.
	 */
	public function test_email_html_processor_preserves_unsupported_media_rules() {
		$html = '<html><head><style>@media screen { .lead { color: blue; } } .lead { color: red; }</style></head><body><p class="lead">Hello</p></body></html>';

		$output = ( new Newspack_Listmonk_Connector_Email_HTML_Processor() )->process( $html );

		$this->assertStringContainsString( '@media screen', $output );
		$this->assertStringContainsString( 'style="color: red"', $output );
	}

	/**
	 * Unsafe tags, event handlers, and javascript URLs are removed.
	 */
	public function test_email_html_processor_removes_unsafe_html() {
		$html = '<html><body><a href="javascript:alert(1)" onclick="alert(1)">Bad</a><script>alert(1)</script><iframe src="/frame"></iframe><img src="/image.jpg" srcset="/small.jpg 1x, javascript:alert(1) 2x, https://example.com/large.jpg 2x"></body></html>';

		$output = ( new Newspack_Listmonk_Connector_Email_HTML_Processor() )->process( $html );

		$this->assertStringNotContainsString( '<script', $output );
		$this->assertStringNotContainsString( '<iframe', $output );
		$this->assertStringNotContainsString( 'onclick', $output );
		$this->assertStringNotContainsString( 'javascript:', $output );
		$this->assertStringNotContainsString( 'href=', $output );
		$this->assertStringContainsString( 'src="' . home_url( '/image.jpg' ) . '"', $output );
		$this->assertStringContainsString( 'srcset="' . home_url( '/small.jpg' ) . ' 1x, https://example.com/large.jpg 2x"', $output );
	}

	/**
	 * The non-DOM fallback skips CSS inlining but still performs minimal cleanup.
	 */
	public function test_email_html_processor_without_dom_falls_back_safely() {
		$html = '<style>.lead { color: red; }</style><p class="lead" onclick="alert(1)">Hello</p><script>alert(1)</script><img src="/image.jpg">';

		$output = ( new Newspack_Listmonk_Connector_Email_HTML_Processor( false ) )->process( $html );

		$this->assertStringContainsString( '<style>.lead { color: red; }</style>', $output );
		$this->assertStringContainsString( '<p class="lead">Hello</p>', $output );
		$this->assertStringContainsString( 'src="' . home_url( '/image.jpg' ) . '"', $output );
		$this->assertStringNotContainsString( 'onclick', $output );
		$this->assertStringNotContainsString( '<script', $output );
		$this->assertStringNotContainsString( 'style="color: red', $output );
	}
}
