<?php
/**
 * Plugin Name: AI Post Scheduler
 * Plugin URI: https://nunezserver.com/nunezscheduler
 * Description: Schedule AI-generated posts using advanced features & scheduling options.
 * Version: 2.4.1
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

// Capture the request start time as early as possible so AIPS_Telemetry
// can compute an accurate elapsed-time measurement.
if (!defined('AIPS_REQUEST_START')) {
    define('AIPS_REQUEST_START', microtime(true));
}

// Enable SAVEQUERIES as early as possible for telemetry-enabled requests so
// slow/duplicate query analysis can inspect the collected query log.
if (!defined('SAVEQUERIES') && function_exists('get_option') && get_option('aips_enable_telemetry', false)) {
    define('SAVEQUERIES', true);
}

if (!defined('AIPS_TELEMETRY_SLOW_QUERY_MS')) {
    define('AIPS_TELEMETRY_SLOW_QUERY_MS', 100);
}

if (!defined('AIPS_TELEMETRY_SLOW_REQUEST_MS')) {
    define('AIPS_TELEMETRY_SLOW_REQUEST_MS', 1500);
}

if (!defined('AIPS_TELEMETRY_QUERY_SAMPLE_LIMIT')) {
    define('AIPS_TELEMETRY_QUERY_SAMPLE_LIMIT', 10);
}

// Define plugin constants
if (!defined('AIPS_VERSION')) {
    define('AIPS_VERSION', '2.4.1');
}

if (!defined('AIPS_PLUGIN_DIR')) {
    define('AIPS_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (!defined('AIPS_PLUGIN_URL')) {
    define('AIPS_PLUGIN_URL', plugin_dir_url(__FILE__));
}

if (!defined('AIPS_PLUGIN_BASENAME')) {
    define('AIPS_PLUGIN_BASENAME', plugin_basename(__FILE__));
}

// Prompt-preview logging can expose generated content in logs. Off by default;
// opt-in by defining the constant to true earlier (e.g. in wp-config.php), or
// it will automatically enable when WP_DEBUG is true.
if (!defined('AIPS_AI_DEBUG_LOG_PROMPTS')) {
    define('AIPS_AI_DEBUG_LOG_PROMPTS', defined('WP_DEBUG') && WP_DEBUG);
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
        // Primary autoloader: Composer-generated classmap (O(1) hash lookup, no filesystem hits).
        $vendor_autoload = AIPS_PLUGIN_DIR . 'vendor/autoload.php';
        if ( file_exists( $vendor_autoload ) ) {
            require_once $vendor_autoload;
        }

        // Fallback shim: the legacy autoloader handles any AIPS_ class that the
        // Composer classmap does not resolve (e.g. on installs without a vendor/
        // directory or after adding a new class before re-running composer dump-autoload).
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
        $previously_installed = AIPS_Config::get_instance()->has_option('aips_db_version');
        $wizard_completed     = (bool) AIPS_Config::get_instance()->get_option('aips_onboarding_completed');

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
            if (!AIPS_Config::get_instance()->has_option($key)) {
                add_option($key, $value);
            }
        }
    }

    /**
     * Register initial container bindings for core singletons.
     *
     * Phase 1 registration as described in the container architecture plan:
     * Registers the most-duplicated singletons to validate the container works
     * correctly before more complex refactors.
     *
     * @return void
     */
    private function register_container_bindings() {
        $container = AIPS_Container::get_instance();

        // Register AIPS_Config (uses get_instance() instead of instance())
        $container->singleton(AIPS_Config::class, function( $container ) {
            return AIPS_Config::get_instance();
        });

        // Register AIPS_History_Repository
        $container->singleton(AIPS_History_Repository::class, function( $container ) {
            return AIPS_History_Repository::instance();
        });

		$container->singleton(AIPS_History_Repository_Interface::class, function( $container ) {
			return $container->make(AIPS_History_Repository::class);
		});

        // Register AIPS_History_Service
        $container->singleton(AIPS_History_Service::class, function( $container ) {
            return AIPS_History_Service::instance();
        });

		$container->singleton(AIPS_History_Service_Interface::class, function( $container ) {
			return $container->make(AIPS_History_Service::class);
		});

        // Register AIPS_Notifications_Repository
        $container->singleton(AIPS_Notifications_Repository::class, function( $container ) {
            return AIPS_Notifications_Repository::instance();
        });

        $container->singleton(AIPS_Notifications_Repository_Interface::class, function( $container ) {
            return $container->make(AIPS_Notifications_Repository::class);
        });

        $container->singleton(AIPS_Logger::class, function( $container ) {
            return AIPS_Logger::instance();
        });

        $container->singleton(AIPS_Logger_Interface::class, function( $container ) {
            return $container->make(AIPS_Logger::class);
        });

        $container->singleton(AIPS_AI_Service::class, function( $container ) {
            return AIPS_AI_Service::instance();
        });

        $container->singleton(AIPS_AI_Service_Interface::class, function( $container ) {
            return $container->make(AIPS_AI_Service::class);
        });

        $container->singleton(AIPS_Schedule_Repository::class, function( $container ) {
            return AIPS_Schedule_Repository::instance();
        });

        $container->singleton(AIPS_Schedule_Repository_Interface::class, function( $container ) {
            return $container->make(AIPS_Schedule_Repository::class);
        });

        $container->singleton(AIPS_Telemetry_Repository::class, function( $container ) {
            return AIPS_Telemetry_Repository::instance();
        });

        // Register AIPS_Template_Repository
        $container->singleton(AIPS_Template_Repository::class, function( $container ) {
            return AIPS_Template_Repository::instance();
        });
    }

    /**
     * Register thin lazy-resolving wp_ajax_* hooks for all actions in the registry.
     *
     * Each closure registered at priority 5 removes itself and then constructs the
     * correct controller (which registers its own handler at the default priority 10).
     * WordPress continues iterating priorities after priority 5 completes, so the
     * controller's handler at priority 10 fires automatically on the same request.
     *
     * This satisfies WordPress's requirement that wp_ajax_* hooks are added during
     * the init phase while deferring controller construction to request time, so
     * only one controller is constructed per AJAX request.
     *
     * Used as a fallback in boot_ajax() when an action is not found in the registry.
     *
     * @return void
     */
    private function register_lazy_ajax_hooks() {
        foreach (AIPS_Ajax_Registry::all_actions() as $action) {
            // $resolver is set to null first so the closure can capture it by reference
            // and call remove_action() on itself — PHP requires the variable to exist
            // before the closure is assigned.
            $resolver = null;
            $resolver = function() use ($action, &$resolver) {
                // Remove this resolver before constructing the controller so that
                // if do_action('wp_ajax_' . $action) is re-entered (e.g. via a
                // recursive call or test scaffolding) the closure does not fire twice.
                remove_action('wp_ajax_' . $action, $resolver, 5);

                $controller_class = AIPS_Ajax_Registry::get_controller_for($action);
                if ($controller_class && class_exists($controller_class)) {
                    // Intentionally not capturing the return value: each controller
                    // registers its own wp_ajax_{$action} handler at priority 10 as
                    // a constructor side-effect.  WordPress will invoke that handler
                    // as the next hook priority in this same wp_ajax_{$action} cycle.
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
     * Loads text domain, registers container bindings, and registers the
     * Source Group taxonomy. Called before any context-specific boot method.
     *
     * @return void
     */
    private function boot_common() {
        load_plugin_textdomain('ai-post-scheduler', false, dirname(AIPS_PLUGIN_BASENAME) . '/languages');

        // Register initial container bindings for core singletons.
        $this->register_container_bindings();

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
    }

    /**
     * Boot subsystems required only during WP-Cron execution.
     *
     * Registers cron hook callbacks as closures that resolve the singleton
     * instance at runtime (when WordPress fires the event). This means that
     * a cron request dispatched for, say, aips_generate_author_topics will only
     * ever instantiate AIPS_Author_Topics_Scheduler — the other scheduler
     * objects are never constructed unless their own hooks fire in the same run.
     *
     * Also boots the notification event handler (for generation-failure and quota
     * alerts fired from cron) and the partial-generation reconciler
     * (save_post fires when cron creates posts).
     *
     * @return void
     */
    private function boot_cron() {
        // Lazy-resolve the main template scheduler only when its hook fires.
        add_action('aips_generate_scheduled_posts', function() {
            AIPS_Scheduler::instance()->process();
        });
        add_filter('cron_schedules', function($schedules) {
            return AIPS_Scheduler::instance()->add_cron_intervals($schedules);
        });

        // Lazy-resolve the author-topics scheduler only when its hook fires.
        add_action('aips_generate_author_topics', function() {
            AIPS_Author_Topics_Scheduler::instance()->process_topic_generation();
        });

        // Lazy-resolve the author-post generator only when its hook fires.
        add_action('aips_generate_author_posts', function() {
            AIPS_Author_Post_Generator::instance()->process();
        });

        // Lazy-resolve the embeddings worker only when its hook fires.
        add_action('aips_process_author_embeddings', function($args) {
            AIPS_Embeddings_Cron::instance()->process_author_embeddings($args);
        }, 10, 1);

        // Research controller registers the aips_scheduled_research cron hook.
        new AIPS_Research_Controller();

        // Sources cron: fetch content for sources that have a fetch_interval configured.
        // AIPS_Sources_Cron::schedule() handles registering the cron event at the
        // correct recurrence (every_6_hours) during construction.
        AIPS_Sources_Cron::instance();

        // Notification event handler receives generation-failure/quota alerts from cron.
        new AIPS_Notifications();

        // Reconciler's save_post hook fires when cron creates or updates posts.
        new AIPS_Partial_Generation_State_Reconciler();

        // Internal Links indexing cron — construct the controller lazily only
        // when the cron hook fires to avoid eager instantiation on every cron boot.
        add_action('aips_index_posts_batch', function($args) {
            (new AIPS_Internal_Links_Controller())->process_indexing_batch_cron($args);
        }, 10, 1);
    }

    /**
     * Boot subsystems required only during an admin AJAX request.
     *
     * Resolves and instantiates only the single controller class mapped to the
     * current AJAX action in the registry.
     *
     * For plugin-owned actions (those starting with "aips_") that are not yet
     * registered in the registry, a lazy-resolving fallback is used so that
     * newly added controllers are still dispatched correctly.
     *
     * Non-plugin actions (from other plugins or WordPress core) are ignored
     * entirely — registering 100+ wp_ajax_* hooks for an action this plugin does
     * not own would be a performance regression rather than an improvement.
     *
     * @return void
     */
    private function boot_ajax() {
        $action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';

        $controller_class = AIPS_Ajax_Registry::get_controller_for($action);
        if ($controller_class && class_exists($controller_class)) {
            // Constructing the controller registers its wp_ajax_* hooks; WordPress
            // will dispatch the matching action automatically after init completes.
            new $controller_class();
            return;
        }

        // Only fall back to lazy-hook registration for plugin-owned actions that
        // are not yet in the registry (e.g. a newly added controller). Actions
        // from other plugins or WordPress core are ignored.
        if (strncmp($action, 'aips_', 5) === 0) {
            $this->register_lazy_ajax_hooks();
        }
    }

    /**
     * Boot subsystems required for admin (non-AJAX) page views.
     *
     * Registers the admin menu, enqueues assets, initializes settings and
     * onboarding, adds the admin toolbar node, and binds the notification event
     * handler and partial-generation reconciler. All page-specific AJAX
     * controllers are intentionally omitted here; they are resolved on demand
     * via boot_ajax() when an AJAX request arrives.
     *
     * @return void
     */
    private function boot_admin() {
        new AIPS_Admin_Menu();
        new AIPS_Admin_Assets();
        new AIPS_Settings();
        new AIPS_Onboarding_Wizard();

        // Toolbar node is visible on WP admin pages as well as the frontend.
        new AIPS_Admin_Bar();

        // Notification event handler listens for plugin-level events on admin pages.
        new AIPS_Notifications();

        // Reconciler's save_post hook fires on post-save actions initiated from admin.
        new AIPS_Partial_Generation_State_Reconciler();

        // Internal Links controller must be available globally so the admin-menu
        // render callback can call $controller->render_page() without reconstructing
        // the object (which would double-register all AJAX hooks).
        global $aips_internal_links_controller;
        $aips_internal_links_controller = new AIPS_Internal_Links_Controller();
    }

    /**
     * Boot subsystems required only for frontend (non-admin) page loads.
     *
     * Creates the admin toolbar node for users with manage_options capability.
     * No schedulers, controllers, or admin-only subsystems are instantiated here.
     *
     * @return void
     */
    private function boot_frontend() {
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

/**
 * Tell Query Monitor that files under the real (symlink-resolved) plugin
 * directory belong to this plugin, not WordPress Core.
 *
 * When the plugin directory is a symlink, PHP's debug_backtrace() returns the
 * real path (e.g. C:/Projects/.../ai-post-scheduler) which does not start with
 * WP_PLUGIN_DIR, so QM falls back to "WordPress Core".
 *
 * The three companion filters together register the resolved path and map the
 * custom key back to TYPE_PLUGIN with the correct plugin-slug context so that
 * QM shows "Plugin: ai-post-scheduler" in the Component column.
 */
add_filter('qm/component_dirs', function( array $dirs ) {
    $real = realpath( AIPS_PLUGIN_DIR );
    if ( false === $real ) {
        return $dirs;
    }

    $real = rtrim( str_replace( '\\', '/', $real ), '/' );

    // Compare against the canonical WordPress plugins path, not AIPS_PLUGIN_DIR.
    // In symlinked installs plugin_dir_path(__FILE__) can already be resolved.
    $expected = rtrim( str_replace( '\\', '/', WP_PLUGIN_DIR . '/ai-post-scheduler' ), '/' );

    // Only add when the real path differs from the canonical WP plugin path
    // (i.e. a symlink is in use). Using a namespaced key avoids overwriting
    // QM's built-in 'plugin' entry which is appended after this filter runs.
    if ( $real !== $expected ) {
        $dirs['plugin:ai-post-scheduler'] = $real;
    }

    return $dirs;
});

// Map the custom dir-key type back to TYPE_PLUGIN so QM shows the correct
// component class and name rather than falling into the "unknown" branch.
add_filter('qm/component_type/plugin:ai-post-scheduler', function() {
    return 'plugin';
});

// Supply the plugin slug as the context so the component name becomes
// "Plugin: ai-post-scheduler" instead of "Plugin: plugin:ai-post-scheduler".
add_filter('qm/component_context/plugin', function( $context, $file ) {
    $real = realpath( AIPS_PLUGIN_DIR );
    
    if ( false === $real || ! is_string( $file ) ) {
        return $context;
    }

    $real = rtrim( str_replace( '\\', '/', $real ), '/' );
    $file = str_replace( '\\', '/', $file );

    if ( 0 === strpos( $file, $real . '/' ) || $file === $real ) {
        return 'ai-post-scheduler';
    }

    return $context;
}, 10, 2);

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
