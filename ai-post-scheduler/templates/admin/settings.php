<?php
if (!defined('ABSPATH')) {
	exit;
}

$active_tab  = !empty($aips_hub_subtab) ? $aips_hub_subtab : 'settings-general';
?>

		<!-- Plugin Settings Form -->
		<div class="aips-content-panel">
			<div class="aips-panel-header">
				<h2><?php esc_html_e('Plugin Configuration', 'ai-post-scheduler'); ?></h2>
			</div>
				<div class="aips-panel-body">

				<form method="post" action="options.php" id="aips-settings-form">
					<?php settings_fields('aips_settings'); ?>

					<!-- General Tab -->
					<div id="settings-general-tab" class="aips-tab-content<?php echo 'settings-general' === $active_tab ? ' active' : ''; ?>"<?php echo 'settings-general' === $active_tab ? '' : ' style="display:none;"'; ?>>
						<p class="description"><?php esc_html_e('Configure default settings for AI-generated posts.', 'ai-post-scheduler'); ?></p>
						<table class="form-table" role="presentation">
							<?php do_settings_fields('aips-settings', 'aips_general_section'); ?>
						</table>
						<p class="submit">
							<input type="submit" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'ai-post-scheduler'); ?>">
						</p>
					</div>

					<!-- AI Tab -->
					<div id="settings-ai-tab" class="aips-tab-content<?php echo 'settings-ai' === $active_tab ? ' active' : ''; ?>"<?php echo 'settings-ai' === $active_tab ? '' : ' style="display:none;"'; ?>>
						<p class="description"><?php esc_html_e('Configure the AI Engine model and environment used for content generation.', 'ai-post-scheduler'); ?></p>
						<table class="form-table" role="presentation">
							<?php do_settings_fields('aips-settings', 'aips_ai_section'); ?>
						</table>
						<p class="submit">
							<input type="submit" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'ai-post-scheduler'); ?>">
						</p>
					</div>

					<!-- Feedback Tab -->
					<div id="settings-feedback-tab" class="aips-tab-content<?php echo 'settings-feedback' === $active_tab ? ' active' : ''; ?>"<?php echo 'settings-feedback' === $active_tab ? '' : ' style="display:none;"'; ?>>
						<p class="description"><?php esc_html_e('Configure how the plugin evaluates and deduplicates generated topic suggestions.', 'ai-post-scheduler'); ?></p>
						<table class="form-table" role="presentation">
							<?php do_settings_fields('aips-settings', 'aips_feedback_section'); ?>
						</table>
						<p class="submit">
							<input type="submit" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'ai-post-scheduler'); ?>">
						</p>
					</div>

					<!-- Notifications Tab -->
					<div id="settings-notifications-tab" class="aips-tab-content<?php echo 'settings-notifications' === $active_tab ? ' active' : ''; ?>"<?php echo 'settings-notifications' === $active_tab ? '' : ' style="display:none;"'; ?>>
						<p class="description"><?php esc_html_e('Configure the notification email address and delivery channels for all plugin notifications.', 'ai-post-scheduler'); ?></p>
						<table class="form-table" role="presentation">
							<?php do_settings_fields('aips-settings', 'aips_notifications_section'); ?>
						</table>
						<p class="submit">
							<input type="submit" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'ai-post-scheduler'); ?>">
						</p>
					</div>

					<!-- Resilience & Limits Tab -->
					<div id="settings-resilience-tab" class="aips-tab-content<?php echo 'settings-resilience' === $active_tab ? ' active' : ''; ?>"<?php echo 'settings-resilience' === $active_tab ? '' : ' style="display:none;"'; ?>>
						<p class="description"><?php esc_html_e('Configure advanced resilience options to protect the application from failing and being blocked when external services return errors.', 'ai-post-scheduler'); ?></p>
						<table class="form-table" role="presentation">
							<?php do_settings_fields('aips-settings', 'aips_resilience_section'); ?>
						</table>
						<p class="submit">
							<input type="submit" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'ai-post-scheduler'); ?>">
						</p>
					</div>

					<!-- Content Strategy Tab -->
					<div id="settings-content-strategy-tab" class="aips-tab-content<?php echo 'settings-content-strategy' === $active_tab ? ' active' : ''; ?>"<?php echo 'settings-content-strategy' === $active_tab ? '' : ' style="display:none;"'; ?>>
						<p class="description"><?php esc_html_e('Define the overall content identity of your website. These settings are shared across Author Suggestions, topic generation, and post generation to ensure consistent, on-brand output.', 'ai-post-scheduler'); ?></p>
						<table class="form-table" role="presentation">
							<?php do_settings_fields('aips-settings', 'aips_content_strategy_section'); ?>
						</table>
						<p class="submit">
							<input type="submit" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'ai-post-scheduler'); ?>">
						</p>
					</div>

					<!-- Performance Tab -->
					<div id="settings-cache-tab" class="aips-tab-content<?php echo 'settings-cache' === $active_tab ? ' active' : ''; ?>"<?php echo 'settings-cache' === $active_tab ? '' : ' style="display:none;"'; ?>>
						<p class="description"><?php esc_html_e('Configure performance-related options for the plugin, including the internal cache layer used to speed up database reads, template processing, and scheduled operations.', 'ai-post-scheduler'); ?></p>

						<h3><?php esc_html_e('Cache System', 'ai-post-scheduler'); ?></h3>
						<table class="form-table" role="presentation" id="aips-cache-settings-table">
							<?php do_settings_fields('aips-settings', 'aips_cache_section'); ?>
						</table>
						<p class="submit">
							<input type="submit" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'ai-post-scheduler'); ?>">
						</p>
					</div>

					<!-- API Keys Tab -->
					<div id="settings-api-keys-tab" class="aips-tab-content<?php echo 'settings-api-keys' === $active_tab ? ' active' : ''; ?>"<?php echo 'settings-api-keys' === $active_tab ? '' : ' style="display:none;"'; ?>>
						<p class="description"><?php esc_html_e('Enter API keys for third-party services used by the plugin.', 'ai-post-scheduler'); ?></p>
						<table class="form-table" role="presentation">
							<?php do_settings_fields('aips-settings', 'aips_api_keys_section'); ?>
						</table>
						<p class="submit">
							<input type="submit" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'ai-post-scheduler'); ?>">
						</p>
					</div>

					<!-- Developers Tab -->
					<div id="settings-developers-tab" class="aips-tab-content<?php echo 'settings-developers' === $active_tab ? ' active' : ''; ?>"<?php echo 'settings-developers' === $active_tab ? '' : ' style="display:none;"'; ?>>
						<p class="description"><?php esc_html_e('Options for debugging and plugin development. Not recommended for production use.', 'ai-post-scheduler'); ?></p>
						<table class="form-table" role="presentation">
							<?php do_settings_fields('aips-settings', 'aips_developers_section'); ?>
						</table>
						<p class="submit">
							<input type="submit" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'ai-post-scheduler'); ?>">
						</p>
					</div>

				</form>
			</div>
		</div>

	</div>
</div>
