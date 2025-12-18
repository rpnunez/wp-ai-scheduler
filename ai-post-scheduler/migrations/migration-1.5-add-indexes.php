<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Migration 1.5: Add database indexes for better query performance
 */

global $wpdb;

// Add indexes to schedule table
$schedule_table = $wpdb->prefix . 'aips_schedule';
$wpdb->query("
    ALTER TABLE {$schedule_table}
    ADD INDEX idx_is_active_next_run (is_active, next_run),
    ADD INDEX idx_template_id (template_id)
");

// Add indexes to history table
$history_table = $wpdb->prefix . 'aips_history';
$wpdb->query("
    ALTER TABLE {$history_table}
    ADD INDEX idx_status (status),
    ADD INDEX idx_template_id (template_id),
    ADD INDEX idx_created_at (created_at)
");

// Add indexes to templates table
$templates_table = $wpdb->prefix . 'aips_templates';
$wpdb->query("
    ALTER TABLE {$templates_table}
    ADD INDEX idx_is_active (is_active)
");

// Add indexes to voices table
$voices_table = $wpdb->prefix . 'aips_voices';
$wpdb->query("
    ALTER TABLE {$voices_table}
    ADD INDEX idx_is_active (is_active)
");
