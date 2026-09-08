<?php
if (!defined('ABSPATH')) {
    die;
}

/**
 * Class AIPS_Admin_Menu
 *
 * Handles the registration of admin menu pages and rendering of admin interfaces
 * for the AI Post Scheduler plugin.
 *
 * @package AI_Post_Scheduler
 */
class AIPS_Admin_Menu {

    /**
     * Initialize the admin menu class.
     *
     * Hooks into admin_menu and menu/page filters.
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_pages'));
        add_filter('parent_file', array($this, 'fix_author_topics_parent_file'));
        add_filter('submenu_file', array($this, 'fix_author_topics_submenu_file'));
    }

    /**
     * Add menu pages to the WordPress admin dashboard.
     *
     * Registers the primary admin submenu structure. Diagnostics-only tools are
     * grouped under the Diagnostics page while retaining hidden direct routes.
     *
     * @return void
     */
    public function add_menu_pages() {
        // Main menu page
        add_menu_page(
            __('AI Post Scheduler', 'ai-post-scheduler'),
            __('AI Post Scheduler', 'ai-post-scheduler'),
            'manage_options',
            'ai-post-scheduler',
            array($this, 'render_dashboard_page'),
            'dashicons-schedule',
            30
        );

        // 1. Dashboard
        add_submenu_page(
            'ai-post-scheduler',
            __('Dashboard', 'ai-post-scheduler'),
            __('Dashboard', 'ai-post-scheduler'),
            'manage_options',
            'ai-post-scheduler',
            array($this, 'render_dashboard_page')
        );

        // 2. Automations
        add_submenu_page(
            'ai-post-scheduler',
            __('Automations', 'ai-post-scheduler'),
            __('Automations', 'ai-post-scheduler'),
            'manage_options',
            'aips-automations',
            array($this, 'render_automations_page')
        );

        // 3. Studio
        add_submenu_page(
            'ai-post-scheduler',
            __('Studio', 'ai-post-scheduler'),
            __('Studio', 'ai-post-scheduler'),
            'manage_options',
            'aips-studio',
            array($this, 'render_studio_page')
        );

        // 4. Research
        add_submenu_page(
            'ai-post-scheduler',
            __('Research', 'ai-post-scheduler'),
            __('Research', 'ai-post-scheduler'),
            'manage_options',
            'aips-research',
            array($this, 'render_research_page')
        );

        // 5. Content
        add_submenu_page(
            'ai-post-scheduler',
            __('Content', 'ai-post-scheduler'),
            __('Content', 'ai-post-scheduler'),
            'manage_options',
            'aips-generated-posts',
            array($this, 'render_generated_posts_page')
        );

        // 6. History
        add_submenu_page(
            'ai-post-scheduler',
            __('History', 'ai-post-scheduler'),
            __('History', 'ai-post-scheduler'),
            'manage_options',
            'aips-history',
            array($this, 'render_history_page')
        );

        // 7. Settings
        add_submenu_page(
            'ai-post-scheduler',
            __('Settings', 'ai-post-scheduler'),
            __('Settings', 'ai-post-scheduler'),
            'manage_options',
            'aips-settings',
            array($this, 'render_settings_page')
        );

        // 8. Diagnostics
        add_submenu_page(
            'ai-post-scheduler',
            __('Diagnostics', 'ai-post-scheduler'),
            __('Diagnostics', 'ai-post-scheduler'),
            'manage_options',
            'aips-diagnostics',
            array($this, 'render_diagnostics_page')
        );

        // Hidden child pages accessible directly via URL / redirects
        add_submenu_page(
            null,
            __('Campaign Wizard', 'ai-post-scheduler'),
            __('Campaign Wizard', 'ai-post-scheduler'),
            'manage_options',
            AIPS_Campaigns_Controller::PAGE_SLUG,
            array($this, 'render_campaign_wizard_page')
        );

        add_submenu_page(
            null,
            __('Campaign Detail', 'ai-post-scheduler'),
            __('Campaign Detail', 'ai-post-scheduler'),
            'manage_options',
            AIPS_Campaigns_Controller::DETAIL_PAGE_SLUG,
            array($this, 'render_campaign_detail_page')
        );

        add_submenu_page(
            null,
            __('Author Topics', 'ai-post-scheduler'),
            __('Author Topics', 'ai-post-scheduler'),
            'manage_options',
            'aips-author-topics',
            array($this, 'render_author_topics_page')
        );

        add_submenu_page(
            null,
            __('System Status', 'ai-post-scheduler'),
            __('System Status', 'ai-post-scheduler'),
            'manage_options',
            'aips-status',
            array($this, 'render_status_page')
        );

        add_submenu_page(
            null,
            __('Operations Insights', 'ai-post-scheduler'),
            __('Operations Insights', 'ai-post-scheduler'),
            'manage_options',
            'aips-operations-insights',
            array($this, 'render_operations_insights_page')
        );

        if (AIPS_Config::get_instance()->get_option('aips_enable_telemetry')) {
            add_submenu_page(
                null,
                __('Telemetry', 'ai-post-scheduler'),
                __('Telemetry', 'ai-post-scheduler'),
                'manage_options',
                'aips-telemetry',
                array($this, 'render_telemetry_page')
            );
        }

        add_submenu_page(
            null,
            __('Stress Test', 'ai-post-scheduler'),
            __('Stress Test', 'ai-post-scheduler'),
            'manage_options',
            AIPS_Stress_Test_Controller::PAGE_SLUG,
            array($this, 'render_stress_test_page')
        );

        if (AIPS_Config::get_instance()->get_option('aips_cache_monitor_enabled')) {
            add_submenu_page(
                null,
                __('Cache Monitor', 'ai-post-scheduler'),
                __('Cache Monitor', 'ai-post-scheduler'),
                'manage_options',
                'aips-cache-monitor',
                array($this, 'render_cache_monitor_page')
            );
        }
      
        if (AIPS_Config::get_instance()->get_option('aips_developer_mode')) {
            add_submenu_page(
                null,
                __('Dev Tools', 'ai-post-scheduler'),
                __('Dev Tools', 'ai-post-scheduler'),
                'manage_options',
                'aips-dev-tools',
                array($this, 'render_dev_tools_page')
            );
        }
    }

