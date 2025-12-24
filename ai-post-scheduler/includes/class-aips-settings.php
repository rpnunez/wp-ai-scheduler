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
        register_setting('aips_settings', 'aips_default_post_status');
        register_setting('aips_settings', 'aips_default_category');
        register_setting('aips_settings', 'aips_enable_logging');
        register_setting('aips_settings', 'aips_max_retries');
        register_setting('aips_settings', 'aips_ai_model');
        
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
            'aips_max_retries',
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

        register_setting('aips_settings', 'aips_limit_topic');

        add_settings_section(
            'aips_security_section',
            __('Security Limits', 'ai-post-scheduler'),
            array($this, 'security_section_callback'),
            'aips-settings'
        );

        add_settings_field(
            'aips_limit_topic',
            __('Topic Character Limit', 'ai-post-scheduler'),
            array($this, 'limit_topic_field_callback'),
            'aips-settings',
            'aips_security_section'
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
        $value = get_option('aips_max_retries', 3);
        ?>
        <input type="number" name="aips_max_retries" value="<?php echo esc_attr($value); ?>" min="0" max="10" class="small-text">
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
     * Render the description for the security limits section.
     *
     * @return void
     */
    public function security_section_callback() {
        echo '<p>' . esc_html__('Configure security limits to prevent misuse.', 'ai-post-scheduler') . '</p>';
    }

    /**
     * Render the topic limit setting field.
     *
     * Displays a number input for the topic character limit.
     *
     * @return void
     */
    public function limit_topic_field_callback() {
        $value = get_option('aips_limit_topic', 1000);
        ?>
        <input type="number" name="aips_limit_topic" value="<?php echo esc_attr($value); ?>" min="10" max="10000" class="small-text">
        <p class="description"><?php esc_html_e('Maximum number of characters allowed for topics.', 'ai-post-scheduler'); ?></p>
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
        global $wpdb;
        
        $table_history = $wpdb->prefix . 'aips_history';
        $table_schedule = $wpdb->prefix . 'aips_schedule';
        $table_templates = $wpdb->prefix . 'aips_templates';
        
        $total_generated = $wpdb->get_var("SELECT COUNT(*) FROM $table_history WHERE status = 'completed'");
        $pending_scheduled = $wpdb->get_var("SELECT COUNT(*) FROM $table_schedule WHERE is_active = 1");
        $total_templates = $wpdb->get_var("SELECT COUNT(*) FROM $table_templates WHERE is_active = 1");
        $failed_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_history WHERE status = 'failed'");
        
        $recent_posts = $wpdb->get_results("SELECT * FROM $table_history ORDER BY created_at DESC LIMIT 5");
        
        $upcoming = $wpdb->get_results("
            SELECT s.*, t.name as template_name 
            FROM $table_schedule s 
            LEFT JOIN $table_templates t ON s.template_id = t.id 
            WHERE s.is_active = 1 
            ORDER BY s.next_run ASC 
            LIMIT 5
        ");
        
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
