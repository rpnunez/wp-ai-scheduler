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

        // Dashboard (top level)
        add_submenu_page(
            'ai-post-scheduler',
            __('Dashboard', 'ai-post-scheduler'),
            __('Dashboard', 'ai-post-scheduler'),
            'manage_options',
            'ai-post-scheduler',
            array($this, 'render_dashboard_page')
        );

        add_submenu_page(
            'ai-post-scheduler',
            __('Automations', 'ai-post-scheduler'),
            __('Automations', 'ai-post-scheduler'),
            'manage_options',
            'aips-automations',
            array($this, 'render_automations_page')
        );

        add_submenu_page(
            null,
            __('Templates', 'ai-post-scheduler'),
            __('Templates', 'ai-post-scheduler'),
            'manage_options',
            'aips-templates',
            array($this, 'render_templates_page')
        );

        add_submenu_page(
            'ai-post-scheduler',
            __('Voices', 'ai-post-scheduler'),
            __('Voices', 'ai-post-scheduler'),
            'manage_options',
            'aips-voices',
            array($this, 'render_voices_page')
        );

        add_submenu_page(
            'ai-post-scheduler',
            __('Article Structures', 'ai-post-scheduler'),
            __('Article Structures', 'ai-post-scheduler'),
            'manage_options',
            'aips-structures',
            array($this, 'render_structures_page')
        );

        add_submenu_page(
            null,
            __('Authors', 'ai-post-scheduler'),
            __('Authors', 'ai-post-scheduler'),
            'manage_options',
            'aips-authors',
            array($this, 'render_authors_page')
        );

        add_submenu_page(
            'ai-post-scheduler',
            __('Post Slices', 'ai-post-scheduler'),
            __('Post Slices', 'ai-post-scheduler'),
            'manage_options',
            'aips-post-slices',
            array($this, 'render_post_slices_page')
        );

        // Author Topics page - hidden from menu navigation, accessible via URL.
        add_submenu_page(
            null,
            __('Author Topics', 'ai-post-scheduler'),
            __('Author Topics', 'ai-post-scheduler'),
            'manage_options',
            'aips-author-topics',
            array($this, 'render_author_topics_page')
        );

        add_submenu_page(
            'ai-post-scheduler',
            __('Research', 'ai-post-scheduler'),
            __('Research', 'ai-post-scheduler'),
            'manage_options',
            'aips-research',
            array($this, 'render_research_page')
        );

        add_submenu_page(
            null,
            __('Schedule', 'ai-post-scheduler'),
            __('Schedule', 'ai-post-scheduler'),
            'manage_options',
            'aips-schedule',
            array($this, 'render_schedule_page')
        );

        add_submenu_page(
            null,
            __('Campaigns', 'ai-post-scheduler'),
            __('Campaigns', 'ai-post-scheduler'),
            'manage_options',
            'aips-campaigns',
            array($this, 'render_campaigns_page')
        );

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
            'ai-post-scheduler',
            __('Schedule Calendar', 'ai-post-scheduler'),
            __('Schedule Calendar', 'ai-post-scheduler'),
            'manage_options',
            'aips-schedule-calendar',
            array($this, 'render_schedule_calendar_page')
        );

        add_submenu_page(
            'ai-post-scheduler',
            __('Content', 'ai-post-scheduler'),
            __('Content', 'ai-post-scheduler'),
            'manage_options',
            'aips-generated-posts',
            array($this, 'render_generated_posts_page')
        );

        add_submenu_page(
            'ai-post-scheduler',
            __('History', 'ai-post-scheduler'),
            __('History', 'ai-post-scheduler'),
            'manage_options',
            'aips-history',
            array($this, 'render_history_page')
        );
        add_submenu_page(
            null,
            __('Sources', 'ai-post-scheduler'),
            __('Sources', 'ai-post-scheduler'),
            'manage_options',
            'aips-sources',
            array($this, 'render_sources_page')
        );

        add_submenu_page(
            null,
            __('View Source Data', 'ai-post-scheduler'),
            __('View Source Data', 'ai-post-scheduler'),
            'manage_options',
            'aips-source-data',
            array($this, 'render_source_data_page')
        );

        add_submenu_page(
            null,
            __('Taxonomy', 'ai-post-scheduler'),
            __('Taxonomy', 'ai-post-scheduler'),
            'manage_options',
            'aips-taxonomy',
            array($this, 'render_taxonomy_page')
        );

        add_submenu_page(
            null,
            __('Internal Links', 'ai-post-scheduler'),
            __('Internal Links', 'ai-post-scheduler'),
            'manage_options',
            'aips-internal-links',
            array($this, 'render_internal_links_page')
        );

        add_submenu_page(
            'ai-post-scheduler',
            __('Settings', 'ai-post-scheduler'),
            __('Settings', 'ai-post-scheduler'),
            'manage_options',
            'aips-settings',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'ai-post-scheduler',
            __('Diagnostics', 'ai-post-scheduler'),
            __('Diagnostics', 'ai-post-scheduler'),
            'manage_options',
            'aips-diagnostics',
            array($this, 'render_diagnostics_page')
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
            __('Seeder', 'ai-post-scheduler'),
            __('Seeder', 'ai-post-scheduler'),
            'manage_options',
            'aips-seeder',
            array($this, 'render_seeder_page')
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
            __('Cache Monitor', 'ai-post-scheduler'),
            __('Cache Monitor', 'ai-post-scheduler'),
            'manage_options',
            'aips-cache-monitor',
            array($this, 'render_cache_monitor_page')
        );
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
        if ($page === 'aips-author-topics' || $page === AIPS_Campaigns_Controller::DETAIL_PAGE_SLUG || $this->is_diagnostics_child_page($page) || $this->is_automations_child_page($page)) {
            return 'ai-post-scheduler';
        }
        return $parent_file;
    }

    /**
     * Highlight consolidated submenu items for hidden child pages.
     *
     * Hidden pages registered with a null parent do not automatically activate a submenu
     * item in WordPress. This filter maps Diagnostics and Automations child pages to
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
        return $submenu_file;
    }

    /**
     * Determine whether a hidden page belongs under Diagnostics.
     *
     * @param string $page Current admin page slug.
     * @return bool
     */
    private function is_diagnostics_child_page($page) {
        return in_array(
            $page,
            array(
                'aips-operations-insights',
                'aips-status',
                'aips-telemetry',
                'aips-seeder',
                'aips-dev-tools',
                'aips-cache-monitor',
            ),
            true
        );
    }

    /**
     * Determine whether a hidden page belongs under Automations.
     *
     * @param string $page Current admin page slug.
     * @return bool
     */
    private function is_automations_child_page($page) {
        return in_array(
            $page,
            array(
                'aips-schedule',
                'aips-campaigns',
                'aips-templates',
                'aips-authors',
                'aips-sources',
                'aips-taxonomy',
                'aips-internal-links',
                'aips-author-topics',
                AIPS_Campaigns_Controller::PAGE_SLUG,
                AIPS_Campaigns_Controller::DETAIL_PAGE_SLUG,
            ),
            true
        );
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
        $controller = new AIPS_Dashboard_Controller();
        $controller->render_page();
    }

    /**
     * Render the Automations page.
     *
     * @return void
     */
    public function render_automations_page() {
        $controller = new AIPS_Automations_Controller();
        $controller->render_page();
    }

    /**
     * Render the Voices management page.
     *
     * Delegates rendering to the AIPS_Voices class.
     *
     * @return void
     */
    public function render_voices_page() {
        $voices_handler = new AIPS_Voices();
        $voices_handler->render_page();
    }

    /**
     * Render the Templates management page.
     *
     * Delegates rendering to the AIPS_Templates class.
     *
     * @return void
     */
    public function render_templates_page() {
        $templates_handler = new AIPS_Templates();
        $templates_handler->render_page();
    }

    /**
     * Render the Schedule management page.
     *
     * Includes the schedule template file.
     *
     * @return void
     */
    public function render_schedule_page() {
        include AIPS_PLUGIN_DIR . 'templates/admin/schedule.php';
    }

    /**
     * Render the Campaigns page.
     *
     * Delegates to the Campaigns controller.
     *
     * @return void
     */
    public function render_campaigns_page() {
        $controller = new AIPS_Campaigns_Controller();
        $controller->render_page();
    }

    /**
     * Render the campaign wizard page.
     */
    public function render_campaign_wizard_page() {
        $controller = new AIPS_Campaigns_Controller();
        $controller->render_wizard_page();
    }


    /**
     * Render the campaign detail page.
     */
    public function render_campaign_detail_page() {
        $controller = new AIPS_Campaigns_Controller();
        $controller->render_detail_page();
    }

    /**
     * Render the Schedule Calendar page.
     *
     * Includes the schedule calendar template file.
     *
     * @return void
     */
    public function render_schedule_calendar_page() {
        include AIPS_PLUGIN_DIR . 'templates/admin/calendar.php';
    }

    /**
     * Render the Trending Topics Research page.
     *
     * Includes the research template file.
     *
     * @return void
     */
    public function render_research_page() {
        include AIPS_PLUGIN_DIR . 'templates/admin/research.php';
    }

    /**
     * Render the Authors management page.
     *
     * Includes the authors template file.
     *
     * @return void
     */
    public function render_authors_page() {
        include AIPS_PLUGIN_DIR . 'templates/admin/authors.php';
    }

    /**
     * Render the Post Slices management page.
     *
     * @return void
     */
    public function render_post_slices_page() {
        $post_slices_repo   = AIPS_Post_Slices_Repository::instance();
        $post_slices        = $post_slices_repo->get_all(false);
        $post_slice_counts  = $post_slices_repo->get_counts();

        include AIPS_PLUGIN_DIR . 'templates/admin/post-slices.php';
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
        include AIPS_PLUGIN_DIR . 'templates/admin/author-topics.php';
    }

    /**
     * Render the Generated Posts page.
     *
     * @return void
     */
    public function render_generated_posts_page() {
        $controller = new AIPS_Generated_Posts_Controller();
        $controller->render_page();
    }

    /*
     * Render the Article Structures page.
     *
     * Fetches structures and sections from repositories and passes them to the template.
     *
     * @return void
     */
    public function render_structures_page() {
        $structure_repo = new AIPS_Article_Structure_Repository();
        $section_repo = new AIPS_Prompt_Section_Repository();

        $structures = $structure_repo->get_all(false);
        $sections = $section_repo->get_all(false);

        include AIPS_PLUGIN_DIR . 'templates/admin/structures.php';
    }

    /**
     * Render the Prompt Sections page.
     *
     * Fetches prompt sections and passes them to the template.
     *
     * @return void
     */
    public function render_prompt_sections_page() {
        $section_repo = new AIPS_Prompt_Section_Repository();
        $sections = $section_repo->get_all(false);

        include AIPS_PLUGIN_DIR . 'templates/admin/sections.php';
    }

    /**
     * Render the History page.
     *
     * Delegates rendering to the AIPS_History class.
     *
     * @return void
     */
    public function render_history_page() {
        $history_handler = new AIPS_History();
        $history_handler->render_page();
    }

    /**
     * Render the Diagnostics page.
     *
     * @return void
     */
    public function render_diagnostics_page() {
        $controller = new AIPS_Diagnostics_Controller();
        $controller->render_page();
    }

    public function render_operations_insights_page() {
        $controller = new AIPS_Operations_Insights_Controller();
        $controller->render_page();
    }

    /**
     * Render the Telemetry page.
     *
     * @return void
     */
    public function render_telemetry_page() {
        $controller = new AIPS_Telemetry_Controller();
        $controller->render_page();
    }

    /**
     * Render the Sources page.
     *
     * Loads all sources from the repository and includes the sources template.
     *
     * @return void
     */
    public function render_sources_page() {
        $repo    = new AIPS_Sources_Repository();
        $sources = $repo->get_all(false);

        // Build source group name map: term_id => name (avoid per-row get_term calls in the template).
        $source_groups = get_terms(array(
            'taxonomy'   => 'aips_source_group',
            'hide_empty' => false,
        ));
        if (is_wp_error($source_groups)) {
            $source_groups = array();
        }
        $source_group_name_map = array();
        foreach ($source_groups as $group) {
            $source_group_name_map[(int) $group->term_id] = $group->name;
        }

        // Build source → term IDs map: source_id => int[] (one query, not N queries).
        $all_source_ids = array_map(function ($s) { return (int) $s->id; }, $sources);
        $source_term_ids_map = $repo->get_term_ids_for_sources($all_source_ids);

        // Build source → fetch-data map for the Content status column (latest row per source).
        $data_repo             = new AIPS_Sources_Data_Repository();
        $source_fetch_data_map = $data_repo->get_by_source_ids( $all_source_ids );

        // Build source → archived content count map for the Content column badge.
        $source_content_count_map = $data_repo->get_counts_by_source_ids( $all_source_ids );

        include AIPS_PLUGIN_DIR . 'templates/admin/sources.php';
    }

    /**
     * Render the Source Data page.
     *
     * @return void
     */
    public function render_source_data_page() {
        $source_id = isset($_GET['source_id']) ? absint(wp_unslash($_GET['source_id'])) : 0;
        $paged     = isset($_GET['source_data_paged']) ? absint(wp_unslash($_GET['source_data_paged'])) : 1;
        $search    = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $per_page  = 20;

        $repo      = new AIPS_Sources_Repository();
        $data_repo = new AIPS_Sources_Data_Repository();
        $source    = $source_id ? $repo->get_by_id($source_id) : null;
        $is_global_view = $source_id <= 0;
        $sources   = $is_global_view ? $repo->get_all(false) : array();

        $filters = array(
            'source_id' => isset($_GET['filter_source_id']) ? absint(wp_unslash($_GET['filter_source_id'])) : 0,
            'status'    => isset($_GET['filter_status']) ? sanitize_key(wp_unslash($_GET['filter_status'])) : '',
            'date_from' => isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : '',
            'date_to'   => isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : '',
        );
        $is_global_view = $source_id <= 0;

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
            $source_data = $data_repo->get_paginated_by_source_id($source_id, $search, $per_page, $paged);
        }

        include AIPS_PLUGIN_DIR . 'templates/admin/source-data.php';
    }

    /**
     * Render the Settings page.
     *
     * Includes the settings template file.
     *
     * @return void
     */
    public function render_settings_page() {
        include AIPS_PLUGIN_DIR . 'templates/admin/settings.php';
    }

    /**
     * Render the Seeder page.
     *
     * Includes the seeder template file.
     *
     * @return void
     */
    public function render_seeder_page() {
        $seeder_admin = new AIPS_Seeder_Admin();
        $seeder_admin->render_page();
    }

    /**
     * Render the Cache Monitor page.
     *
     * @return void
     */
    public function render_cache_monitor_page() {
        $controller = new AIPS_Cache_Monitor_Controller();
        $controller->render_page();
    }

    /**
     * Render the System Status page.
     *
     * Delegates rendering to the AIPS_System_Status class.
     *
     * @return void
     */
    public function render_status_page() {
        $status_handler = new AIPS_System_Status();
        $status_handler->render_page();
    }

    /**
     * Render the Dev Tools page.
     *
     * Delegates rendering to the AIPS_Dev_Tools class.
     *
     * @return void
     */
    public function render_dev_tools_page() {
        // AIPS_Dev_Tools is instantiated in init if admin, but we need to call render_page on an instance.
        // Since we don't have a global instance registry accessible easily here, we'll instantiate it on demand.
        // It's a lightweight class, mostly for AJAX and rendering.
        $dev_tools = new AIPS_Dev_Tools();
        $dev_tools->render_page();
    }

    /**
     * Render the Taxonomy page.
     *
     * Includes the taxonomy template file.
     *
     * @return void
     */
    public function render_taxonomy_page() {
        include AIPS_PLUGIN_DIR . 'templates/admin/taxonomy.php';
    }

    /**
     * Render the Internal Links page.
     *
     * Reuses the globally-registered controller instance to avoid
     * re-registering AJAX hooks.
     *
     * @return void
     */
    public function render_internal_links_page() {
        global $aips_internal_links_controller;

        if ($aips_internal_links_controller instanceof AIPS_Internal_Links_Controller) {
            try {
                $aips_internal_links_controller->render_page();
                return;
            } catch (Throwable $throwable) {
                echo '<div class="notice notice-error"><p>' .
                    esc_html__('The Internal Links page could not be rendered. Please reload the page or check the plugin configuration.', 'ai-post-scheduler') .
                '</p></div>';
                return;
            }
        }

        echo '<div class="notice notice-error"><p>' .
            esc_html__('The Internal Links controller is not available, so the Internal Links page could not be loaded.', 'ai-post-scheduler') .
        '</p></div>';
    }
}
