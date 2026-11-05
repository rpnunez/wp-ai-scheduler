<?php
/**
 * Recent database migrations for the active development installation.
 *
 * The supported existing-installation baseline is 3.7.15. Schema definitions
 * live in AIPS_DB_Manager; only the five 3.7.16-3.7.20 migrations remain.
 * Apply the current schema first, then pending migrations in version order.
 * A failed step leaves the original version intact so the next request retries.
 *
 * @package AI_Post_Scheduler
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_DB_Migrations {
	/** @var AIPS_Logger */
	private $logger;

	public function __construct() {
		$this->logger = new AIPS_Logger();
	}

	/**
	 * Apply pending migrations, or force schema application on activation.
	 *
	 * @param bool $force_schema Apply the schema even when versions match.
	 * @return true|WP_Error
	 */
	public static function check_and_run( $force_schema = false ) {
		// Suspend metadata before Config/logger construction can write cache entries.
		AIPS_Cache_Index::suspend();
		try {
			$current_version = (string) get_option( 'aips_db_version', '' );
			if ( ! $force_schema && '' !== $current_version && ! version_compare( $current_version, AIPS_VERSION, '<' ) ) {
				return true;
			}
			return ( new self() )->run_upgrade( $current_version );
		} finally {
			AIPS_Cache_Index::resume();
		}
	}

	/**
	 * Apply schema and eligible recent migrations before acknowledging completion.
	 *
	 * @param string $from_version Previously stored DB version; empty on a new site.
	 * @return true|WP_Error
	 */
	private function run_upgrade( $from_version ) {
		$result = AIPS_DB_Manager::install_tables( false );
		if ( is_wp_error( $result ) ) {
			$this->logger->log( 'Database schema application failed: ' . $result->get_error_message(), 'error' );
			return $result;
		}

		// Fresh installations already have the current schema and no old rows to backfill.
		if ( '' !== $from_version && '0' !== $from_version ) {
			foreach ( array( '3.7.16', '3.7.17', '3.7.18', '3.7.19', '3.7.20' ) as $version ) {
				if ( ! version_compare( $from_version, $version, '<' ) ) {
					continue;
				}
				$method = 'migrate_to_' . str_replace( '.', '_', $version );
				if ( ! $this->$method() ) {
					$this->logger->log( 'Migration ' . $version . ' failed; database version unchanged for retry.', 'error' );
					/* translators: %s: migration version. */
					return new WP_Error( 'db_migration_failed', sprintf( __( 'Database migration %s failed.', 'ai-post-scheduler' ), $version ) );
				}
			}
		}

		AIPS_Config::get_instance()->set_option( 'aips_db_version', AIPS_VERSION );
		if ( AIPS_VERSION !== get_option( 'aips_db_version' ) ) {
			return new WP_Error( 'db_version_save_failed', __( 'Unable to save the completed database version.', 'ai-post-scheduler' ) );
		}
		$this->logger->log( 'Database upgraded from version ' . $from_version . ' to ' . AIPS_VERSION, 'info' );
		return true;
	}

	/**
	 * Migration for version 3.7.16.
	 *
	 * The status sync and the 3.7.13 backfill updated each post's newest non-event row,
	 * which for an indexed post is its content_indexing row. Reset those rows and put
	 * the status back on the generation run. Idempotent, so a failed attempt can simply
	 * run again.
	 *
	 * @return bool True when every statement succeeded; false leaves the version unstamped.
	 */
	private function migrate_to_3_7_16() {
		$repaired = AIPS_History_Repository::instance()->repair_generation_statuses();

		if ( $repaired['errors'] > 0 ) {
			$this->logger->log(
				sprintf( 'Migration 3.7.16: %d History repair statement(s) failed; will retry on the next request.', $repaired['errors'] ),
				'error'
			);

			return false;
		}

		$this->logger->log(
			sprintf(
				'Migration 3.7.16: Reset %d content-indexing rows from partial, marked %d runs partial and %d runs completed in History.',
				$repaired['jobs_reset'],
				$repaired['marked_partial'],
				$repaired['marked_completed']
			),
			'info'
		);

		return true;
	}

	/**
	 * Migration for version 3.7.17.
	 *
	 * Adds the record_kind column (via the schema apply) and classifies existing rows as
	 * generation runs or background jobs; everything else stays an event. The
	 * post_type backfill and status repair select rows by record_kind, so on a
	 * site upgrading from an older version they ran before any row was classified and
	 * did nothing; run both again here. All three steps are idempotent, so a failed
	 * attempt can simply run again.
	 *
	 * @return bool True when every statement succeeded; false leaves the version unstamped.
	 */
	private function migrate_to_3_7_17() {
		$history = AIPS_History_Repository::instance();

		$classified = $history->backfill_record_kind();
		$post_types = $history->backfill_post_type();
		$post_type_failed = $history->post_type_backfill_failed();
		$repaired   = $history->repair_generation_statuses();

		$errors = (int) $classified['errors'] + (int) $repaired['errors'] + (int) $post_type_failed;
		if ( $errors > 0 ) {
			$this->logger->log(
				sprintf( 'Migration 3.7.17: %d History statement(s) failed; will retry on the next request.', $errors ),
				'error'
			);

			return false;
		}

		$this->logger->log(
			sprintf(
				'Migration 3.7.17: Classified %d History rows as generation runs and %d as background jobs, backfilled post_type on %d rows, repaired %d run statuses.',
				$classified['generation'],
				$classified['job'],
				$post_types,
				$repaired['jobs_reset'] + $repaired['marked_partial'] + $repaired['marked_completed']
			),
			'info'
		);

		if ( ! empty( $classified['unclassified'] ) ) {
			$listed = array();
			foreach ( $classified['unclassified'] as $method => $count ) {
				$listed[] = sprintf( '%s (%d)', $method, $count );
			}

			$this->logger->log(
				sprintf(
					'Migration 3.7.17: History rows that carry a post were left as events because their creation_method is not a known run, job or event type: %s. If any are generation runs, add them with the aips_generation_run_creation_methods filter and run AIPS_History_Repository::backfill_record_kind().',
					implode( ', ', $listed )
				),
				'warning'
			);
		}

		return true;
	}

	/** Consolidate current author approval columns/options. @return bool */
	private function migrate_to_3_7_18() {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_authors';
		if ( false === $wpdb->query(
			"UPDATE `{$table}` SET topic_auto_approval_min_relevance = topic_auto_approval_min_score
			WHERE topic_auto_approval_mode IN ('similarity', 'embeddings')
			AND topic_auto_approval_min_score BETWEEN 1 AND 100"
		) ) {
			return false;
		}
		if ( false === $wpdb->query(
			"UPDATE `{$table}` SET topic_auto_approval_mode = 'inherit'
			WHERE topic_auto_approval_mode IS NULL OR topic_auto_approval_mode IN ('', 'manual')"
		) ) {
			return false;
		}
		if ( 'reject' === get_option( 'aips_author_topic_auto_approval_fallback' ) ) {
			update_option( 'aips_author_topic_auto_approval_fallback', 'rejected' );
			if ( 'rejected' !== get_option( 'aips_author_topic_auto_approval_fallback' ) ) {
				return false;
			}
		}
		foreach ( array(
			'aips_author_topic_auto_approval_enabled',
			'aips_author_topic_approval_mode',
			'aips_author_topic_min_relevance',
			'aips_author_topic_max_duplicate',
			'aips_author_topic_fallback_action',
		) as $option_name ) {
			delete_option( $option_name );
			if ( false !== get_option( $option_name, false ) ) {
				return false;
			}
		}
		$this->logger->log( 'Migration 3.7.18: Consolidated author topic auto-approval options and backfilled min_relevance.', 'info' );
		return true;
	}

	/** Guarded attempt-state addition; legacy attempt status remains unknown. @return bool */
	private function migrate_to_3_7_19() {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_sources';
		$schedule_column = $wpdb->get_row( "SHOW COLUMNS FROM `$table` LIKE 'next_fetch_at'" );
		if ( ! $schedule_column ) {
			return false;
		}
		if ( 'YES' !== $schedule_column->Null ) {
			if ( false === $wpdb->query( "ALTER TABLE `$table` MODIFY COLUMN `next_fetch_at` bigint(20) unsigned DEFAULT NULL" ) ) {
				return false;
			}
		}
		$columns = array(
			'last_fetch_attempt_at' => 'bigint(20) unsigned DEFAULT NULL',
			'last_fetch_status' => 'varchar(20) DEFAULT NULL',
			'last_fetch_error' => 'text DEFAULT NULL',
		);
		foreach ( $columns as $column => $definition ) {
			if ( ! $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `$table` LIKE %s", $column ) ) ) {
				if ( false === $wpdb->query( "ALTER TABLE `$table` ADD COLUMN `$column` $definition" ) ) {
					return false;
				}
			}
			if ( ! $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `$table` LIKE %s", $column ) ) ) {
				return false;
			}
		}
		$schedule_column = $wpdb->get_row( "SHOW COLUMNS FROM `$table` LIKE 'next_fetch_at'" );
		return $schedule_column && 'YES' === $schedule_column->Null;
	}

	/**
	 * Migration to 3.7.20:
	 * Adds composite index `history_type_ts` (history_type_id, timestamp) to `aips_history_log`.
	 *
	 * @return bool
	 */
	private function migrate_to_3_7_20() {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_history_log';

		$has_index = $wpdb->get_row(
			"SHOW INDEX FROM `{$table}` WHERE Key_name = 'history_type_ts'"
		);
		if ( $wpdb->last_error ) {
			return false;
		}
		if ( ! $has_index && false === $wpdb->query( "ALTER TABLE `{$table}` ADD KEY history_type_ts (history_type_id, timestamp)" ) ) {
			return false;
		}

		return true;
	}
}
