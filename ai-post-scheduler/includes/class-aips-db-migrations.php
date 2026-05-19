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
		$container = AIPS_Container::get_instance();
		$this->logger = $container->makeIfExists(AIPS_Logger_Interface::class, AIPS_Logger::class);
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
	 *   1. Versioned migrations run first — they handle the structural changes
	 *      that dbDelta cannot (column renames, type changes, index drops/adds,
	 *      data backfills). The schema must be consistent before dbDelta runs.
	 *   2. AIPS_DB_Manager::install_tables() runs second — dbDelta then applies
	 *      CREATE TABLE and ADD COLUMN for any new schema objects introduced in
	 *      the current plugin version.
	 *   3. aips_db_version is stamped to AIPS_VERSION via AIPS_Config::set_option()
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
}
