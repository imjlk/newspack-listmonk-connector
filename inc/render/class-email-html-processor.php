<?php
/**
 * Email HTML post-processor.
 *
 * @package Newspack_Listmonk_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies conservative email-safe cleanup and CSS inlining.
 */
class Newspack_Listmonk_Connector_Email_HTML_Processor {
	/**
	 * Whether DOMDocument processing is available.
	 *
	 * @var bool
	 */
	private $dom_available;

	/**
	 * Constructor.
	 *
	 * @param bool|null $dom_available Optional override for tests.
	 */
	public function __construct( $dom_available = null ) {
		$this->dom_available = null === $dom_available ? class_exists( 'DOMDocument' ) : (bool) $dom_available;
	}

	/**
	 * Process raw newsletter HTML for email delivery.
	 *
	 * @param string $html Raw HTML.
	 * @return string
	 */
	public function process( $html ) {
		$html = (string) $html;

		if ( ! $this->dom_available ) {
			return $this->cleanup_without_dom( $html );
		}

		$dom = new DOMDocument( '1.0', 'UTF-8' );

		$had_doctype = (bool) preg_match( '/^\s*<!doctype\s+html/i', $html );
		$previous    = libxml_use_internal_errors( true );
		$loaded      = $dom->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded ) {
			return $this->cleanup_without_dom( $html );
		}

		$this->remove_xml_processing_instruction( $dom );
		$this->inline_css( $dom );
		$this->cleanup_dom( $dom );

		$output = (string) $dom->saveHTML();

		if ( $had_doctype ) {
			$output = preg_replace( '/^\s*<!DOCTYPE html>\s*/i', '<!doctype html>', $output, 1 );
		}

