<?php
/**
 * Versioned database migrations for the AI Post Scheduler plugin.
 *
 * Two-layer DB contract
 * ─────────────────────
 * Layer 1 — AIPS_DB_Manager  (schema truth via dbDelta)
 *   Handles CREATE TABLE, ADD COLUMN, and column widening.
 *   Called by run_upgrade() after all migrations complete, ensuring new
 *   schema objects are applied on BOTH the activation path and the
 *   plugins_loaded path (WordPress auto-updates skip activation).
 *
 * Layer 2 — AIPS_DB_Migrations  (this class)
 *   Handles structural changes that WordPress's dbDelta() CANNOT perform:
 *     - Column renames and type changes (e.g. DATETIME → BIGINT UNSIGNED)
 *     - DROP INDEX / ADD INDEX / CHANGE INDEX
 *     - Data backfills
 *
 *   Migrations always run BEFORE dbDelta so the schema is in a consistent
 *   state before Layer 1 normalises it. Schema definitions live exclusively
 *   in AIPS_DB_Manager::get_schema(); no CREATE TABLE SQL belongs here.
 *
 * Adding a new migration
 * ──────────────────────
 * 1. Add a private migrate_to_X_Y_Z() method.
 * 2. Add the corresponding version_compare() gate in run_upgrade().
 * 3. Guard every ALTER with SHOW COLUMNS / SHOW INDEX so the method is
 *    always a no-op on fresh installs where dbDelta has already applied
 *    the target schema.
 *
 * @package AI_Post_Scheduler
 * @since   2.3.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIPS_DB_Migrations {

	/**
	 * @var AIPS_Logger
	 */
	private $logger;

	public function __construct() {
		$this->logger = new AIPS_Logger();
	}

	/**
	 * Run any pending versioned migrations.
	 *
	 * Called on plugin activation (via AI_Post_Scheduler::check_upgrades()) and
	 * on every page load via the plugins_loaded hook. The version gate ensures
	 * this is a fast no-op for sites that are already up-to-date.
	 *
	 * @return void
	 */
	public static function check_and_run() {
		$current_version = AIPS_Config::get_instance()->get_option( 'aips_db_version' );

		if ( version_compare( $current_version, AIPS_VERSION, '<' ) ) {
			$instance = new self();
			$instance->run_upgrade( $current_version );
		}
	}

	/**
	 * Run every migration whose target version is above $from_version, apply
	 * the dbDelta schema (Layer 1), then persist the new DB version so
	 * subsequent calls are skipped.
	 *
	 * Call order (must not be changed):
	 *   1. Structural migrations run first — they handle changes that dbDelta
	 *      cannot (column renames, type changes, index drops/adds). The schema
	 *      must be in a consistent state before dbDelta runs.
	 *   2. AIPS_DB_Manager::install_tables() runs second — dbDelta applies
	 *      CREATE TABLE and ADD COLUMN for any new schema objects introduced in
	 *      the current plugin version.
	 *   3. Data-backfill migrations that depend on newly-created tables or
	 *      columns run after install_tables(). These are an intentional
	 *      exception to the "migrations before dbDelta" rule: they require the
	 *      schema to be fully up-to-date before they can operate safely. All
	 *      such migrations must guard themselves with SHOW COLUMNS / SHOW TABLES
	 *      checks so they are no-ops when the target schema is absent.
	 *   4. aips_db_version is stamped to AIPS_VERSION via AIPS_Config::set_option()
	 *      so the Config in-memory cache is kept in sync and subsequent reads
	 *      within the same request see the updated value.
	 *
	 * install_tables() is idempotent (dbDelta is safe to run multiple times), so
	 * calling it here is harmless even when activate() also calls it directly
	 * afterward for the re-activation / fresh-install edge case.
	 *
	 * @param string $from_version The currently stored DB version.
	 * @return void
	 */
	private function run_upgrade( $from_version ) {
		if ( version_compare( $from_version, '2.3.1', '<' ) ) {
			$this->migrate_to_2_3_1();
		}

		if ( version_compare( $from_version, '2.4.0', '<' ) ) {
			$this->migrate_to_2_4_0();
		}

		if ( version_compare( $from_version, '2.4.1', '<' ) ) {
			$this->migrate_to_2_4_1();
		}

		if ( version_compare( $from_version, '2.5.0', '<' ) ) {
			$this->migrate_to_2_5_0();
		}

		// Apply Layer-1 schema changes (new tables / new columns) so that plugin
		// updates delivered via WordPress auto-update — which skip activate() —
		// still get a complete, up-to-date schema.
		$install_result = AIPS_DB_Manager::install_tables();

		if ( is_wp_error( $install_result ) ) {
			$this->logger->log(
				'install_tables() failed during upgrade from ' . $from_version . ': ' . $install_result->get_error_message(),
				'error'
			);

			if ( class_exists( 'AIPS_Notifications' ) ) {
				( new AIPS_Notifications() )->system_error( array(
					'title'         => __( 'Database upgrade failed', 'ai-post-scheduler' ),
					'error_code'    => $install_result->get_error_code(),
					'error_message' => $install_result->get_error_message(),
					'from_version'  => $from_version,
					'url'           => admin_url( 'admin.php?page=aips-status' ),
					'dedupe_key'    => 'db_upgrade_failed_' . sanitize_key( (string) $from_version ),
					'dedupe_window' => 1800,
				) );
			}

			// Return without stamping the version so the next request retries.
			return;
		}

		// migrate_to_2_8_2() is a data-backfill migration that requires the
		// aips_campaigns table and the campaign_id columns introduced in this
		// release to already exist (created above by install_tables()). It must
		// therefore run after install_tables() rather than before it.
		if ( version_compare( $from_version, '2.8.2', '<' ) ) {
			$this->migrate_to_2_8_2();
		}

		if ( version_compare( $from_version, '2.8.3', '<' ) ) {
			$this->migrate_to_2_8_3();
		}

		if ( version_compare( $from_version, '2.9.1', '<' ) ) {
			$this->migrate_to_2_9_1();
		}

		if ( version_compare( $from_version, '3.1.0', '<' ) ) {
			$this->migrate_to_3_1_0();
		}

		// Use AIPS_Config::set_option() so the per-request option cache is
		// invalidated immediately; bare update_option() would leave the cache
		// stale for the rest of this request.
		AIPS_Config::get_instance()->set_option( 'aips_db_version', AIPS_VERSION );
		$this->logger->log( 'Database upgraded from version ' . $from_version . ' to ' . AIPS_VERSION, 'info' );
	}

	/**
	 * Migration for version 2.3.1.
	 *
	 * Adds composite indexes to aips_notifications that were missing in older
	 * schema versions:
	 *   - is_read_created_at (is_read, created_at) — speeds up get_unread() / count_unread()
	 *   - dedupe_key_created_at (dedupe_key, created_at) — speeds up was_recently_sent()
	 *
	 * Both checks are guarded with SHOW INDEX so the ALTER is a no-op on fresh
	 * installs where dbDelta has already created the indexes from get_schema().
	 */
	private function migrate_to_2_3_1() {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_notifications';

		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $table_exists !== $table ) {
			return;
		}

		$indexes = array(
			'is_read_created_at'    => 'ADD KEY is_read_created_at (is_read, created_at)',
			'dedupe_key_created_at' => 'ADD KEY dedupe_key_created_at (dedupe_key, created_at)',
		);

		foreach ( $indexes as $key_name => $add_clause ) {
			$exists = $wpdb->get_row( $wpdb->prepare(
				"SHOW INDEX FROM `{$table}` WHERE Key_name = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$key_name
			) );

			if ( ! $exists ) {
				$wpdb->query( "ALTER TABLE `{$table}` {$add_clause}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
			}
		}
	}

	/**
	 * Migration for version 2.4.0.
	 *
	 * Adds three new columns to aips_sources for scheduled-fetch support:
	 *   - fetch_interval  varchar(50)  — frequency key (e.g. 'daily')
	 *   - last_fetched_at datetime     — timestamp of last successful fetch
	 *   - next_fetch_at   datetime     — pre-computed next fetch time
	 *
	 * Each ADD is guarded with a SHOW COLUMNS check so the ALTER is a no-op
	 * on fresh installs (where dbDelta has already created the columns).
	 *
	 * The new aips_sources_data table is created via dbDelta in install_tables().
	 */
	private function migrate_to_2_4_0() {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_sources';

		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $table_exists !== $table ) {
			return;
		}

		$columns = array(
			'fetch_interval'  => 'ADD COLUMN fetch_interval varchar(50) DEFAULT NULL AFTER is_active',
			'last_fetched_at' => 'ADD COLUMN last_fetched_at datetime DEFAULT NULL AFTER fetch_interval',
			'next_fetch_at'   => 'ADD COLUMN next_fetch_at datetime DEFAULT NULL AFTER last_fetched_at',
		);

		foreach ( $columns as $col_name => $add_clause ) {
			$exists = $wpdb->get_row( $wpdb->prepare(
				"SHOW COLUMNS FROM `{$table}` WHERE Field = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$col_name
			) );

			if ( ! $exists ) {
				$wpdb->query( "ALTER TABLE `{$table}` {$add_clause}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
			}
		}

		// Rename word_count → char_count on aips_sources_data if it exists with the old name.
		$table_sources_data  = $wpdb->prefix . 'aips_sources_data';
		$sources_data_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_sources_data ) );
		if ( $sources_data_exists === $table_sources_data ) {
			$old_col = $wpdb->get_row( $wpdb->prepare(
				"SHOW COLUMNS FROM `{$table_sources_data}` WHERE Field = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'word_count'
			) );
			if ( $old_col ) {
				$wpdb->query( "ALTER TABLE `{$table_sources_data}` CHANGE COLUMN `word_count` `char_count` int NOT NULL DEFAULT 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
		}
	}

	/**
	 * Migration for version 2.4.1.
	 *
	 * Converts aips_sources_data from a single-snapshot-per-source model to a
	 * growing archive model:
	 *
	 *  - Adds content_hash varchar(64) — SHA-256 of extracted_text for deduplication.
	 *  - Adds num_used int              — prompt usage counter for round-robin selection.
	 *  - Drops the UNIQUE KEY source_id (source_id) that prevented multiple rows per source.
	 *  - Adds UNIQUE KEY source_content_hash (source_id, content_hash) for deduplication.
	 *  - Adds KEY num_used (num_used) for efficient ordering.
	 *
	 * All ALTER statements are guarded with SHOW COLUMNS / SHOW INDEX checks so
	 * they are no-ops on fresh installs where dbDelta has already applied the
	 * new schema.
	 */
	private function migrate_to_2_4_1() {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_sources_data';

		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $table_exists !== $table ) {
			return;
		}

		// Add content_hash column.
		$has_hash = $wpdb->get_row( $wpdb->prepare(
			"SHOW COLUMNS FROM `{$table}` WHERE Field = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'content_hash'
		) );
		if ( ! $has_hash ) {
			$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN content_hash varchar(64) DEFAULT NULL AFTER char_count" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		// Add num_used column.
		$has_num_used = $wpdb->get_row( $wpdb->prepare(
			"SHOW COLUMNS FROM `{$table}` WHERE Field = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'num_used'
		) );
		if ( ! $has_num_used ) {
			$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN num_used int NOT NULL DEFAULT 0 AFTER content_hash" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		// Backfill content_hash for any existing success rows that have extracted_text.
		$wpdb->query(
			"UPDATE `{$table}` SET content_hash = SHA2(extracted_text, 256) WHERE content_hash IS NULL AND extracted_text IS NOT NULL AND extracted_text != '' AND fetch_status = 'success'" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		// Drop the old single-row-per-source unique key if it still exists.
		$has_old_unique = $wpdb->get_row(
			"SHOW INDEX FROM `{$table}` WHERE Key_name = 'source_id' AND Non_unique = 0" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
		if ( $has_old_unique ) {
			$wpdb->query( "ALTER TABLE `{$table}` DROP INDEX source_id" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		// Add non-unique source_id key if not present (replacing the dropped unique one).
		$has_source_key = $wpdb->get_row(
			"SHOW INDEX FROM `{$table}` WHERE Key_name = 'source_id' AND Non_unique = 1" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
		if ( ! $has_source_key ) {
			$wpdb->query( "ALTER TABLE `{$table}` ADD KEY source_id (source_id)" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		// Add composite deduplication unique key if not present.
		$has_dedup_key = $wpdb->get_row(
			"SHOW INDEX FROM `{$table}` WHERE Key_name = 'source_content_hash'" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
		if ( ! $has_dedup_key ) {
			$wpdb->query( "ALTER TABLE `{$table}` ADD UNIQUE KEY source_content_hash (source_id, content_hash)" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		// Add num_used index for efficient ordering if not present.
		$has_num_used_key = $wpdb->get_row(
			"SHOW INDEX FROM `{$table}` WHERE Key_name = 'num_used'" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
		if ( ! $has_num_used_key ) {
			$wpdb->query( "ALTER TABLE `{$table}` ADD KEY num_used (num_used)" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	/**
	 * Migration for version 2.5.0.
	 *
	 * Converts every DATETIME column in the plugin schema to BIGINT UNSIGNED
	 * storing Unix timestamps. This standardises all date/time handling on a
	 * single UTC-based integer representation so AIPS_DateTime is the single
	 * entry point for all date/time operations.
	 *
	 * All live sites running versions prior to 2.5.0 have had this migration
	 * applied. The SHOW COLUMNS guards in AIPS_DB_Manager::convert_datetime_column_to_bigint()
	 * make each column conversion a no-op when the column is already BIGINT.
	 *
	 * @deprecated 2.5.0 Permanently historical — all sites have already migrated.
	 *             Kept to protect the edge case of a very old install upgrading
	 *             directly to a post-2.5.0 version.
	 */
	private function migrate_to_2_5_0() {
		global $wpdb;

		foreach ( AIPS_DB_Manager::get_datetime_column_map() as $table_slug => $columns ) {
			$table = $wpdb->prefix . $table_slug;

			$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( $table_exists !== $table ) {
				continue;
			}

			foreach ( $columns as $col_def ) {
				AIPS_DB_Manager::convert_datetime_column_to_bigint( $table, $col_def[0] );
			}
		}
	}

	/**
	 * Migration for version 2.8.2.
	 *
	 * Adds the canonical campaigns parent table plus campaign ownership
	 * columns on templates, schedules, and history. Existing campaign-style
	 * template schedules are backfilled into parent campaign rows for the
	 * current local installation.
	 *
	 * @return void
	 */
	private function migrate_to_2_8_2() {
		global $wpdb;

		$table_campaigns = $wpdb->prefix . 'aips_campaigns';
		$table_templates = $wpdb->prefix . 'aips_templates';
		$table_schedule  = $wpdb->prefix . 'aips_schedule';
		$table_history   = $wpdb->prefix . 'aips_history';

		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_schedule ) );
		if ( $table_exists !== $table_schedule ) {
			return;
		}

		$template_campaign_column = $wpdb->get_row( $wpdb->prepare(
			"SHOW COLUMNS FROM `{$table_templates}` WHERE Field = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'campaign_id'
		) );
		$schedule_campaign_column = $wpdb->get_row( $wpdb->prepare(
			"SHOW COLUMNS FROM `{$table_schedule}` WHERE Field = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'campaign_id'
		) );
		$history_campaign_column = $wpdb->get_row( $wpdb->prepare(
			"SHOW COLUMNS FROM `{$table_history}` WHERE Field = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'campaign_id'
		) );

		if ( ! $template_campaign_column || ! $schedule_campaign_column || ! $history_campaign_column ) {
			return;
		}

		$campaigns_table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_campaigns ) );
		if ( $campaigns_table_exists !== $table_campaigns ) {
			return;
		}

		$existing_campaign_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_campaigns}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( $existing_campaign_count > 0 ) {
			$wpdb->query(
				"UPDATE {$table_history} h
				INNER JOIN {$table_templates} t ON h.template_id = t.id
				SET h.campaign_id = t.campaign_id
				WHERE h.campaign_id IS NULL
				AND t.campaign_id IS NOT NULL" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			);
			return;
		}

		$schedules = $wpdb->get_results(
			"SELECT s.*, t.name AS template_name
			FROM {$table_schedule} s
			LEFT JOIN {$table_templates} t ON s.template_id = t.id
			WHERE s.schedule_type = 'post_generation'", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		if ( empty( $schedules ) ) {
			return;
		}

		foreach ( $schedules as $schedule ) {
			$created_at = ! empty( $schedule['created_at'] ) ? absint( $schedule['created_at'] ) : AIPS_DateTime::now()->timestamp();
			$updated_at = AIPS_DateTime::now()->timestamp();
			$campaign_name = ! empty( $schedule['title'] ) ? $schedule['title'] : ( ! empty( $schedule['template_name'] ) ? $schedule['template_name'] : sprintf( 'Campaign %d', absint( $schedule['id'] ) ) );
			$content_goal = isset( $schedule['topic'] ) ? (string) $schedule['topic'] : '';
			$campaign_mode = ! empty( $schedule['campaign_mode'] ) ? sanitize_key( $schedule['campaign_mode'] ) : 'template';
			$is_active = ! empty( $schedule['is_active'] ) ? 1 : 0;
			$is_archived = ( isset( $schedule['status'] ) && 'archived' === $schedule['status'] ) ? 1 : 0;

			$wpdb->insert(
				$table_campaigns,
				array(
					'name'          => sanitize_text_field( $campaign_name ),
					'content_goal'  => sanitize_textarea_field( $content_goal ),
					'campaign_mode' => $campaign_mode,
					'is_active'     => $is_active,
					'is_archived'   => $is_archived,
					'created_at'    => $created_at,
					'updated_at'    => $updated_at,
				),
				array( '%s', '%s', '%s', '%d', '%d', '%d', '%d' )
			);

			$campaign_id = (int) $wpdb->insert_id;
			if ( ! $campaign_id ) {
				continue;
			}

			$wpdb->update(
				$table_schedule,
				array( 'campaign_id' => $campaign_id ),
				array( 'id' => absint( $schedule['id'] ) ),
				array( '%d' ),
				array( '%d' )
			);

			if ( ! empty( $schedule['template_id'] ) ) {
				$wpdb->update(
					$table_templates,
					array( 'campaign_id' => $campaign_id ),
					array( 'id' => absint( $schedule['template_id'] ) ),
					array( '%d' ),
					array( '%d' )
				);
			}
		}

		$wpdb->query(
			"UPDATE {$table_history} h
			INNER JOIN {$table_templates} t ON h.template_id = t.id
			SET h.campaign_id = t.campaign_id
			WHERE h.campaign_id IS NULL
			AND t.campaign_id IS NOT NULL"
		);
	}

	/**
	 * Migration for version 2.8.3.
	 *
	 * Repairs corrupted scheduling timestamps left behind by legacy writes that
	 * stored MySQL datetime strings in BIGINT-backed schedule columns. This
	 * reuses the central date/time repair utility for poisoned next-run values,
	 * then backfills template schedule last_run from run_state.timestamp when
	 * the stored last_run value is clearly invalid.
	 *
	 * @return void
	 */
	private function migrate_to_2_8_3() {
		$summary = ( new AIPS_Date_Time_DB_Repair() )->run();
		$fixed_last_runs = $this->repair_schedule_last_run_from_run_state();
		$fixed_template_alignments = $this->repair_template_schedule_next_runs_from_last_run();
		$fixed_author_alignments   = $this->repair_author_schedule_next_runs_from_last_run();

		$this->logger->log(
			sprintf(
				'2.8.3 schedule repair: normalized=%d, fixed_schedule_next_runs=%d, fixed_author_next_runs=%d, fixed_source_next_runs=%d, fixed_schedule_last_runs=%d, fixed_template_alignments=%d, fixed_author_alignments=%d',
				isset( $summary['normalized_null_values'] ) ? (int) $summary['normalized_null_values'] : 0,
				isset( $summary['fixed_schedule_next_runs'] ) ? (int) $summary['fixed_schedule_next_runs'] : 0,
				isset( $summary['fixed_author_next_runs'] ) ? (int) $summary['fixed_author_next_runs'] : 0,
				isset( $summary['fixed_source_next_runs'] ) ? (int) $summary['fixed_source_next_runs'] : 0,
				$fixed_last_runs,
				$fixed_template_alignments,
				$fixed_author_alignments
			),
			'info'
		);
	}

	/**
	 * Backfill invalid schedule.last_run values from run_state.timestamp.
	 *
	 * @return int Number of schedule rows updated.
	 */
	private function repair_schedule_last_run_from_run_state() {
		global $wpdb;

		$table = $wpdb->prefix . 'aips_schedule';

		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $table_exists !== $table ) {
			return 0;
		}

		$rows = $wpdb->get_results(
			"SELECT id, last_run, run_state FROM `{$table}` WHERE run_state IS NOT NULL AND run_state != ''", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return 0;
		}

		$updated = 0;

		foreach ( $rows as $row ) {
			$current_last_run = isset( $row['last_run'] ) ? (int) $row['last_run'] : 0;
			if ( $current_last_run >= AIPS_Date_Time_DB_Repair::MIN_VALID_TIMESTAMP ) {
				continue;
			}

			$state = json_decode( (string) $row['run_state'], true );
			if ( ! is_array( $state ) || empty( $state['timestamp'] ) ) {
				continue;
			}

			$run_at_ts = $this->normalize_run_state_timestamp( $state['timestamp'] );
			if ( $run_at_ts < AIPS_Date_Time_DB_Repair::MIN_VALID_TIMESTAMP ) {
				continue;
			}

			$result = $wpdb->update(
				$table,
				array( 'last_run' => $run_at_ts ),
				array( 'id' => absint( $row['id'] ) ),
				array( '%d' ),
				array( '%d' )
			);

			if ( false !== $result ) {
				$updated++;
			}
		}

		return $updated;
	}

	/**
	 * Realign template schedule next_run values from last_run using 2.8.3 rules.
	 *
	 * @return int
	 */
	private function repair_template_schedule_next_runs_from_last_run() {
		global $wpdb;

		$table = $wpdb->prefix . 'aips_schedule';

		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $table_exists !== $table ) {
			return 0;
		}

		$rows = $wpdb->get_results(
			"SELECT id, frequency, next_run, last_run FROM `{$table}`", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return 0;
		}

		$calculator = new AIPS_Interval_Calculator();
		$updated    = 0;

		foreach ( $rows as $row ) {
			$last_run = $this->normalize_timestamp_value( $row['last_run'] ?? 0 );
			if ( $last_run < AIPS_Date_Time_DB_Repair::MIN_VALID_TIMESTAMP ) {
				continue;
			}

			$frequency = isset( $row['frequency'] ) ? (string) $row['frequency'] : '';
			if ( ! $calculator->is_valid_frequency( $frequency ) ) {
				continue;
			}

			$expected_next_run = (int) $calculator->calculate_next_run( $frequency, $last_run );
			$current_next_run  = $this->normalize_timestamp_value( $row['next_run'] ?? 0 );

			if ( $current_next_run === $expected_next_run ) {
				continue;
			}

			$result = $wpdb->update(
				$table,
				array( 'next_run' => $expected_next_run ),
				array( 'id' => absint( $row['id'] ) ),
				array( '%d' ),
				array( '%d' )
			);

			if ( false !== $result ) {
				$updated++;
			}
		}

		return $updated;
	}

	/**
	 * Realign author topic/post next_run values from last_run using 2.8.3 rules.
	 *
	 * @return int
	 */
	private function repair_author_schedule_next_runs_from_last_run() {
		global $wpdb;

		$table = $wpdb->prefix . 'aips_authors';

		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $table_exists !== $table ) {
			return 0;
		}

		$rows = $wpdb->get_results(
			"SELECT id, topic_generation_frequency, topic_generation_next_run, topic_generation_last_run, post_generation_frequency, post_generation_next_run, post_generation_last_run FROM `{$table}`", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return 0;
		}

		$calculator = new AIPS_Interval_Calculator();
		$updated    = 0;

		foreach ( $rows as $row ) {
			$update_data   = array();
			$update_format = array();

			$topic_last_run = $this->normalize_timestamp_value( $row['topic_generation_last_run'] ?? 0 );
			if ( $topic_last_run >= AIPS_Date_Time_DB_Repair::MIN_VALID_TIMESTAMP ) {
				$frequency = isset( $row['topic_generation_frequency'] ) ? (string) $row['topic_generation_frequency'] : '';
				if ( $calculator->is_valid_frequency( $frequency ) ) {
					$expected_topic_next = (int) $calculator->calculate_next_run( $frequency, $topic_last_run );
					$current_topic_next  = $this->normalize_timestamp_value( $row['topic_generation_next_run'] ?? 0 );

					if ( $current_topic_next !== $expected_topic_next ) {
						$update_data['topic_generation_next_run'] = $expected_topic_next;
						$update_format[]                          = '%d';
					}
				}
			}

			$post_last_run = $this->normalize_timestamp_value( $row['post_generation_last_run'] ?? 0 );
			if ( $post_last_run >= AIPS_Date_Time_DB_Repair::MIN_VALID_TIMESTAMP ) {
				$frequency = isset( $row['post_generation_frequency'] ) ? (string) $row['post_generation_frequency'] : '';
				if ( $calculator->is_valid_frequency( $frequency ) ) {
					$expected_post_next = (int) $calculator->calculate_next_run( $frequency, $post_last_run );
					$current_post_next  = $this->normalize_timestamp_value( $row['post_generation_next_run'] ?? 0 );

					if ( $current_post_next !== $expected_post_next ) {
						$update_data['post_generation_next_run'] = $expected_post_next;
						$update_format[]                         = '%d';
					}
				}
			}

			if ( empty( $update_data ) ) {
				continue;
			}

			$result = $wpdb->update(
				$table,
				$update_data,
				array( 'id' => absint( $row['id'] ) ),
				$update_format,
				array( '%d' )
			);

			if ( false !== $result ) {
				$updated += count( $update_data );
			}
		}

		return $updated;
	}

	/**
	 * Normalize mixed timestamp-like values to integers.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	private function normalize_timestamp_value( $value ) {
		return is_numeric( $value ) ? max( 0, (int) $value ) : 0;
	}

	/**
	 * Migration for version 2.9.1.
	 *
	 * Converts the `post_category` column in `aips_templates` from a single
	 * BIGINT category ID to a TEXT column storing a JSON-encoded array of
	 * category IDs, enabling templates to be assigned to multiple categories.
	 *
	 * Steps:
	 *   1. Check the column still has a BIGINT-like type (no-op on fresh installs
	 *      where dbDelta has already created it as TEXT).
	 *   2. Migrate existing non-null, non-zero values to single-element JSON arrays
	 *      (e.g. 5 → [5]).
	 *   3. NULL and 0 values are set to NULL to preserve the "no category" state.
	 *   4. ALTER COLUMN to TEXT.
	 *
	 * @return void
	 */
	private function migrate_to_2_9_1() {
		global $wpdb;

		$table = $wpdb->prefix . 'aips_templates';

		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $table_exists !== $table ) {
			return;
		}

		// Check the current column type. If it is already TEXT/LONGTEXT the
		// migration has been applied (e.g. fresh install via dbDelta).
		$col_info = $wpdb->get_row( $wpdb->prepare(
			"SHOW COLUMNS FROM `{$table}` WHERE Field = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'post_category'
		) );

		if ( ! $col_info ) {
			return; // Column doesn't exist at all — nothing to do.
		}

		// If the Type is already a text-family type the data migration is done.
		$col_type = strtolower( (string) ( $col_info->Type ?? '' ) );
		if ( strpos( $col_type, 'text' ) !== false || strpos( $col_type, 'varchar' ) !== false ) {
			return;
		}

		// Step 1: Wrap existing non-zero integer values as JSON arrays.
		$wpdb->query(
			"UPDATE `{$table}` SET post_category = CONCAT('[', post_category, ']') WHERE post_category IS NOT NULL AND post_category != '0'" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		// Step 2: Normalise zero values to NULL (zero means "no category").
		$wpdb->query(
			"UPDATE `{$table}` SET post_category = NULL WHERE post_category = '0'" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		// Step 3: Change the column type from BIGINT to TEXT.
		$wpdb->query(
			"ALTER TABLE `{$table}` MODIFY COLUMN post_category text DEFAULT NULL" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}

	/**
	 * Migration for version 3.1.0.
	 *
	 * Drops the `log_type` column from `aips_history_log`. The semantic label
	 * previously stored there is moved into the `details` JSON payload under the
	 * `log_subtype` key by the container layer before every insert, so existing
	 * rows are backfilled in batches before the column is dropped.
	 *
	 * Guarded by SHOW COLUMNS so it is a no-op on fresh installs where dbDelta
	 * has already applied the target schema without the column.
	 */
	private function migrate_to_3_1_0() {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_history_log';

		// No-op if the table doesn't exist yet.
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $table_exists !== $table ) {
			return;
		}

		// No-op if log_type was already dropped (fresh install / re-run guard).
		$column_exists = $wpdb->get_var(
			$wpdb->prepare( "SHOW COLUMNS FROM `{$table}` LIKE %s", 'log_type' ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		if ( ! $column_exists ) {
			return;
		}

		// Backfill: copy log_type into details JSON as log_subtype, in batches.
		$batch_size = 500;
		$offset     = 0;
		$updated    = 0;

		do {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, log_type, details FROM `{$table}` ORDER BY id LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$batch_size,
					$offset
				)
			);

			foreach ( $rows as $row ) {
				$details = json_decode( $row->details, true );
				if ( ! is_array( $details ) ) {
					$details = array();
				}

				// Only write if not already backfilled.
				if ( ! isset( $details['log_subtype'] ) ) {
					$details['log_subtype'] = (string) $row->log_type;
					$wpdb->update(
						$table,
						array( 'details' => wp_json_encode( $details ) ),
						array( 'id' => (int) $row->id ),
						array( '%s' ),
						array( '%d' )
					);
					++$updated;
				}
			}

			$offset += $batch_size;
		} while ( count( $rows ) === $batch_size );

		// Drop the column now that data is safely in details JSON.
		$wpdb->query( "ALTER TABLE `{$table}` DROP COLUMN log_type" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$this->logger->log(
			"migrate_to_3_1_0: backfilled {$updated} rows, dropped log_type column from {$table}",
			'info'
		);
	}

	/**
	 * Normalize the run_state timestamp payload into a Unix timestamp.
	 *
	 * @param mixed $value Raw run_state timestamp value.
	 * @return int
	 */
	private function normalize_run_state_timestamp( $value ) {
		if ( is_numeric( $value ) ) {
			return max( 0, (int) $value );
		}

		if ( ! is_string( $value ) || '' === $value ) {
			return 0;
		}

		try {
			$run_at = new DateTimeImmutable( $value, new DateTimeZone( 'UTC' ) );
		} catch ( Exception $e ) {
			return 0;
		}

		return (int) $run_at->getTimestamp();
	}
}