    /**
     * Expand the "AI Post Scheduler" top-level menu when on the hidden Author Topics page.
     *
     * WordPress collapses the parent menu when a page is registered with null parent_slug.
     * This filter overrides that behaviour so the plugin menu stays open.
     *
     * @param string $parent_file The current parent file slug.
     * @return string
     */
    public function fix_author_topics_parent_file($parent_file) {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ($page === 'aips-author-topics' || $page === AIPS_Campaigns_Controller::DETAIL_PAGE_SLUG || $this->is_diagnostics_child_page($page) || $this->is_automations_child_page($page) || $this->is_studio_child_page($page)) {
            return 'ai-post-scheduler';
        }
        return $parent_file;
    }

    /**
     * Highlight consolidated submenu items for hidden child pages.
     *
     * Hidden pages registered with a null parent do not automatically activate a submenu
     * item in WordPress. This filter maps Diagnostics, Automations, and Studio child pages to
     * their corresponding visible submenu entries.
     *
     * @param string $submenu_file The current submenu file slug.
     * @return string
     */
    public function fix_author_topics_submenu_file($submenu_file) {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ($this->is_diagnostics_child_page($page)) {
            return 'aips-diagnostics';
        }
        if ($this->is_automations_child_page($page)) {
            return 'aips-automations';
        }
        if ($this->is_studio_child_page($page)) {
            return 'aips-studio';
        }
        return $submenu_file;
    }

    /**
     * Determine whether a hidden page belongs under Diagnostics.
     *
     * @param string $page Current admin page slug.
     * @return bool
     */
    private function is_diagnostics_child_page($page) {
        if (class_exists('AIPS_Admin_Page_Context') && AIPS_Admin_Page_Context::is_child_page($page, AIPS_Admin_Page_Context::HUB_DIAGNOSTICS)) {
            return true;
        }
        return $page === AIPS_Stress_Test_Controller::PAGE_SLUG;
    }

