<?php
/**
 * Datetime column definitions and bigint conversion migrator.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_DB_Datetime_Migrator
 *
 * Encapsulates the schema map of temporal datetime/timestamp columns across all
 * custom plugin tables, and provides safe ALTER TABLE conversion to BIGINT UNIX timestamps.
 */
class AIPS_DB_Datetime_Migrator {

	/**
	 * Return the plugin-wide map of datetime/timestamp-backed columns.
	 *
	 * Array of table_slug => array( array( column_name, is_nullable ), ... ).
	 *
	 * @return array<string, array<int, array{0: string, 1: bool}>>
	 */
	public static function get_datetime_column_map() {
		return array(
			'aips_history' => array(
				array( 'created_at', false ),
				array( 'completed_at', false ),
			),
			'aips_history_log' => array(
				array( 'timestamp', false ),
			),
			'aips_templates' => array(
				array( 'created_at', false ),
				array( 'updated_at', false ),
			),
			'aips_schedule' => array(
				array( 'next_run', false ),
				array( 'last_run', false ),
				array( 'created_at', false ),
			),
			'aips_voices' => array(
				array( 'created_at', false ),
			),
			'aips_article_structures' => array(
				array( 'created_at', false ),
				array( 'updated_at', false ),
			),
			'aips_prompt_sections' => array(
				array( 'created_at', false ),
				array( 'updated_at', false ),
			),
			'aips_trending_topics' => array(
				array( 'researched_at', false ),
			),
			'aips_authors' => array(
				array( 'topic_generation_next_run', false ),
				array( 'topic_generation_last_run', false ),
				array( 'post_generation_next_run', false ),
				array( 'post_generation_last_run', false ),
				array( 'created_at', false ),
				array( 'updated_at', false ),
			),
			'aips_post_slices' => array(
				array( 'created_at', false ),
				array( 'updated_at', false ),
			),
			'aips_author_topics' => array(
				array( 'generated_at', false ),
				array( 'reviewed_at', false ),
			),
			'aips_author_topic_logs' => array(
				array( 'created_at', false ),
			),
			'aips_topic_feedback' => array(
				array( 'created_at', false ),
			),
			'aips_notifications' => array(
				array( 'read_at', false ),
				array( 'created_at', false ),
			),
			'aips_sources' => array(
				array( 'last_fetched_at', false ),
				array( 'next_fetch_at', false ),
				array( 'created_at', false ),
				array( 'updated_at', false ),
			),
			'aips_sources_data' => array(
				array( 'fetched_at', false ),
				array( 'created_at', false ),
				array( 'updated_at', false ),
			),
			'aips_taxonomy' => array(
				array( 'created_at', false ),
				array( 'updated_at', false ),
			),
			'aips_post_embeddings' => array(
				array( 'indexed_at', false ),
			),
			'aips_ai_assistance' => array(
				array( 'created_at', false ),
			),
			'aips_internal_links' => array(
				array( 'created_at', false ),
				array( 'updated_at', false ),
			),
			'aips_affiliate_links' => array(
				array( 'created_at', false ),
				array( 'updated_at', false ),
			),
			'aips_link_index' => array(
				array( 'created_at', false ),
			),
			'aips_link_clicks' => array(
				array( 'day_start', false ),
			),
			'aips_redirects' => array(
				array( 'last_hit_at', false ),
				array( 'created_at', false ),
				array( 'updated_at', false ),
			),
			'aips_cache' => array(
				array( 'expires_at', false ),
				array( 'updated_at', false ),
			),
			'aips_telemetry' => array(
				array( 'inserted_at', false ),
			),
		);
	}

	/**
	 * Convert an existing MySQL DATETIME/TIMESTAMP column to BIGINT UNSIGNED Unix timestamp.
	 *
	 * Safe and idempotent: if the column is already BIGINT, or does not exist, returns
	 * false immediately (no-op).
	 *
	 * @param string    $table    Full table name including WordPress prefix.
	 * @param string    $col_name Name of the column to convert.
	 * @param wpdb|null $wpdb     Optional database instance.
	 * @return bool True if conversion occurred, false if no-op.
	 */
	public static function convert_datetime_column_to_bigint( $table, $col_name, $wpdb = null ) {
		if ( ! $wpdb ) {
			global $wpdb;
		}

		$col_info = $wpdb->get_row( $wpdb->prepare(
			"SHOW COLUMNS FROM `{$table}` WHERE Field = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$col_name
		) );

		if ( ! $col_info ) {
			return false; // Column doesn't exist; dbDelta will create it.
		}

		$type_lower = strtolower( $col_info->Type );

		if ( false !== strpos( $type_lower, 'bigint' ) ) {
			return false; // Already BIGINT — nothing to do.
		}

		if ( false === strpos( $type_lower, 'datetime' ) && false === strpos( $type_lower, 'timestamp' ) ) {
			return false; // Not a temporal column — skip.
		}

		$tmp_col = $col_name . '_ts_tmp';

		// 1. Add temporary BIGINT column.
		$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `{$tmp_col}` bigint(20) unsigned NOT NULL DEFAULT 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// 2. Populate from the existing DATETIME.
		$wpdb->query( "UPDATE `{$table}` SET `{$tmp_col}` = IFNULL(UNIX_TIMESTAMP(`{$col_name}`), 0)" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// 3. Drop the original DATETIME column.
		$wpdb->query( "ALTER TABLE `{$table}` DROP COLUMN `{$col_name}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// 4. Rename temp column to the original name.
		$wpdb->query( "ALTER TABLE `{$table}` CHANGE `{$tmp_col}` `{$col_name}` bigint(20) unsigned NOT NULL DEFAULT 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return true;
	}
}
