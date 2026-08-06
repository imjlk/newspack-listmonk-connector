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
	 * Remove filters registered by individual tests.
	 */
	public function tear_down() {
		remove_filter( 'newspack_listmonk_connector_should_append_unsubscribe_footer', array( $this, 'disable_unsubscribe_footer' ), 10 );
		remove_filter( 'newspack_listmonk_connector_unsubscribe_footer_html', array( $this, 'custom_unsubscribe_footer' ), 10 );
		parent::tear_down();
	}

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
		$this->assertStringContainsString( 'Hello world.', $html );
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
	 * Raw HTML without a Listmonk template gets a default unsubscribe footer.
	 */
	public function test_raw_html_builder_appends_unsubscribe_footer_without_template() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Footer Test',
				'post_content' => '<p>Newsletter body.</p>',
			)
		);

		$html = ( new Newspack_Listmonk_Connector_Raw_HTML_Builder() )->build(
			get_post( $post_id ),
			array( 'template_id' => 0 )
		);

		$this->assertStringContainsString( 'wp-typia-newsletter-connector-unsubscribe-footer', $html );
		$this->assertStringContainsString( 'href="{{ UnsubscribeURL }}"', $html );
		$this->assertStringContainsString( 'Unsubscribe or manage preferences', $html );
		$this->assertStringNotContainsString( 'TrackView', $html );
	}

	/**
	 * Existing Listmonk unsubscribe placeholders prevent duplicate footers.
	 */
	public function test_raw_html_builder_does_not_duplicate_existing_unsubscribe_placeholder() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Existing Footer Test',
				'post_content' => '<p><a href="{{ UnsubscribeURL }}">Manage preferences</a></p>',
			)
		);

		$html = ( new Newspack_Listmonk_Connector_Raw_HTML_Builder() )->build(
			get_post( $post_id ),
			array( 'template_id' => 0 )
		);

		$this->assertSame( 1, substr_count( $html, 'UnsubscribeURL' ) );
		$this->assertStringContainsString( 'href="{{ UnsubscribeURL }}"', $html );
		$this->assertStringNotContainsString( '%7B%7B', $html );
		$this->assertStringNotContainsString( 'wp-typia-newsletter-connector-unsubscribe-footer', $html );
	}

	/**
	 * Listmonk templates are expected to own their own unsubscribe footer.
	 */
	public function test_raw_html_builder_does_not_append_unsubscribe_footer_with_template() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Template Footer Test',
				'post_content' => '<p>Newsletter body.</p>',
			)
		);

		$html = ( new Newspack_Listmonk_Connector_Raw_HTML_Builder() )->build(
			get_post( $post_id ),
			array( 'template_id' => 7 )
		);

		$this->assertStringNotContainsString( 'UnsubscribeURL', $html );
		$this->assertStringNotContainsString( 'wp-typia-newsletter-connector-unsubscribe-footer', $html );
	}

	/**
	 * Sites can disable the default unsubscribe footer with a filter.
	 */
	public function test_raw_html_builder_allows_filter_to_disable_unsubscribe_footer() {
		add_filter( 'newspack_listmonk_connector_should_append_unsubscribe_footer', array( $this, 'disable_unsubscribe_footer' ), 10, 4 );

		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Disabled Footer Test',
				'post_content' => '<p>Newsletter body.</p>',
			)
		);

		$html = ( new Newspack_Listmonk_Connector_Raw_HTML_Builder() )->build( get_post( $post_id ) );

		$this->assertStringNotContainsString( 'UnsubscribeURL', $html );
	}

	/**
	 * Sites can replace the default unsubscribe footer HTML.
	 */
	public function test_raw_html_builder_allows_filter_to_customize_unsubscribe_footer() {
		add_filter( 'newspack_listmonk_connector_unsubscribe_footer_html', array( $this, 'custom_unsubscribe_footer' ), 10, 3 );

		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Custom Footer Test',
				'post_content' => '<p>Newsletter body.</p>',
			)
		);

		$html = ( new Newspack_Listmonk_Connector_Raw_HTML_Builder() )->build( get_post( $post_id ) );

		$this->assertStringContainsString( '<footer class="custom-listmonk-footer">', $html );
		$this->assertStringContainsString( 'Custom unsubscribe', $html );
		$this->assertStringContainsString( 'href="{{ UnsubscribeURL }}"', $html );
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
	 * Plain text builder preserves link URLs for unsubscribe placeholders.
	 */
	public function test_plain_text_builder_preserves_link_urls() {
		$text = ( new Newspack_Listmonk_Connector_Plain_Text_Builder() )->build(
			'<p><a href="{{ UnsubscribeURL }}">Unsubscribe or manage preferences</a></p>'
		);

		$this->assertSame( 'Unsubscribe or manage preferences: {{ UnsubscribeURL }}', $text );
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
	 * Known Listmonk placeholders survive URL cleanup.
	 */
	public function test_email_html_processor_preserves_known_listmonk_placeholders() {
		$html = '<html><body><a href="{{ TrackView }}">Track</a><a href="{{%20UnsubscribeURL%20}}">Unsubscribe</a></body></html>';

		$output = ( new Newspack_Listmonk_Connector_Email_HTML_Processor() )->process( $html );

		$this->assertStringContainsString( 'href="{{ TrackView }}"', $output );
		$this->assertStringContainsString( 'href="{{ UnsubscribeURL }}"', $output );
		$this->assertStringNotContainsString( '%7B%7B', $output );
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

	/**
	 * Disable unsubscribe footer callback.
	 *
	 * @return bool
	 */
	public function disable_unsubscribe_footer() {
		return false;
	}

	/**
	 * Custom unsubscribe footer callback.
	 *
	 * @return string
	 */
	public function custom_unsubscribe_footer() {
		return '<footer class="custom-listmonk-footer"><a href="{{ UnsubscribeURL }}">Custom unsubscribe</a></footer>';
	}
}
