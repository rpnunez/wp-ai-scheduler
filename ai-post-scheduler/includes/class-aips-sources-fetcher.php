<?php
/**
 * Sources Fetcher Service
 *
 * Retrieves and parses a source URL via the WordPress HTTP API,
 * then persists the extracted text to aips_sources_data.
 *
 * @package AI_Post_Scheduler
 * @since 2.4.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Sources_Fetcher
 *
 * Fetches a source URL, strips noise HTML tags, extracts readable body text,
 * and upserts the result into AIPS_Sources_Data_Repository.
 */
class AIPS_Sources_Fetcher {

	/**
	 * Maximum characters of extracted_text stored per source.
	 * Configurable via the aips_source_fetch_max_chars option.
	 *
	 * @var int
	 */
	const DEFAULT_MAX_CHARS = 5000;

	/**
	 * Maximum characters of extracted_text included per source in an AI prompt.
	 * Configurable via the aips_source_snippet_max_chars option.
	 *
	 * @var int
	 */
	const DEFAULT_PROMPT_SNIPPET_CHARS = 800;

	/**
	 * @var AIPS_Sources_Data_Repository
	 */
	private $data_repo;

	/**
	 * @var AIPS_Sources_Repository
	 */
	private $sources_repo;

	/**
	 * @var AIPS_Logger
	 */
	private $logger;

	/**
	 * @param AIPS_Sources_Data_Repository|null $data_repo    Optional (injectable for tests).
	 * @param AIPS_Sources_Repository|null       $sources_repo Optional (injectable for tests).
	 * @param AIPS_Logger|null                   $logger       Optional (injectable for tests).
	 */
	public function __construct( $data_repo = null, $sources_repo = null, $logger = null ) {
		$this->data_repo    = $data_repo    ?: new AIPS_Sources_Data_Repository();
		$this->sources_repo = $sources_repo ?: new AIPS_Sources_Repository();
		$this->logger       = $logger       ?: new AIPS_Logger();
	}

	/**
	 * Fetch and store content for a source row.
	 *
	 * @param object $source Source row from aips_sources (must have id, url properties).
	 * @return array {
	 *     Result summary.
	 *
	 *     @type bool   $success    True if fetch and storage succeeded.
	 *     @type int    $word_count Number of characters stored.
	 *     @type string $error      Error message on failure (empty on success).
	 * }
	 */
	public function fetch( $source ) {
		$source_id = isset( $source->id ) ? absint( $source->id ) : 0;
		$url       = isset( $source->url ) ? esc_url_raw( $source->url ) : '';

		if ( ! $source_id || empty( $url ) ) {
			return array( 'success' => false, 'word_count' => 0, 'error' => 'Invalid source data.' );
		}

		if ( ! wp_http_validate_url( $url ) ) {
			$error_msg = 'Invalid or unsafe source URL.';
			$this->data_repo->mark_fetch_failed( $source_id, $error_msg, 0 );
			$this->sources_repo->update_after_fetch( $source_id, false );

			$this->logger->log( sprintf( 'AIPS_Sources_Fetcher: rejected unsafe URL for source #%d (%s)', $source_id, $url ), 'warning' );
			return array( 'success' => false, 'word_count' => 0, 'error' => $error_msg );
		}

		$start = microtime( true );
		$this->logger->log( sprintf( 'AIPS_Sources_Fetcher: starting fetch for source #%d (%s)', $source_id, $url ), 'info' );

		$response = wp_safe_remote_get( $url, array(
			'timeout'            => 15,
			'user-agent'         => 'Mozilla/5.0 (compatible; AIPS-Source-Fetcher/2.4; +https://wordpress.org)',
			'sslverify'          => true,
			'reject_unsafe_urls' => true,
		) );

		if ( is_wp_error( $response ) ) {
			$error_msg = $response->get_error_message();
			$this->data_repo->mark_fetch_failed( $source_id, $error_msg, 0 );
			$this->sources_repo->update_after_fetch( $source_id, false );

			$this->logger->log( sprintf( 'AIPS_Sources_Fetcher: fetch failed for source #%d — %s', $source_id, $error_msg ), 'warning' );
			return array( 'success' => false, 'word_count' => 0, 'error' => $error_msg );
		}

		$http_status = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( $http_status < 200 || $http_status >= 400 ) {
			$error_msg = sprintf( 'HTTP %d', $http_status );
			$this->data_repo->mark_fetch_failed( $source_id, $error_msg, $http_status );
			$this->sources_repo->update_after_fetch( $source_id, false );

			$this->logger->log( sprintf( 'AIPS_Sources_Fetcher: HTTP error for source #%d — %s', $source_id, $error_msg ), 'warning' );
			return array( 'success' => false, 'word_count' => 0, 'error' => $error_msg );
		}

		// Parse the HTML.
		$page_title       = $this->extract_page_title( $body );
		$meta_description = $this->extract_meta_description( $body );
		$extracted_text   = $this->extract_text( $body );

		$max_chars = absint( get_option( 'aips_source_fetch_max_chars', self::DEFAULT_MAX_CHARS ) );
		if ( $max_chars < 500 ) {
			$max_chars = self::DEFAULT_MAX_CHARS;
		}
		if ( mb_strlen( $extracted_text ) > $max_chars ) {
			$extracted_text = mb_substr( $extracted_text, 0, $max_chars );
		}

		$store_raw_html = (bool) get_option( 'aips_source_store_raw_html', false );

		$word_count = mb_strlen( $extracted_text );
		$duration   = round( microtime( true ) - $start, 2 );

		$this->data_repo->upsert( $source_id, array(
			'url'              => $url,
			'page_title'       => $page_title,
			'meta_description' => $meta_description,
			'extracted_text'   => $extracted_text,
			'raw_html'         => $store_raw_html ? $body : '',
			'word_count'       => $word_count,
			'fetch_status'     => 'success',
			'http_status'      => $http_status,
			'error_message'    => '',
		) );

		$this->sources_repo->update_after_fetch( $source_id, true );

		$this->logger->log(
			sprintf(
				'AIPS_Sources_Fetcher: fetched source #%d (%s) in %ss — %d chars extracted.',
				$source_id,
				$url,
				$duration,
				$word_count
			),
			'info'
		);

		return array( 'success' => true, 'word_count' => $word_count, 'error' => '' );
	}

