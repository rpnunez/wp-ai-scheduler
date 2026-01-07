<?php
if (!defined('ABSPATH')) {
	exit;
}

global $wpdb;
$charset_collate = $wpdb->get_charset_collate();

// Add status column to schedule table
$table_schedule = $wpdb->prefix . 'aips_schedule';
$column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_schedule LIKE 'status'");
if (empty($column_exists)) {
	// Add status column with default 'active'
	$wpdb->query("ALTER TABLE $table_schedule ADD COLUMN status varchar(20) DEFAULT 'active' AFTER is_active");
	$wpdb->query("ALTER TABLE $table_schedule ADD KEY status (status)");
	
	// Update existing records: set status based on is_active field
	$wpdb->query("UPDATE $table_schedule SET status = 'active' WHERE is_active = 1");
	$wpdb->query("UPDATE $table_schedule SET status = 'inactive' WHERE is_active = 0");
}

// Create activity table for tracking events
$table_activity = $wpdb->prefix . 'aips_activity';
$sql_activity = "CREATE TABLE $table_activity (
	id bigint(20) NOT NULL AUTO_INCREMENT,
	event_type varchar(50) NOT NULL,
	event_status varchar(20) NOT NULL,
	schedule_id bigint(20) DEFAULT NULL,
	post_id bigint(20) DEFAULT NULL,
	template_id bigint(20) DEFAULT NULL,
	message text,
	metadata longtext,
	created_at datetime DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY  (id),
	KEY event_type (event_type),
	KEY event_status (event_status),
	KEY schedule_id (schedule_id),
	KEY post_id (post_id),
	KEY created_at (created_at)
) $charset_collate;";

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
dbDelta($sql_activity);