		return $this->restore_listmonk_template_placeholders( $output );
	}

	/**
	 * Remove the temporary XML processing instruction used for UTF-8 parsing.
	 *
	 * @param DOMDocument $dom Document.
	 */
	private function remove_xml_processing_instruction( DOMDocument $dom ) {
		foreach ( iterator_to_array( $dom->childNodes ) as $node ) {
			if ( XML_PI_NODE === $node->nodeType ) {
				$dom->removeChild( $node );
			}
		}
	}

	/**
	 * Inline supported simple CSS selectors.
	 *
	 * @param DOMDocument $dom Document.
	 */
	private function inline_css( DOMDocument $dom ) {
		$rules = array();

		foreach ( $dom->getElementsByTagName( 'style' ) as $style ) {
			$rules = array_merge( $rules, $this->parse_css_rules( (string) $style->textContent ) );
		}

		if ( empty( $rules ) ) {
			return;
		}

		$xpath             = new DOMXPath( $dom );
		$elements          = array();
		$original_styles   = array();
		$stylesheet_styles = array();
		$next_id           = 1;

		foreach ( iterator_to_array( $dom->getElementsByTagName( '*' ) ) as $element ) {
			if ( ! $element instanceof DOMElement ) {
				continue;
			}

			$id = (string) $next_id++;
			$element->setAttribute( 'data-newspack-listmonk-css-id', $id );

			$style = $this->parse_declarations( $element->getAttribute( 'style' ) );

			$elements[ $id ]        = $element;
			$original_styles[ $id ] = $style;
		}

		foreach ( $rules as $rule ) {
			foreach ( $rule['selectors'] as $selector ) {
				$query = $this->selector_to_xpath( $selector );
				if ( '' === $query ) {
					continue;
				}

				$nodes = $xpath->query( $query );
				if ( ! $nodes ) {
					continue;
				}

				foreach ( $nodes as $node ) {
					if ( ! $node instanceof DOMElement ) {
						continue;
					}

					$id = $node->getAttribute( 'data-newspack-listmonk-css-id' );
					if ( '' === $id ) {
						continue;
					}

					if ( ! isset( $stylesheet_styles[ $id ] ) ) {
						$stylesheet_styles[ $id ] = array();
					}

					foreach ( $rule['declarations'] as $property => $value ) {
						$stylesheet_styles[ $id ][ $property ] = $value;
					}
				}
			}
		}

		foreach ( $elements as $id => $element ) {
			$final_style = $original_styles[ $id ];

			foreach ( $stylesheet_styles[ $id ] ?? array() as $property => $value ) {
				if ( ! array_key_exists( $property, $original_styles[ $id ] ) ) {
					$final_style[ $property ] = $value;
				}
			}

			$style = $this->stringify_declarations( $final_style );
			if ( '' === $style ) {
				$element->removeAttribute( 'style' );
			} else {
				$element->setAttribute( 'style', $style );
			}

			$element->removeAttribute( 'data-newspack-listmonk-css-id' );
		}
	}

	/**
	 * Parse top-level supported CSS rules.
	 *
	 * @param string $css Stylesheet contents.
	 * @return array<int,array{selectors:array<int,string>,declarations:array<string,string>}>
	 */
	private function parse_css_rules( $css ) {
		$css   = preg_replace( '!/\*.*?\*/!s', '', (string) $css );
		$css   = preg_replace( '/@[^{};]+;/', '', (string) $css );
		$rules = array();
		$pos   = 0;

		while ( false !== ( $open = strpos( $css, '{', $pos ) ) ) {
			$selector = trim( substr( $css, $pos, $open - $pos ) );
			$close    = $this->find_matching_brace( $css, $open );

			if ( false === $close ) {
				break;
			}

			if ( '' !== $selector && '@' !== $selector[0] ) {
				$selectors    = array_map( 'trim', explode( ',', $selector ) );
				$simple       = array_filter( $selectors, array( $this, 'is_simple_selector' ) );
				$declarations = $this->parse_declarations( substr( $css, $open + 1, $close - $open - 1 ) );

				if ( ! empty( $selectors ) && count( $selectors ) === count( $simple ) && ! empty( $declarations ) ) {
					$rules[] = array(
						'selectors'    => array_values( $selectors ),
						'declarations' => $declarations,
					);
				}
			}

			$pos = $close + 1;
		}

		return $rules;
	}

	/**
	 * Find the matching closing brace for a CSS block.
	 *
	 * @param string $css CSS string.
	 * @param int    $open Opening brace offset.
	 * @return int|false
	 */
	private function find_matching_brace( $css, $open ) {
		$depth  = 0;
		$length = strlen( $css );

		for ( $i = $open; $i < $length; $i++ ) {
			if ( '{' === $css[ $i ] ) {
				$depth++;
			} elseif ( '}' === $css[ $i ] ) {
				$depth--;
				if ( 0 === $depth ) {
					return $i;
				}
			}
		}

		return false;
	}

	/**
	 * Parse CSS declarations into a property map.
	 *
	 * @param string $css CSS declarations.
	 * @return array<string,string>
	 */
	private function parse_declarations( $css ) {
		$declarations = array();

		foreach ( explode( ';', (string) $css ) as $declaration ) {
			if ( false === strpos( $declaration, ':' ) ) {
				continue;
			}

			list( $property, $value ) = array_map( 'trim', explode( ':', $declaration, 2 ) );
			$property                = strtolower( $property );

			if ( ! preg_match( '/^[a-z][a-z0-9-]*$/', $property ) || '' === $value ) {
				continue;
			}

			$declarations[ $property ] = $value;
		}

		return $declarations;
	}

	/**
	 * Convert CSS declarations to a sanitized inline style attribute.
	 *
	 * @param array<string,string> $declarations Declarations.
	 * @return string
	 */
	private function stringify_declarations( array $declarations ) {
		$style = array();

		foreach ( $declarations as $property => $value ) {
			$style[] = $property . ': ' . $value;
		}

		$style = implode( '; ', $style );
		if ( '' !== $style ) {
			$style .= ';';
		}

		return function_exists( 'safecss_filter_attr' ) ? trim( safecss_filter_attr( $style ) ) : trim( $style );
	}

	/**
	 * Check whether a selector is intentionally supported by the MVP inliner.
	 *
	 * @param string $selector CSS selector.
	 * @return bool
	 */
	private function is_simple_selector( $selector ) {
		return (bool) preg_match( '/^(?:[a-z][a-z0-9_-]*|#[a-z][a-z0-9_-]*|\.[a-z0-9_-]+|[a-z][a-z0-9_-]*\.[a-z0-9_-]+)$/i', trim( (string) $selector ) );
	}

	/**
	 * Convert a supported selector to XPath.
	 *
	 * @param string $selector CSS selector.
	 * @return string
	 */
	private function selector_to_xpath( $selector ) {
		$selector = trim( (string) $selector );

		if ( preg_match( '/^[a-z][a-z0-9_-]*$/i', $selector ) ) {
			return '//' . strtolower( $selector );
		}

		if ( preg_match( '/^#([a-z][a-z0-9_-]*)$/i', $selector, $matches ) ) {
			return '//*[@id=' . $this->xpath_literal( $matches[1] ) . ']';
		}

		if ( preg_match( '/^\.([a-z0-9_-]+)$/i', $selector, $matches ) ) {
			return '//*[contains(concat(" ", normalize-space(@class), " "), ' . $this->xpath_literal( ' ' . $matches[1] . ' ' ) . ')]';
		}

		if ( preg_match( '/^([a-z][a-z0-9_-]*)\.([a-z0-9_-]+)$/i', $selector, $matches ) ) {
			return '//' . strtolower( $matches[1] ) . '[contains(concat(" ", normalize-space(@class), " "), ' . $this->xpath_literal( ' ' . $matches[2] . ' ' ) . ')]';
		}

		return '';
	}

	/**
	 * Quote an XPath string literal.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private function xpath_literal( $value ) {
		if ( false === strpos( $value, "'" ) ) {
			return "'" . $value . "'";
		}

		if ( false === strpos( $value, '"' ) ) {
			return '"' . $value . '"';
		}

		$parts = array_map(
			static function ( $part ) {
				return "'" . $part . "'";
			},
			explode( "'", $value )
		);

		return 'concat(' . implode( ', "\'", ', $parts ) . ')';
	}

	/**
	 * Remove unsafe nodes and attributes from a parsed document.
	 *
	 * @param DOMDocument $dom Document.
	 */
	private function cleanup_dom( DOMDocument $dom ) {
		foreach ( array( 'script', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'select', 'textarea', 'option' ) as $tag ) {
			foreach ( iterator_to_array( $dom->getElementsByTagName( $tag ) ) as $node ) {
				if ( $node->parentNode ) {
					$node->parentNode->removeChild( $node );
				}
			}
		}

		foreach ( iterator_to_array( $dom->getElementsByTagName( '*' ) ) as $element ) {
			if ( ! $element instanceof DOMElement ) {
				continue;
			}

			$attributes = array();
			foreach ( $element->attributes as $attribute ) {
				$attributes[] = $attribute->name;
			}

			foreach ( $attributes as $attribute ) {
				$name  = strtolower( $attribute );
				$value = $element->getAttribute( $attribute );

				if ( 0 === strpos( $name, 'on' ) || 'srcdoc' === $name ) {
					$element->removeAttribute( $attribute );
					continue;
				}

				if ( in_array( $name, array( 'href', 'src', 'poster', 'background' ), true ) ) {
					if ( $this->is_unsafe_url( $value ) ) {
						$element->removeAttribute( $attribute );
					} else {
						$element->setAttribute( $attribute, $this->absolutize_url( $value ) );
					}
					continue;
				}

				if ( 'srcset' === $name ) {
					$srcset = $this->cleanup_srcset( $value );
					if ( '' === $srcset ) {
						$element->removeAttribute( $attribute );
					} else {
						$element->setAttribute( $attribute, $srcset );
					}
				}
			}
		}
	}

	/**
	 * Minimal cleanup path used when DOMDocument is unavailable.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	private function cleanup_without_dom( $html ) {
		$html = preg_replace( '#<\s*(script|iframe|object|embed|form|select|textarea|button)\b[^>]*>.*?<\s*/\s*\1\s*>#is', '', $html );
		$html = preg_replace( '#<\s*(input|option)\b[^>]*>#is', '', (string) $html );
		$html = preg_replace( '/\s(?:on[a-z0-9_-]+|srcdoc)\s*=\s*(["\']).*?\1/is', '', (string) $html );
		$html = preg_replace( '/\s(?:on[a-z0-9_-]+|srcdoc)\s*=\s*[^\s>]+/is', '', (string) $html );

		$html = preg_replace_callback(
			'/\s(href|src|poster|background)=(["\'])(.*?)\2/is',
			function ( $matches ) {
				if ( $this->is_unsafe_url( $matches[3] ) ) {
					return '';
				}

				return sprintf( ' %s=%s%s%s', $matches[1], $matches[2], esc_attr( $this->absolutize_url( $matches[3] ) ), $matches[2] );
			},
			(string) $html
		);

		$html = preg_replace_callback(
			'/\ssrcset=(["\'])(.*?)\1/is',
			function ( $matches ) {
				$srcset = $this->cleanup_srcset( $matches[2] );

				return '' === $srcset ? '' : sprintf( ' srcset=%s%s%s', $matches[1], esc_attr( $srcset ), $matches[1] );
			},
			(string) $html
		);

		return $this->restore_listmonk_template_placeholders( $html );
	}

	/**
	 * Restore Listmonk template placeholders that DOMDocument may encode in URL
	 * attributes.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	private function restore_listmonk_template_placeholders( $html ) {
		$html = preg_replace( '/%7B%7B(?:%20)*UnsubscribeURL(?:%20)*%7D%7D/i', '{{ UnsubscribeURL }}', (string) $html );

		return preg_replace( '/\{\{(?:%20)*UnsubscribeURL(?:%20)*\}\}/i', '{{ UnsubscribeURL }}', (string) $html );
	}

	/**
	 * Clean and absolutize a srcset value.
	 *
	 * @param string $srcset Srcset.
	 * @return string
	 */
	private function cleanup_srcset( $srcset ) {
		$candidates = array();

		foreach ( explode( ',', (string) $srcset ) as $candidate ) {
			$candidate = trim( $candidate );
			if ( '' === $candidate || ! preg_match( '/^(\S+)(.*)$/', $candidate, $matches ) ) {
				continue;
			}

			if ( $this->is_unsafe_url( $matches[1] ) ) {
				continue;
			}

			$candidates[] = $this->absolutize_url( $matches[1] ) . $matches[2];
		}

		return implode( ', ', $candidates );
	}

	/**
	 * Convert root-relative URLs to absolute site URLs.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private function absolutize_url( $url ) {
		$url = trim( (string) $url );

		if ( preg_match( '#^/(?!/)#', $url ) ) {
			return esc_url_raw( home_url( '/' ) . ltrim( $url, '/' ) );
		}

		return $url;
	}

	/**
	 * Detect javascript: style unsafe URLs.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	private function is_unsafe_url( $url ) {
		$decoded = html_entity_decode( (string) $url, ENT_QUOTES, get_bloginfo( 'charset' ) );
		$decoded = preg_replace( '/[\x00-\x20]+/', '', $decoded );

		return (bool) preg_match( '/^javascript:/i', (string) $decoded );
	}
}
