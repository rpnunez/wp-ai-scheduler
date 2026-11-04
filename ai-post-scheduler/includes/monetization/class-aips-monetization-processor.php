<?php
/**
 * Monetization Post-Processor & Ad Layout Engine
 *
 * Transforms raw AI-generated content into optimized commercial content:
 * - Compiles AI monetization tags into dynamic shortcodes
 * - Enforces RPM-optimized short paragraph formatting
 * - Inserts ad slots at configured intervals
 * - Injects Table of Contents and FTC affiliate disclosures
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Monetization_Processor {

	/**
	 * @var AIPS_Config
	 */
	private $config;

	/**
	 * Constructor.
	 *
	 * @param AIPS_Config $config
	 */
	public function __construct( $config = null ) {
		$this->config = $config ?: AIPS_Config::get_instance();
	}

	/**
	 * Process generated post content.
	 *
	 * @param string $content
	 * @param array  $context Additional generation context (e.g. template settings, post_type).
	 * @return string
	 */
	public function process_content( $content, $context = array() ) {
		if ( empty( $content ) ) {
			return $content;
		}

		// 1. Transform raw AI marker tags into valid AIPS shortcodes
		$content = $this->transform_ai_markers( $content );

		// 2. Format paragraphs for ad RPM readability if enabled
		$content = $this->format_paragraphs_for_rpm( $content );

		// 3. Inject Ad Slots if configured
		$content = $this->inject_ad_slots( $content );

		// 4. Prepend FTC Disclosure if affiliate links or product cards are present
		$content = $this->maybe_prepend_ftc_disclosure( $content );

		// 5. Prepend Table of Contents if enabled or requested in context
		if ( ! empty( $context['enable_toc'] ) || false !== strpos( $content, '[aips_toc]' ) ) {
			if ( false === strpos( $content, '[aips_toc]' ) ) {
				$content = "[aips_toc]\n\n" . $content;
			}
		}

		return trim( $content );
	}

	/**
	 * Transform AI text markers into native shortcodes.
	 *
	 * Supports formats:
	 * <!-- aips:component key="val" -->
	 * [COMPONENT key="val"]
	 *
	 * @param string $content
	 * @return string
	 */
	public function transform_ai_markers( $content ) {
		// Convert comment markers: <!-- aips:product_card id="12" badge="Top Pick" -->
		$content = preg_replace_callback(
			'/<!--\s*aips:([a-z_]+)\s+([^>]+)-->/i',
			function( $matches ) {
				$tag   = strtolower( $matches[1] );
				$attrs = trim( $matches[2] );
				return "[aips_{$tag} {$attrs}]";
			},
			$content
		);

		// Normalize case for bracket markers like [COMPARISON_TABLE ids="..."]
		$tags = array( 'product_card', 'comparison_table', 'pros_cons', 'faq', 'toc', 'cross_sells', 'ftc_disclosure' );
		foreach ( $tags as $tag ) {
			$content = preg_replace(
				'/\[' . strtoupper( $tag ) . '\s+/i',
				"[aips_{$tag} ",
				$content
			);
		}

		return $content;
	}

	/**
	 * Reformat large walls of text into bite-sized 2-3 sentence paragraphs for higher ad RPM.
	 *
	 * @param string $content
	 * @return string
	 */
	public function format_paragraphs_for_rpm( $content ) {
		$paragraphs = explode( "\n\n", str_replace( "\r\n", "\n", $content ) );
		$processed  = array();

		foreach ( $paragraphs as $paragraph ) {
			$trimmed = trim( $paragraph );
			if ( empty( $trimmed ) ) {
				continue;
			}

			// Don't alter shortcodes, HTML headings, tables, or list items
			if (
				str_starts_with( $trimmed, '[' ) ||
				str_starts_with( $trimmed, '<' ) ||
				str_starts_with( $trimmed, '#' ) ||
				str_starts_with( $trimmed, '-' ) ||
				str_starts_with( $trimmed, '*' )
			) {
				$processed[] = $trimmed;
				continue;
			}

			// Split paragraphs that have more than 3 sentences
			$sentences = preg_split( '/(?<=[.?!])\s+(?=[A-Z0-9"\'“‘])/u', $trimmed );
			if ( count( $sentences ) > 3 ) {
				$chunks = array_chunk( $sentences, 3 );
				foreach ( $chunks as $chunk ) {
					$processed[] = implode( ' ', $chunk );
				}
			} else {
				$processed[] = $trimmed;
			}
		}

		return implode( "\n\n", $processed );
	}

	/**
	 * Injects ad slot placeholders or ad codes at designated paragraph intervals.
	 *
	 * @param string $content
	 * @return string
	 */
	public function inject_ad_slots( $content ) {
		$ad_enabled = (bool) $this->config->get_option( 'aips_ad_injection_enabled', false );
		$ad_code    = (string) $this->config->get_option( 'aips_ad_slot_code', '' );
		$interval   = max( 2, (int) $this->config->get_option( 'aips_ad_paragraph_interval', 3 ) );

		if ( ! $ad_enabled || empty( $ad_code ) ) {
			return $content;
		}

		$ad_markup  = "\n\n<div class=\"aips-ad-slot-wrapper\">" . $ad_code . "</div>\n\n";
		$paragraphs = explode( "\n\n", $content );
		$output     = array();
		$p_count    = 0;

		foreach ( $paragraphs as $para ) {
			$output[] = $para;
			$trimmed  = trim( $para );

			// Count standard text paragraphs (skip shortcodes and headings)
			if ( ! empty( $trimmed ) && ! str_starts_with( $trimmed, '<h' ) && ! str_starts_with( $trimmed, '[' ) ) {
				$p_count++;
				if ( 0 === ( $p_count % $interval ) ) {
					$output[] = trim( $ad_markup );
				}
			}
		}

		return implode( "\n\n", $output );
	}

	/**
	 * Prepend FTC affiliate disclosure if commercial elements are present.
	 *
	 * @param string $content
	 * @return string
	 */
	public function maybe_prepend_ftc_disclosure( $content ) {
		$disclosure_enabled = (bool) $this->config->get_option( 'aips_ftc_disclosure_enabled', true );
		if ( ! $disclosure_enabled ) {
			return $content;
		}

		// Check if already present
		if ( false !== strpos( $content, '[aips_ftc_disclosure]' ) ) {
			return $content;
		}

		// Detect commercial shortcodes
		$commercial_markers = array(
			'[aips_product_card',
			'[aips_comparison_table',
			'[aips_cross_sells',
			'data-action-type="affiliate"',
		);

		$has_commercial = false;
		foreach ( $commercial_markers as $marker ) {
			if ( false !== strpos( $content, $marker ) ) {
				$has_commercial = true;
				break;
			}
		}

		if ( $has_commercial ) {
			$content = "[aips_ftc_disclosure]\n\n" . $content;
		}

		return $content;
	}
}
