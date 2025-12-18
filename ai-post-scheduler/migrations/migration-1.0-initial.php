<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$charset_collate = $wpdb->get_charset_collate();

$table_history = $wpdb->prefix . 'aips_history';
$table_templates = $wpdb->prefix . 'aips_templates';
$table_schedule = $wpdb->prefix . 'aips_schedule';
$table_voices = $wpdb->prefix . 'aips_voices';

$sql_history = "CREATE TABLE IF NOT EXISTS $table_history (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    post_id bigint(20) DEFAULT NULL,
    template_id bigint(20) DEFAULT NULL,
    status varchar(50) NOT NULL DEFAULT 'pending',
    prompt text,
    generated_title varchar(500),
    generated_content longtext,
    error_message text,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    completed_at datetime DEFAULT NULL,
    PRIMARY KEY (id),
    KEY post_id (post_id),
    KEY template_id (template_id),
    KEY status (status)
) $charset_collate;";

$sql_templates = "CREATE TABLE IF NOT EXISTS $table_templates (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    name varchar(255) NOT NULL,
    prompt_template text NOT NULL,
    title_prompt text,
    post_status varchar(50) DEFAULT 'draft',
    post_category bigint(20) DEFAULT NULL,
    post_tags text,
    post_author bigint(20) DEFAULT NULL,
    is_active tinyint(1) DEFAULT 1,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) $charset_collate;";

$sql_schedule = "CREATE TABLE IF NOT EXISTS $table_schedule (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    template_id bigint(20) NOT NULL,
    frequency varchar(50) NOT NULL DEFAULT 'daily',
    next_run datetime NOT NULL,
    last_run datetime DEFAULT NULL,
    is_active tinyint(1) DEFAULT 1,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY template_id (template_id),
    KEY next_run (next_run)
) $charset_collate;";

$sql_voices = "CREATE TABLE IF NOT EXISTS $table_voices (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    name varchar(255) NOT NULL,
    title_prompt text NOT NULL,
    content_instructions text NOT NULL,
    excerpt_instructions text,
    is_active tinyint(1) DEFAULT 1,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) $charset_collate;";

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
dbDelta($sql_history);
dbDelta($sql_templates);
dbDelta($sql_schedule);
dbDelta($sql_voices);
?>
