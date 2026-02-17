<?php

namespace AIPS\Admin;

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
class Settings {
    
    /**
     * Initialize the settings class.
     *
     * Hooks into admin_menu, admin_init, and admin_enqueue_scripts.
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_pages'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_aips_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_aips_get_activity', array($this, 'ajax_get_activity'));
        add_action('wp_ajax_aips_get_activity_detail', array($this, 'ajax_get_activity_detail'));
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
     * Add a non-clickable section header to the submenu.
     *
     * Creates a visual separator/header in the admin menu for grouping related pages.
     * Section headers are styled to be non-interactive with CSS.
     *
     * @param string $parent_slug The slug of the parent menu.
     * @param string $title The title of the section header.
     * @return void
     */
    private function add_section_header($parent_slug, $title) {
        global $submenu;
        
        if (isset($submenu[$parent_slug])) {
            // Create a unique slug for this section header
            $section_slug = 'aips-section-' . sanitize_title($title);
            
            // Register a submenu page with a redirect callback
            add_submenu_page(
                $parent_slug,
                $title,
                $title,
                'manage_options',
                $section_slug,
                array($this, 'redirect_section_header')
            );
            
            // Find and update the menu class
            if (isset($submenu[$parent_slug])) {
                foreach ($submenu[$parent_slug] as $key => $item) {
                    if ($item[2] === $section_slug) {
                        $submenu[$parent_slug][$key][4] = 'aips-menu-section-header';
                        break;
                    }
                }
            }
        }
    }
    
    /**
     * Redirect section header access to dashboard.
     *
     * If someone tries to access a section header URL directly, redirect them
     * to the dashboard to prevent permission errors.
     *
     * @return void
     */
    public function redirect_section_header() {
        wp_safe_redirect(admin_url('admin.php?page=ai-post-scheduler'));
        exit;
    }
    
