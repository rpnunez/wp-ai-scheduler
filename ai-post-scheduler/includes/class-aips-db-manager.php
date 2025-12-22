<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_DB_Manager {

    private static $tables = array(
        'aips_history',
        'aips_templates',
        'aips_schedule',
        'aips_voices'
    );

    public function __construct() {
        add_action('wp_ajax_aips_repair_db', array($this, 'ajax_repair_db'));
        add_action('wp_ajax_aips_reinstall_db', array($this, 'ajax_reinstall_db'));
        add_action('wp_ajax_aips_wipe_db', array($this, 'ajax_wipe_db'));
    }

    /**
     * Get the list of plugin tables (without prefix)
     */
    public static function get_table_names() {
        return self::$tables;
    }

    /**
     * Get the list of plugin tables with full prefix
     */
    public static function get_full_table_names() {
        global $wpdb;
        $full_names = array();
        foreach (self::$tables as $table) {
            $full_names[$table] = $wpdb->prefix . $table;
        }
        return $full_names;
    }

    /**
     * Get a single table name with full prefix from table data
     * 
     * @param string $tableData The table name without prefix (e.g., 'aips_history')
     * @return string|null The full table name with prefix, or null if table not found
     */
    public static function get_table_name($tableData) {
        if (!is_string($tableData) || empty($tableData)) {
            return null;
        }
        if (!in_array($tableData, self::$tables)) {
            return null;
        }
        global $wpdb;
        return $wpdb->prefix . $tableData;
    }

    public function get_schema() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $tables = self::get_full_table_names();

        $table_history = $tables['aips_history'];
        $table_templates = $tables['aips_templates'];
        $table_schedule = $tables['aips_schedule'];
        $table_voices = $tables['aips_voices'];

        $sql = array();

        $sql[] = "CREATE TABLE $table_history (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) DEFAULT NULL,
            template_id bigint(20) DEFAULT NULL,
            status varchar(50) NOT NULL DEFAULT 'pending',
            prompt text,
            generated_title varchar(500),
            generated_content longtext,
            generation_log longtext,
            error_message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY post_id (post_id),
            KEY template_id (template_id),
            KEY status (status)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_templates (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            prompt_template text NOT NULL,
            title_prompt text,
            voice_id bigint(20) DEFAULT NULL,
            post_quantity int DEFAULT 1,
            image_prompt text,
            generate_featured_image tinyint(1) DEFAULT 0,
            post_status varchar(50) DEFAULT 'draft',
            post_category bigint(20) DEFAULT NULL,
            post_tags text,
            post_author bigint(20) DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_schedule (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            template_id bigint(20) NOT NULL,
            frequency varchar(50) NOT NULL DEFAULT 'daily',
            topic TEXT DEFAULT NULL,
            next_run datetime NOT NULL,
            last_run datetime DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY template_id (template_id),
            KEY next_run (next_run)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_voices (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            title_prompt text NOT NULL,
            content_instructions text NOT NULL,
            excerpt_instructions text,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        return $sql;
    }

    public static function install_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $instance = new self();
        $schema = $instance->get_schema();
        foreach ($schema as $sql) {
            dbDelta($sql);
        }
    }

    public function drop_tables() {
        global $wpdb;
        $tables = self::get_full_table_names();

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }

    public function truncate_tables() {
        global $wpdb;
        $tables = self::get_full_table_names();

        foreach ($tables as $table) {
            $wpdb->query("TRUNCATE TABLE $table");
        }
    }

    public function backup_data() {
        global $wpdb;
        $data = array();
        $tables = self::get_full_table_names();

        foreach ($tables as $key => $table_name) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
                $data[$key] = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
            } else {
                $data[$key] = array();
            }
        }

        return $data;
    }

    public function restore_data($data) {
        global $wpdb;
        if (empty($data)) return;

        $tables = self::get_full_table_names();

        foreach ($data as $key => $rows) {
            if (empty($rows)) continue;
            if (!isset($tables[$key])) continue;

            $table_name = $tables[$key];

            foreach ($rows as $row) {
                $wpdb->insert($table_name, $row);
            }
        }
    }

    public function ajax_repair_db() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        self::install_tables();
        wp_send_json_success(array('message' => 'Database tables repaired successfully.'));
    }

    public function ajax_reinstall_db() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $backup = isset($_POST['backup']) && $_POST['backup'] === 'true';
        $data = null;

        if ($backup) {
            $data = $this->backup_data();
        }

        $this->drop_tables();
        self::install_tables();

        if ($backup && $data) {
            $this->restore_data($data);
        }

        wp_send_json_success(array('message' => 'Database tables reinstalled successfully.'));
    }

    public function ajax_wipe_db() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $this->truncate_tables();
        wp_send_json_success(array('message' => 'Plugin data wiped successfully.'));
    }
}
