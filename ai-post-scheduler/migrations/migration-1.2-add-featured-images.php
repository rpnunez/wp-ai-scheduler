<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$table_templates = $wpdb->prefix . 'aips_templates';

$columns_to_add = array(
    'image_prompt' => "ALTER TABLE $table_templates ADD COLUMN image_prompt text AFTER post_quantity;",
    'generate_featured_image' => "ALTER TABLE $table_templates ADD COLUMN generate_featured_image tinyint(1) DEFAULT 0 AFTER image_prompt;",
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
