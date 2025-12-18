<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$table_templates = $wpdb->prefix . 'aips_templates';

$columns_to_add = array(
    'voice_id' => "ALTER TABLE $table_templates ADD COLUMN voice_id bigint(20) DEFAULT NULL AFTER title_prompt;",
    'post_quantity' => "ALTER TABLE $table_templates ADD COLUMN post_quantity int DEFAULT 1 AFTER voice_id;",
);

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

foreach ($columns_to_add as $column => $sql) {
    $column_exists = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = %s AND COLUMN_NAME = %s",
            $wpdb->prefix . 'aips_templates',
            $column
        )
    );
    
    if (empty($column_exists)) {
        $wpdb->query($sql);
    }
}
?>
