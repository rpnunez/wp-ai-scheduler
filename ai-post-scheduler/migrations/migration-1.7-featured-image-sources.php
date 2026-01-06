<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$table_templates = $wpdb->prefix . 'aips_templates';

$columns_to_add = array(
    'featured_image_source' => "ALTER TABLE $table_templates ADD COLUMN featured_image_source varchar(50) DEFAULT 'ai_prompt' AFTER generate_featured_image;",
    'featured_image_unsplash_keywords' => "ALTER TABLE $table_templates ADD COLUMN featured_image_unsplash_keywords text AFTER featured_image_source;",
    'featured_image_media_ids' => "ALTER TABLE $table_templates ADD COLUMN featured_image_media_ids text AFTER featured_image_unsplash_keywords;",
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
