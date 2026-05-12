<?php
/**
 * Newspack newsletter HTML renderer.
 *
 * @package Newspack_Listmonk_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds email-safe-ish raw HTML for Listmonk.
 */
class Newspack_Listmonk_Connector_Raw_HTML_Builder {
	/**
	 * Build HTML for a newsletter post.
	 *
	 * @param WP_Post $post Newsletter post.
	 * @param array   $context Optional render context.
	 * @return string
	 */
	public function build( WP_Post $post, array $context = array() ) {
		$html = '';

		if ( class_exists( 'Newspack_Newsletters_Renderer' ) ) {
			$renderer = new Newspack_Newsletters_Renderer();
			if ( method_exists( $renderer, 'retrieve_email_html' ) ) {
				$html = (string) $renderer->retrieve_email_html( $post );
			}
		}

		if ( '' === trim( $html ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Use the core content pipeline as the fallback renderer.
			$content = apply_filters( 'the_content', $post->post_content );
			$html    = $this->wrap_document( $post, (string) $content );
		}

		$html = $this->absolutize_urls( $html );
		$html = ( new Newspack_Listmonk_Connector_Email_HTML_Processor() )->process( $html );
		$html = $this->append_unsubscribe_footer( $html, $post, $context );

		return (string) apply_filters( 'newspack_listmonk_connector_raw_html', $html, $post );
	}

	/**
	 * Wrap fallback rendered content in a minimal email document.
	 *
	 * @param WP_Post $post Newsletter post.
	 * @param string  $content Rendered content.
	 * @return string
	 */
	private function wrap_document( WP_Post $post, $content ) {
		if ( false !== stripos( $content, '<html' ) ) {
			return $content;
		}

		return sprintf(
			'<!doctype html><html><head><meta charset="%s"><title>%s</title></head><body>%s</body></html>',
			esc_attr( get_bloginfo( 'charset' ) ),
			esc_html( wp_strip_all_tags( $post->post_title ) ),
			$content
		);
	}

	/**
	 * Convert root-relative href/src attributes to absolute site URLs.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	private function absolutize_urls( $html ) {
		$home = home_url( '/' );

		return preg_replace_callback(
			'/\s(href|src)=(["\'])\/(?!\/)([^"\']*)\2/i',
			static function ( $matches ) use ( $home ) {
				return sprintf( ' %s=%s%s%s', $matches[1], $matches[2], esc_url_raw( $home . ltrim( $matches[3], '/' ) ), $matches[2] );
			},
			$html
		);
	}

	/**
	 * Append a default Listmonk unsubscribe footer when raw campaign HTML is not
	 * wrapped by a Listmonk template.
	 *
	 * @param string  $html HTML.
	 * @param WP_Post $post Newsletter post.
	 * @param array   $context Render context.
	 * @return string
	 */
	private function append_unsubscribe_footer( $html, WP_Post $post, array $context ) {
		$template_id = absint( $context['template_id'] ?? 0 );
		$should      = 0 === $template_id && false === stripos( $html, 'UnsubscribeURL' );

		/**
		 * Filter whether the connector should append the default Listmonk
		 * unsubscribe footer to raw campaign HTML.
		 *
		 * @param bool    $should Whether to append the footer.
		 * @param string  $html Rendered newsletter HTML.
		 * @param WP_Post $post Newsletter post.
		 * @param array   $context Render context.
		 */
		$should = (bool) apply_filters( 'newspack_listmonk_connector_should_append_unsubscribe_footer', $should, $html, $post, $context );
		if ( ! $should ) {
			return $html;
		}

		$footer = $this->get_unsubscribe_footer_html( $post, $context );
		if ( '' === trim( $footer ) ) {
			return $html;
		}

		if ( false !== stripos( $html, '</body>' ) ) {
			return preg_replace( '/<\/body\s*>/i', $footer . '</body>', $html, 1 );
		}

		return $html . $footer;
	}

	/**
	 * Get the default Listmonk unsubscribe footer HTML.
	 *
	 * @param WP_Post $post Newsletter post.
	 * @param array   $context Render context.
	 * @return string
	 */
	private function get_unsubscribe_footer_html( WP_Post $post, array $context ) {
		$footer = '<footer class="newspack-listmonk-connector-unsubscribe-footer" style="border-top: 1px solid #dcdcde; color: #646970; font-family: Arial, sans-serif; font-size: 12px; line-height: 1.5; margin-top: 32px; padding-top: 16px; text-align: center;"><p style="margin: 0;"><a href="{{ UnsubscribeURL }}" style="color: #646970; text-decoration: underline;">Unsubscribe or manage preferences</a></p></footer>';

		/**
		 * Filter the default Listmonk unsubscribe footer HTML.
		 *
		 * The footer is inserted after DOM cleanup so Listmonk's Go template
		 * expression remains exactly `{{ UnsubscribeURL }}`.
		 *
		 * @param string  $footer Footer HTML.
		 * @param WP_Post $post Newsletter post.
		 * @param array   $context Render context.
		 */
		return (string) apply_filters( 'newspack_listmonk_connector_unsubscribe_footer_html', $footer, $post, $context );
	}
}
