<?php
/**
 * Plugin Name: AI Post Scheduler
 * Plugin URI: https://example.com/ai-post-scheduler
 * Description: Schedule AI-generated posts using Meow Apps AI Engine
 * Version: 1.5.0
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

define('AIPS_VERSION', '1.5.0');
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
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-settings.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-voices.php';
        
        // Repository layer
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-history-repository.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-schedule-repository.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-template-repository.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-article-structure-repository.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-prompt-section-repository.php';
        
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-templates.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-template-processor.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-article-structure-manager.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-template-type-selector.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-interval-calculator.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-resilience-service.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-ai-service.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-image-service.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-generation-session.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-post-creator.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-generator.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-scheduler.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-schedule-controller.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-planner.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-history.php';
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-system-status.php';
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
        
        if (!wp_next_scheduled('aips_generate_scheduled_posts')) {
            wp_schedule_event(time(), 'hourly', 'aips_generate_scheduled_posts');
        }
        
        flush_rewrite_rules();
    }
    
    public function check_upgrades() {
        AIPS_Upgrades::check_and_run();
    }
    
    public function deactivate() {
        wp_clear_scheduled_hook('aips_generate_scheduled_posts');
        flush_rewrite_rules();
    }
    
    
    private function set_default_options() {
        $defaults = array(
            'aips_default_post_status' => 'draft',
            'aips_default_category' => 0,
            'aips_enable_logging' => 1,
            'aips_max_retries' => 3,
            'aips_ai_model' => '',
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
            new AIPS_History();
            new AIPS_Planner();
            new AIPS_Schedule_Controller();
        }
        
        new AIPS_Scheduler();
    }
}

function aips_init() {
    return AI_Post_Scheduler::get_instance();
}

add_action('plugins_loaded', 'aips_init', 5);
