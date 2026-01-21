<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Settings
 *
 * Handles the registration of settings and fields for the AI Post Scheduler plugin.
 *
 * @package AI_Post_Scheduler
 */
class AIPS_Settings {
    
    /**
     * Initialize the settings class.
     *
     * Hooks into admin_init.
     */
    public function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_aips_test_connection', array($this, 'ajax_test_connection'));
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
        register_setting('aips_settings', 'aips_unsplash_access_key', array(
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
     * Handle AJAX request to test AI connection.
     *
     * @return void
     */
    public function ajax_test_connection() {
        check_ajax_referer('aips_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized access.', 'ai-post-scheduler')));
        }

        $ai_service = new AIPS_AI_Service();
        $result = $ai_service->generate_text('Say "Hello World" in 2 words.', array('max_tokens' => 10));

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        } else {
            // SECURITY: Escape the AI response before sending it to the browser to prevent XSS.
            // Even though the prompt is hardcoded ("Say Hello World"), the AI response should be treated as untrusted.
            wp_send_json_success(array('message' => __('Connection successful! AI response: ', 'ai-post-scheduler') . esc_html($result)));
        }
    }
}
