<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIPS_Settings {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_pages'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
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
    }
    
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
    }
    
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
        
        wp_localize_script('aips-admin-script', 'aipsAjax', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aips_ajax_nonce'),
        ));
    }
    
    public function general_section_callback() {
        echo '<p>' . esc_html__('Configure default settings for AI-generated posts.', 'ai-post-scheduler') . '</p>';
    }
    
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
    
    public function ai_model_field_callback() {
        $value = get_option('aips_ai_model', '');
        ?>
        <input type="text" name="aips_ai_model" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="Leave empty for default">
        <p class="description"><?php esc_html_e('AI Engine model to use (leave empty to use AI Engine default).', 'ai-post-scheduler'); ?></p>
        <?php
    }
    
    public function max_retries_field_callback() {
        $value = get_option('aips_max_retries', 3);
        ?>
        <input type="number" name="aips_max_retries" value="<?php echo esc_attr($value); ?>" min="0" max="10" class="small-text">
        <p class="description"><?php esc_html_e('Number of retry attempts if generation fails.', 'ai-post-scheduler'); ?></p>
        <?php
    }
    
    public function logging_field_callback() {
        $value = get_option('aips_enable_logging', 1);
        ?>
        <label>
            <input type="checkbox" name="aips_enable_logging" value="1" <?php checked($value, 1); ?>>
            <?php esc_html_e('Enable detailed logging for debugging', 'ai-post-scheduler'); ?>
        </label>
        <?php
    }
    
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
    
    public function render_templates_page() {
        $templates_handler = new AIPS_Templates();
        $templates_handler->render_page();
    }
    
    public function render_schedule_page() {
        include AIPS_PLUGIN_DIR . 'templates/admin/schedule.php';
    }
    
    public function render_history_page() {
        $history_handler = new AIPS_History();
        $history_handler->render_page();
    }
    
    public function render_settings_page() {
        include AIPS_PLUGIN_DIR . 'templates/admin/settings.php';
    }
}
