<?php
/**
 * Multisite Network Settings Template
 *
 * @package AI_Post_Scheduler
 * @since 3.8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

/** @var array $settings Loaded in AIPS_Network_Admin_Menu */
?>
<div class="wrap aips-wrap">
	<h1><?php esc_html_e('AI Post Scheduler - Network Settings', 'ai-post-scheduler'); ?></h1>
	<p class="description">
		<?php esc_html_e('Configure network-wide policies, shared AI credentials, quota enforcement, and emergency kill-switches for all sub-sites.', 'ai-post-scheduler'); ?>
	</p>

	<form method="post" action="">
		<?php wp_nonce_field('aips_network_settings_save', 'aips_network_settings_nonce'); ?>

		<h2><?php esc_html_e('Network Governance & Policies', 'ai-post-scheduler'); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e('Emergency Kill-Switch', 'ai-post-scheduler'); ?></th>
				<td>
					<label for="prevent_ai_generation">
						<input name="prevent_ai_generation" type="checkbox" id="prevent_ai_generation" value="1" <?php checked($settings['prevent_ai_generation']); ?>>
						<strong style="color: #d63638;"><?php esc_html_e('Pause all AI generation network-wide immediately', 'ai-post-scheduler'); ?></strong>
					</label>
					<p class="description">
						<?php esc_html_e('When enabled, all sub-site scheduled and manual AI generation runs are blocked before calling external APIs.', 'ai-post-scheduler'); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e('Sub-Site AI Configuration', 'ai-post-scheduler'); ?></th>
				<td>
					<fieldset>
						<label for="allow_site_ai_override">
							<input name="allow_site_ai_override" type="checkbox" id="allow_site_ai_override" value="1" <?php checked($settings['allow_site_ai_override']); ?>>
							<?php esc_html_e('Allow sub-site administrators to override network AI settings and use their own API keys', 'ai-post-scheduler'); ?>
						</label>
						<br>
						<label for="share_network_ai_credentials">
							<input name="share_network_ai_credentials" type="checkbox" id="share_network_ai_credentials" value="1" <?php checked($settings['share_network_ai_credentials']); ?>>
							<?php esc_html_e('Share main site AI Engine / WP AI Client credentials with sub-sites that do not have custom keys configured', 'ai-post-scheduler'); ?>
						</label>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e('Database & Repair Tools', 'ai-post-scheduler'); ?></th>
				<td>
					<label for="restrict_db_tools">
						<input name="restrict_db_tools" type="checkbox" id="restrict_db_tools" value="1" <?php checked($settings['restrict_db_tools']); ?>>
						<?php esc_html_e('Restrict database wipe, repair, reinstall, and cron flush tools to Super Administrators', 'ai-post-scheduler'); ?>
					</label>
					<p class="description">
						<?php esc_html_e('Prevents individual sub-site administrators from running destructive database resets or truncating logs.', 'ai-post-scheduler'); ?>
					</p>
				</td>
			</tr>
		</table>

		<hr>

		<h2><?php esc_html_e('Network AI Defaults (Shared Model & Provider)', 'ai-post-scheduler'); ?></h2>
		<p class="description">
			<?php esc_html_e('These settings provide default values for sub-sites when credential sharing is active or sub-site overrides are disabled.', 'ai-post-scheduler'); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="ai_provider"><?php esc_html_e('Default AI Provider', 'ai-post-scheduler'); ?></label></th>
				<td>
					<select name="ai_provider" id="ai_provider" class="regular-text">
						<option value="" <?php selected($settings['ai_provider'], ''); ?>><?php esc_html_e('-- Defer to Sub-Site / Default --', 'ai-post-scheduler'); ?></option>
						<option value="openai" <?php selected($settings['ai_provider'], 'openai'); ?>>OpenAI</option>
						<option value="anthropic" <?php selected($settings['ai_provider'], 'anthropic'); ?>>Anthropic</option>
						<option value="google" <?php selected($settings['ai_provider'], 'google'); ?>>Google Gemini</option>
						<option value="openrouter" <?php selected($settings['ai_provider'], 'openrouter'); ?>>OpenRouter</option>
						<option value="wp_ai_client" <?php selected($settings['ai_provider'], 'wp_ai_client'); ?>>WordPress 7.0+ AI Client</option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ai_model"><?php esc_html_e('Default Model', 'ai-post-scheduler'); ?></label></th>
				<td>
					<input name="ai_model" type="text" id="ai_model" value="<?php echo esc_attr($settings['ai_model']); ?>" class="regular-text" placeholder="e.g. gpt-4o, claude-3-5-sonnet-20241022">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ai_env_id"><?php esc_html_e('AI Engine Environment ID', 'ai-post-scheduler'); ?></label></th>
				<td>
					<input name="ai_env_id" type="text" id="ai_env_id" value="<?php echo esc_attr($settings['ai_env_id']); ?>" class="regular-text" placeholder="<?php esc_attr_e('Optional AI Engine Env ID', 'ai-post-scheduler'); ?>">
					<p class="description"><?php esc_html_e('Shared Meow Apps AI Engine environment ID from the main site.', 'ai-post-scheduler'); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="temperature"><?php esc_html_e('Default Temperature', 'ai-post-scheduler'); ?></label></th>
				<td>
					<input name="temperature" type="number" step="0.1" min="0" max="2" id="temperature" value="<?php echo esc_attr((string) $settings['temperature']); ?>" class="small-text">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="max_tokens_limit"><?php esc_html_e('Max Tokens Cap', 'ai-post-scheduler'); ?></label></th>
				<td>
					<input name="max_tokens_limit" type="number" step="100" min="500" max="128000" id="max_tokens_limit" value="<?php echo esc_attr((string) $settings['max_tokens_limit']); ?>" class="small-text">
					<p class="description"><?php esc_html_e('Upper limit on generation tokens enforced across all sites.', 'ai-post-scheduler'); ?></p>
				</td>
			</tr>
		</table>

		<hr>

		<h2><?php esc_html_e('Per-Site Quotas & Protections', 'ai-post-scheduler'); ?></h2>
		<p class="description">
			<?php esc_html_e('Protect shared API accounts against runaway spending and runaway cron jobs across large networks.', 'ai-post-scheduler'); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="daily_quota_per_site"><?php esc_html_e('Daily Quota Per Site', 'ai-post-scheduler'); ?></label></th>
				<td>
					<input name="daily_quota_per_site" type="number" min="0" id="daily_quota_per_site" value="<?php echo esc_attr((string) $settings['daily_quota_per_site']); ?>" class="small-text">
					<span class="description"><?php esc_html_e('AI calls / day (0 = unlimited). Applies to sites using shared network credentials.', 'ai-post-scheduler'); ?></span>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="monthly_quota_per_site"><?php esc_html_e('Monthly Quota Per Site', 'ai-post-scheduler'); ?></label></th>
				<td>
					<input name="monthly_quota_per_site" type="number" min="0" id="monthly_quota_per_site" value="<?php echo esc_attr((string) $settings['monthly_quota_per_site']); ?>" class="small-text">
					<span class="description"><?php esc_html_e('AI calls / month (0 = unlimited). Applies to sites using shared network credentials.', 'ai-post-scheduler'); ?></span>
				</td>
			</tr>
		</table>

		<?php submit_button(esc_html__('Save Network Settings', 'ai-post-scheduler'), 'primary', 'aips_network_settings_submit'); ?>
	</form>
</div>
