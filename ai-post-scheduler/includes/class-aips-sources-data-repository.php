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

		$extracted_text = isset( $data['extracted_text'] ) ? sanitize_textarea_field( $data['extracted_text'] ) : '';
		$content_hash   = '' !== $extracted_text ? hash( 'sha256', $extracted_text ) : null;

		// Skip if this exact content snapshot is already stored.
		if ( null !== $content_hash ) {
			$existing_id = $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT id FROM {$this->table_name} WHERE source_id = %d AND content_hash = %s LIMIT 1",
					$source_id,
					$content_hash
				)
			);
			if ( $existing_id ) {
				return true;
			}
		}

		$row = array(
			'source_id'        => $source_id,
			'url'              => isset( $data['url'] ) ? esc_url_raw( $data['url'] ) : '',
			'page_title'       => isset( $data['page_title'] ) ? sanitize_text_field( $data['page_title'] ) : '',
			'meta_description' => isset( $data['meta_description'] ) ? sanitize_textarea_field( $data['meta_description'] ) : '',
			'extracted_text'   => $extracted_text,
			'raw_html'         => isset( $data['raw_html'] ) ? $data['raw_html'] : '',
			'char_count'       => isset( $data['char_count'] ) ? absint( $data['char_count'] ) : 0,
			'content_hash'     => $content_hash,
			'num_used'         => 0,
			'fetch_status'     => isset( $data['fetch_status'] ) ? sanitize_text_field( $data['fetch_status'] ) : 'success',
			'http_status'      => isset( $data['http_status'] ) ? absint( $data['http_status'] ) : 0,
			'error_message'    => isset( $data['error_message'] ) ? sanitize_textarea_field( $data['error_message'] ) : '',
			'fetched_at'       => current_time( 'mysql' ),
		);

		$format = array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%d', '%s', '%s' );
		$result = $this->wpdb->insert( $this->table_name, $row, $format );
		return $result !== false;
	}

	/**
	 * @deprecated 2.4.1 Use insert_if_new() instead. Kept for backwards compatibility.
	 *
	 * @param int   $source_id Source ID.
	 * @param array $data      Fetch result data.
	 * @return bool
	 */
	public function upsert( $source_id, array $data ) {
		return $this->insert_if_new( $source_id, $data );
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
		if ( empty( $source_ids ) ) {
			return array();
		}

		$source_ids   = array_map( 'absint', $source_ids );
		$placeholders = implode( ',', array_fill( 0, count( $source_ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$this->table_name}
				 WHERE source_id IN ($placeholders)
				   AND extracted_text != ''
				   AND fetch_status = 'success'
				 ORDER BY source_id ASC, num_used ASC, id ASC",
				...$source_ids
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		// Keep only the first (best) row per source_id.
		$map = array();
		foreach ( $rows as $row ) {
			$sid = (int) $row->source_id;
			if ( ! isset( $map[ $sid ] ) ) {
				$map[ $sid ] = $row;
			}
		}
		return $map;
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
		if ( empty( $source_ids ) ) {
			return array();
		}

		$source_ids   = array_map( 'absint', $source_ids );
		$placeholders = implode( ',', array_fill( 0, count( $source_ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$this->table_name}
				 WHERE source_id IN ($placeholders)
				   AND extracted_text != ''
				   AND fetch_status = 'success'
				 ORDER BY source_id ASC, num_used ASC, id ASC",
				...$source_ids
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		// PHP-side: keep the first row per source_id (lowest num_used, oldest id wins).
		$map = array();
		foreach ( $rows as $row ) {
			$sid = (int) $row->source_id;
			if ( ! isset( $map[ $sid ] ) ) {
				$map[ $sid ] = $row;
			}
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
					'fetched_at'    => current_time( 'mysql' ),
				),
				array( 'id' => (int) $fail_row->id ),
				array( '%d', '%s', '%s' ),
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
					'fetched_at'    => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%d', '%s', '%s' )
			);
		}
	}
}
