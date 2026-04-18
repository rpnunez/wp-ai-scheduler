<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AIPS_Settings_UI
 *
 * Handles the rendering of settings fields and sections, as well as sanitization
 * of those settings.
 *
 * @package AI_Post_Scheduler
 */
class AIPS_Settings_UI {

    /**
     * Render the description for the general settings section.
     *
     * @return void
     */
    public function general_section_callback() {
        echo '<p>' . esc_html__('Configure default settings for AI-generated posts.', 'ai-post-scheduler') . '</p>';
    }

    /**
     * Render the description for the AI settings section.
     *
     * @return void
     */
    public function ai_section_callback() {
        echo '<p>' . esc_html__('Configure the AI Engine model and environment used for content generation.', 'ai-post-scheduler') . '</p>';
    }

    /**
     * Render the description for the feedback settings section.
     *
     * @return void
     */
    public function feedback_section_callback() {
        echo '<p>' . esc_html__('Configure how the plugin evaluates and deduplicates generated topic suggestions.', 'ai-post-scheduler') . '</p>';
    }

    /**
     * Render the description for the API keys settings section.
     *
     * @return void
     */
    public function api_keys_section_callback() {
        echo '<p>' . esc_html__('Enter API keys for third-party services used by the plugin.', 'ai-post-scheduler') . '</p>';
    }

    /**
     * Render the description for the developer settings section.
     *
     * @return void
     */
    public function developers_section_callback() {
        echo '<p>' . esc_html__('Options for debugging and plugin development. Not recommended for production use.', 'ai-post-scheduler') . '</p>';
    }

    /**
     * Render the default post status setting field.
     *
     * Displays a dropdown to select between draft, pending, or publish.
     *
     * @return void
     */
    public function post_status_field_callback() {
        $value = AIPS_Config::get_instance()->get_option('aips_default_post_status');
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
        $value = AIPS_Config::get_instance()->get_option('aips_default_category');
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
        $value = AIPS_Config::get_instance()->get_option('aips_ai_model');
        ?>
        <input type="text" name="aips_ai_model" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="Leave empty for default">
        <p class="description"><?php esc_html_e('AI Engine model to use (leave empty to use AI Engine default).', 'ai-post-scheduler'); ?></p>
        <?php
    }

    /**
     * Render the AI environment ID setting field.
     *
     * Displays a text input for specifying a custom AI Engine environment ID.
     *
     * @return void
     */
    public function ai_env_id_field_callback() {
        $value = AIPS_Config::get_instance()->get_option('aips_ai_env_id');
        ?>
        <input type="text" name="aips_ai_env_id" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="Leave empty for default">
        <p class="description"><?php esc_html_e('AI Engine environment ID to use (leave empty to use AI Engine default environment).', 'ai-post-scheduler'); ?></p>
        <?php
    }

    /**
     * Render the max tokens limit setting field.
     *
     * Sets a hard upper bound on the number of tokens the plugin will ever request
     * from the AI in a single call. The dynamic token calculation will never exceed
     * this value, preventing unexpectedly large or costly requests.
     *
     * @return void
     */
    public function max_tokens_limit_field_callback() {
        $value = AIPS_Config::get_instance()->get_option('aips_max_tokens_limit');
        ?>
        <input type="number" name="aips_max_tokens_limit" value="<?php echo esc_attr($value); ?>" min="100" class="small-text">
        <p class="description"><?php esc_html_e('Hard maximum number of tokens that can be requested in a single AI call. The plugin calculates tokens dynamically per request type (title, excerpt, content) and will never exceed this limit. Default: 16000.', 'ai-post-scheduler'); ?></p>
        <?php
    }

    /**
     * Render the max tokens for post titles setting field.
     *
     * Controls the expected output token budget when generating post titles.
     *
     * @return void
     */
    public function max_tokens_title_field_callback() {
        $value = AIPS_Config::get_instance()->get_option('aips_max_tokens_title');
        ?>
        <input type="number" name="aips_max_tokens_title" value="<?php echo esc_attr($value); ?>" min="1" class="small-text">
        <p class="description"><?php esc_html_e('Expected output token budget for post title generation (~10–20 words). Default: 150.', 'ai-post-scheduler'); ?></p>
        <?php
    }

