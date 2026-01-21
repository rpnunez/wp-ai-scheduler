<?php
if (!defined('ABSPATH')) {
    exit;
}

// Base Classes
abstract class AIPS_Data_Management_Export {
    abstract public function export();
}

abstract class AIPS_Data_Management_Import {
    abstract public function import($file);
}

// Export Implementations
class AIPS_Data_Management_Export_MySQL extends AIPS_Data_Management_Export {
    public function export() {
        global $wpdb;
        $tables = AIPS_DB_Manager::get_full_table_names();

        $sql = "-- AI Post Scheduler MySQL Dump\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $key => $table_name) {
            // Check if table exists
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
                continue;
            }

            // Get Create Table
            $create_table = $wpdb->get_row("SHOW CREATE TABLE $table_name", ARRAY_N);
            $sql .= "DROP TABLE IF EXISTS `$table_name`;\n";
            $sql .= $create_table[1] . ";\n\n";

            // Get Data
            $rows = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
            if ($rows) {
                $sql .= "INSERT INTO `$table_name` VALUES ";
                $values = [];
                foreach ($rows as $row) {
                    $row_values = [];
                    foreach ($row as $value) {
                        if (is_null($value)) {
                            $row_values[] = "NULL";
                        } else {
                            $row_values[] = "'" . esc_sql($value) . "'";
                        }
                    }
                    $values[] = "(" . implode(", ", $row_values) . ")";
                }
                $sql .= implode(",\n", $values) . ";\n\n";
            }
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        // Download
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="aips-backup-' . date('Y-m-d-H-i-s') . '.sql"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . strlen($sql));
        echo $sql;
        exit;
    }
}

class AIPS_Data_Management_Export_JSON extends AIPS_Data_Management_Export {
    public function export() {
        // Not implemented
        wp_die('JSON Export not implemented yet.');
    }
}

// Import Implementations
class AIPS_Data_Management_Import_MySQL extends AIPS_Data_Management_Import {
    public function import($file) {
        global $wpdb;

        if (!file_exists($file) || !is_readable($file)) {
            return new WP_Error('invalid_file', 'Invalid file.');
        }

        $sql_content = file_get_contents($file);
        if (empty($sql_content)) {
            return new WP_Error('empty_file', 'File is empty.');
        }

        // Basic SQL splitter
        $queries = explode(";\n", $sql_content);

        foreach ($queries as $query) {
            $query = trim($query);
            if (empty($query) || strpos($query, '--') === 0) continue;

            $wpdb->query($query);
        }

        return true;
    }
}

class AIPS_Data_Management_Import_JSON extends AIPS_Data_Management_Import {
    public function import($file) {
        // Not implemented
        return new WP_Error('not_implemented', 'JSON Import not implemented yet.');
    }
}

class AIPS_Data_Management {
    public function __construct() {
        add_action('admin_post_aips_export_data', array($this, 'handle_export'));
        add_action('admin_post_aips_import_data', array($this, 'handle_import'));
    }

    public function handle_export() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('aips_export_data_nonce', 'nonce');

        $format = isset($_POST['format']) ? sanitize_text_field($_POST['format']) : 'mysql';

        if ($format === 'mysql') {
            $exporter = new AIPS_Data_Management_Export_MySQL();
        } elseif ($format === 'json') {
            $exporter = new AIPS_Data_Management_Export_JSON();
        } else {
            wp_die('Invalid format');
        }

        $exporter->export();
    }

    public function handle_import() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('aips_import_data_nonce', 'nonce');

        $format = isset($_POST['format']) ? sanitize_text_field($_POST['format']) : 'mysql';

        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            wp_die('File upload error.');
        }

        $file = $_FILES['import_file']['tmp_name'];

        if ($format === 'mysql') {
            $importer = new AIPS_Data_Management_Import_MySQL();
        } elseif ($format === 'json') {
            $importer = new AIPS_Data_Management_Import_JSON();
        } else {
            wp_die('Invalid format');
        }

        $result = $importer->import($file);

        if (is_wp_error($result)) {
            wp_die($result->get_error_message());
        }

        // Redirect back with success message
        wp_redirect(add_query_arg(array('page' => 'aips-system-status', 'message' => 'import_success'), admin_url('admin.php')));
        exit;
    }
}
