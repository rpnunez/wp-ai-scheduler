<?php
if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="wrap aips-wrap">
	<div class="aips-page-container">
		<!-- Page Header -->
		<div class="aips-page-header">
			<div class="aips-page-header-top">
				<div>
					<h1 class="aips-page-title"><?php esc_html_e('Settings', 'ai-post-scheduler'); ?></h1>
					<p class="aips-page-description"><?php esc_html_e('Configure plugin settings, check system status, and manage AI Engine connection.', 'ai-post-scheduler'); ?></p>
				</div>
			</div>
		</div>

		<!-- Plugin Settings Form -->
		<div class="aips-content-panel">
			<div class="aips-panel-header">
				<h2><?php esc_html_e('Plugin Configuration', 'ai-post-scheduler'); ?></h2>
			</div>
			<div class="aips-panel-body">

				<!-- Tab Navigation -->
				<div class="aips-tab-nav" id="aips-settings-tab-nav">
					<button type="button" class="aips-tab-link active" data-tab="settings-general"><?php esc_html_e('General', 'ai-post-scheduler'); ?></button>
					<button type="button" class="aips-tab-link" data-tab="settings-ai"><?php esc_html_e('AI', 'ai-post-scheduler'); ?></button>
					<button type="button" class="aips-tab-link" data-tab="settings-feedback"><?php esc_html_e('Feedback', 'ai-post-scheduler'); ?></button>
					<button type="button" class="aips-tab-link" data-tab="settings-notifications"><?php esc_html_e('Notifications', 'ai-post-scheduler'); ?></button>
					<button type="button" class="aips-tab-link" data-tab="settings-resilience"><?php esc_html_e('Resilience &amp; Limits', 'ai-post-scheduler'); ?></button>
					<button type="button" class="aips-tab-link" data-tab="settings-content-strategy"><?php esc_html_e('Content Strategy', 'ai-post-scheduler'); ?></button>
					<button type="button" class="aips-tab-link" data-tab="settings-api-keys"><?php esc_html_e('API Keys', 'ai-post-scheduler'); ?></button>
					<button type="button" class="aips-tab-link" data-tab="settings-developers"><?php esc_html_e('Developers', 'ai-post-scheduler'); ?></button>
				</div>

				<form method="post" action="options.php" id="aips-settings-form">
					<?php settings_fields('aips_settings'); ?>

					<!-- General Tab -->
					<div id="settings-general-tab" class="aips-tab-content">
						<p class="description"><?php esc_html_e('Configure default settings for AI-generated posts.', 'ai-post-scheduler'); ?></p>
						<table class="form-table" role="presentation">
							<?php do_settings_fields('aips-settings', 'aips_general_section'); ?>
						</table>
						<p class="submit">
							<input type="submit" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'ai-post-scheduler'); ?>">
						</p>
					</div>

					<!-- AI Tab -->
					<div id="settings-ai-tab" class="aips-tab-content" style="display:none;">
						<p class="description"><?php esc_html_e('Configure the AI Engine model and environment used for content generation.', 'ai-post-scheduler'); ?></p>
						<table class="form-table" role="presentation">
							<?php do_settings_fields('aips-settings', 'aips_ai_section'); ?>
						</table>
						<p class="submit">
							<input type="submit" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'ai-post-scheduler'); ?>">
						</p>
					</div>

					<!-- Feedback Tab -->
					<div id="settings-feedback-tab" class="aips-tab-content" style="display:none;">
						<p class="description"><?php esc_html_e('Configure how the plugin evaluates and deduplicates generated topic suggestions.', 'ai-post-scheduler'); ?></p>
						<table class="form-table" role="presentation">
							<?php do_settings_fields('aips-settings', 'aips_feedback_section'); ?>
						</table>
						<p class="submit">
							<input type="submit" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'ai-post-scheduler'); ?>">
						</p>
					</div>

					<!-- Notifications Tab -->
					<div id="settings-notifications-tab" class="aips-tab-content" style="display:none;">
						<p class="description"><?php esc_html_e('Configure the notification email address and delivery channels for all plugin notifications.', 'ai-post-scheduler'); ?></p>
						<table class="form-table" role="presentation">
							<?php do_settings_fields('aips-settings', 'aips_notifications_section'); ?>
						</table>
						<p class="submit">
							<input type="submit" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'ai-post-scheduler'); ?>">
						</p>
					</div>

					<!-- Resilience & Limits Tab -->
					<div id="settings-resilience-tab" class="aips-tab-content" style="display:none;">
						<p class="description"><?php esc_html_e('Configure advanced resilience options to protect the application from failing and being blocked when external services return errors.', 'ai-post-scheduler'); ?></p>
						<table class="form-table" role="presentation">
							<?php do_settings_fields('aips-settings', 'aips_resilience_section'); ?>
						</table>
						<p class="submit">
							<input type="submit" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'ai-post-scheduler'); ?>">
						</p>
					</div>

					<!-- Content Strategy Tab -->
					<div id="settings-content-strategy-tab" class="aips-tab-content" style="display:none;">
						<p class="description"><?php esc_html_e('Define the overall content identity of your website. These settings are shared across Author Suggestions, topic generation, and post generation to ensure consistent, on-brand output.', 'ai-post-scheduler'); ?></p>
						<table class="form-table" role="presentation">
							<?php do_settings_fields('aips-settings', 'aips_content_strategy_section'); ?>
						</table>
						<p class="submit">
							<input type="submit" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'ai-post-scheduler'); ?>">
						</p>
					</div>

					<!-- API Keys Tab -->
					<div id="settings-api-keys-tab" class="aips-tab-content" style="display:none;">
						<p class="description"><?php esc_html_e('Enter API keys for third-party services used by the plugin.', 'ai-post-scheduler'); ?></p>
						<table class="form-table" role="presentation">
							<?php do_settings_fields('aips-settings', 'aips_api_keys_section'); ?>
						</table>
						<p class="submit">
							<input type="submit" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'ai-post-scheduler'); ?>">
						</p>
					</div>

					<!-- Developers Tab -->
					<div id="settings-developers-tab" class="aips-tab-content" style="display:none;">
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

		<!-- Cron Status -->
		<div class="aips-content-panel" style="margin-top: 20px;">
			<div class="aips-panel-header">
				<h2>
					<span class="dashicons dashicons-clock" style="margin-right: 5px;"></span>
					<?php esc_html_e('Cron Status', 'ai-post-scheduler'); ?>
				</h2>
			</div>
			<div class="aips-panel-body">
				<?php
				$next_scheduled = wp_next_scheduled('aips_generate_scheduled_posts');
				if ($next_scheduled) : ?>
					<p class="aips-status-message aips-status-success">
						<span class="aips-badge aips-badge-success">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e('Active', 'ai-post-scheduler'); ?>
						</span>
						<?php
						printf(
							esc_html__('Next scheduled check: %s', 'ai-post-scheduler'),
							'<strong>' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_scheduled)) . '</strong>'
						);
						?>
					</p>
				<?php else : ?>
					<p class="aips-status-message aips-status-error">
						<span class="aips-badge aips-badge-warning">
							<span class="dashicons dashicons-warning"></span>
							<?php esc_html_e('Inactive', 'ai-post-scheduler'); ?>
						</span>
						<?php esc_html_e('Cron job is not scheduled. Try deactivating and reactivating the plugin.', 'ai-post-scheduler'); ?>
					</p>
				<?php endif; ?>
			</div>
		</div>

		<!-- AI Engine Status -->
		<div class="aips-content-panel" style="margin-top: 20px;">
			<div class="aips-panel-header">
				<h2>
					<span class="dashicons dashicons-admin-plugins" style="margin-right: 5px;"></span>
					<?php esc_html_e('AI Engine Status', 'ai-post-scheduler'); ?>
				</h2>
			</div>
			<div class="aips-panel-body">
				<?php if (class_exists('Meow_MWAI_Core')): ?>
					<p class="aips-status-message aips-status-success">
						<span class="aips-badge aips-badge-success">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e('Connected', 'ai-post-scheduler'); ?>
						</span>
						<?php esc_html_e('AI Engine is installed and active.', 'ai-post-scheduler'); ?>
					</p>
					<div class="aips-test-connection-wrapper" style="margin-top: 15px;">
						<button type="button" id="aips-test-connection" class="aips-btn aips-btn-secondary">
							<span class="dashicons dashicons-update"></span>
							<?php esc_html_e('Test Connection', 'ai-post-scheduler'); ?>
						</button>
						<span class="spinner" style="float: none;"></span>
						<span id="aips-connection-result" class="aips-connection-result"></span>
					</div>
				<?php else: ?>
					<p class="aips-status-message aips-status-error">
						<span class="aips-badge aips-badge-error">
							<span class="dashicons dashicons-dismiss"></span>
							<?php esc_html_e('Not Found', 'ai-post-scheduler'); ?>
						</span>
						<?php esc_html_e('AI Engine is not installed or not activated. Please install and activate the AI Engine plugin.', 'ai-post-scheduler'); ?>
					</p>
					<p style="margin-top: 10px;">
						<a href="https://wordpress.org/plugins/ai-engine/" target="_blank" rel="noopener" class="aips-btn aips-btn-primary">
							<span class="dashicons dashicons-download"></span>
							<?php esc_html_e('Download AI Engine', 'ai-post-scheduler'); ?>
						</a>
					</p>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