    /**
     * Render the max tokens for post excerpts setting field.
     *
     * Controls the expected output token budget when generating post excerpts.
     *
     * @return void
     */
    public function max_tokens_excerpt_field_callback() {
        $value = AIPS_Config::get_instance()->get_option('aips_max_tokens_excerpt');
        ?>
        <input type="number" name="aips_max_tokens_excerpt" value="<?php echo esc_attr($value); ?>" min="1" class="small-text">
        <p class="description"><?php esc_html_e('Expected output token budget for post excerpt generation (~2–3 sentence summary). Default: 300.', 'ai-post-scheduler'); ?></p>
        <?php
    }

    /**
     * Render the max tokens for post content setting field.
     *
     * Controls the expected output token budget when generating full post content.
     *
     * @return void
     */
    public function max_tokens_content_field_callback() {
        $value = AIPS_Config::get_instance()->get_option('aips_max_tokens_content');
        ?>
        <input type="number" name="aips_max_tokens_content" value="<?php echo esc_attr($value); ?>" min="1" class="small-text">
        <p class="description"><?php esc_html_e('Expected output token budget for full post content generation (approximately 2,000–3,000 words, depending on the model and content). Default: 4000. Actual output is also capped by the Max Tokens Limit setting.', 'ai-post-scheduler'); ?></p>
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
        $value = AIPS_Config::get_instance()->get_option('aips_unsplash_access_key');
        ?>
        <input type="text" name="aips_unsplash_access_key" value="<?php echo esc_attr($value); ?>" class="regular-text" autocomplete="new-password">
        <p class="description"><?php esc_html_e('Required for fetching images from Unsplash. Generate a Client ID at unsplash.com/developers.', 'ai-post-scheduler'); ?></p>
        <?php
    }

    /**
     * Render the resilience section description.
     */
    public function resilience_section_callback() {
        echo '<p>' . esc_html__('Configure advanced resilience options to protect the application from failing and being blocked when external services return errors.', 'ai-post-scheduler') . '</p>';
    }

    /**
     * Render the enable retry setting field.
     */
    public function enable_retry_field_callback() {
        $value = AIPS_Config::get_instance()->get_option('aips_enable_retry');
        ?>
        <input type="hidden" name="aips_enable_retry" value="0">
        <input type="checkbox" name="aips_enable_retry" value="1" <?php checked(1, $value); ?>>
        <p class="description"><?php esc_html_e('Enable exponential backoff and retry logic for failed AI requests.', 'ai-post-scheduler'); ?></p>
        <?php
    }

    /**
     * Render the max retries setting field.
     */
    public function max_retries_field_callback() {
        $value = AIPS_Config::get_instance()->get_option('aips_retry_max_attempts');
        ?>
        <input type="number" name="aips_retry_max_attempts" value="<?php echo esc_attr($value); ?>" min="0" max="10" class="small-text">
        <p class="description"><?php esc_html_e('Number of retry attempts if generation fails.', 'ai-post-scheduler'); ?></p>
        <?php
    }

    /**
     * Render the retry initial delay setting field.
     */
    public function retry_initial_delay_field_callback() {
        $value = AIPS_Config::get_instance()->get_option('aips_retry_initial_delay');
        ?>
        <input type="number" name="aips_retry_initial_delay" value="<?php echo esc_attr($value); ?>" min="1" max="60" class="small-text">
        <p class="description"><?php esc_html_e('Initial delay (in seconds) before the first retry attempt.', 'ai-post-scheduler'); ?></p>
        <?php
    }

    /**
     * Render the enable rate limiting setting field.
     */
    public function enable_rate_limiting_field_callback() {
        $value = AIPS_Config::get_instance()->get_option('aips_enable_rate_limiting');
        ?>
        <input type="hidden" name="aips_enable_rate_limiting" value="0">
        <input type="checkbox" name="aips_enable_rate_limiting" value="1" <?php checked(1, $value); ?>>
        <p class="description"><?php esc_html_e('Limit the number of AI requests per time period.', 'ai-post-scheduler'); ?></p>
        <?php
    }

    /**
     * Render the rate limit requests setting field.
     */
    public function rate_limit_requests_field_callback() {
        $value = AIPS_Config::get_instance()->get_option('aips_rate_limit_requests');
        ?>
        <input type="number" name="aips_rate_limit_requests" value="<?php echo esc_attr($value); ?>" min="1" class="small-text">
        <p class="description"><?php esc_html_e('Maximum number of allowed requests within the defined period.', 'ai-post-scheduler'); ?></p>
        <?php
    }

