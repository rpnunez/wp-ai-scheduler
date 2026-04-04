<?php
/**
 * Plugin Name: AI Post Scheduler
 * Plugin URI: https://nunezserver.com/nunezscheduler
 * Description: Schedule AI-generated posts using advanced features & scheduling options.
 * Version: 2.1.0
 * Author: Raymond Nunez
 * Author URI: https://nunezserver.com
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

// Define plugin constants
define('AIPS_VERSION', '2.1.0');
define('AIPS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AIPS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AIPS_PLUGIN_BASENAME', plugin_basename(__FILE__));

final class AI_Post_Scheduler {
    
    /**
     * @var AI_Post_Scheduler|null Singleton instance
     */
    private static $instance = null;

    /**
     * Get plugin cron definitions.
     *
     * @return array<string,array<string,string>>
     */
    public static function get_cron_events() {
        return array(
            'aips_generate_scheduled_posts' => array(
                'schedule' => 'hourly',
                'label'   => __( 'Post Generation', 'ai-post-scheduler' ),
            ),
            'aips_generate_author_topics' => array(
                'schedule' => 'hourly',
                'label'   => __( 'Author Topic Generation', 'ai-post-scheduler' ),
            ),
            'aips_generate_author_posts' => array(
                'schedule' => 'hourly',
                'label'   => __( 'Author Post Generation', 'ai-post-scheduler' ),
            ),
            'aips_scheduled_research' => array(
                'schedule' => 'daily',
                'label'   => __( 'Automated Research', 'ai-post-scheduler' ),
            ),
            'aips_notification_rollups' => array(
                'schedule' => 'daily',
                'label'   => __( 'Notification Rollups', 'ai-post-scheduler' ),
            ),
            'aips_cleanup_export_files' => array(
                'schedule' => 'daily',
                'label'   => __( 'Export Cleanup', 'ai-post-scheduler' ),
            ),
        );
    }

    /**
     * Get the singleton instance.
     *
     * @return AI_Post_Scheduler
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construct the plugin bootstrap instance.
     *
     * Registers dependency checks, includes, and runtime hooks.
     */
    private function __construct() {
        $this->check_dependencies();
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Register dependency checks for required runtime plugins.
     *
     * @return void
     */
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

    /**
     * Load required bootstrap files.
     *
     * @return void
     */
    private function includes() {
        // Register autoloader
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-autoloader.php';
        AIPS_Autoloader::register();

        // Helpers
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-admin-menu-helper.php';
    }

    /**
     * Register plugin lifecycle and runtime hooks.
     *
     * @return void
     */
    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'check_upgrades'));
        add_action('init', array($this, 'init'));
    }

    /**
     * Handle plugin activation tasks.
     *
     * Seeds default options, runs upgrades/table checks, schedules cron events,
     * and applies onboarding redirect logic for first-time installs.
     *
     * @return void
     */
    public function activate() {
        // Ensure logger is available
        if (!class_exists('AIPS_Logger')) {
            require_once AIPS_PLUGIN_DIR . 'includes/class-aips-logger.php';
        }

        $logger = new AIPS_Logger();

        $logger->log('Running plugin activation.');

        // Detect a prior installation before set_default_options() writes defaults.
        $previously_installed = get_option('aips_db_version') !== false;
        $wizard_completed     = (bool) get_option('aips_onboarding_completed', false);

        $this->set_default_options();

        if ($previously_installed || $wizard_completed) {
            // Plugin already had data or the wizard was already run — mark it
            // complete so the wizard never resurfaces on future reactivations.
            if (!$wizard_completed) {
                update_option('aips_onboarding_completed', 1, false);
            }
        } else {
            // Fresh install: redirect admins to the onboarding wizard once.
            set_transient('aips_onboarding_redirect', 1, MINUTE_IN_SECONDS * 10);
        }
        $this->check_upgrades();

        // Ensure tables exist even if version matches (e.g. re-activation after manual deletion or partial install)
        $install_result = AIPS_DB_Manager::install_tables();

        if (is_wp_error($install_result)) {
            $logger->log('Table installation failed during activation: ' . $install_result->get_error_message(), 'error');

            $notifications = class_exists('AIPS_Notifications') ? new AIPS_Notifications() : null;
            if ($notifications instanceof AIPS_Notifications) {
                $notifications->system_error(array(
                    'title'         => __('Database installation failed', 'ai-post-scheduler'),
                    'error_code'    => $install_result->get_error_code(),
                    'error_message' => $install_result->get_error_message(),
                    'url'           => admin_url('admin.php?page=aips-status'),
                    'dedupe_key'    => 'db_install_failed_activation',
                    'dedupe_window' => 1800,
                ));
            }
        }
        
        $crons = self::get_cron_events();

        foreach ($crons as $hook => $cron_config) {
            $schedule = $cron_config['schedule'];
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

    /**
     * Run versioned upgrade checks.
     *
     * @return void
     */
    public function check_upgrades() {
        AIPS_Upgrades::check_and_run();
    }

    /**
     * Handle plugin deactivation cleanup.
     *
     * @return void
     */
    public function deactivate() {
        foreach (array_keys(self::get_cron_events()) as $hook) {
            wp_clear_scheduled_hook($hook);
        }
        flush_rewrite_rules();
    }

    /**
     * Seed plugin options with default values when missing.
     *
     * Uses AIPS_Config defaults as the source of truth and adds activation-
     * specific values where required.
     *
     * @return void
     */
    private function set_default_options() {
        $defaults = AIPS_Config::get_instance()->get_default_options();

        // Activation-specific fallback: if unset in defaults, use admin email.
        if (empty($defaults['aips_review_notifications_email'])) {
            $defaults['aips_review_notifications_email'] = get_option('admin_email');
        }

        // Keep DB version initialization during activation.
        $defaults['aips_db_version'] = AIPS_VERSION;
        
        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }

    /**
     * Initialize plugin runtime.
     *
     * Loads translations, registers taxonomy, instantiates admin controllers,
     * and boots scheduler/services used in all contexts.
     *
     * @return void
     */
    public function init() {
        load_plugin_textdomain('ai-post-scheduler', false, dirname(AIPS_PLUGIN_BASENAME) . '/languages');

        // Register the Source Group taxonomy (not attached to any post type).
        register_taxonomy(
            'aips_source_group',
            array(),
            array(
                'labels'            => array(
                    'name'              => __('Source Groups', 'ai-post-scheduler'),
                    'singular_name'     => __('Source Group', 'ai-post-scheduler'),
                    'add_new_item'      => __('Add New Source Group', 'ai-post-scheduler'),
                    'edit_item'         => __('Edit Source Group', 'ai-post-scheduler'),
                    'new_item'          => __('New Source Group', 'ai-post-scheduler'),
                    'not_found'         => __('No source groups found.', 'ai-post-scheduler'),
                ),
                'hierarchical'      => false,
                'show_ui'           => false,
                'show_in_nav_menus' => false,
                'show_in_rest'      => false,
                'public'            => false,
                'rewrite'           => false,
                'query_var'         => false,
            )
        );
        
        if (is_admin()) {
            new AIPS_DB_Manager();
            new AIPS_Admin_Menu();
            new AIPS_Settings();
            new AIPS_Onboarding_Wizard();
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

            // AI Edit + Calendar controllers (AJAX endpoints)
            new AIPS_AI_Edit_Controller();
            new AIPS_Calendar_Controller();
            // Sources controller (AJAX endpoints for trusted sources management)
            new AIPS_Sources_Controller();
            // Dev Tools
            if (get_option('aips_developer_mode')) {
                new AIPS_Dev_Tools();
            }
        }

        // Internal Links controller must be available on cron and frontend contexts.
        global $aips_internal_links_controller;
        $aips_internal_links_controller = new AIPS_Internal_Links_Controller();
        
        // Initialize schedulers (both admin and frontend)
        $aips_scheduler = new AIPS_Scheduler();
        add_action('aips_generate_scheduled_posts', array($aips_scheduler, 'process'));
        add_filter('cron_schedules', array($aips_scheduler, 'add_cron_intervals'));

        $aips_author_topics_scheduler = new AIPS_Author_Topics_Scheduler();
        add_action('aips_generate_author_topics', array($aips_author_topics_scheduler, 'process_topic_generation'));

        $aips_author_post_generator = new AIPS_Author_Post_Generator();
        add_action('aips_generate_author_posts', array($aips_author_post_generator, 'process'));
        new AIPS_Notifications();
		new AIPS_Partial_Generation_State_Reconciler();

        // Admin toolbar (visible on both admin and frontend for users with manage_options)
        new AIPS_Admin_Bar();
    }
}

/**
 * Initialize and return the plugin singleton.
 *
 * @return AI_Post_Scheduler
 */
function aips_init() {
    return AI_Post_Scheduler::get_instance();
}

add_action('plugins_loaded', 'aips_init', 5);

// Backward-compatibility alias: old review hook now triggers the rollup hook.
add_action('aips_send_review_notifications', function() {
    do_action('aips_notification_rollups');
}, 1);

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

    do_action('aips_export_cleanup_completed', array(
        'deleted' => isset($result['deleted']) ? (int) $result['deleted'] : 0,
        'errors'  => isset($result['errors']) && is_array($result['errors']) ? count($result['errors']) : 0,
    ));
	
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

/**
 * Activation hook callback.
 *
 * @return void
 */
function aips_activate_callback() {
    AI_Post_Scheduler::get_instance()->activate();
}

register_activation_hook(__FILE__, 'aips_activate_callback');

/**
 * Deactivation hook callback.
 *
 * @return void
 */
function aips_deactivate_callback() {
    AI_Post_Scheduler::get_instance()->deactivate();
}

register_deactivation_hook(__FILE__, 'aips_deactivate_callback');
