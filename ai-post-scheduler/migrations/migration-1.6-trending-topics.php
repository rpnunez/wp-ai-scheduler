<?php
/**
 * Migration: Add Trending Topics Table
 *
 * Creates the database table for storing automated research data
 * on trending topics discovered by the AI Research Service.
 *
 * @package AI_Post_Scheduler
 * @since 1.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Run the migration to create trending topics table.
 *
 * @param wpdb $wpdb WordPress database instance.
 * @return bool True on success, false on failure.
 */
function aips_migration_1_6_trending_topics($wpdb) {
    $table_name = $wpdb->prefix . 'aips_trending_topics';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        niche varchar(255) NOT NULL,
        topic varchar(500) NOT NULL,
        score int(11) NOT NULL DEFAULT 50,
        reason text DEFAULT NULL,
        keywords text DEFAULT NULL,
        researched_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY niche_idx (niche),
        KEY score_idx (score),
        KEY researched_at_idx (researched_at)
    ) {$charset_collate};";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Verify table was created
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
    
    if (!$table_exists) {
        error_log("AIPS Migration 1.6: Failed to create {$table_name} table");
        return false;
    }
    
    error_log("AIPS Migration 1.6: Successfully created {$table_name} table");
    return true;
}

// Execute migration
global $wpdb;
aips_migration_1_6_trending_topics($wpdb);