    /**
     * Determine whether a hidden page belongs under Automations.
     *
     * @param string $page Current admin page slug.
     * @return bool
     */
    private function is_automations_child_page($page) {
        if (class_exists('AIPS_Admin_Page_Context') && AIPS_Admin_Page_Context::is_child_page($page, AIPS_Admin_Page_Context::HUB_AUTOMATIONS)) {
            return true;
        }
        return in_array($page, array(AIPS_Campaigns_Controller::PAGE_SLUG, AIPS_Campaigns_Controller::DETAIL_PAGE_SLUG), true);
    }

    /**
     * Determine whether a hidden page belongs under Studio.
     *
     * @param string $page Current admin page slug.
     * @return bool
     */
    private function is_studio_child_page($page) {
        if (class_exists('AIPS_Admin_Page_Context') && AIPS_Admin_Page_Context::is_child_page($page, AIPS_Admin_Page_Context::HUB_STUDIO)) {
            return true;
        }
        return false;
    }

    /**
     * Render the main dashboard page.
     *
     * Fetches statistics and recent activity from the database to display
     * on the dashboard template.
     *
     * @return void
     */
    public function render_dashboard_page() {
        AIPS_Admin_Menu_Helper::safe_render(function() {
            $controller = new AIPS_Dashboard_Controller();
            $controller->render_page();
        }, __('Dashboard', 'ai-post-scheduler'));
    }

    /**
     * Render the Automations page.
     *
     * @return void
     */
    public function render_automations_page() {
        AIPS_Admin_Menu_Helper::safe_render(function() {
            $controller = new AIPS_Automations_Controller();
            $controller->render_page();
        }, __('Automations', 'ai-post-scheduler'));
    }

    /**
     * Render the Studio page.
     *
     * @return void
     */
    public function render_studio_page() {
        AIPS_Admin_Menu_Helper::safe_render(function() {
            $controller = new AIPS_Studio_Controller();
            $controller->render_page();
        }, __('Studio', 'ai-post-scheduler'));
    }

    /**
     * Safely redirect a legacy or consolidated subpage to its new hub and tab,
     * preserving all existing query parameters (filters, search, pagination, etc.).
     *
     * @param string $hub_page   Target parent hub slug (e.g. 'aips-studio').
     * @param string $tab        Optional target tab identifier within the hub.
     * @param array  $extra_args Additional query parameters to include or override.
     * @return void
     */
    public function redirect_to_hub($hub_page, $tab = '', $extra_args = array()) {
        $query_args = array();

        if (!empty($_GET) && is_array($_GET)) {
            foreach ($_GET as $key => $val) {
                $sanitized_key = sanitize_key(wp_unslash($key));
                if ('page' === $sanitized_key) {
                    continue;
                }
                if (is_array($val)) {
                    $query_args[$sanitized_key] = array_map('sanitize_text_field', wp_unslash($val));
                } else {
                    $query_args[$sanitized_key] = sanitize_text_field(wp_unslash($val));
                }
            }
        }

        $query_args['page'] = $hub_page;
        if (!empty($tab)) {
            $query_args['tab'] = $tab;
        }

        if (!empty($extra_args)) {
            $query_args = array_merge($query_args, $extra_args);
        }

        $target_url = add_query_arg($query_args, admin_url('admin.php'));
        wp_safe_redirect($target_url);
        exit;
    }

    /**
     * Render the Voices management page.
     *
     * Delegates rendering to the AIPS_Voices class.
     *
     * @return void
     */
    public function render_voices_page() {
        $this->redirect_to_hub('aips-studio', 'voices');
    }

    /**
     * Render the Templates management page.
     *
     * Delegates rendering to the AIPS_Templates class.
     *
     * @return void
     */
    public function render_templates_page() {
        $this->redirect_to_hub('aips-studio', 'templates');
    }

    /**
     * Render the Schedule management page.
     *
     * Includes the schedule template file.
     *
     * @return void
     */
    public function render_schedule_page() {
        $this->redirect_to_hub('aips-automations', 'schedules');
    }

    /**
     * Render the Campaigns page.
     *
     * Delegates to the Campaigns controller.
     *
     * @return void
     */
    public function render_campaigns_page() {
        AIPS_Admin_Menu_Helper::safe_render(function() {
            $controller = new AIPS_Campaigns_Controller();
            $controller->render_page();
        }, __('Campaigns', 'ai-post-scheduler'));
    }

