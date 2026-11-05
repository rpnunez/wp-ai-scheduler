<?php
/**
 * Plugin Name: AI Post Scheduler
 * Plugin URI: https://nunezserver.com/nunezscheduler
 * Version: 3.7.19
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

// Load runtime constants, telemetry thresholds, and versioning.
require_once __DIR__ . '/config.php';

// Define plugin filesystem and URL paths.
if (!defined('AIPS_PLUGIN_DIR')) {
    define('AIPS_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (!defined('AIPS_PLUGIN_URL')) {
    define('AIPS_PLUGIN_URL', plugin_dir_url(__FILE__));
}

if (!defined('AIPS_PLUGIN_BASENAME')) {
    define('AIPS_PLUGIN_BASENAME', plugin_basename(__FILE__));
}

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
        $events = array(
            'aips_generate_scheduled_posts' => array(
                'schedule' => 'hourly',
                'label'    => __( 'Post Generation', 'ai-post-scheduler' ),
            ),
            'aips_generate_author_topics' => array(
                'schedule' => 'hourly',
                'label'    => __( 'Author Topic Generation', 'ai-post-scheduler' ),
            ),
            'aips_generate_author_posts' => array(
                'schedule' => 'hourly',
                'label'    => __( 'Author Post Generation', 'ai-post-scheduler' ),
            ),
            'aips_scheduled_research' => array(
                'schedule' => 'daily',
                'label'    => __( 'Automated Research', 'ai-post-scheduler' ),
            ),
            'aips_notification_rollups' => array(
                'schedule' => 'daily',
                'label'    => __( 'Notification Rollups', 'ai-post-scheduler' ),
            ),
            'aips_cleanup_export_files' => array(
                'schedule' => 'daily',
                'label'    => __( 'Export Cleanup', 'ai-post-scheduler' ),
            ),
            'aips_cleanup_bulk_batch_jobs' => array(
                'schedule' => 'daily',
                'label'    => __( 'Bulk Batch Job Cleanup', 'ai-post-scheduler' ),
            ),
            'aips_cache_monitor_maintenance' => array(
                'schedule' => 'daily',
                'label'    => __( 'Cache Monitor Maintenance', 'ai-post-scheduler' ),
            ),
        );

        // Only include the prune cleanup cron while the feature is actually enabled.
        if ( (bool) AIPS_Config::get_instance()->get_option( 'aips_auto_prune_enabled' ) ) {
            $events['aips_database_prune_cleanup'] = array(
                'schedule' => sanitize_key( (string) AIPS_Config::get_instance()->get_option( 'aips_telemetry_prune_interval', 'daily' ) ),
                'label'    => __( 'Database Prune & Retention Cleanup', 'ai-post-scheduler' ),
            );
        }

        return $events;
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
            // The plugin needs at least one AI backend: the Meow Apps AI Engine
            // plugin OR a ready native WordPress AI Client text-generation connector.
            if (class_exists('AIPS_AI_Provider_Factory') && !AIPS_AI_Provider_Factory::has_available_provider()) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error"><p>';
                    echo esc_html__('AI Post Scheduler requires a configured AI provider. Activate Meow Apps AI Engine, or configure credentials for an active WordPress AI Client connector (WordPress 7.0+).', 'ai-post-scheduler');
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
        // Primary autoloader: Composer-generated classmap (O(1) hash lookup, no filesystem hits).
        $vendor_autoload = AIPS_PLUGIN_DIR . 'vendor/autoload.php';

        if ( file_exists( $vendor_autoload ) ) {
            require_once $vendor_autoload;
        }

        // Fallback shim: handles any AIPS_ class that Composer classmap does not resolve.
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-autoloader.php';
        AIPS_Autoloader::register();

        // Helpers
        require_once AIPS_PLUGIN_DIR . 'includes/class-aips-admin-menu-helper.php';

        // Query Monitor integration
        AIPS_Query_Monitor::init();
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
        $previously_installed = AIPS_Config::get_instance()->has_option('aips_db_version');
        $wizard_completed     = (bool) AIPS_Config::get_instance()->get_option('aips_onboarding_completed');

        $this->set_default_options();

        if ($previously_installed || $wizard_completed) {
            if (!$wizard_completed) {
                update_option('aips_onboarding_completed', 1, false);
            }
        } else {
            set_transient('aips_onboarding_redirect', 1, MINUTE_IN_SECONDS * 10);
        }
        $this->check_upgrades();

        // Ensure tables exist even if version matches.
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

        AIPS_Sources_Cron::instance()->schedule();
        AIPS_Sources_Maintenance::instance();
        flush_rewrite_rules();

        $logger->log('Plugin activation finished.');
    }

    /**
     * Run versioned upgrade checks.
     *
     * @return void
     */
    public function check_upgrades() {
        AIPS_DB_Migrations::check_and_run();
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
        foreach (array('aips_fetch_sources', 'aips_fetch_sources_continue', 'aips_sources_archive_maintenance', 'aips_sources_archive_maintenance_continue') as $hook) {
            wp_clear_scheduled_hook($hook);
        }
        wp_unschedule_hook(AIPS_Schedule_Processor::QUEUED_DUE_SCHEDULE_HOOK);
        flush_rewrite_rules();
    }

    /**
     * Seed plugin options with default values when missing.
     *
     * @return void
     */
    private function set_default_options() {
        $defaults = AIPS_Config::get_instance()->get_default_options();

        if (empty($defaults['aips_review_notifications_email'])) {
            $defaults['aips_review_notifications_email'] = get_option('admin_email');
        }

        $defaults['aips_db_version'] = AIPS_VERSION;

        foreach ($defaults as $key => $value) {
            if (!AIPS_Config::get_instance()->has_option($key)) {
                add_option($key, $value);
            }
        }
    }

    /**
     * Register initial container bindings for core singletons.
     *
     * Delegates to AIPS_Container_Bindings for modular dependency registration.
     * Public for test suite container reset compatibility.
     *
     * @return void
     */
    public function register_container_bindings() {
        AIPS_Container_Bindings::register();
    }

    /**
     * Register thin lazy-resolving wp_ajax_* hooks for all actions in the registry.
     *
     * @return void
     */
    private function register_lazy_ajax_hooks() {
        foreach (AIPS_Ajax_Registry::all_actions() as $action) {
            $resolver = null;
            $resolver = function() use ($action, &$resolver) {
                remove_action('wp_ajax_' . $action, $resolver, 5);

                $controller_class = AIPS_Ajax_Registry::get_controller_for($action);
                if ($controller_class && class_exists($controller_class)) {
                    new $controller_class();
                }
            };
            add_action('wp_ajax_' . $action, $resolver, 5);
        }
    }

    /**
     * Initialize plugin runtime.
     *
     * Dispatches to the appropriate context-specific boot method based on the
     * current request type, ensuring only the subsystems required for that
     * context are instantiated.
     *
     * @return void
     */
    public function init() {
        $this->boot_common();

        if (wp_doing_cron()) {
            $this->boot_cron();
        } elseif (wp_doing_ajax()) {
            $this->boot_ajax();
        } elseif (is_admin()) {
            $this->boot_admin();
        } else {
            $this->boot_frontend();
        }
    }

    /**
     * Boot subsystems required in every request context.
     *
     * @return void
     */
    private function boot_common() {
        load_plugin_textdomain('ai-post-scheduler', false, dirname(AIPS_PLUGIN_BASENAME) . '/languages');

        // Register initial container bindings for core singletons.
        $this->register_container_bindings();

        // Reconcile source scheduling in every request, including upgrades without activation.
        AIPS_Sources_Cron::instance();
        AIPS_Sources_Maintenance::instance();

        // Boot request-level telemetry if the option is enabled.
        if (AIPS_Config::get_instance()->get_option('aips_enable_telemetry')) {
            AIPS_Telemetry::instance()->boot();
        }

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

        // Integration bridge listeners.
        add_action('aips_post_generated', function ($post_id, $template_or_context, $history_id, $context) {
            (new AIPS_Integration_Manager())->handle_post_generated($post_id, $template_or_context, $history_id, $context);
        }, 10, 4);
        add_action('aips_template_changed', function ($args) {
            (new AIPS_Integration_Manager())->handle_template_deleted($args);
        });

        // Continuous semantic indexing: automatically index published posts and refresh relationships.
        add_action('save_post', function ($post_id, $post) {
            if (!is_object($post) || !isset($post->post_status)) {
                return;
            }
            AIPS_Container::get_instance()->make(AIPS_Content_Indexer_Service::class)->on_post_save($post_id, $post);
        }, 10, 2);

        // Link index save and delete listeners.
        add_action('save_post', function ($post_id, $post) {
            if (!is_object($post) || !isset($post->post_status)) {
                return;
            }
            AIPS_Container::get_instance()->make(AIPS_Link_Index_Service::class)->on_post_save($post_id, $post);
        }, 20, 2);

        add_action('before_delete_post', function ($post_id) {
            AIPS_Container::get_instance()->make(AIPS_Link_Index_Service::class)->on_before_delete_post($post_id);
            AIPS_Container::get_instance()->make(AIPS_Link_Click_Tracking_Service::class)->on_before_delete_post($post_id);
        });

        // Silos: keep the link index and guide cache in step with pillar/cluster changes.
        AIPS_Container::get_instance()->make(AIPS_Silo_Service::class)->register_common_hooks();

        // Generation-time linking.
        add_action('transition_post_status', function ($new_status, $old_status, $post) {
            if ($new_status === 'publish' && $old_status !== 'publish') {
                AIPS_Container::get_instance()->make(AIPS_Publish_Linking_Service::class)->on_transition($new_status, $old_status, $post);
            }
        }, 20, 3);

        add_action('aips_post_generated', function ($post_id) {
            AIPS_Container::get_instance()->make(AIPS_Publish_Linking_Service::class)->on_generated($post_id);
        }, 20);

        add_action('save_post', function ($post_id, $post) {
            AIPS_Container::get_instance()->make(AIPS_Publish_Linking_Service::class)->on_saved($post_id, $post);
        }, 20, 2);

        add_action('aips_autolink_run_undone', function ($run) {
            AIPS_Container::get_instance()->make(AIPS_Publish_Linking_Service::class)->on_run_undone($run);
        });

        // Process pending background indexing queue.
        add_action('aips_process_pending_indexer_queue', function () {
            AIPS_Container::get_instance()->make(AIPS_Content_Indexer_Service::class)->process_pending_indexer_queue();
        });

        // Background processes.
        add_action(AIPS_Managed_Background_Process::TICK_HOOK, function ($process_key) {
            AIPS_Container::get_instance()->make(AIPS_Background_Process_Manager::class)->tick((string) $process_key);
        }, 10, 1);

        add_filter('heartbeat_received', function ($response, $data) {
            if (empty($data['aips_bg'])) {
                return $response;
            }
            return AIPS_Container::get_instance()->make(AIPS_Background_Process_Manager::class)->on_heartbeat_received($response, $data);
        }, 10, 2);

        // Related Posts Frontend integration.
        new AIPS_Related_Posts_Frontend(
            AIPS_Container::get_instance()->make(AIPS_Related_Posts_Service::class)
        );
    }

    /**
     * Boot subsystems required only during WP-Cron execution.
     *
     * Delegates hook registration and strategy configuration to AIPS_Cron_Bootstrapper.
     *
     * @return void
     */
    private function boot_cron() {
        AIPS_Cron_Bootstrapper::boot();
    }

    /**
     * Boot subsystems required only during an admin AJAX request.
     *
     * @return void
     */
    private function boot_ajax() {
        $action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';

        $controller_class = AIPS_Ajax_Registry::get_controller_for($action);
        if ($controller_class && class_exists($controller_class)) {
            new $controller_class();
            return;
        }

        if (strncmp($action, 'aips_', 5) === 0) {
            $this->register_lazy_ajax_hooks();
        }
    }

    /**
     * Boot subsystems required for admin (non-AJAX) page views.
     *
     * @return void
     */
    private function boot_admin() {
        new AIPS_Admin_Menu();
        new AIPS_Admin_Assets();
        new AIPS_Settings();
        new AIPS_Onboarding_Wizard();
        new AIPS_Admin_Bar();
        new AIPS_Notifications();
        new AIPS_Partial_Generation_State_Reconciler();
        new AIPS_Post_History_UI();
        new AIPS_Internal_Links_Editor_Panel();

        (new AIPS_Duplicate_Group_Service())->ensure_schedule();
        AIPS_Container::get_instance()->make(AIPS_GSC_Keywords_Service::class)->ensure_schedule();

        global $aips_internal_links_controller;
        $aips_internal_links_controller = new AIPS_Internal_Links_Controller();

        if ( AIPS_Config::get_instance()->get_option('aips_developer_mode') ) {
            new AIPS_Seeder_Admin();
        }

        new AIPS_Post_Insights_Controller();
    }

    /**
     * Boot subsystems required only for frontend (non-admin) page loads.
     *
     * @return void
     */
    private function boot_frontend() {
        new AIPS_Admin_Bar();

        add_filter('the_content', function ($content) {
            return AIPS_Container::get_instance()->make(AIPS_Link_Rules_Service::class)->filter_content($content);
        }, 9);

        AIPS_Container::get_instance()->make(AIPS_Silo_Service::class)->register_frontend_hooks();

        add_action('template_redirect', function () {
            AIPS_Container::get_instance()->make(AIPS_Redirects_Service::class)->maybe_redirect();
        }, 1);

        AIPS_Container::get_instance()->make(AIPS_Link_Click_Tracking_Service::class)->register_frontend();
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