    /**
     * Render the rate limit period setting field.
     */
    public function rate_limit_period_field_callback() {
        $value = AIPS_Config::get_instance()->get_option('aips_rate_limit_period');
        ?>
        <input type="number" name="aips_rate_limit_period" value="<?php echo esc_attr($value); ?>" min="1" class="small-text">
        <p class="description"><?php esc_html_e('Period (in seconds) for rate limiting (e.g., 60 = 1 minute).', 'ai-post-scheduler'); ?></p>
        <?php
    }

    /**
     * Render the enable circuit breaker setting field.
     */
    public function enable_circuit_breaker_field_callback() {
        $value = AIPS_Config::get_instance()->get_option('aips_enable_circuit_breaker');
        ?>
        <input type="hidden" name="aips_enable_circuit_breaker" value="0">
        <input type="checkbox" name="aips_enable_circuit_breaker" value="1" <?php checked(1, $value); ?>>
        <p class="description"><?php esc_html_e('Enable circuit breaker to temporarily halt requests after a number of consecutive failures.', 'ai-post-scheduler'); ?></p>
        <?php
    }

    /**
     * Render the circuit breaker threshold setting field.
     */
    public function circuit_breaker_threshold_field_callback() {
        $value = AIPS_Config::get_instance()->get_option('aips_circuit_breaker_threshold');
        ?>
        <input type="number" name="aips_circuit_breaker_threshold" value="<?php echo esc_attr($value); ?>" min="1" class="small-text">
        <p class="description"><?php esc_html_e('Number of consecutive failures required to open the circuit.', 'ai-post-scheduler'); ?></p>
        <?php
    }

    /**
     * Render the circuit breaker timeout setting field.
     */
    public function circuit_breaker_timeout_field_callback() {
        $value = AIPS_Config::get_instance()->get_option('aips_circuit_breaker_timeout');
        ?>
        <input type="number" name="aips_circuit_breaker_timeout" value="<?php echo esc_attr($value); ?>" min="1" class="small-text">
        <p class="description"><?php esc_html_e('Time (in seconds) to keep the circuit open before attempting to recover.', 'ai-post-scheduler'); ?></p>
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
        $value = AIPS_Config::get_instance()->get_option('aips_enable_logging');
        ?>
        <input type="hidden" name="aips_enable_logging" value="0">
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
        $value = AIPS_Config::get_instance()->get_option('aips_developer_mode');
        ?>
        <input type="hidden" name="aips_developer_mode" value="0">
        <label>
            <input type="checkbox" name="aips_developer_mode" value="1" <?php checked($value, 1); ?>>
            <?php esc_html_e('Enable developer tools and features', 'ai-post-scheduler'); ?>
        </label>
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
        $value = AIPS_Config::get_instance()->get_option('aips_review_notifications_email') ?: get_option('admin_email');
        ?>
        <input type="text" name="aips_review_notifications_email" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <p class="description"><?php esc_html_e('Comma-separated email addresses used for system notification emails.', 'ai-post-scheduler'); ?></p>
        <?php
    }

    /**
     * Render the notifications section description.
     *
     * @return void
     */
    public function notifications_section_callback() {
        echo '<p>' . esc_html__('Configure the notification email address and delivery channels for all plugin notifications. Email is sent to the notification email addresses configured below.', 'ai-post-scheduler') . '</p>';
    }

