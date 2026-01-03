<?php
/**
 * Migration: 1.7.0 Schema Updates
 *
 * Updates database schema for the Schedule Queue and advanced scheduling features.
 * 1. Creates `aips_schedule_queue` table.
 * 2. Adds `advanced_rules` and `schedule_type` to `aips_schedule`.
 * 3. Adds `review_required` to `aips_templates`.
 *
 * @package AI_Post_Scheduler
 * @since 1.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Run the migration to update schema.
 *
 * @param wpdb $wpdb WordPress database instance.
 * @return bool True on success, false on failure.
 */
function aips_migration_1_7_0_schema_updates($wpdb) {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $charset_collate = $wpdb->get_charset_collate();

    // 1. Create aips_schedule_queue table
    $queue_table = $wpdb->prefix . 'aips_schedule_queue';
    $sql_queue = "CREATE TABLE IF NOT EXISTS {$queue_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        schedule_id bigint(20) unsigned NOT NULL,
        topic text NOT NULL,
        status varchar(50) NOT NULL DEFAULT 'pending',
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY schedule_status_idx (schedule_id, status),
        KEY created_at_idx (created_at)
    ) {$charset_collate};";

    dbDelta($sql_queue);

    // 2. Update aips_schedule table
    $schedule_table = $wpdb->prefix . 'aips_schedule';

    // Check if columns exist before adding
    $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '{$schedule_table}' AND column_name = 'advanced_rules'");
    if (empty($row)) {
        $wpdb->query("ALTER TABLE {$schedule_table} ADD COLUMN advanced_rules text DEFAULT NULL AFTER frequency");
    }

    $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '{$schedule_table}' AND column_name = 'schedule_type'");
    if (empty($row)) {
        $wpdb->query("ALTER TABLE {$schedule_table} ADD COLUMN schedule_type varchar(50) DEFAULT 'simple' AFTER frequency");
    }

    // 3. Update aips_templates table
    $templates_table = $wpdb->prefix . 'aips_templates';

    $row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '{$templates_table}' AND column_name = 'review_required'");
    if (empty($row)) {
        $wpdb->query("ALTER TABLE {$templates_table} ADD COLUMN review_required tinyint(1) NOT NULL DEFAULT 0 AFTER is_active");
    }

    error_log("AIPS Migration 1.7.0: Schema updates completed");
    return true;
}

// Execute migration
global $wpdb;
aips_migration_1_7_0_schema_updates($wpdb);
