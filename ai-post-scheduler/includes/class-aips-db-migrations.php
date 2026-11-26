<?php
/**
 * Recent database migrations for the active development installation.
 *
 * The supported existing-installation baseline is 3.7.15. Schema definitions
 * live in AIPS_DB_Manager; only recent migrations remain.
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
			foreach ( array( '3.7.16', '3.7.17', '3.7.18', '3.7.19', '3.7.20', '3.7.22', '3.7.23', '3.7.24' ) as $version ) {
				if ( ! version_compare( $from_version, $version, '<' ) ) {
					continue;
				}
				$method = 'migrate_to_' . str_replace( '.', '_', $version );
				if ( ! method_exists( $this, $method ) ) {
					continue;
				}
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

	/**
	 * Migration for version 3.7.22.
	 *
	 * Consolidates redundant single-column indexes on aips_history and aips_author_topics,
	 * adds author_id_status_score covering index, strips cached candidate embedding
	 * vectors from aips_author_topics metadata, cleans up orphaned aips_history_log rows,
	 * and clears the decoded embeddings cache group from aips_cache.
	 *
	 * @return bool
	 */
	private function migrate_to_3_7_22() {
		$this->consolidate_history_indexes();
		$this->consolidate_author_topic_indexes();
		$this->strip_author_topic_metadata_embeddings();
		$this->cleanup_orphaned_history_log();
		$this->cleanup_embeddings_cache();

		return true;
	}

	/**
	 * Drop the decoded-embedding rows from the aips_cache 'aips_embeddings' group.
	 *
	 * @return void
	 */
	private function cleanup_embeddings_cache() {
		global $wpdb;
		$cache = $wpdb->prefix . 'aips_cache';

		$deleted = $wpdb->query( $wpdb->prepare(
			"DELETE FROM `{$cache}` WHERE cache_group = %s",
			'aips_embeddings'
		) );

		if ( $deleted ) {
			$this->logger->log( sprintf( 'Migration 3.7.22: Cleared %d decoded-embedding rows from the aips_cache embeddings group.', (int) $deleted ), 'info' );
		}
	}

	/**
	 * Drop redundant indexes on aips_history.
	 *
	 * @return void
	 */
	private function consolidate_history_indexes() {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_history';

		$redundant = array(
			'status'      => 'status_created',
			'template_id' => 'template_created',
			'post_id'     => 'post_status',
		);

		foreach ( $redundant as $index_name => $covering_index ) {
			$this->drop_redundant_index( $table, $index_name, $covering_index );
		}
	}

	/**
	 * Add author_id_status_score covering index on aips_author_topics and drop redundant indexes.
	 *
	 * @return void
	 */
	private function consolidate_author_topic_indexes() {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_author_topics';

		$has_new = $wpdb->get_row(
			"SHOW INDEX FROM `{$table}` WHERE Key_name = 'author_id_status_score'"
		);
		if ( ! $has_new ) {
			$wpdb->query( "ALTER TABLE `{$table}` ADD KEY author_id_status_score (author_id, status, score)" );
		}

		$this->drop_redundant_index( $table, 'author_id', 'author_id_status_score' );
		$this->drop_redundant_index( $table, 'author_id_status', 'author_id_status_score' );
		$this->drop_redundant_index( $table, 'status', 'status_score_reviewed' );
	}

	/**
	 * Drop an index, but only once the covering index that replaces it exists.
	 *
	 * @param string $table          Full table name.
	 * @param string $index_name     Index to drop.
	 * @param string $covering_index Index that must be present first.
	 * @return void
	 */
	private function drop_redundant_index( $table, $index_name, $covering_index ) {
		global $wpdb;

		$covering = $wpdb->get_row( $wpdb->prepare(
			"SHOW INDEX FROM `{$table}` WHERE Key_name = %s",
			$covering_index
		) );
		if ( ! $covering ) {
			return;
		}

		$exists = $wpdb->get_row( $wpdb->prepare(
			"SHOW INDEX FROM `{$table}` WHERE Key_name = %s",
			$index_name
		) );
		if ( $exists ) {
			$wpdb->query( "ALTER TABLE `{$table}` DROP INDEX `{$index_name}`" );
		}
	}

	/**
	 * Remove cached embedding vector from existing aips_author_topics metadata payloads.
	 *
	 * @return void
	 */
	private function strip_author_topic_metadata_embeddings() {
		global $wpdb;
		$table = $wpdb->prefix . 'aips_author_topics';

		$batch_size = 200;
		$last_id    = 0;
		$updated    = 0;

		do {
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, metadata FROM `{$table}`
				WHERE id > %d AND metadata LIKE %s
				ORDER BY id LIMIT %d",
				$last_id,
				'%"embedding"%',
				$batch_size
			) );

			foreach ( $rows as $row ) {
				$last_id = (int) $row->id;

				$meta = json_decode( (string) $row->metadata, true );
				if ( ! is_array( $meta ) || ! array_key_exists( 'embedding', $meta ) ) {
					continue;
				}

				unset( $meta['embedding'] );

				$wpdb->update(
					$table,
					array( 'metadata' => wp_json_encode( $meta ) ),
					array( 'id' => (int) $row->id ),
					array( '%s' ),
					array( '%d' )
				);
				++$updated;
			}
		} while ( ! empty( $rows ) );

		if ( $updated ) {
			$this->logger->log( sprintf( 'Migration 3.7.22: Stripped cached embeddings from %d aips_author_topics metadata rows.', $updated ), 'info' );
		}
	}

	/**
	 * Delete aips_history_log rows whose parent aips_history row no longer exists.
	 *
	 * @return void
	 */
	private function cleanup_orphaned_history_log() {
		global $wpdb;
		$log  = $wpdb->prefix . 'aips_history_log';
		$hist = $wpdb->prefix . 'aips_history';

		$deleted = $wpdb->query(
			"DELETE l FROM `{$log}` l
			LEFT JOIN `{$hist}` h ON h.id = l.history_id
			WHERE h.id IS NULL"
		);

		if ( $deleted ) {
			$this->logger->log( sprintf( 'Migration 3.7.22: Deleted %d orphaned aips_history_log rows.', (int) $deleted ), 'info' );
		}
	}

	/**
	 * Migration to 3.7.23:
	 * Adds generate_slug and slug_prompt to aips_templates and aips_authors.
	 *
	 * @return bool
	 */
	private function migrate_to_3_7_23() {
		global $wpdb;

		$table_templates = $wpdb->prefix . 'aips_templates';
		$table_authors   = $wpdb->prefix . 'aips_authors';

		// 1. Templates table: generate_slug and slug_prompt
		$has_gen_slug = $wpdb->get_row( $wpdb->prepare(
			"SHOW COLUMNS FROM `{$table_templates}` WHERE Field = %s",
			'generate_slug'
		) );
		if ( ! $has_gen_slug && false === $wpdb->query( "ALTER TABLE `{$table_templates}` ADD COLUMN generate_slug tinyint(1) DEFAULT 0 AFTER generate_featured_image" ) ) {
			return false;
		}

		$has_slug_prompt = $wpdb->get_row( $wpdb->prepare(
			"SHOW COLUMNS FROM `{$table_templates}` WHERE Field = %s",
			'slug_prompt'
		) );
		if ( ! $has_slug_prompt && false === $wpdb->query( "ALTER TABLE `{$table_templates}` ADD COLUMN slug_prompt text DEFAULT NULL AFTER title_prompt" ) ) {
			return false;
		}

		// 2. Authors table: generate_slug and slug_prompt
		$has_author_gen_slug = $wpdb->get_row( $wpdb->prepare(
			"SHOW COLUMNS FROM `{$table_authors}` WHERE Field = %s",
			'generate_slug'
		) );
		if ( ! $has_author_gen_slug && false === $wpdb->query( "ALTER TABLE `{$table_authors}` ADD COLUMN generate_slug tinyint(1) DEFAULT 0 AFTER generate_featured_image" ) ) {
			return false;
		}

		$has_author_slug_prompt = $wpdb->get_row( $wpdb->prepare(
			"SHOW COLUMNS FROM `{$table_authors}` WHERE Field = %s",
			'slug_prompt'
		) );
		if ( ! $has_author_slug_prompt && false === $wpdb->query( "ALTER TABLE `{$table_authors}` ADD COLUMN slug_prompt text DEFAULT NULL AFTER generate_slug" ) ) {
			return false;
		}

		$this->logger->log( 'Migration 3.7.23: Added generate_slug and slug_prompt to templates and authors.', 'info' );

		return true;
	}

	/**
	 * Migration to 3.7.24:
	 * Adds author featured-image columns and confirms post-image schema columns.
	 *
	 * @return bool
	 */
	private function migrate_to_3_7_24() {
		global $wpdb;

		$authors_table = $wpdb->prefix . 'aips_authors';

		$author_columns = array(
			'image_prompt'                     => 'text DEFAULT NULL',
			'featured_image_unsplash_keywords' => 'text DEFAULT NULL',
			'featured_image_media_ids'         => 'text DEFAULT NULL',
		);
		foreach ( $author_columns as $column => $definition ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `{$authors_table}` LIKE %s", $wpdb->esc_like( $column ) ) );
			if ( ! $exists && false === $wpdb->query( "ALTER TABLE `{$authors_table}` ADD COLUMN `{$column}` {$definition}" ) ) {
				return false;
			}
			if ( ! $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `{$authors_table}` LIKE %s", $wpdb->esc_like( $column ) ) ) ) {
				return false;
			}
		}

		$post_images = $wpdb->prefix . 'aips_post_images';
		$generated   = $wpdb->prefix . 'aips_post_images_generated';
		$required_columns = array(
			$post_images => array( 'entity_type', 'entity_id', 'image_order', 'source', 'placement' ),
			$generated   => array( 'post_id', 'history_id', 'config_image_id', 'image_index', 'attachment_id', 'status' ),
		);
		foreach ( $required_columns as $table => $columns ) {
			foreach ( $columns as $column ) {
				if ( ! $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `{$table}` LIKE %s", $wpdb->esc_like( $column ) ) ) ) {
					return false;
				}
			}
		}

		$this->logger->log( 'Migration 3.7.24: Added author featured-image columns and confirmed post-image tables.', 'info' );

		return true;
	}
}
