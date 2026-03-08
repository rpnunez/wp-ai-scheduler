<?php
if (!defined('ABSPATH')) {
    return;
}

/**
 * Class AIPS_Admin_Menu
 *
 * Handles the registration of admin menu pages and navigation structure.
 *
 * @package AI_Post_Scheduler
 */
class AIPS_Admin_Menu {

    /**
     * Initialize the admin menu class.
     *
     * Hooks into admin_menu.
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_pages'));
    }

    /**
     * Add a non-clickable section header to the admin menu.
     *
     * @param string $parent_slug The slug name for the parent menu.
     * @param string $title The title to display.
     * @return void
     */
    private function add_section_header($parent_slug, $title) {
        global $submenu;

        $separator_slug = 'aips-separator-' . sanitize_title($title);

        add_submenu_page(
            $parent_slug,
            $title,
            '<div style="margin-top: 10px; margin-bottom: 5px; font-weight: 600; font-size: 11px; text-transform: uppercase; color: rgba(255,255,255,0.5); cursor: default;">' . esc_html($title) . '</div>',
            'manage_options',
            $separator_slug,
            '__return_false'
        );

        // Ensure the separator isn't clickable
        if (isset($submenu[$parent_slug])) {
            foreach ($submenu[$parent_slug] as &$item) {
                if ($item[2] === $separator_slug) {
                    $item[4] = 'aips-menu-separator';
                    break;
                }
            }
        }
    }

