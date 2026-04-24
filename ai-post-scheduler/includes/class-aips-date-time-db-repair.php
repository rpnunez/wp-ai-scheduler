<?php
/**
 * Developer-focused date/time repair utilities.
 *
 * Scans known plugin date/time columns, converts any lingering legacy
 * DATETIME/TIMESTAMP storage to Unix timestamps, and backfills missing
 * scheduler-facing next-run values for active records.
 *
 * @package AI_Post_Scheduler
 * @since   2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_Date_Time_DB_Repair {

	/**
	 * Lowest plausible Unix timestamp for plugin-managed date/time values.
	 *
	 * Values like "2026" appear in some migrated rows and should be treated as
	 * corrupted legacy data rather than as real 1970-era timestamps.
	 */
	const MIN_VALID_TIMESTAMP = 946684800; // 2000-01-01 00:00:00 UTC.

	/**
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * @var AIPS_Interval_Calculator
	 */
	private $interval_calculator;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;

		$this->wpdb                = $wpdb;
		$this->interval_calculator = new AIPS_Interval_Calculator();
	}

	/**
	 * Execute the full date/time repair routine.
	 *
	 * @return array
	 */
	public function run() {
		$summary = array(
			'converted_columns'        => 0,
			'normalized_null_values'   => 0,
			'fixed_schedule_next_runs' => 0,
			'fixed_author_next_runs'   => 0,
			'fixed_source_next_runs'   => 0,
		);

		$summary['converted_columns']      = $this->convert_legacy_datetime_columns();
		$summary['normalized_null_values'] = $this->normalize_null_timestamp_values();
		$summary['fixed_schedule_next_runs'] = $this->repair_template_schedule_next_runs();
		$summary['fixed_author_next_runs']   = $this->repair_author_next_runs();
		$summary['fixed_source_next_runs']   = $this->repair_source_next_runs();

		return $summary;
	}

	/**
	 * Convert lingering DATETIME/TIMESTAMP columns to BIGINT Unix timestamps.
	 *
	 * @return int
	 */
	private function convert_legacy_datetime_columns() {
		$converted = 0;

		foreach ( AIPS_DB_Manager::get_datetime_column_map() as $table_slug => $columns ) {
			$table = $this->wpdb->prefix . $table_slug;

			if ( $this->table_exists( $table ) !== $table ) {
				continue;
			}

			foreach ( $columns as $column ) {
				$col_name = is_array( $column ) ? $column[0] : $column;
				if ( $this->convert_legacy_datetime_column_to_bigint( $table, $col_name ) ) {
					$converted++;
				}
			}
		}

		return $converted;
	}

	/**
	 * Normalize any NULLs in timestamp-backed columns to zero.
	 *
	 * @return int
	 */
	private function normalize_null_timestamp_values() {
		$updated = 0;

		foreach ( AIPS_DB_Manager::get_datetime_column_map() as $table_slug => $columns ) {
			$table = $this->wpdb->prefix . $table_slug;

			if ( $this->table_exists( $table ) !== $table ) {
				continue;
			}

			foreach ( $columns as $column ) {
				$col_name = is_array( $column ) ? $column[0] : $column;
				if ( ! $this->column_exists( $table, $col_name ) ) {
					continue;
				}

				$result = $this->wpdb->query(
					"UPDATE `{$table}` SET `{$col_name}` = 0 WHERE `{$col_name}` IS NULL OR (`{$col_name}` > 0 AND `{$col_name}` < " . self::MIN_VALID_TIMESTAMP . ')' // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				);

				if ( is_numeric( $result ) ) {
					$updated += (int) $result;
				}
			}
		}

		return $updated;
	}

	/**
	 * Backfill missing template-schedule next_run values for active schedules.
	 *
	 * @return int
	 */
	private function repair_template_schedule_next_runs() {
		$table = $this->wpdb->prefix . 'aips_schedule';

		if ( $this->table_exists( $table ) !== $table ) {
			return 0;
		}

		$rows = $this->wpdb->get_results(
			"SELECT id, frequency, next_run, last_run, created_at, is_active FROM `{$table}`" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		if ( empty( $rows ) ) {
			return 0;
		}

		$fixed = 0;

		foreach ( $rows as $row ) {
			if ( empty( $row->is_active ) || ! $this->interval_calculator->is_valid_frequency( $row->frequency ) ) {
				continue;
			}

			$next_run = $this->normalize_db_datetime_value( $row->next_run );
			$last_run = $this->normalize_db_datetime_value( $row->last_run );
			$created  = $this->normalize_db_datetime_value( $row->created_at );

			if ( $next_run > 0 && ( $last_run <= 0 || $next_run > $last_run ) ) {
				continue;
			}

			$base = $last_run > 0 ? $last_run : $created;
			if ( $base <= 0 ) {
				$base = AIPS_DateTime::now()->timestamp();
			}

			$repaired_next_run = $this->interval_calculator->calculate_next_run( $row->frequency, $base );

			$result = $this->wpdb->update(
				$table,
				array( 'next_run' => absint( $repaired_next_run ) ),
				array( 'id' => absint( $row->id ) ),
				array( '%d' ),
				array( '%d' )
			);

			if ( false !== $result ) {
				$fixed++;
			}
		}

		return $fixed;
	}

	/**
	 * Backfill missing author topic/post next-run values for active authors.
	 *
	 * @return int
	 */
	private function repair_author_next_runs() {
		$table = $this->wpdb->prefix . 'aips_authors';

		if ( $this->table_exists( $table ) !== $table ) {
			return 0;
		}

		$rows = $this->wpdb->get_results(
			"SELECT id, is_active, created_at, topic_generation_frequency, topic_generation_next_run, topic_generation_last_run, topic_generation_is_active, post_generation_frequency, post_generation_next_run, post_generation_last_run, post_generation_is_active FROM `{$table}`" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		if ( empty( $rows ) ) {
			return 0;
		}

		$fixed = 0;

		foreach ( $rows as $row ) {
			if ( empty( $row->is_active ) ) {
				continue;
			}

			$created = $this->normalize_db_datetime_value( $row->created_at );
			if ( $created <= 0 ) {
				$created = AIPS_DateTime::now()->timestamp();
			}

			$topic_next = $this->maybe_calculate_missing_next_run(
				$row->topic_generation_frequency,
				$row->topic_generation_next_run,
				$row->topic_generation_last_run,
				$created,
				! isset( $row->topic_generation_is_active ) || (int) $row->topic_generation_is_active === 1
			);

			$post_next = $this->maybe_calculate_missing_next_run(
				$row->post_generation_frequency,
				$row->post_generation_next_run,
				$row->post_generation_last_run,
				$created,
				! isset( $row->post_generation_is_active ) || (int) $row->post_generation_is_active === 1
			);

			$update_data   = array();
			$update_format = array();

			if ( $topic_next > 0 ) {
				$update_data['topic_generation_next_run'] = absint( $topic_next );
				$update_format[]                          = '%d';
			}

			if ( $post_next > 0 ) {
				$update_data['post_generation_next_run'] = absint( $post_next );
				$update_format[]                         = '%d';
			}

			if ( empty( $update_data ) ) {
				continue;
			}

			$result = $this->wpdb->update(
				$table,
				$update_data,
				array( 'id' => absint( $row->id ) ),
				$update_format,
				array( '%d' )
			);

			if ( false !== $result ) {
				$fixed += count( $update_data );
			}
		}

		return $fixed;
	}

	/**
	 * Backfill missing source next-fetch values for active sources.
	 *
	 * @return int
	 */
	private function repair_source_next_runs() {
		$table = $this->wpdb->prefix . 'aips_sources';

		if ( $this->table_exists( $table ) !== $table ) {
			return 0;
		}

		$rows = $this->wpdb->get_results(
			"SELECT id, is_active, fetch_interval, next_fetch_at, last_fetched_at, created_at FROM `{$table}`" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		if ( empty( $rows ) ) {
			return 0;
		}

		$fixed = 0;

		foreach ( $rows as $row ) {
			$repaired_next_run = $this->maybe_calculate_missing_next_run(
				$row->fetch_interval,
				$row->next_fetch_at,
				$row->last_fetched_at,
				$this->normalize_db_datetime_value( $row->created_at ),
				! empty( $row->is_active )
			);

			if ( $repaired_next_run <= 0 ) {
				continue;
			}

			$result = $this->wpdb->update(
				$table,
				array( 'next_fetch_at' => absint( $repaired_next_run ) ),
				array( 'id' => absint( $row->id ) ),
				array( '%d' ),
				array( '%d' )
			);

			if ( false !== $result ) {
				$fixed++;
			}
		}

		return $fixed;
	}

	/**
	 * Calculate a missing next-run value for an active record when possible.
	 *
	 * @param string     $frequency Frequency key.
	 * @param mixed      $next_run Existing next-run DB value.
	 * @param mixed      $last_run Existing last-run DB value.
	 * @param int        $created_at Creation timestamp.
	 * @param bool|int   $is_active Whether the schedule is active.
	 * @return int
	 */
	private function maybe_calculate_missing_next_run( $frequency, $next_run, $last_run, $created_at, $is_active ) {
		if ( ! $is_active || empty( $frequency ) || ! $this->interval_calculator->is_valid_frequency( $frequency ) ) {
			return 0;
		}

		$normalized_next = $this->normalize_db_datetime_value( $next_run );
		$normalized_last = $this->normalize_db_datetime_value( $last_run );

		if ( $normalized_next > 0 && ( $normalized_last <= 0 || $normalized_next > $normalized_last ) ) {
			return 0;
		}

		$base = $normalized_last > 0 ? $normalized_last : absint( $created_at );
		if ( $base <= 0 ) {
			$base = AIPS_DateTime::now()->timestamp();
		}

		return absint( $this->interval_calculator->calculate_next_run( $frequency, $base ) );
	}

	/**
	 * Convert a single legacy DATETIME/TIMESTAMP column to BIGINT.
	 *
	 * @param string $table Full table name.
	 * @param string $col_name Column name.
	 * @return bool
	 */
	private function convert_legacy_datetime_column_to_bigint( $table, $col_name ) {
		$col_info = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SHOW COLUMNS FROM `{$table}` WHERE Field = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$col_name
			)
		);

		if ( ! $col_info ) {
			return false;
		}

		$type_lower = strtolower( $col_info->Type );

		if ( false !== strpos( $type_lower, 'bigint' ) ) {
			return false;
		}

		if ( false === strpos( $type_lower, 'datetime' ) && false === strpos( $type_lower, 'timestamp' ) ) {
			return false;
		}

		$tmp_col = $col_name . '_ts_tmp';

		$this->wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `{$tmp_col}` bigint(20) unsigned NOT NULL DEFAULT 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$this->wpdb->query( "UPDATE `{$table}` SET `{$tmp_col}` = IFNULL(UNIX_TIMESTAMP(`{$col_name}`), 0)" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$this->wpdb->query( "ALTER TABLE `{$table}` DROP COLUMN `{$col_name}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$this->wpdb->query( "ALTER TABLE `{$table}` CHANGE `{$tmp_col}` `{$col_name}` bigint(20) unsigned NOT NULL DEFAULT 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return true;
	}

	/**
	 * Normalize a DB date/time value to the plugin's timestamp format.
	 *
	 * @param mixed $value Raw DB value.
	 * @return int
	 */
	private function normalize_db_datetime_value( $value ) {
		if ( null === $value || '' === $value || '0000-00-00 00:00:00' === $value ) {
			return 0;
		}

		if ( is_numeric( $value ) ) {
			$timestamp = max( 0, (int) $value );
			if ( $timestamp > 0 && $timestamp < self::MIN_VALID_TIMESTAMP ) {
				return 0;
			}
			return $timestamp;
		}

		$parsed = AIPS_DateTime::fromMysqlOrNull( (string) $value );
		if ( $parsed instanceof AIPS_DateTime ) {
			return $parsed->timestamp();
		}

		return 0;
	}

	/**
	 * Check whether a table exists.
	 *
	 * @param string $table Table name.
	 * @return string|null
	 */
	private function table_exists( $table ) {
		return $this->wpdb->get_var( $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}

	/**
	 * Check whether a column exists.
	 *
	 * @param string $table Table name.
	 * @param string $column Column name.
	 * @return bool
	 */
	private function column_exists( $table, $column ) {
		$exists = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SHOW COLUMNS FROM `{$table}` WHERE Field = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$column
			)
		);

		return (bool) $exists;
	}
}
