<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Settings
 *
 * Handles the registration of admin menu pages, settings, and rendering of admin interfaces
 * for the AI Post Scheduler plugin.
 *
 * @package AI_Post_Scheduler
 */
class AIPS_Settings {
    
    /**
     * @var AIPS_Settings_Manager
     */
    private $settings_manager;

    /**
     * @var AIPS_Admin_Assets
     */
    private $assets_manager;

    /**
     * Initialize the settings class.
     *
     * Hooks into admin_menu and instantiates sub-managers.
     */
    public function __construct() {
        // Instantiate managers
        $this->settings_manager = new AIPS_Settings_Manager();
        $this->assets_manager = new AIPS_Admin_Assets();

        add_action('admin_menu', array($this, 'add_menu_pages'));

        // Delegate asset enqueueing
        add_action('admin_enqueue_scripts', array($this->assets_manager, 'enqueue_admin_assets'));
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
            __('Activity', 'ai-post-scheduler'),
            __('Activity', 'ai-post-scheduler'),
            'manage_options',
            'aips-activity',
            array($this, 'render_activity_page')
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
            __('Post Review', 'ai-post-scheduler'),
            __('Post Review', 'ai-post-scheduler'),
            'manage_options',
            'aips-post-review',
            array($this, 'render_post_review_page')
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
            __('Templates', 'ai-post-scheduler'),
            __('Templates', 'ai-post-scheduler'),
            'manage_options',
            'aips-templates',
            array($this, 'render_templates_page')
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
            'ai-post-scheduler',
            __('Voices', 'ai-post-scheduler'),
            __('Voices', 'ai-post-scheduler'),
            'manage_options',
            'aips-voices',
            array($this, 'render_voices_page')
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
            __('Article Structures', 'ai-post-scheduler'),
            __('Article Structures', 'ai-post-scheduler'),
            'manage_options',
            'aips-structures',
            array($this, 'render_structures_page')
        );

        add_submenu_page(
            'ai-post-scheduler',
            __('Seeder', 'ai-post-scheduler'),
            __('Seeder', 'ai-post-scheduler'),
            'manage_options',
            'aips-seeder',
            array($this, 'render_seeder_page')
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
            __('Settings', 'ai-post-scheduler'),
            __('Settings', 'ai-post-scheduler'),
            'manage_options',
            'aips-settings',
            array($this, 'render_settings_page')
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
     * Render the Dev Tools page.
     *
     * Delegates rendering to the AIPS_Dev_Tools class.
     *
     * @return void
     */
    public function render_dev_tools_page() {
        $dev_tools = new AIPS_Dev_Tools();
        $dev_tools->render_page();
    }
    
    /**
     * Render the main dashboard page.
     *
     * Delegates to AIPS_Dashboard_Controller.
     *
     * @return void
     */
    public function render_dashboard_page() {
        $controller = new AIPS_Dashboard_Controller();
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
     * Render the Activity page.
     *
     * Includes the activity template file.
     *
     * @return void
     */
    public function render_activity_page() {
        // Use History Service to get activity feed
        $history_service = new AIPS_History_Service();
        
        $current_page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $per_page = 50;
        $offset = ($current_page - 1) * $per_page;
        
        $event_type = isset($_GET['event_type']) ? sanitize_text_field($_GET['event_type']) : '';
        $event_status = isset($_GET['event_status']) ? sanitize_text_field($_GET['event_status']) : '';
        $search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        $filters = array();
        if ($event_type) {
            $filters['event_type'] = $event_type;
        }
        if ($event_status) {
            $filters['event_status'] = $event_status;
        }
        if ($search_query) {
            $filters['search'] = $search_query;
        }
        
        $activities = $history_service->get_activity_feed($per_page, $offset, $filters);
        
        include AIPS_PLUGIN_DIR . 'templates/admin/activity.php';
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
     * Includes the post review template file.
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
     * Render the Settings page.
     *
     * Delegates to AIPS_Settings_Manager.
     *
     * @return void
     */
    public function render_settings_page() {
        $this->settings_manager->render_settings_page();
    }

    /**
     * Render the Seeder page.
     *
     * Includes the seeder template file.
     *
     * @return void
     */
    public function render_seeder_page() {
        include AIPS_PLUGIN_DIR . 'templates/admin/seeder.php';
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
