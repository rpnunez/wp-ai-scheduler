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
     * Initialize the settings class.
     *
     * Hooks into admin_menu, admin_init, and admin_enqueue_scripts.
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_pages'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Add menu pages to the WordPress admin dashboard.
     *
     * Registers the main menu page and subpages for Dashboard, Voices, Templates,
     * Schedule, History, Settings, and System Status.
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
        
        // Ensure Dashboard is the first submenu
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
        register_setting('aips_settings', 'aips_retry_max_attempts', array(
            'sanitize_callback' => 'absint'
        ));
        register_setting('aips_settings', 'aips_ai_model', array(
            'sanitize_callback' => 'sanitize_text_field'
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
            'aips-admin-dashboard',
            AIPS_PLUGIN_URL . 'assets/js/admin-dashboard.js',
            array('aips-admin-script'),
            AIPS_VERSION,
            true
        );
        
        wp_localize_script('aips-admin-script', 'aipsAjax', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aips_ajax_nonce'),
        ));
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
     * Render the main dashboard page.
     *
     * Delegates to AIPS_Dashboard to render the unified dashboard.
     *
     * @return void
     */
    public function render_dashboard_page() {
        // We use AIPS_Dashboard to render the dashboard tab content,
        // but here we are rendering the top-level page which includes tabs.
        // We should just include main.php, and let main.php include dashboard.php for the dashboard tab.
        // However, existing pages like render_templates_page instantiate a class and call render_page().

        // Since we unified everything into main.php with tabs, and we want 'Dashboard' to be the active tab:
        // We can just instantiate AIPS_Dashboard and call render_page(), BUT AIPS_Dashboard::render_page() currently
        // includes dashboard.php directly (as per my previous step), NOT main.php.

        // Wait, I designed AIPS_Dashboard::render_page to include dashboard.php?
        // Let me check my previous step.
        // Yes: "include AIPS_PLUGIN_DIR . 'templates/admin/dashboard.php';"

        // But main.php is the container with tabs.
        // So render_dashboard_page should include main.php, just like AIPS_Templates::render_page does.

        // Let's reuse AIPS_Templates pattern but for Dashboard.
        // I should have made AIPS_Dashboard::render_page include main.php.
        // Let me fix AIPS_Dashboard::render_page first?
        // Or I can just include main.php here directly.
        // But main.php expects variables/context?
        // Actually main.php just includes other files based on tabs.
        // The *content* of dashboard tab is what needs variables.
        // Those variables are prepared in AIPS_Dashboard::render_page.

        // Correct approach:
        // AIPS_Dashboard::render_page() should PREPARE data, and then include main.php?
        // OR main.php includes dashboard.php, and dashboard.php expects variables.
        // So we need to prepare variables BEFORE including main.php.

        // So I will call AIPS_Dashboard to prepare data, then include main.php.
        // But AIPS_Dashboard::render_page currently includes dashboard.php directly.

        // I will change this method to instantiate AIPS_Dashboard to prepare data (if I can separate it)
        // or just rely on AIPS_Dashboard handling the view.

        // If I use AIPS_Dashboard::render_page() as is, it includes dashboard.php (the inner content).
        // If I want the tabs, I need main.php.

        // Let's look at AIPS_Templates::render_page. It includes main.php.
        // And inside main.php, it includes templates.php.

        // So I should modify AIPS_Dashboard::render_page to include main.php instead of dashboard.php.
        // But I already wrote AIPS_Dashboard::render_page to include dashboard.php.
        // I should update AIPS_Dashboard::render_page in the next step or modify this method to work around it.

        // The best way is to update AIPS_Dashboard::render_page to include main.php.
        // But I can't do that in this step (modifying settings.php).

        // So for now, I will assume AIPS_Dashboard::render_page will be fixed to include main.php
        // OR I will instantiate AIPS_Dashboard, let it prepare data, and then I include main.php?
        // No, AIPS_Dashboard is a class.

        // I will update this method to just call AIPS_Dashboard::render_page()
        // and I will update AIPS_Dashboard::render_page in a separate step (or realized I made a mistake).
        
        // Wait, I can overwrite AIPS_Dashboard.php again.
        // But let's stick to the plan order if possible.
        // I'll update this file to call AIPS_Dashboard::render_page().
        // And I will fix AIPS_Dashboard::render_page in a subsequent step if needed.
        
        // Actually, if I look at my created AIPS_Dashboard::render_page:
        // it fetches stats, then includes templates/admin/dashboard.php.
        // If I call it here, it will output the dashboard content WITHOUT the tabs (main.php wrapper).
        // That's bad.
        
        // I should change AIPS_Dashboard::render_page to include 'templates/admin/main.php'.
        // And inside main.php, it includes dashboard.php.
        // But dashboard.php needs variables ($stats, etc.).
        // Variables scope: if AIPS_Dashboard::render_page includes main.php, and main.php includes dashboard.php,
        // variables defined in render_page should be available in dashboard.php.
        
        // So the fix is indeed in AIPS_Dashboard::render_page.
        // Here I just call it.
        
        $dashboard = new AIPS_Dashboard();
        $dashboard->render_page();
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
     * Includes the settings template file.
     *
     * @return void
     */
    public function render_settings_page() {
        include AIPS_PLUGIN_DIR . 'templates/admin/settings.php';
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