    /**
     * Render the campaign wizard page.
     */
    public function render_campaign_wizard_page() {
        AIPS_Admin_Menu_Helper::safe_render(function() {
            $controller = new AIPS_Campaigns_Controller();
            $controller->render_wizard_page();
        }, __('Campaign Wizard', 'ai-post-scheduler'));
    }


    /**
     * Render the campaign detail page.
     */
    public function render_campaign_detail_page() {
        AIPS_Admin_Menu_Helper::safe_render(function() {
            $controller = new AIPS_Campaigns_Controller();
            $controller->render_detail_page();
        }, __('Campaign Details', 'ai-post-scheduler'));
    }

    /**
     * Render the Trending Topics Research page.
     *
     * Includes the research template file.
     *
     * @return void
     */
    public function render_research_page() {
        AIPS_Admin_Menu_Helper::safe_render(function() {
            include AIPS_PLUGIN_DIR . 'templates/admin/research.php';
        }, __('Research', 'ai-post-scheduler'));
    }

    /**
     * Render the Authors management page.
     *
     * Includes the authors template file.
     *
     * @return void
     */
    public function render_authors_page() {
        $this->redirect_to_hub('aips-automations', 'authors');
    }

    /**
     * Render the Post Slices management page.
     *
     * @return void
     */
    public function render_post_slices_page() {
        $this->redirect_to_hub('aips-studio', 'post-slices');
    }

    /**
     * Render the Author Topics page.
     *
     * Displays all AI-generated topics for a specific author with full
     * management capabilities (approve, reject, edit, delete, generate post).
     *
     * @return void
     */
    public function render_author_topics_page() {
        $this->redirect_to_hub('aips-automations', 'author-topics');
    }

    /**
     * Render the Generated Posts page.
     *
     * @return void
     */
    public function render_generated_posts_page() {
        AIPS_Admin_Menu_Helper::safe_render(function() {
            $controller = new AIPS_Generated_Posts_Controller();
            $controller->render_page();
        }, __('Content', 'ai-post-scheduler'));
    }

    /*
     * Render the Article Structures page.
     *
     * Fetches structures and sections from repositories and passes them to the template.
     *
     * @return void
     */
    public function render_structures_page() {
        $this->redirect_to_hub('aips-studio', 'structures');
    }

    /**
     * Render the History page.
     *
     * Delegates rendering to the AIPS_History class.
     *
     * @return void
     */
    public function render_history_page() {
        AIPS_Admin_Menu_Helper::safe_render(function() {
            $history_handler = new AIPS_History();
            $history_handler->render_page();
        }, __('History', 'ai-post-scheduler'));
    }

    /**
     * Render the Diagnostics page.
     *
     * @return void
     */
    public function render_diagnostics_page() {
        AIPS_Admin_Menu_Helper::safe_render(function() {
            $controller = new AIPS_Diagnostics_Controller();
            $controller->render_page();
        }, __('Diagnostics', 'ai-post-scheduler'));
    }

    public function render_operations_insights_page() {
        $this->redirect_to_hub('aips-diagnostics', 'insights');
    }

    /**
     * Render the Telemetry page.
     *
     * @return void
     */
    public function render_telemetry_page() {
        $this->redirect_to_hub('aips-diagnostics', 'telemetry');
    }

    /**
     * Render the Sources page.
     *
     * Loads all sources from the repository and includes the sources template.
     *
     * @return void
     */
    public function render_sources_page() {
        $this->redirect_to_hub('aips-automations', 'sources');
    }

