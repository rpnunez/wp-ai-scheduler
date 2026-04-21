<?php
/**
 * Uninstall handler for AI Post Scheduler.
 *
 * Fired when the plugin is deleted through the WordPress admin.
 * Drops all plugin database tables, removes all plugin options, and
 * cleans up post-meta, cron hooks, and transients.
 *
 * @package AI_Post_Scheduler
 * @since 3.0.0
 */

// Abort if not called by WordPress core uninstall machinery.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
exit;
}

global $wpdb;

// -----------------------------------------------------------------------
// 1. Drop all plugin tables
// -----------------------------------------------------------------------
$plugin_tables = array(
'aips_history',
'aips_history_log',
'aips_templates',
'aips_schedule',
'aips_voices',
'aips_article_structures',
'aips_prompt_sections',
'aips_trending_topics',
'aips_authors',
'aips_author_topics',
'aips_author_topic_logs',
'aips_topic_feedback',
'aips_notifications',
'aips_sources',
'aips_source_group_terms',
'aips_taxonomy',
'aips_post_embeddings',
'aips_internal_links',
'aips_cache',
'aips_telemetry',
);

foreach ( $plugin_tables as $table ) {
$full_name = $wpdb->prefix . $table;
$wpdb->query( "DROP TABLE IF EXISTS `{$full_name}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// -----------------------------------------------------------------------
// 2. Delete all plugin options (aips_* prefix)
// -----------------------------------------------------------------------
$wpdb->query(
"DELETE FROM {$wpdb->options} WHERE option_name LIKE 'aips\_%'"
);

// -----------------------------------------------------------------------
// 3. Delete plugin post-meta from generated posts
// -----------------------------------------------------------------------
$meta_keys = array(
'_aips_generated',
'_aips_template_id',
'_aips_history_id',
'_aips_topic_id',
'_aips_author_id',
'_aips_creation_method',
'_aips_generation_session_id',
'_aips_post_generation_total_time',
'_aips_original_post_status',
'aips_post_generation_incomplete',
'aips_post_generation_had_partial',
'aips_post_generation_component_statuses',
);

foreach ( $meta_keys as $meta_key ) {
delete_metadata( 'post', 0, $meta_key, '', true );
}

// -----------------------------------------------------------------------
// 4. Clear all plugin cron hooks
//    Use wp_unschedule_hook() to remove events with args (e.g. embeddings,
//    indexing batches).  wp_clear_scheduled_hook() only removes events
//    matching the exact args supplied (default: none).
// -----------------------------------------------------------------------
$cron_hooks = array(
'aips_generate_scheduled_posts',
'aips_generate_author_topics',
'aips_generate_author_posts',
'aips_scheduled_research',
'aips_notification_rollups',
'aips_cleanup_export_files',
'aips_send_review_notifications',
'aips_process_author_embeddings',
'aips_index_posts_batch',
);

foreach ( $cron_hooks as $hook ) {
wp_unschedule_hook( $hook );
}

// Clear Action Scheduler jobs when available (embeddings + internal links).
if ( function_exists( 'as_unschedule_all_actions' ) ) {
as_unschedule_all_actions( 'aips_process_author_embeddings', array(), 'aips-embeddings' );
as_unschedule_all_actions( 'aips_index_posts_batch', array(), 'aips-internal-links' );
}

// -----------------------------------------------------------------------
// 5. Delete plugin transients
// -----------------------------------------------------------------------
$wpdb->query(
"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_aips\_%' OR option_name LIKE '_transient_timeout_aips\_%'"
);

// -----------------------------------------------------------------------
// 6. Remove the Source Group taxonomy terms
// -----------------------------------------------------------------------
$terms = get_terms( array(
'taxonomy'   => 'aips_source_group',
'hide_empty' => false,
'fields'     => 'ids',
) );

if ( ! is_wp_error( $terms ) && is_array( $terms ) ) {
foreach ( $terms as $term_id ) {
wp_delete_term( $term_id, 'aips_source_group' );
}
}

// -----------------------------------------------------------------------
// 7. Clean up export and log directories
// -----------------------------------------------------------------------
$upload_dir = wp_upload_dir();

if ( ! empty( $upload_dir['basedir'] ) ) {
$dirs_to_remove = array(
$upload_dir['basedir'] . '/aips-exports',
$upload_dir['basedir'] . '/aips-logs',
);

foreach ( $dirs_to_remove as $dir ) {
if ( is_dir( $dir ) ) {
$files = glob( trailingslashit( $dir ) . '*' );
if ( is_array( $files ) ) {
foreach ( $files as $file ) {
if ( is_file( $file ) ) {
wp_delete_file( $file );
}
}
}
rmdir( $dir );
}
}
}
