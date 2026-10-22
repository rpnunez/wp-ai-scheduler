<?php
/**
 * Sources Data Repository
 *
 * Database abstraction layer for scraped source content.
 * Each fetch is stored as a new row (archive model), deduplicated by content hash.
 * A num_used counter allows round-robin selection when injecting content into prompts.
 *
 * @package AI_Post_Scheduler
 * @since 2.4.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Sources_Data_Repository
 *
 * Owns all SQL for the aips_sources_data table.
 * Each row represents one archived fetch snapshot for a source.
 * Rows are deduplicated by content_hash; num_used tracks prompt usage for round-robin selection.
 */
class AIPS_Sources_Data_Repository {

	/**
	 * @var self|null Singleton instance.
	 */
	private static $instance = null;

	/**
	 * Get the shared singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * @var string The sources_data table name (with prefix).
	 */
	private $table_name;

	/**
	 * @var wpdb WordPress database abstraction object.
	 */
	private $wpdb;

	/**
	 * Initialize the repository.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb       = $wpdb;
		$this->table_name = $wpdb->prefix . 'aips_sources_data';
	}

	/**
	 * Insert a new fetch record for a source, skipping if the content already exists.
	 *
	 * Computes a SHA-256 hash of the extracted_text and checks whether a row
	 * with the same source_id + content_hash already exists. If it does, the
	 * method returns true without writing anything (deduplication). Otherwise
	 * a new archive row is inserted with num_used = 0.
	 *
	 * @param int   $source_id Source ID (FK to aips_sources.id).
	 * @param array $data {
	 *     Fetch result data.
	 *
	 *     @type string $url              URL that was fetched.
	 *     @type string $page_title       Extracted <title> value.
	 *     @type string $meta_description Extracted meta description.
	 *     @type string $extracted_text   Cleaned readable body text.
	 *     @type string $raw_html         Original raw HTML (may be empty).
	 *     @type int    $char_count       Character count of extracted_text.
	 *     @type string $fetch_status     'success' or 'failed'.
	 *     @type int    $http_status      HTTP response code.
	 *     @type string $error_message    Error message on failure.
	 * }
	 * @return bool True if a new row was inserted or content already exists, false on DB error.
	 */
	public function insert_if_new( $source_id, array $data ) {
		$source_id = absint( $source_id );
		if ( $source_id <= 0 ) {
			return false;
		}

		$extracted_text = isset( $data['extracted_text'] ) ? self::normalize_text( $data['extracted_text'] ) : '';
		$snapshot_url   = isset( $data['url'] ) ? esc_url_raw( $data['url'] ) : '';
		$content_hash   = '' !== $extracted_text ? self::content_hash( $snapshot_url, $extracted_text ) : null;
		$char_count     = mb_strlen( $extracted_text, 'UTF-8' );
		$now            = AIPS_DateTime::now()->timestamp();

		// Skip if this exact content snapshot is already stored.
		if ( null !== $content_hash ) {
			$existing_id = $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT id FROM {$this->table_name} WHERE source_id = %d AND fetch_status = 'success' AND (content_hash = %s OR (BINARY url = BINARY %s AND BINARY extracted_text = BINARY %s)) LIMIT 1",
					$source_id, $content_hash, $snapshot_url, $extracted_text
				)
			);
			if ( $existing_id ) {
				return true;
			}
			if ( ! isset( $data['fetch_status'] ) || 'success' === $data['fetch_status'] ) {
				// An editor may have marked this exact snapshot pending/failed.
				// Restore usability after a real successful fetch without replacing
				// its provenance or requiring legacy text-only hashes to be rewritten.
				$promoted = $this->wpdb->query( $this->wpdb->prepare(
					"UPDATE {$this->table_name} SET fetch_status = 'success', error_message = '', http_status = %d, updated_at = %d
					 WHERE source_id = %d AND fetch_status IN ('pending', 'failed')
					 AND BINARY url = BINARY %s AND BINARY extracted_text = BINARY %s LIMIT 1",
					isset( $data['http_status'] ) ? absint( $data['http_status'] ) : 0,
					$now, $source_id, $snapshot_url, $extracted_text
				) );
				if ( false === $promoted ) {
					return false;
				}
				if ( $promoted > 0 ) {
					return true;
				}
			}
		}

		$row = array(
			'source_id'        => $source_id,
			'url'              => isset( $data['url'] ) ? esc_url_raw( $data['url'] ) : '',
			'page_title'       => isset( $data['page_title'] ) ? sanitize_text_field( $data['page_title'] ) : '',
			'meta_description' => isset( $data['meta_description'] ) ? sanitize_textarea_field( $data['meta_description'] ) : '',
			'extracted_text'   => $extracted_text,
			'raw_html'         => isset( $data['raw_html'] ) ? $data['raw_html'] : '',
			'char_count'       => $char_count,
			'content_hash'     => $content_hash,
			'num_used'         => 0,
			'fetch_status'     => isset( $data['fetch_status'] ) ? sanitize_text_field( $data['fetch_status'] ) : 'success',
			'http_status'      => isset( $data['http_status'] ) ? absint( $data['http_status'] ) : 0,
			'error_message'    => isset( $data['error_message'] ) ? sanitize_textarea_field( $data['error_message'] ) : '',
			'fetched_at'       => $now,
			'created_at'       => $now,
			'updated_at'       => $now,
		);

		$format = array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%d', '%s', '%d', '%d', '%d' );
		$result = $this->wpdb->insert( $this->table_name, $row, $format );
		if ( false !== $result ) {
			return true;
		}
		// A concurrent identical insert is success only after verifying the row.
		return null !== $content_hash && (bool) $this->wpdb->get_var( $this->wpdb->prepare(
			"SELECT id FROM {$this->table_name} WHERE source_id = %d AND fetch_status = 'success' AND content_hash = %s AND BINARY extracted_text = BINARY %s LIMIT 1",
			$source_id, $content_hash, $extracted_text
		) );
	}

	/**
	 * Retrieve one source data row by ID.
	 *
	 * @param int $id Source data row ID.
	 * @return object|null Row object or null if not found.
	 */
	public function get_by_id( $id ) {
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE id = %d",
				absint( $id )
			)
		);
	}

	/**
	 * Retrieve a paginated set of source data rows for one source.
	 *
	 * @param int    $source_id Source ID.
	 * @param string $search    Optional search term. Defaults to cheaper fields unless search_body_text is enabled.
	 * @param int    $per_page  Rows per page.
	 * @param int    $page      Current page.
	 * @param array  $filters   Optional structured filters.
	 * @return array{items:array,total:int,pages:int,current_page:int,per_page:int} Paginated results.
	 */
	public function get_paginated_by_source_id( $source_id, $search = '', $per_page = 20, $page = 1, array $filters = array() ) {
		$source_id = absint( $source_id );
		$per_page  = max( 1, min( 100, absint( $per_page ) ) );
		$page      = max( 1, absint( $page ) );
		$offset    = ( $page - 1 ) * $per_page;
		$search    = is_string( $search ) ? trim( $search ) : '';
		$filters   = $this->normalize_pagination_filters( $filters );

		if ( $source_id <= 0 ) {
			return array(
				'items'        => array(),
				'total'        => 0,
				'pages'        => 0,
				'current_page' => $page,
				'per_page'     => $per_page,
			);
		}

		$where = 'WHERE source_id = %d';
		$args  = array( $source_id );

		if ( '' !== $filters['fetch_status'] ) {
			$where .= ' AND fetch_status = %s';
			$args[] = $filters['fetch_status'];
		}

		if ( $filters['http_status_class'] > 0 ) {
			$where .= ' AND http_status >= %d AND http_status <= %d';
			$args[] = $filters['http_status_class'];
			$args[] = $filters['http_status_class'] + 99;
		}

		if ( $filters['fetched_after'] > 0 ) {
			$where .= ' AND fetched_at >= %d';
			$args[] = $filters['fetched_after'];
		}

		if ( $filters['fetched_before'] > 0 ) {
			$where .= ' AND fetched_at <= %d';
			$args[] = $filters['fetched_before'];
		}

		if ( $filters['min_char_count'] > 0 ) {
			$where .= ' AND char_count >= %d';
			$args[] = $filters['min_char_count'];
		}

		if ( $filters['max_char_count'] > 0 ) {
			$where .= ' AND char_count <= %d';
			$args[] = $filters['max_char_count'];
		}

		if ( '' !== $search ) {
			$like          = '%' . $this->wpdb->esc_like( $search ) . '%';
			$search_fields = array( 'url', 'page_title', 'meta_description', 'fetch_status', 'error_message' );

			if ( ! empty( $filters['search_body_text'] ) ) {
				$search_fields[] = 'extracted_text';
			}

			$where .= ' AND (' . implode( ' LIKE %s OR ', $search_fields ) . ' LIKE %s)';
			foreach ( $search_fields as $field ) {
				$args[] = $like;
			}
		}

		$total = (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM {$this->table_name} {$where}",
				...$args
			)
		);

		$query_args   = $args;
		$query_args[] = $per_page;
		$query_args[] = $offset;

		// Longtext columns (extracted_text, raw_html) are excluded from the list
		// query and replaced with a bounded snippet plus computed byte lengths,
		// since list rows only ever display a short preview + a size figure.
		// Full content is available via get_by_id().
		$items = $this->wpdb->get_results(
			$this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT id, source_id, url, page_title, meta_description, fetch_status, http_status, error_message,
				        char_count, num_used, content_hash, fetched_at, created_at, updated_at,
				        LEFT(extracted_text, 500) AS extracted_text,
				        LENGTH(extracted_text) AS extracted_text_bytes,
				        LENGTH(raw_html) AS raw_html_bytes
				 FROM {$this->table_name} {$where} ORDER BY fetched_at DESC, id DESC LIMIT %d OFFSET %d",
				...$query_args
			)
		);

		return array(
			'items'        => $items,
			'total'        => $total,
			'pages'        => (int) ceil( $total / $per_page ),
			'current_page' => $page,
			'per_page'     => $per_page,
		);
	}

	/**
	 * Retrieve a paginated set of source data rows across all sources.
	 *
	 * Rows are enriched with source_label and source_url from the sources table.
	 *
	 * @param string $search   Optional search term.
	 * @param int    $per_page Rows per page.
	 * @param int    $page     Current page.
	 * @param array  $filters  Optional structured filters (same keys as get_paginated_by_source_id, plus source_id).
	 * @return array{items:array,total:int,pages:int,current_page:int,per_page:int} Paginated results.
	 */
	public function get_paginated( $search = '', $per_page = 20, $page = 1, array $filters = array() ) {
		$per_page      = max( 1, min( 100, absint( $per_page ) ) );
		$page          = max( 1, absint( $page ) );
		$offset        = ( $page - 1 ) * $per_page;
		$search        = is_string( $search ) ? trim( $search ) : '';
		$sources_table = $this->wpdb->prefix . 'aips_sources';

		$filter_source_id = isset( $filters['source_id'] ) ? absint( $filters['source_id'] ) : 0;
		$norm             = $this->normalize_pagination_filters( $filters );

		$where = 'WHERE 1=1';
		$args  = array();

		if ( $filter_source_id > 0 ) {
			$where .= ' AND sd.source_id = %d';
			$args[] = $filter_source_id;
		}

		if ( '' !== $norm['fetch_status'] ) {
			$where .= ' AND sd.fetch_status = %s';
			$args[] = $norm['fetch_status'];
		}

		if ( $norm['http_status_class'] > 0 ) {
			$where .= ' AND sd.http_status >= %d AND sd.http_status <= %d';
			$args[] = $norm['http_status_class'];
			$args[] = $norm['http_status_class'] + 99;
		}

		if ( $norm['fetched_after'] > 0 ) {
			$where .= ' AND sd.fetched_at >= %d';
			$args[] = $norm['fetched_after'];
		}

		if ( $norm['fetched_before'] > 0 ) {
			$where .= ' AND sd.fetched_at <= %d';
			$args[] = $norm['fetched_before'];
		}

		if ( $norm['min_char_count'] > 0 ) {
			$where .= ' AND sd.char_count >= %d';
			$args[] = $norm['min_char_count'];
		}

		if ( $norm['max_char_count'] > 0 ) {
			$where .= ' AND sd.char_count <= %d';
			$args[] = $norm['max_char_count'];
		}

		if ( '' !== $search ) {
			$like          = '%' . $this->wpdb->esc_like( $search ) . '%';
			$search_fields = array( 'sd.url', 'sd.page_title', 'sd.meta_description', 'sd.fetch_status', 'sd.error_message', 's.label', 's.url' );

			if ( ! empty( $norm['search_body_text'] ) ) {
				$search_fields[] = 'sd.extracted_text';
			}

			$where .= ' AND (' . implode( ' LIKE %s OR ', $search_fields ) . ' LIKE %s)';
			foreach ( $search_fields as $field ) {
				$args[] = $like;
			}
		}

		$count_sql = "SELECT COUNT(*) FROM {$this->table_name} sd LEFT JOIN {$sources_table} s ON sd.source_id = s.id {$where}";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) ( empty( $args ) ? $this->wpdb->get_var( $count_sql ) : $this->wpdb->get_var( $this->wpdb->prepare( $count_sql, ...$args ) ) );

		$query_args   = $args;
		$query_args[] = $per_page;
		$query_args[] = $offset;

		// See get_paginated_by_source_id() for why longtext columns are excluded
		// from the list query in favor of a bounded snippet + byte lengths.
		$items = $this->wpdb->get_results(
			$this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT sd.id, sd.source_id, sd.url, sd.page_title, sd.meta_description, sd.fetch_status, sd.http_status, sd.error_message,
				        sd.char_count, sd.num_used, sd.content_hash, sd.fetched_at, sd.created_at, sd.updated_at,
				        LEFT(sd.extracted_text, 500) AS extracted_text,
				        LENGTH(sd.extracted_text) AS extracted_text_bytes,
				        LENGTH(sd.raw_html) AS raw_html_bytes,
				        s.label AS source_label, s.url AS source_url
				 FROM {$this->table_name} sd LEFT JOIN {$sources_table} s ON sd.source_id = s.id {$where} ORDER BY sd.fetched_at DESC, sd.id DESC LIMIT %d OFFSET %d",
				...$query_args
			)
		);

		return array(
			'items'        => $items,
			'total'        => $total,
			'pages'        => (int) ceil( $total / $per_page ),
			'current_page' => $page,
			'per_page'     => $per_page,
		);
	}

	/**
	 * Normalize structured filters for source data pagination.
	 *
	 * @param array $filters Raw filter values.
	 * @return array Normalized filter values.
	 */
	private function normalize_pagination_filters( array $filters ): array {
		$allowed_statuses     = array( 'pending', 'success', 'failed' );
		$allowed_http_classes = array( 100, 200, 300, 400, 500 );

		$fetch_status = isset( $filters['fetch_status'] ) ? sanitize_key( $filters['fetch_status'] ) : '';
		if ( ! in_array( $fetch_status, $allowed_statuses, true ) ) {
			$fetch_status = '';
		}

		$http_status_class = isset( $filters['http_status_class'] ) ? absint( $filters['http_status_class'] ) : 0;
		if ( ! in_array( $http_status_class, $allowed_http_classes, true ) ) {
			$http_status_class = 0;
		}

		$fetched_after  = isset( $filters['fetched_after'] ) ? $this->date_filter_to_timestamp( $filters['fetched_after'], false ) : 0;
		$fetched_before = isset( $filters['fetched_before'] ) ? $this->date_filter_to_timestamp( $filters['fetched_before'], true ) : 0;

		return array(
			'fetch_status'      => $fetch_status,
			'http_status_class' => $http_status_class,
			'fetched_after'     => $fetched_after,
			'fetched_before'    => $fetched_before,
			'min_char_count'    => isset( $filters['min_char_count'] ) ? absint( $filters['min_char_count'] ) : 0,
			'max_char_count'    => isset( $filters['max_char_count'] ) ? absint( $filters['max_char_count'] ) : 0,
			'search_body_text'  => ! empty( $filters['search_body_text'] ),
		);
	}

	/**
	 * Convert a YYYY-MM-DD filter to a UTC timestamp.
	 *
	 * @param mixed $date   Date filter input.
	 * @param bool  $end_of_day Whether to return the final second of the date.
	 * @return int Timestamp or zero when invalid.
	 */
	private function date_filter_to_timestamp( $date, bool $end_of_day = false ): int {
		$date = is_string( $date ) ? trim( $date ) : '';
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches ) ) {
			return 0;
		}

		if ( ! checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] ) ) {
			return 0;
		}

		try {
			$date_time = AIPS_DateTime::fromDate( $date );
			if ( $end_of_day ) {
				$date_time = $date_time->advance( '+1 day -1 second' );
			}
			return $date_time->timestamp();
		} catch ( Exception $e ) {
			return 0;
		}
	}

	/**
	 * Update one source data row.
	 *
	 * @param int   $id   Source data row ID.
	 * @param array $data Public source data fields to update.
	 * @return bool True when the update succeeded, false otherwise.
	 */
	public function update( $id, array $data ) {
		return true === $this->update_with_result( $id, $data );
	}

	/** Normalize text identically before hashing, counting, and storage. */
	public static function content_hash( string $url, string $text ): string {
		return hash( 'sha256', $url . "\n" . $text );
	}

	public static function normalize_text( $text ): string {
		$text = sanitize_textarea_field( (string) $text );
		return trim( preg_replace( '/[\p{Z}\t]+/u', ' ', $text ) ?? '' );
	}

	/** Update an archive row with an actionable failure code. @return true|WP_Error */
	public function update_with_result( $id, array $data ) {
		$id = absint( $id );
		$existing = $this->get_by_id( $id );
		if ( ! $existing ) {
			return new WP_Error( 'source_data_not_found', __( 'Source data record not found.', 'ai-post-scheduler' ) );
		}

		$allowed = array(
			'url'              => '%s',
			'page_title'       => '%s',
			'meta_description' => '%s',
			'extracted_text'   => '%s',
			'raw_html'         => '%s',
			'char_count'       => '%d',
			'fetch_status'     => '%s',
			'http_status'      => '%d',
			'error_message'    => '%s',
			'fetched_at'       => '%d',
		);

		$row    = array();
		$format = array();
		foreach ( $allowed as $key => $fmt ) {
			if ( array_key_exists( $key, $data ) ) {
				$row[ $key ] = $data[ $key ];
				$format[]    = $fmt;
			}
		}

		if ( empty( $row ) ) {
			return new WP_Error( 'source_data_write_failed', __( 'No archive fields supplied.', 'ai-post-scheduler' ) );
		}

		if ( array_key_exists( 'extracted_text', $row ) || array_key_exists( 'url', $row ) ) {
			if ( ! array_key_exists( 'extracted_text', $row ) ) {
				$row['extracted_text'] = $existing->extracted_text;
				$format[] = '%s';
			}
			$text       = self::normalize_text( $row['extracted_text'] );
			$row['extracted_text'] = $text;
			$char_count = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
			if ( array_key_exists( 'char_count', $row ) ) {
				$row['char_count'] = $char_count;
			} else {
				$row['char_count'] = $char_count;
				$format[]          = '%d';
			}
			$row['content_hash'] = '' !== $text ? self::content_hash( isset( $row['url'] ) ? $row['url'] : $existing->url, $text ) : null;
			$format[]            = '%s';
		}

		if ( ! empty( $row['content_hash'] ) && $this->duplicate_content_id( $existing->source_id, $row['content_hash'], $id, $row['extracted_text'], $row['url'] ?? $existing->url ) ) {
			return $this->duplicate_content_error();
		}

		$row['updated_at'] = AIPS_DateTime::now()->timestamp();
		$format[]          = '%d';

		$result = $this->wpdb->update(
			$this->table_name,
			$row,
			array( 'id' => $id ),
			$format,
			array( '%d' )
		);

		if ( false === $result ) {
			if ( ! empty( $row['content_hash'] ) && $this->duplicate_content_id( $existing->source_id, $row['content_hash'], $id, $row['extracted_text'], $row['url'] ?? $existing->url ) ) {
				return $this->duplicate_content_error();
			}
			return new WP_Error( 'source_data_write_failed', __( 'Failed to update source data.', 'ai-post-scheduler' ) );
		}
		if ( ! $this->get_by_id( $id ) ) {
			return new WP_Error( 'source_data_not_found', __( 'Source data record not found.', 'ai-post-scheduler' ) );
		}
		return true;
	}

	private function duplicate_content_id( $source_id, $hash, $id, $text, $url ) {
		return $this->wpdb->get_var( $this->wpdb->prepare(
			"SELECT id FROM {$this->table_name} WHERE source_id = %d AND (content_hash = %s OR (BINARY extracted_text = BINARY %s AND BINARY url = BINARY %s)) AND id != %d LIMIT 1",
			$source_id, $hash, $text, $url, $id
		) );
	}

	private function duplicate_content_error() {
		return new WP_Error( 'duplicate_source_content', __( 'Another archive snapshot for this source already contains this text. Both snapshots have been kept; change the text before saving.', 'ai-post-scheduler' ) );

	}

	/**
	 * Delete one source data row.
	 *
	 * @param int $id Source data row ID.
	 * @return bool True when deleted, false otherwise.
	 */
	public function delete( $id ) {
		$result = $this->wpdb->delete(
			$this->table_name,
			array( 'id' => absint( $id ) ),
			array( '%d' )
		);

		return false !== $result && $result > 0;
	}

	/**
	 * Retrieve recent generation history rows that used one source-data snapshot.
	 *
	 * @param int $id    Source data row ID.
	 * @param int $limit Maximum rows to return.
	 * @return array<int,array<string,mixed>> Usage rows for the admin modal.
	 */
	public function get_generation_usage( $id, $limit = 10 ) {
		$id    = absint( $id );
		$limit = max( 1, min( 50, absint( $limit ) ) );

		if ( $id <= 0 ) {
			return array();
		}

		$history_table     = $this->wpdb->prefix . 'aips_history';
		$history_log_table = $this->wpdb->prefix . 'aips_history_log';
		$candidate_limit   = $limit * 5;

		$candidates = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT hl.details, hl.timestamp, h.id AS history_id, h.post_id, h.generated_title, h.created_at, h.completed_at, p.post_title
				 FROM {$history_log_table} hl
				 INNER JOIN {$history_table} h ON hl.history_id = h.id
				 LEFT JOIN {$this->wpdb->posts} p ON h.post_id = p.ID
				 WHERE hl.details LIKE %s
				   AND hl.details LIKE %s
				 ORDER BY hl.timestamp DESC, hl.id DESC
				 LIMIT %d",
				'%"source_data_ids"%',
				'%' . $this->wpdb->esc_like( (string) $id ) . '%',
				$candidate_limit
			)
		);

		if ( empty( $candidates ) ) {
			return array();
		}

		$usage = array();

		foreach ( $candidates as $row ) {
			$details = json_decode( (string) $row->details, true );
			if ( ! is_array( $details ) ) {
				continue;
			}

			if ( ! isset( $details['context'] ) || ! is_array( $details['context'] ) ) {
				continue;
			}

			if ( empty( $details['context']['source_data_ids'] ) || ! is_array( $details['context']['source_data_ids'] ) ) {
				continue;
			}

			$source_data_ids = array_map( 'absint', $details['context']['source_data_ids'] );
			if ( ! in_array( $id, $source_data_ids, true ) ) {
				continue;
			}

			$post_id = isset( $row->post_id ) ? absint( $row->post_id ) : 0;
			$title   = '';

			if ( isset( $row->post_title ) && '' !== (string) $row->post_title ) {
				$title = (string) $row->post_title;
			} elseif ( isset( $row->generated_title ) && '' !== (string) $row->generated_title ) {
				$title = (string) $row->generated_title;
			} else {
				$title = sprintf(
					/* translators: %d = history row ID */
					__( 'History #%d', 'ai-post-scheduler' ),
					(int) $row->history_id
				);
			}

			$usage[] = array(
				'history_id'    => (int) $row->history_id,
				'post_id'       => $post_id,
				'title'         => $title,
				'history_url'   => AIPS_Admin_Menu_Helper::get_page_url( 'history', array( 'history_id' => (int) $row->history_id ) ),
				'post_edit_url' => $post_id ? get_edit_post_link( $post_id, 'raw' ) : '',
				'used_at'       => isset( $row->timestamp ) ? (int) $row->timestamp : 0,
			);

			if ( count( $usage ) >= $limit ) {
				break;
			}
		}

		return $usage;
	}

	/**
	 * Retrieve the most recent fetch snapshot for a source.
	 *
	 * @param int $source_id Source ID.
	 * @return object|null Row object or null if not found.
	 */
	public function get_by_source_id( $source_id ) {
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE source_id = %d ORDER BY id DESC LIMIT 1",
				absint( $source_id )
			)
		);
	}

	/**
	 * Retrieve the most recent successful fetch snapshot for a source.
	 *
	 * Useful after recording a failure row — returns the last row with
	 * fetch_status = 'success' so callers can confirm previously-cached
	 * content is still present.
	 *
	 * @param int $source_id Source ID.
	 * @return object|null Row object or null if no success row exists.
	 */
	public function get_latest_success_by_source_id( $source_id ) {
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE source_id = %d AND fetch_status = 'success' ORDER BY id DESC LIMIT 1",
				absint( $source_id )
			)
		);
	}

	/**
	 * Bulk-load the most recent fetch data row for each of the given source IDs.
	 *
	 * Returns every source regardless of fetch_status so callers can display
	 * error states. One row per source_id (the most recently inserted row).
	 *
	 * @param int[] $source_ids Array of source IDs.
	 * @return array<int,object> Map of source_id => row object.
	 */
	public function get_by_source_ids( array $source_ids ) {
		if ( empty( $source_ids ) ) {
			return array();
		}

		$source_ids   = array_map( 'absint', $source_ids );
		$placeholders = implode( ',', array_fill( 0, count( $source_ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT sd.* FROM {$this->table_name} sd
				 INNER JOIN (
				     SELECT source_id, MAX(id) AS max_id
				     FROM {$this->table_name}
				     WHERE source_id IN ($placeholders)
				     GROUP BY source_id
				 ) latest ON sd.id = latest.max_id",
				...$source_ids
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		$map = array();
		foreach ( $rows as $row ) {
			$map[ (int) $row->source_id ] = $row;
		}
		return $map;
	}

	/**
	 * Bulk-load extracted text for multiple source IDs.
	 *
	 * Returns one row per source_id — the row with the lowest num_used count
	 * (tiebroken by oldest id). This gives callers a fair, round-robin view
	 * of the archive without incrementing num_used (use pick_next_for_prompt_bulk()
	 * when you need the usage counter incremented).
	 *
	 * @param int[] $source_ids Array of source IDs.
	 * @return array<int,object> Map of source_id => row object (only rows where extracted_text is non-empty).
	 */
	public function get_extracted_texts_by_source_ids( array $source_ids ) {
		return $this->pick_next_for_prompt_bulk( $source_ids );
	}

	/**
	 * Pick the best archive row per source for injection into an AI prompt.
	 *
	 * Selection rule: prefer num_used = 0; if none, use the lowest num_used.
	 * Ties within the same num_used value are broken by oldest id (FIFO rotation).
	 *
	 * After using the returned rows in a prompt, call increment_num_used() on
	 * each row's id so the rotation advances on the next generation.
	 *
	 * @param int[] $source_ids Array of source IDs.
	 * @return array<int,object> Map of source_id => row object (only success rows with extracted_text).
	 */
	public function pick_next_for_prompt_bulk( array $source_ids ) {
		$source_ids = array_values( array_unique( array_filter( array_map( 'absint', $source_ids ) ) ) );
		if ( empty( $source_ids ) ) {
			return array();
		}
		$placeholders = implode( ',', array_fill( 0, count( $source_ids ), '%d' ) );
		$sources = $this->wpdb->prefix . 'aips_sources';
		$rows = $this->wpdb->get_results( $this->wpdb->prepare(
			"SELECT d.id, d.source_id, d.url, d.page_title, d.meta_description,
			        d.extracted_text, d.char_count, d.num_used, d.fetched_at
			 FROM {$this->table_name} d
			 INNER JOIN {$sources} s ON s.id = d.source_id AND BINARY s.url = BINARY d.url
			 LEFT JOIN {$this->table_name} better ON better.source_id = d.source_id
			   AND BINARY better.url = BINARY s.url AND better.fetch_status = 'success'
			   AND TRIM(better.extracted_text) != ''
			   AND (better.num_used < d.num_used OR (better.num_used = d.num_used AND better.id < d.id))
			 WHERE d.source_id IN ($placeholders) AND s.is_active = 1
			   AND d.fetch_status = 'success' AND TRIM(d.extracted_text) != '' AND better.id IS NULL
			 ORDER BY d.source_id ASC",
			...$source_ids
		) );
		$map = array();
		foreach ( $rows as $row ) {
			$map[ (int) $row->source_id ] = $row;
		}
		return $map;
	}

	/**
	 * Increment the num_used counter for a specific archive row.
	 *
	 * Called by the prompt builder after a row has been injected into a generation
	 * prompt, advancing the round-robin rotation for the next generation.
	 *
	 * @param int $id Row ID in aips_sources_data.
	 * @return void
	 */
	public function increment_num_used( $id ) {
		$id = absint( $id );
		if ( $id <= 0 ) {
			return;
		}
		$this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->table_name} SET num_used = num_used + 1 WHERE id = %d",
				$id
			)
		);
	}

	/**
	 * Get the number of archived content rows for a source.
	 *
	 * Only counts rows with fetch_status = 'success' and non-empty extracted_text.
	 *
	 * @param int $source_id Source ID.
	 * @return int Number of archived content rows.
	 */
	public function get_count_by_source_id( $source_id ) {
		return (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name}
				 WHERE source_id = %d AND fetch_status = 'success' AND extracted_text != ''",
				absint( $source_id )
			)
		);
	}

	/**
	 * Bulk-load the archived content count for multiple source IDs.
	 *
	 * Only counts rows with fetch_status = 'success' and non-empty extracted_text.
	 *
	 * @param int[] $source_ids Array of source IDs.
	 * @return array<int,int> Map of source_id => content count (0 for sources with no data).
	 */
	public function get_counts_by_source_ids( array $source_ids ) {
		if ( empty( $source_ids ) ) {
			return array();
		}

		$source_ids   = array_map( 'absint', $source_ids );
		$placeholders = implode( ',', array_fill( 0, count( $source_ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT source_id, COUNT(*) AS content_count
				 FROM {$this->table_name}
				 WHERE source_id IN ($placeholders)
				   AND fetch_status = 'success'
				   AND extracted_text != ''
				 GROUP BY source_id",
				...$source_ids
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		$map = array();
		foreach ( $rows as $row ) {
			$map[ (int) $row->source_id ] = (int) $row->content_count;
		}
		return $map;
	}

	/**
	 * Delete all fetch data for a source (called when source is deleted).
	 *
	 * @param int $source_id Source ID.
	 * @return void
	 */
	public function delete_by_source_id( $source_id ) {
		$this->wpdb->delete(
			$this->table_name,
			array( 'source_id' => absint( $source_id ) ),
			array( '%d' )
		);
	}

	/**
	 * Record a failed fetch attempt.
	 *
	 * Finds any existing failure row for the source and updates it in place,
	 * so failed attempts do not accumulate in the archive table. If no failure
	 * row exists yet, a new one is inserted. Success rows in the archive are
	 * never touched.
	 *
	 * @param int    $source_id   Source ID.
	 * @param string $error       Error message.
	 * @param int    $http_status HTTP response code (0 if no response).
	 * @return void
	 */
	public function mark_fetch_failed( $source_id, $error, $http_status = 0 ) {
		$source_id = absint( $source_id );
		if ( $source_id <= 0 ) {
			return;
		}

		$fail_row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT id FROM {$this->table_name} WHERE source_id = %d AND fetch_status = 'failed' ORDER BY id DESC LIMIT 1",
				$source_id
			)
		);

		if ( $fail_row ) {
			$this->wpdb->update(
				$this->table_name,
				array(
					'http_status'   => absint( $http_status ),
					'error_message' => sanitize_textarea_field( $error ),
					'fetched_at'    => AIPS_DateTime::now()->timestamp(),
					'updated_at'    => AIPS_DateTime::now()->timestamp(),
				),
				array( 'id' => (int) $fail_row->id ),
				array( '%d', '%s', '%d', '%d' ),
				array( '%d' )
			);
		} else {
			$this->wpdb->insert(
				$this->table_name,
				array(
					'source_id'     => $source_id,
					'fetch_status'  => 'failed',
					'http_status'   => absint( $http_status ),
					'error_message' => sanitize_textarea_field( $error ),
					'fetched_at'    => AIPS_DateTime::now()->timestamp(),
					'created_at'    => AIPS_DateTime::now()->timestamp(),
					'updated_at'    => AIPS_DateTime::now()->timestamp(),
				),
				array( '%d', '%s', '%d', '%s', '%d', '%d', '%d' )
			);
		}
	}
	/** Shared retention predicate: preserve the newest usable current-URL snapshot. */
	private function prunable_query( int $cutoff ): string {
		$sources = $this->wpdb->prefix . 'aips_sources';
		return $this->wpdb->prepare(
			"FROM {$this->table_name} d
			 LEFT JOIN {$sources} s ON s.id = d.source_id
			 WHERE d.fetched_at < %d AND NOT (
			   s.id IS NOT NULL AND BINARY d.url = BINARY s.url
			   AND d.fetch_status = 'success' AND TRIM(d.extracted_text) != ''
			   AND NOT EXISTS (SELECT 1 FROM {$this->table_name} newer
			      WHERE newer.source_id = d.source_id AND BINARY newer.url = BINARY s.url
			      AND newer.fetch_status = 'success' AND TRIM(newer.extracted_text) != ''
			      AND (newer.fetched_at > d.fetched_at OR (newer.fetched_at = d.fetched_at AND newer.id > d.id)))
			 )", $cutoff
		);
	}

	public function count_prunable_archive( int $cutoff ): int {
		$count = $this->wpdb->get_var( 'SELECT COUNT(*) ' . $this->prunable_query( $cutoff ) );
		if ( ! empty( $this->wpdb->last_error ) ) {
			throw new RuntimeException( 'Could not count eligible source archive rows.' );
		}
		return (int) $count;
	}

	/** Delete only archive rows; generation history retains its snapshot metadata. @return int|false */
	public function prune_archive( int $cutoff, int $limit = 100 ) {
		$limit = max( 1, min( 100, $limit ) );
		// The LIMIT forces materialization of the derived table (MySQL 5.7+),
		// allowing a self-referencing deletion while checking eligibility in
		// the same statement instead of deleting stale, previously selected IDs.
		$candidates = 'SELECT d.id ' . $this->prunable_query( $cutoff ) . ' ORDER BY d.fetched_at ASC, d.id ASC LIMIT ' . $limit;
		return $this->wpdb->query(
			"DELETE FROM {$this->table_name} WHERE id IN (SELECT id FROM ($candidates) AS prunable_archive)"
		);
	}

}
