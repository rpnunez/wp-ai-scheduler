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
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-autoloader.php';
        AIPS_Autoloader::register();
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
            'aips_scheduled_research' => 'daily',
            'aips_send_review_notifications' => 'daily',
            'aips_cleanup_export_files' => 'daily'
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
        wp_clear_scheduled_hook('aips_send_review_notifications');
        wp_clear_scheduled_hook('aips_cleanup_export_files');
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
            'aips_review_notifications_enabled' => 0,
            'aips_review_notifications_email' => get_option('admin_email'),
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
            new AIPS_Admin_Assets();
            new AIPS_Voices();
            new AIPS_Templates();
            new AIPS_Templates_Controller();
            new AIPS_History();
            
            // Initialize Post Review handler globally to avoid duplicate AJAX registration
            global $aips_post_review_handler;
            $aips_post_review_handler = new AIPS_Post_Review();
            
            new AIPS_Planner();
            new AIPS_Schedule_Controller();
            new AIPS_Generated_Posts_Controller();
            new AIPS_Research_Controller();
            new AIPS_Seeder_Admin();
            new AIPS_Data_Management();
            // Structures admin controller (CRUD endpoints for Article Structures UI)
            new AIPS_Structures_Controller();
            // Prompt Sections admin controller (CRUD endpoints for Prompt Sections UI)
            new AIPS_Prompt_Sections_Controller();
            
            // Authors feature controllers
            new AIPS_Authors_Controller();
            new AIPS_Author_Topics_Controller();
            
            // AI Edit controller (for component-level regeneration)
            new AIPS_AI_Edit_Controller();

            // Calendar controller
            new AIPS_Calendar_Controller();

            // Dev Tools
            if (get_option('aips_developer_mode')) {
                new AIPS_Dev_Tools();
            }
        }
        
        // Initialize schedulers (both admin and frontend)
        new AIPS_Scheduler();
        new AIPS_Author_Topics_Scheduler();
        new AIPS_Author_Post_Generator();
        new AIPS_Post_Review_Notifications();
    }
}

function aips_init() {
    return AI_Post_Scheduler::get_instance();
}

add_action('plugins_loaded', 'aips_init', 5);

// Register cleanup cron handler
add_action('aips_cleanup_export_files', 'aips_cleanup_export_files_handler');

/**
 * Cron handler to clean up old export files
 * 
 * Runs daily to remove session export files older than 24 hours.
 */
function aips_cleanup_export_files_handler() {
	// Clean up files older than 24 hours (86400 seconds)
	$result = AIPS_Session_To_JSON::cleanup_old_exports(86400);
	
	// Log the cleanup results
	if (class_exists('AIPS_Logger')) {
		$logger = new AIPS_Logger();
		$logger->log(sprintf(
			'Export files cleanup completed. Deleted: %d files. Errors: %d',
			$result['deleted'],
			count($result['errors'])
		));
		
		if (!empty($result['errors'])) {
			foreach ($result['errors'] as $error) {
				$logger->log('Export cleanup error: ' . $error);
			}
		}
	}
}

function aips_activate_callback() {
    AI_Post_Scheduler::get_instance()->activate();
}

register_activation_hook(__FILE__, 'aips_activate_callback');

function aips_deactivate_callback() {
    AI_Post_Scheduler::get_instance()->deactivate();
}

register_deactivation_hook(__FILE__, 'aips_deactivate_callback');
