<?php
if (!defined('ABSPATH')) {
return;
}

/**
 * AIPS_System_Diagnostics_Environment_Provider
 */
class AIPS_System_Diagnostics_Environment_Provider implements AIPS_System_Diagnostic_Provider_Interface {

/**
 * @return array<string, mixed>
 */
public function get_diagnostics(): array {
return array(
'environment' => $this->check_environment(),
'plugin'      => $this->check_plugin(),
'database'    => $this->check_database(),
'filesystem'  => $this->check_filesystem(),
);
}

/**
 * Check PHP, WordPress, MySQL, and server environment.
 *
 * @return array<string, array<string, mixed>>
 */
private function check_environment() {
global $wp_version, $wpdb;
return array(
'php_version' => array(
'label'  => __( 'PHP Version', 'ai-post-scheduler' ),
'value'  => phpversion(),
'status' => version_compare( phpversion(), '8.2', '>=' ) ? 'ok' : 'warning',
),
'wp_version' => array(
'label'  => __( 'WordPress Version', 'ai-post-scheduler' ),
'value'  => $wp_version,
'status' => 'ok',
),
'mysql_version' => array(
'label'  => __( 'MySQL Version', 'ai-post-scheduler' ),
'value'  => $wpdb->db_version(),
'status' => 'ok',
),
'server_software' => array(
'label'  => __( 'Web Server', 'ai-post-scheduler' ),
'value'  => isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown',
'status' => 'info',
),
);
}

/**
 * Check plugin version, database version, and AI Engine presence.
 *
 * @return array<string, array<string, mixed>>
 */
private function check_plugin() {
$ai_engine_active  = class_exists( 'Meow_MWAI_Core' );
$db_version_raw    = AIPS_Config::get_instance()->get_option( 'aips_db_version' );
$db_version        = is_scalar( $db_version_raw ) ? trim( (string) $db_version_raw ) : 'Unknown';
$db_version_is_valid = (bool) preg_match( '/^\d+(?:\.\d+)*(?:[-+~._][0-9A-Za-z.-]+)?$/', $db_version );
$db_version_matches  = $db_version_is_valid && version_compare( $db_version, AIPS_VERSION, '==' );

$db_version_details = array();
if ( ! $db_version_matches ) {
$db_version_details[] = sprintf(
/* translators: %s: stored database version */
__( 'Stored database version: %s', 'ai-post-scheduler' ),
empty( $db_version ) ? __( 'Unknown', 'ai-post-scheduler' ) : $db_version
);
$db_version_details[] = sprintf(
/* translators: %s: expected plugin database version */
__( 'Expected database version for this plugin build: %s', 'ai-post-scheduler' ),
AIPS_VERSION
);
$db_version_details[] = __( 'This usually means the database schema is from a different plugin build or an upgrade did not complete.', 'ai-post-scheduler' );
$db_version_details[] = __( 'Try "Repair DB Tables" first. If this persists, run "Reinstall DB Tables" with backup enabled.', 'ai-post-scheduler' );
}

return array(
'version' => array(
'label'  => __( 'Plugin Version', 'ai-post-scheduler' ),
'value'  => AIPS_VERSION,
'status' => 'ok',
),
'db_version' => array(
'label'   => __( 'Database Version', 'ai-post-scheduler' ),
'value'   => empty( $db_version ) ? 'Unknown' : $db_version,
'status'  => $db_version_matches ? 'ok' : 'warning',
'details' => $db_version_details,
),
'ai_engine' => array(
'label'  => __( 'AI Engine Plugin', 'ai-post-scheduler' ),
'value'  => $ai_engine_active ? __( 'Active', 'ai-post-scheduler' ) : __( 'Missing', 'ai-post-scheduler' ),
'status' => $ai_engine_active ? 'ok' : 'error',
),
);
}

/**
 * Check all plugin database tables for existence and required columns.
 *
 * @return array<string, array<string, mixed>>
 */
private function check_database() {
global $wpdb;

$tables  = AIPS_DB_Manager::get_expected_columns();
$results = array();

foreach ( $tables as $table_name => $columns ) {
$full_table_name = $wpdb->prefix . $table_name;
$table_exists    = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $full_table_name ) ) === $full_table_name;

if ( ! $table_exists ) {
$results[ $table_name ] = array(
'label'  => sprintf( __( 'Table: %s', 'ai-post-scheduler' ), $table_name ),
'value'  => __( 'Missing', 'ai-post-scheduler' ),
'status' => 'error',
);
continue;
}

$missing_columns = array();
$db_columns      = $wpdb->get_results( "SHOW COLUMNS FROM $full_table_name", ARRAY_A );
$db_column_names = array_column( $db_columns, 'Field' );

foreach ( $columns as $col ) {
if ( ! in_array( $col, $db_column_names ) ) {
$missing_columns[] = $col;
}
}

if ( ! empty( $missing_columns ) ) {
$results[ $table_name ] = array(
'label'  => sprintf( __( 'Table: %s', 'ai-post-scheduler' ), $table_name ),
'value'  => sprintf( __( 'Missing columns: %s', 'ai-post-scheduler' ), implode( ', ', $missing_columns ) ),
'status' => 'error',
);
} else {
$results[ $table_name ] = array(
'label'  => sprintf( __( 'Table: %s', 'ai-post-scheduler' ), $table_name ),
'value'  => __( 'OK', 'ai-post-scheduler' ),
'status' => 'ok',
);
}
}

return $results;
}

/**
 * Check the plugin log directory exists and is writable.
 *
 * @return array<string, array<string, mixed>>
 */
private function check_filesystem() {
$upload_dir = wp_upload_dir();
$log_dir    = $upload_dir['basedir'] . '/aips-logs';

$exists   = file_exists( $log_dir );
$writable = wp_is_writable( $log_dir );

return array(
'log_dir' => array(
'label'  => __( 'Log Directory', 'ai-post-scheduler' ),
'value'  => $exists ? ( $writable ? __( 'Writable', 'ai-post-scheduler' ) : __( 'Not Writable', 'ai-post-scheduler' ) ) : __( 'Missing', 'ai-post-scheduler' ),
'status' => ( $exists && $writable ) ? 'ok' : 'error',
),
);
}
}