    /**
     * Register plugin settings and fields.
     *
     * Defines the settings section and fields for general configuration including
     * post status, category, AI model, retries, and logging.
     *
     * @return void
     */
    public function register_settings() {
        register_setting('aips_settings', 'aips_default_post_status', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting('aips_settings', 'aips_default_category', array(
            'sanitize_callback' => 'absint'
        ));
        register_setting('aips_settings', 'aips_enable_logging', array(
            'sanitize_callback' => 'absint'
        ));
        register_setting('aips_settings', 'aips_developer_mode', array(
            'sanitize_callback' => 'absint'
        ));
        register_setting('aips_settings', 'aips_retry_max_attempts', array(
            'sanitize_callback' => 'absint'
        ));
        register_setting('aips_settings', 'aips_ai_model', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting('aips_settings', 'aips_chatbot_id', array(
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'default'
        ));
        register_setting('aips_settings', 'aips_unsplash_access_key', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting('aips_settings', 'aips_review_notifications_enabled', array(
            'sanitize_callback' => 'absint'
        ));
        register_setting('aips_settings', 'aips_review_notifications_email', array(
            'sanitize_callback' => 'sanitize_email'
        ));
        
        add_settings_section(
            'aips_general_section',
            __('General Settings', 'ai-post-scheduler'),
            array($this, 'general_section_callback'),
            'aips-settings'
        );
        
        add_settings_field(
            'aips_default_post_status',
            __('Default Post Status', 'ai-post-scheduler'),
            array($this, 'post_status_field_callback'),
            'aips-settings',
            'aips_general_section'
        );
        
        add_settings_field(
            'aips_default_category',
            __('Default Category', 'ai-post-scheduler'),
            array($this, 'category_field_callback'),
            'aips-settings',
            'aips_general_section'
        );
        
        add_settings_field(
            'aips_ai_model',
            __('AI Model', 'ai-post-scheduler'),
            array($this, 'ai_model_field_callback'),
            'aips-settings',
            'aips_general_section'
        );
        
        add_settings_field(
            'aips_chatbot_id',
            __('Chatbot ID', 'ai-post-scheduler'),
            array($this, 'chatbot_id_field_callback'),
            'aips-settings',
            'aips_general_section'
        );

        add_settings_field(
            'aips_unsplash_access_key',
            __('Unsplash Access Key', 'ai-post-scheduler'),
            array($this, 'unsplash_access_key_field_callback'),
            'aips-settings',
            'aips_general_section'
        );
        
        add_settings_field(
            'aips_retry_max_attempts',
            __('Max Retries on Failure', 'ai-post-scheduler'),
            array($this, 'max_retries_field_callback'),
            'aips-settings',
            'aips_general_section'
        );
        
        add_settings_field(
            'aips_enable_logging',
            __('Enable Logging', 'ai-post-scheduler'),
            array($this, 'logging_field_callback'),
            'aips-settings',
            'aips_general_section'
        );

        add_settings_field(
            'aips_developer_mode',
            __('Developer Mode', 'ai-post-scheduler'),
            array($this, 'developer_mode_field_callback'),
            'aips-settings',
            'aips_general_section'
        );
        
        add_settings_field(
            'aips_review_notifications_enabled',
            __('Send Email Notifications for Posts Awaiting Review', 'ai-post-scheduler'),
            array($this, 'review_notifications_enabled_field_callback'),
            'aips-settings',
            'aips_general_section'
        );
        
        add_settings_field(
            'aips_review_notifications_email',
            __('Notifications Email Address', 'ai-post-scheduler'),
            array($this, 'review_notifications_email_field_callback'),
            'aips-settings',
            'aips_general_section'
        );
    }
        
    /**
     * Render the description for the general settings section.
     *
     * @return void
     */
    public function general_section_callback() {
        echo '<p>' . esc_html__('Configure default settings for AI-generated posts.', 'ai-post-scheduler') . '</p>';
    }
    
    /**
     * Render the default post status setting field.
     *
     * Displays a dropdown to select between draft, pending, or publish.
     *
     * @return void
     */
    public function post_status_field_callback() {
        $value = get_option('aips_default_post_status', 'draft');
        ?>
        <select name="aips_default_post_status">
            <option value="draft" <?php selected($value, 'draft'); ?>><?php esc_html_e('Draft', 'ai-post-scheduler'); ?></option>
            <option value="pending" <?php selected($value, 'pending'); ?>><?php esc_html_e('Pending Review', 'ai-post-scheduler'); ?></option>
            <option value="publish" <?php selected($value, 'publish'); ?>><?php esc_html_e('Published', 'ai-post-scheduler'); ?></option>
        </select>
        <p class="description"><?php esc_html_e('Default status for newly generated posts.', 'ai-post-scheduler'); ?></p>
        <?php
    }
    
    /**
     * Render the default category setting field.
     *
     * Displays a dropdown of available post categories.
     *
     * @return void
     */
    public function category_field_callback() {
        $value = get_option('aips_default_category', 0);
        wp_dropdown_categories(array(
            'name' => 'aips_default_category',
            'selected' => $value,
            'show_option_none' => __('Select a category', 'ai-post-scheduler'),
            'option_none_value' => 0,
            'hide_empty' => false,
        ));
        echo '<p class="description">' . esc_html__('Default category for generated posts.', 'ai-post-scheduler') . '</p>';
    }
    
    /**
     * Render the AI model setting field.
     *
     * Displays a text input for specifying a custom AI Engine model.
     *
     * @return void
     */
    public function ai_model_field_callback() {
        $value = get_option('aips_ai_model', '');
        ?>
        <input type="text" name="aips_ai_model" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="Leave empty for default">
        <p class="description"><?php esc_html_e('AI Engine model to use (leave empty to use AI Engine default).', 'ai-post-scheduler'); ?></p>
        <?php
    }
    
    /**
     * Render the Chatbot ID field.
     *
     * Allows users to specify which AI Engine chatbot to use for post generation.
     *
     * @return void
     */
    public function chatbot_id_field_callback() {
        $value = get_option('aips_chatbot_id', 'default');
        ?>
        <input type="text" name="aips_chatbot_id" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="default">
        <p class="description"><?php esc_html_e('AI Engine chatbot ID to use for post generation. This enables conversational context between title, content, and excerpt generation for better coherence.', 'ai-post-scheduler'); ?></p>
        <?php
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
        $dev_tools = new \AIPS_Dev_Tools();
        $dev_tools->render_page();
    }

    /**
     * Render Unsplash access key field.
     *
     * Provides a place to store the Unsplash API key required for image searches.
     *
     * @return void
     */
    public function unsplash_access_key_field_callback() {
        $value = get_option('aips_unsplash_access_key', '');
        ?>
        <input type="text" name="aips_unsplash_access_key" value="<?php echo esc_attr($value); ?>" class="regular-text" autocomplete="new-password">
        <p class="description"><?php esc_html_e('Required for fetching images from Unsplash. Generate a Client ID at unsplash.com/developers.', 'ai-post-scheduler'); ?></p>
        <?php
    }
    
    /**
     * Render the max retries setting field.
     *
     * Displays a number input for configuring retry attempts on failure.
     *
     * @return void
     */
    public function max_retries_field_callback() {
        $value = get_option('aips_retry_max_attempts', 3);
        ?>
        <input type="number" name="aips_retry_max_attempts" value="<?php echo esc_attr($value); ?>" min="0" max="10" class="small-text">
        <p class="description"><?php esc_html_e('Number of retry attempts if generation fails.', 'ai-post-scheduler'); ?></p>
        <?php
    }
    
    /**
     * Render the logging enable setting field.
     *
     * Displays a checkbox to enable or disable detailed logging.
     *
     * @return void
     */
    public function logging_field_callback() {
        $value = get_option('aips_enable_logging', 1);
        ?>
        <label>
            <input type="checkbox" name="aips_enable_logging" value="1" <?php checked($value, 1); ?>>
            <?php esc_html_e('Enable detailed logging for debugging', 'ai-post-scheduler'); ?>
        </label>
        <?php
    }

    /**
     * Render the developer mode setting field.
     *
     * Displays a checkbox to enable or disable developer mode.
     *
     * @return void
     */
    public function developer_mode_field_callback() {
        $value = get_option('aips_developer_mode', 0);
        ?>
        <label>
            <input type="checkbox" name="aips_developer_mode" value="1" <?php checked($value, 1); ?>>
            <?php esc_html_e('Enable developer tools and features', 'ai-post-scheduler'); ?>
        </label>
        <?php
    }
    
    /**
     * Render the review notifications enabled setting field.
     *
     * Displays a checkbox to enable or disable email notifications for posts awaiting review.
     *
     * @return void
     */
    public function review_notifications_enabled_field_callback() {
        $value = get_option('aips_review_notifications_enabled', 0);
        ?>
        <input type="hidden" name="aips_review_notifications_enabled" value="0">
        <label>
            <input type="checkbox" name="aips_review_notifications_enabled" value="1" <?php checked($value, 1); ?>>
            <?php esc_html_e('Send daily email notifications when posts are awaiting review', 'ai-post-scheduler'); ?>
        </label>
        <p class="description"><?php esc_html_e('A daily email will be sent with a list of draft posts pending review.', 'ai-post-scheduler'); ?></p>
        <?php
    }
    
    /**
     * Render the review notifications email setting field.
     *
     * Displays an email input field for the notifications recipient.
     *
     * @return void
     */
    public function review_notifications_email_field_callback() {
        $value = get_option('aips_review_notifications_email', get_option('admin_email'));
        ?>
        <input type="email" name="aips_review_notifications_email" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <p class="description"><?php esc_html_e('Email address to receive notifications about posts awaiting review.', 'ai-post-scheduler'); ?></p>
        <?php
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
        $controller = new \AIPS_Dashboard_Controller();
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
        $voices_handler = new \AIPS_Voices();
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
        $templates_handler = new \AIPS_Templates();
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
     * Render the Activity page.
     *
     * Includes the activity template file.
     *
     * @return void
     */
    public function render_activity_page() {
        // Use History Service to get activity feed
        $history_service = new \AIPS_History_Service();
        
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
        $controller = new \AIPS_Generated_Posts_Controller();
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
        $structure_repo = new \AIPS_Article_Structure_Repository();
        $section_repo = new \AIPS_Prompt_Section_Repository();
        
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
        $section_repo = new \AIPS_Prompt_Section_Repository();
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
        $history_handler = new \AIPS_History();
        $history_handler->render_page();
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
        $status_handler = new \AIPS_System_Status();
        $status_handler->render_page();
    }

    /**
     * Handle AJAX request to test AI connection.
     *
     * @return void
     */
    public function ajax_test_connection() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized access.', 'ai-post-scheduler')));
        }

        $ai_service = new \AIPS_AI_Service();
        $result = $ai_service->generate_text('Say "Hello World" in 2 words.', array('max_tokens' => 10));

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        } else {
            // SECURITY: Escape the AI response before sending it to the browser to prevent XSS.
            // Even though the prompt is hardcoded ("Say Hello World"), the AI response should be treated as untrusted.
            wp_send_json_success(array('message' => __('Connection successful! AI response: ', 'ai-post-scheduler') . esc_html($result)));
        }
    }

    /**
     * AJAX handler to get activity feed.
     */
    public function ajax_get_activity() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized access.', 'ai-post-scheduler')));
        }

