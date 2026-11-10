<?php
/**
 * Settings UI Presenter: AI Engine & Embeddings
 *
 * Handles rendering and sanitization for AI provider configs, connectors, models,
 * environment IDs, token limits, and vector embeddings settings.
 *
 * @package AI_Post_Scheduler
 * @since   3.7.19
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class AIPS_Settings_UI_AI
 */
class AIPS_Settings_UI_AI {

	/**
	 * Render the description for the AI settings section.
	 *
	 * @return void
	 */
	public function ai_section_callback() {
		echo '<p>' . esc_html__('Configure the AI Engine model and environment used for content generation.', 'ai-post-scheduler') . '</p>';
	}

	public function ai_provider_section_callback() {
		echo '<p class="description">' . esc_html__('Select which AI backend powers post generation.', 'ai-post-scheduler') . '</p>';
	}

	public function ai_tokens_section_callback() {
		echo '<p class="description">' . esc_html__('Configure token output limits and budgets for each post part.', 'ai-post-scheduler') . '</p>';
	}

	public function ai_embeddings_section_callback() {
		echo '<p class="description">' . esc_html__('Configure vector embeddings and semantic search models.', 'ai-post-scheduler') . '</p>';
	}

	public function ai_scope_section_callback() {
		echo '<p class="description">' . esc_html__('Configure indexing scope and rate limits for vector embeddings.', 'ai-post-scheduler') . '</p>';
	}

	/**
	 * Render the AI provider selection field.
	 *
	 * @return void
	 */
	public function ai_provider_field_callback() {
		$value     = (string) AIPS_Config::get_instance()->get_option('aips_ai_provider');
		$available = AIPS_AI_Provider_Factory::available_providers();
		$reasons   = AIPS_AI_Provider_Factory::unavailable_reasons();
		$all       = AIPS_AI_Provider_Factory::all_providers();
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
		$config       = AIPS_Config::get_instance();
		$mode         = (string) $config->get_option('aips_wp_ai_connector_mode');
		$selected     = (array) $config->get_option('aips_wp_ai_connector_ids');
		$failover     = (bool) $config->get_option('aips_wp_ai_connector_failover');
		$provider     = new AIPS_WP_AI_Client_Provider();
		$connectors   = $provider->get_active_ai_connectors();
		$ordered_ids  = array_values(array_unique(array_merge($selected, array_keys($connectors))));
		$settings_url = admin_url('options-general.php?page=connectors');
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
						$connector  = isset($connectors[$connector_id]) ? $connectors[$connector_id] : null;
						$name       = is_array($connector) && !empty($connector['name']) ? (string) $connector['name'] : $connector_id;
						$health     = $provider->get_connector_health($connector_id);
						$cooling    = !empty($health['cooldown_until']) && (int) $health['cooldown_until'] > time();
						$approved   = $provider->get_connector_approval_status($connector_id);
						$configured = is_array($connector) && $provider->is_connector_configured($connector);
						$status     = !$configured
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
	 * Render conversational generation toggle.
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
	 * Sanitize token budget (minimum 1).
	 *
	 * @param mixed $value
	 * @return int
	 */
	public function sanitize_token_budget($value) {
		$int = absint($value);
		return max(1, $int);
	}

	/**
	 * Sanitize AI provider identifier.
	 *
	 * @param mixed $value
	 * @return string
	 */
	public function sanitize_ai_provider($value) {
		$value = sanitize_text_field((string) $value);
		if ($value === '') {
			return '';
		}

		$known = array_keys(AIPS_AI_Provider_Factory::all_providers());
		return in_array($value, $known, true) ? $value : '';
	}

	/**
	 * Sanitize the WordPress AI connector selection mode.
	 *
	 * @param mixed $value
	 * @return string
	 */
	public function sanitize_wp_ai_connector_mode($value) {
		$value = sanitize_key((string) $value);
		return in_array($value, array('all', 'selected'), true) ? $value : 'all';
	}

	/**
	 * Sanitize an ordered list of WordPress AI connector IDs.
	 *
	 * @param mixed $value
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
	 * Sanitize embeddings scope.
	 *
	 * @param mixed $value
	 * @return string
	 */
	public function sanitize_embeddings_scope($value) {
		$value = sanitize_key((string) $value);
		return in_array($value, array('aips_only', 'date_range', 'all'), true) ? $value : 'aips_only';
	}
}
