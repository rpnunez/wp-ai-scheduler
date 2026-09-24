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
	 * Render the description for Card 1: Content Generation AI Provider.
	 *
	 * @return void
	 */
	public function ai_provider_section_callback() {
	}

	/**
	 * Render the description for Card 2: Token Budgets & Prompt Optimization.
	 *
	 * @return void
	 */
	public function ai_tokens_section_callback() {
	}

	/**
	 * Render the description for Card 3: Vector Embeddings Engine & Model.
	 *
	 * @return void
	 */
	public function ai_embeddings_section_callback() {
	}

	/**
	 * Render the description for Card 4: Indexing Scope, Continuous Sync & Rate Limits.
	 *
	 * @return void
	 */
	public function ai_scope_section_callback() {
	}

	/**
	 * Render the description for Card 7: Internal Link Automation.
	 *
	 * @return void
	 */
	public function ai_autolink_section_callback() {
	}

	/**
	 * Render the description for the Link Index card (Internal Linking tab).
	 *
	 * @return void
	 */
	public function link_index_section_callback() {
	}

	/**
	 * Render the description for the Keyword Link Rules card (Internal Linking tab).
	 *
	 * @return void
	 */
	public function link_rules_section_callback() {
	}

	/**
	 * Render the description for Card 5: Frontend Related Posts Engine.
	 *
	 * @return void
	 */
	public function ai_related_posts_section_callback() {
	}

	/**
	 * Render the description for Card 6: Semantic Duplicate Detection & Gatekeeper Guard.
	 *
	 * @return void
	 */
	public function ai_deduplication_section_callback() {
	}

	/**
	 * Render the description for the Authors settings section.
	 *
	 * @return void
	 */
	public function authors_section_callback() {
		echo '<p>' . esc_html__('Configure default auto-approval policies and dual-boundary semantic gates for author topics.', 'ai-post-scheduler') . '</p>';
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
     * Render the Google Search Console connection (API Keys tab).
     *
     * Search Console data is private, so it needs a service account key
     * rather than a plain API key. The saved key is never printed back.
     *
     * @return void
     */
    public function gsc_field_callback() {
        $config     = AIPS_Config::get_instance();
        $service    = AIPS_Container::get_instance()->make(AIPS_GSC_Keywords_Service::class);
        $client     = $service->get_client();
        $email      = $client->get_client_email();
        $unreadable = $client->has_unreadable_credentials();
        $property   = $client->get_property();
        $anchor     = (bool) $config->get_option('aips_gsc_anchor_enabled', true);
        $status     = $service->get_status();
        ?>
        <div class="aips-gsc-field" id="aips-gsc-field">
            <p class="aips-gsc-status">
                <?php if ($email !== '') : ?>
                    <span class="aips-badge aips-badge-success"><?php esc_html_e('Key saved', 'ai-post-scheduler'); ?></span>
                    <?php
                    /* translators: %s: service account email */
                    printf(esc_html__('Service account: %s', 'ai-post-scheduler'), '<code>' . esc_html($email) . '</code>');
                    ?>
                <?php elseif ($unreadable) : ?>
                    <span class="aips-badge aips-badge-danger"><?php esc_html_e('Key unreadable', 'ai-post-scheduler'); ?></span>
                    <?php esc_html_e('The saved key can no longer be decrypted (the site\'s security salts changed). Paste the JSON key again.', 'ai-post-scheduler'); ?>
                <?php else : ?>
                    <span class="aips-badge aips-badge-neutral"><?php esc_html_e('Not connected', 'ai-post-scheduler'); ?></span>
                <?php endif; ?>
            </p>

            <p>
                <label for="aips_gsc_service_account"><strong><?php esc_html_e('Service account JSON key', 'ai-post-scheduler'); ?></strong></label><br>
                <textarea name="aips_gsc_service_account" id="aips_gsc_service_account" rows="4" class="large-text code" autocomplete="off" spellcheck="false" placeholder="<?php echo esc_attr($email !== '' ? __('Leave empty to keep the saved key, or paste a new JSON key to replace it.', 'ai-post-scheduler') : __('Paste the contents of the downloaded JSON key file ({ "type": "service_account", ... })', 'ai-post-scheduler')); ?>"></textarea>
            </p>

            <p>
                <label for="aips_gsc_property"><strong><?php esc_html_e('Property', 'ai-post-scheduler'); ?></strong></label><br>
                <input type="text" name="aips_gsc_property" id="aips_gsc_property" value="<?php echo esc_attr($property); ?>" class="regular-text" placeholder="<?php echo esc_attr('sc-domain:' . wp_parse_url(home_url(), PHP_URL_HOST)); ?>">
                <span class="description"><?php esc_html_e('Exactly as shown in Search Console: sc-domain:example.com for a Domain property, or https://example.com/ for a URL-prefix property.', 'ai-post-scheduler'); ?></span>
            </p>

            <p>
                <label for="aips_gsc_anchor_enabled">
                    <input type="checkbox" name="aips_gsc_anchor_enabled" id="aips_gsc_anchor_enabled" value="1" <?php checked($anchor); ?>>
                    <?php esc_html_e('Prefer the search queries each post ranks for as anchor text in internal link suggestions', 'ai-post-scheduler'); ?>
                </label>
            </p>

            <p class="aips-gsc-actions">
                <button type="button" class="aips-btn aips-btn-sm aips-btn-secondary" id="aips-gsc-test"><?php esc_html_e('Test Connection', 'ai-post-scheduler'); ?></button>
                <button type="button" class="aips-btn aips-btn-sm aips-btn-secondary" id="aips-gsc-sync"><?php esc_html_e('Sync Keywords Now', 'ai-post-scheduler'); ?></button>
                <?php if ($email !== '' || $unreadable) : ?>
                <button type="button" class="aips-btn aips-btn-sm aips-btn-ghost" id="aips-gsc-disconnect"><?php esc_html_e('Disconnect', 'ai-post-scheduler'); ?></button>
                <?php endif; ?>
            </p>

            <p class="description" id="aips-gsc-last-sync">
                <?php
                if ($status && $status['error'] !== '') {
                    /* translators: 1: time ago, 2: error message */
                    printf(esc_html__('Last sync failed %1$s ago: %2$s', 'ai-post-scheduler'), esc_html(human_time_diff((int) $status['time'])), esc_html($status['error']));
                } elseif ($status) {
                    /* translators: 1: time ago, 2: number of keywords, 3: number of posts */
                    printf(esc_html__('Last synced %1$s ago: %2$d target keywords for %3$d posts. Syncs daily.', 'ai-post-scheduler'), esc_html(human_time_diff((int) $status['time'])), (int) $status['queries'], (int) $status['posts']);
                } else {
                    esc_html_e('Not synced yet. Save your settings first, then test and sync.', 'ai-post-scheduler');
                }
                ?>
            </p>

            <details class="aips-gsc-help">
                <summary><?php esc_html_e('How to connect (why not an API key?)', 'ai-post-scheduler'); ?></summary>
                <p><?php esc_html_e('Search Console data is private to verified site owners, so Google does not allow reading it with a plain API key. A service account is a robot Google user you create once and grant read-only access:', 'ai-post-scheduler'); ?></p>
                <ol>
                    <li><?php esc_html_e('In Google Cloud Console, create (or pick) a project and enable the "Google Search Console API".', 'ai-post-scheduler'); ?></li>
                    <li><?php esc_html_e('Go to IAM & Admin → Service Accounts → Create service account (no roles needed).', 'ai-post-scheduler'); ?></li>
                    <li><?php esc_html_e('Open it → Keys → Add key → Create new key → JSON. Paste the downloaded file above.', 'ai-post-scheduler'); ?></li>
                    <li><?php esc_html_e('In Search Console → Settings → Users and permissions, add the service account email as a user with Restricted permission.', 'ai-post-scheduler'); ?></li>
                    <li><?php esc_html_e('Enter the property, save settings, then click Test Connection and Sync Keywords Now.', 'ai-post-scheduler'); ?></li>
                </ol>
                <p><?php esc_html_e('The key is stored encrypted and only has read-only access. Only the top search queries per post are stored.', 'ai-post-scheduler'); ?></p>
            </details>
        </div>
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
     * Render the generation pacing delay setting field.
     */
    public function generation_delay_seconds_field_callback() {
        $value = AIPS_Config::get_instance()->get_option('aips_generation_delay_seconds');
        ?>
        <input type="number" name="aips_generation_delay_seconds" value="<?php echo esc_attr($value); ?>" min="0" max="30" class="small-text">
        <p class="description"><?php esc_html_e('Pause between posts in scheduled batch runs to smooth server load and stay under provider rate limits. Manual runs are not delayed. 0 disables the pause.', 'ai-post-scheduler'); ?></p>
        <?php
    }

    /**
     * Render the batch resume cooldown setting field.
     */
    public function batch_resume_cooldown_minutes_field_callback() {
        $value = AIPS_Config::get_instance()->get_option('aips_batch_resume_cooldown_minutes');
        ?>
        <input type="number" name="aips_batch_resume_cooldown_minutes" value="<?php echo esc_attr($value); ?>" min="1" max="1440" class="small-text">
        <p class="description"><?php esc_html_e('When a scheduled batch stops early to avoid a PHP timeout, wait this many minutes before generating its remaining posts.', 'ai-post-scheduler'); ?></p>
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
	 * Render the table filter persistence setting field.
	 *
	 * Displays a checkbox to enable or disable table filter persistence in localStorage.
	 *
	 * @return void
	 */
	public function persist_table_filters_field_callback() {
		$value = AIPS_Config::get_instance()->get_option('aips_persist_table_filters');
		?>
		<input type="hidden" name="aips_persist_table_filters" value="0">
		<label>
			<input type="checkbox" name="aips_persist_table_filters" value="1" <?php checked($value, 1); ?>>
			<?php esc_html_e('Persist table filter and search selections across sessions', 'ai-post-scheduler'); ?>
		</label>
		<p class="description">
			<?php esc_html_e('When enabled, your active search terms, status views, and filter dropdowns are automatically remembered in your browser for each table.', 'ai-post-scheduler'); ?>
		</p>
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
     * Sanitize an auto-link confidence threshold (0.50 - 1.00).
     *
     * @param mixed $value Raw value.
     * @return float
     */
    public function sanitize_autolink_threshold($value) {
        if (!is_numeric($value)) {
            return 0.85;
        }
        return round(min(1.0, max(0.5, (float) $value)), 2);
    }

    /**
     * Sanitize an auto-link count limit (1 - 100).
     *
     * @param mixed $value Raw value.
     * @return int
     */
    public function sanitize_autolink_limit($value) {
        return min(100, max(1, absint($value)));
    }

    /**
     * Sanitize the number of posts indexed per link index rebuild batch (10 - 500).
     *
     * @param mixed $value Raw value.
     * @return int
     */
    public function sanitize_link_index_batch_size($value) {
        return min(500, max(10, absint($value)));
    }

    /**
     * Sanitize the pause between link index rebuild batches in seconds (0 - 600).
     *
     * @param mixed $value Raw value.
     * @return int
     */
    public function sanitize_link_index_batch_delay($value) {
        return min(600, absint($value));
    }

    /**
     * Sanitize the maximum rule links added to one post (1 - 20).
     *
     * @param mixed $value Raw value.
     * @return int
     */
    public function sanitize_link_rules_max_per_post($value) {
        return min(20, max(1, absint($value)));
    }

    /**
     * Sanitize the generation-time linking mode.
     *
     * @param mixed $value Raw value.
     * @return string off|review|apply
     */
    public function sanitize_publish_linking_mode($value) {
        $value = sanitize_key((string) $value);
        return in_array($value, array('off', 'review', 'apply'), true) ? $value : 'review';
    }

    /**
     * Sanitize the link click retention period (30–730 days).
     *
     * @param mixed $value Raw value.
     * @return int
     */
    public function sanitize_link_click_retention_days($value) {
        return min(730, max(30, absint($value)));
    }

    /**
     * Sanitize where the silo "In this guide" list goes on a pillar.
     *
     * @param mixed $value Raw value.
     * @return string 'end' or 'after_first_paragraph'.
     */
    public function sanitize_silo_guide_position($value) {
        return $value === 'after_first_paragraph' ? 'after_first_paragraph' : 'end';
    }

    /**
     * Sanitize the number of articles in the silo "In this guide" list.
     *
     * @param mixed $value Raw value.
     * @return int 1-50.
     */
    public function sanitize_silo_guide_max($value) {
        return min(50, max(1, absint($value)));
    }

    /**
     * Sanitize the silo "In this guide" list style.
     *
     * @param mixed $value Raw value.
     * @return string 'aips' or 'theme'.
     */
    public function sanitize_silo_guide_style($value) {
        return $value === 'theme' ? 'theme' : 'aips';
    }

    /**
     * Sanitize the rel attribute applied to auto-inserted links.
     *
     * @param mixed $value Raw value.
     * @return string One of '', 'nofollow', 'sponsored', 'ugc'.
     */
    public function sanitize_autolink_rel($value) {
        $value = sanitize_key((string) $value);
        return in_array($value, array('nofollow', 'sponsored', 'ugc'), true) ? $value : '';
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
	 * Render header description for embeddings section in AI settings.
	 *
	 * @return void
	 */
	public function embeddings_header_callback() {
		echo '<p class="description">' . esc_html__('Configure vector embeddings, semantic search models, indexing scope, and rate-limiting safeguards to prevent unexpected AI API costs.', 'ai-post-scheduler') . '</p>';
	}

	/**
	 * Render the master toggle for Vector Embeddings (Card 3).
	 *
	 * @return void
	 */
	public function embeddings_enabled_field_callback() {
		$value = (bool) AIPS_Config::get_instance()->get_option('aips_embeddings_enabled', true);
		?>
		<label for="aips_embeddings_enabled">
			<input type="checkbox" name="aips_embeddings_enabled" id="aips_embeddings_enabled" value="1" <?php checked($value); ?>>
			<strong><?php esc_html_e('Enable the Vector Embeddings Engine', 'ai-post-scheduler'); ?></strong>
		</label>
		<p class="description"><?php esc_html_e('Master switch to enable or disable all vector embedding generation, automated continuous indexing, topic embeddings cron workers, and semantic duplicate detection.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render the embeddings provider selection field (Card 3).
	 *
	 * @return void
	 */
	public function embeddings_provider_field_callback() {
		$value = (string) AIPS_Config::get_instance()->get_option('aips_embeddings_provider', '');
		?>
		<select name="aips_embeddings_provider" id="aips_embeddings_provider" class="regular-text">
			<option value="" <?php selected($value, ''); ?>><?php esc_html_e('Auto-detect (Meow Apps AI Engine preferred)', 'ai-post-scheduler'); ?></option>
			<option value="meow" <?php selected($value, 'meow'); ?>><?php esc_html_e('Meow Apps AI Engine', 'ai-post-scheduler'); ?></option>
			<option value="wp_ai_client" <?php selected($value, 'wp_ai_client'); ?>><?php esc_html_e('WordPress AI Client (WP AI API)', 'ai-post-scheduler'); ?></option>
		</select>
		<p class="description"><?php esc_html_e('Select which AI subsystem produces vector embeddings. Decoupled from the primary post generation provider.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render the embeddings model field (Card 3).
	 *
	 * @return void
	 */
	public function embeddings_model_field_callback() {
		$value = (string) AIPS_Config::get_instance()->get_option('aips_embeddings_model', 'text-embedding-3-small');
		?>
		<input type="text" name="aips_embeddings_model" id="aips_embeddings_model" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="text-embedding-3-small">
		<p class="description"><?php esc_html_e('Model identifier (e.g. text-embedding-3-small, text-embedding-3-large, text-embedding-ada-002, all-minilm).', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render the embeddings environment ID field with discovery button (Card 3).
	 *
	 * @return void
	 */
	public function embeddings_env_id_field_callback() {
		$value = (string) AIPS_Config::get_instance()->get_option('aips_embeddings_env_id', '');
		?>
		<div class="aips-env-input-wrap">
			<input type="text" name="aips_embeddings_env_id" id="aips_embeddings_env_id" value="<?php echo esc_attr($value); ?>" class="regular-text" placeholder="<?php esc_attr_e('e.g. default, percona_db, pinecone_env', 'ai-post-scheduler'); ?>">
			<button type="button" id="aips-fetch-meow-envs-btn" class="aips-btn aips-btn-secondary aips-btn-sm">
				<span class="dashicons dashicons-rest-api"></span>
				<?php esc_html_e('Fetch Environments from Meow Apps', 'ai-post-scheduler'); ?>
			</button>
		</div>
		<div id="aips-meow-envs-dropdown-container" class="aips-meow-envs-container aips-hidden">
			<select id="aips-meow-envs-select" class="regular-text">
				<option value=""><?php esc_html_e('— Select a discovered environment —', 'ai-post-scheduler'); ?></option>
			</select>
		</div>
		<p class="description"><?php esc_html_e('Optional connection / environment ID from Meow AI Engine (e.g. Percona Server vector database, OpenAI, Pinecone, Qdrant, Ollama).', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render the embeddings dimensions field (Card 3).
	 *
	 * @return void
	 */
	public function embeddings_dimensions_field_callback() {
		$value = (int) AIPS_Config::get_instance()->get_option('aips_embeddings_dimensions', 1536);
		?>
		<input type="number" min="1" max="8192" step="1" name="aips_embeddings_dimensions" id="aips_embeddings_dimensions" value="<?php echo esc_attr((string) $value); ?>" class="small-text">
		<p class="description"><?php esc_html_e('Number of vector dimensions produced by the model (e.g. 1536 for OpenAI, 768 for Percona / Sentence-Transformers, 384 for all-MiniLM).', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render persistent vector caching toggle (Card 3).
	 *
	 * @return void
	 */
	public function embeddings_persistent_cache_field_callback() {
		$value = (bool) AIPS_Config::get_instance()->get_option('aips_embeddings_persistent_cache_enabled', true);
		?>
		<label for="aips_embeddings_persistent_cache_enabled">
			<input type="checkbox" name="aips_embeddings_persistent_cache_enabled" id="aips_embeddings_persistent_cache_enabled" value="1" <?php checked($value); ?>>
			<?php esc_html_e('Cache raw text vectors persistently in WordPress transients / object cache (7-day TTL)', 'ai-post-scheduler'); ?>
		</label>
		<p class="description">
			<?php esc_html_e('Prevents duplicate AI API calls when the same text prompt or post content is vectorized multiple times across background scans, topic checks, and internal link generation.', 'ai-post-scheduler'); ?>
		</p>
		<?php
	}

	/**
	 * Render live quota meters and active scope badge (Card 4).
	 *
	 * @return void
	 */
	public function embeddings_quota_meters_field_callback() {
		$config      = AIPS_Config::get_instance();
		$scope_val   = (string) $config->get_option('aips_embeddings_scope', 'aips_only');
		$daily_lim   = (int) $config->get_option('aips_embeddings_daily_limit', 50);
		$weekly_lim  = (int) $config->get_option('aips_embeddings_weekly_limit', 200);
		$monthly_lim = (int) $config->get_option('aips_embeddings_monthly_limit', 500);

		$daily_cnt   = 0;
		$weekly_cnt  = 0;
		$monthly_cnt = 0;

		if (class_exists('AIPS_Content_Indexer_Service')) {
			$indexer = new AIPS_Content_Indexer_Service();
			$status  = $indexer->get_indexing_status();
			if (isset($status['rate_limits'])) {
				$daily_cnt   = isset($status['rate_limits']['daily_count']) ? (int) $status['rate_limits']['daily_count'] : 0;
				$weekly_cnt  = isset($status['rate_limits']['weekly_count']) ? (int) $status['rate_limits']['weekly_count'] : 0;
				$monthly_cnt = isset($status['rate_limits']['monthly_count']) ? (int) $status['rate_limits']['monthly_count'] : 0;
			}
		}

		$daily_pct   = $daily_lim > 0 ? min(100, (int) round(($daily_cnt / $daily_lim) * 100)) : 0;
		$weekly_pct  = $weekly_lim > 0 ? min(100, (int) round(($weekly_cnt / $weekly_lim) * 100)) : 0;
		$monthly_pct = $monthly_lim > 0 ? min(100, (int) round(($monthly_cnt / $monthly_lim) * 100)) : 0;
		?>
		<div class="aips-quota-meters-grid">
			<div class="aips-quota-meter-item">
				<div class="aips-quota-meter-header">
					<span class="aips-quota-meter-label"><?php esc_html_e('Daily Quota (24h)', 'ai-post-scheduler'); ?></span>
					<span class="aips-quota-meter-count" id="aips-meter-daily-count">
						<strong><?php echo esc_html($daily_cnt); ?></strong> / <?php echo $daily_lim > 0 ? esc_html($daily_lim) : '∞'; ?>
					</span>
				</div>
				<div class="aips-quota-bar-track">
					<div class="aips-quota-bar-fill <?php echo $daily_pct >= 90 ? 'aips-quota-danger' : ($daily_pct >= 70 ? 'aips-quota-warning' : ''); ?>" style="width: <?php echo esc_attr($daily_pct); ?>%;"></div>
				</div>
			</div>

			<div class="aips-quota-meter-item">
				<div class="aips-quota-meter-header">
					<span class="aips-quota-meter-label"><?php esc_html_e('Weekly Quota (7d)', 'ai-post-scheduler'); ?></span>
					<span class="aips-quota-meter-count" id="aips-meter-weekly-count">
						<strong><?php echo esc_html($weekly_cnt); ?></strong> / <?php echo $weekly_lim > 0 ? esc_html($weekly_lim) : '∞'; ?>
					</span>
				</div>
				<div class="aips-quota-bar-track">
					<div class="aips-quota-bar-fill <?php echo $weekly_pct >= 90 ? 'aips-quota-danger' : ($weekly_pct >= 70 ? 'aips-quota-warning' : ''); ?>" style="width: <?php echo esc_attr($weekly_pct); ?>%;"></div>
				</div>
			</div>

			<div class="aips-quota-meter-item">
				<div class="aips-quota-meter-header">
					<span class="aips-quota-meter-label"><?php esc_html_e('Monthly Quota (30d)', 'ai-post-scheduler'); ?></span>
					<span class="aips-quota-meter-count" id="aips-meter-monthly-count">
						<strong><?php echo esc_html($monthly_cnt); ?></strong> / <?php echo $monthly_lim > 0 ? esc_html($monthly_lim) : '∞'; ?>
					</span>
				</div>
				<div class="aips-quota-bar-track">
					<div class="aips-quota-bar-fill <?php echo $monthly_pct >= 90 ? 'aips-quota-danger' : ($monthly_pct >= 70 ? 'aips-quota-warning' : ''); ?>" style="width: <?php echo esc_attr($monthly_pct); ?>%;"></div>
				</div>
			</div>
		</div>

		<div class="aips-scope-badge-wrap">
			<span class="aips-scope-badge-label"><?php esc_html_e('Active Indexing Scope:', 'ai-post-scheduler'); ?></span>
			<span class="aips-scope-badge">
				<?php
				if ('aips_only' === $scope_val) {
					esc_html_e('AIPS-Generated Posts Only (Safe Mode)', 'ai-post-scheduler');
				} elseif ('date_range' === $scope_val) {
					esc_html_e('Posts Within Configured Date Range', 'ai-post-scheduler');
				} else {
					esc_html_e('All Posts (Entire Site Archive)', 'ai-post-scheduler');
				}
				?>
			</span>
		</div>
		<?php
	}

	/**
	 * Render the Indexing Scope Filter setting field (Card 4).
	 *
	 * @return void
	 */
	public function embeddings_scope_field_callback() {
		$config     = AIPS_Config::get_instance();
		$scope      = (string) $config->get_option('aips_embeddings_scope', 'aips_only');
		$date_days  = (int) $config->get_option('aips_embeddings_date_days', 30);
		$date_after = (string) $config->get_option('aips_embeddings_date_after', '');
		?>
		<div class="aips-scope-selector-wrap">
			<label class="aips-scope-radio-label">
				<input type="radio" name="aips_embeddings_scope" value="aips_only" <?php checked($scope, 'aips_only'); ?>>
				<strong><?php esc_html_e('AIPS-Generated Posts Only', 'ai-post-scheduler'); ?></strong>
				<span class="description"><?php esc_html_e('(Recommended for targeted semantic linking between scheduled articles)', 'ai-post-scheduler'); ?></span>
			</label>
			<label class="aips-scope-radio-label">
				<input type="radio" name="aips_embeddings_scope" value="all" <?php checked($scope === 'all' || $scope === 'all_posts', true); ?>>
				<strong><?php esc_html_e('All WordPress Posts (Entire Site Library)', 'ai-post-scheduler'); ?></strong>
				<span class="description"><?php esc_html_e('(Indexes all historical and manual posts across selected post types)', 'ai-post-scheduler'); ?></span>
			</label>
			<label class="aips-scope-radio-label">
				<input type="radio" name="aips_embeddings_scope" value="date_range" <?php checked($scope, 'date_range'); ?>>
				<strong><?php esc_html_e('Specific Publication Date Range', 'ai-post-scheduler'); ?></strong>
				<span class="description"><?php esc_html_e('(Index only posts published within a specific historical time window)', 'ai-post-scheduler'); ?></span>
			</label>
			<div id="aips-scope-date-range-fields" class="aips-scope-date-range-box" style="<?php echo $scope === 'date_range' ? '' : 'display: none;'; ?>">
				<p>
					<label for="aips_embeddings_date_days">
						<strong><?php esc_html_e('Index posts published in the last:', 'ai-post-scheduler'); ?></strong>
					</label>
					<input type="number" min="1" max="3650" step="1" name="aips_embeddings_date_days" id="aips_embeddings_date_days" value="<?php echo esc_attr((string) $date_days); ?>" class="small-text">
					<?php esc_html_e('days', 'ai-post-scheduler'); ?>
				</p>
				<p>
					<label for="aips_embeddings_date_after">
						<strong><?php esc_html_e('Or index posts published on/after (YYYY-MM-DD):', 'ai-post-scheduler'); ?></strong>
					</label>
					<input type="date" name="aips_embeddings_date_after" id="aips_embeddings_date_after" value="<?php echo esc_attr($date_after); ?>" class="regular-text aips-scope-date-input">
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Indexed Post Types setting field (Card 4).
	 *
	 * @return void
	 */
	public function indexer_post_types_field_callback() {
		$selected_pts  = (array) AIPS_Config::get_instance()->get_option('aips_indexer_post_types', array('post'));
		$available_pts = get_post_types(array('public' => true), 'objects');
		unset($available_pts['attachment']);
		?>
		<fieldset>
			<?php foreach ($available_pts as $pt_slug => $pt_obj) : ?>
				<label class="aips-checkbox-label-block">
					<input type="checkbox" name="aips_indexer_post_types[]" value="<?php echo esc_attr($pt_slug); ?>" <?php checked(in_array($pt_slug, $selected_pts, true)); ?>>
					<strong><?php echo esc_html($pt_obj->labels->name); ?></strong> <code>(<?php echo esc_html($pt_slug); ?>)</code>
				</label>
			<?php endforeach; ?>
			<p class="description"><?php esc_html_e('Select which post types to generate embeddings for and include in Related Posts.', 'ai-post-scheduler'); ?></p>
		</fieldset>
		<?php
	}

	/**
	 * Render auto-index on publish toggle (Card 4).
	 *
	 * @return void
	 */
	public function auto_index_on_publish_field_callback() {
		$value = (bool) AIPS_Config::get_instance()->get_option('aips_auto_index_on_publish', true);
		?>
		<label for="aips_auto_index_on_publish">
			<input type="checkbox" name="aips_auto_index_on_publish" id="aips_auto_index_on_publish" value="1" <?php checked($value); ?>>
			<?php esc_html_e('Automatically compute vector embeddings whenever a new post is published or updated', 'ai-post-scheduler'); ?>
		</label>
		<?php
	}

	/**
	 * Render verbose activity logging toggle (Card 4).
	 *
	 * @return void
	 */
	public function indexer_verbose_history_field_callback() {
		$value = (bool) AIPS_Config::get_instance()->get_option('aips_indexer_verbose_history', false);
		?>
		<label for="aips_indexer_verbose_history">
			<input type="checkbox" name="aips_indexer_verbose_history" id="aips_indexer_verbose_history" value="1" <?php checked($value); ?>>
			<?php esc_html_e('Enable verbose indexer run logging in History tab', 'ai-post-scheduler'); ?>
		</label>
		<p class="description"><?php esc_html_e('Leave disabled for large reindex runs to limit History log volume.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render Rate Limiting and Quota Protection fields (Card 4).
	 *
	 * @return void
	 */
	public function embeddings_rate_limits_field_callback() {
		$config        = AIPS_Config::get_instance();
		$enabled       = (bool) $config->get_option('aips_embeddings_rate_limits_enabled', true);
		$daily_limit   = (int) $config->get_option('aips_embeddings_daily_limit', 50);
		$weekly_limit  = (int) $config->get_option('aips_embeddings_weekly_limit', 200);
		$monthly_limit = (int) $config->get_option('aips_embeddings_monthly_limit', 500);
		?>
		<fieldset class="aips-embeddings-rate-limits">
			<label for="aips_embeddings_rate_limits_enabled">
				<input type="checkbox" name="aips_embeddings_rate_limits_enabled" id="aips_embeddings_rate_limits_enabled" value="1" <?php checked($enabled); ?>>
				<strong><?php esc_html_e('Block further vector embedding API calls when spending limits are reached', 'ai-post-scheduler'); ?></strong>
			</label>
			<p class="description">
				<?php esc_html_e('Protects your AI API budget by capping total embedding calls across rolling 24-hour, 7-day, and 30-day windows.', 'ai-post-scheduler'); ?>
			</p>
			<div class="aips-rate-limits-grid">
				<div class="aips-rate-limit-field">
					<label for="aips_embeddings_daily_limit"><?php esc_html_e('24-Hour Limit:', 'ai-post-scheduler'); ?></label>
					<input type="number" min="0" step="1" name="aips_embeddings_daily_limit" id="aips_embeddings_daily_limit" value="<?php echo esc_attr((string) $daily_limit); ?>" class="small-text">
					<span class="description"><?php esc_html_e('posts (0 = unlimited)', 'ai-post-scheduler'); ?></span>
				</div>
				<div class="aips-rate-limit-field">
					<label for="aips_embeddings_weekly_limit"><?php esc_html_e('7-Day Limit:', 'ai-post-scheduler'); ?></label>
					<input type="number" min="0" step="1" name="aips_embeddings_weekly_limit" id="aips_embeddings_weekly_limit" value="<?php echo esc_attr((string) $weekly_limit); ?>" class="small-text">
					<span class="description"><?php esc_html_e('posts (0 = unlimited)', 'ai-post-scheduler'); ?></span>
				</div>
				<div class="aips-rate-limit-field">
					<label for="aips_embeddings_monthly_limit"><?php esc_html_e('30-Day Limit:', 'ai-post-scheduler'); ?></label>
					<input type="number" min="0" step="1" name="aips_embeddings_monthly_limit" id="aips_embeddings_monthly_limit" value="<?php echo esc_attr((string) $monthly_limit); ?>" class="small-text">
					<span class="description"><?php esc_html_e('posts (0 = unlimited)', 'ai-post-scheduler'); ?></span>
				</div>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Render the link index toggle and post types (Internal Linking tab).
	 *
	 * @return void
	 */
	public function link_index_field_callback() {
		$config        = AIPS_Config::get_instance();
		$enabled       = (bool) $config->get_option('aips_link_index_enabled', true);
		$selected_pts  = (array) $config->get_option('aips_link_index_post_types', array('post', 'page'));
		$available_pts = get_post_types(array('public' => true), 'objects');
		unset($available_pts['attachment']);
		?>
		<fieldset>
			<label for="aips_link_index_enabled">
				<input type="checkbox" name="aips_link_index_enabled" id="aips_link_index_enabled" value="1" <?php checked($enabled); ?>>
				<strong><?php esc_html_e('Keep a link index of published content (powers the Link Report and orphan detection)', 'ai-post-scheduler'); ?></strong>
			</label>
			<p class="description"><?php esc_html_e('Links are re-read whenever a post is saved. No AI calls are made.', 'ai-post-scheduler'); ?></p>
			<?php foreach ($available_pts as $pt_slug => $pt_obj) : ?>
				<label class="aips-checkbox-label-block">
					<input type="checkbox" name="aips_link_index_post_types[]" value="<?php echo esc_attr($pt_slug); ?>" <?php checked(in_array($pt_slug, $selected_pts, true)); ?>>
					<strong><?php echo esc_html($pt_obj->labels->name); ?></strong> <code>(<?php echo esc_html($pt_slug); ?>)</code>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<?php
	}

	/**
	 * Render the keyword link rules toggle and per-post cap (Internal Linking tab).
	 *
	 * @return void
	 */
	public function link_rules_field_callback() {
		$config  = AIPS_Config::get_instance();
		$enabled = (bool) $config->get_option('aips_link_rules_enabled', true);
		$max     = (int) $config->get_option('aips_link_rules_max_per_post', 3);
		?>
		<fieldset>
			<label for="aips_link_rules_enabled">
				<input type="checkbox" name="aips_link_rules_enabled" id="aips_link_rules_enabled" value="1" <?php checked($enabled); ?>>
				<strong><?php esc_html_e('Apply keyword link rules to published posts', 'ai-post-scheduler'); ?></strong>
			</label>
			<p>
				<label for="aips_link_rules_max_per_post"><?php esc_html_e('Maximum rule links per post:', 'ai-post-scheduler'); ?></label>
				<input type="number" min="1" max="20" step="1" name="aips_link_rules_max_per_post" id="aips_link_rules_max_per_post" value="<?php echo esc_attr((string) $max); ?>" class="small-text">
			</p>
			<p class="description">
				<?php
				printf(
					/* translators: %s: link to the Link Rules page */
					esc_html__('Rules are added when a post is displayed, so post content is never changed. Manage rules under %s.', 'ai-post-scheduler'),
					'<a href="' . esc_url(admin_url('admin.php?page=aips-generated-posts&tab=link-rules')) . '">' . esc_html__('Content → Link Rules', 'ai-post-scheduler') . '</a>'
				);
				?>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * Render the generation-time linking mode (Internal Linking tab).
	 *
	 * @return void
	 */
	public function publish_linking_field_callback() {
		$config   = AIPS_Config::get_instance();
		$mode     = (string) $config->get_option('aips_publish_linking_mode', 'review');
		$outbound = (bool) $config->get_option('aips_publish_linking_outbound', true);
		$modes    = array(
			'off'    => __('Off', 'ai-post-scheduler'),
			'review' => __('Find links and queue them for review (no content changes)', 'ai-post-scheduler'),
			'apply'  => __('Insert confident links automatically, queue the rest for review', 'ai-post-scheduler'),
		);
		?>
		<fieldset>
			<?php foreach ($modes as $value => $label) : ?>
				<label class="aips-checkbox-label-block">
					<input type="radio" name="aips_publish_linking_mode" value="<?php echo esc_attr($value); ?>" <?php checked($mode, $value); ?>>
					<?php echo esc_html($label); ?>
				</label>
			<?php endforeach; ?>
			<label for="aips_publish_linking_outbound" class="aips-checkbox-label-block">
				<input type="checkbox" name="aips_publish_linking_outbound" id="aips_publish_linking_outbound" value="1" <?php checked($outbound); ?>>
				<?php esc_html_e('Also suggest links from the new post to related posts (Internal Links page)', 'ai-post-scheduler'); ?>
			</label>
			<p class="description"><?php esc_html_e('When a post generated by AI Post Scheduler is published, older related posts get links to it, about two minutes later in the background. "Insert automatically" uses the auto-apply threshold and caps above. Each publish appears in the Link Report run history, where its links can be undone at once.', 'ai-post-scheduler'); ?></p>
		</fieldset>
		<?php
	}

	/**
	 * Render the link click tracking toggle and retention (Internal Linking tab).
	 *
	 * @return void
	 */
	public function link_click_tracking_field_callback() {
		$config    = AIPS_Config::get_instance();
		$enabled   = (bool) $config->get_option('aips_link_click_tracking_enabled', false);
		$retention = (int) $config->get_option('aips_link_click_retention_days', 365);
		?>
		<fieldset>
			<label for="aips_link_click_tracking_enabled">
				<input type="checkbox" name="aips_link_click_tracking_enabled" id="aips_link_click_tracking_enabled" value="1" <?php checked($enabled); ?>>
				<strong><?php esc_html_e('Count clicks on internal links in post content', 'ai-post-scheduler'); ?></strong>
			</label>
			<p>
				<label for="aips_link_click_retention_days"><?php esc_html_e('Keep click counts for (days):', 'ai-post-scheduler'); ?></label>
				<input type="number" min="30" max="730" step="1" name="aips_link_click_retention_days" id="aips_link_click_retention_days" value="<?php echo esc_attr((string) $retention); ?>" class="small-text">
			</p>
			<p class="description"><?php esc_html_e('Adds a small script to single posts that reports which internal link was clicked. Only a daily count per link is stored: no IP addresses, cookies or visitor IDs. Clicks by logged-in editors and known bots are ignored. Results appear in Content → Link Report.', 'ai-post-scheduler'); ?></p>
		</fieldset>
		<?php
	}

	/**
	 * Render the silo "In this guide" list settings (Internal Linking tab).
	 *
	 * @return void
	 */
	public function silo_guide_field_callback() {
		$config   = AIPS_Config::get_instance();
		$enabled  = (bool) $config->get_option('aips_silo_guide_enabled', true);
		$position = (string) $config->get_option('aips_silo_guide_position', 'end');
		$max      = (int) $config->get_option('aips_silo_guide_max', 10);
		$style    = (string) $config->get_option('aips_silo_guide_style', 'aips');
		$heading  = (string) $config->get_option('aips_silo_guide_heading', '');
		?>
		<fieldset>
			<label for="aips_silo_guide_enabled">
				<input type="checkbox" name="aips_silo_guide_enabled" id="aips_silo_guide_enabled" value="1" <?php checked($enabled); ?>>
				<strong><?php esc_html_e('Show an "In this guide" list of its articles on each silo pillar', 'ai-post-scheduler'); ?></strong>
			</label>
			<p>
				<label for="aips_silo_guide_heading"><?php esc_html_e('Heading:', 'ai-post-scheduler'); ?></label>
				<input type="text" name="aips_silo_guide_heading" id="aips_silo_guide_heading" value="<?php echo esc_attr($heading); ?>" placeholder="<?php esc_attr_e('In this guide', 'ai-post-scheduler'); ?>" class="regular-text">
			</p>
			<p>
				<label for="aips_silo_guide_position"><?php esc_html_e('Place it:', 'ai-post-scheduler'); ?></label>
				<select name="aips_silo_guide_position" id="aips_silo_guide_position">
					<option value="end" <?php selected($position, 'end'); ?>><?php esc_html_e('At the end of the post', 'ai-post-scheduler'); ?></option>
					<option value="after_first_paragraph" <?php selected($position, 'after_first_paragraph'); ?>><?php esc_html_e('After the first paragraph', 'ai-post-scheduler'); ?></option>
				</select>
			</p>
			<p>
				<label for="aips_silo_guide_max"><?php esc_html_e('List up to', 'ai-post-scheduler'); ?></label>
				<input type="number" min="1" max="50" step="1" name="aips_silo_guide_max" id="aips_silo_guide_max" value="<?php echo esc_attr((string) $max); ?>" class="small-text">
				<?php esc_html_e('articles, closest to the pillar first', 'ai-post-scheduler'); ?>
			</p>
			<p>
				<label for="aips_silo_guide_style"><?php esc_html_e('Style:', 'ai-post-scheduler'); ?></label>
				<select name="aips_silo_guide_style" id="aips_silo_guide_style">
					<option value="aips" <?php selected($style, 'aips'); ?>><?php esc_html_e('AI Post Scheduler box (a light bordered box in your theme\'s colours)', 'ai-post-scheduler'); ?></option>
					<option value="theme" <?php selected($style, 'theme'); ?>><?php esc_html_e('Plain HTML (your theme styles the heading and list)', 'ai-post-scheduler'); ?></option>
				</select>
			</p>
			<p class="description">
				<?php
				printf(
					/* translators: %s: shortcode */
					esc_html__('The list is added when the pillar is displayed; the pillar\'s content is never edited, and the list updates itself as articles join or leave the silo. Articles the pillar already links to in its text are left out. To place it yourself, put %s in the pillar. Silos are managed in Content → Silos.', 'ai-post-scheduler'),
					'<code>[aips_silo_guide]</code>'
				);
				?>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * Render the link index rebuild throttle (Internal Linking tab).
	 *
	 * @return void
	 */
	public function link_index_throttle_field_callback() {
		$config     = AIPS_Config::get_instance();
		$batch_size = (int) $config->get_option('aips_link_index_batch_size', 50);
		$delay      = (int) $config->get_option('aips_link_index_batch_delay', 20);
		?>
		<fieldset class="aips-link-index-throttle">
			<div class="aips-rate-limits-grid">
				<div class="aips-rate-limit-field">
					<label for="aips_link_index_batch_size"><?php esc_html_e('Posts per batch:', 'ai-post-scheduler'); ?></label>
					<input type="number" min="10" max="500" step="10" name="aips_link_index_batch_size" id="aips_link_index_batch_size" value="<?php echo esc_attr((string) $batch_size); ?>" class="small-text">
				</div>
				<div class="aips-rate-limit-field">
					<label for="aips_link_index_batch_delay"><?php esc_html_e('Pause between batches:', 'ai-post-scheduler'); ?></label>
					<input type="number" min="0" max="600" step="5" name="aips_link_index_batch_delay" id="aips_link_index_batch_delay" value="<?php echo esc_attr((string) $delay); ?>" class="small-text">
					<span class="description"><?php esc_html_e('seconds', 'ai-post-scheduler'); ?></span>
				</div>
			</div>
			<p class="description"><?php esc_html_e('A full rebuild runs in the background as WP-Cron batches. Each batch only parses post HTML (no AI calls); lower the batch size or raise the pause on slow or shared hosting.', 'ai-post-scheduler'); ?></p>
		</fieldset>
		<?php
	}

	/**
	 * Render the bulk auto-linking toggle (Internal Linking tab).
	 *
	 * @return void
	 */
	public function autolink_enabled_field_callback() {
		$enabled = (bool) AIPS_Config::get_instance()->get_option('aips_autolink_enabled', false);
		?>
		<label for="aips_autolink_enabled">
			<input type="checkbox" name="aips_autolink_enabled" id="aips_autolink_enabled" value="1" <?php checked($enabled); ?>>
			<strong><?php esc_html_e('Allow bulk auto-link runs to insert internal links automatically', 'ai-post-scheduler'); ?></strong>
		</label>
		<p class="description">
			<?php esc_html_e('Links at or above the auto-apply threshold are inserted; links between the review and auto-apply thresholds wait in a review queue. Every insertion can be undone individually or per run.', 'ai-post-scheduler'); ?>
		</p>
		<?php
	}

	/**
	 * Render the auto-apply and review confidence thresholds (Card 7).
	 *
	 * @return void
	 */
	public function autolink_thresholds_field_callback() {
		$config = AIPS_Config::get_instance();
		$auto   = (float) $config->get_option('aips_autolink_auto_apply_threshold', 0.85);
		$review = (float) $config->get_option('aips_autolink_review_threshold', 0.70);
		?>
		<fieldset class="aips-autolink-thresholds">
			<div class="aips-rate-limits-grid">
				<div class="aips-rate-limit-field">
					<label for="aips_autolink_auto_apply_threshold"><?php esc_html_e('Auto-apply at:', 'ai-post-scheduler'); ?></label>
					<input type="number" min="0.5" max="1" step="0.01" name="aips_autolink_auto_apply_threshold" id="aips_autolink_auto_apply_threshold" value="<?php echo esc_attr((string) $auto); ?>" class="small-text">
				</div>
				<div class="aips-rate-limit-field">
					<label for="aips_autolink_review_threshold"><?php esc_html_e('Send to review at:', 'ai-post-scheduler'); ?></label>
					<input type="number" min="0.5" max="1" step="0.01" name="aips_autolink_review_threshold" id="aips_autolink_review_threshold" value="<?php echo esc_attr((string) $review); ?>" class="small-text">
				</div>
			</div>
			<p class="description"><?php esc_html_e('Confidence combines semantic similarity with anchor-text quality (0.50 - 1.00). Suggestions below the review threshold are skipped.', 'ai-post-scheduler'); ?></p>
		</fieldset>
		<?php
	}

	/**
	 * Render the per-post and per-target link caps (Card 7).
	 *
	 * @return void
	 */
	public function autolink_limits_field_callback() {
		$config = AIPS_Config::get_instance();
		$fields = array(
			'aips_autolink_max_links_per_post'          => array((int) $config->get_option('aips_autolink_max_links_per_post', 3), __('New links per post, per run:', 'ai-post-scheduler')),
			'aips_autolink_max_total_internal_per_post' => array((int) $config->get_option('aips_autolink_max_total_internal_per_post', 15), __('Max internal links in a post:', 'ai-post-scheduler')),
			'aips_autolink_max_inbound_per_target'      => array((int) $config->get_option('aips_autolink_max_inbound_per_target', 5), __('New inbound links per target, per run:', 'ai-post-scheduler')),
		);
		?>
		<fieldset class="aips-autolink-limits">
			<div class="aips-rate-limits-grid">
				<?php foreach ($fields as $name => $field) : ?>
					<div class="aips-rate-limit-field">
						<label for="<?php echo esc_attr($name); ?>"><?php echo esc_html($field[1]); ?></label>
						<input type="number" min="1" max="100" step="1" name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr((string) $field[0]); ?>" class="small-text">
					</div>
				<?php endforeach; ?>
			</div>
			<p class="description"><?php esc_html_e('Posts already at the internal link cap never receive automatic links.', 'ai-post-scheduler'); ?></p>
		</fieldset>
		<?php
	}

	/**
	 * Render placement and link attribute options (Card 7).
	 *
	 * @return void
	 */
	public function autolink_placement_field_callback() {
		$config     = AIPS_Config::get_instance();
		$skip_first = (bool) $config->get_option('aips_autolink_skip_first_paragraph', true);
		$rel        = (string) $config->get_option('aips_autolink_rel', '');
		$new_tab    = (bool) $config->get_option('aips_autolink_target_blank', false);
		$rel_labels = array(
			''          => __('None (recommended for internal links)', 'ai-post-scheduler'),
			'nofollow'  => 'nofollow',
			'sponsored' => 'sponsored',
			'ugc'       => 'ugc',
		);
		?>
		<fieldset class="aips-autolink-placement">
			<label for="aips_autolink_skip_first_paragraph">
				<input type="checkbox" name="aips_autolink_skip_first_paragraph" id="aips_autolink_skip_first_paragraph" value="1" <?php checked($skip_first); ?>>
				<?php esc_html_e('Never link inside the first paragraph', 'ai-post-scheduler'); ?>
			</label>
			<br>
			<label for="aips_autolink_target_blank">
				<input type="checkbox" name="aips_autolink_target_blank" id="aips_autolink_target_blank" value="1" <?php checked($new_tab); ?>>
				<?php esc_html_e('Open auto-inserted links in a new tab', 'ai-post-scheduler'); ?>
			</label>
			<p>
				<label for="aips_autolink_rel"><?php esc_html_e('rel attribute:', 'ai-post-scheduler'); ?></label>
				<select name="aips_autolink_rel" id="aips_autolink_rel">
					<?php foreach ($rel_labels as $value => $label) : ?>
						<option value="<?php echo esc_attr($value); ?>" <?php selected($rel, $value); ?>><?php echo esc_html($label); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p class="description"><?php esc_html_e('Links are never placed inside headings, existing links, code, buttons, shortcodes or HTML blocks.', 'ai-post-scheduler'); ?></p>
		</fieldset>
		<?php
	}

	/**
	 * Render related posts similarity threshold setting (Card 4).
	 *
	 * @return void
	 */
	public function indexer_similarity_threshold_field_callback() {
		$value = (float) AIPS_Config::get_instance()->get_option('aips_indexer_similarity_threshold', 0.65);
		?>
		<input type="number" step="0.05" min="0.40" max="0.95" name="aips_indexer_similarity_threshold" id="aips_indexer_similarity_threshold" value="<?php echo esc_attr((string) $value); ?>" class="small-text">
		<p class="description"><?php esc_html_e('Minimum cosine similarity (0.40 - 0.95) for two posts to be considered related. Default: 0.65', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render publish indexing execution timing mode (Card 4).
	 *
	 * @return void
	 */
	public function indexer_publish_execution_timing_field_callback() {
		$value = (string) AIPS_Config::get_instance()->get_option('aips_indexer_publish_execution_timing', 'queued');
		?>
		<fieldset>
			<label style="display:block;margin-bottom:6px;">
				<input type="radio" name="aips_indexer_publish_execution_timing" value="queued" <?php checked($value, 'queued'); ?>>
				<strong><?php esc_html_e('Debounced Background Batch Queue (Recommended)', 'ai-post-scheduler'); ?></strong>
				<span class="description" style="display:block;margin-left:22px;"><?php esc_html_e('Buffers posts when published and processes them in rate-limited background batches via WP-Cron.', 'ai-post-scheduler'); ?></span>
			</label>
			<label style="display:block;margin-bottom:6px;">
				<input type="radio" name="aips_indexer_publish_execution_timing" value="immediate" <?php checked($value, 'immediate'); ?>>
				<strong><?php esc_html_e('Immediate (Synchronous on Publish)', 'ai-post-scheduler'); ?></strong>
				<span class="description" style="display:block;margin-left:22px;"><?php esc_html_e('Generates vector embeddings and relationships immediately upon post publishing. May slightly slow down post saving.', 'ai-post-scheduler'); ?></span>
			</label>
			<label style="display:block;">
				<input type="radio" name="aips_indexer_publish_execution_timing" value="disabled" <?php checked($value, 'disabled'); ?>>
				<strong><?php esc_html_e('Disabled', 'ai-post-scheduler'); ?></strong>
				<span class="description" style="display:block;margin-left:22px;"><?php esc_html_e('Do not vectorize posts on publish. Indexing must be initiated manually from Content Indexer.', 'ai-post-scheduler'); ?></span>
			</label>
		</fieldset>
		<?php
	}

	/**
	 * Render Batch Queue and Quota Pause configuration (Card 4).
	 *
	 * @return void
	 */
	public function indexer_batch_config_field_callback() {
		$config        = AIPS_Config::get_instance();
		$batch_size    = (int) $config->get_option('aips_indexer_batch_size', 10);
		$debounce_sec  = (int) $config->get_option('aips_indexer_queue_debounce_seconds', 15);
		$quota_pause   = (bool) $config->get_option('aips_indexer_quota_pause_enabled', true);
		$notifications = (bool) $config->get_option('aips_indexer_queue_notifications_enabled', true);
		?>
		<div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start;margin-bottom:12px;">
			<div>
				<label for="aips_indexer_batch_size"><strong><?php esc_html_e('Batch Size:', 'ai-post-scheduler'); ?></strong></label><br>
				<input type="number" min="1" max="50" step="1" name="aips_indexer_batch_size" id="aips_indexer_batch_size" value="<?php echo esc_attr((string) $batch_size); ?>" class="small-text">
				<p class="description"><?php esc_html_e('Posts per chunk (1-50).', 'ai-post-scheduler'); ?></p>
			</div>
			<div>
				<label for="aips_indexer_queue_debounce_seconds"><strong><?php esc_html_e('Debounce Delay:', 'ai-post-scheduler'); ?></strong></label><br>
				<input type="number" min="5" max="300" step="5" name="aips_indexer_queue_debounce_seconds" id="aips_indexer_queue_debounce_seconds" value="<?php echo esc_attr((string) $debounce_sec); ?>" class="small-text">
				<p class="description"><?php esc_html_e('Seconds before processing queue.', 'ai-post-scheduler'); ?></p>
			</div>
		</div>
		<div>
			<label for="aips_indexer_quota_pause_enabled" style="display:block;margin-bottom:6px;">
				<input type="checkbox" name="aips_indexer_quota_pause_enabled" id="aips_indexer_quota_pause_enabled" value="1" <?php checked($quota_pause); ?>>
				<?php esc_html_e('Auto-Pause indexing queue when approaching rate limits or remote quota', 'ai-post-scheduler'); ?>
			</label>
			<label for="aips_indexer_queue_notifications_enabled" style="display:block;">
				<input type="checkbox" name="aips_indexer_queue_notifications_enabled" id="aips_indexer_queue_notifications_enabled" value="1" <?php checked($notifications); ?>>
				<?php esc_html_e('Notify admin via email when background indexing queue is paused or exhausts quota', 'ai-post-scheduler'); ?>
			</label>
		</div>
		<?php
	}

	/**
	 * Render Rate Limit Error Pattern Auto-Cooldown fields (Card 4).
	 *
	 * @return void
	 */
	public function indexer_error_cooldown_field_callback() {
		$config    = AIPS_Config::get_instance();
		$duration  = (int) $config->get_option('aips_indexer_error_pause_duration', 30);
		$unit      = (string) $config->get_option('aips_indexer_error_pause_unit', 'minutes');
		$threshold = (int) $config->get_option('aips_indexer_consecutive_error_threshold', 2);
		?>
		<div style="display:flex;gap:20px;flex-wrap:wrap;align-items:center;">
			<div>
				<label for="aips_indexer_error_pause_duration"><strong><?php esc_html_e('Cooldown Duration:', 'ai-post-scheduler'); ?></strong></label><br>
				<input type="number" min="1" max="100" step="1" name="aips_indexer_error_pause_duration" id="aips_indexer_error_pause_duration" value="<?php echo esc_attr((string) $duration); ?>" class="small-text">
				<select name="aips_indexer_error_pause_unit" id="aips_indexer_error_pause_unit">
					<option value="minutes" <?php selected($unit, 'minutes'); ?>><?php esc_html_e('Minutes', 'ai-post-scheduler'); ?></option>
					<option value="hours" <?php selected($unit, 'hours'); ?>><?php esc_html_e('Hours', 'ai-post-scheduler'); ?></option>
					<option value="days" <?php selected($unit, 'days'); ?>><?php esc_html_e('Days', 'ai-post-scheduler'); ?></option>
				</select>
			</div>
			<div>
				<label for="aips_indexer_consecutive_error_threshold"><strong><?php esc_html_e('Consecutive Errors to Trigger:', 'ai-post-scheduler'); ?></strong></label><br>
				<input type="number" min="1" max="10" step="1" name="aips_indexer_consecutive_error_threshold" id="aips_indexer_consecutive_error_threshold" value="<?php echo esc_attr((string) $threshold); ?>" class="small-text">
				<span class="description"><?php esc_html_e('failures', 'ai-post-scheduler'); ?></span>
			</div>
		</div>
		<p class="description" style="margin-top:8px;">
			<?php esc_html_e('When remote provider returns HTTP 429 ("Resource exhausted" / "Quota exceeded"), indexing halts automatically for the configured duration.', 'ai-post-scheduler'); ?>
		</p>
		<?php
	}

	/**
	 * Render Post Cluster similarity threshold setting (Card 4).
	 *
	 * @return void
	 */
	public function indexer_post_cluster_threshold_field_callback() {
		$value = (float) AIPS_Config::get_instance()->get_option('aips_indexer_post_cluster_threshold', 0.65);
		?>
		<input type="number" step="0.05" min="0.30" max="0.95" name="aips_indexer_post_cluster_threshold" id="aips_indexer_post_cluster_threshold" value="<?php echo esc_attr((string) $value); ?>" class="small-text">
		<p class="description"><?php esc_html_e('Minimum similarity threshold for grouping connected posts into a thematic cluster. Default: 0.65', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render Enable Post Insights UI setting (Card 4).
	 *
	 * @return void
	 */
	public function enable_post_insights_ui_field_callback() {
		$value = (bool) AIPS_Config::get_instance()->get_option('aips_enable_post_insights_ui', true);
		?>
		<label for="aips_enable_post_insights_ui">
			<input type="checkbox" name="aips_enable_post_insights_ui" id="aips_enable_post_insights_ui" value="1" <?php checked($value, true); ?>>
			<?php esc_html_e('Inject AI Insights column, duplication risk badges, and editor panels on native WordPress Posts and Editor screens.', 'ai-post-scheduler'); ?>
		</label>
		<p class="description"><?php esc_html_e('When enabled, adds the AI Insights column and filters to the Posts table, a Document Setting Panel to the Block Editor, and a Meta Box to the Classic Editor.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render Run Embeddings when Topics are Generated setting.
	 *
	 * @return void
	 */
	public function indexer_topics_continuous_sync_field_callback() {
		$value = (bool) AIPS_Config::get_instance()->get_option('aips_indexer_topics_continuous_sync', true);
		?>
		<label for="aips_indexer_topics_continuous_sync">
			<input type="checkbox" name="aips_indexer_topics_continuous_sync" id="aips_indexer_topics_continuous_sync" value="1" <?php checked($value, true); ?>>
			<?php esc_html_e('Automatically generate and persist vector embeddings immediately when Author Topics are created or generated.', 'ai-post-scheduler'); ?>
		</label>
		<p class="description"><?php esc_html_e('Enables real-time duplicate idea detection and cross-referencing between Author Topics and published WordPress articles.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render Global Author Topic Auto-Approval Mode.
	 *
	 * @return void
	 */
	public function author_topic_auto_approval_mode_field_callback() {
		$value = (string) AIPS_Config::get_instance()->get_option('aips_author_topic_auto_approval_mode', 'similarity');
		?>
		<select name="aips_author_topic_auto_approval_mode" id="aips_author_topic_auto_approval_mode">
			<option value="similarity" <?php selected($value, 'similarity'); ?>><?php esc_html_e('Dual-Boundary Semantic Gate (Vector Similarity)', 'ai-post-scheduler'); ?></option>
			<option value="manual" <?php selected($value, 'manual'); ?>><?php esc_html_e('Manual Review (Hold in Pending)', 'ai-post-scheduler'); ?></option>
			<option value="all" <?php selected($value, 'all'); ?>><?php esc_html_e('Auto-Approve All (Unfiltered)', 'ai-post-scheduler'); ?></option>
		</select>
		<p class="description"><?php esc_html_e('Default auto-approval behavior for generated topics across all authors inheriting global policy.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render Global Author Topic Minimum Niche Relevance %.
	 *
	 * @return void
	 */
	public function author_topic_auto_approval_min_score_field_callback() {
		$value = (float) AIPS_Config::get_instance()->get_option('aips_author_topic_auto_approval_min_score', 70);
		?>
		<input type="number" min="0" max="100" step="1" name="aips_author_topic_auto_approval_min_score" id="aips_author_topic_auto_approval_min_score" value="<?php echo esc_attr((string) $value); ?>" class="small-text"> %
		<p class="description"><?php esc_html_e('Minimum cosine relevance percentage against the author persona and focus niche. Topics below this threshold fail approval.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render Global Author Topic Maximum Duplicate Ceiling.
	 *
	 * @return void
	 */
	public function author_topic_auto_approval_max_similarity_field_callback() {
		$value = (float) AIPS_Config::get_instance()->get_option('aips_author_topic_auto_approval_max_similarity', 0.85);
		?>
		<input type="number" min="0.50" max="0.99" step="0.01" name="aips_author_topic_auto_approval_max_similarity" id="aips_author_topic_auto_approval_max_similarity" value="<?php echo esc_attr((string) $value); ?>" class="small-text">
		<p class="description"><?php esc_html_e('Maximum cosine similarity allowed against existing topics and published articles. Topics at or above this score are flagged as duplicates.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render Global Author Topic Sub-Threshold Fallback Handling.
	 *
	 * @return void
	 */
	public function author_topic_auto_approval_fallback_field_callback() {
		$value = (string) AIPS_Config::get_instance()->get_option('aips_author_topic_auto_approval_fallback', 'reject');
		?>
		<select name="aips_author_topic_auto_approval_fallback" id="aips_author_topic_auto_approval_fallback">
			<option value="reject" <?php selected($value, 'reject'); ?>><?php esc_html_e('Immediate Rejection (Move to Rejected tab)', 'ai-post-scheduler'); ?></option>
			<option value="smart_split" <?php selected($value, 'smart_split'); ?>><?php esc_html_e('Smart Split (Keep relevant near-duplicates in Pending for manual review)', 'ai-post-scheduler'); ?></option>
		</select>
		<p class="description"><?php esc_html_e('Determines whether non-qualifying topics are rejected outright or held in Pending when they are on-niche but close to the duplicate threshold.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render related posts enable toggle (Card 5).
	 *
	 * @return void
	 */
	public function related_posts_enabled_field_callback() {
		$value = (bool) AIPS_Config::get_instance()->get_option('aips_related_posts_enabled', true);
		?>
		<label for="aips_related_posts_enabled">
			<input type="checkbox" name="aips_related_posts_enabled" id="aips_related_posts_enabled" value="1" <?php checked($value); ?>>
			<?php esc_html_e('Enable semantic recommendations via vector similarity', 'ai-post-scheduler'); ?>
		</label>
		<?php
	}

	/**
	 * Render related posts auto-append toggle (Card 5).
	 *
	 * @return void
	 */
	public function related_posts_auto_append_field_callback() {
		$value = (bool) AIPS_Config::get_instance()->get_option('aips_related_posts_auto_append', false);
		?>
		<label for="aips_related_posts_auto_append">
			<input type="checkbox" name="aips_related_posts_auto_append" id="aips_related_posts_auto_append" value="1" <?php checked($value); ?>>
			<?php esc_html_e('Automatically render related articles below single post content', 'ai-post-scheduler'); ?>
		</label>
		<?php
	}

	/**
	 * Render related posts heading title (Card 5).
	 *
	 * @return void
	 */
	public function related_posts_heading_field_callback() {
		$value = (string) AIPS_Config::get_instance()->get_option('aips_related_posts_heading', 'Related Articles');
		?>
		<input type="text" name="aips_related_posts_heading" id="aips_related_posts_heading" value="<?php echo esc_attr($value); ?>" class="regular-text">
		<?php
	}

	/**
	 * Render related posts count and layout options (Card 5).
	 *
	 * @return void
	 */
	public function related_posts_count_layout_field_callback() {
		$count  = (int) AIPS_Config::get_instance()->get_option('aips_related_posts_count', 4);
		$layout = (string) AIPS_Config::get_instance()->get_option('aips_related_posts_layout', 'grid');
		?>
		<input type="number" min="1" max="12" name="aips_related_posts_count" id="aips_related_posts_count" value="<?php echo esc_attr((string) $count); ?>" class="small-text"> <?php esc_html_e('articles', 'ai-post-scheduler'); ?>
		<select name="aips_related_posts_layout" id="aips_related_posts_layout" class="aips-select-inline-gap">
			<option value="grid" <?php selected($layout, 'grid'); ?>><?php esc_html_e('Card Grid', 'ai-post-scheduler'); ?></option>
			<option value="list" <?php selected($layout, 'list'); ?>><?php esc_html_e('List Layout', 'ai-post-scheduler'); ?></option>
		</select>
		<?php
	}

	/**
	 * Render related posts shortcode and block integration helper with copy button (Card 5).
	 *
	 * @return void
	 */
	public function related_posts_shortcode_field_callback() {
		?>
		<div class="aips-shortcode-preview-card">
			<div class="aips-shortcode-code-wrap">
				<code class="aips-shortcode-display" id="aips-related-posts-shortcode">[aips_related_posts]</code>
				<button type="button" class="aips-btn aips-btn-sm aips-btn-secondary aips-copy-btn" id="aips-copy-shortcode-btn" data-clipboard-text="[aips_related_posts]" title="<?php esc_attr_e('Copy shortcode to clipboard', 'ai-post-scheduler'); ?>">
					<span class="dashicons dashicons-clipboard"></span>
					<span class="aips-copy-text"><?php esc_html_e('Copy Shortcode', 'ai-post-scheduler'); ?></span>
				</button>
			</div>
			<p class="description">
				<?php esc_html_e('Place this shortcode anywhere in your content, page builders (Elementor, Divi, Beaver Builder), or widget templates to insert semantic related recommendations.', 'ai-post-scheduler'); ?>
			</p>
			<div class="aips-shortcode-attributes-hint">
				<span class="aips-hint-tag"><strong><?php esc_html_e('Gutenberg Block:', 'ai-post-scheduler'); ?></strong> <code>/Related Posts (AIPS)</code></span>
				<span class="aips-hint-tag"><strong><?php esc_html_e('Attributes:', 'ai-post-scheduler'); ?></strong> <code>count="4"</code>, <code>heading="Related Articles"</code>, <code>layout="grid|list"</code></span>
			</div>
		</div>
		<?php
	}

	/**
	 * Render deduplication gatekeeper action select (Card 6).
	 *
	 * @return void
	 */
	public function deduplication_mode_field_callback() {
		$value = (string) AIPS_Config::get_instance()->get_option('aips_deduplication_mode', 'warn');
		?>
		<select name="aips_deduplication_mode" id="aips_deduplication_mode">
			<option value="warn" <?php selected($value, 'warn'); ?>><?php esc_html_e('Warn & Flag (Lowers Score & shows Duplicate Badge)', 'ai-post-scheduler'); ?></option>
			<option value="block" <?php selected($value, 'block'); ?>><?php esc_html_e('Strict Block (Skip automated generation if duplicate exists)', 'ai-post-scheduler'); ?></option>
		</select>
		<?php
	}

	/**
	 * Render deduplication similarity threshold number input (Card 6).
	 *
	 * @return void
	 */
	public function deduplication_threshold_field_callback() {
		$value = (float) AIPS_Config::get_instance()->get_option('aips_deduplication_threshold', 0.85);
		?>
		<input type="number" step="0.05" min="0.70" max="0.99" name="aips_deduplication_threshold" id="aips_deduplication_threshold" value="<?php echo esc_attr((string) $value); ?>" class="small-text">
		<p class="description"><?php esc_html_e('Cosine similarity threshold to classify a topic or post as a duplicate candidate. Default: 0.85', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Sanitize indexing scope option.
	 *
	 * @param mixed $value Submitted value.
	 * @return string Sanitized scope ('aips_only', 'date_range', 'all').
	 */
	public function sanitize_embeddings_scope($value) {
		$value = sanitize_key((string) $value);
		return in_array($value, array('aips_only', 'date_range', 'all'), true) ? $value : 'aips_only';
	}

	/**
	 * Sanitize related posts layout option.
	 *
	 * @param mixed $value Submitted value.
	 * @return string ('grid', 'list').
	 */
	public function sanitize_related_posts_layout($value) {
		$value = sanitize_key((string) $value);
		return in_array($value, array('grid', 'list'), true) ? $value : 'grid';
	}

	/**
	 * Sanitize deduplication mode option.
	 *
	 * @param mixed $value Submitted value.
	 * @return string ('warn', 'block').
	 */
	public function sanitize_deduplication_mode($value) {
		$value = sanitize_key((string) $value);
		return in_array($value, array('warn', 'block'), true) ? $value : 'warn';
	}

	/**
	 * Sanitize post types array.
	 *
	 * @param mixed $value Submitted value.
	 * @return array<string>
	 */
	public function sanitize_post_types($value) {
		if (!is_array($value)) {
			return array('post');
		}
		$sanitized = array();
		foreach ($value as $pt) {
			if (is_scalar($pt)) {
				$pt = sanitize_key((string) $pt);
				if (!empty($pt)) {
					$sanitized[] = $pt;
				}
			}
		}
		return !empty($sanitized) ? array_values(array_unique($sanitized)) : array('post');
	}

	/**
	 * Sanitize publish execution timing.
	 *
	 * @param mixed $value Submitted value.
	 * @return string ('queued', 'immediate', 'disabled').
	 */
	public function sanitize_publish_execution_timing($value) {
		$value = sanitize_key((string) $value);
		return in_array($value, array('queued', 'immediate', 'disabled'), true) ? $value : 'queued';
	}

	/**
	 * Sanitize error pause unit.
	 *
	 * @param mixed $value Submitted value.
	 * @return string ('minutes', 'hours', 'days').
	 */
	public function sanitize_error_pause_unit($value) {
		$value = sanitize_key((string) $value);
		return in_array($value, array('minutes', 'hours', 'days'), true) ? $value : 'minutes';
	}

	/**
	 * Sanitize author topic auto approval mode.
	 *
	 * @param mixed $value Submitted value.
	 * @return string ('similarity', 'manual', 'all').
	 */
	public function sanitize_author_topic_auto_approval_mode($value) {
		$value = sanitize_key((string) $value);
		return in_array($value, array('similarity', 'manual', 'all'), true) ? $value : 'similarity';
	}

	/**
	 * Sanitize author topic auto approval fallback.
	 *
	 * @param mixed $value Submitted value.
	 * @return string ('reject', 'smart_split').
	 */
	public function sanitize_author_topic_auto_approval_fallback($value) {
		$value = sanitize_key((string) $value);
		return in_array($value, array('reject', 'smart_split'), true) ? $value : 'reject';
	}

}

