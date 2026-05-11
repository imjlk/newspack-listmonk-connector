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
		$text = html_entity_decode( wp_strip_all_tags( (string) $html, true ), ENT_QUOTES, get_bloginfo( 'charset' ) );
		$text = str_replace( "\xc2\xa0", ' ', $text );
		$text = preg_replace( "/[ \t]+/", ' ', $text );
		$text = preg_replace( "/\n{3,}/", "\n\n", $text );

		return trim( (string) $text );
	}
}