    /**
     * Render a notification channel preference select field.
     *
     * @param array $args Field configuration.
     * @return void
     */
    public function notification_preference_field_callback($args) {
        $type = isset($args['type']) ? sanitize_key($args['type']) : '';
        $preferences_stored = AIPS_Config::get_instance()->get_option('aips_notification_preferences');
        $preferences = is_array($preferences_stored) ? $preferences_stored : array();
        $defaults = AIPS_Config::get_instance()->get_option('aips_notification_preferences');
        $defaults = is_array($defaults) ? $defaults : array();
        $registry = AIPS_Notifications::get_notification_type_registry();
        $registry_default = isset($registry[$type]['default_mode']) ? $registry[$type]['default_mode'] : AIPS_Notifications::MODE_BOTH;
        $value = isset($preferences[$type]) ? $preferences[$type] : (isset($defaults[$type]) ? $defaults[$type] : $registry_default);
        ?>
        <select name="aips_notification_preferences[<?php echo esc_attr($type); ?>]">
            <?php foreach (AIPS_Notifications::get_channel_mode_options() as $mode => $label) : ?>
                <option value="<?php echo esc_attr($mode); ?>" <?php selected($value, $mode); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (!empty($args['description'])) : ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Sanitize comma-separated notification email addresses.
     *
     * @param mixed $value Raw option value.
     * @return string
     */
    public function sanitize_notification_emails($value) {
        $emails = preg_split('/\s*,\s*/', (string) $value);
        $emails = is_array($emails) ? $emails : array();
        $sanitized = array();

        foreach ($emails as $email) {
            $email = sanitize_email($email);
            if (!empty($email) && is_email($email)) {
                $sanitized[] = $email;
            }
        }

        $sanitized = array_values(array_unique($sanitized));

        return implode(', ', $sanitized);
    }

    /**
     * Sanitize notification preference channel modes.
     *
     * @param mixed $value Raw option value.
     * @return array
     */
    public function sanitize_notification_preferences($value) {
        $preferences = is_array($value) ? $value : array();
        $defaults = AIPS_Config::get_instance()->get_option('aips_notification_preferences');
        $allowed_modes = array_keys(AIPS_Notifications::get_channel_mode_options());
        $sanitized = array();

        foreach (AIPS_Notifications::get_notification_type_registry() as $type => $meta) {
            $fallback_mode = isset($defaults[$type]) ? $defaults[$type] : (isset($meta['default_mode']) ? $meta['default_mode'] : 'both');
            $mode = isset($preferences[$type]) ? sanitize_key($preferences[$type]) : $fallback_mode;

            if (!in_array($mode, $allowed_modes, true)) {
                $mode = $fallback_mode;
            }

            $sanitized[$type] = $mode;
        }

        return $sanitized;
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
     * Sanitize a per-type token budget value.
     *
     * Ensures the saved value is a positive integer (≥ 1). An empty submission or
     * a value of zero would silently remove the output token budget and cause the
     * AI to receive an unexpectedly tiny maxTokens value, so we clamp to 1.
     *
     * @param mixed $value Raw input value.
     * @return int Sanitized token budget (minimum 1).
     */
    public function sanitize_token_budget($value) {
        $int = absint($value);
        return max(1, $int);
    }

    /**
     * Render the topic similarity threshold field.
     *
     * Displays a number input for the semantic duplicate detection threshold.
     *
     * @return void
     */
    public function topic_similarity_threshold_field_callback() {
        $raw = AIPS_Config::get_instance()->get_option('aips_topic_similarity_threshold');
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
        $value = AIPS_Config::get_instance()->get_option('aips_site_niche');
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
        $value = AIPS_Config::get_instance()->get_option('aips_site_target_audience');
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
        $value = AIPS_Config::get_instance()->get_option('aips_site_content_goals');
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
        $value = AIPS_Config::get_instance()->get_option('aips_site_brand_voice');
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
        $value = AIPS_Config::get_instance()->get_option('aips_site_content_language');
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
        $value = AIPS_Config::get_instance()->get_option('aips_site_content_guidelines');
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
        $value = AIPS_Config::get_instance()->get_option('aips_site_excluded_topics');
        ?>
        <textarea name="aips_site_excluded_topics" class="large-text" rows="3" placeholder="<?php esc_attr_e('e.g., competitor brand names, controversial political topics, adult content', 'ai-post-scheduler'); ?>"><?php echo esc_textarea($value); ?></textarea>
        <p class="description"><?php esc_html_e('Topics or subjects that should never appear in any generated post or topic suggestion. Applied globally.', 'ai-post-scheduler'); ?></p>
        <?php
    }

    // -----------------------------------------------------------------------
    // Cache Settings fields
    // -----------------------------------------------------------------------

    /**
     * Render the description for the Cache settings section.
     *
     * @return void
     */
    public function cache_section_callback() {
        echo '<p>' . esc_html__('Configure the caching layer used by the plugin. Choose a driver and supply any required connection details.', 'ai-post-scheduler') . '</p>';
    }

    /**
     * Render the Cache Driver selector.
     *
     * @return void
     */
    public function cache_driver_field_callback() {
        $value = AIPS_Config::get_instance()->get_option('aips_cache_driver');
        $drivers = array(
            'array'           => __('Array (in-memory, request-scoped)', 'ai-post-scheduler'),
            'session'         => __('Session (PHP session, user-scoped across pages)', 'ai-post-scheduler'),
            'db'              => __('Database (persistent, uses plugin DB table)', 'ai-post-scheduler'),
            'redis'           => __('Redis (persistent, requires PHP redis extension)', 'ai-post-scheduler'),
            'wp_object_cache' => __('WP Object Cache (uses wp_cache_* functions)', 'ai-post-scheduler'),
        );
        ?>
        <select name="aips_cache_driver" id="aips_cache_driver">
            <?php foreach ($drivers as $key => $label) : ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($value, $key); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php esc_html_e('Select which cache backend to use. Array is the safe default and requires no configuration. Session persists across page loads for the current user. DB is persistent for all users. Redis requires the PHP redis extension.', 'ai-post-scheduler'); ?></p>
        <?php
    }

    /**
     * Render the Default TTL field.
     *
     * @return void
     */
    public function cache_default_ttl_field_callback() {
        $value = AIPS_Config::get_instance()->get_option('aips_cache_default_ttl');
        ?>
        <input type="number" name="aips_cache_default_ttl" value="<?php echo esc_attr($value); ?>" min="0" class="small-text">
        <p class="description"><?php esc_html_e('Default time-to-live in seconds for cached values. 0 = no expiration. Default: 3600 (1 hour).', 'ai-post-scheduler'); ?></p>
        <?php
    }

    /**
     * Render the DB Cache Key Prefix field.
     *
     * @return void
     */
    public function cache_db_prefix_field_callback() {
        $value = AIPS_Config::get_instance()->get_option('aips_cache_db_prefix');
        ?>
        <div class="aips-cache-db-fields">
            <input type="text" name="aips_cache_db_prefix" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="<?php esc_attr_e('Optional — e.g. mysite', 'ai-post-scheduler'); ?>">
            <p class="description"><?php esc_html_e('Optional prefix applied to every cache key in the database table. Useful when multiple environments share the same DB.', 'ai-post-scheduler'); ?></p>
        </div>
        <?php
    }

    /**
     * Render the Redis Host field.
     *
     * @return void
     */
    public function cache_redis_host_field_callback() {
        $value = AIPS_Config::get_instance()->get_option('aips_cache_redis_host');
        ?>
        <div class="aips-cache-redis-fields">
            <input type="text" name="aips_cache_redis_host" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="127.0.0.1">
            <p class="description"><?php esc_html_e('Redis server hostname or IP address.', 'ai-post-scheduler'); ?></p>
        </div>
        <?php
    }

    /**
     * Render the Redis Port field.
     *
     * @return void
     */
    public function cache_redis_port_field_callback() {
        $value = AIPS_Config::get_instance()->get_option('aips_cache_redis_port');
        ?>
        <div class="aips-cache-redis-fields">
            <input type="number" name="aips_cache_redis_port" value="<?php echo esc_attr($value); ?>" min="1" max="65535" class="small-text">
            <p class="description"><?php esc_html_e('Redis server port. Default: 6379.', 'ai-post-scheduler'); ?></p>
        </div>
        <?php
    }

    /**
     * Render the Redis Password field.
     *
     * @return void
     */
    public function cache_redis_password_field_callback() {
        $value = AIPS_Config::get_instance()->get_option('aips_cache_redis_password');
        ?>
        <div class="aips-cache-redis-fields">
            <input type="password" name="aips_cache_redis_password" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="<?php esc_attr_e('Leave empty if not required', 'ai-post-scheduler'); ?>" autocomplete="new-password">
            <p class="description"><?php esc_html_e('Redis authentication password. Leave empty if your Redis server does not require authentication.', 'ai-post-scheduler'); ?></p>
        </div>
        <?php
    }

    /**
     * Render the Redis Database Index field.
     *
     * @return void
     */
    public function cache_redis_db_field_callback() {
        $value = AIPS_Config::get_instance()->get_option('aips_cache_redis_db');
        ?>
        <div class="aips-cache-redis-fields">
            <input type="number" name="aips_cache_redis_db" value="<?php echo esc_attr($value); ?>" min="0" max="15" class="small-text">
            <p class="description"><?php esc_html_e('Redis database index (0–15). Default: 0.', 'ai-post-scheduler'); ?></p>
        </div>
        <?php
    }

    /**
     * Render the Redis Key Prefix field.
     *
     * @return void
     */
    public function cache_redis_prefix_field_callback() {
        $value = AIPS_Config::get_instance()->get_option('aips_cache_redis_prefix');
        ?>
        <div class="aips-cache-redis-fields">
            <input type="text" name="aips_cache_redis_prefix" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="aips">
            <p class="description"><?php esc_html_e('Prefix prepended to every Redis key. Helps avoid collisions with other applications on the same server. Default: aips.', 'ai-post-scheduler'); ?></p>
        </div>
        <?php
    }

    /**
     * Render the Redis Connection Timeout field.
     *
     * @return void
     */
    public function cache_redis_timeout_field_callback() {
        $value = AIPS_Config::get_instance()->get_option('aips_cache_redis_timeout');
        ?>
        <div class="aips-cache-redis-fields">
            <input type="number" name="aips_cache_redis_timeout" value="<?php echo esc_attr($value); ?>" min="1" max="30" class="small-text">
            <p class="description"><?php esc_html_e('Maximum time in seconds to wait for a Redis connection to be established. Default: 2.', 'ai-post-scheduler'); ?></p>
        </div>
        <?php
    }

    /**
     * Sanitize and validate the selected cache driver value.
     *
     * @param mixed $value Raw input value.
     * @return string Sanitized driver name, or 'array' as safe fallback.
     */
    public function sanitize_cache_driver( $value ) {
        $allowed = array('array', 'session', 'db', 'redis', 'wp_object_cache');
        $value   = sanitize_text_field( (string) $value );
        return in_array($value, $allowed, true) ? $value : 'array';
    }

    // -----------------------------------------------------------------------
    // Feature Flags section callbacks
    // -----------------------------------------------------------------------

    /**
     * Render the description for the Feature Flags settings section.
     *
     * @return void
     */
    public function feature_flags_section_callback() {
        echo '<p>' . esc_html__('Enable or disable experimental and opt-in features. Changes take effect on the next page load. Use with caution in production — enable on staging first.', 'ai-post-scheduler') . '</p>';
    }

    /**
     * Render a single feature-flag checkbox field.
     *
     * @param array $args {
     *     Field configuration passed from add_settings_field().
     *
     *     @type string $flag        Internal flag name (key in aips_feature_flags array).
     *     @type string $description Human-readable description shown below the checkbox.
     * }
     * @return void
     */
    public function feature_flag_field_callback( $args ) {
        $flag        = isset($args['flag']) ? sanitize_key($args['flag']) : '';
        $description = isset($args['description']) ? $args['description'] : '';
        $config      = AIPS_Config::get_instance();
        $features    = $config->get_available_features();
        $default     = false;

        if (isset($features[ $flag ]) && is_array($features[ $flag ]) && isset($features[ $flag ]['default'])) {
            $default = (bool) $features[ $flag ]['default'];
        }

        $enabled = $config->is_feature_enabled($flag, $default);
        ?>
        <input type="hidden" name="aips_feature_flags[<?php echo esc_attr($flag); ?>]" value="0">
        <label>
            <input type="checkbox"
                   name="aips_feature_flags[<?php echo esc_attr($flag); ?>]"
                   value="1"
                   <?php checked($enabled, true); ?>>
            <?php echo esc_html($description); ?>
        </label>
        <?php
    }

    /**
     * Sanitize the submitted feature-flags array.
     *
     * Ensures each flag value is a boolean integer (0 or 1) and that only
     * known flags are accepted. Unknown keys are silently discarded.
     *
     * @param mixed $value Raw submitted value (expected to be an array).
     * @return array Sanitized associative array of flag_name => bool.
     */
    public function sanitize_feature_flags( $value ) {
        $submitted = is_array($value) ? $value : array();
        $features  = AIPS_Config::get_instance()->get_available_features();
        $sanitized = array();

        foreach ($features as $flag => $feature_config) {
            $default = false;

            if (is_array($feature_config) && isset($feature_config['default'])) {
                $default = (bool) $feature_config['default'];
            }

            $sanitized[$flag] = isset($submitted[$flag]) ? (bool) $submitted[$flag] : $default;
        }

        return $sanitized;
    }

}
