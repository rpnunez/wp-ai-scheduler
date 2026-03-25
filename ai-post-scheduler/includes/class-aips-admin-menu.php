<?php
/**
 * Admin Menu
 *
 * Handles the registration of admin menu pages and rendering of admin interfaces
 * for the AI Post Scheduler plugin.
 *
 * @package AI_Post_Scheduler
 */

if (!defined('ABSPATH')) {
    die;
}

/**
 * Class AIPS_Admin_Menu
 *
 * Responsible for adding menu pages to the WordPress admin dashboard and rendering their templates.
 */
class AIPS_Admin_Menu {

    /**
     * Initialize the admin menu class.
     *
     * Hooks into admin_menu and related filters.
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_pages'));
        add_filter('parent_file', array($this, 'fix_author_topics_parent_file'));
        add_filter('submenu_file', array($this, 'fix_author_topics_submenu_file'));
    }

    /**
     * Add menu pages to the WordPress admin dashboard.
     *
     * Registers a traditional flat submenu structure.
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
            'ai-post-scheduler',
            __('Authors', 'ai-post-scheduler'),
            __('Authors', 'ai-post-scheduler'),
            'manage_options',
            'aips-authors',
            array($this, 'render_authors_page')
        );

        // Author Topics page - hidden from menu navigation, accessible via URL
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
            'ai-post-scheduler',
            __('Schedule', 'ai-post-scheduler'),
            __('Schedule', 'ai-post-scheduler'),
            'manage_options',
            'aips-schedule',
            array($this, 'render_schedule_page')
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
            __('Generated Posts', 'ai-post-scheduler'),
            __('Generated Posts', 'ai-post-scheduler'),
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
            'ai-post-scheduler',
            __('Sources', 'ai-post-scheduler'),
            __('Sources', 'ai-post-scheduler'),
            'manage_options',
            'aips-sources',
            array($this, 'render_sources_page')
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
            __('System Status', 'ai-post-scheduler'),
            __('System Status', 'ai-post-scheduler'),
            'manage_options',
            'aips-status',
            array($this, 'render_status_page')
        );

        add_submenu_page(
            'ai-post-scheduler',
            __('Seeder', 'ai-post-scheduler'),
            __('Seeder', 'ai-post-scheduler'),
            'manage_options',
            'aips-seeder',
            array($this, 'render_seeder_page')
        );

        if (get_option('aips_developer_mode')) {
            add_submenu_page(
                'ai-post-scheduler',
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
     * @param string $parent_file The current parent file slug.
     * @return string
     */
    public function fix_author_topics_parent_file($parent_file) {
        $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
        if ($page === 'aips-author-topics') {
            return 'ai-post-scheduler';
        }
        return $parent_file;
    }

    /**
     * Highlight the "Authors" submenu item when on the hidden Author Topics page.
     *
     * @param string $submenu_file The current submenu file slug.
     * @return string
     */
    public function fix_author_topics_submenu_file($submenu_file) {
        $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
        if ($page === 'aips-author-topics') {
            return 'aips-authors';
        }
        return $submenu_file;
    }

    /**
     * Render the Dashboard page.
     *
     * @return void
     */
    public function render_dashboard_page() {
        $controller = new AIPS_Dashboard_Controller();
        $controller->render_page();
    }

    /**
     * Render the Voices page.
     *
     * @return void
     */
    public function render_voices_page() {
        $voices_handler = new AIPS_Voices();
        $voices_handler->render_page();
    }

    /**
     * Render the Templates page.
     *
     * @return void
     */
    public function render_templates_page() {
        $templates_handler = new AIPS_Templates();
        $templates_handler->render_page();
    }

    /**
     * Render the Schedule page.
     *
     * @return void
     */
    public function render_schedule_page() {
        include AIPS_PLUGIN_DIR . 'templates/admin/schedule.php';
    }

    /**
     * Render the Schedule Calendar page.
     *
     * @return void
     */
    public function render_schedule_calendar_page() {
        include AIPS_PLUGIN_DIR . 'templates/admin/calendar.php';
    }

    /**
     * Render the Trending Topics Research page.
     *
     * @return void
     */
    public function render_research_page() {
        include AIPS_PLUGIN_DIR . 'templates/admin/research.php';
    }

    /**
     * Render the Authors management page.
     *
     * @return void
     */
    public function render_authors_page() {
        include AIPS_PLUGIN_DIR . 'templates/admin/authors.php';
    }

    /**
     * Render the Author Topics page.
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

    /**
     * Render the Post Review page.
     *
     * @return void
     */
    public function render_post_review_page() {
        // Get the globally-initialized Post Review handler to avoid duplicate AJAX registration
        global $aips_post_review_handler;
        if (!isset($aips_post_review_handler)) {
            // Fallback: repository only (AJAX handlers already registered in main init)
            $post_review_handler = null;
        } else {
            $post_review_handler = $aips_post_review_handler;
        }
        include AIPS_PLUGIN_DIR . 'templates/admin/post-review.php';
    }

    /**
     * Render the Article Structures page.
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
     * @return void
     */
    public function render_history_page() {
        $history_handler = new AIPS_History();
        $history_handler->render_page();
    }

    /**
     * Render the Sources page.
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

        include AIPS_PLUGIN_DIR . 'templates/admin/sources.php';
    }

    /**
     * Render the Settings page.
     *
     * @return void
     */
    public function render_settings_page() {
        include AIPS_PLUGIN_DIR . 'templates/admin/settings.php';
    }

    /**
     * Render the Seeder page.
     *
     * @return void
     */
    public function render_seeder_page() {
        include AIPS_PLUGIN_DIR . 'templates/admin/seeder.php';
    }

    /**
     * Render the System Status page.
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
     * @return void
     */
    public function render_dev_tools_page() {
        // AIPS_Dev_Tools is instantiated in init if admin, but we need to call render_page on an instance.
        // Since we don't have a global instance registry accessible easily here, we'll instantiate it on demand.
        // It's a lightweight class, mostly for AJAX and rendering.
        $dev_tools = new AIPS_Dev_Tools();
        $dev_tools->render_page();
    }
}
