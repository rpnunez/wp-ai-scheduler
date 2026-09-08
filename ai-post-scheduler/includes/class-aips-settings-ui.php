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
     * Render the AI provider selection field.
     *
     * Lets the admin pick which AI backend serves requests. "Auto-detect" lets
     * AIPS_AI_Provider_Factory choose the first available provider (Meow first).
     *
     * @return void
     */
    public function ai_provider_field_callback() {
        $value     = (string) AIPS_Config::get_instance()->get_option('aips_ai_provider');
        $available = AIPS_AI_Provider_Factory::available_providers();
        $reasons   = AIPS_AI_Provider_Factory::unavailable_reasons();
        // Show every known provider so an unavailable one can still be selected,
        // while clearly marking whether it is locally configured for use.
        $all = AIPS_AI_Provider_Factory::all_providers();
        ?>
        <select name="aips_ai_provider" id="aips_ai_provider">
            <option value="" <?php selected($value, ''); ?>><?php esc_html_e('Auto-detect (recommended)', 'ai-post-scheduler'); ?></option>
            <?php foreach ($all as $id => $label) : ?>
                <?php $is_available = isset($available[$id]); ?>
                <option value="<?php echo esc_attr($id); ?>" <?php selected($value, $id); ?>>
                    <?php
                    echo esc_html($label);
                    if (!$is_available) {
                        echo ' ' . esc_html__('(currently unavailable)', 'ai-post-scheduler');
                    }
                    ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php esc_html_e('Which AI backend to use. Auto-detect prefers Meow Apps AI Engine, then a locally configured WordPress AI Client connector. Live connector and model capability is verified when a generation request runs. The Model and Environment ID fields below are interpreted per provider (Meow uses the Environment ID; the WordPress AI Client uses the Model as a model preference, with credentials managed under WordPress core Settings > AI Connectors).', 'ai-post-scheduler'); ?></p>
        <?php if (!empty($reasons)) : ?>
            <ul class="description aips-provider-readiness">
                <?php foreach ($reasons as $id => $reason) : ?>
                    <li><strong><?php echo esc_html(isset($all[$id]) ? $all[$id] : $id); ?>:</strong> <?php echo esc_html($reason); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php
    }

	/**
	 * Render WordPress AI Client connector routing controls.
	 *
	 * @return void
	 */
	public function wp_ai_connectors_field_callback() {
		$config          = AIPS_Config::get_instance();
		$mode            = (string) $config->get_option('aips_wp_ai_connector_mode');
		$selected        = (array) $config->get_option('aips_wp_ai_connector_ids');
		$failover        = (bool) $config->get_option('aips_wp_ai_connector_failover');
		$provider        = new AIPS_WP_AI_Client_Provider();
		$connectors      = $provider->get_active_ai_connectors();
		$ordered_ids     = array_values(array_unique(array_merge($selected, array_keys($connectors))));
		$settings_url    = admin_url('options-general.php?page=connectors');
		?>
		<fieldset class="aips-wp-ai-connectors">
			<label>
				<input type="radio" name="aips_wp_ai_connector_mode" value="all" <?php checked($mode, 'all'); ?>>
				<?php esc_html_e('Use all available connectors (recommended)', 'ai-post-scheduler'); ?>
			</label><br>
			<label>
				<input type="radio" name="aips_wp_ai_connector_mode" value="selected" <?php checked($mode, 'selected'); ?>>
				<?php esc_html_e('Use only selected connectors', 'ai-post-scheduler'); ?>
			</label>

			<?php if (!empty($ordered_ids)) : ?>
				<p><label for="aips_wp_ai_connector_ids"><strong><?php esc_html_e('Allowed connectors and priority', 'ai-post-scheduler'); ?></strong></label></p>
				<input type="hidden" name="aips_wp_ai_connector_ids[]" value="">
				<select name="aips_wp_ai_connector_ids[]" id="aips_wp_ai_connector_ids" multiple size="<?php echo esc_attr((string) min(8, max(3, count($ordered_ids)))); ?>" style="min-width: 24em;">
					<?php foreach ($ordered_ids as $connector_id) : ?>
						<?php
						$connector = isset($connectors[$connector_id]) ? $connectors[$connector_id] : null;
						$name      = is_array($connector) && !empty($connector['name']) ? (string) $connector['name'] : $connector_id;
						$health    = $provider->get_connector_health($connector_id);
						$cooling   = !empty($health['cooldown_until']) && (int) $health['cooldown_until'] > time();
						$approved  = $provider->get_connector_approval_status($connector_id);
						$configured = is_array($connector) && $provider->is_connector_configured($connector);
						$status    = !$configured
							? __('currently unavailable', 'ai-post-scheduler')
							: ($approved === false
							? __('not approved for AI Post Scheduler', 'ai-post-scheduler')
							: ($cooling
							? __('temporarily unavailable', 'ai-post-scheduler')
							: __('connected', 'ai-post-scheduler')));
						?>
						<option value="<?php echo esc_attr($connector_id); ?>" <?php selected(in_array($connector_id, $selected, true)); ?>>
							<?php echo esc_html(sprintf('%1$s (%2$s)', $name, $status)); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p>
					<button type="button" class="button" data-aips-connector-move="up"><?php esc_html_e('Move up', 'ai-post-scheduler'); ?></button>
					<button type="button" class="button" data-aips-connector-move="down"><?php esc_html_e('Move down', 'ai-post-scheduler'); ?></button>
				</p>
				<p class="description"><?php esc_html_e('When selected-connector mode is active, highlighted connectors are attempted from top to bottom. Use Ctrl/Cmd to select more than one.', 'ai-post-scheduler'); ?></p>
			<?php else : ?>
				<p class="description"><?php esc_html_e('No active WordPress AI connectors are registered.', 'ai-post-scheduler'); ?></p>
			<?php endif; ?>

			<input type="hidden" name="aips_wp_ai_connector_failover" value="0">
			<label>
				<input type="checkbox" name="aips_wp_ai_connector_failover" value="1" <?php checked($failover); ?>>
				<?php esc_html_e('Try the next allowed connector when a connector-specific failure occurs', 'ai-post-scheduler'); ?>
			</label>
			<p class="description">
				<?php esc_html_e('Credentials remain managed by WordPress. Request validation and content-policy failures do not trigger connector failover.', 'ai-post-scheduler'); ?>
				<a href="<?php echo esc_url($settings_url); ?>"><?php esc_html_e('Manage Connectors', 'ai-post-scheduler'); ?></a>
			</p>
		</fieldset>
		<?php
	}

    /**
     * Render the AI model setting field.
     *
     * Displays a text input for specifying a custom AI model.
     *
     * @return void
     */
    public function ai_model_field_callback() {
        $value = AIPS_Config::get_instance()->get_option('aips_ai_model');
        ?>
        <input type="text" name="aips_ai_model" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="Leave empty for default">
        <p class="description"><?php esc_html_e('AI model to use (leave empty for the provider default). For the WordPress AI Client you may enter a comma-separated model preference list, e.g. "claude-sonnet-4-5, gemini-3-pro-preview".', 'ai-post-scheduler'); ?></p>
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
        <p class="description"><?php esc_html_e('Meow AI Engine only: environment ID to use (leave empty for the AI Engine default). Ignored by the WordPress AI Client, which manages connectors and credentials under WordPress core settings.', 'ai-post-scheduler'); ?></p>
        <?php
    }

	/**
	 * Render the AI generation prevention setting field.
	 *
	 * @return void
	 */
	public function prevent_scheduled_ai_generation_field_callback() {
		$value = AIPS_Config::get_instance()->is_scheduled_ai_generation_prevented();
		?>
		<input type="hidden" name="aips_prevent_scheduled_ai_generation" value="0">
		<input type="checkbox" name="aips_prevent_scheduled_ai_generation" value="1" <?php checked(1, $value); ?>>
		<p class="description"><?php esc_html_e('Stop schedule runs before they begin AI generation. This applies to cron-started runs and to manual "Run Now" executions. Schedules remain active and record an early-termination history entry instead of generating content while this setting is enabled. A large batch stopped part-way through resumes from where it left off once you turn this back off.', 'ai-post-scheduler'); ?></p>
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
        <p class="description"><?php esc_html_e('Expected visible-output budget for post title generation (~10–20 words). Short-form requests reserve at least 1200 provider tokens so reasoning-capable models can finish the response. Default: 150.', 'ai-post-scheduler'); ?></p>
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
        <p class="description"><?php esc_html_e('Expected visible-output budget for post excerpt generation (~2–3 sentence summary). Short-form requests reserve at least 1200 provider tokens so reasoning-capable models can finish the response. Default: 300.', 'ai-post-scheduler'); ?></p>
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
     * Render the retry delay jitter setting field.
     */
    public function retry_jitter_field_callback() {
        $value = AIPS_Config::get_instance()->get_option('aips_retry_jitter');
        ?>
        <input type="hidden" name="aips_retry_jitter" value="0">
        <label>
            <input type="checkbox" name="aips_retry_jitter" value="1" <?php checked(!empty($value)); ?>>
            <?php esc_html_e('Add randomized jitter (0–25%) to retry delays to prevent thundering herd', 'ai-post-scheduler'); ?>
        </label>
        <p class="description"><?php esc_html_e('Randomizes exponential backoff intervals between network retries against upstream AI providers.', 'ai-post-scheduler'); ?></p>
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
     * Render the conversational generation setting field.
     *
     * When enabled, the title, excerpt, and image-prompt steps continue the same
     * conversation as the content step instead of each pasting a copy of the
     * article into a fresh prompt. Requires an AI provider that can replay
     * conversation history.
     *
     * @return void
     */
    public function conversational_generation_field_callback() {
        $value     = AIPS_Config::get_instance()->get_option('aips_conversational_generation');
        $supported = AIPS_AI_Provider_Factory::create()->supports_conversation();
        ?>
        <input type="hidden" name="aips_conversational_generation" value="0">
        <label>
            <input type="checkbox" name="aips_conversational_generation" value="1" <?php checked($value, 1); ?>>
            <?php esc_html_e('Generate post components as one conversation instead of separate prompts', 'ai-post-scheduler'); ?>
        </label>
        <p class="description">
            <?php esc_html_e('The model keeps the article it just wrote in context, so titles and excerpts stay consistent with the body and the article text is not re-sent with every request.', 'ai-post-scheduler'); ?>
        </p>
        <?php if (!$supported) : ?>
            <p class="description">
                <strong><?php esc_html_e('The active AI provider does not support conversation history. This setting has no effect until you switch to one that does.', 'ai-post-scheduler'); ?></strong>
            </p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render the combined metadata turn setting field.
     *
     * @return void
     */
    public function conversational_metadata_turn_field_callback() {
        $value  = AIPS_Config::get_instance()->get_option('aips_conversational_metadata_turn');
        $parent = AIPS_Config::get_instance()->get_option('aips_conversational_generation');
        ?>
        <input type="hidden" name="aips_conversational_metadata_turn" value="0">
        <label>
            <input type="checkbox" name="aips_conversational_metadata_turn" value="1" <?php checked($value, 1); ?>>
            <?php esc_html_e('Request the title, excerpt, and image prompt in a single structured response', 'ai-post-scheduler'); ?>
        </label>
        <p class="description">
            <?php esc_html_e('Collapses up to four follow-up requests into one, roughly halving the number of AI calls per post. Falls back to separate requests if the response cannot be parsed.', 'ai-post-scheduler'); ?>
        </p>
        <?php if (!$parent) : ?>
            <p class="description">
                <strong><?php esc_html_e('Requires Conversational Generation to be enabled.', 'ai-post-scheduler'); ?></strong>
            </p>
        <?php endif; ?>
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
     * Render the enable telemetry setting field.
     *
     * Displays a checkbox to enable or disable request-level telemetry
     * recording (staging/dev use only).
     *
     * @return void
     */
    public function enable_telemetry_field_callback() {
        $value = AIPS_Config::get_instance()->get_option('aips_enable_telemetry');
        ?>
        <input type="hidden" name="aips_enable_telemetry" value="0">
        <label>
            <input type="checkbox" name="aips_enable_telemetry" value="1" <?php checked($value, 1); ?>>
            <?php esc_html_e('Enable request-level telemetry (staging/dev only)', 'ai-post-scheduler'); ?>
        </label>
        <p class="description"><?php esc_html_e('Logs query counts, memory usage, elapsed time, and events for each request to the aips_telemetry table. Not recommended for production.', 'ai-post-scheduler'); ?></p>
        <?php
    }

    /**
     * Render the cache monitor setting field.
     *
     * Displays a checkbox to enable or disable the Cache Monitor
     * admin page and its AJAX endpoints (internal cache introspection tool).
     *
     * @return void
     */
    public function cache_monitor_enabled_field_callback() {
        $value = AIPS_Config::get_instance()->get_option('aips_cache_monitor_enabled');
        ?>
        <input type="hidden" name="aips_cache_monitor_enabled" value="0">
        <label>
            <input type="checkbox" name="aips_cache_monitor_enabled" value="1" <?php checked($value, 1); ?>>
            <?php esc_html_e('Enable Cache Monitor (internal cache introspection tool)', 'ai-post-scheduler'); ?>
        </label>
        <p class="description"><?php esc_html_e('Exposes an admin page and AJAX endpoints for inspecting and flushing internal cache entries. Not recommended for production.', 'ai-post-scheduler'); ?></p>
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
     * AI to receive an unexpectedly tiny max_tokens value, so we clamp to 1.
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
     * Render the Default Article Structure field.
     *
     * @return void
     */
    public function site_default_article_structure_field_callback() {
        $value = absint(AIPS_Config::get_instance()->get_option('aips_default_article_structure_id'));
        $repository = new AIPS_Article_Structure_Repository();
        $structures = $repository->get_all(false);
        ?>
        <select name="aips_default_article_structure_id">
            <option value="0"><?php esc_html_e('Select an article structure', 'ai-post-scheduler'); ?></option>
            <?php foreach ($structures as $structure) : ?>
                <?php
                $structure_id = isset($structure->id) ? absint($structure->id) : 0;
                $is_active    = true;

                if (isset($structure->is_active)) {
                    $is_active = (bool) $structure->is_active;
                }

                $label = $structure->name;
                if (!$is_active) {
                    $label .= ' ' . __('(Inactive)', 'ai-post-scheduler');
                }

                $disabled = (!$is_active && $value !== $structure_id) ? ' disabled="disabled"' : '';
                ?>
                <option value="<?php echo esc_attr($structure_id); ?>" <?php selected($value, $structure_id); ?><?php echo $disabled; ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php esc_html_e('Used as the fallback structure whenever a schedule or generation flow does not specify one explicitly.', 'ai-post-scheduler'); ?></p>
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
        echo '<p>' . esc_html__('Configure the caching layer used by the plugin. Choose between Array, WP Object Cache, or Database.', 'ai-post-scheduler') . '</p>';
    }

    /**
     * Render the Enable Cache System radio field.
     *
     * @return void
     */
    public function enable_cache_system_field_callback() {
        $value = AIPS_Config::get_instance()->get_option('aips_enable_cache_system');
        // Normalise: treat anything except a stored '0' or 0 as enabled.
        $enabled = ($value !== '0' && $value !== 0 && $value !== false);
        ?>
        <fieldset>
            <label>
                <input type="radio" name="aips_enable_cache_system" value="1" <?php checked($enabled, true); ?>>
                <?php esc_html_e('Yes', 'ai-post-scheduler'); ?>
            </label>
            &nbsp;&nbsp;
            <label>
                <input type="radio" name="aips_enable_cache_system" value="0" <?php checked($enabled, false); ?>>
                <?php esc_html_e('No', 'ai-post-scheduler'); ?>
            </label>
        </fieldset>
        <p class="description">
            <?php esc_html_e('Enable the plugin\'s internal cache layer to improve performance. When enabled, the cache stores repeated database reads, compiled template data, and other expensive computations in memory (or another backend) so they are not recalculated on every page load or cron run. This can significantly reduce database queries and speed up content generation, admin pages, and scheduled tasks. When disabled, every operation reads directly from the database and no data is stored in cache — useful for debugging or troubleshooting cache-related issues.', 'ai-post-scheduler'); ?>
        </p>
        <?php
    }

    /**
     * Sanitize the Enable Cache System value.
     *
     * Accepts '1'/'0', 1/0, true/false. Returns '1' or '0'.
     *
     * @param mixed $value Raw input value.
     * @return string '1' when enabled, '0' when disabled.
     */
    public function sanitize_enable_cache_system( $value ) {
        return ($value === '1' || $value === 1 || $value === true) ? '1' : '0';
    }

    /**
     * Render the Cache Driver selector.
     *
     * @return void
     */
    public function cache_driver_field_callback() {
        $value = AIPS_Config::get_instance()->get_option('aips_cache_driver');
        $drivers = array(
            'array'           => __('Array (in-memory, request-scoped) (default)', 'ai-post-scheduler'),
            'wp_object_cache' => __('WP Object Cache (uses wp_cache_* functions)', 'ai-post-scheduler'),
            'db'              => __('Database (persistent, uses plugin DB table)', 'ai-post-scheduler'),
        );
        ?>
        <div class="aips-cache-system-fields">
            <select name="aips_cache_driver" id="aips_cache_driver">
                <?php foreach ($drivers as $key => $label) : ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($value, $key); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
            <p class="description"><?php esc_html_e('Select which cache backend to use. Array is the safe default and requires no configuration. WP Object Cache is recommended when your site has a persistent object cache backend. Database provides persistent plugin-managed storage.', 'ai-post-scheduler'); ?></p>
        </div>
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
        <div class="aips-cache-system-fields">
            <input type="number" name="aips_cache_default_ttl" value="<?php echo esc_attr($value); ?>" min="0" class="small-text">
            <p class="description"><?php esc_html_e('Default time-to-live in seconds for cached values. 0 = no expiration. Default: 3600 (1 hour).', 'ai-post-scheduler'); ?></p>
        </div>
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
     * Sanitize and validate the selected cache driver value.
     *
     * @param mixed $value Raw input value.
     * @return string Sanitized driver name, or 'array' as safe fallback.
     */
    public function sanitize_cache_driver( $value ) {
        $allowed = array('array', 'db', 'wp_object_cache');
        $legacy  = array('session', 'redis');
        $value   = sanitize_text_field( (string) $value );

        if (in_array($value, $legacy, true)) {
            return 'wp_object_cache';
        }

        return in_array($value, $allowed, true) ? $value : 'array';
    }

    /**
     * Sanitize the AI provider selection.
     *
     * Accepts an empty string (auto-detect) or a known provider id; anything
     * else falls back to auto-detect.
     *
     * @param mixed $value Raw submitted value.
     * @return string
     */
    public function sanitize_ai_provider( $value ) {
        $value = sanitize_text_field( (string) $value );

        if ($value === '') {
            return '';
        }

        $known = array_keys(AIPS_AI_Provider_Factory::all_providers());

        return in_array($value, $known, true) ? $value : '';
    }

	/**
	 * Sanitize the WordPress AI connector selection mode.
	 *
	 * @param mixed $value Raw submitted value.
	 * @return string
	 */
	public function sanitize_wp_ai_connector_mode($value) {
		$value = sanitize_key((string) $value);

		return in_array($value, array('all', 'selected'), true) ? $value : 'all';
	}

	/**
	 * Sanitize an ordered list of WordPress AI connector IDs.
	 *
	 * Unknown IDs are preserved so a temporarily deactivated connector can resume
	 * its prior position when it is registered again.
	 *
	 * @param mixed $value Raw submitted value.
	 * @return array<int,string>
	 */
	public function sanitize_wp_ai_connector_ids($value) {
		if (!is_array($value)) {
			return array();
		}

		$connector_ids = array();

		foreach ($value as $connector_id) {
			if (!is_scalar($connector_id)) {
				continue;
			}

			$connector_id = strtolower(trim((string) $connector_id));

			if ($connector_id !== '' && preg_match('/^[a-z0-9_-]+$/', $connector_id)) {
				$connector_ids[] = $connector_id;
			}
		}

		return array_values(array_unique($connector_ids));
	}

	/**
	 * Sanitize AI temperature value (0.0 to 2.0).
	 *
	 * @param mixed $value Raw submitted value.
	 * @return float
	 */
	public function sanitize_temperature($value) {
		if (!is_numeric($value)) {
			return 0.7;
		}
		$float = (float) $value;
		return min(2.0, max(0.0, $float));
	}

	/**
	 * Sanitize post types array.
	 *
	 * @param mixed $value Raw submitted value.
	 * @return array
	 */
	public function sanitize_post_types($value) {
		if (!is_array($value)) {
			return array('post');
		}
		$valid = array();
		$registered = get_post_types(array('public' => true), 'names');
		foreach ($value as $post_type) {
			$post_type = sanitize_key($post_type);
			if (in_array($post_type, $registered, true)) {
				$valid[] = $post_type;
			}
		}
		return !empty($valid) ? array_values(array_unique($valid)) : array('post');
	}

	/**
	 * Sanitize deduplication mode.
	 *
	 * @param mixed $value Raw submitted value.
	 * @return string
	 */
	public function sanitize_deduplication_mode($value) {
		$value = sanitize_key((string) $value);
		return in_array($value, array('warn', 'block', 'off'), true) ? $value : 'warn';
	}

	/**
	 * Sanitize related posts layout.
	 *
	 * @param mixed $value Raw submitted value.
	 * @return string
	 */
	public function sanitize_related_posts_layout($value) {
		$value = sanitize_key((string) $value);
		return in_array($value, array('grid', 'list'), true) ? $value : 'grid';
	}

	/**
	 * Sanitize webhook events array.
	 *
	 * @param mixed $value Raw submitted value.
	 * @return array
	 */
	public function sanitize_webhook_events($value) {
		if (!is_array($value)) {
			return array();
		}
		$allowed = array('generation_completed', 'generation_failed', 'post_ready_for_review');
		$sanitized = array();
		foreach ($value as $event) {
			$event = sanitize_key($event);
			if (in_array($event, $allowed, true)) {
				$sanitized[] = $event;
			}
		}
		return array_values(array_unique($sanitized));
	}

	// -------------------------------------------------------------------------
	// General Tab field callbacks
	// -------------------------------------------------------------------------

	/**
	 * Render default post author setting field.
	 *
	 * @return void
	 */
	public function post_author_field_callback() {
		$value = (int) AIPS_Config::get_instance()->get_option('aips_default_post_author');
		if ($value <= 0) {
			$value = get_current_user_id() ?: 1;
		}
		wp_dropdown_users(array(
			'name'       => 'aips_default_post_author',
			'selected'   => $value,
			'capability' => array('edit_posts'),
		));
		echo '<p class="description">' . esc_html__('Default WordPress author assigned to generated posts if an author is not specified.', 'ai-post-scheduler') . '</p>';
	}

	/**
	 * Render default post format setting field.
	 *
	 * @return void
	 */
	public function default_post_format_field_callback() {
		$value   = (string) AIPS_Config::get_instance()->get_option('aips_default_post_format');
		$formats = get_post_format_strings();
		?>
		<select name="aips_default_post_format">
			<option value="standard" <?php selected($value, 'standard'); ?>><?php esc_html_e('Standard', 'ai-post-scheduler'); ?></option>
			<?php foreach ($formats as $format_slug => $format_name) : ?>
				<option value="<?php echo esc_attr($format_slug); ?>" <?php selected($value, $format_slug); ?>><?php echo esc_html($format_name); ?></option>
			<?php endforeach; ?>
		</select>
		<p class="description"><?php esc_html_e('Default post format applied to newly created posts.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render default comment status setting field.
	 *
	 * @return void
	 */
	public function default_comment_status_field_callback() {
		$value = (string) AIPS_Config::get_instance()->get_option('aips_default_comment_status');
		?>
		<select name="aips_default_comment_status">
			<option value="open" <?php selected($value, 'open'); ?>><?php esc_html_e('Open', 'ai-post-scheduler'); ?></option>
			<option value="closed" <?php selected($value, 'closed'); ?>><?php esc_html_e('Closed', 'ai-post-scheduler'); ?></option>
		</select>
		<p class="description"><?php esc_html_e('Whether comments are open or closed by default on generated posts.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render default ping status setting field.
	 *
	 * @return void
	 */
	public function default_ping_status_field_callback() {
		$value = (string) AIPS_Config::get_instance()->get_option('aips_default_ping_status');
		?>
		<select name="aips_default_ping_status">
			<option value="open" <?php selected($value, 'open'); ?>><?php esc_html_e('Open', 'ai-post-scheduler'); ?></option>
			<option value="closed" <?php selected($value, 'closed'); ?>><?php esc_html_e('Closed', 'ai-post-scheduler'); ?></option>
		</select>
		<p class="description"><?php esc_html_e('Whether pingbacks and trackbacks are allowed on generated posts.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render auto-generate meta description setting field.
	 *
	 * @return void
	 */
	public function auto_generate_meta_description_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_auto_generate_meta_description');
		?>
		<input type="hidden" name="aips_auto_generate_meta_description" value="0">
		<label>
			<input type="checkbox" name="aips_auto_generate_meta_description" value="1" <?php checked(!empty($value)); ?>>
			<?php esc_html_e('Automatically generate search-optimized meta descriptions and post excerpts', 'ai-post-scheduler'); ?>
		</label>
		<p class="description"><?php esc_html_e('Creates a concise 150-160 character meta description for search engines and social sharing.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render auto-generate tags setting field.
	 *
	 * @return void
	 */
	public function auto_generate_tags_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_auto_generate_tags');
		?>
		<input type="hidden" name="aips_auto_generate_tags" value="0">
		<label>
			<input type="checkbox" name="aips_auto_generate_tags" value="1" <?php checked(!empty($value)); ?>>
			<?php esc_html_e('Automatically generate and assign relevant tags from generated content', 'ai-post-scheduler'); ?>
		</label>
		<p class="description"><?php esc_html_e('Extracts key topical tags during post generation and attaches them to the WordPress post.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render max tags count setting field.
	 *
	 * @return void
	 */
	public function max_tags_count_field_callback() {
		$value = (int) AIPS_Config::get_instance()->get_option('aips_max_tags_count');
		?>
		<input type="number" name="aips_max_tags_count" value="<?php echo esc_attr($value); ?>" min="1" max="20" class="small-text">
		<p class="description"><?php esc_html_e('Maximum number of tags to generate and attach per post. Default: 5.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	// -------------------------------------------------------------------------
	// AI Tab field callbacks
	// -------------------------------------------------------------------------

	/**
	 * Render AI temperature setting field.
	 *
	 * @return void
	 */
	public function temperature_field_callback() {
		$value = (float) AIPS_Config::get_instance()->get_option('aips_temperature');
		?>
		<input type="number" name="aips_temperature" value="<?php echo esc_attr($value); ?>" min="0.0" max="2.0" step="0.05" class="small-text">
		<p class="description"><?php esc_html_e('Sampling temperature (0.0 to 2.0). Lower values (e.g. 0.2) make output focused and deterministic; higher values (e.g. 0.8) make it more creative. Default: 0.7.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render global system instructions prompt field.
	 *
	 * @return void
	 */
	public function global_system_prompt_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_global_system_prompt');
		?>
		<textarea name="aips_global_system_prompt" class="large-text" rows="4" placeholder="<?php esc_attr_e('e.g. Never mention competitors. Always cite authoritative sources. Maintain an objective journalistic tone.', 'ai-post-scheduler'); ?>"><?php echo esc_textarea($value); ?></textarea>
		<p class="description"><?php esc_html_e('Global instructions appended to the AI system prompt across all generation flows.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render max tokens for outline setting field.
	 *
	 * @return void
	 */
	public function max_tokens_outline_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_max_tokens_outline');
		?>
		<input type="number" name="aips_max_tokens_outline" value="<?php echo esc_attr($value); ?>" min="50" class="small-text">
		<p class="description"><?php esc_html_e('Token budget allocated for article outline and section planning. Default: 800.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render max tokens for FAQ setting field.
	 *
	 * @return void
	 */
	public function max_tokens_faq_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_max_tokens_faq');
		?>
		<input type="number" name="aips_max_tokens_faq" value="<?php echo esc_attr($value); ?>" min="50" class="small-text">
		<p class="description"><?php esc_html_e('Token budget allocated for FAQ and key takeaways generation. Default: 600.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render fallback AI provider field.
	 *
	 * @return void
	 */
	public function ai_fallback_provider_field_callback() {
		$value = (string) AIPS_Config::get_instance()->get_option('aips_ai_fallback_provider');
		$all   = AIPS_AI_Provider_Factory::all_providers();
		?>
		<select name="aips_ai_fallback_provider">
			<option value="" <?php selected($value, ''); ?>><?php esc_html_e('None (disabled)', 'ai-post-scheduler'); ?></option>
			<?php foreach ($all as $id => $label) : ?>
				<option value="<?php echo esc_attr($id); ?>" <?php selected($value, $id); ?>><?php echo esc_html($label); ?></option>
			<?php endforeach; ?>
		</select>
		<p class="description"><?php esc_html_e('Secondary AI provider to invoke if the primary provider encounters unrecoverable errors.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render fallback AI model field.
	 *
	 * @return void
	 */
	public function ai_fallback_model_field_callback() {
		$value = (string) AIPS_Config::get_instance()->get_option('aips_ai_fallback_model');
		?>
		<input type="text" name="aips_ai_fallback_model" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="e.g. gpt-4o-mini">
		<p class="description"><?php esc_html_e('Model identifier to use with the fallback provider. Leave empty to use fallback provider default.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	// -------------------------------------------------------------------------
	// Feedback Tab field callbacks
	// -------------------------------------------------------------------------

	/**
	 * Render max topic suggestions batch setting field.
	 *
	 * @return void
	 */
	public function max_topic_suggestions_batch_field_callback() {
		$value = (int) AIPS_Config::get_instance()->get_option('aips_max_topic_suggestions_batch');
		?>
		<input type="number" name="aips_max_topic_suggestions_batch" value="<?php echo esc_attr($value); ?>" min="1" max="50" class="small-text">
		<p class="description"><?php esc_html_e('Maximum number of new topic suggestions generated per research/discovery batch. Default: 10.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render topics retention days setting field.
	 *
	 * @return void
	 */
	public function topics_retention_days_field_callback() {
		$value = (int) AIPS_Config::get_instance()->get_option('aips_topics_retention_days');
		?>
		<input type="number" name="aips_topics_retention_days" value="<?php echo esc_attr($value); ?>" min="0" class="small-text">
		<p class="description"><?php esc_html_e('Number of days to keep rejected and expired topic suggestions before automatic cleanup (0 = never clean). Default: 60.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	// -------------------------------------------------------------------------
	// Resilience & Limits field callbacks
	// -------------------------------------------------------------------------

	/**
	 * Render enable schedule jitter field.
	 *
	 * @return void
	 */
	public function enable_schedule_jitter_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_enable_schedule_jitter');
		?>
		<input type="hidden" name="aips_enable_schedule_jitter" value="0">
		<label>
			<input type="checkbox" name="aips_enable_schedule_jitter" value="1" <?php checked(!empty($value)); ?>>
			<?php esc_html_e('Add randomized jitter / time variance to scheduled posts', 'ai-post-scheduler'); ?>
		</label>
		<p class="description"><?php esc_html_e('Varies execution times slightly so scheduled posts appear published at natural, non-uniform intervals.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render schedule jitter minutes field.
	 *
	 * @return void
	 */
	public function schedule_jitter_minutes_field_callback() {
		$value = (int) AIPS_Config::get_instance()->get_option('aips_schedule_jitter_minutes');
		?>
		<input type="number" name="aips_schedule_jitter_minutes" value="<?php echo esc_attr($value); ?>" min="1" max="120" class="small-text">
		<p class="description"><?php esc_html_e('Maximum random offset window in minutes (± window). Default: 15.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render cron batch size field.
	 *
	 * @return void
	 */
	public function cron_batch_size_field_callback() {
		$value = (int) AIPS_Config::get_instance()->get_option('aips_cron_batch_size');
		?>
		<input type="number" name="aips_cron_batch_size" value="<?php echo esc_attr($value); ?>" min="1" max="20" class="small-text">
		<p class="description"><?php esc_html_e('Maximum number of due schedules processed per cron runner tick. Default: 3.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render large batch slicing threshold field.
	 *
	 * @return void
	 */
	public function large_batch_threshold_field_callback() {
		$value = (int) AIPS_Config::get_instance()->get_option('aips_large_batch_threshold');
		?>
		<input type="number" name="aips_large_batch_threshold" value="<?php echo esc_attr($value); ?>" min="2" max="50" class="small-text">
		<p class="description"><?php esc_html_e('Minimum post quantity that triggers background queue slicing instead of running all posts in a single cron tick. Default: 5.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render batch queue max slices field.
	 *
	 * @return void
	 */
	public function batch_max_slices_field_callback() {
		$value = (int) AIPS_Config::get_instance()->get_option('aips_batch_max_slices');
		?>
		<input type="number" name="aips_batch_max_slices" value="<?php echo esc_attr($value); ?>" min="1" max="50" class="small-text">
		<p class="description"><?php esc_html_e('Maximum number of asynchronous cron slices created per large batch run. Default: 10.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render batch queue window seconds field.
	 *
	 * @return void
	 */
	public function batch_queue_window_seconds_field_callback() {
		$value = (int) AIPS_Config::get_instance()->get_option('aips_batch_queue_window_seconds');
		?>
		<input type="number" name="aips_batch_queue_window_seconds" value="<?php echo esc_attr($value); ?>" min="60" max="7200" step="30" class="small-text">
		<p class="description"><?php esc_html_e('Time window (in seconds) across which sliced batch jobs are spread (600s = 10 minutes). Default: 600.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render generation timeout seconds field.
	 *
	 * @return void
	 */
	public function generation_timeout_seconds_field_callback() {
		$value = (int) AIPS_Config::get_instance()->get_option('aips_generation_timeout_seconds');
		?>
		<input type="number" name="aips_generation_timeout_seconds" value="<?php echo esc_attr($value); ?>" min="30" max="600" class="small-text">
		<p class="description"><?php esc_html_e('Maximum execution time (in seconds) allowed for a single post generation request before timing out. Default: 120.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	// -------------------------------------------------------------------------
	// Content Strategy / Embeddings / Deduplication / Related Posts
	// -------------------------------------------------------------------------

	/**
	 * Render embeddings provider field.
	 *
	 * @return void
	 */
	public function embeddings_provider_field_callback() {
		$value = (string) AIPS_Config::get_instance()->get_option('aips_embeddings_provider');
		$all   = AIPS_AI_Provider_Factory::all_providers();
		?>
		<select name="aips_embeddings_provider">
			<option value="" <?php selected($value, ''); ?>><?php esc_html_e('Auto-detect (Meow preferred)', 'ai-post-scheduler'); ?></option>
			<?php foreach ($all as $id => $label) : ?>
				<option value="<?php echo esc_attr($id); ?>" <?php selected($value, $id); ?>><?php echo esc_html($label); ?></option>
			<?php endforeach; ?>
		</select>
		<p class="description"><?php esc_html_e('AI provider used for generating vector embeddings.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render embeddings model field.
	 *
	 * @return void
	 */
	public function embeddings_model_field_callback() {
		$value = (string) AIPS_Config::get_instance()->get_option('aips_embeddings_model');
		?>
		<input type="text" name="aips_embeddings_model" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="text-embedding-3-small">
		<p class="description"><?php esc_html_e('Model name used to calculate embeddings (e.g. text-embedding-3-small). Default: text-embedding-3-small.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render embeddings environment ID field.
	 *
	 * @return void
	 */
	public function embeddings_env_id_field_callback() {
		$value = (string) AIPS_Config::get_instance()->get_option('aips_embeddings_env_id');
		?>
		<input type="text" name="aips_embeddings_env_id" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="Leave empty for default">
		<p class="description"><?php esc_html_e('Meow AI Engine environment ID for embeddings (optional).', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render embeddings dimensions field.
	 *
	 * @return void
	 */
	public function embeddings_dimensions_field_callback() {
		$value = (int) AIPS_Config::get_instance()->get_option('aips_embeddings_dimensions');
		?>
		<input type="number" name="aips_embeddings_dimensions" value="<?php echo esc_attr($value); ?>" min="1" class="small-text">
		<p class="description"><?php esc_html_e('Vector dimension count produced by the embeddings model. Default: 1536.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render indexer post types field.
	 *
	 * @return void
	 */
	public function indexer_post_types_field_callback() {
		$stored = AIPS_Config::get_instance()->get_option('aips_indexer_post_types');
		$selected = is_array($stored) ? $stored : array('post');
		$post_types = get_post_types(array('public' => true), 'objects');
		?>
		<fieldset>
			<input type="hidden" name="aips_indexer_post_types[]" value="">
			<?php foreach ($post_types as $pt) : ?>
				<label style="margin-right: 15px;">
					<input type="checkbox" name="aips_indexer_post_types[]" value="<?php echo esc_attr($pt->name); ?>" <?php checked(in_array($pt->name, $selected, true)); ?>>
					<?php echo esc_html($pt->labels->name); ?> (<code><?php echo esc_html($pt->name); ?></code>)
				</label>
			<?php endforeach; ?>
		</fieldset>
		<p class="description"><?php esc_html_e('Public post types included in vector indexing and related post discovery.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render indexer similarity threshold field.
	 *
	 * @return void
	 */
	public function indexer_similarity_threshold_field_callback() {
		$value = (float) AIPS_Config::get_instance()->get_option('aips_indexer_similarity_threshold');
		?>
		<input type="number" name="aips_indexer_similarity_threshold" value="<?php echo esc_attr($value); ?>" min="0.1" max="1.0" step="0.01" class="small-text">
		<p class="description"><?php esc_html_e('Minimum similarity score (0.1–1.0) required for a post to be considered semantically relevant. Default: 0.65.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render auto-index on publish field.
	 *
	 * @return void
	 */
	public function auto_index_on_publish_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_auto_index_on_publish');
		?>
		<input type="hidden" name="aips_auto_index_on_publish" value="0">
		<label>
			<input type="checkbox" name="aips_auto_index_on_publish" value="1" <?php checked(!empty($value)); ?>>
			<?php esc_html_e('Automatically compute embeddings and index posts when published or updated', 'ai-post-scheduler'); ?>
		</label>
		<?php
	}

	/**
	 * Render deduplication mode field.
	 *
	 * @return void
	 */
	public function deduplication_mode_field_callback() {
		$value = (string) AIPS_Config::get_instance()->get_option('aips_deduplication_mode');
		?>
		<select name="aips_deduplication_mode">
			<option value="warn" <?php selected($value, 'warn'); ?>><?php esc_html_e('Warn (Flag in generation log and review queue)', 'ai-post-scheduler'); ?></option>
			<option value="block" <?php selected($value, 'block'); ?>><?php esc_html_e('Block (Abort generation to prevent keyword cannibalization)', 'ai-post-scheduler'); ?></option>
			<option value="off" <?php selected($value, 'off'); ?>><?php esc_html_e('Off (Disabled)', 'ai-post-scheduler'); ?></option>
		</select>
		<p class="description"><?php esc_html_e('Action taken when a new topic or post is detected as cannibalizing an existing published post.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render deduplication threshold field.
	 *
	 * @return void
	 */
	public function deduplication_threshold_field_callback() {
		$value = (float) AIPS_Config::get_instance()->get_option('aips_deduplication_threshold');
		?>
		<input type="number" name="aips_deduplication_threshold" value="<?php echo esc_attr($value); ?>" min="0.1" max="1.0" step="0.01" class="small-text">
		<p class="description"><?php esc_html_e('Cosine similarity threshold (0.1–1.0) above which content is flagged as duplicate/cannibalizing. Default: 0.85.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render generation inject related context field.
	 *
	 * @return void
	 */
	public function generation_inject_related_context_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_generation_inject_related_context');
		?>
		<input type="hidden" name="aips_generation_inject_related_context" value="0">
		<label>
			<input type="checkbox" name="aips_generation_inject_related_context" value="1" <?php checked(!empty($value)); ?>>
			<?php esc_html_e('Inject summaries of existing related posts into AI prompt for internal linking and context', 'ai-post-scheduler'); ?>
		</label>
		<?php
	}

	/**
	 * Render related posts enabled field.
	 *
	 * @return void
	 */
	public function related_posts_enabled_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_related_posts_enabled');
		?>
		<input type="hidden" name="aips_related_posts_enabled" value="0">
		<label>
			<input type="checkbox" name="aips_related_posts_enabled" value="1" <?php checked(!empty($value)); ?>>
			<?php esc_html_e('Enable AI-powered semantic related posts system', 'ai-post-scheduler'); ?>
		</label>
		<?php
	}

	/**
	 * Render related posts auto append field.
	 *
	 * @return void
	 */
	public function related_posts_auto_append_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_related_posts_auto_append');
		?>
		<input type="hidden" name="aips_related_posts_auto_append" value="0">
		<label>
			<input type="checkbox" name="aips_related_posts_auto_append" value="1" <?php checked(!empty($value)); ?>>
			<?php esc_html_e('Automatically append related posts block to post content on the frontend', 'ai-post-scheduler'); ?>
		</label>
		<?php
	}

	/**
	 * Render related posts count field.
	 *
	 * @return void
	 */
	public function related_posts_count_field_callback() {
		$value = (int) AIPS_Config::get_instance()->get_option('aips_related_posts_count');
		?>
		<input type="number" name="aips_related_posts_count" value="<?php echo esc_attr($value); ?>" min="1" max="12" class="small-text">
		<p class="description"><?php esc_html_e('Number of related posts to display. Default: 4.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render related posts heading field.
	 *
	 * @return void
	 */
	public function related_posts_heading_field_callback() {
		$value = (string) AIPS_Config::get_instance()->get_option('aips_related_posts_heading');
		?>
		<input type="text" name="aips_related_posts_heading" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="Related Articles">
		<p class="description"><?php esc_html_e('Section heading title displayed above related posts. Default: Related Articles.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render related posts layout field.
	 *
	 * @return void
	 */
	public function related_posts_layout_field_callback() {
		$value = (string) AIPS_Config::get_instance()->get_option('aips_related_posts_layout');
		?>
		<select name="aips_related_posts_layout">
			<option value="grid" <?php selected($value, 'grid'); ?>><?php esc_html_e('Grid (2-column cards)', 'ai-post-scheduler'); ?></option>
			<option value="list" <?php selected($value, 'list'); ?>><?php esc_html_e('List', 'ai-post-scheduler'); ?></option>
		</select>
		<?php
	}

	/**
	 * Render related posts show thumbnails field.
	 *
	 * @return void
	 */
	public function related_posts_show_thumbnails_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_related_posts_show_thumbnails');
		?>
		<input type="hidden" name="aips_related_posts_show_thumbnails" value="0">
		<label>
			<input type="checkbox" name="aips_related_posts_show_thumbnails" value="1" <?php checked(!empty($value)); ?>>
			<?php esc_html_e('Display featured image thumbnails in related posts block', 'ai-post-scheduler'); ?>
		</label>
		<?php
	}

	/**
	 * Render related posts show excerpts field.
	 *
	 * @return void
	 */
	public function related_posts_show_excerpts_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_related_posts_show_excerpts');
		?>
		<input type="hidden" name="aips_related_posts_show_excerpts" value="0">
		<label>
			<input type="checkbox" name="aips_related_posts_show_excerpts" value="1" <?php checked(!empty($value)); ?>>
			<?php esc_html_e('Display short excerpts in related posts block', 'ai-post-scheduler'); ?>
		</label>
		<?php
	}

	// -------------------------------------------------------------------------
	// Cache Monitor Advanced field callbacks
	// -------------------------------------------------------------------------

	/**
	 * Render cache monitor event retention days field.
	 *
	 * @return void
	 */
	public function cache_monitor_event_retention_days_field_callback() {
		$value = (int) AIPS_Config::get_instance()->get_option('aips_cache_monitor_event_retention_days');
		?>
		<input type="number" name="aips_cache_monitor_event_retention_days" value="<?php echo esc_attr($value); ?>" min="1" max="365" class="small-text">
		<p class="description"><?php esc_html_e('Days to retain cache monitor hit/miss events. Default: 30.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render cache monitor max index entries field.
	 *
	 * @return void
	 */
	public function cache_monitor_max_index_entries_field_callback() {
		$value = (int) AIPS_Config::get_instance()->get_option('aips_cache_monitor_max_index_entries');
		?>
		<input type="number" name="aips_cache_monitor_max_index_entries" value="<?php echo esc_attr($value); ?>" min="100" max="100000" class="small-text">
		<p class="description"><?php esc_html_e('Maximum tracked keys in the cache index table. Default: 10000.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render cache monitor preview length field.
	 *
	 * @return void
	 */
	public function cache_monitor_preview_length_field_callback() {
		$value = (int) AIPS_Config::get_instance()->get_option('aips_cache_monitor_preview_length');
		?>
		<input type="number" name="aips_cache_monitor_preview_length" value="<?php echo esc_attr($value); ?>" min="50" max="5000" class="small-text">
		<p class="description"><?php esc_html_e('Character length limit for cached value previews in the monitor table. Default: 500.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render cache monitor live refresh interval field.
	 *
	 * @return void
	 */
	public function cache_monitor_live_refresh_interval_field_callback() {
		$value = (int) AIPS_Config::get_instance()->get_option('aips_cache_monitor_live_refresh_interval');
		?>
		<input type="number" name="aips_cache_monitor_live_refresh_interval" value="<?php echo esc_attr($value); ?>" min="5" max="300" class="small-text">
		<p class="description"><?php esc_html_e('Live refresh polling interval (in seconds) on the Cache Monitor dashboard. Default: 30.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	// -------------------------------------------------------------------------
	// API Keys / Webhooks field callbacks
	// -------------------------------------------------------------------------

	/**
	 * Render webhook URL field.
	 *
	 * @return void
	 */
	public function webhook_url_field_callback() {
		$value = (string) AIPS_Config::get_instance()->get_option('aips_webhook_url');
		?>
		<input type="url" name="aips_webhook_url" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="https://hooks.zapier.com/hooks/catch/...">
		<p class="description"><?php esc_html_e('External endpoint URL (e.g. Zapier, Make, custom API) to notify when generation events occur.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render webhook secret field.
	 *
	 * @return void
	 */
	public function webhook_secret_field_callback() {
		$value = (string) AIPS_Config::get_instance()->get_option('aips_webhook_secret');
		?>
		<input type="password" name="aips_webhook_secret" value="<?php echo esc_attr($value); ?>" class="regular-text" autocomplete="new-password">
		<p class="description"><?php esc_html_e('Optional shared secret used to sign the X-AIPS-Signature header with HMAC-SHA256.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render webhook events field.
	 *
	 * @return void
	 */
	public function webhook_events_field_callback() {
		$stored = AIPS_Config::get_instance()->get_option('aips_webhook_events');
		$selected = is_array($stored) ? $stored : array('generation_completed', 'generation_failed', 'post_ready_for_review');
		$events = array(
			'generation_completed' => __('Post Generation Completed', 'ai-post-scheduler'),
			'generation_failed'    => __('Post Generation Failed', 'ai-post-scheduler'),
			'post_ready_for_review' => __('Post Ready for Review', 'ai-post-scheduler'),
		);
		?>
		<fieldset>
			<input type="hidden" name="aips_webhook_events[]" value="">
			<?php foreach ($events as $slug => $label) : ?>
				<label style="display:block; margin-bottom: 5px;">
					<input type="checkbox" name="aips_webhook_events[]" value="<?php echo esc_attr($slug); ?>" <?php checked(in_array($slug, $selected, true)); ?>>
					<?php echo esc_html($label); ?>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<p class="description"><?php esc_html_e('Select which generation lifecycle events trigger webhook notifications.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	// -------------------------------------------------------------------------
	// Developers Tab field callbacks
	// -------------------------------------------------------------------------

	/**
	 * Render log retention days field.
	 *
	 * @return void
	 */
	public function log_retention_days_field_callback() {
		$value = (int) AIPS_Config::get_instance()->get_option('aips_log_retention_days');
		?>
		<input type="number" name="aips_log_retention_days" value="<?php echo esc_attr($value); ?>" min="1" max="365" class="small-text">
		<p class="description"><?php esc_html_e('Number of days to retain generation log files and database logs before automatic purging. Default: 30.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render history retention days field.
	 *
	 * @return void
	 */
	public function history_retention_days_field_callback() {
		$value = (int) AIPS_Config::get_instance()->get_option('aips_history_retention_days');
		?>
		<input type="number" name="aips_history_retention_days" value="<?php echo esc_attr($value); ?>" min="1" max="730" class="small-text">
		<p class="description"><?php esc_html_e('Number of days to keep post generation history records before cleanup. Default: 90.', 'ai-post-scheduler'); ?></p>
		<?php
	}

}

