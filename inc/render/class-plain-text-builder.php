<?php
/**
 * Plain text renderer.
 *
 * @package Newspack_Listmonk_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds Listmonk altbody content.
 */
class Newspack_Listmonk_Connector_Plain_Text_Builder {
	/**
	 * Convert HTML into readable plain text.
	 *
	 * @param string $html HTML body.
	 * @return string
	 */
	public function build( $html ) {
		$html = $this->preserve_link_urls( (string) $html );
		$text = html_entity_decode( wp_strip_all_tags( $html, true ), ENT_QUOTES, get_bloginfo( 'charset' ) );
		$text = str_replace( "\xc2\xa0", ' ', $text );
		$text = preg_replace( "/[ \t]+/", ' ', $text );
		$text = preg_replace( "/\n{3,}/", "\n\n", $text );

		return trim( (string) $text );
	}

	/**
	 * Convert links into text that preserves the destination URL.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	private function preserve_link_urls( $html ) {
		return preg_replace_callback(
			'#<a\b([^>]*)>(.*?)</a>#is',
			function ( $matches ) {
				$href = $this->get_href_attribute( $matches[1] );
				$text = trim( html_entity_decode( wp_strip_all_tags( $matches[2], true ), ENT_QUOTES, get_bloginfo( 'charset' ) ) );

				if ( '' === $href || $this->is_unsafe_url( $href ) ) {
					return $text;
				}

				if ( '' === $text || $text === $href ) {
					return $href;
				}

				return $text . ': ' . $href;
			},
			$html
		);
	}

	/**
	 * Extract an href attribute from an anchor attribute string.
	 *
	 * @param string $attributes Anchor attributes.
	 * @return string
	 */
	private function get_href_attribute( $attributes ) {
		if ( preg_match( '/\bhref\s*=\s*(["\'])(.*?)\1/is', (string) $attributes, $matches ) ) {
			return html_entity_decode( trim( $matches[2] ), ENT_QUOTES, get_bloginfo( 'charset' ) );
		}

		if ( preg_match( '/\bhref\s*=\s*([^\s>]+)/is', (string) $attributes, $matches ) ) {
			return html_entity_decode( trim( $matches[1], "\"'\t\n\r\0\x0B " ), ENT_QUOTES, get_bloginfo( 'charset' ) );
		}

		return '';
	}

	/**
	 * Detect unsafe link URLs.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	private function is_unsafe_url( $url ) {
		$url = preg_replace( '/[\x00-\x20]+/', '', (string) $url );

		return (bool) preg_match( '/^javascript:/i', (string) $url );
	}
}
