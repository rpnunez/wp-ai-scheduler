<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Class AIPS_Admin_Menu
 *
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
     * Hooks into admin_menu, parent_file, and submenu_file.
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_pages'));
        add_filter('parent_file', array($this, 'fix_author_topics_parent_file'));
        add_filter('submenu_file', array($this, 'fix_author_topics_submenu_file'));
    }

    /**
     * Add menu pages to the WordPress admin dashboard.
     */
    public function add_menu_pages() {
        add_menu_page(
            __('AI Post Scheduler', 'ai-post-scheduler'),
            __('AI Post Scheduler', 'ai-post-scheduler'),
            'manage_options',
            'ai-post-scheduler',
            array($this, 'render_dashboard_page'),
            'dashicons-schedule',
            30
        );

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

    public function fix_author_topics_parent_file($parent_file) {
        $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
        if ($page === 'aips-author-topics') {
            return 'ai-post-scheduler';
        }
        return $parent_file;
    }

    public function fix_author_topics_submenu_file($submenu_file) {
        $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
        if ($page === 'aips-author-topics') {
            return 'aips-authors';
        }
        return $submenu_file;
    }

    public function render_dashboard_page() {
        AIPS_Container::get_instance()->get('dashboard_controller')->render_page();
    }

    public function render_voices_page() {
        AIPS_Container::get_instance()->get('voices')->render_page();
    }

    public function render_templates_page() {
        AIPS_Container::get_instance()->get('templates')->render_page();
    }

    public function render_schedule_page() {
        AIPS_Container::get_instance()->get('schedule_controller')->render_page();
    }

    public function render_schedule_calendar_page() {
        AIPS_Container::get_instance()->get('calendar_controller')->render_page();
    }

    public function render_research_page() {
        AIPS_Container::get_instance()->get('research_controller')->render_page();
    }

    public function render_authors_page() {
        AIPS_Container::get_instance()->get('authors_controller')->render_page();
    }

    public function render_author_topics_page() {
        AIPS_Container::get_instance()->get('author_topics_controller')->render_page();
    }

    public function render_generated_posts_page() {
        AIPS_Container::get_instance()->get('generated_posts_controller')->render_page();
    }

    public function render_structures_page() {
        AIPS_Container::get_instance()->get('structures_controller')->render_page();
    }

    public function render_history_page() {
        AIPS_Container::get_instance()->get('history')->render_page();
    }

    public function render_settings_page() {
        AIPS_Container::get_instance()->get('settings')->render_page();
    }

    public function render_seeder_page() {
        AIPS_Container::get_instance()->get('seeder')->render_page();
    }

    public function render_status_page() {
        AIPS_Container::get_instance()->get('system_status')->render_page();
    }

    public function render_dev_tools_page() {
        $container = AIPS_Container::get_instance();
        if ( ! $container->has( 'dev_tools' ) ) {
            return;
        }
        $container->get( 'dev_tools' )->render_page();
    }
}
