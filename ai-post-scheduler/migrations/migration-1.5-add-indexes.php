<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Migration 1.5: Add database indexes for better query performance
 */

global $wpdb;

/**
 * Helper function to check if an index exists
 */
function aips_index_exists($table_name, $index_name) {
    global $wpdb;
    $result = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
        WHERE table_schema = DATABASE() 
        AND table_name = %s 
        AND index_name = %s",
        $table_name,
        $index_name
    ));
    return $result > 0;
}

// Add indexes to schedule table
$schedule_table = $wpdb->prefix . 'aips_schedule';

// Validate table name matches expected pattern for security
if (preg_match('/^[a-zA-Z0-9_]+$/', $schedule_table)) {
    if (!aips_index_exists($schedule_table, 'idx_is_active_next_run')) {
        $wpdb->query("ALTER TABLE `{$schedule_table}` ADD INDEX idx_is_active_next_run (is_active, next_run)");
    }

    if (!aips_index_exists($schedule_table, 'idx_template_id')) {
        $wpdb->query("ALTER TABLE `{$schedule_table}` ADD INDEX idx_template_id (template_id)");
    }
}

// Add indexes to history table
$history_table = $wpdb->prefix . 'aips_history';

if (preg_match('/^[a-zA-Z0-9_]+$/', $history_table)) {
    if (!aips_index_exists($history_table, 'idx_status')) {
        $wpdb->query("ALTER TABLE `{$history_table}` ADD INDEX idx_status (status)");
    }

    if (!aips_index_exists($history_table, 'idx_template_id')) {
        $wpdb->query("ALTER TABLE `{$history_table}` ADD INDEX idx_template_id (template_id)");
    }

    if (!aips_index_exists($history_table, 'idx_created_at')) {
        $wpdb->query("ALTER TABLE `{$history_table}` ADD INDEX idx_created_at (created_at)");
    }
}

// Add indexes to templates table
$templates_table = $wpdb->prefix . 'aips_templates';

if (preg_match('/^[a-zA-Z0-9_]+$/', $templates_table)) {
    if (!aips_index_exists($templates_table, 'idx_is_active')) {
        $wpdb->query("ALTER TABLE `{$templates_table}` ADD INDEX idx_is_active (is_active)");
    }
}

// Add indexes to voices table
$voices_table = $wpdb->prefix . 'aips_voices';

if (preg_match('/^[a-zA-Z0-9_]+$/', $voices_table)) {
    if (!aips_index_exists($voices_table, 'idx_is_active')) {
        $wpdb->query("ALTER TABLE `{$voices_table}` ADD INDEX idx_is_active (is_active)");
    }
}