        $filter = isset($_POST['filter']) ? sanitize_text_field($_POST['filter']) : 'all';
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 50;

        $history_service = new \AIPS_History_Service();
        
        // Build filters
        $filters = array();
        if ($search) {
            $filters['search'] = $search;
        }

        // Map filter to event types or statuses
        if ($filter === 'published') {
            $filters['event_type'] = 'post_published';
        } elseif ($filter === 'drafts') {
            $filters['event_type'] = 'post_generated';
        } elseif ($filter === 'failed') {
            $filters['event_status'] = 'failed';
        }

        $activity_logs = $history_service->get_activity_feed($limit, 0, $filters);

        // Format activities for the frontend
        $activities = array();
        foreach ($activity_logs as $log) {
            $details = json_decode($log->details, true);
            
            $activity = array(
                'id' => $log->id,
                'message' => $log->log_type,
                'type' => isset($details['event_type']) ? $details['event_type'] : 'info',
                'status' => isset($details['event_status']) ? $details['event_status'] : 'info',
                'date_formatted' => mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $log->timestamp),
                'post' => null
            );

            // Get post data if available
            if ($log->post_id) {
                $post = get_post($log->post_id);
                if ($post) {
                    $activity['post'] = array(
                        'id' => $post->ID,
                        'title' => $post->post_title,
                        'status' => $post->post_status,
                        'edit_url' => get_edit_post_link($post->ID, 'raw')
                    );
                }
            }