    /**
     * Render the Source Data page.
     *
     * @return void
     */
    public function render_source_data_page() {
        AIPS_Admin_Menu_Helper::safe_render(function() {
            $source_id      = isset($_GET['source_id']) ? absint(wp_unslash($_GET['source_id'])) : 0;
            $paged          = isset($_GET['source_data_paged']) ? absint(wp_unslash($_GET['source_data_paged'])) : 1;
            $search         = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
            $is_global_view = $source_id <= 0;
            $per_page       = 20;

            $repo      = new AIPS_Sources_Repository();
            $data_repo = new AIPS_Sources_Data_Repository();
            $source    = $source_id ? $repo->get_by_id($source_id) : null;
            $sources   = $is_global_view ? $repo->get_all(false) : array();

            $filters = array(
                'fetch_status'      => isset($_GET['fetch_status']) ? sanitize_key(wp_unslash($_GET['fetch_status'])) : '',
                'http_status_class' => isset($_GET['http_status_class']) ? absint(wp_unslash($_GET['http_status_class'])) : 0,
                'fetched_after'     => isset($_GET['fetched_after']) ? sanitize_text_field(wp_unslash($_GET['fetched_after'])) : '',
                'fetched_before'    => isset($_GET['fetched_before']) ? sanitize_text_field(wp_unslash($_GET['fetched_before'])) : '',
                'min_char_count'    => isset($_GET['min_char_count']) ? absint(wp_unslash($_GET['min_char_count'])) : 0,
                'max_char_count'    => isset($_GET['max_char_count']) ? absint(wp_unslash($_GET['max_char_count'])) : 0,
                'search_body_text'  => !empty($_GET['search_body_text']),
                'source_id'         => isset($_GET['filter_source_id']) ? absint(wp_unslash($_GET['filter_source_id'])) : 0,
            );

            if ($is_global_view) {
                $source_data = $data_repo->get_paginated($search, $per_page, $paged, $filters);
            } elseif (!$source) {
                $source_data = array(
                    'items'        => array(),
                    'total'        => 0,
                    'pages'        => 0,
                    'current_page' => 1,
                    'per_page'     => $per_page,
                );
            } else {
                $source_data = $data_repo->get_paginated_by_source_id($source_id, $search, $per_page, $paged, $filters);
            }

            include AIPS_PLUGIN_DIR . 'templates/admin/source-data.php';
        }, __('Source Data', 'ai-post-scheduler'));
    }

    /**
     * Render the Settings page.
     *
     * Includes the settings template file.
     *
     * @return void
     */
    public function render_settings_page() {
        AIPS_Admin_Menu_Helper::safe_render(function() {
            include AIPS_PLUGIN_DIR . 'templates/admin/settings.php';
        }, __('Settings', 'ai-post-scheduler'));
    }

    /**
     * Render the Cache Monitor page.
     *
     * @return void
     */
    public function render_cache_monitor_page() {
        $this->redirect_to_hub('aips-diagnostics', 'cache-monitor');
    }

    /**
     * Render the System Status page.
     *
     * Delegates rendering to the AIPS_System_Status class.
     *
     * @return void
     */
    public function render_status_page() {
        $this->redirect_to_hub('aips-diagnostics', 'status');
    }

    /**
     * Render the Stress Test page.
     *
     * Delegates rendering to AIPS_Stress_Test_Controller.
     *
     * @return void
     */
    public function render_stress_test_page() {
        $this->redirect_to_hub('aips-diagnostics', 'stress-test');
    }

    /**
     * Render the Dev Tools page.
     *
     * Delegates rendering to the AIPS_Dev_Tools class.
     *
     * @return void
     */
    public function render_dev_tools_page() {
        $this->redirect_to_hub('aips-diagnostics', 'dev-tools');
    }

    /**
     * Render the Taxonomy page.
     *
     * Includes the taxonomy template file.
     *
     * @return void
     */
    public function render_taxonomy_page() {
        $this->redirect_to_hub('aips-automations', 'taxonomy');
    }

    /**
     * Render the Internal Links page.
     *
     * Reuses the globally-registered controller instance to avoid
     * re-registering AJAX hooks.
     *
     * @return void
     */
    public function render_affiliate_links_page() {
        $this->redirect_to_hub('aips-automations', 'affiliate-links');
    }

    public function render_internal_links_page() {
        $this->redirect_to_hub('aips-automations', 'internal-links');
    }

    public function render_content_indexer_page() {
        AIPS_Admin_Menu_Helper::safe_render(function() {
            $controller = new AIPS_Content_Indexer_Controller();
            $controller->render_page();
        }, __('Content Indexer', 'ai-post-scheduler'));
    }
}
