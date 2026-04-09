<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Upgrades {
    
    private $logger;
    
    public function __construct() {
        $this->logger = new AIPS_Logger();
    }
    
    public static function check_and_run() {
        $current_version = AIPS_Config::get_instance()->get_option('aips_db_version');
        
        if (version_compare($current_version, AIPS_VERSION, '<')) {
            $instance = new self();
            $instance->run_upgrade($current_version);
        }
    }
    
    private function run_upgrade($from_version) {
        // Version-specific migrations — run before dbDelta so schema is consistent.
        if (version_compare($from_version, '2.3.1', '<')) {
            $this->migrate_to_2_3_1();
        }

        // Use dbDelta to update schema - it handles adding new tables and columns automatically
        // This is the WordPress standard approach for database schema updates
        $result = AIPS_DB_Manager::install_tables();

        if (is_wp_error($result)) {
            $notifications = class_exists('AIPS_Notifications') ? new AIPS_Notifications() : null;

            if ($notifications instanceof AIPS_Notifications) {
                $notifications->system_error(array(
                    'title'         => __('Database upgrade failed', 'ai-post-scheduler'),
                    'error_code'    => $result->get_error_code(),
                    'error_message' => $result->get_error_message(),
                    'from_version'  => $from_version,
                    'url'           => admin_url('admin.php?page=aips-status'),
                    'dedupe_key'    => 'db_upgrade_failed_' . sanitize_key((string) $from_version),
                    'dedupe_window' => 1800,
                ));
            }

            return $result;
        }

        update_option('aips_db_version', AIPS_VERSION);
        $this->logger->log('Database upgraded from version ' . $from_version . ' to ' . AIPS_VERSION, 'info');

        return true;
    }

    /**
     * Migration for version 2.3.1.
     *
     * Adds composite indexes to aips_notifications that were missing in older schema versions:
     *   - is_read_created_at (is_read, created_at) — speeds up get_unread() / count_unread()
     *   - dedupe_key_created_at (dedupe_key, created_at) — speeds up was_recently_sent()
     *
     * Both checks are guarded with SHOW INDEX so the ALTER is a no-op on fresh installs
     * where dbDelta has already created the indexes from get_schema().
     */
    private function migrate_to_2_3_1() {
        global $wpdb;
        $table = $wpdb->prefix . 'aips_notifications';

        // Only proceed if the notifications table exists (avoids errors on broken installs).
        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( $table_exists !== $table ) {
            return;
        }

        $indexes = array(
            'is_read_created_at'    => 'ADD KEY is_read_created_at (is_read, created_at)',
            'dedupe_key_created_at' => 'ADD KEY dedupe_key_created_at (dedupe_key, created_at)',
        );

        foreach ( $indexes as $key_name => $add_clause ) {
            $exists = $wpdb->get_row( $wpdb->prepare(
                "SHOW INDEX FROM `{$table}` WHERE Key_name = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $key_name
            ) );

            if ( ! $exists ) {
                $wpdb->query( "ALTER TABLE `{$table}` {$add_clause}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
            }
        }
    }
}
?>
