<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Admin_Menu
 *
 * Handles the registration of admin menu pages for the AI Post Scheduler plugin.
 *
 * @package AI_Post_Scheduler
 */
class AIPS_Admin_Menu {

    /**
     * @var AIPS_Settings The settings instance for callback delegation.
     */
    private $settings;

    /**
     * Initialize the menu class.
     *
     * @param AIPS_Settings $settings The settings instance.
     */
    public function __construct(AIPS_Settings $settings) {
        $this->settings = $settings;
        add_action('admin_menu', array($this, 'add_menu_pages'));
    }

    /**
     * Add menu pages to the WordPress admin dashboard.
     *
     * Registers the main menu page and subpages for Dashboard, Voices, Templates,
     * Schedule, Research, Settings, and System Status.
     *
     * @return void
     */
    public function add_menu_pages() {
        add_menu_page(
            __('AI Post Scheduler', 'ai-post-scheduler'),
            __('AI Post Scheduler', 'ai-post-scheduler'),
            'manage_options',
            'ai-post-scheduler',
            array($this->settings, 'render_dashboard_page'),
            'dashicons-schedule',
            30
        );

        add_submenu_page(
            'ai-post-scheduler',
            __('Dashboard', 'ai-post-scheduler'),
            __('Dashboard', 'ai-post-scheduler'),
            'manage_options',
            'ai-post-scheduler',
            array($this->settings, 'render_dashboard_page')
        );

        add_submenu_page(
            'ai-post-scheduler',
            __('Voices', 'ai-post-scheduler'),
            __('Voices', 'ai-post-scheduler'),
            'manage_options',
            'aips-voices',
            array($this->settings, 'render_voices_page')
        );

        add_submenu_page(
            'ai-post-scheduler',
            __('Templates', 'ai-post-scheduler'),
            __('Templates', 'ai-post-scheduler'),
            'manage_options',
            'aips-templates',
            array($this->settings, 'render_templates_page')
        );

        add_submenu_page(
            'ai-post-scheduler',
            __('Schedule', 'ai-post-scheduler'),
            __('Schedule', 'ai-post-scheduler'),
            'manage_options',
            'aips-schedule',
            array($this->settings, 'render_schedule_page')
        );

         add_submenu_page(
             'ai-post-scheduler',
             __('Research', 'ai-post-scheduler'),
             __('Research', 'ai-post-scheduler'),
             'manage_options',
             'aips-research',
             array($this->settings, 'render_research_page')
         );

        add_submenu_page(
            'ai-post-scheduler',
            __('Activity', 'ai-post-scheduler'),
            __('Activity', 'ai-post-scheduler'),
            'manage_options',
            'aips-activity',
            array($this->settings, 'render_activity_page')
        );

        add_submenu_page(
            'ai-post-scheduler',
            __('Article Structures', 'ai-post-scheduler'),
            __('Article Structures', 'ai-post-scheduler'),
            'manage_options',
            'aips-structures',
            array($this->settings, 'render_structures_page')
        );

        add_submenu_page(
            'ai-post-scheduler',
            __('Article Structure Sections', 'ai-post-scheduler'),
            __('Article Structure Sections', 'ai-post-scheduler'),
            'manage_options',
            'aips-prompt-sections',
            array($this->settings, 'render_prompt_sections_page')
        );

        add_submenu_page(
            'ai-post-scheduler',
            __('Settings', 'ai-post-scheduler'),
            __('Settings', 'ai-post-scheduler'),
            'manage_options',
            'aips-settings',
            array($this->settings, 'render_settings_page')
        );

        add_submenu_page(
            'ai-post-scheduler',
            __('System Status', 'ai-post-scheduler'),
            __('System Status', 'ai-post-scheduler'),
            'manage_options',
            'aips-status',
            array($this->settings, 'render_status_page')
        );

        if (get_option('aips_developer_mode')) {
            add_submenu_page(
                'ai-post-scheduler',
                __('Dev Tools', 'ai-post-scheduler'),
                __('Dev Tools', 'ai-post-scheduler'),
                'manage_options',
                'aips-dev-tools',
                array($this->settings, 'render_dev_tools_page')
            );
        }
    }
}
