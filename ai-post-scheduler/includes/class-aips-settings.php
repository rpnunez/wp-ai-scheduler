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
        add_action('wp_ajax_aips_test_connection', array($this, 'ajax_test_connection'));
        add_filter('parent_file', array($this, 'fix_author_topics_parent_file'));
        add_filter('submenu_file', array($this, 'fix_author_topics_submenu_file'));
    }
    
    /**
     * Add menu pages to the WordPress admin dashboard.
     *
     * Registers a traditional flat submenu structure:
     * Dashboard, Templates, Voices, Article Structures, Authors, Research,
     * Schedule, Schedule Calendar, Generated Posts, History,
     * Settings, System Status, Seeder, Dev Tools (when enabled).
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
      
        // Author Topics page - hidden from menu navigation, accessible via URL
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
            __('Sources', 'ai-post-scheduler'),
            __('Sources', 'ai-post-scheduler'),
            'manage_options',
            'aips-sources',
            array($this, 'render_sources_page')
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
    
    /**
     * Expand the "AI Post Scheduler" top-level menu when on the hidden Author Topics page.
     *
     * WordPress collapses the parent menu when a page is registered with null parent_slug.
     * This filter overrides that behaviour so the plugin menu stays open.
     *
     * @param string $parent_file The current parent file slug.
     * @return string
     */
    public function fix_author_topics_parent_file($parent_file) {
        $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
        if ($page === 'aips-author-topics') {
            return 'ai-post-scheduler';
        }
        return $parent_file;
    }

    /**
     * Highlight the "Authors" submenu item when on the hidden Author Topics page.
     *
     * Because the Author Topics page is registered with a null parent, WordPress
     * does not activate any submenu item. This filter makes "Authors" appear active.
     *
     * @param string $submenu_file The current submenu file slug.
     * @return string
     */
    public function fix_author_topics_submenu_file($submenu_file) {
        $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
        if ($page === 'aips-author-topics') {
            return 'aips-authors';
        }
        return $submenu_file;
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
        register_setting('aips_settings', 'aips_review_notifications_enabled', array(
            'sanitize_callback' => 'absint'
        ));
        register_setting('aips_settings', 'aips_review_notifications_email', array(
            'sanitize_callback' => 'sanitize_email'
        ));
        register_setting('aips_settings', 'aips_topic_similarity_threshold', array(
            'sanitize_callback' => array($this, 'sanitize_similarity_threshold'),
            'default' => 0.8
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

        add_settings_field(
            'aips_topic_similarity_threshold',
            __('Topic Similarity Threshold', 'ai-post-scheduler'),
            array($this, 'topic_similarity_threshold_field_callback'),
            'aips-settings',
            'aips_general_section'
        );

        // -----------------------------------------------------------------------
        // Site-wide Content Strategy settings
        //
        // Options are defined via self::get_content_strategy_options(), so the
        // full list is maintained in ONE place. Both settings registration here
        // and AIPS_Site_Context::get() read from that shared list — no duplicates.
        // -----------------------------------------------------------------------
        $cs_options = self::get_content_strategy_options();
        foreach ($cs_options as $option_key => $meta) {
            register_setting('aips_settings', $option_key, array(
                'sanitize_callback' => $meta['sanitize_callback'],
                'default'           => $meta['default'],
            ));
        }

        add_settings_section(
            'aips_content_strategy_section',
            __('Site Content Strategy', 'ai-post-scheduler'),
            array($this, 'content_strategy_section_callback'),
            'aips-settings'
        );

        add_settings_field(
            'aips_site_niche',
            __('Site Niche / Primary Topic', 'ai-post-scheduler'),
            array($this, 'site_niche_field_callback'),
            'aips-settings',
            'aips_content_strategy_section'
        );

        add_settings_field(
            'aips_site_target_audience',
            __('Target Audience', 'ai-post-scheduler'),
            array($this, 'site_target_audience_field_callback'),
            'aips-settings',
            'aips_content_strategy_section'
        );

        add_settings_field(
            'aips_site_content_goals',
            __('Content Goals', 'ai-post-scheduler'),
            array($this, 'site_content_goals_field_callback'),
            'aips-settings',
            'aips_content_strategy_section'
        );

        add_settings_field(
            'aips_site_brand_voice',
            __('Brand Voice / Tone', 'ai-post-scheduler'),
            array($this, 'site_brand_voice_field_callback'),
            'aips-settings',
            'aips_content_strategy_section'
        );

        add_settings_field(
            'aips_site_content_language',
            __('Content Language', 'ai-post-scheduler'),
            array($this, 'site_content_language_field_callback'),
            'aips-settings',
            'aips_content_strategy_section'
        );

        add_settings_field(
            'aips_site_content_guidelines',
            __('Content Guidelines', 'ai-post-scheduler'),
            array($this, 'site_content_guidelines_field_callback'),
            'aips-settings',
            'aips_content_strategy_section'
        );

        add_settings_field(
            'aips_site_excluded_topics',
            __('Excluded Topics (site-wide)', 'ai-post-scheduler'),
            array($this, 'site_excluded_topics_field_callback'),
            'aips-settings',
            'aips_content_strategy_section'
        );
    }

    /**
     * Return the canonical registry of site-wide content strategy options.
     *
     * This is the single source of truth for every option that belongs to the
     * "Site Content Strategy" settings group. Adding a new option here
     * automatically makes it available to AIPS_Site_Context::get() and
     * AIPS_Prompt_Builder::build_site_context_block() without touching those
     * classes.
     *
     * Each entry has:
     *   - 'key'               Short key used by AIPS_Site_Context (e.g. 'niche')
     *   - 'sanitize_callback' Callable used to sanitize the option value on save
     *   - 'default'           Default value returned when the option is not set
     *
     * @return array<string, array{key: string, sanitize_callback: callable, default: mixed}>
     *     Associative array keyed by the full WordPress option name.
     */
    public static function get_content_strategy_options() {
        return array(
            'aips_site_niche' => array(
                'key'               => 'niche',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            ),
            'aips_site_target_audience' => array(
                'key'               => 'target_audience',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            ),
            'aips_site_content_goals' => array(
                'key'               => 'content_goals',
                'sanitize_callback' => 'sanitize_textarea_field',
                'default'           => '',
            ),
            'aips_site_brand_voice' => array(
                'key'               => 'brand_voice',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            ),
            'aips_site_content_language' => array(
                'key'               => 'content_language',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => 'en',
            ),
            'aips_site_content_guidelines' => array(
                'key'               => 'content_guidelines',
                'sanitize_callback' => 'sanitize_textarea_field',
                'default'           => '',
            ),
            'aips_site_excluded_topics' => array(
                'key'               => 'excluded_topics',
                'sanitize_callback' => 'sanitize_textarea_field',
                'default'           => '',
            ),
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
        $dev_tools = new AIPS_Dev_Tools();
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
     * Sanitize the topic similarity threshold value.
     *
     * Clamps the value to the valid range [0.1, 1.0].
     *
     * @param mixed $value Raw input value.
     * @return float Sanitized threshold float.
     */
    public function sanitize_similarity_threshold($value) {
        if (!is_numeric($value)) {
            return 0.8;
        }
        $float = (float) $value;
        return min(1.0, max(0.1, $float));
    }

    /**
     * Render the topic similarity threshold field.
     *
     * Displays a number input for the semantic duplicate detection threshold.
     *
     * @return void
     */
    public function topic_similarity_threshold_field_callback() {
        $raw = get_option('aips_topic_similarity_threshold', 0.8);
        // Normalize on read so the UI always reflects the effective runtime value.
        $value = is_numeric($raw) ? min(1.0, max(0.1, (float) $raw)) : 0.8;
        ?>
        <input
            type="number"
            name="aips_topic_similarity_threshold"
            value="<?php echo esc_attr($value); ?>"
            min="0.1"
            max="1.0"
            step="0.01"
            class="small-text"
        >
        <p class="description">
            <?php esc_html_e('Minimum similarity score (0.1–1.0) used to flag new topics as potential duplicates during generation. A higher value requires topics to be more similar before being flagged. Default: 0.8.', 'ai-post-scheduler'); ?>
        </p>
        <?php
    }

    // -------------------------------------------------------------------------
    // Site Content Strategy field callbacks
    // -------------------------------------------------------------------------

    /**
     * Render the description for the site content strategy settings section.
     *
     * @return void
     */
    public function content_strategy_section_callback() {
        echo '<p>' . esc_html__('Define the overall content identity of your website. These settings are shared across Author Suggestions, topic generation, and post generation to ensure consistent, on-brand output.', 'ai-post-scheduler') . '</p>';
    }

    /**
     * Render the Site Niche / Primary Topic field.
     *
     * @return void
     */
    public function site_niche_field_callback() {
        $value = get_option('aips_site_niche', '');
        ?>
        <input type="text" name="aips_site_niche" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="<?php esc_attr_e('e.g., Personal Finance, WordPress Development, Fitness', 'ai-post-scheduler'); ?>">
        <p class="description"><?php esc_html_e('The main topic or industry your website covers. Used as context for Author Suggestions and AI generation.', 'ai-post-scheduler'); ?></p>
        <?php
    }

    /**
     * Render the Target Audience field.
     *
     * @return void
     */
    public function site_target_audience_field_callback() {
        $value = get_option('aips_site_target_audience', '');
        ?>
        <input type="text" name="aips_site_target_audience" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="<?php esc_attr_e('e.g., Beginner developers, Small business owners, Parents', 'ai-post-scheduler'); ?>">
        <p class="description"><?php esc_html_e('Who your content is written for. Helps the AI tailor the language and depth of generated topics and posts.', 'ai-post-scheduler'); ?></p>
        <?php
    }

    /**
     * Render the Content Goals field.
     *
     * @return void
     */
    public function site_content_goals_field_callback() {
        $value = get_option('aips_site_content_goals', '');
        ?>
        <textarea name="aips_site_content_goals" class="large-text" rows="3" placeholder="<?php esc_attr_e('e.g., Educate readers, Drive product sign-ups, Build a community, Rank on search engines', 'ai-post-scheduler'); ?>"><?php echo esc_textarea($value); ?></textarea>
        <p class="description"><?php esc_html_e('What you want your content to achieve. Informs the angle and call-to-action emphasis in generated content.', 'ai-post-scheduler'); ?></p>
        <?php
    }

    /**
     * Render the Brand Voice / Tone field.
     *
     * @return void
     */
    public function site_brand_voice_field_callback() {
        $value = get_option('aips_site_brand_voice', '');
        ?>
        <input type="text" name="aips_site_brand_voice" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="<?php esc_attr_e('e.g., Friendly and approachable, Authoritative, Conversational', 'ai-post-scheduler'); ?>">
        <p class="description"><?php esc_html_e('The overall voice and tone of your brand. Applied as a default across all authors unless overridden per-author.', 'ai-post-scheduler'); ?></p>
        <?php
    }

    /**
     * Render the Content Language field.
     *
     * @return void
     */
    public function site_content_language_field_callback() {
        $value = get_option('aips_site_content_language', 'en');
        $languages = array(
            'en'    => __('English', 'ai-post-scheduler'),
            'es'    => __('Spanish', 'ai-post-scheduler'),
            'fr'    => __('French', 'ai-post-scheduler'),
            'de'    => __('German', 'ai-post-scheduler'),
            'it'    => __('Italian', 'ai-post-scheduler'),
            'pt'    => __('Portuguese', 'ai-post-scheduler'),
            'nl'    => __('Dutch', 'ai-post-scheduler'),
            'pl'    => __('Polish', 'ai-post-scheduler'),
            'ru'    => __('Russian', 'ai-post-scheduler'),
            'ja'    => __('Japanese', 'ai-post-scheduler'),
            'ko'    => __('Korean', 'ai-post-scheduler'),
            'zh'    => __('Chinese (Simplified)', 'ai-post-scheduler'),
            'ar'    => __('Arabic', 'ai-post-scheduler'),
            'hi'    => __('Hindi', 'ai-post-scheduler'),
            'tr'    => __('Turkish', 'ai-post-scheduler'),
            'sv'    => __('Swedish', 'ai-post-scheduler'),
            'da'    => __('Danish', 'ai-post-scheduler'),
            'fi'    => __('Finnish', 'ai-post-scheduler'),
            'nb'    => __('Norwegian', 'ai-post-scheduler'),
        );
        ?>
        <select name="aips_site_content_language">
            <?php foreach ($languages as $code => $label) : ?>
                <option value="<?php echo esc_attr($code); ?>" <?php selected($value, $code); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php esc_html_e('The primary language for all AI-generated content. Individual authors can override this.', 'ai-post-scheduler'); ?></p>
        <?php
    }

    /**
     * Render the Content Guidelines field.
     *
     * @return void
     */
    public function site_content_guidelines_field_callback() {
        $value = get_option('aips_site_content_guidelines', '');
        ?>
        <textarea name="aips_site_content_guidelines" class="large-text" rows="4" placeholder="<?php esc_attr_e('e.g., Always include at least one actionable tip per post. Avoid profanity. Cite sources where possible.', 'ai-post-scheduler'); ?>"><?php echo esc_textarea($value); ?></textarea>
        <p class="description"><?php esc_html_e('General rules and guidelines for all generated content. Included in every generation prompt as hard constraints.', 'ai-post-scheduler'); ?></p>
        <?php
    }

    /**
     * Render the Excluded Topics (site-wide) field.
     *
     * @return void
     */
    public function site_excluded_topics_field_callback() {
        $value = get_option('aips_site_excluded_topics', '');
        ?>
        <textarea name="aips_site_excluded_topics" class="large-text" rows="3" placeholder="<?php esc_attr_e('e.g., competitor brand names, controversial political topics, adult content', 'ai-post-scheduler'); ?>"><?php echo esc_textarea($value); ?></textarea>
        <p class="description"><?php esc_html_e('Topics or subjects that should never appear in any generated post or topic suggestion. Applied globally.', 'ai-post-scheduler'); ?></p>
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
     * Render the Author Topics page.
     *
     * Displays all AI-generated topics for a specific author with full
     * management capabilities (approve, reject, edit, delete, generate post).
     *
     * @return void
     */
    public function render_author_topics_page() {
        include AIPS_PLUGIN_DIR . 'templates/admin/author-topics.php';
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
     * Render the Sources page.
     *
     * Loads all sources from the repository and includes the sources template.
     *
     * @return void
     */
    public function render_sources_page() {
        $repo    = new AIPS_Sources_Repository();
        $sources = $repo->get_all(false);

        // Build source group name map: term_id => name (avoid per-row get_term calls in the template).
        $source_groups = get_terms(array(
            'taxonomy'   => 'aips_source_group',
            'hide_empty' => false,
        ));
        if (is_wp_error($source_groups)) {
            $source_groups = array();
        }
        $source_group_name_map = array();
        foreach ($source_groups as $group) {
            $source_group_name_map[(int) $group->term_id] = $group->name;
        }

        // Build source → term IDs map: source_id => int[] (one query, not N queries).
        $all_source_ids = array_map(function ($s) { return (int) $s->id; }, $sources);
        $source_term_ids_map = $repo->get_term_ids_for_sources($all_source_ids);

        include AIPS_PLUGIN_DIR . 'templates/admin/sources.php';
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
        $status_handler = new AIPS_System_Status();
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