	/**
	 * Extract the <title> text from raw HTML.
	 *
	 * @param string $html Raw HTML.
	 * @return string Extracted title, or empty string.
	 */
	private function extract_page_title( $html ) {
		if ( preg_match( '/<title[^>]*>(.*?)<\/title>/is', $html, $matches ) ) {
			return html_entity_decode( strip_tags( $matches[1] ), ENT_QUOTES, 'UTF-8' );
		}
		return '';
	}

	/**
	 * Extract the meta description from raw HTML.
	 *
	 * @param string $html Raw HTML.
	 * @return string Extracted description, or empty string.
	 */
	private function extract_meta_description( $html ) {
		if ( preg_match( '/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)["\'][^>]*>/i', $html, $matches ) ) {
			return html_entity_decode( $matches[1], ENT_QUOTES, 'UTF-8' );
		}
		if ( preg_match( '/<meta[^>]+content=["\']([^"\']*)["\'][^>]+name=["\']description["\'][^>]*>/i', $html, $matches ) ) {
			return html_entity_decode( $matches[1], ENT_QUOTES, 'UTF-8' );
		}
		return '';
	}

	/**
	 * Strip noise elements from HTML and extract readable body text.
	 *
	 * Removes <script>, <style>, <nav>, <footer>, <aside>, <header>, <form>,
	 * <noscript>, and <iframe> tags (and their contents) before running
	 * strip_tags() on the remaining markup.
	 *
	 * @param string $html Raw HTML.
	 * @return string Clean readable text.
	 */
	private function extract_text( $html ) {
		// Remove noise elements with their content.
		$noise_tags = array( 'script', 'style', 'nav', 'footer', 'aside', 'header', 'form', 'noscript', 'iframe' );
		foreach ( $noise_tags as $tag ) {
			$html = preg_replace( '/<' . $tag . '[^>]*>.*?<\/' . $tag . '>/is', ' ', $html );
		}

		// Extract body content when present, to skip <head> metadata noise.
		if ( preg_match( '/<body[^>]*>(.*?)<\/body>/is', $html, $matches ) ) {
			$html = $matches[1];
		}

		// Convert block-level tags to line breaks for readability.
		$html = preg_replace( '/<\/?(p|div|li|h[1-6]|br|tr|td|th|blockquote|pre)[^>]*>/i', "\n", $html );

		// Strip remaining tags and decode HTML entities.
		$text = strip_tags( $html );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );

		// Normalize whitespace: collapse runs of spaces/tabs and then collapse
		// runs of blank lines to a single blank line.
		$text = preg_replace( '/[ \t]+/', ' ', $text );
		$text = preg_replace( '/\n{3,}/', "\n\n", $text );

		return trim( $text );
	}
}
