<?php

namespace AIPS\DataManagement\Import;

use AIPS\Repositories\DBManager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * MySQL dump import implementation
 *
 * SECURITY: Executes raw SQL from uploaded files. Only available to users with
 * 'manage_options' capability. Intended for restoring plugin backups.
 */
class MySQLImporter extends ImportHandler {

    /**
     * Get the import format name
     *
     * @return string
     */
    public function get_format_name() {
        return 'MySQL Dump';
    }

    /**
     * Get the file extension for this format
     *
     * @return string
     */
    public function get_file_extension() {
        return 'sql';
    }

    /**
     * Validate the uploaded file
     *
     * @param array $file The uploaded file data from $_FILES
     * @return bool|\WP_Error True on success, WP_Error on failure
     */
    public function validate_file($file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new \WP_Error('upload_error', __('File upload failed.', 'ai-post-scheduler'));
        }

        if (!$this->check_file_extension($file['name'])) {
            return new \WP_Error('invalid_extension', __('Invalid file extension. Expected .sql file.', 'ai-post-scheduler'));
        }

        $max_size = 50 * 1024 * 1024; // 50MB
        if ($file['size'] > $max_size) {
            return new \WP_Error('file_too_large', __('File is too large. Maximum size is 50MB.', 'ai-post-scheduler'));
        }

        if (!is_readable($file['tmp_name'])) {
            return new \WP_Error('file_not_readable', __('Cannot read uploaded file.', 'ai-post-scheduler'));
        }

        return true;
    }

    /**
     * Import the data from SQL file
     *
     * @param string $file_path Path to the uploaded file
     * @return bool|\WP_Error True on success, WP_Error on failure
     */
    public function import($file_path) {
        global $wpdb;

        $sql_content = file_get_contents($file_path);

        if ($sql_content === false) {
            return new \WP_Error('read_error', __('Could not read the SQL file.', 'ai-post-scheduler'));
        }

        if (strpos($sql_content, 'AI Post Scheduler Data Export') === false) {
            return new \WP_Error(
                'invalid_file',
                __('This does not appear to be a valid AI Post Scheduler export file.', 'ai-post-scheduler')
            );
        }

        $queries = $this->split_sql_file($sql_content);

        if (empty($queries)) {
            return new \WP_Error('no_queries', __('No valid SQL queries found in file.', 'ai-post-scheduler'));
        }

        $plugin_tables = DBManager::get_full_table_names();
        $plugin_table_names = array_values($plugin_tables);

        foreach ($queries as $query) {
            $query = trim($query);
            $query_upper = strtoupper($query);

            if (empty($query_upper)) {
                continue;
            }

            $allowed_commands = array('INSERT', 'DROP', 'CREATE', 'SET', 'LOCK', 'UNLOCK', 'TRUNCATE');
            $is_allowed = false;
            foreach ($allowed_commands as $cmd) {
                if (strpos($query_upper, $cmd) === 0) {
                    $is_allowed = true;
                    break;
                }
            }

            if (!$is_allowed) {
                return new \WP_Error(
                    'invalid_query',
                    __('SQL file contains disallowed query type.', 'ai-post-scheduler')
                );
            }

            if (strpos($query_upper, 'SET') !== 0 && strpos($query_upper, 'UNLOCK') !== 0) {
                $escaped_table_names = array_map(function ($t) {
                    return preg_quote($t, '/');
                }, $plugin_table_names);
                $table_pattern = implode('|', $escaped_table_names);

                if (strpos($query_upper, 'LOCK') === 0) {
                    $tables_part = preg_replace('/^LOCK\s+TABLES\s+/i', '', $query);

                    if ($tables_part === $query) {
                        return new \WP_Error(
                            'invalid_table',
                            __('SQL file contains queries for non-plugin tables or invalid format.', 'ai-post-scheduler')
                        );
                    }

                    $table_specs = explode(',', $tables_part);

                    foreach ($table_specs as $spec) {
                        $spec = trim($spec);

                        if ($spec === '') {
                            return new \WP_Error(
                                'invalid_table',
                                __('SQL file contains queries for non-plugin tables or invalid format.', 'ai-post-scheduler')
                            );
                        }

                        if (!preg_match('/^\s*[`\'"]?([A-Za-z0-9_]+)[`\'"]?/i', $spec, $matches)) {
                            return new \WP_Error(
                                'invalid_table',
                                __('SQL file contains queries for non-plugin tables or invalid format.', 'ai-post-scheduler')
                            );
                        }

                        $table_name = $matches[1];

                        if (!in_array($table_name, $plugin_table_names, true)) {
                            return new \WP_Error(
                                'invalid_table',
                                __('SQL file contains queries for non-plugin tables or invalid format.', 'ai-post-scheduler')
                            );
                        }
                    }
                } else {
                    $regex = '/^(?:INSERT(?:\s+INTO)?|DROP\s+TABLE(?:\s+IF\s+EXISTS)?|CREATE\s+TABLE)\s+[`\'"]?(' . $table_pattern . ')[`\'"]?/i';

                    if (!preg_match($regex, $query)) {
                        return new \WP_Error(
                            'invalid_table',
                            __('SQL file contains queries for non-plugin tables or invalid format.', 'ai-post-scheduler')
                        );
                    }
                }
            }

            if (strpos($query_upper, 'UNLOCK') === 0) {
                if (!preg_match('/^UNLOCK\s+TABLES\s*$/i', $query_upper)) {
                    return new \WP_Error(
                        'invalid_table',
                        __('SQL file contains queries for non-plugin tables or invalid format.', 'ai-post-scheduler')
                    );
                }
            }
        }

        $wpdb->query('SET FOREIGN_KEY_CHECKS = 0');

        $success_count = 0;
        $error_count = 0;
        $last_error = '';

        foreach ($queries as $query) {
            $query = trim($query);

            if (empty($query)) {
                continue;
            }

            $result = $wpdb->query($query);

            if ($result === false) {
                $error_count++;
                $last_error = $wpdb->last_error;
            } else {
                $success_count++;
            }
        }

        $wpdb->query('SET FOREIGN_KEY_CHECKS = 1');

        if ($error_count > 0) {
            return new \WP_Error(
                'import_errors',
                sprintf(
                    __('Import completed with %d successful queries and %d errors. Last error: %s', 'ai-post-scheduler'),
                    $success_count,
                    $error_count,
                    $last_error
                )
            );
        }

        return true;
    }

    /**
     * Split SQL file into individual queries
     *
     * @param string $sql_content SQL file content
     * @return array
     */
    private function split_sql_file($sql_content) {
        $sql_content = preg_replace('/--[^\n]*\n/', "\n", $sql_content);
        $sql_content = preg_replace('/\/\*.*?\*\//s', '', $sql_content);

        $queries = explode(';', $sql_content);

        $queries = array_filter($queries, function ($query) {
            return !empty(trim($query));
        });

        return $queries;
    }
}
