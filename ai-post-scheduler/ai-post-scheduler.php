<?php
/**
 * Plugin Name: AI Post Scheduler
 * Plugin URI: https://nunezserver.com/nunezscheduler
 * Description: Schedule AI-generated posts using advanced features & scheduling options.
 * Version: 3.6.6
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

require_once __DIR__ . '/config.php';

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
        return AIPS_Lifecycle::get_cron_events();
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
     * @return void
     */
    public function activate() {
        AIPS_Lifecycle::activate();
    }

    /**
     * Run versioned upgrade checks.
     *
     * @return void
     */
    public function check_upgrades() {
        AIPS_Lifecycle::check_upgrades();
    }

    /**
     * Handle plugin deactivation cleanup.
     *
     * @return void
     */
    public function deactivate() {
        AIPS_Lifecycle::deactivate();
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

        $container->singleton(AIPS_AI_Provider_Interface::class, function( $container ) {
            return AIPS_AI_Provider_Factory::create();
        });

        $container->singleton(AIPS_AI_Service::class, function( $container ) {
            return new AIPS_AI_Service(
                provider: $container->make(AIPS_AI_Provider_Interface::class)
            );
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

        // Register AIPS_System_Diagnostics_Service
        $container->singleton(AIPS_System_Diagnostics_Service::class, function( $container ) {
            return new AIPS_System_Diagnostics_Service();
        });

        // Register AIPS_System_Status_Diagnostics_Service
        $container->singleton(AIPS_System_Status_Diagnostics_Service::class, function( $container ) {
            return new AIPS_System_Status_Diagnostics_Service();
        });

        // Register AIPS_Embeddings_Repository
        $container->singleton(AIPS_Embeddings_Repository::class, function( $container ) {
            return new AIPS_Embeddings_Repository();
        });

        // Register AIPS_Relationships_Repository
        $container->singleton(AIPS_Relationships_Repository::class, function( $container ) {
            return new AIPS_Relationships_Repository();
        });

        // Register AIPS_Embeddings_Service
        $container->singleton(AIPS_Embeddings_Service::class, function( $container ) {
            return new AIPS_Embeddings_Service(
                $container->make(AIPS_AI_Service_Interface::class),
                $container->make(AIPS_Logger_Interface::class)
            );
        });

        // Register AIPS_Content_Indexer_Service
        $container->singleton(AIPS_Content_Indexer_Service::class, function( $container ) {
            return new AIPS_Content_Indexer_Service(
                $container->make(AIPS_Embeddings_Repository::class),
                $container->make(AIPS_Relationships_Repository::class),
                $container->make(AIPS_Embeddings_Service::class),
                $container->make(AIPS_History_Service_Interface::class),
                $container->make(AIPS_Logger_Interface::class),
                $container->make(AIPS_Config::class),
                $container->has(AIPS_Author_Topics_Repository::class) ? $container->make(AIPS_Author_Topics_Repository::class) : new AIPS_Author_Topics_Repository()
            );
        });

        // Register AIPS_Related_Posts_Service
        $container->singleton(AIPS_Related_Posts_Service::class, function( $container ) {
            return new AIPS_Related_Posts_Service(
                $container->make(AIPS_Relationships_Repository::class),
                $container->make(AIPS_Embeddings_Repository::class),
                $container->make(AIPS_Embeddings_Service::class),
                $container->make(AIPS_Config::class)
            );
        });

        // Register AIPS_Deduplication_Service
        $container->singleton(AIPS_Deduplication_Service::class, function( $container ) {
            return new AIPS_Deduplication_Service(
                $container->make(AIPS_Embeddings_Repository::class),
                $container->make(AIPS_Relationships_Repository::class),
                $container->make(AIPS_Embeddings_Service::class),
                $container->make(AIPS_Config::class),
                $container->make(AIPS_Logger_Interface::class)
            );
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

        // Integration bridge: listens for 'aips_post_generated' and
        // 'aips_template_changed' in every request context (cron and AJAX
        // both trigger generation). Registered as lazy closures rather than
        // an eagerly-constructed object — AIPS_Integration_Manager resolves
        // AIPS_AI_Service (and, through it, AIPS_Resilience_Service, whose
        // constructor reads a transient) via the container, which is not
        // lazy, so constructing it here would do real work on every request
        // even when no post is ever generated.
        add_action('aips_post_generated', function ($post_id, $template_or_context, $history_id, $context) {
            (new AIPS_Integration_Manager())->handle_post_generated($post_id, $template_or_context, $history_id, $context);
        }, 10, 4);
        add_action('aips_template_changed', function ($args) {
            (new AIPS_Integration_Manager())->handle_template_deleted($args);
        });

        // Continuous semantic indexing: automatically index published posts and refresh relationships
        add_action('save_post', function ($post_id, $post) {
            if (!is_object($post) || !isset($post->post_status)) {
                return;
            }
            AIPS_Container::get_instance()->make(AIPS_Content_Indexer_Service::class)->on_post_save($post_id, $post);
        }, 10, 2);

        // Related Posts Frontend integration (content filter, shortcode, block)
        new AIPS_Related_Posts_Frontend(
            AIPS_Container::get_instance()->make(AIPS_Related_Posts_Service::class)
        );
    }

    /**
     * Boot subsystems required only during WP-Cron execution.
     *
     * @return void
     */
    private function boot_cron() {
        AIPS_Scheduler::register_cron_hooks();
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

        // Native WordPress post list/editor History links for plugin containers.
        new AIPS_Post_History_UI();

        // Internal Links controller must be available globally so the admin-menu
        // render callback can call $controller->render_page() without reconstructing
        // the object (which would double-register all AJAX hooks).
        global $aips_internal_links_controller;
        $aips_internal_links_controller = new AIPS_Internal_Links_Controller();

        // Ensure Seeder admin hooks are registered when developer mode is enabled
        // so the Seeder JS will be enqueued on the Dev Tools diagnostics tab.
        if ( AIPS_Config::get_instance()->get_option('aips_developer_mode') ) {
            // Lazy instantiate the Seeder admin class so its admin_enqueue_scripts
            // hook is available on Diagnostics/Dev Tools pages.
            new AIPS_Seeder_Admin();
        }

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

