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

define('AIPS_VERSION', '2.0.0');
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
        $autoload_path = AIPS_PLUGIN_DIR . 'vendor/autoload.php';
        if (file_exists($autoload_path)) {
            require_once $autoload_path;
        }

        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-logger.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-config.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-db-manager.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-upgrades.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-settings.php';

        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-template-processor.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-prompt-builder.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-article-structure-manager.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-template-type-selector.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-interval-calculator.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-template-helper.php';
        
        // Generation Context architecture
        require_once AIPS_PLUGIN_DIR . 'includes/interface-aips-generation-context.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-template-context.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-topic-context.php';
        
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-generation-session.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-post-creator.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-generator.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-scheduler.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-planner.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-dev-tools.php';

        // Data Management Feature
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-data-management-export.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-data-management-import.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-data-management-export-mysql.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-data-management-import-mysql.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-data-management-export-json.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-data-management-import-json.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-data-management.php';
        // Authors Feature
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-author-topics-generator.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-author-topics-scheduler.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-author-post-generator.php';

        // Seeder Feature
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-seeder-admin.php';
    }
    
    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'check_upgrades'));
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    public function activate() {
        // Ensure logger is available
        if (!class_exists('AIPS_Logger')) {
            require_once AIPS_PLUGIN_DIR . 'includes/class-aips-logger.php';
        }

        $logger = new AIPS_Logger();

        $logger->log('Running plugin activation.');

        $this->set_default_options();
        $this->check_upgrades();

        // Ensure tables exist even if version matches (e.g. re-activation after manual deletion or partial install)
        AIPS_DB_Manager::install_tables();
        
        $crons = array(
            'aips_generate_scheduled_posts' => 'hourly',
            'aips_generate_author_topics' => 'hourly',
            'aips_generate_author_posts' => 'hourly',
            'aips_scheduled_research' => 'daily'
        );

        foreach ($crons as $hook => $schedule) {
            $logger->log("Checking cron: $hook");

            if (!wp_next_scheduled($hook)) {
                $logger->log("Cron '$hook' not scheduled. Scheduling now with schedule: '$schedule'.");

                wp_schedule_event(time(), $schedule, $hook);
                
                $next_run = wp_next_scheduled($hook);

                if ($next_run) {
                    $logger->log("Successfully scheduled '$hook'. Next run: " . date('Y-m-d H:i:s', $next_run));
                } else {
                    $logger->log("Failed to schedule '$hook'. wp_next_scheduled returned false.", 'error');
                }
            } else {
                $logger->log("Cron '$hook' is already scheduled.");
            }
        }
        
        flush_rewrite_rules();

        $logger->log('Plugin activation finished.');
    }
    
    public function check_upgrades() {
        AIPS_Upgrades::check_and_run();
    }
    
    public function deactivate() {
        wp_clear_scheduled_hook('aips_generate_scheduled_posts');
        wp_clear_scheduled_hook('aips_generate_author_topics');
        wp_clear_scheduled_hook('aips_generate_author_posts');
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
            new \AIPS\Controllers\Voices();
            new \AIPS\Controllers\Templates();
            new \AIPS\Controllers\TemplatesAjax();
            new \AIPS\Controllers\History();
            new AIPS_Planner();
            new \AIPS\Controllers\Schedule();
            new \AIPS\Controllers\Activity();
            new \AIPS\Controllers\Research();
            new AIPS_Seeder_Admin();
            new AIPS_Data_Management();
            // Structures admin controller (CRUD endpoints for Article Structures UI)
            new \AIPS\Controllers\Structures();
            // Prompt Sections admin controller (CRUD endpoints for Prompt Sections UI)
            new \AIPS\Controllers\PromptSections();
            
            // Authors feature controllers
            new \AIPS\Controllers\Authors();
            new \AIPS\Controllers\AuthorTopics();

            // Dev Tools
            if (get_option('aips_developer_mode')) {
                new AIPS_Dev_Tools();
            }
        }
        
        // Initialize schedulers (both admin and frontend)
        new AIPS_Scheduler();
        new AIPS_Author_Topics_Scheduler();
        new AIPS_Author_Post_Generator();
    }
}

function aips_init() {
    return AI_Post_Scheduler::get_instance();
}

add_action('plugins_loaded', 'aips_init', 5);

function aips_activate_callback() {
    AI_Post_Scheduler::get_instance()->activate();
}

register_activation_hook(__FILE__, 'aips_activate_callback');

function aips_deactivate_callback() {
    AI_Post_Scheduler::get_instance()->deactivate();
}

register_deactivation_hook(__FILE__, 'aips_deactivate_callback');
