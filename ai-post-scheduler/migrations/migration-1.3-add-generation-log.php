<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$table_history = $wpdb->prefix . 'aips_history';

$columns_to_add = array(
    'generation_log' => "ALTER TABLE $table_history ADD COLUMN generation_log longtext AFTER generated_content;",
);

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

foreach ($columns_to_add as $column => $sql) {
    $column_exists = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = %s AND COLUMN_NAME = %s",
            $wpdb->prefix . 'aips_history',
            $column
        )
    );
    
    if (empty($column_exists)) {
        $wpdb->query($sql);
    }
}
?>
