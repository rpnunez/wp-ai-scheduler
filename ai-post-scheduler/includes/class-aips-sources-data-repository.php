<?php
/**
 * Sources Data Repository
 *
 * Database abstraction layer for scraped source content.
 * Stores one fetch snapshot per source URL.
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
 * Each row represents the most recent fetch result for a single source.
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
	 * Insert or update a fetch record for a source.
	 *
	 * Checks whether a row already exists for the given source_id and then
	 * performs either an update or an insert so the latest fetch snapshot is
	 * stored for that source.
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
	 * @return bool True on success, false on failure.
	 */
	public function upsert( $source_id, array $data ) {
		$source_id = absint( $source_id );
		if ( $source_id <= 0 ) {
			return false;
		}

		$existing = $this->get_by_source_id( $source_id );

		$row = array(
			'source_id'        => $source_id,
			'url'              => isset( $data['url'] ) ? esc_url_raw( $data['url'] ) : '',
			'page_title'       => isset( $data['page_title'] ) ? sanitize_text_field( $data['page_title'] ) : '',
			'meta_description' => isset( $data['meta_description'] ) ? sanitize_textarea_field( $data['meta_description'] ) : '',
			'extracted_text'   => isset( $data['extracted_text'] ) ? sanitize_textarea_field( $data['extracted_text'] ) : '',
			'raw_html'         => isset( $data['raw_html'] ) ? $data['raw_html'] : '',
			'char_count'       => isset( $data['char_count'] ) ? absint( $data['char_count'] ) : 0,
			'fetch_status'     => isset( $data['fetch_status'] ) ? sanitize_text_field( $data['fetch_status'] ) : 'success',
			'http_status'      => isset( $data['http_status'] ) ? absint( $data['http_status'] ) : 0,
			'error_message'    => isset( $data['error_message'] ) ? sanitize_textarea_field( $data['error_message'] ) : '',
			'fetched_at'       => current_time( 'mysql' ),
		);

		$format = array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s' );

		if ( $existing ) {
			$result = $this->wpdb->update(
				$this->table_name,
				$row,
				array( 'source_id' => $source_id ),
				$format,
				array( '%d' )
			);
			return $result !== false;
		}

		$result = $this->wpdb->insert( $this->table_name, $row, $format );
		return $result !== false;
	}

	/**
	 * Retrieve the fetch snapshot for a source.
	 *
	 * @param int $source_id Source ID.
	 * @return object|null Row object or null if not found.
	 */
	public function get_by_source_id( $source_id ) {
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE source_id = %d",
				absint( $source_id )
			)
		);
	}

	/**
	 * Bulk-load all fetch data rows for an array of source IDs.
	 *
	 * Unlike get_extracted_texts_by_source_ids(), this returns every row
	 * regardless of fetch_status so callers can display error states too.
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
				"SELECT * FROM {$this->table_name} WHERE source_id IN ($placeholders)",
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
				"SELECT * FROM {$this->table_name} WHERE source_id IN ($placeholders) AND extracted_text != '' AND fetch_status = 'success'",
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
	 * Record a failed fetch attempt without touching extracted_text.
	 *
	 * Preserves any previously stored content while updating the error metadata.
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

		$existing = $this->get_by_source_id( $source_id );

		if ( $existing ) {
			$this->wpdb->update(
				$this->table_name,
				array(
					'fetch_status'  => 'failed',
					'http_status'   => absint( $http_status ),
					'error_message' => sanitize_textarea_field( $error ),
					'fetched_at'    => current_time( 'mysql' ),
				),
				array( 'source_id' => $source_id ),
				array( '%s', '%d', '%s', '%s' ),
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
