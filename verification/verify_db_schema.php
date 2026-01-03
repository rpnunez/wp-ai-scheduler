<?php
/**
 * DB Schema Verification Script
 *
 * Checks if the expected tables and columns exist in the database.
 * Uses the WordPress environment.
 */

require_once 'tests/bootstrap.php';

function verify_schema() {
    global $wpdb;

    // Simulate migration
    require_once 'ai-post-scheduler/migrations/migration-1.7.0-schema-updates.php';

    echo "Verifying Schema Updates...\n";

    // Check tables
    $expected_tables = [
        'aips_schedule_queue',
        'aips_schedule',
        'aips_templates'
    ];

    $all_passed = true;

    foreach ($expected_tables as $table) {
        $full_table = $wpdb->prefix . $table;
        if ($wpdb->get_var("SHOW TABLES LIKE '$full_table'") !== $full_table) {
            echo "[FAIL] Table $full_table does not exist.\n";
            $all_passed = false;
        } else {
            echo "[PASS] Table $full_table exists.\n";
        }
    }

    // Check columns in aips_schedule_queue
    $queue_columns = ['schedule_id', 'topic', 'status'];
    foreach ($queue_columns as $col) {
        $full_table = $wpdb->prefix . 'aips_schedule_queue';
        $result = $wpdb->get_results("SHOW COLUMNS FROM $full_table LIKE '$col'");
        if (empty($result)) {
            echo "[FAIL] Column '$col' missing in $full_table.\n";
            $all_passed = false;
        } else {
            echo "[PASS] Column '$col' exists in $full_table.\n";
        }
    }

    // Check columns in aips_schedule
    $schedule_columns = ['advanced_rules', 'schedule_type'];
    foreach ($schedule_columns as $col) {
        $full_table = $wpdb->prefix . 'aips_schedule';
        $result = $wpdb->get_results("SHOW COLUMNS FROM $full_table LIKE '$col'");
        if (empty($result)) {
            echo "[FAIL] Column '$col' missing in $full_table.\n";
            $all_passed = false;
        } else {
            echo "[PASS] Column '$col' exists in $full_table.\n";
        }
    }

    // Check columns in aips_templates
    $template_columns = ['review_required'];
    foreach ($template_columns as $col) {
        $full_table = $wpdb->prefix . 'aips_templates';
        $result = $wpdb->get_results("SHOW COLUMNS FROM $full_table LIKE '$col'");
        if (empty($result)) {
            echo "[FAIL] Column '$col' missing in $full_table.\n";
            $all_passed = false;
        } else {
            echo "[PASS] Column '$col' exists in $full_table.\n";
        }
    }

    if ($all_passed) {
        echo "\nSUCCESS: Database schema matches 1.7.0 requirements.\n";
    } else {
        echo "\nFAILURE: Schema mismatch.\n";
        exit(1);
    }
}

verify_schema();
