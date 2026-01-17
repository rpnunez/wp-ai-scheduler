<?php
/**
 * Plugin Name: AI Post Scheduler
 * Plugin URI: https://example.com/ai-post-scheduler
 * Description: Schedule AI-generated posts using Meow Apps AI Engine
 * Version: 1.7.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-post-scheduler
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 8.2
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AIPS_VERSION', '1.7.0');
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
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-logger.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-config.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-db-manager.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-upgrades.php';

        // Admin UI Components
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-admin-menu.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-admin-assets.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-settings.php';

        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-voices.php';
        
        // Repository layer
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-history-repository.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-schedule-repository.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-template-repository.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-article-structure-repository.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-prompt-section-repository.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-trending-topics-repository.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-activity-repository.php';
        
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-templates.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-templates-controller.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-template-processor.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-prompt-builder.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-article-structure-manager.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-template-type-selector.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-structures-controller.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-prompt-sections-controller.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-interval-calculator.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-template-helper.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-resilience-service.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-ai-service.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-image-service.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-generation-session.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-research-service.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-post-creator.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-generator.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-scheduler.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-schedule-controller.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-activity-controller.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-research-controller.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-planner.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-history.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-system-status.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-dev-tools.php';

        // Seeder Feature
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-seeder-service.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-seeder-admin.php';
    }
    
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('plugins_loaded', array($this, 'check_upgrades'));
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    public function activate() {
        $this->set_default_options();
        $this->check_upgrades();

        // Ensure tables exist even if version matches (e.g. re-activation after manual deletion or partial install)
        AIPS_DB_Manager::install_tables();
        
        if (!wp_next_scheduled('aips_generate_scheduled_posts')) {
            wp_schedule_event(time(), 'hourly', 'aips_generate_scheduled_posts');
        }
        
        // Schedule automated research (daily by default)
        if (!wp_next_scheduled('aips_scheduled_research')) {
            wp_schedule_event(time(), 'daily', 'aips_scheduled_research');
        }
        
        flush_rewrite_rules();
    }
    
    public function check_upgrades() {
        AIPS_Upgrades::check_and_run();
    }
    
    public function deactivate() {
        wp_clear_scheduled_hook('aips_generate_scheduled_posts');
        wp_clear_scheduled_hook('aips_scheduled_research');
        flush_rewrite_rules();
    }
    
    
    private function set_default_options() {
        $defaults = array(
            'aips_default_post_status' => 'draft',
            'aips_default_category' => 0,
            'aips_enable_logging' => 1,
            'aips_retry_max_attempts' => 3,
            'aips_ai_model' => '',
            'aips_unsplash_access_key' => '',
            'aips_db_version' => AIPS_VERSION,
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
            new AIPS_DB_Manager();
            new AIPS_Settings();
            new AIPS_Voices();
            new AIPS_Templates();
            new AIPS_Templates_Controller();
            new AIPS_History();
            new AIPS_Planner();
            new AIPS_Schedule_Controller();
            new AIPS_Activity_Controller();
            new AIPS_Research_Controller();
            new AIPS_Seeder_Admin();
            // Structures admin controller (CRUD endpoints for Article Structures UI)
            new AIPS_Structures_Controller();
            // Prompt Sections admin controller (CRUD endpoints for Prompt Sections UI)
            new AIPS_Prompt_Sections_Controller();

            // Dev Tools
            if (get_option('aips_developer_mode')) {
                new AIPS_Dev_Tools();
            }
        }
        
        new AIPS_Scheduler();
    }
}

function aips_init() {
    return AI_Post_Scheduler::get_instance();
}

add_action('plugins_loaded', 'aips_init', 5);
