<?php
/**
 * Plugin Name: AI Post Scheduler
 * Plugin URI: https://nunezserver.com/nunezscheduler
 * Description: Schedule AI-generated posts using advanced features & scheduling options.
 * Version: 2.2.0
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
define('AIPS_VERSION', '2.2.0');
define('AIPS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AIPS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AIPS_PLUGIN_BASENAME', plugin_basename(__FILE__));

final class AI_Post_Scheduler {
    
    /**
     * @var AI_Post_Scheduler|null Singleton instance
     */
    private static $instance = null;

	/**
	 * @var array<string,object> Admin bootstrap instances keyed by class name.
	 */
	private $admin_instances = array();

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
   * Bootstrap admin-only services.
   *
   * Keeps globally-used admin services eager while lazy-loading page and AJAX
   * controllers only when the current request needs them.
   *
   * @return void
   */
  private function init_admin_runtime() {
    $current_page        = $this->get_current_admin_page();
    $current_ajax_action = $this->get_current_admin_ajax_action();

    foreach ($this->get_global_admin_classes() as $class_name) {
      $this->boot_admin_class($class_name);
    }

    if ($this->should_boot_onboarding_wizard($current_page, $current_ajax_action)) {
      $this->boot_admin_class('AIPS_Onboarding_Wizard');
    }

    foreach ($this->get_request_admin_classes($current_page, $current_ajax_action) as $class_name) {
      $this->boot_admin_class($class_name);
    }
  }

  /**
   * Get admin services that should be available on every admin request.
   *
   * @return array<int,string>
   */
  private function get_global_admin_classes() {
    return array(
      'AIPS_DB_Manager',
      'AIPS_Admin_Menu',
      'AIPS_Settings',
      'AIPS_Admin_Assets',
    );
  }

  /**
   * Determine whether the onboarding wizard should be bootstrapped.
   *
   * @param string $current_page Current admin page slug.
   * @param string $current_ajax_action Current AJAX action.
   * @return bool
   */
  private function should_boot_onboarding_wizard($current_page, $current_ajax_action) {
    if ($current_page === AIPS_Onboarding_Wizard::PAGE_SLUG) {
      return true;
    }

    if (strpos($current_ajax_action, 'aips_onboarding_') === 0) {
      return true;
    }

    return !(bool) get_option('aips_onboarding_completed', false)
      || (bool) get_transient('aips_onboarding_redirect');
  }

  /**
   * Resolve lazy-loaded admin classes for the current page or AJAX request.
   *
   * @param string $current_page Current admin page slug.
   * @param string $current_ajax_action Current AJAX action.
   * @return array<int,string>
   */
  private function get_request_admin_classes($current_page, $current_ajax_action) {
    $classes = array();

    // Page-map: only controllers that register page-load-time hooks (e.g., admin_enqueue_scripts).
    // Most other controllers only register AJAX hooks and are loaded on-demand when AJAX fires.
    // Note: Asset loading for all pages is now centralized in AIPS_Admin_Assets.
    $page_map = array();

    if (isset($page_map[$current_page])) {
      $classes = array_merge($classes, $page_map[$current_page]);
    }

    $ajax_map = array(
      'AIPS_Voices' => array(
        'aips_save_voice',
        'aips_delete_voice',
        'aips_get_voice',
        'aips_search_voices',
      ),
      'AIPS_Templates_Controller' => array(
        'aips_save_template',
        'aips_delete_template',
        'aips_get_template',
        'aips_test_template',
        'aips_clone_template',
        'aips_preview_template_prompts',
      ),
      'AIPS_History' => array(
        'aips_bulk_delete_history',
        'aips_clear_history',
        'aips_export_history',
        'aips_get_history_details',
        'aips_get_history_logs',
        'aips_reload_history',
        'aips_retry_generation',
      ),
      'AIPS_Post_Review' => array(
        'aips_get_draft_posts',
        'aips_publish_post',
        'aips_bulk_publish_posts',
        'aips_regenerate_post',
        'aips_delete_draft_post',
        'aips_bulk_delete_draft_posts',
        'aips_bulk_regenerate_posts',
        'aips_get_draft_post_preview',
      ),
      'AIPS_Planner' => array(
        'aips_generate_topics',
        'aips_bulk_schedule',
        'aips_bulk_generate_now',
      ),
      'AIPS_Schedule_Controller' => array(
        'aips_save_schedule',
        'aips_delete_schedule',
        'aips_toggle_schedule',
        'aips_run_now',
        'aips_bulk_delete_schedules',
        'aips_bulk_toggle_schedules',
        'aips_bulk_run_now_schedules',
        'aips_get_schedules_post_count',
        'aips_get_schedule_history',
        'aips_unified_run_now',
        'aips_unified_toggle',
        'aips_unified_bulk_toggle',
        'aips_unified_bulk_run_now',
        'aips_unified_bulk_delete',
        'aips_get_unified_schedule_history',
      ),
      'AIPS_Generated_Posts_Controller' => array(
        'aips_get_post_session',
        'aips_get_session_json',
        'aips_download_session_json',
      ),
      'AIPS_Research_Controller' => array(
        'aips_research_topics',
        'aips_get_trending_topics',
        'aips_delete_trending_topic',
        'aips_delete_trending_topic_bulk',
        'aips_schedule_trending_topics',
        'aips_generate_trending_topics_bulk',
        'aips_get_trending_topic_posts',
        'aips_perform_gap_analysis',
        'aips_generate_topics_from_gap',
      ),
      'AIPS_Seeder_Admin' => array(
        'aips_process_seeder',
      ),
      'AIPS_Data_Management' => array(
        'aips_export_data',
        'aips_import_data',
      ),
      'AIPS_Structures_Controller' => array(
        'aips_get_structures',
        'aips_get_structure',
        'aips_save_structure',
        'aips_delete_structure',
        'aips_set_structure_default',
        'aips_toggle_structure_active',
      ),
      'AIPS_Prompt_Sections_Controller' => array(
        'aips_get_prompt_sections',
        'aips_get_prompt_section',
        'aips_save_prompt_section',
        'aips_delete_prompt_section',
        'aips_toggle_prompt_section_active',
      ),
      'AIPS_Authors_Controller' => array(
        'aips_save_author',
        'aips_delete_author',
        'aips_get_author',
        'aips_get_author_topics',
        'aips_get_author_posts',
        'aips_get_author_feedback',
        'aips_generate_topics_now',
        'aips_get_topic_posts',
        'aips_suggest_authors',
      ),
      'AIPS_Author_Topics_Controller' => array(
        'aips_approve_topic',
        'aips_reject_topic',
        'aips_edit_topic',
        'aips_delete_topic',
        'aips_generate_post_from_topic',
        'aips_get_topic_logs',
        'aips_get_topic_feedback',
        'aips_bulk_approve_topics',
        'aips_bulk_reject_topics',
        'aips_bulk_delete_topics',
        'aips_bulk_generate_topics',
        'aips_bulk_delete_feedback',
        'aips_delete_generated_post',
        'aips_get_similar_topics',
        'aips_suggest_related_topics',
        'aips_compute_topic_embeddings',
        'aips_get_generation_queue',
        'aips_bulk_generate_from_queue',
        'aips_get_bulk_generate_estimate',
      ),
      'AIPS_Taxonomy_Controller' => array(
        'aips_get_taxonomy_items',
        'aips_generate_taxonomy',
        'aips_approve_taxonomy',
        'aips_reject_taxonomy',
        'aips_delete_taxonomy',
        'aips_bulk_approve_taxonomy',
        'aips_bulk_reject_taxonomy',
        'aips_bulk_delete_taxonomy',
        'aips_bulk_create_taxonomy_terms',
        'aips_create_taxonomy_term',
        'aips_search_posts',
      ),
      'AIPS_AI_Edit_Controller' => array(
        'aips_get_post_components',
        'aips_regenerate_component',
        'aips_regenerate_all_components',
        'aips_save_post_components',
        'aips_get_component_revisions',
        'aips_restore_component_revision',
      ),
      'AIPS_Calendar_Controller' => array(
        'aips_get_calendar_events',
      ),
      'AIPS_Sources_Controller' => array(
        'aips_get_sources',
        'aips_save_source',
        'aips_delete_source',
        'aips_toggle_source_active',
        'aips_get_source_groups',
        'aips_save_source_group',
        'aips_delete_source_group',
      ),
    );

    if (get_option('aips_developer_mode')) {
      $ajax_map['AIPS_Dev_Tools'] = array(
        'aips_generate_scaffold',
      );
    }

    foreach ($ajax_map as $class_name => $actions) {
      if (in_array($current_ajax_action, $actions, true)) {
        $classes[] = $class_name;
      }
    }

    return array_values(array_unique($classes));
  }

  /**
   * Instantiate an admin class once per request.
   *
   * @param string $class_name Fully-qualified class name.
   * @return object
   */
  private function boot_admin_class($class_name) {
    if (isset($this->admin_instances[$class_name])) {
      return $this->admin_instances[$class_name];
    }

    $this->admin_instances[$class_name] = new $class_name();

    if ('AIPS_Post_Review' === $class_name) {
      global $aips_post_review_handler;
      $aips_post_review_handler = $this->admin_instances[$class_name];
    }

    return $this->admin_instances[$class_name];
  }

  /**
   * Get the current admin page slug.
   *
   * @return string
   */
  private function get_current_admin_page() {
    return isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
  }

  /**
   * Get the current admin AJAX action.
   *
   * @return string
   */
  private function get_current_admin_ajax_action() {
    if (!wp_doing_ajax()) {
      return '';
    }

    return isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';
  }

    /**
     * Run scheduled research through a lazily-instantiated controller.
     *
     * @return void
     */
    public function handle_scheduled_research() {
        $controller = new AIPS_Research_Controller();
        $controller->run_scheduled_research();
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
			$this->init_admin_runtime();
        }
        
        // Initialize schedulers (both admin and frontend)
        $aips_scheduler = new AIPS_Scheduler();
        add_action('aips_generate_scheduled_posts', array($aips_scheduler, 'process'));
        add_filter('cron_schedules', array($aips_scheduler, 'add_cron_intervals'));
        add_action('aips_scheduled_research', array($this, 'handle_scheduled_research'));

        $aips_author_topics_scheduler = new AIPS_Author_Topics_Scheduler();
        add_action('aips_generate_author_topics', array($aips_author_topics_scheduler, 'process_topic_generation'));

        $aips_author_post_generator = new AIPS_Author_Post_Generator();
        add_action('aips_generate_author_posts', array($aips_author_post_generator, 'process'));

        // Embeddings background worker
        $aips_embeddings_cron = new AIPS_Embeddings_Cron();
        add_action('aips_process_author_embeddings', array($aips_embeddings_cron, 'process_author_embeddings'));

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
