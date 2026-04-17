<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_DB_Manager {

    private static $tables = array(
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
        'aips_cache',
    );

    public function __construct() {
        add_action('wp_ajax_aips_repair_db', array($this, 'ajax_repair_db'));
        add_action('wp_ajax_aips_reinstall_db', array($this, 'ajax_reinstall_db'));
        add_action('wp_ajax_aips_wipe_db', array($this, 'ajax_wipe_db'));
        add_action('wp_ajax_aips_flush_cron_events', array($this, 'ajax_flush_cron_events'));
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

    public function get_schema() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $tables = self::get_full_table_names();

        $table_history = $tables['aips_history'];
        $table_history_log = $tables['aips_history_log'];
        $table_templates = $tables['aips_templates'];
        $table_schedule = $tables['aips_schedule'];
        $table_voices = $tables['aips_voices'];
        $table_structures = $tables['aips_article_structures'];
        $table_sections = $tables['aips_prompt_sections'];
        $table_trending_topics = $tables['aips_trending_topics'];
        $table_authors = $tables['aips_authors'];
        $table_author_topics = $tables['aips_author_topics'];
        $table_author_topic_logs = $tables['aips_author_topic_logs'];
        $table_topic_feedback = $tables['aips_topic_feedback'];
        $table_notifications        = $tables['aips_notifications'];
        $table_sources              = $tables['aips_sources'];
        $table_source_group_terms   = $tables['aips_source_group_terms'];
        $table_taxonomy             = $tables['aips_taxonomy'];
        $table_cache                = $tables['aips_cache'];

        $sql = array();

        $sql[] = "CREATE TABLE $table_history (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            uuid varchar(36) DEFAULT NULL,
            correlation_id varchar(36) DEFAULT NULL,
            post_id bigint(20) DEFAULT NULL,
            template_id bigint(20) DEFAULT NULL,
            author_id bigint(20) DEFAULT NULL,
            topic_id bigint(20) DEFAULT NULL,
            creation_method varchar(20) DEFAULT NULL,
            status varchar(50) NOT NULL DEFAULT 'pending',
            prompt text,
            generated_title varchar(500),
            generated_content longtext,
            generation_log longtext,
            error_message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uuid (uuid),
            KEY post_id (post_id),
            KEY template_id (template_id),
            KEY author_id (author_id),
            KEY topic_id (topic_id),
            KEY status (status),
            KEY created_at (created_at),
            KEY status_created (status, created_at),
            KEY template_created (template_id, created_at),
            KEY correlation_id (correlation_id)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_history_log (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            history_id bigint(20) NOT NULL,
            log_type varchar(50) NOT NULL,
            history_type_id int DEFAULT 1,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            details longtext,
            PRIMARY KEY  (id),
            KEY history_id (history_id),
            KEY history_type_id (history_type_id)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_templates (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            prompt_template text NOT NULL,
            title_prompt text,
            voice_id bigint(20) DEFAULT NULL,
            post_quantity int DEFAULT 1,
            image_prompt text,
            generate_featured_image tinyint(1) DEFAULT 0,
            featured_image_source varchar(50) DEFAULT 'ai_prompt',
            featured_image_unsplash_keywords text,
            featured_image_media_ids text,
            post_status varchar(50) DEFAULT 'draft',
            post_category bigint(20) DEFAULT NULL,
            post_tags text,
            post_author bigint(20) DEFAULT NULL,
            include_sources tinyint(1) DEFAULT 0,
            source_group_ids text DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_schedule (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            template_id bigint(20) NOT NULL,
            title varchar(255) DEFAULT NULL,
            article_structure_id bigint(20) DEFAULT NULL,
            rotation_pattern varchar(50) DEFAULT NULL,
            frequency varchar(50) NOT NULL DEFAULT 'daily',
            topic TEXT DEFAULT NULL,
            next_run datetime NOT NULL,
            last_run datetime DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            status varchar(20) DEFAULT 'active',
            schedule_history_id bigint(20) DEFAULT NULL,
            schedule_type varchar(50) NOT NULL DEFAULT 'post_generation',
            circuit_state varchar(20) NOT NULL DEFAULT 'closed',
            run_state text DEFAULT NULL,
            batch_progress longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY template_id (template_id),
            KEY article_structure_id (article_structure_id),
            KEY next_run (next_run),
            KEY is_active_next_run (is_active, next_run),
            KEY status (status),
            KEY schedule_history_id (schedule_history_id),
            KEY schedule_type (schedule_type),
            KEY circuit_state (circuit_state)
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

        $sql[] = "CREATE TABLE $table_structures (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            structure_data longtext NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            is_default tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY is_active (is_active),
            KEY is_default (is_default)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_sections (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            section_key varchar(100) NOT NULL,
            content text NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY section_key (section_key),
            KEY is_active (is_active)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_trending_topics (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            niche varchar(255) NOT NULL,
            topic varchar(500) NOT NULL,
            score int(11) NOT NULL DEFAULT 50,
            reason text DEFAULT NULL,
            keywords text DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'new',
            researched_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY niche_idx (niche),
            KEY score_idx (score),
            KEY status_idx (status),
            KEY researched_at_idx (researched_at)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_authors (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            field_niche varchar(500) NOT NULL,
            description text,
            keywords text,
            details text,
            article_structure_id bigint(20) DEFAULT NULL,
            topic_generation_prompt text,
            topic_generation_frequency varchar(50) DEFAULT 'weekly',
            topic_generation_quantity int DEFAULT 5,
            topic_generation_next_run datetime DEFAULT NULL,
            topic_generation_last_run datetime DEFAULT NULL,
            topic_generation_is_active tinyint(1) DEFAULT 1,
            post_generation_frequency varchar(50) DEFAULT 'daily',
            post_generation_next_run datetime DEFAULT NULL,
            post_generation_last_run datetime DEFAULT NULL,
            post_generation_is_active tinyint(1) DEFAULT 1,
            post_status varchar(50) DEFAULT 'draft',
            post_category bigint(20) DEFAULT NULL,
            post_tags text,
            post_author bigint(20) DEFAULT NULL,
            generate_featured_image tinyint(1) DEFAULT 0,
            featured_image_source varchar(50) DEFAULT 'ai_prompt',
            voice_tone varchar(100) DEFAULT NULL,
            writing_style varchar(100) DEFAULT NULL,
            target_audience varchar(500) DEFAULT NULL,
            expertise_level varchar(50) DEFAULT NULL,
            content_goals text DEFAULT NULL,
            excluded_topics text DEFAULT NULL,
            preferred_content_length varchar(50) DEFAULT NULL,
            language varchar(10) DEFAULT 'en',
            max_posts_per_topic int DEFAULT 1,
            include_sources tinyint(1) DEFAULT 0,
            source_group_ids text DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY article_structure_id (article_structure_id),
            KEY is_active (is_active),
            KEY topic_generation_next_run (topic_generation_next_run),
            KEY post_generation_next_run (post_generation_next_run)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_author_topics (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            author_id bigint(20) NOT NULL,
            topic_title varchar(500) NOT NULL,
            topic_prompt text,
            status varchar(20) DEFAULT 'pending',
            score int DEFAULT 50,
            metadata longtext,
            generated_at datetime DEFAULT CURRENT_TIMESTAMP,
            reviewed_at datetime DEFAULT NULL,
            reviewed_by bigint(20) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY author_id (author_id),
            KEY status (status),
            KEY generated_at (generated_at),
            KEY author_id_status (author_id, status),
            KEY status_score_reviewed (status, score, reviewed_at)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_author_topic_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            author_topic_id bigint(20) NOT NULL,
            post_id bigint(20) DEFAULT NULL,
            action varchar(50) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            notes text,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY author_topic_id (author_topic_id),
            KEY post_id (post_id),
            KEY action (action),
            KEY created_at (created_at)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_topic_feedback (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            author_topic_id bigint(20) NOT NULL,
            action varchar(20) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            reason text,
            reason_category varchar(50) DEFAULT 'other',
            source varchar(50) DEFAULT 'UI',
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY author_topic_id (author_topic_id),
            KEY action (action),
            KEY user_id (user_id),
            KEY reason_category (reason_category),
            KEY source (source),
            KEY created_at (created_at)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_notifications (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            type varchar(100) NOT NULL,
            title varchar(255) DEFAULT NULL,
            message text NOT NULL,
            url varchar(500) DEFAULT NULL,
            level varchar(20) NOT NULL DEFAULT 'info',
            meta longtext DEFAULT NULL,
            dedupe_key varchar(191) DEFAULT NULL,
            is_read tinyint(1) NOT NULL DEFAULT 0,
            read_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY type (type),
            KEY level (level),
            KEY dedupe_key (dedupe_key),
            KEY is_read (is_read),
            KEY created_at (created_at),
            KEY is_read_created_at (is_read, created_at),
            KEY dedupe_key_created_at (dedupe_key, created_at)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_sources (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            url varchar(2083) NOT NULL,
            label varchar(255) DEFAULT NULL,
            description text DEFAULT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY is_active (is_active),
            KEY created_at (created_at)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_source_group_terms (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            source_id bigint(20) NOT NULL,
            term_id bigint(20) NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY source_term (source_id, term_id),
            KEY source_id (source_id),
            KEY term_id (term_id)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_taxonomy (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            taxonomy_type varchar(50) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            base_post_ids text DEFAULT NULL,
            generation_prompt text DEFAULT NULL,
            term_id bigint(20) DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY taxonomy_type (taxonomy_type),
            KEY status (status),
            KEY term_id (term_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE $table_cache (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            cache_key varchar(191) NOT NULL,
            cache_group varchar(100) NOT NULL DEFAULT 'default',
            value longtext NOT NULL,
            expires_at datetime DEFAULT NULL,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY cache_key_group (cache_key, cache_group),
            KEY expires_at (expires_at)
        ) $charset_collate;";

        return $sql;
    }

    public static function install_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $instance = new self();
        $schema = $instance->get_schema();
        global $wpdb;

        foreach ($schema as $sql) {
            $pre_error = $wpdb->last_error;
            dbDelta($sql);

            if (!empty($wpdb->last_error) && $wpdb->last_error !== $pre_error) {
                return new WP_Error('db_install_failed', $wpdb->last_error);
            }
        }

        // Seed default data for new installations or upgrades
        self::seed_default_data();

        // Record that the DB schema is now at the current plugin version
        update_option('aips_db_version', AIPS_VERSION);

        return true;
    }

    public function drop_tables() {
        global $wpdb;
        $tables = self::get_full_table_names();

        foreach ($tables as $table) {
            $table_escaped = '`' . esc_sql($table) . '`';
            $wpdb->query("DROP TABLE IF EXISTS {$table_escaped}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
    }

    public function truncate_tables() {
        global $wpdb;
        $tables = self::get_full_table_names();

        foreach ($tables as $table) {
            $table_escaped = '`' . esc_sql($table) . '`';
            $wpdb->query("TRUNCATE TABLE {$table_escaped}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
    }

    public function backup_data() {
        global $wpdb;
        $data = array();
        $tables = self::get_full_table_names();

        foreach ($tables as $key => $table_name) {
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name) {
                $table_escaped = '`' . esc_sql($table_name) . '`';
                $data[$key] = $wpdb->get_results("SELECT * FROM {$table_escaped}", ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
        if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }
        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::error('Unauthorized');
        }

        self::install_tables();
        AIPS_Ajax_Response::success(array('message' => 'Database tables repaired successfully.'));
    }

    public function ajax_reinstall_db() {
        if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }
        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::error('Unauthorized');
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

        AIPS_Ajax_Response::success(array('message' => 'Database tables reinstalled successfully.'));
    }

    public function ajax_wipe_db() {
        if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }
        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::error('Unauthorized');
        }

        $this->truncate_tables();
        AIPS_Ajax_Response::success(array('message' => 'Plugin data wiped successfully.'));
    }

    /**
     * AJAX handler: flush all plugin WP-Cron events and re-register each exactly once.
     *
     * Removes every scheduled instance of every plugin cron hook (handles
     * cases where duplicate/stacked events have accumulated), then schedules
     * each hook once with its configured recurrence.
     *
     * @return void
     */
    public function ajax_flush_cron_events() {
        if ( ! check_ajax_referer('aips_ajax_nonce', 'nonce', false) ) {
            AIPS_Ajax_Response::error(__('Invalid nonce.', 'ai-post-scheduler'));
        }
        if (!current_user_can('manage_options')) {
            AIPS_Ajax_Response::error(__('Unauthorized', 'ai-post-scheduler'));
        }

        $cron_events    = AI_Post_Scheduler::get_cron_events();
        $unscheduled    = array();
        $rescheduled    = array();
        $failed         = array();

        foreach ($cron_events as $hook => $config) {
            $schedule = isset($config['schedule']) ? $config['schedule'] : 'hourly';
            $label    = isset($config['label']) ? $config['label'] : $hook;

            // Remove all existing instances of this hook from the cron table.
            wp_unschedule_hook($hook);
            $unscheduled[] = $label;

            // Re-register exactly once. Use a 60-second offset to avoid an
            // immediate burst of AI calls right after flushing.
            $scheduled = wp_schedule_event(time() + 60, $schedule, $hook);
            if ($scheduled !== false) {
                $rescheduled[] = $label;
            } else {
                $failed[] = $label;
            }
        }

        if (!empty($failed)) {
            AIPS_Ajax_Response::error(array(
                'message' => sprintf(
                    /* translators: %s: comma-separated hook labels that failed to reschedule */
                    __('Cron events flushed but some hooks could not be rescheduled: %s', 'ai-post-scheduler'),
                    implode(', ', $failed)
                ),
                'details' => array(
                    'unscheduled' => $unscheduled,
                    'rescheduled' => $rescheduled,
                    'failed'      => $failed,
                ),
            ));
            return;
        }

        AIPS_Ajax_Response::success(array(
            'message' => sprintf(
                /* translators: %d: number of cron hooks flushed and rescheduled */
                _n(
                    '%d WP-Cron event flushed and rescheduled successfully.',
                    '%d WP-Cron events flushed and rescheduled successfully.',
                    count($rescheduled),
                    'ai-post-scheduler'
                ),
                count($rescheduled)
            ),
            'details' => array(
                'unscheduled' => $unscheduled,
                'rescheduled' => $rescheduled,
            ),
        ));
    }

    /**
     * Parse column names from CREATE TABLE SQL statement
     *
     * @param string $sql CREATE TABLE SQL statement
     * @return array Column names
     */
    public static function parse_columns_from_sql($sql) {
        $columns = array();

        // Extract content between CREATE TABLE ... ( and the closing )
        if (preg_match('/CREATE TABLE[^(]+\((.+)\)/s', $sql, $matches)) {
            $table_def = $matches[1];

            // Split by lines and process each
            $lines = explode("\n", $table_def);
            foreach ($lines as $line) {
                $line = trim($line);

                // Skip empty lines, PRIMARY KEY, KEY, UNIQUE KEY lines
                if (empty($line) ||
                    stripos($line, 'PRIMARY KEY') !== false ||
                    preg_match('/^KEY\s+/i', $line) ||
                    stripos($line, 'UNIQUE KEY') !== false) {
                    continue;
                }

                // Extract column name (first word after trimming)
                if (preg_match('/^`?(\w+)`?\s+/', $line, $col_matches)) {
                    $columns[] = $col_matches[1];
                }
            }
        }

        return $columns;
    }

    /**
     * Get expected columns for each table by parsing the schema
     *
     * @return array Associative array of table_name => array of column names
     */
    public static function get_expected_columns() {
        $instance = new self();
        $schema = $instance->get_schema();
        $expected = array();

        foreach ($schema as $sql) {
            // Extract table name
            if (preg_match('/CREATE TABLE\s+(\S+)\s*\(/i', $sql, $matches)) {
                $full_table_name = $matches[1];
                // Remove $wpdb->prefix to get just the table name
                global $wpdb;
                $table_name = str_replace($wpdb->prefix, '', $full_table_name);

                $expected[$table_name] = self::parse_columns_from_sql($sql);
            }
        }

        return $expected;
    }

    /**
     * Seed default data for prompt sections and article structures
     * Only inserts if data doesn't already exist (idempotent)
     */
    private static function seed_default_data() {
        global $wpdb;
        $tables = self::get_full_table_names();
        $table_sections = $tables['aips_prompt_sections'];
        $table_structures = $tables['aips_article_structures'];

        // Seed default prompt sections
        $default_sections = array(
            array(
                'name' => 'Introduction',
                'section_key' => 'introduction',
                'description' => 'Opening paragraph that hooks the reader',
                'content' => 'Write an engaging introduction that captures attention and clearly states what the article will cover.',
            ),
            array(
                'name' => 'Prerequisites',
                'section_key' => 'prerequisites',
                'description' => 'Required knowledge or tools',
                'content' => 'List any prerequisites, required tools, or background knowledge needed.',
            ),
            array(
                'name' => 'Step-by-Step Instructions',
                'section_key' => 'steps',
                'description' => 'Detailed procedural steps',
                'content' => 'Provide clear, numbered step-by-step instructions with explanations.',
            ),
            array(
                'name' => 'Code Examples',
                'section_key' => 'examples',
                'description' => 'Practical code samples',
                'content' => 'Include relevant code examples with explanations of how they work.',
            ),
            array(
                'name' => 'Tips and Best Practices',
                'section_key' => 'tips',
                'description' => 'Expert advice and recommendations',
                'content' => 'Share helpful tips, best practices, and common pitfalls to avoid.',
            ),
            array(
                'name' => 'Troubleshooting',
                'section_key' => 'troubleshooting',
                'description' => 'Common issues and solutions',
                'content' => 'Address common problems readers might encounter and provide solutions.',
            ),
            array(
                'name' => 'Conclusion',
                'section_key' => 'conclusion',
                'description' => 'Wrap-up and next steps',
                'content' => 'Summarize key points and suggest next steps or related topics.',
            ),
            array(
                'name' => 'Resources',
                'section_key' => 'resources',
                'description' => 'Additional learning materials',
                'content' => 'Provide links to documentation, further reading, or related resources.',
            ),
        );

        foreach ($default_sections as $section) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_sections WHERE section_key = %s",
                $section['section_key']
            ));

            if (!$exists) {
                $wpdb->insert($table_sections, $section);
            }
        }

        // Seed default article structures
        $default_structures = array(
            array(
                'name' => 'How-To Guide',
                'description' => 'Step-by-step guide for accomplishing a specific task',
                'structure_data' => wp_json_encode(array(
                    'sections' => array('introduction', 'prerequisites', 'steps', 'tips', 'troubleshooting', 'conclusion'),
                    'prompt_template' => "Write a comprehensive how-to guide about {{topic}}.\n\n{{section:introduction}}\n\n{{section:prerequisites}}\n\n{{section:steps}}\n\n{{section:tips}}\n\n{{section:troubleshooting}}\n\n{{section:conclusion}}",
                )),
                'is_default' => 1,
            ),
            array(
                'name' => 'Tutorial',
                'description' => 'In-depth educational content with practical examples',
                'structure_data' => wp_json_encode(array(
                    'sections' => array('introduction', 'prerequisites', 'examples', 'steps', 'tips', 'resources', 'conclusion'),
                    'prompt_template' => "Create a detailed tutorial on {{topic}}.\n\n{{section:introduction}}\n\n{{section:prerequisites}}\n\n{{section:examples}}\n\n{{section:steps}}\n\n{{section:tips}}\n\n{{section:resources}}\n\n{{section:conclusion}}",
                )),
            ),
            array(
                'name' => 'Library Reference',
                'description' => 'Technical documentation for a library or API',
                'structure_data' => wp_json_encode(array(
                    'sections' => array('introduction', 'prerequisites', 'examples', 'resources'),
                    'prompt_template' => "Write technical reference documentation for {{topic}}.\n\n{{section:introduction}}\n\n{{section:prerequisites}}\n\n{{section:examples}}\n\nInclude comprehensive API documentation with parameter descriptions, return values, and usage examples.\n\n{{section:resources}}",
                )),
            ),
            array(
                'name' => 'Listicle',
                'description' => 'List-based article with multiple items or tips',
                'structure_data' => wp_json_encode(array(
                    'sections' => array('introduction', 'conclusion'),
                    'prompt_template' => "Write a comprehensive listicle about {{topic}}.\n\n{{section:introduction}}\n\nPresent the main content as a numbered or bulleted list with detailed explanations for each item.\n\n{{section:conclusion}}",
                )),
            ),
            array(
                'name' => 'Case Study',
                'description' => 'Real-world example with analysis and insights',
                'structure_data' => wp_json_encode(array(
                    'sections' => array('introduction', 'examples', 'conclusion'),
                    'prompt_template' => "Write a detailed case study about {{topic}}.\n\n{{section:introduction}}\n\nProvide background context and the problem/challenge being addressed.\n\n{{section:examples}}\n\nAnalyze the results, lessons learned, and key takeaways.\n\n{{section:conclusion}}",
                )),
            ),
            array(
                'name' => 'Opinion/Editorial',
                'description' => 'Thought leadership or opinion piece',
                'structure_data' => wp_json_encode(array(
                    'sections' => array('introduction', 'tips', 'conclusion'),
                    'prompt_template' => "Write an opinion piece or editorial about {{topic}}.\n\n{{section:introduction}}\n\nPresent your main arguments with supporting evidence and examples.\n\n{{section:tips}}\n\nAddress counterarguments or alternative perspectives.\n\n{{section:conclusion}}",
                )),
            ),
        );

        foreach ($default_structures as $structure) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_structures WHERE name = %s",
                $structure['name']
            ));

            if (!$exists) {
                $wpdb->insert($table_structures, $structure);
            }
        }
    }
}