            $activities[] = $activity;
        }

        wp_send_json_success(array('activities' => $activities));
    }

    /**
     * AJAX handler to get activity detail for a specific post.
     */
    public function ajax_get_activity_detail() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized access.', 'ai-post-scheduler')));
        }

        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

        if (!$post_id) {
            wp_send_json_error(array('message' => __('Invalid post ID.', 'ai-post-scheduler')));
        }

        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(array('message' => __('Post not found.', 'ai-post-scheduler')));
        }

        $detail = array(
            'id' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'status' => $post->post_status,
            'date' => mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $post->post_date),
            'author' => get_the_author_meta('display_name', $post->post_author),
            'edit_url' => get_edit_post_link($post->ID, 'raw'),
            'view_url' => get_permalink($post->ID),
            'featured_image_url' => get_the_post_thumbnail_url($post->ID, 'large'),
            'categories' => array(),
            'tags' => array()
        );

        // Get categories
        $categories = get_the_category($post->ID);
        foreach ($categories as $category) {
            $detail['categories'][] = $category->name;
        }

        // Get tags
        $tags = get_the_tags($post->ID);
        if ($tags) {
            foreach ($tags as $tag) {
                $detail['tags'][] = $tag->name;
            }
        }

        wp_send_json_success(array('post' => $detail));
    }
}
