<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Admin_UI
 *
 * Handles the registration of admin menu pages and enqueueing of admin assets.
 * Acts as the main entry point for the Admin Interface.
 *
 * @package AI_Post_Scheduler
 */
class AIPS_Admin_UI {
    
    /**
     * Initialize the Admin UI class.
     *
     * Hooks into admin_menu and admin_enqueue_scripts.
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_pages'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Add menu pages to the WordPress admin dashboard.
     *
     * Registers the main menu page and subpages.
     *
     * @return void
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
            __('Voices', 'ai-post-scheduler'),
            __('Voices', 'ai-post-scheduler'),
            'manage_options',
            'aips-voices',
            array($this, 'render_voices_page')
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
            __('Schedule', 'ai-post-scheduler'),
            __('Schedule', 'ai-post-scheduler'),
            'manage_options',
            'aips-schedule',
            array($this, 'render_schedule_page')
        );
        
        add_submenu_page(
            'ai-post-scheduler',
            __('Trending Topics', 'ai-post-scheduler'),
            __('Trending Topics', 'ai-post-scheduler'),
            'manage_options',
            'aips-research',
            array($this, 'render_research_page')
        );
        
        add_submenu_page(
            'ai-post-scheduler',
            __('Activity', 'ai-post-scheduler'),
            __('Activity', 'ai-post-scheduler'),
            'manage_options',
            'aips-activity',
            array($this, 'render_activity_page')
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
            __('Article Structure Sections', 'ai-post-scheduler'),
            __('Article Structure Sections', 'ai-post-scheduler'),
            'manage_options',
            'aips-prompt-sections',
            array($this, 'render_prompt_sections_page')
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
    }
    
    /**
     * Enqueue admin styles and scripts.
     *
     * Loads CSS and JS assets only on plugin-specific pages.
     *
     * @param string $hook The current admin page hook.
     * @return void
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'ai-post-scheduler') === false && strpos($hook, 'aips-') === false) {
            return;
        }

        if (function_exists('wp_enqueue_media')) {
            wp_enqueue_media();
        }
        
        wp_enqueue_style(
            'aips-admin-style',
            AIPS_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            AIPS_VERSION
        );
        
        wp_enqueue_script(
            'aips-admin-script',
            AIPS_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            AIPS_VERSION,
            true
        );

        wp_localize_script('aips-admin-script', 'aipsAdminL10n', array(
            'deleteStructureConfirm' => __('Are you sure you want to delete this structure?', 'ai-post-scheduler'),
            'saveStructureFailed' => __('Failed to save structure.', 'ai-post-scheduler'),
            'loadStructureFailed' => __('Failed to load structure.', 'ai-post-scheduler'),
            'deleteStructureFailed' => __('Failed to delete structure.', 'ai-post-scheduler'),
            'deleteSectionConfirm' => __('Are you sure you want to delete this prompt section?', 'ai-post-scheduler'),
            'saveSectionFailed' => __('Failed to save prompt section.', 'ai-post-scheduler'),
            'loadSectionFailed' => __('Failed to load prompt section.', 'ai-post-scheduler'),
            'deleteSectionFailed' => __('Failed to delete prompt section.', 'ai-post-scheduler'),
            'errorOccurred' => __('An error occurred.', 'ai-post-scheduler'),
            'errorTryAgain' => __('An error occurred. Please try again.', 'ai-post-scheduler'),
            'clickToConfirm' => __('Click again to confirm', 'ai-post-scheduler'),
        ));

        wp_enqueue_script(
            'aips-admin-research',
            AIPS_PLUGIN_URL . 'assets/js/admin-research.js',
            array('aips-admin-script'),
            AIPS_VERSION,
            true
        );

        wp_localize_script('aips-admin-research', 'aipsResearchL10n', array(
            'topicsSaved' => __('topics saved for', 'ai-post-scheduler'),
            'topTopics' => __('Top 5 Topics:', 'ai-post-scheduler'),
            'noTopicsFound' => __('No topics found matching your filters.', 'ai-post-scheduler'),
            'deleteTopicConfirm' => __('Delete this topic?', 'ai-post-scheduler'),
            'selectTopicSchedule' => __('Please select at least one topic to schedule.', 'ai-post-scheduler'),
            'researchError' => __('An error occurred during research.', 'ai-post-scheduler'),
            'schedulingError' => __('An error occurred during scheduling.', 'ai-post-scheduler'),
            'delete' => __('Delete', 'ai-post-scheduler'),
        ));

        wp_enqueue_script(
            'aips-admin-planner',
            AIPS_PLUGIN_URL . 'assets/js/admin-planner.js',
            array('aips-admin-script'),
            AIPS_VERSION,
            true
        );

        wp_enqueue_script(
            'aips-admin-db',
            AIPS_PLUGIN_URL . 'assets/js/admin-db.js',
            array('aips-admin-script'),
            AIPS_VERSION,
            true
        );
        
        wp_enqueue_script(
            'aips-admin-activity',
            AIPS_PLUGIN_URL . 'assets/js/admin-activity.js',
            array('aips-admin-script'),
            AIPS_VERSION,
            true
        );
        
        wp_localize_script('aips-admin-script', 'aipsAjax', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aips_ajax_nonce'),
        ));
        
        wp_localize_script('aips-admin-activity', 'aipsActivityL10n', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aips_activity_nonce'),
            'confirmPublish' => __('Are you sure you want to publish this post?', 'ai-post-scheduler'),
            'publishSuccess' => __('Post published successfully!', 'ai-post-scheduler'),
            'publishError' => __('Failed to publish post.', 'ai-post-scheduler'),
            'loadingError' => __('Failed to load activity data.', 'ai-post-scheduler'),
        ));
    }
    
    /**
     * Render the main dashboard page.
     *
     * @return void
     */
    public function render_dashboard_page() {
        // Use repositories instead of direct SQL
        $history_repo = new AIPS_History_Repository();
        $schedule_repo = new AIPS_Schedule_Repository();
        $template_repo = new AIPS_Template_Repository();
        
        // Get stats
        $history_stats = $history_repo->get_stats();
        $schedule_counts = $schedule_repo->count_by_status();
        $template_counts = $template_repo->count_by_status();
        
        $total_generated = $history_stats['completed'];
        $pending_scheduled = $schedule_counts['active'];
        $total_templates = $template_counts['active'];
        $failed_count = $history_stats['failed'];
        
        // Get recent history
        $recent_posts_data = $history_repo->get_history(array('per_page' => 5));
        $recent_posts = $recent_posts_data['items'];
        
        // Get upcoming schedules
        // Fix: Use get_upcoming from AIPS_Schedule_Repository
        $upcoming = $schedule_repo->get_upcoming(5);
        
        include AIPS_PLUGIN_DIR . 'templates/admin/dashboard.php';
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
     * Render the Activity page.
     *
     * Includes the activity template file.
     *
     * @return void
     */
    public function render_activity_page() {
        include AIPS_PLUGIN_DIR . 'templates/admin/activity.php';
    }

    /**
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
     * Render the Settings page.
     *
     * Delegates to AIPS_Settings_Manager.
     *
     * @return void
     */
    public function render_settings_page() {
        $settings_manager = new AIPS_Settings_Manager();
        $settings_manager->render_settings_page();
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
}
