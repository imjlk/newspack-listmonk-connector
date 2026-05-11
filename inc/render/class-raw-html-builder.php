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
	 * @return string
	 */
	public function build( WP_Post $post ) {
		$html = '';

		if ( class_exists( 'Newspack_Newsletters_Renderer' ) ) {
			$renderer = new Newspack_Newsletters_Renderer();
			if ( method_exists( $renderer, 'retrieve_email_html' ) ) {
				$html = (string) $renderer->retrieve_email_html( $post );
			}
		}

		if ( '' === trim( $html ) ) {
			$content = apply_filters( 'the_content', $post->post_content );
			$html    = $this->wrap_document( $post, (string) $content );
		}

		$html = $this->absolutize_urls( $html );

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
}
