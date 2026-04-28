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

        if (version_compare($from_version, '2.4.0', '<')) {
            $this->migrate_to_2_4_0();
        }

        if (version_compare($from_version, '2.4.1', '<')) {
            $this->migrate_to_2_4_1();
        }

        if (version_compare($from_version, '2.5.0', '<')) {
            $this->migrate_to_2_5_0();
        }

        if (version_compare($from_version, '2.5.1', '<')) {
            $this->migrate_to_2_5_1();
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

    /**
     * Migration for version 2.4.0.
     *
     * Adds three new columns to aips_sources for scheduled-fetch support:
     *   - fetch_interval  varchar(50)  — frequency key (e.g. 'daily')
     *   - last_fetched_at datetime     — timestamp of last successful fetch
     *   - next_fetch_at   datetime     — pre-computed next fetch time
     *
     * Each ADD is guarded with a SHOW COLUMNS check so the ALTER is a no-op
     * on fresh installs (where dbDelta has already created the columns).
     *
     * The new aips_sources_data table is created via dbDelta in install_tables().
     */
    private function migrate_to_2_4_0() {
        global $wpdb;
        $table = $wpdb->prefix . 'aips_sources';

        // Only proceed if the sources table already exists.
        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( $table_exists !== $table ) {
            return;
        }

        $columns = array(
            'fetch_interval'  => 'ADD COLUMN fetch_interval varchar(50) DEFAULT NULL AFTER is_active',
            'last_fetched_at' => 'ADD COLUMN last_fetched_at datetime DEFAULT NULL AFTER fetch_interval',
            'next_fetch_at'   => 'ADD COLUMN next_fetch_at datetime DEFAULT NULL AFTER last_fetched_at',
        );

        foreach ( $columns as $col_name => $add_clause ) {
            $exists = $wpdb->get_row( $wpdb->prepare(
                "SHOW COLUMNS FROM `{$table}` WHERE Field = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $col_name
            ) );

            if ( ! $exists ) {
                $wpdb->query( "ALTER TABLE `{$table}` {$add_clause}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
            }
        }

        // Rename word_count → char_count on aips_sources_data if it exists with the old name.
        $table_sources_data = $wpdb->prefix . 'aips_sources_data';
        $sources_data_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_sources_data ) );
        if ( $sources_data_exists === $table_sources_data ) {
            $old_col = $wpdb->get_row( $wpdb->prepare(
                "SHOW COLUMNS FROM `{$table_sources_data}` WHERE Field = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                'word_count'
            ) );
            if ( $old_col ) {
                $wpdb->query( "ALTER TABLE `{$table_sources_data}` CHANGE COLUMN `word_count` `char_count` int NOT NULL DEFAULT 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            }
        }
    }

    /**
     * Migration for version 2.4.1.
     *
     * Converts aips_sources_data from a single-snapshot-per-source model to a
     * growing archive model:
     *
     *  - Adds content_hash varchar(64) — SHA-256 of extracted_text for deduplication.
     *  - Adds num_used int              — prompt usage counter for round-robin selection.
     *  - Drops the UNIQUE KEY source_id (source_id) that prevented multiple rows per source.
     *  - Adds UNIQUE KEY source_content_hash (source_id, content_hash) for deduplication.
     *  - Adds KEY num_used (num_used) for efficient ordering.
     *
     * The ALTER statements are each guarded with SHOW COLUMNS / SHOW INDEX checks
     * so they are safe to run on fresh installs where dbDelta has already applied
     * the new schema.
     */
    private function migrate_to_2_4_1() {
        global $wpdb;
        $table = $wpdb->prefix . 'aips_sources_data';

        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( $table_exists !== $table ) {
            return;
        }

        // Add content_hash column.
        $has_hash = $wpdb->get_row( $wpdb->prepare(
            "SHOW COLUMNS FROM `{$table}` WHERE Field = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            'content_hash'
        ) );
        if ( ! $has_hash ) {
            $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN content_hash varchar(64) DEFAULT NULL AFTER char_count" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }

        // Add num_used column.
        $has_num_used = $wpdb->get_row( $wpdb->prepare(
            "SHOW COLUMNS FROM `{$table}` WHERE Field = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            'num_used'
        ) );
        if ( ! $has_num_used ) {
            $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN num_used int NOT NULL DEFAULT 0 AFTER content_hash" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }

        // Backfill content_hash for any existing success rows that have extracted_text.
        $wpdb->query(
            "UPDATE `{$table}` SET content_hash = SHA2(extracted_text, 256) WHERE content_hash IS NULL AND extracted_text IS NOT NULL AND extracted_text != '' AND fetch_status = 'success'" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        );

        // Drop the old single-row-per-source unique key if it still exists.
        $has_old_unique = $wpdb->get_row(
            "SHOW INDEX FROM `{$table}` WHERE Key_name = 'source_id' AND Non_unique = 0" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        );
        if ( $has_old_unique ) {
            $wpdb->query( "ALTER TABLE `{$table}` DROP INDEX source_id" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }

        // Add non-unique source_id key if not present (replacing the dropped unique one).
        $has_source_key = $wpdb->get_row(
            "SHOW INDEX FROM `{$table}` WHERE Key_name = 'source_id' AND Non_unique = 1" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        );
        if ( ! $has_source_key ) {
            $wpdb->query( "ALTER TABLE `{$table}` ADD KEY source_id (source_id)" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }

        // Add composite deduplication unique key if not present.
        $has_dedup_key = $wpdb->get_row(
            "SHOW INDEX FROM `{$table}` WHERE Key_name = 'source_content_hash'" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        );
        if ( ! $has_dedup_key ) {
            $wpdb->query( "ALTER TABLE `{$table}` ADD UNIQUE KEY source_content_hash (source_id, content_hash)" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }

        // Add num_used index for efficient ordering if not present.
        $has_num_used_key = $wpdb->get_row(
            "SHOW INDEX FROM `{$table}` WHERE Key_name = 'num_used'" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        );
        if ( ! $has_num_used_key ) {
            $wpdb->query( "ALTER TABLE `{$table}` ADD KEY num_used (num_used)" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }
    }

    /**
     * Migration for version 2.5.1.
     *
     * Adds the aips_ai_assistance table for storing AI field suggestion history.
     * The actual table creation is handled by dbDelta in install_tables().
     * This method exists for logging consistency.
     */
    private function migrate_to_2_5_1() {
        global $wpdb;
        
        $this->logger->log( 'Running migration to 2.5.1: AI Assistance table will be created by dbDelta.', 'info' );
        
        // Extend varchar columns for AI-generated suggestions
        // voice_tone and writing_style were varchar(100) but AI suggestions can be longer
        $table = $wpdb->prefix . 'aips_authors';
        $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        
        if ( $table_exists === $table ) {
            // Check if columns need to be extended
            $columns_to_check = array(
                'voice_tone'     => 500,
                'writing_style'  => 500,
            );
            
            foreach ( $columns_to_check as $column_name => $new_length ) {
                $column_info = $wpdb->get_row( $wpdb->prepare(
                    "SHOW COLUMNS FROM `{$table}` WHERE Field = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $column_name
                ) );
                
                if ( $column_info && strpos( $column_info->Type, "varchar({$new_length})" ) === false ) {
                    // Column exists but is not the correct length, alter it
                    $wpdb->query( "ALTER TABLE `{$table}` MODIFY COLUMN `{$column_name}` varchar({$new_length}) DEFAULT NULL" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $this->logger->log( "Extended {$column_name} to varchar({$new_length}) in {$table}", 'info' );
                }
            }
        }
    }


    
    /**
     * Migration for version 2.5.0.
     *
     * Converts every DATETIME column in the plugin schema to BIGINT UNSIGNED
     * storing Unix timestamps.  This standardises all date/time handling on a
     * single UTC-based integer representation so the new AIPS_DateTime class is
     * the single entry point for all date/time operations.
     *
     * Strategy per column:
     *   1. ADD a temporary BIGINT column after the original.
     *   2. UPDATE the temp column with UNIX_TIMESTAMP(original) for non-NULL rows.
     *   3. DROP the original DATETIME column.
     *   4. CHANGE the temp column to the original name.
     *
     * Each column migration is guarded with a SHOW COLUMNS check so the ALTER
     * is a no-op on fresh installs (where dbDelta has already created BIGINT).
     */
    private function migrate_to_2_5_0() {
        global $wpdb;

        // Map: table slug => array of column definitions.
        // Each column: [ name, nullable (bool) ]
        // "nullable" only affects whether we use DEFAULT 0 or DEFAULT NULL
        // in the temp column — but since 2.5.0 schema uses DEFAULT 0 for all
        // we unify on NOT NULL DEFAULT 0.
        $table_columns = AIPS_DB_Manager::get_datetime_column_map();

        foreach ( $table_columns as $table_slug => $columns ) {
            $table = $wpdb->prefix . $table_slug;

            // Skip tables that don't exist yet (fresh install — dbDelta handles it).
            $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
            if ( $table_exists !== $table ) {
                continue;
            }

            foreach ( $columns as $col_def ) {
                $col_name = $col_def[0];
                $this->convert_datetime_to_bigint( $table, $col_name );
            }
        }
    }

    /**
     * Convert a single DATETIME column to BIGINT UNSIGNED on an existing table.
     *
     * If the column is already BIGINT (e.g. fresh install), this is a no-op.
     *
     * @param string $table    Full table name (with prefix).
     * @param string $col_name Column name to convert.
     */
    private function convert_datetime_to_bigint( $table, $col_name ) {
        global $wpdb;

        // Check current column type.
        $col_info = $wpdb->get_row( $wpdb->prepare(
            "SHOW COLUMNS FROM `{$table}` WHERE Field = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $col_name
        ) );

        if ( ! $col_info ) {
            return; // Column doesn't exist; dbDelta will create it.
        }

        $type_lower = strtolower( $col_info->Type );

        // Already BIGINT — nothing to do.
        if ( strpos( $type_lower, 'bigint' ) !== false || strpos( $type_lower, 'int' ) !== false ) {
            // Verify it really is bigint (not tinyint, etc.).
            if ( strpos( $type_lower, 'bigint' ) !== false ) {
                return;
            }
        }

        // Only convert DATETIME/TIMESTAMP columns.
        if ( strpos( $type_lower, 'datetime' ) === false && strpos( $type_lower, 'timestamp' ) === false ) {
            return;
        }

        $tmp_col = $col_name . '_ts_tmp';

        // 1. Add temporary BIGINT column.
        $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `{$tmp_col}` bigint(20) unsigned NOT NULL DEFAULT 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        // 2. Populate from the existing DATETIME.
        $wpdb->query( "UPDATE `{$table}` SET `{$tmp_col}` = IFNULL(UNIX_TIMESTAMP(`{$col_name}`), 0)" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        // 3. Drop the original DATETIME column.
        $wpdb->query( "ALTER TABLE `{$table}` DROP COLUMN `{$col_name}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        // 4. Rename temp column to the original name.
        $wpdb->query( "ALTER TABLE `{$table}` CHANGE `{$tmp_col}` `{$col_name}` bigint(20) unsigned NOT NULL DEFAULT 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }
}
?>
