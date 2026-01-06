<?php
if (!defined('ABSPATH')) {
	exit;
}

global $wpdb;

$table_schedule = $wpdb->prefix . 'aips_schedule';

// Add schedule_type to distinguish interval vs advanced rule based schedules
$column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_schedule LIKE 'schedule_type'");
if (empty($column_exists)) {
	$wpdb->query("ALTER TABLE $table_schedule ADD COLUMN schedule_type varchar(20) NOT NULL DEFAULT 'interval' AFTER frequency");
}

// Add rules column to store advanced scheduling conditions as JSON
$column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_schedule LIKE 'rules'");
if (empty($column_exists)) {
	$wpdb->query("ALTER TABLE $table_schedule ADD COLUMN rules longtext DEFAULT NULL AFTER schedule_type");
}
