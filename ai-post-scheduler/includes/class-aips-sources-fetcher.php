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
 * Fetches a source URL, auto-detects its content type (RSS/Atom feed, JSON,
 * or HTML), parses it with the appropriate strategy, and stores the
 * extracted plain-text result into AIPS_Sources_Data_Repository as a new
 * archived snapshot (deduplicated by content hash).
 *
 * Supported content types:
 *  - RSS 2.0 / Atom feeds  (application/rss+xml, application/atom+xml, text/xml, application/xml)
 *  - JSON feeds            (application/json, WP REST API, JSON Feed spec)
 *  - HTML pages            (text/html, default fallback)
 *
 * Detection uses the Content-Type response header and falls back to
 * body-sniffing when the header is absent or generic.
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
	 * @var AIPS_Source_Parser
	 */
	private $parser;

	/**
	 * @param AIPS_Sources_Data_Repository|null $data_repo    Optional (injectable for tests).
	 * @param AIPS_Sources_Repository|null       $sources_repo Optional (injectable for tests).
	 * @param AIPS_Logger|null                   $logger       Optional (injectable for tests).
	 * @param AIPS_Source_Parser|null            $parser       Optional (injectable for tests).
	 */
	public function __construct( $data_repo = null, $sources_repo = null, $logger = null, $parser = null ) {
		$this->data_repo    = $data_repo    ?: new AIPS_Sources_Data_Repository();
		$this->sources_repo = $sources_repo ?: new AIPS_Sources_Repository();
		$this->logger       = $logger       ?: new AIPS_Logger();
		$this->parser       = $parser       ?: new AIPS_Source_Parser();
	}

	/**
	 * Fetch and store content for a source row.
	 *
	 * @param object $source Source row from aips_sources (must have id, url properties).
	 * @return array {
	 *     Result summary.
	 *
	 *     @type bool   $success    True if fetch and storage succeeded.
	 *     @type int    $char_count Number of characters stored.
	 *     @type string $error      Error message on failure (empty on success).
	 * }
	 */
	public function fetch( $source ) {
		$source_id = isset( $source->id ) ? absint( $source->id ) : 0;
		$url       = isset( $source->url ) ? esc_url_raw( $source->url ) : '';

		if ( ! $source_id || empty( $url ) ) {
			return array( 'success' => false, 'char_count' => 0, 'error' => 'Invalid source data.' );
		}

		if ( ! wp_http_validate_url( $url ) ) {
			$error_msg = 'Invalid or unsafe source URL.';
			$this->data_repo->mark_fetch_failed( $source_id, $error_msg, 0 );
			$this->sources_repo->update_after_fetch( $source_id, false );

			$this->logger->log( sprintf( 'AIPS_Sources_Fetcher: rejected unsafe URL for source #%d (%s)', $source_id, $url ), 'warning' );
			return array( 'success' => false, 'char_count' => 0, 'error' => $error_msg );
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
			return array( 'success' => false, 'char_count' => 0, 'error' => $error_msg );
		}

		$http_status = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( $http_status < 200 || $http_status >= 400 ) {
			$error_msg = sprintf( 'HTTP %d', $http_status );
			$this->data_repo->mark_fetch_failed( $source_id, $error_msg, $http_status );
			$this->sources_repo->update_after_fetch( $source_id, false );

			$this->logger->log( sprintf( 'AIPS_Sources_Fetcher: HTTP error for source #%d — %s', $source_id, $error_msg ), 'warning' );
			return array( 'success' => false, 'char_count' => 0, 'error' => $error_msg );
		}

		// Auto-detect content type and parse accordingly.
		$content_type   = strtolower( (string) wp_remote_retrieve_header( $response, 'content-type' ) );
		$parsed         = $this->parser->parse( $content_type, $body );

		$page_title       = $parsed['page_title'];
		$meta_description = $parsed['meta_description'];
		$extracted_text   = $parsed['extracted_text'];

		$max_chars = absint( get_option( 'aips_source_fetch_max_chars', self::DEFAULT_MAX_CHARS ) );
		if ( $max_chars < 500 ) {
			$max_chars = self::DEFAULT_MAX_CHARS;
		}
		if ( mb_strlen( $extracted_text ) > $max_chars ) {
			$extracted_text = mb_substr( $extracted_text, 0, $max_chars );
		}

		$store_raw_html = (bool) get_option( 'aips_source_store_raw_html', false );

		$char_count = mb_strlen( $extracted_text );
		$duration   = round( microtime( true ) - $start, 2 );

		$insert_ok = $this->data_repo->insert_if_new( $source_id, array(
			'url'              => $url,
			'page_title'       => $page_title,
			'meta_description' => $meta_description,
			'extracted_text'   => $extracted_text,
			'raw_html'         => $store_raw_html ? $body : '',
			'char_count'       => $char_count,
			'fetch_status'     => 'success',
			'http_status'      => $http_status,
			'error_message'    => '',
		) );

		if ( ! $insert_ok ) {
			$error_msg = 'Failed to store fetched content in database.';
			$this->data_repo->mark_fetch_failed( $source_id, $error_msg, $http_status );
			$this->sources_repo->update_after_fetch( $source_id, false );

			$this->logger->log( sprintf( 'AIPS_Sources_Fetcher: insert failed for source #%d', $source_id ), 'warning' );
			return array( 'success' => false, 'char_count' => 0, 'error' => $error_msg );
		}

		$this->sources_repo->update_after_fetch( $source_id, true );

		$this->logger->log(
			sprintf(
				'AIPS_Sources_Fetcher: fetched source #%d (%s) in %ss — %d chars extracted.',
				$source_id,
				$url,
				$duration,
				$char_count
			),
			'info'
		);

		return array( 'success' => true, 'char_count' => $char_count, 'error' => '' );
	}

}