    /**
     * Add menu pages to the WordPress admin dashboard.
     *
     * Implements Proposal B navigation structure with logical grouped sections:
     * - Dashboard (top level)
     * - Content Studio: Templates, Voices, Article Structures
     * - Planning: Authors, Research
     * - Publishing: Schedule, Generated Posts
     * - Monitoring: History (includes Activity)
     * - System: Settings, System Status, Seeder, Dev Tools
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

        // Content Studio section
        $this->add_section_header('ai-post-scheduler', __('Content Studio', 'ai-post-scheduler'));

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

        // Hidden structures pages
        add_submenu_page(
            null,
            __('Prompt Sections', 'ai-post-scheduler'),
            __('Prompt Sections', 'ai-post-scheduler'),
            'manage_options',
            'aips-sections',
            array($this, 'render_prompt_sections_page')
        );

        // Planning section
        $this->add_section_header('ai-post-scheduler', __('Planning', 'ai-post-scheduler'));

        add_submenu_page(
            'ai-post-scheduler',
            __('Authors', 'ai-post-scheduler'),
            __('Authors', 'ai-post-scheduler'),
            'manage_options',
            'aips-authors',
            array($this, 'render_authors_page')
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
            __('Planner', 'ai-post-scheduler'),
            __('Planner', 'ai-post-scheduler'),
            'manage_options',
            'aips-planner',
            array($this, 'render_planner_page')
        );

        // Publishing section
        $this->add_section_header('ai-post-scheduler', __('Publishing', 'ai-post-scheduler'));

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
            __('Generated Posts', 'ai-post-scheduler'),
            __('Generated Posts', 'ai-post-scheduler'),
            'manage_options',
            'aips-generated-posts',
            array($this, 'render_generated_posts_page')
        );

        // Hidden calendar submenu page (accessed via tabs in Schedule)
        add_submenu_page(
            'ai-post-scheduler',
            __('Calendar', 'ai-post-scheduler'),
            __('Calendar', 'ai-post-scheduler'),
            'manage_options',
            'aips-schedule-calendar',
            array($this, 'render_schedule_calendar_page')
        );

        // Hidden review submenu page (accessed via links)
        add_submenu_page(
            'ai-post-scheduler',
            __('Post Review', 'ai-post-scheduler'),
            __('Post Review', 'ai-post-scheduler'),
            'manage_options',
            'aips-post-review',
            array($this, 'render_post_review_page')
        );

        // Monitoring section
        $this->add_section_header('ai-post-scheduler', __('Monitoring', 'ai-post-scheduler'));

        add_submenu_page(
            'ai-post-scheduler',
            __('History', 'ai-post-scheduler'),
            __('History', 'ai-post-scheduler'),
            'manage_options',
            'aips-history',
            array($this, 'render_history_page')
        );

        // System section
        $this->add_section_header('ai-post-scheduler', __('System', 'ai-post-scheduler'));

        add_submenu_page(
            'ai-post-scheduler',
            __('Settings', 'ai-post-scheduler'),
            __('Settings', 'ai-post-scheduler'),
            'manage_options',
            'aips-settings',
            array($this, 'render_settings_page')
        );

        // Add Data Management page
        add_submenu_page(
            'ai-post-scheduler',
            __('Data Management', 'ai-post-scheduler'),
            __('Data Management', 'ai-post-scheduler'),
            'manage_options',
            'aips-db-manager',
            array($this, 'render_data_management_page')
        );

        add_submenu_page(
            'ai-post-scheduler',
            __('System Status', 'ai-post-scheduler'),
            __('System Status', 'ai-post-scheduler'),
            'manage_options',
            'aips-system-status',
            array($this, 'render_status_page')
        );

        // Add Seeder page conditionally based on debug mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_submenu_page(
                'ai-post-scheduler',
                __('Data Seeder', 'ai-post-scheduler'),
                __('Data Seeder', 'ai-post-scheduler'),
                'manage_options',
                'aips-seeder',
                array($this, 'render_seeder_page')
            );
        }

        // Only show dev tools if developer mode is enabled or WP_DEBUG is true
        $dev_mode = get_option('aips_developer_mode', 0);
        if ($dev_mode || (defined('WP_DEBUG') && WP_DEBUG)) {
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

    public function render_dashboard_page() {
        $controller = new AIPS_Dashboard_Controller();
        $controller->render_page();
    }

    public function render_voices_page() {
        $voices_handler = new AIPS_Voices();
        $voices_handler->render_page();
    }

    public function render_templates_page() {
        $templates_handler = new AIPS_Templates();
        $templates_handler->render_page();
    }

    public function render_schedule_page() {
        $controller = new AIPS_Schedule_Controller();
        if (method_exists($controller, 'render_page')) {
            $controller->render_page();
        } else {
            include AIPS_PLUGIN_DIR . 'templates/admin/schedule.php';
        }
    }

    public function render_schedule_calendar_page() {
        $controller = new AIPS_Calendar_Controller();
        if (method_exists($controller, 'render_page')) {
            $controller->render_page();
        } else {
            include AIPS_PLUGIN_DIR . 'templates/admin/calendar.php';
        }
    }

    public function render_research_page() {
        $controller = new AIPS_Research_Controller();
        if (method_exists($controller, 'render_page')) {
            $controller->render_page();
        } else {
            include AIPS_PLUGIN_DIR . 'templates/admin/research.php';
        }
    }

    public function render_authors_page() {
        $controller = new AIPS_Authors_Controller();
        if (method_exists($controller, 'render_page')) {
            $controller->render_page();
        } else {
            include AIPS_PLUGIN_DIR . 'templates/admin/authors.php';
        }
    }

    public function render_generated_posts_page() {
        $controller = new AIPS_Generated_Posts_Controller();
        $controller->render_page();
    }

    public function render_post_review_page() {
        global $aips_post_review_handler;
        if (!isset($aips_post_review_handler)) {
            $post_review_handler = null;
        } else {
            $post_review_handler = $aips_post_review_handler;
        }
        include AIPS_PLUGIN_DIR . 'templates/admin/post-review.php';
    }

    public function render_structures_page() {
        $controller = new AIPS_Structures_Controller();
        if (method_exists($controller, 'render_page')) {
            $controller->render_page();
        } else {
            $structure_repo = new AIPS_Article_Structure_Repository();
            $section_repo = new AIPS_Prompt_Section_Repository();

            $structures = $structure_repo->get_all(false);
            $sections = $section_repo->get_all(false);

            include AIPS_PLUGIN_DIR . 'templates/admin/structures.php';
        }
    }

    public function render_prompt_sections_page() {
        $controller = new AIPS_Prompt_Sections_Controller();
        if (method_exists($controller, 'render_page')) {
            $controller->render_page();
        } else {
            $section_repo = new AIPS_Prompt_Section_Repository();
            $sections = $section_repo->get_all(false);

            include AIPS_PLUGIN_DIR . 'templates/admin/sections.php';
        }
    }

    public function render_history_page() {
        $history_handler = new AIPS_History();
        $history_handler->render_page();
    }

    public function render_settings_page() {
        include AIPS_PLUGIN_DIR . 'templates/admin/settings.php';
    }

    public function render_seeder_page() {
        $controller = new AIPS_Seeder_Admin();
        if (method_exists($controller, 'render_page')) {
            $controller->render_page();
        } else {
            include AIPS_PLUGIN_DIR . 'templates/admin/seeder.php';
        }
    }

    public function render_status_page() {
        $status_handler = new AIPS_System_Status();
        $status_handler->render_page();
    }

    public function render_dev_tools_page() {
        $dev_tools = new AIPS_Dev_Tools();
        $dev_tools->render_page();
    }

    public function render_data_management_page() {
        global $aips_db_manager;
        if (isset($aips_db_manager)) {
            $aips_db_manager->render_admin_page();
        } else {
            $db_manager = new AIPS_DB_Manager();
            $db_manager->render_admin_page();
        }
    }

    public function render_planner_page() {
        $planner = new AIPS_Planner();
        if (method_exists($planner, 'render_page')) {
            $planner->render_page();
        } else {
            // fallback if planner class isn't fully set up yet
        }
    }
}
