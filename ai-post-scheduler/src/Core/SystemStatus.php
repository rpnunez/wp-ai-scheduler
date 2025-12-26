<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_System_Status {

    public function render_page() {
        $system_info = $this->get_system_info();
        include AIPS_PLUGIN_DIR . 'templates/admin/system-status.php';
    }

    public function get_system_info() {
        return array(
            'environment' => $this->check_environment(),
            'plugin' => $this->check_plugin(),
            'database' => $this->check_database(),
            'filesystem' => $this->check_filesystem(),
            'cron' => $this->check_cron(),
            'logs' => $this->check_logs(),
        );
    }

    private function check_environment() {
        global $wp_version, $wpdb;
        return array(
            'php_version' => array(
                'label' => 'PHP Version',
                'value' => phpversion(),
                'status' => version_compare(phpversion(), '7.4', '>=') ? 'ok' : 'warning',
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
        return array(
            'version' => array(
                'label' => 'Plugin Version',
                'value' => AIPS_VERSION,
                'status' => 'ok',
            ),
            'db_version' => array(
                'label' => 'Database Version',
                'value' => get_option('aips_db_version', 'Unknown'),
                'status' => version_compare(get_option('aips_db_version'), AIPS_VERSION, '==') ? 'ok' : 'warning',
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
        $tables = array(
            'aips_history' => array(
                'id', 'post_id', 'template_id', 'status', 'prompt', 'generated_title',
                'generated_content', 'generation_log', 'error_message', 'created_at', 'completed_at'
            ),
            'aips_templates' => array(
                'id', 'name', 'prompt_template', 'title_prompt', 'voice_id', 'post_quantity',
                'image_prompt', 'generate_featured_image', 'post_status', 'post_category',
                'post_tags', 'post_author', 'is_active', 'created_at', 'updated_at'
            ),
            'aips_schedule' => array(
                'id', 'template_id', 'frequency', 'topic', 'next_run', 'last_run', 'is_active', 'created_at'
            ),
            'aips_voices' => array(
                'id', 'name', 'title_prompt', 'content_instructions', 'excerpt_instructions',
                'is_active', 'created_at'
            ),
        );

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
        $next_run = wp_next_scheduled('aips_generate_scheduled_posts');

        return array(
            'schedule_event' => array(
                'label' => 'Cron Event',
                'value' => $next_run ? date('Y-m-d H:i:s', $next_run) : 'Not Scheduled',
                'status' => $next_run ? 'ok' : 'error',
            ),
        );
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

    private function scan_file_for_errors($file_path, $lines = 100, $filter_plugin = false) {
        if (!file_exists($file_path)) {
            return array();
        }

        $chunk_size = 1024 * 100; // Read last 100KB
        $file_size = filesize($file_path);

        if ($file_size === 0) {
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
