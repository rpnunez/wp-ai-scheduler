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
	 * Magic fallback for retired field and section callbacks.
	 *
	 * Delegates to AIPS_Settings_Form_Renderer or AIPS_Settings_Manager
	 * ensuring backward compatibility for third-party or test callers.
	 *
	 * @param string $name Method name.
	 * @param array  $arguments Method arguments.
	 * @return void
	 */
	public function __call( $name, $arguments ) {
		if ( str_ends_with( $name, '_field_callback' ) ) {
			$raw_key  = substr( $name, 0, -strlen( '_field_callback' ) );
			$registry = AIPS_Settings_Registry::instance();
			$key      = $raw_key;
			if ( ! $registry->has_definition( $key ) && $registry->has_definition( 'aips_' . $key ) ) {
				$key = 'aips_' . $key;
			}
			$def = $registry->get_definition( $key );
			if ( $def ) {
				$renderer = new AIPS_Settings_Form_Renderer();
				$renderer->render_field( $def );
				return;
			}
		}

		if ( str_ends_with( $name, '_section_callback' ) ) {
			return;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			trigger_error( "Call to undefined method AIPS_Settings_UI::{$name}()", E_USER_WARNING );
		}
	}


	/**
	 * Magic fallback for retired field callbacks.
	 *
	 * Delegates to AIPS_Settings_Form_Renderer or AIPS_Settings_Manager
	 * ensuring backward compatibility for third-party or test callers.
	 *
	 * @param string $name Method name.
	 * @param array  $arguments Method arguments.
	 * @return void
	 */

	/** Retention is disabled at zero; never convert a negative value into deletion. */

	public function sanitize_source_archive_retention_days( $value ) {
		return max( 0, min( 3650, (int) $value ) );
	}

	/**
	 * Render the per-component inline generation retries setting fields.
	 */
	public function component_generation_retries_field_callback() {
		$values = AIPS_Config::get_instance()->get_component_generation_retries();
		$fields = array(
			'post_content'        => array(
				'label' => __('Post Content', 'ai-post-scheduler'),
				'help'  => __('Retries when the main article body fails or returns empty.', 'ai-post-scheduler'),
			),
			'post_title'          => array(
				'label' => __('Post Title', 'ai-post-scheduler'),
				'help'  => __('Retries when title generation fails or returns empty.', 'ai-post-scheduler'),
			),
			'post_excerpt'        => array(
				'label' => __('Post Excerpt', 'ai-post-scheduler'),
				'help'  => __('Retries when excerpt generation returns empty before falling back to content.', 'ai-post-scheduler'),
			),
			'post_slug'           => array(
				'label' => __('Post Slug', 'ai-post-scheduler'),
				'help'  => __('Recommended: 0 retries. If slug generation is disabled or the AI model does not return a custom slug, WordPress will automatically generate a clean URL slug from the Post Title.', 'ai-post-scheduler'),
			),
			'post_featured_image' => array(
				'label' => __('Featured Image', 'ai-post-scheduler'),
				'help'  => __('Retries when featured image generation encounters a transient error.', 'ai-post-scheduler'),
			),
			'post_images'         => array(
				'label' => __('Post Content Images', 'ai-post-scheduler'),
				'help'  => __('When multiple content images are configured, prompts or image API generations may encounter partial deficits or transient timeouts. Setting retries to 2–5 allows automatic recovery before finishing the post.', 'ai-post-scheduler'),
			),
			'post_meta'           => array(
				'label' => __('Combined Metadata Turn', 'ai-post-scheduler'),
				'help'  => __('Retries when the combined metadata JSON turn returns unparseable output.', 'ai-post-scheduler'),
			),
		);
		?>
		<div class="aips-component-retries-grid">
			<?php foreach ($fields as $key => $meta) : ?>
				<?php $limit = isset($values[$key]) ? (int) $values[$key] : 0; ?>
				<div class="aips-component-retry-row">
					<label for="aips_component_retry_<?php echo esc_attr($key); ?>" class="aips-component-retry-label">
						<span><?php echo esc_html($meta['label']); ?></span>
						<input
							type="number"
							id="aips_component_retry_<?php echo esc_attr($key); ?>"
							name="aips_component_generation_retries[<?php echo esc_attr($key); ?>]"
							value="<?php echo esc_attr($limit); ?>"
							min="0"
							max="5"
							class="small-text"
						>
					</label>
					<p class="description aips-component-retry-desc"><?php echo esc_html($meta['help']); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
		<p class="description"><?php esc_html_e('Maximum retries per component during post generation (0–5). Exponential jitter backoff (500–1500ms) is applied between attempts.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Sanitize per-component inline generation retry limits.
	 *
	 * Clamps each known component key to an integer in the range 0..5 and discards unknown keys.
	 *
	 * @param mixed $value Submitted option value.
	 * @return array<string, int>
	 */
	public function sanitize_component_generation_retries($value) {
		$defaults = AIPS_Config::get_instance()->get_default_options();
		$default_map = isset($defaults['aips_component_generation_retries']) && is_array($defaults['aips_component_generation_retries'])
			? $defaults['aips_component_generation_retries']
			: array();

		if (!is_array($value)) {
			$value = array();
		}

		$sanitized = array();
		foreach ($default_map as $component => $default_limit) {
			if (array_key_exists($component, $value) && '' !== $value[$component] && null !== $value[$component]) {
				$sanitized[$component] = max(0, min(5, (int) $value[$component]));
			} else {
				$sanitized[$component] = max(0, min(5, (int) $default_limit));
			}
		}

		return $sanitized;
	}

    /**
     * Render the description for the general settings section.
     *
     * @return void
     */

    /**
     * Render the description for the AI settings section.
     *
     * @return void
     */

	/**
	 * Render the description for Card 1: Content Generation AI Provider.
	 *
	 * @return void
	 */

	/**
	 * Render the description for Card 2: Token Budgets & Prompt Optimization.
	 *
	 * @return void
	 */

	/**
	 * Render the description for Card 3: Vector Embeddings Engine & Model.
	 *
	 * @return void
	 */

	/**
	 * Render the description for Card 4: Indexing Scope, Continuous Sync & Rate Limits.
	 *
	 * @return void
	 */

	/**
	 * Render the description for Card 7: Internal Link Automation.
	 *
	 * @return void
	 */

	/**
	 * Render the description for the Link Index card (Internal Linking tab).
	 *
	 * @return void
	 */

	/**
	 * Render the description for the Keyword Link Rules card (Internal Linking tab).
	 *
	 * @return void
	 */

	/**
	 * Render the description for Card 5: Frontend Related Posts Engine.
	 *
	 * @return void
	 */

	/**
	 * Render the description for Card 6: Semantic Duplicate Detection & Gatekeeper Guard.
	 *
	 * @return void
	 */

	/**
	 * Render the description for the Authors settings section.
	 *
	 * @return void
	 */

	/**
	 * Render the description for the feedback settings section.
	 *
	 * @return void
	 */

    /**
     * Render the description for the API keys settings section.
     *
     * @return void
     */

    /**
     * Render the description for the developer settings section.
     *
     * @return void
     */

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
     * Render the bulk-publish background threshold field.
     */

    /**
     * Sanitize the bulk-publish background threshold: a whole number, clamped to
     * a sane range so the Pending Review publish path always behaves predictably.
     *
     * @param mixed $value Raw submitted value.
     * @return int
     */

    public function sanitize_bulk_publish_async_threshold($value) {
        $value = absint($value);
        if ($value < 2) {
            $value = 2;
        } elseif ($value > 500) {
            $value = 500;
        }
        return $value;
    }

    /**
     * Render the AI provider selection field.
     *
     * Lets the admin pick which AI backend serves requests. "Auto-detect" lets
     * AIPS_AI_Provider_Factory choose the first available provider (Meow first).
     *
     * @return void
     */

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

    /**
     * Render the AI environment ID setting field.
     *
     * Displays a text input for specifying a custom AI Engine environment ID.
     *
     * @return void
     */

	/**
	 * Render the AI generation prevention setting field.
	 *
	 * @return void
	 */

    /**
     * Render the max tokens limit setting field.
     *
     * Sets a hard upper bound on the number of tokens the plugin will ever request
     * from the AI in a single call. The dynamic token calculation will never exceed
     * this value, preventing unexpectedly large or costly requests.
     *
     * @return void
     */

    /**
     * Render the max tokens for post titles setting field.
     *
     * Controls the expected output token budget when generating post titles.
     *
     * @return void
     */

    /**
     * Render the max tokens for post excerpts setting field.
     *
     * Controls the expected output token budget when generating post excerpts.
     *
     * @return void
     */

    /**
     * Render the max tokens for post content setting field.
     *
     * Controls the expected output token budget when generating full post content.
     *
     * @return void
     */

    /**
     * Render Unsplash access key field.
     *
     * Provides a place to store the Unsplash API key required for image searches.
     *
     * @return void
     */

    /**
     * Render Post Images section description.
     */

    /**
     * Render Max Post Images Limit field.
     */

    /**
     * Render Allowed Image Sources field.
     */

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

    /**
     * Render the enable retry setting field.
     */

    /**
     * Render the max retries setting field.
     */

    /**
     * Render the retry initial delay setting field.
     */

    /**
     * Render the enable rate limiting setting field.
     */

    /**
     * Render the rate limit requests setting field.
     */

    /**
     * Render the rate limit period setting field.
     */

    /**
     * Render the generation pacing delay setting field.
     */

    /**
     * Render the batch resume cooldown setting field.
     */

    /**
     * Render the enable circuit breaker setting field.
     */

    /**
     * Render the circuit breaker threshold setting field.
     */

    /**
     * Render the circuit breaker timeout setting field.
     */

    /**
     * Render the logging enable setting field.
     *
     * Displays a checkbox to enable or disable detailed logging.
     *
     * @return void
     */

	/**
	 * Render the table filter persistence setting field.
	 *
	 * Displays a checkbox to enable or disable table filter persistence in localStorage.
	 *
	 * @return void
	 */

    /**
     * Render the conversational generation setting field.
     *
     * When enabled, post generation uses a 2-turn conversational workflow:
     * Turn 1 generates the article body, and Turn 2 requests the title, excerpt,
     * slug, and image prompt in a single combined metadata turn. This preserves context,
     * cuts API calls in half, and reduces input tokens. Automatically falls back
     * to individual conversational turns if structured parsing fails.
     *
     * @return void
     */

    /**
     * Render the combined metadata turn setting field.
     *
     * @deprecated 3.7.25 Kept for backward compatibility; conversational generation now implies the combined metadata turn.
     * @return void
     */

    /**
     * Render the developer mode setting field.
     *
     * Displays a checkbox to enable or disable developer mode.
     *
     * @return void
     */

    /**
     * Render the enable telemetry setting field.
     *
     * Displays a checkbox to enable or disable request-level telemetry
     * recording (staging/dev use only).
     *
     * @return void
     */

    /**
     * Render the cache monitor setting field.
     *
     * Displays a checkbox to enable or disable the Cache Monitor
     * admin page and its AJAX endpoints (internal cache introspection tool).
     *
     * @return void
     */

    /**
     * Render the review notifications email setting field.
     *
     * Displays an email input field for the notifications recipient.
     *
     * @return void
     */

    /**
     * Render the notifications section description.
     *
     * @return void
     */

    /**
     * Render a notification channel preference select field.
     *
     * @param array $args Field configuration.
     * @return void
     */

	/**
	 * Render the summarized digest enabled toggle field callback.
	 *
	 * @return void
	 */

	/**
	 * Render the summarized digest threshold number field callback.
	 *
	 * @return void
	 */

	public function notif_digest_threshold_field_callback() {
		$threshold = absint(AIPS_Config::get_instance()->get_option('aips_notif_digest_threshold', 5));
		if ($threshold < 2) {
			$threshold = 5;
		}
		?>
		<input type="number" id="aips_notif_digest_threshold" name="aips_notif_digest_threshold" value="<?php echo esc_attr($threshold); ?>" min="2" max="50" step="1" class="small-text">
		<p class="description"><?php esc_html_e('Number of notifications that triggers an immediate digest email (2–50).', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render the summarized digest max delay select field callback.
	 *
	 * @return void
	 */

	/**
	 * Render the summarized digest eligible notification types multi-checkbox callback.
	 *
	 * @return void
	 */

	public function notif_digest_types_field_callback() {
		$selected_types = (array) AIPS_Config::get_instance()->get_option('aips_notif_digest_types', array('post_generated', 'post_ready_for_review'));
		$eligible_types = AIPS_Notification_Registry::get_digest_eligible_types();
		?>
		<fieldset>
			<legend class="screen-reader-text"><span><?php esc_html_e('Eligible Notification Types', 'ai-post-scheduler'); ?></span></legend>
			<?php foreach ($eligible_types as $type_key => $meta) : ?>
				<label class="aips-checkbox-label-block">
					<input type="checkbox" name="aips_notif_digest_types[]" value="<?php echo esc_attr($type_key); ?>" <?php checked(in_array($type_key, $selected_types, true)); ?>>
					<strong><?php echo esc_html($meta['label']); ?></strong>
					<span class="description aips-checkbox-label-desc">&mdash; <?php echo esc_html($meta['description']); ?></span>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<p class="description"><?php esc_html_e('Select which notification types are intercepted into the digest. Critical errors and system resilience alerts are never batched.', 'ai-post-scheduler'); ?></p>
		<?php
	}

	/**
	 * Render the summarized digest manual queue flush action callback.
	 *
	 * @return void
	 */

	public function notif_digest_actions_field_callback() {
		$queue_count = AIPS_Notifications::instance()->get_digest_queue_count();
		?>
		<div class="aips-settings-action-row">
			<button type="button" id="aips-flush-digest-btn" class="button button-secondary">
				<?php esc_html_e('Send Pending Digest Now', 'ai-post-scheduler'); ?>
			</button>
			<span class="description">
				<?php
				printf(
					/* translators: %s: span element containing pending count */
					esc_html__('Currently %s pending notifications in the digest queue.', 'ai-post-scheduler'),
					'<strong id="aips-digest-queue-count">' . esc_html($queue_count) . '</strong>'
				);
				?>
			</span>
		</div>
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
	 * Sanitize the summarized digest count threshold (2–50).
	 *
	 * @param mixed $value Raw input.
	 * @return int Clamped integer between 2 and 50.
	 */

	public function sanitize_digest_threshold($value) {
		if (!is_numeric($value)) {
			return 5;
		}
		$val = intval($value);
		if ($val < 2) {
			$val = 2;
		} elseif ($val > 50) {
			$val = 50;
		}
		return $val;
	}

	/**
	 * Sanitize the summarized digest maximum delay window.
	 *
	 * @param mixed $value Raw input in seconds.
	 * @return int Valid delay in seconds (default 3600).
	 */

	public function sanitize_digest_max_delay($value) {
		$val = absint($value);
		$allowed = array(900, 1800, 3600, 7200, 21600);
		return in_array($val, $allowed, true) ? $val : 3600;
	}

	/**
	 * Sanitize the summarized digest eligible notification types.
	 *
	 * @param mixed $value Raw input array of type keys.
	 * @return array Sanitized list of eligible notification type keys.
	 */

	public function sanitize_digest_types($value) {
		if (!is_array($value)) {
			return array();
		}
		$eligible = array_keys(AIPS_Notification_Registry::get_digest_eligible_types());
		$sanitized = array();
		foreach ($value as $type) {
			$type = sanitize_key($type);
			if (in_array($type, $eligible, true)) {
				$sanitized[] = $type;
			}
		}
		return array_values(array_unique($sanitized));
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

	public function ai_assistance_overwrite_mode_field_callback() {
		$value = AIPS_Settings::sanitize_ai_assistance_overwrite_mode( AIPS_Config::get_instance()->get_option( 'aips_ai_assistance_overwrite_mode' ) );
		?>
		<label>
			<input type="radio" name="aips_ai_assistance_overwrite_mode" value="replace" <?php checked( $value, 'replace' ); ?>>
			<?php esc_html_e( 'Replace automatically', 'ai-post-scheduler' ); ?>
		</label>
		<br>
		<label>
			<input type="radio" name="aips_ai_assistance_overwrite_mode" value="preview" <?php checked( $value, 'preview' ); ?>>
			<?php esc_html_e( 'Show preview and apply choice', 'ai-post-scheduler' ); ?>
		</label>
		<?php
	}

    /**
     * Render the description for the site content strategy settings section.
     *
     * @return void
     */

    /**
     * Render the Site Niche / Primary Topic field.
     *
     * @return void
     */

    /**
     * Render the Target Audience field.
     *
     * @return void
     */

    /**
     * Render the Content Goals field.
     *
     * @return void
     */

    /**
     * Render the Default Article Structure field.
     *
     * @return void
     */

    /**
     * Render the Brand Voice / Tone field.
     *
     * @return void
     */

    /**
     * Render the Content Language field.
     *
     * @return void
     */

    /**
     * Render the Content Guidelines field.
     *
     * @return void
     */

    /**
     * Render the Excluded Topics (site-wide) field.
     *
     * @return void
     */

    /**
     * Render the description for the Cache settings section.
     *
     * @return void
     */

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

    /**
     * Render the Default TTL field.
     *
     * @return void
     */

    /**
     * Render the DB Cache Key Prefix field.
     *
     * @return void
     */

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

	/**
	 * Render the embeddings provider selection field (Card 3).
	 *
	 * @return void
	 */

	/**
	 * Render the embeddings model field (Card 3).
	 *
	 * @return void
	 */

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

	/**
	 * Render persistent vector caching toggle (Card 3).
	 *
	 * @return void
	 */

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

	/**
	 * Render auto-index on publish toggle (Card 4).
	 *
	 * @return void
	 */

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

	/**
	 * Render the link index toggle and post types (Internal Linking tab).
	 *
	 * @return void
	 */

	/**
	 * Render the keyword link rules toggle and per-post cap (Internal Linking tab).
	 *
	 * @return void
	 */

	/**
	 * Render the generation-time linking mode (Internal Linking tab).
	 *
	 * @return void
	 */

	/**
	 * Render the link click tracking toggle and retention (Internal Linking tab).
	 *
	 * @return void
	 */

	/**
	 * Render the silo "In this guide" list settings (Internal Linking tab).
	 *
	 * @return void
	 */

	/**
	 * Render the link index rebuild throttle (Internal Linking tab).
	 *
	 * @return void
	 */

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

	/**
	 * Render Rate Limit Error Pattern Auto-Cooldown fields (Card 4).
	 *
	 * @return void
	 */

	/**
	 * Render Post Cluster similarity threshold setting (Card 4).
	 *
	 * @return void
	 */

	/**
	 * Render the cluster detection safety limits (Card 4).
	 *
	 * @return void
	 */

	/**
	 * Render Topic Cluster defaults (Card 4).
	 *
	 * @return void
	 */

	/**
	 * Render Enable Post Insights UI setting (Card 4).
	 *
	 * @return void
	 */

	/**
	 * Render Run Embeddings when Topics are Generated setting.
	 *
	 * @return void
	 */

	/**
	 * Render Global Author Topic Auto-Approval Mode.
	 *
	 * @return void
	 */

	/**
	 * Render Global Author Topic Minimum Quality Score.
	 *
	 * @return void
	 */

	/**
	 * Render Global Author Topic Minimum Niche Relevance %.
	 *
	 * @return void
	 */

	/**
	 * Render Global Author Topic Maximum Duplicate Ceiling.
	 *
	 * @return void
	 */

	/**
	 * Render Global Author Topic Sub-Threshold Fallback Handling.
	 *
	 * @return void
	 */

	/**
	 * Render related posts enable toggle (Card 5).
	 *
	 * @return void
	 */

	/**
	 * Render related posts auto-append toggle (Card 5).
	 *
	 * @return void
	 */

	/**
	 * Render related posts heading title (Card 5).
	 *
	 * @return void
	 */

	/**
	 * Render related posts count and layout options (Card 5).
	 *
	 * @return void
	 */

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

	/**
	 * Render deduplication similarity threshold number input (Card 6).
	 *
	 * @return void
	 */

	/**
	 * Render the duplicate review similarity threshold (Card 6).
	 *
	 * @return void
	 */

	/**
	 * Render the duplicate group size and pair limits (Card 6).
	 *
	 * @return void
	 */

	/**
	 * Render the keep-post recommendation strategy (Card 6).
	 *
	 * @return void
	 */

	/**
	 * Render the inbound-link protection threshold (Card 6).
	 *
	 * @return void
	 */

	/**
	 * Render the duplicate review exclusions (Card 6).
	 *
	 * @return void
	 */

	/**
	 * Render the default merge handling select (Card 6).
	 *
	 * @return void
	 */

	/**
	 * Render the scheduled duplicate scan select (Card 6).
	 *
	 * @return void
	 */

	/**
	 * @param mixed $value Submitted value.
	 * @return int
	 */

	public function sanitize_cluster_detection_max_posts($value) {
		return $this->clamp_int($value, 50, 5000, 250);
	}

	/**
	 * @param mixed $value Submitted value.
	 * @return int
	 */

	public function sanitize_cluster_detection_time_budget($value) {
		return $this->clamp_int($value, 2, 25, 8);
	}

	/**
	 * @param mixed $value Submitted value.
	 * @return float
	 */

	public function sanitize_duplicate_review_threshold($value) {
		$value = ($value === '' || $value === null) ? 0.88 : (float) $value;
		return max(0.70, min(0.99, $value));
	}

	/**
	 * @param mixed $value Submitted value.
	 * @return int
	 */

	public function sanitize_duplicate_review_max_group_size($value) {
		return $this->clamp_int($value, 2, 10, 5);
	}

	/**
	 * @param mixed $value Submitted value.
	 * @return int
	 */

	public function sanitize_duplicate_review_max_pairs($value) {
		return $this->clamp_int($value, 100, 5000, 1000);
	}

	/**
	 * @param mixed $value Submitted value.
	 * @return string[]
	 */

	public function sanitize_duplicate_excluded_post_types($value) {
		$valid = array_keys(get_post_types(array('public' => true)));
		return array_values(array_intersect(array_map('sanitize_key', (array) $value), $valid));
	}

	/**
	 * @param mixed $value Submitted value.
	 * @return int[]
	 */

	public function sanitize_duplicate_excluded_categories($value) {
		return array_values(array_unique(array_filter(array_map('absint', (array) $value))));
	}

	/**
	 * Keep only digits and commas, normalised to "1, 2, 3".
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */

	public function sanitize_duplicate_excluded_post_ids($value) {
		$ids = array_unique(array_filter(array_map('absint', preg_split('/[^0-9]+/', (string) $value))));
		return implode(', ', $ids);
	}

	/**
	 * @param mixed $value Submitted value.
	 * @return int
	 */

	public function sanitize_duplicate_protect_inbound_links($value) {
		return $this->clamp_int($value, 0, 1000, 10);
	}

	/**
	 * @param mixed $value Submitted value.
	 * @return string
	 */

	public function sanitize_duplicate_default_merge_mode($value) {
		$value = sanitize_key((string) $value);
		return in_array($value, array('none', 'revision', 'rewrite'), true) ? $value : 'revision';
	}

	/**
	 * @param mixed $value Submitted value.
	 * @return string
	 */

	public function sanitize_duplicate_scan_schedule($value) {
		$value = sanitize_key((string) $value);
		return in_array($value, array('off', 'daily', 'weekly'), true) ? $value : 'off';
	}

	/**
	 * @param mixed $value Submitted value.
	 * @return int
	 */

	public function sanitize_cluster_default_min_size($value) {
		return $this->clamp_int($value, 2, 50, 3);
	}

	/**
	 * @param mixed $value Submitted value.
	 * @return string
	 */

	public function sanitize_cluster_tightness($value) {
		$value = sanitize_key((string) $value);
		return in_array($value, array('loose', 'balanced', 'strict'), true) ? $value : 'balanced';
	}

	/**
	 * @param mixed $value Submitted value.
	 * @return float
	 */

	public function sanitize_pillar_spoke_similarity($value) {
		$value = ($value === '' || $value === null) ? 0.75 : (float) $value;
		return max(0.30, min(1.0, $value));
	}

	/**
	 * @param mixed $value Submitted value.
	 * @return int
	 */

	public function sanitize_audit_result_limit($value) {
		return $this->clamp_int($value, 10, 500, 50);
	}

	/**
	 * @param mixed $value Submitted value.
	 * @return string
	 */

	public function sanitize_duplicate_keep_strategy($value) {
		$value = sanitize_key((string) $value);
		return array_key_exists($value, AIPS_Duplicate_Group_Service::get_keep_strategies()) ? $value : 'balanced';
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
	 * @return string ('manual', 'all', 'score', 'similarity').
	 */

	public function sanitize_author_topic_auto_approval_mode($value) {
		return AIPS_Author_Topic_Approval_Policy::normalize_mode($value);
	}

	/**
	 * Sanitize author topic auto approval fallback.
	 *
	 * @param mixed $value Submitted value.
	 * @return string ('pending', 'rejected', 'smart_split').
	 */

	public function sanitize_author_topic_auto_approval_fallback($value) {
		return AIPS_Author_Topic_Approval_Policy::normalize_fallback($value);
	}

	/**
	 * Sanitize an auto-approval threshold expressed as an integer 1-100.
	 *
	 * @param mixed $value Submitted value.
	 * @return int
	 */

	public function sanitize_author_topic_auto_approval_percent($value) {
		return max(1, min(100, absint($value)));
	}

	/**
	 * Sanitize the maximum duplicate similarity (0.50-0.99).
	 *
	 * @param mixed $value Submitted value.
	 * @return float
	 */

	public function sanitize_author_topic_auto_approval_max_similarity($value) {
		return max(0.50, min(0.99, (float) $value));
	}

	/**
	 * Render description for the Database & Retention section.
	 *
	 * @return void
	 */

	/**
	 * Render the automated pruning master toggle.
	 *
	 * @return void
	 */

	/**
	 * Render the telemetry retention period field (number + unit).
	 *
	 * @return void
	 */

	/**
	 * Render the prune frequency selector.
	 *
	 * @return void
	 */

	/**
	 * Render the history log retention field (number + unit).
	 *
	 * @return void
	 */

	/**
	 * Render inputs for AI Error Log retention threshold.
	 *
	 * @return void
	 */

	/**
	 * Render checkboxes for cleaning orphaned embeddings and expired topics.
	 *
	 * @return void
	 */

	/**
	 * Sanitize retention unit selection.
	 *
	 * @param mixed $value Raw input value.
	 * @return string
	 */

	public function sanitize_retention_unit($value) {
		$value = sanitize_key((string) $value);
		return in_array($value, array('days', 'weeks', 'months'), true) ? $value : 'days';
	}

	/**
	 * Sanitize prune interval schedule.
	 *
	 * @param mixed $value Raw input value.
	 * @return string
	 */

	public function sanitize_prune_interval($value) {
		$value = sanitize_key((string) $value);
		return in_array($value, array('daily', 'twicedaily', 'weekly'), true) ? $value : 'daily';
	}

}
