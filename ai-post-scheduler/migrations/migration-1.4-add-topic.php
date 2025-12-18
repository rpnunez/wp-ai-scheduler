<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'aips_schedule';

// Check if topic column exists
$row = $wpdb->get_results("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '{$table_name}' AND column_name = 'topic'");

if (empty($row)) {
    $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN topic TEXT DEFAULT NULL AFTER frequency");
}
