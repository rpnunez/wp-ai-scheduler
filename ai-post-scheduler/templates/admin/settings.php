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
		<form method="post" action="options.php" id="aips-settings-form">
			<?php settings_fields('aips_settings'); ?>

			<div class="aips-settings-layout">

				<!-- Sidebar Navigation -->
				<nav class="aips-settings-sidebar aips-tab-nav" id="aips-settings-tab-nav" aria-label="<?php esc_attr_e('Settings sections', 'ai-post-scheduler'); ?>">
					<button type="button" class="aips-tab-link aips-settings-nav-item active" data-tab="settings-general">
						<span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
						<?php esc_html_e('General', 'ai-post-scheduler'); ?>
					</button>
					<button type="button" class="aips-tab-link aips-settings-nav-item" data-tab="settings-ai">
						<span class="dashicons dashicons-superhero-alt" aria-hidden="true"></span>
						<?php esc_html_e('AI', 'ai-post-scheduler'); ?>
					</button>
					<button type="button" class="aips-tab-link aips-settings-nav-item" data-tab="settings-feedback">
						<span class="dashicons dashicons-star-half" aria-hidden="true"></span>
						<?php esc_html_e('Feedback', 'ai-post-scheduler'); ?>
					</button>
					<button type="button" class="aips-tab-link aips-settings-nav-item" data-tab="settings-notifications">
						<span class="dashicons dashicons-bell" aria-hidden="true"></span>
						<?php esc_html_e('Notifications', 'ai-post-scheduler'); ?>
					</button>
					<button type="button" class="aips-tab-link aips-settings-nav-item" data-tab="settings-resilience">
						<span class="dashicons dashicons-shield" aria-hidden="true"></span>
						<?php esc_html_e('Resilience &amp; Limits', 'ai-post-scheduler'); ?>
					</button>
					<button type="button" class="aips-tab-link aips-settings-nav-item" data-tab="settings-content-strategy">
						<span class="dashicons dashicons-editor-alignleft" aria-hidden="true"></span>
						<?php esc_html_e('Content Strategy', 'ai-post-scheduler'); ?>
					</button>
					<button type="button" class="aips-tab-link aips-settings-nav-item" data-tab="settings-cache">
						<span class="dashicons dashicons-performance" aria-hidden="true"></span>
						<?php esc_html_e('Performance', 'ai-post-scheduler'); ?>
					</button>
					<button type="button" class="aips-tab-link aips-settings-nav-item" data-tab="settings-api-keys">
						<span class="dashicons dashicons-lock" aria-hidden="true"></span>
						<?php esc_html_e('API Keys', 'ai-post-scheduler'); ?>
					</button>
					<button type="button" class="aips-tab-link aips-settings-nav-item" data-tab="settings-developers">
						<span class="dashicons dashicons-code-standards" aria-hidden="true"></span>
						<?php esc_html_e('Developers', 'ai-post-scheduler'); ?>
					</button>
				</nav>

				<!-- Settings Content -->
				<div class="aips-settings-content">

					<!-- General Tab -->
					<div id="settings-general-tab" class="aips-tab-content aips-settings-panel">
						<div class="aips-content-panel">
							<div class="aips-panel-header">
								<h2><?php esc_html_e('General', 'ai-post-scheduler'); ?></h2>
								<p class="aips-panel-description"><?php esc_html_e('Configure default settings for AI-generated posts.', 'ai-post-scheduler'); ?></p>
							</div>
							<div class="aips-panel-body">
								<table class="form-table aips-settings-table" role="presentation">
									<?php do_settings_fields('aips-settings', 'aips_general_section'); ?>
								</table>
								<div class="aips-settings-save-row">
									<button type="submit" class="aips-btn aips-btn-primary">
										<span class="dashicons dashicons-saved" aria-hidden="true"></span>
										<?php esc_html_e('Save Settings', 'ai-post-scheduler'); ?>
									</button>
								</div>
							</div>
						</div>
					</div>

					<!-- AI Tab -->
					<div id="settings-ai-tab" class="aips-tab-content aips-settings-panel" style="display:none;">
						<div class="aips-content-panel">
							<div class="aips-panel-header">
								<h2><?php esc_html_e('AI', 'ai-post-scheduler'); ?></h2>
								<p class="aips-panel-description"><?php esc_html_e('Configure the AI Engine model and environment used for content generation.', 'ai-post-scheduler'); ?></p>
							</div>
							<div class="aips-panel-body">
								<table class="form-table aips-settings-table" role="presentation">
									<?php do_settings_fields('aips-settings', 'aips_ai_section'); ?>
								</table>
								<div class="aips-settings-save-row">
									<button type="submit" class="aips-btn aips-btn-primary">
										<span class="dashicons dashicons-saved" aria-hidden="true"></span>
										<?php esc_html_e('Save Settings', 'ai-post-scheduler'); ?>
									</button>
								</div>
							</div>
						</div>
					</div>

					<!-- Feedback Tab -->
					<div id="settings-feedback-tab" class="aips-tab-content aips-settings-panel" style="display:none;">
						<div class="aips-content-panel">
							<div class="aips-panel-header">
								<h2><?php esc_html_e('Feedback', 'ai-post-scheduler'); ?></h2>
								<p class="aips-panel-description"><?php esc_html_e('Configure how the plugin evaluates and deduplicates generated topic suggestions.', 'ai-post-scheduler'); ?></p>
							</div>
							<div class="aips-panel-body">
								<table class="form-table aips-settings-table" role="presentation">
									<?php do_settings_fields('aips-settings', 'aips_feedback_section'); ?>
								</table>
								<div class="aips-settings-save-row">
									<button type="submit" class="aips-btn aips-btn-primary">
										<span class="dashicons dashicons-saved" aria-hidden="true"></span>
										<?php esc_html_e('Save Settings', 'ai-post-scheduler'); ?>
									</button>
								</div>
							</div>
						</div>
					</div>

					<!-- Notifications Tab -->
					<div id="settings-notifications-tab" class="aips-tab-content aips-settings-panel" style="display:none;">
						<div class="aips-content-panel">
							<div class="aips-panel-header">
								<h2><?php esc_html_e('Notifications', 'ai-post-scheduler'); ?></h2>
								<p class="aips-panel-description"><?php esc_html_e('Configure the notification email address and delivery channels for all plugin notifications.', 'ai-post-scheduler'); ?></p>
							</div>
							<div class="aips-panel-body">
								<table class="form-table aips-settings-table" role="presentation">
									<?php do_settings_fields('aips-settings', 'aips_notifications_section'); ?>
								</table>
								<div class="aips-settings-save-row">
									<button type="submit" class="aips-btn aips-btn-primary">
										<span class="dashicons dashicons-saved" aria-hidden="true"></span>
										<?php esc_html_e('Save Settings', 'ai-post-scheduler'); ?>
									</button>
								</div>
							</div>
						</div>
					</div>

					<!-- Resilience & Limits Tab -->
					<div id="settings-resilience-tab" class="aips-tab-content aips-settings-panel" style="display:none;">
						<div class="aips-content-panel">
							<div class="aips-panel-header">
								<h2><?php esc_html_e('Resilience &amp; Limits', 'ai-post-scheduler'); ?></h2>
								<p class="aips-panel-description"><?php esc_html_e('Configure advanced resilience options to protect the application from failing and being blocked when external services return errors.', 'ai-post-scheduler'); ?></p>
							</div>
							<div class="aips-panel-body">
								<table class="form-table aips-settings-table" role="presentation">
									<?php do_settings_fields('aips-settings', 'aips_resilience_section'); ?>
								</table>
								<div class="aips-settings-save-row">
									<button type="submit" class="aips-btn aips-btn-primary">
										<span class="dashicons dashicons-saved" aria-hidden="true"></span>
										<?php esc_html_e('Save Settings', 'ai-post-scheduler'); ?>
									</button>
								</div>
							</div>
						</div>
					</div>

					<!-- Content Strategy Tab -->
					<div id="settings-content-strategy-tab" class="aips-tab-content aips-settings-panel" style="display:none;">
						<div class="aips-content-panel">
							<div class="aips-panel-header">
								<h2><?php esc_html_e('Content Strategy', 'ai-post-scheduler'); ?></h2>
								<p class="aips-panel-description"><?php esc_html_e('Define the overall content identity of your website. These settings are shared across Author Suggestions, topic generation, and post generation to ensure consistent, on-brand output.', 'ai-post-scheduler'); ?></p>
							</div>
							<div class="aips-panel-body">
								<table class="form-table aips-settings-table" role="presentation">
									<?php do_settings_fields('aips-settings', 'aips_content_strategy_section'); ?>
								</table>
								<div class="aips-settings-save-row">
									<button type="submit" class="aips-btn aips-btn-primary">
										<span class="dashicons dashicons-saved" aria-hidden="true"></span>
										<?php esc_html_e('Save Settings', 'ai-post-scheduler'); ?>
									</button>
								</div>
							</div>
						</div>
					</div>

					<!-- Performance Tab -->
					<div id="settings-cache-tab" class="aips-tab-content aips-settings-panel" style="display:none;">
						<div class="aips-content-panel">
							<div class="aips-panel-header">
								<h2><?php esc_html_e('Performance', 'ai-post-scheduler'); ?></h2>
								<p class="aips-panel-description"><?php esc_html_e('Configure performance-related options for the plugin, including the internal cache layer used to speed up database reads, template processing, and scheduled operations.', 'ai-post-scheduler'); ?></p>
							</div>
							<div class="aips-panel-body">
								<h3><?php esc_html_e('Cache System', 'ai-post-scheduler'); ?></h3>
								<table class="form-table aips-settings-table" role="presentation" id="aips-cache-settings-table">
									<?php do_settings_fields('aips-settings', 'aips_cache_section'); ?>
								</table>
								<div class="aips-settings-save-row">
									<button type="submit" class="aips-btn aips-btn-primary">
										<span class="dashicons dashicons-saved" aria-hidden="true"></span>
										<?php esc_html_e('Save Settings', 'ai-post-scheduler'); ?>
									</button>
								</div>
							</div>
						</div>
					</div>

					<!-- API Keys Tab -->
					<div id="settings-api-keys-tab" class="aips-tab-content aips-settings-panel" style="display:none;">
						<div class="aips-content-panel">
							<div class="aips-panel-header">
								<h2><?php esc_html_e('API Keys', 'ai-post-scheduler'); ?></h2>
								<p class="aips-panel-description"><?php esc_html_e('Enter API keys for third-party services used by the plugin.', 'ai-post-scheduler'); ?></p>
							</div>
							<div class="aips-panel-body">
								<table class="form-table aips-settings-table" role="presentation">
									<?php do_settings_fields('aips-settings', 'aips_api_keys_section'); ?>
								</table>
								<div class="aips-settings-save-row">
									<button type="submit" class="aips-btn aips-btn-primary">
										<span class="dashicons dashicons-saved" aria-hidden="true"></span>
										<?php esc_html_e('Save Settings', 'ai-post-scheduler'); ?>
									</button>
								</div>
							</div>
						</div>
					</div>

					<!-- Developers Tab -->
					<div id="settings-developers-tab" class="aips-tab-content aips-settings-panel" style="display:none;">
						<div class="aips-content-panel">
							<div class="aips-panel-header">
								<h2><?php esc_html_e('Developers', 'ai-post-scheduler'); ?></h2>
								<p class="aips-panel-description"><?php esc_html_e('Options for debugging and plugin development. Not recommended for production use.', 'ai-post-scheduler'); ?></p>
							</div>
							<div class="aips-panel-body">
								<table class="form-table aips-settings-table" role="presentation">
									<?php do_settings_fields('aips-settings', 'aips_developers_section'); ?>
								</table>
								<div class="aips-settings-save-row">
									<button type="submit" class="aips-btn aips-btn-primary">
										<span class="dashicons dashicons-saved" aria-hidden="true"></span>
										<?php esc_html_e('Save Settings', 'ai-post-scheduler'); ?>
									</button>
								</div>
							</div>
						</div>
					</div>

				</div><!-- .aips-settings-content -->
			</div><!-- .aips-settings-layout -->

		</form>
	</div>
</div>
