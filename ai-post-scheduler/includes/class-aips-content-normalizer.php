<?php
/**
 * Content Normalizer
 *
 * @package AIPS
 */

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

class AIPS_Content_Normalizer {
	private $markdown_parser;

	public function __construct( $markdown_parser ) {
		$this->markdown_parser = $markdown_parser;
	}

	/**
	 * Normalize generated content so post bodies are consistently stored as HTML.
	 *
	 * @param string $content Raw generated content.
	 * @return string Sanitized HTML content.
	 */
	public function normalize_generated_content_for_wordpress( $content ) {
		if ( ! is_string( $content ) ) {
			return '';
		}

		$normalized_content = trim( $content );

		if ( $normalized_content === '' ) {
			return '';
		}

		if ( $this->markdown_parser && $this->markdown_parser->is_markdown( $normalized_content ) && ! $this->markdown_parser->contains_html( $normalized_content ) ) {
			$normalized_content = $this->markdown_parser->parse( $normalized_content );
		}

		return wp_kses_post( $normalized_content );
	}
}