<?php
/**
 * Plugin Name: AI Post Scheduler
 * Plugin URI: https://example.com/ai-post-scheduler
 * Description: Schedule AI-generated posts using Meow Apps AI Engine
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-post-scheduler
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AIPS_VERSION', '1.0.0');
define('AIPS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AIPS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AIPS_PLUGIN_BASENAME', plugin_basename(__FILE__));

final class AI_Post_Scheduler {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->check_dependencies();
        $this->includes();
        $this->init_hooks();
    }
    
    private function check_dependencies() {
        add_action('admin_init', function() {
            if (!class_exists('Meow_MWAI_Core')) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error"><p>';
                    echo esc_html__('AI Post Scheduler requires Meow Apps AI Engine plugin to be installed and activated.', 'ai-post-scheduler');
                    echo '</p></div>';
                });
            }
        });
    }
    
    private function includes() {
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-settings.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-voices.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-templates.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-generator.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-scheduler.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-history.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-logger.php';
    }
    
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    public function activate() {
        $this->create_tables();
        $this->set_default_options();
        
        if (!wp_next_scheduled('aips_generate_scheduled_posts')) {
            wp_schedule_event(time(), 'hourly', 'aips_generate_scheduled_posts');
        }
        
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        wp_clear_scheduled_hook('aips_generate_scheduled_posts');
        flush_rewrite_rules();
    }
    
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_history = $wpdb->prefix . 'aips_history';
        $table_templates = $wpdb->prefix . 'aips_templates';
        $table_schedule = $wpdb->prefix . 'aips_schedule';
        $table_voices = $wpdb->prefix . 'aips_voices';
        
        $sql_history = "CREATE TABLE IF NOT EXISTS $table_history (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) DEFAULT NULL,
            template_id bigint(20) DEFAULT NULL,
            status varchar(50) NOT NULL DEFAULT 'pending',
            prompt text,
            generated_title varchar(500),
            generated_content longtext,
            error_message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY template_id (template_id),
            KEY status (status)
        ) $charset_collate;";
        
        $sql_templates = "CREATE TABLE IF NOT EXISTS $table_templates (
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
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        $sql_schedule = "CREATE TABLE IF NOT EXISTS $table_schedule (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            template_id bigint(20) NOT NULL,
            frequency varchar(50) NOT NULL DEFAULT 'daily',
            next_run datetime NOT NULL,
            last_run datetime DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY template_id (template_id),
            KEY next_run (next_run)
        ) $charset_collate;";
        
        $sql_voices = "CREATE TABLE IF NOT EXISTS $table_voices (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            title_prompt text NOT NULL,
            content_instructions text NOT NULL,
            excerpt_instructions text,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_history);
        dbDelta($sql_templates);
        dbDelta($sql_schedule);
        dbDelta($sql_voices);
    }
    
    private function set_default_options() {
        $defaults = array(
            'aips_default_post_status' => 'draft',
            'aips_default_category' => 0,
            'aips_enable_logging' => 1,
            'aips_max_retries' => 3,
            'aips_ai_model' => '',
        );
        
        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }
    
    public function init() {
        load_plugin_textdomain('ai-post-scheduler', false, dirname(AIPS_PLUGIN_BASENAME) . '/languages');
        
        if (is_admin()) {
            new AIPS_Settings();
            new AIPS_Voices();
            new AIPS_Templates();
            new AIPS_History();
        }
        
        new AIPS_Scheduler();
        new AIPS_Logger();
    }
}

function aips_init() {
    return AI_Post_Scheduler::get_instance();
}

add_action('plugins_loaded', 'aips_init', 5);
