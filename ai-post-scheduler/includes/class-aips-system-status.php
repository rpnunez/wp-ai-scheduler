<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_System_Status {

    public function render_page() {
        $system_info = $this->get_system_info();
        $data_management = $this->get_data_management();

        if ( $data_management ) {
            $export_formats = $data_management->get_export_formats();
            $import_formats = $data_management->get_import_formats();
        } else {
            $export_formats = array();
            $import_formats = array();
        }

        include AIPS_PLUGIN_DIR . 'templates/admin/system-status.php';
    }

    /**
     * Get the AIPS_Data_Management instance without causing duplicate hook registrations.
     *
     * @return AIPS_Data_Management|null
     */
    private function get_data_management() {
        if ( ! class_exists( 'AIPS_Data_Management' ) ) {
            return null;
        }

        // Prefer a shared/global instance if the plugin exposes one.
        global $aips_data_management;
        if ( isset( $aips_data_management ) && $aips_data_management instanceof AIPS_Data_Management ) {
            return $aips_data_management;
        }

        // Fallback to a singleton accessor if available.
        if ( method_exists( 'AIPS_Data_Management', 'get_instance' ) ) {
            return AIPS_Data_Management::get_instance();
        }

        // As a last resort, create a new instance.
        return new AIPS_Data_Management();
    }
    public function get_system_info() {
        return array(
            'environment' => $this->check_environment(),
            'plugin' => $this->check_plugin(),
            'database' => $this->check_database(),
            'filesystem' => $this->check_filesystem(),
            'cron' => $this->check_cron(),
            'notifications' => $this->check_notifications(),
            'logs' => $this->check_logs(),
        );
    }

    /**
     * Check notifications configuration and runtime diagnostics.
     *
     * @return array<string, array<string, mixed>>
     */
    private function check_notifications() {
        $repository = class_exists('AIPS_Notifications_Repository') ? new AIPS_Notifications_Repository() : null;
        $recipient_list = (string) get_option('aips_review_notifications_email', get_option('admin_email'));
        $recipient_count = 0;
        if (!empty($recipient_list)) {
            $parts = preg_split('/\s*,\s*/', $recipient_list);
            $parts = is_array($parts) ? array_filter($parts) : array();
            $recipient_count = count($parts);
        }

        $daily_marker = (string) get_option('aips_notif_daily_digest_last_sent', '');
        $weekly_marker = (string) get_option('aips_notif_weekly_summary_last_sent', '');
        $monthly_marker = (string) get_option('aips_notif_monthly_report_last_sent', '');

        $new_cron = wp_next_scheduled('aips_notification_rollups');
        $legacy_cron = wp_next_scheduled('aips_send_review_notifications');

        $counts_24h = array();
        $unread_count = 0;
        if ($repository instanceof AIPS_Notifications_Repository) {
            $counts_24h = $repository->get_type_counts_for_window(DAY_IN_SECONDS);
            $unread_count = (int) $repository->count_unread();
        }

        $top_types = array();
        if (!empty($counts_24h)) {
            arsort($counts_24h);
            $counts_24h = array_slice($counts_24h, 0, 8, true);
            foreach ($counts_24h as $type => $count) {
                $top_types[] = sprintf('%s: %d', $type, (int) $count);
            }
        }

        return array(
            'recipients' => array(
                'label'  => __('Notification Recipients', 'ai-post-scheduler'),
                'value'  => $recipient_count > 0 ? sprintf(_n('%d recipient configured', '%d recipients configured', $recipient_count, 'ai-post-scheduler'), $recipient_count) : __('No recipients configured', 'ai-post-scheduler'),
                'status' => $recipient_count > 0 ? 'ok' : 'warning',
                'details'=> $recipient_count > 0 ? array($recipient_list) : array(),
            ),
            'rollup_markers' => array(
                'label'  => __('Rollup Send Markers', 'ai-post-scheduler'),
                'value'  => __('Available', 'ai-post-scheduler'),
                'status' => 'info',
                'details'=> array(
                    sprintf(__('Daily marker: %s', 'ai-post-scheduler'), $daily_marker ? $daily_marker : __('not set', 'ai-post-scheduler')),
                    sprintf(__('Weekly marker: %s', 'ai-post-scheduler'), $weekly_marker ? $weekly_marker : __('not set', 'ai-post-scheduler')),
                    sprintf(__('Monthly marker: %s', 'ai-post-scheduler'), $monthly_marker ? $monthly_marker : __('not set', 'ai-post-scheduler')),
                ),
            ),
            'rollup_cron' => array(
                'label'  => __('Rollup Cron Hook', 'ai-post-scheduler'),
                'value'  => $new_cron ? date('Y-m-d H:i:s', $new_cron) : __('Not Scheduled', 'ai-post-scheduler'),
                'status' => $new_cron ? 'ok' : 'warning',
            ),
            'legacy_rollup_cron' => array(
                'label'  => __('Legacy Rollup Hook (Compatibility)', 'ai-post-scheduler'),
                'value'  => $legacy_cron ? date('Y-m-d H:i:s', $legacy_cron) : __('Not Scheduled', 'ai-post-scheduler'),
                'status' => $legacy_cron ? 'info' : 'ok',
            ),
            'unread_notifications' => array(
                'label'  => __('Unread DB Notifications', 'ai-post-scheduler'),
                'value'  => (string) $unread_count,
                'status' => $unread_count > 0 ? 'info' : 'ok',
            ),
            'recent_notification_types' => array(
                'label'  => __('Last 24h Notification Volume', 'ai-post-scheduler'),
                'value'  => empty($top_types) ? __('No notifications in the last 24 hours', 'ai-post-scheduler') : sprintf(__('%d type(s) active', 'ai-post-scheduler'), count($top_types)),
                'status' => empty($top_types) ? 'info' : 'ok',
                'details'=> $top_types,
            ),
        );
    }

    private function check_environment() {
        global $wp_version, $wpdb;
        return array(
            'php_version' => array(
                'label' => 'PHP Version',
                'value' => phpversion(),
                'status' => version_compare(phpversion(), '8.2', '>=') ? 'ok' : 'warning',
            ),
            'wp_version' => array(
                'label' => 'WordPress Version',
                'value' => $wp_version,
                'status' => 'ok',
            ),
            'mysql_version' => array(
                'label' => 'MySQL Version',
                'value' => $wpdb->db_version(),
                'status' => 'ok',
            ),
            'server_software' => array(
                'label' => 'Web Server',
                'value' => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown',
                'status' => 'info',
            ),
        );
    }

    private function check_plugin() {
        $ai_engine_active = class_exists('Meow_MWAI_Core');
        $db_version_raw = get_option('aips_db_version', 'Unknown');
        $db_version = is_scalar($db_version_raw) ? trim((string) $db_version_raw) : 'Unknown';
        $db_version_is_valid = (bool) preg_match('/^\d+(?:\.\d+)*(?:[-+~._][0-9A-Za-z.-]+)?$/', $db_version);
        $db_version_matches = $db_version_is_valid && version_compare($db_version, AIPS_VERSION, '==');

        $db_version_details = array();
        if (!$db_version_matches) {
            $db_version_details[] = sprintf(
                /* translators: %s: stored database version */
                __('Stored database version: %s', 'ai-post-scheduler'),
                empty($db_version) ? __('Unknown', 'ai-post-scheduler') : $db_version
            );
            $db_version_details[] = sprintf(
                /* translators: %s: expected plugin database version */
                __('Expected database version for this plugin build: %s', 'ai-post-scheduler'),
                AIPS_VERSION
            );
            $db_version_details[] = __('This usually means the database schema is from a different plugin build or an upgrade did not complete.', 'ai-post-scheduler');
            $db_version_details[] = __('Try "Repair DB Tables" first. If this persists, run "Reinstall DB Tables" with backup enabled.', 'ai-post-scheduler');
        }

        return array(
            'version' => array(
                'label' => 'Plugin Version',
                'value' => AIPS_VERSION,
                'status' => 'ok',
            ),
            'db_version' => array(
                'label' => 'Database Version',
                'value' => empty($db_version) ? 'Unknown' : $db_version,
                'status' => $db_version_matches ? 'ok' : 'warning',
                'details' => $db_version_details,
            ),
            'ai_engine' => array(
                'label' => 'AI Engine Plugin',
                'value' => $ai_engine_active ? 'Active' : 'Missing',
                'status' => $ai_engine_active ? 'ok' : 'error',
            ),
        );
    }

    private function check_database() {
        global $wpdb;
        
        // Get expected columns from AIPS_DB_Manager (single source of truth)
        $tables = AIPS_DB_Manager::get_expected_columns();

        $results = array();

        foreach ($tables as $table_name => $columns) {
            $full_table_name = $wpdb->prefix . $table_name;
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $full_table_name)) === $full_table_name;

            if (!$table_exists) {
                $results[$table_name] = array(
                    'label' => "Table: $table_name",
                    'value' => 'Missing',
                    'status' => 'error',
                );
                continue;
            }

            $missing_columns = array();
            $db_columns = $wpdb->get_results("SHOW COLUMNS FROM $full_table_name", ARRAY_A);
            $db_column_names = array_column($db_columns, 'Field');

            foreach ($columns as $col) {
                if (!in_array($col, $db_column_names)) {
                    $missing_columns[] = $col;
                }
            }

            if (!empty($missing_columns)) {
                $results[$table_name] = array(
                    'label' => "Table: $table_name",
                    'value' => 'Missing columns: ' . implode(', ', $missing_columns),
                    'status' => 'error',
                );
            } else {
                $results[$table_name] = array(
                    'label' => "Table: $table_name",
                    'value' => 'OK',
                    'status' => 'ok',
                );
            }
        }

        return $results;
    }

    private function check_filesystem() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/aips-logs';

        $exists = file_exists($log_dir);
        $writable = wp_is_writable($log_dir);

        return array(
            'log_dir' => array(
                'label' => 'Log Directory',
                'value' => $exists ? ($writable ? 'Writable' : 'Not Writable') : 'Missing',
                'status' => ($exists && $writable) ? 'ok' : 'error',
            ),
        );
    }

    private function check_cron() {
        $cron_events = AI_Post_Scheduler::get_cron_events();

        $status = array();

        foreach ($cron_events as $event_hook => $event_config) {
            $next_run = wp_next_scheduled($event_hook);
            $status[$event_hook] = array(
                'label' => isset($event_config['label']) ? $event_config['label'] : $event_hook,
                'value' => $next_run ? date('Y-m-d H:i:s', $next_run) : 'Not Scheduled',
                'status' => $next_run ? 'ok' : 'error',
            );
        }

        return $status;
    }

    private function check_logs() {
        $logs_data = array();

        // Check AIPS logs
        $logger = new AIPS_Logger();
        $log_files = $logger->get_log_files();

        if (!empty($log_files)) {
            // Get most recent file
            usort($log_files, function($a, $b) {
                return strtotime($b['modified']) - strtotime($a['modified']);
            });
            $recent_log = $log_files[0];
            $upload_dir = wp_upload_dir();
            $log_path = $upload_dir['basedir'] . '/aips-logs/' . $recent_log['name'];

            $errors = $this->scan_file_for_errors($log_path);

            $logs_data['plugin_log'] = array(
                'label' => 'Plugin Log (' . $recent_log['name'] . ')',
                'value' => empty($errors) ? 'No recent errors' : count($errors) . ' errors found',
                'status' => empty($errors) ? 'ok' : 'warning',
                'details' => $errors
            );
        } else {
            $logs_data['plugin_log'] = array(
                'label' => 'Plugin Log',
                'value' => 'No log files found',
                'status' => 'info',
            );
        }

        // Check WP Debug Log
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $debug_log_path = WP_CONTENT_DIR . '/debug.log';
            if (is_string(WP_DEBUG_LOG)) {
                $debug_log_path = WP_DEBUG_LOG;
            }

            if (file_exists($debug_log_path)) {
                $errors = $this->scan_file_for_errors($debug_log_path, 50, true);
                $logs_data['wp_debug_log'] = array(
                    'label' => 'WP Debug Log',
                    'value' => empty($errors) ? 'No recent errors from this plugin' : count($errors) . ' errors found',
                    'status' => empty($errors) ? 'ok' : 'warning',
                    'details' => $errors
                );
            }
        }

        return $logs_data;
    }

    /**
     * Scans a given file for specific error keywords (e.g., error, warning, fatal).
     *
     * @param string  $file_path     The absolute path to the file.
     * @param int     $lines         The maximum number of lines to scan from the end of the file.
     * @param bool    $filter_plugin Optional. If true, filters for lines containing 'ai-post-scheduler'.
     * @return array Array of matched error lines, or empty array if file is inaccessible.
     */
    private function scan_file_for_errors($file_path, $lines = 100, $filter_plugin = false) {
        if (!file_exists($file_path) || !is_readable($file_path)) {
            return array();
        }

        $chunk_size = 1024 * 100; // Read last 100KB
        $file_size = filesize($file_path);

        if ($file_size === false || $file_size === 0) {
            return array();
        }

        $handle = fopen($file_path, 'rb');
        if ($handle === false) {
            return array();
        }

        $offset = max(0, $file_size - $chunk_size);
        fseek($handle, $offset);

        $content = fread($handle, $chunk_size);
        fclose($handle);

        if ($content === false) {
            return array();
        }

        $file_lines = explode("\n", $content);

        // If we didn't read the whole file, the first line might be partial, so skip it
        if ($offset > 0 && !empty($file_lines)) {
            array_shift($file_lines);
        }

        // Take the last $lines
        $file_lines = array_slice($file_lines, -$lines);

        $errors = array();
        foreach ($file_lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if ($filter_plugin && strpos($line, 'ai-post-scheduler') === false) {
                continue;
            }

            if (stripos($line, 'error') !== false || stripos($line, 'warning') !== false || stripos($line, 'fatal') !== false) {
                $errors[] = $line;
            }
        }

        return array_reverse($errors); // Most recent first
    }
}
