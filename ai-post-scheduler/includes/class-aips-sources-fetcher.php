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
 * or HTML), parses it with the appropriate strategy, and upserts the
 * extracted plain-text result into AIPS_Sources_Data_Repository.
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
		$content_format = $this->detect_content_format( $content_type, $body );

		switch ( $content_format ) {
			case 'feed':
				$parsed = $this->parse_feed( $body );
				break;
			case 'json':
				$parsed = $this->parse_json( $body );
				break;
			default: // 'html'
				$parsed = array(
					'page_title'       => $this->extract_page_title( $body ),
					'meta_description' => $this->extract_meta_description( $body ),
					'extracted_text'   => $this->extract_text( $body ),
				);
				break;
		}

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

		$upsert_ok = $this->data_repo->upsert( $source_id, array(
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

		if ( ! $upsert_ok ) {
			$error_msg = 'Failed to store fetched content in database.';
			$this->data_repo->mark_fetch_failed( $source_id, $error_msg, $http_status );
			$this->sources_repo->update_after_fetch( $source_id, false );

			$this->logger->log( sprintf( 'AIPS_Sources_Fetcher: upsert failed for source #%d', $source_id ), 'warning' );
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
	 * Strip remaining tags and decode HTML entities.
	 *
	 * Normalize whitespace: collapse runs of spaces/tabs and then collapse
	 * runs of blank lines to a single blank line.
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

	// ------------------------------------------------------------------
	// Content-type detection
	// ------------------------------------------------------------------

	/**
	 * Determine whether the response body is an RSS/Atom feed, a JSON document,
	 * or plain HTML.
	 *
	 * Detection priority:
	 *  1. Content-Type header (rss, atom, xml → 'feed'; json → 'json').
	 *  2. Body sniffing for common leading tokens when the header is absent or
	 *     is a generic type such as text/plain.
	 *
	 * @param string $content_type Lowercased Content-Type header value.
	 * @param string $body         Raw response body.
	 * @return string One of 'feed', 'json', or 'html'.
	 */
	private function detect_content_format( $content_type, $body ) {
		if (
			strpos( $content_type, 'rss' )  !== false ||
			strpos( $content_type, 'atom' ) !== false ||
			strpos( $content_type, 'xml' )  !== false
		) {
			return 'feed';
		}

		if ( strpos( $content_type, 'json' ) !== false ) {
			return 'json';
		}

		// Fall back to body-sniffing when the header is missing or generic.
		$trimmed = ltrim( $body );

		if ( '' !== $trimmed ) {
			if (
				strncmp( $trimmed, '<?xml', 5 ) === 0 ||
				strncmp( $trimmed, '<rss',  4 ) === 0 ||
				strncmp( $trimmed, '<feed', 5 ) === 0
			) {
				return 'feed';
			}

			if ( '{' === $trimmed[0] || '[' === $trimmed[0] ) {
				return 'json';
			}
		}

		return 'html';
	}

	// ------------------------------------------------------------------
	// RSS / Atom parsers
	// ------------------------------------------------------------------

	/**
	 * Parse an RSS 2.0 or Atom feed body into a parsed data array.
	 *
	 * Dispatches to parse_rss2_channel() for RSS 2.0 or parse_atom_feed()
	 * for Atom, based on the root element name.
	 *
	 * @param string $body Raw XML feed body.
	 * @return array { string page_title, string meta_description, string extracted_text }
	 */
	private function parse_feed( $body ) {
		libxml_use_internal_errors( true );
		$xml = @simplexml_load_string( $body, 'SimpleXMLElement', LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( false );

		if ( false === $xml ) {
			return array( 'page_title' => '', 'meta_description' => '', 'extracted_text' => '' );
		}

		$root = $xml->getName();

		if ( 'rss' === $root || isset( $xml->channel ) ) {
			return $this->parse_rss2_channel( $xml );
		}

		if ( 'feed' === $root ) {
			return $this->parse_atom_feed( $xml );
		}

		return array( 'page_title' => '', 'meta_description' => '', 'extracted_text' => '' );
	}

	/**
	 * Parse an RSS 2.0 <channel> into page_title, meta_description and
	 * extracted_text (up to 5 item title + description pairs).
	 *
	 * Prefers <content:encoded> over <description> for richer item content.
	 *
	 * @param SimpleXMLElement $xml Root RSS <rss> element.
	 * @return array { string page_title, string meta_description, string extracted_text }
	 */
	private function parse_rss2_channel( SimpleXMLElement $xml ) {
		$channel = $xml->channel;
		if ( ! $channel ) {
			return array( 'page_title' => '', 'meta_description' => '', 'extracted_text' => '' );
		}

		$page_title       = $this->clean_xml_text( (string) $channel->title );
		$meta_description = $this->clean_xml_text( (string) $channel->description );
		$namespaces       = $channel->getNamespaces( true );
		$items            = array();
		$count            = 0;

		foreach ( $channel->item as $item ) {
			if ( $count >= 5 ) {
				break;
			}

			$title = $this->clean_xml_text( (string) $item->title );
			$text  = '';

			// Prefer content:encoded (full post HTML) over description (usually a summary).
			if ( isset( $namespaces['content'] ) ) {
				$content_children = $item->children( $namespaces['content'] );
				if ( isset( $content_children->encoded ) ) {
					$text = $this->extract_text(
						html_entity_decode( (string) $content_children->encoded, ENT_QUOTES, 'UTF-8' )
					);
				}
			}

			if ( empty( $text ) ) {
				$desc = (string) $item->description;
				if ( '' !== $desc ) {
					$text = $this->extract_text( html_entity_decode( $desc, ENT_QUOTES, 'UTF-8' ) );
				}
			}

			if ( '' !== $title || '' !== $text ) {
				$items[] = trim( $title . ( '' !== $text ? "\n" . $text : '' ) );
			}

			$count++;
		}

		return array(
			'page_title'       => $page_title,
			'meta_description' => $meta_description,
			'extracted_text'   => implode( "\n\n---\n\n", $items ),
		);
	}

	/**
	 * Parse an Atom <feed> into page_title, meta_description and
	 * extracted_text (up to 5 entry title + content pairs).
	 *
	 * Handles feeds where elements are in the default Atom namespace
	 * (xmlns="http://www.w3.org/2005/Atom") as well as those without a
	 * namespace declaration on the root element.
	 *
	 * @param SimpleXMLElement $xml Root Atom <feed> element.
	 * @return array { string page_title, string meta_description, string extracted_text }
	 */
	private function parse_atom_feed( SimpleXMLElement $xml ) {
		$atom_ns     = 'http://www.w3.org/2005/Atom';
		$ns_children = $xml->children( $atom_ns );

		// Feed title / subtitle — fall back to direct property if namespace returns empty.
		$ns_title    = isset( $ns_children->title )    ? (string) $ns_children->title    : '';
		$ns_subtitle = isset( $ns_children->subtitle ) ? (string) $ns_children->subtitle : '';

		$page_title       = $this->clean_xml_text( '' !== $ns_title    ? $ns_title    : (string) $xml->title );
		$meta_description = $this->clean_xml_text( '' !== $ns_subtitle ? $ns_subtitle : (string) $xml->subtitle );

		// Entries — use namespace-qualified set when it contains entries.
		$entries = ( isset( $ns_children->entry ) && count( $ns_children->entry ) > 0 )
			? $ns_children->entry
			: $xml->entry;

		$items = array();
		$count = 0;

		foreach ( $entries as $entry ) {
			if ( $count >= 5 ) {
				break;
			}

			$entry_ns = $entry->children( $atom_ns );

			$raw_title   = isset( $entry_ns->title )   && '' !== (string) $entry_ns->title   ? (string) $entry_ns->title   : (string) $entry->title;
			$raw_content = isset( $entry_ns->content ) && '' !== (string) $entry_ns->content ? (string) $entry_ns->content : (string) $entry->content;
			$raw_summary = isset( $entry_ns->summary ) && '' !== (string) $entry_ns->summary ? (string) $entry_ns->summary : (string) $entry->summary;

			$title    = $this->clean_xml_text( $raw_title );
			$text_src = '' !== $raw_content ? $raw_content : $raw_summary;
			$text     = '' !== $text_src
				? $this->extract_text( html_entity_decode( $text_src, ENT_QUOTES, 'UTF-8' ) )
				: '';

			if ( '' !== $title || '' !== $text ) {
				$items[] = trim( $title . ( '' !== $text ? "\n" . $text : '' ) );
			}

			$count++;
		}

		return array(
			'page_title'       => $page_title,
			'meta_description' => $meta_description,
			'extracted_text'   => implode( "\n\n---\n\n", $items ),
		);
	}

	// ------------------------------------------------------------------
	// JSON parsers
	// ------------------------------------------------------------------

	/**
	 * Parse a JSON response body.
	 *
	 * Handles the following formats (in priority order):
	 *  1. WordPress REST API — single post  ({title:{rendered}, content:{rendered}, …})
	 *  2. WordPress REST API — post array   ([{title:{rendered}, …}, …])
	 *  3. JSON Feed spec     — {items:[{title, content_text, content_html, summary}]}
	 *  4. Generic JSON       — flattens all scalar string values to text
	 *
	 * @param string $body Raw JSON response body.
	 * @return array { string page_title, string meta_description, string extracted_text }
	 */
	private function parse_json( $body ) {
		$data = json_decode( $body, true );
		if ( null === $data || ! is_array( $data ) ) {
			return array( 'page_title' => '', 'meta_description' => '', 'extracted_text' => '' );
		}

		// ── WordPress REST API: single post ────────────────────────────
		if ( isset( $data['title']['rendered'] ) ) {
			$page_title       = $this->clean_html_text( $data['title']['rendered'] );
			$meta_description = isset( $data['excerpt']['rendered'] )
				? $this->clean_html_text( $data['excerpt']['rendered'] )
				: '';
			$content          = isset( $data['content']['rendered'] )
				? $this->extract_text( $data['content']['rendered'] )
				: '';

			return array(
				'page_title'       => $page_title,
				'meta_description' => $meta_description,
				'extracted_text'   => trim( ( '' !== $page_title ? $page_title . "\n" : '' ) . $content ),
			);
		}

		// ── WordPress REST API: array of posts ─────────────────────────
		if ( isset( $data[0] ) && is_array( $data[0] ) && isset( $data[0]['title']['rendered'] ) ) {
			$parts = array();
			foreach ( array_slice( $data, 0, 5 ) as $post ) {
				$title   = $this->clean_html_text( $post['title']['rendered'] );
				$excerpt = isset( $post['excerpt']['rendered'] )
					? $this->clean_html_text( $post['excerpt']['rendered'] )
					: '';
				$parts[] = trim( $title . ( '' !== $excerpt ? "\n" . $excerpt : '' ) );
			}
			$parts = array_values( array_filter( $parts ) );

			return array(
				'page_title'       => ! empty( $parts ) ? strtok( $parts[0], "\n" ) : '',
				'meta_description' => '',
				'extracted_text'   => implode( "\n\n---\n\n", $parts ),
			);
		}

		// ── JSON Feed spec ─────────────────────────────────────────────
		if ( isset( $data['items'] ) && is_array( $data['items'] ) ) {
			$page_title       = isset( $data['title'] )       ? (string) $data['title']       : '';
			$meta_description = isset( $data['description'] ) ? (string) $data['description'] : '';
			$parts            = array();

			foreach ( array_slice( $data['items'], 0, 5 ) as $item ) {
				$title = isset( $item['title'] ) ? (string) $item['title'] : '';

				if ( isset( $item['content_text'] ) && '' !== $item['content_text'] ) {
					$text = (string) $item['content_text'];
				} elseif ( isset( $item['content_html'] ) && '' !== $item['content_html'] ) {
					$text = $this->extract_text( (string) $item['content_html'] );
				} elseif ( isset( $item['summary'] ) ) {
					$text = (string) $item['summary'];
				} else {
					$text = '';
				}

				if ( '' !== $title || '' !== $text ) {
					$parts[] = trim( $title . ( '' !== $text ? "\n" . $text : '' ) );
				}
			}
			$parts = array_values( array_filter( $parts ) );

			return array(
				'page_title'       => $page_title,
				'meta_description' => $meta_description,
				'extracted_text'   => implode( "\n\n---\n\n", $parts ),
			);
		}

		// ── Generic JSON fallback ──────────────────────────────────────
		return array(
			'page_title'       => '',
			'meta_description' => '',
			'extracted_text'   => $this->flatten_json_to_text( $data ),
		);
	}

	// ------------------------------------------------------------------
	// Shared text helpers
	// ------------------------------------------------------------------

	/**
	 * Strip HTML tags and decode entities from an HTML string.
	 *
	 * @param string $html HTML string (e.g. from WP REST API rendered fields).
	 * @return string Plain text.
	 */
	private function clean_html_text( $html ) {
		return trim( html_entity_decode( strip_tags( $html ), ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * Strip HTML tags, decode entities, and trim a SimpleXML cast string.
	 *
	 * @param string $text Raw text value cast from a SimpleXMLElement.
	 * @return string Clean plain text.
	 */
	private function clean_xml_text( $text ) {
		return trim( html_entity_decode( strip_tags( $text ), ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * Recursively flatten a decoded JSON array to a plain-text string.
	 *
	 * Only traverses up to three levels deep and includes scalar string values.
	 *
	 * @param array $data  Decoded JSON data (associative or indexed).
	 * @param int   $depth Current recursion depth (0-indexed).
	 * @return string Flattened text.
	 */
	private function flatten_json_to_text( array $data, $depth = 0 ) {
		if ( $depth > 3 ) {
			return '';
		}

		$parts = array();
		foreach ( $data as $key => $value ) {
			if ( is_string( $value ) && '' !== $value ) {
				$parts[] = is_string( $key ) ? $key . ': ' . $value : $value;
			} elseif ( is_array( $value ) ) {
				$sub = $this->flatten_json_to_text( $value, $depth + 1 );
				if ( '' !== $sub ) {
					$parts[] = $sub;
				}
			}
		}

		return implode( "\n", $parts );
	}
}
